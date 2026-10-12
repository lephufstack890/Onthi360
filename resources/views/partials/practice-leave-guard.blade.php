{{--
    SỬA 12/10 (khách: "thêm popup cảnh báo thật đẹp khi đang làm bài luyện tập hoặc đang làm đề luyện tập mà thoát ra là
    phải có cảnh báo — chỉ cần cảnh báo thôi") —

    Hộp cảnh báo DÙNG CHUNG cho 4 màn làm bài luyện tập: làm đề (take, take-pdf), làm bài tập (exercise-play) và luyện
    theo câu (by-question-play). CHỈ cảnh báo, KHÔNG đổi bất kỳ logic nộp/chấm/lưu nào.

    Chặn 3 lối ra:
      1. Bấm liên kết rời trang (nút X, menu bên...) và form "Thoát bài tập / Dừng luyện tập" (gắn data-oi-leave-form)
         -> hộp cảnh báo đẹp bên dưới.
      2. Nút Back của trình duyệt -> cũng hiện hộp này (đệm 1 mục lịch sử rồi bắt popstate).
      3. Đóng tab / F5 / gõ địa chỉ khác -> trình duyệt tự hỏi bằng hộp của NÓ (không dựng giao diện riêng được, chỉ bật/tắt).

    Tham số:
      $kind     : 'exam' (làm đề) | 'problem' (làm bài tập) | 'pool' (luyện theo câu) — đổi câu chữ.
      $activeJs : (tuỳ chọn) thân hàm JS trả true khi ĐANG LÀM DỞ; mặc định: không có phần tử [data-oi-done] trong trang
                  (phần tử này chỉ xuất hiện khi đã nộp / đã xong). Trả false thì hộp không bật.
      $ignore   : (tuỳ chọn) selector các liên kết KHÔNG tính là rời trang (tải tệp, tài liệu...).

    Không dùng Alpine/Tailwind biên dịch (server không có Vite) — CSS + JS nằm trọn trong file này.
--}}
@php
    $kind = $kind ?? 'problem';
    $copy = [
        'exam' => [
            'title' => 'Bạn đang làm đề dở!',
            'text' => 'Đề này chưa được nộp. Nếu thoát bây giờ, bài làm của bạn sẽ chưa được chấm điểm.',
            'tip' => 'Làm xong hãy bấm Nộp đề để lưu kết quả.',
        ],
        'pool' => [
            'title' => 'Bạn đang luyện tập dở!',
            'text' => 'Câu hiện tại chưa được ghi nhận. Nếu thoát bây giờ, phần bạn vừa làm sẽ không được tính vào kết quả.',
            'tip' => 'Trả lời xong hãy bấm ghi nhận / kiểm tra đáp án.',
        ],
        'problem' => [
            'title' => 'Bạn đang làm bài dở!',
            'text' => 'Bài này chưa được nộp. Nếu thoát bây giờ, phần bạn đang làm sẽ chưa được chấm điểm.',
            'tip' => 'Làm xong hãy bấm Nộp bài để lưu kết quả.',
        ],
    ][$kind] ?? null;
    $copy = $copy ?? ['title' => 'Bạn đang làm dở!', 'text' => 'Nếu thoát bây giờ, bài làm sẽ chưa được chấm điểm.', 'tip' => 'Làm xong hãy nộp bài để lưu kết quả.'];
    $activeJs = $activeJs ?? "return !document.querySelector('[data-oi-done]');";
    $ignore = $ignore ?? '';
@endphp

<style>
    .oi-lg-backdrop { position: fixed; inset: 0; z-index: 2147483000; display: flex; align-items: center; justify-content: center; padding: 16px; background: rgba(15, 33, 52, .55); -webkit-backdrop-filter: blur(5px); backdrop-filter: blur(5px); opacity: 0; transition: opacity .18s ease; }
    .oi-lg-backdrop[hidden] { display: none; }
    .oi-lg-backdrop.is-open { opacity: 1; }
    .oi-lg-card { position: relative; width: 100%; max-width: 420px; overflow: hidden; border-radius: 24px; background: #fff; box-shadow: 0 30px 80px -20px rgba(15, 33, 52, .55), 0 0 0 1px rgba(18, 111, 145, .08); text-align: center; transform: translateY(14px) scale(.96); transition: transform .22s cubic-bezier(.2, .9, .3, 1.2); font-family: inherit; }
    .oi-lg-backdrop.is-open .oi-lg-card { transform: none; }
    .oi-lg-top { height: 6px; background: linear-gradient(90deg, #f59e0b, #f97316 55%, #ef4444); }
    .oi-lg-body { padding: 26px 26px 8px; }
    .oi-lg-icon { position: relative; display: grid; place-items: center; width: 68px; height: 68px; margin: 0 auto 16px; border-radius: 22px; background: linear-gradient(160deg, #fff7e0, #ffe9bd); color: #b45309; box-shadow: inset 0 0 0 1px rgba(245, 158, 11, .25); }
    .oi-lg-icon::after { content: ''; position: absolute; inset: -7px; border-radius: 27px; border: 2px solid rgba(245, 158, 11, .35); animation: oi-lg-pulse 1.8s ease-out infinite; }
    .oi-lg-icon svg { width: 34px; height: 34px; }
    @keyframes oi-lg-pulse { 0% { transform: scale(.92); opacity: .9; } 100% { transform: scale(1.16); opacity: 0; } }
    @media (prefers-reduced-motion: reduce) { .oi-lg-icon::after { animation: none; opacity: .5; } .oi-lg-card, .oi-lg-backdrop { transition: none; } }
    .oi-lg-title { margin: 0; font-size: 19px; line-height: 1.3; font-weight: 800; color: #123b68; }
    .oi-lg-text { margin: 10px 0 0; font-size: 13.5px; line-height: 1.6; color: #4b6a82; }
    .oi-lg-tip { display: inline-flex; align-items: center; gap: 7px; margin: 14px 0 0; padding: 8px 12px; border-radius: 12px; background: #eef7fa; color: #0d5b77; font-size: 12px; font-weight: 700; text-align: left; }
    .oi-lg-tip svg { flex: none; width: 15px; height: 15px; }
    .oi-lg-actions { display: flex; flex-direction: column; gap: 9px; padding: 20px 26px 26px; }
    .oi-lg-btn { appearance: none; border: 0; cursor: pointer; border-radius: 14px; padding: 12px 16px; font: inherit; font-size: 13.5px; font-weight: 800; line-height: 1.2; transition: background-color .15s, transform .1s, box-shadow .15s; }
    .oi-lg-btn:active { transform: scale(.98); }
    .oi-lg-btn:focus-visible { outline: 3px solid rgba(18, 111, 145, .35); outline-offset: 2px; }
    .oi-lg-stay { background: #126f91; color: #fff; box-shadow: 0 8px 18px -8px rgba(18, 111, 145, .75); }
    .oi-lg-stay:hover { background: #0d5b77; }
    .oi-lg-leave { background: transparent; color: #b4403c; }
    .oi-lg-leave:hover { background: #fdeeee; }
    @media (min-width: 480px) { .oi-lg-actions { flex-direction: row-reverse; } .oi-lg-btn { flex: 1; } .oi-lg-leave { border: 1px solid #f3cfcd; } }
    .theme-dark .oi-lg-card { background: #14283c; box-shadow: 0 30px 80px -20px rgba(0, 0, 0, .7), 0 0 0 1px rgba(255, 255, 255, .08); }
    .theme-dark .oi-lg-title { color: #e7f2fa; }
    .theme-dark .oi-lg-text { color: #a5bccd; }
    .theme-dark .oi-lg-icon { background: linear-gradient(160deg, #3b2a0c, #54380d); color: #fbbf24; box-shadow: inset 0 0 0 1px rgba(251, 191, 36, .3); }
    .theme-dark .oi-lg-tip { background: #19384d; color: #9fd6ea; }
    .theme-dark .oi-lg-leave { color: #fca5a1; }
    .theme-dark .oi-lg-leave:hover { background: rgba(239, 68, 68, .14); }
    @media (min-width: 480px) { .theme-dark .oi-lg-leave { border-color: rgba(252, 165, 161, .3); } }
</style>

<div class="oi-lg-backdrop" id="oi-leave-guard" hidden data-oi-noguard>
    <div class="oi-lg-card" role="alertdialog" aria-modal="true" aria-labelledby="oi-lg-title" aria-describedby="oi-lg-text">
        <div class="oi-lg-top"></div>
        <div class="oi-lg-body">
            <span class="oi-lg-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </span>
            <h2 class="oi-lg-title" id="oi-lg-title">{{ $copy['title'] }}</h2>
            <p class="oi-lg-text" id="oi-lg-text">{{ $copy['text'] }}</p>
            <p class="oi-lg-tip">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                <span>{{ $copy['tip'] }}</span>
            </p>
        </div>
        <div class="oi-lg-actions">
            <button type="button" class="oi-lg-btn oi-lg-stay" data-oi-lg-stay>Ở lại làm tiếp</button>
            <button type="button" class="oi-lg-btn oi-lg-leave" data-oi-lg-leave>Vẫn thoát</button>
        </div>
    </div>
</div>

<script>
(function () {
    if (window.oiLeaveGuard) return;

    var box = document.getElementById('oi-leave-guard');
    if (!box) return;
    var stayBtn = box.querySelector('[data-oi-lg-stay]');
    var leaveBtn = box.querySelector('[data-oi-lg-leave]');
    var ignoreSel = @json($ignore);
    var leaving = false;     // đã đồng ý rời / trang đang tự điều hướng (nộp form...) -> không hỏi nữa
    var pending = null;      // việc làm tiếp khi bấm "Vẫn thoát": { type: 'url'|'form'|'back', ... }
    var lastFocus = null;

    function isActive() {
        if (leaving) return false;
        try { return !!(function () { {!! $activeJs !!} })(); } catch (e) { return true; }
    }

    function open(action) {
        pending = action;
        lastFocus = document.activeElement;
        box.hidden = false;
        // ép reflow để transition chạy
        void box.offsetWidth;
        box.classList.add('is-open');
        stayBtn.focus();
    }

    function close() {
        box.classList.remove('is-open');
        setTimeout(function () { if (!box.classList.contains('is-open')) box.hidden = true; }, 200);
        if (lastFocus && lastFocus.focus) { try { lastFocus.focus(); } catch (e) {} }
    }

    function stay() {
        var wasBack = pending && pending.type === 'back';
        pending = null;
        close();
        // đã bị Back kéo về mục gốc -> đệm lại 1 mục để lần Back sau vẫn bắt được
        if (wasBack) { try { history.pushState({ oiGuard: 1 }, '', location.href); } catch (e) {} }
    }

    function leave() {
        var action = pending;
        pending = null;
        leaving = true;
        box.classList.remove('is-open');
        box.hidden = true;
        if (!action) return;
        if (action.type === 'url') { window.location.href = action.url; }
        else if (action.type === 'form') { action.form.submit(); }
        else if (action.type === 'back') { history.back(); }
    }

    stayBtn.addEventListener('click', stay);
    leaveBtn.addEventListener('click', leave);
    box.addEventListener('click', function (e) { if (e.target === box) stay(); });
    document.addEventListener('keydown', function (e) {
        if (box.hidden) return;
        if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); stay(); }
        else if (e.key === 'Tab') {
            // giữ focus trong hộp
            e.preventDefault();
            (document.activeElement === stayBtn ? leaveBtn : stayBtn).focus();
        }
    }, true);

    // 1) Liên kết rời trang
    document.addEventListener('click', function (e) {
        if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        var a = e.target.closest ? e.target.closest('a[href]') : null;
        if (!a || a.closest('[data-oi-noguard]')) return;
        if (a.hasAttribute('download') || (a.target && a.target !== '_self')) return;
        var href = a.getAttribute('href') || '';
        if (!href || href.charAt(0) === '#' || /^(javascript|mailto|tel|blob|data):/i.test(href)) return;
        if (ignoreSel && a.matches(ignoreSel)) return;
        var url;
        try { url = new URL(a.href, location.href); } catch (err) { return; }
        if (url.origin === location.origin && url.pathname === location.pathname && url.search === location.search) return;
        if (!isActive()) return;
        e.preventDefault();
        e.stopPropagation();
        open({ type: 'url', url: url.href });
    }, true);

    // 1b) Form "Thoát bài tập" / "Dừng luyện tập"
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form && form.matches && form.matches('[data-oi-leave-form]') && isActive()) {
            e.preventDefault();
            e.stopPropagation();
            open({ type: 'form', form: form });
            return;
        }
    }, true);
    // Mọi form khác của trang (nộp bài, câu tiếp theo...) tự điều hướng -> không hỏi tab-close lúc đó
    document.addEventListener('submit', function (e) {
        if (e.defaultPrevented) return;
        leaving = true;
        setTimeout(function () { leaving = false; }, 6000);
    }, false);

    // 2) Nút Back của trình duyệt
    try { if (!(history.state && history.state.oiGuard)) history.pushState({ oiGuard: 1 }, '', location.href); } catch (e) {}
    window.addEventListener('popstate', function (e) {
        if (e.state && e.state.oiGuard) return;          // đi tiến vào mục đệm
        if (!isActive()) { history.back(); return; }      // không còn gì để mất: lùi nốt mục thật
        open({ type: 'back' });
    });

    // 3) Đóng tab / F5 — hộp của trình duyệt
    window.addEventListener('beforeunload', function (e) {
        if (!isActive()) return;
        e.preventDefault();
        e.returnValue = '';
    });

    window.oiLeaveGuard = {
        /** Trang tự điều hướng (reload, chuyển sang kết quả...) -> không hỏi. */
        allow: function () { leaving = true; },
        isActive: isActive,
    };
})();
</script>
