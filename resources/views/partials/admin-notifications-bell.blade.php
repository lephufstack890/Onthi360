{{-- Chuông thông báo của khu quản trị.
     SỬA 12/9 — bản riêng cho admin (partials/notifications-bell.blade.php gốc giữ nguyên cho
     layouts/app). Đổi emoji 🔔 sang icon lucide và chỉnh lại bảng thả xuống cho khớp thiết kế.
     LOGIC GIỮ NGUYÊN: vẫn đọc $bellItems / $bellUnreadCount / $bellViewAllRoute như cũ. --}}
@php
    $bellItems = $bellItems ?? [];
    $bellUnreadCount = $bellUnreadCount ?? 0;
    $bellViewAllRoute = $bellViewAllRoute ?? null;
@endphp
<div x-data="{ open: false }" class="relative" @click.outside="open = false">
    <button type="button" @click="open = !open" aria-label="Thông báo"
            class="relative grid h-9 w-9 place-items-center rounded-xl text-slate-500 transition-colors hover:bg-sky-50 hover:text-blue-700">
        <x-lucide name="bell" class="h-4.5 w-4.5" />
        @if ($bellUnreadCount > 0)
            <span class="absolute right-1 top-1 grid h-4 min-w-[16px] place-items-center rounded-full bg-amber-400 px-0.5 text-[9px] font-black text-amber-950 ring-2 ring-white">{{ $bellUnreadCount > 9 ? '9+' : $bellUnreadCount }}</span>
        @endif
    </button>

    <div x-show="open" x-cloak x-transition
         class="absolute right-0 z-50 mt-2 w-72 overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-xl sm:w-80">
        <div class="border-b border-slate-100 px-3 py-2.5">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Thông báo</p>
        </div>
        <div class="max-h-96 divide-y divide-slate-100 overflow-y-auto">
            @forelse ($bellItems as $n)
                <a href="{{ route('notifications.read', $n['id']) }}"
                   class="flex items-start gap-2.5 p-3 transition-colors hover:bg-sky-50 {{ ! $n['read'] ? 'bg-blue-50/50' : '' }}">
                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-sky-50 text-sm">{{ $n['icon'] }}</span>
                    <span class="min-w-0">
                        <span class="block truncate text-xs font-bold text-slate-700">{{ $n['title'] }}</span>
                        <span class="mt-0.5 block text-[10px] text-slate-400">{{ $n['time'] }}</span>
                    </span>
                </a>
            @empty
                <p class="p-6 text-center text-xs text-slate-400">Chưa có thông báo nào</p>
            @endforelse
        </div>
        @if ($bellViewAllRoute)
            <a href="{{ $bellViewAllRoute }}"
               class="block border-t border-slate-100 py-2.5 text-center text-xs font-bold text-blue-600 transition-colors hover:bg-sky-50">Xem tất cả</a>
        @endif
    </div>
</div>
