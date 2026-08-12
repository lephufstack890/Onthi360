<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CourseController extends Controller
{
    /** student.courses.index (STU-02) — danh sách lớp học sinh đang tham gia. */
    public function index(Request $request): View
    {
        $user = Auth::user();

        $classes = $user->classEnrollments()
            ->where('status', 'active')
            ->with('classRoom.course', 'classRoom.teachers')
            ->get()
            ->map(function ($enrollment) {
                $classRoom = $enrollment->classRoom;
                $teacher = $classRoom->teachers->first();

                return [
                    'id' => $classRoom->id,
                    'course' => $classRoom->course->title ?? '',
                    'class' => $classRoom->name,
                    'teacher' => $teacher ? 'GV '.$teacher->name : 'Chưa phân công',
                    // TODO: % tiến độ thật cần công thức tổng hợp progress_unlocks + attempts theo lớp.
                    'percent' => 0,
                    'nextSession' => null, // TODO: cần bảng class_sessions sắp tới gần nhất.
                ];
            })->values()->all();

        return view('student.courses.index', ['classes' => $classes]);
    }
}
