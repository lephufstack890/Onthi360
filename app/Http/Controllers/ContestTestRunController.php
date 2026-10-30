<?php

namespace App\Http\Controllers;

use App\Enums\AttemptSource;
use App\Enums\AttemptStatus;
use App\Models\Attempt;
use App\Models\Competition;
use App\Models\CompetitionExam;
use App\Services\Contest\ContestOrganizerService;
use App\Services\Contest\ContestPaperService;
use App\Services\Student\AssessmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * SỬA 30/10 (khách: "admin làm được lúc nào cũng được, kể cả kỳ sắp diễn ra, để test trước đề xem chấm đã ok chưa — thêm nút
 * làm khảo sát để test; chỉ admin và giáo viên") — LÀM THỬ ĐỀ của một kỳ thi / kỳ khảo sát.
 *
 *  · Ai: chỉ ban tổ chức của kỳ (admin; giáo viên chủ trì kỳ / tổ chức cuộc thi — ContestOrganizerService::canManageRound).
 *  · Khi nào: bất cứ lúc nào (sắp diễn ra / đang diễn ra / đã kết thúc), không cần mật khẩu, không cần đăng ký.
 *  · Lượt làm thử TÁCH HẲN khỏi kỳ: source = staff_test, KHÔNG gắn competition_id / competition_exam_id → không vào bảng xếp
 *    hạng, số người tham gia, bài nộp, nhật ký của kỳ, không khoá việc đổi đề; các trang Luyện tập công khai cũng bỏ qua lượt
 *    này. Chấm bài, chạy thử, nộp, trang kết quả: dùng NGUYÊN màn làm đề / máy chấm thật — đúng thứ cần kiểm tra.
 *  · Mỗi lần bấm "Làm thử" là một lượt mới (lượt làm thử còn dở của cùng đề bị xoá).
 */
class ContestTestRunController extends Controller
{
    public function __construct(
        private ContestOrganizerService $organizers,
        private ContestPaperService $papers,
        private AssessmentService $assessmentService,
    ) {}

    /** POST — tạo lượt làm thử mới rồi mở màn làm đề. */
    public function start(Request $request, int $competition, int $exam): RedirectResponse
    {
        [$c, $e] = $this->authorizeRound($request, $competition, $exam);
        $options = $this->testableAssessments($e);
        if ($options === []) {
            return $this->back($c, $e, 'Kỳ này chưa có đề để làm thử — chọn / chốt đề ở tab đề trước.');
        }

        $assessmentId = (int) $request->input('assessment_id', 0);
        if (! isset($options[$assessmentId])) {
            $assessmentId = (int) array_key_first($options);
        }

        $user = $request->user();
        DB::transaction(function () use ($user, $assessmentId) {
            $this->discardTestDrafts((int) $user->id, $assessmentId);
            Attempt::query()->create([
                'user_id' => $user->id,
                'assessment_id' => $assessmentId,
                'source' => AttemptSource::StaffTest->value,
                'started_at' => now(),
                'status' => AttemptStatus::InProgress->value,
                'is_provisional' => true,
            ]);
        });

        return redirect()->route('competitions.manage.test.take', ['competition' => $c->id, 'exam' => $e->id, 'assessment' => $assessmentId]);
    }

    /** GET — màn làm đề của lượt làm thử (dùng chung màn làm đề của Luyện tập / phòng thi). */
    public function take(Request $request, int $competition, int $exam, int $assessment): View|RedirectResponse
    {
        [$c, $e] = $this->authorizeRound($request, $competition, $exam);
        $options = $this->testableAssessments($e);
        abort_unless(isset($options[$assessment]), 404);

        $user = $request->user();
        // Chỉ mở khi đang có lượt LÀM THỬ dở dang của đề này — không bao giờ để màn này tự tạo lượt thi thật.
        $hasDraft = Attempt::query()->where('user_id', $user->id)->where('assessment_id', $assessment)
            ->where('source', AttemptSource::StaffTest->value)->where('status', AttemptStatus::InProgress->value)->exists();
        if (! $hasDraft) {
            return $this->back($c, $e, 'Lượt làm thử đã nộp hoặc đã đóng — bấm "Làm thử" để bắt đầu lượt mới.');
        }

        try {
            $data = $this->assessmentService->buildTakeData($user, $assessment);
        } catch (ValidationException $ex) {
            return $this->back($c, $e, (string) ($ex->errors()['attempt'][0] ?? 'Không mở được lượt làm thử.'));
        }

        if ($data['attempt']->status !== AttemptStatus::InProgress) {
            return redirect()->route('student.assessment.result', $data['attempt']->id);
        }

        $extras = [
            'exitUrl' => route('competitions.show', ['competition' => $c->id, 'vong' => $e->id]),
            'examTitleOverride' => 'LÀM THỬ · '.$c->displayTitle().' · '.$e->displayTitle().' · '.$options[$assessment],
        ];

        return view($data['isPdfMode'] ? 'student.assessment.take-pdf' : 'student.assessment.take', array_merge($data, $extras));
    }

    /**
     * Các đề làm thử được của kỳ: đề đã chốt (kỳ sinh đề theo ma trận), đề ngân hàng (kỳ chọn nhiều đề), hoặc đề gắn ở kỳ.
     *
     * @return array<int, string> assessment id => nhãn
     */
    public function testableAssessments(CompetitionExam $exam): array
    {
        $out = [];
        foreach ($this->papers->publishedPapers($exam) as $paper) {
            if ($paper->assessment_id !== null) {
                $out[(int) $paper->assessment_id] = (string) ($paper->label ?: ($paper->assessment?->title ?? 'Đề #'.$paper->assessment_id));
            }
        }
        if ($out === []) {
            $ids = $exam->bankAssessmentIds();
            if ($ids !== []) {
                $titles = \App\Models\Assessment::query()->whereIn('id', $ids)->pluck('title', 'id');
                foreach ($ids as $id) {
                    if (isset($titles[$id])) {
                        $out[(int) $id] = (string) $titles[$id];
                    }
                }
            }
        }

        return $out;
    }

    /** @return array{0: Competition, 1: CompetitionExam} */
    private function authorizeRound(Request $request, int $competition, int $exam): array
    {
        $c = Competition::query()->findOrFail($competition);
        $e = CompetitionExam::query()->where('competition_id', $c->id)->findOrFail($exam);
        $user = $request->user();
        abort_unless($this->organizers->canCreateRound($user) && $this->organizers->canManageRound($user, $c, $e), 403,
            'Chỉ admin hoặc giáo viên tổ chức kỳ này mới làm thử được.');

        return [$c, $e];
    }

    /** Xoá lượt làm thử còn dở của người này ở đề này (cùng cách ContestEntryService::discardInProgress()). */
    private function discardTestDrafts(int $userId, int $assessmentId): void
    {
        $ids = Attempt::query()->where('user_id', $userId)->where('assessment_id', $assessmentId)
            ->where('source', AttemptSource::StaffTest->value)->where('status', AttemptStatus::InProgress->value)->pluck('id')->all();
        if ($ids === []) {
            return;
        }
        $answerIds = DB::table('attempt_answers')->whereIn('attempt_id', $ids)->pluck('id')->all();
        if ($answerIds !== [] && Schema::hasTable('judge_submissions')) {
            DB::table('judge_submissions')->whereIn('attempt_answer_id', $answerIds)->delete();
        }
        foreach (['attempt_answers', 'attempt_answer_keys', 'attempt_coding_items'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->whereIn('attempt_id', $ids)->delete();
            }
        }
        Attempt::query()->whereIn('id', $ids)->delete();
    }

    private function back(Competition $c, CompetitionExam $e, string $message): RedirectResponse
    {
        return redirect()->route('competitions.show', ['competition' => $c->id, 'vong' => $e->id])->with('contest_notice', $message);
    }
}
