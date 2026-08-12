<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\ClassRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ResultController extends Controller
{
    /** teacher.results.index (TEA-08) — phễu Lớp → Đề → Học sinh → Lần nộp (10.2). */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $classRooms = $user->classRoomsTeaching()->with('course')->get();

        $selectedClassId = (int) $request->query('class', $classRooms->first()->id ?? 0);
        $selectedClassRoom = $classRooms->firstWhere('id', $selectedClassId);

        $assignments = $selectedClassRoom
            ? Assignment::where('class_room_id', $selectedClassRoom->id)->with('assessment')->latest('opens_at')->get()
            : collect();

        $selectedAssignmentId = (int) $request->query('assessment', $assignments->first()->id ?? 0);
        $selectedAssignment = $assignments->firstWhere('id', $selectedAssignmentId);

        $students = collect();
        $stats = ['submitted' => 0, 'inProgress' => 0, 'notStarted' => 0];

        if ($selectedClassRoom && $selectedAssignment) {
            $roster = $selectedClassRoom->students;
            $attempts = $selectedAssignment->attempts()->whereIn('user_id', $roster->pluck('id'))->get()->keyBy('user_id');

            $students = $roster->map(function ($student) use ($attempts) {
                $attempt = $attempts->get($student->id);
                $status = match (true) {
                    $attempt === null => 'Chưa làm',
                    $attempt->submitted_at !== null => 'Đã nộp',
                    default => 'Đang làm',
                };
                $tone = match ($status) {
                    'Đã nộp' => 'success',
                    'Đang làm' => 'info',
                    default => 'neutral',
                };

                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'status' => $status,
                    'score' => $attempt?->total_score !== null ? (string) $attempt->total_score : '—',
                    'tone' => $tone,
                    'time' => $attempt?->submitted_at?->diffForHumans() ?? ($attempt ? 'Đang mở' : '—'),
                ];
            })->values();

            $stats['submitted'] = $students->where('status', 'Đã nộp')->count();
            $stats['inProgress'] = $students->where('status', 'Đang làm')->count();
            $stats['notStarted'] = $students->where('status', 'Chưa làm')->count();
        }

        return view('teacher.results.index', [
            'classRooms' => $classRooms,
            'selectedClassId' => $selectedClassId,
            'assignments' => $assignments,
            'selectedAssignmentId' => $selectedAssignmentId,
            'students' => $students,
            'stats' => $stats,
        ]);
    }
}
