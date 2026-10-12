@extends('layouts.student')

@section('title', 'Thông báo')
@section('page-title', 'Thông báo')

@section('content')
    {{-- SỬA 12/10 — DỰNG LẠI THEO education-main (source mới): StudentContent → "Thông báo của bạn" — mỗi thông báo là một hàng: ô icon màu,
         tiêu đề, giờ, mô tả. Giữ NGUYÊN logic: NotificationService::forUser, link đọc notifications.read, "Đánh dấu tất cả đã đọc".
         Thêm phân trang 10 dòng/trang trên trình duyệt. --}}
    @php
        $items = $items ?? [];
        $unreadCount = $unreadCount ?? 0;
        $toneMap = ['success' => 'emerald', 'warning' => 'amber', 'danger' => 'rose', 'error' => 'rose'];
    @endphp

    <div class="sw" data-section="STUDENT-NOTIFICATIONS">
        @include('student._hero')

        <section class="student-overview-card" x-data="stuNotices({{ count($items) }})">
            <div class="sw-split">
                <div>
                    <h2 class="sw-h2"><x-lucide name="message-square-text" />Thông báo của bạn</h2>
                    <p class="sw-sub">{{ $unreadCount > 0 ? "Bạn có {$unreadCount} thông báo chưa đọc." : 'Bạn đã xem hết thông báo.' }}</p>
                </div>
                @if ($unreadCount > 0)
                    <form method="POST" action="{{ route('notifications.readAll') }}">
                        @csrf
                        <button type="submit" class="student-overview-action">Đánh dấu tất cả đã đọc</button>
                    </form>
                @endif
            </div>

            <div style="margin-top:8px">
                @forelse ($items as $n)
                    <a href="{{ route('notifications.read', $n['id']) }}" class="sw-notice {{ ! $n['read'] ? 'unread' : '' }}" x-show="show({{ $loop->index }})">
                        <span class="sw-tile {{ $toneMap[$n['tone']] ?? '' }}">@if (! empty($n['icon']) && $n['icon'] !== '🔔'){{ $n['icon'] }}@else<x-lucide name="message-square-text" />@endif</span>
                        <div class="sw-notice-b">
                            <div><h2>{{ $n['title'] }}</h2><time>{{ $n['time'] }}</time></div>
                            @if ($n['text'] && $n['text'] !== $n['title'])<p>{{ $n['text'] }}</p>@endif
                        </div>
                        @if (! $n['read'])<span class="sw-new" aria-label="Chưa đọc"></span>@endif
                    </a>
                @empty
                    <p class="sw-empty">Chưa có thông báo nào.</p>
                @endforelse
            </div>

            @include('student._pager')
        </section>
    </div>
    <script>
        function stuNotices(total) { return window.swPager(total, 10); }
    </script>
@endsection
