@extends('layouts.guest')

@section('title', trim((string) $a->title) !== '' ? $a->title : 'Thông báo')

@section('content')
    {{-- SỬA 4/11 — thông báo Quản trị đang ghim ở thanh "Thông báo hệ thống" trang chủ (NotificationDetailController::publicShow).
         Cùng bố cục trang chính sách (lề trùng thanh menu). CSS thường, tiền tố anp-. --}}
    @php $imp = $a->level === 'important'; @endphp
    <style>
        .anp-wrap { width: 100%; margin: 0; padding: 16px 12px 56px; }
        @media (min-width: 640px) { .anp-wrap { padding: 20px 20px 64px; } }
        @media (min-width: 1024px) { .anp-wrap { padding: 20px 16px 72px; } }
        @media (min-width: 1536px) { .anp-wrap { padding: 24px 32px 72px; } }
        .anp-card { max-width: 960px; border: 1px solid #DCE9F1; border-radius: 24px; background: #fff; box-shadow: 0 10px 34px rgba(18, 59, 104, .06); overflow: hidden; }
        .anp-head { padding: 18px 20px 16px; border-bottom: 1px solid #E8F0F5;
            background: radial-gradient(120% 140% at 100% 0%, #E3F2F8 0%, rgba(227, 242, 248, 0) 55%), linear-gradient(180deg, #F7FBFD, #fff); }
        .anp-card.is-imp .anp-head { background: radial-gradient(120% 140% at 100% 0%, #FFF1D6 0%, rgba(255, 241, 214, 0) 55%), linear-gradient(180deg, #FFFBF2, #fff); }
        .anp-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px 4px 5px; border-radius: 999px; background: #EAF5F8; color: #126F91; font-size: 11px; font-weight: 700; }
        .anp-badge i { display: grid; place-items: center; width: 18px; height: 18px; border-radius: 8px; background: #126F91; color: #fff; }
        .anp-badge svg { width: 11px; height: 11px; }
        .anp-card.is-imp .anp-badge { background: #FDECC8; color: #8A5A12; }
        .anp-card.is-imp .anp-badge i { background: #C27C1E; }
        .anp-title { margin: 10px 0 0; font-size: 18px; line-height: 1.3; font-weight: 800; color: #123B68; }
        @media (min-width: 768px) { .anp-title { font-size: 20px; } }
        .anp-date { display: inline-flex; align-items: center; gap: 6px; margin-top: 8px; font-size: 11.5px; font-weight: 600; color: #7B8EA0; }
        .anp-date svg { width: 13px; height: 13px; }
        .anp-body { padding: 16px 20px 22px; font-size: 13.5px; line-height: 1.75; color: #3D566B; white-space: pre-line; word-break: break-word; }
        .anp-home { display: inline-flex; align-items: center; gap: 6px; margin: 0 20px 20px; height: 36px; padding: 0 14px; border-radius: 11px; background: #126F91; color: #fff; font-size: 12px; font-weight: 800; text-decoration: none; }
        .anp-home:hover { background: #0D5B77; }
        .anp-home svg { width: 14px; height: 14px; }
    </style>

    <div class="anp-wrap">
        <article class="anp-card {{ $imp ? 'is-imp' : '' }}">
            <header class="anp-head">
                <span class="anp-badge"><i><x-lucide name="megaphone" /></i>{{ $imp ? 'Thông báo quan trọng' : 'Thông báo hệ thống' }}</span>
                <h1 class="anp-title">{{ trim((string) $a->title) !== '' ? $a->title : 'Thông báo từ Ôn Thi 360' }}</h1>
                @if ($a->published_at)
                    <p class="anp-date"><x-lucide name="calendar-days" /> Đăng lúc {{ $a->published_at->format('H:i d/m/Y') }} · Ban quản trị Ôn Thi 360</p>
                @endif
            </header>
            <div class="anp-body">{{ $a->body }}</div>
            <a href="{{ route('home') }}" class="anp-home"><x-lucide name="home" /> Về trang chủ</a>
        </article>
    </div>
@endsection
