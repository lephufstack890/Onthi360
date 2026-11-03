<?php

namespace App\Services\Contest;

use App\Enums\AttemptSource;
use App\Enums\AttemptStatus;
use App\Http\Controllers\ContestTestRunController;
use App\Models\Attempt;
use App\Models\Competition;
use App\Models\CompetitionExam;
use App\Models\CompetitionExamMakeup;
use App\Models\CompetitionRegistration;
use App\Models\User;
use App\Services\AttemptService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * SỬA 3/11 (khách: "kỳ thi / kỳ khảo sát đã kết thúc thì cho học sinh làm lại bài nhưng không lưu điểm, không lưu bảng xếp
 * hạng, làm đi làm lại được") — LUYỆN LẠI ĐỀ của một kỳ đã kết thúc.
 *
 *  · Ai: học sinh ĐÃ THAM GIA kỳ / cuộc thi (đăng ký đã duyệt — khảo sát là đã từng nhập đúng mật khẩu — hoặc đã có lượt làm ở kỳ).
 *  · Khi nào: chỉ sau giờ kết thúc kỳ; học sinh đang được cho LÀM BÙ thì làm bù trước (lượt làm bù mới tính điểm).
 *  · Lượt luyện lại dùng ĐÚNG cơ chế lượt "Làm thử" của ban tổ chức (ContestTestRunController): source = staff_test, KHÔNG gắn
 *    competition_id / competition_exam_id → không vào bảng xếp hạng, điểm, bài nộp, nhật ký của kỳ, không tính vào Luyện tập
 *    công khai / thống kê. Chấm bài, kết quả: dùng nguyên màn làm đề thật. Làm bao nhiêu lần cũng được — mỗi lần bấm là lượt mới.
 */
class ContestReviewService
{
    public function __construct(
        private readonly ContestEntryService $entry,
        private readonly AttemptService $attempts,
    ) {}

    /** Lý do KHÔNG cho luyện lại (null = được luyện lại). */
    public function blockReason(?User $user, CompetitionExam $exam): ?string
    {
        if ($user === null) {
            return 'Đăng nhập để luyện lại đề.';
        }
        if ($exam->computedStatus() !== 'ended') {
            return 'Kỳ này chưa kết thúc — luyện lại chỉ mở sau giờ kết thúc.';
        }
        if (CompetitionExamMakeup::openFor((int) $user->id, $exam) !== null) {
            return 'Bạn đang được cho làm bù kỳ này — làm bù trước, lượt làm bù mới được tính điểm.';
        }
        if (! $this->participated($user, $exam)) {
            return 'Chỉ học sinh đã tham gia kỳ này mới luyện lại được đề.';
        }
        if ($this->options($exam) === []) {
            return 'Kỳ này không có đề để luyện lại.';
        }

        return null;
    }

    /** Đã tham gia: đăng ký đã duyệt (khảo sát: từng nhập đúng mật khẩu) hoặc đã có lượt làm ở kỳ. */
    public function participated(User $user, CompetitionExam $exam): bool
    {
        if (CompetitionRegistration::supported()) {
            $reg = $this->entry->registrationFor($user, (int) $exam->competition_id);
            if ($reg !== null && $reg->isApproved()) {
                return true;
            }
        }

        return Attempt::query()->where('user_id', $user->id)->where('competition_exam_id', $exam->id)->exists();
    }

    /** @return array<int, string> assessment id => nhãn — cùng danh sách đề với "Làm thử" của ban tổ chức. */
    public function options(CompetitionExam $exam): array
    {
        return app(ContestTestRunController::class)->testableAssessments($exam);
    }

    /** Đề nên mở sẵn: đề học sinh đã làm ở kỳ (lượt gần nhất), không có thì đề đầu tiên. */
    public function preferredAssessment(User $user, CompetitionExam $exam, array $options): ?int
    {
        $mine = Attempt::query()->where('user_id', $user->id)->where('competition_exam_id', $exam->id)
            ->latest('started_at')->value('assessment_id');
        if ($mine !== null && isset($options[(int) $mine])) {
            return (int) $mine;
        }

        return $options === [] ? null : (int) array_key_first($options);
    }

    /** Dữ liệu khối "Luyện lại" ở phòng thi (null = không hiện khối). */
    public function panel(?User $user, Competition $competition, CompetitionExam $exam): ?array
    {
        if ($this->blockReason($user, $exam) !== null) {
            return null;
        }
        $options = $this->options($exam);
        $draft = Attempt::query()->where('user_id', $user->id)->whereIn('assessment_id', array_keys($options))
            ->where('source', AttemptSource::StaffTest->value)->where('status', AttemptStatus::InProgress->value)
            ->latest('started_at')->first();
        $done = Attempt::query()->where('user_id', $user->id)->whereIn('assessment_id', array_keys($options))
            ->where('source', AttemptSource::StaffTest->value)->whereNotNull('submitted_at')->count();

        return [
            'url' => route('competitions.review', ['competition' => $competition->id, 'exam' => $exam->id]),
            'options' => $options,
            'preferred' => $this->preferredAssessment($user, $exam, $options),
            'resumeUrl' => $draft !== null
                ? route('competitions.review.take', ['competition' => $competition->id, 'exam' => $exam->id, 'assessment' => $draft->assessment_id]) : null,
            'doneCount' => $done,
        ];
    }

    /**
     * Mở lượt luyện lại MỚI cho đề đã chọn.
     *
     * @throws ValidationException
     */
    public function start(User $user, CompetitionExam $exam, int $assessmentId): void
    {
        // Lượt thi THẬT còn dở của đề này (vd quên nộp, kỳ đã hết giờ) → nộp nốt như hết giờ. Còn dở thật (đang làm bù) thì chặn,
        // để màn làm đề không bao giờ "nhặt" nhầm lượt thật thành lượt luyện.
        $real = Attempt::query()->where('user_id', $user->id)->where('assessment_id', $assessmentId)
            ->where('status', AttemptStatus::InProgress->value)
            ->where(fn ($q) => $q->whereNull('source')->orWhere('source', '!=', AttemptSource::StaffTest->value))
            ->get();
        foreach ($real as $attempt) {
            $attempt = $this->attempts->finalizeIfExpired($attempt);
            if ($attempt->status === AttemptStatus::InProgress) {
                throw ValidationException::withMessages(['review' => 'Bạn đang có một bài làm dở của đề này — nộp bài đó trước rồi mới luyện lại.']);
            }
        }

        DB::transaction(function () use ($user, $assessmentId) {
            self::discardDrafts((int) $user->id, $assessmentId);
            Attempt::query()->create([
                'user_id' => $user->id,
                'assessment_id' => $assessmentId,
                'source' => AttemptSource::StaffTest->value,
                'started_at' => now(),
                'status' => AttemptStatus::InProgress->value,
                'is_provisional' => true,
            ]);
        });
    }

    public function hasDraft(User $user, int $assessmentId): bool
    {
        return Attempt::query()->where('user_id', $user->id)->where('assessment_id', $assessmentId)
            ->where('source', AttemptSource::StaffTest->value)->where('status', AttemptStatus::InProgress->value)->exists();
    }

    /** Xoá lượt LÀM THỬ / LUYỆN LẠI còn dở (source staff_test) của người này ở đề này — cùng cách ContestTestRunController. */
    public static function discardDrafts(int $userId, int $assessmentId): void
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
}
