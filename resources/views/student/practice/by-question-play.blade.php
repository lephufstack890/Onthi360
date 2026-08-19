{{--
  Route: student.practiceByQuestion.play / .answer / .next / .stop | Giai đoạn 6
  Spec: luyện 1 câu tại 1 thời điểm — trả lời xong hiện đúng/sai ngay (App\Services\
  QuestionGrader, dùng chung với AttemptService) rồi mới bấm "Câu tiếp theo". $finished,
  $question, $options, $progress, $feedback do App\Services\Student\PracticeByQuestionService::
  playData() truyền vào qua PracticeByQuestionController::play().
--}}
@extends('layouts.student')

@section('title', 'Luyện tập theo câu')
@section('page-title', 'Luyện tập theo câu')

@section('content')
    @php
        $finished = $finished ?? false;
        $feedback = $feedback ?? null;
        $options = $options ?? [];
    @endphp

    @if ($finished)
        <div class="max-w-xl mx-auto text-center bg-white rounded-2xl border border-slate-200 p-8 mt-8">
            <div class="text-5xl mb-3">🎉</div>
            <h2 class="text-xl font-semibold text-slate-800 mb-2">Đã luyện hết {{ $total }} câu!</h2>
            <p class="text-slate-500 mb-6">Đúng <span class="font-semibold text-emerald-600">{{ $correct }}</span>/{{ $answered }} câu đã trả lời{{ $answered < $total ? ' ('.($total - $answered).' câu bỏ qua)' : '' }}.</p>
            <div class="flex items-center justify-center gap-3">
                <a href="{{ route('student.practiceByQuestion.setup') }}" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium">Luyện lại ›</a>
                <a href="{{ route('student.practice.index') }}" class="px-5 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600">Về Luyện tập</a>
            </div>
        </div>
    @else
        <div class="max-w-2xl mx-auto">
            <div class="flex items-center justify-between mb-4">
                <p class="text-sm text-slate-500">Câu {{ $progress['current'] }}/{{ $progress['total'] }} · Đúng {{ $progress['correct'] }}/{{ $progress['answered'] }}</p>
                <form method="POST" action="{{ route('student.practiceByQuestion.stop') }}">
                    @csrf
                    <button type="submit" class="text-sm text-slate-400 hover:text-rose-600">Dừng luyện tập ✕</button>
                </form>
            </div>

            <div class="w-full h-1.5 rounded-full bg-slate-100 mb-6 overflow-hidden">
                <div class="h-full bg-rose-500 transition-all" style="width: {{ $progress['total'] > 0 ? round($progress['current'] / $progress['total'] * 100) : 0 }}%"></div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5 lg:p-6">
                <x-status-badge tone="info">{{ $question->type->value === 'mcq' ? '🔤 Trắc nghiệm' : '✏️ Điền đáp án' }}</x-status-badge>
                <h3 class="font-semibold text-slate-800 text-lg mt-3 mb-1">{{ $question->title }}</h3>
                <p class="text-sm text-slate-600 whitespace-pre-line mb-5">{{ $question->body }}</p>

                @if ($question->tags->isNotEmpty())
                    <div class="flex flex-wrap gap-1 mb-5">
                        @foreach ($question->tags as $t)
                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">📚 {{ $t->name }}</span>
                        @endforeach
                    </div>
                @endif

                @if ($feedback === null)
                    {{-- Chưa trả lời câu này — hiện form nhập đáp án. --}}
                    <form method="POST" action="{{ route('student.practiceByQuestion.answer') }}" class="space-y-3">
                        @csrf
                        @if ($question->type->value === 'mcq')
                            @foreach ($options as $i => $opt)
                                @if ($opt !== '' && $opt !== null)
                                    <label class="flex items-center gap-2 p-3 rounded-lg border border-slate-200 hover:border-rose-200 cursor-pointer has-[:checked]:border-rose-300 has-[:checked]:bg-rose-50">
                                        <input type="radio" name="selected_option" value="{{ $i }}" required>
                                        <span class="text-sm text-slate-700">{{ $opt }}</span>
                                    </label>
                                @endif
                            @endforeach
                        @else
                            <input type="text" name="text" required maxlength="500" placeholder="Nhập đáp án..."
                                   class="w-full rounded-lg border border-slate-200 text-sm p-3">
                        @endif
                        <button type="submit" class="w-full px-4 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium">Kiểm tra đáp án</button>
                    </form>
                @else
                    {{-- Đã trả lời — hiện kết quả đúng/sai + đáp án đúng, khoá form lại. --}}
                    <div class="space-y-3">
                        @if ($question->type->value === 'mcq')
                            @foreach ($options as $i => $opt)
                                @if ($opt !== '' && $opt !== null)
                                    @php
                                        $isCorrectOpt = in_array((int) $i, array_map('intval', $feedback['correctOptions']), true);
                                        $isYourPick = (string) $feedback['yourSelectedOption'] === (string) $i;
                                    @endphp
                                    <div @class([
                                        'flex items-center gap-2 p-3 rounded-lg border text-sm',
                                        'border-emerald-300 bg-emerald-50 text-emerald-700' => $isCorrectOpt,
                                        'border-rose-300 bg-rose-50 text-rose-600' => $isYourPick && ! $isCorrectOpt,
                                        'border-slate-200 text-slate-500' => ! $isCorrectOpt && ! $isYourPick,
                                    ])>
                                        <span>{{ $isCorrectOpt ? '✓' : ($isYourPick ? '✕' : '') }}</span>
                                        <span>{{ $opt }}</span>
                                    </div>
                                @endif
                            @endforeach
                        @else
                            <div class="p-3 rounded-lg border border-slate-200 text-sm text-slate-500">
                                Bạn trả lời: <span class="font-medium text-slate-700">{{ $feedback['yourText'] }}</span>
                            </div>
                            <div class="p-3 rounded-lg border border-emerald-300 bg-emerald-50 text-sm text-emerald-700">
                                Đáp án đúng: {{ implode(', ', $feedback['acceptedAnswers']) }}
                            </div>
                        @endif

                        <div @class([
                            'rounded-lg p-3 text-sm font-medium text-center',
                            'bg-emerald-50 text-emerald-700 border border-emerald-200' => $feedback['isCorrect'],
                            'bg-rose-50 text-rose-600 border border-rose-200' => ! $feedback['isCorrect'],
                        ])>
                            {{ $feedback['isCorrect'] ? '✓ Chính xác!' : '✕ Chưa đúng — xem đáp án ở trên.' }}
                        </div>

                        <form method="POST" action="{{ route('student.practiceByQuestion.next') }}">
                            @csrf
                            <button type="submit" class="w-full px-4 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium">
                                {{ $progress['current'] < $progress['total'] ? 'Câu tiếp theo ›' : 'Xem kết quả ›' }}
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    @endif
@endsection
