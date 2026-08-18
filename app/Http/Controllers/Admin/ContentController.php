<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UploadedDocumentStatus;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Material;
use App\Models\Question;
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
        $tab = $request->query('tab', 'materials');

        return view('admin.content.index', $this->contentService->indexData($tab));
    }

    /** admin.content.show — 6.2 (chặn phát hành khi thiếu cấu hình). */
    public function show(Request $request, int $content): View
    {
        return view('admin.content.show', $this->contentService->showData($content));
    }

    // ================= Học liệu (Material) =================

    public function materialsCreate(): View
    {
        return view('admin.content.materials.create', $this->contentService->materialCreateFormData());
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
        ]);

        $material = $this->contentService->materialStore($data);

        return redirect()->route('admin.content.show', $material->id)->with('status', 'material-created');
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
        ]);

        $this->contentService->materialUpdate($material, $data);

        return redirect()->route('admin.content.show', $material->id)->with('status', 'material-updated');
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

    public function questionsStore(Request $request): RedirectResponse
    {
        $data = $request->validate(array_merge([
            'code' => ['required', 'string', 'max:40'],
            'type' => ['required', 'string', 'in:coding,mcq,fill_blank'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'points' => ['nullable', 'integer', 'min:0'],
            'visibility' => ['required', 'string', 'in:public,private'],
        ], $this->questionGradingRules()));

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
        ], $this->questionGradingRules()));

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
        ], $this->questionGradingRules()));

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
        $this->contentService->assessmentPublish($assessment);

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
}
