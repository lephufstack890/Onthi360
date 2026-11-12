{{-- SỬA 12/11 (khách: "bỏ tab Danh sách bảng xếp hạng, không cần — chỉ để mỗi Tài khoản vi phạm") —
     màn Quản trị › Bảng xếp hạng giờ CHỈ còn màn Tài khoản vi phạm. File này tự nạp dữ liệu rồi dựng đúng màn đó, nên dù
     controller nào trả view 'admin.ranking.index' cũng không còn hiện bảng danh sách / dải tab cũ. --}}
@php
    $__vp = app(\App\Services\Admin\LeaderboardPenaltyService::class)->indexData(request()->query('filter')) + [
        'reasons' => \App\Models\LeaderboardPenalty::REASONS,
        'lockDays' => \App\Models\LeaderboardPenalty::LOCK_DAYS,
    ];
@endphp
@include('admin.ranking.violations', $__vp)
