{{-- SỬA 6/11 — thanh phân trang máy chủ của Kho giáo viên, CÙNG markup với admin/content/index.blade.php.
     Tham số: $pagination (page/perPage/lastPage/total), $pgCount (số dòng trang này), $pgUnit ("câu hỏi" / "đề"), $filterLink. --}}
@php
    $pgCurrent = (int) ($pagination['page'] ?? 1);
    $pgLast = (int) ($pagination['lastPage'] ?? 1);
    $pgTotalAll = (int) ($pagination['total'] ?? 0);
    $pgFrom = $pgTotalAll > 0 ? ($pgCurrent - 1) * (int) ($pagination['perPage'] ?? 10) + 1 : 0;
    $pgTo = $pgTotalAll > 0 ? $pgFrom + (int) $pgCount - 1 : 0;
    $pgItems = [];
    $pgPrev = 0;
    for ($i = 1; $i <= $pgLast; $i++) {
        if ($i === 1 || $i === $pgLast || abs($i - $pgCurrent) <= 1) {
            if ($pgPrev && $i - $pgPrev > 1) {
                $pgItems[] = '…';
            }
            $pgItems[] = $i;
            $pgPrev = $i;
        }
    }
@endphp
<nav aria-label="Phân trang {{ $pgUnit }}" class="akx-pg">
    <span>{{ $pgFrom }}–{{ $pgTo }} / {{ $pgTotalAll }} {{ $pgUnit }}{{ $pgLast > 1 ? ' · Trang '.$pgCurrent.'/'.$pgLast : '' }}</span>
    <div class="akx-pg__btns">
        @if ($pgCurrent > 1)
            <a href="{{ $filterLink(['page' => $pgCurrent > 2 ? $pgCurrent - 1 : null]) }}" aria-label="Trang trước" class="akx-pgb"><x-lucide name="chevron-left" /></a>
        @else
            <span aria-hidden="true" class="akx-pgb is-off"><x-lucide name="chevron-left" /></span>
        @endif

        @foreach ($pgItems as $item)
            @if ($item === '…')
                <span class="akx-dots">…</span>
            @elseif ($item === $pgCurrent)
                <span aria-current="page" class="akx-pgb is-on">{{ $item }}</span>
            @else
                <a href="{{ $filterLink(['page' => $item > 1 ? $item : null]) }}" aria-label="Trang {{ $item }}" class="akx-pgb">{{ $item }}</a>
            @endif
        @endforeach

        @if ($pgCurrent < $pgLast)
            <a href="{{ $filterLink(['page' => $pgCurrent + 1]) }}" aria-label="Trang sau" class="akx-pgb"><x-lucide name="chevron-right" /></a>
        @else
            <span aria-hidden="true" class="akx-pgb is-off"><x-lucide name="chevron-right" /></span>
        @endif
    </div>
</nav>
