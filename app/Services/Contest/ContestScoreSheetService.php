<?php

namespace App\Services\Contest;

use App\Enums\AttemptStatus;
use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Competition;
use App\Models\CompetitionExam;
use App\Models\User;
use App\Support\PracticeActivityLog;

/**
 * SỬA 30/10 (khách: "khảo sát kết thúc thì có nút In — bấm ra modal chọn bản ghi nào để in, mẫu như Bảng điểm THCS Vũ Ninh: cột
 * từng bài, tổng điểm, giải 1 Nhất · 1 Nhì · 1 Ba · 2 KK; bạn gian lận không tính thì thêm ghi chú; có kết quả kiểm tra thao tác
 * làm bài; nộp 2 lần thì chọn lần nào") — DỮ LIỆU cho hộp "In bảng điểm & xếp giải" của một kỳ.
 *
 * Chỉ BAN TỔ CHỨC của kỳ (admin; giáo viên chủ trì / tổ chức — ContestOrganizerService::canManageRound). Trả mọi học sinh có bài
 * nộp ở kỳ, kèm TỪNG lượt nộp (điểm từng bài, tổng, dấu hiệu trong nhật ký làm bài) để ban tổ chức tự chọn lượt đưa vào bảng; việc
 * xếp hạng / chia giải / ghi chú / in làm ở trình duyệt (public/competitions/partials/score-sheet) — không ghi gì vào cơ sở dữ liệu.
 */
class ContestScoreSheetService
{
    public function __construct(
        private ContestOrganizerService $organizers,
        private ContestPaperService $papers,
        private ContestActivityLogService $activity,
    ) {}

    /** @return array<string, mixed> */
    public function forRound(User $viewer, int $competitionId, int $examId): array
    {
        $c = Competition::query()->with('advisors:id,name')->findOrFail($competitionId);
        $exam = CompetitionExam::query()->where('competition_id', $c->id)->findOrFail($examId);
        abort_unless($this->organizers->canCreateRound($viewer) && $this->organizers->canManageRound($viewer, $c, $exam), 403,
            'Chỉ admin hoặc giáo viên tổ chức kỳ này mới in được bảng điểm.');

        $survey = $c->isSurvey();
        $assessments = $this->assessmentsOf($exam);
        $questions = $this->questionsOf($assessments);
        $maxScore = (float) array_sum(array_column($questions, 'points'));
        if ($maxScore <= 0) {
            $maxScore = (float) ($assessments->first()?->total_points ?: 0);
        }

        $attempts = Attempt::query()
            ->with('user:id,name,email,phone', 'assessment:id,title,total_points')
            ->where('competition_exam_id', $exam->id)
            ->whereNotNull('submitted_at')
            ->orderBy('submitted_at')->orderBy('id')
            ->get();

        $scores = [];
        if ($attempts->isNotEmpty() && $questions !== []) {
            AttemptAnswer::query()->whereIn('attempt_id', $attempts->pluck('id')->all())
                ->get(['attempt_id', 'question_id', 'score'])
                ->each(function ($a) use (&$scores) {
                    if ($a->score !== null) {
                        $scores[(int) $a->attempt_id][(int) $a->question_id] = round((float) $a->score, 2);
                    }
                });
        }
        $logs = $this->activity->logsFor($attempts);
        $classes = $this->organizers->classNamesFor($attempts->pluck('user_id')->map(fn ($id) => (int) $id)->unique()->values()->all());

        $students = [];
        foreach ($attempts->groupBy('user_id') as $userId => $list) {
            $user = $list->first()->user;
            $rows = [];
            foreach ($list->values() as $i => $a) {
                $pending = $a->is_provisional || $a->total_score === null || $a->status === AttemptStatus::Grading;
                $counts = ['tab' => 0, 'screenshot' => 0, 'guideOpen' => 0, 'sampleOpen' => 0, 'other' => 0];
                foreach ($logs[$a->id] ?? [] as $e) {
                    $type = $e['eventType'] ?? null;
                    $cat = PracticeActivityLog::category($e);
                    match (true) {
                        $type === 'solution_guide_opened' => $counts['guideOpen']++,
                        $type === 'sample_opened' => $counts['sampleOpen']++,
                        $cat === 'tab' => $counts['tab']++,
                        $cat === 'screenshot' => $counts['screenshot']++,
                        $cat === 'other' => $counts['other']++,
                        default => null,
                    };
                }
                $rows[] = [
                    'id' => (int) $a->id,
                    'no' => $i + 1,
                    'submittedAt' => $a->submitted_at->format('H:i d/m/Y'),
                    'submittedTs' => $a->submitted_at->getTimestamp(),
                    'paper' => (string) ($a->assessment?->title ?? ''),
                    'assessmentId' => (int) $a->assessment_id,
                    'score' => $pending ? null : round((float) $a->total_score, 2),
                    'pending' => $pending,
                    'perQuestion' => (object) ($scores[$a->id] ?? []),
                    'activity' => $counts,
                    'hasLog' => isset($logs[$a->id]),
                ];
            }

            // Lượt mặc định: khảo sát = điểm cao nhất (bằng điểm → nộp sớm hơn); cuộc thi = lượt nộp đầu tiên (như Bảng xếp hạng).
            $graded = array_values(array_filter($rows, fn ($r) => $r['score'] !== null));
            $default = $rows[0]['id'];
            if ($graded !== []) {
                if ($survey) {
                    usort($graded, fn ($x, $y) => [$y['score'], $x['submittedTs']] <=> [$x['score'], $y['submittedTs']]);
                }
                $default = $graded[0]['id'];
            }

            $students[] = [
                'userId' => (int) $userId,
                'name' => $user?->name ?? 'Tài khoản đã xoá',
                'account' => (string) ($user?->email ?: ($user?->phone ?: '')),
                'className' => (string) ($classes[(int) $userId] ?? ''),
                'attempts' => $rows,
                'defaultAttemptId' => $default,
            ];
        }
        usort($students, fn ($x, $y) => strcmp(mb_strtolower($x['name']), mb_strtolower($y['name'])));

        $title = $c->displayTitle();

        return [
            'ok' => true,
            'isSurvey' => $survey,
            'competitionTitle' => $title,
            'roundLabel' => $exam->displayTitle(),
            'organizer' => $this->organizers->organizerLabel($c),
            'questions' => $questions,
            'maxScore' => $maxScore,
            'students' => $students,
            'defaults' => [
                'heading' => 'BẢNG TỔNG HỢP ĐIỂM VÀ XẾP GIẢI',
                'subheading' => mb_strtoupper($title),
                'unit' => $this->organizers->organizerLabel($c),
            ],
        ];
    }

