<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\Teacher\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function __construct(private readonly ScheduleService $scheduleService) {}

    /** teacher.schedule.index (TEA-01/02) — lịch buổi học xuyên mọi lớp giáo viên phụ trách (8.2). */
    public function index(): View
    {
        return view('teacher.schedule.index', $this->scheduleService->indexData(Auth::user()));
    }

    /** teacher.schedule.store — tạo buổi học mới cho một lớp đang dạy. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'class_room_id' => ['required', 'integer', 'exists:class_rooms,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'topic' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $session = $this->scheduleService->store(Auth::user(), $data);

        if ($request->filled('back_to_class')) {
            return redirect()->route('teacher.classes.show', ['class' => $session->class_room_id, 'tab' => 'schedule'])->with('status', 'session-created');
        }

        return redirect()->route('teacher.schedule.index')->with('status', 'session-created');
    }

    /** teacher.schedule.attendance — form điểm danh cho một buổi học cụ thể (8.2). */
    public function attendance(int $session): View
    {
        return view('teacher.schedule.attendance', $this->scheduleService->attendanceForSession(Auth::user(), $session));
    }

    /** teacher.schedule.attendance.save — lưu điểm danh (present/absent/excused/late). */
    public function saveAttendance(Request $request, int $session)
    {
        $data = $request->validate([
            'status' => ['required', 'array'],
            'status.*' => ['required', 'string', 'in:present,absent,excused,late'],
        ]);

        $this->scheduleService->saveAttendance(Auth::user(), $session, $data['status']);

        return redirect()->route('teacher.schedule.attendance', $session)->with('status', 'attendance-saved');
    }
}
