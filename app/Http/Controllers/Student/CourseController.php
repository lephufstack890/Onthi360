<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\CourseService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function __construct(
        private CourseService $courseService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $classes = $this->courseService->activeClassesForUser($user);

        return view('student.courses.index', ['classes' => $classes]);
    }
}
