<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Cầu nối HTTP tới máy chấm Judge0 tự host (16 mục 1 — "Cầu nối tới judge/runner riêng",
 * xem migration database/migrations/2025_01_01_000250_create_judge_submissions_table.php).
 * Lớp DUY NHẤT trong toàn hệ thống được phép gọi thẳng ra Judge0 — mọi nơi cần chấm code đều
 * phải đi qua App\Services\CodeJudgingService (nơi biết cách build payload/ánh xạ verdict),
 * KHÔNG gọi thẳng lớp này.
 *
 * SỬA 3/9 (khách báo "Lỗi hệ thống chấm bài" cho C++ — điều tra qua curl trực tiếp trên VPS
 * mới phát hiện) — bản trước đây gọi POST /submissions/batch?wait=true rồi TIN LUÔN response
 * đã có đủ kết quả (giống hệt cách endpoint ĐƠN /submissions?wait=true hoạt động). Thực tế đã
 * kiểm chứng: Judge0 CE (v1.13.1) endpoint BATCH KHÔNG BAO GIỜ chờ chấm xong dù có truyền
 * wait=true hay không, kể cả thêm fields=* — POST .../batch luôn trả về NGAY [{"token":...}]
 * (chỉ token, không có status/stdout gì cả) rồi để worker chấm ngầm phía sau. Vì code cũ đọc
 * $r['status'] không thấy field này nên rơi vào fallback "Internal Error" → CodeJudgingService
 * map thành SystemError cho MỌI bài nộp qua batch (không riêng C++, chỉ là chưa ai từng thấy
 * vì đây là lần đầu test thật qua giao diện — 2 lần "print(1+1)" chạy được trước đó là gọi
 * thẳng endpoint ĐƠN /submissions, không qua app).
 *
 * Cách ĐÚNG (theo đúng thiết kế thật của Judge0 batch API, đã xác nhận qua GET thủ công):
 *   1) POST /submissions/batch?base64_encoded=true → chỉ nhận về mảng token theo ĐÚNG thứ tự
 *      đã gửi lên.
 *   2) GET /submissions/batch?tokens=<token1>,<token2>,...&base64_encoded=true&fields=...
 *      lặp lại (poll) tới khi TẤT CẢ bài trong lô có status.id > 2 (không còn "In Queue"(1)/
 *      "Processing"(2)) — response bọc trong khoá "submissions" (khác POST trả mảng trần).
 */
class Judge0Client
{
    /** Field cần lấy khi GET kết quả — chỉ xin đúng những gì CodeJudgingService cần, đỡ nặng payload. */
    private const RESULT_FIELDS = 'token,status,stdout,stderr,compile_output,time,memory';

    /** Nghỉ giữa 2 lần hỏi lại kết quả batch (micro-giây) — 0.5s, đủ nhanh mà không dí API liên tục. */
    private const POLL_INTERVAL_US = 500_000;

    /**
     * Gửi 1 lô bài nộp, CHỜ chấm xong (poll ngầm bên trong hàm, người gọi không cần biết) — trả
     * về mảng kết quả theo ĐÚNG thứ tự đã gửi lên.
     *
     * @param  array<int, array{source_code:string, language_id:int, stdin?:string, expected_output?:string, cpu_time_limit?:float, wall_time_limit?:float, memory_limit?:int}>  $submissions
     * @return array<int, array{stdout:?string, stderr:?string, compile_output:?string, status:array{id:int,description:string}, time:?string, memory:?int}>
     *
     * @throws RuntimeException nếu không gọi được máy chấm (mất mạng/đường hầm SSH đứt/Judge0
     *                           trả lỗi HTTP/timeout/chấm quá lâu chưa xong) — bên gọi
     *                           (CodeJudgingService) PHẢI bắt lỗi này để không làm hỏng luồng
     *                           lưu/nộp bài của học sinh khi Judge0 tạm thời không tới được.
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

        // COUNT=1 (1 worker) trong judge0.conf hiện tại trên VPS nghĩa là các bài nộp trong 1
        // batch được chấm TUẦN TỰ, không song song — tổng thời gian CHỜ (poll) phải đủ cho CẢ
        // LÔ, không phải chỉ 1 bài, nếu không sẽ báo lỗi "chấm quá lâu" dù Judge0 vẫn đang chấm.
        $maxWallTime = max(array_map(static fn (array $s) => (float) ($s['wall_time_limit'] ?? 20), $submissions));
        $totalWaitSeconds = (int) min(300, 30 + count($submissions) * ($maxWallTime + 3));
        $connectTimeout = (int) config('judge0.connect_timeout');
        // Timeout cho MỖI lần gọi HTTP riêng lẻ (không phải tổng thời gian chờ cả lô) — POST
        // tạo batch trả về gần như ngay lập tức (không chờ chấm), GET poll cũng vậy.
        $perRequestTimeout = max(10, min(30, $connectTimeout + 20));

        // SỬA 3/9 (khách báo lỗi thật trong storage/logs/laravel.log: "Maximum execution time
        // of 30 seconds exceeded" TẠI ĐÚNG dòng poll bên dưới) — trước khi sửa poll cho đúng,
        // Judge0Client trả về gần như ngay lập tức (bug cũ: không hề chờ chấm thật) nên chưa
        // bao giờ chạm giới hạn max_execution_time mặc định (thường 30s) của PHP. Giờ chờ chấm
        // THẬT (COUNT=1 worker trên VPS, nhiều test case chấm tuần tự + độ trễ mạng) có thể mất
        // hơn 30s — set_time_limit() RESET đồng hồ đếm ngược của tiến trình PHP HIỆN TẠI kể từ
        // lúc gọi (không cộng dồn vào thời gian đã chạy), nên gọi ngay trước khi bắt đầu là đủ.
        // An toàn khi chạy CLI/queue (vốn đã unlimited, gọi thêm không hại gì) — chỉ thật sự
        // cần thiết cho php-fpm/máy chủ dev tích hợp (`php artisan serve`, hiện khách đang dùng
        // để test cục bộ). Dùng @ vì 1 số môi trường chặn hàm này (disable_functions) — nếu bị
        // chặn thì coi như không đổi được gì, ít nhất không văng lỗi khác ra ngoài.
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

        // Trả về ĐÚNG THỨ TỰ đã gửi lên (dùng mảng $tokens theo thứ tự request ban đầu — response
        // GET không đảm bảo giữ nguyên thứ tự tokens đã truyền).
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
