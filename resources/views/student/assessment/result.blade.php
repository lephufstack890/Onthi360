{{--
  Route: student.assessment.result | Frame: STU-08/09
  Spec: 10.1 — kết quả nêu trạng thái nộp/chấm, điểm, thời gian, theo
  câu, verdict, lời giải/đáp án theo quy tắc công bố. Đề hỗn hợp: kết quả
  tổng "tạm tính" cho tới khi mọi câu chấm xong (6.3). Cuối trang có CTA
  nhẹ đánh giá tài liệu/lớp — không chặn hành trình học.
  TODO controller: truyền $attempt thật + $eligibleForReview từ
  App\Services\ReviewEligibilityService.
--}}
@extends('layouts.student')

@section('title', 'Kết quả')
@section('page-title', 'Kết quả bài làm')

@section('content')
    @php
        $isFinal = false; // true khi mọi câu đã chấm xong (6.3: "tạm tính" cho tới lúc đó)
        $score = 7.5; $total = 10;
        $breakdown = [
            ['no' => 1, 'type' => 'Trắc nghiệm', 'verdict' => 'Đúng', 'points' => '1.5/1.5', 'tone' => 'success'],
            ['no' => 2, 'type' => 'Điền đáp án', 'verdict' => 'Đúng', 'points' => '1.0/1.0', 'tone' => 'success'],
            ['no' => 3, 'type' => 'Trắc nghiệm', 'verdict' => 'Sai', 'points' => '0/1.5', 'tone' => 'danger'],
            ['no' => 7, 'type' => 'Lập trình', 'verdict' => 'Đang chấm', 'points' => '—', 'tone' => 'info'],
        ];
    @endphp

    <div class="rounded-3xl bg-gradient-to-br from-emerald-50 to-sky-50 p-8 mb-6 text-center">
        <p class="text-sm text-slate-500">{{ $isFinal ? 'Kết quả cuối cùng' : 'Kết quả tạm tính — còn câu đang chờ chấm' }}</p>
        <p class="text-4xl font-semibold text-slate-800 mt-2">{{ $score }} <span class="text-lg text-slate-400">/ {{ $total }}</span></p>
        <p class="text-sm text-slate-500 mt-2">Nộp lúc 21:42 · Thời gian làm bài: 38 phút</p>
        @if (!$isFinal)
            <x-status-badge tone="info">Đang chấm 1 câu lập trình</x-status-badge>
        @endif
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden mb-6">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr><th class="px-4 py-3">Câu</th><th class="px-4 py-3">Loại</th><th class="px-4 py-3">Verdict</th><th class="px-4 py-3 text-right">Điểm</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($breakdown as $b)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-700">Câu {{ $b['no'] }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $b['type'] }}</td>
                        <td class="px-4 py-3"><x-status-badge :tone="$b['tone']">{{ $b['verdict'] }}</x-status-badge></td>
                        <td class="px-4 py-3 text-right text-slate-600">{{ $b['points'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap gap-3 mb-8">
        <a href="{{ route('student.practice.index') }}" class="px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium">← Quay lại Luyện tập</a>
        <a href="#" class="px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium">Xem lời giải (nếu đã công bố)</a>
    </div>

    {{-- CTA đánh giá nhẹ nhàng — không chặn hành trình học (10.1, 9.6) --}}
    <div class="rounded-2xl bg-amber-50 border border-amber-100 p-5 flex items-center justify-between flex-wrap gap-3">
        <div>
            <p class="font-medium text-slate-700">Bạn thấy tài liệu này thế nào?</p>
            <p class="text-sm text-slate-500">Chia sẻ trải nghiệm giúp học sinh khác chọn đúng tài liệu hơn.</p>
        </div>
        <a href="{{ route('reviews.form', ['type' => 'material', 'id' => 1]) }}" class="px-4 py-2 rounded-lg bg-amber-500 text-white text-sm font-medium shrink-0">
            Đánh giá tài liệu này
        </a>
    </div>
@endsection
