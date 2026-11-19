{{-- SỬA 10/10 (khách: "tag chuyên đề nhiều hay dài quá thì UI xấu") — ô chọn Tag/Chuyên đề dùng CHUNG cho
     form tạo/sửa câu hỏi của admin, giáo viên và bài tập sản phẩm. Khung cuộn có chiều cao tối đa (nhiều tag
     không kéo dài cả form), chip dài tự cắt "…" (rê chuột đọc đủ), có ô tìm + đếm số đã chọn + bỏ chọn tất cả.
     Tham số: $tags (collection Tag), $selected (mảng id đã chọn, ép chuỗi khi so sánh). Input vẫn là
     tag_ids[] nên phía máy chủ KHÔNG đổi. CSS/JS nhúng thẳng vì máy chủ không có Vite. --}}
@php
    $tpId = 'tp'.substr(md5(uniqid('', true)), 0, 8);
    $tpSelected = collect($selected ?? [])->map(fn ($v) => (string) $v);
    // SỬA 19/11 (khách: "khi tạo câu hỏi và cập nhật cho gắn tag chuyên đề chính luôn") — $withPrimary = true thì có thêm
    // hàng chọn TAG CHÍNH (gửi primary_tag_id) trong số tag đang tick; $primary = id tag chính hiện tại.
    $tpWithPrimary = (bool) ($withPrimary ?? false);
    $tpPrimary = (string) ($primary ?? '');
