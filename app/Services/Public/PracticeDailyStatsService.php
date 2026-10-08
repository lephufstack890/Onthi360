<?php

namespace App\Services\Public;

use App\Enums\AttemptStatus;
use App\Enums\VerdictStatus;
use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\User;
use App\Support\PracticeActivityLog;
use Carbon\Carbon;
use Throwable;

/**
 * SỬA 8/10 (khách: "trang luyện tập public thêm khối LUYỆN TẬP HÔM NAY như source mới; dữ liệu lấy từ
 * nhật ký khi học sinh làm bài để tổng hợp") — số liệu cho khối "Luyện tập hôm nay" ở đầu trang.
 *
 * Dựng theo education-main: PracticeStatsChart.jsx / PracticeDailyStats.jsx / utils/practiceStats.js.
 * Bản mẫu đọc lượt nộp từ localStorage của trình duyệt; ở đây lấy từ cơ sở dữ liệu và CHỈ của chính
 * người đang đăng nhập (khách chưa đăng nhập thì không có số liệu).
 *
 *  · "Lượt nộp"  : mỗi bài tập (attempt_answers của phiên tự luyện, attempt.assessment_id = null) hoặc mỗi
 *                  lần nộp đề luyện tập (attempts.submitted_at) trong NGÀY HÔM NAY theo giờ Việt Nam.
 *                  Cùng một bài nộp lại trong cùng phiên thì chỉ còn dòng mới nhất (giống trang Nhật ký
 *                  nộp bài — PracticeHistoryService).
 *  · "Đúng"      : bài = AC / đủ điểm; đề = đạt trọn điểm toàn đề. Lượt chờ chấm KHÔNG tính là đúng.
 *  · Mức dấu hiệu: đọc từ attempt_answers.activity_log (nhật ký trình duyệt gửi kèm lúc nộp bài —
 *                  xem App\Support\PracticeActivityLog). Chưa có nhật ký = "unknown".
 *      - high    : có phím/yêu cầu chụp-quay màn hình, HOẶC rời tab/cửa sổ từ 3 lần trong một lượt;
 *      - unusual : có tín hiệu khác (rời tab dưới 3 lần, mở Hướng dẫn/Bài mẫu…);
 *      - normal  : có nhật ký nhưng không có tín hiệu nào;
 *      - unknown : lượt nộp không có nhật ký (trước khi có tính năng, hoặc đề thi).
 *
 * Đây là TÍN HIỆU ĐỂ ĐỐI CHIẾU, không phải kết luận gian lận.
 */
class PracticeDailyStatsService
{
    public const TIME_ZONE = 'Asia/Ho_Chi_Minh';

    /** Rời tab từ ngần này lần trong một lượt nộp thì coi là "nghi vấn cao". */
    public const HIGH_RISK_TAB_THRESHOLD = 3;

    /** Chặn trên số dòng đưa ra trang (một ngày hiếm khi vượt mức này). */
    private const LIMIT = 300;

    /**
     * @return array{signedIn: bool, date: string, rows: list<array<string, mixed>>}
     */
    public function forViewer(?User $viewer): array
    {
        $now = Carbon::now(self::TIME_ZONE);
        $payload = ['signedIn' => $viewer !== null, 'date' => $now->format('d/m'), 'rows' => []];

        if ($viewer === null) {
            return $payload;
        }

        // Ranh giới ngày theo giờ Việt Nam, đổi sang múi giờ lưu trong CSDL để so sánh.
        $from = $now->copy()->startOfDay()->setTimezone(config('app.timezone'));
        $to = $now->copy()->endOfDay()->setTimezone(config('app.timezone'));

        try {
            $rows = array_merge($this->problemRows($viewer, $from, $to), $this->examRows($viewer, $from, $to));
        } catch (Throwable $e) {
            // Khối thống kê chỉ là phần phụ — lỗi truy vấn (vd. máy chủ chưa chạy migrate) không được làm
            // hỏng cả trang Luyện tập.
            report($e);

            return $payload;
        }

        usort($rows, fn ($a, $b) => $b['ts'] <=> $a['ts']);
        $payload['rows'] = array_slice($rows, 0, self::LIMIT);

        return $payload;
    }

    /** @return list<array<string, mixed>> */
    private function problemRows(User $viewer, Carbon $from, Carbon $to): array
    {
        $answers = AttemptAnswer::query()
            ->with(['question:id,title,points'])
            ->whereHas('attempt', fn ($a) => $a->where('user_id', $viewer->id)->whereNull('assessment_id'))
            ->whereBetween('updated_at', [$from, $to])
            ->where(function ($w) {
                $w->where('submission_count', '>', 0)->orWhereNotNull('graded_at')->orWhereNotNull('score');
            })
            ->orderByDesc('updated_at')
            ->limit(self::LIMIT)
            ->get();

        return $answers->map(function (AttemptAnswer $a) {
            $final = $a->verdict instanceof VerdictStatus ? $a->verdict->isFinal() : true;
            $max = (float) ($a->question?->points ?? 0);
            $score = $a->score !== null ? (float) $a->score : null;
            $at = Carbon::parse($a->updated_at)->setTimezone(self::TIME_ZONE);

            $log = AttemptAnswer::supportsActivityLog() && is_array($a->activity_log) ? $a->activity_log : null;

            return [
                'type' => 'problem',
                'subjectId' => (int) $a->question_id,
                'id' => 'A'.$a->id,
                'title' => $a->question?->title ?? 'Bài tập',
                'pending' => ! $final,
                'result' => $final ? $this->resultOf($score, $max, $a->verdict === VerdictStatus::Accepted) : 'pending',
                'risk' => self::riskOf($log),
                'at' => $at->toIso8601String(),
                'time' => $at->format('H:i'),
                'ts' => $at->getTimestamp(),
                'url' => route('practice.history.problem', $a->question_id),
            ];
        })->all();
    }

