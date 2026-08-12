{{--
  Route: student.assessment.take | Frame: STU-05
  Spec: 6.3 (đề hỗn hợp, điều hướng theo câu/phần, trạng thái chưa trả
  lời/đã trả lời/đánh dấu xem lại/đang chấm, auto-save, cảnh báo rời
  trang) + 10.1 (không gian làm bài).
  Đây là UI tĩnh minh họa 1 câu trắc nghiệm đang mở; câu lập trình dùng
  student/assessment/oj.blade.php riêng (STU-06/07).
  TODO: JS thật cho auto-save, đếm giờ, điều hướng câu không reload trang,
  cảnh báo beforeunload khi còn câu chưa lưu.
--}}
@extends('layouts.student')

@section('title', 'Làm bài')
@section('page-title', '')

@section('content')
    {{-- $assessmentModel, $attempt, $questions do App\Http\Controllers\Student\AssessmentController
    truyền vào. Nội dung câu hỏi hiện tại vẫn minh họa tĩnh — TODO: hiển thị đúng câu theo
    $questions[$current]['questionId'] và loại câu (MCQ/điền đáp án/lập trình) khi có JS
    điều hướng câu thật (6.3). --}}
    @php
        $questions = $questions ?? [
            ['no' => 1, 'status' => 'answered'],
            ['no' => 2, 'status' => 'answered'],
            ['no' => 3, 'status' => 'current'],
            ['no' => 4, 'status' => 'flagged'],
        ];
        $statusStyle = [
            'answered' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
            'current' => 'bg-rose-600 text-white border-rose-600',
            'flagged' => 'bg-amber-100 text-amber-700 border-amber-200',
            'unanswered' => 'bg-white text-slate-500 border-slate-200',
            'grading' => 'bg-sky-100 text-sky-700 border-sky-200',
        ];
        $assessmentTitle = $assessmentModel->title ?? 'Đề ôn chương 3 — Cấu trúc dữ liệu';
        $resubmissionNote = isset($assessmentModel) && $assessmentModel->resubmission_policy
            ? 'Nộp lại theo quy tắc của đề'
            : 'Nộp lại tối đa 2 lần';
    @endphp

    {{-- Header sticky: tên đề, thời gian còn lại, lưu/nộp --}}
    <div class="sticky top-0 z-10 -mx-4 lg:-mx-6 px-4 lg:px-6 py-3 bg-white/90 backdrop-blur border-b border-slate-200 flex items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="font-medium text-slate-800">{{ $assessmentTitle }}</h1>
            <p class="text-xs text-slate-400">{{ $resubmissionNote }}</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="px-3 py-1.5 rounded-lg bg-rose-50 text-rose-600 text-sm font-medium">⏱ 24:38</div>
            <button type="button" class="px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium">Lưu nháp</button>
            <button type="button" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">Nộp bài</button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        {{-- Điều hướng câu --}}
        <div class="lg:col-span-1 order-2 lg:order-1">
            <div class="bg-white rounded-2xl border border-slate-200 p-4">
                <p class="text-xs text-slate-400 mb-3">{{ count($questions) }} câu · Hỗn hợp trắc nghiệm/điền đáp án/lập trình</p>
                <div class="grid grid-cols-5 lg:grid-cols-4 gap-2">
                    @foreach ($questions as $q)
                        <button type="button" class="w-9 h-9 rounded-lg border text-sm font-medium {{ $statusStyle[$q['status']] }}">
                            {{ $q['no'] }}
                        </button>
                    @endforeach
                </div>
                <div class="mt-4 space-y-1.5 text-xs text-slate-500">
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded bg-emerald-100 border border-emerald-200 inline-block"></span> Đã trả lời</div>
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded bg-amber-100 border border-amber-200 inline-block"></span> Đánh dấu xem lại</div>
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded bg-sky-100 border border-sky-200 inline-block"></span> Đang chấm</div>
                    <div class="flex items-center gap-2"><span class="w-3 h-3 rounded bg-white border border-slate-200 inline-block"></span> Chưa trả lời</div>
                </div>
            </div>
        </div>

        {{-- Nội dung câu hiện tại --}}
        <div class="lg:col-span-3 order-1 lg:order-2">
            <div class="bg-white rounded-2xl border border-slate-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <x-status-badge tone="info">Câu 3 · Trắc nghiệm · 1.5 điểm</x-status-badge>
                    <button type="button" class="text-xs text-amber-600 font-medium">🚩 Đánh dấu xem lại</button>
                </div>

                <p class="text-slate-800 font-medium mb-4">
                    Cấu trúc dữ liệu nào cho phép truy xuất phần tử theo nguyên tắc "vào trước ra trước" (FIFO)?
                </p>

                <div class="space-y-2">
                    @foreach (['Ngăn xếp (Stack)', 'Hàng đợi (Queue)', 'Danh sách liên kết (Linked List)', 'Cây nhị phân (Binary Tree)'] as $i => $opt)
                        <label class="flex items-center gap-3 px-4 py-3 rounded-xl border border-slate-200 hover:border-rose-300 cursor-pointer text-sm text-slate-700">
                            <input type="radio" name="q3" @checked($i === 1)>
                            {{ $opt }}
                        </label>
                    @endforeach
                </div>

                <div class="flex items-center justify-between mt-6 pt-4 border-t border-slate-100">
                    <button type="button" class="text-sm text-slate-500">‹ Câu trước</button>
                    <button type="button" class="text-sm text-rose-600 font-medium">Câu tiếp theo ›</button>
                </div>
            </div>

            <p class="text-xs text-slate-400 mt-3 text-center">Bài của bạn được tự động lưu — không cần lo mất dữ liệu khi mạng chập chờn (16 mục 5).</p>
        </div>
    </div>
@endsection
