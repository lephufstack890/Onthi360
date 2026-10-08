@extends('layouts.admin')

@section('title', 'Sửa tài liệu')
@section('page-title', 'Sửa tài liệu')

@section('content')
    @php $types = $types ?? []; $visibilities = $visibilities ?? []; $statuses = $statuses ?? []; $grades = $grades ?? []; @endphp

    {{-- SỬA 8/10 — đổi giao diện theo source mới (AdminContentWorkspace.jsx: thẻ "Thông tin chung" + thẻ
         "Thiết lập hiển thị"); tên field, route, validation, thứ tự gửi form giữ nguyên. Hiển thị/Trạng thái
         nằm ở thẻ bên phải nhưng vẫn là ô của CÙNG một form. --}}
    @include('partials.admin-products-ui')

    <div class="acx-wrap">
        <a href="{{ route('admin.products.show', $product->id) }}" class="acx-back">‹ Quay lại chi tiết</a>

        <div class="acx-head">
            <div class="acx-head__id">
                <span class="acx-avatar"><x-lucide name="pencil" /></span>
                <div>
                    <h1>Sửa tài liệu</h1>
                    <div class="acx-head__meta"><span>{{ $product->title }}</span></div>
                </div>
            </div>
        </div>

        @if ($errors->any())
            @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
        @endif

        <div class="apx-editor">
            <form method="POST" action="{{ route('admin.products.update', $product->id) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <section class="acx-card acx-card--white apx-editor__main">
                    <h2><x-lucide name="book-open" /> Thông tin chung</h2>
                    <div class="apx-fields">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="apx-lbl" for="title">Tên tài liệu</label>
                                <input id="title" name="title" type="text" value="{{ old('title', $product->title) }}" required maxlength="255"
                                       class="admin-input">
                            </div>
                            <div>
                                <label class="apx-lbl" for="type">Loại tài liệu</label>
                                <x-ws.select id="type" name="type" required>
                                    @foreach ($types as $value => $label)
                                        <option value="{{ $value }}" @selected(old('type', $product->type->value) === $value)>{{ $label }}</option>
                                    @endforeach
                                </x-ws.select>
                            </div>
                        </div>

                        <div>
                            <label class="apx-lbl" for="cover_image">Ảnh bìa (tùy chọn)</label>
                    @if ($product->cover_image_path)
                        <div class="apx-cover">
                            <img src="{{ asset('storage/'.$product->cover_image_path) }}" alt="Ảnh bìa hiện tại">
                            <p class="apx-note" style="margin:0">Ảnh hiện tại — chọn ảnh mới bên dưới để thay thế.</p>
                        </div>
                    @endif
                            <input id="cover_image" name="cover_image" type="file" accept="image/*" class="admin-input apx-file">
                            <p class="apx-note">Ảnh JPG/PNG/WebP, tối đa 4MB. Để trống nếu không đổi ảnh.</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="apx-lbl" for="subject">Môn học</label>
                                <input id="subject" name="subject" type="text" value="{{ old('subject', $product->subject) }}" maxlength="60"
                                       class="admin-input">
                            </div>
                            <div>
                                <label class="apx-lbl" for="grade">Khối lớp</label>
                                <x-ws.select id="grade" name="grade" icon="🎓">
                                    <option value="">— Không chỉ định —</option>
                                    @foreach ($grades ?? [] as $g)
                                        <option value="{{ $g }}" @selected(old('grade', $product->grade) === $g)>{{ $g }}</option>
                                    @endforeach
                                </x-ws.select>
                            </div>
                            <div>
                                <label class="apx-lbl" for="topic">Chuyên đề</label>
                                <input id="topic" name="topic" type="text" value="{{ old('topic', $product->topic) }}" maxlength="120"
                                       class="admin-input">
                            </div>
                        </div>

                        <div>
                            <label class="apx-lbl" for="description">Mô tả</label>
                            <textarea id="description" name="description" rows="5" maxlength="5000" data-rich-editor
                                      class="admin-input">{{ old('description', $product->description) }}</textarea>
                        </div>

                        <div class="apx-section grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="apx-lbl" for="price">Giá để học (đ)</label>
                                <input id="price" name="price" type="number" min="0" value="{{ old('price', $product->price) }}" required
                                       class="admin-input">
                            </div>
                            <div>
                                <label class="apx-lbl" for="price_teaching">Giá để dạy (đ)</label>
                                <input id="price_teaching" name="price_teaching" type="number" min="0" value="{{ old('price_teaching', $product->price_teaching) }}" required
                                       class="admin-input">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="apx-lbl" for="duration_months">Thời hạn quyền (tháng)</label>
                                <input id="duration_months" name="duration_months" type="number" min="1" value="{{ old('duration_months', $product->duration_months) }}"
                                   placeholder="Để trống = không giới hạn"
                                       class="admin-input">
                            </div>
                            <div class="flex items-end pb-2.5">
                                <label class="flex items-center gap-2 text-[13px] text-slate-600">
                                    <input type="checkbox" name="has_print_option" value="1" @checked(old('has_print_option', $product->has_print_option))> Có bản in
                                </label>
                            </div>
                        </div>

                        {{-- SỬA 29/9 (khách chốt: "bỏ file pdf sách đi, chỗ chương mỗi chương là thêm từng
                             file pdf") — ĐÃ BỎ ô "File PDF" (tệp tổng của cả sản phẩm). Nội dung đọc giờ
                             tải theo TỪNG chương/phần/đề ở trang chi tiết sản phẩm, người học đọc liền mạch
                             các tệp đó (xem App\Services\ProductReadService). Ô "PDF hướng dẫn" giữ nguyên
                             — đó là giáo án cho giáo viên, không phải nội dung để học sinh đọc. --}}
                        <div class="apx-section">
                            <label class="apx-lbl" for="guide_pdf">File PDF hướng dẫn</label>
                            <input id="guide_pdf" name="guide_pdf" type="file" accept="application/pdf" class="admin-input apx-file">
                            <p class="apx-note {{ $product->guide_pdf_path ? 'apx-ok' : '' }}">
                                {{ $product->guide_pdf_path ? '✓ Đã có: '.$product->guide_pdf_original_name : 'Chưa có — PDF tối đa 50MB' }}
                            </p>
                        </div>
                    </div>
                </section>

                <section class="acx-card acx-card--mint">
                    <h3><x-lucide name="target" /> Thiết lập hiển thị</h3>
                    <div class="apx-fields">
                        <div>
                            <label class="apx-lbl" for="visibility">Hiển thị</label>
                            <x-ws.select id="visibility" name="visibility" required>
                                @foreach ($visibilities as $value => $label)
                                    <option value="{{ $value }}" @selected(old('visibility', $product->visibility->value) === $value)>{{ $label }}</option>
                                @endforeach
                            </x-ws.select>
                        </div>
                        <div>
                            <label class="apx-lbl" for="status">Trạng thái</label>
                            <x-ws.select id="status" name="status" required>
                                @foreach ($statuses as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', $product->status->value) === $value)>{{ $label }}</option>
                                @endforeach
                            </x-ws.select>
                        </div>
                        <div>
                            <button type="submit" class="acx-btn acx-btn--primary apx-save">Lưu thay đổi</button>
                            <a href="{{ route('admin.products.show', $product->id) }}" class="acx-btn apx-save">Huỷ</a>
                        </div>
                    </div>
                </section>
            </form>

        <div class="acx-danger" x-data="{ open: false, reason: '' }">
            <h3><x-lucide name="alert-triangle" /> Xóa tài liệu</h3>
            <p>Xóa mềm — quyền truy cập đã cấp trước đó vẫn còn dữ liệu để tra cứu. Bắt buộc nêu lý do (10.4).</p>
            <button type="button" @click="open = !open" class="acx-danger__toggle" x-text="open ? 'Đóng' : 'Tôi muốn xóa tài liệu này'"></button>
            <form x-show="open" x-cloak method="POST" action="{{ route('admin.products.destroy', $product->id) }}" onsubmit="return confirm('Xác nhận xóa tài liệu này?');">
                @csrf
                @method('DELETE')
                <div>
                    <label style="display:block;font-size:13px;color:#475569">Lý do xóa (bắt buộc)</label>
                    <textarea name="reason" x-model="reason" rows="3" required class="admin-input" placeholder="Nêu rõ lý do..."></textarea>
                </div>
                <button type="submit" :disabled="reason.trim().length === 0" class="acx-btn acx-btn--danger" style="width:100%;margin-top:10px" :style="reason.trim().length === 0 ? 'opacity:.4;cursor:not-allowed' : ''">Xác nhận xóa</button>
            </form>
        </div>
        </div>
    </div>

    @push('scripts')
        @include('partials.rich-editor-assets')
    @endpush
@endsection
