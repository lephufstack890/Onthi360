<?php

namespace App\Services\Contest;

use App\Enums\AttemptStatus;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Competition;
use App\Models\CompetitionExam;
use App\Models\Question;
use App\Models\User;
use App\Support\PracticeActivityLog;
use Carbon\Carbon;

/**
 * SỬA 30/10 (khách: "thêm tab Nhật ký cho khảo sát / cuộc thi — admin, giáo viên xem được toàn bộ; học sinh chỉ xem của riêng
 * mình; in đúc nhật ký làm bài bên Luyện tập để biết ai xem Hướng dẫn, ai mở tab mới, ai xem Bài mẫu…") — dữ liệu tab Nhật ký
 * của MỘT KỲ (competition exam), trả JSON cho route competitions.activity.
 *
 * Ai xem được gì (kiểm ở máy chủ, giao diện chỉ hiển thị):
 *   · ban tổ chức của kỳ (admin; giáo viên chủ trì kỳ / tổ chức cuộc thi — ContestOrganizerService::canManageRound): mọi lượt;
 *   · người khác đã đăng nhập (học sinh…): CHỈ lượt của chính mình — máy chủ không gửi lượt của người khác ra.
 *
 * Nguồn nhật ký: attempts.activity_log (cả lượt, từ 30/10) gộp với attempt_answers.activity_log (theo câu, có từ 11/10 — để các
 * lượt nộp trước 30/10 vẫn xem được). Nhật ký do trình duyệt ghi lúc làm đề rồi gửi kèm khi nộp (partials/work-activity-log).
 */
class ContestActivityLogService
{
    /** Trần số lượt đưa ra (mới nộp trước). */
    private const LIMIT = 500;

    /** Mỗi lượt chỉ đưa tối đa ngần này mốc mới nhất (eventCount vẫn là tổng thật). */
    private const EVENTS_PER_ATTEMPT = 200;

    public function __construct(private ContestOrganizerService $organizers) {}

