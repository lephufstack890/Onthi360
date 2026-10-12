<?php

namespace App\Support;

use App\Enums\VerdictStatus;
use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use Illuminate\Support\Collection;

/**
 * SỬA 12/10 — tách khỏi PracticeHistoryService::examLogPenalties() để DÙNG CHUNG: Nhật ký làm đề và danh sách "Đề đã giao"
 * cùng tính điểm đã bị trừ theo nhật ký bằng đúng một đoạn mã, nên hai nơi không bao giờ hiện hai con số khác nhau.
 */
final class ExamAttemptPenalty
{
    /**
     * SỬA 11/10 — mỗi lượt thi: số điểm ĐÃ BỊ TRỪ theo nhật ký + số dấu hiệu (dòng 'signal' trong nhật ký làm đề).
     *
     * Điểm đã lưu là điểm SAU khi trừ nên điểm gốc được dựng lại từ dữ liệu không bị trừ: câu lập trình = điểm tối đa × số test
     * qua / tổng test; trắc nghiệm / điền đáp án = trọn điểm khi đúng (verdict không đổi sau khi trừ). Thiếu số liệu thì lấy
     * điểm đã lưu / (1 − %trừ). Đề PDF và máy chủ chưa có cột activity_log: không có dữ liệu (mảng rỗng).
     *
     * @param  Collection<int, Attempt>  $attempts
     * @return array<int, array{deducted: float, signals: int}>  attempt id => số liệu
     */
    public static function forAttempts(Assessment $exam, Collection $attempts, bool $pdf = false): array
    {
        if ($pdf || $attempts->isEmpty() || ! AttemptAnswer::supportsActivityLog()) {
            return [];
        }

        $maxByQuestion = [];
        foreach ($exam->items as $item) {
            $maxByQuestion[$item->question_id] = (float) $item->effectivePoints();
        }

        $out = [];

        AttemptAnswer::query()
            ->whereIn('attempt_id', $attempts->pluck('id')->all())
            ->whereNotNull('activity_log')
            ->get()
            ->each(function (AttemptAnswer $a) use (&$out, $maxByQuestion) {
                $log = is_array($a->activity_log) ? $a->activity_log : [];
                $signals = count(array_filter($log, fn ($e) => is_array($e) && ($e['kind'] ?? null) === 'signal'));
                $percent = \App\Support\PracticeActivityLog::penalty($log)['percent'];
                $deducted = 0.0;

                if ($percent > 0 && $a->score !== null) {
                    $after = (float) $a->score;
                    $maxPoints = (float) ($maxByQuestion[$a->question_id] ?? 0.0);

                    if (AttemptAnswer::supportsTestCounts() && (int) ($a->total_tests ?? 0) > 0) {
                        $raw = round($maxPoints * (int) $a->passed_tests / (int) $a->total_tests, 2);
                    } elseif ($a->verdict === VerdictStatus::Accepted && $maxPoints > 0) {
                        $raw = $maxPoints;
                    } elseif ($percent < 100) {
                        $raw = $after / (1 - $percent / 100);
                    } else {
                        $raw = $after;
                    }

                    $deducted = max(0.0, round($raw - $after, 2));
                }

                $out[$a->attempt_id]['deducted'] = round(($out[$a->attempt_id]['deducted'] ?? 0.0) + $deducted, 2);
                $out[$a->attempt_id]['signals'] = ($out[$a->attempt_id]['signals'] ?? 0) + $signals;
            });

        return $out;
    }
}
