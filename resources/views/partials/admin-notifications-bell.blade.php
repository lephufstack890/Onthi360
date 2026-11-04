{{-- Chuông thông báo của khu quản trị.
     SỬA 12/9 — bản riêng cho admin (partials/notifications-bell.blade.php gốc giữ nguyên cho
     layouts/app). Đổi emoji 🔔 sang icon lucide và chỉnh lại bảng thả xuống cho khớp thiết kế.
     LOGIC GIỮ NGUYÊN: vẫn đọc $bellItems / $bellUnreadCount / $bellViewAllRoute như cũ. --}}
@php
    $bellItems = $bellItems ?? [];
    $bellUnreadCount = $bellUnreadCount ?? 0;
    $bellViewAllRoute = $bellViewAllRoute ?? null;
@endphp
{{-- SỬA 4/11 — chỉ hiện thông báo CHƯA ĐỌC, có nút ✓ "Đã đọc" từng cái và "Đọc tất cả" (partials.bell-actions). --}}
@include('partials.bell-actions')
<div x-data="Object.assign({ open: false }, window.oiBell ? oiBell({{ (int) $bellUnreadCount }}) : { bellCount: {{ (int) $bellUnreadCount }}, bellLeft: 0 })" class="relative" @click.outside="open = false">
    <button type="button" @click="open = !open" aria-label="Thông báo"
            class="relative grid h-9 w-9 place-items-center rounded-xl text-slate-500 transition-colors hover:bg-sky-50 hover:text-blue-700">
        <x-lucide name="bell" class="h-4.5 w-4.5" />
        <span x-show="bellCount > 0" x-text="bellCount > 9 ? '9+' : bellCount" @if ($bellUnreadCount <= 0) style="display:none" @endif
              class="absolute right-1 top-1 grid h-4 min-w-[16px] place-items-center rounded-full bg-amber-400 px-0.5 text-[9px] font-black text-amber-950 ring-2 ring-white">{{ $bellUnreadCount > 9 ? '9+' : $bellUnreadCount }}</span>
    </button>

    <div x-show="open" x-cloak x-transition
         class="absolute right-0 z-50 mt-2 w-72 overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-xl sm:w-80">
        <div class="flex items-center justify-between border-b border-slate-100 px-3 py-2.5">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Thông báo</p>
            <button type="button" class="bel-all" x-show="bellCount > 0" @click="bellReadAll()" title="Đánh dấu tất cả đã đọc"><x-lucide name="check" />Đọc tất cả</button>
        </div>
        <div class="max-h-96 divide-y divide-slate-100 overflow-y-auto">
            @foreach ($bellItems as $n)
                <div class="bel-item" data-bell-id="{{ $n['id'] }}">
                <a href="{{ route('notifications.read', $n['id']) }}"
                   class="flex items-start gap-2.5 p-3 transition-colors hover:bg-sky-50 {{ ! $n['read'] ? 'bg-blue-50/50' : '' }}">
                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-sky-50 text-sm">{{ $n['icon'] }}</span>
                    <span class="min-w-0">
                        <span class="block truncate text-xs font-bold text-slate-700">{{ $n['title'] }}</span>
                        <span class="mt-0.5 block text-[10px] text-slate-400">{{ $n['time'] }}</span>
                    </span>
                </a>
                <button type="button" class="bel-x" title="Đánh dấu đã đọc" aria-label="Đánh dấu đã đọc" @click.stop="bellReadOne(@js($n['id']))"><x-lucide name="check" /></button>
                </div>
            @endforeach
            <p class="p-6 text-center text-xs text-slate-400" x-show="bellLeft === 0" @if (! empty($bellItems)) style="display:none" @endif>
                <span x-show="bellCount === 0">Bạn đã xem hết thông báo</span>
                <span x-show="bellCount > 0" style="display:none">Còn <b x-text="bellCount"></b> thông báo chưa đọc — bấm Xem tất cả</span>
            </p>
        </div>
        @if ($bellViewAllRoute)
            <a href="{{ $bellViewAllRoute }}"
               class="block border-t border-slate-100 py-2.5 text-center text-xs font-bold text-blue-600 transition-colors hover:bg-sky-50">Xem tất cả</a>
        @endif
    </div>
</div>