    /** @return array<string, mixed> */
    public function forRound(User $viewer, int $competitionId, int $examId): array
    {
        $competition = Competition::query()->findOrFail($competitionId);
        $exam = CompetitionExam::query()->where('competition_id', $competition->id)->findOrFail($examId);

        $manager = $this->organizers->canCreateRound($viewer) && $this->organizers->canManageRound($viewer, $competition, $exam);

        $attempts = Attempt::query()
            ->with(['user:id,name,email,phone', 'assessment:id,title,total_points'])
            ->where('competition_exam_id', $exam->id)
            ->where(fn ($q) => $q->whereNotNull('submitted_at')->orWhere('status', AttemptStatus::InProgress->value))
            ->when(! $manager, fn ($q) => $q->where('user_id', $viewer->id))
            ->orderByRaw('submitted_at IS NULL DESC') // lượt đang làm lên đầu
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();

        $logs = $this->logsFor($attempts);

        $questionIds = [];
        foreach ($logs as $events) {
            foreach ($events as $e) {
                if (! empty($e['questionId'])) {
                    $questionIds[(int) $e['questionId']] = true;
                }
            }
        }
        $titles = $questionIds === [] ? [] : Question::query()->whereIn('id', array_keys($questionIds))->pluck('title', 'id')->all();

        $tz = config('app.timezone');
        $rows = $attempts->map(function (Attempt $a) use ($viewer, $manager, $logs, $titles, $tz) {
            $log = $logs[$a->id] ?? [];
            $user = $a->user;
            $mine = (int) $a->user_id === (int) $viewer->id;
            $account = (string) ($user?->email ?: ($user?->phone ?: ''));
            $done = $a->submitted_at !== null;
            $seconds = ($a->started_at !== null && $done) ? max(0, (int) $a->submitted_at->diffInSeconds($a->started_at, true)) : null;
            $pending = $done && ($a->is_provisional || $a->total_score === null || $a->status === AttemptStatus::Grading);

            // Đếm theo loại — tách riêng Hướng dẫn / Bài mẫu cho dễ đọc (nhóm 'guide' gộp cả hai).
            $signals = ['tab' => 0, 'screenshot' => 0, 'guideOpen' => 0, 'sampleOpen' => 0, 'other' => 0];
            foreach ($log as $e) {
                $type = $e['eventType'] ?? null;
                $cat = PracticeActivityLog::category($e);
                match (true) {
                    $type === 'solution_guide_opened' => $signals['guideOpen']++,
                    $type === 'sample_opened' => $signals['sampleOpen']++,
                    $cat === 'tab' => $signals['tab']++,
                    $cat === 'screenshot' => $signals['screenshot']++,
                    $cat === 'other' => $signals['other']++,
                    default => null,
                };
            }
            $penalty = PracticeActivityLog::penalty($log);

            $events = array_map(function (array $e) use ($titles, $tz) {
                $at = null;
                if (! empty($e['occurredAt'])) {
                    try {
                        $at = Carbon::parse($e['occurredAt'])->setTimezone($tz);
                    } catch (\Throwable) {
                        $at = null;
                    }
                }
                $type = $e['eventType'] ?? null;

                return [
                    'id' => (string) ($e['id'] ?? ''),
                    'kind' => ($e['kind'] ?? null) === 'signal' ? 'signal' : 'event',
                    'group' => match (true) {
                        $type === 'solution_guide_opened' => 'guideOpen',
                        $type === 'sample_opened' => 'sampleOpen',
                        default => PracticeActivityLog::category($e) ?? 'event',
                    },
                    'title' => (string) ($e['title'] ?? ''),
                    'detail' => (string) ($e['detail'] ?? ''),
                    'question' => ! empty($e['questionId']) ? ($titles[(int) $e['questionId']] ?? 'Câu #'.(int) $e['questionId']) : null,
                    'time' => $at?->format('H:i:s'),
                    'date' => $at?->format('d/m/Y'),
                ];
            }, array_slice($log, -self::EVENTS_PER_ATTEMPT));

            return [
                'id' => (int) $a->id,
                'mine' => $mine,
                'name' => $user?->name ?? 'Người dùng đã xoá',
                // Ban tổ chức thấy đủ tài khoản; người khác chỉ thấy lượt của mình nên cũng là tài khoản của mình.
                'account' => ($manager || $mine) ? $account : '',
                'paper' => (string) ($a->assessment?->title ?? ''),
                'state' => $done ? 'done' : 'doing',
                'startedAt' => $a->started_at?->format('H:i d/m/Y'),
                'submittedAt' => $a->submitted_at?->format('H:i d/m/Y'),
                'submittedTs' => $a->submitted_at?->getTimestamp() ?? 0,
                'duration' => $seconds !== null ? intdiv($seconds, 60).' phút '.($seconds % 60).' giây' : null,
                'score' => ($done && ! $pending && $a->total_score !== null) ? (float) $a->total_score : null,
                'maxScore' => (float) ($a->assessment?->total_points ?: 0),
                'pending' => $pending,
                'signals' => $signals,
                'signalTotal' => array_sum($signals),
                'penaltyPercent' => (int) $penalty['percent'],
                'eventCount' => count($log),
                'events' => array_reverse($events), // mới nhất trước
            ];
        })->values()->all();

        return [
            'ok' => true,
            'manager' => $manager,
            'rows' => $rows,
            'truncated' => count($rows) >= self::LIMIT,
            'hasLogColumn' => Attempt::supportsActivityLog(),
        ];
    }

    /**
     * Nhật ký từng lượt (cũ → mới): gộp attempts.activity_log với attempt_answers.activity_log, bỏ trùng theo id dòng.
     *
     * @param  \Illuminate\Support\Collection<int, Attempt>  $attempts
     * @return array<int, list<array<string, mixed>>>
     */
    private function logsFor($attempts): array
    {
        $out = [];
        if ($attempts->isEmpty()) {
            return $out;
        }

        if (Attempt::supportsActivityLog()) {
            foreach ($attempts as $a) {
                if (is_array($a->activity_log) && $a->activity_log !== []) {
                    $out[$a->id] = $a->activity_log;
                }
            }
        }

        if (AttemptAnswer::supportsActivityLog()) {
            AttemptAnswer::query()
                ->whereIn('attempt_id', $attempts->pluck('id')->all())
                ->whereNotNull('activity_log')
                ->get(['id', 'attempt_id', 'question_id', 'activity_log'])
                ->each(function (AttemptAnswer $ans) use (&$out) {
                    $log = is_array($ans->activity_log) ? $ans->activity_log : [];
                    if ($log === []) {
                        return;
                    }
                    // Dòng cũ có thể thiếu questionId — gắn câu của chính dòng trả lời này.
                    $log = array_map(function ($e) use ($ans) {
                        if (is_array($e) && empty($e['questionId'])) {
                            $e['questionId'] = (int) $ans->question_id;
                        }

                        return $e;
                    }, $log);
                    $out[$ans->attempt_id] = PracticeActivityLog::merge($out[$ans->attempt_id] ?? null, array_values(array_filter($log, 'is_array')));
                });
        }

        return $out;
    }
}
