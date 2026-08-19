<?php

namespace App\Services;

use App\Models\Attempt;
use App\Models\CompetitionExam;
use App\Models\LeaderboardEntry;
use Illuminate\Support\Facades\DB;

class CompetitionLeaderboardService
{

    /**
     * SỬA 19/8 (fix tận gốc "tái sử dụng đề bị chặn chéo giữa các cuộc thi"): trước đây tra
     * lại CompetitionExam theo assessment_id của attempt — nếu 1 đề được dùng lại ở NHIỀU kỳ
     * thi khác nhau, 1 lượt nộp sẽ bị ghi NHẦM vào bảng xếp hạng của MỌI kỳ thi cùng dùng đề
     * đó (kể cả kỳ thi học sinh chưa từng "vào thi" qua). Giờ chỉ ghi vào ĐÚNG 1 kỳ thi mà
     * chính lượt làm bài này đã được xác nhận thuộc về lúc bắt đầu (Attempt::competition_exam_id,
     * xem AttemptService::startOrResume()) — attempt cũ tạo trước bản vá này sẽ có cột null
     * nên không ghi bảng xếp hạng (chấp nhận được, xem docblock migration
     * ..._add_competition_columns_to_attempts_table.php).
     */
    public function recordIfCompetitionExam(Attempt $attempt): void
    {
        if ($attempt->competition_exam_id !== null) {
            $exam = CompetitionExam::find($attempt->competition_exam_id);

            if ($exam !== null) {
                $this->upsertAndRerank('competition_exam', 'competition_exam_id', $exam->id, $exam->competition_id, $attempt);
            }

            return;
        }

        // Cuộc thi kiểu cũ tham chiếu đề TRỰC TIẾP (Competition::assessment_id, không qua
        // CompetitionExam) — hiếm gặp ở dữ liệu mới (Admin giờ luôn tạo qua "Thêm kỳ thi") nhưng
        // vẫn xử lý cho đủ, ghi thẳng vào scope='competition' thay vì scope='competition_exam'.
        if ($attempt->competition_id !== null) {
            $this->upsertAndRerank('competition', 'competition_id', $attempt->competition_id, $attempt->competition_id, $attempt);
        }
    }

    private function upsertAndRerank(string $scope, string $scopeColumn, int $scopeId, int $competitionId, Attempt $attempt): void
    {
        DB::transaction(function () use ($scope, $scopeColumn, $scopeId, $competitionId, $attempt) {
            LeaderboardEntry::updateOrCreate(
                [
                    'scope' => $scope,
                    $scopeColumn => $scopeId,
                    'user_id' => $attempt->user_id,
                ],
                [
                    'competition_id' => $competitionId,
                    'class_room_id' => null,
                    'topic' => null,
                    'score' => $attempt->total_score ?? 0,
                    'computed_at' => now(),
                ]
            );

            $entries = LeaderboardEntry::query()
                ->where('scope', $scope)
                ->where($scopeColumn, $scopeId)
                ->orderByDesc('score')
                ->orderBy('computed_at')
                ->get(['id', 'rank']);

            foreach ($entries->values() as $i => $entry) {
                $newRank = $i + 1;
                if ((int) $entry->rank !== $newRank) {
                    $entry->update(['rank' => $newRank]);
                }
            }
        });
    }
}
