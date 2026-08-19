@extends('layouts.admin')

@section('title', 'Tạo sản phẩm')
@section('page-title', 'Tạo sản phẩm')

@section('content')
    @php $types = $types ?? []; $visibilities = $visibilities ?? []; $statuses = $statuses ?? []; $grades = $grades ?? []; @endphp

    <a href="{{ route('admin.products.index') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Sản phẩm & Quyền</a>

    <x-page-header title="🎫 Tạo sản phẩm" subtitle="Sản phẩm là thứ được bán/cấp quyền: sách, chuyên đề, đề thi, khóa học (5.1)." />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-6">
            <form method="POST" action="{{ route('admin.products.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="title">Tên sản phẩm</label>
                        <input id="title" name="title" type="text" value="{{ old('title') }}" required maxlength="255"
                               placeholder="Ví dụ: Sách luyện thi Tin học 10"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="type">Loại sản phẩm</label>
                        <x-select id="type" name="type" required>
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', 'book') === $value)>{{ $label }}</option>
                            @endforeach
                        </x-select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="cover_image">Ảnh bìa (tùy chọn)</label>
                    <input id="cover_image" name="cover_image" type="file" accept="image/*"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-rose-50 file:text-rose-600 file:text-sm">
                    <p class="text-xs text-slate-400 mt-1">Ảnh JPG/PNG/WebP, tối đa 4MB.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="subject">Môn học</label>
                        <input id="subject" name="subject" type="text" value="{{ old('subject') }}" maxlength="60"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="grade">Khối lớp</label>
                        <x-select id="grade" name="grade" icon="🎓">
                            <option value="">— Không chỉ định —</option>
                            @foreach ($grades ?? [] as $g)
                                <option value="{{ $g }}" @selected(old('grade') === $g)>{{ $g }}</option>
                            @endforeach
                        </x-select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="topic">Chuyên đề</label>
                        <input id="topic" name="topic" type="text" value="{{ old('topic') }}" maxlength="120"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="description">Mô tả</label>
                    <textarea id="description" name="description" rows="5" maxlength="5000" data-rich-editor
                              class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">{{ old('description') }}</textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="price">Giá (đ)</label>
                        <input id="price" name="price" type="number" min="0" value="{{ old('price', 0) }}" required
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="duration_months">Thời hạn quyền (tháng)</label>
                        <input id="duration_months" name="duration_months" type="number" min="1" value="{{ old('duration_months') }}"
                               placeholder="Để trống = không giới hạn"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
                    <div class="flex items-end pb-2.5">
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" name="has_print_option" value="1" @checked(old('has_print_option'))> Có bản in
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="visibility">Hiển thị</label>
                        <x-select id="visibility" name="visibility" required>
                            @foreach ($visibilities as $value => $label)
                                <option value="{{ $value }}" @selected(old('visibility', 'public') === $value)>{{ $label }}</option>
                            @endforeach
                        </x-select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="status">Trạng thái</label>
                        <x-select id="status" name="status" required>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', 'draft') === $value)>{{ $label }}</option>
                            @endforeach
                        </x-select>
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">Tạo sản phẩm</button>
                    <a href="{{ route('admin.products.index') }}" class="px-5 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">Huỷ</a>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-6 space-y-4">
            <h3 class="font-medium text-slate-700 flex items-center gap-2"><span>💡</span> Cần biết</h3>
            <div class="flex items-start gap-3">
                <x-icon-tile emoji="🔗" tone="sky" />
                <p class="text-sm text-slate-500">Đường dẫn (slug) tự sinh từ tên sản phẩm, không cần tự nhập.</p>
            </div>
            <div class="flex items-start gap-3">
                <x-icon-tile emoji="⏳" tone="violet" />
                <p class="text-sm text-slate-500">"Thời hạn quyền" là mặc định khi kích hoạt mã/cấp quyền — mỗi lần cấp vẫn có thể chỉnh riêng (7.4).</p>
            </div>
        </div>
    </div>

    @push('scripts')
        @include('partials.rich-editor-assets')
    @endpush
@endsection
