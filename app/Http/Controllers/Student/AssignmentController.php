<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\StudentWorkspaceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** student.assignments.index — "Bài được giao" (bài/đề giao riêng + bài của lớp) kèm lối vào làm bài. */
class AssignmentController extends Controller
{
    public function __construct(private StudentWorkspaceService $workspace) {}

    public function index(Request $request): View
    {
        $data = $this->workspace->assignments($request->user());

        return view('student.assignments.index', [
            'assignments' => $data['rows'],
            'counts' => $data['counts'],
            'next' => $data['next'],
        ]);
    }
}
