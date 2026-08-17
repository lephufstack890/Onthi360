<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\Teacher\ScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function __construct(private readonly ScheduleService $scheduleService) {}

    public function index(Request $request): View
    {
        return view('teacher.schedule.index', $this->scheduleService->indexData(Auth::user()));
    }

    private function storeRules(): array
    {
        return [
            'class_room_id' => ['required', 'integer'],
            'topic' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_date' => ['required', 'date'],
            'starts_hour' => ['required', 'string'],
            'starts_minute' => ['required', 'string'],
            'ends_date' => ['required', 'date'],
            'ends_hour' => ['required', 'string'],
            'ends_minute' => ['required', 'string'],
        ];
    }

    private function combineDateTime(array $data, string $prefix): string
    {
        return sprintf('%s %s:%s:00', $data[$prefix.'_date'], $data[$prefix.'_hour'], $data[$prefix.'_minute']);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->storeRules());

        $this->scheduleService->store(Auth::user(), [
            'class_room_id' => $data['class_room_id'],
            'topic' => $data['topic'] ?? null,
            'location' => $data['location'] ?? null,
            'starts_at' => $this->combineDateTime($data, 'starts'),
            'ends_at' => $this->combineDateTime($data, 'ends'),
        ]);

        return redirect()->route('teacher.schedule.index')->with('status', 'session-created');
    }

    public function attendance(Request $request, int $session): View
    {
        return view('teacher.schedule.attendance', $this->scheduleService->attendanceForSession(Auth::user(), $session));
    }

    public function saveAttendance(Request $request, int $session): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'array'],
            'status.*' => ['string', 'in:present,absent,excused,late'],
            'note' => ['nullable', 'array'],
            'note.*' => ['nullable', 'string', 'max:1000'],
            'needs_more_practice' => ['nullable', 'array'],
        ]);

        $this->scheduleService->saveAttendance(
            Auth::user(),
            $session,
            $data['status'] ?? [],
            $data['note'] ?? [],
            $data['needs_more_practice'] ?? []
        );

        return redirect()->route('teacher.schedule.attendance', $session)->with('status', 'attendance-saved');
    }

    public function saveSummary(Request $request, int $session): RedirectResponse
    {
        $data = $request->validate(['summary' => ['nullable', 'string', 'max:5000']]);

        $this->scheduleService->saveSummary(Auth::user(), $session, $data['summary'] ?? null);

        return redirect()->route('teacher.schedule.attendance', $session)->with('status', 'summary-saved');
    }

    public function addResource(Request $request, int $session): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:material,question,assessment,video,link,note'],
            'material_id' => ['nullable', 'integer'],
            'question_id' => ['nullable', 'integer'],
            'assessment_id' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:2048'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->scheduleService->addResource(Auth::user(), $session, $data);

        return redirect()->route('teacher.schedule.attendance', $session)->with('status', 'resource-added');
    }

    public function removeResource(Request $request, int $session, int $resource): RedirectResponse
    {
        $this->scheduleService->removeResource(Auth::user(), $session, $resource);

        return redirect()->route('teacher.schedule.attendance', $session)->with('status', 'resource-removed');
    }
}
