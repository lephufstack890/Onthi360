<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\UploadedDocumentStatus;
use App\Http\Controllers\Controller;
use App\Models\AssessmentCodingItem;
use App\Services\PdfAssessmentEditingService;
use App\Services\Teacher\AssessmentService;
use App\Services\Teacher\DocumentImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssessmentController extends Controller
{
    public function __construct(
        private readonly AssessmentService $assessmentService,
        private readonly DocumentImportService $documentImportService,
    ) {}

    /** teacher.assessments.index (TEA-04) — đề do chính giáo viên tạo (6.3, 8.4). */
    public function index(Request $request): View
    {
        return view('teacher.assessments.index', $this->assessmentService->listForTeacher(Auth::user()));
    }

    /**
     * teacher.assessments.create (TEA-04) — chọn câu từ kho riêng của giáo viên, trộn được
     * nhiều kiểu câu trong cùng một đề (6.3).
     */
    public function create(Request $request): View
    {
        return view('teacher.assessments.create', $this->assessmentService->createFormData(Auth::user()));
    }

    /**
     * teacher.assessments.store — SỬA 24/8 (khách yêu cầu): CHỈ lưu đề (luôn Nháp) — bỏ hẳn
     * nhánh "giao ngay cho lớp" từng nằm ở đây. Giao đề giờ làm ở tab "Giao đề" trong Chi
     * tiết lớp, xem Teacher\ClassRoomController::assignAssessment() (tái dùng nguyên
     * AssessmentService::assignToClass() bên dưới, không đổi logic đó).
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->storeRules());

        $this->assessmentService->store(Auth::user(), $data);

        return redirect()->route('teacher.assessments.index')->with('status', 'assessment-created');
    }

    /** teacher.assessments.import (TEA-05 — tải) — trạng thái xử lý OCR thật (6.4). */
    public function import(Request $request): View
    {
        $user = Auth::user();

        return view('teacher.assessments.import', $this->assessmentService->importStatusFor($user) + [
            'maxFileKb' => DocumentImportService::maxFileKb(),
        ]);
    }

    /**
     * teacher.assessments.import.store — tải Word/PDF lên và xử lý ngay (6.4): quét chữ
     * ký định dạng, trích xuất văn bản (OCR nếu là PDF scan/ảnh), phân rã thành câu nháp.
     * Có thể mất vài chục giây với tệp scan nhiều trang nên nới thời gian chạy tối đa.
     */
    public function importStore(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:docx,pdf', 'max:'.DocumentImportService::maxFileKb()],
        ], [], ['file' => 'Tệp']);

        set_time_limit(300);

        $document = $this->documentImportService->import(Auth::user(), $request->file('file'));

        if ($document->status === UploadedDocumentStatus::Failed) {
            return redirect()->route('teacher.assessments.import')
                ->with('status', 'import-failed')
                ->with('importError', $document->error_log);
        }

        return redirect()->route('teacher.assessments.reviewDraft', ['document' => $document->id])
            ->with('status', 'import-parsed');
    }

    /** teacher.assessments.documents.download — tải lại đúng tệp gốc đã upload (chỉ chủ sở hữu). */
    public function downloadDocument(Request $request, int $document): StreamedResponse
    {
        $documentModel = $this->documentImportService->findOwnedDocument(Auth::user(), $document);

        return Storage::disk('local')->download($documentModel->storage_path, $documentModel->original_filename);
    }

    /**
     * teacher.assessments.reviewDraft (TEA-05 — rà soát), truyền $document + $drafts thật
     * từ App\Models\DraftQuestion (6.4). Nhận ?document=<id>; nếu không có, lấy tài liệu
     * "cần rà soát" gần nhất của giáo viên.
     */
    public function reviewDraft(Request $request): View
    {
        $user = Auth::user();
        $documentId = $request->filled('document') ? (int) $request->query('document') : null;

        return view('teacher.assessments.review-draft', $this->assessmentService->reviewDraftFor($user, $documentId));
    }

    /** teacher.assessments.drafts.store — "+ Thêm câu thủ công" ở màn rà soát (6.4). */
    public function draftStore(Request $request, int $document): RedirectResponse
    {
        $documentModel = $this->documentImportService->findOwnedDocument(Auth::user(), $document);
        $this->documentImportService->addManualDraft($documentModel);

        return redirect()->route('teacher.assessments.reviewDraft', ['document' => $documentModel->id])
            ->with('status', 'draft-added');
    }

    /**
     * teacher.assessments.drafts.update — sửa nội dung/đáp án/loại của 1 câu nháp (6.4). Nếu
     * câu đủ điều kiện, lưu là chuyển thẳng vào kho câu hỏi riêng (dạng Nháp) luôn — không
     * cần bước "Chuyển vào kho câu hỏi" riêng nữa. Sửa lại 1 câu đã ở trong kho sẽ cập nhật
     * đúng câu đó, không tạo bản sao.
     */
    public function draftUpdate(Request $request, int $draft): RedirectResponse
    {
        $draftModel = $this->documentImportService->findOwnedDraft(Auth::user(), $draft);
        $type = $request->input('type_guess', 'mcq');
        $data = $request->validate($this->draftValidationRules($type));

        $result = $this->documentImportService->reviewSave(Auth::user(), $draftModel, $type, $data);

        $redirect = redirect()->route('teacher.assessments.reviewDraft', ['document' => $draftModel->uploaded_document_id]);

        return $result['promoted']
            ? $redirect->with('status', 'draft-promoted-one')
            : $redirect->with('status', 'draft-saved-pending')->with('draftPendingReason', $result['reason']);
    }

    /** teacher.assessments.drafts.merge — gộp 2 câu bị OCR/tách sai thành 1 (6.4). */
    public function draftMerge(Request $request, int $draft): RedirectResponse
    {
        $draftModel = $this->documentImportService->findOwnedDraft(Auth::user(), $draft);
        $documentId = $draftModel->uploaded_document_id;
        $data = $request->validate(['merge_with_id' => ['required', 'integer']]);

        try {
            $this->documentImportService->mergeDrafts(Auth::user(), $draftModel, (int) $data['merge_with_id']);
        } catch (ValidationException $e) {
            return redirect()->route('teacher.assessments.reviewDraft', ['document' => $documentId])->withErrors($e->errors());
        }

        return redirect()->route('teacher.assessments.reviewDraft', ['document' => $documentId])->with('status', 'draft-merged');
    }

    /** teacher.assessments.drafts.discard — bỏ 1 câu nháp (không xóa cứng, giữ lịch sử — 6.4). */
    public function draftDiscard(Request $request, int $draft): RedirectResponse
    {
        $draftModel = $this->documentImportService->findOwnedDraft(Auth::user(), $draft);
        $documentId = $draftModel->uploaded_document_id;

        $this->documentImportService->discardDraft($draftModel);

        return redirect()->route('teacher.assessments.reviewDraft', ['document' => $documentId])->with('status', 'draft-discarded');
    }

    /**
     * teacher.assessments.publish — phát hành riêng, không giao lớp ngay (6.2). SỬA 18/8:
     * dùng CHUNG route/hàm này cho cả đề PDF (papers.*) — AssessmentService::publish() đã tự
     * nhận diện content_mode. Chỉ khác chỗ redirect về đúng danh sách (Bài giao cũ vs Đề PDF).
     */
    public function publish(Request $request, int $assessment): RedirectResponse
    {
        $assessmentModel = $this->assessmentService->findOwned(Auth::user(), $assessment);
        $indexRoute = $assessmentModel->isPdfMode() ? 'teacher.papers.index' : 'teacher.assessments.index';

        try {
            $this->assessmentService->publish($assessmentModel);
        } catch (ValidationException $e) {
            return redirect()->route($indexRoute)->withErrors($e->errors());
        }

        return redirect()->route($indexRoute)->with('status', 'assessment-published');
    }

    // SỬA 24/8 — khách yêu cầu bỏ hẳn "Giao cho lớp" khỏi Bài tập & Đề: route/hàm
    // teacher.assessments.assign (cùng assignRules()/combineOptionalDateTime() dùng riêng
    // cho nó) đã CHUYỂN HẲN sang Teacher\ClassRoomController::assignAssessment() — "Giao đề"
    // giờ chỉ làm từ tab "Giao đề" trong Chi tiết lớp (đã biết sẵn lớp, chỉ cần chọn đề).
    // AssessmentService::assignToClass() KHÔNG đổi — vẫn 1 nơi duy nhất giữ logic giao đề.

    private function storeRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'question_ids' => ['required', 'array', 'min:1'],
            'question_ids.*' => ['integer'],
            'points_override' => ['nullable', 'array'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'max_resubmissions' => ['nullable', 'integer', 'min:1', 'max:10'],
            'publish_answer_rule' => ['nullable', 'in:never,after_deadline,immediately'],
        ];
    }

    private function draftValidationRules(string $type): array
    {
        $common = [
            'type_guess' => ['required', 'in:mcq,fill_blank,coding'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'points' => ['required', 'integer', 'min:1', 'max:100'],
        ];

        return match ($type) {
            'mcq' => $common + [
                'options' => ['nullable', 'array'],
                'options.*' => ['nullable', 'string', 'max:500'],
                'correct_option' => ['nullable', 'string', 'max:10'],
            ],
            'fill_blank' => $common + [
                'accepted_answers' => ['nullable', 'string', 'max:1000'],
                'case_sensitive' => ['nullable', 'boolean'],
            ],
            'coding' => $common + [
                'time_limit_ms' => ['nullable', 'integer', 'min:100', 'max:60000'],
                'memory_limit_mb' => ['nullable', 'integer', 'min:16', 'max:2048'],
                'test_cases' => ['nullable', 'string', 'max:20000'],
            ],
            default => $common,
        };
    }

    // ================= Đề thi lẻ (PDF) — 16/8 mục 1.2/2/5/6 =================
    // SỬA 18/8 ("Admin hoặc giáo viên" tải đề PDF): tách hẳn khỏi index/create/store ở trên
    // (đó là "Bài giao" cũ — chọn Question rời, KHÔNG đổi). Đề PDF ở đây luôn riêng tư của
    // giáo viên (owner_type=teacher) cho tới khi Admin duyệt đưa ra kho chung (chưa làm ở
    // Giai đoạn 1, xem ghi chú App\Services\Teacher\AssessmentService).

    /** teacher.papers.index — danh sách đề PDF riêng của giáo viên. */
    public function papersIndex(): View
    {
        return view('teacher.assessments.papers.index', $this->assessmentService->papersForTeacher(Auth::user()));
    }

    public function papersCreate(): View
    {
        return view('teacher.assessments.papers.create', $this->assessmentService->paperCreateFormData());
    }

    public function papersStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:assignment,exam,competition_paper'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'publish_answer_rule' => ['nullable', 'string', 'in:never,after_deadline,immediately'],
        ]);

        $paper = $this->assessmentService->paperStore(Auth::user(), $data);

        return redirect()->route('teacher.papers.pdf.edit', $paper->id)->with('status', 'paper-created');
    }

    // ================= "Bộ đề" — nhập hàng loạt nhiều đề PDF (Giai đoạn 3, 19/8) ==========
    // Khác papersCreate/Store ở trên (tạo TỪNG đề PDF trống 1 lần) — bulk tạo NHIỀU đề cùng
    // lúc, riêng tư của giáo viên (owner_type=teacher), theo 2 cách: tách từ 1 file PDF lớn
    // theo khoảng trang, hoặc tải nhiều file PDF riêng lẻ cùng lúc.

    public function papersBulkCreate(): View
    {
        return view('teacher.assessments.papers.bulk', $this->assessmentService->paperBulkCreateFormData());
    }

    public function papersBulkSplit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source_pdf' => ['required', 'file', 'mimes:pdf', 'max:'.AssessmentService::maxBulkSourcePdfKb()],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.exam_code' => ['nullable', 'string', 'max:60'],
            'rows.*.title' => ['required', 'string', 'max:255'],
            'rows.*.type' => ['required', 'string', 'in:assignment,exam,competition_paper'],
            'rows.*.from_page' => ['required', 'integer', 'min:1'],
            'rows.*.to_page' => ['required', 'integer', 'min:1', 'gte:rows.*.from_page'],
        ], [], ['source_pdf' => 'File PDF gốc']);

        $created = $this->assessmentService->paperBulkSplit(Auth::user(), $data['source_pdf'], $data['rows']);

        return redirect()->route('teacher.papers.index')
            ->with('status', 'papers-bulk-created')
            ->with('bulkCreatedCount', $created->count());
    }

    public function papersBulkMulti(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'mimes:pdf', 'max:'.PdfAssessmentEditingService::maxPdfKb()],
            'meta' => ['required', 'array', 'min:1'],
            'meta.*.exam_code' => ['nullable', 'string', 'max:60'],
            'meta.*.title' => ['required', 'string', 'max:255'],
            'meta.*.type' => ['required', 'string', 'in:assignment,exam,competition_paper'],
        ]);

        $created = $this->assessmentService->paperBulkMulti(Auth::user(), $data['files'], $data['meta']);

        return redirect()->route('teacher.papers.index')
            ->with('status', 'papers-bulk-created')
            ->with('bulkCreatedCount', $created->count());
    }

    /** teacher.papers.pdf.edit — màn cấu hình PDF + đáp án + bài lập trình con (chỉ đề của chính giáo viên). */
    public function papersPdfEdit(int $assessment): View
    {
        $paper = $this->assessmentService->findOwned(Auth::user(), $assessment);

        return view('teacher.assessments.papers.pdf', $this->assessmentService->paperPdfFormData($paper));
    }

    public function papersPdfUpdate(Request $request, int $assessment): RedirectResponse
    {
        $paper = $this->assessmentService->findOwned(Auth::user(), $assessment);

        $data = $request->validate([
            'exam_code' => ['nullable', 'string', 'max:60', 'unique:assessments,exam_code,'.$paper->id],
            'preview_page_from' => ['nullable', 'integer', 'min:1'],
            'preview_page_to' => ['nullable', 'integer', 'min:1', 'gte:preview_page_from'],
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:'.PdfAssessmentEditingService::maxPdfKb()],
            'solution_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:'.PdfAssessmentEditingService::maxPdfKb()],
            'answer_keys' => ['nullable', 'array'],
            'answer_keys.*.question_no' => ['required_with:answer_keys', 'integer', 'min:1'],
            'answer_keys.*.question_type' => ['required_with:answer_keys', 'string', 'in:single_choice,true_false_group,short_answer'],
            'answer_keys.*.correct_answer' => ['required_with:answer_keys'],
            'answer_keys.*.points' => ['nullable', 'integer', 'min:0'],
        ], [], [
            'exam_code' => 'Mã đề',
            'pdf' => 'Tệp PDF đề',
            'solution_pdf' => 'Tệp PDF lời giải',
        ]);

        $answerKeyRows = array_map(
            fn (array $row) => $row + ['correct_answer' => $this->normalizeAnswerSheetValue($row['question_type'], $row['correct_answer'])],
            $data['answer_keys'] ?? [],
        );

        $this->assessmentService->paperPdfUpdate(
            $paper,
            $data,
            $answerKeyRows,
            $request->file('pdf'),
            $request->file('solution_pdf'),
        );

        return redirect()->route('teacher.papers.pdf.edit', $paper->id)->with('status', 'paper-pdf-updated');
    }

    /** Cùng cách chuẩn hoá true_false_group với AdminContentController — xem docblock ở đó. */
    private function normalizeAnswerSheetValue(string $questionType, mixed $raw): mixed
    {
        return match ($questionType) {
            'single_choice' => strtoupper(trim((string) $raw)),
            'short_answer' => trim((string) $raw),
            'true_false_group' => collect((array) $raw)->mapWithKeys(
                fn ($v, $k) => [$k => (bool) ((int) $v)]
            )->all(),
            default => $raw,
        };
    }

    public function papersCodingItemsStore(Request $request, int $assessment): RedirectResponse
    {
        $paper = $this->assessmentService->findOwned(Auth::user(), $assessment);
        $data = $request->validate($this->codingItemRules(), [], ['code' => 'Mã bài']);

        $this->assessmentService->paperCodingItemStore($paper, $data);

        return redirect()->route('teacher.papers.pdf.edit', $paper->id)->with('status', 'coding-item-created');
    }

    public function papersCodingItemsUpdate(Request $request, AssessmentCodingItem $codingItem): RedirectResponse
    {
        $this->assessmentService->assertOwnsCodingItem(Auth::user(), $codingItem);
        $data = $request->validate($this->codingItemRules(), [], ['code' => 'Mã bài']);

        $this->assessmentService->paperCodingItemUpdate($codingItem, $data);

        return redirect()->route('teacher.papers.pdf.edit', $codingItem->assessment_id)->with('status', 'coding-item-updated');
    }

    public function papersCodingItemsDestroy(AssessmentCodingItem $codingItem): RedirectResponse
    {
        $this->assessmentService->assertOwnsCodingItem(Auth::user(), $codingItem);
        $assessmentId = $codingItem->assessment_id;

        $this->assessmentService->paperCodingItemDestroy($codingItem);

        return redirect()->route('teacher.papers.pdf.edit', $assessmentId)->with('status', 'coding-item-deleted');
    }

    private function codingItemRules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40'],
            'title' => ['required', 'string', 'max:255'],
            'pdf_page' => ['nullable', 'integer', 'min:1'],
            'allowed_languages' => ['nullable', 'array'],
            'allowed_languages.*' => ['string', 'in:cpp,python'],
            'time_limit_ms' => ['nullable', 'integer', 'min:100', 'max:60000'],
            'memory_limit_kb' => ['nullable', 'integer', 'min:16384', 'max:1048576'],
            'points' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function papersCodingItemsTestCasesImport(Request $request, AssessmentCodingItem $codingItem): RedirectResponse
    {
        $this->assessmentService->assertOwnsCodingItem(Auth::user(), $codingItem);

        $request->validate([
            'test_cases_zip' => ['required', 'file', 'mimes:zip', 'max:'.PdfAssessmentEditingService::maxPdfKb()],
        ], [], ['test_cases_zip' => 'Gói ZIP test case']);

        $created = $this->assessmentService->paperCodingItemImportTestCasesZip($codingItem, $request->file('test_cases_zip'));

        return redirect()->route('teacher.papers.pdf.edit', $codingItem->assessment_id)
            ->with('status', 'test-cases-imported')
            ->with('testCasesImportedCount', $created);
    }
}
