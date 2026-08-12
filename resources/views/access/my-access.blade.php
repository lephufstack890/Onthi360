{{--
  Route: access.myAccess | Frame: ACC-07 "Quyền của tôi"
  Spec: 7.3 — phân biệt Đang có quyền / Sắp hết hạn / Đã hết hạn +
  lịch sử; hết hạn vẫn xem được lịch sử nộp/điểm/kết quả cũ.
  TODO controller: truyền $rights = auth()->user()->accessRights thật.
--}}
@extends('layouts.student')

@section('title', 'Quyền của tôi')
@section('page-title', 'Quyền của tôi')

@section('content')
    @php
        $tab = request('tab', 'active');
        $tabs = [
            ['label' => 'Đang có quyền', 'href' => route('access.myAccess'), 'active' => $tab === 'active', 'count' => 2],
            ['label' => 'Sắp hết hạn', 'href' => route('access.myAccess', ['tab' => 'expiring']), 'active' => $tab === 'expiring', 'count' => 1],
            ['label' => 'Đã hết hạn', 'href' => route('access.myAccess', ['tab' => 'expired']), 'active' => $tab === 'expired', 'count' => 3],
        ];
        $rights = [
            ['title' => 'Sách: Ôn thi Tin học 10', 'expires' => '30/06/2027', 'status' => 'Còn hiệu lực', 'tone' => 'success'],
        ];
    @endphp

    <x-page-header title="Quyền của tôi" subtitle="Hết hạn vẫn xem được lịch sử nộp, điểm và kết quả cũ — chỉ không đọc/làm/nộp mới nội dung được bảo vệ (7.3)." />

    <x-tabs :tabs="$tabs" />

    <div class="space-y-3">
        @forelse ($rights as $r)
            <div class="bg-white rounded-2xl border border-slate-200 p-4 flex items-center justify-between flex-wrap gap-3">
                <div>
                    <p class="font-medium text-slate-700">{{ $r['title'] }}</p>
                    <p class="text-xs text-slate-400">Hết hạn: {{ $r['expires'] }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <x-status-badge :tone="$r['tone']">{{ $r['status'] }}</x-status-badge>
                    <a href="{{ route('materials.show', 1) }}" class="text-sm text-rose-600 font-medium">Gia hạn</a>
                </div>
            </div>
        @empty
            <x-empty-state title="Không có quyền nào ở trạng thái này" />
        @endforelse
    </div>
@endsection
