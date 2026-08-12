<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\AssessmentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssessmentController extends Controller
{
    public function __construct(
        private AssessmentService $assessmentService,
    ) {}

    /** student.assessment.take (STU-05) — không gian làm bài hỗn hợp. */
    public function take(Request $request, int $assessment): View
    {
        $user = $request->user();

        return view('student.assessment.take', $this->assessmentService->buildTakeData($user, $assessment));
    }

    /** student.assessment.oj (STU-06/07) — làm câu lập trình đơn lẻ. */
    public function oj(Request $request, int $question): View
    {
        $user = $request->user();

        return view('student.assessment.oj', $this->assessmentService->buildOjData($user, $question));
    }

    /** student.assessment.result (STU-08/09) — kết quả bài làm. */
    public function result(Request $request, int $attempt): View
    {
        $user = $request->user();

        return view('student.assessment.result', $this->assessmentService->buildResultData($user, $attempt));
    }
}
