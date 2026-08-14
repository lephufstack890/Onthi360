{{--
  Route: student.assessment.take | Frame: STU-05
  Spec: 6.3 (đề hỗn hợp) + 10.1 (không gian làm bài).
  $assessmentModel, $attempt, $questions do App\Services\Student\AssessmentService::
  buildTakeData() truyền vào — DỮ LIỆU THẬT (không còn là UI tĩnh minh họa): mở/tiếp tục 1
  Attempt thật qua App\Services\AttemptService, câu trả lời đã lưu được hiển thị lại khi
  quay lại làm dở (resume). "Lưu nháp"/"Nộp bài" là 2 nút cùng 1 <form>, khác nhau qua
  formaction — không có JS auto-save/điều hướng câu không-reload-trang (để ở lượt sau);
  câu lập trình được LƯU bài nộp thật nhưng CHƯA có sandbox chấm code (verdict luôn
  "Đang chấm" cho tới khi có worker chấm thật — 6.3 phần chấm code nằm ngoài phạm vi này).
--}}
@extends('layouts.student')

@section('title', 'Làm bài')
@section('page-title', '')

@section('content')
    @php
        $questions = $questions ?? [];
        $statusStyle = [
            'answered' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
            'unanswered' => 'bg-white text-slate-500 border-slate-200',
        ];
        $assessmentTitle = $assessmentModel->title ?? 'Đề';
        $maxAttempts = $assessmentModel->resubmission_policy['max_attempts'] ?? null;
        $resubmissionNote = $maxAttempts ? 'Nộp lại tối đa '.$maxAttempts.' lần' : 'Không giới hạn số lần nộp lại';
    @endphp

    @if (session('status') === 'draft-saved')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu nháp bài làm.'])
    @endif
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <form method="POST" action="{{ route('student.assessment.take.submit', $attempt->id) }}" id="take-form">
        @csrf

        {{-- Header sticky: tên đề, thời lượng, lưu/nộp --}}
        <div class="sticky top-0 z-10 -mx-4 lg:-mx-6 px-4 lg:px-6 py-3 bg-white/90 backdrop-blur border-b border-slate-200 flex items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-medium text-slate-800">{{ $assessmentTitle }}</h1>
                <p class="text-xs text-slate-400">{{ $resubmissionNote }}</p>
            </div>
            <div class="flex items-center gap-3">
                @if ($assessmentModel->duration_minutes)
                    <div class="px-3 py-1.5 rounded-lg bg-rose-50 text-rose-600 text-sm font-medium">⏱ {{ $assessmentModel->duration_minutes }} phút</div>
                @endif
                <button type="submit" formaction="{{ route('student.assessment.take.save', $attempt->id) }}" class="px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium">Lưu nháp</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">Nộp bài</button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            {{-- Điều hướng câu --}}
            <div class="lg:col-span-1 order-2 lg:order-1">
                <div class="bg-white rounded-2xl border border-slate-200 p-4 sticky top-24">
                    <p class="text-xs text-slate-400 mb-3">{{ count($questions) }} câu · Hỗn hợp trắc nghiệm/điền đáp án/lập trình</p>
                    <div class="grid grid-cols-5 lg:grid-cols-4 gap-2">
                        @foreach ($questions as $q)
                            <a href="#question-{{ $q['no'] }}" class="w-9 h-9 flex items-center justify-center rounded-lg border text-sm font-medium {{ $statusStyle[$q['status']] }}">
                                {{ $q['no'] }}
                            </a>
                        @endforeach
                    </div>
                    <div class="mt-4 space-y-1.5 text-xs text-slate-500">
                        <div class="flex items-center gap-2"><span class="w-3 h-3 rounded bg-emerald-100 border border-emerald-200 inline-block"></span> Đã trả lời</div>
                        <div class="flex items-center gap-2"><span class="w-3 h-3 rounded bg-white border border-slate-200 inline-block"></span> Chưa trả lời</div>
                    </div>
                </div>
            </div>

            {{-- Danh sách câu hỏi --}}
            <div class="lg:col-span-3 order-1 lg:order-2 space-y-5">
                @foreach ($questions as $q)
                    <div id="question-{{ $q['no'] }}" class="bg-white rounded-2xl border border-slate-200 p-6 scroll-mt-24">
                        <div class="flex items-center justify-between mb-4">
                            <x-status-badge tone="info">
                                Câu {{ $q['no'] }} ·
                                {{ match ($q['type']) { 'mcq' => 'Trắc nghiệm', 'fill_blank' => 'Điền đáp án', 'coding' => 'Lập trình', default => $q['type'] } }}
                                · {{ $q['points'] }} điểm
                            </x-status-badge>
                        </div>

                        <p class="text-slate-800 font-medium mb-4">{{ $q['title'] }}</p>
                        @if ($q['body'])
                            <p class="text-sm text-slate-500 mb-4 whitespace-pre-line">{{ $q['body'] }}</p>
                        @endif

                        @if ($q['type'] === 'mcq')
                            <div class="space-y-2">
                                @foreach ($q['options'] as $i => $opt)
                                    <label class="flex items-center gap-3 px-4 py-3 rounded-xl border border-slate-200 hover:border-rose-300 cursor-pointer text-sm text-slate-700">
                                        <input type="radio" name="answers[{{ $q['questionId'] }}][selected_option]" value="{{ $i }}"
                                               @checked((string) $q['selectedOption'] === (string) $i)>
                                        {{ $opt }}
                                    </label>
                                @endforeach
                            </div>
                        @elseif ($q['type'] === 'fill_blank')
                            <input type="text" name="answers[{{ $q['questionId'] }}][text]" value="{{ $q['textAnswer'] }}"
                                   placeholder="Nhập đáp án..."
                                   class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                        @else
                            <div class="space-y-2">
                                <input type="text" name="answers[{{ $q['questionId'] }}][language]" value="{{ $q['language'] ?? 'cpp' }}"
                                       placeholder="Ngôn ngữ (vd cpp, python, java)"
                                       class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                                <textarea name="answers[{{ $q['questionId'] }}][code_source]" rows="10"
                                          placeholder="Viết code ở đây..."
                                          class="w-full rounded-lg border border-slate-200 text-sm p-2.5 font-mono">{{ $q['codeSource'] }}</textarea>
                                <p class="text-xs text-slate-400">Bài nộp sẽ ở trạng thái "Đang chấm" — hệ thống chấm code tự động chưa nối vào ở phiên bản này.</p>
                            </div>
                        @endif
                    </div>
                @endforeach

                <p class="text-xs text-slate-400 text-center">Câu trả lời chỉ được lưu khi bấm "Lưu nháp" hoặc "Nộp bài" (chưa có auto-save theo thời gian thực).</p>
            </div>
        </div>
    </form>
@endsection