    /** @return list<array<string, mixed>> */
    private function examRows(User $viewer, Carbon $from, Carbon $to): array
    {
        $attempts = Attempt::query()
            ->with(['assessment:id,title,total_points', 'assessment.items.question:id,points'])
            ->where('user_id', $viewer->id)
            ->whereNotNull('assessment_id')
            ->whereNotNull('submitted_at')
            ->whereBetween('submitted_at', [$from, $to])
            ->whereHas('assessment', fn ($q) => $q->where('type', 'practice'))
            ->orderByDesc('submitted_at')
            ->limit(self::LIMIT)
            ->get();

        if ($attempts->isEmpty()) {
            return [];
        }

        // Nhật ký của đề (nếu có) nằm ở các câu trả lời của lượt thi; gộp lại theo lượt.
        $logs = [];
        if (AttemptAnswer::supportsActivityLog()) {
            AttemptAnswer::query()
                ->whereIn('attempt_id', $attempts->pluck('id'))
                ->whereNotNull('activity_log')
                ->get(['id', 'attempt_id', 'activity_log'])
                ->each(function (AttemptAnswer $a) use (&$logs) {
                    if (is_array($a->activity_log)) {
                        $logs[$a->attempt_id] = array_merge($logs[$a->attempt_id] ?? [], $a->activity_log);
                    }
                });
        }

        return $attempts->map(function (Attempt $a) use ($logs) {
            $exam = $a->assessment;
            $itemsSum = (float) ($exam?->items->sum(fn ($i) => (float) ($i->points_override ?? $i->question?->points ?? 0)) ?? 0);
            $max = (float) ($exam?->total_points ?: 0) ?: $itemsSum;
            $score = $a->total_score !== null ? (float) $a->total_score : null;
            $pending = $a->is_provisional || $score === null || $a->status === AttemptStatus::Grading;
            $at = Carbon::parse($a->submitted_at)->setTimezone(self::TIME_ZONE);

            return [
                'type' => 'exam',
                'subjectId' => (int) $a->assessment_id,
                'id' => 'L'.$a->id,
                'title' => $exam?->title ?? 'Đề thi luyện tập',
                'pending' => $pending,
                'result' => $pending ? 'pending' : $this->resultOf($score, $max, false),
                'risk' => self::riskOf($logs[$a->id] ?? null),
                'at' => $at->toIso8601String(),
                'time' => $at->format('H:i'),
                'ts' => $at->getTimestamp(),
                'url' => route('practice.history.exam', $a->assessment_id),
            ];
        })->all();
    }

    /**
     * Mức dấu hiệu của MỘT lượt nộp từ nhật ký của nó (port của submissionRisk() trong practiceStats.js).
     *
     * @param  list<array<string, mixed>>|null  $events  null = lượt nộp không có nhật ký
     * @return 'normal'|'unusual'|'high'|'unknown'
     */
    public static function riskOf(?array $events): string
    {
        if ($events === null) {
            return 'unknown';
        }

        $events = array_values(array_filter($events, 'is_array'));
        usort($events, fn ($a, $b) => self::ms($a['occurredAt'] ?? null) <=> self::ms($b['occurredAt'] ?? null));

        $seen = [];
        $tabs = 0;
        $lastTab = null;
        $signals = 0;

        foreach ($events as $event) {
            $id = (string) ($event['id'] ?? '');
            if ($id !== '') {
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
            }

            $category = PracticeActivityLog::category($event);
            if ($category === null) {
                continue;
            }

            $signals++;

            if ($category === 'screenshot') {
                return 'high';
            }

            if ($category === 'tab') {
                $time = self::ms($event['occurredAt'] ?? null);
                // "Rời tab" và "mất tiêu điểm cửa sổ" thường là CÙNG một lần rời đi — cách nhau ≤ 1,5 giây thì tính 1.
                if ($time === null || $lastTab === null || $time - $lastTab > 1500) {
                    $tabs++;
                }
                if ($time !== null) {
                    $lastTab = $time;
                }
            }
        }

        return $tabs >= self::HIGH_RISK_TAB_THRESHOLD ? 'high' : ($signals > 0 ? 'unusual' : 'normal');
    }

    private static function ms(mixed $value): ?int
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->getTimestamp() * 1000;
        } catch (Throwable) {
            return null;
        }
    }

    /** ac / partial / wa theo điểm; accepted luôn là ac (cùng luật với PracticeHistoryService). */
    private function resultOf(?float $score, float $max, bool $accepted): string
    {
        if ($accepted) {
            return 'ac';
        }

        if ($score === null || $max <= 0) {
            return $score !== null && $score > 0 ? 'partial' : 'wa';
        }

        if ($score >= $max - 0.005) {
            return 'ac';
        }

        return $score > 0 ? 'partial' : 'wa';
    }
}
