<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UploadedDocumentStatus;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentCodingItem;
use App\Models\Material;
use App\Models\Question;
use App\Models\Tag;
use App\Services\Admin\ContentService;
use App\Services\Admin\DocumentImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContentController extends Controller
{
    public function __construct(
        private ContentService $contentService,
        private DocumentImportService $documentImportService,
    ) {}

    /** admin.content.index (ADM-03) — 6.2/6.4/6.5. */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'questions');

        // SỬA 26/8 ("gộp Học liệu vào Sản phẩm & quyền"): tab "Học liệu" đã bỏ khỏi Nội dung —
        // link/bookmark cũ ?tab=materials được đưa về tab mặc định thay vì hiện bảng cũ đã
        // không còn lối vào từ giao diện nữa (thêm/sửa/xoá học liệu giờ làm ở trang chi tiết
        // từng sản phẩm, xem ProductController::show()).
        if ($tab === 'materials') {
            return redirect()->route('admin.content.index', ['tab' => 'questions']);
        }

        return view('admin.content.index', $this->contentService->indexData($tab));
    }

    /** admin.content.show — 6.2 (chặn phát hành khi thiếu cấu hình). */
    public function show(Request $request, int $content): View
    {
        return view('admin.content.show', $this->contentService->showData($content));
    }

    // ================= Học liệu (Material) =================

    /** SỬA 26/8 ("gộp Học liệu vào Sản phẩm & quyền") — ?product_id= khi vào từ nút "+ Thêm học liệu" ở trang chi tiết 1 sản phẩm, để form tự điền sẵn (xem ContentService::materialCreateFormData()). */
    public function materialsCreate(Request $request): View
    {
        $productId = $request->integer('product_id') ?: null;

        return view('admin.content.materials.create', $this->contentService->materialCreateFormData($productId));
    }

    public function materialsStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'parent_id' => ['nullable', 'integer', 'exists:materials,id'],
            'type' => ['required', 'string', 'in:chapter,section,assessment_ref'],
            'title' => ['required', 'string', 'max:255'],
            'order' => ['nullable', 'integer', 'min:0'],
            'assessment_id' => ['nullable', 'integer', 'exists:assessments,id'],
            'status' => ['required', 'string', 'in:draft,pending_review,published,archived'],
            // SỬA 25/8 (tải bài — cả 2 đều TÙY CHỌN, không phá form tạo mục lục cũ không cần
            // mã/PDF): mã trùng trong CÙNG sản phẩm được ContentService::materialStore() ném
            // ValidationException riêng (không kiểm tra được bằng rule 'unique' đơn giản vì
            // phạm vi trùng lặp lồng theo product_id, xem assertMaterialCodeAvailable()).
            'code' => ['nullable', 'string', 'max:60'],
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:'.ContentService::maxPdfKb()],
            // SỬA 4/9 (khách yêu cầu: "file học liệu có thể là audio, pdf, ảnh động... đính
            // nhiều loại cùng lúc") — cả 2 đều TÙY CHỌN, độc lập với pdf ở trên.
            'audio' => ['nullable', 'file', 'mimes:mp3,wav,ogg,m4a,aac', 'max:'.ContentService::maxMaterialAudioKb()],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:'.ContentService::maxMaterialImageKb()],
        ], [], ['code' => 'Mã bài', 'pdf' => 'Tệp PDF bài học', 'audio' => 'Tệp audio', 'image' => 'Tệp ảnh']);

        try {
            $material = $this->contentService->materialStore($data);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        // SỬA 4/9 — quay về đúng trang tài liệu vừa thêm học liệu (đồng bộ với
        // materialsUpdate()/materialsDestroy() bên dưới) thay vì trang "content.show" chung.
        return redirect()->route('admin.products.show', $material->product_id)->with('status', 'material-created');
    }

    public function materialsEdit(int $material): View
    {
        return view('admin.content.materials.edit', $this->contentService->materialEditFormData($material));
    }

    public function materialsUpdate(Request $request, Material $material): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'parent_id' => ['nullable', 'integer', 'exists:materials,id'],
            'type' => ['required', 'string', 'in:chapter,section,assessment_ref'],
            'title' => ['required', 'string', 'max:255'],
            'order' => ['nullable', 'integer', 'min:0'],
            'assessment_id' => ['nullable', 'integer', 'exists:assessments,id'],
            'status' => ['required', 'string', 'in:draft,pending_review,published,archived'],
            // SỬA 25/8 (tải bài — "cần có cơ chế sửa sau khi nhập"): để trống 'pdf' thì GIỮ
            // NGUYÊN file cũ (materialUpdate() chỉ đụng vào pdf_path khi có tải file mới).
            'code' => ['nullable', 'string', 'max:60'],
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:'.ContentService::maxPdfKb()],
            // SỬA 4/9 — xem materialsStore() ở trên.
            'audio' => ['nullable', 'file', 'mimes:mp3,wav,ogg,m4a,aac', 'max:'.ContentService::maxMaterialAudioKb()],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:'.ContentService::maxMaterialImageKb()],
        ], [], ['code' => 'Mã bài', 'pdf' => 'Tệp PDF bài học', 'audio' => 'Tệp audio', 'image' => 'Tệp ảnh']);

        try {
            $updated = $this->contentService->materialUpdate($material, $data);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        // SỬA 26/8: sau khi lưu, quay về trang chi tiết sản phẩm của học liệu đó (giống
        // materialsDestroy()/materialsBulkImportStore()) thay vì trang "content.show" chung —
        // dùng product_id của bản ghi ĐÃ CẬP NHẬT vì admin có thể đổi "Thuộc sản phẩm" ngay
        // trong form sửa này.
        return redirect()->route('admin.products.show', $updated->product_id)->with('status', 'material-updated');
    }

    public function materialsPublish(Material $material): RedirectResponse
    {
        $this->contentService->materialPublish($material);

        return redirect()->route('admin.content.show', $material->id)->with('status', 'material-published');
    }

    public function materialsReject(Request $request, Material $material): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->contentService->materialReject($material, $data['reason']);

        return redirect()->route('admin.content.show', $material->id)->with('status', 'material-rejected');
    }

    public function materialsArchive(Request $request, Material $material): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->contentService->materialArchive($material, $data['reason']);

        return redirect()->route('admin.content.show', $material->id)->with('status', 'material-archived');
    }

    /**
     * admin.content.materials.destroy (25/8, SỬA 25/8 (7)) — "thêm tính năng xóa cho admin":
     * XÓA THẬT bản ghi + file PDF liên quan (khác materialsArchive() ở trên, vốn chỉ đổi
     * status) — xem ContentService::materialDelete(). Không thể khôi phục.
     */
    public function materialsDestroy(Material $material): RedirectResponse
    {
        // SỬA 26/8 ("gộp Học liệu vào Sản phẩm & quyền"): lấy product_id TRƯỚC khi xoá — sau
        // materialDelete() bản ghi (và có thể cả các bài con) đã mất, không đọc lại được nữa.
        // Quay về đúng trang sản phẩm thay vì tab "Học liệu" đã bỏ (xem ContentService::
        // materialDelete()).
        $productId = $material->product_id;

        $this->contentService->materialDelete($material);

        return redirect()->route('admin.products.show', $productId)->with('status', 'material-deleted');
    }

    // ================= Học liệu — "tải bài hàng loạt" qua ZIP (25/8) =================
    // Xem App\Services\Admin\ContentService::materialsBulkImportFromZip() — mỗi tệp .pdf ở gốc
    // ZIP tạo thành 1 Material, mã bài lấy thẳng từ tên tệp. Bài nào cần sửa lại (tên/mã/PDF)
    // thì vào materialsEdit như bình thường sau khi nhập xong (đã hỗ trợ sửa, xem materialsUpdate()).

    /**
     * admin.content.materials.bulk.create — chọn sản phẩm + loại + trạng thái áp dụng chung, rồi tải 1 ZIP.
     * SỬA 26/8 ("gộp Học liệu vào Sản phẩm & quyền") — ?product_id= khi vào từ nút "+ Tải hàng
     * loạt (ZIP)" ở trang chi tiết 1 sản phẩm, để form tự điền sẵn.
     */
    public function materialsBulkImportCreate(Request $request): View
    {
        $productId = $request->integer('product_id') ?: null;

        return view('admin.content.materials.bulk', $this->contentService->materialsBulkImportFormData($productId));
    }

    public function materialsBulkImportStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'parent_id' => ['nullable', 'integer', 'exists:materials,id'],
            // Cố ý KHÔNG cho 'assessment_ref' ở đây — loại đó chỉ tham chiếu 1 Assessment có sẵn,
            // không có PDF riêng (xem ContentService::materialsBulkImportFormData()).
            'type' => ['required', 'string', 'in:chapter,section'],
            'status' => ['required', 'string', 'in:draft,pending_review,published,archived'],
            'zip_package' => ['required', 'file', 'mimes:zip', 'max:'.ContentService::maxBulkMaterialZipKb()],
        ], [], ['zip_package' => 'Gói ZIP']);

        try {
            $created = $this->contentService->materialsBulkImportFromZip(
                (int) $data['product_id'],
                $data['type'],
                $data['parent_id'] ? (int) $data['parent_id'] : null,
                $data['status'],
                $request->file('zip_package'),
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        // SỬA 26/8 ("gộp Học liệu vào Sản phẩm & quyền"): quay về đúng trang sản phẩm thay vì
        // tab "Học liệu" đã bỏ.
        return redirect()->route('admin.products.show', (int) $data['product_id'])
            ->with('status', 'materials-bulk-imported')
            ->with('bulkCreatedCount', $created->count());
    }

    // ================= Câu hỏi kho chung (Question) =================

    public function questionsCreate(): View
    {
        return view('admin.content.questions.create', $this->contentService->questionCreateFormData());
    }

    private function questionGradingRules(): array
    {
        return [
            'options' => ['nullable', 'array'],
            'options.*' => ['nullable', 'string', 'max:255'],
            'correct_option' => ['nullable', 'integer', 'min:0', 'max:3'],
            'accepted_answers' => ['nullable', 'string', 'max:2000'],
            'case_sensitive' => ['nullable', 'boolean'],
            'test_cases_raw' => ['nullable', 'string', 'max:10000'],
            'time_limit_ms' => ['nullable', 'integer', 'min:1'],
            'memory_limit_mb' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** SỬA 19/8 (Giai đoạn 6) — tag có sẵn (tick chọn) + tag mới gõ tay, xem ContentService::resolveTagIds(). */
    private function tagRules(): array
    {
        return [
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'new_tags' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function questionsStore(Request $request): RedirectResponse
    {
        $data = $request->validate(array_merge([
            'code' => ['required', 'string', 'max:40'],
            'type' => ['required', 'string', 'in:coding,mcq,fill_blank'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'points' => ['nullable', 'integer', 'min:0'],
            'visibility' => ['required', 'string', 'in:public,private'],
        ], $this->questionGradingRules(), $this->tagRules()));

        $question = $this->contentService->questionStore(Auth::user(), $data);

        return redirect()->route('admin.content.show', $question->id)->with('status', 'question-created');
    }

    public function questionsEdit(int $question): View
    {
        return view('admin.content.questions.edit', $this->contentService->questionEditFormData($question));
    }

    public function questionsUpdate(Request $request, Question $question): RedirectResponse
    {
        $data = $request->validate(array_merge([
            'code' => ['required', 'string', 'max:40'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'points' => ['nullable', 'integer', 'min:0'],
            'visibility' => ['required', 'string', 'in:public,private'],
        ], $this->questionGradingRules(), $this->tagRules()));

        $this->contentService->questionUpdate($question, $data);

        return redirect()->route('admin.content.show', $question->id)->with('status', 'question-updated');
    }

    /** admin.content.questions.newVersion — 6.2: câu đã có người làm phải tạo version mới. */
    public function questionsNewVersion(Request $request, Question $question): RedirectResponse
    {
        $data = $request->validate(array_merge([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'points' => ['nullable', 'integer', 'min:0'],
            'visibility' => ['required', 'string', 'in:public,private'],
        ], $this->questionGradingRules(), $this->tagRules()));

        $newQuestion = $this->contentService->questionCreateNewVersion($question, $data);

        return redirect()->route('admin.content.show', $newQuestion->id)->with('status', 'question-versioned');
    }

    public function questionsPublish(Question $question): RedirectResponse
    {
        $result = $this->contentService->questionPublish($question);

        if (! $result['ok']) {
            return redirect()->route('admin.content.show', $question->id)->withErrors(['publish' => $result['message']]);
        }

        return redirect()->route('admin.content.show', $question->id)->with('status', 'question-published');
    }

    public function questionsReject(Request $request, Question $question): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->contentService->questionReject($question, $data['reason']);

        return redirect()->route('admin.content.show', $question->id)->with('status', 'question-rejected');
    }

    public function questionsArchive(Request $request, Question $question): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->contentService->questionArchive($question, $data['reason']);

        return redirect()->route('admin.content.show', $question->id)->with('status', 'question-archived');
    }

    // ================= Câu hỏi lập trình — "Nhập từ gói ZIP" (24/8) =================
    // Xem App\Services\Admin\ContentService::questionStoreFromZipPackage() — tái sử dụng
    // questionStore() nguyên vẹn nên KHÔNG đụng gì tới questionsCreate/questionsStore ở trên.

    /** admin.content.questions.zipImport — tải 1 gói ZIP OT360-QPACK, tự điền, redirect sang Sửa. */
    public function questionsZipImportStore(Request $request): RedirectResponse
    {
        $request->validate([
            'zip_package' => ['required', 'file', 'mimes:zip', 'max:'.ContentService::maxQuestionZipKb()],
        ], [], ['zip_package' => 'Gói ZIP']);

        try {
            $question = $this->contentService->questionStoreFromZipPackage(Auth::user(), $request->file('zip_package'));
        } catch (ValidationException $e) {
            return redirect()->route('admin.content.questions.create')->withErrors($e->errors());
        }

        return redirect()->route('admin.content.questions.edit', $question->id)->with('status', 'question-zip-imported');
    }

    /** admin.content.questions.attachment — tải lại 1 tệp đính kèm (đề/lời giải/code mẫu) đã nhập từ ZIP. */
    public function questionsAttachmentDownload(Request $request, Question $question, string $kind): StreamedResponse
    {
        $info = $this->contentService->questionAttachmentInfo($question, $kind);

        return Storage::disk('local')->download($info['path'], $info['filename']);
    }

    // ================= Nhập đề Word/PDF/OCR -> Kho chung (6.4) =================

    /** admin.content.questions.import (ADM-03, TEA-05 tương đương phía admin) — trạng thái xử lý OCR thật. */
    public function questionsImport(Request $request): View
    {
        return view('admin.content.questions.import', [
            'documents' => $this->contentService->indexData('drafts')['documents'],
            'maxFileKb' => DocumentImportService::maxFileKb(),
        ]);
    }

    /**
     * admin.content.questions.import.store — tải Word/PDF lên và xử lý ngay (6.4): quét
     * chữ ký định dạng, trích xuất văn bản (OCR nếu là PDF scan/ảnh), phân rã thành câu
     * nháp vào "Kho chung". Có thể mất vài chục giây với tệp scan nhiều trang.
     */
    public function questionsImportStore(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:docx,pdf', 'max:'.DocumentImportService::maxFileKb()],
        ], [], ['file' => 'Tệp']);

        set_time_limit(300);

        $document = $this->documentImportService->import(Auth::user(), $request->file('file'));

        if ($document->status === UploadedDocumentStatus::Failed) {
            return redirect()->route('admin.content.questions.import')
                ->with('status', 'import-failed')
                ->with('importError', $document->error_log);
        }

        return redirect()->route('admin.content.questions.reviewDraft', ['document' => $document->id])
            ->with('status', 'import-parsed');
    }

    /** admin.content.documents.download — tải lại đúng tệp gốc đã upload để đối chiếu. */
    public function downloadDocument(Request $request, int $document): StreamedResponse
    {
        $documentModel = $this->documentImportService->findDocument($document);

        return Storage::disk('local')->download($documentModel->storage_path, $documentModel->original_filename);
    }

    /**
     * admin.content.questions.reviewDraft — truyền $document + $drafts thật (6.4). Nhận
     * ?document=<id>; nếu không có, lấy tài liệu "cần rà soát" gần nhất trên toàn Kho chung.
     */
    public function questionsReviewDraft(Request $request): View
    {
        $documentId = $request->filled('document') ? (int) $request->query('document') : null;

        return view('admin.content.questions.review-draft', $this->contentService->reviewDraftFor($documentId));
    }

    /** admin.content.drafts.store — "+ Thêm câu thủ công" ở màn rà soát (6.4). */
    public function draftStore(Request $request, int $document): RedirectResponse
    {
        $documentModel = $this->documentImportService->findDocument($document);
        $this->documentImportService->addManualDraft($documentModel);

        return redirect()->route('admin.content.questions.reviewDraft', ['document' => $documentModel->id])
            ->with('status', 'draft-added');
    }

    /**
     * admin.content.drafts.update — sửa nội dung/đáp án/loại của 1 câu nháp (6.4). Nếu câu
     * đủ điều kiện, lưu là chuyển thẳng vào Kho chung (dạng Nháp) luôn — không cần bước
     * "Chuyển vào Kho chung" riêng nữa. Sửa lại 1 câu đã ở trong Kho chung sẽ cập nhật
     * đúng câu đó, không tạo bản sao.
     */
    public function draftUpdate(Request $request, int $draft): RedirectResponse
    {
        $draftModel = $this->documentImportService->findDraft($draft);
        $type = $request->input('type_guess', 'mcq');
        $data = $request->validate($this->draftValidationRules($type));

        $result = $this->documentImportService->reviewSave(Auth::user(), $draftModel, $type, $data);

        $redirect = redirect()->route('admin.content.questions.reviewDraft', ['document' => $draftModel->uploaded_document_id]);

        return $result['promoted']
            ? $redirect->with('status', 'draft-promoted-one')
            : $redirect->with('status', 'draft-saved-pending')->with('draftPendingReason', $result['reason']);
    }

    /** admin.content.drafts.merge — gộp 2 câu bị OCR/tách sai thành 1 (6.4). */
    public function draftMerge(Request $request, int $draft): RedirectResponse
    {
        $draftModel = $this->documentImportService->findDraft($draft);
        $documentId = $draftModel->uploaded_document_id;
        $data = $request->validate(['merge_with_id' => ['required', 'integer']]);

        try {
            $this->documentImportService->mergeDrafts($draftModel, (int) $data['merge_with_id']);
        } catch (ValidationException $e) {
            return redirect()->route('admin.content.questions.reviewDraft', ['document' => $documentId])->withErrors($e->errors());
        }

        return redirect()->route('admin.content.questions.reviewDraft', ['document' => $documentId])->with('status', 'draft-merged');
    }

    /** admin.content.drafts.discard — bỏ 1 câu nháp (không xóa cứng, giữ lịch sử — 6.4). */
    public function draftDiscard(Request $request, int $draft): RedirectResponse
    {
        $draftModel = $this->documentImportService->findDraft($draft);
        $documentId = $draftModel->uploaded_document_id;

        $this->documentImportService->discardDraft($draftModel);

        return redirect()->route('admin.content.questions.reviewDraft', ['document' => $documentId])->with('status', 'draft-discarded');
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

    // ================= Tag/Chuyên đề (Giai đoạn 6, 19/8) =================
    // Quản lý ở đúng tab "Tag/Chuyên đề" trong admin.content.index (tab=tags) — không tạo
    // trang riêng, xem ContentService::indexData()/tagStore()/tagUpdate()/tagDestroy().

    public function tagsStore(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $this->contentService->tagStore($data['name']);

        return redirect()->route('admin.content.index', ['tab' => 'tags'])->with('status', 'tag-created');
    }

    public function tagsUpdate(Request $request, Tag $tag): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);

        try {
            $this->contentService->tagUpdate($tag, $data['name']);
        } catch (ValidationException $e) {
            return redirect()->route('admin.content.index', ['tab' => 'tags'])->withErrors($e->errors());
        }

        return redirect()->route('admin.content.index', ['tab' => 'tags'])->with('status', 'tag-updated');
    }

    public function tagsDestroy(Tag $tag): RedirectResponse
    {
        $this->contentService->tagDestroy($tag);

        return redirect()->route('admin.content.index', ['tab' => 'tags'])->with('status', 'tag-deleted');
    }

    // ================= Đề/bộ bài (Assessment) =================

    public function assessmentsCreate(): View
    {
        return view('admin.content.assessments.create', $this->contentService->assessmentCreateFormData());
    }

    public function assessmentsStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:practice,assignment,exam,competition_paper'],
            'total_points' => ['nullable', 'integer', 'min:0'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'publish_answer_rule' => ['required', 'string', 'in:never,after_deadline,immediately'],
        ]);

        $assessment = $this->contentService->assessmentStore(Auth::user(), $data);

        return redirect()->route('admin.content.show', $assessment->id)->with('status', 'assessment-created');
    }

    public function assessmentsEdit(int $assessment): View
    {
        return view('admin.content.assessments.edit', $this->contentService->assessmentEditFormData($assessment));
    }

    /**
     * SỬA 18/8: trước đây trang chi tiết đề (admin.content.show) chỉ để lại 1 dòng TODO
     * "danh sách câu hỏi trong đề — quản lý ở màn soạn đề của giáo viên" — nhưng đề do ADMIN
     * tạo (owner_type=shared, vd "Đề thi quốc gia") thì KHÔNG giáo viên nào sở hữu để vào màn
     * soạn đề (teacher.assessments.create chỉ cho giáo viên soạn đề CỦA CHÍNH HỌ, không có màn
     * sửa đề đã tạo), nên các đề admin tự tạo không cách nào gắn câu hỏi được — đúng lỗi anh
     * gặp ("chọn đề đâu???" ở màn Sửa đề/bộ bài). Thêm 2 route/2 hàm này để admin tự chọn câu
     * hỏi (từ toàn bộ Kho chung + kho riêng từng giáo viên — admin xem được hết) ngay tại đây.
     */
    public function assessmentsItemsEdit(Assessment $assessment): View
    {
        return view('admin.content.assessments.items', $this->contentService->assessmentItemsFormData($assessment));
    }

    public function assessmentsItemsUpdate(Request $request, Assessment $assessment): RedirectResponse
    {
        $data = $request->validate([
            'question_ids' => ['required', 'array', 'min:1'],
            'question_ids.*' => ['integer', 'exists:questions,id'],
            'points_override' => ['nullable', 'array'],
        ], [], ['question_ids' => 'Câu hỏi']);

        $this->contentService->assessmentItemsUpdate($assessment, $data);

        return redirect()->route('admin.content.show', $assessment->id)->with('status', 'assessment-updated');
    }

    public function assessmentsUpdate(Request $request, Assessment $assessment): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:practice,assignment,exam,competition_paper'],
            'total_points' => ['nullable', 'integer', 'min:0'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'publish_answer_rule' => ['required', 'string', 'in:never,after_deadline,immediately'],
        ]);

        $this->contentService->assessmentUpdate($assessment, $data);

        return redirect()->route('admin.content.show', $assessment->id)->with('status', 'assessment-updated');
    }

    public function assessmentsPublish(Assessment $assessment): RedirectResponse
    {
        $result = $this->contentService->assessmentPublish($assessment);

        if (! $result['ok']) {
            return redirect()->route('admin.content.show', $assessment->id)->withErrors(['publish' => $result['message']]);
        }

        return redirect()->route('admin.content.show', $assessment->id)->with('status', 'assessment-published');
    }

    public function assessmentsReject(Request $request, Assessment $assessment): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->contentService->assessmentReject($assessment, $data['reason']);

        return redirect()->route('admin.content.show', $assessment->id)->with('status', 'assessment-rejected');
    }

    public function assessmentsArchive(Request $request, Assessment $assessment): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->contentService->assessmentArchive($assessment, $data['reason']);

        return redirect()->route('admin.content.show', $assessment->id)->with('status', 'assessment-archived');
    }

    /**
     * SỬA 19/8 (Giai đoạn 4) — nút "Duyệt đưa vào kho chung" ở ngay bảng danh sách tab
     * "Đề/bộ bài" (admin.content.index?tab=assessments), KHÁC assessmentsPublish/Reject/
     * Archive ở trên vốn redirect về trang chi tiết 1 đề (admin.content.show) — hành động
     * này bấm thẳng từ bảng danh sách nên quay lại đúng bảng đó, không cần vào chi tiết.
     */
    public function assessmentsPromoteToShared(Assessment $assessment): RedirectResponse
    {
        $this->contentService->assessmentPromoteToShared($assessment);

        return redirect()->route('admin.content.index', ['tab' => 'assessments'])->with('status', 'assessment-promoted-shared');
    }

    // ================= "Bộ đề" — nhập hàng loạt nhiều đề PDF (Giai đoạn 3, 19/8) ==========
    // Khác assessmentsCreate/Store ở trên (tạo TỪNG đề PDF trống 1 lần) — bulk tạo NHIỀU đề
    // cùng lúc, theo 2 cách: tách từ 1 file PDF lớn theo khoảng trang, hoặc tải nhiều file PDF
    // riêng lẻ cùng lúc. Đáp án vẫn phải vào "Quản lý đề PDF" nhập tay từng đề sau khi tạo.

    public function assessmentsBulkCreate(): View
    {
        return view('admin.content.assessments.bulk', $this->contentService->assessmentBulkCreateFormData());
    }

    public function assessmentsBulkSplit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source_pdf' => ['required', 'file', 'mimes:pdf', 'max:'.ContentService::maxBulkSourcePdfKb()],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.exam_code' => ['nullable', 'string', 'max:60'],
            'rows.*.title' => ['required', 'string', 'max:255'],
            'rows.*.type' => ['required', 'string', 'in:assignment,exam,competition_paper'],
            'rows.*.from_page' => ['required', 'integer', 'min:1'],
            'rows.*.to_page' => ['required', 'integer', 'min:1', 'gte:rows.*.from_page'],
        ], [], ['source_pdf' => 'File PDF gốc']);

        $created = $this->contentService->assessmentBulkSplit(Auth::user(), $data['source_pdf'], $data['rows']);

        return redirect()->route('admin.content.index', ['tab' => 'assessments'])
            ->with('status', 'assessments-bulk-created')
            ->with('bulkCreatedCount', $created->count());
    }

    public function assessmentsBulkMulti(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'mimes:pdf', 'max:'.ContentService::maxPdfKb()],
            'meta' => ['required', 'array', 'min:1'],
            'meta.*.exam_code' => ['nullable', 'string', 'max:60'],
            'meta.*.title' => ['required', 'string', 'max:255'],
            'meta.*.type' => ['required', 'string', 'in:assignment,exam,competition_paper'],
        ]);

        $created = $this->contentService->assessmentBulkMulti(Auth::user(), $data['files'], $data['meta']);

        return redirect()->route('admin.content.index', ['tab' => 'assessments'])
            ->with('status', 'assessments-bulk-created')
            ->with('bulkCreatedCount', $created->count());
    }

    // ================= Đề PDF + phiếu đáp án (18/8, 16/8 mục 1.2/5/6) =================
    // Chỉ áp dụng cho Assessment content_mode=pdf_answer_sheet (mọi type trừ Practice — xem
    // App\Services\Admin\ContentService::contentModeForType()). Không dùng chung route/hàm
    // với assessmentsItemsEdit/Update ở trên — đó là cho content_mode=structured (gắn Question
    // rời), đề PDF không có Question nào cả.

    /** admin.content.assessments.pdf.edit — màn cấu hình PDF + đáp án + bài lập trình con. */
    public function assessmentsPdfEdit(Assessment $assessment): View
    {
        return view('admin.content.assessments.pdf', $this->contentService->assessmentPdfFormData($assessment));
    }

    /**
     * admin.content.assessments.pdf.update — lưu mã đề/phạm vi xem thử, thay file PDF/lời
     * giải nếu có tải mới, và THAY TOÀN BỘ đáp án đúng từng câu (khách chốt 16/8 mục 1.2:
     * "đáp án nhập trực tiếp trên form", KHÔNG làm nhập bằng Excel/CSV).
     */
    public function assessmentsPdfUpdate(Request $request, Assessment $assessment): RedirectResponse
    {
        $data = $request->validate([
            'exam_code' => ['nullable', 'string', 'max:60', 'unique:assessments,exam_code,'.$assessment->id],
            'preview_page_from' => ['nullable', 'integer', 'min:1'],
            'preview_page_to' => ['nullable', 'integer', 'min:1', 'gte:preview_page_from'],
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:'.ContentService::maxPdfKb()],
            'solution_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:'.ContentService::maxPdfKb()],
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

        $this->contentService->assessmentPdfUpdate(
            $assessment,
            $data,
            $answerKeyRows,
            $request->file('pdf'),
            $request->file('solution_pdf'),
        );

        return redirect()->route('admin.content.assessments.pdf.edit', $assessment->id)->with('status', 'assessment-pdf-updated');
    }

    /**
     * Chuẩn hoá $correct_answer thô từ form (mọi giá trị đều là string/array of string, HTML
     * form không tự biết kiểu) về đúng hình dạng App\Models\AssessmentAnswerKey::isCorrect()
     * mong đợi theo từng question_type — quan trọng nhất là true_false_group: input đến từ
     * checkbox qua field ẩn ("1"/"0" dạng chuỗi) phải đổi thành bool thật, nếu không phép so
     * sánh !== trong trueFalseGroupMatches() sẽ luôn sai kiểu dù đúng giá trị.
     */
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

    /** admin.content.assessments.coding-items.store — thêm 1 bài lập trình con vào đề PDF. */
    public function assessmentsCodingItemsStore(Request $request, Assessment $assessment): RedirectResponse
    {
        $data = $request->validate($this->codingItemRules(), [], ['code' => 'Mã bài']);

        $this->contentService->codingItemStore($assessment, $data);

        return redirect()->route('admin.content.assessments.pdf.edit', $assessment->id)->with('status', 'coding-item-created');
    }

    public function assessmentsCodingItemsUpdate(Request $request, AssessmentCodingItem $codingItem): RedirectResponse
    {
        $data = $request->validate($this->codingItemRules(), [], ['code' => 'Mã bài']);

        $this->contentService->codingItemUpdate($codingItem, $data);

        return redirect()->route('admin.content.assessments.pdf.edit', $codingItem->assessment_id)->with('status', 'coding-item-updated');
    }

    public function assessmentsCodingItemsDestroy(AssessmentCodingItem $codingItem): RedirectResponse
    {
        $assessmentId = $codingItem->assessment_id;
        $this->contentService->codingItemDestroy($codingItem);

        return redirect()->route('admin.content.assessments.pdf.edit', $assessmentId)->with('status', 'coding-item-deleted');
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

    /**
     * admin.content.assessments.coding-items.test-cases.import — tải gói ZIP chứa nhiều cặp
     * file input/output cho 1 bài lập trình con (16/8 mục 1.2 — không phải nhập đáp án bằng
     * Excel/CSV, chỉ là tệp kèm theo cho việc chấm code).
     */
    public function assessmentsCodingItemsTestCasesImport(Request $request, AssessmentCodingItem $codingItem): RedirectResponse
    {
        $request->validate([
            'test_cases_zip' => ['required', 'file', 'mimes:zip', 'max:'.ContentService::maxPdfKb()],
        ], [], ['test_cases_zip' => 'Gói ZIP test case']);

        $created = $this->contentService->codingItemImportTestCasesZip($codingItem, $request->file('test_cases_zip'));

        return redirect()->route('admin.content.assessments.pdf.edit', $codingItem->assessment_id)
            ->with('status', 'test-cases-imported')
            ->with('testCasesImportedCount', $created);
    }
}
