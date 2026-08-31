@extends('layouts.student')

@section('title', 'Tài liệu của tôi')
@section('page-title', 'Tài liệu của tôi')

@section('content')
    @php
        $tabs = $tabs ?? [];
        $products = $products ?? [];
    @endphp

    <x-page-header title="📖 Tài liệu của tôi" subtitle="Sách, chuyên đề, bộ đề bạn đã mua hoặc kích hoạt — tải bài tập và học liệu đi kèm ngay tại đây." />

    <x-tabs :tabs="$tabs" />

    @if (empty($products))
        <x-empty-state title="Chưa có tài liệu nào trong mục này" description="Mua hoặc nhập mã kích hoạt ở trang Tài liệu để bắt đầu." actionLabel="Khám phá tài liệu" :actionHref="route('materials.index')" />
    @else
        <div class="mb-4 flex items-center gap-2">
            <div class="relative w-full sm:w-64">
                <input type="search" id="materials-search" placeholder="Tìm theo tên..."
                       class="w-full rounded-lg border border-slate-200 text-sm p-2.5 pl-9 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">🔍</span>
            </div>
            <span id="materials-count" class="text-xs text-slate-400 shrink-0">{{ count($products) }} tài liệu</span>
        </div>

        <div id="materials-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach ($products as $p)
                <div class="material-card bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col"
                     data-title="{{ mb_strtolower($p['title']) }}" x-data="{ open: false }">
                    <div class="p-5 flex items-start gap-3">
                        <div class="w-14 h-16 rounded-lg overflow-hidden shrink-0 bg-gradient-to-br from-rose-100 to-sky-50 flex items-center justify-center">
                            @if ($p['coverPath'])
                                <img src="{{ asset('storage/'.$p['coverPath']) }}" alt="Bìa {{ $p['title'] }}" class="w-full h-full object-cover">
                            @else
                                <span class="text-xl">📘</span>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-semibold text-slate-800 leading-snug text-sm line-clamp-2">{{ $p['title'] }}</h3>
                            <span class="inline-flex items-center gap-1 text-xs text-emerald-600 font-medium mt-1">
                                <span>✓</span> Đã sở hữu
                            </span>
                        </div>
                    </div>

                    <p class="px-5 pb-4 text-xs text-slate-400">
                        {{ count($p['resources']) }} tài nguyên
                        @if (count($p['exercises']) > 0)
                            · {{ count($p['exercises']) }} bài tập
                        @endif
                    </p>

                    @if (count($p['resources']) > 0 || count($p['exercises']) > 0)
                        <button type="button" @click="open = ! open"
                                class="mt-auto px-5 py-3 border-t border-slate-100 text-sm text-rose-600 font-medium flex items-center justify-between hover:bg-rose-50 transition">
                            <span x-text="open ? 'Thu gọn ︿' : 'Xem chi tiết ﹀'"></span>
                        </button>

                        <div x-show="open" x-cloak class="border-t border-slate-100 p-5 space-y-4">
                            @if (count($p['resources']) > 0)
                                <div>
                                    <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-2">Tài nguyên đính kèm</p>
                                    <div class="space-y-2">
                                        @foreach ($p['resources'] as $res)
                                            <a href="{{ route('access.resource', ['product' => $p['id'], 'kind' => $res['kind']]) }}" target="_blank" rel="noopener"
                                               class="flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:border-rose-200 hover:text-rose-600 transition">
                                                <span>{{ $res['icon'] }}</span> {{ $res['label'] }}
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if (count($p['exercises']) > 0)
                                <div>
                                    <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-2">🧪 Bài tập</p>
                                    <div class="space-y-2">
                                        @foreach ($p['exercises'] as $ex)
                                            <div class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg border border-slate-200">
                                                <div class="min-w-0">
                                                    <p class="text-sm text-slate-700 truncate">{{ $ex['title'] }}</p>
                                                    <p class="text-xs text-slate-400">{{ $ex['points'] }} điểm · {{ $ex['summary'] }}</p>
                                                </div>
                                                <form action="{{ route('student.practiceByQuestion.startExercise', $ex['id']) }}" method="POST" class="shrink-0">
                                                    @csrf
                                                    {{-- SỬA (fix "quay lại" sai trang): url()->full() trả về URL TUYỆT ĐỐI
                                                         (có scheme+host) — PracticeByQuestionController::startExercise() chỉ
                                                         nhận đường dẫn bắt đầu bằng đúng 1 dấu '/' (chặn open-redirect) nên
                                                         luôn bị coi là không hợp lệ và rơi về mặc định route('student.library.index')
                                                         (xem exercise-play.blade.php: $backUrl = $returnUrl ?? route(...)),
                                                         DÙ đang bấm "Làm bài" từ trang nào khác (vd tab Học liệu ở chi tiết
                                                         lớp) — request()->getRequestUri() trả về đúng path+query TƯƠNG ĐỐI
                                                         (bắt đầu bằng '/'), qua được kiểm tra và quay lại ĐÚNG trang đã bấm. --}}
                                                    <input type="hidden" name="return_url" value="{{ request()->getRequestUri() }}">
                                                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-rose-600 text-white text-xs font-medium hover:bg-rose-700 transition">Làm bài ›</button>
                                                </form>
                                            </div>
                                        @endforeach
                                    </div>
                                    <p class="text-xs text-slate-400 mt-2">Bài tập chưa có chấm tự động — bài làm sẽ được ghi nhận, chưa báo đúng/sai ngay.</p>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <p id="materials-empty-search" class="hidden text-sm text-slate-400 text-center py-10">Không tìm thấy tài liệu nào khớp từ khoá tìm kiếm.</p>
    @endif

    @push('scripts')
        <style>
            [x-cloak] { display: none !important; }
        </style>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var search = document.getElementById('materials-search');
                var cards = document.querySelectorAll('#materials-grid .material-card');
                var countLabel = document.getElementById('materials-count');
                var emptyMsg = document.getElementById('materials-empty-search');
                if (!search || !cards.length) return;

                search.addEventListener('input', function () {
                    var q = search.value.trim().toLowerCase();
                    var visible = 0;
                    cards.forEach(function (card) {
                        var match = !q || (card.getAttribute('data-title') || '').indexOf(q) !== -1;
                        card.style.display = match ? '' : 'none';
                        if (match) visible++;
                    });
                    if (countLabel) countLabel.textContent = visible + ' tài liệu';
                    if (emptyMsg) emptyMsg.classList.toggle('hidden', visible !== 0);
                });
            });
        </script>
    @endpush
@endsection
