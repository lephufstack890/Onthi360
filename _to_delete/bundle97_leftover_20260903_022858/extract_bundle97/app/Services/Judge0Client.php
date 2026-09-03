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
 * Dùng /submissions/batch?wait=true (đồng bộ) thay vì tạo submission rồi tự polling — đơn
 * giản hơn nhiều cho quy mô hiện tại (1 bài lập trình vài chục test case), đổi lại request có
 * thể chờ khá lâu nếu nhiều test case cùng lúc — xem timeout tự tính bên dưới. Khi quy mô lớn
 * hơn (nhiều học sinh chấm cùng lúc) mới cần đổi sang callback/hàng đợi thật (ENABLE_CALLBACKS
 * đã bật sẵn trong judge0.conf trên VPS, dành cho giai đoạn sau).
 */
class Judge0Client
{
    /**
     * Gửi 1 lô bài nộp, chờ chấm xong luôn (đồng bộ) — trả về mảng kết quả theo ĐÚNG thứ tự
     * đã gửi lên (Judge0 đảm bảo thứ tự trong response batch khớp thứ tự request).
     *
     * @param  array<int, array{source_code:string, language_id:int, stdin?:string, expected_output?:string, cpu_time_limit?:float, wall_time_limit?:float, memory_limit?:int}>  $submissions
     * @return array<int, array{stdout:?string, stderr:?string, compile_output:?string, status:array{id:int,description:string}, time:?string, memory:?int}>
     *
     * @throws RuntimeException nếu không gọi được máy chấm (mất mạng/đường hầm SSH đứt/Judge0
     *                           trả lỗi HTTP/timeout) — bên gọi (CodeJudgingService) PHẢI bắt
     *                           lỗi này để không làm hỏng luồng lưu/nộp bài của học sinh khi
     *                           Judge0 tạm thời không tới được.
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
        // batch được chấm TUẦN TỰ, không song song — timeout HTTP phải đủ cho CẢ LÔ, không
        // phải chỉ 1 bài, nếu không request sẽ bị cắt giữa chừng dù Judge0 vẫn đang chấm tiếp.
        $maxWallTime = max(array_map(static fn (array $s) => (float) ($s['wall_time_limit'] ?? 20), $submissions));
        $httpTimeout = (int) min(300, 30 + count($submissions) * ($maxWallTime + 3));

        try {
            $response = Http::baseUrl(rtrim((string) config('judge0.base_url'), '/'))
                ->withHeaders([(string) config('judge0.auth_header') => (string) config('judge0.auth_token')])
                ->timeout($httpTimeout)
                ->connectTimeout((int) config('judge0.connect_timeout'))
                ->post('/submissions/batch?base64_encoded=true&wait=true', ['submissions' => $encoded]);
        } catch (Throwable $e) {
            throw new RuntimeException('Không kết nối được máy chấm Judge0: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            throw new RuntimeException('Máy chấm Judge0 trả lỗi HTTP '.$response->status().': '.$response->body());
        }

        $results = $response->json();

        if (! is_array($results) || count($results) !== count($submissions)) {
            throw new RuntimeException('Máy chấm Judge0 trả dữ liệu không hợp lệ hoặc thiếu kết quả.');
        }

        return array_map(static fn (array $r) => [
            'stdout' => isset($r['stdout']) ? base64_decode((string) $r['stdout']) : null,
            'stderr' => isset($r['stderr']) ? base64_decode((string) $r['stderr']) : null,
            'compile_output' => isset($r['compile_output']) ? base64_decode((string) $r['compile_output']) : null,
            'status' => $r['status'] ?? ['id' => 13, 'description' => 'Internal Error'],
            'time' => $r['time'] ?? null,
            'memory' => $r['memory'] ?? null,
        ], $results);
    }
}
