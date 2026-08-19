<?php

namespace App\Services;

use App\Models\Attempt;
use App\Models\CompetitionExam;
use App\Models\LeaderboardEntry;
use Illuminate\Support\Facades\DB;

class CompetitionLeaderboardService
{

    public function recordIfCompetitionExam(Attempt $attempt): void
    {
        $exams = CompetitionExam::where('assessment_id', $attempt->assessment_id)->get();

        foreach ($exams as $exam) {
            $this->upsertAndRerank($exam, $attempt);
        }
    }

    private function upsertAndRerank(CompetitionExam $exam, Attempt $attempt): void
    {
        DB::transaction(function () use ($exam, $attempt) {
            LeaderboardEntry::updateOrCreate(
                [
                    'scope' => 'competition_exam',
                    'competition_exam_id' => $exam->id,
                    'user_id' => $attempt->user_id,
                ],
                [
                    'competition_id' => $exam->competition_id,
                    'class_room_id' => null,
                    'topic' => null,
                    'score' => $attempt->total_score ?? 0,
                    'computed_at' => now(),
                ]
            );

            $entries = LeaderboardEntry::query()
                ->where('scope', 'competition_exam')
                ->where('competition_exam_id', $exam->id)
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
