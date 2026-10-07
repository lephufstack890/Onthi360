@extends('layouts.admin')

@section('title', 'Khóa & Lớp')
@section('page-title', 'Khóa & Lớp')

@section('content')
    @php
        $tab = $tab ?? 'courses';
        $tabs = $tabs ?? [];
        $rows = $rows ?? [];
    @endphp

    @if (in_array(session('status'), ['course-created', 'course-deleted', 'class-deleted'], true))
        @include('partials.toast-flash', ['type' => 'success', 'message' => session('status') === 'course-created' ? 'Đã tạo khóa học mới.' : session('statusMessage', 'Đã xóa.')])
    @endif
    {{-- SỬA 7/10 — việc xoá bị từ chối / lỗi đi theo $errors. --}}
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <x-ws.page-header title="Khóa & Lớp" icon="graduation-cap" subtitle="Một khóa học có thể có nhiều lớp; lớp là nơi tổ chức lịch, học viên và tiến độ (8.1).">
        @if ($tab === 'courses')
            <x-slot:actions>
                <a href="{{ route('admin.courses.create') }}" class="inline-flex min-h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-white px-4 py-2 text-xs font-bold text-blue-700 shadow-sm transition-colors hover:bg-sky-50 shadow-sm hover:bg-blue-700 transition">+ Tạo khóa học</a>
            </x-slot:actions>
        @endif
    </x-ws.page-header>

    <x-ws.tabs :tabs="$tabs" />

    <x-ws.table :columns="['Tên', 'Thông tin', 'Trạng thái', '']">
        @forelse ($rows as $r)
            <tr>
                <td class="px-4 py-3 font-medium text-slate-700">{{ $r['name'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $r['meta'] }}</td>
                <td class="px-4 py-3"><x-ws.badge :tone="$r['tone']">{{ $r['status'] }}</x-ws.badge></td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    @if ($tab === 'courses')
                        <a href="{{ route('admin.courses.show', $r['id']) }}" class="text-blue-600 font-medium">Xem</a>
                    @else
                        <a href="{{ route('admin.classes.edit', $r['id']) }}" class="text-blue-600 font-medium">Sửa</a>
                    @endif
                    {{-- SỬA 7/10 (khách: "làm thêm tính năng xoá, xoá khoá/lớp là xoá hết dữ liệu liên quan") —
                         xoá vĩnh viễn kèm toàn bộ dữ liệu con. Nội dung cảnh báo (có tên khoá/lớp) đi qua
                         data-confirm để khỏi vướng dấu nháy khi nhét vào onsubmit. --}}
                    @if (! empty($r['deleteHref']))
                        <form method="POST" action="{{ $r['deleteHref'] }}" class="inline ml-3"
                              data-confirm="{{ $r['deleteLabel'] }}" onsubmit="return confirm(this.dataset.confirm);">
                            @csrf
                            @method('DELETE')
                            @if ($tab !== 'courses')
                                <input type="hidden" name="return" value="index">
                            @endif
                            <button type="submit" class="text-rose-600 hover:text-rose-700 font-medium">Xóa</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">Chưa có dữ liệu.</td></tr>
        @endforelse
    </x-ws.table>
@endsection
