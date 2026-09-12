@extends('layouts.admin')

@section('title', 'Tạo tài liệu')
@section('page-title', 'Tạo tài liệu')

@section('content')
    @php $types = $types ?? []; $visibilities = $visibilities ?? []; $statuses = $statuses ?? []; $grades = $grades ?? []; @endphp

    <a href="{{ route('admin.products.index') }}" class="text-[13px] text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-blue-600">‹ Quay lại Tài liệu</a>

    <x-admin.page-header title="Tạo tài liệu" icon="wallet-cards" subtitle="Tài liệu là thứ được bán/cấp quyền: sách, chuyên đề, đề thi, khóa học (5.1)." />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-5 sm:p-6">
            <form method="POST" action="{{ route('admin.products.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="title">Tên tài liệu</label>
                        <input id="title" name="title" type="text" value="{{ old('title') }}" required maxlength="255"
                               placeholder="Ví dụ: Sách luyện thi Tin học 10"
                               class="admin-input">
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="type">Loại tài liệu</label>
                        <x-admin.select id="type" name="type" required>
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', 'book') === $value)>{{ $label }}</option>
                            @endforeach
                        </x-admin.select>
                    </div>
                </div>

                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="cover_image">Ảnh bìa (tùy chọn)</label>
                    <input id="cover_image" name="cover_image" type="file" accept="image/*"
                           class="admin-input file:mr-3 file:rounded-xl file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-blue-700">
                    <p class="text-xs text-slate-400 mt-1">Ảnh JPG/PNG/WebP, tối đa 4MB.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="subject">Môn học</label>
                        <input id="subject" name="subject" type="text" value="{{ old('subject') }}" maxlength="60"
                               class="admin-input">
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="grade">Khối lớp</label>
                        <x-admin.select id="grade" name="grade" icon="🎓">
                            <option value="">— Không chỉ định —</option>
                            @foreach ($grades ?? [] as $g)
                                <option value="{{ $g }}" @selected(old('grade') === $g)>{{ $g }}</option>
                            @endforeach
                        </x-admin.select>
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="topic">Chuyên đề</label>
                        <input id="topic" name="topic" type="text" value="{{ old('topic') }}" maxlength="120"
                               class="admin-input">
                    </div>
                </div>

                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="description">Mô tả</label>
                    <textarea id="description" name="description" rows="5" maxlength="5000" data-rich-editor
                              class="admin-input">{{ old('description') }}</textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="price">Giá để học (đ)</label>
                        <input id="price" name="price" type="number" min="0" value="{{ old('price', 0) }}" required
                               class="admin-input">
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="price_teaching">Giá để dạy (đ)</label>
                        <input id="price_teaching" name="price_teaching" type="number" min="0" value="{{ old('price_teaching', 0) }}" required
                               class="admin-input">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="duration_months">Thời hạn quyền (tháng)</label>
                        <input id="duration_months" name="duration_months" type="number" min="1" value="{{ old('duration_months') }}"
                               placeholder="Để trống = không giới hạn"
                               class="admin-input">
                    </div>
                    <div class="flex items-end pb-2.5">
                        <label class="flex items-center gap-2 text-[13px] text-slate-600">
                            <input type="checkbox" name="has_print_option" value="1" @checked(old('has_print_option'))> Có bản in
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="content_pdf">File PDF</label>
                        <input id="content_pdf" name="content_pdf" type="file" accept="application/pdf"
                               class="admin-input file:mr-3 file:rounded-xl file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-blue-700">
                        <p class="text-xs text-slate-400 mt-1">PDF, tối đa 50MB.</p>
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="guide_pdf">File PDF hướng dẫn</label>
                        <input id="guide_pdf" name="guide_pdf" type="file" accept="application/pdf"
                               class="admin-input file:mr-3 file:rounded-xl file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-blue-700">
                        <p class="text-xs text-slate-400 mt-1">PDF, tối đa 50MB.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="visibility">Hiển thị</label>
                        <x-admin.select id="visibility" name="visibility" required>
                            @foreach ($visibilities as $value => $label)
                                <option value="{{ $value }}" @selected(old('visibility', 'public') === $value)>{{ $label }}</option>
                            @endforeach
                        </x-admin.select>
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="status">Trạng thái</label>
                        <x-admin.select id="status" name="status" required>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', 'draft') === $value)>{{ $label }}</option>
                            @endforeach
                        </x-admin.select>
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-[13px] font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 shadow-sm hover:bg-blue-700 transition">Tạo tài liệu</button>
                    <a href="{{ route('admin.products.index') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-sky-100 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition-colors hover:border-sky-200 hover:bg-sky-50">Huỷ</a>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-3xl border border-sky-100 p-6 space-y-4">
            <h3 class="font-medium text-slate-700 flex items-center gap-2"><span><x-lucide name="sparkles" class="h-4 w-4" /></span> Cần biết</h3>
            <div class="flex items-start gap-3">
                <x-admin.icon-tile emoji="🔗" tone="sky" />
                <p class="text-[13px] text-slate-500">Đường dẫn (slug) tự sinh từ tên tài liệu, không cần tự nhập.</p>
            </div>
            <div class="flex items-start gap-3">
                <x-admin.icon-tile emoji="⏳" tone="violet" />
                <p class="text-[13px] text-slate-500">"Thời hạn quyền" là mặc định khi kích hoạt mã/cấp quyền — mỗi lần cấp vẫn có thể chỉnh riêng.</p>
            </div>
        </div>
    </div>

    @push('scripts')
        @include('partials.rich-editor-assets')
    @endpush
@endsection
