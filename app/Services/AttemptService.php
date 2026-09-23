<?php

namespace App\Services;

use App\Enums\AssessmentType;
use App\Enums\AttemptSource;
use App\Enums\AttemptStatus;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\CompetitionStatus;
use App\Enums\ContentStatus;
use App\Enums\QuestionType;
use App\Enums\VerdictStatus;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Attempt;
use App\Jobs\GradeCodingAnswerJob;
use App\Models\AttemptAnswer;
use App\Models\Competition;
use App\Models\CompetitionExam;
use App\Models\CompetitionRegistration;
use App\Models\JudgeSubmission;
use App\Models\Question;
use App\Models\SessionResource;
use App\Models\User;
use App\Repositories\Contracts\AttemptAnswerRepositoryInterface;
use App\Repositories\Contracts\AttemptRepositoryInterface;
use App\Repositories\Contracts\AttendanceRepositoryInterface;
use App\Repositories\Contracts\ClassEnrollmentRepositoryInterface;
use App\Repositories\Contracts\ClassSessionRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Luồng "học sinh làm bài & nộp bài" thật (trước đây chỉ có UI tĩnh minh họa, xem TODO cũ
 * ở Services\Student\AssessmentService::buildTakeData). Chấm điểm tự động ngay cho MCQ/điền
 * đáp án (so khớp grading_config); câu lập trình chỉ ghi nhận bài nộp ở trạng thái "Queued"
 * — CHƯA có sandbox chấm code thật (JudgeSubmission), việc đó nằm ngoài phạm vi đợt này.
 *
 * startOrResume() PHẢI kiểm tra đủ 3 lớp trước khi mở lượt làm bài (note họp 13/8, 7.1/7.3):
 * (1) Assignment còn mở CHO ĐÚNG học sinh này (isOpenNowFor — có tính ca thi nếu có, note
 * họp 13/8 mục 7) + học sinh thực sự thuộc lớp được giao (không tin client, 16 mục 3); (2)
 * nếu đề gắn với 1 Material (chương/mục sách) thì phải qua AccessGateService::
 * canAccessMaterial() — bộ máy trung tâm đã cài đúng 7.1/7.3, KHÔNG viết lại logic quyền
 * ở đây.
 */
class AttemptService
{
    public function __construct(
        private readonly AttemptRepositoryInterface $attempts,
        private readonly AttemptAnswerRepositoryInterface $attemptAnswers,
        private readonly ClassSessionRepositoryInterface $classSessions,
        private readonly AttendanceRepositoryInterface $attendances,
        private readonly ClassEnrollmentRepositoryInterface $classEnrollments,
        private readonly AccessGateService $accessGate,
        // SỬA 19/8 (Giai đoạn 5 — "Tự động ghi bảng xếp hạng"): xem CompetitionLeaderboardService.
        private readonly CompetitionLeaderboardService $competitionLeaderboard,
        // SỬA (nối máy chấm Judge0 thật) — xem gradePendingCodingAnswers() bên dưới, nơi DUY
        // NHẤT gọi ra Judge0 trong file này.
        private readonly CodeJudgingService $codeJudging,
    ) {}

    /**
     * Mở lượt làm bài mới hoặc tiếp tục lượt đang dở (in_progress) cho 1 đề. Nếu đề gắn
     * với 1 Assignment (giao qua lớp), lượt làm bài mang theo class_room_id + assignment_id
     * và kích hoạt điểm danh tự động (xem autoCheckIn()).
     *
     * @throws ValidationException nếu đã hết lượt làm lại theo resubmission_policy (6.3),
     *                             nếu bài giao đã đóng/chưa mở (hoặc chưa tới ca thi của
     *                             riêng học sinh này), nếu học sinh không thuộc lớp được
     *                             giao, hoặc nếu chưa đủ quyền học liệu (7.1/7.3).
     */
    public function startOrResume(User $user, Assessment $assessment, ?Assignment $assignment = null): Attempt
    {
        $existing = $this->attempts->inProgressForUserAndAssessment($user->id, $assessment->id);

        if ($existing !== null) {
            return $existing;
        }

        if ($assignment !== null) {
            $this->assertAssignmentAccessible($user, $assignment);
        }

        $competitionContext = $this->assertMaterialAccessible($user, $assessment, $assignment);

        $this->assertResubmissionAllowed($user, $assessment, $competitionContext);

        // SỬA 9/9 (5) — đề mở qua hoạt động buổi học không có Assignment nhưng vẫn thuộc 1 lớp
        // cụ thể (xem publishedActivityClassRoomIdFor()), gắn vào để thầy cô còn tra được bài làm
        // theo lớp.
        $classRoomId = $assignment?->class_room_id ?? ($competitionContext['classRoomId'] ?? null);

        $attempt = $this->attempts->create([
            'user_id' => $user->id,
            'assessment_id' => $assessment->id,
            'assignment_id' => $assignment?->id,
            'class_room_id' => $classRoomId,
            'competition_id' => $competitionContext['competitionId'],
            'competition_exam_id' => $competitionContext['competitionExamId'],
            'source' => ($assignment !== null ? AttemptSource::Assignment : AttemptSource::PublicPractice)->value,
            'started_at' => now(),
            'status' => AttemptStatus::InProgress->value,
            'is_provisional' => true,
        ]);

        // Tự điểm danh CHỈ áp dụng cho bài giao qua lớp như trước. Mở đề từ hoạt động buổi học
        // thì KHÔNG tự điểm danh: học sinh có thể làm bài ở nhà sau buổi học, đánh dấu có mặt là sai.
        if ($assignment !== null && $classRoomId !== null) {
            $this->autoCheckIn($user, $classRoomId);
        }

        return $attempt;
    }

