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

    /* ── SỬA 9/10 — hộp "KẾT QUẢ CHẤM ĐỀ" (chép bố cục SubmissionResult của source mới) ── */
    .oi-rs-dialog { display: flex; flex-direction: column; width: 100%; max-width: 42rem; max-height: 96dvh; overflow: hidden;
        border-radius: 12px; background: #fff; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, .35); animation: oi-grading-pop 0.22s cubic-bezier(.2, .9, .3, 1.2); }
    .oi-rs-dialog, .oi-rs-dialog * { box-sizing: border-box; }
    .oi-rs-head { padding: 12px 16px; border-bottom: 1px solid #DDEAF0; background: #fff; }
    .oi-rs-title { margin: 0; font-size: 16px; line-height: 24px; font-weight: 700; color: #123B68; }
    .oi-rs-sub { margin: 4px 0 0; font-size: 11px; color: #607A90; }
    .oi-rs-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; background: #F8FBFC; padding: 12px; }
    @media (min-width: 640px) { .oi-rs-body { padding: 20px; } }
    .oi-rs-wait { margin: 0 0 12px; padding: 8px 12px; border-radius: 8px; background: #FFFBEB; font-size: 11px; line-height: 16px; color: #92400E; }
    .oi-rs-list { margin: 0; padding: 0; list-style: none; }
    .oi-rs-item { padding: 8px 0; font-size: 11px; line-height: 20px; color: #45657D; }
    .oi-rs-item + .oi-rs-item { border-top: 1px solid #DDEAF0; }
    .oi-rs-item p { margin: 0; }
    .oi-rs-name { font-weight: 700; color: #123B68; }
    .oi-rs-status { font-weight: 700; }
    .oi-rs-status.is-ok { color: #047857; }
    .oi-rs-status.is-none { color: #64748B; }
    .oi-rs-status.is-wait { color: #B45309; }
    .oi-rs-status.is-bad { color: #BE123C; }
    .oi-rs-tests { color: #607A90; }
    .oi-rs-foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 12px; border-top: 1px solid #DDEAF0; background: #fff; }
    @media (min-width: 640px) { .oi-rs-foot { padding: 16px; } }
    .oi-rs-link { min-height: 36px; padding: 0 8px; border: 0; background: none; font: inherit; font-size: 12px; font-weight: 600; color: #126F91;
        text-decoration: underline; text-underline-offset: 2px; cursor: pointer; }
    .oi-rs-close { display: inline-flex; align-items: center; min-height: 36px; padding: 8px 16px; border-radius: 12px; background: #126F91;
        font-size: 12px; font-weight: 700; color: #fff; text-decoration: none; }
    .oi-rs-close:hover { background: #0D5B77; }
    /* Giao diện tối của màn làm đề (html.theme-dark) — cùng bảng màu .assessment-submission-dialog của source. */
    html.theme-dark .oi-rs-dialog { background: #1b2d38; color: #dceaf0; }
    html.theme-dark .oi-rs-head, html.theme-dark .oi-rs-foot { background: #1b2d38; border-color: #365361; }
    html.theme-dark .oi-rs-body { background: #1f3644; }
    html.theme-dark .oi-rs-title, html.theme-dark .oi-rs-name { color: #f1f7fa; }
    html.theme-dark .oi-rs-sub, html.theme-dark .oi-rs-tests { color: #9db5c1; }
    html.theme-dark .oi-rs-item { color: #b9ced7; }
    html.theme-dark .oi-rs-item + .oi-rs-item { border-color: #4a6a79; }
    html.theme-dark .oi-rs-status.is-ok { color: #6ee7b7; }
    html.theme-dark .oi-rs-status.is-none { color: #94a9b4; }
    html.theme-dark .oi-rs-status.is-wait { color: #fcd34d; }
    html.theme-dark .oi-rs-status.is-bad { color: #fda4af; }
    html.theme-dark .oi-rs-wait { background: #3a3220; color: #fcd34d; }
    html.theme-dark .oi-rs-link { color: #8fd6e6; }

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
    {{-- Hai trạng thái trong cùng một lớp phủ: đang chấm (vòng quay) -> đã xong.

         SỬA 9/10 (khách: "nộp đề xong nhảy lên modal Kết quả chấm đề, bấm Đóng kết quả thì về trang
         luyện tập tab đề thi luyện tập — UI lấy từ source mới") — trạng thái "đã xong" giờ là hộp
         KẾT QUẢ CHẤM ĐỀ đúng như SubmissionResult trong education-main/AssessmentModal.jsx: tiêu đề
         "Kết quả chấm đề: X/Y điểm" (hoặc "Điểm tạm tính" khi còn câu đang chấm), dòng "a/b câu đã trả
         lời · c/b câu đúng", danh sách từng câu kèm trạng thái và điểm, chân hộp "Tải nhật ký" +
         "Đóng kết quả". Dữ liệu từng câu do Student\AssessmentService::resultSummary() cấp, gửi kèm
         JSON nộp bài / hỏi trạng thái (điểm câu lập trình tự nhích lên khi máy chấm xong). --}}
    <div class="oi-grading-overlay" role="status" aria-live="polite" x-cloak x-show="submitting || submitDone">
        <template x-if="! submitDone">
            <div class="oi-grading-card">
                <div class="oi-grading-ring" aria-hidden="true"></div>
                <p class="oi-grading-title">{{ $overlayTitle }}</p>
                <p class="oi-grading-text">{{ $overlayText }}</p>
                <div class="oi-grading-bar" aria-hidden="true"><span></span></div>
            </div>
        </template>

        <template x-if="submitDone">
            <section class="oi-rs-dialog" role="dialog" aria-modal="true" aria-labelledby="oi-rs-title">
                <header class="oi-rs-head">
                    <h2 id="oi-rs-title" class="oi-rs-title">
                        <span x-text="(submitResult.isProvisional || submitResult.questions.some(function (q) { return q.status === 'Chờ chấm'; })) ? 'Điểm tạm tính' : 'Kết quả chấm đề'"></span>:
                        <span x-text="submitResult.score"></span>/<span x-text="submitResult.maxScore ? fmtScore(submitResult.maxScore) : submitResult.totalPoints"></span> điểm
                    </h2>
                    <p class="oi-rs-sub" x-show="submitResult.total > 0">
                        <span x-text="submitResult.answered"></span>/<span x-text="submitResult.total"></span> câu đã trả lời ·
                        <span x-text="submitResult.correct"></span>/<span x-text="submitResult.total"></span> câu đúng
                    </p>
                </header>

                <div class="oi-rs-body">
                    <p class="oi-rs-wait" x-show="submitResult.isProvisional" x-cloak>
                        Các câu lập trình đang được máy chấm chạy qua từng bộ test — điểm sẽ tự cập nhật ngay tại đây.
                    </p>
                    <ul class="oi-rs-list">
                        <template x-for="q in submitResult.questions" :key="q.id">
                            <li class="oi-rs-item">
                                <p>
                                    <strong class="oi-rs-name">Câu <span x-text="q.number"></span>. <span x-text="q.title"></span></strong> ·
                                    <span class="oi-rs-status"
                                          :class="q.status === 'Đúng' ? 'is-ok' : (q.status === 'Chưa làm' ? 'is-none' : (q.status === 'Chờ chấm' || q.status === 'Đúng một phần' ? 'is-wait' : 'is-bad'))"
                                          x-text="q.status"></span> ·
                                    <span x-text="q.status === 'Chờ chấm' ? 'chưa tính điểm' : fmtScore(q.score) + '/' + fmtScore(q.maxScore) + ' điểm'"></span>
                                    <span class="oi-rs-tests" x-show="q.note" x-cloak> · <span x-text="q.note"></span></span>
                                </p>
                            </li>
                        </template>
                    </ul>
                </div>

                <footer class="oi-rs-foot">
                    <button type="button" class="oi-rs-link" @click="downloadActivityLog()">Tải nhật ký</button>
                    {{-- SỬA 1/10 — về trang Luyện tập CÔNG KHAI (tab Đề thi), không về màn trong khu đăng nhập:
                         màn đó bọc theo vai trò nên admin/giáo viên bấm Thoát là rơi vào giao diện quản trị.
                         Nơi gọi truyền $overlayExitUrl thì theo nơi gọi. --}}
                    <a class="oi-rs-close" href="{{ $overlayExitUrl ?? route('practice.index', ['tab' => 'de-thi']) }}">Đóng kết quả</a>
                </footer>
            </section>
        </template>
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
