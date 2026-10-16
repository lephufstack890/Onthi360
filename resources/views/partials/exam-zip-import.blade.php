{{-- SỬA 16/10 (khách: "thêm phần nhập file ZIP dạng này chỗ tạo đề / bộ bài trong Kho bài tập / câu hỏi và đề, admin và giáo
     viên") — khối "Nhập cả đề từ gói ZIP" (OT360-EPACK 2.0) đặt TRÊN form tạo đề trống ở:
       - admin/content/assessments/create.blade.php   (route admin.content.assessments.zipImport)
       - teacher/assessments/papers/create.blade.php  (route teacher.papers.zipImport)
     Mỗi câu trong exam.json tự khai "type" nên hệ thống tự phân biệt Trắc nghiệm / Đúng-Sai / Đúng-Sai 4 ý / Điền từ / Câu nhiều ý /
     Lập trình (App\Support\ExamZipPackage). Mẫu: public/templates/mau-de-thi.zip.
     SỬA 16/10 — 2 loại gói: Luyện tập (mặc định, admin) = mỗi câu 1 thư mục theo mẫu câu hỏi MAU-CAU-HOI, câu vào Kho câu hỏi,
     đề hiện dạng câu hỏi rời (mẫu: templates/mau-de-luyen-tap.zip); Đề thi PDF ("exam.type": "exam") = de.pdf + đáp án trong
     exam.json (mẫu: templates/mau-de-thi-pdf.zip). Giáo viên hiện chỉ nhập gói Đề thi PDF.
     Tham số: $action, $mode ('admin' | 'teacher'). CSS tự chứa (tiền tố ezi-) vì máy chủ không build lại Tailwind. --}}
@php
    $eziMode = $mode ?? 'admin';
    $eziErrors = $errors->get('exam_zip');
    $eziTypes = [
        ['Trắc nghiệm', 'single_choice', 'list-checks'],
        ['Đúng / Sai', 'true_false', 'check-circle-2'],
        ['Đúng / Sai 4 ý', 'true_false [ ]', 'list-checks'],
        ['Điền từ', 'short_answer', 'text-cursor-input'],
        ['Câu nhiều ý', 'composite', 'layers'],
        ['Lập trình', 'programming', 'code-2'],
    ];
@endphp
<style>
    .ezi { margin-bottom: 16px; padding: 18px; border: 1px solid #DCE8F3; border-radius: 22px; background: #fff; box-shadow: 0 2px 10px rgba(0, 90, 180, .05); }
    .ezi-head { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 12px; }
    .ezi-head__ic { display: grid; flex-shrink: 0; place-items: center; width: 40px; height: 40px; border-radius: 12px; background: #EAF2FF; color: #2563EB; }
    .ezi-head__ic svg { width: 20px; height: 20px; }
    .ezi-head h2 { margin: 0; font-size: 16px; font-weight: 800; color: #123B68; }
    .ezi-head p { margin: 3px 0 0; font-size: 13px; line-height: 1.5; color: #5C7488; }
    .ezi-head code, .ezi-drop code { padding: 1px 5px; border-radius: 5px; background: #EEF2FF; font-size: 11.5px; color: #3730A3; }
    .ezi-err { margin: 0 0 12px; padding: 10px 12px; border: 1px solid #FECACA; border-radius: 12px; background: #FEF2F2; font-size: 13px; color: #B91C1C; }
    .ezi-err ul { margin: 4px 0 0; padding-left: 18px; }
    .ezi-drop { position: relative; display: flex; align-items: center; justify-content: center; gap: 14px; min-height: 120px; padding: 20px 16px; border: 2px dashed #9DC0DA; border-radius: 18px; background: #F7FAFD; cursor: pointer; transition: border-color .15s, background-color .15s; }
    .ezi-drop:hover, .ezi-drop.is-drag { border-color: #2563EB; background: #EEF4FF; }
    .ezi-drop.has-file { border-style: solid; border-color: #2F8A6B; background: #EFFAF5; }
    .ezi-drop input[type=file] { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
    .ezi-drop__ic { display: grid; flex-shrink: 0; place-items: center; width: 50px; height: 50px; border-radius: 15px; background: #EAF2FF; color: #2563EB; }
    .ezi-drop__ic svg { width: 24px; height: 24px; }
    .ezi-drop.has-file .ezi-drop__ic { background: #D1FAE5; color: #047857; }
    .ezi-drop__txt { min-width: 0; }
    .ezi-drop__txt b { display: block; font-size: 15px; color: #123B68; word-break: break-all; }
    .ezi-drop__txt small { display: block; margin-top: 4px; font-size: 12.5px; color: #6B8396; }
    .ezi-drop.has-file .ezi-drop__txt small { color: #207153; font-weight: 600; }
    .ezi-types { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 12px; }
    .ezi-types__lbl { display: inline-flex; align-items: center; gap: 5px; margin-right: 2px; font-size: 12px; font-weight: 700; color: #64748B; }
    .ezi-types__lbl svg { width: 14px; height: 14px; color: #D97706; }
    .ezi-type { display: inline-flex; align-items: center; gap: 5px; padding: 5px 10px; border: 1px solid #DDEAF0; border-radius: 999px; background: #fff; font-size: 12px; font-weight: 600; color: #35617A; }
    .ezi-type svg { width: 13px; height: 13px; color: #126F91; }
    .ezi-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-top: 14px; }
    .ezi-btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 40px; padding: 0 16px; border: 1px solid #D6E4F0; border-radius: 12px; background: #fff; font-size: 13px; font-weight: 700; color: #35506A; text-decoration: none; cursor: pointer; }
    .ezi-btn svg { width: 16px; height: 16px; }
    .ezi-btn:hover { background: #F5F9FF; }
    .ezi-btn--primary { border-color: #2563EB; background: #2563EB; color: #fff; box-shadow: 0 4px 12px rgba(37, 99, 235, .22); }
    .ezi-btn--primary:hover { background: #1D4ED8; }
    .ezi-btn--primary:disabled { opacity: .5; cursor: not-allowed; box-shadow: none; }
    .ezi-btn--tpl { border-style: dashed; border-color: #93C5FD; color: #1D4ED8; }
    .ezi-note { margin: 0; font-size: 12px; color: #7A92A3; }
    .ezi-or { display: flex; align-items: center; gap: 10px; margin: 4px 0 16px; font-size: 12px; font-weight: 700; color: #94A3B8; }
    .ezi-or::before, .ezi-or::after { content: ''; flex: 1; height: 1px; background: #E2E8F0; }
    [x-cloak] { display: none !important; }
    @media (max-width: 640px) { .ezi-drop { flex-direction: column; text-align: center; } }
</style>

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="ezi"
      x-data="{ name: '', size: '', drag: false, busy: false,
                pick(e) { const f = e.target.files && e.target.files[0]; this.name = f ? f.name : ''; this.size = f ? (f.size / 1048576).toFixed(1) + ' MB' : ''; },
                clear() { this.$refs.zip.value = ''; this.name = ''; this.size = ''; } }"
      @submit="busy = true">
    @csrf
    <div class="ezi-head">
        <span class="ezi-head__ic"><x-lucide name="package" /></span>
        <div>
            @if ($eziMode === 'admin')
                <h2>Nhập đề từ gói ZIP</h2>
                <p><b>Đề Luyện tập</b> (mặc định): mỗi câu là 1 thư mục theo <b>đúng mẫu câu hỏi MAU-CAU-HOI</b> — hệ thống
                    <b>tự nhận dạng</b> từng câu theo <code>content.type</code>, thêm cả câu vào Kho câu hỏi (đủ đề, đáp án, lời giải, bộ test)
                    và gắn vào đề dạng câu hỏi rời. Muốn nhập <b>Đề thi PDF</b> thì ghi <code>"type": "exam"</code> trong exam.json.</p>
            @else
                <h2>Nhập đề PDF từ gói ZIP</h2>
                <p>1 gói gồm đề PDF + đáp án + bài lập trình. Mỗi câu trong <code>exam.json</code> tự khai <code>type</code> — hệ thống
                    <b>tự phân biệt</b> dạng câu và tạo sẵn phiếu đáp án, bài lập trình, bộ test.</p>
            @endif
        </div>
    </div>

    @if (! empty($eziErrors))
        <div class="ezi-err" role="alert">
            <b>Chưa nhập được gói đề:</b>
            <ul>
                @foreach ($eziErrors as $eziMsg)
                    <li>{{ $eziMsg }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <label class="ezi-drop" :class="{ 'has-file': name, 'is-drag': drag }" @dragover="drag = true" @dragleave="drag = false" @drop="drag = false">
        <span class="ezi-drop__ic"><x-lucide name="upload" /></span>
        <span class="ezi-drop__txt">
            <b x-text="name || 'Kéo thả gói ZIP đề vào đây, hoặc bấm để chọn'">Kéo thả gói ZIP đề vào đây, hoặc bấm để chọn</b>
            <small x-show="! name">Gói OT360-EPACK 2.0: <code>exam.json</code> + {{ $eziMode === 'admin' ? 'thư mục từng câu (cau/01-…)' : 'de.pdf (+ lời giải, bản xem trước, ảnh bìa, thư mục bài lập trình)' }}</small>
            <small x-show="name" x-cloak x-text="size + ' · bấm Nhập đề để đọc gói'"></small>
        </span>
        <input type="file" name="exam_zip" accept=".zip,application/zip" required x-ref="zip" @change="pick($event)">
    </label>

    <div class="ezi-types" aria-label="Các dạng câu hệ thống tự nhận">
        <span class="ezi-types__lbl"><x-lucide name="sparkles" /> Tự nhận dạng:</span>
        @foreach ($eziTypes as [$tLabel, $tCode, $tIcon])
            <span class="ezi-type" title='type = "{{ $tCode }}"'><x-lucide name="{{ $tIcon }}" />{{ $tLabel }}</span>
        @endforeach
    </div>

    <div class="ezi-actions">
        <button type="submit" class="ezi-btn ezi-btn--primary" :disabled="! name || busy">
            <x-lucide name="upload" /> <span x-text="busy ? 'Đang đọc gói đề…' : 'Nhập đề'">Nhập đề</span>
        </button>
        <button type="button" class="ezi-btn" x-show="name && ! busy" x-cloak @click="clear()">Chọn file khác</button>
        @if ($eziMode === 'admin')
            <a href="{{ asset('templates/mau-de-luyen-tap.zip') }}" download="MAU-DE-LUYEN-TAP.zip" class="ezi-btn ezi-btn--tpl"><x-lucide name="download" /> Mẫu đề Luyện tập</a>
            <a href="{{ asset('templates/mau-de-thi-pdf.zip') }}" download="MAU-DE-THI-PDF.zip" class="ezi-btn ezi-btn--tpl"><x-lucide name="download" /> Mẫu đề thi PDF</a>
        @else
            <a href="{{ asset('templates/mau-de-thi-pdf.zip') }}" download="MAU-DE-THI-PDF.zip" class="ezi-btn ezi-btn--tpl"><x-lucide name="download" /> Tải file mẫu</a>
        @endif
        <p class="ezi-note">Nhập xong mở trang đề để rà lại, sửa / phát hành từng câu rồi phát hành đề.</p>
    </div>
</form>

<div class="ezi-or">hoặc tạo đề trống rồi tải PDF, nhập đáp án bằng tay</div>
