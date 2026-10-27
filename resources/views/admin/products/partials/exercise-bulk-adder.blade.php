{{-- SỬA 27/10 (khách: "up bài tập hàng loạt — 1 file ZIP chứa 96 ZIP bài; bài đã có thì báo không ghi đè, bắt xoá file ZIP đó rồi up lại";
     lần 2: "chỉ cần up form này, nó tự đọc hết các file zip bên trong để up luôn") — khối nhập DUY NHẤT của Bài tập đính kèm:
     thả 1 ZIP bài, nhiều ZIP bài, hoặc 1 ZIP chứa cả chương → bấm Nhập bài tập là tự kiểm & nhập hết (1 bài thì mở luôn màn Sửa). Chọn nhiều ZIP bài và/hoặc 1 ZIP chứa nhiều ZIP bài → máy chủ kiểm hết trước
     (định dạng + trùng mã) → không lỗi mới cho nhập, nhập lần lượt từng bài có thanh tiến độ.
     Máy chủ: Admin\ProductExerciseBulkController + Services\Admin\ProductExerciseBulkImportService.
     Tham số: $product, $chapters, $chapterLabel. --}}
@php
    $bkChapterLower = $chapterLabel ? mb_strtolower($chapterLabel) : null;
    $bkMaxFiles = max(1, (int) ini_get('max_file_uploads') ?: 20);
    $bkCfg = [
        'prepareUrl' => route('admin.products.exercises.bulk.prepare', $product->id),
        'importUrl' => route('admin.products.exercises.bulk.import', [$product->id, 'TOKEN32TOKEN32TOKEN32TOKEN32TOKE', 999999]),
        'finishUrl' => route('admin.products.exercises.bulk.finish', [$product->id, 'TOKEN32TOKEN32TOKEN32TOKEN32TOKE']),
        'csrf' => csrf_token(),
        'chapter' => (string) ($exDefaultChapterId ?? ''), // SỬA 27/10 — chương mặc định (chương 1), xem admin/products/show
        'maxFiles' => $bkMaxFiles,
    ];
@endphp

