<?php

namespace App\Services\Public;

use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Competition;
use App\Models\User;
use App\Repositories\Contracts\CompetitionExamRepositoryInterface;
use App\Repositories\Contracts\CompetitionRepositoryInterface;
use App\Repositories\Contracts\LeaderboardEntryRepositoryInterface;

class LeaderboardService
{
    private const ANONYMOUS_LABEL = 'Học viên đã xác thực';

    private const DISPLAY_LIMIT = 50;

    public function __construct(
        private CompetitionRepositoryInterface $competitions,
        private LeaderboardEntryRepositoryInterface $leaderboardEntries,
        private CompetitionExamRepositoryInterface $competitionExams,
    ) {}

    /**
     * @return array{
     *     boards: array,
     *     selected: ?Competition,
     *     examTabs: array,
     *     selectedExamId: ?int,
     *     rankingRule: array,
     *     updatedAt: mixed,
     *     entries: array,
     *     totalEntries: int,
     *     yourEntry: ?array,
     * }
     */
    public function indexData(?int $competitionId, ?int $examId, ?User $viewer): array
    {
        $publicCompetitions = $this->competitions->query()
            ->where('status', 'published')
            ->withCount(['leaderboardEntries' => fn ($q) => $q->where('scope', 'competition')])
            ->having('leaderboard_entries_count', '>', 0)
            ->latest('publish_result_at')
            ->get();

        $boards = $publicCompetitions->map(fn (Competition $c) => [
            'id' => $c->id,
            'title' => $c->title,
            'participants' => $c->leaderboard_entries_count,
        ])->all();

        $selected = $competitionId !== null
            ? $publicCompetitions->firstWhere('id', $competitionId)
            : $publicCompetitions->first();

        if ($selected === null) {
            return [
                'boards' => $boards,
                'selected' => null,
                'examTabs' => [],
                'selectedExamId' => null,
                'rankingRule' => [],
                'updatedAt' => null,
                'entries' => [],
                'totalEntries' => 0,
                'yourEntry' => null,
                'scopeLabel' => null,
            ];
        }

        $exams = $this->competitionExams->forCompetition($selected->id);

        $examTabs = $exams->map(fn ($e) => [
            'id' => $e->id,
            'title' => $e->displayTitle(),
        ])->all();

        $selectedExam = $examId !== null ? $exams->firstWhere('id', $examId) : null;

        $rawEntries = $selectedExam !== null
            ? $this->leaderboardEntries->entriesForCompetitionExam($selectedExam->id)
            : $this->leaderboardEntries->entriesForCompetition($selected->id);

        // SỬA 11/9 — giao diện mới (education-main/src/components/LeaderboardPage.jsx) có cột
        // "Số bài AC" và dòng thời điểm cập nhật. Cả hai đều tính từ dữ liệu thật:
        //   · AC  = số câu người đó làm ĐÚNG trong đúng phạm vi đang xem (1 câu GROUP BY cho
        //           cả bảng, không phải mỗi dòng 1 truy vấn)
        //   · thời điểm = leaderboard_entries.computed_at của chính dòng đó
        $displayed = $rawEntries->take(self::DISPLAY_LIMIT);
        $acCounts = $this->acceptedCountsByUser(
            $displayed->pluck('user_id')->all(),
            $selected->id,
            $selectedExam?->id,
        );

        $entries = $displayed->map(fn ($e) => [
            'rank' => $e->rank,
            // Ẩn danh cho MỌI người xem — trừ đúng dòng của chính người đang đăng nhập, vì đó
            // là dữ liệu của chính họ nên hiện tên thật không lộ thêm thông tin của ai khác.
            'name' => ($viewer !== null && $e->user_id === $viewer->id)
                ? $viewer->name
                : self::ANONYMOUS_LABEL,
            'score' => (float) $e->score,
            'acCount' => (int) ($acCounts[$e->user_id] ?? 0),
            'computedAt' => $e->computed_at,
            'isYou' => $viewer !== null && $e->user_id === $viewer->id,
        ])->values()->all();

        $yourEntry = null;
        if ($viewer !== null) {
            $mine = $rawEntries->firstWhere('user_id', $viewer->id);
            if ($mine !== null) {
                $yourEntry = ['rank' => $mine->rank, 'score' => (float) $mine->score];
            }
        }

        return [
            'boards' => $boards,
            'selected' => $selected,
            'examTabs' => $examTabs,
            'selectedExamId' => $selectedExam?->id,
            'rankingRule' => $selected->ranking_rule ?? [],
            'updatedAt' => $rawEntries->max('computed_at'),
            'entries' => $entries,
            'totalEntries' => $rawEntries->count(),
            'yourEntry' => $yourEntry,
            // Nhãn phạm vi đang xem — dùng cho cột "Kỳ thi" và dòng trạng thái của giao diện mới.
            'scopeLabel' => $selectedExam !== null ? $selectedExam->displayTitle() : $selected->title,
        ];
    }

    /**
     * Số câu LÀM ĐÚNG của từng học viên trong đúng phạm vi đang xem.
     *
     * "Đúng" = verdict 'accepted' (bài code, chấm bằng bộ test) hoặc score > 0 (trắc nghiệm /
     * điền đáp án). Phạm vi lấy theo attempts.competition_id (hoặc competition_exam_id khi
     * đang xem 1 kỳ thi con) nên con số luôn khớp với bảng đang hiển thị, không gộp nhầm lượt
     * làm ở cuộc thi khác.
     *
     * @param  array<int, int>  $userIds
     * @return array<int, int> keyed theo user_id
     */
    private function acceptedCountsByUser(array $userIds, int $competitionId, ?int $competitionExamId): array
    {
        if ($userIds === []) {
            return [];
        }

        $attemptIds = Attempt::query()
            ->whereIn('user_id', $userIds)
            ->when(
                $competitionExamId !== null,
                fn ($q) => $q->where('competition_exam_id', $competitionExamId),
                fn ($q) => $q->where('competition_id', $competitionId),
            )
            ->pluck('id', 'id');

        if ($attemptIds->isEmpty()) {
            return [];
        }

        return AttemptAnswer::query()
            ->join('attempts', 'attempts.id', '=', 'attempt_answers.attempt_id')
            ->selectRaw('attempts.user_id as uid, COUNT(*) as total')
            ->whereIn('attempt_answers.attempt_id', $attemptIds->all())
            ->where(fn ($q) => $q->where('attempt_answers.verdict', 'accepted')->orWhere('attempt_answers.score', '>', 0))
            ->groupBy('attempts.user_id')
            ->pluck('total', 'uid')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
