<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionExam;
use App\Services\Admin\CompetitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
            'scoring_note' => ['nullable', 'string', 'max:500'],
            'penalty_note' => ['nullable', 'string', 'max:500'],
            'tie_break_note' => ['nullable', 'string', 'max:500'],
            'organizer_type' => ['required', 'string', 'in:internal,external'],
            'organizer_name' => ['nullable', 'string', 'max:255'],
            'advisor_teacher_ids' => ['nullable', 'array'],
            'advisor_teacher_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    /**
     * @throws ValidationException
     */
    private function combineDateTimeInputs(Request $request, array $fields): void
    {
        $errors = [];

        foreach ($fields as $field) {
            $day = trim((string) $request->input($field.'_day'));
            $month = trim((string) $request->input($field.'_month'));
            $year = trim((string) $request->input($field.'_year'));

            $filledCount = ($day !== '' ? 1 : 0) + ($month !== '' ? 1 : 0) + ($year !== '' ? 1 : 0);

            if ($filledCount === 0) {
                $request->merge([$field => null]);

                continue;
            }

            if ($filledCount < 3) {
                $errors[$field] = 'Thiếu Ngày/Tháng/Năm — chọn đủ cả 3 ô, hoặc để trống toàn bộ nếu chưa muốn đặt mốc thời gian này.';

                continue;
            }

            $day = str_pad($day, 2, '0', STR_PAD_LEFT);
            $month = str_pad($month, 2, '0', STR_PAD_LEFT);

            $hour = trim((string) $request->input($field.'_hour'));
            $minute = trim((string) $request->input($field.'_minute'));
            $hour = $hour !== '' ? str_pad($hour, 2, '0', STR_PAD_LEFT) : '00';
            $minute = $minute !== '' ? str_pad($minute, 2, '0', STR_PAD_LEFT) : '00';

            $request->merge([$field => "{$year}-{$month}-{$day}T{$hour}:{$minute}"]);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function create(): View
    {
        return view('admin.competitions.create', $this->competitionService->createFormData());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->combineDateTimeInputs($request, ['starts_at', 'ends_at', 'publish_result_at']);
        $data = $request->validate($this->validationRules());

        try {
            $competition = $this->competitionService->store($data);
        } catch (ValidationException $e) {
            return redirect()->route('admin.competitions.create')->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.competitions.show', $competition->id)->with('status', 'competition-created');
    }

    public function edit(int $competition): View
    {
        return view('admin.competitions.edit', $this->competitionService->editFormData($competition));
    }

    public function update(Request $request, Competition $competition): RedirectResponse
    {
        $this->combineDateTimeInputs($request, ['starts_at', 'ends_at', 'publish_result_at']);
        $data = $request->validate($this->validationRules());

        try {
            $this->competitionService->update($competition, $data);
        } catch (ValidationException $e) {
            return redirect()->route('admin.competitions.edit', $competition->id)->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.competitions.show', $competition->id)->with('status', 'competition-updated');
    }

    /** admin.competitions.archive — không xóa mềm được (không có deleted_at), chỉ chuyển "Lưu trữ" (11.1). */
    public function archive(Request $request, Competition $competition): RedirectResponse
    {
        $this->competitionService->archive($competition);

        return redirect()->route('admin.competitions.index')->with('status', 'competition-archived');
    }

    /** admin.competitions.unarchive — đảo ngược archive(), xem docblock CompetitionService::unarchive(). */
    public function unarchive(Request $request, Competition $competition): RedirectResponse
    {
        $this->competitionService->unarchive($competition);

        return redirect()->route('admin.competitions.show', $competition->id)->with('status', 'competition-unarchived');
    }

    public function show(Request $request, int $competition): View
    {
        return view('admin.competitions.show', $this->competitionService->showData($competition));
    }

    /** admin.competitions.exams.store — thêm 1 kỳ thi (vòng) mới vào cuộc thi. */
    public function examStore(Request $request, Competition $competition): RedirectResponse
    {
        $this->combineDateTimeInputs($request, ['starts_at', 'ends_at']);
        $data = $request->validate($this->competitionService->examValidationRules());

        try {
            $this->competitionService->storeExam($competition, $data);
        } catch (ValidationException $e) {
            return redirect()->route('admin.competitions.show', $competition->id)->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.competitions.show', $competition->id)->with('status', 'exam-added');
    }

    /** admin.competitions.exams.update. */
    public function examUpdate(Request $request, CompetitionExam $competitionExam): RedirectResponse
    {
        $competitionId = $competitionExam->competition_id;

        $this->combineDateTimeInputs($request, ['starts_at', 'ends_at']);
        $data = $request->validate($this->competitionService->examValidationRules());

        try {
            $this->competitionService->updateExam($competitionExam, $data);
        } catch (ValidationException $e) {
            return redirect()->route('admin.competitions.show', $competitionId)->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.competitions.show', $competitionId)->with('status', 'exam-updated');
    }

    /** admin.competitions.exams.destroy — chặn xoá nếu kỳ thi đã có lượt xếp hạng riêng. */
    public function examDestroy(Request $request, CompetitionExam $competitionExam): RedirectResponse
    {
        $competitionId = $competitionExam->competition_id;

        try {
            $this->competitionService->deleteExam($competitionExam);
        } catch (ValidationException $e) {
            return redirect()->route('admin.competitions.show', $competitionId)->withErrors($e->errors());
        }

        return redirect()->route('admin.competitions.show', $competitionId)->with('status', 'exam-deleted');
    }

    /** admin.competitions.recompute-aggregate — "Tính tổng từ các kỳ thi" (dual leaderboard). */
    public function recomputeAggregate(Request $request, Competition $competition): RedirectResponse
    {
        try {
            $this->competitionService->recomputeAggregateFromExams($competition);
        } catch (ValidationException $e) {
            return redirect()->route('admin.competitions.show', $competition->id)->withErrors($e->errors());
        }

        return redirect()->route('admin.competitions.show', $competition->id)->with('status', 'aggregate-recomputed');
    }
}
