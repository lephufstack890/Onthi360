<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\PracticeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PracticeController extends Controller
{
    public function __construct(
        private PracticeService $practiceService,
    ) {}

    /** student.practice.index (STU-04) — tabs Tự luyện · Theo lớp · Bài được giao · Đã lưu · Lịch sử. */
    public function index(Request $request): View
    {
        $user = $request->user();
        $tab = $request->query('tab', 'self');

        return view('student.practice.index', $this->practiceService->buildIndexData($user, $tab));
    }
}
