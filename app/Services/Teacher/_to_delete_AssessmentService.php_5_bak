<?php

namespace App\Services\Teacher;

use App\Enums\AssessmentType;
use App\Enums\AssignmentStatus;
use App\Enums\ContentStatus;
use App\Enums\OwnerType;
use App\Enums\PublishAnswerRule;
use App\Enums\QuestionType;
use App\Enums\UploadedDocumentStatus;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\UploadedDocument;
use App\Models\User;
use App\Repositories\Contracts\AssessmentRepositoryInterface;
use App\Repositories\Contracts\AssignmentRepositoryInterface;
use App\Repositories\Contracts\QuestionRepositoryInterface;
use App\Repositories\Contracts\UploadedDocumentRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** Tổng hợp dữ liệu cho teacher.assessments.* (TEA-04/05, 6.3/6.4/8.4). */
class AssessmentService
{
    public function __construct(
        private readonly UploadedDocumentRepositoryInterface $uploadedDocuments,
        private readonly AssessmentRepositoryInterface $assessments,
        private readonly QuestionRepositoryInterface $questions,
        private readonly AssignmentRepositoryInterface $assignments,
    ) {}

    /** teacher.assessments.import — trạng thái xử lý thật, kể cả tệp lỗi (6.4). */
    public function importStatusFor(User $user): array
    {
        $statuses = [
            UploadedDocumentStatus::Uploaded,
            UploadedDocumentStatus::Scanning,
            UploadedDocumentStatus::QueuedOcr,
            UploadedDocumentStatus::Processing,
            UploadedDocumentStatus::NeedsReview,
            UploadedDocumentStatus::Failed,
        ];

        $processingFiles = $this->uploadedDocuments->forUploaderInStatuses($user->id, $statuses, 20)
            ->map(function (UploadedDocument $doc) {
                [$label, $tone, $progress] = match ($doc->status) {
                    UploadedDocumentStatus::Uploaded => ['Đã tải lên — đang chờ quét', 'info', 10],
                    UploadedDocumentStatus::Scanning => ['Đang quét định dạng...', 'info', 25],
                    UploadedDocumentStatus::QueuedOcr => ['Trong hàng chờ OCR...', 'info', 40],
                    UploadedDocumentStatus::Processing => ['Đang trích xuất/OCR...', 'info', 70],
                    UploadedDocumentStatus::NeedsReview => ['Cần rà soát', 'warning', 100],
                    UploadedDocumentStatus::Failed => ['Xử lý lỗi', 'danger', 100],
                    default => ['Không rõ', 'neutral', 0],
                };

                return [
                    'id' => $doc->id,
                    'name' => $doc->original_filename,
                    'status' => $label,
                    'tone' => $tone,
                    'progress' => $progress,
                    'errorLog' => $doc->status === UploadedDocumentStatus::Failed ? $doc->error_log : null,
                ];
            })->all();

        return ['processingFiles' => $processingFiles];
    }

    /**
     * teacher.assessments.reviewDraft — rà soát; nhận ?document=<id>, nếu không có thì lấy
     * tài liệu "cần rà soát" gần nhất của giáo viên (6.4). Trả đủ dữ liệu để màn rà soát
     * sửa/gộp/xóa/thêm tay và chuyển vào kho — không chỉ hiển thị đọc.
     */
    public function reviewDraftFor(User $user, ?int $documentId): array
    {
        $document = null;
        if ($documentId !== null) {
            $document = $this->uploadedDocuments->findForUploader($user->id, $documentId);
        }
        $document ??= $this->uploadedDocuments->latestNeedsReviewForUploader($user->id);

        $drafts = [];
        if ($document) {
            $allDrafts = $document->draftQuestions()->where('review_status', '!=', 'discarded')->orderBy('order')->get();

            $drafts = $allDrafts->values()->map(function ($d) use ($allDrafts) {
                $confidenceLabel = match ($d->confidence) {
                    'high' => 'Cao',
                    'medium' => 'Trung bình',
                    'low' => 'Thấp — cần kiểm tra kỹ',
                    default => 'Chưa rõ',
                };
                $tone = match ($d->confidence) {
                    'high' => 'success',
                    'medium' => 'warning',
                    'low' => 'danger',
                    default => 'neutral',
                };

                $s = $d->structured_draft ?? [];
                $type = $d->type_guess instanceof QuestionType ? $d->type_guess->value : $d->type_guess;

                return [
                    'id' => $d->id,
                    'no' => $d->order + 1,
                    'type' => $type,
                    'confidence' => $confidenceLabel,
                    'tone' => $tone,
                    'flagged' => $d->needsManualReview(),
                    'promoted' => $d->promoted_question_id !== null,
                    'title' => $s['title'] ?? ($d->raw_text ? mb_substr($d->raw_text, 0, 160) : '(chưa có nội dung)'),
                    'body' => $s['body'] ?? $d->raw_text ?? '',
                    'points' => $s['points'] ?? 1,
                    'options' => $s['options'] ?? ['', '', '', ''],
                    'correctOption' => $s['correct_option'] ?? null,
                    'acceptedAnswers' => $s['accepted_answers'] ?? '',
                    'caseSensitive' => $s['case_sensitive'] ?? false,
                    'testCases' => $s['test_cases'] ?? '',
                    'timeLimitMs' => $s['time_limit_ms'] ?? 1000,
                    'memoryLimitMb' => $s['memory_limit_mb'] ?? 256,
                    'otherDrafts' => $allDrafts->reject(fn ($other) => $other->id === $d->id)
                        ->map(fn ($other) => ['id' => $other->id, 'label' => 'Câu '.($other->order + 1)])
                        ->values()->all(),
                ];
            })->all();
        }

        return ['document' => $document, 'drafts' => $drafts];
    }

