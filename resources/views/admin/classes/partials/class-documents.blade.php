{{--
    SỬA 3/11 (khách: "admin add tài liệu vào lớp giùm giáo viên chưa mua"; "gọi là Tài liệu, thiết kế cho đẹp") — khối "Tài liệu
    của lớp" ở trang Sửa lớp học. Dữ liệu: App\Services\Admin\ClassMaterialAdminService::panel().
      · Ô chọn tài liệu: tab loại (Tất cả / Sách / Chuyên đề / Bộ đề) + tìm kiếm (gõ không dấu được) + danh sách bấm chọn.
      · Danh sách tài liệu đang gắn: thẻ có biểu tượng theo loại, người thêm, nhãn "Giáo viên đã mua" / "Admin thêm hộ", nút Gỡ.
    JS thường (không Alpine), CSS riêng tiền tố cdx- (Tailwind không build lại trên máy chủ).
--}}
@php
    $cmx = $cmx ?? ['attached' => [], 'options' => [], 'attachUrl' => '#', 'hasTeacher' => false, 'typeLabels' => []];
    $cdxIcons = ['book' => 'book-open', 'topic' => 'layers', 'exam' => 'clipboard-list'];
    $cdxCounts = collect($cmx['options'])->countBy('type')->all();
@endphp
<style>
    .cdx { margin-top: 16px; border: 1px solid #E0ECF7; border-radius: 24px; background: #fff; box-shadow: 0 6px 22px rgba(25, 90, 150, .06); overflow: hidden; }
    .cdx-head { display: flex; flex-wrap: wrap; align-items: center; gap: 12px 16px; padding: 18px 22px; background: linear-gradient(120deg, #F3F8FF 0%, #F7FBFD 60%, #fff 100%); border-bottom: 1px solid #E8F0F7; }
    .cdx-head-ic { display: grid; place-items: center; width: 44px; height: 44px; border-radius: 14px; background: linear-gradient(135deg, #2563EB, #0EA5E9); color: #fff; box-shadow: 0 6px 16px rgba(37, 99, 235, .25); flex: none; }
    .cdx-head-ic svg { width: 22px; height: 22px; }
    .cdx-head h2 { margin: 0; font-size: 17px; font-weight: 800; color: #0F2A4A; }
    .cdx-head p { margin: 3px 0 0; font-size: 13px; line-height: 1.5; color: #5B7189; }
    .cdx-head p b { color: #1D4ED8; }
    .cdx-count { margin-left: auto; padding: 6px 12px; border-radius: 999px; background: #E8F1FF; color: #1D4ED8; font-size: 12.5px; font-weight: 800; white-space: nowrap; }
    .cdx-body { padding: 18px 22px 22px; display: grid; grid-template-columns: minmax(0, 1fr); gap: 18px; }
    .cdx-body > * { min-width: 0; }
    @media (max-width: 520px) { .cdx-head, .cdx-body { padding-left: 14px; padding-right: 14px; } }
    .cdx-warn { display: flex; gap: 8px; align-items: flex-start; padding: 10px 12px; border-radius: 12px; background: #FFF8EB; border: 1px solid #F8E1B4; font-size: 12.5px; line-height: 1.5; color: #8A5A00; }
    .cdx-warn svg { width: 16px; height: 16px; flex: none; margin-top: 1px; }
    .cdx-label { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 8px; font-size: 13px; font-weight: 800; color: #1E3A5F; }
    .cdx-label span { font-size: 12px; font-weight: 600; color: #7C8FA3; }
    .cdx-pick { border: 1px solid #DCE7F2; border-radius: 18px; background: #FAFCFE; overflow: hidden; }
    .cdx-tools { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 12px; border-bottom: 1px solid #E8F0F7; background: #fff; }
    .cdx-tabs { display: inline-flex; flex-wrap: wrap; max-width: 100%; gap: 4px; padding: 4px; border-radius: 12px; background: #EEF3F8; }
    .cdx-tab { display: inline-flex; align-items: center; gap: 6px; height: 32px; padding: 0 12px; border: 0; border-radius: 9px; background: transparent; font-size: 12.5px; font-weight: 700; color: #4B6580; cursor: pointer; white-space: nowrap; }
    .cdx-tab i { font-style: normal; min-width: 18px; padding: 0 5px; border-radius: 999px; background: #DCE6F0; font-size: 11px; line-height: 18px; text-align: center; color: #4B6580; }
    .cdx-tab.is-on { background: #fff; color: #1D4ED8; box-shadow: 0 1px 4px rgba(15, 42, 74, .12); }
    .cdx-tab.is-on i { background: #DBEAFE; color: #1D4ED8; }
    .cdx-search { position: relative; flex: 1 1 220px; min-width: 0; }
    .cdx-search svg { position: absolute; left: 11px; top: 50%; width: 16px; height: 16px; transform: translateY(-50%); color: #8CA0B4; }
    .cdx-search input { box-sizing: border-box; width: 100%; height: 40px; padding: 0 12px 0 36px; border: 1px solid #DCE7F2; border-radius: 12px; background: #F8FBFE; font-size: 13px; color: #0F2A4A; }
    .cdx-search input:focus { outline: 0; border-color: #3B82F6; background: #fff; box-shadow: 0 0 0 3px rgba(59, 130, 246, .15); }
    .cdx-list { list-style: none; margin: 0; padding: 6px; max-height: 280px; overflow: auto; }
    .cdx-opt { display: flex; align-items: center; gap: 12px; padding: 9px 10px; border-radius: 12px; cursor: pointer; border: 1.5px solid transparent; }
    .cdx-opt:hover { background: #F1F6FC; }
    .cdx-opt.is-on { background: #EEF5FF; border-color: #93C5FD; }
    .cdx-ic { display: grid; place-items: center; width: 36px; height: 36px; border-radius: 11px; flex: none; }
    .cdx-ic svg { width: 18px; height: 18px; }
    .cdx-ic.t-book { background: #E0EDFF; color: #1D4ED8; }
    .cdx-ic.t-topic { background: #EDE7FE; color: #6D28D9; }
    .cdx-ic.t-exam { background: #FFF1D6; color: #B45309; }
    .cdx-txt { flex: 1; min-width: 0; }
    .cdx-txt b { display: block; font-size: 13.5px; color: #0F2A4A; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .cdx-txt small { display: block; margin-top: 1px; font-size: 12px; color: #7C8FA3; }
    .cdx-radio { display: grid; place-items: center; width: 20px; height: 20px; border-radius: 50%; border: 1.5px solid #C3D3E2; color: transparent; flex: none; }
    .cdx-radio svg { width: 12px; height: 12px; }
    .cdx-opt.is-on .cdx-radio { border-color: #2563EB; background: #2563EB; color: #fff; }
    .cdx-empty { padding: 26px 12px; text-align: center; font-size: 13px; color: #8CA0B4; }
    .cdx-foot { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 12px; border-top: 1px solid #E8F0F7; background: #fff; }
    .cdx-chosen { flex: 1 1 220px; min-width: 0; font-size: 13px; color: #5B7189; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .cdx-chosen b { color: #0F2A4A; }
    .cdx-add { display: inline-flex; align-items: center; gap: 7px; height: 40px; padding: 0 18px; border: 0; border-radius: 12px; background: linear-gradient(135deg, #2563EB, #1D4ED8); color: #fff; font-size: 13.5px; font-weight: 800; cursor: pointer; box-shadow: 0 6px 16px rgba(37, 99, 235, .25); }
    .cdx-add svg { width: 16px; height: 16px; }
    .cdx-add:disabled { opacity: .5; cursor: not-allowed; box-shadow: none; }
    .cdx-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 10px; }
    @media (min-width: 760px) { .cdx-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .cdx-card { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid #E3ECF5; border-radius: 16px; background: #fff; transition: box-shadow .15s, border-color .15s; }
    .cdx-card:hover { border-color: #C9DCEE; box-shadow: 0 6px 16px rgba(15, 42, 74, .06); }
    .cdx-card .cdx-ic { width: 42px; height: 42px; border-radius: 13px; }
    .cdx-badge { display: inline-flex; align-items: center; gap: 4px; margin-top: 5px; padding: 2px 9px; border-radius: 999px; font-size: 11px; font-weight: 800; }
    .cdx-badge.is-own { background: #E3F6EC; color: #047857; }
    .cdx-badge.is-admin { background: #FFF1D6; color: #9A5B00; }
    .cdx-del { display: grid; place-items: center; width: 36px; height: 36px; border: 1px solid #F3D4D4; border-radius: 11px; background: #fff; color: #C2410C; cursor: pointer; flex: none; }
    .cdx-del:hover { background: #FFF1F1; color: #B91C1C; }
    .cdx-del svg { width: 16px; height: 16px; }
    .cdx-none { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 22px 12px; border: 1.5px dashed #D6E3EF; border-radius: 16px; background: #FAFCFE; text-align: center; font-size: 13px; color: #7C8FA3; }
    .cdx-none svg { width: 26px; height: 26px; color: #A9BCCF; }
    .cdx-none b { color: #3E5873; }
</style>

<section id="hoc-lieu-lop" class="cdx" data-cdx>
    <header class="cdx-head">
        <span class="cdx-head-ic"><x-lucide name="library" /></span>
        <div style="min-width:0;flex:1 1 320px">
            <h2>Tài liệu của lớp</h2>
            <p>Admin thêm hộ sách, chuyên đề, bộ đề cho lớp — <b>giáo viên không cần mua trước</b>. Thêm xong, giáo viên dạy lớp và học sinh trong lớp đọc, làm bài được ngay.</p>
        </div>
        <span class="cdx-count">{{ count($cmx['attached']) }} tài liệu</span>
    </header>

    <div class="cdx-body">
        @unless ($cmx['hasTeacher'])
            <div class="cdx-warn"><x-lucide name="info" /><span>Lớp chưa có giáo viên phụ trách — học sinh vẫn dùng được tài liệu; giáo viên được phân công sau cũng dùng được.</span></div>
        @endunless

        {{-- Thêm tài liệu --}}
        <form method="POST" action="{{ $cmx['attachUrl'] }}" data-cdx-form>
            @csrf
            <input type="hidden" name="product_id" value="" data-cdx-value>
            <script type="application/json" data-cdx-data>{!! json_encode($cmx['options'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
            <div class="cdx-label">Thêm tài liệu vào lớp <span>{{ count($cmx['options']) }} tài liệu có thể thêm</span></div>
            <div class="cdx-pick">
                <div class="cdx-tools">
                    <div class="cdx-tabs" role="tablist" aria-label="Lọc theo loại tài liệu">
                        <button type="button" class="cdx-tab is-on" data-cdx-tab="">Tất cả <i>{{ count($cmx['options']) }}</i></button>
                        @foreach ($cmx['typeLabels'] as $key => $label)
                            <button type="button" class="cdx-tab" data-cdx-tab="{{ $key }}">{{ $label }} <i>{{ $cdxCounts[$key] ?? 0 }}</i></button>
                        @endforeach
                    </div>
                    <label class="cdx-search">
                        <x-lucide name="search" />
                        <input type="search" placeholder="Tìm tên tài liệu…" aria-label="Tìm tên tài liệu" data-cdx-search>
                    </label>
                </div>
                <ul class="cdx-list" role="listbox" aria-label="Danh sách tài liệu" data-cdx-list></ul>
                <div class="cdx-foot">
                    <span class="cdx-chosen" data-cdx-chosen>Chưa chọn tài liệu nào</span>
                    <button type="submit" class="cdx-add" data-cdx-add disabled><x-lucide name="plus" />Thêm vào lớp</button>
                </div>
            </div>
        </form>

        {{-- Tài liệu đang gắn với lớp --}}
        <div>
            <div class="cdx-label">Đang dùng trong lớp <span>{{ count($cmx['attached']) }} tài liệu</span></div>
            @if ($cmx['attached'] === [])
                <div class="cdx-none"><x-lucide name="library" /><b>Lớp chưa có tài liệu nào</b><span>Chọn tài liệu ở khung trên rồi bấm "Thêm vào lớp".</span></div>
            @else
                <div class="cdx-grid">
                    @foreach ($cmx['attached'] as $row)
                        <div class="cdx-card">
                            <span class="cdx-ic t-{{ $row['typeKey'] ?? 'book' }}"><x-lucide :name="$cdxIcons[$row['typeKey'] ?? 'book'] ?? 'book-open'" /></span>
                            <div class="cdx-txt">
                                <b title="{{ $row['title'] }}">{{ $row['title'] }}</b>
                                <small>{{ $row['type'] }}{{ $row['addedBy'] ? ' · thêm bởi '.$row['addedBy'] : '' }}{{ $row['addedAt'] ? ' · '.$row['addedAt'] : '' }}</small>
                                <span class="cdx-badge {{ $row['teacherOwns'] ? 'is-own' : 'is-admin' }}">{{ $row['teacherOwns'] ? 'Giáo viên đã mua' : 'Admin thêm hộ' }}</span>
                            </div>
                            <form method="POST" action="{{ $row['removeUrl'] }}" onsubmit="return confirm('Gỡ tài liệu “{{ addslashes($row['title']) }}” khỏi lớp? Giáo viên và học sinh của lớp sẽ không đọc được nữa (nếu không tự mua).');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="cdx-del" title="Gỡ khỏi lớp" aria-label="Gỡ {{ $row['title'] }} khỏi lớp"><x-lucide name="trash-2" /></button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>

<script>
(() => {
    const root = document.querySelector('[data-cdx]');
    if (!root || root.dataset.ready) return; root.dataset.ready = '1';
    const ICONS = {
        book: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>',
        topic: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 2 10 5-10 5L2 7z"/><path d="m2 17 10 5 10-5"/><path d="m2 12 10 5 10-5"/></svg>',
        exam: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4M12 16h4M8 11h.01M8 16h.01"/></svg>',
    };
    const CHECK = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
    const fold = (s) => String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd');
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    let items = [];
    try { items = JSON.parse(root.querySelector('[data-cdx-data]').textContent) || []; } catch (e) {}
    items = items.map((it) => ({ ...it, key: fold(it.title) }));
    const list = root.querySelector('[data-cdx-list]'), search = root.querySelector('[data-cdx-search]');
    const value = root.querySelector('[data-cdx-value]'), chosen = root.querySelector('[data-cdx-chosen]'), add = root.querySelector('[data-cdx-add]');
    const tabs = [...root.querySelectorAll('[data-cdx-tab]')];
    let type = '', picked = null;

    function render() {
        const q = fold(search.value.trim());
        const shown = items.filter((it) => (!type || it.type === type) && (!q || it.key.includes(q)));
        list.innerHTML = shown.length === 0
            ? '<li class="cdx-empty">' + (items.length === 0 ? 'Đã thêm hết tài liệu đang phát hành vào lớp.' : 'Không tìm thấy tài liệu phù hợp.') + '</li>'
            : shown.map((it) => '<li class="cdx-opt' + (picked && picked.id === it.id ? ' is-on' : '') + '" role="option" aria-selected="' + !!(picked && picked.id === it.id) + '" data-id="' + it.id + '">'
                + '<span class="cdx-ic t-' + esc(it.type) + '">' + (ICONS[it.type] || ICONS.book) + '</span>'
                + '<span class="cdx-txt"><b>' + esc(it.title) + '</b><small>' + esc(it.typeLabel) + '</small></span>'
                + '<span class="cdx-radio">' + CHECK + '</span></li>').join('');
    }
    function choose(id) {
        picked = items.find((it) => it.id === id) || null;
        value.value = picked ? String(picked.id) : '';
        add.disabled = !picked;
        chosen.innerHTML = picked ? 'Đã chọn: <b>' + esc(picked.title) + '</b> · ' + esc(picked.typeLabel) : 'Chưa chọn tài liệu nào';
        render();
    }
    tabs.forEach((t) => t.addEventListener('click', () => { type = t.dataset.cdxTab; tabs.forEach((x) => x.classList.toggle('is-on', x === t)); render(); }));
    search.addEventListener('input', render);
    search.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.preventDefault(); });
    list.addEventListener('click', (e) => { const li = e.target.closest('.cdx-opt'); if (li) choose(Number(li.dataset.id)); });
    root.querySelector('[data-cdx-form]').addEventListener('submit', (e) => { if (!value.value) e.preventDefault(); else add.disabled = true; });
    render();
})();
</script>
