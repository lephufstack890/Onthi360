{{-- SỬA 11/11 (khách: "có search nữa nhé trong bài chung admin và giáo viên") — ô lọc Chuyên đề ở hàng bộ lọc
     Kho bài tập (admin) và Kho câu hỏi (giáo viên) nay GÕ TÌM ĐƯỢC: danh sách chuyên đề dài, cuộn cả danh sách
     để kiếm 1 tag rất cực (đúng vấn đề đã xử ở partials/tag-picker hôm 10/10).

     Cách làm: VẪN in ra <select name="tag"> thật (x-ws.select) — máy chủ KHÔNG đổi gì, không có JS thì ô chọn
     chạy y như cũ. JS chỉ "khoác" thêm lớp giao diện: giấu select, dựng nút + bảng chọn có ô tìm, chọn xong thì
     ghi ngược giá trị vào select thật. Bảng chọn gắn vào <body> và position:fixed vì .acx-panel có overflow:hidden
     (nếu để trong panel sẽ bị cắt mất). Tìm BỎ DẤU tiếng Việt: gõ "nhap xuat" vẫn ra "Nhập xuất".
     CSS/JS nhúng thẳng vì máy chủ không chạy Vite.

     Tham số: $tfsId, $tfsName (mặc định tag), $tfsValue (giá trị đang chọn), $tfsOptions (mảng id => tên). --}}
@php
    $tfsId = $tfsId ?? 'filter-tag';
    $tfsName = $tfsName ?? 'tag';
    $tfsValue = (string) ($tfsValue ?? '');
    $tfsOptions = $tfsOptions ?? [];
@endphp