    /**
     * SỬA 19/8 (fix tận gốc "tái sử dụng đề bị chặn chéo giữa các cuộc thi", báo cáo thật của
     * Admin khi test Giai đoạn 5): trước đây resubmission_policy['max_attempts'] luôn đếm theo
     * assessment_id TOÀN CỤC (bất kể đề có đang gắn cuộc thi nào hay không) — nếu 1 đề được
     * dùng lại ở NHIỀU cuộc thi khác nhau, học sinh làm cuộc thi A xong bị tính "đã dùng lượt"
     * luôn ở cuộc thi B dù 2 cuộc thi độc lập, không có cách nào tách bạch. Giờ: nếu đề ĐANG
     * được 1 cuộc thi/kỳ thi tham chiếu ($competitionContext có id — xem assertMaterialAccessible()/
     * competitionEntryDecision()), đếm lượt đã nộp CHỈ TRONG PHẠM VI cuộc thi/kỳ thi ĐÓ; đề
     * KHÔNG gắn cuộc thi nào (Tự luyện/Bài giao) giữ NGUYÊN hành vi cũ — đếm theo assessment_id
     * toàn cục, không đổi gì cho 2 luồng đó.
     *
     * SỬA 19/8 (2, báo cáo thật của Admin khi test: "làm bài rồi mà vẫn cho làm tiếp"):
     * resubmission_policy['max_attempts'] là 1 CỘT CHUNG của Assessment, chỉ có form tạo đề ở
     * Teacher (Bộ đề) mới set được — Admin tạo/gắn đề vào cuộc thi KHÔNG có ô nào để đặt số
     * lượt làm lại, nên hầu hết đề đấu cuộc thi có resubmission_policy = null. Trước đây
     * max_attempts === null thì return sớm ở ngay trên (KHÔNG giới hạn gì cả) — với đề Tự
     * luyện/Bài giao thì đúng ý (null = không giới hạn, giáo viên có thể cố tình không đặt),
     * nhưng với đề đấu CUỘC THI thì sai hẳn yêu cầu gốc "mỗi học sinh chỉ được làm 1 lần" (xem
     * SỬA 18/8 ở Public\CompetitionService — chỉ ẩn nút ở UI, KHÔNG chặn thật ở server) — học
     * sinh nộp bài xong vẫn vào làm lại được vô hạn lần qua route take. Sửa: CÓ bối cảnh cuộc
     * thi/kỳ thi ($isCompetitionScoped) mà resubmission_policy null thì mặc định max_attempts=1
     * (cuộc thi luôn 1 lượt trừ khi sau này Admin có ô cấu hình riêng — hiện chưa có cột nào ở
     * competitions/competition_exams cho việc này, xem migration 2 bảng đó); KHÔNG có bối cảnh
     * cuộc thi (Tự luyện/Bài giao) thì null vẫn là KHÔNG giới hạn như cũ, không đổi gì.
     */
    private function assertResubmissionAllowed(User $user, Assessment $assessment, array $competitionContext): void
    {
        $isCompetitionScoped = $competitionContext['competitionId'] !== null || $competitionContext['competitionExamId'] !== null;

        $maxAttempts = $assessment->resubmission_policy['max_attempts'] ?? ($isCompetitionScoped ? 1 : null);

        if ($maxAttempts === null) {
            return;
        }

        $submittedCount = match (true) {
            $competitionContext['competitionId'] !== null => $this->attempts->countSubmittedForUserAndCompetition($user->id, $competitionContext['competitionId']),
            $competitionContext['competitionExamId'] !== null => $this->attempts->countSubmittedForUserAndCompetitionExam($user->id, $competitionContext['competitionExamId']),
            default => $this->attempts->countSubmittedForUserAndAssessment($user->id, $assessment->id),
        };

        if ($submittedCount >= (int) $maxAttempts) {
            throw ValidationException::withMessages([
                'attempt' => 'Bạn đã dùng hết số lượt làm lại cho '.($isCompetitionScoped ? 'cuộc thi' : 'đề').' này ('.$maxAttempts.' lượt).',
            ]);
        }
    }

    /**
     * (1) Bài giao phải đang mở CHO ĐÚNG học sinh này theo thời gian máy chủ, KHÔNG tin
     * trạng thái/giờ do client gửi lên (16 mục 3) — dùng isOpenNowFor() thay vì isOpenNow()
     * vì khi bài giao có chia ca thi (note họp 13/8, mục 7: "Các kỳ thi nếu đông quá thì
     * mình chia thành các ca thi để chống tấn công ddos"), mỗi học sinh chỉ được vào đúng
     * khung giờ ca của riêng mình, không phải trọn khung opens_at/closes_at chung. (2) Học
     * sinh phải thực sự đang enroll (active) vào đúng lớp được giao đề này — chặn trường
     * hợp học sinh đoán/gõ thẳng URL attempt của lớp khác.
     */
    private function assertAssignmentAccessible(User $user, Assignment $assignment): void
    {
        if (! $this->classEnrollments->existsActiveForUserAndClassRoom($user->id, $assignment->class_room_id)) {
            throw ValidationException::withMessages([
                'attempt' => 'Bạn không thuộc lớp được giao đề này.',
            ]);
        }

        if (! $assignment->isOpenNowFor($user->id)) {
            throw ValidationException::withMessages([
                'attempt' => $this->assignmentClosedMessage($assignment, $user->id),
            ]);
        }
    }

    /** Thông báo đóng/chưa mở — nêu rõ đúng ca thi của học sinh nếu bài giao có chia ca (9 mục "nêu đúng lý do trước khi kêu gọi hành động"). */
    private function assignmentClosedMessage(Assignment $assignment, int $userId): string
    {
        if (! $assignment->hasShifts()) {
            return 'Bài giao này hiện không mở (đã đóng hoặc chưa tới giờ mở).';
        }

        $window = $assignment->shiftWindowFor($userId);

        return sprintf(
            'Bạn thuộc Ca %d/%d của bài thi này: %s – %s. Ngoài khung giờ ca của bạn thì chưa/không còn được vào làm bài (chia ca thi chống nghẽn khi đông thí sinh).',
            $window['index'] + 1,
            $window['count'],
            $window['opens_at']?->format('H:i d/m/Y') ?? '—',
            $window['closes_at']?->format('H:i d/m/Y') ?? '—',
        );
    }

