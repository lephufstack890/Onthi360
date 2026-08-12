<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\Teacher\QuestionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class QuestionController extends Controller
{
    public function __construct(private readonly QuestionService $questionService) {}

    /** teacher.questions.index (TEA-03) — kho riêng của giáo viên (6.5). */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $tab = $request->query('tab', 'all');

        return view('teacher.questions.index', $this->questionService->listForTeacher($user, $tab));
    }

    /** teacher.questions.create (TEA-04) — tạo câu hỏi mới theo loại (MCQ/điền đáp án/lập trình). */
    public function create(Request $request): View
    {
        $type = $request->query('type', 'mcq');

        return view('teacher.questions.create', ['type' => $type]);
    }
}
