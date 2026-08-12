{{--
  Route: admin.featured-teachers.index
  Spec: PUB-10 (trang vinh danh, không phải danh bạ cá nhân — 12.2).
  TODO controller: truyền $teachers đã được chọn công bố + thành tích/khóa-lớp phụ trách.
--}}
@extends('layouts.admin')

@section('title', 'Giáo viên tiêu biểu')
@section('page-title', 'Giáo viên tiêu biểu')

@section('content')
    @php
        $tabs = [
            ['label' => 'Cuộc thi', 'href' => route('admin.competitions.index'), 'active' => false, 'count' => 6],
            ['label' => 'Giáo viên tiêu biểu', 'href' => route('admin.featured-teachers.index'), 'active' => true, 'count' => 14],
        ];
        $teachers = [
            ['id' => 1, 'name' => 'Nguyễn Văn A', 'subject' => 'Tin học', 'featured' => true],
            ['id' => 2, 'name' => 'Lê Văn C', 'subject' => 'Toán', 'featured' => false],
        ];
    @endphp

    <x-page-header title="Giáo viên tiêu biểu" subtitle="Chỉ hiển thị dữ liệu thật/có phép; không lộ số điện thoại cá nhân (12.2)." />

    <x-tabs :tabs="$tabs" />

    <x-data-table :columns="['Giáo viên', 'Môn', 'Đang vinh danh', '']">
        @foreach ($teachers as $t)
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
        @endforeach
    </x-data-table>
@endsection
