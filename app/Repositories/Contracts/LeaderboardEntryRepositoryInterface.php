<?php

namespace App\Repositories\Contracts;

use App\Models\LeaderboardEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface LeaderboardEntryRepositoryInterface extends BaseRepositoryInterface
{

    public function distinctClassRoomIdsForScope(string $scope): array;

    public function countForClassRoomScope(int $classRoomId, string $scope): int;

    public function entriesForCompetition(int $competitionId): Collection;

    public function entriesForCompetitionExam(int $competitionExamId): Collection;

    public function entriesForClassRoom(int $classRoomId): Collection;

    /**
     * SỬA 12/9 (dựng màn Cuộc thi theo source mới) — điểm cao nhất + số người của NHIỀU kỳ
     * thi trong 1 truy vấn gộp, để trang danh sách cuộc thi không phải hỏi DB theo từng vòng.
     */
    public function statsForCompetitionExams(array $competitionExamIds): Collection;

    /** SỬA 12/9 — mọi dòng xếp hạng tổng (scope=competition) của nhiều cuộc thi, đã xếp sẵn theo hạng; tầng dịch vụ tự nhóm và cắt Top N. */
    public function entriesForCompetitions(array $competitionIds): Collection;

}
