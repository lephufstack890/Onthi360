{{-- SỬA 14/10 (khách: "chỗ thêm học liệu cho chọn chương, thiết kế dễ nhìn dễ dùng") — thêm học liệu NGAY trên trang chi
     tiết tài liệu: chọn chương, đặt tên, chọn tệp PDF / audio / ảnh (có thể cả 3), bấm Thêm. Gửi thẳng tới
     admin.content.materials.store (cùng luật kiểm tra như form đầy đủ); vẫn giữ đường sang form đầy đủ.

     Tham số: $product, $chapters, $chapterLabel. --}}
@include('admin.products.partials.adder-style')
@php
    $mtIsMine = old('_form') === 'material';
    $mtChapterLower = $chapterLabel ? mb_strtolower($chapterLabel) : 'chương';
@endphp

<form action="{{ route('admin.content.materials.store') }}" method="POST" enctype="multipart/form-data" class="oi-add"
      x-data="{ pdf: '', audio: '', image: '', pick(kind, e) { this[kind] = (e.target.files[0] && e.target.files[0].name) || ''; } }">
    @csrf
    <input type="hidden" name="_form" value="material">
    <input type="hidden" name="product_id" value="{{ $product->id }}">
    <input type="hidden" name="type" value="section">
    <input type="hidden" name="status" value="published">

    @if ($mtIsMine && $errors->any())
        <p class="oi-add__err" role="alert">{{ implode(' ', $errors->all()) }}</p>
    @endif

    <div class="oi-add__row oi-add__row--2">
        <div>
            <label class="oi-add__lbl" for="mt-chapter">Thuộc {{ $mtChapterLower }}</label>
            <select id="mt-chapter" name="parent_id" class="oi-add__input">
                <option value="">— Chưa gắn {{ $mtChapterLower }} —</option>
                @foreach ($chapters as $c)
                    <option value="{{ $c['id'] }}" @selected($mtIsMine && (string) old('parent_id') === (string) $c['id'])>{{ $c['title'] }}</option>
                @endforeach
            </select>
            @if (empty($chapters))
                <p class="oi-add__note">Chưa có {{ $mtChapterLower }} nào — thêm ở khối "{{ $chapterLabel }}" phía trên.</p>
            @endif
        </div>
        <div>
            <label class="oi-add__lbl" for="mt-title">Tên học liệu</label>
            <input id="mt-title" name="title" type="text" maxlength="255" required value="{{ $mtIsMine ? old('title') : '' }}"
                   class="oi-add__input" placeholder="VD: Bài giảng — Vòng lặp for">
        </div>
    </div>

    <div>
        <span class="oi-add__lbl">Tệp đính kèm <small>— chọn 1, 2 hoặc cả 3 loại</small></span>
        <div class="oi-files">
            <label class="oi-drop" :class="pdf ? 'has-file' : ''">
                <x-lucide name="file-text" />
                <span><b>PDF</b><small x-text="pdf || 'Bấm để chọn tệp .pdf'">Bấm để chọn tệp .pdf</small></span>
                <input type="file" name="pdf" accept="application/pdf" class="oi-sr" @change="pick('pdf', $event)">
            </label>
            <label class="oi-drop" :class="audio ? 'has-file' : ''">
                <x-lucide name="headphones" />
                <span><b>Audio</b><small x-text="audio || 'mp3, wav, ogg, m4a, aac'">mp3, wav, ogg, m4a, aac</small></span>
                <input type="file" name="audio" accept=".mp3,.wav,.ogg,.m4a,.aac,audio/*" class="oi-sr" @change="pick('audio', $event)">
            </label>
            <label class="oi-drop" :class="image ? 'has-file' : ''">
                <x-lucide name="image" />
                <span><b>Ảnh / ảnh động</b><small x-text="image || 'jpg, png, gif, webp'">jpg, png, gif, webp</small></span>
                <input type="file" name="image" accept=".jpg,.jpeg,.png,.gif,.webp,image/*" class="oi-sr" @change="pick('image', $event)">
            </label>
        </div>
    </div>

    <div class="oi-add__actions">
        <button type="submit" class="acx-btn acx-btn--primary acx-btn--sm"><x-lucide name="upload" /> Thêm học liệu</button>
        <a href="{{ route('admin.content.materials.create', ['product_id' => $product->id]) }}" class="acx-link">Mở form đầy đủ (mã bài, thứ tự…) ›</a>
    </div>
</form>