    /**
     * Nếu đề thi này được trỏ tới từ 1 Material (chương/mục sách, quan hệ ngược của
     * Material::assessment_id — xem Assessment::materials()), phải qua đúng
     * AccessGateService::canAccessMaterial() trước khi cho mở lượt làm bài — cùng một cửa
     * duy nhất mà trang đọc học liệu dùng (7.1/7.3), không kiểm tra quyền rời rạc ở đây.
     *
     * SỬA 19/8 (Giai đoạn 4 — vá lỗ hổng "đoán URL vào làm bài miễn phí"): trước đây đề
     * KHÔNG gắn Material nào thì hàm này bỏ qua luôn (return sớm) — đúng cho đề Tự luyện
     * (AssessmentType::Practice, vốn được thiết kế mở tự do, xem PracticeService) VÀ đúng cho
     * đề đấu của Cuộc thi công khai (Competition/CompetitionExam tham chiếu tới — 11.1 "cuộc
     * thi chỉ tham chiếu đề để tổ chức sự kiện", cố ý mở công khai, KHÔNG qua Assignment/
     * Material/Product — xem Public\CompetitionService), NHƯNG với đề PDF loại Bài giao/Đề thi
     * (Giai đoạn 1-3, đa số đề PDF hiện KHÔNG gắn Material vì Bộ đề chỉ tạo Assessment trơn —
     * xem PdfBulkImportService, cố ý chưa bắt buộc gắn Product/Material ngay lúc tạo) thì việc
     * bỏ qua này lại thành lỗ hổng: học sinh chỉ cần biết/đoán đúng assessment_id là vào làm
     * bài được, KHÔNG qua Assignment (route student.assessment.take không bắt buộc truyền
     * assignment). Vì vậy, thứ tự kiểm tra: (1) Practice luôn cho qua; (2) đề ĐANG được 1
     * Competition/CompetitionExam tham chiếu tới VÀ cuộc thi/kỳ thi đó ĐANG MỞ (xem
     * competitionEntryDecision() — Giai đoạn 5, SỬA 19/8) thì cho qua; nếu có tham chiếu
     * nhưng chưa/không còn mở thì CHẶN LUÔN với lý do rõ ràng (KHÔNG rơi xuống kiểm tra
     * Material bên dưới — đề đấu Cuộc thi không nên "lách" qua đường Material để vào thi
     * ngoài giờ); nếu KHÔNG được cuộc thi/kỳ thi nào tham chiếu tới thì rơi xuống bước (4)
     * như đề PDF thường; (3) có Assignment hợp lệ thì coi như đã qua đủ cửa ở
     * assertAssignmentAccessible() phía trên rồi, cho qua tiếp (giáo viên giao bài cho lớp là
     * đủ căn cứ, không bắt buộc phải có Material/Product song song); (4) có Material gắn thì
     * đi đúng AccessGateService như cũ; (5) không khớp trường hợp nào ở trên thì đây là đề PDF
     * đứng một mình chưa gắn vào đâu cả — chặn hẳn, không cho vào làm bài "chùa". Đề muốn cho
     * học sinh làm mà không giao qua lớp thì phải gắn Product (màn "Gắn vào học liệu để bán"
     * ở Admin, dùng lại Admin\ContentService::materialStore()).
     *
     * SỬA 19/8 (fix tận gốc "chặn nhầm đề trong cuộc thi", báo cáo thật của Admin khi test):
     * bước (2) TRƯỚC ĐÂY chỉ chạy khi $assessment->type === AssessmentType::CompetitionPaper
     * — nhưng đi kiểm tra khắp code (Admin\CompetitionService::store()/update()/storeExam()/
     * updateExam()) thì KHÔNG có chỗ nào từng gán type=CompetitionPaper cho đề khi Admin gắn
     * 1 đề có sẵn vào cuộc thi/kỳ thi (form chọn đề dùng LUÔN danh sách đề có sẵn, giữ nguyên
     * type gốc — thường là 'exam' từ lúc tạo ở Bộ đề, KHÔNG PHẢI 'competition_paper'). Nghĩa
     * là bước (2) THỰC TẾ KHÔNG BAO GIỜ CHẠY cho các cuộc thi thật — mọi đề đấu (thường không
     * gắn Material, đúng như PdfBulkImportService) rơi thẳng xuống bước (4)/(5) và bị chặn
     * nhầm với đúng thông báo "chưa giao qua lớp và chưa gắn học liệu" dù cuộc thi đang mở
     * bình thường. Sửa: bỏ điều kiện lọc theo `type`, LUÔN gọi competitionEntryDecision() —
     * hàm này tự tra theo assessment_id trên Competition/CompetitionExam (không phụ thuộc cột
     * type của Assessment), nên vẫn trả về null đúng cách cho đề không liên quan gì tới cuộc
     * thi nào (rơi xuống Assignment/Material như cũ, không đổi hành vi cho luồng đó).
     */
    /**
     * SỬA 19/8 (fix tận gốc "tái sử dụng đề bị chặn chéo giữa các cuộc thi"): trước đây trả về
     * void — giờ trả về bối cảnh cuộc thi/kỳ thi ĐANG mở mà đề này thuộc về (competitionId/
     * competitionExamId, cả 2 null nếu đề không gắn cuộc thi nào ĐANG mở), để startOrResume()
     * dùng ghi lại đúng vào Attempt.competition_id/competition_exam_id ngay lúc tạo, và
     * assertResubmissionAllowed() đếm lượt đã nộp theo ĐÚNG cuộc thi/kỳ thi đó thay vì theo
     * assessment_id toàn cục — xem docblock assertResubmissionAllowed().
     *
     * @return array{competitionId: ?int, competitionExamId: ?int}
     */
    private function assertMaterialAccessible(User $user, Assessment $assessment, ?Assignment $assignment): array
    {
        // SỬA 9/9 (5) — thêm 'classRoomId' vào ngữ cảnh trả về: đề mở qua HOẠT ĐỘNG buổi học
        // (không có Assignment) vẫn cần biết thuộc lớp nào để bài làm gắn đúng lớp.
        $noCompetitionContext = ['competitionId' => null, 'competitionExamId' => null, 'classRoomId' => null];

        if ($assessment->type === AssessmentType::Practice) {
            return $noCompetitionContext;
        }

        $decision = $this->competitionEntryDecision($user, $assessment);

        if ($decision !== null) {
            if (! $decision['open']) {
                throw ValidationException::withMessages(['attempt' => $decision['message']]);
            }

            return ['competitionId' => $decision['competitionId'], 'competitionExamId' => $decision['competitionExamId'], 'classRoomId' => null];
        }
        // $decision === null: đề này chưa được cuộc thi/kỳ thi nào tham chiếu tới — rơi xuống
        // kiểm tra Assignment/Material bên dưới như 1 đề PDF thường.

        if ($assignment !== null) {
            return $noCompetitionContext;
        }

        // SỬA 9/9 (5) (khách: "học sinh có thể click vào làm được đề trong hoạt động") — đề nằm
        // trong 1 HOẠT ĐỘNG ĐÃ PHÁT của buổi học thuộc lớp mà học sinh này đang học chính là một
        // đường được phép làm bài: giáo viên đã chủ động bấm ▶ phát cho đúng lớp đó. Kiểm tra
        // NGAY TẠI ĐÂY (tầng server) chứ không dựa vào việc giao diện có hiện nút hay không —
        // học sinh biết assessment_id vẫn có thể gọi thẳng route làm bài.
        $activityClassRoomId = $this->publishedActivityClassRoomIdFor($user, $assessment);

        if ($activityClassRoomId !== null) {
            return ['competitionId' => null, 'competitionExamId' => null, 'classRoomId' => $activityClassRoomId];
        }

        $material = $assessment->materials()->first();

        if ($material === null) {
            throw ValidationException::withMessages([
                'attempt' => 'Đề này chưa được giao qua lớp, chưa nằm trong hoạt động buổi học nào đã phát, và cũng chưa gắn vào học liệu để mở bán/kích hoạt — bạn chưa có quyền làm đề này.',
            ]);
        }

        $decision = $this->accessGate->canAccessMaterial($user, $material, $assignment?->classRoom);

        if (! $decision->allowed) {
            throw ValidationException::withMessages(['attempt' => $decision->message]);
        }

        return $noCompetitionContext;
    }