@once
    <style>
        .tfs { position: relative; }
        .tfs-native-hidden { display: none; }
        .tfs-btn { display: block; width: 100%; text-align: left; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tfs-pop { position: fixed; z-index: 70; display: none; flex-direction: column; gap: 6px; box-sizing: border-box;
                   padding: 8px; border: 1px solid #DCEBF5; border-radius: 14px; background: #fff;
                   box-shadow: 0 12px 28px rgba(25, 90, 150, .16); }
        .tfs-pop[data-open="1"] { display: flex; }
        .tfs-q { width: 100%; box-sizing: border-box; height: 32px; padding: 0 10px; border: 1px solid #DCEBF5;
                 border-radius: 9px; background: #fff; font-size: 12px; color: #334155; }
        .tfs-q:focus { outline: 2px solid #BFE3F0; outline-offset: 0; }
        .tfs-list { list-style: none; margin: 0; padding: 0; max-height: 220px; overflow-y: auto; overscroll-behavior: contain; }
        .tfs-opt { padding: 7px 10px; border-radius: 9px; font-size: 12px; line-height: 1.35; color: #475569; cursor: pointer;
                   white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tfs-opt:hover, .tfs-opt.is-active { background: #F1F7FD; }
        .tfs-opt[aria-selected="true"] { background: #EFF6FF; color: #2563EB; font-weight: 600; }
        .tfs-opt[hidden] { display: none; }
        .tfs-empty { margin: 0; padding: 6px 10px 2px; font-size: 12px; color: #94A3B8; }
    </style>
    <script>
        (function () {
            var pop, qIn, list, empty, owner = null;

            // Bỏ dấu + đ→d để gõ không dấu vẫn tìm ra.
            function norm(s) {
                return (s == null ? '' : String(s)).toLowerCase()
                    .normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd');
            }

            function buildPop() {
                if (pop) return;
                pop = document.createElement('div');
                pop.className = 'tfs-pop';
                pop.innerHTML = '<input class="tfs-q" type="search" autocomplete="off" spellcheck="false">'
                    + '<ul class="tfs-list" role="listbox"></ul>'
                    + '<p class="tfs-empty" hidden>Không có chuyên đề nào khớp</p>';
                document.body.appendChild(pop);
                qIn = pop.querySelector('.tfs-q');
                list = pop.querySelector('.tfs-list');
                empty = pop.querySelector('.tfs-empty');

                qIn.addEventListener('input', filter);
                qIn.addEventListener('keydown', function (e) {
                    if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
                    else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
                    else if (e.key === 'Enter') { e.preventDefault(); var a = list.querySelector('.tfs-opt.is-active') || visible()[0]; if (a) pick(a); }
                    else if (e.key === 'Escape') { e.preventDefault(); close(true); }
                });
                list.addEventListener('click', function (e) {
                    var o = e.target.closest('.tfs-opt');
                    if (o) pick(o);
                });
            }

            function visible() {
                return Array.prototype.filter.call(list.children, function (o) { return !o.hidden; });
            }

            function filter() {
                var q = norm(qIn.value.trim()), shown = 0;
                Array.prototype.forEach.call(list.children, function (o) {
                    var ok = q === '' || norm(o.textContent).indexOf(q) !== -1;
                    o.hidden = !ok;
                    o.classList.remove('is-active');
                    if (ok) shown++;
                });
                empty.hidden = shown !== 0;
                var v = visible();
                if (q !== '' && v.length) v[0].classList.add('is-active');
            }

            function move(step) {
                var v = visible();
                if (!v.length) return;
                var i = v.indexOf(list.querySelector('.tfs-opt.is-active'));
                i = i === -1 ? (step > 0 ? 0 : v.length - 1) : Math.min(Math.max(i + step, 0), v.length - 1);
                v.forEach(function (o) { o.classList.remove('is-active'); });
                v[i].classList.add('is-active');
                v[i].scrollIntoView({ block: 'nearest' });
            }

            function place() {
                if (!owner) return;
                var r = owner.btn.getBoundingClientRect();
                var w = Math.max(r.width, 240);
                pop.style.width = w + 'px';
                pop.style.left = Math.max(8, Math.min(r.left, window.innerWidth - w - 8)) + 'px';
                var below = window.innerHeight - r.bottom;
                if (below < 280 && r.top > below) {
                    pop.style.top = 'auto';
                    pop.style.bottom = (window.innerHeight - r.top + 6) + 'px';
                } else {
                    pop.style.bottom = 'auto';
                    pop.style.top = (r.bottom + 6) + 'px';
                }
            }

            function open(box) {
                buildPop();
                owner = box;
                list.innerHTML = '';
                Array.prototype.forEach.call(box.sel.options, function (op) {
                    var li = document.createElement('li');
                    li.className = 'tfs-opt';
                    li.setAttribute('role', 'option');
                    li.dataset.value = op.value;
                    li.textContent = op.text;
                    li.title = op.text;
                    li.setAttribute('aria-selected', op.value === box.sel.value ? 'true' : 'false');
                    list.appendChild(li);
                });
                qIn.value = '';
                qIn.placeholder = box.box.dataset.tfsHint || 'Gõ để tìm…';
                filter();
                pop.dataset.open = '1';
                box.btn.setAttribute('aria-expanded', 'true');
                place();
                qIn.focus();
                var cur = list.querySelector('[aria-selected="true"]');
                if (cur) cur.scrollIntoView({ block: 'nearest' });
            }

            function close(focusBtn) {
                if (!owner) return;
                var b = owner.btn;
                pop.dataset.open = '0';
                b.setAttribute('aria-expanded', 'false');
                owner = null;
                if (focusBtn) b.focus();
            }

            function pick(opt) {
                if (!owner) return;
                var s = owner.sel;
                s.value = opt.dataset.value;
                owner.label.textContent = opt.textContent;
                s.dispatchEvent(new Event('change', { bubbles: true }));
                close(true);
            }

            function enhance(box) {
                if (box.dataset.tfsReady) return;
                var sel = box.querySelector('select[data-tfs-native]');
                if (!sel) return;
                box.dataset.tfsReady = '1';

                var wrap = sel.parentElement;      // <div class="relative"> của x-ws.select
                wrap.classList.add('tfs-native-hidden');

                var btn = document.createElement('button');
                btn.type = 'button';
                btn.id = sel.id ? sel.id + '-tfs' : '';
                btn.className = sel.className + ' tfs-btn';
                btn.setAttribute('aria-haspopup', 'listbox');
                btn.setAttribute('aria-expanded', 'false');
                var label = document.createElement('span');
                label.textContent = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : '';
                btn.appendChild(label);
                box.appendChild(btn);

                var chev = document.createElement('span');
                chev.className = 'pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400';
                chev.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
                    + ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="m6 9 6 6 6-6"/></svg>';
                box.appendChild(chev);

                if (sel.id && btn.id) {
                    var lb = document.querySelector('label[for="' + sel.id + '"]');
                    if (lb) lb.setAttribute('for', btn.id);
                }

                var ref = { box: box, sel: sel, btn: btn, label: label };
                btn.addEventListener('click', function () {
                    if (owner && owner.btn === btn) close(true); else open(ref);
                });
                btn.addEventListener('keydown', function (e) {
                    if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(ref); }
                });
            }

            function init() { document.querySelectorAll('[data-tfs]').forEach(enhance); }

            document.addEventListener('click', function (e) {
                if (!owner || !e.target || !e.target.closest) return;
                if (e.target.closest('.tfs-pop') || e.target.closest('[data-tfs]')) return;
                close(false);
            });
            window.addEventListener('resize', function () { if (owner) place(); });
            window.addEventListener('scroll', function () { if (owner) place(); }, true);

            if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
            else init();
        })();
    </script>
@endonce

<div class="tfs" data-tfs data-tfs-hint="Gõ để tìm chuyên đề…">
    <x-ws.select :id="$tfsId" :name="$tfsName" data-tfs-native>
        <option value="">Tất cả chuyên đề</option>
        @foreach ($tfsOptions as $tagId => $tagName)
            <option value="{{ $tagId }}" @selected($tfsValue === (string) $tagId)>{{ $tagName }}</option>
        @endforeach
        <option value="none" @selected($tfsValue === 'none')>Chưa gắn chuyên đề</option>
    </x-ws.select>
</div>
