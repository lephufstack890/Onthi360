{{--
  Route: admin.featured-teachers.index
  Spec: PUB-10 (trang vinh danh, không phải danh bạ cá nhân — 12.2).
  TODO controller: truyền $teachers đã được chọn công bố + thành tích/khóa-lớp phụ trách.
--}}
@extends('layouts.admin')

@section('title', 'Giáo viên tiêu biểu')
@section('page-title', 'Giáo viên tiêu biểu')

@section('content')
    {{-- Dữ liệu thật do App\Http\Controllers\Admin\FeaturedTeacherController truyền vào
    ("featured" luôn false cho tới khi có cột is_featured thật trong schema). --}}
    @php
        $tabs = $tabs ?? [];
        $teachers = $teachers ?? [];
    @endphp

    <x-page-header title="Giáo viên tiêu biểu" subtitle="Chỉ hiển thị dữ liệu thật/có phép; không lộ số điện thoại cá nhân (12.2)." />

    <x-tabs :tabs="$tabs" />

    <x-data-table :columns="['Giáo viên', 'Môn', 'Đang vinh danh', '']">
        @forelse ($teachers as $t)
            <tr>
                <td class="px-4 py-3 font-medium text-slate-700">{{ $t['name'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $t['subject'] }}</td>
                <td class="px-4 py-3">
                    <x-status-badge :tone="$t['featured'] ? 'success' : 'neutral'">{{ $t['featured'] ? 'Đang hiển thị' : 'Chưa chọn' }}</x-status-badge>
                </td>
                <td class="px-4 py-3 text-right">
                    <button type="button" class="text-rose-600 font-medium">{{ $t['featured'] ? 'Bỏ vinh danh' : 'Vinh danh' }}</button>
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">Chưa có giáo viên nào được duyệt.</td></tr>
        @endforelse
    </x-data-table>
@endsection