    /**
     * SỬA 9/9 (5) — trả về class_room_id nếu đề $assessment đang nằm trong 1 hoạt động ĐÃ PHÁT
     * (session_activities.published_at khác null) của một buổi học thuộc lớp mà $user đang là
     * học sinh ĐANG HỌC (class_enrollments.status = 'active'); ngược lại trả null.
     *
     * Hoạt động chưa phát KHÔNG tính — đó chính là ý nghĩa của nút ▶ bên giáo viên.
     */
    private function publishedActivityClassRoomIdFor(User $user, Assessment $assessment): ?int
    {
        // Đề còn Nháp/Lưu trữ thì KHÔNG mở đường này: đề nháp có thể còn thiếu đáp án, cho học
        // sinh làm là chấm sai. Giáo viên phải Phát hành đề trước (cùng luật với mọi đường khác).
        if ($assessment->status !== ContentStatus::Published) {
            return null;
        }

        $row = SessionResource::query()
            ->join('session_activities', 'session_activities.id', '=', 'session_resources.activity_id')
            ->join('class_sessions', 'class_sessions.id', '=', 'session_resources.class_session_id')
            ->join('class_enrollments', 'class_enrollments.class_room_id', '=', 'class_sessions.class_room_id')
            ->where('session_resources.assessment_id', $assessment->id)
            ->whereNotNull('session_activities.published_at')
            ->where('class_enrollments.student_id', $user->id)
            ->where('class_enrollments.status', 'active')
            ->select('class_sessions.class_room_id')
            ->first();

        return $row !== null ? (int) $row->class_room_id : null;
    }

    /**
     * SỬA 19/8 (Giai đoạn 5 — "Chặn cửa sổ giờ thi ở server"): trước đây (Giai đoạn 4) chỉ
     * kiểm tra CÓ được cuộc thi/kỳ thi nào tham chiếu tới hay không, KHÔNG kiểm tra cuộc
     * thi/kỳ thi đó CÒN ĐANG MỞ hay không — học sinh biết assessment_id vẫn vào thi được cả
     * khi cuộc thi chưa tới giờ/đã hết giờ/đã lưu trữ, vì trang public/competitions/show.
     * blade.php chỉ ẨN nút "Vào thi" ở giao diện (canJoinDirectly) chứ KHÔNG có gì chặn thật
     * ở server nếu học sinh vẫn gọi thẳng route student.assessment.take. Hàm này kiểm tra
     * đúng logic đó (Competition::computedStatus()/CompetitionExam::isOngoing(), tính THEO
     * GIỜ HIỆN TẠI, không tin cột status lưu sẵn — 16 mục 3) ở tầng server, cùng 1 nguồn sự
     * thật với CTA "Vào thi" ở Public\CompetitionService (2 nơi PHẢI khớp nhau, không tự bịa
     * luật riêng ở đây).
     *
     * 1 đề có thể được NHIỀU cuộc thi/kỳ thi tham chiếu tới cùng lúc (hiếm nhưng không cấm) —
     * chỉ cần 1 trong số đó đang mở là đủ cho qua, và attempt sẽ được gắn vào ĐÚNG cuộc
     * thi/kỳ thi đầu tiên tìm thấy đang mở đó (competitionId/competitionExamId — SỬA 19/8,
     * xem docblock assertMaterialAccessible()).
     *
     * SỬA 19/8 (3, báo cáo thật của Admin khi test: "1 cuộc thi nhiều kỳ thi, làm xong 1 kỳ mà
     * trang vẫn hiện 'Vào thi'"): trước đây $directCompetitions lấy MỌI Competition có
     * assessment_id = đề này, KỂ CẢ cuộc thi ĐANG DÙNG kỳ thi con (CompetitionExam) — do
     * migration backfill (create_competition_exams_table) luôn copy Competition::assessment_id
     * sang CompetitionExam đầu tiên (order=1) NHƯNG KHÔNG XOÁ cột assessment_id gốc ở
     * Competition, và Admin vẫn có thể tự sửa "Đề/bộ bài tham chiếu" ở cấp cuộc thi song song
     * với việc thêm kỳ thi con — nên với cuộc thi nhiều vòng, đề của round 1 trùng
     * Competition::assessment_id, khiến vòng lặp $directCompetitions bên dưới bắt nhầm TRƯỚC
     * cả $exams và trả về competitionId (competitionExamId = null). Attempt bị gắn nhầm vào
     * competition_id thay vì đúng competition_exam_id của round đó → hasSubmittedAttemptFor
     * CompetitionExam() (Public\CompetitionService) không tìm thấy gì → nút "Vào thi" không
     * bao giờ chuyển thành "Đã làm" dù học sinh đã nộp bài. Sửa: loại khỏi $directCompetitions
     * bất kỳ Competition nào ĐÃ có kỳ thi con (examSittings) — cuộc thi kiểu nhiều vòng PHẢI
     * luôn được vào qua đúng round cụ thể, không được "lách" qua đường tham chiếu trực tiếp ở
     * cấp cuộc thi nữa, dù cột assessment_id cũ vẫn còn đó.
     *
     * @return array{open: bool, message: ?string, competitionId: ?int, competitionExamId: ?int}|null
     *         null nếu đề KHÔNG được cuộc thi/kỳ thi nào tham chiếu tới.
     */
    private function competitionEntryDecision(User $user, Assessment $assessment): ?array
    {
        $directCompetitions = Competition::where('assessment_id', $assessment->id)
            ->whereDoesntHave('examSittings')
            ->get();
        $exams = CompetitionExam::where('assessment_id', $assessment->id)->with('competition')->get();

        if ($directCompetitions->isEmpty() && $exams->isEmpty()) {
            return null;
        }

        foreach ($directCompetitions as $competition) {
            if ($competition->computedStatus() === CompetitionStatus::Ongoing) {
                // SỬA 19/9 — đúng giờ thôi CHƯA đủ: phải được ban tổ chức duyệt đơn đăng ký.
                if (! $this->isApprovedForCompetition($user, $competition->id)) {
                    return $this->notApprovedDecision();
                }

                return ['open' => true, 'message' => null, 'competitionId' => $competition->id, 'competitionExamId' => null];
            }
        }

        foreach ($exams as $exam) {
            $competition = $exam->competition;

            if ($competition === null) {
                continue;
            }

            // SỬA 24/8 (v6, khách chốt sau khi test thật "Cuộc thi B" 2 vòng — xem docblock y
            // hệt ở Public\CompetitionService::showData()): BỎ hẳn việc xét trạng thái CẤP
            // CUỘC THI cha (Archived/Published, thêm ở SỬA 19/8 (4)) để quyết định 1 kỳ thi con
            // có mở hay không — thực tế gây đúng lỗi: cuộc thi nhiều vòng có đặt starts_at/
            // ends_at riêng ở CẤP CUỘC THI, qua giờ kết thúc CỦA CUỘC THI làm computedStatus()
            // cấp cuộc thi nhảy sang "Đã công bố" trong khi 1 kỳ thi con khác (vd Vòng 2) vẫn
            // đang thật sự "Đang diễn ra" theo đúng giờ RIÊNG của nó — bị khoá lây oan, học
            // sinh bấm "Vào thi" vẫn báo lỗi dù nút đã hiện đúng ở UI. Giờ CHỈ còn xét
            // $exam->isOngoing() (giờ giấc riêng của đúng kỳ thi này) — khớp đúng gate mới ở
            // Public\CompetitionService::showData() (2 nơi PHẢI luôn khớp nhau). Muốn khoá 1
            // kỳ thi cụ thể (đã công bố kết quả, hoặc muốn đóng sớm) thì sửa ends_at của ĐÚNG
            // kỳ thi đó, không dùng trạng thái/thời hạn cấp cuộc thi để khoá gián tiếp nữa.
            if ($exam->isOngoing()) {
                // SỬA 19/9 — duyệt tính ở CẤP CUỘC THI (một đơn dùng cho mọi vòng), không bắt
                // học sinh đăng ký lại từng vòng: thi nhiều vòng vẫn là một cuộc thi.
                if (! $this->isApprovedForCompetition($user, $competition->id)) {
                    return $this->notApprovedDecision();
                }

                return ['open' => true, 'message' => null, 'competitionId' => null, 'competitionExamId' => $exam->id];
            }
        }

        return [
            'open' => false,
            'message' => 'Cuộc thi/kỳ thi của đề này hiện không mở (chưa tới giờ, đã kết thúc, hoặc đã lưu trữ) — bạn chưa thể vào làm bài lúc này.',
            'competitionId' => null,
            'competitionExamId' => null,
        ];
    }

