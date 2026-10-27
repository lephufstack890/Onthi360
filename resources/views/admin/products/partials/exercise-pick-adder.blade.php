{{-- SỬA 27/10 (khách: "thêm option chọn up từ bài có sẵn — bài của tài liệu; 1 bài dùng ở chương tài liệu này cũng dùng ở chương
     tài liệu khác. Cho chọn sách, chọn chương lọc để chọn bài cho dễ. Bài nào có rồi thì đừng hiển thị trong option") — cách thêm
     thứ 3 của khối Bài tập đính kèm. Chọn bài ở tài liệu khác → thêm vào chương đang chọn.
     SỬA 27/10 lần 2 (khách: "chỉ dùng ké, không lưu thêm") — thêm = DÙNG CHUNG bài gốc (không chép bài / tệp), xem App\Support\ProductExerciseLinks.
     Máy chủ: Admin\ProductExercisePickController + Services\Admin\ProductExercisePickService (đã bỏ sẵn bài tài liệu này có rồi).
     Tham số: $product, $chapters, $chapterLabel, $exDefaultChapterId. --}}
@php
    $pkChapterLower = $chapterLabel ? mb_strtolower($chapterLabel) : null;
    $pkCfg = [
        'sourcesUrl' => route('admin.products.exercises.pick.sources', $product->id),
        'storeUrl' => route('admin.products.exercises.pick.store', $product->id),
        'csrf' => csrf_token(),
        'chapter' => (string) ($exDefaultChapterId ?? ''),
    ];
@endphp

@once
<style>
    .oi-pk-filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 460px)); gap: 12px; }
    .oi-pk-filters label { display: block; font-size: 12px; font-weight: 700; color: #486581; margin-bottom: 4px; }
    .oi-pk-list { max-height: 380px; overflow-y: auto; margin: 0; padding: 0; list-style: none; border: 1px solid #E2ECF3; border-radius: 12px; background: #fff; }
    .oi-pk-list li { display: flex; align-items: flex-start; gap: 10px; padding: 9px 12px; border-top: 1px solid #EEF3F7; font-size: 13px; color: #334E68; cursor: pointer; }
    .oi-pk-list li:first-child { border-top: 0; }
    .oi-pk-list li:hover { background: #F5F9FC; }
    .oi-pk-list li.is-on { background: #EFF6FF; }
    .oi-pk-list input { margin-top: 3px; accent-color: #2563EB; flex: none; }
    .oi-pk-list code { font-weight: 700; color: #2563EB; margin-right: 4px; }
    .oi-pk-list small { display: block; color: #7A92A3; margin-top: 2px; }
    .oi-pk-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; font-size: 12.5px; color: #486581; margin: 2px 0 6px; }
    .oi-pk-head label { display: inline-flex; align-items: center; gap: 6px; font-weight: 700; cursor: pointer; }
    .oi-pk-empty { padding: 18px 14px; text-align: center; font-size: 13px; color: #7A92A3; border: 1px dashed #D3E0EA; border-radius: 12px; background: #fff; }
    .oi-pk-box { padding: 12px 14px; border-radius: 12px; font-size: 13px; line-height: 1.55; }
    .oi-pk-box.is-bad { border: 1px solid #FBCFE8; background: #FFF1F2; color: #9F1239; }
    .oi-pk-box.is-ok { border: 1px solid #A7F3D0; background: #ECFDF5; color: #065F46; }
    .oi-pk-box ul { margin: 6px 0 0; padding-left: 18px; }
</style>
<script>
    function exercisePick(cfg) {
        return {
            chapter: cfg.chapter || '', taiLieu: '', chuong: '',
            products: [], chapters: [], items: [], total: 0, picked: [], roots: {}, note: '',
            loading: false, saving: false, error: '', result: null, seq: 0,
            loaded: false,
            // Chỉ tải danh sách khi người dùng mở cách thêm này (không tốn truy vấn mỗi lần mở trang tài liệu).
            open() { if (! this.loaded) this.load(); },
            get allOn() { const r = Object.values(this.roots); return this.items.length > 0 && this.items.every((i) => this.picked.includes(i.id) || r.includes(i.root)); },
            // roots: id đã chọn → id bài gốc. Bài gốc và bản sao của nó (ở tài liệu khác) là CÙNG một bài → chỉ cho chọn 1.
            toggle(id, quiet) {
                if (this.picked.includes(id)) { this.picked = this.picked.filter((x) => x !== id); delete this.roots[id]; return true; }
                const it = this.items.find((i) => i.id === id);
                const root = it ? it.root : id;
                if (Object.values(this.roots).includes(root)) {
                    if (!quiet) this.note = (it ? it.code : 'Bài này') + ' là cùng một bài với bài đã chọn (bài gốc / bản sao) — chỉ cần chọn 1.';
                    return false;
                }
                this.picked = this.picked.concat([id]); this.roots[id] = root; this.note = ''; return true;
            },
            toggleAll() {
                if (this.allOn) { this.items.forEach((i) => { if (this.picked.includes(i.id)) this.toggle(i.id, true); }); return; }
                let dup = 0;
                this.items.forEach((i) => { if (!this.picked.includes(i.id) && !this.toggle(i.id, true)) dup++; });
                this.note = dup ? 'Bỏ qua ' + dup + ' bài trùng bài gốc với bài đã chọn.' : '';
            },
            onTaiLieu() { this.chuong = ''; this.load(); },
            async load() {
                const my = ++this.seq; this.loading = true; this.error = '';
                const p = new URLSearchParams();
                if (this.taiLieu) p.set('tai_lieu', this.taiLieu);
                if (this.chuong) p.set('chuong', this.chuong);
                try {
                    const res = await fetch(cfg.sourcesUrl + '?' + p.toString(), { headers: { 'Accept': 'application/json' } });
                    const data = await res.json().catch(() => ({}));
                    if (my !== this.seq) return; // đã có lượt lọc mới hơn
                    if (!res.ok) { this.error = data.message || ('Không tải được danh sách bài (mã lỗi ' + res.status + ').'); return; }
                    this.loaded = true;
                    this.products = data.products || []; this.chapters = data.chapters || [];
                    this.items = data.items || []; this.total = data.total || 0;
                    this.taiLieu = data.selected && data.selected.tai_lieu ? String(data.selected.tai_lieu) : '';
                    this.chuong = data.selected && data.selected.chuong ? String(data.selected.chuong) : '';
                } catch (e) {
                    if (my === this.seq) this.error = 'Mất kết nối khi tải danh sách bài — thử lại.';
                } finally {
                    if (my === this.seq) this.loading = false;
                }
            },
            async save() {
                if (!this.picked.length || this.saving) return;
                this.saving = true; this.error = ''; this.result = null;
                try {
                    const res = await fetch(cfg.storeUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': cfg.csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                        body: JSON.stringify({ ids: this.picked, material_id: this.chapter || null }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.ok) {
                        this.error = data.message || (data.errors ? Object.values(data.errors).flat()[0] : 'Không thêm được (mã lỗi ' + res.status + ').');
                        return;
                    }
                    this.result = data; this.picked = []; this.roots = {}; this.note = '';
                    if (!(data.skipped || []).length) { setTimeout(() => window.location.reload(), 1500); }
                    else { this.load(); }
                } catch (e) {
                    this.error = 'Mất kết nối khi thêm bài — tải lại trang để xem bài nào đã vào.';
                } finally {
                    this.saving = false;
                }
            },
        };
    }
</script>
@endonce

<div class="oi-add oi-zip" x-data="exercisePick(@js($pkCfg))" @ex-chapter.window="if (! saving) chapter = String($event.detail ?? '')" @ex-pick-open.window="open()">
    @if ($chapterLabel)
        <div>
            <label class="oi-add__lbl" for="pk-chapter">1. Thêm vào {{ $pkChapterLower }} <small>(của tài liệu này)</small></label>
            <select id="pk-chapter" x-model="chapter" @change="$dispatch('ex-chapter', chapter)" class="oi-add__input" style="max-width: 460px" :disabled="saving">
                <option value="">— Chưa gắn {{ $pkChapterLower }} —</option>
                @foreach ($chapters as $c)
                    <option value="{{ $c['id'] }}">{{ $c['title'] }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div>
        <span class="oi-add__lbl">{{ $chapterLabel ? '2.' : '1.' }} Chọn bài có sẵn ở tài liệu khác</span>
        {{-- SỬA 27/10 (khách: "chọn sách nào và chọn chương là được, đừng lọc nhiều quá") — chỉ còn 2 ô: Tài liệu, Chương. --}}
        <div class="oi-pk-filters">
            <div>
                <label for="pk-tl">Chọn sách / tài liệu</label>
                <select id="pk-tl" x-model="taiLieu" @change="onTaiLieu()" class="oi-add__input">
                    <option value="">— Chọn tài liệu —</option>
                    <template x-for="p in products" :key="p.id"><option :value="String(p.id)" :selected="String(p.id) === taiLieu" x-text="p.typeLabel + ' · ' + p.title + ' (' + p.count + ' bài)'"></option></template>
                </select>
            </div>
            <div>
                <label for="pk-ch">Chọn chương</label>
                <select id="pk-ch" x-model="chuong" @change="load()" class="oi-add__input" :disabled="! taiLieu">
                    <option value="">Tất cả chương</option>
                    <template x-for="c in chapters" :key="c.id"><option :value="String(c.id)" :selected="String(c.id) === chuong" x-text="c.title + ' (' + c.count + ' bài)'"></option></template>
                </select>
            </div>
        </div>
        <p class="oi-add__note">Danh sách đã <b>ẩn sẵn bài tài liệu này đã có</b> (kể cả bài đã lấy từ nơi khác trước đó) — không lo chọn trùng.</p>
    </div>

    <div x-show="error" x-cloak class="oi-pk-box is-bad" role="alert"><b x-text="error"></b></div>
    <div x-show="note" x-cloak class="oi-pk-box is-bad" role="status" x-text="note"></div>
    <div x-show="result" x-cloak class="oi-pk-box" :class="result && result.skipped && result.skipped.length ? 'is-bad' : 'is-ok'" role="status">
        <b x-text="result ? ('Đã thêm ' + result.added.length + ' bài' + (result.skipped.length ? ', bỏ qua ' + result.skipped.length + ' bài' : '') + (result.skipped.length ? '.' : ' — đang tải lại trang…')) : ''"></b>
        <ul x-show="result && result.skipped.length">
            <template x-for="(s, i) in (result ? result.skipped : [])" :key="'s' + i"><li><code x-text="s.code"></code> <span x-text="s.title"></span> — <span x-text="s.reason"></span></li></template>
        </ul>
    </div>

    <div>
        <div class="oi-pk-head" x-show="items.length" x-cloak>
            <label><input type="checkbox" :checked="allOn" @change="toggleAll(); $nextTick(() => { $el.checked = allOn })" style="accent-color:#2563EB"> Chọn tất cả <span x-text="items.length"></span> bài đang hiện</label>
            <span x-text="(total > items.length ? 'Hiện ' + items.length + ' / ' + total + ' bài — lọc thêm để thu hẹp · ' : '') + 'Đã chọn ' + picked.length + ' bài'"></span>
        </div>
        <ul class="oi-pk-list" x-show="items.length" x-cloak aria-label="Bài có sẵn để chọn">
            <template x-for="it in items" :key="it.id">
                <li :class="picked.includes(it.id) ? 'is-on' : ''" @click="toggle(it.id)">
                    <input type="checkbox" :checked="picked.includes(it.id)"  @click.stop @change="toggle(it.id); $nextTick(() => { $el.checked = picked.includes(it.id) })" :aria-label="'Chọn ' + it.code">
                    <span><code x-text="it.code"></code><span x-text="it.title"></span>
                        <small x-text="it.typeLabel + ' · ' + it.points + ' điểm · ' + it.summary + ' · ' + it.product + (it.chapter ? ' › ' + it.chapter : '') + (it.copyOf ? ' · bản sao của ' + it.copyOf : '')"></small></span>
                </li>
            </template>
        </ul>
        <div class="oi-pk-empty" x-show="! items.length" x-cloak
             x-text="loading ? 'Đang tải…' : (! products.length ? 'Không còn bài nào để lấy — tài liệu này đã có hết bài của các tài liệu khác (hoặc chưa có tài liệu nào khác có bài tập).' : (taiLieu ? 'Chương này không còn bài nào để lấy.' : 'Chọn sách / tài liệu để xem bài.'))"></div>
    </div>

    <div class="oi-add__actions">
        <button type="button" class="acx-btn acx-btn--primary acx-btn--sm" :disabled="! picked.length || saving" @click="save()">
            <x-lucide name="plus" /> <span x-text="saving ? 'Đang thêm…' : (picked.length ? 'Thêm ' + picked.length + ' bài vào {{ $pkChapterLower ?? 'tài liệu' }} đã chọn' : 'Chọn bài để thêm')">Chọn bài để thêm</span>
        </button>
        <button type="button" class="acx-btn acx-btn--sm" x-show="picked.length && ! saving" x-cloak @click="picked = []; roots = {}; note = ''">Bỏ chọn</button>
        <span class="oi-add__note" style="margin:0">Bài được <b>dùng chung</b> với tài liệu gốc — không lưu thêm bản nào, giữ nguyên mã bài. Sửa bài gốc là ở đây đổi theo; muốn bỏ thì bấm "Gỡ" (bài gốc không bị xoá).</span>
    </div>
</div>
