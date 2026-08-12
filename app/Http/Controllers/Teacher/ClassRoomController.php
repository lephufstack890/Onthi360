<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\Teacher\ClassRoomService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ClassRoomController extends Controller
{
    public function __construct(private readonly ClassRoomService $classRoomService) {}

    /** teacher.classes.index (TEA-02) — lớp giáo viên phụ trách hoặc đồng phụ trách (8.1). */
    public function index(Request $request): View
    {
        $user = Auth::user();

        return view('teacher.classes.index', $this->classRoomService->listForTeacher($user));
    }

    /** teacher.classes.show (TEA-02 chi tiết + TEA-06 học liệu, 8.2/8.3). */
    public function show(Request $request, int $class): View
    {
        $user = Auth::user();
        $tab = $request->query('tab', 'overview');

        return view('teacher.classes.show', $this->classRoomService->showForTeacher($user, $class, $tab));
    }

    /** teacher.classes.create — form tạo lớp mới (8.1). */
    public function create(Request $request): View
    {
        return view('teacher.classes.create', $this->classRoomService->createFormData());
    }

    /** teacher.classes.store — tạo lớp mới, tự gắn giáo viên hiện tại làm giáo viên chính. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:255'],
            'schedule_note' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'string', 'in:active,archived'],
        ]);

        $classRoom = $this->classRoomService->store(Auth::user(), $data);

        return redirect()->route('teacher.classes.show', $classRoom->id)->with('status', 'class-created');
    }
}
