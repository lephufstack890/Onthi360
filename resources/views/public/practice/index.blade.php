{{--
  Route: practice.index | Frame: PUB-07
  Spec: 4.1 + 10.1 (Luyện tập công khai: khách/logged-in/lọc rỗng).
  TODO controller: truyền $questions (paginate) theo bộ lọc.
--}}
@extends('layouts.guest')

@section('title', 'Luyện tập')

@section('content')
    @php
        $items = [
            ['title' => 'Sắp xếp nổi bọt cơ bản', 'type' => 'Lập trình', 'difficulty' => 'Dễ'],
            ['title' => 'Trắc nghiệm: Cấu trúc điều khiển', 'type' => 'Trắc nghiệm', 'difficulty' => 'Trung bình'],
            ['title' => 'Điền đáp án: Độ phức tạp thuật toán', 'type' => 'Điền đáp án', 'difficulty' => 'Khó'],
        ];
    @endphp

    <div class="max-w-7xl mx-auto px-4 py-10">
        <x-page-header title="Luyện tập" subtitle="Kho bài công khai — đăng nhập để bắt đầu, nộp bài và lưu kết quả (1.3)." />

        <div class="flex gap-2 mb-6 text-sm">
            <button type="button" class="px-3 py-1.5 rounded-full bg-rose-50 text-rose-600 font-medium">Tất cả</button>
            <button type="button" class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-500">Lập trình</button>
            <button type="button" class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-500">Trắc nghiệm</button>
            <button type="button" class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-500">Điền đáp án</button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse ($items as $it)
                <div class="rounded-2xl bg-white border border-slate-200 p-5">
                    <x-status-badge tone="info">{{ $it['type'] }}</x-status-badge>
                    <h3 class="font-medium text-slate-800 mt-2">{{ $it['title'] }}</h3>
                    <p class="text-xs text-slate-400 mt-1">Độ khó: {{ $it['difficulty'] }}</p>
                    <a href="{{ route('login') }}" class="inline-block mt-3 text-sm text-rose-600 font-medium">Đăng nhập để làm bài ›</a>
                </div>
            @empty
                <x-empty-state title="Không có bài phù hợp bộ lọc" />
            @endforelse
        </div>
    </div>
@endsection
