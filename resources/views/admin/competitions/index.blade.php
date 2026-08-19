{{--
  Route: admin.competitions.index | Frame: ADM-05
  Spec: 11.1 (trạng thái: Sắp diễn ra → Đang diễn ra → Chờ công bố → Đã công bố → Lưu trữ).
  TODO controller: truyền $competitions (paginate).
--}}
@extends('layouts.admin')

@section('title', 'Cuộc thi')
@section('page-title', 'Cuộc thi')

@section('content')
    {{-- Dữ liệu thật do App\Http\Controllers\Admin\CompetitionController truyền vào. --}}
    @php
        $tabs = $tabs ?? [];
        $competitions = $competitions ?? [];
        $competitionStatusMessage = match (session('status')) {
            'competition-archived' => 'Đã lưu trữ cuộc thi.',
            default => null,
        };
    @endphp
    @if ($competitionStatusMessage)
        @include('partials.toast-flash', ['type' => 'success', 'message' => $competitionStatusMessage])
    @endif

    <x-page-header title="🏆 Cuộc thi" subtitle="Đề thi luôn thuộc Tài liệu; cuộc thi chỉ tham chiếu đề để tổ chức sự kiện.">
        <x-slot:actions>
            <a href="{{ route('admin.competitions.create') }}" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">+ Tạo cuộc thi</a>
        </x-slot:actions>
    </x-page-header>

    <x-tabs :tabs="$tabs" />

    <x-data-table :columns="['Tên', 'Loại', 'Bắt đầu', 'Kết thúc', 'Trạng thái', '']">
        @forelse ($competitions as $c)
            <tr>
                <td class="px-4 py-3 font-medium text-slate-700">{{ $c['name'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $c['type'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $c['startsAtLabel'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $c['endsAtLabel'] }}</td>
                <td class="px-4 py-3"><x-status-badge :tone="$c['tone']">{{ $c['status'] }}</x-status-badge></td>
                <td class="px-4 py-3 text-right"><a href="{{ route('admin.competitions.show', $c['id']) }}" class="text-rose-600 font-medium">Xem</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Chưa có cuộc thi nào.</td></tr>
        @endforelse
    </x-data-table>
@endsection
