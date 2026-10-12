{{-- Menu trái khu Học sinh.
     SỬA 12/10 — dựng lại theo StudentWorkspaceMenu.jsx + khung <RoleWorkspace> của source mới:
     tiêu đề "Không gian học tập", 2 nhóm "Học tập" / "Cá nhân", mục đang mở nền xanh, thẻ
     "Cần hỗ trợ?" ở cuối. GIỮ ĐỦ mọi mục sẵn có của hệ thống (Thời khoá biểu, Luyện tập, Cuộc thi,
     Bảng xếp hạng, Ví token, Mã kích hoạt) — xếp vào đúng nhóm. --}}
@php
    $wsNavCompact = $wsNavCompact ?? false;
    $wsNavTitle = $wsNavTitle ?? 'Không gian học tập';

    // [nhãn, route, icon, các mẫu route coi là "đang mở"]
    $groups = [
        'Học tập' => [
            ['Tổng quan', 'dashboard', 'layout-dashboard', ['dashboard', 'student.dashboard']],
            ['Lớp học của tôi', 'student.courses.index', 'book-open', ['student.courses.*', 'student.classes.*']],
            ['Thời khoá biểu', 'student.schedule.index', 'calendar-days', ['student.schedule.*']],
            ['Luyện tập', 'student.practice.index', 'notebook-pen', ['student.practice.*', 'student.practiceByQuestion.*', 'student.assessment.*']],
            ['Bài & đề được giao', 'student.assignments.index', 'clipboard-check', ['student.assignments.*']],
            ['Kết quả & Lịch sử', 'student.results.index', 'trophy', ['student.results.*']],
            ['Tài liệu của tôi', 'student.library.index', 'library', ['student.library.*', 'student.materials.*', 'student.products.*']],
            ['Cuộc thi', 'competitions.index', 'medal', ['competitions.*', 'student.competitions.*']],
            ['Bảng xếp hạng', 'leaderboard.index', 'bar-chart-3', ['leaderboard.*']],
        ],
        'Cá nhân' => [
            ['Thông báo', 'student.notifications', 'message-square-text', ['student.notifications']],
            ['Phụ huynh', 'student.parents.index', 'users', ['student.parents.*']],
            ['Đánh giá của tôi', 'student.reviews.index', 'star', ['student.reviews.*', 'reviews.*']],
            ['Ví token', 'wallet.index', 'wallet-cards', ['wallet.*']],
            ['Mã kích hoạt', 'access.activate', 'key-round', ['access.activate*']],
            ['Thông tin cá nhân', 'student.profile', 'user-cog', ['student.profile*', 'student.password.*']],
        ],
    ];
@endphp

@once
<style>
    .stu-nav { border: 1px solid #e0f2fe; border-radius: 24px; background: #fff; padding: 12px; box-shadow: 0 2px 8px rgba(0,90,180,.04); font-size: 12px; }
    .stu-nav.is-compact { border: 0; border-radius: 0; padding: 0; box-shadow: none; }
    .stu-nav-title { margin: 0; padding: 4px 8px 12px; border-bottom: 1px solid #f1f5f9; font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #94a3b8; }
    .stu-nav-group + .stu-nav-group { margin-top: 10px; padding-top: 8px; border-top: 1px solid #edf2f7; }
    .stu-nav-gt { margin: 9px 10px 5px; font-size: 10px; font-weight: 600; color: #7890a2; }
    .stu-nav-list { display: flex; flex-direction: column; }
    .stu-nav a { display: flex; width: 100%; min-height: 39px; align-items: center; gap: 10px; margin-top: 3px; border: 1px solid transparent; border-radius: 10px; padding: 8px 10px; color: #536b80; font-size: 12px; font-weight: 600; text-decoration: none; transition: background-color 150ms ease, color 150ms ease; }
    .stu-nav a svg { width: 16px; height: 16px; flex-shrink: 0; }
    .stu-nav a span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .stu-nav a:hover { color: #1d65a0; background: #f0f8fd; }
    .stu-nav a[aria-current="page"] { color: #fff; background: #2563eb; border-color: #2563eb; }
    .stu-nav a:focus-visible { outline: 2px solid #93c5fd; outline-offset: 2px; }
    .stu-nav-help { margin-top: 12px; padding: 12px; border: 1px solid #dbeafe; border-radius: 16px; background: linear-gradient(135deg, #f0f9ff, #eff6ff); }
    .stu-nav-help b { display: block; font-size: 10px; color: #1d4ed8; }
    .stu-nav-help p { margin: 4px 0 0; font-size: 10px; line-height: 1.6; color: #64748b; }
    .stu-nav-help a { display: inline; width: auto; min-height: 0; margin: 8px 0 0; padding: 0; border: 0; background: none; font-size: 10px; font-weight: 700; color: #2563eb; }
    .stu-nav-help a:hover { background: none; text-decoration: underline; }
    .stu-nav.is-compact .stu-nav-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 6px; }
    @media (min-width: 640px) { .stu-nav.is-compact .stu-nav-list { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
</style>
@endonce

<nav class="stu-nav {{ $wsNavCompact ? 'is-compact' : '' }}" aria-label="Menu học sinh">
    @unless ($wsNavCompact)
        <p class="stu-nav-title">{{ $wsNavTitle }}</p>
    @endunless
    @foreach ($groups as $groupTitle => $rows)
        <div class="stu-nav-group" role="group" aria-label="{{ $groupTitle }}">
            <p class="stu-nav-gt">{{ $groupTitle }}</p>
            <div class="stu-nav-list">
                @foreach ($rows as [$label, $routeName, $icon, $match])
                    <a href="{{ route($routeName) }}" @if (request()->routeIs(...$match)) aria-current="page" @endif>
                        <x-lucide :name="$icon" /><span>{{ $label }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
    @unless ($wsNavCompact)
        <div class="stu-nav-help">
            <b>Cần hỗ trợ?</b>
            <p>Đội ngũ Ôn Thi 360 luôn sẵn sàng đồng hành.</p>
            <a href="{{ route('info.index') }}">Liên hệ hỗ trợ →</a>
        </div>
    @endunless
</nav>
