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
