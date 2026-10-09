<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\Teacher\AssessmentService;
use App\Services\Teacher\QuestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Support\ProvinceCatalog;
use App\Support\QuestionDifficulty;

class QuestionController extends Controller
{
    public function __construct(
        private readonly QuestionService $questionService,
        private readonly AssessmentService $assessmentService,
    ) {}

    public function index(Request $request): View
    {
        $user = Auth::user();
        $tab = (string) $request->query('tab', 'unused');

        // SỬA 18/9 (khách: "kho câu hỏi của tôi bên giáo viên hiển thêm phần lọc cho đầy đủ như
        // admin") — đọc bộ lọc y hệt Admin\ContentController::index(). Chuỗi rỗng -> null để
        // "Tất cả …" không bị hiểu nhầm thành 1 điều kiện lọc thật.
        $filters = [
            'subject' => $request->query('subject') ?: null,
            'grade' => $request->query('grade') ?: null,
            'type' => $request->query('type') ?: null,
            // SỬA 1/10 (khách: "chỗ lọc danh sách trong admin cũng cho lọc theo tỉnh thành và
            // năm luôn nha. Giáo viên cũng tương tự") — xem
            // QuestionRepository::applyQuestionBankFilters(); 'none' = chưa gán.
            'province' => $request->query('province') ?: null,
            'exam_year' => $request->query('exam_year') ?: null,
            'q' => $request->query('q') ?: null,
        ];

        // SỬA 7/10 — tab "Đề/bộ bài" của màn gộp "Kho bài tập / câu hỏi và đề" (thay cho trang
        // "Đề PDF của tôi" cũ). Luôn lấy danh sách để đếm số đề in lên tab; chỉ đưa ra view khi
        // đang đứng ở tab đó.
        $papers = $this->assessmentService->papersForTeacher($user)['papers'];

        $data = $this->questionService->listForTeacher($user, $tab, $filters, count($papers));
        $data['papers'] = $data['tab'] === 'assessments' ? $papers : [];

        return view('teacher.questions.index', $data);
    }

