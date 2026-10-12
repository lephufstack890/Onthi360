@extends('layouts.teacher')

@section('title', 'Tổng quan')
@section('page-title', 'Tổng quan')

@section('content')
    {{--
      SỬA 12/10 (khách: "check source mới với vai trò giáo viên làm cho giống nha") — dựng lại Tổng quan theo
      TeacherOverviewPanel của source mới (education-main: TeacherDashboard.jsx): hero "Chào …", 4 thẻ số bấm được
      (Lớp phụ trách / Lượt giao bài & đề / Bài nộp chờ chấm / Quá hạn chưa nộp), "Buổi dạy tiếp theo" và
      "Việc cần xử lý" (điểm danh, nhận xét, thông báo nháp).

      GIỮ NGUYÊN những gì bản cũ có mà source không có (dữ liệu thật của Teacher\DashboardService::buildFor):
      cảnh báo hồ sơ chờ duyệt, bài chờ mở tiến độ (toOpen), học sinh cần chú ý, quyền dạy sắp hết hạn — gom vào
      "Việc cần xử lý" / thẻ "Bài cần mở tiến độ". Số liệu mới lấy từ Teacher\TeacherWorkspaceService::overview().
    --}}
    @php
        $overview = $overview ?? [];
        $upcoming = $overview['upcoming'] ?? [];
        $toOpen = $toOpen ?? [];
        $attentionStudents = $attentionStudents ?? [];
        $accessExpiring = $accessExpiring ?? null;
        $isTeacherApproved = auth()->user()->isTeacherApproved();

        $stats = [
            ['Lớp phụ trách', $overview['classCount'] ?? 0, ($overview['studentTotal'] ?? 0).' học sinh trong danh sách lớp', 'users', route('teacher.classes.index')],
            ['Lượt giao bài & đề', $overview['workTotal'] ?? 0, 'Mỗi học sinh một lượt giao riêng', 'clipboard-check', route('teacher.work.index')],
            ['Bài nộp chờ chấm', $overview['pending'] ?? 0, 'Đã nộp, chưa có điểm', 'clock', route('teacher.results.index', ['wstatus' => 'pending'])],
            ['Quá hạn chưa nộp', $overview['overdue'] ?? 0, 'Cần xem lại và nhắc học sinh', 'calendar-days', route('teacher.work.index', ['status' => 'overdue'])],
        ];

        $tasks = [];
        if (! $isTeacherApproved) {
            $tasks[] = ['Hồ sơ giáo viên chờ duyệt', 'Được duyệt rồi mới tạo lớp và giao bài thật được.', 'Xem hồ sơ', route('teacher.profile.show')];
        }
        if ($accessExpiring) {
            $tasks[] = ['Quyền dạy sắp hết hạn', '"'.$accessExpiring['product'].'" — còn '.$accessExpiring['daysLeft'].' ngày.', 'Xem quyền', route('access.myAccess')];
        }
        $tasks[] = [($overview['missingAttendance'] ?? 0).' buổi chưa đủ điểm danh', 'Chỉ tính các buổi đã bắt đầu (60 ngày gần đây).', 'Điểm danh',
            ! empty($overview['firstMissingSessionId']) ? route('teacher.schedule.attendance', $overview['firstMissingSessionId']) : route('teacher.schedule.index')];
        $tasks[] = [($overview['noFeedback'] ?? 0).' bài nộp chưa có nhận xét', 'Đọc bài nộp trước khi phản hồi.', 'Xem bài nộp', route('teacher.results.index')];
        $tasks[] = [($overview['drafts'] ?? 0).' thông báo nháp', 'Kiểm tra nội dung và người nhận.', 'Xem nháp', route('teacher.notifications.index', ['tab' => 'compose', 'filter' => 'draft'])];
        if (count($toOpen) > 0) {
            $tasks[] = [count($toOpen).' bài chờ mở tiến độ', 'Đang ở trạng thái nháp hoặc hẹn giờ của lớp.', 'Mở bài', route('teacher.classes.index')];
        }
        if (count($attentionStudents) > 0) {
            $tasks[] = [count($attentionStudents).' học sinh cần chú ý', 'Đang chậm tiến độ.', 'Lớp học', route('teacher.classes.index')];
        }
    @endphp

    @if (session('warning'))
        @include('partials.toast-flash', ['type' => 'warning', 'message' => session('warning')])
    @endif

    <div class="tw-dashboard" data-section="TEACHER-DASHBOARD">
        @include('teacher._hero')

        @unless ($isTeacherApproved)
            <div class="tw-banner">
                <x-lucide name="clock" class="h-5 w-5" />
                <p>Hồ sơ giáo viên của bạn đang <strong>chờ Admin duyệt</strong>. Sau khi được duyệt, bạn mới tạo lớp học và giao bài kiểm tra thật cho học sinh — trong lúc chờ, bạn vẫn có thể chuẩn bị câu hỏi / đề.</p>
            </div>
        @endunless

        <div class="tw-stats">
            @foreach ($stats as [$title, $value, $hint, $icon, $href])
                <a class="tw-stat" href="{{ $href }}">
                    <span><x-lucide :name="$icon" />{{ $title }}</span>
                    <strong>{{ $value }}</strong>
                    <small>{{ $hint }}</small>
                </a>
            @endforeach
        </div>

        <div class="tw-grid">
            <section class="tw-card">
                <div class="tw-heading">
                    <div><h2><x-lucide name="calendar-days" />Buổi dạy tiếp theo</h2><p>Lịch từng buổi · Giờ Việt Nam (GMT+7)</p></div>
                    <a class="tw-button" href="{{ route('teacher.schedule.index') }}">Xem lịch</a>
                </div>
                @forelse ($upcoming as $i => $s)
                    <article class="tw-row {{ $i === 0 ? 'tw-highlight' : '' }}">
                        <time>{{ $s['time'] }}{{ $s['live'] ? ' · Đang diễn ra' : '' }}</time>
                        <h3>{{ $s['topic'] }}</h3>
                        <p>{{ $s['class'] }}</p>
                        <div class="tw-actions">
                            <a class="tw-button" href="{{ route('teacher.classes.show', $s['classId']) }}">Vào lớp <x-lucide name="arrow-right" /></a>
                            <a class="tw-secondary" href="{{ route('teacher.schedule.attendance', $s['id']) }}">Xem buổi học</a>
                        </div>
                    </article>
                @empty
                    <p class="tw-empty">Chưa có buổi dạy sắp tới trong lịch.</p>
                @endforelse
            </section>

            <section class="tw-card">
                <div class="tw-heading">
                    <div><h2><x-lucide name="clipboard-check" />Việc cần xử lý</h2><p>Tổng hợp từ buổi học và lượt giao hiện có.</p></div>
                </div>
                @foreach ($tasks as [$title, $note, $label, $href])
                    <div class="tw-task">
                        <div><h3>{{ $title }}</h3><p>{{ $note }}</p></div>
                        <a class="tw-button" href="{{ $href }}">{{ $label }}</a>
                    </div>
                @endforeach
            </section>
        </div>

        @if (count($toOpen) > 0)
            <section class="tw-card">
                <div class="tw-heading">
                    <div><h2><x-lucide name="clipboard-list" />Bài cần mở tiến độ</h2><p>Bài của lớp đang ở trạng thái nháp hoặc hẹn giờ.</p></div>
                    <a class="tw-secondary" href="{{ route('teacher.classes.index') }}">Mở lớp học</a>
                </div>
                @foreach ($toOpen as $t)
                    <div class="tw-task">
                        <div><h3>{{ $t['title'] }}</h3><p>{{ $t['class'] }}{{ $t['chapter'] ? ' · '.$t['chapter'] : '' }}</p></div>
                        <a class="tw-secondary" href="{{ route('teacher.classes.index') }}">Mở ngay</a>
                    </div>
                @endforeach
            </section>
        @endif
    </div>
@endsection
