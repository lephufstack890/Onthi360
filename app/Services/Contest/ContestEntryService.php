<?php

namespace App\Services\Contest;

use App\Enums\AttemptStatus;
use App\Models\Attempt;
use App\Models\Competition;
use App\Models\CompetitionExam;
use App\Models\CompetitionRegistration;
use App\Models\User;
use App\Support\ContestEntryPass;
use App\Support\ContestForms;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * SỬA 24/10 (khách: "khảo sát khi tham gia thì nhập mật khẩu, cuộc thi bình thường thì đăng ký như bình thường") —
 * luật VÀO LÀM BÀI của phòng thi mới, gom một chỗ:
 *
 *  CUỘC THI  : đăng ký một lần cho cả cuộc thi → giáo viên/admin duyệt (hoặc ban tổ chức thêm thẳng) → trong giờ
 *              kỳ thi bấm "Vào thi" → nhận mã đề (ngẫu nhiên hoặc tự chọn, chốt cố định) → làm và nộp 1 lần.
 *  KHẢO SÁT  : không đăng ký, không chờ duyệt → trong giờ kỳ khảo sát nhập mật khẩu ban tổ chức đưa → làm bài,
 *              được làm lại nhiều lần; mỗi lần bắt đầu/tiếp tục đều nhập lại mật khẩu (ContestEntryPass).
 *
 * Lớp chặn cuối cùng vẫn là AttemptService (duyệt + số lượt + mật khẩu khảo sát) — ở đây chỉ dẫn đường cho đúng.
 */
class ContestEntryService
{
    /** Nhập sai mật khẩu tối đa bấy nhiêu lần / 10 phút cho một cuộc thi. */
    private const PASSWORD_TRIES = 8;

    public function registrationFor(?User $user, int $competitionId): ?CompetitionRegistration
    {
        if ($user === null || ! CompetitionRegistration::supported()) {
            return null;
        }

        return CompetitionRegistration::query()
            ->where('competition_id', $competitionId)
            ->where('student_id', $user->id)
            ->first();
    }

    /** Mã đề (1, 2, …) đã giao cho học sinh ở kỳ thi này, null = chưa nhận đề. */
    public function assignedForm(?CompetitionRegistration $registration, CompetitionExam $exam): ?int
    {
        if ($registration === null || ! CompetitionRegistration::supportsHubFields()) {
            return null;
        }

        $no = (int) (((array) $registration->exam_forms)[(string) $exam->id] ?? 0);

        return $no >= 1 && $no <= $exam->formCount() ? $no : null;
    }

    /**
     * Bấm "Bắt đầu làm bài / Xác nhận & làm bài" trong hộp thoại vào thi.
     *
     * @throws ValidationException (khoá 'entry')
     */
    public function enter(User $user, int $competitionId, int $examId, ?string $password, ?int $formNo): CompetitionExam
    {
        [$competition, $exam] = $this->load($competitionId, $examId);

        if ($exam->computedStatus() !== 'ongoing') {
            $this->fail($exam->computedStatus() === 'upcoming' ? 'Kỳ thi chưa đến giờ mở.' : 'Kỳ thi đã kết thúc.');
        }

        if ($exam->assessment_id === null) {
            $this->fail('Kỳ thi này chưa được ban tổ chức gắn đề.');
        }

        if (! CompetitionRegistration::supported()) {
            $this->fail('Máy chủ chưa chạy migration cho tính năng này — báo quản trị viên chạy "php artisan migrate".');
        }

        $registration = $this->registrationFor($user, $competition->id);

        if ($competition->isSurvey()) {
            $this->checkSurveyPassword($user, $competition, (string) $password);

            if ($registration !== null && $registration->status === CompetitionRegistration::STATUS_REJECTED) {
                $this->fail('Bạn đã bị ban tổ chức gỡ khỏi khảo sát này'.($registration->reject_reason ? ' — '.$registration->reject_reason : '').'.');
            }

            if ($registration === null || ! $registration->isApproved()) {
                $values = [
                    'status' => CompetitionRegistration::STATUS_APPROVED,
                    'requested_at' => $registration?->requested_at ?? now(),
                    'approved_at' => now(),
                    'approved_by' => null,
                    'reject_reason' => null,
                ];
                if (CompetitionRegistration::supportsHubFields()) {
                    $values['source'] = CompetitionRegistration::SOURCE_PASSWORD;
                }
                CompetitionRegistration::updateOrCreate(['competition_id' => $competition->id, 'student_id' => $user->id], $values);
            }

            ContestEntryPass::grant($exam->id);

            return $exam;
        }

        // ── Cuộc thi ──
        if ($registration === null || ! $registration->isApproved()) {
            $this->fail($registration?->isPending()
                ? 'Đăng ký của bạn đang chờ giáo viên/ban tổ chức duyệt.'
                : 'Bạn cần đăng ký và được duyệt trước khi vào thi.');
        }

        if (! $this->hasInProgress($user, $exam) && $this->submittedCount($user, $exam) > 0) {
            $this->fail('Bạn đã nộp bài kỳ thi này. Mỗi kỳ thi chỉ có một lượt nộp.');
        }

        $this->ensureForm($registration, $exam, $formNo, true);

        return $exam;
    }

