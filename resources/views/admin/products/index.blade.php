{{--
  Route: admin.products.index | "Sản phẩm & Quyền" (4.2), Frame ADM-03
  TODO controller: truyền $products (paginate) — tab "Quyền đã cấp" điều
  hướng sang admin.access-rights.index (bảng access_rights riêng).
--}}
@extends('layouts.admin')

@section('title', 'Sản phẩm & Quyền')
@section('page-title', 'Sản phẩm & Quyền')

@section('content')
    {{-- Dữ liệu thật do App\Http\Controllers\Admin\ProductController truyền vào. --}}
    @php
        $tabs = $tabs ?? [];
        $products = $products ?? [];
    @endphp

    <x-page-header title="🎫 Sản phẩm & Quyền" subtitle="Sản phẩm là thứ được bán/cấp quyền: sách, chuyên đề, đề thi, khóa học (5.1).">
        <x-slot:actions>
            <button type="button" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">+ Tạo sản phẩm</button>
        </x-slot:actions>
    </x-page-header>

    <x-tabs :tabs="$tabs" />

    <x-data-table :columns="['Tên sản phẩm', 'Loại', 'Giá', 'Hiển thị', '']">
        @forelse ($products as $p)
            <tr>
                <td class="px-4 py-3 font-medium text-slate-700">{{ $p['title'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $p['type'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $p['price'] }}</td>
                <td class="px-4 py-3"><x-status-badge :tone="$p['tone']">{{ $p['visibility'] }}</x-status-badge></td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('admin.products.show', $p['id']) }}" class="text-rose-600 font-medium">Xem</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Chưa có sản phẩm nào.</td></tr>
        @endforelse
    </x-data-table>
@endsection
