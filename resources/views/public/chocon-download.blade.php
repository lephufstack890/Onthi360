@extends('layouts.guest')

{{-- SỬA 4/11 — trang tải Chocon Desktop (nút "Tải Chocon Desktop" ở trang chủ trỏ về đây).
     SỬA 4/11 (khách: "UI cho full ra cho đẹp") — trải hết bề ngang, lề trái/phải bằng lề thanh menu (giống trang chính sách);
     khối đầu 2 cột (giới thiệu + nút tải | minh hoạ bảng theo dõi), bên dưới 2 cột (các bước cài | tính năng). Logic giữ nguyên:
     $available / $version / session chocon-missing / route tải / nút "Mở trang Máy tính của con" cho phụ huynh. CSS tiền tố cdl-. --}}
@section('title', 'Tải Chocon Desktop – Quản lý máy tính khi con học online')
@section('meta-description', 'Chocon Desktop: phần mềm miễn phí giúp phụ huynh quản lý giờ học, giờ chơi, trang web và khóa máy tính của con từ xa qua onthi360.')

@section('content')
@php
    $cdlParent = auth()->check() && auth()->user()->hasRole('parent');
    $cdlFeatures = [
        ['book-open', 'Chế độ chỉ học', 'Trong giờ học, máy chỉ vào được onthi360 và các trang bố mẹ cho phép. Game, phim bị chặn.', 'sky'],
        ['trophy', 'Học xong mới được chơi', 'Con làm đủ bài trên onthi360 mới mở được game, làm tốt được thưởng thêm giờ.', 'amber'],
        ['lock', 'Khóa máy từ xa', 'Một nút bấm trên điện thoại là máy của con khóa ngay, kèm lời nhắn của bố mẹ.', 'rose'],
        ['timer', 'Giới hạn giờ chơi', 'Đặt số phút chơi mỗi ngày, giờ ngủ. Máy báo trước 5 phút rồi tự đóng game.', 'violet'],
        ['bar-chart-3', 'Báo cáo mỗi ngày', 'Con học bao lâu, chơi gì, vào trang web nào.', 'emerald'],
        ['message-circle', 'Xin thêm giờ', 'Con bấm xin thêm giờ trên máy, bố mẹ duyệt trên điện thoại.', 'teal'],
    ];
