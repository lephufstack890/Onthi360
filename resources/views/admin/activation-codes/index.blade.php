{{--
  Route: admin.activation-codes.index
  Spec: 7.4 — mã sai scope không tự chuyển đổi (mã quyền dạy không kích hoạt thành quyền học sinh).
  TODO controller: truyền $codes (paginate) với filter trạng thái.
--}}
@extends('layouts.admin')

@section('title', 'Mã kích hoạt')
@section('page-title', 'Mã kích hoạt')

@section('content')
    {{-- Dữ liệu thật do App\Http\Controllers\Admin\ActivationCodeController truyền vào. --}}
    @php
        $codes = $codes ?? [];
    @endphp

    <x-page-header title="Mã kích hoạt" subtitle="Thời hạn quyền bắt đầu tại thời điểm kích hoạt mã hợp lệ, không phải lúc đặt đơn (7.4)." />

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
                <td class="px-4 py-3 text-right"><a href="#" class="text-slate-400">Chi tiết</a></td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Chưa có mã kích hoạt nào.</td></tr>
        @endforelse
    </x-data-table>
@endsection
