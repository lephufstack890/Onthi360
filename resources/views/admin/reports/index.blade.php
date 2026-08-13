{{--
  Route: admin.reports.index
  Spec: 2.3 (P0: báo cáo vận hành cơ bản; P1: báo cáo thương mại sâu/chiến
  dịch — KHÔNG thuộc trang này). Dữ liệu thật do App\Services\Admin\ReportService
  truyền vào.
--}}
@extends('layouts.admin')

@section('title', 'Báo cáo')
@section('page-title', 'Báo cáo')

@section('content')
    @php
        $orderStats = $orderStats ?? [];
        $activationStats = $activationStats ?? [];
        $reviewStats = $reviewStats ?? [];
        $competitionStats = $competitionStats ?? [];
        $userStats = $userStats ?? [];
    @endphp

    <x-page-header title="📄 Báo cáo vận hành" subtitle="Số liệu vận hành cơ bản cho P0; báo cáo thương mại sâu/chiến dịch thuộc P1, ngoài phạm vi trang này (2.3)." />

    <div class="space-y-6">
        <div>
            <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-3">Đơn hàng</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-stat-tile label="Chờ duyệt" :value="$orderStats['pendingApproval'] ?? 0" tone="warning" />
                <x-stat-tile label="Đã hoàn tất" :value="$orderStats['completed'] ?? 0" tone="success" />
                <x-stat-tile label="Từ chối / Huỷ" :value="$orderStats['rejectedOrCanceled'] ?? 0" tone="danger" />
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-3">Mã kích hoạt</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-stat-tile label="Chưa dùng" :value="$activationStats['unused'] ?? 0" />
                <x-stat-tile label="Đã kích hoạt" :value="$activationStats['activated'] ?? 0" tone="success" />
                <x-stat-tile label="Đã thu hồi" :value="$activationStats['revoked'] ?? 0" tone="danger" />
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-3">Đánh giá</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-stat-tile label="Chờ kiểm duyệt" :value="$reviewStats['pendingModeration'] ?? 0" tone="warning" />
                <x-stat-tile label="Đã công bố" :value="$reviewStats['published'] ?? 0" tone="success" />
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-3">Cuộc thi</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-stat-tile label="Đang diễn ra" :value="$competitionStats['ongoing'] ?? 0" />
                <x-stat-tile label="Chờ công bố kết quả" :value="$competitionStats['pendingPublish'] ?? 0" tone="warning" />
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-3">Người dùng theo vai trò</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-stat-tile label="Học viên" :value="$userStats['students'] ?? 0" />
                <x-stat-tile label="Giáo viên" :value="$userStats['teachers'] ?? 0" />
                <x-stat-tile label="Phụ huynh" :value="$userStats['parents'] ?? 0" />
            </div>
        </div>
    </div>
@endsection