    /** Đề của kỳ: đề đã chốt (ma trận) → đề ngân hàng → đề gắn ở kỳ. @return \Illuminate\Support\Collection<int, Assessment> */
    private function assessmentsOf(CompetitionExam $exam)
    {
        $ids = $this->papers->publishedPapers($exam)->pluck('assessment_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        if ($ids === []) {
            $ids = $exam->bankAssessmentIds();
        }
        if ($ids === []) {
            return collect();
        }
        $byId = Assessment::query()->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $byId->get($id))->filter()->values();
    }

    /**
     * Cột "Bài 1 … Bài n": câu của các đề (gộp, không trùng, theo thứ tự trong đề). Đề PDF không có câu rời → không có cột bài.
     *
     * @return list<array{id:int, code:string, title:string, points:float, papers:list<int>}>
     */
    private function questionsOf($assessments): array
    {
        $out = [];
        $seen = [];
        foreach ($assessments as $assessment) {
            if ($assessment->isPdfMode()) {
                continue;
            }
            foreach ($assessment->items()->with('question:id,code,title')->orderBy('order')->get() as $item) {
                $qid = (int) $item->question_id;
                if ($qid <= 0) {
                    continue;
                }
                // Kỳ nhiều đề: ghi lại câu này nằm trong những đề nào (ô của học sinh làm đề khác hiện "—", không phải 0).
                if (isset($seen[$qid])) {
                    $out[$seen[$qid]]['papers'][] = (int) $assessment->id;

                    continue;
                }
                $seen[$qid] = count($out);
                $out[] = [
                    'papers' => [(int) $assessment->id],
                    'id' => $qid,
                    'code' => (string) ($item->question?->code ?? ''),
                    'title' => (string) ($item->question?->title ?? ''),
                    'points' => round((float) $item->effectivePoints(), 2),
                ];
            }
        }

        return $out;
    }
}
