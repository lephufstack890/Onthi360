<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassRoom;
use App\Models\Course;
use App\Services\Admin\CourseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function __construct(private CourseService $courseService) {}

    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'courses');

        return view('admin.courses.index', $this->courseService->indexData($tab));
    }

    public function create(Request $request): View
    {
        return view('admin.courses.create', $this->courseService->createFormData());
    }

    public function show(int $course): View
    {
        return view('admin.courses.show', $this->courseService->showData($course));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'subject' => ['nullable', 'string', 'max:60'],
            'grade' => ['nullable', 'string', 'max:20'],
            'status' => ['required', 'string', 'in:draft,published'],
        ]);

        $course = $this->courseService->store(Auth::user(), $data);

        return redirect()->route('admin.courses.index')->with('status', 'course-created');
    }

    public function edit(int $course): View
    {
        return view('admin.courses.edit', $this->courseService->editFormData($course));
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'subject' => ['nullable', 'string', 'max:60'],
            'grade' => ['nullable', 'string', 'max:20'],
            'status' => ['required', 'string', 'in:draft,published,archived'],
        ]);

        $this->courseService->update($course, $data);

        return redirect()->route('admin.courses.show', $course->id)->with('status', 'course-updated');
    }

    public function destroy(Request $request, Course $course): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $this->courseService->destroy($course, $data['reason']);

        return redirect()->route('admin.courses.index')->with('status', 'course-deleted');
    }

    public function classesCreate(Course $course): View
    {
        return view('admin.classes.create', $this->courseService->classCreateFormData($course));
    }

    public function classesStore(Request $request, Course $course): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:255'],
            'schedule_note' => ['nullable', 'string', 'max:500'],
            'teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['required', 'string', 'in:active,archived'],
        ]);

        $classRoom = $this->courseService->storeClass($course, $data);

        return redirect()->route('admin.courses.show', $course->id)->with('status', 'class-created');
    }

    public function classesEdit(int $classRoom): View
    {
        return view('admin.classes.edit', $this->courseService->classEditFormData($classRoom));
    }

    public function classesUpdate(Request $request, ClassRoom $classRoom): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:255'],
            'schedule_note' => ['nullable', 'string', 'max:500'],
            'teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['required', 'string', 'in:active,archived'],
        ]);

        $this->courseService->updateClass($classRoom, $data);

        return redirect()->route('admin.classes.edit', $classRoom->id)->with('status', 'class-updated');
    }

    public function classesDestroy(Request $request, ClassRoom $classRoom): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $courseId = $classRoom->course_id;

        $this->courseService->destroyClass($classRoom, $data['reason']);

        return redirect()->route('admin.courses.show', $courseId)->with('status', 'class-deleted');
    }
}
