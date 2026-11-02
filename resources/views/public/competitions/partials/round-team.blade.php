{{--
    SỬA 2/11 — khối "Người tổ chức & làm bù" ở tab Tổng quan của ban tổ chức (dữ liệu: ContestRoundTeamService::panel()).
      · Giáo viên cùng tổ chức: chỉ người chủ trì kỳ / admin thêm, gỡ. Người được thêm sửa được kỳ (trừ xoá kỳ).
      · Làm bù: kỳ đã kết thúc → chọn học sinh chưa nộp bài, đặt hạn; bài làm bù chấm và xếp hạng như bình thường.
--}}
@php
    $t = $roundTeam;
    $mk = $t['makeup'];
    $box = 'margin:14px 0 4px;padding:12px 14px;border:1px solid #d6e3ec;border-radius:12px;background:#fff';
    $ico = 'display:grid;place-items:center;width:34px;height:34px;border-radius:10px;background:#eef4fb;color:#1d4f86;flex:none';
    $btnSm = 'height:28px;padding:0 10px;border:1px solid #e3c9c9;border-radius:8px;background:#fff;color:#a33b3b;font-size:12px;cursor:pointer';
    $input = 'height:36px;border:1px solid #d6e3ec;border-radius:9px;padding:0 10px;font-size:12px;color:#123b68;background:#fff';
    $muted = 'font-size:12px;line-height:1.5;color:#61798b';
    $stateColor = ['done' => '#1b7a43', 'doing' => '#126f91', 'expired' => '#a33b3b', 'waiting' => '#8a6d1d'];
@endphp
<div id="to-chuc-ky" style="{{ $box }}">
    <div style="display:flex;align-items:center;gap:10px 14px;flex-wrap:wrap">
        <span style="{{ $ico }}"><x-lucide name="users" style="width:18px;height:18px" /></span>
        <span style="flex:1 1 260px;min-width:0">
            <b style="display:block;font-size:13px;color:#123b68">Người tổ chức {{ $survey ? 'kỳ khảo sát' : 'kỳ thi' }} này</b>
            <span style="display:block;margin-top:2px;{{ $muted }}">Chỉ người chủ trì, giáo viên được thêm vào bên dưới và admin mới sửa được kỳ này (đề, học sinh, làm bù…). Giáo viên khác muốn tham gia phải được người chủ trì thêm vào.</span>
        </span>
    </div>

    <ul style="list-style:none;margin:10px 0 0;padding:0;display:grid;gap:6px">
        <li style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:13px;color:#123b68">
            <span style="padding:2px 8px;border-radius:999px;background:#e0f0f6;color:#126f91;font-size:11px;font-weight:600">Chủ trì</span>
            <b>{{ $t['ownerName'] }}</b>
            @if ($t['ownerIsLegacy'])<span style="{{ $muted }}">(kỳ cũ — giáo viên tổ chức cuộc thi quản lý)</span>@endif
        </li>
        @foreach ($t['managers'] as $m)
            <li style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:13px;color:#123b68">
                <span style="padding:2px 8px;border-radius:999px;background:#f1f5f9;color:#475569;font-size:11px;font-weight:600">Cùng tổ chức</span>
                <b>{{ $m['name'] }}</b>@if ($m['account'] !== '')<span style="{{ $muted }}">{{ $m['account'] }}</span>@endif
                @if ($t['canOwn'])
                    <form method="POST" action="{{ $m['removeUrl'] }}" style="margin-left:auto" onsubmit="return confirm('Gỡ {{ addslashes($m['name']) }} khỏi ban tổ chức kỳ này?');">
                        @csrf
                        <button type="submit" style="{{ $btnSm }}">Gỡ</button>
                    </form>
                @endif
            </li>
        @endforeach
    </ul>

    @if (! $t['supported'])
        <p style="margin:8px 0 0;{{ $muted }}">Máy chủ chưa chạy migration — chạy "php artisan migrate" để thêm người cùng tổ chức.</p>
    @elseif ($t['canOwn'])
        <form method="POST" action="{{ $t['addUrl'] }}" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px">
            @csrf
            <select name="user_id" required aria-label="Chọn giáo viên cùng tổ chức" style="{{ $input }};flex:1 1 240px;max-width:420px">
                <option value="">— Chọn giáo viên để thêm vào —</option>
                @foreach ($t['teacherOptions'] as $opt)
                    <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                @endforeach
            </select>
            <button type="submit" class="hub-primary-action" style="display:inline-flex;align-items:center;gap:6px;flex:none;white-space:nowrap" @disabled($t['teacherOptions'] === [])><x-lucide name="user-plus" style="width:15px;height:15px" />Thêm</button>
        </form>
    @else
        <p style="margin:8px 0 0;{{ $muted }}">Bạn đang là người cùng tổ chức — chỉ người chủ trì hoặc admin thêm / gỡ người cùng tổ chức và xoá kỳ.</p>
    @endif
