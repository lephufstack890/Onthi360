<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\Teacher\QuestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuestionController extends Controller
{
    public function __construct(private readonly QuestionService $questionService) {}

    /** teacher.questions.index (TEA-03) — kho riêng của giáo viên (6.5). */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $tab = $request->query('tab', 'all');

        return view('teacher.questions.index', $this->questionService->listForTeacher($user, $tab));
    }

    /** teacher.questions.create (TEA-04) — tạo câu hỏi mới theo loại (MCQ/điền đáp án/lập trình). */
    public function create(Request $request): View
    {
        $type = $request->query('type', 'mcq');

        return view('teacher.questions.create', ['type' => $type, 'question' => null]);
    }

    /** teacher.questions.store — lưu nháp luôn được phép; "Phát hành" phải qua QuestionPublishGuard (6.2). */
    public function store(Request $request): RedirectResponse
    {
        $type = $request->input('type', 'mcq');
        $data = $request->validate($this->validationRules($type));
        $data['test_cases_parsed'] = $this->parseTestCases($request->input('test_cases', ''));

        $question = $this->questionService->store(Auth::user(), $data);

        return $this->finishSubmit($question, $data['action'], 'question-created');
    }

    /** teacher.questions.edit — chỉ chủ sở hữu (6.5). */
    public function edit(Request $request, int $question): View
    {
        $questionModel = $this->questionService->findOwned(Auth::user(), $question);

        return view('teacher.questions.create', ['type' => $questionModel->type->value, 'question' => $questionModel]);
    }

    /**
     * teacher.questions.update — câu đã có người làm thì tự động tạo phiên bản mới thay vì
     * sửa âm thầm (6.2), xem App\Services\Teacher\QuestionService::update().
     */
    public function update(Request $request, int $question): RedirectResponse
    {
        $questionModel = $this->questionService->findOwned(Auth::user(), $question);
        $type = $request->input('type', $questionModel->type->value);
        $data = $request->validate($this->validationRules($type));
        $data['test_cases_parsed'] = $this->parseTestCases($request->input('test_cases', ''));

        $updated = $this->questionService->update($questionModel, $data);

        return $this->finishSubmit($updated, $data['action'], 'question-updated');
    }

    /** teacher.questions.publish (6.2) — phát hành trực tiếp từ danh sách (câu đã đủ điều kiện). */
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

    /** teacher.questions.archive — gỡ khỏi lưu hành, không xóa (Table 27). */
    public function archive(Request $request, int $question): RedirectResponse
    {
        $questionModel = $this->questionService->findOwned(Auth::user(), $question);
        $this->questionService->archive($questionModel);

        return redirect()->route('teacher.questions.index')->with('status', 'question-archived');
    }

    /** Sau khi lưu (tạo/sửa): nếu bấm "Phát hành" mà chưa đủ điều kiện, quay lại form với lỗi cụ thể (6.4). */
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
            'type' => ['required', 'in:mcq,fill_blank,coding'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'points' => ['required', 'integer', 'min:1', 'max:100'],
            'action' => ['required', 'in:draft,publish'],
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
}
