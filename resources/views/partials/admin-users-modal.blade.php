{{-- POPUP "CHI TIẾT NGƯỜI DÙNG" + HỘP XÁC NHẬN XÓA cho Admin > Người dùng (danh sách).

     Bấm "Chi tiết" mở popup 3 tab (Thông tin / Phân quyền / Lịch sử) — nội dung do admin.users._panel dựng sẵn ở máy chủ,
     nạp bằng fetch; các nút lưu gọi lại route PUT/DELETE sẵn có kèm Accept: application/json. Link "Chi tiết" vẫn có href
     thật tới trang admin.users.show nên khi tắt JavaScript vẫn dùng được như cũ.
     Chỉ dùng CSS nội tuyến (máy chủ không có Vite) và JavaScript thuần. --}}
<style>
    html.aum-lock{overflow:hidden}
    .aum,.aum-cf{position:fixed;inset:0;z-index:90;display:flex;align-items:center;justify-content:center;padding:24px}
    .aum-cf{z-index:95}
    .aum[hidden],.aum-cf[hidden]{display:none}
    .aum__scrim{position:absolute;inset:0;background:rgba(15,32,56,.5);backdrop-filter:blur(2px)}
    .aum__dlg{position:relative;display:flex;flex-direction:column;width:min(980px,100%);max-height:min(90vh,900px);background:#fff;border-radius:20px;box-shadow:0 24px 70px rgba(15,32,56,.35);overflow:hidden;outline:0}
    .aum--wide .aum__dlg{width:min(1320px,100%);height:94vh;max-height:94vh}
    .aum__tools{position:absolute;top:16px;right:16px;z-index:3;display:flex;gap:8px}
    .aum__tool{display:inline-grid;place-items:center;width:38px;height:38px;border:0;border-radius:11px;background:rgba(255,255,255,.75);color:#345674;cursor:pointer;transition:background .15s}
    .aum__tool:hover{background:#fff}
    .aum__tool svg{width:17px;height:17px}
    .aum__tool .i-min{display:none}
    .aum--wide .aum__tool .i-max{display:none}
    .aum--wide .aum__tool .i-min{display:block}
    .aum__body{flex:1;min-height:0;overflow-y:auto;overscroll-behavior:contain}
    .aum-load{padding:70px 24px;text-align:center;color:#587089;font-size:14px}
    .aum-load--err{color:#a3364d}
    .aum-load button{margin-left:8px}

    .aum-panel{display:flex;flex-direction:column;min-height:100%}
    .aum-id{display:flex;align-items:center;gap:16px;padding:24px 110px 14px 28px;background:linear-gradient(180deg,#eaf2fb,#f3f8fd)}
    .aum-id__txt{min-width:0}
    .aum-id h2{margin:0;font-size:20px;line-height:1.25;font-weight:800;color:#1e3a5f;overflow-wrap:anywhere}
    .aum-id__sub{margin:2px 0 6px;font-size:13px;color:#637b92;overflow-wrap:anywhere}
    .aum-state{display:inline-flex;align-items:center;gap:7px;font-size:13px;font-weight:700}
    .aum-state::before{content:"";width:7px;height:7px;border-radius:50%;background:currentColor}
    .aum-state--ok{color:#0f7a58}
    .aum-state--off{color:#b4233f}
    .aum-banner{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:0;padding:12px 28px;font-size:13px}
    .aum-banner--warn{background:#fff6e0;color:#7a5410;border-bottom:1px solid #f3dfae}
    .aum-tabs{display:flex;gap:4px;padding:0 20px;background:#f3f8fd;border-bottom:1px solid #d7e3f0;overflow-x:auto;scrollbar-width:none}
    .aum-tabs::-webkit-scrollbar{display:none}
    .aum-tabs button{position:relative;flex:none;min-height:44px;padding:0 10px;border:0;background:none;color:#587089;font:inherit;font-size:14px;font-weight:600;cursor:pointer;white-space:nowrap}
    .aum-tabs button::after{content:"";position:absolute;left:8px;right:8px;bottom:-1px;height:2px;border-radius:2px;background:transparent}
    .aum-tabs button:hover{color:#1d4ed8}
    .aum-tabs button[aria-selected="true"]{color:#1d4ed8}
    .aum-tabs button[aria-selected="true"]::after{background:#2563eb}
    .aum-count{display:inline-block;min-width:20px;margin-left:4px;padding:1px 6px;border-radius:999px;background:#e3ecf7;color:#456079;font-size:11px;font-weight:700;text-align:center}
    .aum-pane{padding:22px 28px 28px}
    .aum-pane[hidden]{display:none}
    .aum-grid{display:grid;gap:18px;grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);align-items:start}
    .aum-col{display:grid;gap:18px;min-width:0}
    .aum-card{margin:0;padding:18px;border:1px solid #d7e3f0;border-radius:16px;background:#f5f9fe}
    .aum-card--mint{background:#eef7f3;border-color:#d1e6dd}
    .aum-card--danger{background:#fff5f6;border-color:#f3d4da}
    .aum-card h3{margin:0 0 12px;font-size:15px;font-weight:800;color:#1e3a5f}
    .aum-card--mint h3,.aum-card--mint h4{color:#1f5a49}
    .aum-card h4{margin:0 0 4px;font-size:14px;font-weight:800}
    .aum-card hr{margin:14px 0;border:0;border-top:1px solid #cfe2da}
    .aum-hint{margin:0 0 12px;font-size:12.5px;line-height:1.5;color:#637b92}
    .aum-field{display:grid;gap:5px;margin-bottom:12px;min-width:0}
    .aum-field label{font-size:12.5px;font-weight:700;color:#456079}
    .aum-field label b{color:#e11d48}
    .aum-field input,.aum-field select,.aum-field textarea,.aum-perm select{width:100%;min-height:42px;padding:9px 12px;border:1px solid #c4d3e3;border-radius:10px;background:#fff;color:#1e3a5f;font:inherit;font-size:14px;transition:border-color .15s,box-shadow .15s}
    .aum-field textarea{min-height:64px;resize:vertical}
    .aum-field input:focus,.aum-field select:focus,.aum-field textarea:focus,.aum-perm select:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12);outline:0}
    .aum-field input:disabled,.aum-field select:disabled,.aum-field textarea:disabled{background:#eef2f6;color:#8aa0b6}
    .aum-field.is-bad input,.aum-field.is-bad select,.aum-field.is-bad textarea{border-color:#e11d48}
    .aum-field[hidden]{display:none}
    .aum-row2{display:grid;gap:12px;grid-template-columns:1fr 1fr}
    .aum-err{display:block;min-height:0;font-size:12px;color:#c0233f}
    .aum-err:empty{display:none}
    .aum-msg{min-height:0;margin:0 0 8px;font-size:13px;color:#587089}
    .aum-msg:empty{display:none}
    .aum-msg.is-ok{color:#0f7a58}
    .aum-msg.is-bad{color:#c0233f}
    .aum-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:8px}
    .aum-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:40px;padding:8px 16px;border:1px solid #c4d3e3;border-radius:11px;background:#fff;color:#345b80;font:inherit;font-size:13px;font-weight:700;cursor:pointer;transition:background .15s,border-color .15s,opacity .15s}
    .aum-btn:hover:not(:disabled){background:#f0f6fd;border-color:#93b4d8}
    .aum-btn:disabled{opacity:.5;cursor:not-allowed}
    .aum-btn--primary{border-color:#2563eb;background:#2563eb;color:#fff}
    .aum-btn--primary:hover:not(:disabled){background:#1d4ed8;border-color:#1d4ed8}
    .aum-btn--danger{border-color:#e7b3bd;background:#fff;color:#b4233f}
    .aum-btn--danger:hover:not(:disabled){background:#ffeef1;border-color:#d98294}
    .aum-btn svg{width:15px;height:15px}
    .aum-dl{display:grid;gap:8px;margin:0}
    .aum-dl>div{display:grid;grid-template-columns:78px minmax(0,1fr);gap:10px;font-size:13px}
    .aum-dl dt{color:#6b8a80}
    .aum-dl dd{margin:0;color:#1e3a5f;font-weight:600;overflow-wrap:anywhere}
    .aum-roles{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px}
    .aum-roles label{display:inline-flex;align-items:center;gap:8px;min-height:36px;padding:0 12px;border:1px solid #d7e3f0;border-radius:11px;background:#fff;color:#345674;font-size:13px;font-weight:600;cursor:pointer}
    .aum-roles input{accent-color:#2563eb}
    .aum-sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}

    .aum-note{margin-bottom:18px;padding:12px 16px;border-radius:12px;background:#eef3fa;color:#456079;font-size:13.5px;line-height:1.55}
    .aum-note--lock{background:#fff6e0;color:#7a5410}
    .aum-permhead{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:6px}
    .aum-permhead h3{margin:0 0 3px;font-size:15px;font-weight:800;color:#1e3a5f}
    .aum-permcount{flex:none;padding:5px 11px;border-radius:999px;background:#e8f0fb;color:#1d4ed8;font-size:12.5px;font-weight:700;white-space:nowrap}
    .aum-permtable{border:1px solid #d7e3f0;border-radius:14px;overflow:hidden;background:#fff}
    .aum-permcols{display:grid;grid-template-columns:minmax(0,1fr) 170px;gap:12px;padding:9px 16px;background:#f3f8fd;border-bottom:1px solid #d7e3f0;font-size:12px;font-weight:700;color:#587089;text-transform:uppercase;letter-spacing:.03em}
    .aum-permgroup{margin:0;padding:0;border:0;min-width:0}
    .aum-permgroup legend{display:block;width:100%;padding:11px 16px 7px;font-size:13px;font-weight:800;color:#1e3a5f;background:#fff}
    .aum-perm{display:grid;grid-template-columns:minmax(0,1fr) 170px;align-items:center;gap:12px;padding:6px 16px;border-top:1px solid #edf2f8;transition:background .15s}
    .aum-perm.is-changed{background:#f3f8ff}
    .aum-perm__main{display:flex;align-items:center;gap:11px;min-height:38px;font-size:14px;color:#1e3a5f;cursor:pointer;min-width:0}
    .aum-perm__main input{width:18px;height:18px;flex:none;accent-color:#2563eb}
    .aum-perm select{min-height:36px;padding:5px 10px;font-size:13px}
    .aum-perm select:disabled{background:#f1f4f8;color:#9bb0c4}
    .aum-tag{padding:2px 8px;border-radius:7px;font-size:11px;font-style:normal;font-weight:700}
    .aum-tag--revoked{background:#fdf1d8;color:#8a5f10}
    .aum-tag--extra{background:#e6effc;color:#2554a3}
    .aum-permfoot{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:16px}
    .aum-permstate{font-size:12.5px;color:#637b92}
    .aum-permstate.is-dirty{color:#2554a3;font-weight:700}

    .aum-empty{padding:40px 16px;border:1px dashed #c8d7e8;border-radius:14px;text-align:center;color:#637b92;font-size:14px}
    .aum-log{list-style:none;margin:0;padding:6px 18px;border:1px solid #d7e3f0;border-radius:14px;background:#f8fbfe}
    .aum-log__item{position:relative;display:grid;gap:3px;padding:14px 0 14px 22px;border-bottom:1px solid #e4edf6}
    .aum-log__item:last-child{border-bottom:0}
    .aum-log__item::before{content:"";position:absolute;left:2px;top:20px;width:9px;height:9px;border-radius:50%;background:#9db4cd}
    .aum-log__item--ok::before{background:#10b981}
    .aum-log__item--danger::before{background:#e11d48}
    .aum-log__item--info::before{background:#3b82f6}
    .aum-log__item strong{font-size:14px;font-weight:800;color:#1e3a5f}
    .aum-log__meta{font-size:12px;color:#6b8199}
    .aum-log__line{font-size:13.5px;color:#2c4660;overflow-wrap:anywhere}
    .aum-log__reason{color:#7a4a56}
    .aum-pg{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-top:14px;font-size:12.5px;color:#637b92}
    .aum-pg[hidden]{display:none}
    .aum-pg__btns{display:flex;gap:6px}
    .aum-pg__btns button{min-width:34px;height:34px;padding:0 8px;border:1px solid #c4d3e3;border-radius:9px;background:#fff;color:#345b80;font:inherit;font-size:13px;font-weight:700;cursor:pointer}
    .aum-pg__btns button.is-on{background:#2563eb;border-color:#2563eb;color:#fff}
    .aum-pg__btns button:disabled{opacity:.45;cursor:not-allowed}

    .aum-cf__dlg{position:relative;width:min(460px,100%);padding:24px;border-radius:18px;background:#fff;box-shadow:0 24px 70px rgba(15,32,56,.4)}
    .aum-cf__dlg h3{margin:0 0 6px;font-size:17px;font-weight:800;color:#1e3a5f}
    .aum-cf__dlg p{margin:0 0 14px;font-size:13.5px;line-height:1.55;color:#587089}
    .aum-cf__dlg .aum-field{margin-bottom:10px}

    /* nút Xóa / Khôi phục trong bảng danh sách */
    .aux-rowbtn{display:inline-flex;align-items:center;gap:5px;padding:0;border:0;background:none;font:inherit;font-size:13px;font-weight:700;cursor:pointer}
    .aux-rowbtn svg{width:14px;height:14px}
    .aux-rowbtn--del{color:#c0233f}
    .aux-rowbtn--del:hover:not(:disabled){color:#8f1630;text-decoration:underline}
    .aux-rowbtn--restore{color:#0f7a58}
    .aux-rowbtn:disabled{color:#b5c0cc;cursor:not-allowed}
    .aux-actions{display:flex;align-items:center;justify-content:flex-end;gap:6px 16px;flex-wrap:wrap}
    @media (max-width:767px){.aux-actions{justify-content:flex-start;margin-top:4px}}

    @media (max-width:860px){.aum-grid{grid-template-columns:1fr}}
    @media (max-width:640px){
        .aum,.aum-cf{padding:0}
        .aum__dlg,.aum--wide .aum__dlg{width:100%;height:100%;max-height:none;border-radius:0}
        .aum-cf{align-items:flex-end}
        .aum-cf__dlg{width:100%;border-radius:18px 18px 0 0}
        .aum-id{padding:20px 100px 12px 18px;gap:12px}
        .aum-id h2{font-size:17px}
        .aum-pane{padding:16px 16px 24px}
        .aum-tabs{padding:0 10px}
        .aum-banner{padding:12px 18px}
        .aum-row2{grid-template-columns:1fr}
        .aum-permcols{display:none}
        .aum-perm{grid-template-columns:1fr;gap:4px;padding:8px 14px}
        .aum-perm select{margin:0 0 6px 29px;width:calc(100% - 29px)}
        .aum-permhead{flex-direction:column}
        .aum-actions .aum-btn{flex:1 1 auto}
    }
</style>

<div class="aum" id="aum" hidden
     data-u-panel="{{ route('admin.users.panel', '__ID__') }}"
     data-u-del="{{ route('admin.users.destroy', '__ID__') }}"
     data-u-restore="{{ route('admin.users.restore', '__ID__') }}">
    <div class="aum__scrim" data-aum-close></div>
    <div class="aum__dlg" role="dialog" aria-modal="true" aria-labelledby="aumTitle" tabindex="-1">
        <div class="aum__tools">
            <button type="button" class="aum__tool" id="aumExpand" aria-pressed="false" aria-label="Mở rộng cửa sổ" title="Mở rộng">
                <x-lucide name="maximize-2" class="i-max" /><x-lucide name="minimize-2" class="i-min" />
            </button>
            <button type="button" class="aum__tool" data-aum-close aria-label="Đóng" title="Đóng"><x-lucide name="x" /></button>
        </div>
        <div class="aum__body" id="aumBody"></div>
    </div>
</div>

<div class="aum-cf" id="aumDel" hidden>
    <div class="aum__scrim" data-cf-close></div>
    <form class="aum-cf__dlg" role="alertdialog" aria-modal="true" aria-labelledby="aumDelTitle" aria-describedby="aumDelDesc" novalidate>
        <h3 id="aumDelTitle">Xóa người dùng?</h3>
        <p id="aumDelDesc">Tài khoản <b data-del-name></b> sẽ bị xóa mềm: không đăng nhập được nữa, dữ liệu vẫn được giữ và có thể khôi phục ở tab "Đã xóa". Hành động này được ghi vào lịch sử.</p>
        <div class="aum-field">
            <label for="aumDelReason">Lý do xóa <b aria-hidden="true">*</b></label>
            <textarea id="aumDelReason" rows="3" maxlength="1000" placeholder="Ví dụ: Tài khoản trùng"></textarea>
            <span class="aum-err" data-del-err></span>
        </div>
        <div class="aum-actions">
            <button type="button" class="aum-btn" data-cf-close>Hủy</button>
            <button type="submit" class="aum-btn aum-btn--danger" data-del-ok><x-lucide name="trash-2" class="h-4 w-4" /> Xóa người dùng</button>
        </div>
    </form>
</div>

<script>
    (function () {
        var shell = document.getElementById('aum');
        var cf = document.getElementById('aumDel');
        if (!shell || !cf) return;
        var body = document.getElementById('aumBody');
        var dlg = shell.querySelector('.aum__dlg');
        var cfForm = cf.querySelector('form');
        var meta = document.querySelector('meta[name="csrf-token"]');
        var csrf = meta ? meta.getAttribute('content') : '';
        var state = { id: null, tab: 'info', changed: false, opener: null, delId: null, delOpener: null, panel: null };

        function tpl(key, id) { return shell.dataset[key].replace('__ID__', id); }
        function toast(type, msg) { if (window.toast) window.toast(type, msg); }

        /* Gửi PUT/DELETE/POST dạng JSON (Laravel nhận _method qua POST) và trả {ok, status, json}. */
        function send(method, url, fd) {
            fd = fd || new FormData();
            if (method !== 'POST') fd.set('_method', method);
            fd.delete('_token');
            return fetch(url, {
                method: 'POST', body: fd, credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (r) {
                return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, status: r.status, json: j }; });
            }).catch(function () { return { ok: false, status: 0, json: { message: 'Không kết nối được máy chủ. Thử lại sau.' } }; });
        }

        function qs(sel, root) { return (root || state.panel).querySelector(sel); }
        function qsa(sel, root) { return Array.prototype.slice.call((root || state.panel).querySelectorAll(sel)); }

        /* ---------- mở / đóng popup ---------- */
        function open(id, tab, opener) {
            state.id = id; state.tab = tab || 'info'; state.changed = false; state.opener = opener || null;
            shell.hidden = false;
            document.documentElement.classList.add('aum-lock');
            body.innerHTML = '<div class="aum-load" role="status">Đang tải thông tin người dùng…</div>';
            body.scrollTop = 0;
            dlg.focus();
            load();
        }
        function close() {
            if (cf.hidden === false) closeDel();
            shell.hidden = true;
            document.documentElement.classList.remove('aum-lock');
            body.innerHTML = ''; state.panel = null;
            if (state.changed) { window.location.reload(); return; }
            if (state.opener && document.contains(state.opener)) state.opener.focus();
        }
        function load() {
            return fetch(tpl('uPanel', state.id), { credentials: 'same-origin', headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
                .then(function (html) { var top = body.scrollTop; body.innerHTML = html; bind(); selectTab(state.tab); body.scrollTop = top; })
                .catch(function () {
                    body.innerHTML = '<div class="aum-load aum-load--err">Không tải được chi tiết người dùng. <button type="button" class="aum-btn" data-retry>Thử lại</button></div>';
                });
        }

        /* ---------- tab ---------- */
        function selectTab(name) {
            if (!state.panel) return;
            state.tab = name;
            qsa('[data-tab]').forEach(function (b) {
                var on = b.dataset.tab === name;
                b.setAttribute('aria-selected', on ? 'true' : 'false');
                b.tabIndex = on ? 0 : -1;
            });
            qsa('[data-pane]').forEach(function (p) { p.hidden = p.dataset.pane !== name; });
        }

        /* ---------- lỗi / thông báo trong form ---------- */
        function clearErrors(form) {
            qsa('.aum-err', state.panel).forEach(function (e) { e.textContent = ''; });
            qsa('.is-bad', state.panel).forEach(function (e) { e.classList.remove('is-bad'); });
            var m = form && form.querySelector('[data-msg]'); if (m) { m.textContent = ''; m.className = 'aum-msg'; }
        }
        function say(form, text, ok) {
            var m = form.querySelector('[data-msg]');
            if (m) { m.textContent = text; m.className = 'aum-msg ' + (ok ? 'is-ok' : 'is-bad'); }
        }
        function showErrors(form, res) {
            var errs = (res.json && res.json.errors) || {}, left = [];
            Object.keys(errs).forEach(function (k) {
                var el = qs('[data-err="' + k + '"]');
                if (el) {
                    el.textContent = errs[k][0];
                    var f = el.closest('.aum-field'); if (f) f.classList.add('is-bad');
                } else left.push(errs[k][0]);
            });
            if (!Object.keys(errs).length) left.push((res.json && res.json.message) || (res.status === 403 ? 'Bạn không có quyền thực hiện thao tác này.' : 'Có lỗi xảy ra, vui lòng thử lại.'));
            if (left.length) say(form, left.join(' '), false);
        }
        function busy(btn, on) { if (btn) { btn.disabled = on; btn.dataset.busy = on ? '1' : ''; } }

        /* ---------- gắn sự kiện cho nội dung vừa nạp ---------- */
        function bind() {
            state.panel = body.querySelector('.aum-panel');
            if (!state.panel) return;
            var p = state.panel, trashed = p.dataset.trashed === '1';

            qsa('[data-tab]').forEach(function (b) {
                b.addEventListener('click', function () { selectTab(b.dataset.tab); });
                b.addEventListener('keydown', function (e) {
                    var tabs = qsa('[data-tab]'), i = tabs.indexOf(b);
                    if (e.key === 'ArrowRight') i = (i + 1) % tabs.length; else if (e.key === 'ArrowLeft') i = (i + tabs.length - 1) % tabs.length; else return;
                    e.preventDefault(); selectTab(tabs[i].dataset.tab); tabs[i].focus();
                });
            });

            bindInfo(trashed); bindPassword(trashed); bindRoles(trashed); bindPerms(trashed); bindHistory();

            var del = qs('[data-del]'); if (del) del.addEventListener('click', function () { openDel(del.dataset.del, del.dataset.name, del); });
            var rs = qs('[data-restore]'); if (rs) rs.addEventListener('click', function () { restore(rs.dataset.restore, rs); });

            if (trashed) {
                qsa('[data-pane="info"] input, [data-pane="info"] select, [data-pane="info"] textarea, [data-pane="info"] button, [data-pane="perms"] input, [data-pane="perms"] select, [data-pane="perms"] button').forEach(function (e) { e.disabled = true; });
            }
        }

        function snapshot(form) {
            var out = [];
            new FormData(form).forEach(function (v, k) { if (k !== '_token') out.push(k + '=' + v); });
            return out.join('&');
        }

        function bindInfo(trashed) {
            var form = qs('#aumInfoForm'), save = qs('[data-save="info"]'), reset = qs('[data-reset="info"]');
            var status = qs('#aumStatus'), box = qs('[data-reason-box]'), reason = qs('#aumReason');
            var base = snapshot(form);
            function refresh() {
                var needReason = status.value === 'suspended' && status.dataset.orig !== 'suspended';
                box.hidden = !needReason;
                var dirty = snapshot(form) !== base;
                save.disabled = trashed || !dirty; reset.disabled = trashed || !dirty;
            }
            form.addEventListener('input', refresh); form.addEventListener('change', refresh);
            status.addEventListener('change', refresh); reason.addEventListener('input', refresh);
            reset.addEventListener('click', function () { form.reset(); clearErrors(form); refresh(); });
            form.addEventListener('submit', function (e) {
                e.preventDefault(); clearErrors(form);
                if (!form.querySelector('[name="name"]').value.trim()) { showErrors(form, { json: { errors: { name: ['Vui lòng nhập họ tên.'] } } }); return; }
                if (!form.querySelector('[name="email"]').value.trim()) { showErrors(form, { json: { errors: { email: ['Vui lòng nhập email.'] } } }); return; }
                busy(save, true);
                send('PUT', form.action, new FormData(form)).then(function (res) {
                    if (res.ok) { state.changed = true; toast('success', res.json.message || 'Đã lưu.'); load(); }
                    else { busy(save, false); showErrors(form, res); }
                });
            });
            refresh();
        }

        function bindPassword(trashed) {
            var form = qs('#aumPwForm'), btn = qs('[data-save="pw"]');
            form.addEventListener('submit', function (e) {
                e.preventDefault(); clearErrors(form);
                var a = form.elements.password.value, b = form.elements.password_confirmation.value;
                if (a.length < 8) { showErrors(form, { json: { errors: { password: ['Mật khẩu tối thiểu 8 ký tự.'] } } }); return; }
                if (a !== b) { showErrors(form, { json: { errors: { password_confirmation: ['Mật khẩu xác nhận không khớp.'] } } }); return; }
                busy(btn, true);
                send('PUT', form.action, new FormData(form)).then(function (res) {
                    busy(btn, false);
                    if (res.ok) { form.reset(); state.changed = true; say(form, res.json.message || 'Đã đổi mật khẩu.', true); toast('success', res.json.message || 'Đã đổi mật khẩu.'); loadKeep(); }
                    else showErrors(form, res);
                });
            });
        }
        /* Nạp lại để tab Lịch sử cập nhật nhưng không xóa thông báo vừa hiện. */
        function loadKeep() { var t = state.tab; load().then(function () { selectTab(t); }); }

        function bindRoles(trashed) {
            var form = qs('#aumRolesForm'), btn = qs('[data-save="roles"]');
            if (!form || !btn) return;
            form.addEventListener('submit', function (e) {
                e.preventDefault(); clearErrors(form); busy(btn, true);
                var fd = new FormData(form);
                send('PUT', form.action, fd).then(function (res) {
                    if (res.ok) { state.changed = true; toast('success', res.json.message || 'Đã lưu vai trò.'); load(); }
                    else { busy(btn, false); showErrors(form, res); }
                });
            });
        }

        function bindPerms(trashed) {
            var form = qs('#aumPermForm');
            if (!form) return;
            var rows = qsa('[data-perm]', form), save = qs('[data-save="perms"]', form), reset = qs('[data-reset="perms"]', form);
            var countEl = qs('[data-perm-count]', form), stateEl = qs('[data-perm-state]', form);
            var readonly = form.dataset.readonly === '1' || trashed;
            function refresh() {
                var on = 0, dirty = 0;
                rows.forEach(function (r) {
                    var cb = r.querySelector('input[type=checkbox]'), sel = r.querySelector('select');
                    if (!readonly) sel.disabled = !cb.checked;
                    if (cb.checked) on++;
                    var changed = cb.checked !== (r.dataset.on === '1') || (cb.checked && sel.value !== r.dataset.scope);
                    r.classList.toggle('is-changed', changed);
                    if (changed) dirty++;
                });
                countEl.textContent = on;
                if (stateEl) { stateEl.textContent = dirty ? dirty + ' thay đổi chưa lưu' : 'Không có thay đổi mới'; stateEl.classList.toggle('is-dirty', dirty > 0); }
                if (save) { save.disabled = readonly || !dirty; reset.disabled = readonly || !dirty; }
            }
            form.addEventListener('change', refresh);
            if (reset) reset.addEventListener('click', function () {
                rows.forEach(function (r) { r.querySelector('input[type=checkbox]').checked = r.dataset.on === '1'; r.querySelector('select').value = r.dataset.scope; });
                clearErrors(form); refresh();
            });
            form.addEventListener('submit', function (e) {
                e.preventDefault(); if (readonly) return; clearErrors(form);
                var fd = new FormData();
                rows.forEach(function (r) {
                    var cb = r.querySelector('input[type=checkbox]'), sel = r.querySelector('select'), s = r.dataset.slug;
                    fd.append('perms[' + s + '][on]', cb.checked ? '1' : '0');
                    fd.append('perms[' + s + '][scope]', sel.value);
                });
                busy(save, true);
                send('PUT', form.action, fd).then(function (res) {
                    if (res.ok) { if (res.json.changed) state.changed = true; toast('success', res.json.message || 'Đã lưu phân quyền.'); load(); }
                    else { busy(save, false); showErrors(form, res); }
                });
            });
            refresh();
        }

        function bindHistory() {
            var list = qs('[data-hist]'); if (!list) return;
            var nav = qs('[data-hist-nav]'), items = qsa('[data-hist-item]', list), SIZE = 10, page = 1;
            function render() {
                var pages = Math.max(1, Math.ceil(items.length / SIZE));
                page = Math.min(Math.max(1, page), pages);
                var from = (page - 1) * SIZE;
                items.forEach(function (li, i) { li.hidden = !(i >= from && i < from + SIZE); });
                nav.hidden = pages <= 1; nav.innerHTML = '';
                if (pages <= 1) return;
                var info = document.createElement('span');
                info.textContent = (from + 1) + '–' + Math.min(from + SIZE, items.length) + ' / ' + items.length + ' mục · Trang ' + page + '/' + pages;
                var w = document.createElement('div'); w.className = 'aum-pg__btns';
                function b(t, label, dis, on, fn) { var x = document.createElement('button'); x.type = 'button'; x.textContent = t; x.setAttribute('aria-label', label); x.disabled = dis; if (on) x.className = 'is-on'; x.addEventListener('click', fn); w.appendChild(x); }
                b('‹', 'Trang trước', page === 1, false, function () { page--; render(); });
                for (var n = 1; n <= pages; n++) (function (n) { if (n === 1 || n === pages || Math.abs(n - page) <= 1) b(String(n), 'Trang ' + n, false, n === page, function () { page = n; render(); }); })(n);
                b('›', 'Trang sau', page === pages, false, function () { page++; render(); });
                nav.appendChild(info); nav.appendChild(w);
            }
            render();
        }

        /* ---------- xóa / khôi phục ---------- */
        function openDel(id, name, opener) {
            state.delId = id; state.delOpener = opener || null;
            cf.querySelector('[data-del-name]').textContent = name;
            var ta = cf.querySelector('#aumDelReason'); ta.value = '';
            cf.querySelector('[data-del-err]').textContent = '';
            cf.hidden = false; document.documentElement.classList.add('aum-lock');
            ta.focus();
        }
        function closeDel() {
            cf.hidden = true;
            if (shell.hidden) document.documentElement.classList.remove('aum-lock');
            if (state.delOpener && document.contains(state.delOpener)) state.delOpener.focus();
        }
        cfForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var ta = cf.querySelector('#aumDelReason'), err = cf.querySelector('[data-del-err]'), ok = cf.querySelector('[data-del-ok]');
            err.textContent = '';
            if (!ta.value.trim()) { err.textContent = 'Vui lòng nhập lý do xóa.'; ta.focus(); return; }
            busy(ok, true);
            var fd = new FormData(); fd.set('reason', ta.value.trim());
            send('DELETE', tpl('uDel', state.delId), fd).then(function (res) {
                if (res.ok) { window.location.reload(); return; }
                busy(ok, false);
                var errs = (res.json && res.json.errors) || {}, first = null;
                Object.keys(errs).forEach(function (k) { if (!first) first = errs[k][0]; });
                err.textContent = first || (res.json && res.json.message) || 'Không xóa được người dùng.';
            });
        });
        function restore(id, btn) {
            busy(btn, true);
            send('POST', tpl('uRestore', id)).then(function (res) {
                if (res.ok) { window.location.reload(); return; }
                busy(btn, false); toast('error', (res.json && res.json.message) || 'Không khôi phục được người dùng.');
            });
        }

        /* ---------- sự kiện toàn trang ---------- */
        document.addEventListener('click', function (e) {
            var a = e.target.closest ? e.target.closest('[data-user-open]') : null;
            if (a && !(e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1)) { e.preventDefault(); open(a.dataset.userOpen, 'info', a); return; }
            var d = e.target.closest ? e.target.closest('[data-user-del]') : null;
            if (d && !d.disabled) { e.preventDefault(); openDel(d.dataset.userDel, d.dataset.name, d); return; }
            var r = e.target.closest ? e.target.closest('[data-user-restore]') : null;
            if (r && !r.disabled) { e.preventDefault(); restore(r.dataset.userRestore, r); return; }
        });
        shell.addEventListener('click', function (e) {
            if (e.target.closest('[data-aum-close]')) close();
            else if (e.target.closest('[data-retry]')) load();
        });
        cf.addEventListener('click', function (e) { if (e.target.closest('[data-cf-close]')) closeDel(); });
        document.getElementById('aumExpand').addEventListener('click', function () {
            var on = shell.classList.toggle('aum--wide');
            this.setAttribute('aria-pressed', on ? 'true' : 'false');
            this.setAttribute('aria-label', on ? 'Thu nhỏ cửa sổ' : 'Mở rộng cửa sổ');
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                if (!cf.hidden) { closeDel(); e.stopPropagation(); } else if (!shell.hidden) close();
                return;
            }
            if (e.key !== 'Tab') return;
            var layer = !cf.hidden ? cf.querySelector('.aum-cf__dlg') : (!shell.hidden ? dlg : null);
            if (!layer) return;
            var f = Array.prototype.filter.call(layer.querySelectorAll('a[href],button,input,select,textarea,[tabindex]:not([tabindex="-1"])'), function (x) { return !x.disabled && x.offsetParent !== null; });
            if (!f.length) return;
            var first = f[0], last = f[f.length - 1];
            if (e.shiftKey && (document.activeElement === first || document.activeElement === layer)) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        });
    })();
</script>
