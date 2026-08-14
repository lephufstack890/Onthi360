{{--
  Route: practice.index | Frame: PUB-07
  Spec: 4.1 + 10.1 (Luyện tập công khai: khách/logged-in/lọc rỗng).
  Dữ liệu thật do App\Http\Controllers\Public\PracticeController truyền vào qua
  App\Services\Public\PracticeService::indexData() — cùng nguồn với tab "Tự luyện" của
  App\Services\Student\PracticeService để không lệch danh sách giữa 2 nơi.
--}}
@extends('layouts.guest')

@section('title', 'Luyện tập')

@section('content')
    @php
        $items = $items ?? [];
        $canTakeDirectly = $canTakeDirectly ?? false;
    @endphp

    {{-- Hero giới thiệu --}}
    <div class="bg-gradient-to-br from-emerald-50 via-white to-sky-50">
        <div class="max-w-7xl mx-auto px-4 py-12 lg:py-16">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white text-emerald-600 text-xs font-medium mb-4 shadow-sm">📝 Kho luyện tập công khai</span>
            <h1 class="text-2xl lg:text-3xl font-semibold text-slate-800">Luyện đến khi thành thạo<br class="hidden lg:block">— chấm tự động ngay khi nộp</h1>
            <p class="text-slate-500 mt-3 max-w-xl">Chấm được câu lập trình, trắc nghiệm và điền đáp án — trong cùng một đề (6.3). Ai cũng xem được đề; đăng nhập để nộp bài và lưu lại kết quả.</p>
            <div class="flex flex-wrap gap-6 mt-6 text-sm">
                <div><p class="text-2xl font-semibold text-slate-800">{{ count($items) }}+</p><p class="text-slate-400">bài luyện tập công khai</p></div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 py-10">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @forelse ($items as $it)
                <div class="rounded-2xl bg-white border border-slate-200 p-5 hover:shadow-lg hover:-translate-y-0.5 transition-all">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-2xl">📝</span>
                        <x-status-badge tone="info">{{ $it['itemsCount'] }} câu</x-status-badge>
                    </div>
                    <h3 class="font-medium text-slate-800">{{ $it['title'] }}</h3>
                    <div class="flex items-center justify-between mt-3 pt-3 border-t border-slate-100 text-xs text-slate-400">
                        <span>{{ $it['totalPoints'] }} điểm</span>
                        <span>{{ $it['durationMinutes'] ? $it['durationMinutes'].' phút' : 'Không giới hạn' }}</span>
                    </div>
                    @if ($canTakeDirectly)
                        <a href="{{ route('student.assessment.take', $it['id']) }}" class="inline-flex items-center gap-1 mt-4 text-sm font-medium text-rose-600">Làm bài ›</a>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex items-center gap-1 mt-4 text-sm font-medium text-rose-600">Đăng nhập để làm bài ›</a>
                    @endif
                </div>
            @empty
                <div class="col-span-full">
                    <x-empty-state title="Chưa có bài luyện tập công khai nào" description="Quay lại sau để xem bài mới." />
                </div>
            @endforelse
        </div>
    </div>
@endsection
