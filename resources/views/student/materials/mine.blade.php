@extends('layouts.student')

@section('title', 'Tài liệu của tôi')
@section('page-title', 'Tài liệu của tôi')

@section('content')
    @php
        $tabs = $tabs ?? [];
        $products = $products ?? [];

        // Nhãn loại đang mở, lấy từ CHÍNH dải tab do LibraryService dựng (không đặt nhãn mới):
        // bỏ emoji ở đầu để in lên bìa thẻ — "📘 Sách" -> "Sách".
        $activeTabLabel = collect($tabs)->firstWhere('active', true)['label'] ?? '';
        $activeTabLabel = trim(preg_replace('/^[^\p{L}]+/u', '', $activeTabLabel));
    @endphp

    <x-ws.page-header title="Tài liệu của tôi" icon="book-open" subtitle="Sách, chuyên đề, bộ đề bạn đã mua hoặc kích hoạt — mở đọc, tải bài tập và học liệu đi kèm ngay tại đây." />

    <x-ws.tabs :tabs="$tabs" />

    @if (empty($products))
        <x-ws.empty-state title="Chưa có tài liệu nào trong mục này" description="Mua hoặc nhập mã kích hoạt ở trang Tài liệu để bắt đầu." actionLabel="Khám phá tài liệu" :actionHref="route('materials.index')" />
    @else
        {{-- ══════ THANH TÌM KIẾM ══════ --}}
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <div class="relative w-full sm:w-72">
                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[#7FA5B8]"><x-lucide name="search" class="h-4 w-4" /></span>
                <input type="search" id="materials-search" placeholder="Tìm theo tên tài liệu…"
                       class="w-full rounded-xl border border-[#DDEAF0] bg-white py-2.5 pl-9 pr-3 text-[13px] text-[#123B68] placeholder:text-[#8FAABB] transition focus:border-[#9DC8D7] focus:outline-none focus:ring-2 focus:ring-[#CFE6EF]">
            </div>
            <span id="materials-count" class="shrink-0 rounded-full border border-[#DDEAF0] bg-[#F0F8FB] px-3 py-1.5 text-[11px] font-semibold text-[#466278]">{{ count($products) }} tài liệu</span>
        </div>

        {{-- ══════ LƯỚI THẺ TÀI LIỆU ══════ --}}
        <div id="materials-grid" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($products as $p)
                <article class="material-card group flex flex-col overflow-hidden rounded-3xl border border-[#DDEAF0] bg-white shadow-[0_4px_16px_rgba(0,100,220,0.05)] transition-all duration-300 hover:border-[#9DC8D7] hover:shadow-[0_14px_30px_-16px_rgba(15,80,140,.35)]"
                         data-title="{{ mb_strtolower($p['title']) }}" x-data="{ open: false }">

                    {{-- Bìa --}}
                    <div class="relative flex h-44 items-center justify-center overflow-hidden bg-gradient-to-br from-[#F3F9FC] to-[#E4F0F6] p-3">
                        @if ($p['coverPath'])
                            <img src="{{ asset('storage/'.$p['coverPath']) }}" alt="Bìa {{ $p['title'] }}"
                                 class="h-full w-auto object-contain drop-shadow-md transition-transform duration-300 group-hover:scale-105">
                        @else
                            <span class="grid h-16 w-16 place-items-center rounded-2xl bg-white/80 text-[#23869B] shadow-inner"><x-lucide name="book-open" class="h-7 w-7" /></span>
                        @endif

                        @if ($activeTabLabel !== '')
                            <span class="absolute left-3 top-3 rounded-full border border-[#CFE6EF] bg-white/95 px-2.5 py-1 text-[10px] font-bold text-[#0F5B86] shadow-sm backdrop-blur-sm">{{ $activeTabLabel }}</span>
                        @endif
                    </div>

                    {{-- Thân thẻ --}}
                    <div class="flex flex-1 flex-col p-4">
                        {{-- Huy hiệu quyền — y như bản mẫu: "Đã sở hữu" + hạn dùng thật từ AccessRight --}}
                        <div class="mb-2 flex flex-wrap items-center gap-1.5">
                            <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-1 text-[10px] font-semibold text-emerald-700">
                                <x-lucide name="check-circle" class="h-3 w-3" />Đã sở hữu
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2 py-1 text-[10px] font-semibold text-amber-700">
                                <x-lucide name="clock" class="h-3 w-3" />{{ $p['access']['remainingLabel'] }}
                            </span>
                        </div>

                        <h3 class="mb-2 line-clamp-2 text-[13.5px] font-extrabold leading-snug text-[#123B68] transition-colors group-hover:text-[#126F91]">{{ $p['title'] }}</h3>

                        {{-- Số liệu thật của sản phẩm --}}
                        <div class="mb-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] font-medium text-[#61798B]">
                            @if ($p['lessonCount'] > 0)
                                <span class="inline-flex items-center gap-1"><x-lucide name="file-text" class="h-3.5 w-3.5 text-[#2D7FA3]" />{{ $p['lessonCount'] }} {{ $p['lessonWord'] }}</span>
                            @endif
                            @if (count($p['resources']) > 0)
                                <span class="inline-flex items-center gap-1"><x-lucide name="download" class="h-3.5 w-3.5 text-[#2D7FA3]" />{{ count($p['resources']) }} tài nguyên</span>
                            @endif
                            @if (count($p['exercises']) > 0)
                                <span class="inline-flex items-center gap-1"><x-lucide name="layers" class="h-3.5 w-3.5 text-[#AF7C32]" />{{ count($p['exercises']) }} bài tập</span>
                            @endif
                        </div>

                        {{-- Hàng hành động --}}
                        <div class="mt-auto flex items-center justify-between gap-2 border-t border-[#E9F1F5] pt-3">
                            @if ($p['readHref'])
                                <a href="{{ $p['readHref'] }}"
                                   class="inline-flex min-h-9 items-center gap-1.5 rounded-xl bg-blue-600 px-3.5 py-2 text-[12px] font-bold text-white shadow-sm shadow-blue-200 transition hover:bg-blue-700">
                                    <x-lucide name="book-open" class="h-3.5 w-3.5" />Đọc tài liệu<x-lucide name="chevron-right" class="h-3.5 w-3.5" />
                                </a>
                            @else
                                {{-- Sản phẩm chưa có bài nào gắn file PDF — nói thật thay vì đưa nút bấm không ra gì. --}}
                                <span class="inline-flex min-h-9 items-center gap-1.5 rounded-xl border border-[#DDEAF0] bg-[#F5F8FA] px-3 py-2 text-[11.5px] font-semibold text-[#8399A8]" title="Sản phẩm chưa có bài đọc nào đính kèm file PDF">
                                    <x-lucide name="lock" class="h-3.5 w-3.5" />Chưa có nội dung đọc
                                </span>
                            @endif

                            @if (count($p['resources']) > 0 || count($p['exercises']) > 0)
                                <button type="button" @click="open = ! open" :aria-expanded="open ? 'true' : 'false'"
                                        class="inline-flex min-h-9 shrink-0 items-center gap-1 rounded-xl border border-[#DDEAF0] px-2.5 py-2 text-[11.5px] font-semibold text-[#466278] transition hover:border-[#9DC8D7] hover:bg-[#F0F8FB] hover:text-[#126F91]">
                                    <span x-text="open ? 'Thu gọn' : 'Tài nguyên'"></span>
                                    {{-- Mũi tên xoay bằng Alpine đặt trên THẺ THƯỜNG, không đặt ::class lên
                                         component Blade (x-lucide dùng $attributes->merge cho 'class', trộn
                                         thêm ':class' vào đó dễ sinh thuộc tính lạ). --}}
                                    <span class="inline-flex transition-transform" :class="open ? 'rotate-180' : ''"><x-lucide name="chevron-down" class="h-3.5 w-3.5" /></span>
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- ══════ NGĂN KÉO: TÀI NGUYÊN + BÀI TẬP (logic giữ nguyên như cũ) ══════ --}}
                    @if (count($p['resources']) > 0 || count($p['exercises']) > 0)
                        <div x-show="open" x-cloak class="space-y-4 border-t border-[#E9F1F5] bg-[#F8FBFC] p-4">
                            @if (count($p['resources']) > 0)
                                <div>
                                    <p class="mb-2 text-[10px] font-bold uppercase tracking-wide text-[#7FA5B8]">Tài nguyên đính kèm</p>
                                    <div class="space-y-2">
                                        @foreach ($p['resources'] as $res)
                                            <a href="{{ route('access.resource', ['product' => $p['id'], 'kind' => $res['kind']]) }}" target="_blank" rel="noopener"
                                               class="flex items-center gap-2 rounded-xl border border-[#DDEAF0] bg-white px-3 py-2 text-[12.5px] font-medium text-[#466278] transition hover:border-[#9DC8D7] hover:bg-[#F0F8FB] hover:text-[#126F91]">
                                                <span>{{ $res['icon'] }}</span>{{ $res['label'] }}
                                                <x-lucide name="download" class="ml-auto h-3.5 w-3.5 text-[#9DB6C4]" />
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if (count($p['exercises']) > 0)
                                <div>
                                    <p class="mb-2 text-[10px] font-bold uppercase tracking-wide text-[#7FA5B8]">Bài tập</p>
                                    <div class="space-y-2">
                                        @foreach ($p['exercises'] as $ex)
                                            <div class="flex items-center justify-between gap-3 rounded-xl border border-[#DDEAF0] bg-white px-3 py-2.5">
                                                <div class="min-w-0">
                                                    <p class="truncate text-[12.5px] font-semibold text-[#123B68]">{{ $ex['title'] }}</p>
                                                    <p class="text-[11px] text-[#7A92A3]">{{ $ex['points'] }} điểm · {{ $ex['summary'] }}</p>
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
                                                    <button type="submit" class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-xl bg-[#2F9E72] px-3 py-1.5 text-[11.5px] font-bold text-white shadow-sm transition hover:bg-[#278761]">Làm bài<x-lucide name="chevron-right" class="h-3.5 w-3.5" /></button>
                                                </form>
                                            </div>
                                        @endforeach
                                    </div>
                                    <p class="mt-2 text-[11px] text-[#7A92A3]">Bài tập chưa có chấm tự động — bài làm sẽ được ghi nhận, chưa báo đúng/sai ngay.</p>
                                </div>
                            @endif
                        </div>
                    @endif
                </article>
            @endforeach
        </div>

        <p id="materials-empty-search" class="hidden rounded-3xl border border-dashed border-[#CFE6EF] bg-white py-10 text-center text-[13px] text-[#7A92A3]">Không tìm thấy tài liệu nào khớp từ khoá tìm kiếm.</p>
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