@endphp
@once
    <style>
        .tp-box { border: 1px solid #DCEBF5; border-radius: 12px; background: #fff; overflow: hidden; }
        .tp-bar { display: flex; align-items: center; gap: 8px; padding: 6px 8px; border-bottom: 1px solid #E7F0F7; background: #F8FBFD; }
        .tp-search { flex: 1 1 auto; min-width: 0; height: 30px; padding: 0 10px; border: 1px solid #DCEBF5; border-radius: 8px; background: #fff; font-size: 12px; color: #334155; }
        .tp-search:focus { outline: 2px solid #BFE3F0; outline-offset: 0; }
        .tp-count { flex: none; font-size: 11px; font-weight: 600; color: #2563EB; white-space: nowrap; }
        .tp-clear { flex: none; border: 0; background: none; padding: 0; font: inherit; font-size: 11px; font-weight: 600; color: #64748B; cursor: pointer; text-decoration: underline; }
        .tp-clear:hover { color: #DC2626; }
        .tp-list { display: flex; flex-wrap: wrap; gap: 6px; max-height: 176px; overflow-y: auto; padding: 8px; overscroll-behavior: contain; }
        .tp-chip { display: inline-flex; align-items: center; gap: 6px; max-width: 100%; box-sizing: border-box; padding: 4px 10px; border: 1px solid #DCEBF5; border-radius: 999px; background: #fff; font-size: 12px; line-height: 1.3; color: #475569; cursor: pointer; transition: background .12s, border-color .12s, color .12s; }
        .tp-chip:hover { border-color: #93C5FD; }
        .tp-chip input { flex: none; margin: 0; }
        .tp-chip span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .tp-chip:has(input:checked) { background: #EFF6FF; border-color: #93C5FD; color: #2563EB; font-weight: 600; }
        .tp-chip[hidden] { display: none; }
        .tp-empty { padding: 4px 2px; font-size: 12px; color: #94A3B8; }
        /* SỬA 19/11 — hàng chọn tag chính. */
        .tp-primary { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; padding: 8px 10px; border-top: 1px solid #E7F0F7; background: #FFFBEB; }
        .tp-primary[hidden] { display: none; }
        .tp-primary-lbl { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 700; color: #92400E; white-space: nowrap; }
        .tp-primary-lbl svg { width: 14px; height: 14px; color: #F59E0B; }
        .tp-primary-opts { display: flex; flex-wrap: wrap; gap: 6px; }
        .tp-primary-opt { padding: 3px 10px; border: 1px solid #FDE68A; border-radius: 999px; background: #fff; font-size: 12px; font-weight: 600; color: #92400E; cursor: pointer; }
        .tp-primary-opt:hover { border-color: #F59E0B; }
        .tp-primary-opt.is-on { border-color: #F59E0B; background: #F59E0B; color: #fff; }
        .tp-chip.is-primary { border-color: #F59E0B !important; box-shadow: 0 0 0 1px #F59E0B; }
        .tp-chip.is-primary span::after { content: ' ★'; color: #F59E0B; }
    </style>
    <script>
        document.addEventListener('input', function (e) {
            var s = e.target.closest ? e.target.closest('[data-tp-search]') : null;
            if (!s) return;
            var box = s.closest('[data-tp]'), q = s.value.trim().toLowerCase(), shown = 0;
            box.querySelectorAll('.tp-chip').forEach(function (c) {
                var ok = q === '' || c.textContent.toLowerCase().indexOf(q) !== -1;
                c.hidden = !ok; if (ok) shown++;
            });
            box.querySelector('[data-tp-empty]').hidden = shown !== 0;
        });
        function tpCount(box) {
            var n = box.querySelectorAll('.tp-chip input:checked').length;
            box.querySelector('[data-tp-count]').textContent = n ? 'Đã chọn ' + n : '';
            box.querySelector('[data-tp-clear]').hidden = n === 0;
            tpPrimary(box);
        }
        // SỬA 19/11 — hàng "Tag chính": liệt kê các tag đang tick; bấm 1 tag để đặt làm tag chính. Tag chính bị bỏ tick
        // thì tự chuyển sang tag tick đầu tiên. Giá trị gửi lên ở input ẩn primary_tag_id.
        function tpPrimary(box) {
            var row = box.querySelector('[data-tp-primary]');
            if (!row) return;
            var input = row.querySelector('input[name="primary_tag_id"]'), wrap = row.querySelector('[data-tp-primary-opts]');
            var checked = Array.prototype.map.call(box.querySelectorAll('.tp-chip input:checked'), function (i) {
                return { id: i.value, name: i.closest('.tp-chip').getAttribute('title') || i.value };
            });
            if (!checked.some(function (c) { return c.id === input.value; })) input.value = checked.length ? checked[0].id : '';
            wrap.innerHTML = '';
            checked.forEach(function (c) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'tp-primary-opt' + (c.id === input.value ? ' is-on' : '');
                b.textContent = c.name;
                b.title = 'Đặt “' + c.name + '” làm tag chính';
                b.addEventListener('click', function () { input.value = c.id; tpPrimary(box); });
                wrap.appendChild(b);
            });
            row.hidden = checked.length === 0;
            box.querySelectorAll('.tp-chip').forEach(function (ch) {
                var i = ch.querySelector('input');
                ch.classList.toggle('is-primary', !!(i && i.checked && i.value === input.value));
            });
        }
        document.addEventListener('change', function (e) {
            var box = e.target.closest ? e.target.closest('[data-tp]') : null;
            if (box) tpCount(box);
        });
        document.addEventListener('click', function (e) {
            var b = e.target.closest ? e.target.closest('[data-tp-clear]') : null;
            if (!b) return;
            var box = b.closest('[data-tp]');
            box.querySelectorAll('.tp-chip input:checked').forEach(function (i) { i.checked = false; });
            tpCount(box);
        });
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-tp]').forEach(tpCount);
        });
    </script>
@endonce
@if ($tags->isNotEmpty())
    <div class="tp-box" data-tp id="{{ $tpId }}">
        <div class="tp-bar">
            @if ($tags->count() > 5)
                <input type="search" class="tp-search" data-tp-search placeholder="Tìm chuyên đề…" aria-label="Tìm chuyên đề" autocomplete="off">
            @endif
            <span class="tp-count" data-tp-count aria-live="polite" style="margin-left:auto"></span>
            <button type="button" class="tp-clear" data-tp-clear hidden>Bỏ chọn</button>
        </div>
        <div class="tp-list">
            @foreach ($tags as $tagOption)
                <label class="tp-chip" title="{{ $tagOption->name }}">
                    <input type="checkbox" name="tag_ids[]" value="{{ $tagOption->id }}" @checked($tpSelected->contains((string) $tagOption->id))>
                    <span>{{ $tagOption->name }}</span>
                </label>
            @endforeach
            <div class="tp-empty" data-tp-empty hidden>Không có chuyên đề khớp.</div>
        </div>
        @if ($tpWithPrimary)
            <div class="tp-primary" data-tp-primary hidden>
                <input type="hidden" name="primary_tag_id" value="{{ $tpSelected->contains($tpPrimary) ? $tpPrimary : $tpSelected->first() }}">
                <span class="tp-primary-lbl"><svg viewBox="0 0 24 24" fill="currentColor"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg>Tag chính:</span>
                <span class="tp-primary-opts" data-tp-primary-opts></span>
            </div>
        @endif
    </div>
@endif
