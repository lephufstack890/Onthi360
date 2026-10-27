{{-- SỬA 16/10 (khách: "bỏ phần chọn dạng + nhập tay, chỉ cần upload file ZIP — file theo mẫu, hệ thống tự biết dạng nào; làm cho
     đẹp dễ dùng") — khối thêm bài tập trên trang chi tiết tài liệu giờ CHỈ còn: (1) chọn chương → (2) thả 1 gói ZIP.

     Gói ZIP theo mẫu OT360-QPACK dùng chung cho 4 dạng (public/templates/mau-cau-hoi.zip): hệ thống đọc "content.type" trong
     question.json để biết dạng — single_choice (Trắc nghiệm) · true_false (Đúng/Sai) · short_answer (Điền từ) · programming
     (Lập trình). Việc đọc/nhận dạng nằm sẵn ở App\Support\QuestionZipPackage + ContentService::productExerciseStoreFromZipPackage()
     (route admin.products.exercises.store, không đổi). Đọc xong mở màn "Sửa bài tập" để rà lại rồi bấm Lưu như trước.

     Tham số: $product, $chapters, $chapterLabel. --}}
@include('admin.products.partials.adder-style')
@php
    $exChapterLower = $chapterLabel ? mb_strtolower($chapterLabel) : null;
    $exZipError = $errors->first('zip_package');
    $exTypes = [
        ['Trắc nghiệm', 'single_choice', 'list-checks'],
        ['Đúng / Sai', 'true_false', 'check-circle-2'],
        ['Điền từ', 'short_answer', 'text-cursor-input'],
        ['Lập trình', 'programming', 'code-2'],
    ];
@endphp

{{-- SỬA 27/10 (khách: "chọn được 2 loại: 1 loại up ZIP 1 bài như bình thường, 1 loại ZIP up hàng loạt") — 2 cách nhập:
     · Từng bài: form cũ bên dưới (đọc gói → màn Sửa rà lại → Lưu). Trùng mã bài thì báo, không ghi đè.
     · Hàng loạt: partials/exercise-bulk-adder (1 ZIP chứa nhiều ZIP bài, hoặc nhiều ZIP bài) — kiểm trùng hết rồi nhập luôn. --}}
<div x-data="{ addMode: 'single' }">
{{-- SỬA 27/10 (khách: "hiển thị hơi xấu, làm cho đẹp") — 3 cách thêm thành 3 thẻ đều nhau (biểu tượng + tên + mô tả ngắn),
     màn hẹp tự xếp dọc. Chỉ đổi giao diện, giá trị addMode giữ nguyên. --}}
<style>
    .oi-mode { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
    @media (max-width: 860px) { .oi-mode { grid-template-columns: 1fr; } }
    .oi-mode__opt { position: relative; display: flex; align-items: flex-start; gap: 12px; padding: 14px 40px 14px 14px; border: 1.5px solid #DDEAF0; border-radius: 14px; background: #fff; cursor: pointer; transition: border-color .15s, background .15s, box-shadow .15s; }
    .oi-mode__opt:hover { border-color: #B9D3E0; box-shadow: 0 4px 14px rgba(15, 59, 90, .06); }
    .oi-mode__opt input { position: absolute; opacity: 0; pointer-events: none; }
    .oi-mode__ic { flex: none; display: grid; place-items: center; width: 40px; height: 40px; border-radius: 11px; background: #F1F6FA; color: #5B7A90; }
    .oi-mode__ic svg { width: 20px; height: 20px; }
    .oi-mode__txt b { display: block; font-size: 14px; font-weight: 800; color: #1F3B52; line-height: 1.3; }
    .oi-mode__txt small { display: block; margin-top: 3px; font-size: 12.5px; line-height: 1.45; color: #7A92A3; }
    .oi-mode__dot { position: absolute; top: 14px; right: 14px; width: 18px; height: 18px; border: 2px solid #C9D9E3; border-radius: 50%; background: #fff; }
    .oi-mode__opt.is-on { border-color: #2F8A6B; background: #F2FAF6; box-shadow: 0 0 0 3px rgba(47, 138, 107, .12); }
    .oi-mode__opt.is-on .oi-mode__ic { background: #2F8A6B; color: #fff; }
    .oi-mode__opt.is-on .oi-mode__txt b { color: #1B6A4F; }
    .oi-mode__opt.is-on .oi-mode__dot { border-color: #2F8A6B; background: radial-gradient(circle, #2F8A6B 0 4px, #fff 5px); }
    .oi-mode__opt:focus-within { outline: 2px solid #93C5FD; outline-offset: 2px; }
</style>
<div class="oi-mode" role="radiogroup" aria-label="Cách thêm bài tập">
    <label class="oi-mode__opt" :class="addMode === 'single' ? 'is-on' : ''">
        <input type="radio" value="single" x-model="addMode">
        <span class="oi-mode__ic"><x-lucide name="upload" /></span>
        <span class="oi-mode__txt"><b>Từng bài</b><small>1 file ZIP = 1 bài, đọc xong mở màn rà lại rồi Lưu</small></span>
        <span class="oi-mode__dot" aria-hidden="true"></span>
    </label>
    <label class="oi-mode__opt" :class="addMode === 'bulk' ? 'is-on' : ''">
        <input type="radio" value="bulk" x-model="addMode">
        <span class="oi-mode__ic"><x-lucide name="package" /></span>
        <span class="oi-mode__txt"><b>Hàng loạt</b><small>1 file ZIP chứa nhiều bài (cả chương), kiểm trùng rồi nhập hết</small></span>
        <span class="oi-mode__dot" aria-hidden="true"></span>
    </label>
    {{-- SỬA 27/10 (khách: "thêm option chọn up từ bài có sẵn — bài của tài liệu khác") — xem partials/exercise-pick-adder. --}}
    <label class="oi-mode__opt" :class="addMode === 'pick' ? 'is-on' : ''">
        <input type="radio" value="pick" x-model="addMode" @change="$dispatch('ex-pick-open')">
        <span class="oi-mode__ic"><x-lucide name="copy" /></span>
        <span class="oi-mode__txt"><b>Lấy từ bài có sẵn</b><small>Chọn bài của sách / tài liệu khác để thêm vào chương</small></span>
        <span class="oi-mode__dot" aria-hidden="true"></span>
    </label>
</div>
<div x-show="addMode === 'bulk'" x-cloak>@include('admin.products.partials.exercise-bulk-adder')</div>
<div x-show="addMode === 'pick'" x-cloak>@include('admin.products.partials.exercise-pick-adder')</div>
<form x-show="addMode === 'single'" action="{{ route('admin.products.exercises.store', $product->id) }}" method="POST" enctype="multipart/form-data"
      class="oi-add oi-zip"
      @ex-chapter.window="chapter = String($event.detail ?? '')"
      x-data="{ chapter: @js((string) old('material_id', (string) ($exDefaultChapterId ?? ''))), zipName: '', zipSize: '', drag: false, busy: false,
                dup: '', checking: false, checkUrl: @js(route('admin.products.exercises.checkCode', $product->id)),
                pick(e) { const f = e.target.files && e.target.files[0]; this.zipName = f ? f.name : ''; this.zipSize = f ? (f.size / 1048576).toFixed(1) + ' MB' : ''; this.dup = ''; if (f) this.check(f.name); },
                {{-- SỬA 27/10 (khách: "up từng bài thì check luôn, có bài đó rồi thì báo trùng; không trùng mới cho up") --}}
                async check(name) {
                    this.checking = true;
                    try {
                        const res = await fetch(this.checkUrl + '?file=' + encodeURIComponent(name), { headers: { 'Accept': 'application/json' } });
                        const data = await res.json();
                        if (this.zipName === name) this.dup = data.exists ? data.message : '';
                    } catch (e) { this.dup = ''; }
                    this.checking = false;
                },
                clear() { this.$refs.zip.value = ''; this.zipName = ''; this.zipSize = ''; this.dup = ''; } }"
      @submit="busy = true">
    @csrf
    <input type="hidden" name="_form" value="exercise-zip">
    <input type="hidden" name="material_id" :value="chapter">

    @if ($exZipError)
        <p class="oi-add__err" role="alert">{{ $exZipError }}</p>
    @endif

    @if ($chapterLabel)
        <div>
            <label class="oi-add__lbl" for="ex-chapter">1. Thuộc {{ $exChapterLower }}</label>
            <select id="ex-chapter" x-model="chapter" @change="$dispatch('ex-chapter', chapter)" class="oi-add__input" style="max-width: 460px">
                <option value="">— Chưa gắn {{ $exChapterLower }} —</option>
                @foreach ($chapters as $c)
                    <option value="{{ $c['id'] }}">{{ $c['title'] }}</option>
                @endforeach
            </select>
            @if (empty($chapters))
                <p class="oi-add__note">Chưa có {{ $exChapterLower }} nào — thêm ở khối "{{ $chapterLabel }}" phía trên rồi quay lại chọn.</p>
            @endif
        </div>
    @endif

    <div>
        <span class="oi-add__lbl">{{ $chapterLabel ? '2.' : '1.' }} Gói ZIP bài tập</span>
        <label class="oi-drop oi-drop--big oi-zip__drop" :class="{ 'has-file': zipName, 'is-drag': drag }"
               @dragover="drag = true" @dragleave="drag = false" @drop="drag = false">
            <span class="oi-zip__ic"><x-lucide name="upload" /></span>
            <span class="oi-zip__txt">
                <b x-text="zipName || 'Kéo thả file ZIP vào đây, hoặc bấm để chọn'">Kéo thả file ZIP vào đây, hoặc bấm để chọn</b>
                <small x-show="! zipName">Một mẫu dùng chung cho cả 4 dạng — hệ thống tự nhận dạng theo <code>content.type</code> trong question.json.</small>
                <small x-show="zipName" x-cloak x-text="zipSize + ' · bấm Nhập bài tập để đọc gói'"></small>
            </span>
            <input type="file" name="zip_package" accept=".zip,application/zip" required x-ref="zip" @change="pick($event)">
        </label>
        <div class="oi-types" aria-label="Các dạng hệ thống tự nhận">
            <span class="oi-types__lbl"><x-lucide name="sparkles" /> Tự nhận dạng:</span>
            @foreach ($exTypes as [$tLabel, $tCode, $tIcon])
                <span class="oi-type" title='content.type = "{{ $tCode }}"'><x-lucide name="{{ $tIcon }}" />{{ $tLabel }}</span>
            @endforeach
        </div>
    </div>

    <div class="oi-add__actions">
        <p class="oi-add__err" role="alert" x-show="dup" x-cloak x-text="dup" style="flex-basis:100%"></p>
        <button type="submit" class="acx-btn acx-btn--primary acx-btn--sm" :disabled="! zipName || busy || checking || dup">
            <x-lucide name="upload" /> <span x-text="busy ? 'Đang đọc gói…' : 'Nhập bài tập'">Nhập bài tập</span>
        </button>
        <button type="button" class="acx-btn acx-btn--sm" x-show="zipName && ! busy" x-cloak @click="clear()">Chọn file khác</button>
        <a href="{{ asset('templates/mau-cau-hoi.zip') }}" download="MAU-CAU-HOI.zip" class="oi-zip__tpl"><x-lucide name="download" /> Tải file mẫu</a>
        <span class="oi-add__note" style="margin:0">Đọc xong sẽ mở màn kiểm tra lại — xem đề, đáp án rồi bấm "Lưu bài tập" để hoàn tất. Bài đã có (trùng mã = tên file ZIP) thì báo, không ghi đè.</span>
    </div>
</form>
</div>
