<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassRoom;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseController extends Controller
{
    /** admin.courses.index — "Khóa & Lớp" (8.1: Khóa học khác Lớp học). */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'courses');

        $tabs = [
            ['label' => 'Khóa học', 'href' => route('admin.courses.index'), 'active' => $tab === 'courses', 'count' => Course::count()],
            ['label' => 'Lớp học', 'href' => route('admin.courses.index', ['tab' => 'classes']), 'active' => $tab === 'classes', 'count' => ClassRoom::count()],
        ];

        if ($tab === 'classes') {
            $rows = ClassRoom::with('course', 'teachers')->withCount(['students' => fn ($q) => $q->wherePivot('status', 'active')])->latest()->limit(50)->get()
                ->map(function ($c) {
                    $teacher = $c->teachers->first();

                    return [
                        'id' => $c->id,
                        'name' => $c->name.' ('.($c->course->title ?? '').')',
                        'meta' => ($teacher ? 'GV '.$teacher->name : 'Chưa phân công').' · '.$c->students_count.' học sinh',
                        'status' => $c->status === 'active' ? 'Đang học' : (string) $c->status,
                        'tone' => $c->status === 'active' ? 'success' : 'neutral',
                    ];
                })->all();
        } else {
            $rows = Course::withCount('classRooms')->latest()->limit(50)->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->title,
                    'meta' => $c->class_rooms_count.' lớp đang triển khai',
                    'status' => $c->status->value === 'published' ? 'Đang mở' : (string) $c->status->value,
                    'tone' => $c->status->value === 'published' ? 'success' : 'neutral',
                ])->all();
        }

        return view('admin.courses.index', ['tab' => $tab, 'tabs' => $tabs, 'rows' => $rows]);
    }
}
