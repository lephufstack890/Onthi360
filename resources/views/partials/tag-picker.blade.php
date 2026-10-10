{{-- SỬA 10/10 (khách: "tag chuyên đề nhiều hay dài quá thì UI xấu") — ô chọn Tag/Chuyên đề dùng CHUNG cho
     form tạo/sửa câu hỏi của admin, giáo viên và bài tập sản phẩm. Khung cuộn có chiều cao tối đa (nhiều tag
     không kéo dài cả form), chip dài tự cắt "…" (rê chuột đọc đủ), có ô tìm + đếm số đã chọn + bỏ chọn tất cả.
     Tham số: $tags (collection Tag), $selected (mảng id đã chọn, ép chuỗi khi so sánh). Input vẫn là
     tag_ids[] nên phía máy chủ KHÔNG đổi. CSS/JS nhúng thẳng vì máy chủ không có Vite. --}}
@php
    $tpId = 'tp'.substr(md5(uniqid('', true)), 0, 8);
    $tpSelected = collect($selected ?? [])->map(fn ($v) => (string) $v);
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
            @if ($tags->count() > 8)
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
    </div>
@endif
