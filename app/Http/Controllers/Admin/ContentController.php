<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Material;
use App\Models\Question;
use App\Services\Admin\ContentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ContentController extends Controller
{
    public function __construct(private ContentService $contentService) {}

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
