@extends('layouts.guest')

{{-- SỬA 4/11 — trang tải Chocon Desktop (nút "Tải Chocon Desktop" ở trang chủ trỏ về đây). --}}
@section('title', 'Tải Chocon Desktop – Quản lý máy tính khi con học online')
@section('meta-description', 'Chocon Desktop: phần mềm miễn phí giúp phụ huynh quản lý giờ học, giờ chơi, trang web và khóa máy tính của con từ xa qua onthi360.')

@section('content')
<style>
    .cdl { max-width: 880px; margin: 32px auto; padding: 0 16px; color: #1e293b; font-size: 15px; line-height: 1.6; }
    .cdl-card { border: 1px solid #e0f2fe; border-radius: 24px; background: #fff; padding: 24px; box-shadow: 0 2px 10px rgba(0,100,220,.05); margin-bottom: 16px; }
    .cdl h1 { margin: 0; font-size: 26px; line-height: 1.3; color: #0f2a5c; }
    .cdl h2 { margin: 0 0 12px; font-size: 18px; color: #0f2a5c; }
    .cdl-meta { margin: 4px 0 0; color: #64748b; font-size: 13px; }
    .cdl-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; margin-top: 18px; padding: 14px 28px; border-radius: 14px; background: #4a8a83; color: #fff; font-weight: 700; text-decoration: none; }
    .cdl-btn:hover { background: #3d7570; }
    .cdl-btn svg { width: 18px; height: 18px; }
    .cdl ol { margin: 0; padding-left: 20px; } .cdl li { margin: 6px 0; }
    .cdl-grid { display: grid; gap: 12px; grid-template-columns: 1fr; }
    @media (min-width: 720px) { .cdl-grid { grid-template-columns: repeat(3, 1fr); } }
    .cdl-f { border-radius: 16px; background: #f7fbfd; padding: 14px; font-size: 13px; color: #475569; }
    .cdl-f b { display: block; color: #1e293b; margin-bottom: 4px; font-size: 14px; }
    .cdl-note { border-radius: 16px; background: #fffbeb; border: 1px solid #fef3c7; padding: 12px 16px; font-size: 13px; color: #78350f; margin-top: 16px; }
</style>

<div class="cdl">
    <div class="cdl-card">
        <p class="cdl-meta">Dành cho phụ huynh</p>
        <h1>Chocon Desktop – Quản lý máy tính khi con học online</h1>
        <p>Cài một lần trên máy tính của con, sau đó bố mẹ quản lý hoàn toàn trên web onthi360 (điện thoại hay máy tính đều được).</p>

        @if (session('status') === 'chocon-missing' || ! $available)
            <div class="cdl-note">Bộ cài đang được cập nhật, vui lòng quay lại sau ít phút hoặc liên hệ onthi360.</div>
        @else
            <a class="cdl-btn" href="{{ route('chocon.download.file') }}"><x-lucide name="download" />Tải Chocon Desktop cho Windows</a>
            <p class="cdl-meta">Phiên bản {{ $version }} · Windows 10 / 11 · Miễn phí</p>
        @endif
    </div>

    <div class="cdl-card">
        <h2>Cài đặt trong 5 phút</h2>
        <ol>
            <li>Đăng nhập onthi360 bằng tài khoản phụ huynh → <b>Máy tính của con</b> → bấm <b>Lấy mã ghép</b>.</li>
            <li>Trên máy tính của con, đăng nhập Windows bằng tài khoản <b>quản trị</b> (của bố mẹ), mở file vừa tải và cài đặt.</li>
            <li>Nhập <b>mã ghép 6 số</b> khi bộ cài hỏi. Xong!</li>
            <li>Đặt <b>mã PIN phụ huynh</b> trên web để mở khóa máy tại chỗ và để không ai gỡ được phần mềm.</li>
        </ol>
        <div class="cdl-note">Quan trọng: cho con dùng tài khoản Windows loại <b>Standard</b> (không phải Administrator). Nếu con có quyền quản trị, con có thể tự gỡ phần mềm.</div>
        @auth
            @if (auth()->user()->hasRole('parent'))
                <a class="cdl-btn" style="background:#2563eb" href="{{ route('parent.devices.index') }}">Mở trang Máy tính của con</a>
            @endif
        @endauth
    </div>

    <div class="cdl-grid">
        <div class="cdl-f"><b>Chế độ chỉ học</b>Trong giờ học, máy chỉ vào được onthi360 và các trang bố mẹ cho phép. Game, phim bị chặn.</div>
        <div class="cdl-f"><b>Học xong mới được chơi</b>Con làm đủ bài trên onthi360 mới mở được game, làm tốt được thưởng thêm giờ.</div>
        <div class="cdl-f"><b>Khóa máy từ xa</b>Một nút bấm trên điện thoại là máy của con khóa ngay, kèm lời nhắn của bố mẹ.</div>
        <div class="cdl-f"><b>Giới hạn giờ chơi</b>Đặt số phút chơi mỗi ngày, giờ ngủ. Máy báo trước 5 phút rồi tự đóng game.</div>
        <div class="cdl-f"><b>Báo cáo mỗi ngày</b>Con học bao lâu, chơi gì, vào trang web nào.</div>
        <div class="cdl-f"><b>Xin thêm giờ</b>Con bấm xin thêm giờ trên máy, bố mẹ duyệt trên điện thoại.</div>
    </div>
</div>
@endsection
