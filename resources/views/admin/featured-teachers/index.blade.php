@extends('layouts.admin')

@section('title', 'Giáo viên tiêu biểu')
@section('page-title', 'Giáo viên tiêu biểu')

@section('content')
    @php
        $tabs = $tabs ?? [];
        $teachers = $teachers ?? [];
    @endphp

    <x-page-header title="🏆 Giáo viên tiêu biểu" subtitle="Chỉ hiển thị dữ liệu thật/có phép; không lộ số điện thoại cá nhân (12.2)." />

    @if (session('status'))
        @include('partials.toast-flash', ['type' => 'success', 'message' => session('status') === 'featured' ? 'Đã vinh danh giáo viên.' : 'Đã bỏ vinh danh giáo viên.'])
    @endif

    <x-tabs :tabs="$tabs" />

    <x-data-table :columns="['Giáo viên', 'Môn', 'Thành tích công bố', 'Đang vinh danh', '']">
        @forelse ($teachers as $t)
            <tr x-data="{ editing: false }">
                <td class="px-4 py-3 font-medium text-slate-700 align-top">{{ $t['name'] }}</td>
                <td class="px-4 py-3 text-slate-500 align-top">{{ $t['subject'] }}</td>
                <td class="px-4 py-3 text-slate-400 max-w-xs align-top">
                    <span class="line-clamp-2">{{ $t['achievement'] ?: '—' }}</span>
                </td>
                <td class="px-4 py-3 align-top">
                    <x-status-badge :tone="$t['featured'] ? 'success' : 'neutral'">{{ $t['featured'] ? 'Đang hiển thị' : 'Chưa chọn' }}</x-status-badge>
                </td>
                <td class="px-4 py-3 text-right align-top">
                    @if ($t['featured'])
                        <form method="POST" action="{{ route('admin.featured-teachers.unfeature', $t['profile_id']) }}">
                            @csrf
                            <button type="submit" class="text-rose-600 font-medium">Bỏ vinh danh</button>
                        </form>
                    @else
                        <button type="button" @click="editing = !editing" class="text-rose-600 font-medium" x-show="! editing">Vinh danh</button>

                        <form x-show="editing" x-cloak method="POST" action="{{ route('admin.featured-teachers.feature', $t['profile_id']) }}" class="text-left w-56 space-y-2">
                            @csrf
                            <textarea name="achievement" rows="2" placeholder="Thành tích công bố (không bắt buộc)"
                                      class="w-full rounded-lg border border-slate-200 text-xs p-2">{{ $t['achievement'] }}</textarea>
                            <div class="flex gap-2">
                                <button type="submit" class="flex-1 px-3 py-1.5 rounded-lg bg-rose-600 text-white text-xs font-medium">Xác nhận vinh danh</button>
                                <button type="button" @click="editing = false" class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-500 text-xs">Huỷ</button>
                            </div>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Chưa có giáo viên nào được duyệt.</td></tr>
        @endforelse
    </x-data-table>
@endsection
