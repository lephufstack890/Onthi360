<?php

namespace App\Services\Admin;

use App\Enums\AssessmentContentMode;
use App\Enums\AssessmentType;
use App\Enums\ContentStatus;
use App\Enums\OwnerType;
use App\Enums\UploadedDocumentStatus;
use App\Enums\Visibility;
use App\Models\Assessment;
use App\Models\AssessmentCodingItem;
use App\Models\Material;
use App\Models\Product;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\RatingSummary;
use App\Models\Review;
use App\Models\Tag;
use App\Models\UploadedDocument;
use App\Models\User;
use App\Enums\ReviewTargetType;
use App\Repositories\Contracts\AssessmentRepositoryInterface;
use App\Repositories\Contracts\DraftQuestionRepositoryInterface;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\QuestionRepositoryInterface;
use App\Repositories\Contracts\TagRepositoryInterface;
use App\Repositories\Contracts\UploadedDocumentRepositoryInterface;
use App\Services\PdfAssessmentEditingService;
use App\Services\PdfAssessmentPublishGuard;
use App\Services\PdfBulkImportService;
use App\Services\PdfTextExtractor;
use App\Services\QuestionPublishGuard;
use App\Support\UniqueCodeFromFilename;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

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
        private PdfAssessmentPublishGuard $pdfAssessmentGuard,
        // SỬA 18/8 (2): logic soạn đề PDF (file/đáp án/bài lập trình con) chuyển hết sang
        // App\Services\PdfAssessmentEditingService — dùng CHUNG với Teacher\AssessmentService
        // (Giáo viên cũng tải đề PDF riêng, 16/8 mục 1.2) thay vì lặp lại ở 2 nơi.
        private PdfAssessmentEditingService $pdfEditing,
        // SỬA 19/8 (Giai đoạn 3 — "Bộ đề"): nhập hàng loạt nhiều đề PDF cùng lúc, dùng CHUNG
        // với Teacher\AssessmentService — xem App\Services\PdfBulkImportService.
        private PdfBulkImportService $pdfBulkImport,
        // SỬA 19/8 (Giai đoạn 6 — "Gắn tag/chủ đề cho câu hỏi"): xem App\Models\Tag.
        private TagRepositoryInterface $tags,
        // SỬA 3/9 (nối trích PDF thành text cho đề bài nhập ZIP) — xem
        // placeholderBodyForZipImport() bên dưới.
        private PdfTextExtractor $pdfTextExtractor,
    ) {}

    public static function maxPdfKb(): int
    {
        return PdfAssessmentEditingService::maxPdfKb();
    }

    public static function maxBulkSourcePdfKb(): int
    {
        return PdfBulkImportService::maxSourcePdfKb();
    }

    // SỬA 25/8 (tải bài hàng loạt qua ZIP, xem materialsBulkImportFromZip()) — 50MB vì gói
    // này gồm NHIỀU tệp PDF nội dung (mỗi bài 1 tệp), khác MAX_ZIP_PACKAGE_KB (20MB) của gói
    // OT360-QPACK chỉ có 1 câu hỏi + test case.
    private const MAX_BULK_MATERIAL_ZIP_KB = 51200;

    public static function maxBulkMaterialZipKb(): int
    {
        return self::MAX_BULK_MATERIAL_ZIP_KB;
    }

    private const TYPE_LABELS = [
        'material' => 'Học liệu',
        'question' => 'Câu hỏi (kho chung)',
        'assessment' => 'Đề/bộ bài',
    ];

    /**
     * Nhãn tiếng Việt cho Material::type — nguồn DUY NHẤT, dùng cả ở dropdown tạo/sửa học
     * liệu (materialCreateFormData()) lẫn cột "Loại" của bảng Nội dung (indexData()). Trước
     * đây bảng Nội dung hiện thẳng $m->type (chuỗi thô "assessment_ref"...) — khó hiểu với
     * người dùng, đã báo lại và sửa ở đây.
     */
    private const MATERIAL_TYPE_LABELS = [
        'chapter' => 'Chương',
        'section' => 'Bài/Mục',
        'assessment_ref' => 'Tham chiếu đề/bộ bài',
    ];

    /** Nhãn tiếng Việt cho Question::type — nguồn DUY NHẤT, dùng cả ở dropdown tạo/sửa câu hỏi (questionCreateFormData()) lẫn cột "Loại" của bảng Nội dung (indexData()), cùng lý do như MATERIAL_TYPE_LABELS ở trên. */
    private const QUESTION_TYPE_LABELS = [
        'coding' => 'Lập trình (OJ)',
        'mcq' => 'Trắc nghiệm',
        'fill_blank' => 'Điền khuyết',
        // SỬA 31/8 (2, "mở rộng ZIP bài tập") — chỉ tạo được qua nhập ZIP, xem QuestionType::Composite.
        'composite' => 'Nhiều phần (composite)',
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
            // SỬA 31/8 — đếm đúng số lượng hiện ở tab "Câu hỏi (Kho chung + Giáo viên)":
            // loại các câu hỏi là "bài tập đính kèm sản phẩm" (product_id khác null, quản lý
            // riêng ở trang Sản phẩm), khớp với allLatestWithOwner() bên dưới.
            'questions' => $this->questions->query()->whereNull('product_id')->count(),
            'assessments' => $this->assessments->count(),
            'drafts' => $this->draftQuestions->countPendingReview(),
            // SỬA 19/8 (Giai đoạn 6): xem nhánh $tab === 'tags' bên dưới.
            'tags' => $this->tags->count(),
        ];

        // SỬA 26/8 ("gộp Học liệu vào Sản phẩm & quyền"): tab "Học liệu" đã bỏ khỏi đây —
        // thêm/sửa/xoá học liệu giờ làm ngay trong trang chi tiết từng sản phẩm (admin
        // Sản phẩm & quyền), xem ProductService::showData()/buildMaterialsTree(). Link cũ
        // ?tab=materials được ContentController::index() tự đưa về tab mặc định bên dưới.
        $tabs = [
            ['label' => 'Câu hỏi (Kho chung + Giáo viên)', 'href' => route('admin.content.index', ['tab' => 'questions']), 'active' => $tab === 'questions', 'count' => $counts['questions']],
            ['label' => 'Đề/bộ bài', 'href' => route('admin.content.index', ['tab' => 'assessments']), 'active' => $tab === 'assessments', 'count' => $counts['assessments']],
            ['label' => 'Câu hỏi chờ rà soát (OCR)', 'href' => route('admin.content.index', ['tab' => 'drafts']), 'active' => $tab === 'drafts', 'count' => $counts['drafts']],
            ['label' => 'Tag/Chuyên đề', 'href' => route('admin.content.index', ['tab' => 'tags']), 'active' => $tab === 'tags', 'count' => $counts['tags']],
        ];

        $documents = [];
        $rows = [];
        $tags = [];
        if ($tab === 'questions') {
            // Admin xem được toàn bộ câu hỏi — cả Kho chung lẫn kho riêng từng giáo viên
            // (chỉ xem để nắm tình hình; ranh giới sở hữu/sửa vẫn theo 6.5, giống cách
            // tab "Đề/bộ bài" đã hiển thị cả đề của giáo viên bên dưới).
            $rows = $this->questions->allLatestWithOwner(50)->map(function ($q) {
                [$label, $tone] = $this->statusLabel($q->status);

                return ['id' => $q->id, 'title' => $q->title, 'type' => self::QUESTION_TYPE_LABELS[$q->type->value] ?? $q->type->value, 'status' => $label, 'tone' => $tone, 'owner' => $q->owner_type === OwnerType::Shared ? 'Kho chung' : ('GV '.($q->owner->name ?? ''))];
            })->all();
        } elseif ($tab === 'assessments') {
            $rows = $this->assessments->latestWithCreator(50)->map(function ($a) {
                [$label, $tone] = $this->statusLabel($a->status);
                $isTeacherOwned = $a->owner_type !== OwnerType::Shared;

                return [
                    'id' => $a->id, 'title' => $a->title, 'type' => $a->type->label(), 'status' => $label, 'tone' => $tone,
                    'owner' => $isTeacherOwned ? 'GV '.($a->creator->name ?? '') : 'Kho chung',
                    // SỬA 19/8 (Giai đoạn 4 — "Admin duyệt đưa đề GV ra kho chung"): chỉ đề
                    // của giáo viên mới cần nút duyệt — đề đã ở Kho chung thì không có gì để
                    // duyệt thêm (xem assessmentPromoteToShared() bên dưới + nút bấm ở
                    // admin/content/index.blade.php).
                    'canPromoteToShared' => $isTeacherOwned,
                ];
            })->all();
        } elseif ($tab === 'drafts') {
            $documents = $this->pendingDocuments();
        } elseif ($tab === 'tags') {
            // SỬA 19/8 (Giai đoạn 6) — withCount('questions') để hiện rõ tag nào đang được
            // dùng cho bao nhiêu câu hỏi trước khi admin lỡ tay xoá (xem tagDestroy() bên dưới,
            // xoá KHÔNG chặn dù đang dùng — chỉ là nhãn, không phải nội dung theo 6.2).
            $tags = $this->tags->query()->withCount('questions')->orderBy('name')->get()
                ->map(fn (Tag $t) => ['id' => $t->id, 'name' => $t->name, 'questionsCount' => $t->questions_count])
                ->all();
        } else {
            $rows = $this->materials->latestWithProduct(50)->map(function ($m) {
                [$label, $tone] = $this->statusLabel($m->status);

                // SỬA 25/8 (7) — "thêm tính năng xóa cho admin": chỉ tab Học liệu mới có nút
                // Xoá (khách xác nhận phạm vi CHỈ Học liệu, không áp dụng Câu hỏi/Đề/Tag) —
                // xem materialDelete() bên dưới + nút bấm ở admin/content/index.blade.php.
                return ['id' => $m->id, 'title' => $m->title, 'type' => self::MATERIAL_TYPE_LABELS[$m->type] ?? $m->type, 'status' => $label, 'tone' => $tone, 'owner' => $m->product?->owner_type === OwnerType::Teacher ? 'Giáo viên' : 'Kho chung', 'canDelete' => true];
            })->all();
        }

        return [
            'tab' => $tab,
            'tabs' => $tabs,
            'rows' => $rows,
            'documents' => $documents,
            'tags' => $tags,
            'total' => $tab === 'drafts' ? count($documents) : ($counts[$tab] ?? count($rows)),
        ];
    }

    /**
     * admin.content.tags.store — tạo tag mới. findOrCreateByName() (không phải create() thẳng)
     * để tự khớp/không tạo trùng nếu admin lỡ gõ lại đúng tên đã có (không phân biệt hoa/
     * thường, xem TagRepository::findOrCreateByName()).
     */
    public function tagStore(string $name): Tag
    {
        return $this->tags->findOrCreateByName($name);
    }

    /**
     * admin.content.tags.update — đổi tên tag. Ảnh hưởng NGAY tới mọi câu hỏi/bộ lọc đang
     * dùng tag này (tag chỉ là 1 nhãn dùng chung, không phải nội dung riêng của câu hỏi nào —
     * không cần tạo version mới như Question/Assessment, 6.2 không áp dụng ở đây).
     *
     * @throws ValidationException nếu tên mới trùng 1 tag KHÁC đã có sẵn.
     */
    public function tagUpdate(Tag $tag, string $name): Tag
    {
        $name = trim($name);
        $existing = Tag::where('name', $name)->where('id', '!=', $tag->id)->first();

        if ($existing !== null) {
            throw ValidationException::withMessages(['name' => 'Đã có tag khác trùng tên này — gộp bằng cách xoá 1 trong 2 thay vì đổi tên trùng.']);
        }

        return $this->tags->update($tag, ['name' => $name]);
    }

    /**
     * admin.content.tags.destroy — xoá hẳn (KHÔNG lưu trữ/soft-delete như Material/Question/
     * Assessment, vì tag chỉ là 1 nhãn phân loại, không phải nội dung học tập theo 6.2) —
     * cascadeOnDelete ở bảng question_tag tự gỡ tag khỏi mọi câu hỏi đang gắn, KHÔNG xoá câu
     * hỏi. Cố ý KHÔNG chặn xoá dù đang có câu hỏi dùng (khác Assessment/Material vốn chặn xoá
     * khi có phụ thuộc dữ liệu xếp hạng) — gỡ nhãn khỏi câu hỏi không làm mất/sai dữ liệu gì.
     */
    public function tagDestroy(Tag $tag): void
    {
        $this->tags->delete($tag);
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

        // SỬA 18/8: thêm eager-load items.question — trước đây chỉ load 'creator', nên màn
        // show.blade.php không có gì để hiện danh sách câu hỏi trong đề (chỉ hiện được TODO).
        // SỬA 18/8 (2, đề PDF): thêm eager-load answerKeys/codingItems.testCases — chỉ có dữ
        // liệu khi content_mode = pdf_answer_sheet, rỗng (không lỗi) khi content_mode = structured.
        $assessment = $this->assessments->query()
            ->with(['creator', 'items.question', 'answerKeys', 'codingItems.testCases'])
            ->find($id);
        if ($assessment !== null) {
            [$label, $tone] = $this->statusLabel($assessment->status);

            $publishErrors = [];
            if ($assessment->isPdfMode()) {
                $decision = $this->pdfAssessmentGuard->canPublish($assessment);
                $publishErrors = $decision->allowed ? [] : [$decision->message ?? 'Chưa đủ điều kiện phát hành.'];
            }

            return [
                'type' => 'assessment',
                'typeLabel' => self::TYPE_LABELS['assessment'],
                'model' => $assessment,
                'item' => ['id' => $assessment->id, 'title' => $assessment->title, 'status' => $label, 'tone' => $tone, 'statusValue' => $assessment->status->value],
                'publishErrors' => $publishErrors,
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

    /**
     * SỬA 26/8 ("gộp Học liệu vào Sản phẩm & quyền") — $selectedProductId: khi bấm "+ Thêm
     * học liệu" từ NGAY trang chi tiết 1 sản phẩm (admin/products/show.blade.php), sản phẩm
     * đó được truyền qua ?product_id= để form tự điền sẵn, khỏi phải chọn lại; đồng thời danh
     * sách "mục cha" cũng chỉ còn học liệu CỦA ĐÚNG sản phẩm đó (đỡ rối vì trước đây liệt kê
     * lẫn lộn học liệu của mọi sản phẩm). Không truyền (null) thì form vẫn hoạt động y hệt cũ
     * (chọn sản phẩm từ dropdown đầy đủ) — phòng khi có người vào thẳng URL không qua sản phẩm.
     */
    public function materialCreateFormData(?int $selectedProductId = null): array
    {
        $parentsQuery = $this->materials->query()->with('product')->orderBy('product_id')->orderBy('order');
        if ($selectedProductId !== null) {
            $parentsQuery->where('product_id', $selectedProductId);
        }

        return [
            'products' => $this->products->query()->orderBy('title')->get(['id', 'title'])->all(),
            'parents' => $parentsQuery->get()
                ->map(fn ($m) => ['id' => $m->id, 'label' => ($m->product->title ?? '?').' › '.$m->title])->all(),
            'assessments' => $this->assessments->query()->orderBy('title')->get(['id', 'title'])->all(),
            'types' => self::MATERIAL_TYPE_LABELS,
            'statuses' => $this->statusOptions(),
            'selectedProductId' => $selectedProductId,
        ];
    }

    /**
     * SỬA 25/8 (tải bài — 16 mục "tải bài hàng loạt"): $data['code']/$data['pdf'] (UploadedFile,
     * có thể null) là 2 trường MỚI, tùy chọn — xem resolveMaterialCode()/storeMaterialPdf().
     * Vẫn tạo được Material KHÔNG có mã/PDF như trước (ví dụ Material chỉ là mục lục cha), nên
     * không phá luồng cũ.
     */
    public function materialStore(array $data): Material
    {
        $pdf = $data['pdf'] ?? null;
        $code = $this->resolveMaterialCode((int) $data['product_id'], $data['code'] ?? null, $pdf);

        $attributes = [
            'product_id' => $data['product_id'],
            'parent_id' => $data['parent_id'] ?: null,
            'type' => $data['type'],
            'title' => $data['title'],
            'order' => $data['order'] ?? 0,
            'assessment_id' => $data['type'] === 'assessment_ref' ? ($data['assessment_id'] ?: null) : null,
            'status' => $data['status'],
            'code' => $code,
        ];

        if ($pdf !== null) {
            // $code chắc chắn khác null ở đây — pdf khác null thì resolveMaterialCode() luôn
            // trả về 1 mã (tự gõ hoặc tự sinh từ tên tệp), không bao giờ về nhánh "để NULL".
            $attributes = array_merge($attributes, $this->storeMaterialPdf((int) $data['product_id'], $code, $pdf));
        }

        return $this->materials->create($attributes);
    }

    public function materialEditFormData(int $id): array
    {
        return array_merge($this->materialCreateFormData(), [
            'material' => $this->materials->query()->findOrFail($id),
        ]);
    }

    /**
     * SỬA 25/8: khách yêu cầu rõ "các bài cần có cơ chế sửa sau khi nhập" — mã bài và PDF ĐỀU
     * sửa lại được ở đây, không phải tải lên xong là khóa cứng:
     *  - Đổi mã: gõ mã mới vào $data['code'], assertMaterialCodeAvailable() bỏ qua CHÍNH bản ghi
     *    đang sửa (2 mục "$material->id" dưới) nên không tự báo trùng với mã cũ của chính nó.
     *  - Thay PDF: có tải $data['pdf'] mới thì XÓA file PDF cũ trên disk rồi lưu file mới — nếu
     *    không tải gì thì giữ nguyên pdf_path hiện tại (không đụng vào).
     */
    public function materialUpdate(Material $material, array $data): Material
    {
        $pdf = $data['pdf'] ?? null;
        $code = $this->resolveMaterialCode((int) $data['product_id'], $data['code'] ?? null, $pdf, $material->id);

        $attributes = [
            'product_id' => $data['product_id'],
            'parent_id' => $data['parent_id'] ?: null,
            'type' => $data['type'],
            'title' => $data['title'],
            'order' => $data['order'] ?? 0,
            'assessment_id' => $data['type'] === 'assessment_ref' ? ($data['assessment_id'] ?: null) : null,
            'status' => $data['status'],
            'code' => $code,
        ];

        if ($pdf !== null) {
            if ($material->pdf_path) {
                Storage::disk('local')->delete($material->pdf_path);
            }

            $attributes = array_merge($attributes, $this->storeMaterialPdf((int) $data['product_id'], $code, $pdf));
        }

        return $this->materials->update($material, $attributes);
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

    /**
     * SỬA 25/8 (7) — "thêm tính năng xóa cho admin, xóa luôn file liên quan tránh rác": XÓA
     * THẬT (khác materialArchive() ở trên — Table 27 vốn không có trạng thái "đã xóa", chỉ có
     * "lưu trữ"; khách đã được hỏi lại và xác nhận muốn xóa thật, không thể khôi phục).
     *
     * Thứ tự bắt buộc: PHẢI thu thập + xóa file PDF trên disk của material này VÀ TOÀN BỘ
     * material con cháu TRƯỚC khi xóa bản ghi — 1 khi bản ghi mất thì đường dẫn pdf_path cũng
     * mất theo, không còn cách nào dọn file nữa (đúng lo ngại "về lâu về dài nặng máy chủ" của
     * khách). materials.parent_id VÀ class_materials.material_id đều đã có cascadeOnDelete() ở
     * DB (xem migration create_materials_table/create_class_materials_table) nên chỉ cần
     * $this->materials->delete($material) là DB tự dọn sạch các bản ghi con + liên kết lớp —
     * không cần tự viết vòng lặp xóa từng bản ghi con.
     */
    public function materialDelete(Material $material): void
    {
        $ids = $this->collectMaterialAndDescendantIds($material);

        $pdfPaths = $this->materials->query()
            ->whereIn('id', $ids)
            ->whereNotNull('pdf_path')
            ->pluck('pdf_path');

        foreach ($pdfPaths as $path) {
            Storage::disk('local')->delete($path);
        }

        // Dọn luôn đánh giá/tổng hợp điểm mồ côi trỏ vào material sắp không còn tồn tại (9.x)
        // — không phải file, nhưng tránh để lại dữ liệu rác tham chiếu vào bản ghi đã xóa.
        Review::where('target_type', ReviewTargetType::Material->value)->whereIn('target_id', $ids)->delete();
        RatingSummary::where('target_type', ReviewTargetType::Material->value)->whereIn('target_id', $ids)->delete();

        $this->materials->delete($material);
    }

    /**
     * $material + TOÀN BỘ con cháu (đệ quy theo parent_id, không giới hạn độ sâu) — dùng để
     * biết cần xóa những file PDF nào TRƯỚC KHI cascadeOnDelete() ở DB xóa các bản ghi con.
     *
     * @return array<int, int>
     */
    private function collectMaterialAndDescendantIds(Material $material): array
    {
        $ids = [$material->id];
        $queue = [$material->id];

        while ($queue !== []) {
            $childIds = $this->materials->query()->whereIn('parent_id', $queue)->pluck('id')->all();
            if ($childIds === []) {
                break;
            }
            $ids = array_merge($ids, $childIds);
            $queue = $childIds;
        }

        return $ids;
    }

    /**
     * Xác định giá trị 'code' cuối cùng khi tạo/sửa 1 Material — DÙNG CHUNG cho materialStore()
     * và materialUpdate() (nên đổi quy tắc chỉ cần sửa 1 chỗ):
     *  - Admin tự gõ mã (khác rỗng) -> kiểm tra trùng TRONG CÙNG sản phẩm rồi dùng nguyên văn
     *    (assertMaterialCodeAvailable()).
     *  - Bỏ trống NHƯNG có tải PDF kèm -> tự sinh mã từ TÊN GỐC tệp PDF (giống cơ chế
     *    questionStoreFromZipPackage(), qua deriveUniqueMaterialCode()).
     *  - Bỏ trống VÀ không có PDF -> để NULL (Material chỉ làm mục lục/chương cha, chưa cần mã).
     *
     * $ignoreMaterialId: khi SỬA, truyền $material->id để bỏ qua CHÍNH bản ghi đang sửa lúc dò
     * trùng mã — không truyền (null) thì hiểu là đang TẠO MỚI, không có gì để bỏ qua.
     */
    private function resolveMaterialCode(int $productId, ?string $rawCode, ?UploadedFile $pdf, ?int $ignoreMaterialId = null): ?string
    {
        $code = trim((string) $rawCode);

        if ($code !== '') {
            $this->assertMaterialCodeAvailable($productId, $code, $ignoreMaterialId);

            return $code;
        }

        if ($pdf !== null) {
            return $this->deriveUniqueMaterialCode($productId, $pdf->getClientOriginalName(), $ignoreMaterialId);
        }

        return null;
    }

    /**
     * Mã bài tự sinh từ TÊN GỐC 1 tệp (PDF tải tay hoặc 1 mục trong ZIP hàng loạt) — duy nhất
     * TRONG PHẠM VI 1 sản phẩm ($productId), KHÁC deriveUniqueQuestionCode() (duy nhất TOÀN hệ
     * thống) vì 2 quyển sách/chuyên đề khác nhau được phép dùng trùng mã bài. Thuật toán dùng
     * CHUNG qua App\Support\UniqueCodeFromFilename — xem docblock lớp đó.
     */
    private function deriveUniqueMaterialCode(int $productId, string $originalFilename, ?int $ignoreMaterialId = null): string
    {
        return UniqueCodeFromFilename::generate(
            $originalFilename,
            fn (string $code) => $this->materialCodeExists($productId, $code, $ignoreMaterialId),
        );
    }

    /**
     * Ném lỗi validate nếu mã bài đã dùng TRONG CÙNG sản phẩm — chỉ gọi khi ADMIN TỰ GÕ mã
     * (nhánh tự sinh ở deriveUniqueMaterialCode() đã tự thêm hậu tố "-2", "-3"... nên không bao
     * giờ trùng, không cần assert).
     */
    private function assertMaterialCodeAvailable(int $productId, string $code, ?int $ignoreMaterialId = null): void
    {
        if ($this->materialCodeExists($productId, $code, $ignoreMaterialId)) {
            throw ValidationException::withMessages([
                'code' => "Mã bài \"{$code}\" đã được dùng trong sản phẩm này, đổi mã khác.",
            ]);
        }
    }

    private function materialCodeExists(int $productId, string $code, ?int $ignoreMaterialId = null): bool
    {
        return $this->materials->query()
            ->where('product_id', $productId)
            ->where('code', $code)
            ->when($ignoreMaterialId, fn ($q) => $q->where('id', '!=', $ignoreMaterialId))
            ->exists();
    }

    /**
     * Lưu 1 tệp PDF bài học vào disk 'local' (riêng tư — giống quy ước
     * PdfAssessmentEditingService::PDF_DISK, xem migration add_code_and_pdf_to_materials_table).
     * Đặt tên tệp theo $code (đã được resolveMaterialCode() đảm bảo duy nhất trong sản phẩm) chứ
     * không theo material->id, vì lúc TẠO MỚI chưa có id — $code thì luôn có sẵn trước khi lưu.
     *
     * @return array{pdf_path: string, pdf_original_name: string}
     */
    private function storeMaterialPdf(int $productId, string $code, UploadedFile $pdf): array
    {
        $path = "materials/{$productId}/{$code}.pdf";
        Storage::disk('local')->putFileAs("materials/{$productId}", $pdf, "{$code}.pdf");

        return ['pdf_path' => $path, 'pdf_original_name' => $pdf->getClientOriginalName()];
    }

    // ================= Học liệu — "tải bài hàng loạt" qua ZIP (25/8) =================
    // Khác materialsStore/Update ở trên (nhập TỪNG bài 1) — bulk tạo NHIỀU Material cùng lúc,
    // mỗi tệp .pdf ở gốc ZIP = 1 bài, mã bài lấy thẳng từ tên tệp đó (khách chốt 25/8: "đặt tên
    // các gói zip chính là mã bài"). $type/$parentId/$status áp dụng CHUNG cho cả gói — bài nào
    // cần khác thì sửa lại riêng sau qua materialsEdit (đã hỗ trợ sửa mã + thay PDF, xem
    // materialUpdate() ở trên).

    /** SỬA 26/8 ("gộp Học liệu vào Sản phẩm & quyền") — $selectedProductId: xem ghi chú ở materialCreateFormData(), áp dụng y hệt cho form tải hàng loạt này. */
    public function materialsBulkImportFormData(?int $selectedProductId = null): array
    {
        $parentsQuery = $this->materials->query()->with('product')->orderBy('product_id')->orderBy('order');
        if ($selectedProductId !== null) {
            $parentsQuery->where('product_id', $selectedProductId);
        }

        return [
            'products' => $this->products->query()->orderBy('title')->get(['id', 'title'])->all(),
            'parents' => $parentsQuery->get()
                ->map(fn ($m) => ['id' => $m->id, 'label' => ($m->product->title ?? '?').' › '.$m->title])->all(),
            // 'assessment_ref' KHÔNG có trong danh sách này — loại này chỉ để THAM CHIẾU 1
            // Assessment đã có sẵn (không có nội dung PDF riêng, xem materialStore()), không hợp
            // với luồng "mỗi tệp PDF trong ZIP = 1 bài" của bulk import.
            'types' => array_filter(self::MATERIAL_TYPE_LABELS, fn ($key) => $key !== 'assessment_ref', ARRAY_FILTER_USE_KEY),
            'statuses' => $this->statusOptions(),
            'selectedProductId' => $selectedProductId,
        ];
    }

    /**
     * admin.content.materials.bulk.store — mở gói ZIP, mỗi tệp .pdf nằm NGAY GỐC ZIP (không đọc
     * thư mục con lồng nhau, tránh đoán bừa cấu trúc nếu khách nén sai cách) tạo thành 1
     * Material mới. Tiêu đề tạm lấy từ tên tệp (bỏ đuôi .pdf) — admin sửa lại cho gọn sau nếu
     * cần, không bắt buộc đúng chuẩn ngay từ lúc nhập.
     *
     * @throws ValidationException nếu ZIP không mở được hoặc không có tệp .pdf hợp lệ nào ở gốc.
     * @return Collection<int, Material>
     */
    public function materialsBulkImportFromZip(int $productId, string $type, ?int $parentId, string $status, UploadedFile $zip): Collection
    {
        $zipArchive = new ZipArchive();
        if ($zipArchive->open($zip->getRealPath()) !== true) {
            throw ValidationException::withMessages(['zip_package' => 'Không mở được gói ZIP, kiểm tra lại tệp.']);
        }

        $pdfEntries = [];
        for ($i = 0; $i < $zipArchive->numFiles; $i++) {
            $name = $zipArchive->getNameIndex($i);
            if ($name === false || str_contains($name, '/') || strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'pdf') {
                continue; // bỏ qua thư mục con và mọi tệp không phải .pdf nằm ngay gốc ZIP
            }

            $pdfEntries[] = $name;
        }

        if ($pdfEntries === []) {
            $zipArchive->close();

            throw ValidationException::withMessages([
                'zip_package' => 'Không tìm thấy tệp PDF nào ở gốc gói ZIP (mỗi bài = 1 tệp .pdf, tên tệp sẽ dùng làm mã bài).',
            ]);
        }

        natsort($pdfEntries);
        $pdfEntries = array_values($pdfEntries);

        $created = collect();
        foreach ($pdfEntries as $order => $name) {
            $raw = $zipArchive->getFromName($name);
            if ($raw === false) {
                continue; // không đọc được entry này (hiếm) — bỏ qua, không chặn cả gói
            }

            $code = $this->deriveUniqueMaterialCode($productId, $name);
            $path = "materials/{$productId}/{$code}.pdf";
            Storage::disk('local')->put($path, $raw);

            $created->push($this->materials->create([
                'product_id' => $productId,
                'parent_id' => $parentId,
                'type' => $type,
                'title' => pathinfo($name, PATHINFO_FILENAME),
                'order' => $order,
                'assessment_id' => null,
                'status' => $status,
                'code' => $code,
                'pdf_path' => $path,
                'pdf_original_name' => basename($name),
            ]));
        }

        $zipArchive->close();

        return $created;
    }

    // ================= Câu hỏi kho chung (Question, 6.5) =================

    public function questionCreateFormData(): array
    {
        return [
            // SỬA 31/8 (2) — loại 'composite' khỏi dropdown TẠO TAY (không có ô nhập
            // grading_config tương ứng ở form này, xem questionUpdate()) — CHỈ tạo được qua
            // nhập ZIP (content.type = "composite"). QUESTION_TYPE_LABELS đầy đủ 4 dạng vẫn
            // dùng nguyên ở indexData() để hiển thị đúng nhãn cho câu đã có.
            'types' => array_filter(self::QUESTION_TYPE_LABELS, fn ($key) => $key !== 'composite', ARRAY_FILTER_USE_KEY),
            'visibilities' => ['public' => 'Công khai', 'private' => 'Riêng tư (nội bộ)'],
            // SỬA 19/8 (Giai đoạn 6): danh sách tag có sẵn để tick chọn ở form — xem
            // resolveTagIds() (cho phép gõ thêm tag MỚI ngay trong form, không bắt buộc phải
            // sang tab "Tag/Chuyên đề" tạo trước).
            'allTags' => $this->tags->allOrderedByName(),
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

        $question = $this->questions->create([
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

        $question->tags()->sync($this->resolveTagIds($data));

        return $question;
    }

    public function questionEditFormData(int $id): array
    {
        /** @var Question $question */
        $question = $this->questions->query()->with('tags')->findOrFail($id);

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

        $attributes = [
            'code' => $data['code'],
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'points' => $data['points'] ?? 0,
            'visibility' => $data['visibility'] ?? Visibility::Public->value,
        ];

        // SỬA 31/8 (2, "mở rộng ZIP bài tập") — câu Composite (nhiều phần/nhiều dạng con, chỉ
        // tạo qua nhập ZIP) KHÔNG có form nhập tay tương ứng ở Kho câu hỏi (form chỉ có ô cho
        // Mcq/FillBlank/Coding) — buildGradingConfig() sẽ rơi vào nhánh `default => []` và XOÁ
        // SẠCH cấu hình các phần con nếu gọi cho Composite. Giữ NGUYÊN grading_config đã nhập từ
        // ZIP, chỉ cho sửa Tiêu đề/Nội dung/Điểm/Hiển thị/Tag như các dạng khác.
        if ($question->type !== \App\Enums\QuestionType::Composite) {
            $attributes['grading_config'] = $this->buildGradingConfig($question->type->value, $data);
        }

        $updated = $this->questions->update($question, $attributes);

        $updated->tags()->sync($this->resolveTagIds($data));

        return $updated;
    }

    /** Tạo bản version mới thay vì sửa âm thầm (6.2) — dùng khi câu đã có người làm. */
    public function questionCreateNewVersion(Question $question, array $data): Question
    {
        $changes = [
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'points' => $data['points'] ?? 0,
            'visibility' => $data['visibility'] ?? Visibility::Public->value,
        ];

        // SỬA 31/8 (2) — cùng lý do ở questionUpdate() ngay trên: KHÔNG build lại grading_config
        // cho Composite — replicate() ở createNewVersion() đã tự copy NGUYÊN grading_config gốc
        // sang bản version mới, bỏ qua key này trong $changes là đủ để giữ nguyên, không cần gọi
        // buildGradingConfig() (sẽ xoá sạch 'parts').
        if ($question->type !== \App\Enums\QuestionType::Composite) {
            $changes['grading_config'] = $this->buildGradingConfig($question->type->value, $data);
        }

        $newVersion = $this->publishGuard->createNewVersion($question, $changes);

        // Phiên bản mới giữ NGUYÊN tag của bản gốc trừ khi form gửi kèm lựa chọn tag khác —
        // đổi nội dung câu hỏi (sửa lỗi/cập nhật) hiếm khi đổi luôn chủ đề của nó.
        $newVersion->tags()->sync($this->resolveTagIds($data));

        return $newVersion;
    }

    /**
     * SỬA 19/8 (Giai đoạn 6) — gộp 2 nguồn tag từ form: (1) 'tag_ids' — các tag CÓ SẴN được
     * tick chọn; (2) 'new_tags' — chuỗi tên tag MỚI cách nhau bằng dấu phẩy, gõ trực tiếp
     * ngay trong form (không bắt buộc phải sang tab "Tag/Chuyên đề" tạo trước rồi quay lại).
     * findOrCreateByName() tự khớp nếu trùng tên tag đã có (không phân biệt hoa/thường) —
     * tránh tự sinh tag gần trùng chỉ vì gõ khác cách viết hoa/thường.
     *
     * @return array<int> danh sách tag_id cuối cùng để sync() vào câu hỏi.
     */
    private function resolveTagIds(array $data): array
    {
        $tagIds = array_map('intval', $data['tag_ids'] ?? []);

        $newNames = array_filter(array_map('trim', explode(',', (string) ($data['new_tags'] ?? ''))), fn ($n) => $n !== '');

        foreach ($newNames as $name) {
            $tagIds[] = $this->tags->findOrCreateByName($name)->id;
        }

        return array_values(array_unique($tagIds));
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
            'coding' => array_filter([
                // SỬA 24/8 (Nhập câu hỏi lập trình từ gói ZIP "OT360-QPACK"): ưu tiên
                // 'test_cases_parsed' (mảng {input,output} đã tách sẵn, ví dụ đọc thẳng từ
                // tệp trong gói ZIP) nếu có — TRÁNH vòng lặp lossy qua ô "Test cases" 1-dòng-
                // 1-test "input|||output" khi test case gốc có nhiều dòng (xem
                // questionStoreFromZipPackage() bên dưới). Form nhập tay bình thường không
                // gửi 'test_cases_parsed' nên hành vi cũ (đọc 'test_cases_raw') không đổi.
                'test_cases' => $data['test_cases_parsed'] ?? $this->parseTestCases($data['test_cases_raw'] ?? ''),
                'time_limit_ms' => filled($data['time_limit_ms'] ?? null) ? (int) $data['time_limit_ms'] : null,
                'memory_limit_mb' => filled($data['memory_limit_mb'] ?? null) ? (int) $data['memory_limit_mb'] : null,
                // SỬA 24/8 — 3 khoá dưới đây CHỈ được gói ZIP điền (form nhập tay không có
                // trường tương ứng) — giữ lại trong grading_config để dành cho khi có judge
                // chấm code thật sau này (ngôn ngữ cho phép / quy ước tên file input-output /
                // chấm theo nhóm điểm subtasks). Không dùng ở đâu khác hiện tại — vô hại.
                'languages' => $data['languages'] ?? null,
                'file_io' => $data['file_io'] ?? null,
                'subtasks' => $data['subtasks'] ?? null,
            ], fn ($v) => $v !== null),
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

    // ================= Câu hỏi lập trình — "Nhập từ gói ZIP" (24/8) =================
    // Khách muốn tải lên 1 gói ZIP đóng gói sẵn (question.json + statement.pdf + solution.pdf
    // + reference/official.cpp + tests/<số>/INPUT+OUTPUT theo định dạng "OT360-QPACK") rồi CHỈ
    // CẦN bấm Lưu ở màn Sửa — không phải gõ tay từng test case. Cố ý tái sử dụng questionStore()
    // NGUYÊN VẸN bên trên (không đổi chữ ký/nội dung hàm đó) để không đụng vào luồng tạo câu hỏi
    // thủ công đang chạy đúng — ở đây chỉ build đúng $data mà questionStore() đã hiểu, rồi gắn
    // thêm 'metadata' (môn/khối/chuyên đề/độ khó/tác giả — xem cột metadata ở migration
    // questions) và lưu 3 tệp đính kèm sau khi đã có $question->id.

    private const MAX_ZIP_PACKAGE_KB = 20480; // 20MB — gói ZIP gồm cả PDF đề+lời giải+nhiều test case

    public static function maxQuestionZipKb(): int
    {
        return self::MAX_ZIP_PACKAGE_KB;
    }

    /**
     * admin.content.questions.zipImport — điểm vào duy nhất của tính năng. LƯU Ý ĐÃ BÁO CHO
     * KHÁCH: test case nhập từ ZIP được lưu đúng nguyên vẹn (kể cả nhiều dòng) nhờ
     * 'test_cases_parsed' ở buildGradingConfig() trên — nhưng nếu SAU ĐÓ ai sửa câu hỏi này
     * qua ô "Test cases" thủ công (dạng text "input|||output" mỗi dòng), nội dung nhiều dòng
     * có thể bị hiểu sai thành nhiều test case khác nhau. Đây là hạn chế CÓ SẴN TỪ TRƯỚC (ô
     * nhập tay dùng chung cho mọi câu lập trình, không riêng câu nhập từ ZIP) — cố ý KHÔNG sửa
     * ô nhập tay ở đây vì phạm vi rộng hơn nhiều so với yêu cầu hiện tại.
     *
     * @throws ValidationException nếu gói ZIP không mở được, thiếu/sai question.json, hoặc
     *                              không có test case hợp lệ nào trong thư mục tests/.
     */
    public function questionStoreFromZipPackage(User $admin, UploadedFile $zip): Question
    {
        $package = $this->parseZipQuestionPackage($zip);
        $json = $package['json'];
        $content = $json['content'] ?? [];
        $contentType = (string) ($content['type'] ?? '');

        $points = isset($content['points']) ? (int) round((float) $content['points']) : 0;

        $tagNames = array_values(array_filter(array_map('trim', array_merge(
            $json['taxonomy']['tags'] ?? [],
            $json['taxonomy']['keywords'] ?? [],
        )), fn ($t) => $t !== ''));

        // SỬA 31/8 (2, "mở rộng ZIP bài tập" — không chỉ lập trình): trước đây $data['type'] LUÔN
        // gán cứng 'coding' rồi gọi lại questionStore() (đi qua buildGradingConfig() theo cấu
        // trúc form nhập tay) — giờ gói ZIP có thể là 1 trong 4 content.type khác nhau (xem
        // SUPPORTED_ZIP_CONTENT_TYPES), mỗi loại map sang 1 Question::type khác nhau
        // (questionTypeFromZipContentType()) và có cấu trúc grading JSON khác hẳn form nhập tay
        // — build thẳng grading_config qua buildGradingConfigFromZipPackage() (hiểu đúng cấu
        // trúc từng loại) rồi tạo Question trực tiếp, không qua questionStore() nữa (giữ nguyên
        // questionStore() cho luồng nhập tay ở form, không đụng vào).
        $type = $this->questionTypeFromZipContentType($contentType);

        $question = $this->questions->create([
            'bank_id' => $this->sharedBank()->id,
            'code' => $this->deriveUniqueQuestionCode($zip->getClientOriginalName()),
            'type' => $type,
            'title' => $content['title'] ?? 'Câu hỏi (nhập từ ZIP)',
            'body' => $this->placeholderBodyForZipImport($content, $package['attachments']),
            'points' => max(0, $points),
            'grading_config' => $this->buildGradingConfigFromZipPackage($contentType, $json, $package['testCases']),
            'owner_type' => OwnerType::Shared->value,
            'owner_id' => null,
            'visibility' => Visibility::Public->value,
            'status' => ContentStatus::Draft->value,
            'version' => 1,
            'created_by' => $admin->id,
        ]);

        $question->update([
            'metadata' => [
                'source_package' => [
                    'schema' => $json['schema'] ?? null,
                    'content_type' => $contentType,
                    'original_filename' => $zip->getClientOriginalName(),
                    'imported_at' => now()->toIso8601String(),
                ],
                'taxonomy' => $json['taxonomy'] ?? null,
                'pedagogy' => $json['pedagogy'] ?? null,
                'attribution' => $json['attribution'] ?? null,
                'attachments' => $this->storeZipAttachments($question, $package['attachments']),
                // SỬA 31/8 (2) — audio/ảnh... đính kèm (khác 3 tệp cố định statement/solution/
                // reference ở trên) — xem storeZipAssets() + Question::findAsset().
                'assets' => $this->storeZipAssets($question, $package['assets']),
            ],
        ]);

        if ($tagNames !== []) {
            $question->tags()->sync(array_map(fn ($name) => $this->tags->findOrCreateByName($name)->id, $tagNames));
        }

        return $question;
    }

    /**
     * admin.content.questions.attachment — tệp đính kèm (đề/lời giải/code mẫu) lưu trong
     * metadata.attachments khi câu hỏi được nhập từ gói ZIP (xem questionStoreFromZipPackage()).
     * Câu hỏi tạo tay (không qua ZIP) không có metadata.attachments -> luôn 404 ở đây, đúng vì
     * không có tệp gì để tải.
     */
    public function questionAttachmentInfo(Question $question, string $kind): array
    {
        $attachments = $question->metadata['attachments'] ?? [];
        if (! isset($attachments[$kind]['path'])) {
            abort(404);
        }

        return [
            'path' => $attachments[$kind]['path'],
            'filename' => $attachments[$kind]['filename'] ?? basename($attachments[$kind]['path']),
        ];
    }

    /**
     * SỬA 31/8 (2, "mở rộng ZIP bài tập" — không chỉ lập trình): 4 dạng nội dung
     * (content.type) hiện được hỗ trợ — trước đây CHỈ 'programming'. Khách chốt: essay (phần
     * tự luận trong 'composite') ghi nhận, chưa tự chấm được (cùng cách xử lý câu Lập trình).
     */
    private const SUPPORTED_ZIP_CONTENT_TYPES = ['programming', 'single_choice', 'true_false', 'short_answer', 'composite'];

    /**
     * Mở gói ZIP, đọc + kiểm tra question.json (phải đúng schema "OT360-QPACK" và content.type
     * thuộc SUPPORTED_ZIP_CONTENT_TYPES ở trên), gom test case từ các thư mục tests/<số>/ (mỗi
     * thư mục có 1 tệp chứa "input" trong tên và 1 tệp chứa "output" trong tên — KHÔNG cố định
     * đúng tên "INPUT.INP"/"OUTPUT.OUT" vì gói khác có thể đặt tên file khác), đọc nội dung 3
     * tệp đính kèm cố định (statement.pdf/solution.pdf/reference/official.cpp) nếu có, VÀ đọc
     * luôn 'assets' (audio/ảnh... khai báo trong question.json, path bất kỳ trong gói — SỬA
     * 31/8 (2), xem gói mẫu "ANH7AUDIO_DEMO_001") — đọc hết NGAY TRONG hàm này rồi đóng ZIP
     * luôn, tránh phải giữ ZipArchive mở vắt qua nhiều hàm.
     *
     * Test case (tests/<số>/) chỉ BẮT BUỘC với content.type = 'programming' (chấm bằng so khớp
     * input/output qua judge) — 4 dạng còn lại chấm qua 'grading' trong question.json, không
     * cần thư mục tests/ nào.
     *
     * @return array{json: array, testCases: array<int, array{input:string, output:string}>, attachments: array<string, array{content:string, filename:string}>, assets: array<int, array{id:string, kind:string, filename:string, content:string, transcript:?string, alt_text:?string}>}
     */
    private function parseZipQuestionPackage(UploadedFile $zip): array
    {
        $zipArchive = new ZipArchive();
        if ($zipArchive->open($zip->getRealPath()) !== true) {
            throw ValidationException::withMessages(['zip_package' => 'Không mở được gói ZIP, kiểm tra lại tệp.']);
        }

        $jsonRaw = $zipArchive->getFromName('question.json');
        if ($jsonRaw === false) {
            $zipArchive->close();
            throw ValidationException::withMessages(['zip_package' => 'Gói ZIP thiếu tệp question.json ở gốc.']);
        }

        $json = json_decode($jsonRaw, true);
        if (! is_array($json)) {
            $zipArchive->close();
            throw ValidationException::withMessages(['zip_package' => 'question.json trong gói ZIP không đúng định dạng JSON.']);
        }

        $schema = (string) ($json['schema'] ?? '');
        $contentType = (string) ($json['content']['type'] ?? '');
        if (! str_starts_with($schema, 'OT360-QPACK') || ! in_array($contentType, self::SUPPORTED_ZIP_CONTENT_TYPES, true)) {
            $zipArchive->close();
            throw ValidationException::withMessages([
                'zip_package' => 'Gói ZIP không đúng định dạng OT360-QPACK hoặc loại nội dung (content.type) chưa được hỗ trợ.',
            ]);
        }

        $attachmentNames = ['statement.pdf' => 'statement', 'solution.pdf' => 'solution', 'reference/official.cpp' => 'reference'];
        $attachments = [];
        $testFolders = [];

        // Gom trước danh sách đường dẫn asset cần đọc (path -> true) từ question.json['assets']
        // — đọc luôn trong CÙNG vòng lặp quét zip bên dưới, không mở lại ZipArchive lần 2.
        $assetPaths = [];
        foreach (($json['assets'] ?? []) as $asset) {
            if (isset($asset['path']) && is_string($asset['path'])) {
                $assetPaths[$asset['path']] = true;
            }
        }
        $assetsRaw = [];

        for ($i = 0; $i < $zipArchive->numFiles; $i++) {
            $name = $zipArchive->getNameIndex($i);
            if ($name === false || str_ends_with($name, '/')) {
                continue; // thư mục con trong zip, bỏ qua
            }

            if (isset($attachmentNames[$name])) {
                $raw = $zipArchive->getFromName($name);
                if ($raw !== false) {
                    $attachments[$attachmentNames[$name]] = ['content' => $raw, 'filename' => basename($name)];
                }

                continue;
            }

            if (isset($assetPaths[$name])) {
                $raw = $zipArchive->getFromName($name);
                if ($raw !== false) {
                    $assetsRaw[$name] = $raw;
                }

                continue;
            }

            if (preg_match('#^tests/([^/]+)/([^/]+)$#i', $name, $m)) {
                $lower = strtolower($m[2]);
                if (str_contains($lower, 'input')) {
                    $testFolders[$m[1]]['input'] = $name;
                } elseif (str_contains($lower, 'output')) {
                    $testFolders[$m[1]]['output'] = $name;
                }
            }
        }

        ksort($testFolders, SORT_NATURAL);
        $testCases = [];
        foreach ($testFolders as $pair) {
            if (! isset($pair['input'], $pair['output'])) {
                continue; // thiếu 1 trong 2 vế — không đoán bừa, bỏ qua thư mục test này
            }

            $input = $zipArchive->getFromName($pair['input']);
            $output = $zipArchive->getFromName($pair['output']);
            if ($input === false || $output === false) {
                continue;
            }

            $testCases[] = ['input' => $input, 'output' => $output];
        }

        $zipArchive->close();

        if ($contentType === 'programming' && $testCases === []) {
            throw ValidationException::withMessages([
                'zip_package' => 'Không tìm thấy test case hợp lệ trong gói ZIP (cần thư mục tests/<số>/ chứa 2 tệp input/output).',
            ]);
        }

        // Ghép lại 'assets' đầy đủ (metadata khai báo trong question.json + nội dung nhị phân
        // vừa đọc được) — asset khai báo trong JSON nhưng KHÔNG tìm thấy file thật trong zip bị
        // bỏ qua (không đoán bừa/không chặn cả gói chỉ vì 1 asset lỗi).
        $assets = [];
        foreach (($json['assets'] ?? []) as $asset) {
            $path = $asset['path'] ?? null;
            if (! is_string($path) || ! isset($assetsRaw[$path])) {
                continue;
            }

            $assets[] = [
                'id' => (string) ($asset['id'] ?? Str::uuid()),
                'kind' => (string) ($asset['kind'] ?? 'file'),
                'filename' => basename($path),
                'content' => $assetsRaw[$path],
                'transcript' => $asset['transcript'] ?? null,
                'alt_text' => $asset['alt_text'] ?? null,
            ];
        }

        return ['json' => $json, 'testCases' => $testCases, 'attachments' => $attachments, 'assets' => $assets];
    }

    /**
     * SỬA 31/8 (2) — map content.type (gói ZIP) sang Question::type (cột 'type' thật của hệ
     * thống): 'single_choice'/'true_false' quy về 'mcq' — TÁI DÙNG NGUYÊN VẸN máy Mcq đã có
     * (QuestionGrader::isMcqCorrect(), màn Luyện tập/Làm bài Mcq) thay vì viết thêm luồng
     * chấm/hiển thị riêng, xem buildChoiceGradingConfigFromZip()/buildTrueFalseGradingConfigFromZip()
     * bên dưới; 'short_answer' quy về 'fill_blank' cùng lý do. 'composite' là loại DUY NHẤT thật
     * sự mới (nhiều phần khác dạng, không quy về đâu được).
     */
    private function questionTypeFromZipContentType(string $contentType): string
    {
        return match ($contentType) {
            'programming' => 'coding',
            'single_choice', 'true_false' => 'mcq',
            'short_answer' => 'fill_blank',
            'composite' => 'composite',
            default => 'coding',
        };
    }

    /**
     * Dựng grading_config từ gói ZIP theo ĐÚNG content.type — khác buildGradingConfig() ở trên
     * (dựng từ input FORM nhập tay của admin, cấu trúc khác hẳn cấu trúc JSON của gói ZIP) nên
     * tách hàm riêng, không gộp chung tránh 1 hàm phải hiểu 2 nguồn dữ liệu khác hình dạng.
     */
    private function buildGradingConfigFromZipPackage(string $contentType, array $json, array $testCases): array
    {
        $grading = $json['grading'] ?? [];

        return match ($contentType) {
            'programming' => $this->buildGradingConfig('coding', [
                'test_cases_parsed' => $testCases,
                'time_limit_ms' => $grading['time_limit_ms'] ?? 1000,
                'memory_limit_mb' => $grading['memory_limit_mb'] ?? 256,
                'languages' => $grading['languages'] ?? null,
                'file_io' => $grading['file_io'] ?? null,
                'subtasks' => $json['subtasks'] ?? null,
            ]),
            'single_choice' => $this->buildChoiceGradingConfigFromZip($grading),
            'true_false' => $this->buildTrueFalseGradingConfigFromZip($grading),
            'short_answer' => $this->buildShortAnswerGradingConfigFromZip($grading),
            'composite' => $this->buildCompositeGradingConfigFromZip($grading),
            default => [],
        };
    }

    /**
     * ZIP single_choice: grading.choices = [{id,text}] (thứ tự = thứ tự hiện), grading.
     * correct_answer = id chữ cái (vd "B"). Map sang ĐÚNG cấu trúc Mcq hiện có (options: mảng
     * text theo thứ tự, correct_options: mảng CHỈ SỐ — xem QuestionGrader::isMcqCorrect()) để
     * dùng lại NGUYÊN VẸN toàn bộ máy Mcq đã có, không viết thêm UI/luồng chấm riêng.
     */
    private function buildChoiceGradingConfigFromZip(array $grading): array
    {
        $choices = $grading['choices'] ?? [];
        $options = array_values(array_map(fn ($c) => (string) ($c['text'] ?? ''), $choices));

        $correctId = $grading['correct_answer'] ?? null;
        $correctIndex = null;
        foreach (array_values($choices) as $i => $c) {
            if (($c['id'] ?? null) === $correctId) {
                $correctIndex = $i;
                break;
            }
        }

        return [
            'options' => $options,
            'correct_options' => $correctIndex !== null ? [$correctIndex] : [],
        ];
    }

    /**
     * ZIP true_false: chỉ có grading.correct_answer (bool), KHÔNG có 'choices' — map sang Mcq 2
     * phương án cố định "Đúng"/"Sai", cùng lý do tái dùng máy Mcq như single_choice ở trên.
     */
    private function buildTrueFalseGradingConfigFromZip(array $grading): array
    {
        $correct = (bool) ($grading['correct_answer'] ?? false);

        return [
            'options' => ['Đúng', 'Sai'],
            'correct_options' => [$correct ? 0 : 1],
        ];
    }

    /**
     * ZIP short_answer: grading.accepted_answers + grading.normalization {trim, case_sensitive,
     * remove_diacritics} -> khớp thẳng cấu trúc FillBlank hiện có, thêm key 'remove_diacritics'
     * (QuestionGrader::isFillBlankCorrect() đã hỗ trợ đọc, xem SỬA 31/8 (2) ở đó).
     */
    private function buildShortAnswerGradingConfigFromZip(array $grading): array
    {
        $normalization = $grading['normalization'] ?? [];

        return [
            'accepted_answers' => array_values(array_map('strval', $grading['accepted_answers'] ?? [])),
            'case_sensitive' => (bool) ($normalization['case_sensitive'] ?? false),
            'remove_diacritics' => (bool) ($normalization['remove_diacritics'] ?? false),
        ];
    }

    /**
     * ZIP composite: grading.mode = 'per_part', grading.parts = [{code, response_type, points,
     * ...}] — GIỮ NGUYÊN cấu trúc gốc của gói ZIP (KHÔNG quy về Mcq/FillBlank như 3 loại trên)
     * vì mỗi phần (part) có thể khác response_type nhau trong CÙNG 1 câu — không có 1 kiểu
     * chấm/hiển thị chung nào để quy về. Student\PracticeByQuestionService::gradeCompositeParts()
     * đọc thẳng cấu trúc này để chấm từng phần lúc "Làm bài". Chuẩn hoá tối thiểu (đảm bảo có
     * 'points' số) để tránh lỗi truy cập khoá không tồn tại về sau.
     */
    private function buildCompositeGradingConfigFromZip(array $grading): array
    {
        $parts = array_map(function (array $part) {
            $part['points'] = (float) ($part['points'] ?? 0);

            return $part;
        }, $grading['parts'] ?? []);

        return [
            'mode' => 'per_part',
            'parts' => array_values($parts),
        ];
    }

    /**
     * Mã câu hỏi tự sinh từ TÊN GỐC của tệp ZIP (ví dụ "TIN9TONG_2_SO_001.zip" ->
     * "TIN9TONG_2_SO_001") — admin không phải tự gõ mã khi nhập từ ZIP. Thêm hậu tố "-2", "-3"...
     * nếu trùng mã đã có (questionStore() vẫn tự kiểm tra lại lần cuối, đây chỉ để tránh va
     * trùng ngay từ đầu trong trường hợp thường gặp).
     */
    private function deriveUniqueQuestionCode(string $originalFilename): string
    {
        // SỬA 25/8: thuật toán chuyển sang dùng CHUNG App\Support\UniqueCodeFromFilename với
        // deriveUniqueMaterialCode() (mã bài học liệu) — khác nhau đúng 1 chỗ là PHẠM VI kiểm
        // tra trùng ($exists dưới đây: toàn hệ thống cho câu hỏi, trong 1 sản phẩm cho học
        // liệu), còn lại (cắt đuôi, giới hạn độ dài, thêm hậu tố) là 1 thuật toán duy nhất.
        return UniqueCodeFromFilename::generate(
            $originalFilename,
            fn (string $code) => $this->questions->query()->where('code', $code)->exists(),
        );
    }

    /**
     * SỬA 3/9 (khách chốt: "hiển thị thẳng đề bài dạng text, khỏi hiển thị file") — thử trích
     * chữ thật từ statement.pdf đính kèm (nếu gói ZIP có) qua PdfTextExtractor, dùng THẲNG làm
     * body để học sinh đọc ngay trên trang luyện tập/làm bài, KHÔNG cần mở file riêng nữa.
     * Chỉ rơi về dòng ghi chú cũ khi: không có statement.pdf, hoặc trích lỗi/rỗng (PDF là ảnh
     * scan, không có lớp text thật) — để trống body sẽ bị QuestionPublishGuard chặn phát hành
     * (đòi body không rỗng), nên vẫn cần 1 nội dung hợp lệ trong mọi trường hợp.
     *
     * @param  array<string, array{content:string, filename:string}>  $rawAttachments  $package['attachments'] TRƯỚC khi lưu disk (storeZipAttachments()) — cần nguyên bytes PDF để trích, không phải đường dẫn đã lưu.
     */
    private function placeholderBodyForZipImport(array $content, array $rawAttachments = []): string
    {
        $statementPdf = $rawAttachments['statement']['content'] ?? null;
        if ($statementPdf !== null) {
            $extracted = $this->pdfTextExtractor->extractText($statementPdf);
            if ($extracted !== null) {
                return $this->pdfTextExtractor->toBodyHtml($extracted);
            }
        }

        $note = 'Đề bài đầy đủ nằm trong tệp PDF đính kèm (nhập từ gói ZIP) — xem mục "Tệp đính kèm" ở trang Sửa câu hỏi.';
        $title = $content['title'] ?? null;

        return $title ? "{$title}\n\n{$note}" : $note;
    }

    /**
     * Lưu tệp đính kèm (đề/lời giải/code mẫu) vào đúng disk 'local' (riêng tư, giống quy ước
     * PdfAssessmentEditingService::PDF_DISK) theo đường dẫn khoá bởi $question->id — chỉ gọi
     * SAU KHI câu hỏi đã tạo (cần id để đặt đường dẫn).
     *
     * @param  array<string, array{content:string, filename:string}>  $attachments
     * @return array<string, array{path:string, filename:string}>
     */
    private function storeZipAttachments(Question $question, array $attachments): array
    {
        $stored = [];
        foreach ($attachments as $kind => $attachment) {
            $extension = pathinfo($attachment['filename'], PATHINFO_EXTENSION) ?: 'bin';
            $path = "questions/{$question->id}/{$kind}.{$extension}";
            Storage::disk('local')->put($path, $attachment['content']);
            $stored[$kind] = ['path' => $path, 'filename' => $attachment['filename']];
        }

        return $stored;
    }

    /**
     * SỬA 31/8 (2, "mở rộng ZIP bài tập") — lưu asset (audio/ảnh...) đính kèm câu hỏi, SONG
     * SONG với storeZipAttachments() ở trên nhưng khác ở chỗ SỐ LƯỢNG/loại không cố định trước
     * (3 tên statement/solution/reference) — lưu theo asset id (duy nhất trong 1 câu hỏi, do
     * gói ZIP tự đặt hoặc tự sinh UUID nếu thiếu, xem parseZipQuestionPackage()) thay vì theo
     * 'kind' cố định. Trả về mảng lưu vào metadata.assets — Question::findAsset() +
     * Student\PracticeByQuestionService/blade dùng lại để phát audio/hiện ảnh lúc "Làm bài".
     *
     * @param  array<int, array{id:string, kind:string, filename:string, content:string, transcript:?string, alt_text:?string}>  $assets
     * @return array<int, array{id:string, kind:string, path:string, filename:string, transcript:?string, alt_text:?string}>
     */
    private function storeZipAssets(Question $question, array $assets): array
    {
        $stored = [];
        foreach ($assets as $asset) {
            $extension = pathinfo($asset['filename'], PATHINFO_EXTENSION) ?: 'bin';
            $path = "questions/{$question->id}/assets/{$asset['id']}.{$extension}";
            Storage::disk('local')->put($path, $asset['content']);

            $stored[] = [
                'id' => $asset['id'],
                'kind' => $asset['kind'],
                'path' => $path,
                'filename' => $asset['filename'],
                'transcript' => $asset['transcript'],
                'alt_text' => $asset['alt_text'],
            ];
        }

        return $stored;
    }

    // ================= Bài tập đính kèm sản phẩm ("ZIP bài tập", 31/8) =================
    // Khách chốt qua nhiều vòng: (1) nhập bằng ZIP OT360-QPACK — tái dùng
    // parseZipQuestionPackage()/storeZipAttachments()/deriveUniqueQuestionCode()/
    // placeholderBodyForZipImport()/questionAttachmentInfo() y hệt Kho câu hỏi ở trên, chỉ khác
    // gắn product_id thay vì đưa vào Kho chung; (2) không giới hạn số lượng bài tập/sản phẩm;
    // (3) chấm kiểu thi online khi học sinh làm — thực chất tái dùng
    // Student\PracticeByQuestionService (xem startForQuestion() ở đó) — LƯU Ý: hệ thống hiện
    // CHƯA có sandbox chấm code thật (AttemptService.php đã ghi chú từ trước, không riêng gì
    // tính năng này), nên bài nộp vẫn ở trạng thái "Đang chấm" — khách đã được báo và đồng ý
    // làm trước, chấm thật sẽ tự chạy đúng khi có sandbox thật sau này, không cần sửa lại;
    // (4) chọn ZIP xong mà thoát ra không bấm "Lưu bài tập" thì tự xoá — xem
    // discardAbandonedDraftsFor(); (5) CHỈ Admin quản lý — route đặt cùng nhóm middleware
    // role:admin,super_admin với admin.products.* (routes/web.php), giáo viên không có route
    // nào trỏ tới các hàm dưới đây.

    /**
     * admin.products.show — danh sách bài tập để hiển thị + tự dọn bản nháp bỏ dở TRƯỚC khi
     * trả về danh sách (xem discardAbandonedDraftsFor()) — nhờ vậy nút "Thêm ZIP" ở màn hình
     * này luôn có thể bấm ngay, không bao giờ bị "kẹt" vì 1 bản nháp cũ quên chưa lưu.
     */
    public function productExercisesFor(Product $product): array
    {
        $this->discardAbandonedDraftsFor($product);

        return $product->exercises()->with('tags')->get()->map(fn (Question $q) => [
            'id' => $q->id,
            'title' => $q->title,
            'points' => $q->points,
            // SỬA 31/8 (2, "mở rộng ZIP bài tập") — trước đây 'testCasesCount' (chỉ đúng cho
            // Lập trình) — bài tập giờ có thể là bất kỳ dạng nào trong 4 dạng ZIP hỗ trợ, dùng
            // Question::exerciseSummaryLabel() để mô tả đúng theo TỪNG dạng.
            'summary' => $q->exerciseSummaryLabel(),
            'typeLabel' => self::QUESTION_TYPE_LABELS[$q->type->value] ?? $q->type->value,
            'tags' => $q->tags->pluck('name')->all(),
            'createdAt' => $q->created_at?->format('d/m/Y H:i'),
        ])->all();
    }

    /**
     * Bản NHÁP (status=Draft) chỉ tồn tại trong khoảng thời gian giữa lúc admin chọn xong ZIP
     * (productExerciseStoreFromZipPackage() tạo ngay + chuyển thẳng sang màn Sửa) và lúc admin
     * bấm "Lưu bài tập" (productExerciseSave() chuyển status -> Published). Hàm này chỉ được
     * gọi khi trang chi tiết sản phẩm được tải lại — nghĩa là admin CHẮC CHẮN không còn ở màn
     * Sửa bản nháp đó nữa (rời đi mà chưa lưu = bỏ dở) — xoá thẳng, không cần hỏi lại, đúng
     * yêu cầu "thoát ra không bấm Lưu thì xoá luôn, không cần thao tác gì thêm". Bài tập ĐÃ LƯU
     * (status khác Draft) không bao giờ bị đụng tới ở đây.
     */
    public function discardAbandonedDraftsFor(Product $product): void
    {
        $product->exercises()->where('status', ContentStatus::Draft->value)->get()
            ->each(fn (Question $draft) => $this->productExerciseDestroy($draft));
    }

    /**
     * admin.products.exercises.store — chọn ZIP xong tạo NGAY bản nháp rồi chuyển sang màn Sửa
     * (controller redirect) để admin xem lại/sửa Tiêu đề-Điểm-Tag trước khi bấm "Lưu bài tập".
     * Quét dọn nháp bỏ dở TRƯỚC KHI tạo mới (phòng khi admin bấm "Thêm ZIP" từ 1 tab/trang cũ
     * đã lỗi thời) — đúng tinh thần "phải thêm xong thì mới được thêm file ZIP mới".
     *
     * @throws ValidationException nếu gói ZIP không mở được/sai định dạng/thiếu test case hợp
     *                              lệ — xem parseZipQuestionPackage().
     */
    public function productExerciseStoreFromZipPackage(Product $product, User $admin, UploadedFile $zip): Question
    {
        $this->discardAbandonedDraftsFor($product);

        $package = $this->parseZipQuestionPackage($zip);
        $json = $package['json'];
        $content = $json['content'] ?? [];
        $contentType = (string) ($content['type'] ?? '');

        $points = isset($content['points']) ? (int) round((float) $content['points']) : 0;

        $tagNames = array_values(array_filter(array_map('trim', array_merge(
            $json['taxonomy']['tags'] ?? [],
            $json['taxonomy']['keywords'] ?? [],
        )), fn ($t) => $t !== ''));

        // SỬA 31/8 (2, "mở rộng ZIP bài tập") — cùng thay đổi như questionStoreFromZipPackage():
        // 'type' KHÔNG còn gán cứng 'coding', build grading_config qua
        // buildGradingConfigFromZipPackage() (hiểu đúng cấu trúc từng content.type) thay vì
        // buildGradingConfig('coding', ...).
        $type = $this->questionTypeFromZipContentType($contentType);

        $question = $this->questions->create([
            'bank_id' => $this->sharedBank()->id,
            // SỬA 31/8 — điểm khác biệt DUY NHẤT với questionStoreFromZipPackage(): gắn
            // product_id để câu hỏi này là "riêng của sản phẩm", loại khỏi mọi nơi lấy câu hỏi
            // dùng chung (xem whereNull('product_id') ở QuestionRepository).
            'product_id' => $product->id,
            'code' => $this->deriveUniqueQuestionCode($zip->getClientOriginalName()),
            'type' => $type,
            'title' => $content['title'] ?? 'Bài tập (nhập từ ZIP)',
            'body' => $this->placeholderBodyForZipImport($content, $package['attachments']),
            'points' => max(0, $points),
            'grading_config' => $this->buildGradingConfigFromZipPackage($contentType, $json, $package['testCases']),
            'owner_type' => OwnerType::Shared->value,
            'owner_id' => null,
            'visibility' => Visibility::Public->value,
            // Draft = "đang thêm dở, chưa bấm Lưu" — KHÁC nghĩa "chờ duyệt" bên Kho câu hỏi
            // chung. Bài tập sản phẩm không qua vòng duyệt riêng; bấm "Lưu bài tập"
            // (productExerciseSave()) chuyển thẳng sang Published.
            'status' => ContentStatus::Draft->value,
            'version' => 1,
            'created_by' => $admin->id,
        ]);

        $question->update([
            'metadata' => [
                'source_package' => [
                    'schema' => $json['schema'] ?? null,
                    'content_type' => $contentType,
                    'original_filename' => $zip->getClientOriginalName(),
                    'imported_at' => now()->toIso8601String(),
                ],
                'taxonomy' => $json['taxonomy'] ?? null,
                'pedagogy' => $json['pedagogy'] ?? null,
                'attribution' => $json['attribution'] ?? null,
                'attachments' => $this->storeZipAttachments($question, $package['attachments']),
                'assets' => $this->storeZipAssets($question, $package['assets']),
            ],
        ]);

        if ($tagNames !== []) {
            $question->tags()->sync(array_map(fn ($name) => $this->tags->findOrCreateByName($name)->id, $tagNames));
        }

        return $question;
    }

    /** admin.products.exercises.edit — form riêng, đơn giản hơn nhiều so với Kho câu hỏi: chỉ
     *  cho sửa Tiêu đề/Điểm/Tag, test case + tệp đính kèm hiện READ-ONLY (xem lý do ở
     *  productExerciseSave()). */
    public function productExerciseEditFormData(Product $product, Question $exercise): array
    {
        return [
            'product' => $product,
            'exercise' => $exercise->load('tags'),
            'allTags' => $this->tags->allOrderedByName(),
            'isDraft' => $exercise->status === ContentStatus::Draft,
        ];
    }

    /**
     * admin.products.exercises.update — admin bấm "Lưu bài tập". CHỈ cho sửa Tiêu đề/Điểm/Tag
     * — test case nhập từ ZIP giữ NGUYÊN, không cho sửa tay ở đây (ô nhập tay 1-dòng-1-test
     * "input|||output" của Kho câu hỏi dễ làm hỏng test case nhiều dòng đọc thẳng từ ZIP, đã
     * ghi chú ở questionStoreFromZipPackage() phía trên — cố ý không lặp lại rủi ro đó ở đây).
     * status -> Published: bản nháp chính thức trở thành bài tập của sản phẩm, từ nay
     * discardAbandonedDraftsFor() không còn đụng tới bài này nữa.
     */
    public function productExerciseSave(Question $exercise, array $data): Question
    {
        $updated = $this->questions->update($exercise, [
            'title' => $data['title'],
            'points' => $data['points'] ?? 0,
            'status' => ContentStatus::Published->value,
        ]);

        $updated->tags()->sync($this->resolveTagIds($data));

        return $updated;
    }

    /**
     * Xoá bài tập — dùng cho cả (a) nháp bỏ dở (discardAbandonedDraftsFor() tự gọi) lẫn (b)
     * admin chủ động xoá bài đã lưu (nút "Xoá" ở trang chi tiết sản phẩm). Xoá HẲN
     * (forceDelete), KHÔNG soft-delete như Question::delete() mặc định, vì 2 lý do: (1) bài tập
     * sản phẩm không có lịch sử làm bài lưu ở DB để cần giữ lại — luồng "Làm bài"
     * (PracticeByQuestionService) cố ý CHỈ lưu trong session, không ghi Attempt/AttemptAnswer;
     * (2) xoá hẳn mới giải phóng lại cột 'code' (unique) — soft-delete sẽ để hàng cũ chiếm chỗ
     * mã đó, khiến nhập lại ĐÚNG tệp ZIP y hệt (rất dễ xảy ra khi admin thêm-rồi-bỏ-dở-rồi-thêm-
     * lại) báo lỗi trùng khoá ở lần tạo sau. Xoá tệp đính kèm trên đĩa trước, giống
     * materialDelete() ở trên.
     */
    public function productExerciseDestroy(Question $exercise): void
    {
        foreach (($exercise->metadata['attachments'] ?? []) as $attachment) {
            if (isset($attachment['path'])) {
                Storage::disk('local')->delete($attachment['path']);
            }
        }

        $exercise->tags()->detach();
        $exercise->forceDelete();
    }

    // ================= Đề/bộ bài (Assessment) =================
    // SỬA 18/8: trước đây phạm vi cố ý chỉ giới hạn ở metadata, với giả định "gắn/gỡ câu hỏi
    // vào đề là luồng riêng của giáo viên khi soạn đề (TEA-xx)" — nhưng giả định đó SAI với
    // đề do chính ADMIN tạo (owner_type=shared, vd "Đề thi quốc gia", "Đề thi cuộc thi"):
    // không giáo viên nào sở hữu đề này để vào teacher.assessments.create sửa cả (màn đó chỉ
    // cho giáo viên soạn đề CỦA CHÍNH HỌ, không có màn sửa lại đề đã tạo), nên loại đề admin tự
    // tạo trước giờ không cách nào gắn câu hỏi được (để lại đúng 1 dòng TODO chết ở
    // admin.content.show). Thêm assessmentItemsFormData()/assessmentItemsUpdate() bên dưới để
    // bù đúng khoảng trống đó — CHỈ áp dụng cho đề admin tự quản (không đụng vào luồng soạn đề
    // riêng của giáo viên, vẫn giữ nguyên như cũ).

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

    /**
     * SỬA 18/8 (đề PDF, theo file khách chốt "chốt chức năng đề luyện tập tài liệu" mục
     * 1.1/1.2): content_mode do HỆ THỐNG tự suy ra từ type, KHÔNG cho admin tự chọn — chỉ
     * "Luyện tập" (practice) mới dùng câu hỏi rời (structured); Bài giao/Đề thi/Đề thi đấu
     * đều bắt buộc dùng PDF + phiếu đáp án. Xem App\Enums\AssessmentContentMode.
     */
    private function contentModeForType(string $type): string
    {
        return $type === AssessmentType::Practice->value
            ? AssessmentContentMode::Structured->value
            : AssessmentContentMode::PdfAnswerSheet->value;
    }

    public function assessmentStore(User $admin, array $data): Assessment
    {
        return $this->assessments->create([
            'title' => $data['title'],
            'type' => $data['type'],
            'content_mode' => $this->contentModeForType($data['type']),
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

    /**
     * admin.content.assessments.items.edit — dữ liệu cho màn chọn câu hỏi. Admin xem/chọn
     * được TOÀN BỘ câu hỏi (Kho chung + kho riêng từng giáo viên — allLatestWithOwner() đã
     * có sẵn, dùng lại đúng chỗ tab "Câu hỏi" ở admin.content.index đang dùng, xem
     * indexData()), không giới hạn theo 1 giáo viên như teacher.assessments.create.
     */
    public function assessmentItemsFormData(Assessment $assessment): array
    {
        $assessment->load('items.question');
        $existingItems = $assessment->items->keyBy('question_id');

        return [
            'assessment' => $assessment,
            'questions' => $this->questions->allLatestWithOwner(200)->map(fn (Question $q) => [
                'id' => $q->id,
                'title' => $q->title,
                'type' => $q->type->value,
                'points' => $q->points,
                'status' => $q->status->value,
                'ownerLabel' => $q->owner_type === OwnerType::Shared ? 'Kho chung' : ('GV '.($q->owner->name ?? '')),
            ])->all(),
            'selectedIds' => $existingItems->keys()->all(),
            'pointsOverrides' => $existingItems->map(fn ($item) => $item->points_override)->filter()->all(),
        ];
    }

    /**
     * admin.content.assessments.items.update — thay TOÀN BỘ danh sách câu hỏi trong đề bằng
     * danh sách mới chọn (xoá hết item cũ rồi tạo lại theo đúng thứ tự tick trên form — đơn
     * giản, đủ dùng cho phạm vi này, giống cách teacher.assessments.store xử lý lúc tạo mới).
     * Tự tính lại total_points = tổng điểm từng câu (ưu tiên points_override nếu có nhập).
     */
    public function assessmentItemsUpdate(Assessment $assessment, array $data): Assessment
    {
        $questionIds = $data['question_ids'];
        $pointsOverride = $data['points_override'] ?? [];

        $validQuestions = $this->questions->query()->whereIn('id', $questionIds)->get()->keyBy('id');

        $assessment->items()->delete();

        $totalPoints = 0;
        foreach (array_values($questionIds) as $order => $questionId) {
            $question = $validQuestions->get($questionId);
            if ($question === null) {
                continue;
            }

            $override = $pointsOverride[$questionId] ?? null;
            $points = filled($override) ? (int) $override : $question->points;

            $assessment->items()->create([
                'question_id' => $question->id,
                'order' => $order,
                'points_override' => filled($override) ? $points : null,
            ]);

            $totalPoints += $points;
        }

        return $this->assessments->update($assessment, ['total_points' => $totalPoints]);
    }

    public function assessmentUpdate(Assessment $assessment, array $data): Assessment
    {
        return $this->assessments->update($assessment, [
            'title' => $data['title'],
            'type' => $data['type'],
            'content_mode' => $this->contentModeForType($data['type']),
            'total_points' => $data['total_points'] ?? 0,
            'duration_minutes' => $data['duration_minutes'] ?: null,
            'publish_answer_rule' => $data['publish_answer_rule'] ?? 'never',
        ]);
    }

    /**
     * SỬA 18/8 (đề PDF): đề content_mode=pdf_answer_sheet PHẢI qua PdfAssessmentPublishGuard
     * trước khi phát hành (16/8 mục 5: "đề chưa hoàn thiện không bị lộ ra ngoài") — đề
     * content_mode=structured giữ nguyên hành vi cũ, không có guard (chưa có quy tắc nào
     * được đặc tả cho loại đó).
     *
     * @return array{ok: bool, message: ?string}
     */
    public function assessmentPublish(Assessment $assessment): array
    {
        if ($assessment->isPdfMode()) {
            $decision = $this->pdfAssessmentGuard->canPublish($assessment);
            if (! $decision->allowed) {
                return ['ok' => false, 'message' => $decision->message ?? 'Chưa đủ điều kiện phát hành.'];
            }
        }

        $this->assessments->update($assessment, ['status' => ContentStatus::Published->value]);

        return ['ok' => true, 'message' => null];
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

    /**
     * SỬA 19/8 (Giai đoạn 4 — "Admin duyệt đưa đề giáo viên tạo ra kho chung"): trước đây
     * KHÔNG có cơ chế nào để chuyển 1 đề do giáo viên tạo (owner_type=teacher) sang Kho chung
     * (owner_type=shared, owner_id=null) — mọi đề GV tạo mãi mãi thuộc riêng GV đó, kể cả khi
     * chất lượng tốt và Admin muốn dùng chung cho toàn hệ thống. Đây là hành động ĐƠN GIẢN,
     * chỉ đổi quyền sở hữu — KHÔNG đụng tới questions/materials/product đã gắn kèm (nếu có),
     * KHÔNG đổi status/version, và KHÔNG xoá created_by (giữ lại để vẫn biết đề này gốc do ai
     * tạo, hiển thị được ở nơi khác nếu cần — chỉ owner_type/owner_id đổi để đề hiện đúng
     * "Kho chung" ở tab Đề/bộ bài và các màn chọn đề dùng chung khác).
     *
     * Đề đã ở Kho chung rồi (owner_type=shared) thì không có gì để duyệt thêm — chặn sớm bằng
     * ValidationException thay vì âm thầm no-op, để controller trả lỗi rõ ràng nếu có ai bấm
     * nhầm/gọi thẳng route (vd 2 tab admin cùng mở, 1 tab đã duyệt xong trước).
     */
    public function assessmentPromoteToShared(Assessment $assessment): Assessment
    {
        if ($assessment->owner_type === OwnerType::Shared) {
            throw ValidationException::withMessages(['assessment' => 'Đề này đã ở Kho chung rồi, không cần duyệt thêm.']);
        }

        return $this->assessments->update($assessment, [
            'owner_type' => OwnerType::Shared->value,
            'owner_id' => null,
        ]);
    }

    // ================= "Bộ đề" — nhập hàng loạt nhiều đề PDF (Giai đoạn 3, 19/8) ==========
    // 2 cách, theo đúng lựa chọn của khách: (1) tách từ 1 file PDF lớn theo khoảng trang khai
    // sẵn, (2) tải nhiều file PDF riêng lẻ cùng lúc. Toàn bộ đề tạo ra đều content_mode=
    // pdf_answer_sheet, owner_type=shared (kho chung, giống assessmentStore() ở trên) — KHÔNG
    // có type "Luyện tập" (chỉ Luyện tập theo câu mới dùng structured, xem
    // AssessmentContentMode). Đáp án vẫn phải nhập tay sau đó ở màn "Quản lý đề PDF" — bulk chỉ
    // lo tạo khung đề + gắn đúng file PDF, không đọc/OCR nội dung.

    public function assessmentBulkCreateFormData(): array
    {
        return ['types' => $this->bulkTypeOptions()];
    }

    private function bulkTypeOptions(): array
    {
        return [
            AssessmentType::Assignment->value => 'Bài giao',
            AssessmentType::Exam->value => 'Đề thi',
            AssessmentType::CompetitionPaper->value => 'Đề thi đấu',
        ];
    }

    /** @param  array  $rows  @return Collection<int, Assessment> */
    public function assessmentBulkSplit(User $admin, UploadedFile $sourcePdf, array $rows): Collection
    {
        return $this->pdfBulkImport->splitIntoAssessments($admin, OwnerType::Shared, null, $sourcePdf, $rows);
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @param  array  $meta
     * @return Collection<int, Assessment>
     */
    public function assessmentBulkMulti(User $admin, array $files, array $meta): Collection
    {
        return $this->pdfBulkImport->createFromMultipleFiles($admin, OwnerType::Shared, null, $files, $meta);
    }

    // ================= Đề PDF + phiếu đáp án (16/8 mục 1.2/5/6) =================
    // SỬA 18/8: đề content_mode=pdf_answer_sheet KHÔNG dùng assessmentItemsFormData/Update ở
    // trên (đó là cho content_mode=structured — gắn Question thật vào assessment_items).
    // Đề PDF không có Question nào cả — chỉ có 1 file PDF + đáp án đúng từng câu
    // (AssessmentAnswerKey) và/hoặc bài lập trình con (AssessmentCodingItem).
    // SỬA 18/8 (2): toàn bộ xử lý thật đã chuyển sang App\Services\PdfAssessmentEditingService
    // (dùng chung với Teacher\AssessmentService) — các hàm dưới đây chỉ là lớp mỏng giữ
    // nguyên tên/route cũ cho AdminContentController, không đổi hành vi.

    /** admin.content.assessments.pdf.edit — dữ liệu màn cấu hình đề PDF. */
    public function assessmentPdfFormData(Assessment $assessment): array
    {
        return $this->pdfEditing->formData($assessment);
    }

    /** admin.content.assessments.pdf.update. @param  array  $answerKeyRows */
    public function assessmentPdfUpdate(
        Assessment $assessment,
        array $data,
        array $answerKeyRows,
        ?UploadedFile $pdf,
        ?UploadedFile $solutionPdf,
    ): Assessment {
        return $this->pdfEditing->update($assessment, $data, $answerKeyRows, $pdf, $solutionPdf);
    }

    /** admin.content.assessments.coding-items.store — thêm 1 bài lập trình con vào đề PDF. */
    public function codingItemStore(Assessment $assessment, array $data): AssessmentCodingItem
    {
        return $this->pdfEditing->codingItemStore($assessment, $data);
    }

    public function codingItemUpdate(AssessmentCodingItem $item, array $data): AssessmentCodingItem
    {
        return $this->pdfEditing->codingItemUpdate($item, $data);
    }

    public function codingItemDestroy(AssessmentCodingItem $item): void
    {
        $this->pdfEditing->codingItemDestroy($item);
    }

    /** admin.content.assessments.coding-items.test-cases.import — tải 1 gói ZIP input/output. */
    public function codingItemImportTestCasesZip(AssessmentCodingItem $item, UploadedFile $zip): int
    {
        return $this->pdfEditing->codingItemImportTestCasesZip($item, $zip);
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
