<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\Teacher\QuestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuestionController extends Controller
{
    public function __construct(private readonly QuestionService $questionService) {}

    public function index(Request $request): View
    {
        $user = Auth::user();
        $tab = $request->query('tab', 'all');

        // SỬA 18/9 (khách: "kho câu hỏi của tôi bên giáo viên hiển thêm phần lọc cho đầy đủ như
        // admin") — đọc bộ lọc y hệt Admin\ContentController::index(). Chuỗi rỗng -> null để
        // "Tất cả …" không bị hiểu nhầm thành 1 điều kiện lọc thật.
        $filters = [
            'subject' => $request->query('subject') ?: null,
            'grade' => $request->query('grade') ?: null,
            'type' => $request->query('type') ?: null,
            'status' => $request->query('status') ?: null,
            'difficulty' => $request->query('difficulty') ?: null,
            'q' => $request->query('q') ?: null,
        ];

        return view('teacher.questions.index', $this->questionService->listForTeacher($user, $tab, $filters));
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

                return redirect()->route('teacher.questions.index')->with('status', 'question-published');
            } catch (ValidationException $e) {
                return redirect()->route('teacher.questions.edit', $question->id)->withErrors($e->errors())
                    ->with('status', $createdStatus.'-draft-only');
            }
        }

        return redirect()->route('teacher.questions.index')->with('status', $createdStatus);
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
            'difficulty' => ['nullable', 'string', 'in:easy,medium,hard,expert'],
            'title' => ['required', 'string', 'max:255'],
            // SỬA 8/9 (3) ("phân loại kho câu hỏi theo môn") — cả 2 đều tuỳ chọn; giá trị được
            // chuẩn hoá lại ở Teacher\QuestionService::buildAttributes() qua SubjectCatalog.
            'subject' => ['nullable', 'string', 'max:20'],
            'grade' => ['nullable', 'integer', 'min:6', 'max:12'],
            'body' => ['required', 'string'],
            'points' => ['required', 'integer', 'min:1', 'max:100'],
            'action' => ['required', 'in:draft,publish'],
            // SỬA 19/8 (Giai đoạn 6 — "Gắn tag/chủ đề cho câu hỏi"): xem
            // Teacher\QuestionService::resolveTagIds() — tag_ids là ID có sẵn (tick), new_tags
            // là tên tag mới gõ tay (cách nhau bằng dấu phẩy), cả 2 đều tùy chọn.
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer'],
            'new_tags' => ['nullable', 'string', 'max:500'],
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
