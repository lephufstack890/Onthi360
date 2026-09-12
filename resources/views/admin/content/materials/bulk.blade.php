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

    <a href="{{ $backHref }}" class="text-[13px] text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-blue-600">{{ $backLabel }}</a>

    <x-admin.page-header title="Tải bài hàng loạt" icon="library" subtitle="Tải 1 gói ZIP chứa nhiều tệp PDF — mỗi tệp = 1 bài, tên tệp sẽ dùng làm mã bài. Áp dụng cho Sách, Chuyên đề, Đề thi (đều là 1 sản phẩm gồm nhiều bài)." />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-5 sm:p-6">
        <form method="POST" action="{{ route('admin.content.materials.bulk.store') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf

            <div>
                <label class="block text-[13px] font-medium text-slate-600 mb-1" for="product_id">Thuộc sản phẩm (Sách/Chuyên đề/Đề thi)</label>
                <x-admin.select id="product_id" name="product_id" required>
                    <option value="">— Chọn sản phẩm —</option>
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}" @selected((string) old('product_id', $selectedProductId) === (string) $p->id)>{{ $p->title }}</option>
                    @endforeach
                </x-admin.select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="type">Loại (áp dụng cho mọi bài trong gói)</label>
                    <x-admin.select id="type" name="type" required>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected(old('type', 'section') === $value)>{{ $label }}</option>
                        @endforeach
                    </x-admin.select>
                </div>
                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="status">Trạng thái (áp dụng cho mọi bài trong gói)</label>
                    <x-admin.select id="status" name="status" required>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', 'draft') === $value)>{{ $label }}</option>
                        @endforeach
                    </x-admin.select>
                </div>
            </div>

            <div>
                <label class="block text-[13px] font-medium text-slate-600 mb-1" for="parent_id">Thuộc mục cha (tùy chọn, áp dụng cho mọi bài trong gói)</label>
                <x-admin.select id="parent_id" name="parent_id">
                    <option value="">— Không có, mỗi bài là 1 mục gốc —</option>
                    @foreach ($parents as $par)
                        <option value="{{ $par['id'] }}" @selected((string) old('parent_id') === (string) $par['id'])>{{ $par['label'] }}</option>
                    @endforeach
                </x-admin.select>
            </div>

            <div class="border-t border-slate-100 pt-5">
                <label class="block text-[13px] font-medium text-slate-600 mb-1" for="zip_package">Gói ZIP (mỗi tệp .pdf ở gốc ZIP = 1 bài)</label>
                <input id="zip_package" name="zip_package" type="file" accept=".zip,application/zip" required
                       class="admin-input file:mr-3 file:rounded-xl file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-blue-700">
                <p class="text-xs text-slate-400 mt-1">
                    Tối đa {{ number_format(\App\Services\Admin\ContentService::maxBulkMaterialZipKb() / 1024) }} MB.
                    Đặt tên các tệp PDF trong ZIP chính là mã bài mong muốn (ví dụ "BAI01.pdf" → mã "BAI01").
                    Tiêu đề mỗi bài sẽ tạm lấy theo tên tệp — sửa lại sau nếu cần, từng bài đều sửa được (tên/mã/PDF) sau khi tải lên.
                </p>
            </div>

            <div class="flex gap-3 pt-2 border-t border-slate-100">
                <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-[13px] font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 shadow-sm hover:bg-blue-700 transition">Tải lên</button>
                <a href="{{ $backHref }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-sky-100 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition-colors hover:border-sky-200 hover:bg-sky-50">Huỷ</a>
            </div>
        </form>
    </div>
@endsection
