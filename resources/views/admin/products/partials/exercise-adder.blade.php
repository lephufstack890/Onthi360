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

<form action="{{ route('admin.products.exercises.store', $product->id) }}" method="POST" enctype="multipart/form-data"
      class="oi-add oi-zip"
      x-data="{ chapter: @js((string) old('material_id', '')), zipName: '', zipSize: '', drag: false, busy: false,
                pick(e) { const f = e.target.files && e.target.files[0]; this.zipName = f ? f.name : ''; this.zipSize = f ? (f.size / 1048576).toFixed(1) + ' MB' : ''; },
                clear() { this.$refs.zip.value = ''; this.zipName = ''; this.zipSize = ''; } }"
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
            <select id="ex-chapter" x-model="chapter" class="oi-add__input" style="max-width: 460px">
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
        <button type="submit" class="acx-btn acx-btn--primary acx-btn--sm" :disabled="! zipName || busy">
            <x-lucide name="upload" /> <span x-text="busy ? 'Đang đọc gói…' : 'Nhập bài tập'">Nhập bài tập</span>
        </button>
        <button type="button" class="acx-btn acx-btn--sm" x-show="zipName && ! busy" x-cloak @click="clear()">Chọn file khác</button>
        <a href="{{ asset('templates/mau-cau-hoi.zip') }}" download="MAU-CAU-HOI.zip" class="oi-zip__tpl"><x-lucide name="download" /> Tải file mẫu</a>
        <span class="oi-add__note" style="margin:0">Đọc xong sẽ mở màn kiểm tra lại — xem đề, đáp án rồi bấm "Lưu bài tập" để hoàn tất.</span>
    </div>
</form>