    /** teacher.assessments.index — đề do chính giáo viên tạo trong kho riêng (6.3, 8.4). */
    public function listForTeacher(User $teacher): array
    {
        $assessments = $this->assessments->byOwner($teacher->id, 100)
            ->map(fn (Assessment $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'itemsCount' => $a->items_count,
                'assignmentsCount' => $a->assignments_count,
                'totalPoints' => $a->total_points,
                'status' => match ($a->status) {
                    ContentStatus::Published => 'Đã phát hành',
                    ContentStatus::Archived => 'Lưu trữ',
                    default => 'Nháp',
                },
                'tone' => match ($a->status) {
                    ContentStatus::Published => 'success',
                    ContentStatus::Archived => 'neutral',
                    default => 'warning',
                },
                'canPublish' => $a->status === ContentStatus::Draft,
            ])->all();

        $classRooms = $teacher->classRoomsTeaching()->get(['class_rooms.id', 'class_rooms.name'])->all();

        return ['assessments' => $assessments, 'classRooms' => $classRooms];
    }

    /** teacher.assessments.create — chọn câu từ kho riêng + lớp đang phụ trách (6.3, 8.4). */
    public function createFormData(User $teacher): array
    {
        $questions = $this->questions->byOwner($teacher->id, null, 200)
            ->map(fn ($q) => [
                'id' => $q->id,
                'title' => $q->title,
                'type' => $q->type->value,
                'points' => $q->points,
                'status' => $q->status->value,
            ])->all();

        $classRooms = $teacher->classRoomsTeaching()->get(['class_rooms.id', 'class_rooms.name'])->all();

        return ['questions' => $questions, 'classRooms' => $classRooms];
    }

    /**
     * teacher.assessments.store — tạo đề trộn nhiều kiểu câu (6.3), luôn bắt đầu "Nháp".
     * Chỉ nhận câu thuộc đúng kho riêng của giáo viên này (16 mục 3: không tin ID client gửi lên).
     *
     * @throws ValidationException nếu không chọn câu nào hoặc không câu nào hợp lệ.
     */
    public function store(User $teacher, array $data): Assessment
    {
        $questionIds = array_map('intval', $data['question_ids'] ?? []);
        $ownedQuestions = $this->questions->query()
            ->whereIn('id', $questionIds)
            ->where('owner_id', $teacher->id)
            ->where('owner_type', 'teacher')
            ->get()
            ->keyBy('id');

        $items = [];
        $totalPoints = 0;
        foreach ($questionIds as $order => $questionId) {
            $question = $ownedQuestions->get($questionId);
            if ($question === null) {
                continue;
            }
            $override = $data['points_override'][$questionId] ?? null;
            $points = filled($override) ? (int) $override : $question->points;
            $items[] = ['question_id' => $question->id, 'order' => $order, 'points_override' => filled($override) ? $points : null];
            $totalPoints += $points;
        }

        if ($items === []) {
            throw ValidationException::withMessages(['question_ids' => 'Chọn ít nhất 1 câu hợp lệ thuộc kho câu hỏi của bạn.']);
        }

        $assessment = $this->assessments->create([
            'title' => $data['title'],
            'type' => AssessmentType::Assignment,
            'total_points' => $totalPoints,
            'duration_minutes' => filled($data['duration_minutes'] ?? null) ? (int) $data['duration_minutes'] : null,
            'resubmission_policy' => filled($data['max_resubmissions'] ?? null) ? ['max_attempts' => (int) $data['max_resubmissions']] : null,
            'publish_answer_rule' => $data['publish_answer_rule'] ?? PublishAnswerRule::AfterDeadline->value,
            'status' => ContentStatus::Draft,
            'version' => 1,
            'owner_type' => OwnerType::Teacher,
            'owner_id' => $teacher->id,
            'created_by' => $teacher->id,
        ]);

        foreach ($items as $item) {
            $assessment->items()->create($item);
        }

        return $assessment;
    }

