{{-- SỬA 7/11 (khách: "thêm phần up cả quyển sách — chỉ lưu lại, xem trước trong này, có nút in và nút tải PDF; ai đặt bản in thì
     mình chủ động in rồi ship") — khối "Bản in cả cuốn" ở trang chi tiết tài liệu (admin). CHỈ THÊM: tệp lưu nội bộ, học sinh không
     thấy; không đụng logic bán / đọc / chương / bài tập. Xử lý ở Admin\ProductPrintBookController. CSS tiền tố pbk-. --}}
@php
    $pbkReady = \App\Support\PrintBook::ready();
    $pbkHas = $pbkReady && filled($product->print_book_path ?? null);
    $pbkFile = $pbkHas ? route('admin.products.print-book.file', $product->id) : null;
    $pbkBy = $pbkHas && $product->print_book_uploaded_by ? \App\Models\User::query()->whereKey($product->print_book_uploaded_by)->value('name') : null;
    $pbkAt = $pbkHas && $product->print_book_uploaded_at ? \Illuminate\Support\Carbon::parse($product->print_book_uploaded_at)->format('H:i d/m/Y') : null;
@endphp

@if (in_array(session('status'), ['print-book-saved', 'print-book-deleted'], true))
    @include('partials.toast-flash', ['type' => 'success', 'message' => session('statusMessage', 'Đã lưu.')])
@endif

<section class="acx-card acx-card--white pbk" id="ban-in-ca-cuon" style="scroll-margin-top:80px"
         x-data="pbkBox(@js(['upload' => $pbkReady ? route('admin.products.print-book.upload', $product->id) : '', 'file' => $pbkFile, 'csrf' => csrf_token(), 'maxBytes' => \App\Support\PrintBook::maxKilobytes() * 1024, 'maxLabel' => \App\Support\PrintBook::maxLabel()]))">
    <div class="pbk-head">
        <h2><x-lucide name="printer" /> Bản in cả cuốn</h2>
        <span class="pbk-badge"><x-lucide name="lock" />Chỉ lưu nội bộ</span>
    </div>
    <p class="pbk-note">Tệp PDF cả quyển để <b>in khi có khách đặt bản in</b> rồi ship. Học sinh không xem / tải được tệp này; không ảnh hưởng phần đọc và bán tài liệu.</p>

    @if (! $pbkReady)
        <p class="pbk-warn"><x-lucide name="alert-triangle" />Chưa cập nhật cơ sở dữ liệu — chạy <code>php artisan migrate --force</code> trên máy chủ để dùng mục này.</p>
    @else
        @if ($pbkHas)
            <div class="pbk-file">
                <span class="pbk-pdf">PDF</span>
                <div class="pbk-fi">
                    <b title="{{ $product->print_book_name }}">{{ $product->print_book_name ?: 'Ban-in-ca-cuon.pdf' }}</b>
                    <small>{{ \App\Support\PrintBook::sizeLabel((int) $product->print_book_size) }}@if ($pbkAt) · tải lên {{ $pbkAt }}@endif @if ($pbkBy) · {{ $pbkBy }}@endif</small>
                </div>
                <div class="pbk-acts">
                    <button type="button" class="pbk-btn" @click="view = !view" :class="{ on: view }"><x-lucide name="eye" /><span x-text="view ? 'Ẩn xem trước' : 'Xem trước'"></span></button>
                    <button type="button" class="pbk-btn pri" @click="printBook()" :disabled="printing"><x-lucide name="printer" /><span x-text="printing ? 'Đang mở…' : 'In sách'"></span></button>
                    <a href="{{ $pbkFile }}?tai=1" class="pbk-btn"><x-lucide name="download" />Tải PDF</a>
                    <button type="button" class="pbk-btn" @click="$refs.input.click()"><x-lucide name="upload" />Thay tệp</button>
                    <form method="POST" action="{{ route('admin.products.print-book.destroy', $product->id) }}" onsubmit="return confirm('Xoá tệp bản in cả cuốn của tài liệu này? Tài liệu vẫn giữ nguyên.');" style="margin:0">
                        @csrf @method('DELETE')
                        <button type="submit" class="pbk-btn red" title="Xoá tệp bản in"><x-lucide name="trash-2" /></button>
                    </form>
                </div>
            </div>
            <div class="pbk-view" x-show="view" x-cloak>
                <iframe x-ref="frame" :src="view ? cfg.file : 'about:blank'" title="Xem trước bản in cả cuốn" @load="frameLoaded()"></iframe>
            </div>
        @else
            <label class="pbk-drop" :class="{ over: over }" @dragover.prevent="over = true" @dragleave.prevent="over = false" @drop.prevent="over = false; pick($event.dataTransfer.files[0])">
                <span class="pbk-dic"><x-lucide name="upload" /></span>
                <b>Chọn hoặc kéo thả tệp PDF cả cuốn sách</b>
                <small>Một tệp PDF đầy đủ để in · tối đa {{ \App\Support\PrintBook::maxLabel() }}</small>
                <span class="pbk-btn pri" style="pointer-events:none"><x-lucide name="upload" />Chọn tệp PDF</span>
            </label>
        @endif

        <input type="file" accept="application/pdf,.pdf" x-ref="input" hidden @change="pick($event.target.files[0]); $event.target.value = ''">

        <div class="pbk-up" x-show="busy" x-cloak>
            <div class="pbk-upt"><span x-text="name"></span><b x-text="pct + '%'"></b></div>
            <div class="pbk-bar"><i :style="'width:' + pct + '%'"></i></div>
            <small x-text="pct < 100 ? 'Đang tải lên… vui lòng không đóng trang.' : 'Đang lưu…'"></small>
        </div>
        <p class="pbk-err" x-show="err" x-cloak x-text="err"></p>
    @endif
</section>

<script>
    window.pbkBox = function (cfg) {
        return {
            cfg, view: false, printing: false, loaded: false, over: false, busy: false, pct: 0, name: '', err: '',
            pick(f) {
                this.err = '';
                if (!f) return;
                if (!/\.pdf$/i.test(f.name) && f.type !== 'application/pdf') { this.err = 'Tệp phải là PDF.'; return; }
                if (cfg.maxBytes && f.size > cfg.maxBytes) { this.err = 'Tệp vượt quá ' + cfg.maxLabel + ' mà máy chủ nhận được.'; return; }
                const fd = new FormData(); fd.append('book_pdf', f); fd.append('_token', cfg.csrf);
                const x = new XMLHttpRequest();
                this.busy = true; this.pct = 0; this.name = f.name;
                x.upload.onprogress = (e) => { if (e.lengthComputable) this.pct = Math.min(100, Math.round(e.loaded / e.total * 100)); };
                x.onload = () => {
                    let j = {}; try { j = JSON.parse(x.responseText || '{}'); } catch (e) {}
                    if (x.status >= 200 && x.status < 300 && j.ok) { location.hash = 'ban-in-ca-cuon'; location.reload(); return; }
                    this.busy = false;
                    this.err = (j.errors && Object.values(j.errors)[0] && Object.values(j.errors)[0][0]) || j.message
                        || (x.status === 413 ? 'Tệp quá lớn so với giới hạn của máy chủ.' : 'Không tải lên được (mã ' + x.status + ').');
                };
                x.onerror = () => { this.busy = false; this.err = 'Mất kết nối khi tải lên — thử lại.'; };
                x.open('POST', cfg.upload);
                x.setRequestHeader('Accept', 'application/json');
                x.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                x.send(fd);
            },
            frameLoaded() {
                if (!this.$refs.frame || this.$refs.frame.getAttribute('src') === 'about:blank') return;
                this.loaded = true;
                if (this.printing) this.doPrint();
            },
            printBook() {
                if (!cfg.file) return;
                this.view = true; // hiện khung xem trước (trình xem PDF phải hiển thị mới in được)
                this.printing = true;
                if (this.loaded) this.doPrint();
                // Tệp lớn: chờ trình xem PDF tải xong rồi mới mở hộp thoại in (frameLoaded gọi doPrint).
                setTimeout(() => { if (this.printing && !this.loaded) { this.printing = false; window.open(cfg.file, '_blank'); } }, 20000);
            },
            doPrint() {
                this.printing = false;
                try {
                    const w = this.$refs.frame.contentWindow;
                    setTimeout(() => { try { w.focus(); w.print(); } catch (e) { window.open(cfg.file, '_blank'); } }, 400);
                } catch (e) { window.open(cfg.file, '_blank'); }
            },
        };
    };
</script>
<style>
    .pbk-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; }
    .pbk-head h2 { margin: 0; }
    .pbk-badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 999px; background: #F1F5F9; font-size: 12px; font-weight: 700; color: #475569; }
    .pbk-badge svg { width: 13px; height: 13px; }
    .pbk-note { margin: 6px 0 12px; font-size: 13px; line-height: 1.55; color: #587089; } .pbk-note b { color: #1E3A5F; }
    .pbk-warn { display: flex; align-items: center; gap: 8px; margin: 0; padding: 10px 12px; border-radius: 12px; background: #FFF7E6; font-size: 13px; color: #92400E; }
    .pbk-warn svg { width: 16px; height: 16px; flex: none; }
    .pbk-file { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid #DCE8F3; border-radius: 14px; background: #F8FBFE; }
    .pbk-pdf { display: grid; place-items: center; width: 44px; height: 52px; flex: none; border-radius: 10px; background: linear-gradient(160deg, #EF4444, #B91C1C); font-size: 12px; font-weight: 800; letter-spacing: .04em; color: #fff; box-shadow: 0 6px 14px rgba(185, 28, 28, .25); }
    .pbk-fi { flex: 1 1 220px; min-width: 0; }
    .pbk-fi b { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 14px; font-weight: 700; color: #1E3A5F; }
    .pbk-fi small { display: block; margin-top: 2px; font-size: 12px; color: #6B8197; }
    .pbk-acts { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
    .pbk-btn { display: inline-flex; align-items: center; gap: 6px; height: 36px; padding: 0 12px; border: 1px solid #D6E3EF; border-radius: 10px; background: #fff; font: inherit; font-size: 13px; font-weight: 700; color: #1D4ED8; text-decoration: none; white-space: nowrap; cursor: pointer; transition: all .15s; }
    .pbk-btn svg { width: 15px; height: 15px; } .pbk-btn:hover { border-color: #93C5FD; background: #F5F9FF; }
    .pbk-btn.on { border-color: #2563EB; background: #EFF5FF; }
    .pbk-btn.pri { border: 0; background: linear-gradient(135deg, #2563EB, #1D4ED8); color: #fff; box-shadow: 0 6px 14px rgba(37, 99, 235, .22); }
    .pbk-btn.pri:hover { background: linear-gradient(135deg, #1D4ED8, #1E40AF); }
    .pbk-btn.red { color: #DC2626; border-color: #FECACA; } .pbk-btn.red:hover { background: #FFF5F5; }
    .pbk-btn:disabled { opacity: .6; cursor: wait; }
    .pbk-view { margin-top: 12px; overflow: hidden; border: 1px solid #DCE8F3; border-radius: 14px; background: #525659; }
    .pbk-view iframe { display: block; width: 100%; height: min(80vh, 900px); border: 0; }
    .pbk-drop { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 26px 16px; border: 2px dashed #C9DAEA; border-radius: 16px; background: #F8FBFE; text-align: center; cursor: pointer; transition: all .15s; }
    .pbk-drop:hover, .pbk-drop.over { border-color: #2563EB; background: #EFF5FF; }
    .pbk-drop b { font-size: 14px; color: #1E3A5F; } .pbk-drop small { font-size: 12px; color: #6B8197; }
    .pbk-dic { display: grid; place-items: center; width: 46px; height: 46px; border-radius: 14px; background: #E6EFFF; color: #2563EB; } .pbk-dic svg { width: 22px; height: 22px; }
    .pbk-up { margin-top: 12px; padding: 12px 14px; border: 1px solid #DCE8F3; border-radius: 14px; background: #fff; }
    .pbk-upt { display: flex; justify-content: space-between; gap: 10px; font-size: 13px; color: #1E3A5F; } .pbk-upt span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pbk-bar { height: 8px; margin: 8px 0 6px; overflow: hidden; border-radius: 9px; background: #E8EEF5; } .pbk-bar i { display: block; height: 100%; border-radius: 9px; background: linear-gradient(90deg, #2563EB, #60A5FA); transition: width .2s; }
    .pbk-up small { font-size: 12px; color: #6B8197; }
    .pbk-err { margin: 10px 0 0; font-size: 13px; font-weight: 600; color: #DC2626; }
</style>
