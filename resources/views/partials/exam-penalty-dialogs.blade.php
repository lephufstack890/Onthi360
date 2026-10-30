{{--
    SỬA 11/10 — HỘP CẢNH BÁO TRỪ ĐIỂM CỦA MÀN LÀM ĐỀ (cùng giao diện với màn Luyện tập 1 bài — xem oi-confirm-* ở
    student/practice/exercise-play.blade.php). PHẢI @include BÊN TRONG khối x-data="examWorkspace(...)" vì dùng
    penaltyAsk / penaltyNotice / penaltyAccept() / penaltyCancel() / showPenaltyNotice() của nó.

    Dựng bằng CSS thường (không class Tailwind mới) vì CSS trên máy chủ là bản build sẵn.
--}}
<style>
    .oi-confirm-backdrop { position: fixed; inset: 0; z-index: 90; display: flex; align-items: center; justify-content: center; padding: 16px; background: rgba(15, 23, 42, .55); backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px); }
    .oi-confirm-card { width: 100%; max-width: 400px; padding: 24px 22px 20px; border-radius: 18px; background: #fff; color: #123B68; text-align: center; box-shadow: 0 24px 60px rgba(15, 23, 42, .35); }
    .oi-confirm-enter { transition: opacity .18s ease, transform .18s ease; }
    .oi-confirm-enter-from { opacity: 0; transform: translateY(8px) scale(.96); }
    .oi-confirm-enter-to { opacity: 1; transform: none; }
    .oi-confirm-icon { display: flex; align-items: center; justify-content: center; width: 52px; height: 52px; margin: 0 auto 12px; border-radius: 9999px; background: #FFEDD5; color: #EA580C; }
    .oi-confirm-title { margin: 0; font-size: 17px; font-weight: 800; line-height: 1.35; }
    .oi-confirm-pct { display: inline-block; margin-top: 10px; padding: 3px 12px; border-radius: 9999px; background: #FFF7ED; border: 1px solid #FED7AA; color: #C2410C; font-size: 14px; font-weight: 800; }
    .oi-confirm-desc { margin: 12px 0 0; font-size: 13px; line-height: 1.6; color: #45657D; }
    .oi-confirm-desc b { color: #123B68; }
    .oi-confirm-actions { display: flex; gap: 10px; margin-top: 20px; }
    .oi-confirm-btn { flex: 1 1 0; padding: 10px 12px; border: 0; border-radius: 12px; font-size: 13px; font-weight: 700; cursor: pointer; transition: background-color .15s ease, transform .05s ease; }
    .oi-confirm-btn:active { transform: scale(.98); }
    .oi-confirm-btn:focus-visible { outline: 2px solid #0E7490; outline-offset: 2px; }
    .oi-confirm-btn-ghost { background: #EAF3F7; color: #123B68; }
    .oi-confirm-btn-ghost:hover { background: #DCEAF1; }
    .oi-confirm-btn-warn { background: #EA580C; color: #fff; }
    .oi-confirm-btn-warn:hover { background: #C2410C; }
    html.theme-dark .oi-confirm-card { background: #12283A; color: #E6F1F7; box-shadow: 0 24px 60px rgba(0, 0, 0, .6); border: 1px solid #25445C; }
    html.theme-dark .oi-confirm-title { color: #F1F8FC !important; }
    html.theme-dark .oi-confirm-icon { background: #3A2A18; color: #FDBA74; }
    html.theme-dark .oi-confirm-pct { background: #3A2A18; border-color: #7C4A1E; color: #FDD9A8; }
    html.theme-dark .oi-confirm-desc { color: #A9C2D3 !important; }
    html.theme-dark .oi-confirm-desc b { color: #E6F1F7; }
    html.theme-dark .oi-confirm-btn-ghost { background: #1E3B52; color: #E6F1F7; }
    html.theme-dark .oi-confirm-btn-ghost:hover { background: #28506C; }
    .oi-penalty-warn { display: inline-flex; flex-wrap: wrap; align-items: center; gap: 4px 6px; max-width: 100%; padding: 3px 8px; border: 1px solid #FED7AA; border-radius: 8px; background: #FFF7ED; color: #9A3412; font-size: 11px; line-height: 1.4; }
    .oi-penalty-badge { padding: 0 6px; border-radius: 6px; background: #FDBA74; color: #7C2D12; font-weight: 800; }
    .oi-penalty-badge:empty, .oi-penalty-badge[data-on="0"] { display: none; }
    html.theme-dark .oi-penalty-warn { background: #3A2A18; border-color: #7C4A1E; color: #FDD9A8; }
    html.theme-dark .oi-penalty-badge { background: #B45309; color: #FFF7ED; }
</style>

{{-- Hộp BÁO vừa bị trừ điểm (chụp màn hình): chỉ có nút "Đã hiểu". --}}
<div class="oi-confirm-backdrop" style="z-index: 95" x-cloak x-show="penaltyNotice.open" x-transition.opacity.duration.150ms
     @keydown.escape.window="penaltyNotice.open = false" @click.self="penaltyNotice.open = false">
    <div class="oi-confirm-card" role="alertdialog" aria-modal="true" aria-labelledby="oi-notice-title"
         x-show="penaltyNotice.open"
         x-transition:enter="oi-confirm-enter" x-transition:enter-start="oi-confirm-enter-from" x-transition:enter-end="oi-confirm-enter-to"
         x-effect="if (penaltyNotice.open) $nextTick(function () { var b = $el.querySelector('[data-oi-notice-ok]'); if (b) b.focus(); })">
        <div class="oi-confirm-icon"><x-lucide name="alert-triangle" class="h-6 w-6" /></div>
        <h3 id="oi-notice-title" class="oi-confirm-title" x-text="penaltyNotice.title"></h3>
        <div class="oi-confirm-pct">Bị trừ −<span x-text="penaltyNotice.percent"></span>% điểm câu này</div>
        <p class="oi-confirm-desc">
            Hành vi này đã được ghi vào Nhật ký làm bài. Tổng điểm đã bị trừ cho câu đang làm:
            <b><span x-text="penaltyNotice.total"></span>%</b>.
        </p>
        <div class="oi-confirm-actions">
            <button type="button" class="oi-confirm-btn oi-confirm-btn-warn" data-oi-notice-ok @click="penaltyNotice.open = false">Đã hiểu</button>
        </div>
    </div>
</div>

{{-- Hộp XÁC NHẬN trước khi mở Hướng dẫn / Bài mẫu. --}}
<div class="oi-confirm-backdrop" x-show="penaltyAsk.open" x-cloak x-transition.opacity.duration.150ms
     @keydown.escape.window="if (penaltyAsk.open) penaltyCancel()" @click.self="penaltyCancel()">
    <div class="oi-confirm-card" role="alertdialog" aria-modal="true" aria-labelledby="oi-confirm-title" aria-describedby="oi-confirm-desc"
         x-show="penaltyAsk.open"
         x-transition:enter="oi-confirm-enter" x-transition:enter-start="oi-confirm-enter-from" x-transition:enter-end="oi-confirm-enter-to"
         x-effect="if (penaltyAsk.open) $nextTick(function () { var b = $el.querySelector('[data-oi-confirm-cancel]'); if (b) b.focus(); })">
        <div class="oi-confirm-icon"><x-lucide name="alert-triangle" class="h-6 w-6" /></div>
        <h3 id="oi-confirm-title" class="oi-confirm-title">Xem <span x-text="penaltyAsk.label"></span> sẽ bị trừ điểm</h3>
        <div class="oi-confirm-pct">−<span x-text="penaltyAsk.pct"></span>% điểm câu này</div>
        <p id="oi-confirm-desc" class="oi-confirm-desc">
            Mở tab <b x-text="penaltyAsk.label"></b> được ghi vào Nhật ký làm bài và bị trừ
            <b><span x-text="penaltyAsk.pct"></span>%</b> điểm của câu đang làm (chỉ trừ một lần — mở lại sau đó không trừ thêm). Bạn có chắc muốn xem không?
        </p>
        <div class="oi-confirm-actions">
            <button type="button" class="oi-confirm-btn oi-confirm-btn-ghost" data-oi-confirm-cancel @click="penaltyCancel()">Không xem</button>
            <button type="button" class="oi-confirm-btn oi-confirm-btn-warn" @click="penaltyAccept()">Vẫn xem (−<span x-text="penaltyAsk.pct"></span>%)</button>
        </div>
    </div>
</div>
