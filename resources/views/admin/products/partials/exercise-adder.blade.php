{{-- SỬA 14/10 (khách: "bỏ nút Thêm thủ công; cho chọn dạng: Trắc nghiệm 4 đáp án chọn 1, Trắc nghiệm đúng sai, Lập trình,
     Điền từ — Lập trình thì chọn file ZIP, các dạng còn lại nhập thủ công; cho chọn chương") — khối thêm bài tập ngay trên
     trang chi tiết tài liệu, không phải sang trang khác.

     Bước: (1) chọn chương → (2) chọn dạng → (3) Lập trình: chọn gói ZIP · dạng khác: nhập nội dung rồi Lưu.
     ZIP -> admin.products.exercises.store (mở màn kiểm tra lại như cũ); nhập tay -> admin.products.exercises.storeManual.
     Hai form cùng một trang nên có ô ẩn _form để old() chỉ đổ lại dữ liệu vào đúng form vừa gửi lỗi.

     Tham số: $product, $chapters, $chapterLabel. --}}
@include('admin.products.partials.adder-style')
@php
    $exIsMine = old('_form') === 'exercise';
    $exOld = fn (string $key, $default = null) => $exIsMine ? old($key, $default) : $default;
    $exKind = $exIsMine && in_array(old('type'), ['mcq', 'true_false', 'fill_blank'], true)
        ? old('type')
        : ($errors->has('zip_package') ? 'coding' : 'mcq');
    $exKinds = [
        'mcq' => ['Trắc nghiệm', '4 đáp án · chọn 1 đáp án đúng', 'list-checks'],
        'true_false' => ['Trắc nghiệm Đúng / Sai', '1 nhận định · chọn Đúng hoặc Sai', 'check-circle-2'],
        'coding' => ['Lập trình', 'Nhập gói ZIP có đề + test case', 'code-2'],
        'fill_blank' => ['Điền từ', 'Học sinh gõ đáp án vào ô trống', 'text-cursor-input'],
    ];
    $exChapterLower = $chapterLabel ? mb_strtolower($chapterLabel) : null;
    $exOptions = array_pad((array) $exOld('options', []), 4, '');
@endphp

<div class="oi-add"
     x-data="{ kind: @js($exKind), chapter: @js((string) ($exIsMine ? old('material_id', '') : ($errors->has('zip_package') ? old('material_id', '') : ''))),
               zipName: '', correct: @js((string) $exOld('correct_option', '')), tf: @js((string) $exOld('tf_answer', '')) }">

    @if ($exIsMine && $errors->any())
        <p class="oi-add__err" role="alert">{{ implode(' ', $errors->all()) }}</p>
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
        <span class="oi-add__lbl">{{ $chapterLabel ? '2.' : '1.' }} Dạng bài</span>
        <div class="oi-kinds" role="radiogroup" aria-label="Dạng bài tập">
            @foreach ($exKinds as $k => [$kLabel, $kDesc, $kIcon])
                <button type="button" role="radio" class="oi-kind" :class="kind === '{{ $k }}' ? 'is-on' : ''"
                        :aria-checked="kind === '{{ $k }}' ? 'true' : 'false'" @click="kind = '{{ $k }}'">
                    <span class="oi-kind__ic"><x-lucide name="{{ $kIcon }}" /></span>
                    <span><b>{{ $kLabel }}</b><small>{{ $kDesc }}</small></span>
                </button>
            @endforeach
        </div>
    </div>

    {{-- ── Lập trình: nhập gói ZIP ── --}}
    <form x-show="kind === 'coding'" x-cloak action="{{ route('admin.products.exercises.store', $product->id) }}" method="POST"
          enctype="multipart/form-data" class="oi-panel">
        @csrf
        <input type="hidden" name="_form" value="exercise-zip">
        <input type="hidden" name="material_id" :value="chapter">
        <label class="oi-drop" :class="zipName ? 'has-file' : ''">
            <x-lucide name="upload" />
            <span>
                <b x-text="zipName ? 'Đã chọn gói ZIP' : 'Bấm để chọn gói ZIP bài lập trình'">Bấm để chọn gói ZIP bài lập trình</b>
                <small x-text="zipName || 'Định dạng OT360-QPACK — hệ thống tự đọc đề bài + test case.'">Định dạng OT360-QPACK — hệ thống tự đọc đề bài + test case.</small>
            </span>
            <input type="file" name="zip_package" accept=".zip" class="oi-sr" :required="kind === 'coding'"
                   @change="zipName = ($event.target.files[0] && $event.target.files[0].name) || ''">
        </label>
        <div class="oi-add__actions">
            <button type="submit" class="acx-btn acx-btn--primary acx-btn--sm" :disabled="!zipName"><x-lucide name="upload" /> Nhập từ ZIP</button>
            <span class="oi-add__note" style="margin:0">Đọc xong sẽ mở màn kiểm tra lại — bấm "Lưu bài tập" ở đó để hoàn tất.</span>
        </div>
    </form>

    {{-- ── Trắc nghiệm / Đúng-Sai / Điền từ: nhập tay ── --}}
    <form x-show="kind !== 'coding'" action="{{ route('admin.products.exercises.storeManual', $product->id) }}" method="POST" class="oi-panel">
        @csrf
        <input type="hidden" name="_form" value="exercise">
        <input type="hidden" name="type" :value="kind">
        <input type="hidden" name="material_id" :value="chapter">

        <div class="oi-add__row oi-add__row--pts">
            <div>
                <label class="oi-add__lbl" for="ex-title">Tên bài tập</label>
                <input id="ex-title" name="title" type="text" maxlength="255" required value="{{ $exOld('title') }}"
                       class="oi-add__input" placeholder="VD: Câu 1 — Kiểu dữ liệu số nguyên">
            </div>
            <div>
                <label class="oi-add__lbl" for="ex-points">Điểm</label>
                <input id="ex-points" name="points" type="number" min="0" value="{{ $exOld('points', 1) }}" class="oi-add__input">
            </div>
        </div>

        <div>
            <label class="oi-add__lbl" for="ex-body">
                <span x-text="kind === 'true_false' ? 'Nhận định cần đánh giá Đúng / Sai' : (kind === 'fill_blank' ? 'Đề bài (ghi rõ chỗ cần điền, VD: ___ )' : 'Câu hỏi')">Câu hỏi</span>
            </label>
            <textarea id="ex-body" name="body" rows="3" maxlength="20000" class="oi-add__input"
                      :placeholder="kind === 'true_false' ? 'VD: Trong Python, kiểu int có giới hạn giá trị tối đa.' : (kind === 'fill_blank' ? 'VD: Từ khoá dùng để định nghĩa hàm trong Python là ___.' : 'VD: Kết quả của biểu thức 7 // 2 trong Python là?')">{{ $exOld('body') }}</textarea>
        </div>

        {{-- Trắc nghiệm 4 đáp án --}}
        <div x-show="kind === 'mcq'">
            <span class="oi-add__lbl">Đáp án <small>— bấm "Đúng" ở đáp án đúng</small></span>
            <div class="oi-opts">
                @foreach (['A', 'B', 'C', 'D'] as $i => $letter)
                    <div class="oi-opt" :class="correct === '{{ $i }}' ? 'is-correct' : ''">
                        <label class="oi-opt__pick">
                            <input type="radio" name="correct_option" value="{{ $i }}" x-model="correct" :disabled="kind !== 'mcq'">
                            <span x-text="correct === '{{ $i }}' ? '✓ Đúng' : 'Đúng'">Đúng</span>
                        </label>
                        <b style="width:18px;color:#123B68">{{ $letter }}.</b>
                        <input name="options[{{ $i }}]" type="text" maxlength="255" value="{{ $exOptions[$i] ?? '' }}"
                               class="oi-add__input" placeholder="Nội dung đáp án {{ $letter }}" :required="kind === 'mcq'" :disabled="kind !== 'mcq'">
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Đúng / Sai --}}
        <div x-show="kind === 'true_false'" x-cloak>
            <span class="oi-add__lbl">Đáp án đúng của nhận định</span>
            <div class="oi-tf" role="radiogroup">
                <label :class="tf === 'true' ? 'is-on-true' : ''"><input type="radio" name="tf_answer" value="true" x-model="tf" :disabled="kind !== 'true_false'"> ✓ Đúng</label>
                <label :class="tf === 'false' ? 'is-on-false' : ''"><input type="radio" name="tf_answer" value="false" x-model="tf" :disabled="kind !== 'true_false'"> ✕ Sai</label>
            </div>
        </div>

        {{-- Điền từ --}}
        <div x-show="kind === 'fill_blank'" x-cloak>
            <label class="oi-add__lbl" for="ex-accepted">Đáp án được chấp nhận <small>— mỗi dòng một cách viết đúng</small></label>
            <textarea id="ex-accepted" name="accepted_answers" rows="3" maxlength="2000" class="oi-add__input"
                      placeholder="def&#10;DEF" :required="kind === 'fill_blank'" :disabled="kind !== 'fill_blank'">{{ $exOld('accepted_answers') }}</textarea>
            <label class="oi-check" style="margin-top:8px">
                <input type="checkbox" name="case_sensitive" value="1" @checked($exOld('case_sensitive')) :disabled="kind !== 'fill_blank'"> Phân biệt chữ hoa / chữ thường
            </label>
        </div>

        <div class="oi-add__actions">
            <button type="submit" class="acx-btn acx-btn--primary acx-btn--sm"><x-lucide name="save" /> Lưu bài tập</button>
            <span class="oi-add__note" style="margin:0">Lưu xong bài hiện ngay trong danh sách bên dưới; khung này để trống cho bài tiếp theo.</span>
        </div>
    </form>
</div>