</div>

<div id="lam-bu" style="{{ $box }}">
    <div style="display:flex;align-items:center;gap:10px 14px;flex-wrap:wrap">
        <span style="{{ $ico }};background:#fff4e0;color:#9a6200"><x-lucide name="clock-3" style="width:18px;height:18px" /></span>
        <span style="flex:1 1 260px;min-width:0">
            <b style="display:block;font-size:13px;color:#123b68">Cho học sinh làm bù</b>
            <span style="display:block;margin-top:2px;{{ $muted }}">
                @if ($mk['ended'])
                    Kỳ đã kết thúc. Chọn học sinh quên / chưa làm bài và đặt hạn — các em vào làm như lúc kỳ đang mở{{ $survey ? ' (không cần nhập mật khẩu)' : '' }}. Bài làm bù được chấm, xếp hạng và in bảng điểm như bình thường.
                @else
                    Khi kỳ kết thúc, bạn có thể cho học sinh quên / chưa làm bài được làm bù ở đây.
                @endif
            </span>
        </span>
    </div>

    @if (! $mk['supported'])
        <p style="margin:8px 0 0;{{ $muted }}">Máy chủ chưa chạy migration — chạy "php artisan migrate" để dùng tính năng làm bù.</p>
    @else
        @if ($mk['rows'] !== [])
            <div style="margin-top:10px;overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:12px;color:#123b68">
                    <thead><tr style="text-align:left;color:#61798b">
                        <th style="padding:6px 8px;border-bottom:1px solid #e5edf3">Học sinh</th>
                        <th style="padding:6px 8px;border-bottom:1px solid #e5edf3">Hạn làm bù</th>
                        <th style="padding:6px 8px;border-bottom:1px solid #e5edf3">Trạng thái</th>
                        <th style="padding:6px 8px;border-bottom:1px solid #e5edf3"></th>
                    </tr></thead>
                    <tbody>
                        @foreach ($mk['rows'] as $r)
                            <tr>
                                <td style="padding:6px 8px;border-bottom:1px solid #f0f4f7"><b>{{ $r['name'] }}</b>@if ($r['account'] !== '')<br><span style="color:#61798b">{{ $r['account'] }}</span>@endif</td>
                                <td style="padding:6px 8px;border-bottom:1px solid #f0f4f7;white-space:nowrap">{{ $r['deadline'] }}@if ($r['addedBy'])<br><span style="color:#61798b">bởi {{ $r['addedBy'] }}</span>@endif</td>
                                <td style="padding:6px 8px;border-bottom:1px solid #f0f4f7;white-space:nowrap;font-weight:600;color:{{ $stateColor[$r['state']] }}">{{ $r['stateLabel'] }}</td>
                                <td style="padding:6px 8px;border-bottom:1px solid #f0f4f7;text-align:right">
                                    @if ($r['state'] !== 'done')
                                        <form method="POST" action="{{ $r['removeUrl'] }}" onsubmit="return confirm('Huỷ quyền làm bù của {{ addslashes($r['name']) }}?');">
                                            @csrf
                                            <button type="submit" style="{{ $btnSm }}">Huỷ</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($mk['ended'])
            {{-- SỬA 2/11 (khách: "đổ hết toàn bộ danh sách học sinh, dạng select UI thật đẹp dễ dùng") — ô chọn nhiều học sinh có
                 tìm kiếm (gõ không dấu cũng được), chọn xong hiện thành thẻ tên; + thời gian làm bù → "Cho làm bù".
                 Viết bằng JS thường (không Alpine) — chạy độc lập trong khối này. --}}
            <form method="POST" action="{{ $mk['addUrl'] }}" class="mkp-form" data-mkp>
                @csrf
                <script type="application/json" data-mkp-data>{!! json_encode(['students' => $mk['candidates'], 'old' => array_map('intval', (array) old('student_ids', []))], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
                <label class="mkp-label" for="mkp-search-{{ $round['id'] }}">Học sinh làm bù</label>
                <div class="mkp" data-mkp-root>
                    <div class="mkp-field" data-mkp-field>
                        <x-lucide name="users" class="mkp-lead" />
                        <div class="mkp-chips" data-mkp-chips></div>
                        <input id="mkp-search-{{ $round['id'] }}" class="mkp-search" data-mkp-search type="text" autocomplete="off" role="combobox"
                               aria-expanded="false" aria-autocomplete="list" placeholder="Chọn học sinh… (gõ tên để tìm)">
                        <button type="button" class="mkp-caret" data-mkp-caret aria-label="Mở danh sách học sinh"><x-lucide name="chevron-down" /></button>
                    </div>
                    <div class="mkp-panel" data-mkp-panel hidden>
                        <div class="mkp-head">
                            <span data-mkp-count></span>
                            <span class="mkp-head-actions">
                                <button type="button" data-mkp-all>Chọn tất cả</button>
                                <button type="button" data-mkp-none>Bỏ chọn</button>
                            </span>
                        </div>
                        <ul class="mkp-list" role="listbox" aria-multiselectable="true" data-mkp-list></ul>
                        <div class="mkp-foot" data-mkp-foot></div>
                    </div>
                    <div data-mkp-inputs></div>
                </div>

                <div class="mkp-row">
                    <label class="mkp-when">
                        <span class="mkp-label">Thời gian làm bù đến</span>
                        <input type="datetime-local" name="deadline_at" required value="{{ old('deadline_at', $mk['defaultDeadline']) }}" min="{{ $mk['minDeadline'] }}">
                    </label>
                    <button type="submit" class="hub-primary-action mkp-submit" data-mkp-submit disabled><x-lucide name="clock-3" />Cho làm bù<span data-mkp-badge></span></button>
                </div>
            </form>
            @once
                <style>
                    .mkp-form{margin-top:12px;display:grid;gap:12px}
                    .mkp-label{display:block;margin-bottom:5px;font-size:12px;font-weight:600;color:#123b68}
                    .mkp{position:relative}
                    .mkp-field{display:flex;align-items:center;flex-wrap:wrap;gap:6px;min-height:44px;padding:6px 8px 6px 38px;border:1px solid #cfdce7;border-radius:12px;background:#fff;cursor:text;position:relative;transition:border-color .15s,box-shadow .15s}
                    .mkp-field:hover{border-color:#a9c3d6}
                    .mkp.is-open .mkp-field,.mkp-field:focus-within{border-color:#126f91;box-shadow:0 0 0 3px rgba(18,111,145,.14)}
                    .mkp-lead{position:absolute;left:12px;top:13px;width:17px;height:17px;color:#7e93a6}
                    .mkp-chips{display:contents}
                    .mkp-chip{display:inline-flex;align-items:center;gap:6px;max-width:100%;height:28px;padding:0 4px 0 4px;border-radius:999px;background:#e8f3f8;color:#0f4f6a;font-size:12.5px;font-weight:600}
                    .mkp-chip i{display:grid;place-items:center;width:20px;height:20px;border-radius:50%;font-style:normal;font-size:10px;color:#fff;flex:none}
                    .mkp-chip span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
                    .mkp-chip button{display:grid;place-items:center;width:20px;height:20px;border:0;border-radius:50%;background:transparent;color:#4b7a90;font-size:15px;line-height:1;cursor:pointer}
                    .mkp-chip button:hover{background:#cfe6f0;color:#a33b3b}
                    .mkp-search{flex:1 1 160px;min-width:120px;height:30px;border:0;outline:0;background:transparent;font-size:13px;color:#123b68;padding:0 4px}
                    .mkp-search::placeholder{color:#94a6b6}
                    .mkp-caret{display:grid;place-items:center;width:28px;height:28px;margin-left:auto;border:0;border-radius:8px;background:transparent;color:#7e93a6;cursor:pointer}
                    .mkp-caret svg{width:17px;height:17px;transition:transform .15s}
                    .mkp.is-open .mkp-caret svg{transform:rotate(180deg)}
                    .mkp-panel{position:absolute;z-index:40;left:0;right:0;top:calc(100% + 6px);border:1px solid #d6e3ec;border-radius:12px;background:#fff;box-shadow:0 14px 34px rgba(16,42,67,.16);overflow:hidden}
                    .mkp-head{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:8px 12px;border-bottom:1px solid #edf2f6;font-size:12px;color:#61798b;background:#f8fbfd}
                    .mkp-head-actions{display:flex;gap:4px}
                    .mkp-head button{height:26px;padding:0 9px;white-space:nowrap;border:0;border-radius:7px;background:transparent;color:#126f91;font-size:12px;font-weight:600;cursor:pointer}
                    .mkp-head button:hover{background:#e8f3f8}
                    .mkp-list{list-style:none;margin:0;padding:4px;max-height:300px;overflow:auto}
                    .mkp-opt{display:flex;align-items:center;gap:10px;padding:7px 9px;border-radius:9px;cursor:pointer;user-select:none}
                    .mkp-opt.is-active{background:#f0f6fa}
                    .mkp-opt[aria-selected="true"]{background:#e8f3f8}
                    .mkp-av{display:grid;place-items:center;width:30px;height:30px;border-radius:50%;color:#fff;font-size:11.5px;font-weight:700;flex:none}
                    .mkp-txt{min-width:0;flex:1}
                    .mkp-txt b{display:block;font-size:13px;color:#123b68;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
                    .mkp-txt small{display:block;font-size:11.5px;color:#8597a6;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
                    .mkp-tick{display:grid;place-items:center;width:20px;height:20px;border:1.5px solid #c3d3df;border-radius:6px;color:transparent;flex:none;font-size:13px;line-height:1}
                    .mkp-opt[aria-selected="true"] .mkp-tick{border-color:#126f91;background:#126f91;color:#fff}
                    .mkp-empty{padding:18px 12px;text-align:center;font-size:12.5px;color:#8597a6}
                    .mkp-foot{padding:6px 12px 8px;font-size:11.5px;color:#8597a6;border-top:1px solid #edf2f6}
                    .mkp-foot:empty{display:none}
                    .mkp-row{display:flex;flex-wrap:wrap;align-items:flex-end;gap:10px}
                    .mkp-when input{height:40px;border:1px solid #cfdce7;border-radius:10px;padding:0 10px;font-size:13px;color:#123b68;background:#fff}
                    .mkp-when input:focus{outline:0;border-color:#126f91;box-shadow:0 0 0 3px rgba(18,111,145,.14)}
                    .mkp-submit{display:inline-flex;align-items:center;gap:6px;height:40px}
                    .mkp-submit svg{width:15px;height:15px}
                    .mkp-submit[disabled]{opacity:.55;cursor:not-allowed}
                    .mkp-submit [data-mkp-badge]:not(:empty){display:inline-grid;place-items:center;min-width:20px;height:20px;padding:0 6px;border-radius:999px;background:rgba(255,255,255,.25);font-size:11px;font-weight:700}
                </style>
                <script>
                (() => {
                    const COLORS = ['#126f91', '#1b7a43', '#9a6200', '#7c3aed', '#be185d', '#0f766e', '#b45309', '#1d4ed8'];
                    const SHOW_MAX = 200;
                    const fold = (s) => String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd');
                    const initials = (name) => { const w = String(name || '?').trim().split(/\s+/); return ((w.length > 1 ? w[w.length - 2][0] : '') + w[w.length - 1][0]).toUpperCase(); };
                    const color = (id) => COLORS[Math.abs(Number(id)) % COLORS.length];
                    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

                    function setup(form) {
                        if (form.dataset.mkpReady) return; form.dataset.mkpReady = '1';
                        let data = { students: [], old: [] };
                        try { data = JSON.parse(form.querySelector('[data-mkp-data]').textContent) || data; } catch (e) {}
                        // Tìm theo tên (+ lớp); gõ có @ hoặc chữ số thì tìm cả email / số điện thoại (tránh "mai" khớp mọi "gmail").
                        const all = (data.students || []).map((s) => ({ ...s, key: fold(s.name + ' ' + (s.className || '')), acc: fold(s.account) }));
                        const byId = new Map(all.map((s) => [s.id, s]));
                        const picked = new Set((data.old || []).filter((id) => byId.has(id)));
                        const $ = (sel) => form.querySelector(sel);
                        const root = $('[data-mkp-root]'), field = $('[data-mkp-field]'), chips = $('[data-mkp-chips]'), search = $('[data-mkp-search]');
                        const panel = $('[data-mkp-panel]'), list = $('[data-mkp-list]'), count = $('[data-mkp-count]'), foot = $('[data-mkp-foot]');
                        const inputs = $('[data-mkp-inputs]'), submit = $('[data-mkp-submit]'), badge = $('[data-mkp-badge]');
                        let shown = [], active = 0;

                        const matches = () => {
                            const q = fold(search.value.trim()).replace(/\s+/g, ' ');
                            if (q === '') return all;
                            const acc = /[@\d]/.test(q);
                            return all.filter((s) => s.key.includes(q) || (acc && s.acc.includes(q)));
                        };
                        const meta = (s) => [s.className, s.account].filter(Boolean).join(' · ');

                        function renderList() {
                            const found = matches();
                            shown = found.slice(0, SHOW_MAX);
                            if (active >= shown.length) active = Math.max(0, shown.length - 1);
                            count.textContent = all.length === 0 ? 'Không có học sinh nào chưa nộp bài'
                                : (search.value.trim() ? found.length + ' kết quả' : all.length + ' học sinh chưa nộp bài') + (picked.size ? ' · đã chọn ' + picked.size : '');
                            list.innerHTML = shown.length === 0
                                ? '<li class="mkp-empty">' + (all.length === 0 ? 'Mọi học sinh đều đã nộp bài kỳ này.' : 'Không tìm thấy học sinh phù hợp.') + '</li>'
                                : shown.map((s, i) => '<li class="mkp-opt' + (i === active ? ' is-active' : '') + '" role="option" data-id="' + s.id + '" aria-selected="' + picked.has(s.id) + '">'
                                    + '<span class="mkp-av" style="background:' + color(s.id) + '">' + esc(initials(s.name)) + '</span>'
                                    + '<span class="mkp-txt"><b>' + esc(s.name) + '</b>' + (meta(s) ? '<small>' + esc(meta(s)) + '</small>' : '') + '</span>'
                                    + '<span class="mkp-tick">✓</span></li>').join('');
                            foot.textContent = found.length > SHOW_MAX ? 'Đang hiện ' + SHOW_MAX + ' / ' + found.length + ' học sinh — gõ tên để lọc nhanh.' : '';
                        }
                        function renderPicked() {
                            chips.innerHTML = [...picked].map((id) => { const s = byId.get(id); return s
                                ? '<span class="mkp-chip" data-id="' + id + '"><i style="background:' + color(id) + '">' + esc(initials(s.name)) + '</i><span>' + esc(s.name) + '</span><button type="button" aria-label="Bỏ ' + esc(s.name) + '">×</button></span>' : ''; }).join('');
                            inputs.innerHTML = [...picked].map((id) => '<input type="hidden" name="student_ids[]" value="' + id + '">').join('');
                            submit.disabled = picked.size === 0;
                            badge.textContent = picked.size ? String(picked.size) : '';
                            search.placeholder = picked.size ? 'Thêm học sinh…' : 'Chọn học sinh… (gõ tên để tìm)';
                        }
                        const isOpen = () => !panel.hidden;
                        function open() { if (isOpen()) return; panel.hidden = false; root.classList.add('is-open'); search.setAttribute('aria-expanded', 'true'); renderList(); }
                        function close() { panel.hidden = true; root.classList.remove('is-open'); search.setAttribute('aria-expanded', 'false'); }
                        function toggle(id) { picked.has(id) ? picked.delete(id) : picked.add(id); renderPicked(); renderList(); }
                        function moveActive(d) {
                            if (!shown.length) return; active = (active + d + shown.length) % shown.length; renderList();
                            const el = list.children[active]; if (el && el.scrollIntoView) el.scrollIntoView({ block: 'nearest' });
                        }

                        field.addEventListener('mousedown', (e) => { if (e.target.closest('.mkp-chip button')) return; if (e.target !== search) e.preventDefault(); search.focus(); isOpen() && e.target.closest('[data-mkp-caret]') ? close() : open(); });
                        chips.addEventListener('click', (e) => { const b = e.target.closest('.mkp-chip button'); if (!b) return; toggle(Number(b.parentElement.dataset.id)); search.focus(); });
                        search.addEventListener('focus', open);
                        search.addEventListener('input', () => { active = 0; open(); renderList(); });
                        search.addEventListener('keydown', (e) => {
                            if (e.key === 'ArrowDown') { e.preventDefault(); open(); moveActive(1); }
                            else if (e.key === 'ArrowUp') { e.preventDefault(); open(); moveActive(-1); }
                            else if (e.key === 'Enter') { e.preventDefault(); if (isOpen() && shown[active]) toggle(shown[active].id); }
                            else if (e.key === 'Escape') { close(); }
                            else if (e.key === 'Backspace' && search.value === '' && picked.size) { toggle([...picked].pop()); }
                        });
                        list.addEventListener('mousedown', (e) => e.preventDefault());
                        list.addEventListener('click', (e) => { const li = e.target.closest('.mkp-opt'); if (li) { active = [...list.children].indexOf(li); toggle(Number(li.dataset.id)); } });
                        $('[data-mkp-all]').addEventListener('mousedown', (e) => e.preventDefault());
                        $('[data-mkp-none]').addEventListener('mousedown', (e) => e.preventDefault());
                        $('[data-mkp-all]').addEventListener('click', () => { matches().forEach((s) => picked.add(s.id)); renderPicked(); renderList(); });
                        $('[data-mkp-none]').addEventListener('click', () => { picked.clear(); renderPicked(); renderList(); });
                        document.addEventListener('mousedown', (e) => { if (!root.contains(e.target)) close(); });
                        form.addEventListener('submit', (e) => { if (picked.size === 0) { e.preventDefault(); open(); search.focus(); } });
                        renderPicked();
                    }
                    const boot = () => document.querySelectorAll('[data-mkp]').forEach(setup);
                    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', boot) : boot();
                })();
                </script>
            @endonce
            <script>
                window.makeupPicker = window.makeupPicker || function (cfg) {
                    return {
                        q: '', hits: [], picked: [], loading: false, seq: 0,
                        async search() {
                            const q = this.q.trim(); if (q === '') { this.hits = []; return; }
                            const my = ++this.seq; this.loading = true;
                            try {
                                const r = await fetch(cfg.url + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                                const data = r.ok ? await r.json() : { students: [] };
                                if (my !== this.seq) return;
                                this.hits = (data.students || []).filter((s) => !this.picked.some((p) => p.id === s.id));
                            } catch (e) { if (my === this.seq) this.hits = []; }
                            if (my === this.seq) this.loading = false;
                        },
                        add(s) { if (!this.picked.some((p) => p.id === s.id)) this.picked.push(s); this.q = ''; this.hits = []; },
                        remove(id) { this.picked = this.picked.filter((p) => p.id !== id); },
                    };
                };
            </script>
        @endif
    @endif
</div>
