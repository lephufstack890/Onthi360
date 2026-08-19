<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\PracticeByQuestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Route: student.practiceByQuestion.* | Giai đoạn 6 — "Luyện tập theo câu" (xem docblock đầy
 * đủ ở App\Services\Student\PracticeByQuestionService: luyện từng câu ngoài đề, tiến trình
 * lưu tạm ở session, KHÔNG tạo Attempt/AttemptAnswer).
 */
class PracticeByQuestionController extends Controller
{
    public function __construct(private readonly PracticeByQuestionService $service) {}

    public function setup(Request $request): View
    {
        return view('student.practice.by-question-setup', $this->service->setupData());
    }

    public function start(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer'],
            'type' => ['nullable', 'in:mcq,fill_blank'],
        ]);

        $started = $this->service->start($data['tag_ids'] ?? [], $data['type'] ?? null);

        if (! $started) {
            return redirect()->route('student.practiceByQuestion.setup')
                ->withErrors(['tag_ids' => 'Không tìm thấy câu hỏi phù hợp bộ lọc — thử bỏ bớt điều kiện lọc.']);
        }

        return redirect()->route('student.practiceByQuestion.play');
    }

    public function play(Request $request): View|RedirectResponse
    {
        $data = $this->service->playData();

        if ($data === null) {
            return redirect()->route('student.practiceByQuestion.setup');
        }

        return view('student.practice.by-question-play', $data);
    }

    public function answer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'selected_option' => ['nullable', 'integer'],
            'text' => ['nullable', 'string', 'max:500'],
        ]);

        if (! $this->service->answer($data)) {
            return redirect()->route('student.practiceByQuestion.setup');
        }

        return redirect()->route('student.practiceByQuestion.play');
    }

    public function next(Request $request): RedirectResponse
    {
        $this->service->advance();

        return redirect()->route('student.practiceByQuestion.play');
    }

    public function stop(Request $request): RedirectResponse
    {
        $this->service->stop();

        return redirect()->route('student.practice.index');
    }
}