    /**
     * SỬA 19/9 (khách: "click đăng ký tham gia thì admin sẽ duyệt, duyệt xong học sinh mới vào
     * được") — học sinh này đã được duyệt vào cuộc thi đó chưa.
     *
     * Đây là CHỐT CHẶN THẬT của cả tính năng: giao diện có ẩn nút hay không cũng không quan
     * trọng, gõ thẳng địa chỉ đề vẫn phải đi qua đây. Phần hiển thị ở
     * Public\CompetitionService PHẢI nói đúng cùng một luật này (2 nơi luôn phải khớp).
     *
     * Máy chủ chưa chạy migration tạo bảng đăng ký thì trả TRUE — giữ nguyên hành vi cũ (cứ
     * đúng giờ là vào) thay vì khoá sạch mọi cuộc thi đang chạy chỉ vì quên `artisan migrate`.
     */
    private function isApprovedForCompetition(User $user, int $competitionId): bool
    {
        if (! CompetitionRegistration::supported()) {
            return true;
        }

        return CompetitionRegistration::query()
            ->where('competition_id', $competitionId)
            ->where('student_id', $user->id)
            ->where('status', CompetitionRegistration::STATUS_APPROVED)
            ->exists();
    }

    /** @return array{open: bool, message: string, competitionId: null, competitionExamId: null} */
    private function notApprovedDecision(): array
    {
        return [
            'open' => false,
            'message' => 'Bạn chưa được ban tổ chức duyệt tham gia cuộc thi này — mở trang Cuộc thi, bấm "Chi tiết cuộc thi" rồi gửi đăng ký tham gia.',
            'competitionId' => null,
            'competitionExamId' => null,
        ];
    }

    /**
     * Điểm danh tự động (source=auto) khi học sinh vào làm bài trong lúc buổi học của lớp
     * đang diễn ra — note họp: "Học sinh vào lớp để làm bài thì sẽ được điểm danh luôn".
     * Không ghi đè nếu đã có điểm danh (vd giáo viên đã điểm danh tay trước) — firstOrCreate.
     */
    private function autoCheckIn(User $user, int $classRoomId): void
    {
        $session = $this->classSessions->currentlyInProgressForClassRoomIds([$classRoomId])->first();

        if ($session === null) {
            return;
        }

        $this->attendances->query()->firstOrCreate(
            ['class_session_id' => $session->id, 'student_id' => $user->id],
            ['status' => AttendanceStatus::Present->value, 'source' => AttendanceSource::Auto->value]
        );
    }

    /**
     * Hạn nộp thật của 1 lượt làm bài — theo giờ MÁY CHỦ, không tin đồng hồ client (16 mục 3).
     * Là mốc SỚM NHẤT trong 2 nguồn (có cái nào tính cái đó, không có cái nào thì không giới
     * hạn — giữ đúng hành vi cũ cho đề không đặt duration_minutes/không giao qua lớp):
     *  (1) started_at + assessment.duration_minutes — thời lượng làm bài của riêng lượt này;
     *  (2) khung giờ ca thi của assignment (Assignment::shiftWindowFor(), có thể sớm hơn nếu
     *      học sinh bắt đầu làm bài gần sát giờ đóng bài giao).
     *  (3) SỬA 19/9 (6) — ends_at của VÒNG THI (competition_exams) và của CUỘC THI
     *      (competitions) mà lượt làm bài này thuộc về.
     *
     * Vì sao phải có (3): trước đây thiếu nó thì thí sinh vào thi sát giờ đóng vòng vẫn được
     * làm trọn thời lượng của đề. Ví dụ vòng đóng 10:00, đề dài 60 phút, vào lúc 9:55 -> hạn
     * nộp tính ra 10:55, tức là làm thêm 55 phút SAU KHI vòng thi đã đóng, trong khi người
     * vào đúng giờ chỉ có 60 phút. Lấy mốc sớm nhất nên ai vào muộn thì mất phần thời gian
     * tương ứng — đúng luật của một cuộc thi.
     *
     * Dùng CHUNG cho cả (a) đồng hồ đếm ngược hiển thị ở client (chỉ để NHÌN, không phải nơi
     * chặn) và (b) chặn thật ở server trong saveAnswer()/isExpired() bên dưới.
     */
    public function deadlineFor(Attempt $attempt): ?Carbon
    {
        $deadline = null;

        $durationMinutes = $attempt->assessment?->duration_minutes;
        if ($durationMinutes !== null && $attempt->started_at !== null) {
            $deadline = $attempt->started_at->copy()->addMinutes((int) $durationMinutes);
        }

        if ($attempt->assignment_id !== null && $attempt->assignment !== null) {
            $closesAt = $attempt->assignment->shiftWindowFor($attempt->user_id)['closes_at'] ?? null;

            if ($closesAt !== null) {
                $deadline = $deadline === null ? $closesAt : $deadline->min($closesAt);
            }
        }

        /*
         * SỬA 19/9 (6) — nguồn (3): giờ đóng của vòng thi, rồi tới giờ đóng của cuộc thi.
         *
         * Đọc qua quan hệ (competitionExam/competition) chứ không truy vấn thêm ở đây; hai cột
         * competition_exam_id / competition_id đã được ghi vào lượt làm bài ngay lúc tạo
         * (startOrResume), nên lượt nào không thuộc cuộc thi thì cả hai đều null và khối này
         * không đụng gì tới $deadline — đề Tự luyện/Bài giao giữ NGUYÊN hành vi cũ.
         */
        foreach ([$attempt->competitionExam?->ends_at, $attempt->competition?->ends_at] as $endsAt) {
            if ($endsAt !== null) {
                // ->copy() ở cả hai nhánh: Carbon::min() trả về CHÍNH đối tượng nhỏ hơn, có thể
                // là ends_at của model. Trả thẳng ra ngoài thì nơi gọi lỡ tay sửa mốc giờ sẽ
                // sửa luôn thuộc tính của model đang nằm trong bộ nhớ.
                $deadline = $deadline === null ? $endsAt->copy() : $deadline->min($endsAt)->copy();
            }
        }

        return $deadline;
    }