    /**
     * Mở trang làm bài (GET student.competitions.exam): khảo sát phải còn dấu đã nhập mật khẩu; cuộc thi phải có mã đề.
     * Trả về số mã đề để sắp lại thứ tự câu (1 = thứ tự gốc).
     *
     * @throws ValidationException (khoá 'entry')
     */
    public function guardExamPage(User $user, int $competitionId, int $examId): int
    {
        [$competition, $exam] = $this->load($competitionId, $examId);

        if ($competition->isSurvey()) {
            if (! ContestEntryPass::has($exam->id)) {
                $this->fail('Nhập mật khẩu khảo sát để bắt đầu hoặc tiếp tục làm bài.');
            }

            return 1;
        }

        $registration = $this->registrationFor($user, $competition->id);
        if ($registration === null || ! $registration->isApproved()) {
            return 1; // examContext() sẽ báo đúng lý do chưa được duyệt.
        }

        return $this->ensureForm($registration, $exam, null, false);
    }

    public function submittedCount(User $user, CompetitionExam $exam): int
    {
        return Attempt::query()
            ->where('user_id', $user->id)
            ->where('competition_exam_id', $exam->id)
            ->whereNotNull('submitted_at')
            ->count();
    }

    public function hasInProgress(User $user, CompetitionExam $exam): bool
    {
        return Attempt::query()
            ->where('user_id', $user->id)
            ->where('competition_exam_id', $exam->id)
            ->where('status', AttemptStatus::InProgress->value)
            ->exists();
    }

    /** Giao (một lần) và trả về mã đề của học sinh ở kỳ thi. */
    private function ensureForm(CompetitionRegistration $registration, CompetitionExam $exam, ?int $requested, bool $fromDialog): int
    {
        $count = $exam->formCount();
        if ($count <= 1 || ! CompetitionRegistration::supportsHubFields()) {
            return 1;
        }

        $assigned = $this->assignedForm($registration, $exam);
        if ($assigned !== null) {
            return $assigned; // đã nhận đề thì giữ nguyên, kể cả khi tải lại / chọn khác
        }

        if ($exam->assignmentMode() === CompetitionExam::MODE_CHOICE) {
            if ($requested === null || $requested < 1 || $requested > $count) {
                $this->fail($fromDialog ? 'Hãy chọn một mã đề trong danh sách trước khi vào thi.' : 'Chọn mã đề ở phòng thi rồi bấm "Vào thi".');
            }
            $no = $requested;
        } else {
            $no = random_int(1, $count);
        }

        $forms = (array) $registration->exam_forms;
        $forms[(string) $exam->id] = $no;
        $registration->forceFill(['exam_forms' => $forms])->save();

        return $no;
    }

    private function checkSurveyPassword(User $user, Competition $competition, string $password): void
    {
        if (! $competition->hasEntryPassword()) {
            return; // ban tổ chức chưa đặt mật khẩu → vào thẳng (form admin bắt buộc nhập, đây chỉ là dữ liệu cũ)
        }

        $key = 'contest-entry:'.$user->id.':'.$competition->id;
        if (RateLimiter::tooManyAttempts($key, self::PASSWORD_TRIES)) {
            $this->fail('Bạn nhập sai quá nhiều lần. Thử lại sau '.max(1, (int) ceil(RateLimiter::availableIn($key) / 60)).' phút.');
        }

        if (! $competition->checkEntryPassword($password)) {
            RateLimiter::hit($key, 600);
            $this->fail('Mật khẩu chưa đúng. Hãy kiểm tra lại hoặc liên hệ giáo viên.');
        }

        RateLimiter::clear($key);
    }

    /** @return array{0: Competition, 1: CompetitionExam} */
    private function load(int $competitionId, int $examId): array
    {
        $competition = Competition::query()->findOrFail($competitionId);
        $exam = CompetitionExam::query()->where('competition_id', $competition->id)->findOrFail($examId);

        return [$competition, $exam];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['entry' => $message]);
    }

    public static function formLabel(int $no, int $count): ?string
    {
        return $count > 1 ? ContestForms::label($no) : null;
    }
}
