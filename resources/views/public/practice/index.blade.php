@extends('layouts.guest')

@section('title', 'Luyện tập')

@section('content')
    @php
        $items = $items ?? [];
        $canTakeDirectly = $canTakeDirectly ?? false;
        $cardAccent = fn (bool $hasCoding) => $hasCoding
            ? ['tone' => 'amber', 'bar' => 'from-amber-400 to-amber-300']
            : ['tone' => 'emerald', 'bar' => 'from-emerald-400 to-emerald-300'];
    @endphp

    {{-- Hero giới thiệu --}}
    <div class="bg-gradient-to-br from-emerald-50 via-white to-sky-50">
        <div class="max-w-7xl mx-auto px-4 py-12 lg:py-16 flex items-center justify-between flex-wrap gap-6">
            <div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white text-emerald-600 text-xs font-medium mb-4 shadow-sm">📝 Kho luyện tập công khai</span>
                <h1 class="text-2xl lg:text-3xl font-semibold text-slate-800">Luyện đến khi thành thạo<br class="hidden lg:block">— chấm tự động ngay khi nộp</h1>
                <p class="text-slate-500 mt-3 max-w-xl">Chấm được câu lập trình, trắc nghiệm và điền đáp án — trong cùng một đề (6.3). Ai cũng xem được đề; đăng nhập để nộp bài và lưu lại kết quả.</p>
                <div class="flex flex-wrap gap-6 mt-6 text-sm">
                    <div><p class="text-2xl font-semibold text-slate-800">{{ count($items) }}+</p><p class="text-slate-400">bài luyện tập công khai</p></div>
                </div>
            </div>
            <div class="text-6xl hidden sm:block">🎯</div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 py-10">
        {{-- SỬA 24/8 — thêm lối "Luyện tập theo câu" (lọc theo chuyên đề THẬT — Tag — và dạng
             câu hỏi, luyện từng câu một, bấm "Câu tiếp theo ›" biết đúng/sai ngay) ra trang công
             khai này, mirror đúng CTA đã có sẵn ở resources/views/student/practice/index.blade.php.
             Route student.practiceByQuestion.setup nằm sau middleware auth+role:student — khách
             bấm vào sẽ tự được chuyển sang đăng nhập (không cần code thêm gì), học sinh đã đăng
             nhập vào thẳng màn chọn bộ lọc rồi luyện. KHÔNG lặp lại UI bộ lọc ở đây — dùng lại
             nguyên form lọc đã có ở student.practiceByQuestion.setup. --}}
        <a href="{{ $canTakeDirectly ? route('student.practiceByQuestion.setup') : route('login') }}"
           class="mb-8 flex items-center justify-between gap-4 rounded-2xl border border-sky-200 bg-gradient-to-r from-sky-50 to-cyan-50 p-5 lg:p-6 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200">
            <div class="flex items-center gap-3">
                <x-icon-tile emoji="🧠" tone="sky" />
                <div>
                    <h3 class="font-semibold text-slate-800">Luyện tập theo câu</h3>
                    <p class="text-sm text-slate-500 mt-0.5">Chọn chuyên đề và dạng câu hỏi muốn ôn — hệ thống trộn ngẫu nhiên câu hỏi, luyện từng câu một, biết đúng/sai ngay lập tức.</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1 text-sm font-medium text-sky-600 shrink-0">{{ $canTakeDirectly ? 'Bắt đầu' : 'Đăng nhập để bắt đầu' }} <span aria-hidden="true">→</span></span>
        </a>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @forelse ($items as $it)
                @php $accent = $cardAccent($it['hasCoding'] ?? false); @endphp
                <a href="{{ $canTakeDirectly ? route('student.assessment.take', $it['id']) : route('login') }}"
                   class="group relative flex flex-col h-full rounded-2xl bg-white border border-slate-200 p-5 pt-6 overflow-hidden hover:shadow-lg hover:-translate-y-0.5 transition-all">
                    <span class="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r {{ $accent['bar'] }}"></span>

                    <div class="flex items-center justify-between mb-3">
                        <x-icon-tile emoji="📝" :tone="$accent['tone']" />
                        <div class="flex items-center gap-1.5">
                            @if ($it['hasCoding'] ?? false)
                                <x-status-badge tone="warning">💻 Có lập trình</x-status-badge>
                            @endif
                            <x-status-badge tone="info">{{ $it['itemsCount'] }} câu</x-status-badge>
                        </div>
                    </div>

                    <h3 class="font-semibold text-slate-800 leading-snug line-clamp-2">{{ $it['title'] }}</h3>

                    <div class="flex items-center justify-between mt-3 pt-3 border-t border-slate-100 text-xs text-slate-400">
                        <span>{{ $it['totalPoints'] }} điểm</span>
                        <span>{{ $it['durationMinutes'] ? $it['durationMinutes'].' phút' : 'Không giới hạn' }}</span>
                    </div>

                    <div class="mt-auto pt-4 flex items-center justify-end">
                        <span class="inline-flex items-center gap-1 text-sm font-medium text-rose-600 group-hover:gap-2 transition-all">
                            {{ $canTakeDirectly ? 'Làm bài' : 'Đăng nhập để làm bài' }}
                            <span aria-hidden="true">→</span>
                        </span>
                    </div>
                </a>
            @empty
                <div class="col-span-full">
                    <x-empty-state title="Chưa có bài luyện tập công khai nào" description="Quay lại sau để xem bài mới." />
                </div>
            @endforelse
        </div>
    </div>
@endsection
