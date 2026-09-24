<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class Judge0Client
{
    private const RESULT_FIELDS = 'token,status,stdout,stderr,compile_output,time,memory';

    private const POLL_INTERVAL_US = 500_000;

    /**
     * @param  array<int, array{source_code:string, language_id:int, stdin?:string, expected_output?:string, cpu_time_limit?:float, wall_time_limit?:float, memory_limit?:int}>  $submissions
     * @return array<int, array{stdout:?string, stderr:?string, compile_output:?string, status:array{id:int,description:string}, time:?string, memory:?int}>
     *
     * @throws RuntimeException 
     */
    /**
     * SỬA 24/9 (khách: "chấm lâu quá, muốn 3-6s/bài") — CHẤM GỘP: gửi ĐÚNG MỘT bài nộp kiểu
     * "Multi-file program" của Judge0 (một gói ZIP gồm mã nguồn + toàn bộ dữ liệu vào + 2
     * script compile/run), thay cho việc gửi mỗi test một bài nộp.
     *
     * Vì sao: đo thật trên máy chủ ngày 24/9 — 1 bài nộp mất 3,09 giây, trong đó CHẠY chương
     * trình chỉ 0,01 giây; toàn bộ phần còn lại là biên dịch (~2 giây với bits/stdc++.h) cộng
     * phụ phí dựng hộp cách ly (~1 giây). Nhân 20 test thành 28,9 giây. Gộp lại một bài nộp
     * thì biên dịch 1 lần, phụ phí 1 lần -> về đúng ~3 giây.
     *
     * Yêu cầu ENABLE_ADDITIONAL_FILES=true trong judge0.conf.
     *
     * @param  string  $zipBinary  Nội dung NHỊ PHÂN của gói ZIP (chưa base64 — hàm này tự mã hoá).
     * @return array{stdout:?string, stderr:?string, compile_output:?string, status:array{id:int,description:string}, time:?string, memory:?int}
     *
     * @throws RuntimeException khi không gọi được Judge0 hoặc chờ quá lâu.
     */
    public function runBundled(string $zipBinary, float $cpuTimeLimit, float $wallTimeLimit, int $memoryLimit): array
    {
        $languageId = (int) config('judge0.multifile_language_id');
        $connectTimeout = (int) config('judge0.connect_timeout');
        $perRequestTimeout = max(10, min(30, $connectTimeout + 20));

        // Chờ tối đa = đúng ngân sách đã cấp cho bài nộp, cộng biên an toàn cho lúc máy chấm
        // đang bận (bài nằm hàng đợi Redis chưa tới lượt).
        $totalWaitSeconds = (int) min(300, $wallTimeLimit + 30);
        @set_time_limit($totalWaitSeconds + $perRequestTimeout + 10);

        $baseUrl = rtrim((string) config('judge0.base_url'), '/');
        $headers = [(string) config('judge0.auth_header') => (string) config('judge0.auth_token')];

        $client = fn () => Http::baseUrl($baseUrl)->withHeaders($headers)
            ->timeout($perRequestTimeout)->connectTimeout($connectTimeout);

        try {
            $createResponse = $client()->post('/submissions?base64_encoded=true&wait=false', [
                // Ngôn ngữ 89 lấy mã nguồn từ trong gói ZIP; trường này chỉ để Judge0 khỏi từ chối.
                'source_code' => base64_encode(''),
                'language_id' => $languageId,
                'additional_files' => base64_encode($zipBinary),
                'cpu_time_limit' => $cpuTimeLimit,
                'wall_time_limit' => $wallTimeLimit,
                'memory_limit' => $memoryLimit,
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException('Không kết nối được máy chấm Judge0 (chấm gộp): '.$e->getMessage(), previous: $e);
        }

        if ($createResponse->failed()) {
            throw new RuntimeException('Máy chấm Judge0 trả lỗi HTTP '.$createResponse->status().' khi tạo bài nộp gộp: '.$createResponse->body());
        }

        $token = (string) ($createResponse->json('token') ?? '');

        if ($token === '') {
            throw new RuntimeException('Máy chấm Judge0 không trả về token cho bài nộp gộp.');
        }

        $deadlineAt = microtime(true) + $totalWaitSeconds;

        while (true) {
            try {
                $pollResponse = $client()->get('/submissions/'.$token, [
                    'base64_encoded' => 'true',
                    'fields' => self::RESULT_FIELDS,
                ]);
            } catch (Throwable $e) {
                throw new RuntimeException('Không kết nối được máy chấm Judge0 (khi chờ kết quả gộp): '.$e->getMessage(), previous: $e);
            }

            if ($pollResponse->failed()) {
                throw new RuntimeException('Máy chấm Judge0 trả lỗi HTTP '.$pollResponse->status().' khi chờ kết quả gộp: '.$pollResponse->body());
            }

            $r = (array) $pollResponse->json();
            $statusId = (int) ($r['status']['id'] ?? 1);

            // 1 = In Queue, 2 = Processing. Lớn hơn 2 là đã xong (dù đúng hay sai).
            if ($statusId > 2) {
                return [
                    'stdout' => isset($r['stdout']) ? base64_decode((string) $r['stdout']) : null,
                    'stderr' => isset($r['stderr']) ? base64_decode((string) $r['stderr']) : null,
                    'compile_output' => isset($r['compile_output']) ? base64_decode((string) $r['compile_output']) : null,
                    'status' => $r['status'] ?? ['id' => 13, 'description' => 'Internal Error'],
                    'time' => $r['time'] ?? null,
                    'memory' => $r['memory'] ?? null,
                ];
            }

            if (microtime(true) >= $deadlineAt) {
                throw new RuntimeException("Máy chấm Judge0 chấm quá lâu (gộp), chưa xong sau {$totalWaitSeconds}s.");
            }

            usleep(self::POLL_INTERVAL_US);
        }
    }

    public function runBatch(array $submissions): array
    {
        if ($submissions === []) {
            return [];
        }

        $encoded = array_map(function (array $s) {
            $payload = [
                'source_code' => base64_encode($s['source_code']),
                'language_id' => $s['language_id'],
                'stdin' => base64_encode($s['stdin'] ?? ''),
            ];

            if (isset($s['expected_output'])) {
                $payload['expected_output'] = base64_encode($s['expected_output']);
            }
            if (isset($s['cpu_time_limit'])) {
                $payload['cpu_time_limit'] = $s['cpu_time_limit'];
            }
            if (isset($s['wall_time_limit'])) {
                $payload['wall_time_limit'] = $s['wall_time_limit'];
            }
            if (isset($s['memory_limit'])) {
                $payload['memory_limit'] = $s['memory_limit'];
            }

            return $payload;
        }, $submissions);

        $maxWallTime = max(array_map(static fn (array $s) => (float) ($s['wall_time_limit'] ?? 20), $submissions));
        $totalWaitSeconds = (int) min(300, 30 + count($submissions) * ($maxWallTime + 3));
        $connectTimeout = (int) config('judge0.connect_timeout');
        $perRequestTimeout = max(10, min(30, $connectTimeout + 20));
        @set_time_limit($totalWaitSeconds + $perRequestTimeout + 10);

        $baseUrl = rtrim((string) config('judge0.base_url'), '/');
        $headers = [(string) config('judge0.auth_header') => (string) config('judge0.auth_token')];

        $client = fn () => Http::baseUrl($baseUrl)->withHeaders($headers)
            ->timeout($perRequestTimeout)->connectTimeout($connectTimeout);

        try {
            $createResponse = $client()->post('/submissions/batch?base64_encoded=true', ['submissions' => $encoded]);
        } catch (Throwable $e) {
            throw new RuntimeException('Không kết nối được máy chấm Judge0: '.$e->getMessage(), previous: $e);
        }

        if ($createResponse->failed()) {
            throw new RuntimeException('Máy chấm Judge0 trả lỗi HTTP '.$createResponse->status().' khi tạo batch: '.$createResponse->body());
        }

        $created = $createResponse->json();

        if (! is_array($created) || count($created) !== count($submissions)) {
            throw new RuntimeException('Máy chấm Judge0 trả dữ liệu không hợp lệ hoặc thiếu token khi tạo batch.');
        }

        $tokens = array_map(static fn ($c) => is_array($c) ? (string) ($c['token'] ?? '') : '', $created);

        if (in_array('', $tokens, true)) {
            throw new RuntimeException('Máy chấm Judge0 không trả về token cho ít nhất 1 bài nộp trong batch.');
        }

        $byToken = [];
        $deadlineAt = microtime(true) + $totalWaitSeconds;

        while (true) {
            try {
                $pollResponse = $client()->get('/submissions/batch', [
                    'tokens' => implode(',', $tokens),
                    'base64_encoded' => 'true',
                    'fields' => self::RESULT_FIELDS,
                ]);
            } catch (Throwable $e) {
                throw new RuntimeException('Không kết nối được máy chấm Judge0 (khi chờ kết quả batch): '.$e->getMessage(), previous: $e);
            }

            if ($pollResponse->failed()) {
                throw new RuntimeException('Máy chấm Judge0 trả lỗi HTTP '.$pollResponse->status().' khi chờ kết quả batch: '.$pollResponse->body());
            }

            foreach ((array) ($pollResponse->json('submissions') ?? []) as $item) {
                if (is_array($item) && isset($item['token'])) {
                    $byToken[(string) $item['token']] = $item;
                }
            }

            $allDone = count($byToken) === count($tokens) && ! in_array(false, array_map(
                static fn (string $t) => (int) ($byToken[$t]['status']['id'] ?? 1) > 2,
                $tokens
            ), true);

            if ($allDone) {
                break;
            }

            if (microtime(true) >= $deadlineAt) {
                throw new RuntimeException("Máy chấm Judge0 chấm quá lâu, chưa xong sau {$totalWaitSeconds}s.");
            }

            usleep(self::POLL_INTERVAL_US);
        }

        return array_map(function (string $token) use ($byToken) {
            $r = $byToken[$token];

            return [
                'stdout' => isset($r['stdout']) ? base64_decode((string) $r['stdout']) : null,
                'stderr' => isset($r['stderr']) ? base64_decode((string) $r['stderr']) : null,
                'compile_output' => isset($r['compile_output']) ? base64_decode((string) $r['compile_output']) : null,
                'status' => $r['status'] ?? ['id' => 13, 'description' => 'Internal Error'],
                'time' => $r['time'] ?? null,
                'memory' => $r['memory'] ?? null,
            ];
        }, $tokens);
    }
}
