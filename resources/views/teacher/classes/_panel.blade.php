{{-- SỬA 6/11 (khách: "chuyển tab không load lại trang") — NỘI DUNG 1 tab của không gian lớp, tách khỏi show.blade.php để
     ClassRoomController::show() trả RIÊNG phần này khi chuyển tab bằng JS (header X-Csp-Tab) — không dựng lại layout / sidebar.
     Logic / giao diện từng tab giữ nguyên như cũ. --}}
    @php
        $tab = $tab ?? 'overview';
        $cspPartial = request()->header('X-Csp-Tab') === '1';
        $materials = $materials ?? [];
        $members = $members ?? collect();
        $studentsCount = $studentsCount ?? 0;
        $courseTitle = $classRoom->course->title ?? '';
        $className = $classRoom->name ?? '';
        $nextSessionLabel = isset($nextSession) && $nextSession ? 'Buổi tới: '.$nextSession->starts_at->format('d/m H:i') : 'Chưa có buổi học sắp tới';
        $ratingAverage = $ratingSummary->avg_rating ?? 0;
        $ratingCount = $ratingSummary->review_count ?? 0;
        $tcxCover = method_exists($classRoom->course ?? null, 'coverUrl') ? $classRoom->course->coverUrl() : null;
        $tcxCompletion = (int) ($completion ?? 0);
        $tcxTabIcons = ['Tổng quan' => 'home', 'Tài liệu' => 'file-text', 'Học liệu' => 'file-text', 'Buổi học' => 'play-circle', 'Giao đề' => 'clipboard-list', 'Kết quả' => 'bar-chart-3', 'Thành viên' => 'users', 'Học sinh' => 'users', 'Thông báo' => 'megaphone'];
        // Loại tài liệu suy từ nhãn có sẵn ("📘 Sách" / "🗂️ Chuyên đề" / "📝 Bộ đề") — không đổi dữ liệu của service.
        $tcxType = function (?string $label): string {
            $l = mb_strtolower((string) $label);
            return str_contains($l, 'chuyên đề') ? 'topic' : (str_contains($l, 'bộ đề') ? 'exam' : 'book');
        };
        $tcxTypeIcon = ['book' => 'book-open', 'topic' => 'layers', 'exam' => 'clipboard-list'];
        $tcxTypeName = ['book' => 'Sách', 'topic' => 'Chuyên đề', 'exam' => 'Bộ đề'];
        $tcxClean = fn (?string $label) => trim(preg_replace('/^[^\p{L}]+/u', '', (string) $label));
    @endphp
{{-- SỬA 7/11 — chuyển tab / bấm nút không tải lại trang: phần này được trả riêng, nên tự hiện thông báo "đã lưu" / lỗi (bình
     thường nằm ở đầu trang _header). Tab Tài liệu, Thông báo đã tự hiện lỗi của mình bên dưới. --}}
