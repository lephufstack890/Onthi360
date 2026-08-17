<?php

namespace App\Services\Admin;

use App\Enums\ContentStatus;
use App\Enums\OwnerType;
use App\Enums\UploadedDocumentStatus;
use App\Enums\Visibility;
use App\Models\Assessment;
use App\Models\Material;
use App\Models\Product;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\UploadedDocument;
use App\Models\User;
use App\Repositories\Contracts\AssessmentRepositoryInterface;
use App\Repositories\Contracts\DraftQuestionRepositoryInterface;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\QuestionRepositoryInterface;
use App\Repositories\Contracts\UploadedDocumentRepositoryInterface;
use App\Services\QuestionPublishGuard;
use Illuminate\Validation\ValidationException;

/**
 * Gom truy vấn/nhãn cho admin.content.* (ADM-03, 6.2/6.4/6.5).
 */
class ContentService
{
    public function __construct(
        private MaterialRepositoryInterface $materials,
        private QuestionRepositoryInterface $questions,
        private AssessmentRepositoryInterface $assessments,
        private DraftQuestionRepositoryInterface $draftQuestions,
        private ProductRepositoryInterface $products,
        private QuestionPublishGuard $publishGuard,
        private UploadedDocumentRepositoryInterface $uploadedDocuments,
    ) {}

    private const TYPE_LABELS = [
        'material' => 'Học liệu',
        'question' => 'Câu hỏi (kho chung)',
        'assessment' => 'Đề/bộ bài',
    ];

    /**
     * Kho câu hỏi chung dùng chung cho toàn hệ thống (6.5: "Kho chung: Editor/Admin/Super
     * Admin quản lý"). Tự tạo nếu chưa seed sẵn (môi trường mới/production chưa chạy
     * DemoDataSeeder) — không bắt admin phải tự quản lý khái niệm "bank" khi tạo câu hỏi.
     */
    private function sharedBank(): QuestionBank
    {
        return QuestionBank::firstOrCreate(
            ['owner_type' => OwnerType::Shared->value, 'name' => 'Kho chung'],
        );
    }

    private function statusLabel(ContentStatus $status): array
    {
        return match ($status) {
            ContentStatus::Draft => ['Nháp', 'neutral'],
            ContentStatus::PendingReview => ['Chờ duyệt', 'warning'],
            ContentStatus::Published => ['Phát hành', 'success'],
            ContentStatus::Archived => ['Lưu trữ', 'neutral'],
        };
    }

