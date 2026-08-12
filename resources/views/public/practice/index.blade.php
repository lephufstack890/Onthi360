{{--
  Route: practice.index | Frame: PUB-07
  Spec: 4.1 + 10.1 (Luyện tập công khai: khách/logged-in/lọc rỗng).
  TODO controller: truyền $questions (paginate) theo bộ lọc — hiện là dữ
  liệu minh họa để dựng UI.
--}}
@extends('layouts.guest')

@section('title', 'Luyện tập')

@section('content')
    @php
        $items = [
            ['title' => 'Sắp xếp nổi bọt cơ bản', 'type' => 'Lập trình', 'icon' => '💻', 'difficulty' => 'Dễ', 'tone' => 'success', 'attempts' => 1240],
            ['title' => 'Trắc nghiệm: Cấu trúc điều khiển', 'type' => 'Trắc nghiệm', 'icon' => '🔤', 'difficulty' => 'Trung bình', 'tone' => 'warning', 'attempts' => 860],
            ['title' => 'Điền đáp án: Độ phức tạp thuật toán', 'type' => 'Điền đáp án', 'icon' => '✏️', 'difficulty' => 'Khó', 'tone' => 'danger', 'attempts' => 410],
            ['title' => 'Tìm kiếm nhị phân', 'type' => 'Lập trình', 'icon' => '💻', 'difficulty' => 'Trung bình', 'tone' => 'warning', 'attempts' => 995],
            ['title' => 'Trắc nghiệm: Kiểu dữ liệu cơ bản', 'type' => 'Trắc nghiệm', 'icon' => '🔤', 'difficulty' => 'Dễ', 'tone' => 'success', 'attempts' => 1580],
            ['title' => 'Đệ quy: Tháp Hà Nội', 'type' => 'Lập trình', 'icon' => '💻', 'difficulty' => 'Khó', 'tone' => 'danger', 'attempts' => 320],
        ];
    @endphp

    {{-- Hero giới thiệu --}}
    <div class="bg-gradient-to-br from-emerald-50 via-white to-sky-50">
        <div class="max-w-7xl mx-auto px-4 py-12 lg:py-16">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white text-emerald-600 text-xs font-medium mb-4 shadow-sm">📝 Kho luyện tập công khai</span>
            <h1 class="text-2xl lg:text-3xl font-semibold text-slate-800">Luyện đến khi thành thạo<br class="hidden lg:block">— chấm tự động ngay khi nộp</h1>
            <p class="text-slate-500 mt-3 max-w-xl">Chấm được câu lập trình, trắc nghiệm và điền đáp án — trong cùng một đề (6.3). Ai cũng xem được đề; đăng nhập để nộp bài và lưu lại kết quả.</p>
            <div class="flex flex-wrap gap-6 mt-6 text-sm">
                <div><p class="text-2xl font-semibold text-slate-800">{{ count($items) }}+</p><p class="text-slate-400">bài luyện tập công khai</p></div>
                <div><p class="text-2xl font-semibold text-slate-800">5.4k+</p><p class="text-slate-400">lượt làm bài</p></div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 py-10">
        {{-- Bộ lọc — UI tĩnh, TODO nối filter thật theo query string --}}
        <div class="flex flex-wrap gap-2 mb-8 text-sm">
            <button type="button" class="px-3 py-1.5 rounded-full bg-rose-50 text-rose-600 font-medium">Tất cả</button>
            <button type="button" class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-500 hover:border-rose-200">💻 Lập trình</button>
            <button type="button" class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-500 hover:border-rose-200">🔤 Trắc nghiệm</button>
            <button type="button" class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-500 hover:border-rose-200">✏️ Điền đáp án</button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @forelse ($items as $it)
                <div class="rounded-2xl bg-white border border-slate-200 p-5 hover:shadow-lg hover:-translate-y-0.5 transition-all">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-2xl">{{ $it['icon'] }}</span>
                        <x-status-badge tone="info">{{ $it['type'] }}</x-status-badge>
                    </div>
                    <h3 class="font-medium text-slate-800">{{ $it['title'] }}</h3>
                    <div class="flex items-center justify-between mt-3 pt-3 border-t border-slate-100">
                        <x-status-badge :tone="$it['tone']">Độ khó: {{ $it['difficulty'] }}</x-status-badge>
                        <span class="text-xs text-slate-400">✓ {{ number_format($it['attempts']) }} lượt làm</span>
                    </div>
                    <a href="{{ route('login') }}" class="inline-flex items-center gap-1 mt-4 text-sm font-medium text-rose-600">Đăng nhập để làm bài ›</a>
                </div>
            @empty
                <div class="col-span-full">
                    <x-empty-state title="Không có bài phù hợp bộ lọc" description="Thử bỏ bộ lọc để xem toàn bộ bài luyện tập." />
                </div>
            @endforelse
        </div>
    </div>
@endsection
