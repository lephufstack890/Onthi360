<?php

namespace App\Services;

use App\Enums\VerdictStatus;
use RuntimeException;

/**
 * Chấm code học sinh nộp bằng máy chấm Judge0 thật (16 mục 1) — nơi DUY NHẤT trong hệ thống
 * biết cách build payload gửi Judge0Client và ánh xạ status Judge0 sang App\Enums\
 * VerdictStatus của onthi360. Dùng CHUNG cho cả 3 nơi có bài lập trình cần chấm:
 *   (1) bài lập trình con trong đề PDF chính thức — App\Services\PdfAttemptService,
 *       App\Models\AssessmentCodingItem/AttemptCodingItem (test case là FILE trên disk);
 *   (2) câu hỏi loại Coding trong đề cấu trúc chính thức — App\Services\AttemptService,
 *       App\Models\Question (test case là mảng ['input','output'] trong grading_config);
 *   (3) "Luyện tập theo câu" — Student\PracticeByQuestionService (chấm ngay, không lưu DB).
 * Cả 3 nơi tự đọc test case theo đúng hình dạng riêng của mình rồi chuẩn hoá về
 * array{input:string, expected_output:string} trước khi gọi judge() — lớp này không biết gì
 * về Model/disk, chỉ nhận chuỗi.
 *
 * Chỉ hỗ trợ đúng 2 ngôn ngữ toàn hệ thống đang cho phép (xem config/judge0.php
 * 'languages') — ngôn ngữ khác hoặc code rỗng trả thẳng CompileError, KHÔNG gọi ra Judge0.
 */
class CodeJudgingService
{
    public function __construct(private readonly Judge0Client $client) {}

    /**
     * @param  array<int, array{input: string, expected_output: string}>  $testCases
     * @return array{verdict: VerdictStatus, isAccepted: bool, details: array<int, array{status: string, time: ?string, memory: ?int, stderr: ?string, compileOutput: ?string}>}
     *
     * @throws RuntimeException nếu không gọi được máy chấm Judge0 (mất mạng/đường hầm SSH
     *                           đứt/timeout) — CẢ 3 nơi gọi hàm này PHẢI bắt lỗi này (try/catch)
     *                           để không làm hỏng luồng lưu/nộp bài của học sinh, và nên GIỮ
     *                           NGUYÊN verdict cũ (Queued) thay vì coi như chấm xong với điểm 0,
     *                           vì đây chỉ là Judge0 tạm thời không tới được, không phải bài sai.
     */
    public function judge(string $sourceCode, ?string $language, array $testCases, int $timeLimitMs, int $memoryLimitKb): array
    {
        if ($testCases === []) {
            // Câu hỏi/bài chưa có test case nào (dữ liệu thiếu) — không có gì để chấm, không
            // phải lỗi biên dịch của học sinh, nhưng cũng không thể là Accepted.
            return ['verdict' => VerdictStatus::SystemError, 'isAccepted' => false, 'details' => []];
        }

        $languageId = config("judge0.languages.{$language}");

        if ($languageId === null || trim($sourceCode) === '') {
            return ['verdict' => VerdictStatus::CompileError, 'isAccepted' => false, 'details' => []];
        }

        // Ép (clamp) giới hạn của riêng bài/câu hỏi này không vượt quá mức Judge0 cho phép
        // (xem ghi chú ở config/judge0.php) — tự bảo vệ thay vì tin Judge0 sẽ tự xử lý đúng.
        $cpuTimeLimit = min((float) config('judge0.max_cpu_time_limit'), max(1.0, $timeLimitMs / 1000));
        $wallTimeLimit = min((float) config('judge0.max_wall_time_limit'), $cpuTimeLimit + 10);
        $memoryLimit = min((int) config('judge0.max_memory_limit_kb'), max(16384, $memoryLimitKb));

        $submissions = array_map(static fn (array $tc) => [
            'source_code' => $sourceCode,
            'language_id' => $languageId,
            'stdin' => $tc['input'],
            // Gửi kèm expected_output để Judge0 TỰ so khớp stdout (status Accepted/Wrong
            // Answer) — tin vào cách so khớp đã được kiểm chứng của Judge0 (bỏ qua khoảng
            // trắng cuối dòng) thay vì tự viết lại so khớp riêng, dễ sai lệch với chuẩn OJ.
            'expected_output' => $tc['expected_output'],
            'cpu_time_limit' => $cpuTimeLimit,
            'wall_time_limit' => $wallTimeLimit,
            'memory_limit' => $memoryLimit,
        ], $testCases);

        $results = $this->client->runBatch($submissions);

        $verdict = VerdictStatus::Accepted;
        $details = [];

        foreach ($results as $r) {
            $caseVerdict = $this->mapStatus((int) ($r['status']['id'] ?? 13), $r['memory'] ?? null, $memoryLimit);
            $verdict = $this->worseOf($verdict, $caseVerdict);

            $details[] = [
                'status' => (string) ($r['status']['description'] ?? 'Unknown'),
                'time' => $r['time'] ?? null,
                'memory' => $r['memory'] ?? null,
                'stderr' => $r['stderr'] !== null && $r['stderr'] !== '' ? $r['stderr'] : null,
                'compileOutput' => $r['compile_output'] !== null && $r['compile_output'] !== '' ? $r['compile_output'] : null,
            ];
        }

        return [
            'verdict' => $verdict,
            'isAccepted' => $verdict === VerdictStatus::Accepted,
            'details' => $details,
        ];
    }

    /**
     * Ánh xạ status id chuẩn của Judge0 1.13.x sang VerdictStatus của onthi360. Judge0 KHÔNG
     * có status "Memory Limit Exceeded" riêng — chương trình vượt bộ nhớ thường bị kernel/
     * isolate giết và trả về Runtime Error (id 7-12) giống hệt lỗi runtime khác — heuristic:
     * nếu memory Judge0 báo đã dùng >= giới hạn đã gửi thì coi là hết bộ nhớ thay vì lỗi runtime
     * thường, giúp học sinh/giáo viên đọc verdict đúng nguyên nhân hơn.
     */
    private function mapStatus(int $statusId, ?int $memoryUsedKb, int $memoryLimitKb): VerdictStatus
    {
        return match (true) {
            $statusId === 3 => VerdictStatus::Accepted,
            $statusId === 4 => VerdictStatus::WrongAnswer,
            $statusId === 5 => VerdictStatus::TimeLimitExceeded,
            $statusId === 6 => VerdictStatus::CompileError,
            $statusId >= 7 && $statusId <= 12 => ($memoryUsedKb !== null && $memoryUsedKb >= $memoryLimitKb)
                ? VerdictStatus::MemoryLimitExceeded
                : VerdictStatus::RuntimeError,
            default => VerdictStatus::SystemError, // 1,2 (không nên xảy ra vì đã wait=true), 13, 14
        };
    }

    /** "Verdict xấu nhất thắng" khi 1 bài có nhiều test case — Accepted chỉ khi TẤT CẢ đều Accepted. */
    private function worseOf(VerdictStatus $a, VerdictStatus $b): VerdictStatus
    {
        $rank = [
            VerdictStatus::CompileError->value => 6,
            VerdictStatus::SystemError->value => 5,
            VerdictStatus::RuntimeError->value => 4,
            VerdictStatus::MemoryLimitExceeded->value => 4,
            VerdictStatus::TimeLimitExceeded->value => 3,
            VerdictStatus::WrongAnswer->value => 2,
            VerdictStatus::Accepted->value => 1,
        ];

        return $rank[$b->value] > $rank[$a->value] ? $b : $a;
    }
}