    /**
     * Tài liệu OCR đang chờ rà soát/xử lý — hiển thị ở tab "Câu hỏi chờ rà soát (OCR)"
     * (6.4). KHÔNG lọc theo người tải lên: tài liệu thuộc "Kho chung", cả nhóm
     * Admin/Editor cùng thấy và rà soát được (khác Teacher — nơi mỗi giáo viên chỉ
     * thấy tài liệu của chính mình).
     */
    private function pendingDocuments(): array
    {
        $statuses = [
            UploadedDocumentStatus::Uploaded,
            UploadedDocumentStatus::Scanning,
            UploadedDocumentStatus::QueuedOcr,
            UploadedDocumentStatus::Processing,
            UploadedDocumentStatus::NeedsReview,
            UploadedDocumentStatus::Failed,
        ];

        return $this->uploadedDocuments->query()
            ->whereIn('status', $statuses)
            ->with('uploader')
            ->latest()
            ->limit(20)
            ->get()
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
                    'uploader' => $doc->uploader->name ?? '?',
                    'status' => $label,
                    'tone' => $tone,
                    'progress' => $progress,
                    'reviewable' => $doc->status === UploadedDocumentStatus::NeedsReview,
                    'errorLog' => $doc->status === UploadedDocumentStatus::Failed ? $doc->error_log : null,
                ];
            })->all();
    }

    /** @return array{tab: string, tabs: array, rows: array, total: int, documents: array} */
    public function indexData(string $tab): array
    {
        $counts = [
            'materials' => $this->materials->count(),
            'questions' => $this->questions->count(),
            'assessments' => $this->assessments->count(),
            'drafts' => $this->draftQuestions->countPendingReview(),
        ];

        $tabs = [
            ['label' => 'Học liệu (Sách/Chuyên đề/Đề thi)', 'href' => route('admin.content.index'), 'active' => $tab === 'materials', 'count' => $counts['materials']],
            ['label' => 'Câu hỏi (Kho chung + Giáo viên)', 'href' => route('admin.content.index', ['tab' => 'questions']), 'active' => $tab === 'questions', 'count' => $counts['questions']],
            ['label' => 'Đề/bộ bài', 'href' => route('admin.content.index', ['tab' => 'assessments']), 'active' => $tab === 'assessments', 'count' => $counts['assessments']],
            ['label' => 'Câu hỏi chờ rà soát (OCR)', 'href' => route('admin.content.index', ['tab' => 'drafts']), 'active' => $tab === 'drafts', 'count' => $counts['drafts']],
        ];

        $documents = [];
        $rows = [];
        if ($tab === 'questions') {
            // Admin xem được toàn bộ câu hỏi — cả Kho chung lẫn kho riêng từng giáo viên
            // (chỉ xem để nắm tình hình; ranh giới sở hữu/sửa vẫn theo 6.5, giống cách
            // tab "Đề/bộ bài" đã hiển thị cả đề của giáo viên bên dưới).
            $rows = $this->questions->allLatestWithOwner(50)->map(function ($q) {
                [$label, $tone] = $this->statusLabel($q->status);

                return ['id' => $q->id, 'title' => $q->title, 'type' => $q->type->value, 'status' => $label, 'tone' => $tone, 'owner' => $q->owner_type === OwnerType::Shared ? 'Kho chung' : ('GV '.($q->owner->name ?? ''))];
            })->all();
        } elseif ($tab === 'assessments') {
            $rows = $this->assessments->latestWithCreator(50)->map(function ($a) {
                [$label, $tone] = $this->statusLabel($a->status);

                return ['id' => $a->id, 'title' => $a->title, 'type' => $a->type->value, 'status' => $label, 'tone' => $tone, 'owner' => $a->owner_type === OwnerType::Shared ? 'Kho chung' : ('GV '.($a->creator->name ?? ''))];
            })->all();
        } elseif ($tab === 'drafts') {
            $documents = $this->pendingDocuments();
        } else {
            $rows = $this->materials->latestWithProduct(50)->map(function ($m) {
                [$label, $tone] = $this->statusLabel($m->status);

                return ['id' => $m->id, 'title' => $m->title, 'type' => $m->type, 'status' => $label, 'tone' => $tone, 'owner' => $m->product?->owner_type === OwnerType::Teacher ? 'Giáo viên' : 'Kho chung'];
            })->all();
        }

        return [
            'tab' => $tab,
            'tabs' => $tabs,
            'rows' => $rows,
            'documents' => $documents,
            'total' => $tab === 'drafts' ? count($documents) : ($counts[$tab] ?? count($rows)),
        ];
    }

    /**
     * admin.content.questions.reviewDraft — nhận ?document=<id>; nếu không có thì lấy
     * tài liệu "cần rà soát" gần nhất TRÊN TOÀN "Kho chung" (không giới hạn theo người
     * tải lên — khác Teacher). Trả đủ dữ liệu để màn rà soát sửa/gộp/xóa/thêm tay và
     * chuyển vào Kho chung.
     */
    public function reviewDraftFor(?int $documentId): array
    {
        $document = null;
        if ($documentId !== null) {
            $document = $this->uploadedDocuments->query()->find($documentId);
        }
        $document ??= $this->uploadedDocuments->query()
            ->where('status', UploadedDocumentStatus::NeedsReview)
            ->latest()
            ->first();

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
                $type = $d->type_guess instanceof \App\Enums\QuestionType ? $d->type_guess->value : $d->type_guess;

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
                    'otherDrafts' => $allDrafts->reject(fn ($other) => $other->id === $d->id || $other->promoted_question_id !== null)
                        ->map(fn ($other) => ['id' => $other->id, 'label' => 'Câu '.($other->order + 1)])
                        ->values()->all(),
                ];
            })->all();
        }

        return ['document' => $document, 'drafts' => $drafts];
    }

    /**
     * admin.content.show dùng chung MỘT route cho cả 3 loại nội dung (Material/Question/
     * Assessment) — route chỉ nhận int $content, không có route-model-binding theo loại vì
     * mỗi tab ở admin.content.index đều trỏ "Xem" vào cùng route với id riêng của loại đó.
     * Ta không thể biết chắc id thuộc loại nào chỉ từ con số (hạn chế thiết kế route có sẵn,
     * không sửa route ở đây) nên tra cứu tuần tự Material -> Question -> Assessment.
     *
     * QuestionPublishGuard::canPublish() chỉ áp dụng cho Question (6.2/6.4 quy định điều kiện
     * phát hành CÂU HỎI). Material và Assessment không đi qua guard này — không có quy tắc
     * publish tương ứng nào được đặc tả cho 2 loại đó, nên $publishErrors luôn ræủ ġ{ 2
     * nhánh đó thay vì ép một Material/Assessment qua guard nhận Question làm tham số.
     *
     * @return array{type: string, typeLabel: string, model: mixed, item: array, publishErrors: array, hasBeenAttempted: bool}
     */
    public function showData(int $id): array
    {
        $material = $this->materials->findWithProduct($id);
        if ($material !== null) {
            [$label, $tone] = $this->statusLabel($material->status);

            return [
                'type' => 'material',
                'typeLabel' => self::TYPE_LABELS['material'],
                'model' => $material,
                'item' => ['id' => $material->id, 'title' => $material->title, 'status' => $label, 'tone' => $tone, 'statusValue' => $material->status->value],
                'publishErrors' => [],
                'hasBeenAttempted' => false,
            ];
        }

        /** @var Question|null $question */
        $question = $this->questions->query()->with('bank')->find($id);
        if ($question !== null) {
            [$label, $tone] = $this->statusLabel($question->status);
            $decision = $this->publishGuard->canPublish($question);

            return [
                'type' => 'question',
                'typeLabel' => self::TYPE_LABELS['question'],
                'model' => $question,
                'item' => ['id' => $question->id, 'title' => $question->title, 'status' => $label, 'tone' => $tone, 'statusValue' => $question->status->value],
                'publishErrors' => $decision->allowed ? [] : [$decision->message ?? 'Chưa đủ điều kiện phát hành.'],
                'hasBeenAttempted' => $this->publishGuard->hasBeenAttempted($question),
            ];
        }

        $assessment = $this->assessments->query()->with('creator')->find($id);
        if ($assessment !== null) {
            [$label, $tone] = $this->statusLabel($assessment->status);

            return [
                'type' => 'assessment',
                'typeLabel' => self::TYPE_LABELS['assessment'],
                'model' => $assessment,
                'item' => ['id' => $assessment->id, 'title' => $assessment->title, 'status' => $label, 'tone' => $tone, 'statusValue' => $assessment->status->value],
                'publishErrors' => [],
                'hasBeenAttempted' => false,
            ];
        }

        return [
            'type' => null,
            'typeLabel' => '',
            'model' => null,
            'item' => ['id' => $id, 'title' => 'Không tìm thấy nội dung', 'status' => '', 'tone' => 'neutral', 'statusValue' => null],
            'publishErrors' => [],
            'hasBeenAttempted' => false,
        ];
    }

    // ================= Học liệu (Material) =================

    public function materialCreateFormData(): array
    {
        return [
            'products' => $this->products->query()->orderBy('title')->get(['id', 'title'])->all(),
            'parents' => $this->materials->query()->with('product')->orderBy('product_id')->orderBy('order')->get()
                ->map(fn ($m) => ['id' => $m->id, 'label' => ($m->product->title ?? '?').' › '.$m->title])->all(),
            'assessments' => $this->assessments->query()->orderBy('title')->get(['id', 'title'])->all(),
            'types' => ['chapter' => 'Chương', 'section' => 'Bài/Mục', 'assessment_ref' => 'Tham chiếu đề/bộ bài'],
            'statuses' => $this->statusOptions(),
        ];
    }

    public function materialStore(array $data): Material
    {
        return $this->materials->create([
            'product_id' => $data['product_id'],
            'parent_id' => $data['parent_id'] ?: null,
            'type' => $data['type'],
            'title' => $data['title'],
            'order' => $data['order'] ?? 0,
            'assessment_id' => $data['type'] === 'assessment_ref' ? ($data['assessment_id'] ?: null) : null,
            'status' => $data['status'],
        ]);
    }

    public function materialEditFormData(int $id): array
    {
        return array_merge($this->materialCreateFormData(), [
            'material' => $this->materials->query()->findOrFail($id),
        ]);
    }

    public function materialUpdate(Material $material, array $data): Material
    {
        return $this->materials->update($material, [
            'product_id' => $data['product_id'],
            'parent_id' => $data['parent_id'] ?: null,
            'type' => $data['type'],
            'title' => $data['title'],
            'order' => $data['order'] ?? 0,
            'assessment_id' => $data['type'] === 'assessment_ref' ? ($data['assessment_id'] ?: null) : null,
            'status' => $data['status'],
        ]);
    }

    /** Không có guard đặc thù cho Material (chỉ Question mới có điều kiện phát hành, 6.2). */
    public function materialPublish(Material $material): Material
    {
        return $this->materials->update($material, ['status' => ContentStatus::Published->value]);
    }

    /** Trả về nháp — PHẢI có lý do + audit log (10.4). */
    public function materialReject(Material $material, string $reason): Material
    {
        Material::$auditReason = $reason;
        $this->materials->update($material, ['status' => ContentStatus::Draft->value]);
        Material::$auditReason = null;

        return $material;
    }

    /** Lưu trữ — cách "gỡ khỏi lưu hành" cho nội dung (không có trạng thái xóa, Table 27). */
    public function materialArchive(Material $material, string $reason): Material
    {
        Material::$auditReason = $reason;
        $this->materials->update($material, ['status' => ContentStatus::Archived->value]);
        Material::$auditReason = null;

        return $material;
    }

    // ================= Câu hỏi kho chung (Question, 6.5) =================

    public function questionCreateFormData(): array
    {
        return [
            'types' => ['coding' => 'Lập trình (OJ)', 'mcq' => 'Trắc nghiệm', 'fill_blank' => 'Điền khuyết'],
            'visibilities' => ['public' => 'Công khai', 'private' => 'Riêng tư (nội bộ)'],
        ];
    }

    /**
     * admin.content.questions.store — luôn tạo vào "Kho chung" (owner_type=shared) vì admin
     * đang thao tác ở mục Nội dung CHUNG (6.5) — câu hỏi riêng của giáo viên được tạo ở
     * teacher.questions.create (owner_type=teacher), không đi qua đây.
     */
    public function questionStore(User $admin, array $data): Question
    {
        if ($this->questions->query()->where('code', $data['code'])->exists()) {
            throw ValidationException::withMessages(['code' => 'Mã câu hỏi này đã tồn tại, chọn mã khác.']);
        }

        return $this->questions->create([
            'bank_id' => $this->sharedBank()->id,
            'code' => $data['code'],
            'type' => $data['type'],
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'points' => $data['points'] ?? 0,
            'grading_config' => $this->buildGradingConfig($data['type'], $data),
            'owner_type' => OwnerType::Shared->value,
            'owner_id' => null,
            'visibility' => $data['visibility'] ?? Visibility::Public->value,
            'status' => ContentStatus::Draft->value,
            'version' => 1,
            'created_by' => $admin->id,
        ]);
    }

    public function questionEditFormData(int $id): array
    {
        /** @var Question $question */
        $question = $this->questions->query()->findOrFail($id);

        return array_merge($this->questionCreateFormData(), [
            'question' => $question,
            'hasBeenAttempted' => $this->publishGuard->hasBeenAttempted($question),
        ]);
    }

    /**
     * admin.content.questions.update — CHẶN sửa âm thầm câu đã có người làm (6.2: "Câu hỏi
     * đã có người làm — sửa nội dung phải tạo phiên bản mới"). Admin phải dùng
     * questionCreateNewVersion() thay vì gọi hàm này khi $hasBeenAttempted = true.
     */
    public function questionUpdate(Question $question, array $data): Question
    {
        if ($this->publishGuard->hasBeenAttempted($question)) {
            throw ValidationException::withMessages([
                'code' => 'Câu hỏi này đã có học sinh làm bài — không thể sửa trực tiếp, hãy dùng "Tạo phiên bản mới" (6.2).',
            ]);
        }

        return $this->questions->update($question, [
            'code' => $data['code'],
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'points' => $data['points'] ?? 0,
            'grading_config' => $this->buildGradingConfig($question->type->value, $data),
            'visibility' => $data['visibility'] ?? Visibility::Public->value,
        ]);
    }

    /** Tạo bản version mới thay vì sửa âm thầm (6.2) — dùng khi câu đã có người làm. */
    public function questionCreateNewVersion(Question $question, array $data): Question
    {
        return $this->publishGuard->createNewVersion($question, [
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'points' => $data['points'] ?? 0,
            'grading_config' => $this->buildGradingConfig($question->type->value, $data),
            'visibility' => $data['visibility'] ?? Visibility::Public->value,
        ]);
    }

    /**
     * Dựng grading_config đúng cấu trúc tối thiểu theo từng loại câu (6.1/6.2) từ input có
     * cấu trúc trên form — KHÔNG bắt admin tự viết JSON tay (dễ sai, khó kiểm tra lỗi rõ).
     * Test case coding dùng định dạng đơn giản "input|||output" mỗi dòng — cố ý CHƯA làm
     * trình tải file test/kéo-thả (phạm vi lớn hơn nhiều, để dành cho màn hình OJ chuyên biệt
     * sau này) — vẫn đủ để QuestionPublishGuard::hasMinimumGradingConfig() nhận đúng cấu trúc.
     */
    private function buildGradingConfig(string $type, array $data): array
    {
        return match ($type) {
            'mcq' => [
                'options' => array_values(array_filter($data['options'] ?? [], fn ($o) => filled($o))),
                'correct_options' => isset($data['correct_option']) && $data['correct_option'] !== ''
                    ? [(int) $data['correct_option']]
                    : [],
            ],
            'fill_blank' => [
                'accepted_answers' => array_values(array_filter(
                    array_map('trim', preg_split('/\r?\n|,/', (string) ($data['accepted_answers'] ?? ''))),
                    fn ($a) => $a !== ''
                )),
                'case_sensitive' => (bool) ($data['case_sensitive'] ?? false),
            ],
            'coding' => [
                'test_cases' => $this->parseTestCases($data['test_cases_raw'] ?? ''),
                'time_limit_ms' => filled($data['time_limit_ms'] ?? null) ? (int) $data['time_limit_ms'] : null,
                'memory_limit_mb' => filled($data['memory_limit_mb'] ?? null) ? (int) $data['memory_limit_mb'] : null,
            ],
            default => [],
        };
    }

    /** Mỗi dòng "input|||output" -> 1 test case. Dòng không đúng định dạng bị b�o qua. */
    private function parseTestCases(string $raw): array
    {
        $cases = [];
        foreach (preg_split('/\r?\n/', trim($raw)) as $line) {
            if (blank($line) || ! str_contains($line, '|||')) {
                continue;
            }
            [$input, $output] = explode('|||', $line, 2);
            $cases[] = ['input' => $input, 'output' => $output];
        }

        return $cases;
    }

    /** Phát hành — PHẢI qua QuestionPublishGuard (6.2/6.4), không có ngoại lệ cho admin. */
    public function questionPublish(Question $question): array
    {
        $decision = $this->publishGuard->canPublish($question);
        if (! $decision->allowed) {
            return ['ok' => false, 'message' => $decision->message ?? 'Chưa đủ điều kiện phát hành.'];
        }

        $this->questions->update($question, ['status' => ContentStatus::Published->value]);

        return ['ok' => true, 'message' => null];
    }

    /** Trả về nháp — PHẢI có lý do + audit log (10.4). Auditable đã gắn sẵn ở Question model. */
    public function questionReject(Question $question, string $reason): Question
    {
        Question::$auditReason = $reason;
        $this->questions->update($question, ['status' => ContentStatus::Draft->value]);
        Question::$auditReason = null;

        return $question;
    }

    public function questionArchive(Question $question, string $reason): Question
    {
        Question::$auditReason = $reason;
        $this->questions->update($question, ['status' => ContentStatus::Archived->value]);
        Question::$auditReason = null;

        return $question;
    }

    // ================= Đề/bộ bài (Assessment) =================
    // Phạm vi cố ý giới hạn ở metadata (không có trình xây danh sách câu hỏi/items ở đây) —
    // gắn/gỡ câu hỏi vào đề là luồng riêng của giáo viên khi soạn đề (TEA-xx), không lặp lại
    // ở admin để tránh 2 nơi có thể sửa cùng 1 đề theo 2 luật khác nhau.

    public function assessmentCreateFormData(): array
    {
        return [
            'types' => [
                'practice' => 'Luyện tập', 'assignment' => 'Bài giao', 'exam' => 'Đề thi', 'competition_paper' => 'Đề thi đấu',
            ],
            'publishAnswerRules' => [
                'never' => 'Không bao giờ hiện đáp án', 'after_deadline' => 'Hiện sau hạn nộp', 'immediately' => 'Hiện ngay sau khi nộp',
            ],
        ];
    }

    public function assessmentStore(User $admin, array $data): Assessment
    {
        return $this->assessments->create([
            'title' => $data['title'],
            'type' => $data['type'],
            'total_points' => $data['total_points'] ?? 0,
            'duration_minutes' => $data['duration_minutes'] ?: null,
            'publish_answer_rule' => $data['publish_answer_rule'] ?? 'never',
            'status' => ContentStatus::Draft->value,
            'version' => 1,
            'owner_type' => OwnerType::Shared->value,
            'owner_id' => null,
            'created_by' => $admin->id,
        ]);
    }

    public function assessmentEditFormData(int $id): array
    {
        return array_merge($this->assessmentCreateFormData(), [
            'assessment' => $this->assessments->query()->findOrFail($id),
        ]);
    }

    public function assessmentUpdate(Assessment $assessment, array $data): Assessment
    {
        return $this->assessments->update($assessment, [
            'title' => $data['title'],
            'type' => $data['type'],
            'total_points' => $data['total_points'] ?? 0,
            'duration_minutes' => $data['duration_minutes'] ?: null,
            'publish_answer_rule' => $data['publish_answer_rule'] ?? 'never',
        ]);
    }

    public function assessmentPublish(Assessment $assessment): Assessment
    {
        return $this->assessments->update($assessment, ['status' => ContentStatus::Published->value]);
    }

    public function assessmentReject(Assessment $assessment, string $reason): Assessment
    {
        Assessment::$auditReason = $reason;
        $this->assessments->update($assessment, ['status' => ContentStatus::Draft->value]);
        Assessment::$auditReason = null;

        return $assessment;
    }

    public function assessmentArchive(Assessment $assessment, string $reason): Assessment
    {
        Assessment::$auditReason = $reason;
        $this->assessments->update($assessment, ['status' => ContentStatus::Archived->value]);
        Assessment::$auditReason = null;

        return $assessment;
    }

    /** @return array<string, string> */
    private function statusOptions(): array
    {
        return [
            ContentStatus::Draft->value => 'Nháp',
            ContentStatus::PendingReview->value => 'Chờ duyệt',
            ContentStatus::Published->value => 'Phát hành',
            ContentStatus::Archived->value => 'Lưu trữ',
        ];
    }

}
