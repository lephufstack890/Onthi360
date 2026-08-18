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

    public function index(Request $request): View
    {
        $user = $request->user();
        $tab = $request->query('tab', 'self');
        // SỬA 18/8: 2 tham số lọc thật (App\Enums\QuestionType + tên QuestionBank làm "chuyên
        // đề" tạm — xem docblock PracticeService::buildIndexData()), null nếu không lọc.
        $type = $request->query('type');
        $topic = $request->query('topic');

        return view('student.practice.index', $this->practiceService->buildIndexData($user, $tab, $type, $topic));
    }
}
