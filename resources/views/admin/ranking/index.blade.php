{{--
  Route: admin.ranking.index
  Spec: 11.2 (phạm vi rõ: cuộc thi/lớp/chuyên đề; nêu công thức điểm; Chờ công bố không lộ rank tạm).
  TODO controller: truyền $boards theo phạm vi.
--}}
@extends('layouts.admin')

@section('title', 'Bảng xếp hạng')
@section('page-title', 'Bảng xếp hạng')

@section('content')
    {{-- Dữ liệu thật do App\Http\Controllers\Admin\RankingController truyền vào. --}}
    @php
        $boards = $boards ?? [];
    @endphp

    <x-page-header title="📊 Bảng xếp hạng" subtitle="Không trộn số liệu giữa các phạm vi khác nhau; tách biệt hoàn toàn với sao/rating (11.2, 9.1)." />

    <x-data-table :columns="['Phạm vi', 'Số người xếp hạng', 'Trạng thái', '']">
        @forelse ($boards as $b)
            <tr>
                <td class="px-4 py-3 font-medium text-slate-700">{{ $b['scope'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $b['entries'] }}</td>
                <td class="px-4 py-3"><x-status-badge :tone="$b['tone']">{{ $b['status'] }}</x-status-badge></td>
                <td class="px-4 py-3 text-right"><a href="#" class="text-rose-600 font-medium">Cấu hình</a></td>
            </tr>
        @empty
            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">Chưa có bảng xếp hạng nào.</td></tr>
        @endforelse
    </x-data-table>
@endsection
