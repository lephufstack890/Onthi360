@extends('layouts.admin')

@section('title', 'Sửa sản phẩm')
@section('page-title', 'Sửa sản phẩm')

@section('content')
    @php $types = $types ?? []; $visibilities = $visibilities ?? []; $statuses = $statuses ?? []; $grades = $grades ?? []; @endphp

    <a href="{{ route('admin.products.show', $product->id) }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại chi tiết</a>

    <x-page-header title="✏️ Sửa sản phẩm" :subtitle="$product->title" />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-6">
            <form method="POST" action="{{ route('admin.products.update', $product->id) }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="title">Tên sản phẩm</label>
                        <input id="title" name="title" type="text" value="{{ old('title', $product->title) }}" required maxlength="255"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="type">Loại sản phẩm</label>
                        <x-select id="type" name="type" required>
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', $product->type->value) === $value)>{{ $label }}</option>
                            @endforeach
                        </x-select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="cover_image">Ảnh bìa (tùy chọn)</label>
                    @if ($product->cover_image_path)
                        <div class="mb-2 flex items-center gap-3">
                            <img src="{{ asset('storage/'.$product->cover_image_path) }}" alt="Ảnh bìa hiện tại" class="w-20 h-20 rounded-lg object-cover border border-slate-200">
                            <p class="text-xs text-slate-400">Ảnh hiện tại — chọn ảnh mới bên dưới để thay thế.</p>
                        </div>
                    @endif
                    <input id="cover_image" name="cover_image" type="file" accept="image/*"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-rose-50 file:text-rose-600 file:text-sm">
                    <p class="text-xs text-slate-400 mt-1">Ảnh JPG/PNG/WebP, tối đa 4MB. Để trống nếu không đổi ảnh.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="subject">Môn học</label>
                        <input id="subject" name="subject" type="text" value="{{ old('subject', $product->subject) }}" maxlength="60"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="grade">Khối lớp</label>
                        <x-select id="grade" name="grade" icon="🎓">
                            <option value="">— Không chỉ định —</option>
                            @foreach ($grades ?? [] as $g)
                                <option value="{{ $g }}" @selected(old('grade', $product->grade) === $g)>{{ $g }}</option>
                            @endforeach
                        </x-select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="topic">Chuyên đề</label>
                        <input id="topic" name="topic" type="text" value="{{ old('topic', $product->topic) }}" maxlength="120"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="description">Mô tả</label>
                    <textarea id="description" name="description" rows="5" maxlength="5000" data-rich-editor
                              class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">{{ old('description', $product->description) }}</textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="price">Giá để học (đ)</label>
                        <input id="price" name="price" type="number" min="0" value="{{ old('price', $product->price) }}" required
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="price_teaching">Giá để dạy (đ)</label>
                        <input id="price_teaching" name="price_teaching" type="number" min="0" value="{{ old('price_teaching', $product->price_teaching) }}" required
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="duration_months">Thời hạn quyền (tháng)</label>
                        <input id="duration_months" name="duration_months" type="number" min="1" value="{{ old('duration_months', $product->duration_months) }}"
                               placeholder="Để trống = không giới hạn"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
                    <div class="flex items-end pb-2.5">
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" name="has_print_option" value="1" @checked(old('has_print_option', $product->has_print_option))> Có bản in
                        </label>
                    </div>
                </div>

                {{-- SỬA 27/8 (3 — "thiếu 1 cái upload file pdf nữa có 4 lần upload á"): đủ 4 ô
                     — content_pdf (nội dung chính) + 3 tài nguyên phụ. Để trống = giữ nguyên
                     file cũ (xem ProductController::applyResourceUploads()), chọn file mới thì
                     thay thế. --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="content_pdf">File PDF</label>
                        <input id="content_pdf" name="content_pdf" type="file" accept="application/pdf"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-rose-50 file:text-rose-600 file:text-sm">
                        <p class="text-xs mt-1 {{ $product->content_pdf_path ? 'text-emerald-600' : 'text-slate-400' }}">
                            {{ $product->content_pdf_path ? '✓ Đã có: '.$product->content_pdf_original_name : 'Chưa có — PDF tối đa 50MB' }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="guide_pdf">File PDF hướng dẫn</label>
                        <input id="guide_pdf" name="guide_pdf" type="file" accept="application/pdf"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-rose-50 file:text-rose-600 file:text-sm">
                        <p class="text-xs mt-1 {{ $product->guide_pdf_path ? 'text-emerald-600' : 'text-slate-400' }}">
                            {{ $product->guide_pdf_path ? '✓ Đã có: '.$product->guide_pdf_original_name : 'Chưa có — PDF tối đa 50MB' }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="exercise_zip">File ZIP bài tập</label>
                        <input id="exercise_zip" name="exercise_zip" type="file" accept=".zip"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-rose-50 file:text-rose-600 file:text-sm">
                        <p class="text-xs mt-1 {{ $product->exercise_zip_path ? 'text-emerald-600' : 'text-slate-400' }}">
                            {{ $product->exercise_zip_path ? '✓ Đã có: '.$product->exercise_zip_original_name : 'Chưa có — ZIP tối đa 200MB' }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="media">Học liệu (ảnh động/audio)</label>
                        <input id="media" name="media" type="file" accept=".gif,.webp,.png,.jpg,.jpeg,.mp4,.mp3,.wav,.ogg"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-rose-50 file:text-rose-600 file:text-sm">
                        <p class="text-xs mt-1 {{ $product->media_path ? 'text-emerald-600' : 'text-slate-400' }}">
                            {{ $product->media_path ? '✓ Đã có: '.$product->media_original_name : 'Chưa có — tối đa 50MB' }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="visibility">Hiển thị</label>
                        <x-select id="visibility" name="visibility" required>
                            @foreach ($visibilities as $value => $label)
                                <option value="{{ $value }}" @selected(old('visibility', $product->visibility->value) === $value)>{{ $label }}</option>
                            @endforeach
                        </x-select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="status">Trạng thái</label>
                        <x-select id="status" name="status" required>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $product->status->value) === $value)>{{ $label }}</option>
                            @endforeach
                        </x-select>
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">Lưu thay đổi</button>
                    <a href="{{ route('admin.products.show', $product->id) }}" class="px-5 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">Huỷ</a>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-2xl border border-rose-200 p-6 space-y-3" x-data="{ open: false, reason: '' }">
            <h3 class="font-medium text-rose-700 flex items-center gap-2"><span>⚠️</span> Xóa sản phẩm</h3>
            <p class="text-sm text-slate-500">Xóa mềm — quyền truy cập đã cấp trước đó vẫn còn dữ liệu để tra cứu. Bắt buộc nêu lý do (10.4).</p>
            <button type="button" @click="open = !open" class="text-sm font-medium text-rose-600 hover:underline" x-text="open ? 'Đóng' : 'Tôi muốn xóa sản phẩm này'"></button>
            <form x-show="open" x-cloak method="POST" action="{{ route('admin.products.destroy', $product->id) }}" class="space-y-3 pt-2" onsubmit="return confirm('Xác nhận xóa sản phẩm này?');">
                @csrf
                @method('DELETE')
                <div>
                    <label class="block text-sm text-slate-600 mb-1">Lý do xóa (bắt buộc)</label>
                    <textarea name="reason" x-model="reason" rows="3" required class="w-full rounded-lg border border-slate-200 text-sm p-2" placeholder="Nêu rõ lý do..."></textarea>
                </div>
                <button type="submit" :disabled="reason.trim().length === 0" class="w-full px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium disabled:opacity-40 disabled:cursor-not-allowed">Xác nhận xóa</button>
            </form>
        </div>
    </div>

    @push('scripts')
        @include('partials.rich-editor-assets')
    @endpush
@endsection
