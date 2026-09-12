{{-- Thân của menu trái, dùng chung cho cả 4 khu — chuyển đúng <aside> + <IconButton> của
     source (education-main/src/components/RoleWorkspace.jsx).

     Bên gọi truyền vào:
       $items        mảng ['label','icon','href','active'] — DO TỪNG SIDEBAR TỰ DỰNG, giữ
                     nguyên route và điều kiện "đang mở" vốn có của khu đó.
                     Thêm khoá 'badge' (số) thì hiện viên số việc tồn bên phải nhãn; khu nào
                     không truyền thì không đổi gì.
       $wsNavTitle   nhãn nhỏ phía trên ("Không gian học tập"...)
       $wsUserName / $wsRoleLabel  hiển thị ở đầu thẻ
       $wsNavCompact true khi nhúng vào ngăn kéo của màn hình nhỏ
       $wsFooter     (tuỳ chọn) khối gợi ý ở cuối thẻ --}}
@php
    $wsNavCompact = $wsNavCompact ?? false;
    $wsNavTitle = $wsNavTitle ?? 'Khu làm việc';
@endphp

<div class="{{ $wsNavCompact ? '' : 'rounded-2xl border border-sky-100 bg-white p-2 shadow-[0_2px_8px_rgba(0,90,180,.04)] lg:rounded-3xl lg:p-3' }}">

    @unless ($wsNavCompact)
        <div class="border-b border-slate-100 px-2 pb-3 pt-1">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $wsNavTitle }}</p>
            <div class="mt-2 flex items-center gap-2.5">
                <x-ws.avatar :name="$wsUserName ?? ''" />
                <span class="min-w-0">
                    <span class="block truncate text-[13px] font-bold text-slate-700">{{ $wsUserName }}</span>
                    <span class="block text-[11px] text-slate-400">{{ $wsRoleLabel }}</span>
                </span>
            </div>
        </div>
    @endunless

    <nav aria-label="{{ $wsNavTitle }}"
         class="{{ $wsNavCompact ? 'grid grid-cols-2 gap-1.5 sm:grid-cols-3' : 'mt-2 flex min-w-0 flex-col gap-1' }}">
        @foreach ($items as $item)
            <a href="{{ $item['href'] }}"
               @if ($item['active']) aria-current="page" @endif
               class="flex min-h-11 w-full shrink-0 items-center gap-3 rounded-xl px-3 py-2.5 text-left text-xs font-semibold transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-300 {{ $item['active'] ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'text-slate-600 hover:bg-sky-50 hover:text-blue-700' }}">
                <x-lucide :name="$item['icon']" class="h-4 w-4 shrink-0" />
                <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                @if (($item['badge'] ?? 0) > 0)
                    <span aria-label="{{ $item['badge'] }} việc chưa xử lý"
                          class="shrink-0 rounded-md px-1.5 py-0.5 text-[10px] font-black leading-none {{ $item['active'] ? 'bg-white/25 text-white' : 'bg-rose-100 text-rose-700' }}">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                @endif
            </a>
        @endforeach
    </nav>

    @unless ($wsNavCompact)
        @isset($wsFooter)
            <div class="mt-3 rounded-2xl border border-blue-100 bg-gradient-to-br from-sky-50 to-blue-50 p-3">
                {{ $wsFooter }}
            </div>
        @endisset
    @endunless
</div>
