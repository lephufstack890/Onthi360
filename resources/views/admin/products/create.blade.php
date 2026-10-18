@extends('layouts.admin')

@php
    // SỬA 18/10 — mở từ nút "Thêm sách / Thêm chuyên đề / Thêm bộ đề" (?type=) thì tiêu đề theo đúng loại.
    $createType = old('type', request('type'));
    $createHeading = ['book' => 'Thêm sách', 'topic' => 'Thêm chuyên đề', 'exam' => 'Thêm bộ đề'][is_string($createType) ? $createType : ''] ?? 'Tạo tài liệu';
@endphp
@section('title', $createHeading)
@section('page-title', $createHeading)

@section('content')
    @php $types = $types ?? []; $visibilities = $visibilities ?? []; $statuses = $statuses ?? []; $grades = $grades ?? []; @endphp

    {{-- SỬA 8/10 — đổi giao diện theo source mới (AdminContentWorkspace.jsx: thẻ "Thông tin chung" + thẻ
         "Thiết lập hiển thị"); tên field, route, validation, thứ tự gửi form giữ nguyên. Hiển thị/Trạng thái
         nằm ở thẻ bên phải nhưng vẫn là ô của CÙNG một form. --}}
    @include('partials.admin-products-ui')

    <div class="acx-wrap">
        <a href="{{ route('admin.products.index') }}" class="acx-back">‹ Quay lại Tài liệu</a>

        <div class="acx-head">
            <div class="acx-head__id">
                <span class="acx-avatar"><x-lucide name="wallet-cards" /></span>
                <div>
                    <h1>{{ $createHeading }}</h1>
                    <div class="acx-head__meta"><span>Tài liệu là thứ được bán/cấp quyền: sách, chuyên đề, bộ đề (5.1).</span></div>
                </div>
            </div>
        </div>

        @if ($errors->any())
            @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
        @endif

        <div class="apx-editor">
            <form method="POST" action="{{ route('admin.products.store') }}" enctype="multipart/form-data">
                @csrf
                <section class="acx-card acx-card--white apx-editor__main">
                    <h2><x-lucide name="book-open" /> Thông tin chung</h2>
                    <div class="apx-fields">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="apx-lbl" for="title">Tên tài liệu</label>
                                <input id="title" name="title" type="text" value="{{ old('title') }}" required maxlength="255"
                                   placeholder="Ví dụ: Sách luyện thi Tin học 10"
                                       class="admin-input">
                            </div>
                            <div>
                                <label class="apx-lbl" for="type">Loại tài liệu</label>
                                <x-ws.select id="type" name="type" required>
                                    @foreach ($types as $value => $label)
                                        <option value="{{ $value }}" @selected(old('type', in_array(request('type'), ['book', 'topic', 'exam'], true) ? request('type') : 'book') === $value)>{{ $label }}</option>
                                    @endforeach
                                </x-ws.select>
                            </div>
                        </div>

                        @include('admin.products._cover-catalog')

                        {{-- SỬA 14/10 (khách) — bỏ ô "Chuyên đề" (giá trị cũ trong CSDL vẫn giữ, không bị xoá). --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="apx-lbl" for="subject">Môn học</label>
                                <input id="subject" name="subject" type="text" value="{{ old('subject') }}" maxlength="60"
                                       class="admin-input">
                            </div>
                            @include('partials.product-grade-field', ['product' => null])
                        </div>

                        <div>
                            <label class="apx-lbl" for="description">Mô tả</label>
                            <textarea id="description" name="description" rows="5" maxlength="5000" data-rich-editor
                                      class="admin-input">{{ old('description') }}</textarea>
                        </div>

                        {{-- SỬA 14/10 (khách: "gộp giá để học và giá để dạy thành 1 giá; thời hạn quyền mặc định 1 tháng; kick Có bản
                             in thì thêm tiền bản in, giá + giá bản in ra giá chính") — một ô Giá; Thời hạn quyền điền sẵn 1 (để trống
                             cũng lưu 1); bật "Có bản in" thì hiện ô Giá bản in và dòng Giá chính = Giá + Giá bản in (người mua chọn
                             kèm bản in ở trang đặt đơn thì trả đúng số này). --}}
                        <div class="apx-section grid grid-cols-1 sm:grid-cols-2 gap-4"
                             x-data="{ price: {{ (int) old('price', 0) }}, printOn: {{ ((bool) old('has_print_option')) ? 'true' : 'false' }}, printPrice: {{ (int) old('print_price', 0) }},
                                       money(n) { return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 }).format((Number(n) || 0) / {{ \App\Support\TokenPrice::rate() }}) + ' token'; } }">
                            <div>
                                {{-- SỬA 18/10 — nhập giá bằng đồng như cũ; ngoài web hiển thị bằng token (1.000đ = 1 token). --}}
                                <label class="apx-lbl" for="price">Giá (đ) <span style="font-weight:600;color:#2f8a6b" x-text="'= ' + money(price)"></span></label>
                                <input id="price" name="price" type="number" min="0" value="{{ (int) old('price', 0) }}" required
                                       x-model.number="price" class="admin-input">
                            </div>
                            <div>
                                <label class="apx-lbl" for="duration_months">Thời hạn quyền (tháng)</label>
                                <input id="duration_months" name="duration_months" type="number" min="1" value="{{ old('duration_months', 1) }}"
                                       placeholder="Mặc định 1 tháng" class="admin-input">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="flex items-center gap-2 text-[13px] text-slate-600">
                                    <input type="checkbox" name="has_print_option" value="1" x-model="printOn" @checked((bool) old('has_print_option'))> Có bản in
                                </label>
                            </div>
                            <div x-show="printOn" x-cloak>
                                <label class="apx-lbl" for="print_price">Giá bản in (đ) <span style="font-weight:600;color:#2f8a6b" x-text="'= ' + money(printPrice)"></span></label>
                                <input id="print_price" name="print_price" type="number" min="0" value="{{ (int) old('print_price', 0) }}"
                                       x-model.number="printPrice" :disabled="!printOn" class="admin-input">
                            </div>
                            <div x-show="printOn" x-cloak class="flex flex-col justify-end">
                                <p class="apx-lbl">Giá chính (giá + bản in)</p>
                                <p class="admin-input" style="display:flex;align-items:center;font-weight:800;color:#123B68;background:#F4F9FB" x-text="money(price + printPrice)">{{ number_format((int) old('price', 0) + (int) old('print_price', 0)) }}đ</p>
                            </div>
                        </div>

                        {{-- SỬA 9/10 (khách: "check UI source mới trang tài liệu, thiếu field thì bổ sung") — 4 trường mà thẻ tài
                             liệu của bản mẫu mới hiển thị / lọc theo: độ khó, tác giả, đánh giá (nhập tay). --}}
                        <div class="apx-section grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="apx-lbl" for="difficulty_level">Độ khó</label>
                                <select id="difficulty_level" name="difficulty_level" class="admin-input">
                                    <option value="">— Chưa xếp độ khó —</option>
                                    @foreach ([1 => 'Cơ bản', 2 => 'Dễ', 3 => 'Trung bình', 4 => 'Khó', 5 => 'Nâng cao'] as $dlValue => $dlLabel)
                                        <option value="{{ $dlValue }}" @selected((string) old('difficulty_level') === (string) $dlValue)>{{ $dlValue }} sao · {{ $dlLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="apx-lbl" for="author_name">Tác giả</label>
                                <input id="author_name" name="author_name" type="text" maxlength="150" value="{{ old('author_name') }}"
                                       placeholder="VD: Thầy Nguyễn Tiến Thành & Ban Chuyên môn"
                                       class="admin-input">
                            </div>
                            <div>
                                <label class="apx-lbl" for="rating_score">Điểm đánh giá (0–5)</label>
                                <input id="rating_score" name="rating_score" type="number" min="0" max="5" step="0.1" value="{{ old('rating_score') }}"
                                       placeholder="VD: 4.8" class="admin-input">
                            </div>
                            <div>
                                <label class="apx-lbl" for="rating_count">Số lượt đánh giá</label>
                                <input id="rating_count" name="rating_count" type="number" min="0" value="{{ old('rating_count') }}"
                                       placeholder="VD: 124" class="admin-input">
                                <p class="mt-1 text-[11px] text-slate-400">Nhập cả điểm và số lượt thì thẻ tài liệu mới hiện sao; số này được gộp với đánh giá thật của người đọc.</p>
                            </div>
                        </div>

                        {{-- SỬA 29/9 (khách chốt: "bỏ file pdf sách đi, chỗ chương mỗi chương là thêm từng
                             file pdf") — ĐÃ BỎ ô "File PDF" (tệp tổng của cả sản phẩm). Nội dung đọc giờ
                             tải theo TỪNG chương/phần/đề ở trang chi tiết sản phẩm, người học đọc liền mạch
                             các tệp đó (xem App\Services\ProductReadService). Ô "PDF hướng dẫn" giữ nguyên
                             — đó là giáo án cho giáo viên, không phải nội dung để học sinh đọc. --}}
                        {{-- SỬA 14/10 (khách: "ẩn field file hướng dẫn") — đã bỏ ô "File PDF hướng dẫn"; tệp cũ (nếu có) vẫn giữ nguyên. --}}
                    </div>
                </section>

                <section class="acx-card acx-card--mint">
                    <h3><x-lucide name="target" /> Thiết lập hiển thị</h3>
                    <div class="apx-fields">
                        <div>
                            <label class="apx-lbl" for="visibility">Hiển thị</label>
                            <x-ws.select id="visibility" name="visibility" required>
                                @foreach ($visibilities as $value => $label)
                                    <option value="{{ $value }}" @selected(old('visibility', 'public') === $value)>{{ $label }}</option>
                                @endforeach
                            </x-ws.select>
                        </div>
                        <div>
                            <label class="apx-lbl" for="status">Trạng thái</label>
                            <x-ws.select id="status" name="status" required>
                                @foreach ($statuses as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', 'draft') === $value)>{{ $label }}</option>
                                @endforeach
                            </x-ws.select>
                        </div>
                        <div>
                            <button type="submit" class="acx-btn acx-btn--primary apx-save">Tạo tài liệu</button>
                            <a href="{{ route('admin.products.index') }}" class="acx-btn apx-save">Huỷ</a>
                        </div>
                    </div>
                </section>
            </form>

        <div class="acx-card acx-card--mint">
            <h3><x-lucide name="sparkles" /> Cần biết</h3>
            <div class="acx-tips">
                <div><x-lucide name="tag" class="h-4 w-4" style="flex-shrink:0;margin-top:3px" /><span>Đường dẫn (slug) tự sinh từ tên tài liệu, không cần tự nhập.</span></div>
                <div><x-lucide name="clock" class="h-4 w-4" style="flex-shrink:0;margin-top:3px" /><span>"Thời hạn quyền" là mặc định khi kích hoạt mã/cấp quyền — mỗi lần cấp vẫn có thể chỉnh riêng.</span></div>
            </div>
        </div>
        </div>
    </div>

    @push('scripts')
        @include('partials.rich-editor-assets')
    @endpush
@endsection
