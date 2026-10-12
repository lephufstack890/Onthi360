<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\StudentWorkspaceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** student.reviews.index — "Đánh giá của tôi": lớp đủ điều kiện đánh giá + các đánh giá đã gửi. Form thật vẫn là reviews.form. */
class ReviewController extends Controller
{
    public function __construct(private StudentWorkspaceService $workspace) {}

    public function index(Request $request): View
    {
        return view('student.reviews.index', $this->workspace->reviews($request->user()));
    }
}