    /** true nếu lượt làm bài này đã quá hạn nộp thật (deadlineFor()) tại thời điểm gọi. */
    public function isExpired(Attempt $attempt): bool
    {
        $deadline = $this->deadlineFor($attempt);

        return $deadline !== null && now()->gt($deadline);
    }

    /**
     * Nếu lượt làm bài đang dở đã quá hạn nộp thật, TỰ ĐỘNG nộp luôn (dùng đúng những câu đã
     * lưu tới thời điểm hết giờ) thay vì để học sinh tiếp tục sửa câu trả lời sau khi hết giờ
     * — gọi ở đầu mỗi lần mở lại trang làm bài (App\Services\Student\AssessmentService::
     * buildTakeData()) để bắt cả trường hợp học sinh đóng tab lúc hết giờ rồi quay lại sau.
     */
    public function finalizeIfExpired(Attempt $attempt): Attempt
    {
        if ($attempt->status === AttemptStatus::InProgress && $this->isExpired($attempt)) {
            return $this->submit($attempt);
        }

        return $attempt;
    }

    /**
     * Lưu (hoặc cập nhật) câu trả lời cho 1 câu trong lượt làm bài. MCQ/điền đáp án được
     * chấm ngay tại đây; câu lập trình chỉ lưu code_source/language, verdict=Queued.
     *
     * @throws ValidationException nếu lượt làm bài đã nộp/kết thúc, hoặc vừa hết giờ (trong
     *                              trường hợp này lượt làm bài được TỰ ĐỘNG nộp trước khi ném
     *                              lỗi, để client biết sang thẳng trang kết quả).
     */
    public function saveAnswer(Attempt $attempt, Question $question, array $rawInput): AttemptAnswer
    {
        if ($attempt->status !== AttemptStatus::InProgress) {
            throw ValidationException::withMessages(['attempt' => 'Lượt làm bài này đã kết thúc, không thể sửa câu trả lời.']);
        }

        if ($this->isExpired($attempt)) {
            $this->submit($attempt);

            throw ValidationException::withMessages(['attempt' => 'Đã hết thời gian làm bài — bài của bạn đã được tự động nộp.']);
        }

        $codeSource = null;
        $language = null;
        $answer = [];

        if ($question->type === QuestionType::Coding) {
            $codeSource = $rawInput['code_source'] ?? null;
            $language = $rawInput['language'] ?? null;
            $score = null;
            $verdict = VerdictStatus::Queued;
        } elseif ($question->type === QuestionType::Composite) {
            // SỬA 31/8 (2, "mở rộng ZIP bài tập" nhiều dạng câu) — câu Composite chưa có gắn
            // vào 1 Assessment thật nào (chỉ tạo qua nhập ZIP vào Kho chung/bài tập sản phẩm,
            // dùng ở "Luyện tập theo câu" — xem Student\PracticeByQuestionService::answer(), nơi
            // DUY NHẤT hiện chấm từng phần) — nhưng NẾU sau này có ai gắn 1 câu Composite vào
            // đề thi chính thức, saveAnswer() vẫn phải xử lý được thay vì rơi vào nhánh 'else'
            // bên dưới (dành cho FillBlank) và hiểu sai cấu trúc dữ liệu. Chưa có pipeline
            // chấm/tổng hợp điểm từng phần cho luồng thi chính thức — ghi nhận "Queued" giống
            // hệt Lập trình (không tự chấm), an toàn hơn là chấm sai.
            $answer = ['parts' => $rawInput['parts'] ?? []];
            $score = null;
            $verdict = VerdictStatus::Queued;
        } elseif ($question->type === QuestionType::Mcq) {
            $answer = ['selected_option' => $rawInput['selected_option'] ?? null];
            [$score, $verdict] = $this->gradeMcq($attempt, $question, $answer);
        } else {
            $answer = ['text' => $rawInput['text'] ?? null];
            [$score, $verdict] = $this->gradeFillBlank($attempt, $question, $answer);
        }

        $existing = $this->attemptAnswers->query()
            ->where('attempt_id', $attempt->id)
            ->where('question_id', $question->id)
            ->first();

        return $this->attemptAnswers->upsertAnswer($attempt->id, $question->id, [
            'answer' => $answer,
            'code_source' => $codeSource,
            'language' => $language,
            'verdict' => $verdict->value,
            'score' => $score,
            'graded_at' => $verdict->isFinal() ? now() : null,
            'submission_count' => ($existing?->submission_count ?? 0) + 1,
        ]);
    }

    /**
     * Nộp bài: khoá lượt làm bài, cộng điểm các câu đã chấm xong (MCQ/điền đáp án), câu
     * lập trình còn "Queued" thì tổng điểm vẫn tạm tính (is_provisional) cho tới khi có
     * chấm thật (recalculateProvisionalFlag() đã có sẵn ở App\Models\Attempt).
     *
     * @throws ValidationException nếu lượt làm bài đã nộp trước đó.
     */
    public function submit(Attempt $attempt): Attempt
    {
        // SỬA (nối máy chấm Judge0 thật) — chấm THẬT câu Lập trình còn "queued" TRƯỚC khi mở
        // transaction/khoá dòng bên dưới, cùng lý do với App\Services\PdfAttemptService::
        // submit(): gọi Judge0 qua mạng có thể chậm, không nên giữ khoá dòng suốt lúc chờ.
        /*
         * SỬA 23/9 (khách: "bấm nộp đề trong luyện tập nó đứng luôn") — TRƯỚC ĐÂY chấm ngay tại
         * đây: mỗi câu lập trình 20 test, đề 5 câu là 100 lượt chạy nối nhau, vượt xa thời gian
         * chờ của web nên trình duyệt đứng im rồi lỗi, học sinh tưởng mất bài.
         *
         * GIỜ: nộp xong trả trang kết quả ngay, mỗi câu lập trình thành 1 việc chạy nền
         * (App\Jobs\GradeCodingAnswerJob). Điểm hiện dần, trang kết quả tự làm mới.
         *
         * Máy chủ chưa bật tiến trình chạy nền thì đặt QUEUE_CONNECTION=sync trong .env —
         * Laravel chạy thẳng tại chỗ, đúng y hành vi cũ, không cần sửa mã.
         */
        // Trước đây đọc $attempt->status rồi mới ghi (check-then-write) KHÔNG có transaction/
        // khoá dòng — 2 request nộp bài đồng thời cho CÙNG 1 lượt làm (double-click, hoặc
        // client tự động retry khi mất mạng giữa chừng) có thể cùng đọc thấy 'in_progress'
        // trước khi request nào kịp ghi 'graded', khiến cả 2 cùng vượt qua guard phía trên và
        // cùng ghi đè total_score/submitted_at — vi phạm đúng yêu cầu "không thể nộp lại 2 lần
        // cho cùng 1 lượt làm" ở mức DB (guard cũ chỉ đúng ở mức ứng dụng, không đúng khi có
        // 2 request chạy song song thật). Khoá dòng (lockForUpdate) bên trong transaction để
        // request thứ 2 phải đợi request thứ 1 commit xong, rồi tự thấy status đã đổi và bị
        // chặn đúng như luồng bình thường.
        $locked = DB::transaction(function () use ($attempt) {
            $locked = $this->attempts->query()->whereKey($attempt->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status !== AttemptStatus::InProgress) {
                throw ValidationException::withMessages(['attempt' => 'Lượt làm bài này đã được nộp trước đó.']);
            }

            $locked->load('answers');

            // SỬA 23/9 — cộng theo SỐ THỰC rồi mới làm tròn 2 chữ số. Trước đây ép (int) nên
            // câu lập trình qua 14/20 test được 2,1 điểm bị cắt còn 2 — càng nhiều câu càng lệch.
            $totalScore = round((float) $locked->answers->whereNotNull('score')->sum('score'), 2);

            $locked->recalculateProvisionalFlag();
            $locked->total_score = $totalScore;
            $locked->submitted_at = now();
            $locked->status = ($locked->is_provisional ? AttemptStatus::Grading : AttemptStatus::Graded)->value;
            $locked->save();

            return $locked;
        });

        /*
         * SỬA 23/9 (bản vá tiếp) — ĐẨY VIỆC CHẤM NỀN SAU KHI ĐÃ COMMIT nộp bài.
         *
         * Trước đó lệnh đẩy việc nằm TRƯỚC transaction: máy chạy nền nhanh tay có thể chấm
         * xong một câu trong lúc submitted_at còn null, khiến refreshScoreAfterGrading() thoát
         * sớm và tổng điểm chỉ được cộng lại nhờ may mắn. Đẩy sau khi commit thì việc nền luôn
         * nhìn thấy lượt làm đã nộp.
         */
        $this->dispatchCodingGrading($locked);

        $this->recordCompetitionLeaderboardSafely($locked);

        return $locked;
    }