    /** Chỉ giáo viên sở hữu đề mới được xem/sửa/phát hành/giao (tương tự 6.5 áp cho kho riêng). */
    public function findOwned(User $teacher, int $id): Assessment
    {
        $assessment = $this->assessments->withItemsAndQuestions($id)
            ?? $this->assessments->findOrFail($id);

        abort_unless($assessment->owner_type === OwnerType::Teacher && (int) $assessment->owner_id === $teacher->id, 403);

        return $assessment;
    }

    /**
     * Điều kiện phát hành đề (6.2 áp dụng cho cấp đề): phải có câu, và MỌI câu trong đề
     * phải đã Published — không lộ câu nháp/thiếu cấu hình chấm cho học sinh.
     *
     * @return array{0: bool, 1: ?string}
     */
    public function canPublish(Assessment $assessment): array
    {
        $items = $assessment->items()->with('question')->get();

        if ($items->isEmpty()) {
            return [false, 'Đề chưa có câu nào.'];
        }

        $notPublished = $items->filter(fn ($item) => $item->question === null || $item->question->status !== ContentStatus::Published);

        if ($notPublished->isNotEmpty()) {
            return [false, 'Còn '.$notPublished->count().' câu trong đề chưa được phát hành ở Kho câu hỏi — phát hành từng câu trước (6.2).'];
        }

        return [true, null];
    }

    /** @throws ValidationException nếu chưa đủ điều kiện. */
    public function publish(Assessment $assessment): Assessment
    {
        [$ok, $reason] = $this->canPublish($assessment);

        if (! $ok) {
            throw ValidationException::withMessages(['publish' => $reason]);
        }

        $assessment->update(['status' => ContentStatus::Published]);

        return $assessment;
    }

    /**
     * teacher.assessments.assign — "Giao đề" (8.4): chọn đề, lớp, mốc thời gian, hướng dẫn,
     * quy tắc công bố. KHÔNG hỗ trợ ngoại lệ từng học sinh (8.4). Đề phải Published trước
     * khi giao — tự động phát hành nếu đủ điều kiện, ném lỗi cụ thể nếu chưa.
     *
     * shift_count (tùy chọn, > 1) — "chia ca thi" (note họp 13/8, mục 7: "Các kỳ thi nếu
     * đông quá thì mình chia thành các ca thi để chống tấn công ddos"): bắt buộc phải có
     * đủ cả opens_at và closes_at thì mới chia ca được (không có 2 mốc thì không biết chia
     * khung giờ nào) — xem App\Models\Assignment::shiftWindowFor().
     *
     * @throws ValidationException nếu đề chưa đủ điều kiện phát hành, lớp không hợp lệ,
     *                              hoặc yêu cầu chia ca nhưng thiếu mốc mở/đóng.
     */
    public function assignToClass(User $teacher, Assessment $assessment, array $data): Assignment
    {
        if ($assessment->status !== ContentStatus::Published) {
            $this->publish($assessment);
        }

        $classRoom = $teacher->classRoomsTeaching()->where('class_rooms.id', (int) $data['class_room_id'])->first();

        if ($classRoom === null) {
            throw ValidationException::withMessages(['class_room_id' => 'Bạn không phụ trách lớp này.']);
        }

        $opensAt = filled($data['opens_at'] ?? null) ? Carbon::parse($data['opens_at']) : null;
        $closesAt = filled($data['closes_at'] ?? null) ? Carbon::parse($data['closes_at']) : null;
        $dueAt = filled($data['due_at'] ?? null) ? Carbon::parse($data['due_at']) : $closesAt;

        $shiftCount = filled($data['shift_count'] ?? null) ? (int) $data['shift_count'] : null;
        if ($shiftCount !== null && $shiftCount > 1 && ($opensAt === null || $closesAt === null)) {
            throw ValidationException::withMessages([
                'shift_count' => 'Cần đặt đủ cả mốc Mở lúc và Đóng lúc thì mới chia ca thi được.',
            ]);
        }

        return $this->assignments->create([
            'class_room_id' => $classRoom->id,
            'assessment_id' => $assessment->id,
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
            'due_at' => $dueAt,
            'shift_count' => $shiftCount,
            'rules' => ['publish_answer_rule' => $assessment->publish_answer_rule->value],
            'instructions' => $data['instructions'] ?? null,
            'status' => $this->computeAssignmentStatus($opensAt, $closesAt)->value,
            'created_by' => $teacher->id,
        ]);
    }

    /** Trạng thái khởi tạo theo mốc thời gian (8.4: Nháp → Đã lên lịch → Đang mở → Đã đóng → Đã lưu trữ). */
    private function computeAssignmentStatus(?Carbon $opensAt, ?Carbon $closesAt): AssignmentStatus
    {
        if ($opensAt !== null && $opensAt->isFuture()) {
            return AssignmentStatus::Scheduled;
        }

        if ($closesAt !== null && $closesAt->isPast()) {
            return AssignmentStatus::Closed;
        }

        return AssignmentStatus::Open;
    }
}
