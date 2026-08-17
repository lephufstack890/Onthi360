<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\CourseService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function __construct(private CourseService $courseService) {}

    public function index(Request $request): View
    {
        return view('public.courses.index', $this->courseService->indexData($request->query('subject')));
    }

    public function show(Request $request, int $course): View
    {
        return view('public.courses.show', $this->courseService->showData($course, $request->user()));
    }
}
