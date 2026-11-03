<?php

namespace App\Http\Controllers;

use App\Enums\AttemptStatus;
use App\Models\Competition;
use App\Models\CompetitionExam;
use App\Services\Contest\ContestReviewService;
use App\Services\Student\AssessmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * SỬA 3/11 — LUYỆN LẠI đề của kỳ thi / kỳ khảo sát đã kết thúc (học sinh đã tham gia). Không tính điểm, không vào bảng xếp
 * hạng, làm đi làm lại được — xem App\Services\Contest\ContestReviewService.
 */
class ContestReviewController extends Controller
{
    public function __construct(
        private ContestReviewService $reviews,
        private AssessmentService $assessmentService,
    ) {}

    /** POST — mở lượt luyện lại mới rồi vào màn làm đề. */
    public function start(Request $request, int $competition, int $exam): RedirectResponse
    {
        [$c, $e] = $this->load($competition, $exam);
        $user = $request->user();
        if (($reason = $this->reviews->blockReason($user, $e)) !== null) {
            return $this->back($c, $e, $reason);
        }

        $options = $this->reviews->options($e);
        $assessmentId = (int) $request->input('assessment_id', 0);
        if (! isset($options[$assessmentId])) {
            $assessmentId = (int) $this->reviews->preferredAssessment($user, $e, $options);
        }

        try {
            $this->reviews->start($user, $e, $assessmentId);
        } catch (ValidationException $ex) {
            return $this->back($c, $e, (string) (collect($ex->errors())->flatten()->first() ?? 'Chưa mở được lượt luyện lại.'));
        }

        return redirect()->route('competitions.review.take', ['competition' => $c->id, 'exam' => $e->id, 'assessment' => $assessmentId]);
    }

    /** GET — màn làm đề của lượt luyện lại (dùng chung màn làm đề thật). */
    public function take(Request $request, int $competition, int $exam, int $assessment): View|RedirectResponse
    {
        [$c, $e] = $this->load($competition, $exam);
        $user = $request->user();
        if (($reason = $this->reviews->blockReason($user, $e)) !== null) {
            return $this->back($c, $e, $reason);
        }
        $options = $this->reviews->options($e);
        abort_unless(isset($options[$assessment]), 404);

        // Chỉ mở khi đang có lượt LUYỆN LẠI dở của đề này — màn này không bao giờ tự tạo lượt thi thật.
        if (! $this->reviews->hasDraft($user, $assessment)) {
            return $this->back($c, $e, 'Lượt luyện lại đã nộp — bấm "Luyện lại" để làm lượt mới.');
        }

        try {
            $data = $this->assessmentService->buildTakeData($user, $assessment);
        } catch (ValidationException $ex) {
            return $this->back($c, $e, (string) ($ex->errors()['attempt'][0] ?? 'Không mở được lượt luyện lại.'));
        }

        if ($data['attempt']->status !== AttemptStatus::InProgress) {
            return redirect()->route('student.assessment.result', $data['attempt']->id);
        }

        $extras = [
            'exitUrl' => route('competitions.show', ['competition' => $c->id, 'vong' => $e->id]),
            'examTitleOverride' => 'LUYỆN LẠI (không tính điểm) · '.$c->displayTitle().' · '.$e->displayTitle(),
        ];

        return view($data['isPdfMode'] ? 'student.assessment.take-pdf' : 'student.assessment.take', array_merge($data, $extras));
    }

    /** @return array{0: Competition, 1: CompetitionExam} */
    private function load(int $competition, int $exam): array
    {
        $c = Competition::query()->findOrFail($competition);
        $e = CompetitionExam::query()->where('competition_id', $c->id)->findOrFail($exam);

        return [$c, $e];
    }

    private function back(Competition $c, CompetitionExam $e, string $message): RedirectResponse
    {
        return redirect()->route('competitions.show', ['competition' => $c->id, 'vong' => $e->id])->with('contest_notice', $message);
    }
}
