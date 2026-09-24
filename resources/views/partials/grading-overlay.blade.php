{{--
  SỬA 24/9 — MÀN "ĐANG CHẤM", dùng chung cho cả 2 nơi nộp bài.

  Khách: "khi click nộp bài thì nó phải hiển thị popup đang xoay xoay để họ biết là bài đang
  chấm" — làm cho trang Luyện tập theo câu trước, rồi khách xin y như vậy cho trang Làm đề.
  Tách ra đây để hai nơi luôn giống nhau: sửa một lần là cả hai đổi theo.

  Dựng bằng CSS thường chứ KHÔNG dùng class Tailwind: bản CSS trên máy chủ là bản build sẵn
  (VPS không chạy được vite), class mới thêm sẽ không có trong đó.

  Cách dùng:
    @include('partials.grading-overlay')                             -> bật/tắt bằng JS thường
    @include('partials.grading-overlay', ['overlayMode' => 'alpine']) -> bật/tắt bằng x-show="submitting"

  Bản 'alpine' PHẢI đặt BÊN TRONG khối x-data chứa biến `submitting`.
--}}
@php
    $overlayMode = $overlayMode ?? 'js';
    $overlayTitle = $overlayTitle ?? 'Đang chấm bài của bạn…';
    $overlayText = $overlayText ?? 'Máy chấm đang chạy chương trình qua từng bộ test. Bài nhiều test có thể mất một lúc — đừng đóng cửa sổ này nhé.';
@endphp

