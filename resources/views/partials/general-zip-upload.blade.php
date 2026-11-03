{{--
    SỬA 3/11 (khách: "chỗ up file ZIP đổi thành 2 dạng Lập trình / Phổ thông; Phổ thông = 1 bài nhiều câu trắc nghiệm, điền từ…;
    lập trình giữ nguyên") — ô nhập gói ZIP "Bài phổ thông" (App\Http\Controllers\GeneralSetController). Gói ĐÚNG khuôn
    MAU-CAU-HOI (question.json + statement.pdf + solution.pdf + assets/) với content.type = "general" và content.questions.
    Tham số: $gzAction (route nhận gói), $gzNote (dòng nói bài tạo ra sẽ nằm ở đâu). CSS tiền tố gz-.
--}}
<style>
    .gz-box { display: flex; flex-wrap: wrap; align-items: center; gap: 14px; padding: 16px 18px; border: 1.5px dashed #A5B4FC; border-radius: 18px; background: linear-gradient(135deg, #EEF2FF, #F5F3FF); }
    .gz-box.is-drag { border-color: #6366F1; background: #E0E7FF; }
    .gz-ic { display: grid; place-items: center; width: 44px; height: 44px; flex: none; border-radius: 13px; background: #6366F1; color: #fff; }
    .gz-ic svg { width: 22px; height: 22px; }
    .gz-body { flex: 1 1 320px; min-width: 0; }
    .gz-body strong { display: block; font-size: 14px; font-weight: 800; color: #312E81; }
    .gz-body p { margin: 3px 0 0; font-size: 12.5px; line-height: 1.5; color: #4C4F7A; }
    .gz-types { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
    .gz-type { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 999px; border: 1px solid #C7D2FE; background: #fff; font-size: 12px; font-weight: 700; color: #4338CA; }
    .gz-type svg { width: 13px; height: 13px; }
    .gz-tpl { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 999px; border: 1px dashed #818CF8; font-size: 12px; font-weight: 800; color: #4338CA; text-decoration: none; }
    .gz-tpl:hover { background: #fff; } .gz-tpl svg { width: 13px; height: 13px; }
    .gz-btn { display: inline-flex; align-items: center; gap: 7px; height: 42px; padding: 0 18px; border-radius: 12px; background: #4F46E5; color: #fff; font-size: 13px; font-weight: 800; cursor: pointer; box-shadow: 0 8px 18px rgba(79, 70, 229, .25); }
    .gz-btn svg { width: 16px; height: 16px; }
    .gz-err { width: 100%; margin: 0; padding: 10px 12px; border-radius: 12px; border: 1px solid #FECACA; background: #FEF2F2; font-size: 12.5px; color: #B91C1C; }
    .gz-err li { margin-left: 16px; list-style: disc; }
    .gz-box details { margin-top: 6px; font-size: 12px; color: #4C4F7A; }
    .gz-box details summary { cursor: pointer; font-weight: 700; color: #4338CA; }
    .gz-box code { padding: 0 4px; border-radius: 4px; background: #fff; font-size: 11.5px; }
</style>
<form method="POST" action="{{ $gzAction }}" enctype="multipart/form-data"
      x-data="{ submitting: false, drag: false }" class="gz-box" :class="drag && 'is-drag'"
      @dragover.prevent="drag = true" @dragleave.prevent="drag = false"
      @drop.prevent="drag = false; if ($event.dataTransfer.files.length) { $refs.gz.files = $event.dataTransfer.files; submitting = true; $el.requestSubmit(); }">
    @csrf
    <span class="gz-ic"><x-lucide name="list-checks" /></span>
    <div class="gz-body">
        <strong>Nhập BÀI PHỔ THÔNG từ gói ZIP — 1 bài nhiều câu</strong>
        <p>Đúng khuôn gói mẫu cũ (question.json + statement.pdf + solution.pdf), chỉ khác <code>"type": "general"</code> và danh sách câu
            <code>"questions"</code>. Mỗi câu tự khai dạng. Học sinh làm cả bài, <b>nộp mới chấm</b>, xong xem đáp án + lời giải. {{ $gzNote ?? '' }}</p>
        <div class="gz-types">
            <span class="gz-type"><x-lucide name="list-checks" /> Trắc nghiệm</span>
            <span class="gz-type"><x-lucide name="check-circle-2" /> Đúng / Sai</span>
            <span class="gz-type"><x-lucide name="text-cursor-input" /> Điền từ</span>
            <span class="gz-type"><x-lucide name="layers" /> Câu nhiều ý</span>
            <a href="{{ asset('templates/mau-pho-thong.zip') }}" download="MAU-BAI-PHO-THONG.zip" class="gz-tpl"><x-lucide name="download" /> Tải file mẫu Phổ thông</a>
        </div>
        <details>
            <summary>Cách soạn gói</summary>
            <p>Tên file ZIP = mã bài. <code>statement.pdf</code> = đề cả bài (học sinh xem ở tab "Đề bài PDF"), <code>solution.pdf</code> = lời giải
                cả bài (hiện sau khi nộp, không bắt buộc). Mỗi câu trong <code>questions</code>: <code>no</code>, <code>type</code>
                (single_choice / true_false / short_answer / composite), <code>points</code>, <code>grading</code> (y hệt gói 1 câu), tuỳ chọn
                <code>prompt</code> (nội dung câu bằng chữ), <code>explanation</code> (lời giải ngắn), <code>assets</code> (ảnh). Gói 1 câu cũ nhập ở đây
                thành bài 1 câu. Bài lập trình nhập ở mục "Lập trình / câu lẻ". Xem HUONG-DAN.txt trong file mẫu.</p>
        </details>
    </div>
    <input id="general_zip" name="general_zip" type="file" accept=".zip,application/zip" required x-ref="gz" style="display:none"
           @change="submitting = true; $el.form.requestSubmit()" :disabled="submitting">
    <label for="general_zip" class="gz-btn" :style="submitting && 'opacity:.6;pointer-events:none'">
        <x-lucide name="upload" /><span x-text="submitting ? 'Đang xử lý…' : 'Nhập bài phổ thông'">Nhập bài phổ thông</span>
    </label>
    @if ($errors->has('general_zip'))
        <ul class="gz-err">@foreach ($errors->get('general_zip') as $msg)<li>{{ $msg }}</li>@endforeach</ul>
    @endif
</form>
