@extends('layouts.student')

@section('title', 'Tổng quan')
@section('page-title', 'Tổng quan')

@section('content')
    {{--
      SỬA 12/10 — DỰNG LẠI THEO education-main (source mới): StudentOverview.jsx + StudentConnections.jsx.
      Thứ tự khối: Hero → "Bài & đề được giao" (4 ô số) → "Phụ huynh đã liên kết" + "Giáo viên của tôi"
      → "Cần hoàn thành" + "Buổi học tiếp theo". Dữ liệu thật: Student\StudentWorkspaceService::overview().
    --}}
    @php
        $counts = $counts ?? [];
        $pending = $pending ?? [];
        $sessions = $sessions ?? [];
        $teachers = $teachers ?? [];
        $parents = $parents ?? [];
        $total = $counts['total'] ?? 0;
        $pendingN = $counts['pending'] ?? 0;
        $overdueN = $counts['overdue'] ?? 0;
        $summary = $total === 0
            ? 'Chưa có bài hoặc đề nào được giao'
            : $total.' mục được giao · '.($pendingN ? $pendingN.' mục cần làm'.($overdueN ? ', trong đó '.$overdueN.' quá hạn' : '') : 'Bạn đã nộp tất cả bài và đề được giao');
        $tiles = [
            ['Bài tập cần làm', $counts['pendingExercises'] ?? 0, 'Gồm chưa làm và đang làm', 'book-open', 'blue'],
            ['Đề cần làm', $counts['pendingExams'] ?? 0, 'Gồm chưa làm và đang làm', 'file-text', 'violet'],
            ['Chờ chấm', $counts['submitted'] ?? 0, 'Đã nộp, chưa hoàn thành', 'clock-3', 'amber'],
            ['Hoàn thành', $counts['completed'] ?? 0, 'Bài và đề đã ghi nhận', 'check-circle-2', 'teal'],
        ];
    @endphp

    <div class="sw student-overview">
        @include('student._hero')

        <section class="student-overview-card">
            <div class="student-overview-heading">
                <h2 class="student-overview-title"><x-lucide name="clipboard-check" class="h-4 w-4" />Bài &amp; đề được giao</h2>
            </div>
            <p class="student-overview-muted">{{ $summary }}</p>
            <dl class="student-work-counts">
                @foreach ($tiles as [$label, $value, $hint, $icon, $tone])
                    <div class="student-work-count student-work-{{ $tone }}">
                        <dt><x-lucide :name="$icon" class="h-3.5 w-3.5" />{{ $label }}</dt>
                        <dd>{{ $value }}</dd>
                        <p>{{ $hint }}</p>
                    </div>
                @endforeach
            </dl>
        </section>

        <div class="student-overview-grid">
            <section class="student-overview-card">
                <h2 class="student-overview-title"><x-lucide name="users" class="h-4 w-4" />Phụ huynh đã liên kết</h2>
                @forelse ($parents as $p)
                    <div class="student-connection-person">
                        <span class="student-connection-avatar"><x-lucide name="users" class="h-5 w-5" /></span>
                        <div>
                            <h3>{{ $p['name'] }}</h3>
                            <p>{{ $p['relationship'] }}@if ($p['email']) · {{ $p['email'] }}@endif</p>
                        </div>
                        <span class="student-connected"><x-lucide name="check-circle-2" class="h-3 w-3" />{{ $p['active'] ? 'Đã liên kết' : 'Không hoạt động' }}</span>
                    </div>
                @empty
                    <p class="student-overview-empty">Chưa có phụ huynh đã liên kết.</p>
                @endforelse
                <a class="student-overview-action" href="{{ route('student.parents.index') }}">Quản lý liên kết</a>
            </section>

            <section class="student-overview-card">
                <h2 class="student-overview-title"><x-lucide name="graduation-cap" class="h-4 w-4" />Giáo viên của tôi</h2>
                <p class="student-overview-muted">Giáo viên phụ trách các lớp bạn đang tham gia.</p>
                @if (count($teachers))
                    <div class="student-teacher-list">
                        @foreach ($teachers as $t)
                            <article class="student-connection-person" aria-label="{{ $t['name'] }}">
                                @if ($t['avatar'])
                                    <img src="{{ $t['avatar'] }}" alt="" loading="lazy">
                                @else
                                    <span class="student-connection-avatar">{{ $t['initial'] }}</span>
                                @endif
                                <div style="min-width:0;flex:1">
                                    <h3>{{ $t['name'] }}</h3>
                                    <p>{{ $t['title'] }}</p>
                                </div>
                                <a class="student-overview-action" href="{{ $t['url'] }}" aria-label="Xem lớp của {{ $t['name'] }}">Xem lớp</a>
                            </article>
                        @endforeach
                    </div>
                @else
                    <p class="student-overview-empty">Bạn chưa tham gia lớp nào.</p>
                @endif
            </section>
        </div>

        <div class="student-overview-grid">
            <section class="student-overview-card">
                <div class="student-overview-heading">
                    <h2 class="student-overview-title"><x-lucide name="clipboard-check" class="h-4 w-4" />Cần hoàn thành</h2>
                    <a class="student-overview-action" href="{{ route('student.assignments.index') }}">Xem tất cả <x-lucide name="arrow-right" class="h-3 w-3" /></a>
                </div>
                <p class="student-overview-muted">Ưu tiên hạn nộp gần nhất. Bài đã nộp chờ chấm nằm ở mục riêng.</p>
                @if (count($pending))
                    <div class="student-overview-list">
                        @foreach ($pending as $item)
                            <article aria-label="{{ $item['title'] }}">
                                <div class="student-task-heading">
                                    <span class="student-task-kind {{ $item['kind'] === 'exam' ? 'is-exam' : '' }}">{{ $item['kind'] === 'exam' ? 'Đề' : 'Bài tập' }}</span>
                                    <span class="student-overview-muted">{{ $item['state'] === 'active' ? 'Đang làm' : 'Chưa làm' }}</span>
                                </div>
                                <h3>{{ $item['title'] }}</h3>
                                <p class="student-overview-muted">{{ $item['className'] }}</p>
                                <div class="student-overview-heading">
                                    <p class="{{ $item['overdue'] ? 'student-overdue' : 'student-overview-muted' }}">{{ $item['overdue'] ? 'Quá hạn' : 'Hạn nộp' }}: {{ $item['deadline'] ?? 'Không có hạn' }}</p>
                                    <a class="student-overview-action" href="{{ route('student.assignments.index', ['open' => $item['id']]) }}" aria-label="Xem {{ $item['title'] }}">Xem chi tiết <x-lucide name="arrow-right" class="h-3 w-3" /></a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <p class="student-overview-empty">Không còn bài hoặc đề cần làm.</p>
                @endif
            </section>

            <section class="student-overview-card">
                <div class="student-overview-heading">
                    <h2 class="student-overview-title"><x-lucide name="calendar-days" class="h-4 w-4" />Buổi học tiếp theo</h2>
                    <a class="student-overview-action" href="{{ route('student.courses.index') }}">Xem lớp học <x-lucide name="arrow-right" class="h-3 w-3" /></a>
                </div>
                <p class="student-overview-muted">Theo lịch lớp đã thiết lập · Giờ Việt Nam (GMT+7).</p>
                @if (count($sessions))
                    <div class="student-overview-list">
                        @foreach ($sessions as $i => $s)
                            <article class="{{ $i === 0 ? 'student-next-session' : '' }}" aria-label="Buổi học {{ $s['title'] }}">
                                <div class="student-overview-heading">
                                    <span class="student-session-date">{{ $s['date'] }} · {{ $s['time'] }}</span>
                                    <span class="student-overview-muted">{{ $s['ongoing'] ? 'Đang diễn ra' : ($i === 0 ? 'Gần nhất' : 'Sắp tới') }}</span>
                                </div>
                                <h3>{{ $s['title'] }}</h3>
                                @if ($s['teacher'])<p class="student-overview-muted">{{ $s['teacher'] }}</p>@endif
                                @if ($i === 0)
                                    <a class="student-class-enter" href="{{ $s['url'] }}">Vào lớp <x-lucide name="arrow-right" class="h-3.5 w-3.5" /></a>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @else
                    <p class="student-overview-empty">Chưa có buổi học sắp tới trong lịch đã thiết lập.</p>
                @endif
                <a class="student-overview-action" href="{{ route('student.notifications') }}">Xem thông báo</a>
            </section>
        </div>
    </div>
@endsection
