{{-- SỬA 16/10 (khách: "chỗ thêm câu hỏi của Kho bài tập / câu hỏi admin và giáo viên cũng vậy" — giống trang tài liệu: bỏ chọn
     dạng + form nhập tay, chỉ upload 1 file ZIP theo mẫu, hệ thống tự biết dạng). Khối tải gói ZIP DÙNG CHUNG cho:
       - admin/content/questions/create.blade.php   (route admin.content.questions.zipImport)
       - teacher/questions/create.blade.php (tạo mới) (route teacher.questions.zipImport)
     Dạng câu lấy theo "content.type" trong question.json (App\Support\QuestionZipPackage): single_choice → Trắc nghiệm,
     true_false → Đúng/Sai, short_answer → Điền từ, programming → Lập trình. Đọc xong controller chuyển sang trang Sửa câu hỏi
     để rà lại (form Sửa vẫn nhập tay đầy đủ như cũ). Mẫu: public/templates/mau-cau-hoi.zip.

     Tham số: $action (URL post), $maxKb (giới hạn dung lượng, KB), $hidden (mảng tên => giá trị ô ẩn, tuỳ chọn),
              $reviewLabel (tên trang kiểm tra sau khi nhập, tuỳ chọn). CSS tự chứa (tiền tố qzu-) để chạy được ở cả layout
              admin lẫn giáo viên, không phụ thuộc lớp Tailwind tuỳ biến. --}}
@php
    $qzuHidden = $hidden ?? [];
    $qzuMaxKb = (int) ($maxKb ?? 51200);
    $qzuMaxMb = max(1, (int) round($qzuMaxKb / 1024));
    $qzuReview = $reviewLabel ?? 'trang Sửa câu hỏi';
    $qzuErrors = $errors->all();
    $qzuTypes = [
        ['Trắc nghiệm', 'single_choice', 'list-checks', 'Chọn 1 đáp án A/B/C/D'],
        ['Đúng / Sai', 'true_false', 'check-circle-2', 'Đáp án true hoặc false'],
        ['Điền từ', 'short_answer', 'text-cursor-input', 'Danh sách đáp án chấp nhận'],
        ['Lập trình', 'programming', 'code-2', 'Chấm tự động theo bộ test'],
    ];
@endphp

