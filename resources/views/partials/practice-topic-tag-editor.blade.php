{{-- SỬA 16/11 (khách: "thêm nút sửa tag chuyên đề ngay trên từng bài, chỉ admin thấy; bấm thì hiện popup thật đẹp để cập nhật
     tag, chọn tag xong thì chỉ định TAG CHÍNH — tag chính hiện to ở trên, tag phụ nhỏ bên dưới").
     Chỉ dựng khi người xem có quyền Ngân hàng nội dung › Sửa (practice-catalog quyết định). Mở bằng sự kiện 'topic-tag-edit'
     kèm { id, title, code, tags: [{id, name}] } (tag đầu = tag chính). Lưu: admin.content.questions.topic-tags (JSON). --}}
@php
    $tteTags = \App\Models\Tag::query()->orderBy('name')->get()
        ->map(fn ($t) => ['id' => (int) $t->id, 'name' => (string) $t->name, 'kind' => $t->kind ?? null])->values();
@endphp
<style>
.tte-mask { position: fixed; inset: 0; z-index: 90; background: rgba(15, 35, 55, .45); backdrop-filter: blur(3px); }
.tte { position: fixed; z-index: 91; left: 50%; top: 50%; display: flex; flex-direction: column; width: min(640px, calc(100vw - 24px)); max-height: min(720px, calc(100vh - 24px)); overflow: hidden; border-radius: 24px; background: #fff; box-shadow: 0 30px 80px rgba(15, 35, 55, .3); transform: translate(-50%, -50%); animation: ttePop .18s ease-out; }
@keyframes ttePop { from { opacity: 0; transform: translate(-50%, -46%) scale(.98); } to { opacity: 1; transform: translate(-50%, -50%); } }
.tte-hd { display: flex; align-items: flex-start; gap: 12px; padding: 18px 20px 14px; border-bottom: 1px solid #EAF1F5; }
.tte-hd__ic { display: grid; place-items: center; flex: 0 0 42px; width: 42px; height: 42px; border-radius: 13px; background: linear-gradient(135deg, #E3F4F8, #D2ECF3); color: #126F91; }
.tte-hd__ic svg { width: 20px; height: 20px; }
.tte-hd h2 { font-size: 16px; font-weight: 800; color: #123B68; }
.tte-hd p { margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12.5px; color: #607A90; }
.tte-hd p code { margin-left: 4px; padding: 1px 6px; border-radius: 6px; background: #F1F6F9; font-size: 11px; color: #365B7A; }
.tte-x { margin-left: auto; display: grid; place-items: center; flex: 0 0 34px; width: 34px; height: 34px; border-radius: 10px; color: #607A90; }
.tte-x:hover { background: #F1F6F9; color: #123B68; }
.tte-x svg { width: 18px; height: 18px; }
.tte-bd { flex: 1; min-height: 0; overflow-y: auto; padding: 16px 20px; display: grid; gap: 16px; align-content: start; }
.tte-lbl { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 8px; font-size: 11px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #6B8295; }
.tte-lbl small { font-size: 11px; font-weight: 600; letter-spacing: 0; text-transform: none; color: #94A3B8; }
.tte-sel { display: grid; gap: 6px; }
.tte-row { display: flex; align-items: center; gap: 10px; padding: 8px 10px 8px 12px; border: 1px solid #E3ECF4; border-radius: 13px; background: #fff; transition: border-color .12s, background .12s; }
.tte-row.is-primary { border-color: #2D7FA3; background: #F0F9FC; box-shadow: 0 0 0 3px #DDF1F6; }
.tte-row__name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 13px; font-weight: 700; color: #123B68; }
.tte-row__kind { flex: 0 0 auto; padding: 1px 7px; border-radius: 999px; font-size: 10px; font-weight: 700; }
.tte-row__kind--coding { background: #E0F2FE; color: #0369A1; }
.tte-row__kind--general { background: #ECFDF5; color: #047857; }
.tte-star { display: inline-flex; align-items: center; gap: 5px; flex: 0 0 auto; padding: 5px 10px; border: 1px solid #E3ECF4; border-radius: 999px; background: #fff; font-size: 11.5px; font-weight: 700; color: #607A90; cursor: pointer; transition: all .12s; }
.tte-star svg { width: 13px; height: 13px; }
.tte-star:hover { border-color: #F59E0B; color: #B45309; }
.tte-star.is-on { border-color: #F59E0B; background: #FFFBEB; color: #B45309; cursor: default; }
.tte-star.is-on svg { fill: #F59E0B; }
.tte-icon-btn { display: grid; place-items: center; flex: 0 0 28px; width: 28px; height: 28px; border-radius: 8px; color: #94A3B8; }
.tte-icon-btn:hover:not(:disabled) { background: #F1F6F9; color: #123B68; }
.tte-icon-btn:disabled { opacity: .3; cursor: default; }
.tte-icon-btn--del:hover:not(:disabled) { background: #FFF1F2; color: #E11D48; }
.tte-icon-btn svg { width: 15px; height: 15px; }
.tte-empty { padding: 14px; border: 1px dashed #D6E3EF; border-radius: 13px; text-align: center; font-size: 12.5px; color: #94A3B8; }
.tte-preview { display: flex; align-items: flex-start; gap: 14px; padding: 12px 14px; border-radius: 14px; background: #F8FBFC; }
.tte-preview > span { flex: 0 0 auto; padding-top: 3px; font-size: 11px; font-weight: 700; color: #6B8295; }
.tte-search { display: flex; align-items: center; gap: 8px; height: 40px; padding: 0 12px; border: 1px solid #D6E3EF; border-radius: 12px; background: #fff; transition: border-color .12s, box-shadow .12s; }
.tte-search:focus-within { border-color: #2D7FA3; box-shadow: 0 0 0 3px #DDF1F6; }
.tte-search svg { width: 16px; height: 16px; color: #94A3B8; }
.tte-search input { flex: 1; min-width: 0; height: 100%; border: 0; outline: none; box-shadow: none; padding: 0; background: transparent; font-size: 13px; color: #123B68; }
.tte-kinds { display: flex; gap: 6px; margin: 10px 0 8px; }
.tte-kinds button { padding: 5px 11px; border: 1px solid #E3ECF4; border-radius: 999px; background: #fff; font-size: 11.5px; font-weight: 700; color: #607A90; }
.tte-kinds button.is-on { border-color: #123B68; background: #123B68; color: #fff; }
.tte-pool { display: flex; flex-wrap: wrap; gap: 6px; max-height: 210px; overflow-y: auto; padding: 2px; }
.tte-chip { display: inline-flex; align-items: center; gap: 5px; padding: 5px 10px; border: 1px solid #D6E3EF; border-radius: 9px; background: #fff; font-size: 12px; font-weight: 600; color: #365B7A; cursor: pointer; transition: all .12s; }
.tte-chip svg { width: 12px; height: 12px; }
.tte-chip:hover { border-color: #2D7FA3; background: #F0F9FC; color: #126F91; }
.tte-chip.is-on { border-color: #2D7FA3; background: #2D7FA3; color: #fff; }
.tte-pool__note { width: 100%; padding: 10px; text-align: center; font-size: 12px; color: #94A3B8; }
.tte-err { padding: 9px 12px; border-radius: 11px; background: #FFF1F2; color: #BE123C; font-size: 12.5px; font-weight: 600; }
.tte-ft { display: flex; align-items: center; gap: 10px; padding: 14px 20px; border-top: 1px solid #EAF1F5; background: #F8FBFC; }
.tte-ft__info { flex: 1; font-size: 12px; color: #607A90; }
.tte-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-height: 42px; padding: 0 18px; border-radius: 12px; font-size: 13px; font-weight: 800; transition: all .12s; }
.tte-btn svg { width: 15px; height: 15px; }
.tte-btn--ghost { border: 1px solid #D6E3EF; background: #fff; color: #365B7A; }
.tte-btn--ghost:hover { background: #F1F6F9; }
.tte-btn--primary { background: #237052; color: #fff; box-shadow: 0 6px 16px rgba(35, 112, 82, .25); }
.tte-btn--primary:hover { background: #1C5C43; }
.tte-btn:disabled { opacity: .55; cursor: not-allowed; box-shadow: none; }
/* Nút mở popup trên từng bài */
.oi-tagedit-btn { display: inline-flex; align-items: center; gap: 4px; padding: 1px 8px; border: 1px dashed #B9CFE0; border-radius: 7px; background: #fff; font-size: 11px; font-weight: 700; line-height: 1.6; color: #2D7FA3; cursor: pointer; transition: all .12s; }
.oi-tagedit-btn svg { width: 11px; height: 11px; }
.oi-tagedit-btn:hover { border-style: solid; border-color: #2D7FA3; background: #F0F9FC; }
@media (max-width: 560px) { .tte-row { flex-wrap: wrap; } .tte-star span { display: none; } }
</style>

<script>
    window.onthiTopicTagEditor = function (cfg) {
        const plain = (s) => String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd');
        const allTags = (cfg.tags || []).map((t) => ({ ...t, key: plain(t.name) }));
        const byId = Object.fromEntries(allTags.map((t) => [t.id, t]));
        return {
            open: false, q: '', kind: 'all', item: null, selected: [], primary: null, busy: false, error: '',
            show(d) {
                this.item = d; this.q = ''; this.kind = 'all'; this.error = ''; this.busy = false;
                this.selected = (d.tags || []).map((t) => Number(t.id)).filter((id) => byId[id]);
                this.primary = this.selected[0] ?? null;
                this.open = true;
                document.documentElement.style.overflow = 'hidden';
                this.$nextTick(() => this.$refs.search && this.$refs.search.focus());
            },
            close() { if (this.busy) return; this.open = false; document.documentElement.style.overflow = ''; },
            tag(id) { return byId[id] || { id, name: '#' + id, kind: null }; },
            has(id) { return this.selected.includes(id); },
            toggle(id) {
                if (this.has(id)) return this.remove(id);
                this.selected.push(id);
                if (this.primary === null) this.primary = id;
            },
            remove(id) {
                this.selected = this.selected.filter((x) => x !== id);
                if (this.primary === id) this.primary = this.selected[0] ?? null;
            },
            move(id, d) {
                const i = this.selected.indexOf(id), j = i + d;
                if (i < 0 || j < 0 || j >= this.selected.length) return;
                const a = [...this.selected]; [a[i], a[j]] = [a[j], a[i]]; this.selected = a;
            },
            get ordered() { return this.primary === null ? this.selected : [this.primary, ...this.selected.filter((x) => x !== this.primary)]; },
            get pool() {
                const q = plain(this.q.trim());
                return allTags.filter((t) => (this.kind === 'all' || (this.kind === 'none' ? !t.kind : t.kind === this.kind)) && (!q || t.key.includes(q))).slice(0, 200);
            },
            addFirstMatch() { const t = this.pool.find((x) => !this.has(x.id)); if (t) { this.toggle(t.id); this.q = ''; } },
            async save() {
                if (!this.item || this.busy) return;
                this.busy = true; this.error = '';
                try {
                    const res = await fetch(cfg.url.replace('__ID__', this.item.id), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({ tag_ids: this.ordered, primary_id: this.primary }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.ok) {
                        this.error = res.status === 419 ? 'Phiên đăng nhập đã hết hạn — tải lại trang rồi thử lại.'
                            : res.status === 403 ? 'Bạn không có quyền sửa chuyên đề.'
                            : (data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Không lưu được, thử lại sau.'));
                        this.busy = false;
                        return;
                    }
                    // Danh sách bài dựng sẵn từ máy chủ → tải lại (giữ nguyên vị trí cuộn) để chip + bộ lọc chuyên đề cập nhật đúng.
                    try { sessionStorage.setItem('oiTagEditToast', data.message || 'Đã lưu chuyên đề.'); sessionStorage.setItem('oiTagEditScroll', String(window.scrollY)); } catch (e) {}
                    location.reload();
                } catch (e) {
                    this.error = 'Mất kết nối tới máy chủ — thử lại sau.';
                    this.busy = false;
                }
            },
        };
    };
    // Sau khi lưu xong và tải lại: trả lại vị trí cuộn + báo đã lưu.
    (function () {
        try {
            const msg = sessionStorage.getItem('oiTagEditToast'), y = sessionStorage.getItem('oiTagEditScroll');
            if (msg === null) return;
            sessionStorage.removeItem('oiTagEditToast'); sessionStorage.removeItem('oiTagEditScroll');
            window.addEventListener('load', function () {
                if (y) window.scrollTo(0, Number(y));
                setTimeout(function () { if (window.toast) window.toast('success', msg); }, 300);
            });
        } catch (e) {}
    })();
</script>

<div x-data="onthiTopicTagEditor({{ Js::from([
        'tags' => $tteTags,
        'url' => route('admin.content.questions.topic-tags', ['question' => '__ID__']),
        'csrf' => csrf_token(),
    ]) }})" @topic-tag-edit.window="show($event.detail)" @keydown.escape.window="open && close()">
    <div class="tte-mask" x-show="open" x-cloak x-transition.opacity @click="close()"></div>
    <template x-if="open && item">
        <div class="tte" role="dialog" aria-modal="true" aria-labelledby="tteTitle">
            <div class="tte-hd">
                <span class="tte-hd__ic"><x-lucide name="tag" /></span>
                <div style="min-width:0">
                    <h2 id="tteTitle">Sửa chuyên đề</h2>
                    <p><span x-text="item.title"></span><code x-text="item.code" x-show="item.code"></code></p>
                </div>
                <button type="button" class="tte-x" @click="close()" aria-label="Đóng"><x-lucide name="x" /></button>
            </div>

            <div class="tte-bd">
                <div class="tte-err" x-show="error" x-text="error"></div>

                {{-- Đã chọn + tag chính --}}
                <section>
                    <div class="tte-lbl">Đã chọn <small x-text="selected.length ? selected.length + ' chuyên đề · bấm ★ để đặt tag chính' : ''"></small></div>
                    <div class="tte-sel">
                        <template x-for="(id, i) in ordered" :key="id">
                            <div class="tte-row" :class="primary === id && 'is-primary'">
                                <span class="tte-row__name" x-text="tag(id).name"></span>
                                <span class="tte-row__kind" :class="'tte-row__kind--' + tag(id).kind" x-show="tag(id).kind" x-text="tag(id).kind === 'coding' ? 'Lập trình' : 'Phổ thông'"></span>
                                <button type="button" class="tte-star" :class="primary === id && 'is-on'" @click="primary = id" :aria-pressed="primary === id">
                                    <x-lucide name="star" /><span x-text="primary === id ? 'Tag chính' : 'Đặt làm chính'"></span>
                                </button>
                                <button type="button" class="tte-icon-btn" @click="move(id, -1)" :disabled="primary === id || i <= 1" aria-label="Lên"><x-lucide name="chevron-up" /></button>
                                <button type="button" class="tte-icon-btn" @click="move(id, 1)" :disabled="primary === id || i === ordered.length - 1" aria-label="Xuống"><x-lucide name="chevron-down" /></button>
                                <button type="button" class="tte-icon-btn tte-icon-btn--del" @click="remove(id)" aria-label="Bỏ"><x-lucide name="x" /></button>
                            </div>
                        </template>
                        <div class="tte-empty" x-show="!selected.length">Chưa có chuyên đề nào — chọn ở danh sách bên dưới.</div>
                    </div>
                </section>

                {{-- Xem trước đúng như ngoài danh sách --}}
                <div class="tte-preview" x-show="selected.length">
                    <span>Xem trước</span>
                    <div class="oi-topics">
                        <span class="oi-topic-chip rounded-lg border border-[#D6E3EF] bg-[#EEF4FA] px-2 py-0.5 text-[12px] font-semibold text-[#365B7A]" x-text="tag(ordered[0]).name"></span>
                        <span class="oi-topic-subs" x-show="ordered.length > 1">
                            <template x-for="id in ordered.slice(1, 3)" :key="'pv' + id"><span class="oi-topic-sub" x-text="tag(id).name"></span></template>
                            <span class="oi-topic-more" x-show="ordered.length > 3" x-text="'+' + (ordered.length - 3)"></span>
                        </span>
                    </div>
                </div>

                {{-- Kho tag --}}
                <section>
                    <div class="tte-lbl">Thêm chuyên đề <small>{{ count($tteTags) }} tag trong hệ thống</small></div>
                    <label class="tte-search">
                        <x-lucide name="search" />
                        <input type="text" x-ref="search" x-model="q" placeholder="Tìm tag… (gõ không dấu cũng được, Enter để thêm)" @keydown.enter.prevent="addFirstMatch()">
                    </label>
                    <div class="tte-kinds">
                        <button type="button" :class="kind === 'all' && 'is-on'" @click="kind = 'all'">Tất cả</button>
                        <button type="button" :class="kind === 'coding' && 'is-on'" @click="kind = 'coding'">Lập trình</button>
                        <button type="button" :class="kind === 'general' && 'is-on'" @click="kind = 'general'">Phổ thông</button>
                        <button type="button" :class="kind === 'none' && 'is-on'" @click="kind = 'none'">Chưa phân loại</button>
                    </div>
                    <div class="tte-pool">
                        <template x-for="t in pool" :key="'pool' + t.id">
                            <button type="button" class="tte-chip" :class="has(t.id) && 'is-on'" @click="toggle(t.id)" :aria-pressed="has(t.id)">
                                <x-lucide name="check" x-show="has(t.id)" /><x-lucide name="plus" x-show="!has(t.id)" /><span x-text="t.name"></span>
                            </button>
                        </template>
                        <p class="tte-pool__note" x-show="!pool.length">Không có tag phù hợp — thêm tag mới ở Quản trị › Ngân hàng nội dung › Tag/Chuyên đề.</p>
                    </div>
                </section>
            </div>

            <div class="tte-ft">
                <span class="tte-ft__info" x-text="primary !== null ? 'Tag chính: ' + tag(primary).name : 'Chưa có tag chính'"></span>
                <button type="button" class="tte-btn tte-btn--ghost" @click="close()" :disabled="busy">Huỷ</button>
                <button type="button" class="tte-btn tte-btn--primary" @click="save()" :disabled="busy"><x-lucide name="check" /><span x-text="busy ? 'Đang lưu…' : 'Lưu chuyên đề'"></span></button>
            </div>
        </div>
    </template>
</div>
