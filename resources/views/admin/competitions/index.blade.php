@extends('layouts.admin')

@section('title', 'Cuộc thi')
@section('page-title', 'Cuộc thi')

@section('content')
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

    <x-admin.page-header title="Cuộc thi" icon="trophy" subtitle="Đề thi luôn thuộc Tài liệu; cuộc thi chỉ tham chiếu đề để tổ chức sự kiện.">
        <x-slot:actions>
            <a href="{{ route('admin.competitions.create') }}" class="inline-flex min-h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-white px-4 py-2 text-xs font-bold text-blue-700 shadow-sm transition-colors hover:bg-sky-50">+ Tạo cuộc thi</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.tabs :tabs="$tabs" />

    {{--
      TẠM ẨN 24/8: Khách hiện không cần hiện cột Bắt đầu/Kết thúc/Trạng thái ở danh sách cuộc
      thi (đang thừa). Header cột là 1 mảng PHP literal trong thuộc tính :columns nên không
      thể tự comment riêng từng cột — giữ nguyên bản ĐẦY ĐỦ ở đây, sau này cần dùng lại thì
      dán nguyên khối comment này để THAY THẾ khối <x-admin.table> đang chạy ngay dưới:

    <x-admin.table :columns="['Tên', 'Loại', 'Bắt đầu', 'Kết thúc', 'Trạng thái', '']">
        @forelse ($competitions as $c)
            <tr>
                <td class="px-4 py-3 font-medium text-slate-700">{{ $c['name'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $c['type'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $c['startsAtLabel'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $c['endsAtLabel'] }}</td>
                <td class="px-4 py-3"><x-admin.badge :tone="$c['tone']">{{ $c['status'] }}</x-admin.badge></td>
                <td class="px-4 py-3 text-right"><a href="{{ route('admin.competitions.show', $c['id']) }}" class="text-blue-600 font-medium">Xem</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Chưa có cuộc thi nào.</td></tr>
        @endforelse
    </x-admin.table>
    --}}

    <x-admin.table :columns="['Tên', 'Loại', '']">
        @forelse ($competitions as $c)
            <tr>
                <td class="px-4 py-3 font-medium text-slate-700">{{ $c['name'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $c['type'] }}</td>
                <td class="px-4 py-3 text-right"><a href="{{ route('admin.competitions.show', $c['id']) }}" class="text-blue-600 font-medium">Xem</a></td>
            </tr>
        @empty
            <tr><td colspan="3" class="px-4 py-6 text-center text-slate-400">Chưa có cuộc thi nào.</td></tr>
        @endforelse
    </x-admin.table>
@endsection