<style>
    .qzu { display: grid; gap: 16px; max-width: 980px; }
    .qzu-card { padding: 20px; border: 1px solid #DCE8F3; border-radius: 22px; background: #fff; box-shadow: 0 2px 10px rgba(0, 90, 180, .05); }
    .qzu-head { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 14px; }
    .qzu-head__ic { display: grid; flex-shrink: 0; place-items: center; width: 40px; height: 40px; border-radius: 12px; background: #EAF2FF; color: #2563EB; }
    .qzu-head__ic svg { width: 20px; height: 20px; }
    .qzu-head h2 { margin: 0; font-size: 16px; font-weight: 800; color: #123B68; }
    .qzu-head p { margin: 3px 0 0; font-size: 13px; line-height: 1.5; color: #5C7488; }
    .qzu-err { margin: 0 0 12px; padding: 10px 12px; border: 1px solid #FECACA; border-radius: 12px; background: #FEF2F2; font-size: 13px; color: #B91C1C; }
    .qzu-err ul { margin: 4px 0 0; padding-left: 18px; }
    .qzu-drop { position: relative; display: flex; align-items: center; justify-content: center; gap: 16px; min-height: 150px; padding: 24px 18px; border: 2px dashed #9DC0DA; border-radius: 18px; background: #F7FAFD; cursor: pointer; transition: border-color .15s, background-color .15s; }
    .qzu-drop:hover, .qzu-drop.is-drag { border-color: #2563EB; background: #EEF4FF; }
    .qzu-drop.has-file { border-style: solid; border-color: #2F8A6B; background: #EFFAF5; }
    .qzu-drop.is-bad { border-color: #DC2626; background: #FEF2F2; }
    .qzu-drop input[type=file] { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
    .qzu-drop__ic { display: grid; flex-shrink: 0; place-items: center; width: 56px; height: 56px; border-radius: 16px; background: #EAF2FF; color: #2563EB; }
    .qzu-drop__ic svg { width: 26px; height: 26px; }
    .qzu-drop.has-file .qzu-drop__ic { background: #D1FAE5; color: #047857; }
    .qzu-drop.is-bad .qzu-drop__ic { background: #FEE2E2; color: #DC2626; }
    .qzu-drop__txt { min-width: 0; }
    .qzu-drop__txt b { display: block; font-size: 15.5px; color: #123B68; word-break: break-all; }
    .qzu-drop__txt small { display: block; margin-top: 4px; font-size: 12.5px; color: #6B8396; }
    .qzu-drop.has-file .qzu-drop__txt small { color: #207153; font-weight: 600; }
    .qzu-drop.is-bad .qzu-drop__txt small { color: #B91C1C; font-weight: 600; }
    .qzu-drop code, .qzu-steps code { padding: 1px 5px; border-radius: 5px; background: #EEF2FF; font-size: 11.5px; color: #3730A3; }
    .qzu-types { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; margin-top: 12px; }
    .qzu-type { display: flex; align-items: center; gap: 9px; padding: 10px 11px; border: 1px solid #E2ECF4; border-radius: 14px; background: #FBFDFF; }
    .qzu-type__ic { display: grid; flex-shrink: 0; place-items: center; width: 30px; height: 30px; border-radius: 9px; background: #E0F2FE; color: #0E7490; }
    .qzu-type__ic svg { width: 15px; height: 15px; }
    .qzu-type b { display: block; font-size: 13px; color: #1E3A5F; }
    .qzu-type small { display: block; font-size: 11px; color: #7A92A3; }
    .qzu-types__lbl { display: flex; align-items: center; gap: 6px; margin-top: 14px; font-size: 12px; font-weight: 700; color: #64748B; }
    .qzu-types__lbl svg { width: 14px; height: 14px; color: #D97706; }
    .qzu-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-top: 16px; }
    .qzu-btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 40px; padding: 0 16px; border: 1px solid #D6E4F0; border-radius: 12px; background: #fff; font-size: 13.5px; font-weight: 700; color: #35506A; text-decoration: none; cursor: pointer; }
    .qzu-btn svg { width: 16px; height: 16px; }
    .qzu-btn:hover { background: #F5F9FF; }
    .qzu-btn--primary { border-color: #2563EB; background: #2563EB; color: #fff; box-shadow: 0 4px 12px rgba(37, 99, 235, .22); }
    .qzu-btn--primary:hover { background: #1D4ED8; }
    .qzu-btn--primary:disabled { opacity: .5; cursor: not-allowed; box-shadow: none; }
    .qzu-btn--tpl { border-style: dashed; border-color: #93C5FD; color: #1D4ED8; }
    .qzu-steps { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
    .qzu-step { display: flex; gap: 10px; padding: 14px; border: 1px solid #E2ECF4; border-radius: 16px; background: #fff; }
    .qzu-step__n { display: grid; flex-shrink: 0; place-items: center; width: 26px; height: 26px; border-radius: 999px; background: #123B68; font-size: 12.5px; font-weight: 800; color: #fff; }
    .qzu-step b { display: block; font-size: 13.5px; color: #123B68; }
    .qzu-step p { margin: 3px 0 0; font-size: 12.5px; line-height: 1.5; color: #5C7488; }
    [x-cloak] { display: none !important; }
    @media (max-width: 760px) {
        .qzu-types { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .qzu-steps { grid-template-columns: 1fr; }
        .qzu-drop { flex-direction: column; text-align: center; }
    }
</style>

<div class="qzu">
    <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="qzu-card"
          x-data="{ name: '', size: '', bad: '', drag: false, busy: false, max: {{ $qzuMaxKb * 1024 }},
                    pick(e) {
                        const f = e.target.files && e.target.files[0];
                        this.name = f ? f.name : ''; this.size = f ? (f.size / 1048576).toFixed(1) + ' MB' : ''; this.bad = '';
                        if (f && ! /\.zip$/i.test(f.name)) this.bad = 'File phải có đuôi .zip';
                        else if (f && f.size > this.max) this.bad = 'File lớn hơn {{ $qzuMaxMb }} MB';
                    },
                    clear() { this.$refs.zip.value = ''; this.name = ''; this.size = ''; this.bad = ''; } }"
          @submit="busy = true">
        @csrf
        @foreach ($qzuHidden as $hName => $hValue)
            <input type="hidden" name="{{ $hName }}" value="{{ $hValue }}">
        @endforeach

        <div class="qzu-head">
            <span class="qzu-head__ic"><x-lucide name="package" /></span>
            <div>
                <h2>Thêm câu hỏi bằng gói ZIP</h2>
                <p>Một mẫu dùng chung cho cả 4 dạng — hệ thống <b>tự nhận dạng</b> theo <code style="padding:1px 5px;border-radius:5px;background:#EEF2FF;font-size:11.5px;color:#3730A3">content.type</code> trong question.json, không cần chọn dạng trước.</p>
            </div>
        </div>

        @if (! empty($qzuErrors))
            <div class="qzu-err" role="alert">
                <b>Chưa nhập được gói:</b>
                <ul>
                    @foreach ($qzuErrors as $qzuMsg)
                        <li>{{ $qzuMsg }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <label class="qzu-drop" :class="{ 'has-file': name && ! bad, 'is-bad': bad, 'is-drag': drag }"
               @dragover="drag = true" @dragleave="drag = false" @drop="drag = false">
            <span class="qzu-drop__ic"><x-lucide name="upload" /></span>
            <span class="qzu-drop__txt">
                <b x-text="name || 'Kéo thả file ZIP vào đây, hoặc bấm để chọn'">Kéo thả file ZIP vào đây, hoặc bấm để chọn</b>
                <small x-show="! name">Mỗi file ZIP = 1 câu hỏi · tên file = mã câu (vd SNT.zip → mã SNT) · tối đa {{ $qzuMaxMb }} MB</small>
                <small x-show="name && ! bad" x-cloak x-text="size + ' · bấm Nhập câu hỏi để đọc gói'"></small>
                <small x-show="bad" x-cloak x-text="bad"></small>
            </span>
            <input type="file" name="zip_package" accept=".zip,application/zip" required x-ref="zip" @change="pick($event)">
        </label>

        <div class="qzu-types__lbl"><x-lucide name="sparkles" /> Hệ thống tự nhận 4 dạng:</div>
        <div class="qzu-types">
            @foreach ($qzuTypes as [$tLabel, $tCode, $tIcon, $tHint])
                <div class="qzu-type" title='content.type = "{{ $tCode }}"'>
                    <span class="qzu-type__ic"><x-lucide name="{{ $tIcon }}" /></span>
                    <span><b>{{ $tLabel }}</b><small>{{ $tHint }}</small></span>
                </div>
            @endforeach
        </div>

        <div class="qzu-actions">
            <button type="submit" class="qzu-btn qzu-btn--primary" :disabled="! name || bad || busy">
                <x-lucide name="upload" /> <span x-text="busy ? 'Đang đọc gói…' : 'Nhập câu hỏi'">Nhập câu hỏi</span>
            </button>
            <button type="button" class="qzu-btn" x-show="name && ! busy" x-cloak @click="clear()">Chọn file khác</button>
            <a href="{{ asset('templates/mau-cau-hoi.zip') }}" download="MAU-CAU-HOI.zip" class="qzu-btn qzu-btn--tpl"><x-lucide name="download" /> Tải file mẫu</a>
        </div>
    </form>

    <div class="qzu-steps">
        <div class="qzu-step">
            <span class="qzu-step__n">1</span>
            <div><b>Tải file mẫu</b><p>Giải nén MAU-CAU-HOI.zip, đọc HUONG-DAN.txt bên trong.</p></div>
        </div>
        <div class="qzu-step">
            <span class="qzu-step__n">2</span>
            <div><b>Sửa nội dung</b><p>Đổi <code>content.type</code>, đáp án trong question.json; thay statement.pdf bằng đề của bạn.</p></div>
        </div>
        <div class="qzu-step">
            <span class="qzu-step__n">3</span>
            <div><b>Nén lại &amp; tải lên</b><p>Nhập xong mở {{ $qzuReview }} để rà đề, đáp án, tag… rồi bấm Lưu.</p></div>
        </div>
    </div>
</div>
