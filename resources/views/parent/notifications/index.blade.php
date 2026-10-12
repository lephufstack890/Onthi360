{{--
  Route: parent.notifications.index
  Spec: 10.3 ("nhận xét/thông báo") — thông báo dùng chung mọi vai trò qua
  App\Services\NotificationService (kênh 'database' của Illuminate Notifications), cùng
  cách dùng như teacher.notifications.index/student.notifications.
  SỬA 12/10 — danh sách dài thì phân trang 10 dòng/trang (phía trình duyệt, tối đa 60 thông báo mới nhất).
--}}
@extends('layouts.parent')

@section('title', 'Thông báo')
@section('page-title', 'Thông báo')

@section('content')
    @include('partials.parent-workspace-style')
    @php
        $items = $items ?? [];
        $unreadCount = $unreadCount ?? 0;
        $subtitle = $unreadCount > 0 ? "Bạn có {$unreadCount} thông báo chưa đọc." : 'Bạn đã xem hết thông báo.';
    @endphp

    <x-ws.page-header title="Thông báo" icon="message-square-text" :subtitle="$subtitle">
        @if ($unreadCount > 0)
            <x-slot:actions>
                <form method="POST" action="{{ route('notifications.readAll') }}">
                    @csrf
                    <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-sky-100 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition-colors hover:border-sky-200 hover:bg-sky-50">Đánh dấu tất cả đã đọc</button>
                </form>
            </x-slot:actions>
        @endif
    </x-ws.page-header>

    <div class="pw" style="margin-top:1rem">
        <div class="pw-card" x-data="pwPager({{ count($items) }}, 10)" style="padding:0">
            <div class="divide-y divide-slate-100">
                @forelse ($items as $n)
                    <a href="{{ route('notifications.read', $n['id']) }}" x-show="show({{ $loop->index }})"
                       class="flex items-start gap-3 p-4 hover:bg-slate-50 {{ !$n['read'] ? 'bg-blue-50/40' : '' }}" style="{{ $loop->first ? '' : 'border-top:1px solid #f1f5f9' }}">
                        <x-ws.icon-tile :emoji="$n['icon']" :tone="$n['tone']" />
                        <div class="flex-1">
                            <p class="text-[13px] font-medium text-slate-700">{{ $n['title'] }}</p>
                            <p class="text-[13px] text-slate-500 mt-0.5">{{ $n['text'] }}</p>
                            <p class="text-xs text-slate-400 mt-1">{{ $n['time'] }}</p>
                        </div>
                        @if (!$n['read'])
                            <span class="w-2 h-2 rounded-full bg-blue-500 mt-2 shrink-0"></span>
                        @endif
                    </a>
                @empty
                    <div class="p-8">
                        <x-ws.empty-state title="Chưa có thông báo nào" />
                    </div>
                @endforelse
            </div>
            <div style="padding:0 16px 16px">@include('parent._pager')</div>
        </div>
    </div>
@endsection
