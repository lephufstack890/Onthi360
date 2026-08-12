<?php

namespace App\Services\Admin;

use App\Repositories\Contracts\ClassRoomRepositoryInterface;
use App\Repositories\Contracts\CompetitionRepositoryInterface;
use App\Repositories\Contracts\LeaderboardEntryRepositoryInterface;

/**
 * Gom truy vấn cho admin.ranking.index — 11.2: không trộn phạm vi, không lộ rank tạm khi "Chờ công bố".
 */
class RankingService
{
    public function __construct(
        private CompetitionRepositoryInterface $competitions,
        private LeaderboardEntryRepositoryInterface $leaderboardEntries,
        private ClassRoomRepositoryInterface $classRooms,
    ) {}

    /** @return array{boards: array} */
    public function indexData(): array
    {
        $boards = $this->competitions->withLeaderboardCounts(20)->map(fn ($c) => [
            'id' => $c->id,
            'scope' => 'Cuộc thi: '.$c->title,
            'entries' => $c->ranksArePublic() ? $c->leaderboard_entries_count : 0,
            'status' => $c->ranksArePublic() ? 'Đã công bố' : 'Chờ công bố',
            'tone' => $c->ranksArePublic() ? 'success' : 'neutral',
        ])->all();

        // Bổ sung các bảng xếp hạng theo lớp (scope=class_room) đang có dữ liệu — dùng 1 câu
        // whereIn lấy toàn bộ ClassRoom liên quan + 1 câu group-by đếm entries theo
        // class_room_id, thay cho ClassRoom::find() và count() riêng lẻ theo từng id (N+1/N+2 cũ).
        $classRoomIds = $this->leaderboardEntries->distinctClassRoomIdsForScope('class_room');

        if ($classRoomIds !== []) {
            $classRoomsById = $this->classRooms->query()->whereIn('id', $classRoomIds)->get()->keyBy('id');
            $countsByClassRoomId = $this->leaderboardEntries->query()
                ->where('scope', 'class_room')
                ->whereIn('class_room_id', $classRoomIds)
                ->selectRaw('class_room_id, count(*) as cnt')
                ->groupBy('class_room_id')
                ->pluck('cnt', 'class_room_id');

            foreach ($classRoomIds as $classRoomId) {
                $classRoom = $classRoomsById->get($classRoomId);
                if ($classRoom === null) {
                    continue;
                }

                $boards[] = [
                    'id' => 'class-'.$classRoomId,
                    'scope' => 'Lớp: '.$classRoom->name,
                    'entries' => (int) ($countsByClassRoomId[$classRoomId] ?? 0),
                    'status' => 'Đã công bố',
                    'tone' => 'success',
                ];
            }
        }

        return ['boards' => $boards];
    }
}
