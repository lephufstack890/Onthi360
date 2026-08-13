{{--
  Route: admin.products.edit / .update / .destroy
  Dữ liệu thật ($product, $types, $visibilities, $statuses) do ProductController::edit()
  truyền vào qua ProductService::editFormData(). Slug KHÔNG cho sửa (giữ SEO/link công khai).
--}}
@extends('layouts.admin')

@section('title', 'Sửa sản phẩm')
@section('page-title', 'Sửa sản phẩm')

@section('content')
    @php $types = $types ?? []; $visibilities = $visibilities ?? []; $statuses = $statuses ?? []; @endphp

    <a href="{{ route('admin.products.show', $product->id) }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại chi tiết</a>

    <x-page-header title="✏️ Sửa sản phẩm" :subtitle="$product->title" />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-6">
            <form method="POST" action="{{ route('admin.products.update', $product->id) }}" class="space-y-4">
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
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="cover_image_path">Ảnh bìa (đường dẫn URL, tùy chọn)</label>
                    <input id="cover_image_path" name="cover_image_path" type="text" value="{{ old('cover_image_path', $product->cover_image_path) }}" maxlength="500"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="subject">Môn học</label>
                        <input id="subject" name="subject" type="text" value="{{ old('subject', $product->subject) }}" maxlength="60"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="grade">Khối lớp</label>
                        <input id="grade" name="grade" type="text" value="{{ old('grade', $product->grade) }}" maxlength="20"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
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

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="price">Giá (đ)</label>
                        <input id="price" name="price" type="number" min="0" value="{{ old('price', $product->price) }}" required
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
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
