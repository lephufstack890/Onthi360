<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Services\Admin\CompetitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompetitionController extends Controller
{
    public function __construct(private CompetitionService $competitionService) {}

    /** admin.competitions.index (ADM-05) — 11.1: vòng đời cuộc thi. */
    public function index(Request $request): View
    {
        return view('admin.competitions.index', $this->competitionService->indexData());
    }

    private function validationRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:contest,survey'],
            'assessment_id' => ['nullable', 'integer', 'exists:assessments,id'],
            'rules' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'publish_result_at' => ['nullable', 'date'],
            'status' => ['required', 'string', 'in:upcoming,ongoing,pending_publish,published,archived'],
            'scoring_note' => ['nullable', 'string', 'max:500'],
            'penalty_note' => ['nullable', 'string', 'max:500'],
            'tie_break_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function create(): View
    {
        return view('admin.competitions.create', $this->competitionService->createFormData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->validationRules());
        $competition = $this->competitionService->store($data);

        return redirect()->route('admin.competitions.show', $competition->id)->with('status', 'competition-created');
    }

    public function edit(int $competition): View
    {
        return view('admin.competitions.edit', $this->competitionService->editFormData($competition));
    }

    public function update(Request $request, Competition $competition): RedirectResponse
    {
        $data = $request->validate($this->validationRules());
        $this->competitionService->update($competition, $data);

        return redirect()->route('admin.competitions.show', $competition->id)->with('status', 'competition-updated');
    }

    /** admin.competitions.archive — không xóa mềm được (không có deleted_at), chỉ chuyển "Lưu trữ" (11.1). */
    public function archive(Request $request, Competition $competition): RedirectResponse
    {
        $this->competitionService->archive($competition);

        return redirect()->route('admin.competitions.index')->with('status', 'competition-archived');
    }

    public function show(Request $request, int $competition): View
    {
        return view('admin.competitions.show', $this->competitionService->showData($competition));
    }
}
