@extends('layouts.teacher')

@section('title', 'Cuộc thi')
@section('page-title', 'Cuộc thi')

@section('content')
    @php
        $competitions = $competitions ?? [];
    @endphp

    <x-page-header title="🏆 Cuộc thi" subtitle="Danh sách cuộc thi bạn là giáo viên cố vấn/đồng hành — chỉ được thêm và sửa kỳ thi (vòng), không sửa được thông tin cuộc thi (chỉ Admin mới sửa được)." />

    <x-data-table :columns="['Tên', 'Loại', 'Số kỳ thi', '']">
        @forelse ($competitions as $c)
            <tr>
                <td class="px-4 py-3 font-medium text-slate-700">{{ $c['name'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $c['type'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $c['examsCount'] }}</td>
                <td class="px-4 py-3 text-right"><a href="{{ route('teacher.competitions.show', $c['id']) }}" class="text-rose-600 font-medium">Xem</a></td>
            </tr>
        @empty
            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">Bạn chưa được thêm làm cố vấn/đồng hành cho cuộc thi nào.</td></tr>
        @endforelse
    </x-data-table>
@endsection
