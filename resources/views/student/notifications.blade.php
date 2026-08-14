{{--
  Route: student.notifications | Frame: STU-11 (phần thông báo)
  Dữ liệu thật do App\Http\Controllers\Student\NotificationController truyền vào, qua
  App\Services\NotificationService dùng chung mọi vai trò (kênh 'database' của Illuminate
  Notifications) — cùng nguồn với chuông thông báo global và teacher.notifications.index.
  Bấm vào 1 thông báo sẽ đánh dấu đã đọc + chuyển tới url gắn kèm (nếu có) qua route dùng
  chung notifications.read; "Đánh dấu tất cả đã đọc" qua notifications.readAll — trước đây
  trang này chỉ hiển thị tĩnh, không bấm được, không có nút đánh dấu tất cả như trang giáo
  viên tương đương.
--}}
@extends('layouts.student')

@section('title', 'Thông báo')
@section('page-title', 'Thông báo')

@section('content')
    @php
        $items = $items ?? [];
        $unreadCount = $unreadCount ?? 0;
        $subtitle = $unreadCount > 0 ? "Bạn có {$unreadCount} thông báo chưa đọc." : 'Bạn đã xem hết thông báo.';
    @endphp

    <x-page-header title="🔔 Thông báo" :subtitle="$subtitle">
        @if ($unreadCount > 0)
            <x-slot:actions>
                <form method="POST" action="{{ route('notifications.readAll') }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">Đánh dấu tất cả đã đọc</button>
                </form>
            </x-slot:actions>
        @endif
    </x-page-header>

    <div class="bg-white rounded-2xl border border-slate-200 divide-y divide-slate-100">
        @forelse ($items as $n)
            <a href="{{ route('notifications.read', $n['id']) }}" class="flex items-start gap-3 p-4 hover:bg-slate-50 {{ !$n['read'] ? 'bg-rose-50/40' : '' }}">
                <x-icon-tile :emoji="$n['icon']" :tone="$n['tone']" />
                <div class="flex-1">
                    <p class="text-sm font-medium text-slate-700">{{ $n['title'] }}</p>
                    <p class="text-sm text-slate-500 mt-0.5">{{ $n['text'] }}</p>
                    <p class="text-xs text-slate-400 mt-1">{{ $n['time'] }}</p>
                </div>
                @if (!$n['read'])
                    <span class="w-2 h-2 rounded-full bg-rose-500 mt-2 shrink-0"></span>
                @endif
            </a>
        @empty
            <div class="p-8">
                <x-empty-state title="Chưa có thông báo nào" />
            </div>
        @endforelse
    </div>
@endsection
