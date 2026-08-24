@extends('layouts.admin')

@section('title', 'Mã kích hoạt')
@section('page-title', 'Mã kích hoạt')

@section('content')
    @php
        $codes = $codes ?? [];
        $codeStatusMessage = match (session('status')) {
            'code-revoked' => 'Đã thu hồi mã, đã ghi lý do.',
            default => null,
        };
    @endphp
    @if ($codeStatusMessage)
        @include('partials.toast-flash', ['type' => 'success', 'message' => $codeStatusMessage])
    @endif
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <x-page-header title="🔑 Mã kích hoạt" subtitle="Thời hạn quyền bắt đầu tại thời điểm kích hoạt mã hợp lệ, không phải lúc đặt đơn" />

    <x-data-table :columns="['Mã', 'Đơn liên quan', 'Phạm vi', 'Trạng thái', '']">
        @forelse ($codes as $c)
            <tr>
                <td class="px-4 py-3 font-mono text-slate-700">{{ $c['code'] }}</td>
                <td class="px-4 py-3 text-slate-500">
                    @if ($c['order'])
                        <a href="{{ route('admin.orders.show', $c['order']) }}" class="text-rose-600">#OD-{{ $c['order'] }}</a>
                    @else
                        —
                    @endif
                </td>
                <td class="px-4 py-3 text-slate-500">{{ $c['scope'] }}</td>
                <td class="px-4 py-3"><x-status-badge :tone="$c['tone']">{{ $c['status'] }}</x-status-badge></td>
                <td class="px-4 py-3 text-right">
                    @if ($c['canRevoke'])
                        <div x-data="{ open: false, reason: '' }" class="inline-block text-left">
                            <button type="button" @click="open = !open" class="text-rose-600 font-medium" x-text="open ? 'Đóng' : 'Thu hồi'"></button>
                            <form x-show="open" x-cloak method="POST" action="{{ route('admin.activation-codes.revoke', $c['id']) }}" class="mt-2 space-y-2 text-left bg-slate-50 border border-slate-200 rounded-lg p-3 w-64">
                                @csrf
                                <textarea name="reason" x-model="reason" rows="2" required class="w-full rounded-lg border border-slate-200 text-xs p-2" placeholder="Lý do thu hồi (bắt buộc)..."></textarea>
                                <button type="submit" :disabled="reason.trim().length === 0" class="w-full px-3 py-1.5 rounded-lg bg-rose-600 text-white text-xs font-medium disabled:opacity-40 disabled:cursor-not-allowed">Xác nhận thu hồi</button>
                            </form>
                        </div>
                    @else
                        <span class="text-slate-300">—</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Chưa có mã kích hoạt nào.</td></tr>
        @endforelse
    </x-data-table>
@endsection
