{{-- SỬA 9/10 (khách: "chỉ thêm chỗ nhập bộ test. Khi nhập bộ test xong nó sẽ ra danh sách bộ test
     .in .out") — khối "Bộ test" cho câu Lập trình ở khu Admin (màn Tạo + màn Sửa dùng chung),
     dựng theo ô "Bộ test — Ghép theo tên: bai1.in + bai1.out" của source mới (ZipProgrammingEditor).

     CÁCH HOẠT ĐỘNG (toàn bộ ở trình duyệt, KHÔNG đụng máy chấm):
       1. Chọn / kéo thả nhiều tệp .in/.inp + .out/.ans -> ghép theo tên gốc -> hiện danh sách.
       2. Lúc bấm Lưu, danh sách được đóng thành 1 ô ẩn `test_cases_json` = [{input, output}, …].
       3. ContentController::testCasesFromJson() đổi ô đó thành 'test_cases_parsed' — ĐÚNG đường
          mà nhập gói ZIP đang dùng — nên grading_config.test_cases có đúng dạng cũ {input, output},
          giữ nguyên xuống dòng, và CodeJudgingService / PracticeByQuestionService không phải sửa.

     Biến truyền vào:
       · $qeInitialTests  mảng [{input, output}] đang có (form Sửa: test đã lưu; form Tạo: [])
       · $qeMode          'create' | 'edit'
     Form Sửa chỉ gửi `test_cases_json` KHI người dùng có đổi bộ test; không đổi thì không gửi gì
     và server GIỮ NGUYÊN test cũ (xem buildGradingConfig nhánh '_existing_test_cases'). --}}
@php
    $qeMode = $qeMode ?? 'create';
    $qeInitialTests = collect($qeInitialTests ?? [])->map(fn ($c) => [
        'input' => (string) ($c['input'] ?? ''),
        'output' => (string) ($c['output'] ?? ''),
    ])->values()->all();
    $qeDirty = false;

    // Lần gửi trước bị báo lỗi (thiếu mã câu…) -> dựng lại ĐÚNG bộ test người dùng vừa nhập.
    $qeOld = old('test_cases_json');
    if (is_string($qeOld) && $qeOld !== '') {
        $qeDecoded = json_decode($qeOld, true);
        if (is_array($qeDecoded)) {
            $qeInitialTests = collect($qeDecoded)->map(fn ($c) => [
                'input' => (string) ($c['input'] ?? ''),
                'output' => (string) ($c['output'] ?? ''),
            ])->values()->all();
            $qeDirty = true;
        }
    }

    $qeCfg = ['initial' => $qeInitialTests, 'dirty' => $qeDirty, 'always' => $qeMode === 'create', 'mode' => $qeMode];
@endphp

@verbatim
<script>
    window.qeTestSet = function (cfg) {
        const IN_EXT = ['in', 'inp', 'input'];
        const OUT_EXT = ['out', 'ans', 'output', 'sol'];
        const MAX_TOTAL = 5 * 1024 * 1024; // ~5 MB chữ: dưới giới hạn post_max_size mặc định của PHP
        const BIG = 200000;
        const SKIP_NAMES = ['info', '.ds_store', 'thumbs.db'];

        // Đọc tệp .zip ngay trong trình duyệt (không cần thư viện): đọc danh mục ở cuối tệp, giải nén
        // từng mục khi cần (kiểu lưu trữ "stored" hoặc "deflate"). Trả về các mục giống đối tượng File
        // (name, size, text()) để dùng chung đường ghép cặp .in/.out ở importFiles().
        const readZip = async (file) => {
            const buf = await file.arrayBuffer();
            const dv = new DataView(buf);
            const u8 = new Uint8Array(buf);
            let eocd = -1;
            for (let i = buf.byteLength - 22; i >= Math.max(0, buf.byteLength - 65557); i--) {
                if (dv.getUint32(i, true) === 0x06054b50) { eocd = i; break; }
            }
            if (eocd < 0) throw new Error('không phải tệp ZIP hợp lệ');
            const total = dv.getUint16(eocd + 10, true);
            let p = dv.getUint32(eocd + 16, true);
            const out = [];
            for (let k = 0; k < total; k++) {
                if (p + 46 > buf.byteLength || dv.getUint32(p, true) !== 0x02014b50) throw new Error('danh mục ZIP bị hỏng');
                const flags = dv.getUint16(p + 8, true);
                const method = dv.getUint16(p + 10, true);
                const csize = dv.getUint32(p + 20, true);
                const usize = dv.getUint32(p + 24, true);
                const nlen = dv.getUint16(p + 28, true);
                const xlen = dv.getUint16(p + 30, true);
                const clen = dv.getUint16(p + 32, true);
                const lho = dv.getUint32(p + 42, true);
                const full = new TextDecoder('utf-8').decode(u8.subarray(p + 46, p + 46 + nlen));
                p += 46 + nlen + xlen + clen;
                if (full.endsWith('/') || full.split('/').includes('__MACOSX')) continue;
                const name = full.split('/').pop();
                if (!name || SKIP_NAMES.includes(name.toLowerCase())) continue;
                if (flags & 1) throw new Error('ZIP đặt mật khẩu, không đọc được');
                if (csize === 0xffffffff || usize === 0xffffffff) throw new Error('ZIP64 quá lớn, không hỗ trợ');
                out.push({
                    name,
                    size: usize,
                    async text() {
                        const lnlen = dv.getUint16(lho + 26, true);
                        const lxlen = dv.getUint16(lho + 28, true);
                        const start = lho + 30 + lnlen + lxlen;
                        const data = u8.subarray(start, start + csize);
                        if (method === 0) return new TextDecoder('utf-8').decode(data);
                        if (method !== 8) throw new Error('kiểu nén ZIP không hỗ trợ (' + method + ')');
                        if (typeof DecompressionStream === 'undefined') throw new Error('trình duyệt quá cũ để giải nén ZIP');
                        const stream = new Blob([data]).stream().pipeThrough(new DecompressionStream('deflate-raw'));
                        return new TextDecoder('utf-8').decode(await new Response(stream).arrayBuffer());
                    },
                });
            }
            return out;
        };

        return {
            tests: [],
            dirty: false,
            report: null,
            drag: false,
            openId: null,
            confirmClear: false,
            mode: 'replace',
            seq: 0,
            busy: false,
            init() {
                this.tests = (cfg.initial || []).map((t) => this.make(t.input, t.output, '', '', ''));
                this.dirty = !!cfg.dirty;
                const form = this.$root.closest('form');
                if (form) form.addEventListener('submit', () => this.sync());
            },
            make(input, output, inName, outName, key) {
                input = String(input ?? '');
                output = String(output ?? '');
                return { id: ++this.seq, key: key || '', inName: inName || '', outName: outName || '', input, output, big: input.length + output.length > BIG };
            },
            pad(i) { return String(i + 1).padStart(2, '0'); },
            inLabel(t, i) { return t.inName || ('test' + this.pad(i) + '.in'); },
            outLabel(t, i) { return t.outName || ('test' + this.pad(i) + '.out'); },
            size(t) {
                const f = (n) => n < 1024 ? n + ' B' : n < 1048576 ? (n / 1024).toFixed(1) + ' KB' : (n / 1048576).toFixed(1) + ' MB';
                return f(t.input.length) + ' → ' + f(t.output.length);
            },
            total() { return this.tests.reduce((s, t) => s + t.input.length + t.output.length, 0); },
            emptyOut() { return this.tests.filter((t) => t.output.trim() === '').length; },
            touch() { this.dirty = true; },
            preview(text) { return text.length > 3000 ? text.slice(0, 3000) + '\n… (còn nữa — tệp lớn, chỉ xem)' : text; },
            toggle(id) { this.openId = this.openId === id ? null : id; },
            remove(id) {
                this.tests = this.tests.filter((t) => t.id !== id);
                if (this.openId === id) this.openId = null;
                this.report = null;
                this.touch();
            },
            clearAll() {
                this.tests = [];
                this.openId = null;
                this.confirmClear = false;
                this.report = null;
                this.touch();
            },
            async importFiles(fileList) {
                const files = Array.from(fileList || []);
                if (!files.length || this.busy) return;
                this.busy = true;
                try {
                    const groups = new Map();
                    const ignored = [];
                    const dup = [];
                    const zipErrors = [];
                    const flat = [];
                    for (const f of files) {
                        if (/\.zip$/i.test(f.name)) {
                            try { flat.push(...await readZip(f)); } catch (err) { zipErrors.push(f.name + ' — ' + (err && err.message ? err.message : 'không đọc được')); }
                        } else {
                            flat.push(f);
                        }
                    }
                    for (const f of flat) {
                        const m = /^(.*)\.([^.]+)$/.exec(f.name);
                        const ext = m ? m[2].toLowerCase() : '';
                        const kind = IN_EXT.includes(ext) ? 'in' : OUT_EXT.includes(ext) ? 'out' : null;
                        if (!m || !kind) { ignored.push(f.name); continue; }
                        const key = m[1].toLowerCase();
                        const g = groups.get(key) || { base: m[1], key };
                        if (g[kind]) { dup.push(f.name); continue; }
                        g[kind] = f;
                        groups.set(key, g);
                    }
                    const pairs = [];
                    const missing = [];
                    for (const g of groups.values()) {
                        if (g.in && g.out) pairs.push(g);
                        else missing.push(g.in ? g.in.name + ' (thiếu tệp ra)' : g.out.name + ' (thiếu tệp vào)');
                    }
                    pairs.sort((a, b) => a.base.localeCompare(b.base, undefined, { numeric: true, sensitivity: 'base' }));

                    let added = 0, replaced = 0, wiped = 0;
                    const tooBig = [];
                    // Chế độ "Thay toàn bộ": chỉ xoá bộ cũ khi lần nhập này CÓ ít nhất 1 cặp hợp lệ,
                    // nhập hỏng thì bộ test đang có vẫn còn nguyên.
                    if (this.mode === 'replace' && pairs.length > 0 && this.tests.length > 0) {
                        wiped = this.tests.length;
                        this.tests = [];
                        this.openId = null;
                    }
                    let running = this.total();
                    for (const g of pairs) {
                        if ((g.in.size || 0) + (g.out.size || 0) > MAX_TOTAL) { tooBig.push(g.base); continue; }
                        let input, output;
                        try {
                            [input, output] = await Promise.all([g.in.text(), g.out.text()]);
                        } catch (err) {
                            zipErrors.push(g.base + ' — ' + (err && err.message ? err.message : 'không đọc được'));
                            continue;
                        }
                        const idx = this.tests.findIndex((t) => t.key && t.key === g.key);
                        const prev = idx >= 0 ? this.tests[idx].input.length + this.tests[idx].output.length : 0;
                        if (running - prev + input.length + output.length > MAX_TOTAL) { tooBig.push(g.base); continue; }
                        running = running - prev + input.length + output.length;
                        const item = this.make(input, output, g.in.name, g.out.name, g.key);
                        if (idx >= 0) { this.tests.splice(idx, 1, item); replaced++; } else { this.tests.push(item); added++; }
                    }
                    if (added || replaced) this.touch();
                    this.report = { added, replaced, wiped, missing, dup, ignored, tooBig, zipErrors };
                } finally {
                    this.busy = false;
                }
            },
            sync() {
                const el = this.$refs.json;
                if (!el) return;
                const send = cfg.always ? this.tests.length > 0 || this.dirty : this.dirty;
                el.value = send ? JSON.stringify(this.tests.map((t) => ({ input: t.input, output: t.output }))) : '';
                el.disabled = !send;
            },
        };
    };
</script>
@endverbatim

<div x-data="qeTestSet({{ \Illuminate\Support\Js::from($qeCfg) }})" class="qe-tests">
    <input type="hidden" name="test_cases_json" x-ref="json" disabled>
    <input type="file" multiple x-ref="files" class="hidden" style="display:none"
           accept=".zip,.in,.inp,.input,.out,.ans,.output,.sol"
           @change="importFiles($event.target.files); $event.target.value = ''">

    <div class="qe-tests-head">
        <span class="qe-count" :class="tests.length === 0 && 'is-empty'" x-text="tests.length + ' test'"></span>
        <span class="qe-spacer"></span>
        <button type="button" class="qe-btn qe-btn--green qe-btn--ghost" @click="$refs.files.click()" :disabled="busy">
            <x-lucide name="upload" class="h-4 w-4" /> <span x-text="busy ? 'Đang đọc tệp…' : 'Nhập bộ test'"></span>
        </button>
        <template x-if="tests.length > 0 && !confirmClear">
            <button type="button" class="qe-btn qe-btn--ghost qe-btn--danger" @click="confirmClear = true">
                <x-lucide name="trash-2" class="h-4 w-4" /> Xoá hết
            </button>
        </template>
        <template x-if="confirmClear">
            <span class="qe-btn qe-btn--ghost qe-btn--danger" style="cursor:default; gap:10px;">
                Xoá toàn bộ test?
                <a href="#" @click.prevent="clearAll()" style="font-weight:800; text-decoration:underline;">Xoá</a>
                <a href="#" @click.prevent="confirmClear = false" style="color:#526b87; text-decoration:underline;">Không</a>
            </span>
        </template>
    </div>

    <div class="qe-drop" :class="drag && 'is-drag'" role="button" tabindex="0"
         @click="$refs.files.click()" @keydown.enter.prevent="$refs.files.click()" @keydown.space.prevent="$refs.files.click()"
         @dragover.prevent="drag = true" @dragleave.prevent="drag = false"
         @drop.prevent="drag = false; importFiles($event.dataTransfer.files)">
        <span class="qe-zip-ico"><x-lucide name="file-text" class="h-5 w-5" /></span>
        <div>
            <strong>Ghép theo tên: <code>bai1.in</code> + <code>bai1.out</code></strong>
            <span>Chọn hoặc kéo thả <strong>1 tệp <code>.zip</code></strong> chứa các cặp test (<code>1.in</code> + <code>1.out</code>, <code>2.in</code> + <code>2.out</code>…), hoặc chọn nhiều tệp lẻ cùng lúc. Nhận <code>.in</code> <code>.inp</code> (dữ liệu vào) và <code>.out</code> <code>.ans</code> (kết quả đúng); tệp lẻ không đủ cặp sẽ được báo lại.</span>
        </div>
    </div>

    <template x-if="report">
        <div class="qe-report" :class="(report.missing.length || report.dup.length || report.ignored.length || report.tooBig.length || report.zipErrors.length) ? 'is-warn' : 'is-ok'">
            <div>
                <strong x-text="'Đã nhập ' + report.added + ' test mới' + (report.wiped ? ', đã thay ' + report.wiped + ' test cũ' : '') + (report.replaced ? ', thay ' + report.replaced + ' test trùng tên' : '') + '.'"></strong>
                <span x-show="report.added + report.replaced === 0"> Chưa có bộ test nào hợp lệ.</span>
            </div>
            <ul x-show="report.missing.length"><li x-text="'Thiếu cặp: ' + report.missing.join(', ')"></li></ul>
            <ul x-show="report.dup.length"><li x-text="'Trùng tên, bỏ qua: ' + report.dup.join(', ')"></li></ul>
            <ul x-show="report.ignored.length"><li x-text="'Không đúng đuôi .in/.out, bỏ qua: ' + report.ignored.join(', ')"></li></ul>
            <ul x-show="report.zipErrors.length"><li x-text="'Không đọc được: ' + report.zipErrors.join('; ')"></li></ul>
            <ul x-show="report.tooBig.length"><li x-text="'Vượt giới hạn ~5 MB tổng dung lượng test, chưa nhập: ' + report.tooBig.join(', ')"></li></ul>
        </div>
    </template>

    <div class="qe-manifest" x-show="tests.length > 0" x-cloak>
        <template x-for="(t, i) in tests" :key="t.id">
            <div class="qe-test">
                <div class="qe-test-row">
                    <span class="qe-test-no" x-text="i + 1"></span>
                    <span class="qe-test-files">
                        <code class="in" x-text="inLabel(t, i)"></code>
                        <i>→</i>
                        <code class="out" x-text="outLabel(t, i)"></code>
                    </span>
                    <span class="qe-test-meta" x-text="size(t)"></span>
                    <span class="qe-test-act">
                        <button type="button" class="qe-icon-btn" :title="openId === t.id ? 'Thu gọn' : 'Xem / sửa nội dung'" :aria-label="openId === t.id ? 'Thu gọn test' : 'Xem hoặc sửa nội dung test'" @click="toggle(t.id)">
                            <x-lucide name="pencil" class="h-4 w-4" />
                        </button>
                        <button type="button" class="qe-icon-btn is-danger" title="Xoá test này" aria-label="Xoá test này" @click="remove(t.id)">
                            <x-lucide name="trash-2" class="h-4 w-4" />
                        </button>
                    </span>
                </div>
                <div class="qe-test-edit" x-show="openId === t.id" x-cloak>
                    <div>
                        <label>Input (dữ liệu vào)</label>
                        <textarea spellcheck="false" :readonly="t.big" :value="t.big ? preview(t.input) : t.input" @input="t.input = $event.target.value; touch()"></textarea>
                    </div>
                    <div>
                        <label>Output (kết quả đúng)</label>
                        <textarea spellcheck="false" :readonly="t.big" :value="t.big ? preview(t.output) : t.output" @input="t.output = $event.target.value; touch()"></textarea>
                    </div>
                    <p x-show="t.big">Test lớn — chỉ xem được phần đầu. Muốn đổi, xoá test này rồi nhập lại bộ test.</p>
                </div>
            </div>
        </template>
    </div>

    <div class="qe-tests-empty" x-show="tests.length === 0" x-cloak>
        Chưa có test nào. Bấm <strong>Nhập bộ test</strong> để chọn file <code>.zip</code> hoặc các tệp <code>.in</code> / <code>.out</code>.
    </div>

    <div class="qe-tests-foot">
        <span x-show="emptyOut() > 0" x-cloak style="color:#b45309;" x-text="emptyOut() + ' test chưa có kết quả đúng (output rỗng) — kiểm tra lại trước khi lưu.'"></span>
        <template x-if="dirty">
            <span class="qe-dirty">Bộ test đã thay đổi — bấm Lưu để áp dụng.</span>
        </template>
        @if ($qeMode === 'edit')
            <template x-if="!dirty">
                <span>Không thay đổi gì thì bộ test hiện tại được giữ nguyên khi Lưu.</span>
            </template>
        @endif
    </div>
</div>
