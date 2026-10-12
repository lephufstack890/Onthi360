{{-- Menu trái khu Giáo viên.
     SỬA 12/10 — dựng lại theo TeacherWorkspaceMenu.jsx + khung <RoleWorkspace> của source mới: tiêu đề
     "Không gian giảng dạy", 2 nhóm "Giảng dạy" / "Cá nhân", mục đang mở nền xanh lá, thẻ "Cần hỗ trợ?" ở cuối.
     GIỮ ĐỦ mọi mục sẵn có của hệ thống (Luyện tập khi bật cờ, Kho bài tập / câu hỏi và đề, Cuộc thi, Ví token,
     Mã kích hoạt, Đổi mật khẩu) — xếp vào đúng nhóm. Luật cũ giữ nguyên: route trùng nhau thì chỉ mục ĐẦU TIÊN sáng. --}}
@php
    $wsNavCompact = $wsNavCompact ?? false;
    $wsNavTitle = $wsNavTitle ?? 'Không gian giảng dạy';

    // [nhãn, route, icon, các mẫu route coi là "đang mở"]
    $groups = [
        'Giảng dạy' => array_values(array_filter([
            ['Tổng quan', 'dashboard', 'layout-dashboard', ['dashboard', 'teacher.dashboard']],
            ['Lớp học', 'teacher.classes.index', 'users', ['teacher.classes.*']],
            ['Bài & đề', 'teacher.work.index', 'clipboard-check', ['teacher.work.*']],
            config('features.teacher_practice_screen', false)
                ? ['Luyện tập', 'teacher.assessments.index', 'notebook-pen', ['teacher.assessments.*']]
                : null,
            // SỬA 7/10 (khách: bỏ "Đề PDF của tôi", đổi "Kho câu hỏi của tôi" thành "Kho bài tập / câu hỏi và đề") — đề PDF
            // giờ là tab "Đề/bộ bài" trong màn này; các route con (trang quản lý/tạo đề PDF) cũng làm sáng mục này.
            ['Kho bài tập / câu hỏi và đề', 'teacher.questions.index', 'library', ['teacher.papers.*', 'teacher.questions.*']],
            ['Cuộc thi', 'teacher.competitions.index', 'trophy', ['teacher.competitions.*']],
            ['Học liệu', 'teacher.library.index', 'book-open', ['teacher.library.*', 'teacher.materials.*', 'teacher.products.*']],
            ['Lịch & điểm danh', 'teacher.schedule.index', 'calendar-days', ['teacher.schedule.*']],
            ['Kết quả & nhận xét', 'teacher.results.index', 'trending-up', ['teacher.results.*']],
        ])),
        'Cá nhân' => [
            ['Thông báo', 'teacher.notifications.index', 'message-square-text', ['teacher.notifications.*', 'teacher.notices.*']],
            // SỬA 14/9 — khu Giáo viên cũng có ví token, cùng route với khu Học sinh.
            ['Ví token', 'wallet.index', 'wallet-cards', ['wallet.*']],
            ['Mã kích hoạt', 'access.activate', 'key-round', ['access.activate*']],
            ['Thông tin cá nhân', 'teacher.profile.show', 'user-cog', ['teacher.profile.*']],
            ['Đổi mật khẩu', 'teacher.password.edit', 'lock', ['teacher.password.*']],
        ],
    ];

    // Giữ nguyên luật cũ: route trùng nhau thì chỉ mục ĐẦU TIÊN được tính là đang mở.
    $primaryRoutes = [];
@endphp

@once
<style>
    .tea-nav { border: 1px solid #e0f2fe; border-radius: 24px; background: #fff; padding: 12px; box-shadow: 0 2px 8px rgba(0,90,180,.04); font-size: 12px; }
    .tea-nav.is-compact { border: 0; border-radius: 0; padding: 0; box-shadow: none; }
    .tea-nav-title { margin: 0; padding: 4px 8px 12px; border-bottom: 1px solid #f1f5f9; font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #94a3b8; }
    .tea-nav-group + .tea-nav-group { margin-top: 10px; padding-top: 8px; border-top: 1px solid #edf2f7; }
    .tea-nav-gt { margin: 9px 10px 5px; font-size: 10px; font-weight: 600; color: #7890a2; }
    .tea-nav-list { display: flex; flex-direction: column; }
    .tea-nav a { display: flex; width: 100%; min-height: 39px; align-items: center; gap: 10px; margin-top: 3px; border: 1px solid transparent; border-radius: 10px; padding: 8px 10px; color: #536b80; font-size: 12px; font-weight: 600; text-decoration: none; transition: background-color 150ms ease, color 150ms ease; }
    .tea-nav a svg { width: 16px; height: 16px; flex-shrink: 0; }
    .tea-nav a span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .tea-nav a:hover { color: #176b50; background: #f0f8f4; }
    .tea-nav a[aria-current="page"] { color: #fff; background: #176b50; border-color: #176b50; }
    .tea-nav a:focus-visible { outline: 2px solid #34b78b; outline-offset: 2px; }
    .tea-nav-help { margin-top: 12px; padding: 12px; border: 1px solid #dbeafe; border-radius: 16px; background: linear-gradient(135deg, #f0f9ff, #eff6ff); }
    .tea-nav-help b { display: block; font-size: 10px; color: #1d4ed8; }
    .tea-nav-help p { margin: 4px 0 0; font-size: 10px; line-height: 1.6; color: #64748b; }
    .tea-nav-help a { display: inline; width: auto; min-height: 0; margin: 8px 0 0; padding: 0; border: 0; background: none; font-size: 10px; font-weight: 700; color: #2563eb; }
    .tea-nav-help a:hover { background: none; text-decoration: underline; }
    .tea-nav.is-compact .tea-nav-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 6px; }
    @media (min-width: 640px) { .tea-nav.is-compact .tea-nav-list { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
</style>
@endonce

<nav class="tea-nav {{ $wsNavCompact ? 'is-compact' : '' }}" aria-label="Menu giáo viên">
    @unless ($wsNavCompact)
        <p class="tea-nav-title">{{ $wsNavTitle }}</p>
    @endunless
    @foreach ($groups as $groupTitle => $rows)
        <div class="tea-nav-group" role="group" aria-label="{{ $groupTitle }}">
            <p class="tea-nav-gt">{{ $groupTitle }}</p>
            <div class="tea-nav-list">
                @foreach ($rows as [$label, $routeName, $icon, $match])
                    @php
                        $isPrimary = ! in_array($routeName, $primaryRoutes, true);
                        $primaryRoutes[] = $routeName;
                    @endphp
                    <a href="{{ route($routeName) }}" @if ($isPrimary && request()->routeIs(...$match)) aria-current="page" @endif>
                        <x-lucide :name="$icon" /><span>{{ $label }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
    @unless ($wsNavCompact)
        <div class="tea-nav-help">
            <b>Cần hỗ trợ?</b>
            <p>Đội ngũ Ôn Thi 360 luôn sẵn sàng đồng hành.</p>
            <a href="{{ route('info.index') }}">Liên hệ hỗ trợ →</a>
        </div>
    @endunless
</nav>