@endphp
<style>
    .cdl { width: 100%; margin: 0; padding: 16px 12px 24px; color: #1E3A52; font-size: 14px; line-height: 1.6; }
    @media (min-width: 640px) { .cdl { padding: 20px 20px 24px; } }
    @media (min-width: 1024px) { .cdl { padding: 20px 16px 28px; } }
    @media (min-width: 1536px) { .cdl { padding: 24px 32px 28px; } }

    /* ── Khối đầu ── */
    .cdl-hero { position: relative; overflow: hidden; display: grid; gap: 24px; align-items: center; padding: 22px 18px; border: 1px solid #DCE9F1; border-radius: 26px;
        background: radial-gradient(90% 120% at 100% 0%, #DDF0F7 0%, rgba(221, 240, 247, 0) 60%), radial-gradient(60% 80% at 0% 100%, #EEF4FF 0%, rgba(238, 244, 255, 0) 60%), #fff;
        box-shadow: 0 10px 34px rgba(18, 59, 104, .06); }
    @media (min-width: 768px) { .cdl-hero { padding: 30px 32px; } }
    @media (min-width: 1100px) { .cdl-hero { grid-template-columns: minmax(0, 1.15fr) minmax(0, .85fr); gap: 36px; padding: 36px 40px; } }
    .cdl-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 11px 4px 5px; border-radius: 999px; background: #EAF5F8; color: #126F91; font-size: 11.5px; font-weight: 700; }
    .cdl-badge i { display: grid; place-items: center; width: 20px; height: 20px; border-radius: 8px; background: #126F91; color: #fff; }
    .cdl-badge svg { width: 12px; height: 12px; }
    .cdl h1 { margin: 12px 0 0; font-size: 22px; line-height: 1.3; font-weight: 800; letter-spacing: -.01em; color: #123B68; }
    @media (min-width: 768px) { .cdl h1 { font-size: 26px; } }
    .cdl-lead { margin: 10px 0 0; max-width: 640px; font-size: 14.5px; line-height: 1.7; color: #4A6378; }
    .cdl-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 14px; margin-top: 20px; }
    .cdl-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; height: 46px; padding: 0 22px; border-radius: 14px; background: #126F91; color: #fff; font-size: 14px; font-weight: 800; text-decoration: none; box-shadow: 0 10px 22px rgba(18, 111, 145, .25); transition: background .15s, transform .15s; }
    .cdl-btn:hover { background: #0D5B77; transform: translateY(-1px); }
    .cdl-btn svg { width: 18px; height: 18px; }
    .cdl-btn.ghost { height: 42px; background: #fff; color: #126F91; border: 1px solid #CFE3EC; box-shadow: none; font-size: 13px; }
    .cdl-btn.ghost:hover { background: #F0F8FB; }
    .cdl-meta { margin: 0; color: #6B8196; font-size: 12.5px; }
    .cdl-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 18px; }
    .cdl-chip { display: inline-flex; align-items: center; gap: 6px; padding: 6px 11px; border-radius: 999px; border: 1px solid #DCE9F1; background: rgba(255, 255, 255, .8); font-size: 12px; font-weight: 700; color: #3D566B; }
    .cdl-chip svg { width: 14px; height: 14px; color: #2F8A6B; }
    .cdl-note { border-radius: 16px; background: #FFF9EC; border: 1px solid #F2E1BC; padding: 12px 16px; font-size: 13px; line-height: 1.6; color: #7A4E12; }
    .cdl-hero .cdl-note { margin-top: 18px; max-width: 640px; }

    /* Minh hoạ bảng theo dõi */
    .cdl-mock { position: relative; max-width: 460px; width: 100%; justify-self: center; padding: 18px 0 30px; }
    .cdl-screen { border: 1px solid #D6E6EE; border-radius: 20px; background: #fff; box-shadow: 0 18px 40px rgba(18, 59, 104, .12); overflow: hidden; }
    .cdl-screen-bar { display: flex; align-items: center; gap: 6px; padding: 10px 14px; border-bottom: 1px solid #EEF3F7; background: #F8FBFD; font-size: 11.5px; font-weight: 700; color: #6B8196; }
    .cdl-screen-bar i { width: 9px; height: 9px; border-radius: 50%; background: #F3C7C1; } .cdl-screen-bar i:nth-child(2) { background: #F6DFA8; } .cdl-screen-bar i:nth-child(3) { background: #BFE3CF; }
    .cdl-screen-bar span { margin-left: 6px; }
    .cdl-screen-b { padding: 16px; display: grid; gap: 12px; }
    .cdl-row { display: flex; align-items: center; gap: 10px; }
    .cdl-av { display: grid; place-items: center; width: 38px; height: 38px; flex: none; border-radius: 12px; background: #EAF5F8; color: #126F91; }
    .cdl-av svg { width: 19px; height: 19px; }
    .cdl-row b { display: block; font-size: 13.5px; color: #123B68; }
    .cdl-row small { font-size: 11.5px; color: #6B8196; }
    .cdl-pill { margin-left: auto; padding: 4px 10px; border-radius: 999px; background: #E6F6EF; color: #1F7A55; font-size: 11px; font-weight: 800; white-space: nowrap; }
    .cdl-bar { display: grid; gap: 5px; }
    .cdl-bar div { display: flex; justify-content: space-between; font-size: 12px; color: #4A6378; }
    .cdl-bar div b { color: #123B68; }
    .cdl-track { height: 8px; border-radius: 999px; background: #EEF3F7; overflow: hidden; }
    .cdl-track i { display: block; height: 100%; border-radius: inherit; }
    .cdl-mock-btns { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .cdl-mock-btns span { display: flex; align-items: center; justify-content: center; gap: 6px; height: 34px; border-radius: 10px; font-size: 12px; font-weight: 800; }
    .cdl-mock-btns svg { width: 14px; height: 14px; }
    .cdl-float { position: absolute; display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 14px; background: #fff; border: 1px solid #E0EEF4; box-shadow: 0 10px 24px rgba(18, 59, 104, .12); font-size: 12px; font-weight: 700; color: #123B68; }
    .cdl-float svg { width: 16px; height: 16px; }
    .cdl-float.f1 { left: -12px; bottom: 2px; } .cdl-float.f2 { right: 14px; top: -2px; }
    @media (max-width: 520px) { .cdl-float { display: none; } }
    .cdl-cap { margin: 8px 0 0; text-align: right; font-size: 11px; color: #94A3B8; }

    /* ── Hai cột dưới ── */
    .cdl-cols { display: grid; gap: 16px; margin-top: 16px; }
    /* SỬA 4/11 (khách: "cho bằng nhau, gần footer hơn") — 2 thẻ cao bằng nhau; lề dưới trang thu nhỏ. */
    @media (min-width: 1100px) { .cdl-cols { grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr); align-items: stretch; } }
    .cdl-cols > .cdl-card { display: flex; flex-direction: column; }
    .cdl-cols .cdl-feats { flex: 1; grid-auto-rows: 1fr; }
    .cdl-cols .cdl-card .cdl-btn.ghost { align-self: flex-start; }
    .cdl-card { border: 1px solid #DCE9F1; border-radius: 24px; background: #fff; padding: 20px 18px; box-shadow: 0 8px 26px rgba(18, 59, 104, .05); }
    @media (min-width: 768px) { .cdl-card { padding: 24px 26px; } }
    .cdl-h2 { display: flex; align-items: center; gap: 9px; margin: 0 0 14px; font-size: 16px; font-weight: 800; color: #123B68; }
    .cdl-h2 i { display: grid; place-items: center; width: 30px; height: 30px; border-radius: 10px; background: linear-gradient(135deg, #1C86AE, #126F91); color: #fff; }
    .cdl-h2 svg { width: 16px; height: 16px; }
    .cdl-steps { margin: 0; padding: 0; list-style: none; display: grid; gap: 0; }
    .cdl-step { position: relative; display: flex; gap: 12px; padding-bottom: 16px; }
    .cdl-step:last-child { padding-bottom: 0; }
    .cdl-step::before { content: ''; position: absolute; left: 14px; top: 30px; bottom: 2px; width: 2px; background: #E0EEF4; }
    .cdl-step:last-child::before { display: none; }
    .cdl-step-n { position: relative; display: grid; place-items: center; width: 30px; height: 30px; flex: none; border-radius: 50%; background: #EAF5F8; border: 2px solid #BFE0EC; color: #126F91; font-size: 13px; font-weight: 800; }
    .cdl-step p { margin: 4px 0 0; font-size: 13.5px; line-height: 1.6; color: #3D566B; }
    .cdl-step b { color: #123B68; }
    .cdl-card .cdl-note { margin-top: 16px; }
    .cdl-card .cdl-btn { margin-top: 16px; }

    .cdl-feats { display: grid; gap: 12px; grid-template-columns: 1fr; }
    @media (min-width: 640px) { .cdl-feats { grid-template-columns: 1fr 1fr; } }
    .cdl-f { display: flex; gap: 12px; padding: 14px; border-radius: 18px; border: 1px solid #E3ECF2; background: linear-gradient(180deg, #FAFDFE, #fff); transition: border-color .15s, box-shadow .15s, transform .15s; }
    .cdl-f:hover { border-color: #BFE0EC; box-shadow: 0 8px 20px rgba(18, 111, 145, .08); transform: translateY(-1px); }
    .cdl-f-ic { display: grid; place-items: center; width: 40px; height: 40px; flex: none; border-radius: 13px; }
    .cdl-f-ic svg { width: 19px; height: 19px; }
    .cdl-f b { display: block; margin-bottom: 3px; font-size: 14px; color: #123B68; }
    .cdl-f p { margin: 0; font-size: 12.5px; line-height: 1.6; color: #52687B; }
    .t-sky { background: #E3F2F8; color: #126F91; } .t-amber { background: #FDF0D5; color: #B7791F; } .t-rose { background: #FDE7E4; color: #C2410C; }
    .t-violet { background: #EDE9FE; color: #6D28D9; } .t-emerald { background: #DCF5EA; color: #1F7A55; } .t-teal { background: #DDF3F1; color: #0F766E; }
</style>

<div class="cdl">
    {{-- ── Khối đầu: giới thiệu + nút tải | minh hoạ ── --}}
    <section class="cdl-hero">
        <div>
            <span class="cdl-badge"><i><x-lucide name="users" /></i>Dành cho phụ huynh</span>
            <h1>Chocon Desktop – Quản lý máy tính khi con học online</h1>
            <p class="cdl-lead">Cài một lần trên máy tính của con, sau đó bố mẹ quản lý hoàn toàn trên web onthi360 (điện thoại hay máy tính đều được).</p>

            @if (session('status') === 'chocon-missing' || ! $available)
                <div class="cdl-note">Bộ cài đang được cập nhật, vui lòng quay lại sau ít phút hoặc liên hệ onthi360.</div>
            @else
                <div class="cdl-actions">
                    <a class="cdl-btn" href="{{ route('chocon.download.file') }}"><x-lucide name="download" />Tải Chocon Desktop cho Windows</a>
                    <p class="cdl-meta">Phiên bản {{ $version }} · Windows 10 / 11 · Miễn phí</p>
                </div>
            @endif

            <div class="cdl-chips">
                <span class="cdl-chip"><x-lucide name="check-circle-2" />Miễn phí</span>
                <span class="cdl-chip"><x-lucide name="check-circle-2" />Cài đặt trong 5 phút</span>
                <span class="cdl-chip"><x-lucide name="check-circle-2" />Quản lý bằng điện thoại</span>
                <span class="cdl-chip"><x-lucide name="check-circle-2" />Gắn với việc học trên onthi360</span>
            </div>
        </div>

        <div class="cdl-mock" aria-hidden="true">
            <div class="cdl-screen">
                <div class="cdl-screen-bar"><i></i><i></i><i></i><span>Máy tính của con · onthi360</span></div>
                <div class="cdl-screen-b">
                    <div class="cdl-row">
                        <span class="cdl-av"><x-lucide name="monitor" /></span>
                        <span><b>Máy tính của con</b><small>Đang hoạt động</small></span>
                        <span class="cdl-pill">Chế độ chỉ học</span>
                    </div>
                    <div class="cdl-bar">
                        <div><span>Thời gian học hôm nay</span><b>45 phút</b></div>
                        <span class="cdl-track"><i style="width:75%;background:linear-gradient(90deg,#1C86AE,#126F91)"></i></span>
                    </div>
                    <div class="cdl-bar">
                        <div><span>Giờ chơi đã dùng</span><b>20 / 60 phút</b></div>
                        <span class="cdl-track"><i style="width:33%;background:linear-gradient(90deg,#F6C76B,#E39A2D)"></i></span>
                    </div>
                    <div class="cdl-mock-btns">
                        <span style="background:#FDE7E4;color:#B42318"><x-lucide name="lock" />Khóa máy</span>
                        <span style="background:#EAF5F8;color:#126F91"><x-lucide name="clock" />Cho thêm giờ</span>
                    </div>
                </div>
            </div>
            <span class="cdl-float f1"><x-lucide name="check-circle-2" style="color:#2F8A6B" />Con đã làm xong 5 bài</span>
            <span class="cdl-float f2"><x-lucide name="smartphone" style="color:#126F91" />Quản lý từ xa</span>
            <p class="cdl-cap">Hình minh hoạ</p>
        </div>
    </section>

    {{-- ── Hai cột: các bước cài | tính năng ── --}}
    <div class="cdl-cols">
        <section class="cdl-card">
            <h2 class="cdl-h2"><i><x-lucide name="list-checks" /></i>Cài đặt trong 5 phút</h2>
            <ol class="cdl-steps">
                <li class="cdl-step"><span class="cdl-step-n">1</span><p>Đăng nhập onthi360 bằng tài khoản phụ huynh → <b>Máy tính của con</b> → bấm <b>Lấy mã ghép</b>.</p></li>
                <li class="cdl-step"><span class="cdl-step-n">2</span><p>Trên máy tính của con, đăng nhập Windows bằng tài khoản <b>quản trị</b> (của bố mẹ), mở file vừa tải và cài đặt.</p></li>
                <li class="cdl-step"><span class="cdl-step-n">3</span><p>Nhập <b>mã ghép 6 số</b> khi bộ cài hỏi. Xong!</p></li>
                <li class="cdl-step"><span class="cdl-step-n">4</span><p>Đặt <b>mã PIN phụ huynh</b> trên web để mở khóa máy tại chỗ và để không ai gỡ được phần mềm.</p></li>
            </ol>
            <div class="cdl-note">Quan trọng: cho con dùng tài khoản Windows loại <b>Standard</b> (không phải Administrator). Nếu con có quyền quản trị, con có thể tự gỡ phần mềm.</div>
            @if ($cdlParent)
                <a class="cdl-btn ghost" href="{{ route('parent.devices.index') }}"><x-lucide name="monitor" />Mở trang Máy tính của con <x-lucide name="arrow-right" /></a>
            @endif
        </section>

        <section class="cdl-card">
            <h2 class="cdl-h2"><i><x-lucide name="sparkles" /></i>Bố mẹ quản lý được gì?</h2>
            <div class="cdl-feats">
                @foreach ($cdlFeatures as [$fIcon, $fTitle, $fText, $fTone])
                    <div class="cdl-f">
                        <span class="cdl-f-ic t-{{ $fTone }}"><x-lucide :name="$fIcon" /></span>
                        <div><b>{{ $fTitle }}</b><p>{{ $fText }}</p></div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</div>
@endsection
