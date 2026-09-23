{{--
    SỬA 23/9 (khách: "lịch sử làm bài dài quá, cho phân trang") — thanh chuyển trang dùng chung.

    Cố ý KHÔNG dùng $paginator->links() mặc định của Laravel: bản dựng CSS trên máy chủ là bản
    build sẵn (máy chủ không chạy được vite), mà view phân trang mặc định mang theo một bộ class
    Tailwind riêng chưa có trong bản build đó — in ra sẽ vỡ giao diện. Ở đây chỉ dùng đúng những
    class đã có sẵn trong dự án (lấy theo x-ws.pagination-note).

    Dùng:  @include('partials.pager', ['paginator' => $historyPaginator, 'unit' => 'bài đã nộp'])
--}}
@php
    $paginator = $paginator ?? null;
    $unit = $unit ?? 'kết quả';
    $window = 2; // số trang hiện hai bên trang hiện tại
@endphp

@if ($paginator !== null && $paginator->total() > 0)
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $from = $paginator->firstItem();
        $to = $paginator->lastItem();

        $pages = [];
        if ($last <= 7) {
            $pages = range(1, $last);
        } else {
            $start = max(1, $current - $window);
            $end = min($last, $current + $window);
            $pages = range($start, $end);
            if ($start > 1) {
                $pages = array_merge($start > 2 ? [1, '…'] : [1], $pages);
            }
            if ($end < $last) {
                $pages = array_merge($pages, $end < $last - 1 ? ['…', $last] : [$last]);
            }
        }

        $navBtn = 'grid h-8 w-8 place-items-center rounded-lg border border-sky-100 bg-white text-slate-500 transition hover:bg-sky-50 hover:text-[#126F91]';
        $navBtnOff = 'grid h-8 w-8 place-items-center rounded-lg border border-sky-100 bg-white text-slate-400 cursor-not-allowed opacity-40';
    @endphp

    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-sky-100 bg-white px-4 py-3 text-[11px] text-slate-500 shadow-[0_2px_8px_rgba(0,90,180,.04)]">
        <span>
            Hiển thị <strong class="font-bold text-slate-700">{{ $from }}–{{ $to }}</strong>
            / {{ $paginator->total() }} {{ $unit }}
        </span>

        @if ($last > 1)
            <div class="flex items-center gap-1">
                @if ($paginator->onFirstPage())
                    <span class="{{ $navBtnOff }}" aria-hidden="true"><x-lucide name="chevron-left" class="h-4 w-4" /></span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Trang trước" class="{{ $navBtn }}">
                        <x-lucide name="chevron-left" class="h-4 w-4" />
                    </a>
                @endif

                @foreach ($pages as $p)
                    @if ($p === '…')
                        <span class="px-1 text-slate-400">…</span>
                    @elseif ($p === $current)
                        <span aria-current="page"
                              class="grid h-8 min-w-8 place-items-center rounded-lg bg-[#126F91] px-2 text-[12px] font-bold text-white">{{ $p }}</span>
                    @else
                        <a href="{{ $paginator->url($p) }}"
                           class="grid h-8 min-w-8 place-items-center rounded-lg border border-sky-100 bg-white px-2 text-[12px] font-semibold text-slate-600 transition hover:bg-sky-50 hover:text-[#126F91]">{{ $p }}</a>
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Trang sau" class="{{ $navBtn }}">
                        <x-lucide name="chevron-right" class="h-4 w-4" />
                    </a>
                @else
                    <span class="{{ $navBtnOff }}" aria-hidden="true"><x-lucide name="chevron-right" class="h-4 w-4" /></span>
                @endif
            </div>
        @endif
    </div>
@endif
