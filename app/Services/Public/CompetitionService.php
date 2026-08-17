<?php

namespace App\Services\Public;

use App\Enums\CompetitionStatus;
use App\Models\Competition;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\CompetitionRepositoryInterface;

/**
 * Cuộc thi công khai (PUB-08, 11.1 "Menu Cuộc thi": lịch, thể lệ, đề/bộ bài, quy tắc công
 * bố, trạng thái Sắp diễn ra→Đang diễn ra→Chờ công bố→Đã công bố→Lưu trữ).
 */
class CompetitionService
{
    private const STATUS_META = [
        'upcoming' => ['label' => 'Sắp diễn ra', 'tone' => 'info'],
        'ongoing' => ['label' => 'Đang diễn ra', 'tone' => 'success'],
        'pending_publish' => ['label' => 'Chờ công bố', 'tone' => 'neutral'],
        'published' => ['label' => 'Đã công bố', 'tone' => 'neutral'],
        'archived' => ['label' => 'Lưu trữ', 'tone' => 'neutral'],
    ];

    public function __construct(private CompetitionRepositoryInterface $competitions) {}

    /** competitions.index — danh sách cuộc thi/khảo sát công khai. */
    public function indexData(): array
    {
        return [
            'competitions' => $this->competitions->withLeaderboardCounts(30)->map(fn ($c) => $this->mapCard($c))->all(),
        ];
    }

    /**
     * competitions.show — chi tiết cuộc thi thật + đề tham chiếu + đơn vị tổ chức/cố vấn
     * (note họp 13/8, mục 1) + CTA theo trạng thái/vai trò.
     */
    public function showData(int $competitionId, ?User $viewer): array
    {
        $competition = $this->competitions->query()
            ->with(['assessment', 'advisors'])
            ->withCount('leaderboardEntries')
            ->findOrFail($competitionId);

        $meta = self::STATUS_META[$competition->status->value] ?? ['label' => $competition->status->value, 'tone' => 'neutral'];

        return [
            'competition' => $competition,
            'statusLabel' => $meta['label'],
            'statusTone' => $meta['tone'],
            'rankingRule' => $competition->ranking_rule ?? [],
            'daysUntilStart' => $competition->starts_at !== null ? now()->diffInDays($competition->starts_at, false) : null,
            /*
             * "Vào thi" ở đây = làm đề tham chiếu thật (11.1: "cuộc thi chỉ tham chiếu đề để
             * tổ chức sự kiện") qua hạ tầng student.assessment.take sẵn có. Việc TỰ ĐỘNG ghi
             * nhận lượt làm bài này vào leaderboard_entries chưa được nối (cần sửa
             * AttemptService để biết attempt thuộc cuộc thi nào) — đây là phạm vi riêng, rộng
             * hơn việc dựng trang khám phá/chi tiết cuộc thi lần này nên chưa làm ở đây.
             */
            'canJoinDirectly' => $viewer !== null
                && $viewer->hasRole(Role::STUDENT)
                && $competition->assessment_id !== null
                && $competition->status->value === 'ongoing',
        ];
    }

    /**
     * Trang chủ (PUB-01/02, 12.1) — "Cuộc thi sắp tới": CHỈ status=upcoming, xếp gần nhất
     * trước (starts_at TĂNG dần) — khác competitions.index vốn liệt kê MỌI trạng thái, mới
     * TẠO trước (starts_at GIẢM dần qua withLeaderboardCounts()). Không dùng
     * withLeaderboardCounts() ở đây vì cuộc thi sắp diễn ra chưa có lượt tham gia nào để đếm.
     */
    public function upcomingData(int $limit = 4): array
    {
        $competitions = $this->competitions->query()
            ->where('status', CompetitionStatus::Upcoming->value)
            ->orderBy('starts_at')
            ->limit($limit)
            ->get();

        return $competitions->map(fn (Competition $c) => $this->mapCard($c))->all();
    }

    private function mapCard(Competition $c): array
    {
        $meta = self::STATUS_META[$c->status->value] ?? ['label' => $c->status->value, 'tone' => 'neutral'];

        return [
            'id' => $c->id,
            'title' => $c->title,
            'typeLabel' => $c->type->value === 'contest' ? 'Cuộc thi' : 'Khảo sát',
            'statusLabel' => $meta['label'],
            'statusTone' => $meta['tone'],
            'startsAt' => $c->starts_at,
            'endsAt' => $c->ends_at,
            'participants' => $c->leaderboard_entries_count,
        ];
    }
}
