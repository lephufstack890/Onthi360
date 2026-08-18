<?php

namespace App\Services\Public;

use App\Models\Competition;
use App\Models\User;
use App\Repositories\Contracts\CompetitionRepositoryInterface;
use App\Repositories\Contracts\LeaderboardEntryRepositoryInterface;

/**
 * Bảng xếp hạng công khai (PUB-09, 11.2): phạm vi rõ — chỉ hiển thị bảng của Cuộc thi ĐÃ
 * CÔNG BỐ ("Chờ công bố" không lộ rank tạm thời); ẩn danh tên mặc định để bảo vệ dữ liệu
 * trẻ em (chưa có cột "đồng ý hiển thị công khai" nên áp dụng ẩn danh cho MỌI người, không
 * có ngoại lệ); nêu công thức điểm/penalty/đồng điểm (ranking_rule) và thời điểm cập nhật
 * (computed_at) thay vì bộ lọc thời gian giả (tuần này/tháng này — không có trong BA và
 * không có nguồn dữ liệu thật để lọc theo).
 *
 * Chỉ xử lý scope=competition. scope=class_room đã có ở App\Services\Admin\RankingService
 * nhưng dùng nội bộ cho giáo viên/admin quản lý lớp — hiển thị công khai bảng xếp hạng theo
 * lớp cho người ngoài lớp không phù hợp về quyền riêng tư nên chưa đưa vào đây.
 */
class LeaderboardService
{
    private const ANONYMOUS_LABEL = 'Học viên đã xác thực';

    /** Hiển thị top N — bảng đầy đủ (không giới hạn) đã có ở trang quản trị ranking. */
    private const DISPLAY_LIMIT = 50;

    public function __construct(
        private CompetitionRepositoryInterface $competitions,
        private LeaderboardEntryRepositoryInterface $leaderboardEntries,
    ) {}

    /**
     * @return array{
     *     boards: array,
     *     selected: ?Competition,
     *     rankingRule: array,
     *     updatedAt: mixed,
     *     entries: array,
     *     totalEntries: int,
     *     yourEntry: ?array,
     * }
     */
    public function indexData(?int $competitionId, ?User $viewer): array
    {
        $publicCompetitions = $this->competitions->query()
            ->where('status', 'published')
            ->withCount('leaderboardEntries')
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
                'rankingRule' => [],
                'updatedAt' => null,
                'entries' => [],
                'totalEntries' => 0,
                'yourEntry' => null,
            ];
        }

        $rawEntries = $this->leaderboardEntries->entriesForCompetition($selected->id);

        $entries = $rawEntries->take(self::DISPLAY_LIMIT)->map(fn ($e) => [
            'rank' => $e->rank,
            'name' => self::ANONYMOUS_LABEL,
            'score' => (float) $e->score,
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
            'rankingRule' => $selected->ranking_rule ?? [],
            'updatedAt' => $rawEntries->max('computed_at'),
            'entries' => $entries,
            'totalEntries' => $rawEntries->count(),
            'yourEntry' => $yourEntry,
        ];
    }
}
