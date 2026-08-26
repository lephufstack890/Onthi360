@extends('layouts.admin')

@section('title', 'Tải bài hàng loạt')
@section('page-title', 'Tải bài hàng loạt')

@section('content')
    @php
        $products = $products ?? []; $parents = $parents ?? [];
        $types = $types ?? []; $statuses = $statuses ?? [];
        // SỬA 26/8 ("gộp Học liệu vào Sản phẩm & quyền") — xem ghi chú tương ứng ở create.blade.php.
        $selectedProductId = $selectedProductId ?? null;
        $backHref = $selectedProductId ? route('admin.products.show', $selectedProductId) : route('admin.products.index');
        $backLabel = $selectedProductId ? '‹ Quay lại sản phẩm' : '‹ Quay lại Sản phẩm & quyền';
    @endphp

    <a href="{{ $backHref }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">{{ $backLabel }}</a>

    <x-page-header title="🗂️ Tải bài hàng loạt" subtitle="Tải 1 gói ZIP chứa nhiều tệp PDF — mỗi tệp = 1 bài, tên tệp sẽ dùng làm mã bài. Áp dụng cho Sách, Chuyên đề, Đề thi (đều là 1 sản phẩm gồm nhiều bài)." />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="bg-white rounded-2xl border border-slate-200 p-6">
        <form method="POST" action="{{ route('admin.content.materials.bulk.store') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf

            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="product_id">Thuộc sản phẩm (Sách/Chuyên đề/Đề thi)</label>
                <x-select id="product_id" name="product_id" required>
                    <option value="">— Chọn sản phẩm —</option>
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}" @selected((string) old('product_id', $selectedProductId) === (string) $p->id)>{{ $p->title }}</option>
                    @endforeach
                </x-select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="type">Loại (áp dụng cho mọi bài trong gói)</label>
                    <x-select id="type" name="type" required>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected(old('type', 'section') === $value)>{{ $label }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="status">Trạng thái (áp dụng cho mọi bài trong gói)</label>
                    <x-select id="status" name="status" required>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', 'draft') === $value)>{{ $label }}</option>
                        @endforeach
                    </x-select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="parent_id">Thuộc mục cha (tùy chọn, áp dụng cho mọi bài trong gói)</label>
                <x-select id="parent_id" name="parent_id">
                    <option value="">— Không có, mỗi bài là 1 mục gốc —</option>
                    @foreach ($parents as $par)
                        <option value="{{ $par['id'] }}" @selected((string) old('parent_id') === (string) $par['id'])>{{ $par['label'] }}</option>
                    @endforeach
                </x-select>
            </div>

            <div class="border-t border-slate-100 pt-5">
                <label class="block text-sm font-medium text-slate-600 mb-1" for="zip_package">Gói ZIP (mỗi tệp .pdf ở gốc ZIP = 1 bài)</label>
                <input id="zip_package" name="zip_package" type="file" accept=".zip,application/zip" required
                       class="w-full rounded-lg border border-slate-200 text-sm p-2 file:mr-3 file:rounded-lg file:border-0 file:bg-rose-50 file:text-rose-600 file:px-3 file:py-1.5">
                <p class="text-xs text-slate-400 mt-1">
                    Tối đa {{ number_format(\App\Services\Admin\ContentService::maxBulkMaterialZipKb() / 1024) }} MB.
                    Đặt tên các tệp PDF trong ZIP chính là mã bài mong muốn (ví dụ "BAI01.pdf" → mã "BAI01").
                    Tiêu đề mỗi bài sẽ tạm lấy theo tên tệp — sửa lại sau nếu cần, từng bài đều sửa được (tên/mã/PDF) sau khi tải lên.
                </p>
            </div>

            <div class="flex gap-3 pt-2 border-t border-slate-100">
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">Tải lên</button>
                <a href="{{ $backHref }}" class="px-5 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">Huỷ</a>
            </div>
        </form>
    </div>
@endsection
