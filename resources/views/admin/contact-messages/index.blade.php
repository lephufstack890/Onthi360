{{--
  Route: admin.contact-messages.index
  Spec: PUB-11 (4.1 mục "Liên hệ") — tin nhắn gửi từ form Liên hệ ở trang công khai info.index.
  Gắn chung nhóm điều hướng với "Đánh giá" (không có mục nav riêng — sidebar cố định đúng 12
  mục theo BA spec), chuyển qua lại bằng tabs như cặp Cuộc thi/Giáo viên tiêu biểu.
--}}
@extends('layouts.admin')

@section('title', 'Tin nhắn liên hệ')
@section('page-title', 'Tin nhắn liên hệ')

@section('content')
    {{-- Dữ liệu thật do App\Http\Controllers\Admin\ContactMessageController truyền vào. --}}
    @php
        $tabs = $tabs ?? [];
        $messages = $messages ?? [];
    @endphp

    <x-page-header title="✉️ Tin nhắn liên hệ" subtitle="Tin nhắn khách gửi từ form Liên hệ ở trang Thông tin công khai." />

    @if (session('status'))
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã đánh dấu tin nhắn là đã xử lý.'])
    @endif

    <x-tabs :tabs="$tabs" />

    <x-data-table :columns="['Người gửi', 'Nội dung', 'Thời gian', 'Trạng thái', '']">
        @forelse ($messages as $m)
            <tr>
                <td class="px-4 py-3 align-top">
                    <p class="font-medium text-slate-700">{{ $m['name'] }}</p>
                    <p class="text-xs text-slate-400">{{ $m['email'] }}</p>
                </td>
                <td class="px-4 py-3 text-slate-500 max-w-md align-top">
                    <p class="whitespace-pre-line">{{ $m['message'] }}</p>
                </td>
                <td class="px-4 py-3 text-slate-400 text-xs align-top">{{ $m['created_at'] }}</td>
                <td class="px-4 py-3 align-top">
                    <x-status-badge :tone="$m['resolved'] ? 'success' : 'warning'">
                        {{ $m['resolved'] ? 'Đã xử lý'.($m['handled_by'] ? ' — '.$m['handled_by'] : '') : 'Mới' }}
                    </x-status-badge>
                </td>
                <td class="px-4 py-3 text-right align-top">
                    @unless ($m['resolved'])
                        <form method="POST" action="{{ route('admin.contact-messages.resolve', $m['id']) }}">
                            @csrf
                            <button type="submit" class="text-rose-600 font-medium">Đánh dấu đã xử lý</button>
                        </form>
                    @endunless
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Chưa có tin nhắn liên hệ nào.</td></tr>
        @endforelse
    </x-data-table>
@endsection