    public function create(Request $request): View
    {
        $type = $request->query('type', 'mcq');

        return view('teacher.questions.create', ['type' => $type, 'question' => null, 'allTags' => $this->questionService->allTags(), 'subjects' => \App\Support\SubjectCatalog::SUBJECTS, 'grades' => \App\Support\SubjectCatalog::GRADES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $type = $request->input('type', 'mcq');
        $data = $request->validate($this->validationRules($type));
        $data['test_cases_parsed'] = $this->parseTestCases($request->input('test_cases', ''));

        $question = $this->questionService->store(Auth::user(), $data);

        return $this->finishSubmit($question, $data['action'], 'question-created');
    }

    public function edit(Request $request, int $question): View
    {
        $questionModel = $this->questionService->findOwned(Auth::user(), $question);
        $questionModel->load('tags');

        return view('teacher.questions.create', ['type' => $questionModel->type->value, 'question' => $questionModel, 'allTags' => $this->questionService->allTags(), 'subjects' => \App\Support\SubjectCatalog::SUBJECTS, 'grades' => \App\Support\SubjectCatalog::GRADES]);
    }

    public function update(Request $request, int $question): RedirectResponse
    {
        $questionModel = $this->questionService->findOwned(Auth::user(), $question);
        $type = $request->input('type', $questionModel->type->value);
        $data = $request->validate($this->validationRules($type));
        $data['test_cases_parsed'] = $this->parseTestCases($request->input('test_cases', ''));

        $updated = $this->questionService->update($questionModel, $data);

        return $this->finishSubmit($updated, $data['action'], 'question-updated');
    }

    public function publish(Request $request, int $question): RedirectResponse
    {
        $questionModel = $this->questionService->findOwned(Auth::user(), $question);

        try {
            $this->questionService->publish($questionModel);
        } catch (ValidationException $e) {
            return redirect()->route('teacher.questions.edit', $questionModel->id)->withErrors($e->errors());
        }

        return redirect()->route('teacher.questions.index')->with('status', 'question-published');
    }

    public function archive(Request $request, int $question): RedirectResponse
    {
        $questionModel = $this->questionService->findOwned(Auth::user(), $question);
        $this->questionService->archive($questionModel);

        return redirect()->route('teacher.questions.index')->with('status', 'question-archived');
    }

    // ================= "Nhập từ gói ZIP" (24/8, mở rộng mọi loại câu 18/9) =================
    // Xem App\Services\Teacher\QuestionService::storeFromZipPackage() — KHÔNG đụng gì tới
    // create/store nhập tay ở trên.

    /** teacher.questions.zipImport — tải 1 gói ZIP OT360-QPACK, tự điền, redirect sang Sửa. */
    public function zipImportStore(Request $request): RedirectResponse
    {
        $request->validate([
            'zip_package' => ['required', 'file', 'mimes:zip', 'max:'.QuestionService::maxZipPackageKb()],
        ], [], ['zip_package' => 'Gói ZIP']);

        try {
            $question = $this->questionService->storeFromZipPackage(Auth::user(), $request->file('zip_package'));
        } catch (ValidationException $e) {
            // SỬA 18/9 — quay lại ĐÚNG loại câu giáo viên đang đứng khi gói ZIP lỗi (trước đây
            // luôn ép về 'coding' vì ô tải ZIP chỉ hiện ở loại Lập trình, giờ hiện ở mọi loại).
            $returnType = $request->input('return_type');
            $returnType = in_array($returnType, ['mcq', 'fill_blank', 'coding'], true) ? $returnType : 'coding';

            return redirect()->route('teacher.questions.create', ['type' => $returnType])->withErrors($e->errors());
        }

        return redirect()->route('teacher.questions.edit', $question->id)->with('status', 'question-zip-imported');
    }

    /** teacher.questions.attachment — tải lại 1 tệp đính kèm (đề/lời giải/code mẫu) đã nhập từ ZIP. */
    public function attachmentDownload(Request $request, int $question, string $kind): StreamedResponse
    {
        $info = $this->questionService->attachmentInfo(Auth::user(), $question, $kind);

        return Storage::disk('local')->download($info['path'], $info['filename']);
    }

    private function finishSubmit($question, string $action, string $createdStatus): RedirectResponse
    {
        if ($action === 'publish') {
            try {
                $this->questionService->publish($question);

                return redirect()->route('teacher.questions.index', $this->listTabFor($createdStatus))->with('status', 'question-published');
            } catch (ValidationException $e) {
                return redirect()->route('teacher.questions.edit', $question->id)->withErrors($e->errors())
                    ->with('status', $createdStatus.'-draft-only');
            }
        }

        return redirect()->route('teacher.questions.index', $this->listTabFor($createdStatus))->with('status', $createdStatus);
    }

    /**
     * SỬA 7/10 — câu hỏi VỪA TẠO chưa nằm trong đề nào, nên quay về tab "Chưa dùng trong đề" để
     * giáo viên thấy ngay câu mình vừa lưu (tab mặc định là "Đã dùng trong đề", giống admin).
     */
    private function listTabFor(string $createdStatus): array
    {
        return $createdStatus === 'question-created' ? ['tab' => 'unused'] : [];
    }

    /** Mỗi dòng "input=>output"; dòng rỗng/thiếu dấu phân cách bị bỏ qua (6.2: test phải hợp lệ). */
    private function parseTestCases(string $raw): array
    {
        $cases = [];
        foreach (preg_split('/\r?\n/', trim($raw)) as $line) {
            $line = trim($line);
            if ($line === '' || ! str_contains($line, '=>')) {
                continue;
            }
            [$input, $output] = explode('=>', $line, 2);
            $cases[] = ['input' => trim($input), 'output' => trim($output)];
        }

        return $cases;
    }

    private function validationRules(string $type): array
    {
        $common = [
            // SỬA 18/9 — thêm 'composite': câu Nhiều phần nhập từ gói ZIP không có form nhập
            // tay, nhưng vẫn phải MỞ được trang Sửa để đổi Tiêu đề/Nội dung/Điểm/Độ khó/Tag —
            // không có 'composite' ở đây thì mọi lần Lưu đều bị chặn "type không hợp lệ".
            'type' => ['required', 'in:mcq,fill_blank,coding,composite'],
            // SỬA 18/9 — ô "Độ khó" mới ở form câu hỏi. Rỗng = chưa đặt (hệ thống tự suy
            // theo điểm), 4 khoá còn lại khớp đúng bộ lọc ngoài trang Luyện tập.
            'difficulty' => ['nullable', 'string', QuestionDifficulty::validationRule()],
            'title' => ['required', 'string', 'max:255'],
            // SỬA 8/9 (3) ("phân loại kho câu hỏi theo môn") — cả 2 đều tuỳ chọn; giá trị được
            // chuẩn hoá lại ở Teacher\QuestionService::buildAttributes() qua SubjectCatalog.
            'subject' => ['nullable', 'string', 'max:20'],
            // SỬA 3/10 (khách: "chỗ chọn khối lớp thì cho chọn nhiều nha") — ô khối lớp giờ là
            // các ô tick, gửi lên mảng 'grades'. Vẫn nhận 'grade' (1 số) cho các chỗ gọi cũ
            // (nhập ZIP, tự phân loại) — xem SubjectCatalog::normalizeGrades().
            'grades' => ['nullable', 'array', 'max:12'],
            'grades.*' => ['integer', 'min:6', 'max:12'],
            'grade' => ['nullable', 'integer', 'min:6', 'max:12'],
            // SỬA 1/10 (khách: "thêm 1 cái field nữa cho chọn tỉnh thành và năm… Giáo viên cũng
            // tương tự nhé") — Tỉnh thành (MÃ trong App\Support\ProvinceCatalog) + Năm của đề.
            // KHÔNG dùng 'in:...' danh sách mã: QuestionService chuẩn hoá lại qua
            // ProvinceCatalog::normalize()/normalizeYear(), mã lạ thành null = "Chưa gán" thay vì
            // chặn cả form chỉ vì 1 ô phân loại tuỳ chọn.
            'province' => ['nullable', 'string', 'max:20'],
            'exam_year' => ['nullable', 'integer', 'min:'.ProvinceCatalog::MIN_YEAR, 'max:'.((int) date('Y') + 1)],
            // SỬA 1/10 (khách: "bên giáo viên cũng update giúp tôi luôn") — ô "Nội dung đề bài"
            // đang ẩn ở form (đề bài nhập bằng tệp PDF), nên KHÔNG còn 'required': để 'required'
            // thì mọi lần Lưu đều bị chặn "Nội dung đề bài là bắt buộc". Teacher\QuestionService
            // ::buildAttributes() chỉ ghi 'body' khi form CÓ gửi ô đó, nên đề bài cũ không mất.
            'body' => ['nullable', 'string'],
            // SỬA 1/10 — ô Điểm giờ là readonly, giá trị thật được TÍNH LẠI ở
            // QuestionService::buildAttributes() theo Độ khó (QuestionDifficulty::POINTS), nên
            // luật ở đây chỉ còn để chặn rác. Bỏ 'required': ô readonly vẫn gửi lên bình thường,
            // nhưng không đáng để cả form bị chặn nếu trình duyệt/tiện ích nào đó bỏ qua nó.
            'points' => ['nullable', 'integer', 'min:0', 'max:100'],
            // SỬA 30/9 — "thứ tự ưu tiên hiển thị": số càng lớn càng hiện trước, 0 = bình thường.
            'display_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'action' => ['required', 'in:draft,publish'],
            // SỬA 19/8 (Giai đoạn 6 — "Gắn tag/chủ đề cho câu hỏi"): xem
            // Teacher\QuestionService::resolveTagIds() — tag_ids là ID có sẵn (tick), new_tags
            // là tên tag mới gõ tay (cách nhau bằng dấu phẩy), cả 2 đều tùy chọn.
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer'],
            'new_tags' => ['nullable', 'string', 'max:500'],
            /*
             * SỬA 1/10 — các ô tệp MỚI, giống hệt bên Kho chung của admin (xem
             * Admin\ContentController::questionUploadRules()):
             *   - statement_file: tệp PDF đề bài. QUAN TRỌNG NHẤT — nuôi tab "Đề bài" của học
             *     sinh lúc làm bài (Question::attachmentInfo('statement')); trước đây chỉ gói
             *     ZIP tạo được nên câu giáo viên gõ tay không bao giờ có.
             *   - solution_file / reference_file: lời giải PDF + code mẫu, chỉ giáo viên tải.
             *   - asset_files[]: ảnh/âm thanh cần để trả lời (Question::findAsset()).
             *   - remove_attachments[] / remove_assets[]: ô bỏ tệp đang có.
             *   - file_io_input / file_io_output: tên tệp vào/ra của câu Lập trình; regex khớp
             *     ĐÚNG CodeJudgingService::withFileIo() để không có tên nào "lưu được mà máy
             *     chấm lặng lẽ bỏ qua".
             */
            'statement_file' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'solution_file' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'reference_file' => ['nullable', 'file', 'max:2048', 'extensions:cpp,cc,c,py,pas,java,js,ts,txt,md'],
            'remove_attachments' => ['nullable', 'array'],
            'remove_attachments.*' => ['string', 'in:statement,solution,reference'],
            'asset_files' => ['nullable', 'array', 'max:20'],
            'asset_files.*' => ['file', 'max:20480', 'mimetypes:image/jpeg,image/png,image/gif,image/webp,audio/mpeg,audio/mp4,audio/aac,audio/ogg,audio/wav,audio/x-wav,audio/webm'],
            'remove_assets' => ['nullable', 'array'],
            'remove_assets.*' => ['string', 'max:64'],
            'io_mode' => ['nullable', 'in:std,file'],
            'file_io_input' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'file_io_output' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
        ];

        return match ($type) {
            // Composite: KHÔNG có trường cấu hình chấm nào trên form (giữ nguyên theo gói ZIP),
            // xem Teacher\QuestionService::buildAttributes().
            'composite' => $common,
            // SỬA 19/8 — correct_option PHẢI là chỉ số (0-3), không phải chữ cái — xem sửa lỗi
            // chấm điểm ở QuestionService::buildGradingConfig() + create.blade.php (khớp
            // đúng validation Admin\ContentController đã dùng từ đầu: 'integer','min:0','max:3').
            'mcq' => $common + [
                'options' => ['nullable', 'array'],
                'options.*' => ['nullable', 'string', 'max:500'],
                'correct_option' => ['nullable', 'integer', 'min:0', 'max:3'],
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
}