    /**
     * SỬA 23/9 — việc chấm nền hỏng hẳn (máy chấm không tới được sau 3 lượt thử): ghi verdict
     * "lỗi hệ thống chấm bài" cho câu đó để trang kết quả THÔI quay vòng "Đang chấm" mãi, rồi
     * tổng kết lại điểm. Không ghi 0 điểm — score vẫn null, admin/giáo viên chấm lại được bằng
     * php artisan attempt:regrade-stuck.
     */
    public function markCodingAnswerSystemError(AttemptAnswer $answer): void
    {
        $answer->loadMissing('attempt');

        if ($answer->verdict->isFinal()) {
            return;
        }

        $answer->verdict = VerdictStatus::SystemError->value;
        $answer->graded_at = now();
        $answer->save();

        if ($answer->attempt !== null) {
            $this->refreshScoreAfterGrading($answer->attempt);
        }
    }

    /**
     * SỬA 23/9 — đẩy mỗi câu Lập trình chưa chấm thành 1 việc chạy nền.
     *
     * Đánh dấu 'judging' ngay để trang kết quả biết mà hiện "Đang chấm" và để lần nộp/refresh
     * sau không đẩy trùng việc.
     */
    private function dispatchCodingGrading(Attempt $attempt): void
    {
        $attempt->load(['answers.question']);

        foreach ($attempt->answers as $answer) {
            $question = $answer->question;

            if ($question === null || $question->type !== QuestionType::Coding) {
                continue;
            }

            if ($answer->verdict->isFinal() || blank($answer->code_source)) {
                continue;
            }

            if ($answer->verdict !== VerdictStatus::Judging) {
                $answer->verdict = VerdictStatus::Judging->value;
                $answer->save();
            }

            GradeCodingAnswerJob::dispatch($answer->id);
        }
    }

    /**
     * SỬA 19/8 (Giai đoạn 5 — "Tự động ghi bảng xếp hạng"): gọi NGOÀI transaction nộp bài ở
     * trên (đã commit xong, Attempt đã lưu chắc chắn) — cố ý bọc try/catch NUỐT lỗi thay vì
     * để lỗi ném ra ngoài, vì đây chỉ là tác dụng phụ (ghi bảng xếp hạng cho đề đấu Cuộc thi),
     * KHÔNG được phép làm hỏng việc nộp bài THẬT của học sinh (vd mất kết nối CSDL đúng lúc
     * này thì học sinh vẫn phải thấy nộp bài THÀNH CÔNG — bảng xếp hạng có thể cập nhật trễ,
     * Admin vẫn bấm lại "Tính tổng từ các kỳ thi" sau được, không mất dữ liệu Attempt gốc).
     */
    private function recordCompetitionLeaderboardSafely(Attempt $attempt): void
    {
        try {
            $this->competitionLeaderboard->recordIfCompetitionExam($attempt);
        } catch (Throwable $e) {
            Log::error('Không ghi được bảng xếp hạng Cuộc thi cho attempt #'.$attempt->id, ['exception' => $e]);
        }
    }

    /**
     * SỬA (nối máy chấm Judge0 thật): chấm THẬT mọi câu trả lời loại Coding còn "chưa chấm"
     * (verdict chưa final) của attempt này — test case đọc từ Question::grading_config
     * ['test_cases'] (mảng {input,output} phẳng, KHÔNG phân biệt sample/hidden như bên PDF —
     * cả bài thi cấu trúc chính thức, mọi test case đều tính điểm). Ghi thêm 1 dòng
     * App\Models\JudgeSubmission làm nhật ký (16 mục 1 — bảng này sinh ra sẵn CHO đúng việc
     * này, xem migration 2025_01_01_000250) — 1 dòng/1 lượt chấm (không phải 1 dòng/test case,
     * vì AttemptAnswer chỉ có đúng 1 verdict/score tổng, không chấm từng phần).
     *
     * Bỏ qua (giữ nguyên "queued") nếu học sinh chưa nộp code, hoặc nếu Judge0 không gọi được
     * — KHÔNG được để 1 lỗi mạng biến thành điểm 0 sai oan cho học sinh.
     */
    private function gradePendingCodingAnswers(Attempt $attempt): void
    {
        // 'assessment.items' để maxPointsFor() đọc được điểm đè của từng câu trong đề mà
        // không bắn thêm truy vấn cho mỗi câu.
        $attempt->load(['answers.question', 'assessment.items']);

        foreach ($attempt->answers as $answer) {
            $this->gradeCodingAnswer($answer);
        }
    }