@once
<style>
    .oi-bk-list { max-height: 320px; overflow-y: auto; margin: 0; padding: 0; list-style: none; border: 1px solid #E2ECF3; border-radius: 12px; background: #fff; }
    .oi-bk-list li { display: flex; align-items: flex-start; gap: 10px; padding: 8px 12px; border-top: 1px solid #EEF3F7; font-size: 12.5px; color: #334E68; }
    .oi-bk-list li:first-child { border-top: 0; }
    .oi-bk-list code { font-weight: 700; color: #123B68; }
    .oi-bk-list small { display: block; color: #7A92A3; }
    .oi-bk-list .is-bad code { color: #BE123C; }
    .oi-bk-st { flex: none; min-width: 74px; font-weight: 700; font-size: 11.5px; }
    .oi-bk-st.ok { color: #047857; } .oi-bk-st.bad { color: #BE123C; } .oi-bk-st.run { color: #2563EB; } .oi-bk-st.wait { color: #94A3B8; }
    .oi-bk-box { padding: 12px 14px; border-radius: 12px; font-size: 13px; line-height: 1.55; }
    .oi-bk-box.is-bad { border: 1px solid #FBCFE8; background: #FFF1F2; color: #9F1239; }
    .oi-bk-box.is-ok { border: 1px solid #A7F3D0; background: #ECFDF5; color: #065F46; }
    .oi-bk-bar { height: 8px; overflow: hidden; border-radius: 999px; background: #E2ECF3; }
    .oi-bk-bar i { display: block; height: 100%; border-radius: 999px; background: #2563EB; transition: width .2s; }
</style>
<script>
    function exerciseBulk(cfg) {
        return {
            chapter: cfg.chapter || '', files: [], drag: false, phase: 'pick', // pick | checking | blocked | ready | importing | done
            message: '', duplicates: [], invalid: [], items: [], token: '', doneCount: 0, failCount: 0,
            get sizeLabel() { return (this.files.reduce((s, f) => s + f.size, 0) / 1048576).toFixed(1) + ' MB'; },
            get percent() { return this.items.length ? Math.round((this.doneCount + this.failCount) / this.items.length * 100) : 0; },
            pick(e) { this.files = Array.from(e.target.files || []).filter((f) => /\.zip$/i.test(f.name)); this.reset(); },
            clear() { this.$refs.files.value = ''; this.files = []; this.reset(); },
            reset() { this.phase = 'pick'; this.message = ''; this.duplicates = []; this.invalid = []; this.items = []; this.token = ''; this.doneCount = 0; this.failCount = 0; },
            url(tpl, index) { return tpl.replace('TOKEN32TOKEN32TOKEN32TOKEN32TOKE', this.token).replace('999999', String(index)); },
            async check() {
                if (!this.files.length) return;
                if (this.files.length > cfg.maxFiles) {
                    this.message = 'Máy chủ chỉ nhận tối đa ' + cfg.maxFiles + ' tệp mỗi lần tải. Hãy nén các ZIP bài vào 1 tệp ZIP (như Chuong-01.zip) rồi chọn tệp đó.';
                    this.phase = 'blocked'; return;
                }
                this.phase = 'checking'; this.message = '';
                const fd = new FormData();
                this.files.forEach((f) => fd.append('files[]', f));
                if (this.chapter) fd.append('material_id', this.chapter);
                try {
                    const res = await fetch(cfg.prepareUrl, { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': cfg.csrf, 'Accept': 'application/json' } });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        this.message = res.status === 413 ? 'Tệp quá lớn so với giới hạn tải lên của máy chủ — chia nhỏ gói rồi thử lại.'
                            : (data.errors ? Object.values(data.errors).flat()[0] : (data.message || 'Không kiểm được gói (mã lỗi ' + res.status + ').'));
                        this.phase = 'blocked'; return;
                    }
                    if (!data.ok) {
                        this.duplicates = data.duplicates || []; this.invalid = data.invalid || [];
                        this.message = (this.duplicates.length ? this.duplicates.length + ' bài đã có / trùng mã — hệ thống KHÔNG ghi đè. ' : '')
                            + (this.invalid.length ? this.invalid.length + ' gói lỗi. ' : '')
                            + 'Chưa nhập bài nào. Xoá các file ZIP dưới đây khỏi lựa chọn / khỏi gói rồi tải lại.';
                        this.phase = 'blocked'; return;
                    }
                    this.token = data.token;
                    this.items = (data.items || []).map((i) => Object.assign({ state: 'wait', note: '' }, i));
                    this.phase = 'ready';
                } catch (e) {
                    this.message = 'Mất kết nối khi tải lên — kiểm tra mạng rồi thử lại.'; this.phase = 'blocked';
                }
            },
            // Bấm "Nhập bài tập": kiểm cả gói, không có gì sai thì nhập luôn.
            async start() { await this.check(); if (this.phase === 'ready') await this.run(); },
            async run() {
                this.phase = 'importing'; this.doneCount = 0; this.failCount = 0;
                for (let i = 0; i < this.items.length; i++) {
                    const it = this.items[i]; it.state = 'run';
                    try {
                                        const res = await fetch(this.url(cfg.importUrl, i), { method: 'POST', headers: { 'X-CSRF-TOKEN': cfg.csrf, 'Accept': 'application/json' } });
                        const data = await res.json().catch(() => ({}));
                        if (res.ok && data.ok) { it.state = 'ok'; it.note = data.code; it.editUrl = data.editUrl; this.doneCount++; }
                        else { it.state = 'bad'; it.note = data.message || ('Lỗi ' + res.status); this.failCount++; }
                    } catch (e) { it.state = 'bad'; it.note = 'Mất kết nối'; this.failCount++; }
                }
                fetch(this.url(cfg.finishUrl, 0), { method: 'POST', headers: { 'X-CSRF-TOKEN': cfg.csrf, 'Accept': 'application/json' } }).catch(() => {});
                this.phase = 'done';
                // 1 bài: mở luôn màn Sửa để rà lại (như nhập từng bài trước đây). Nhiều bài, không lỗi: tải lại trang.
                if (this.items.length === 1 && this.doneCount === 1 && this.items[0].editUrl) { window.location.href = this.items[0].editUrl; }
                else if (this.failCount === 0) { setTimeout(() => window.location.reload(), 1500); }
            },
        };
    }
</script>
@endonce

<div class="oi-add oi-zip" x-data="exerciseBulk(@js($bkCfg))" @ex-chapter.window="if (phase !== 'importing') chapter = String($event.detail ?? '')">
    @if ($chapterLabel)
        <div>
            <label class="oi-add__lbl" for="bk-chapter">1. Thuộc {{ $bkChapterLower }} <small>(áp cho mọi bài trong lần nhập này)</small></label>
            <select id="bk-chapter" x-model="chapter" @change="$dispatch('ex-chapter', chapter)" class="oi-add__input" style="max-width: 460px" :disabled="phase === 'importing'">
                <option value="">— Chưa gắn {{ $bkChapterLower }} —</option>
                @foreach ($chapters as $c)
                    <option value="{{ $c['id'] }}">{{ $c['title'] }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div>
        <span class="oi-add__lbl">{{ $chapterLabel ? '2.' : '1.' }} Gói ZIP bài tập</span>
        <label class="oi-drop oi-drop--big oi-zip__drop" :class="{ 'has-file': files.length, 'is-drag': drag }"
               @dragover="drag = true" @dragleave="drag = false" @drop="drag = false">
            <span class="oi-zip__ic"><x-lucide name="package" /></span>
            <span class="oi-zip__txt">
                <b x-text="files.length ? (files.length === 1 ? files[0].name : files.length + ' tệp đã chọn') + ' · ' + sizeLabel : 'Kéo thả file ZIP vào đây, hoặc bấm để chọn'">Kéo thả file ZIP vào đây, hoặc bấm để chọn</b>
                <small x-show="! files.length">1 ZIP bài, nhiều ZIP bài, hoặc <b>1 ZIP chứa cả chương</b> (vd <code>Chuong-01.zip</code> có T1B001.zip … T1B096.zip) — hệ thống tự đọc hết các ZIP bên trong. Mã bài = tên file ZIP.</small>
                <small x-show="files.length" x-cloak x-text="files.map((f) => f.name).slice(0, 6).join(', ') + (files.length > 6 ? ' …' : '')"></small>
            </span>
            <input type="file" multiple accept=".zip,application/zip" x-ref="files" @change="pick($event)" :disabled="phase === 'checking' || phase === 'importing'">
        </label>
        <div class="oi-types" aria-label="Các dạng hệ thống tự nhận">
            <span class="oi-types__lbl"><x-lucide name="sparkles" /> Tự nhận dạng:</span>
            @foreach ([['Trắc nghiệm', 'single_choice', 'list-checks'], ['Đúng / Sai', 'true_false', 'check-circle-2'], ['Điền từ', 'short_answer', 'text-cursor-input'], ['Lập trình', 'programming', 'code-2']] as [$tLabel, $tCode, $tIcon])
                <span class="oi-type" title='content.type = "{{ $tCode }}"'><x-lucide name="{{ $tIcon }}" />{{ $tLabel }}</span>
            @endforeach
        </div>
        <p class="oi-add__note">Bài nào <b>đã có</b> (trùng mã bài) thì hệ thống <b>không ghi đè</b> và không nhập gì cả — sẽ báo đúng file ZIP cần xoá khỏi gói để tải lại.</p>
    </div>

    {{-- Kết quả kiểm tra: bị chặn --}}
    <div x-show="phase === 'blocked'" x-cloak class="oi-bk-box is-bad" role="alert">
        <b x-text="message"></b>
    </div>
    <ul x-show="phase === 'blocked' && (duplicates.length || invalid.length)" x-cloak class="oi-bk-list" aria-label="Các file cần xoá khỏi gói">
        <template x-for="(d, i) in duplicates" :key="'d' + i">
            <li class="is-bad"><span class="oi-bk-st bad">Đã có</span><span><code x-text="d.file"></code><small x-text="'Mã ' + d.code + ' · ' + d.where + (d.from ? ' · trong gói ' + d.from : '')"></small></span></li>
        </template>
        <template x-for="(d, i) in invalid" :key="'v' + i">
            <li class="is-bad"><span class="oi-bk-st bad">Lỗi gói</span><span><code x-text="d.file"></code><small x-text="d.message + (d.from ? ' · trong gói ' + d.from : '')"></small></span></li>
        </template>
    </ul>

    {{-- Sẵn sàng / đang nhập / xong --}}
    <div x-show="phase === 'ready'" x-cloak class="oi-bk-box is-ok"><b x-text="'Đã kiểm xong: ' + items.length + ' bài hợp lệ, không trùng bài nào. Bấm “Nhập ' + items.length + ' bài” để bắt đầu.'"></b></div>
    <div x-show="phase === 'importing' || phase === 'done'" x-cloak>
        <div class="oi-bk-bar" aria-hidden="true"><i :style="'width:' + percent + '%'"></i></div>
        <p class="oi-add__note" role="status" x-text="phase === 'done'
            ? ('Xong: đã nhập ' + doneCount + ' bài' + (failCount ? ', ' + failCount + ' bài lỗi (xem danh sách)' : '') + '. Tải lại trang để xem danh sách bài tập.')
            : ('Đang nhập ' + (doneCount + failCount + 1) + ' / ' + items.length + ' … đừng đóng trang.')"></p>
    </div>
    <ul x-show="items.length" x-cloak class="oi-bk-list" aria-label="Danh sách bài sẽ nhập">
        <template x-for="(it, i) in items" :key="'i' + i">
            <li :class="it.state === 'bad' ? 'is-bad' : ''">
                <span class="oi-bk-st" :class="it.state" x-text="{ wait: 'Chờ nhập', run: 'Đang nhập…', ok: 'Đã nhập', bad: 'Lỗi' }[it.state]"></span>
                <span><code x-text="it.code"></code> · <span x-text="it.title"></span>
                    <small x-text="it.state === 'bad' ? it.note : (it.typeLabel + (it.tests ? ' · ' + it.tests + ' test' : '') + (it.from ? ' · từ ' + it.from : ''))"></small></span>
            </li>
        </template>
    </ul>

    <div class="oi-add__actions">
        <button type="button" class="acx-btn acx-btn--primary acx-btn--sm" x-show="phase === 'pick' || phase === 'checking' || phase === 'blocked'"
                :disabled="! files.length || phase === 'checking'" @click="start()">
            <x-lucide name="upload" /> <span x-text="phase === 'checking' ? 'Đang tải lên & kiểm tra…' : 'Nhập bài tập'">Nhập bài tập</span>
        </button>
        <button type="button" class="acx-btn acx-btn--primary acx-btn--sm" x-show="phase === 'ready'" x-cloak @click="run()">
            <x-lucide name="upload" /> <span x-text="'Nhập ' + items.length + ' bài'"></span>
        </button>
        <a href="{{ asset('templates/mau-cau-hoi.zip') }}" download="MAU-CAU-HOI.zip" class="oi-zip__tpl"><x-lucide name="download" /> Tải file mẫu</a>
        <button type="button" class="acx-btn acx-btn--primary acx-btn--sm" x-show="phase === 'done'" x-cloak @click="window.location.reload()">Tải lại trang</button>
        <button type="button" class="acx-btn acx-btn--sm" x-show="files.length && phase !== 'checking' && phase !== 'importing'" x-cloak @click="clear()">Chọn file khác</button>
        <span class="oi-add__note" style="margin:0">Kiểm trùng xong là nhập luôn (bài lưu ở trạng thái Phát hành, vẫn bấm Sửa từng bài để rà lại). 1 bài thì mở ngay màn Sửa. Mỗi lần tối đa {{ \App\Services\Admin\ProductExerciseBulkImportService::MAX_ITEMS }} bài.</span>
    </div>
</div>
