<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Services\Access\CourseEnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * C3 — Chọn lớp sau khi đã mua khoá học.
 *
 * Controller mỏng: mọi luật (có quyền chưa, lớp có thuộc khoá không, đã ở trong lớp chưa)
 * nằm ở App\Services\Access\CourseEnrollmentService.
 */
class CourseEnrollmentController extends Controller
{
    public function __construct(private readonly CourseEnrollmentService $enrollment) {}

    public function chooseClass(Request $request, int $course): View
    {
        return view('access.choose-class', $this->enrollment->chooseClassData(Auth::user(), $course));
    }

    public function enroll(Request $request, int $course): RedirectResponse
    {
        $data = $request->validate([
            'class_room_id' => ['required', 'integer'],
        ]);

        try {
            $classRoom = $this->enrollment->enroll(Auth::user(), $course, (int) $data['class_room_id']);
        } catch (ValidationException $e) {
            return redirect()->route('access.chooseClass', $course)->withErrors($e->errors());
        }

        // Học sinh vào thẳng lớp vừa chọn; vai trò khác (giáo viên mua để dạy) không có màn
        // lớp của học sinh nên quay về "Quyền của tôi".
        if (Auth::user()?->hasRole(\App\Models\Role::STUDENT)) {
            return redirect()->route('student.classes.show', $classRoom->id)->with('status', 'joined-class');
        }

        return redirect()->route('access.myAccess')->with('status', 'joined-class');
    }
}
