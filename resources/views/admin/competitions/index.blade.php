{{--
  Route: admin.competitions.index | Frame: ADM-05
  Spec: 11.1 (trạng thái: Sắp diễn ra → Đang diễn ra → Chờ công bố → Đã công bố → Lưu trữ).
  TODO controller: truyền $competitions (paginate).
--}}
@extends('layouts.admin')

@section('title', 'Cuộc thi')
@section('page-title', 'Cuộc thi')

@section('content')
    @php
        $tabs = [
            ['label' => 'Cuộc thi', 'href' => route('admin.competitions.index'), 'active' => true, 'count' => 6],
            ['label' => 'Giáo viên tiêu biểu', 'href' => route('admin.featured-teachers.index'), 'active' => false, 'count' => 14],
        ];
        $competitions = [
            ['id' => 1, 'name' => 'Cuộc thi Tin học trẻ 2026', 'type' => 'Cuộc thi', 'time' => '20/08 - 25/08/2026', 'status' => 'Sắp diễn ra', 'tone' => 'info'],
            ['id' => 2, 'name' => 'Khảo sát mức độ hài lòng Q3', 'type' => 'Khảo sát', 'time' => '01/07 - 15/07/2026', 'status' => 'Đã công bố', 'tone' => 'success'],
        ];
    @endphp

    <x-page-header title="Cuộc thi" subtitle="Đề thi luôn thuộc Tài liệu; cuộc thi chỉ tham chiếu đề để tổ chức sự kiện (4.3, 11.1).">
        <x-slot:actions>
            <button type="button" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">+ Tạo cuộc thi</button>
        </x-slot:actions>
    </x-page-header>

    <x-tabs :tabs="$tabs" />

    <x-data-table :columns="['Tên', 'Loại', 'Thời gian', 'Trạng thái', '']">
        @foreach ($competitions as $c)
            <tr>
                <td class="px-4 py-3 font-medium text-slate-700">{{ $c['name'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $c['type'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $c['time'] }}</td>
                <td class="px-4 py-3"><x-status-badge :tone="$c['tone']">{{ $c['status'] }}</x-status-badge></td>
                <td class="px-4 py-3 text-right"><a href="#" class="text-rose-600 font-medium">Xem</a></td>
            </tr>
        @endforeach
    </x-data-table>
@endsection