    /**
     * SỬA 23/9 (khách: "bấm nộp đề nó đứng luôn") — CHẤM ĐÚNG 1 CÂU lập trình.
     *
     * Tách riêng khỏi vòng lặp cũ để App\Jobs\GradeCodingAnswerJob gọi được: nộp bài giờ chỉ
     * đẩy mỗi câu thành 1 việc chạy nền, trang kết quả mở ra ngay thay vì treo chờ máy chấm
     * chạy hết 20 test × N câu (đề 5 bài là 100 lượt chạy — quá thời gian chờ của web).
     *
     * An toàn khi gọi lại nhiều lần: câu đã chấm xong (verdict final) hoặc chưa có mã nguồn
     * thì bỏ qua ngay.
     */
    public function gradeCodingAnswer(AttemptAnswer $answer): void
    {
        $answer->loadMissing(['question', 'attempt.assessment.items']);

        $question = $answer->question;
        $attempt = $answer->attempt;

        if ($question === null || $attempt === null || $question->type !== QuestionType::Coding) {
            return;
        }

        if ($answer->verdict->isFinal() || blank($answer->code_source)) {
            return;
        }

        $config = $question->grading_config ?? [];
        $testCases = collect($config['test_cases'] ?? [])
            ->map(fn ($tc) => ['input' => (string) ($tc['input'] ?? ''), 'expected_output' => (string) ($tc['output'] ?? '')])
            ->all();
        $timeLimitMs = (int) ($config['time_limit_ms'] ?? 5000);
        $memoryLimitKb = (int) ($config['memory_limit_mb'] ?? 256) * 1024;

        try {
            $result = $this->codeJudging->judge($answer->code_source, $answer->language, $testCases, $timeLimitMs, $memoryLimitKb, $config['file_io'] ?? null);
        } catch (Throwable $e) {
            Log::error('Không chấm được câu trả lời Lập trình #'.$answer->id.' (Judge0 không tới được)', ['exception' => $e]);

            JudgeSubmission::create([
                'attempt_answer_id' => $answer->id,
                'status' => 'failed',
                'dispatched_at' => now(),
                'raw_result' => ['error' => $e->getMessage()],
            ]);

            return;
        }

        [$score, $passed, $totalTests] = $this->scoreFromTestResults(
            $result['details'],
            $this->maxPointsFor($attempt, $question),
        );

        $answer->verdict = $result['verdict']->value;
        $answer->score = $score;
        $answer->graded_at = now();

        // SỬA 19/9 (8) — số test qua/tổng, để màn kết quả nói được "Qua 14/20 test".
        if (AttemptAnswer::supportsTestCounts()) {
            $answer->passed_tests = $passed;
            $answer->total_tests = $totalTests;
        }

        $answer->save();

        JudgeSubmission::create([
            'attempt_answer_id' => $answer->id,
            'status' => 'completed',
            'verdict' => $result['verdict']->value,
            'raw_result' => $result['details'],
            'dispatched_at' => now(),
            'completed_at' => now(),
        ]);
    }

    /**
     * SỬA 23/9 — cộng lại tổng điểm sau khi một câu vừa được chấm nền xong, và chốt trạng thái
     * lượt làm bài khi không còn câu nào chờ chấm.
     *
     * Gọi từ GradeCodingAnswerJob sau mỗi câu, nên điểm nhích dần ngay trên trang kết quả.
     */
    public function refreshScoreAfterGrading(Attempt $attempt): void
    {
        DB::transaction(function () use ($attempt) {
            $locked = $this->attempts->query()->whereKey($attempt->id)->lockForUpdate()->first();

            if ($locked === null || $locked->submitted_at === null) {
                return;
            }

            $locked->load('answers');

            $locked->total_score = round((float) $locked->answers->whereNotNull('score')->sum('score'), 2);
            $locked->recalculateProvisionalFlag();
            $locked->status = ($locked->is_provisional ? AttemptStatus::Grading : AttemptStatus::Graded)->value;
            $locked->save();
        });
    }

    /**
     * SỬA 19/8 (Giai đoạn 6): so khớp đáp án chuyển qua App\Services\QuestionGrader — dùng
     * chung với Student\PracticeByQuestionService, xem docblock ở đó để biết lý do tách (bug
     * chấm sai MCQ giáo viên tự tạo phát hiện cùng đợt cho thấy so khớp đáp án chỉ nên có
     * đúng 1 nơi hiện thực).
     *
     * @return array{0: ?int, 1: VerdictStatus}
     */
    /**
     * SỬA 23/9 (khách: "làm xong bấm nộp thì tổng lại được bao nhiêu điểm") — ĐIỂM TỐI ĐA của
     * 1 câu TRONG ĐỀ NÀY.
     *
     * Trước đây mọi chỗ chấm đều lấy thẳng $question->points, tức là BỎ QUA điểm admin nhập đè
     * khi gắn câu vào đề (assessment_items.points_override — xem
     * Admin\ContentService::assessmentItemsUpdate(), nơi total_points của đề được tính CHÍNH
     * TỪ điểm đè đó). Hậu quả: tổng điểm đề ghi 10 nhưng học sinh làm đúng hết lại ra 30, vì
     * điểm gốc của câu trong kho khác điểm đặt cho đề.
     *
     * Không thuộc đề nào (tự luyện) thì quay về điểm gốc của câu.
     */
    private function maxPointsFor(Attempt $attempt, Question $question): float
    {
        $assessment = $attempt->assessment;

        if ($assessment !== null) {
            $item = $assessment->items->firstWhere('question_id', $question->id);

            if ($item !== null && $item->points_override !== null) {
                return (float) $item->points_override;
            }
        }

        return (float) $question->points;
    }

    /**
     * SỬA 23/9 — CHẤM THEO TỈ LỆ TEST cho câu Lập trình (khách chốt): qua bao nhiêu phần test
     * thì được bấy nhiêu phần điểm, thay vì "thiếu 1 test cũng 0 điểm" như trước.
     *
     * · Lỗi biên dịch -> 0 điểm (máy chấm đã dừng sớm, không test nào chạy).
     * · Qua hết test  -> trọn điểm câu.
     * · Làm tròn 2 chữ số ở từng câu; tổng bài làm tròn 1 lần ở submit(), tránh sai số cộng dồn.
     *
     * @param  array<int, array<string, mixed>>  $details  Kết quả từng test của CodeJudgingService
     * @return array{0: float, 1: int, 2: int}  [điểm đạt, số test qua, tổng số test]
     */
    private function scoreFromTestResults(array $details, float $maxPoints): array
    {
        $total = count($details);
        $passed = 0;

        foreach ($details as $detail) {
            if (($detail['isAccepted'] ?? false) === true) {
                $passed++;
            }
        }

        if ($total === 0) {
            return [0.0, 0, 0];
        }

        return [round($maxPoints * $passed / $total, 2), $passed, $total];
    }

    private function gradeMcq(Attempt $attempt, Question $question, array $answer): array
    {
        $isCorrect = QuestionGrader::isMcqCorrect($question, $answer['selected_option'] ?? null);

        // SỬA 23/9 — điểm lấy theo ĐỀ (points_override), xem maxPointsFor().
        return [$isCorrect ? $this->maxPointsFor($attempt, $question) : 0.0, $isCorrect ? VerdictStatus::Accepted : VerdictStatus::WrongAnswer];
    }

    /** @return array{0: ?float, 1: VerdictStatus} */
    private function gradeFillBlank(Attempt $attempt, Question $question, array $answer): array
    {
        $isCorrect = QuestionGrader::isFillBlankCorrect($question, (string) ($answer['text'] ?? ''));

        return [$isCorrect ? $this->maxPointsFor($attempt, $question) : 0.0, $isCorrect ? VerdictStatus::Accepted : VerdictStatus::WrongAnswer];
    }
}
