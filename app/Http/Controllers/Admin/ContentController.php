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
use App\Support\AnswerKeySheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Support\ProvinceCatalog;
use App\Support\QuestionDifficulty;
use App\Support\UploadLimit;

class ContentController extends Controller
{
    public function __construct(
        private ContentService $contentService,
        private DocumentImportService $documentImportService,
    ) {}

    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'questions');

        if ($tab === 'materials') {
            return redirect()->route('admin.content.index', ['tab' => 'questions']);
        }

        $filters = [
            'subject' => $request->query('subject') ?: null,
            'grade' => $request->query('grade') ?: null,
            // SỬA 1/10 (khách: "chỗ lọc danh sách trong admin cũng cho lọc theo tỉnh thành và
            // năm luôn nha") — xem QuestionRepository::applyQuestionBankFilters(); 'none' =
            // chưa gán.
            'province' => $request->query('province') ?: null,
            'exam_year' => $request->query('exam_year') ?: null,
            'type' => $request->query('type') ?: null,
            'status' => $request->query('status') ?: null,
            // SỬA 18/9 (khách: "admin thêm lọc theo độ khó nữa nha") — xem
            // QuestionRepository::applyDifficultyFilter(); 'none' = chưa ai đặt độ khó.
            'difficulty' => $request->query('difficulty') ?: null,
            // SỬA 30/9 (khách: "nên thêm phần lọc theo chuyên đề vào") — id tag, hoặc 'none'.
            'tag' => $request->query('tag') ?: null,
            'q' => $request->query('q') ?: null,
        ];

        return view('admin.content.index', $this->contentService->indexData($tab, $filters));
    }

    /** admin.content.show — 6.2 (chặn phát hành khi thiếu cấu hình). */
    public function show(Request $request, int $content): View
    {
        // SỬA 23/9 — 'kind' nói rõ đang xem Học liệu / Câu hỏi / Đề, vì 3 bảng đánh id riêng
        // nên chỉ có id là đoán sai loại (xem ContentService::showData()).
        return view('admin.content.show', $this->contentService->showData($content, $request->query('kind')));
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

        return redirect()->route('admin.content.show', ['content' => $material->id, 'kind' => 'material'])->with('status', 'material-published');
    }

    public function materialsReject(Request $request, Material $material): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->contentService->materialReject($material, $data['reason']);

        return redirect()->route('admin.content.show', ['content' => $material->id, 'kind' => 'material'])->with('status', 'material-rejected');
    }

    public function materialsArchive(Request $request, Material $material): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->contentService->materialArchive($material, $data['reason']);

        return redirect()->route('admin.content.show', ['content' => $material->id, 'kind' => 'material'])->with('status', 'material-archived');
    }

    public function materialsDestroy(Material $material): RedirectResponse
    {
       
        $productId = $material->product_id;

        $this->contentService->materialDelete($material);

        return redirect()->route('admin.products.show', $productId)->with('status', 'material-deleted');
    }

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

    /**
     * SỬA 1/10 (khách: "chỗ nhập thủ công đang thiếu vài trường… thiếu chọn file pdf") — luật
     * kiểm tra cho các ô MỚI ở form Tạo/Sửa câu hỏi, trước đây chỉ gói ZIP mới điền được:
     *
     *   - statement_file / solution_file / reference_file: 3 tệp đính kèm cố định. 'statement'
     *     là tệp QUAN TRỌNG NHẤT — nó nuôi tab "Đề bài PDF" của học sinh lúc làm bài (xem
     *     Question::attachmentInfo() + Student\AssessmentService), thiếu nó thì học sinh chỉ
     *     thấy phần đề gõ tay.
     *   - remove_attachments[] / remove_assets[]: ô bỏ chọn tệp đang có ở form Sửa.
     *   - asset_files[]: ảnh/âm thanh cần để trả lời (Question::findAsset()).
     *   - file_io_input / file_io_output: quy ước tên tệp vào/ra của câu Lập trình. Khớp ĐÚNG
     *     regex mà CodeJudgingService::withFileIo() dùng để lọc tên tệp — sai regex ở đây thì
     *     tên lưu được nhưng máy chấm lặng lẽ bỏ qua.
     *
     * 3 action Tạo / Sửa / Tạo phiên bản mới dùng chung bộ luật này để không chỗ nào thiếu.
     */
    private function questionUploadRules(): array
    {
        return [
            'statement_file' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'solution_file' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'reference_file' => ['nullable', 'file', 'max:2048', 'extensions:cpp,cc,c,py,pas,java,js,ts,txt,md'],
            'remove_attachments' => ['nullable', 'array'],
            'remove_attachments.*' => ['string', 'in:statement,solution,reference'],
            'asset_files' => ['nullable', 'array', 'max:20'],
            'asset_files.*' => ['file', 'max:20480', 'mimetypes:image/jpeg,image/png,image/gif,image/webp,audio/mpeg,audio/mp4,audio/aac,audio/ogg,audio/wav,audio/x-wav,audio/webm'],
            'remove_assets' => ['nullable', 'array'],
            'remove_assets.*' => ['string', 'max:64'],
            'file_io_input' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'file_io_output' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
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
            // SỬA 18/9 — ô "Độ khó" mới ở form câu hỏi. Rỗng = chưa đặt (hệ thống tự suy
            // theo điểm), 4 khoá còn lại khớp đúng bộ lọc ngoài trang Luyện tập.
            'difficulty' => ['nullable', 'string', QuestionDifficulty::validationRule()],
            'title' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:20'],
            'grade' => ['nullable', 'integer', 'min:6', 'max:12'],
            // SỬA 1/10 — Tỉnh thành (MÃ trong App\Support\ProvinceCatalog) + Năm của đề. Cố ý
            // KHÔNG dùng 'in:...' danh sách mã: ContentService chuẩn hoá lại qua
            // ProvinceCatalog::normalize()/normalizeYear(), mã lạ thành null = "Chưa gán" thay vì
            // chặn cả form chỉ vì 1 ô phân loại tuỳ chọn.
            'province' => ['nullable', 'string', 'max:20'],
            'exam_year' => ['nullable', 'integer', 'min:'.ProvinceCatalog::MIN_YEAR, 'max:'.((int) date('Y') + 1)],
            'body' => ['nullable', 'string'],
            'points' => ['nullable', 'integer', 'min:0'],
            // SỬA 30/9 — "thứ tự ưu tiên hiển thị": số càng lớn càng hiện trước, 0 = bình thường.
            'display_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'visibility' => ['required', 'string', 'in:public,private'],
        ], $this->questionGradingRules(), $this->questionUploadRules(), $this->tagRules()));

        $question = $this->contentService->questionStore(Auth::user(), $data);

        return redirect()->route('admin.content.show', ['content' => $question->id, 'kind' => 'question'])->with('status', 'question-created');
    }

    public function questionsEdit(int $question): View
    {
        return view('admin.content.questions.edit', $this->contentService->questionEditFormData($question));
    }

    public function questionsUpdate(Request $request, Question $question): RedirectResponse
    {
        $data = $request->validate(array_merge([
            'code' => ['required', 'string', 'max:40'],
            // SỬA 18/9 — Độ khó sửa được ở màn Sửa, không chỉ lúc tạo.
            'difficulty' => ['nullable', 'string', QuestionDifficulty::validationRule()],
            'title' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:20'],
            'grade' => ['nullable', 'integer', 'min:6', 'max:12'],
            // SỬA 1/10 — Tỉnh thành (MÃ trong App\Support\ProvinceCatalog) + Năm của đề. Cố ý
            // KHÔNG dùng 'in:...' danh sách mã: ContentService chuẩn hoá lại qua
            // ProvinceCatalog::normalize()/normalizeYear(), mã lạ thành null = "Chưa gán" thay vì
            // chặn cả form chỉ vì 1 ô phân loại tuỳ chọn.
            'province' => ['nullable', 'string', 'max:20'],
            'exam_year' => ['nullable', 'integer', 'min:'.ProvinceCatalog::MIN_YEAR, 'max:'.((int) date('Y') + 1)],
            'body' => ['nullable', 'string'],
            'points' => ['nullable', 'integer', 'min:0'],
            // SỬA 30/9 — "thứ tự ưu tiên hiển thị": số càng lớn càng hiện trước, 0 = bình thường.
            'display_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'visibility' => ['required', 'string', 'in:public,private'],
        ], $this->questionGradingRules(), $this->questionUploadRules(), $this->tagRules()));

        $this->contentService->questionUpdate($question, $data);

        return redirect()->route('admin.content.show', ['content' => $question->id, 'kind' => 'question'])->with('status', 'question-updated');
    }

    /** admin.content.questions.newVersion — 6.2: câu đã có người làm phải tạo version mới. */
    public function questionsNewVersion(Request $request, Question $question): RedirectResponse
    {
        $data = $request->validate(array_merge([
            'title' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:20'],
            'grade' => ['nullable', 'integer', 'min:6', 'max:12'],
            // SỬA 1/10 — Tỉnh thành (MÃ trong App\Support\ProvinceCatalog) + Năm của đề. Cố ý
            // KHÔNG dùng 'in:...' danh sách mã: ContentService chuẩn hoá lại qua
            // ProvinceCatalog::normalize()/normalizeYear(), mã lạ thành null = "Chưa gán" thay vì
            // chặn cả form chỉ vì 1 ô phân loại tuỳ chọn.
            'province' => ['nullable', 'string', 'max:20'],
            'exam_year' => ['nullable', 'integer', 'min:'.ProvinceCatalog::MIN_YEAR, 'max:'.((int) date('Y') + 1)],
            'body' => ['nullable', 'string'],
            'points' => ['nullable', 'integer', 'min:0'],
            // SỬA 30/9 — "thứ tự ưu tiên hiển thị": số càng lớn càng hiện trước, 0 = bình thường.
            'display_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'visibility' => ['required', 'string', 'in:public,private'],
            // SỬA 18/9 — PHẢI có ở đây nữa: form "Tạo phiên bản mới" dùng CHUNG màn Sửa (có ô
            // Độ khó). Thiếu luật này thì giá trị gửi lên bị validate() loại bỏ, và
            // questionCreateNewVersion() hiểu là "bỏ trống" -> XOÁ mất độ khó của bản mới.
            'difficulty' => ['nullable', 'string', QuestionDifficulty::validationRule()],
        ], $this->questionGradingRules(), $this->questionUploadRules(), $this->tagRules()));

        $newQuestion = $this->contentService->questionCreateNewVersion($question, $data);

        return redirect()->route('admin.content.show', ['content' => $newQuestion->id, 'kind' => 'question'])->with('status', 'question-versioned');
    }

    public function questionsPublish(Question $question): RedirectResponse
    {
        $result = $this->contentService->questionPublish($question);

        if (! $result['ok']) {
            return redirect()->route('admin.content.show', ['content' => $question->id, 'kind' => 'question'])->withErrors(['publish' => $result['message']]);
        }

        return redirect()->route('admin.content.show', ['content' => $question->id, 'kind' => 'question'])->with('status', 'question-published');
    }

    public function questionsReject(Request $request, Question $question): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->contentService->questionReject($question, $data['reason']);

        return redirect()->route('admin.content.show', ['content' => $question->id, 'kind' => 'question'])->with('status', 'question-rejected');
    }

    public function questionsArchive(Request $request, Question $question): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->contentService->questionArchive($question, $data['reason']);

        return redirect()->route('admin.content.show', ['content' => $question->id, 'kind' => 'question'])->with('status', 'question-archived');
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
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'publish_answer_rule' => ['required', 'string', 'in:never,after_deadline,immediately'],
            // SỬA 2/10 — 6 trường mô tả đề cho bản mẫu UI mới. 'province'/'exam_category' cố ý
            // KHÔNG dùng 'in:...' danh sách mã: ContentService chuẩn hoá lại qua catalog, mã lạ
            // thành null = "chưa gán" thay vì chặn cả form chỉ vì một ô phân loại tuỳ chọn.
            'subtitle' => ['nullable', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:20'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'exam_category' => ['nullable', 'string', 'max:30'],
            // SỬA 2/10 lần 3 — BẢN XEM TRƯỚC: một tệp PDF do người ra đề tải lên. Không nhận
            // preview_page_from/to ở form này (đó là của màn "Quản lý đề PDF") — xem ghi chú
            // trong partials/assessment-detail-fields.
            // max lấy theo giới hạn THẬT của máy chủ (upload_max_filesize/post_max_size), không
            // ghi cứng — xem App\Support\UploadLimit.
            'preview_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:'.UploadLimit::maxKilobytes()],
            'remove_preview_pdf' => ['nullable', 'boolean'],
            'cover' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:'.UploadLimit::maxKilobytes(4096)],
            'remove_cover' => ['nullable', 'boolean'],
        ]);

        $assessment = $this->contentService->assessmentStore(Auth::user(), $data, $request->file('cover'));

        return redirect()->route('admin.content.show', ['content' => $assessment->id, 'kind' => 'assessment'])->with('status', 'assessment-created');
    }

    public function assessmentsEdit(int $assessment): View
    {
        return view('admin.content.assessments.edit', $this->contentService->assessmentEditFormData($assessment));
    }

    public function assessmentsItemsEdit(Assessment $assessment): View
    {
        return view('admin.content.assessments.items', $this->contentService->assessmentItemsFormData($assessment));
    }

    public function assessmentsItemsUpdate(Request $request, Assessment $assessment): RedirectResponse
    {
        $data = $request->validate([
            'question_ids' => ['required', 'array', 'min:1'],
            'question_ids.*' => ['integer', 'exists:questions,id'],
            // SỬA 1/10 (khách: "đừng cho nhập nhé mà tự động active điểm của các câu theo độ
            // khó của câu đó") — ĐÃ BỎ HẲN 'points_override' khỏi đây: form không còn ô nhập, và
            // điểm được tính ở server từ độ khó (ContentService::assessmentItemsUpdate ->
            // QuestionDifficulty::pointsForQuestion). Bỏ luật này nghĩa là dù ai có tự gửi
            // points_override lên thì validate() cũng loại bỏ, không có đường nào đặt điểm tay.
        ], [], ['question_ids' => 'Câu hỏi']);

        $this->contentService->assessmentItemsUpdate($assessment, $data);

        return redirect()->route('admin.content.show', ['content' => $assessment->id, 'kind' => 'assessment'])->with('status', 'assessment-updated');
    }

    public function assessmentsUpdate(Request $request, Assessment $assessment): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:practice,assignment,exam,competition_paper'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'publish_answer_rule' => ['required', 'string', 'in:never,after_deadline,immediately'],
            // SỬA 2/10 — 6 trường mô tả đề cho bản mẫu UI mới. 'province'/'exam_category' cố ý
            // KHÔNG dùng 'in:...' danh sách mã: ContentService chuẩn hoá lại qua catalog, mã lạ
            // thành null = "chưa gán" thay vì chặn cả form chỉ vì một ô phân loại tuỳ chọn.
            'subtitle' => ['nullable', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:20'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'exam_category' => ['nullable', 'string', 'max:30'],
            // SỬA 2/10 lần 3 — BẢN XEM TRƯỚC: một tệp PDF do người ra đề tải lên. Không nhận
            // preview_page_from/to ở form này (đó là của màn "Quản lý đề PDF") — xem ghi chú
            // trong partials/assessment-detail-fields.
            // max lấy theo giới hạn THẬT của máy chủ (upload_max_filesize/post_max_size), không
            // ghi cứng — xem App\Support\UploadLimit.
            'preview_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:'.UploadLimit::maxKilobytes()],
            'remove_preview_pdf' => ['nullable', 'boolean'],
            'cover' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:'.UploadLimit::maxKilobytes(4096)],
            'remove_cover' => ['nullable', 'boolean'],
        ]);

        $this->contentService->assessmentUpdate($assessment, $data, $request->file('cover'), $request->boolean('remove_cover'));

        return redirect()->route('admin.content.show', ['content' => $assessment->id, 'kind' => 'assessment'])->with('status', 'assessment-updated');
    }

    public function assessmentsPublish(Assessment $assessment): RedirectResponse
    {
        $result = $this->contentService->assessmentPublish($assessment);

        if (! $result['ok']) {
            return redirect()->route('admin.content.show', ['content' => $assessment->id, 'kind' => 'assessment'])->withErrors(['publish' => $result['message']]);
        }

        return redirect()->route('admin.content.show', ['content' => $assessment->id, 'kind' => 'assessment'])->with('status', 'assessment-published');
    }

    public function assessmentsReject(Request $request, Assessment $assessment): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->contentService->assessmentReject($assessment, $data['reason']);

        return redirect()->route('admin.content.show', ['content' => $assessment->id, 'kind' => 'assessment'])->with('status', 'assessment-rejected');
    }

    public function assessmentsArchive(Request $request, Assessment $assessment): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->contentService->assessmentArchive($assessment, $data['reason']);

        return redirect()->route('admin.content.show', ['content' => $assessment->id, 'kind' => 'assessment'])->with('status', 'assessment-archived');
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
            'answer_keys.*.question_type' => ['required_with:answer_keys', 'string', 'in:single_choice,true_false,true_false_group,short_answer,multi_part'],
            'answer_keys.*.correct_answer' => ['required_with:answer_keys'],
            'answer_keys.*.points' => ['nullable', 'integer', 'min:0'],
        ], [], [
            'exam_code' => 'Mã đề',
            'pdf' => 'Tệp PDF đề',
            'solution_pdf' => 'Tệp PDF lời giải',
        ]);

        // SỬA 9/9 (3) — LỖI CŨ (khách báo: "dạng đúng/sai, đúng sai 4 ý, câu nhiều ý không lưu
        // được"): chỗ này viết `$row + [...]`. Toán tử "+" của PHP CHỈ thêm khoá còn THIẾU, không
        // ghi đè khoá đã có — mà $row đã có sẵn 'correct_answer' từ form, nên giá trị đã chuẩn hoá
        // bị vứt đi, thứ lưu xuống là giá trị thô của form:
        //   · Đúng/Sai 4 ý  -> lưu ["a"=>"1","b"=>"0"…] (chuỗi) thay vì true/false: mở lại thấy cả
        //     4 ý đều "Đúng" (chuỗi "0" vẫn là truthy) và chấm bài không bao giờ khớp;
        //   · Câu nhiều ý   -> lưu nguyên chuỗi "a:A-b:Đ-c:123" thay vì cấu trúc từng ý: mở lại ô
        //     nhập trống trơn;
        //   · Đúng/Sai      -> lưu chuỗi "1"/"0" thay vì bool.
        // Trắc nghiệm và Trả lời ngắn không lộ lỗi vì giá trị thô vốn đã đúng dạng cần lưu.
        // array_replace() ghi đè đúng khoá 'correct_answer'.
        $answerKeyRows = array_map(
            fn (array $row) => array_replace($row, [
                'correct_answer' => AnswerKeySheet::normalizeFormAnswer($row['question_type'], $row['correct_answer']),
            ]),
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
            // SỬA 30/9 — "thứ tự ưu tiên hiển thị": số càng lớn càng hiện trước, 0 = bình thường.
            'display_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
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
