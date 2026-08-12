{{-- Route: admin.products.show | TODO controller: truyền $product thật + rating summary. --}}
@extends('layouts.admin')

@section('title', 'Chi tiết sản phẩm')
@section('page-title', 'Chi tiết sản phẩm')

@section('content')
    {{-- $product (Eloquent thật) do App\Http\Controllers\Admin\ProductController truyền vào. --}}
    <a href="{{ route('admin.products.index') }}" class="text-sm text-slate-500 mb-4 inline-block">‹ Quay lại Sản phẩm</a>

    <x-page-header :title="$product->title" :subtitle="'Giá: '.number_format($product->price).'đ · Hiển thị: '.($product->visibility->value === 'public' ? 'Công khai' : 'Riêng tư')" />

    <div class="bg-white rounded-2xl border border-slate-200 p-5">
        <p class="text-sm text-slate-500">TODO: form sửa thông tin sản phẩm, mục lục học liệu, cấu hình bản in (7.4), rating summary nội bộ.</p>
    </div>
@endsection
