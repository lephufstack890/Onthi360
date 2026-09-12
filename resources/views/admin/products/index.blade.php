
@extends('layouts.admin')

@section('title', 'Tài liệu')
@section('page-title', 'Tài liệu')

@section('content')
    @php
        $tabs = $tabs ?? [];
        $products = $products ?? [];
    @endphp

    @if (in_array(session('status'), ['product-created', 'product-deleted'], true))
        @include('partials.toast-flash', ['type' => 'success', 'message' => session('status') === 'product-created' ? 'Đã tạo tài liệu mới.' : 'Đã xóa tài liệu (xóa mềm, đã ghi lý do).'])
    @endif

    <x-admin.page-header title="Tài liệu" icon="wallet-cards" subtitle="Tài liệu là thứ được bán/cấp quyền: sách, chuyên đề, đề thi, khóa học">
        <x-slot:actions>
            <a href="{{ route('admin.products.create') }}" class="inline-flex min-h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-white px-4 py-2 text-xs font-bold text-blue-700 shadow-sm transition-colors hover:bg-sky-50">+ Tạo tài liệu</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.tabs :tabs="$tabs" />

    <x-admin.table :columns="['Tên tài liệu', 'Loại', 'Giá', 'Hiển thị', '']">
        @forelse ($products as $p)
            <tr>
                <td class="px-4 py-3 font-medium text-slate-700">{{ $p['title'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $p['type'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $p['price'] }}</td>
                <td class="px-4 py-3"><x-admin.badge :tone="$p['tone']">{{ $p['visibility'] }}</x-admin.badge></td>
                <td class="px-4 py-3 text-right space-x-3">
                    <a href="{{ route('admin.products.show', $p['id']) }}" class="text-blue-600 font-medium">Xem</a>
                    <a href="{{ route('admin.products.edit', $p['id']) }}" class="text-slate-500 font-medium hover:text-blue-600">Sửa</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Chưa có tài liệu nào.</td></tr>
        @endforelse
    </x-admin.table>
@endsection
