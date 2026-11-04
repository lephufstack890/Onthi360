@extends('layouts.workspace', ['wsRole' => $wsRole ?? 'student'])

@section('title', 'Thông báo')
@section('page-title', 'Thông báo')

@section('content')
    {{-- SỬA 4/11 (khách: "hiển thị ra thông báo") — trang đọc đủ một thông báo (NotificationDetailController::show).
         CSS thường, tiền tố ntd-. --}}
    @php
        $n = $n ?? [];
        $important = ! empty($n['important']);
        $body = (string) ($n['body'] ?? '');
    @endphp
    <style>
        .ntd-wrap { max-width: 860px; }
        .ntd-back { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 10px; font-size: 12.5px; font-weight: 700; color: #607A90; text-decoration: none; }
        .ntd-back:hover { color: #126F91; }
        .ntd-back svg { width: 14px; height: 14px; }
        .ntd-card { border: 1px solid #DCE9F1; border-radius: 22px; background: #fff; box-shadow: 0 10px 30px rgba(18, 59, 104, .06); overflow: hidden; }
        .ntd-head { display: flex; gap: 14px; align-items: flex-start; padding: 18px 20px 16px; border-bottom: 1px solid #E8F0F5;
            background: radial-gradient(120% 140% at 100% 0%, #E3F2F8 0%, rgba(227, 242, 248, 0) 55%), linear-gradient(180deg, #F7FBFD, #fff); }
        .ntd-card.is-imp .ntd-head { background: radial-gradient(120% 140% at 100% 0%, #FFF1D6 0%, rgba(255, 241, 214, 0) 55%), linear-gradient(180deg, #FFFBF2, #fff); }
        .ntd-ic { display: grid; place-items: center; width: 44px; height: 44px; flex: none; border-radius: 14px; background: #EAF5F8; font-size: 22px; }
        .ntd-card.is-imp .ntd-ic { background: #FFF1D6; }
        .ntd-title { margin: 0; font-size: 18px; line-height: 1.35; font-weight: 800; color: #123B68; }
        .ntd-meta { display: flex; flex-wrap: wrap; gap: 6px 12px; margin-top: 6px; font-size: 12px; color: #6B8196; }
        .ntd-meta span { display: inline-flex; align-items: center; gap: 5px; }
        .ntd-meta svg { width: 13px; height: 13px; }
        .ntd-tag { display: inline-flex; align-items: center; padding: 2px 9px; border-radius: 999px; font-size: 11px; font-weight: 800; background: #FDECC8; color: #8A5A12; }
        .ntd-body { padding: 18px 20px 22px; font-size: 14px; line-height: 1.75; color: #33495C; white-space: pre-line; word-break: break-word; }
        .ntd-empty { color: #94A3B8; font-style: italic; }
        .ntd-foot { display: flex; flex-wrap: wrap; gap: 8px; padding: 0 20px 20px; }
        .ntd-btn { display: inline-flex; align-items: center; gap: 6px; height: 36px; padding: 0 14px; border-radius: 11px; font-size: 12.5px; font-weight: 800; text-decoration: none; border: 1px solid #CFE3EC; color: #126F91; background: #fff; }
        .ntd-btn:hover { background: #F0F8FB; }
        .ntd-btn.pri { background: #126F91; border-color: #126F91; color: #fff; }
        .ntd-btn.pri:hover { background: #0D5B77; }
        .ntd-btn svg { width: 14px; height: 14px; }
        @media (max-width: 640px) { .ntd-head { padding: 14px; } .ntd-body { padding: 14px 14px 18px; font-size: 13.5px; } .ntd-foot { padding: 0 14px 16px; } .ntd-title { font-size: 16px; } }
    </style>

    <div class="ntd-wrap">
        <a href="{{ $backUrl }}" class="ntd-back"><x-lucide name="arrow-left" />Tất cả thông báo</a>

        <article class="ntd-card {{ $important ? 'is-imp' : '' }}">
            <header class="ntd-head">
                <span class="ntd-ic" aria-hidden="true">{{ $n['icon'] ?? '🔔' }}</span>
                <div class="min-w-0">
                    <h1 class="ntd-title">{{ $n['title'] ?? 'Thông báo' }}</h1>
                    <div class="ntd-meta">
                        @if ($important)<span class="ntd-tag">Quan trọng</span>@endif
                        @if (! empty($n['from']))<span><x-lucide name="user-round" />{{ $n['from'] }}</span>@endif
                        @if (! empty($n['meta']))<span><x-lucide name="school" />{{ $n['meta'] }}</span>@endif
                        @if (! empty($n['time']))<span><x-lucide name="clock" />{{ $n['time']->format('H:i d/m/Y') }} · {{ $n['time']->diffForHumans() }}</span>@endif
                    </div>
                </div>
            </header>

            <div class="ntd-body">@if (trim($body) !== ''){{ $body }}@else<span class="ntd-empty">Thông báo này không có nội dung chi tiết.</span>@endif</div>

            <div class="ntd-foot">
                @if (! empty($n['url']))
                    <a href="{{ $n['url'] }}" class="ntd-btn pri">Mở trang liên quan <x-lucide name="arrow-right" /></a>
                @endif
                <a href="{{ $backUrl }}" class="ntd-btn"><x-lucide name="bell" />Về danh sách thông báo</a>
            </div>
        </article>
    </div>
@endsection