@if ($cspPartial)
    @if (session('space_ok'))
        @include('partials.toast-flash', ['type' => 'success', 'message' => session('space_ok')])
    @endif
    @if (! in_array($tab, ['materials', 'notices'], true) && $errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif
@endif

    @if ($tab === 'materials')
        {{-- Tab Tài liệu — logic như cũ (gắn NGUYÊN 1 sản phẩm, chỉ hiện sách/chuyên đề/bộ đề còn quyền dạy, Gỡ = "Đã gỡ").
             Tìm kiếm + lọc loại chạy ngay trên trang (JS ở cuối), không gọi máy chủ. --}}
        @php
            $attachableProducts = $attachableProducts ?? [];
            $hasAnyAttachable = collect($attachableProducts)->sum(fn ($g) => count($g['products'])) > 0;
            $defaultType = null;
            foreach ($attachableProducts as $typeKey => $group) {
                if (count($group['products']) > 0) { $defaultType = $typeKey; break; }
            }
            $defaultType = $defaultType ?? array_key_first($attachableProducts);
        @endphp

        @if (session('status') === 'material-attached')
            @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã thêm tài liệu vào lớp — học sinh trong lớp xem được ngay, không cần tự mua riêng.'])
        @elseif (session('status') === 'material-detached')
            @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã gỡ tài liệu — lịch sử bài làm cũ vẫn giữ nguyên.'])
        @endif
        @if ($errors->any())
            @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
        @endif

        <div data-tcx-docs>
            @if (count($materials) > 0)
                <div class="tcx-toolbar">
                    <label class="tcx-search"><x-lucide name="search" /><input type="search" placeholder="Tìm kiếm tài liệu…" aria-label="Tìm kiếm tài liệu" data-tcx-q></label>
                    <label class="tcx-filter"><x-lucide name="filter" />
                        <select aria-label="Lọc theo loại" data-tcx-type>
                            <option value="">Bộ lọc: Tất cả</option>
                            <option value="book">Sách</option>
                            <option value="topic">Chuyên đề</option>
                            <option value="exam">Bộ đề</option>
                        </select>
                    </label>
                </div>
            @endif

            <div class="tcx-docs">
                @forelse ($materials as $m)
                    @php $mt = $tcxType($m['typeLabel'] ?? ''); @endphp
                    <div class="tcx-doc t-{{ $mt }}" data-tcx-row data-type="{{ $mt }}" data-k="{{ mb_strtolower($m['title'].' '.$tcxClean($m['typeLabel'] ?? '')) }}">
                        <span class="tcx-ic"><x-lucide :name="$tcxTypeIcon[$mt]" /></span>
                        <div class="tcx-doc-txt">
                            <b title="{{ $m['title'] }}">{{ $m['title'] }}</b>
                            <small>{{ $tcxClean($m['typeLabel'] ?? '') ?: $tcxTypeName[$mt] }}</small>
                        </div>
                        <span class="tcx-use"><x-lucide name="check-circle-2" />{{ $m['scope'] }}</span>
                        <div class="tcx-acts">
                            @if ($m['readHref'] ?? null)
                                <a href="{{ $m['readHref'] }}" target="_blank" rel="noopener" class="tcx-btn is-view"><x-lucide name="eye" />Xem trước</a>
                                <span class="tcx-sep" aria-hidden="true"></span>
                            @endif
                            <form method="POST" action="{{ route('teacher.classes.materials.detach', ['class' => $classRoom->id, 'classMaterial' => $m['id']]) }}"
                                  onsubmit="return confirm('Gỡ tài liệu này khỏi lớp? Lịch sử bài làm cũ vẫn giữ nguyên.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="tcx-btn is-del"><x-lucide name="trash-2" />Gỡ khỏi lớp</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="tcx-empty">Lớp chưa gắn tài liệu nào — chọn tài liệu ở khung bên dưới để thêm.</div>
                @endforelse
                <div class="tcx-nomatch" data-tcx-nomatch hidden>Không có tài liệu nào khớp tìm kiếm / bộ lọc.</div>
            </div>
        </div>

        {{-- SỬA 6/11 (khách: "nhiều sách / bộ đề kéo dài quá — thiết kế đẹp, có phân trang, bố cục hài hoà") — khung "Thêm tài liệu
             vào lớp" dựng lại: đầu khung (giới thiệu + ô tìm) / hàng chọn loại / lưới thẻ 3 cột / phân trang 9 thẻ mỗi trang (điện thoại
             5). Vẫn gửi đúng form cũ (teacher.classes.materials.attach, product_id) — logic gắn không đổi. CSS tiền tố tca-. --}}
        @php
            $tcaItems = [];
            foreach ($attachableProducts as $typeKey => $group) {
                foreach ($group['products'] as $p) {
                    $tcaItems[] = [
                        'id' => $p['id'], 'type' => $typeKey, 'title' => $p['title'],
                        'sub' => (! empty($p['adminFree']) && empty($p['expiresAtLabel'])) ? 'Quyền quản trị · không cần mua' : 'Quyền dạy đến '.($p['expiresAtLabel'] ?? '—'),
                    ];
                }
            }
            $tcaTypes = collect($attachableProducts)->map(fn ($g, $k) => ['key' => $k, 'label' => $tcxClean($g['label']), 'count' => count($g['products'])])->values()->all();
        @endphp
        <section class="tca" x-data="tcaPick(@js(['type' => $defaultType, 'items' => $tcaItems]))">
            <div class="tca-head">
                <span class="tcx-folder" aria-hidden="true"><x-lucide name="book-open" /><span><x-lucide name="plus" /></span></span>
                <div class="tca-intro">
                    <h3>Thêm tài liệu vào lớp</h3>
                    <p>@if (auth()->user()?->actingRole() === \App\Models\Role::TEACHER)Bạn đang ở vai Giáo viên từ tài khoản quản trị — thêm được mọi tài liệu đã phát hành, không cần mua.@else Chỉ hiện sách, chuyên đề, bộ đề bạn còn quyền dạy (còn hạn).@endif</p>
                </div>
                @if ($hasAnyAttachable)
                    <label class="tca-search">
                        <x-lucide name="search" />
                        <input type="search" x-model="q" @input="page = 1" placeholder="Tìm tên sách, bộ đề…" aria-label="Tìm tài liệu để thêm">
                        <button type="button" x-show="q" x-cloak @click="q = ''; page = 1" aria-label="Xoá tìm kiếm"><x-lucide name="x" /></button>
                    </label>
                @endif
            </div>

            @if (empty($attachableProducts))
                <div class="tca-empty"><x-lucide name="book-open" /><p>Không có sách / chuyên đề / bộ đề nào bạn còn quyền dạy để thêm — quyền dạy đã hết hạn hoặc chưa được cấp.</p></div>
            @elseif (! $hasAnyAttachable)
                <div class="tca-empty"><x-lucide name="check-circle-2" /><p>Mọi sách / chuyên đề / bộ đề bạn còn quyền dạy đã gắn hết vào lớp này rồi.</p></div>
            @else
                <div class="tca-bar">
                    <div class="tca-types" role="tablist">
                        @foreach ($tcaTypes as $t)
                            <button type="button" role="tab" class="tca-type t-{{ $t['key'] }}" :class="{ on: type === '{{ $t['key'] }}' }" @click="setType('{{ $t['key'] }}')" @disabled($t['count'] === 0)>
                                <x-lucide :name="$tcxTypeIcon[$t['key']] ?? 'book-open'" />{{ $t['label'] }}<em x-text="countFor('{{ $t['key'] }}')">{{ $t['count'] }}</em>
                            </button>
                        @endforeach
                    </div>
                    <span class="tca-range" x-show="filtered.length" x-text="rangeText"></span>
                </div>

                <div class="tca-grid">
                    <template x-for="it in pageItems" :key="it.id">
                        <form method="POST" action="{{ route('teacher.classes.materials.attach', $classRoom->id) }}" class="tca-card" :class="'t-' + it.type" @submit="busy = it.id">
                            <input type="hidden" name="_token" value="{{ csrf_token() }}">
                            <input type="hidden" name="product_id" :value="it.id">
                            <span class="tca-ic">
                                <span x-show="it.type === 'book'"><x-lucide name="book-open" /></span>
                                <span x-show="it.type === 'topic'"><x-lucide name="layers" /></span>
                                <span x-show="it.type === 'exam'"><x-lucide name="clipboard-list" /></span>
                            </span>
                            <div class="tca-tx">
                                <b :title="it.title" x-text="it.title"></b>
                                <small x-text="it.sub"></small>
                            </div>
                            <button type="submit" class="tca-add" :disabled="busy !== null" :aria-label="'Thêm ' + it.title + ' vào lớp'">
                                <span x-show="busy !== it.id"><x-lucide name="plus" /></span><span x-text="busy === it.id ? 'Đang thêm…' : 'Thêm'"></span>
                            </button>
                        </form>
                    </template>
                </div>
                <div class="tca-none" x-show="filtered.length === 0" x-cloak>
                    <x-lucide name="search" /><p x-text="q ? 'Không tìm thấy tài liệu khớp “' + q + '” trong mục này.' : 'Mục này không còn tài liệu nào để thêm.'"></p>
                </div>

                <nav class="tca-pager" x-show="pages > 1" x-cloak aria-label="Phân trang tài liệu">
                    <button type="button" class="tca-pg" @click="go(page - 1)" :disabled="page === 1" aria-label="Trang trước"><x-lucide name="chevron-left" /></button>
                    <template x-for="(n, k) in pageList" :key="k">
                        <button type="button" class="tca-pg" :class="{ on: n === page, dots: n === '…' }" :disabled="n === '…'" @click="go(n)" x-text="n"></button>
                    </template>
                    <button type="button" class="tca-pg" @click="go(page + 1)" :disabled="page === pages" aria-label="Trang sau"><x-lucide name="chevron-right" /></button>
                </nav>
            @endif
        </section>
        <style>
            .tca { margin-top: 12px; padding: 18px; border: 1.5px dashed #BFD4F1; border-radius: 18px; background: linear-gradient(180deg, #FBFDFF, #F6FAFF); }
            .tca-head { display: flex; flex-wrap: wrap; align-items: center; gap: 12px 14px; }
            .tca-intro { flex: 1 1 320px; min-width: 0; }
            .tca-intro h3 { margin: 0; font-size: 14.5px; font-weight: 700; color: var(--tcx-ink, #0F2F57); }
            .tca-intro p { margin: 3px 0 0; font-size: 13px; line-height: 1.5; color: #64748B; }
            .tca-search { display: flex; align-items: center; gap: 8px; flex: 0 1 320px; min-width: 220px; height: 40px; padding: 0 12px; border: 1px solid #D9E3EE; border-radius: 12px; background: #fff; transition: border-color .15s, box-shadow .15s; }
            .tca-search:focus-within { border-color: #3B82F6; box-shadow: 0 0 0 4px rgba(59, 130, 246, .12); }
            .tca-search svg { width: 16px; height: 16px; flex: none; color: #94A3B8; }
            .tca-search input { flex: 1; min-width: 0; height: 100%; padding: 0; border: 0; outline: 0; background: none; box-shadow: none; font: inherit; font-size: 13px; color: #0F172A; }
            .tca-search input:focus { box-shadow: none; outline: 0; }
            .tca-search input::-webkit-search-cancel-button { display: none; }
            .tca-search button { display: grid; place-items: center; width: 24px; height: 24px; flex: none; padding: 0; border: 0; border-radius: 7px; background: #F1F5F9; color: #64748B; cursor: pointer; }
            .tca-search button svg { width: 13px; height: 13px; color: inherit; }
            .tca-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin: 16px 0 12px; }
            .tca-types { display: flex; flex-wrap: wrap; gap: 4px; padding: 4px; border-radius: 13px; background: #EDF2F8; }
            .tca-type { display: inline-flex; align-items: center; gap: 7px; height: 34px; padding: 0 14px; border: 0; border-radius: 10px; background: none; font: inherit; font-size: 13px; font-weight: 700; color: #475569; cursor: pointer; transition: all .15s; }
            .tca-type svg { width: 15px; height: 15px; }
            .tca-type.t-book svg { color: #2563EB; } .tca-type.t-topic svg { color: #7C3AED; } .tca-type.t-exam svg { color: #EA580C; }
            .tca-type em { font-style: normal; min-width: 20px; padding: 1px 7px; border-radius: 999px; background: rgba(255, 255, 255, .8); font-size: 11px; font-weight: 700; color: #64748B; text-align: center; }
            .tca-type:hover:not(:disabled) { color: #0F172A; }
            .tca-type.on { background: #fff; color: #0F2F57; box-shadow: 0 2px 8px rgba(15, 42, 74, .1); }
            .tca-type.on em { background: #EEF4FF; color: #1D4ED8; }
            .tca-type:disabled { opacity: .45; cursor: not-allowed; }
            .tca-range { font-size: 12px; font-weight: 600; color: #94A3B8; }
            .tca-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 10px; }
            @media (min-width: 720px) { .tca-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
            @media (min-width: 1200px) { .tca-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
            .tca-card { display: flex; align-items: center; gap: 12px; min-width: 0; margin: 0; padding: 12px; border: 1px solid #E3EBF4; border-radius: 14px; background: #fff; transition: border-color .15s, box-shadow .15s, transform .15s; }
            .tca-card:hover { border-color: #BFD3EA; box-shadow: 0 10px 22px rgba(15, 42, 74, .07); transform: translateY(-1px); }
            .tca-ic { display: grid; place-items: center; width: 40px; height: 40px; flex: none; border-radius: 12px; background: #EEF4FF; color: #2563EB; }
            .tca-card.t-topic .tca-ic { background: #F3EEFF; color: #7C3AED; } .tca-card.t-exam .tca-ic { background: #FFF1E6; color: #EA580C; }
            .tca-ic svg { width: 19px; height: 19px; display: block; }
            .tca-tx { flex: 1; min-width: 0; }
            .tca-tx b { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-size: 13px; font-weight: 700; line-height: 1.4; color: #0F172A; }
            .tca-tx small { display: block; margin-top: 3px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; color: #94A3B8; }
            .tca-add { display: inline-flex; align-items: center; gap: 4px; flex: none; height: 32px; padding: 0 12px; border: 1px solid #C7DAF7; border-radius: 10px; background: #F2F7FF; font: inherit; font-size: 13px; font-weight: 700; color: #1D4ED8; white-space: nowrap; cursor: pointer; transition: all .15s; }
            .tca-add svg { width: 14px; height: 14px; display: block; }
            .tca-card:hover .tca-add, .tca-add:focus-visible { border-color: #2563EB; background: linear-gradient(135deg, #2563EB, #1D4ED8); color: #fff; box-shadow: 0 6px 14px rgba(37, 99, 235, .25); }
            .tca-add:disabled { opacity: .55; cursor: wait; }
            .tca-none, .tca-empty { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 26px 12px; border-radius: 14px; border: 1px dashed #D5E1EE; background: #fff; text-align: center; }
            .tca-empty { margin-top: 14px; }
            .tca-none svg, .tca-empty svg { width: 22px; height: 22px; color: #A3B6CC; }
            .tca-none p, .tca-empty p { margin: 0; font-size: 13px; color: #8093A8; }
            .tca-pager { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px; margin-top: 16px; }
            .tca-pg { display: grid; place-items: center; min-width: 36px; height: 36px; padding: 0 8px; border: 1px solid #DCE6F2; border-radius: 10px; background: #fff; font: inherit; font-size: 13px; font-weight: 700; color: #475569; cursor: pointer; transition: all .15s; }
            .tca-pg svg { width: 16px; height: 16px; }
            .tca-pg:hover:not(:disabled) { border-color: #93C5FD; color: #1D4ED8; }
            .tca-pg.on { border-color: #2563EB; background: #2563EB; color: #fff; box-shadow: 0 4px 12px rgba(37, 99, 235, .25); }
            .tca-pg:disabled { opacity: .4; cursor: default; } .tca-pg.dots { border-color: transparent; background: none; opacity: 1; }
            @media (max-width: 560px) { .tca { padding: 14px; } .tca-head .tcx-folder { display: none; } .tca-search { flex: 1 1 100%; min-width: 0; } .tca-types { width: 100%; display: grid; grid-auto-flow: column; grid-auto-columns: minmax(0, 1fr); } .tca-type { justify-content: center; gap: 4px; padding: 0 4px; font-size: 12px; white-space: nowrap; } .tca-type svg { display: none; } .tca-add { padding: 0 10px; } }
        </style>
        <script>
            window.tcaPick = function (c) {
                const fold = (s) => String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd');
                const items = (c.items || []).map((it) => Object.assign({}, it, { f: fold(it.title) }));
                return {
                    type: c.type, q: '', page: 1, busy: null, items, per: (window.matchMedia && window.matchMedia('(max-width: 719px)').matches) ? 5 : 9,
                    setType(t) { this.type = t; this.page = 1; },
                    countFor(t) { const k = fold(this.q.trim()); return this.items.filter((it) => it.type === t && (!k || it.f.includes(k))).length; },
                    get filtered() { const k = fold(this.q.trim()); return this.items.filter((it) => it.type === this.type && (!k || it.f.includes(k))); },
                    get pages() { return Math.max(1, Math.ceil(this.filtered.length / this.per)); },
                    get pageItems() { const p = Math.min(this.page, this.pages); return this.filtered.slice((p - 1) * this.per, p * this.per); },
                    get rangeText() { const n = this.filtered.length, p = Math.min(this.page, this.pages); return 'Hiển thị ' + ((p - 1) * this.per + 1) + '–' + Math.min(p * this.per, n) + ' / ' + n + ' tài liệu'; },
                    get pageList() {
                        const n = this.pages, p = this.page;
                        if (n <= 7) return Array.from({ length: n }, (_, i) => i + 1);
                        const set = [1, p - 1, p, p + 1, n].filter((x) => x >= 1 && x <= n);
                        const out = []; [...new Set(set)].sort((a, b) => a - b).forEach((x, i, arr) => { if (i && x - arr[i - 1] > 1) out.push('…'); out.push(x); });
                        return out;
                    },
                    go(n) { if (typeof n === 'number' && n >= 1 && n <= this.pages) this.page = n; },
                };
            };
        </script>

        <script>
        (() => {
            const box = document.querySelector('[data-tcx-docs]');
            if (!box) return;
            const q = box.querySelector('[data-tcx-q]'), type = box.querySelector('[data-tcx-type]'), none = box.querySelector('[data-tcx-nomatch]');
            const rows = [...box.querySelectorAll('[data-tcx-row]')];
            const fold = (s) => String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd');
            rows.forEach((r) => { r.dataset.f = fold(r.dataset.k); });
            const apply = () => {
                const k = fold(q ? q.value.trim() : ''), t = type ? type.value : '';
                let shown = 0;
                rows.forEach((r) => { const ok = (!t || r.dataset.type === t) && (!k || r.dataset.f.includes(k)); r.hidden = !ok; if (ok) shown++; });
                if (none) none.hidden = rows.length === 0 || shown > 0;
            };
            if (q) q.addEventListener('input', apply);
            if (type) type.addEventListener('change', apply);
        })();
        </script>
    @elseif ($tab === 'attendance')
        @include('teacher.classes.space.attendance')
    @elseif ($tab === 'assign')
        @include('teacher.classes.space.assign')
    @elseif ($tab === 'results')
        @include('teacher.classes.space.results')
    @elseif ($tab === 'organizers')
        @include('teacher.classes.space.organizers')
    @elseif ($tab === 'progress')
        @include('teacher.classes.space.progress')
    @elseif ($tab === 'notices')
        {{-- SỬA 6/11 (khách: "thêm tab Thông báo để giáo viên thông báo cho toàn bộ học sinh của lớp, thiết kế đẹp dễ dùng") —
             soạn + gửi thông báo cho cả lớp (học sinh / kèm phụ huynh / chỉ phụ huynh), mẫu soạn nhanh, xem trước như học sinh
             thấy, bước xác nhận trước khi gửi, lịch sử đã gửi. Gửi qua teacher.notices.store (back_to_class=1) — dùng lại nguyên
             TeacherWorkspaceService::saveAnnouncement() (thông báo vào chuông + dải "Thông báo mới" ở trang Tổng quan học sinh). --}}
        @php
            $tncCounts = $noticeCounts ?? ['students' => 0, 'parents' => 0];
            $tncList = $notices ?? [];
            $tncAudLabel = ['students' => 'Học sinh', 'families' => 'Học sinh + phụ huynh', 'parents' => 'Chỉ phụ huynh'];
        @endphp
        @if (session('notice_status') === 'published')
            @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã gửi thông báo — học sinh nhận được ngay.'])
        @elseif (session('notice_status') === 'deleted')
            @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã xoá thông báo — học sinh, phụ huynh không còn thấy nữa.'])
        @endif
        @if ($errors->any())
            <div class="tcx-alert"><x-lucide name="alert-triangle" /><div>@foreach ($errors->all() as $message)<p style="margin:0">{{ $message }}</p>@endforeach</div></div>
        @endif

        <section class="tcx-sec tnc-compose" x-data="tncForm(@js([
            'students' => (int) $tncCounts['students'], 'parents' => (int) $tncCounts['parents'],
            'aud' => old('audience', 'students'), 'title' => old('title', ''), 'body' => old('body', ''),
            'className' => $classRoom->name, 'teacher' => auth()->user()->name,
        ]))">
            <div class="tcx-sec-head">
                <span class="tcx-ic tnc-ic"><x-lucide name="megaphone" /></span>
                <div style="flex:1 1 260px;min-width:0"><h3>Gửi thông báo cho cả lớp</h3><p>Các em nhận ngay trong chuông thông báo và ở mục "Thông báo mới" trang Tổng quan.</p></div>
                <span class="tcx-count">{{ $tncCounts['students'] }} học sinh · {{ $tncCounts['parents'] }} phụ huynh</span>
            </div>

            @if ((int) $tncCounts['students'] === 0)
                <div class="tnc-warn"><x-lucide name="alert-triangle" /><span>Lớp chưa có học sinh đang học nên chưa gửi được. Hãy thêm học sinh ở tab <a href="{{ route('teacher.classes.show', ['class' => $classRoom->id, 'tab' => 'members']) }}">Học sinh</a> trước.</span></div>
            @endif

            <div class="tnc-grid">
                <form method="POST" action="{{ route('teacher.notices.store') }}" class="tnc-form" @submit="sending = true" data-csp-ajax>
                    @csrf
                    <input type="hidden" name="class_room_id" value="{{ $classRoom->id }}">
                    <input type="hidden" name="back_to_class" value="1">
                    <input type="hidden" name="action" value="publish">

                    <div>
                        <span class="tnc-lb">1. Gửi tới</span>
                        <div class="tnc-aud" role="radiogroup">
                            <label class="tnc-opt" :class="{ on: aud === 'students' }">
                                <input type="radio" name="audience" value="students" x-model="aud">
                                <span class="tnc-opt-ic"><x-lucide name="users" /></span>
                                <span class="tnc-opt-tx"><b>Học sinh trong lớp</b><small x-text="students + ' học sinh'"></small></span>
                                <span class="tnc-tick"><x-lucide name="check" /></span>
                            </label>
                            <label class="tnc-opt" :class="{ on: aud === 'families' }">
                                <input type="radio" name="audience" value="families" x-model="aud">
                                <span class="tnc-opt-ic f"><x-lucide name="user-round" /></span>
                                <span class="tnc-opt-tx"><b>Học sinh + phụ huynh</b><small x-text="students + ' HS · ' + parents + ' phụ huynh'"></small></span>
                                <span class="tnc-tick"><x-lucide name="check" /></span>
                            </label>
                            <label class="tnc-opt" :class="{ on: aud === 'parents', dis: parents === 0 }">
                                <input type="radio" name="audience" value="parents" x-model="aud" :disabled="parents === 0">
                                <span class="tnc-opt-ic p"><x-lucide name="user-round" /></span>
                                <span class="tnc-opt-tx"><b>Chỉ phụ huynh</b><small x-text="parents ? parents + ' phụ huynh đã liên kết' : 'Chưa có phụ huynh liên kết'"></small></span>
                                <span class="tnc-tick"><x-lucide name="check" /></span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <span class="tnc-lb">2. Soạn nội dung <em>— hoặc bấm một mẫu có sẵn rồi sửa lại</em></span>
                        <div class="tnc-tpl">
                            <template x-for="(t, i) in templates" :key="i">
                                <button type="button" @click="useTpl(t)" :class="{ on: picked === i }"><span x-text="t.icon"></span><span x-text="t.name"></span></button>
                            </template>
                        </div>
                    </div>

                    <div class="tnc-field">
                        <div class="tnc-fh"><label for="tnc-title">Tiêu đề</label><small :class="{ over: title.length > 110 }" x-text="title.length + '/120'"></small></div>
                        <input id="tnc-title" name="title" type="text" maxlength="120" x-model="title" x-ref="title" placeholder="Ví dụ: Lịch học tuần sau thay đổi" class="tnc-input">
                    </div>
                    <div class="tnc-field">
                        <div class="tnc-fh"><label for="tnc-body">Nội dung</label><small :class="{ over: body.length > 2900 }" x-text="body.length + '/3000'"></small></div>
                        <textarea id="tnc-body" name="body" rows="6" maxlength="3000" x-model="body" placeholder="Viết rõ ràng, ngắn gọn: việc gì, khi nào, các em cần làm gì…" class="tnc-input tnc-ta"></textarea>
                    </div>

                    <div class="tnc-foot" x-show="!confirming">
                        <small x-show="!canSend" x-text="hint"></small>
                        <button type="button" class="tnc-btn pri" :disabled="!canSend" @click="confirming = true"><x-lucide name="send" /><span x-text="'Gửi tới ' + recipients + ' người'"></span></button>
                    </div>
                    <div class="tnc-confirm" x-show="confirming" x-cloak x-transition>
                        <span class="tnc-confirm-ic"><x-lucide name="bell" /></span>
                        <p>Gửi <b x-text="'“' + title.trim() + '”'"></b> tới <b x-text="whoLabel"></b>? Đã gửi thì không sửa hay thu hồi được.</p>
                        <div class="tnc-acts">
                            <button type="button" class="tnc-btn ghost" @click="confirming = false" :disabled="sending">Xem lại</button>
                            <button type="submit" class="tnc-btn pri" :disabled="sending"><x-lucide name="send" /><span x-text="sending ? 'Đang gửi…' : 'Gửi ngay'"></span></button>
                        </div>
                    </div>
                </form>

                <aside class="tnc-prev">
                    <span class="tnc-lb"><x-lucide name="eye" />Các em sẽ thấy như sau</span>
                    <div class="tnc-phone">
                        <div class="tnc-phone-h"><x-lucide name="bell" />Thông báo <span>1 mới</span></div>
                        <div class="tnc-noti">
                            <span class="tnc-noti-ic">📣</span>
                            <div class="tnc-noti-tx">
                                <b x-text="title.trim() || 'Tiêu đề thông báo'" :class="{ ph: !title.trim() }"></b>
                                <p x-text="previewText" :class="{ ph: !body.trim() }"></p>
                                <small>Vừa xong · Giáo viên <span x-text="teacher"></span></small>
                            </div>
                        </div>
                        <div class="tnc-noti ghost"><span class="tnc-noti-ic">📘</span><div class="tnc-noti-tx"><i></i><i class="s"></i></div></div>
                    </div>
                    <ul class="tnc-tips">
                        <li><x-lucide name="sparkles" />Tiêu đề nói ngay việc chính, vd "Nghỉ học thứ Bảy 08/11".</li>
                        <li><x-lucide name="clock-3" />Ghi rõ ngày, giờ, việc các em cần chuẩn bị.</li>
                        <li><x-lucide name="user-round" />Việc cần gia đình biết thì chọn "Học sinh + phụ huynh".</li>
                    </ul>
                </aside>
            </div>
        </section>

        <section class="tcx-sec" x-data="tncPager({{ count($tncList) }})" x-ref="tncSec">
            <div class="tcx-sec-head">
                <span class="tcx-ic v"><x-lucide name="history" /></span>
                <div style="flex:1 1 220px;min-width:0"><h3>Thông báo đã gửi</h3><p>Mới nhất ở trên — gồm thông báo của mọi giáo viên dạy lớp này.</p></div>
                <span class="tcx-count">{{ count($tncList) }} thông báo</span>
            </div>
            @if (empty($tncList))
                <div class="tcx-empty">Lớp chưa có thông báo nào. Soạn ở khung phía trên rồi bấm <b>Gửi</b> — các em nhận được ngay.</div>
            @else
                <div class="tnc-list">
                    @foreach ($tncList as $n)
                        <article class="tnc-item" x-data="{ del: false }" :class="{ 'is-del': del }" x-show="onPage({{ $loop->index }})" @if ($loop->index >= 5) style="display:none" @endif>
                            <span class="tnc-item-ic">📣</span>
                            <div class="tnc-item-main">
                                <div class="tnc-item-top">
                                    <b>{{ $n['title'] ?: 'Thông báo lớp' }}</b>
                                    <span class="tnc-aud-chip a-{{ $n['audience'] }}">{{ $tncAudLabel[$n['audience']] ?? 'Học sinh' }}</span>
                                </div>
                                <p class="tnc-item-body" :class="{ open: openId === {{ $n['id'] }} }">{{ $n['body'] }}</p>
                                @if (mb_strlen($n['body']) > 180)
                                    <button type="button" class="tnc-more" @click="openId = openId === {{ $n['id'] }} ? null : {{ $n['id'] }}" x-text="openId === {{ $n['id'] }} ? 'Thu gọn' : 'Xem đầy đủ'">Xem đầy đủ</button>
                                @endif
                                <div class="tnc-meta">
                                    <span title="{{ $n['at'] }}"><x-lucide name="clock-3" />{{ $n['ago'] }} · {{ $n['at'] }}</span>
                                    <span><x-lucide name="user-round" />{{ $n['mine'] ? 'Bạn' : $n['by'] }}</span>
                                    @if ($n['students'] > 0)<span><x-lucide name="users" />{{ $n['students'] }} học sinh</span>@endif
                                    @if ($n['parents'] > 0)<span><x-lucide name="user-round" />{{ $n['parents'] }} phụ huynh</span>@endif
                                </div>
                                {{-- SỬA 6/11 — xoá thông báo đã gửi (hỏi lại ngay tại chỗ, không bật hộp thoại). --}}
                                @if (! empty($n['canDelete']))
                                    <form method="POST" action="{{ route('teacher.classes.notices.destroy', ['class' => $classRoom->id, 'notice' => $n['id']]) }}" class="tnc-del-bar" x-show="del" x-cloak x-transition @submit="$el.querySelector('[type=submit]').disabled = true">
                                        @csrf
                                        @method('DELETE')
                                        <x-lucide name="alert-triangle" />
                                        <p>Xoá thông báo này? Học sinh{{ $n['parents'] > 0 ? ', phụ huynh' : '' }} sẽ không còn thấy trong chuông thông báo nữa. Không khôi phục được.</p>
                                        <div class="tnc-del-acts">
                                            <button type="button" class="tnc-mini ghost" @click="del = false">Giữ lại</button>
                                            <button type="submit" class="tnc-mini red"><x-lucide name="trash-2" />Xoá luôn</button>
                                        </div>
                                    </form>
                                @endif
                            </div>
                            @if (! empty($n['canDelete']))
                                <button type="button" class="tnc-del" x-show="! del" @click="del = true" title="Xoá thông báo" aria-label="Xoá thông báo"><x-lucide name="trash-2" /><span>Xoá</span></button>
                            @endif
                        </article>
                    @endforeach
                </div>
                {{-- SỬA 6/11 (khách: "nhiều thông báo thì có phân trang, đừng dài xuống quá") — 5 thông báo mỗi trang. --}}
                <div class="tnc-pager" x-show="pages > 1" x-cloak>
                    <span class="tnc-range" x-text="rangeText"></span>
                    <nav class="tnc-pgs" aria-label="Phân trang thông báo">
                        <button type="button" class="tnc-pg" @click="go(page - 1)" :disabled="page === 1" aria-label="Trang trước"><x-lucide name="chevron-left" /></button>
                        <template x-for="(n, k) in pageList" :key="k">
                            <button type="button" class="tnc-pg" :class="{ on: n === page, dots: n === '…' }" :disabled="n === '…'" @click="go(n)" x-text="n"></button>
                        </template>
                        <button type="button" class="tnc-pg" @click="go(page + 1)" :disabled="page === pages" aria-label="Trang sau"><x-lucide name="chevron-right" /></button>
                    </nav>
                </div>
            @endif
        </section>

        <style>
            .tnc-ic { background: linear-gradient(135deg, #FFEDD5, #FFE4E6) !important; color: #EA580C !important; }
            .tnc-warn { display: flex; align-items: flex-start; gap: 8px; margin-bottom: 14px; padding: 10px 12px; border-radius: 12px; background: #FFF7E6; border: 1px solid #FDE2B3; font-size: 13px; line-height: 1.5; color: #92400E; }
            .tnc-warn svg { width: 15px; height: 15px; flex: none; margin-top: 2px; } .tnc-warn a { color: #B45309; font-weight: 700; }
            .tnc-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 18px; }
            @media (min-width: 1100px) { .tnc-grid { grid-template-columns: minmax(0, 1.55fr) minmax(300px, 1fr); gap: 22px; } }
            .tnc-form { display: grid; gap: 16px; min-width: 0; }
            .tnc-lb { display: block; margin: 0 0 8px 2px; line-height: 1.5; font-size: 13px; font-weight: 700; color: #1E293B; }
            .tnc-lb em { font-style: normal; font-weight: 600; color: #94A3B8; }
            .tnc-lb svg { width: 15px; height: 15px; margin-right: 5px; vertical-align: -3px; color: #64748B; }
            .tnc-aud { display: grid; grid-template-columns: minmax(0, 1fr); gap: 10px; }
            @media (min-width: 700px) { .tnc-aud { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
            .tnc-opt { position: relative; display: flex; align-items: center; gap: 10px; padding: 12px; border: 1.5px solid #E2E8F0; border-radius: 14px; background: #fff; cursor: pointer; transition: all .15s; }
            .tnc-opt:hover { border-color: #BFD3EA; background: #FAFCFF; }
            .tnc-opt input { position: absolute; opacity: 0; pointer-events: none; }
            .tnc-opt.on { border-color: #2563EB; background: #F5F9FF; box-shadow: 0 0 0 4px rgba(37, 99, 235, .1); }
            .tnc-opt.dis { opacity: .55; cursor: not-allowed; }
            .tnc-opt-ic { display: grid; place-items: center; width: 36px; height: 36px; flex: none; border-radius: 11px; background: #EEF4FF; color: #2563EB; }
            .tnc-opt-ic.f { background: #ECFDF5; color: #059669; } .tnc-opt-ic.p { background: #FFF1F2; color: #E11D48; }
            .tnc-opt-ic svg { width: 18px; height: 18px; }
            .tnc-opt-tx { flex: 1; min-width: 0; } .tnc-opt-tx b { display: block; font-size: 13px; font-weight: 700; color: #0F172A; } .tnc-opt-tx small { display: block; margin-top: 1px; font-size: 12px; color: #64748B; }
            .tnc-tick { display: grid; place-items: center; width: 20px; height: 20px; flex: none; border-radius: 50%; border: 1.5px solid #CBD5E1; color: transparent; }
            .tnc-tick svg { width: 12px; height: 12px; }
            .tnc-opt.on .tnc-tick { border-color: #2563EB; background: #2563EB; color: #fff; }
            .tnc-tpl { display: flex; flex-wrap: wrap; gap: 8px; }
            .tnc-tpl button { display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 13px; border: 1px solid #E2E8F0; border-radius: 999px; background: #fff; font: inherit; font-size: 13px; font-weight: 700; color: #334155; cursor: pointer; transition: all .15s; }
            .tnc-tpl button:hover { border-color: #FDBA74; background: #FFF7ED; color: #C2410C; }
            .tnc-tpl button.on { border-color: #F97316; background: #FFF7ED; color: #C2410C; }
            .tnc-field { min-width: 0; }
            .tnc-fh { display: flex; align-items: baseline; justify-content: space-between; margin: 0 2px 6px; }
            .tnc-fh label { font-size: 13px; font-weight: 700; color: #1E293B; }
            .tnc-fh small { font-size: 11px; font-weight: 600; color: #94A3B8; font-variant-numeric: tabular-nums; } .tnc-fh small.over { color: #DC2626; }
            .tnc-input { display: block; box-sizing: border-box; width: 100%; height: 44px; padding: 0 14px; border: 1px solid #D9E3EE; border-radius: 12px; background: #fff; font: inherit; font-size: 14px; color: #0F172A; transition: border-color .15s, box-shadow .15s; }
            .tnc-input::placeholder { color: #A3B1C2; }
            .tnc-input:focus { outline: 0; border-color: #3B82F6; box-shadow: 0 0 0 4px rgba(59, 130, 246, .12); }
            .tnc-ta { height: auto; min-height: 140px; padding: 12px 14px; line-height: 1.6; resize: vertical; }
            .tnc-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 12px; }
            .tnc-foot small { margin-right: auto; font-size: 12px; color: #94A3B8; }
            .tnc-btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; height: 42px; padding: 0 20px; border: 1px solid transparent; border-radius: 12px; font: inherit; font-size: 14px; font-weight: 700; white-space: nowrap; cursor: pointer; transition: all .15s; }
            .tnc-btn svg { width: 16px; height: 16px; }
            .tnc-btn.pri { background: linear-gradient(135deg, #F97316, #EA580C); color: #fff; box-shadow: 0 8px 20px rgba(234, 88, 12, .28); }
            .tnc-btn.pri:hover { transform: translateY(-1px); }
            .tnc-btn.pri:disabled { opacity: .45; cursor: not-allowed; transform: none; box-shadow: none; }
            .tnc-btn.ghost { border-color: #E2E8F0; background: #fff; color: #475569; }
            .tnc-confirm { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding: 14px; border-radius: 14px; background: linear-gradient(120deg, #FFF7ED, #FFF1F2); border: 1px solid #FED7AA; }
            .tnc-confirm-ic { display: grid; place-items: center; width: 38px; height: 38px; flex: none; border-radius: 12px; background: #fff; color: #EA580C; box-shadow: 0 4px 10px rgba(234, 88, 12, .15); }
            .tnc-confirm-ic svg { width: 18px; height: 18px; }
            .tnc-confirm p { flex: 1 1 240px; margin: 0; font-size: 13px; line-height: 1.5; color: #7C2D12; }
            .tnc-acts { display: flex; gap: 10px; margin-left: auto; }
            .tnc-prev { min-width: 0; }
            .tnc-phone { padding: 14px; border-radius: 20px; background: linear-gradient(160deg, #F1F5FB, #EEF2FF); border: 1px solid #E2E8F0; }
            .tnc-phone-h { display: flex; align-items: center; gap: 6px; margin-bottom: 10px; font-size: 13px; font-weight: 700; color: #0F2F57; }
            .tnc-phone-h svg { width: 15px; height: 15px; } .tnc-phone-h span { margin-left: auto; padding: 2px 8px; border-radius: 999px; background: #EF4444; color: #fff; font-size: 11px; }
            .tnc-noti { display: flex; gap: 10px; padding: 12px; border-radius: 14px; background: #fff; box-shadow: 0 6px 16px rgba(15, 42, 74, .07); }
            .tnc-noti + .tnc-noti { margin-top: 8px; }
            .tnc-noti.ghost { opacity: .5; box-shadow: none; }
            .tnc-noti-ic { display: grid; place-items: center; width: 34px; height: 34px; flex: none; border-radius: 11px; background: #FFF1E6; font-size: 16px; }
            .tnc-noti-tx { flex: 1; min-width: 0; }
            .tnc-noti-tx b { display: block; font-size: 13px; font-weight: 700; color: #0F172A; word-break: break-word; }
            .tnc-noti-tx p { margin: 3px 0 0; font-size: 12px; line-height: 1.5; color: #475569; word-break: break-word; display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden; }
            .tnc-noti-tx .ph { color: #A3B1C2; font-weight: 600; }
            .tnc-noti-tx small { display: block; margin-top: 6px; font-size: 11px; color: #94A3B8; }
            .tnc-noti-tx i { display: block; height: 9px; width: 70%; border-radius: 6px; background: #E2E8F0; margin-top: 4px; } .tnc-noti-tx i.s { width: 45%; }
            .tnc-tips { display: grid; gap: 8px; margin: 14px 0 0; padding: 0; list-style: none; }
            .tnc-tips li { display: flex; align-items: flex-start; gap: 8px; font-size: 12px; line-height: 1.5; color: #64748B; }
            .tnc-tips svg { width: 14px; height: 14px; flex: none; margin-top: 2px; color: #F59E0B; }
            .tnc-list { display: grid; gap: 10px; }
            .tnc-item { display: flex; gap: 12px; padding: 14px; border: 1px solid #E6EEF6; border-radius: 14px; background: #fff; transition: box-shadow .15s; }
            .tnc-item:hover { box-shadow: 0 8px 18px rgba(15, 42, 74, .06); }
            .tnc-item-ic { display: grid; place-items: center; width: 40px; height: 40px; flex: none; border-radius: 12px; background: #FFF1E6; font-size: 18px; }
            .tnc-item-main { flex: 1; min-width: 0; }
            .tnc-item-top { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 10px; }
            .tnc-item-top b { font-size: 14px; font-weight: 700; color: #0F172A; }
            .tnc-aud-chip { padding: 2px 9px; border-radius: 999px; font-size: 11px; font-weight: 700; background: #EEF4FF; color: #1D4ED8; }
            .tnc-aud-chip.a-families { background: #ECFDF5; color: #047857; } .tnc-aud-chip.a-parents { background: #FFF1F2; color: #BE123C; }
            .tnc-item-body { margin: 6px 0 0; font-size: 13px; line-height: 1.6; color: #334155; white-space: pre-line; word-break: break-word; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
            .tnc-item-body.open { display: block; -webkit-line-clamp: unset; }
            .tnc-more { margin-top: 4px; padding: 0; border: 0; background: none; font: inherit; font-size: 12px; font-weight: 700; color: #2563EB; cursor: pointer; }
            .tnc-meta { display: flex; flex-wrap: wrap; gap: 6px 14px; margin-top: 8px; }
            .tnc-meta span { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; color: #64748B; }
            .tnc-meta svg { width: 13px; height: 13px; }
            .tnc-item { align-items: flex-start; }
            .tnc-pager { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-top: 14px; padding-top: 14px; border-top: 1px solid #EEF2F7; }
            .tnc-range { font-size: 12px; font-weight: 600; color: #94A3B8; }
            .tnc-pgs { display: flex; flex-wrap: wrap; gap: 6px; }
            .tnc-pg { display: grid; place-items: center; min-width: 34px; height: 34px; padding: 0 8px; border: 1px solid #DCE6F2; border-radius: 10px; background: #fff; font: inherit; font-size: 13px; font-weight: 700; color: #475569; cursor: pointer; transition: all .15s; }
            .tnc-pg svg { width: 16px; height: 16px; }
            .tnc-pg:hover:not(:disabled) { border-color: #93C5FD; color: #1D4ED8; }
            .tnc-pg.on { border-color: #2563EB; background: #2563EB; color: #fff; box-shadow: 0 4px 12px rgba(37, 99, 235, .25); }
            .tnc-pg:disabled { opacity: .4; cursor: default; } .tnc-pg.dots { border-color: transparent; background: none; opacity: 1; }
            @media (max-width: 560px) { .tnc-pager { justify-content: center; } .tnc-range { width: 100%; text-align: center; } }
            .tnc-item.is-del { border-color: #FECACA; background: #FFFBFB; }
            .tnc-del { display: inline-flex; align-items: center; gap: 5px; flex: none; height: 32px; padding: 0 11px; border: 1px solid #E2E8F0; border-radius: 10px; background: #fff; font: inherit; font-size: 12px; font-weight: 700; color: #64748B; cursor: pointer; transition: all .15s; }
            .tnc-del svg { width: 14px; height: 14px; }
            .tnc-del:hover { border-color: #FCA5A5; background: #FFF5F5; color: #DC2626; }
            .tnc-del-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 10px; margin-top: 12px; padding: 10px 12px; border-radius: 12px; background: #FEF2F2; border: 1px solid #FECACA; }
            .tnc-del-bar > svg { width: 16px; height: 16px; flex: none; color: #DC2626; }
            .tnc-del-bar p { flex: 1 1 220px; margin: 0; font-size: 13px; line-height: 1.5; color: #991B1B; }
            .tnc-del-acts { display: flex; gap: 8px; margin-left: auto; }
            .tnc-mini { display: inline-flex; align-items: center; gap: 5px; height: 32px; padding: 0 12px; border: 1px solid transparent; border-radius: 9px; font: inherit; font-size: 12px; font-weight: 700; cursor: pointer; }
            .tnc-mini svg { width: 14px; height: 14px; }
            .tnc-mini.ghost { border-color: #E2E8F0; background: #fff; color: #475569; }
            .tnc-mini.red { background: #DC2626; color: #fff; box-shadow: 0 4px 12px rgba(220, 38, 38, .25); } .tnc-mini.red:hover { background: #B91C1C; } .tnc-mini.red:disabled { opacity: .6; }
            @media (max-width: 560px) { .tnc-foot .tnc-btn, .tnc-acts { width: 100%; } .tnc-acts .tnc-btn { flex: 1; } .tnc-del span { display: none; } .tnc-del { padding: 0 9px; } }
        </style>
        <script>
            window.tncPager = function (total) {
                return {
                    openId: null, page: 1, per: 5, total,
                    get pages() { return Math.max(1, Math.ceil(this.total / this.per)); },
                    onPage(i) { return Math.floor(i / this.per) + 1 === this.page; },
                    get rangeText() { return 'Hiển thị ' + ((this.page - 1) * this.per + 1) + '–' + Math.min(this.page * this.per, this.total) + ' / ' + this.total + ' thông báo'; },
                    get pageList() {
                        const n = this.pages, p = this.page;
                        if (n <= 7) return Array.from({ length: n }, (_, i) => i + 1);
                        const out = []; [...new Set([1, p - 1, p, p + 1, n].filter((x) => x >= 1 && x <= n))].sort((a, b) => a - b)
                            .forEach((x, i, arr) => { if (i && x - arr[i - 1] > 1) out.push('…'); out.push(x); });
                        return out;
                    },
                    go(n) {
                        if (typeof n !== 'number' || n < 1 || n > this.pages || n === this.page) return;
                        this.page = n; this.openId = null;
                        const r = this.$refs.tncSec; if (r && r.getBoundingClientRect().top < 0) r.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    },
                };
            };
            window.tncForm = function (c) {
                const cls = c.className || 'lớp';
                return {
                    students: c.students, parents: c.parents, aud: c.aud || 'students', title: c.title || '', body: c.body || '',
                    teacher: c.teacher || '', confirming: false, sending: false, picked: null,
                    templates: [
                        { icon: '🏖️', name: 'Nghỉ học', title: 'Thông báo nghỉ học ngày [dd/mm]', body: 'Các em lưu ý: lớp ' + cls + ' nghỉ học buổi ngày [dd/mm]. Lịch học bù thầy/cô sẽ thông báo sau. Các em tranh thủ ôn lại bài cũ nhé!' },
                        { icon: '🗓️', name: 'Đổi lịch học', title: 'Thay đổi lịch học tuần này', body: 'Buổi học ngày [dd/mm] của lớp ' + cls + ' chuyển sang [giờ], ngày [dd/mm]. Các em sắp xếp thời gian tham gia đầy đủ nhé.' },
                        { icon: '📝', name: 'Nhắc làm bài', title: 'Nhắc hoàn thành bài tập', body: 'Các em nhớ hoàn thành bài tập đã giao trước [giờ] ngày [dd/mm]. Em nào gặp khó khăn cứ nhắn thầy/cô để được hỗ trợ.' },
                        { icon: '🎯', name: 'Kiểm tra sắp tới', title: 'Lịch kiểm tra ngày [dd/mm]', body: 'Lớp ' + cls + ' sẽ làm bài kiểm tra vào [giờ], ngày [dd/mm]. Nội dung: [phạm vi kiến thức]. Các em ôn tập kỹ và vào đúng giờ nhé.' },
                        { icon: '🌟', name: 'Khen ngợi', title: 'Cả lớp làm rất tốt!', body: 'Thầy/cô rất vui vì tuần qua cả lớp học tập chăm chỉ và tiến bộ rõ rệt. Các em tiếp tục cố gắng nhé!' },
                    ],
                    get recipients() { return this.aud === 'parents' ? this.parents : (this.aud === 'families' ? this.students + this.parents : this.students); },
                    get whoLabel() { return this.aud === 'parents' ? this.parents + ' phụ huynh' : (this.aud === 'families' ? this.students + ' học sinh và ' + this.parents + ' phụ huynh' : this.students + ' học sinh'); },
                    get canSend() { return this.students > 0 && this.recipients > 0 && this.title.trim() !== '' && this.body.trim() !== ''; },
                    get hint() { return this.students === 0 ? 'Lớp chưa có học sinh.' : (!this.title.trim() || !this.body.trim() ? 'Nhập tiêu đề và nội dung để gửi.' : ''); },
                    get previewText() { return this.body.trim() ? 'Lớp ' + cls + ' · ' + this.teacher + ': ' + this.body.trim() : 'Nội dung thông báo sẽ hiện ở đây…'; },
                    useTpl(t) { this.picked = this.templates.indexOf(t); this.title = t.title; this.body = t.body; this.confirming = false; this.$nextTick(() => this.$refs.title && this.$refs.title.focus()); },
                };
            };
        </script>
    @else
        @include('teacher.classes.space.overview')
    @endif