<style>
    .oi-grading-overlay {
        position: fixed;
        inset: 0;
        z-index: 90;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;
        background: rgba(12, 44, 66, 0.55);
        backdrop-filter: blur(3px);
        -webkit-backdrop-filter: blur(3px);
        animation: oi-grading-fade 0.18s ease-out;
    }
    .oi-grading-overlay[hidden] { display: none; }

    .oi-grading-card {
        width: 100%;
        max-width: 340px;
        border-radius: 20px;
        background: #fff;
        padding: 28px 24px 24px;
        text-align: center;
        box-shadow: 0 18px 50px rgba(12, 44, 66, 0.28);
        animation: oi-grading-pop 0.22s cubic-bezier(.2, .9, .3, 1.2);
    }

    .oi-grading-ring {
        width: 54px;
        height: 54px;
        margin: 0 auto 16px;
        border-radius: 50%;
        border: 4px solid #E3EFF4;
        border-top-color: #126F91;
        border-right-color: #2F8A6B;
        animation: oi-grading-spin 0.85s linear infinite;
    }

    .oi-grading-title { font-size: 15px; font-weight: 800; color: #123B68; }
    .oi-grading-text { margin-top: 6px; font-size: 11.5px; line-height: 1.6; color: #607A90; }

    /* Thanh chạy tới chạy lui: KHÔNG phải tiến độ thật (máy chấm không báo % nào), chỉ để màn
       hình có nhịp sống, khỏi trông như bị treo. */
    .oi-grading-bar {
        margin-top: 18px;
        height: 4px;
        border-radius: 999px;
        background: #EDF4F7;
        overflow: hidden;
    }
    .oi-grading-bar > span {
        display: block;
        width: 40%;
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, #126F91, #2F8A6B);
        animation: oi-grading-slide 1.15s ease-in-out infinite;
    }

    /* ── Trạng thái ĐÃ CHẤM XONG (chế độ alpine-result) ── */
    .oi-grading-done {
        width: 54px;
        height: 54px;
        margin: 0 auto 14px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #E7F6EF;
        color: #2F8A6B;
        animation: oi-grading-pop 0.28s cubic-bezier(.2, .9, .3, 1.3);
    }
    .oi-grading-done--wait { background: #EAF4F8; color: #126F91; }

    .oi-grading-score {
        margin-top: 10px;
        font-size: 30px;
        font-weight: 900;
        line-height: 1.1;
        color: #123B68;
    }
    .oi-grading-score small { font-size: 15px; font-weight: 700; color: #8FA3B3; }

    .oi-grading-actions {
        margin-top: 18px;
        display: flex;
        gap: 8px;
    }
    .oi-grading-btn {
        flex: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 40px;
        padding: 8px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
        background: #126F91;
        color: #fff;
        border: 1px solid #126F91;
        transition: background-color .15s ease;
    }
    .oi-grading-btn:hover { background: #0D5B77; }
    .oi-grading-btn--ghost {
        background: #fff;
        color: #45657D;
        border-color: #DDEAF0;
    }
    .oi-grading-btn--ghost:hover { background: #F4F9FB; }

    @keyframes oi-grading-spin { to { transform: rotate(360deg); } }
    @keyframes oi-grading-slide {
        0%   { transform: translateX(-110%); }
        100% { transform: translateX(260%); }
    }
    @keyframes oi-grading-fade { from { opacity: 0; } to { opacity: 1; } }
    @keyframes oi-grading-pop {
        from { opacity: 0; transform: translateY(10px) scale(.96); }
        to   { opacity: 1; transform: none; }
    }

    @media (prefers-reduced-motion: reduce) {
        .oi-grading-overlay,
        .oi-grading-card { animation: none; }
        .oi-grading-ring { animation-duration: 2s; }
        .oi-grading-bar > span { animation: none; width: 100%; }
    }
</style>

@if ($overlayMode === 'alpine-result')
    {{-- Hai trạng thái trong cùng một hộp: đang chấm -> đã xong (hiện điểm ngay tại chỗ). --}}
    <div class="oi-grading-overlay" role="status" aria-live="polite" x-cloak x-show="submitting || submitDone">
        <div class="oi-grading-card">
            <template x-if="! submitDone">
                <div>
                    <div class="oi-grading-ring" aria-hidden="true"></div>
                    <p class="oi-grading-title">{{ $overlayTitle }}</p>
                    <p class="oi-grading-text">{{ $overlayText }}</p>
                    <div class="oi-grading-bar" aria-hidden="true"><span></span></div>
                </div>
            </template>

            <template x-if="submitDone">
                <div>
                    <div class="oi-grading-done" :class="submitResult.isProvisional ? 'oi-grading-done--wait' : ''" aria-hidden="true">
                        {{-- Hai SVG riêng, bật/tắt bằng x-show. CỐ Ý KHÔNG lồng <template> vào
                             trong <svg>: thẻ đó không thuộc mô hình nội dung của SVG, trình
                             duyệt có thể tự đẩy nó ra ngoài và làm hỏng hình. --}}
                        <svg x-show="! submitResult.isProvisional" xmlns="http://www.w3.org/2000/svg" width="26" height="26"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                             stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                        <svg x-show="submitResult.isProvisional" x-cloak xmlns="http://www.w3.org/2000/svg" width="26" height="26"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                             stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    </div>

                    <p class="oi-grading-title"
                       x-text="submitResult.isProvisional ? 'Đã nộp bài — đang chấm phần lập trình' : 'Đã nộp bài xong'"></p>

                    <p class="oi-grading-score">
                        <span x-text="submitResult.score"></span><small> / <span x-text="submitResult.totalPoints"></span></small>
                    </p>

                    <p class="oi-grading-text"
                       x-text="submitResult.isProvisional
                           ? 'Điểm tạm tính — mấy câu lập trình còn đang chạy, con số sẽ tự cập nhật ngay đây.'
                           : 'Xem chi tiết từng câu và đáp án ở trang kết quả.'"></p>

                    <div class="oi-grading-actions">
                        <a class="oi-grading-btn oi-grading-btn--ghost" href="{{ route('student.practice.index') }}">Thoát</a>
                        <a class="oi-grading-btn" :href="submitResult.resultUrl">Xem chi tiết</a>
                    </div>
                </div>
            </template>
        </div>
    </div>
@else
    <div id="oi-grading-overlay" class="oi-grading-overlay" role="status" aria-live="polite"
         @if ($overlayMode === 'alpine') x-cloak x-show="submitting" @else hidden @endif>
        <div class="oi-grading-card">
            <div class="oi-grading-ring" aria-hidden="true"></div>
            <p class="oi-grading-title">{{ $overlayTitle }}</p>
            <p class="oi-grading-text">{{ $overlayText }}</p>
            <div class="oi-grading-bar" aria-hidden="true"><span></span></div>
        </div>
    </div>
@endif
