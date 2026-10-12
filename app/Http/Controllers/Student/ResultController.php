<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\StudentWorkspaceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** student.results.index — "Kết quả & lịch sử nộp" của chính học sinh. */
class ResultController extends Controller
{
    public function __construct(private StudentWorkspaceService $workspace) {}

    public function index(Request $request): View
    {
        return view('student.results.index', $this->workspace->results($request->user()));
    }
}
