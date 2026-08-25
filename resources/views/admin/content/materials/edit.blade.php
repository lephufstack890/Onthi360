@extends('layouts.admin')

@section('title', 'Sửa học liệu')
@section('page-title', 'Sửa học liệu')

@section('content')
    @php
        $products = $products ?? []; $parents = $parents ?? []; $assessments = $assessments ?? [];
        $types = $types ?? []; $statuses = $statuses ?? [];
    @endphp

    <a href="{{ route('admin.content.show', $material->id) }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại chi tiết</a>

    <x-page-header title="✏️ Sửa học liệu" :subtitle="$material->title" />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="bg-white rounded-2xl border border-slate-200 p-6" x-data="{ type: '{{ old('type', $material->type) }}' }">
        <form method="POST" action="{{ route('admin.content.materials.update', $material->id) }}" class="space-y-4" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="product_id">Thuộc sản phẩm</label>
                <x-select id="product_id" name="product_id" required>
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}" @selected((string) old('product_id', $material->product_id) === (string) $p->id)>{{ $p->title }}</option>
                    @endforeach
                </x-select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="type">Loại</label>
                    <x-select id="type" name="type" x-model="type" required>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected(old('type', $material->type) === $value)>{{ $label }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="order">Thứ tự hiển thị</label>
                    <input id="order" name="order" type="number" min="0" value="{{ old('order', $material->order) }}"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="title">Tiêu đề</label>
                <input id="title" name="title" type="text" value="{{ old('title', $material->title) }}" required maxlength="255"
                       class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="parent_id">Thuộc mục cha (tùy chọn)</label>
                <x-select id="parent_id" name="parent_id">
                    <option value="">— Không có, đây là mục gốc —</option>
                    @foreach ($parents as $par)
                        @if ($par['id'] !== $material->id)
                            <option value="{{ $par['id'] }}" @selected((string) old('parent_id', $material->parent_id) === (string) $par['id'])>{{ $par['label'] }}</option>
                        @endif
                    @endforeach
                </x-select>
            </div>

            <div x-show="type === 'assessment_ref'" x-cloak>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="assessment_id">Đề/bộ bài tham chiếu</label>
                <x-select id="assessment_id" name="assessment_id">
                    <option value="">— Chọn đề/bộ bài —</option>
                    @foreach ($assessments as $a)
                        <option value="{{ $a->id }}" @selected((string) old('assessment_id', $material->assessment_id) === (string) $a->id)>{{ $a->title }}</option>
                    @endforeach
                </x-select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="status">Trạng thái</label>
                <x-select id="status" name="status" required>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $material->status->value) === $value)>{{ $label }}</option>
                    @endforeach
                </x-select>
            </div>

            {{--
                SỬA 25/8 (khách chốt: "các bài cần có cơ chế sửa sau khi nhập" — mã bài và PDF
                ĐỀU sửa lại được, không phải tải lên xong là khóa cứng, xem ContentService::materialUpdate()).
                Để trống ô PDF thì GIỮ NGUYÊN tệp hiện tại, không xóa/thay gì cả.
            --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="code">Mã bài (tùy chọn)</label>
                    <input id="code" name="code" type="text" value="{{ old('code', $material->code) }}" maxlength="60"
                           placeholder="Để trống sẽ tự đặt theo tên tệp PDF"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="pdf">Tệp PDF bài học (tùy chọn)</label>
                    @if ($material->pdf_path)
                        <p class="text-xs text-slate-500 mb-1.5">
                            📄 Đã có tệp: <span class="font-medium text-slate-600">{{ $material->pdf_original_name ?: basename($material->pdf_path) }}</span>
                            — chọn tệp mới bên dưới để thay thế.
                        </p>
                    @endif
                    <input id="pdf" name="pdf" type="file" accept="application/pdf"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">Lưu thay đổi</button>
                <a href="{{ route('admin.content.show', $material->id) }}" class="px-5 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">Huỷ</a>
            </div>
        </form>
    </div>
@endsection
