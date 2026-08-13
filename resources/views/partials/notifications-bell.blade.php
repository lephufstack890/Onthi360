{{--
  Chuông thông báo dùng CHUNG mọi vai trò. Dữ liệu thật ($bellItems/$bellUnreadCount/
  $bellViewAllRoute) do View Composer trong App\Providers\AppServiceProvider bơm vào
  (nguồn: App\Services\NotificationService, kênh 'database' của Illuminate Notifications)
  — không cần sửa từng Controller vì bell được include ở layouts.app cho mọi role.
--}}
@php
    $bellItems = $bellItems ?? [];
    $bellUnreadCount = $bellUnreadCount ?? 0;
    $bellViewAllRoute = $bellViewAllRoute ?? null;
@endphp
<div x-data="{ open: false }" class="relative">
    <button type="button" @click="open = !open" @click.outside="open = false" class="relative text-slate-500" aria-label="Thông báo">
        🔔
        @if ($bellUnreadCount > 0)
            <span class="absolute -top-1 -right-1 min-w-[16px] h-4 px-0.5 rounded-full bg-rose-500 text-white text-[10px] flex items-center justify-center">{{ $bellUnreadCount > 9 ? '9+' : $bellUnreadCount }}</span>
        @endif
    </button>
    <div x-show="open" x-cloak x-transition class="absolute right-0 mt-2 w-80 bg-white rounded-xl border border-slate-200 shadow-lg z-20 overflow-hidden">
        <div class="max-h-96 overflow-y-auto divide-y divide-slate-100">
            @forelse ($bellItems as $n)
                <a href="{{ route('notifications.read', $n['id']) }}" class="flex items-start gap-2.5 p-3 hover:bg-slate-50 {{ !$n['read'] ? 'bg-rose-50/40' : '' }}">
                    <span class="text-lg shrink-0">{{ $n['icon'] }}</span>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-700 truncate">{{ $n['title'] }}</p>
                        <p class="text-xs text-slate-400 mt-0.5">{{ $n['time'] }}</p>
                    </div>
                </a>
            @empty
                <p class="text-sm text-slate-400 text-center p-6">Chưa có thông báo nào</p>
            @endforelse
        </div>
        @if ($bellViewAllRoute)
            <a href="{{ $bellViewAllRoute }}" class="block text-center text-sm text-rose-600 font-medium py-2.5 border-t border-slate-100 hover:bg-slate-50">Xem tất cả</a>
        @endif
    </div>
</div>
