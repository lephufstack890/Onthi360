@extends('layouts.teacher')

@section('title', 'Kết quả & nhận xét')
@section('page-title', 'Kết quả & nhận xét')

@section('content')
    {{--
      SỬA 12/10 — dựng lại theo TeacherWorkPanel (resultsOnly) của source mới: tab "Bài nộp & nhận xét" liệt kê lượt giao
      riêng đã có bài nộp (xem bài làm, điểm, lưu nhận xét cho học sinh). GIỮ NGUYÊN phễu cũ Lớp → Đề → Học sinh → Lần nộp
      (10.2) ở tab "Theo lớp học", kể cả lọc theo trạng thái, xuất CSV và trang chi tiết từng lần nộp.
    --}}
    @php
        $classRooms = $classRooms ?? collect();
        $assignments = $assignments ?? collect();
        $students = $students ?? collect();
        $stats = $stats ?? ['submitted' => 0, 'inProgress' => 0, 'notStarted' => 0];
        $selectedStatus = $selectedStatus ?? '';
        $exportQuery = ['class' => $selectedClassId ?? null, 'assessment' => $selectedAssignmentId ?? null, 'status' => $selectedStatus];
        $tab = $tab ?? 'work';
        $workRows = $workRows ?? [];
        $payload = [
            'rows' => $workRows,
            'urls' => ['feedback' => url('teacher/bai-va-de')],
            'initialStatus' => $initialStatus ?? 'all',
            'stateKeys' => ['pending', 'completed'],
        ];
    @endphp

    <div class="tw-dashboard" data-section="TEACHER-RESULTS" @if ($tab === 'work') x-data="twWorkList(@js($payload), { emptyAll: 'Chưa có bài nộp phù hợp.', emptyFiltered: 'Chưa có bài nộp phù hợp.' })" @endif>
        @include('teacher._hero')

        <div class="tw-tabs" role="tablist" aria-label="Kết quả">
            <a class="tw-tab" href="{{ route('teacher.results.index') }}" @if ($tab === 'work') aria-current="page" @endif><x-lucide name="file-text" />Bài nộp &amp; nhận xét</a>
            <a class="tw-tab" href="{{ route('teacher.results.index', ['tab' => 'class']) }}" @if ($tab === 'class') aria-current="page" @endif><x-lucide name="graduation-cap" />Theo lớp học</a>
        </div>

        @if ($tab === 'work')
            @include('teacher.work._rows', ['resultsOnly' => true])
        @else
            <section class="tw-card">
                <div class="tw-heading">
                    <div><h2><x-lucide name="graduation-cap" />Kết quả theo lớp học</h2><p>Phễu Lớp → Đề → Học sinh → Lần nộp.</p></div>
                    <a class="tw-secondary" href="{{ route('teacher.results.export', $exportQuery) }}">⇩ Xuất CSV</a>
                </div>

                <form method="GET" action="{{ route('teacher.results.index') }}" class="tw-filters">
                    <input type="hidden" name="tab" value="class">
                    <label>Lớp
                        <select name="class" onchange="this.form.submit()">
                            @forelse ($classRooms as $c)
                                <option value="{{ $c->id }}" @selected(($selectedClassId ?? null) == $c->id)>{{ $c->name }}</option>
                            @empty
                                <option>Chưa có lớp nào</option>
                            @endforelse
                        </select>
                    </label>
                    <label>Đề
                        <select name="assessment" onchange="this.form.submit()">
                            @forelse ($assignments as $a)
                                <option value="{{ $a->id }}" @selected(($selectedAssignmentId ?? null) == $a->id)>{{ $a->assessment->title ?? 'Bài tập' }}</option>
                            @empty
                                <option>Chưa có đề nào giao cho lớp này</option>
                            @endforelse
                        </select>
                    </label>
                    <label>Trạng thái
                        <select name="status" onchange="this.form.submit()">
                            <option value="" @selected($selectedStatus === '')>Tất cả</option>
                            @foreach (['Chưa làm', 'Đang làm', 'Đã nộp'] as $st)
                                <option value="{{ $st }}" @selected($selectedStatus === $st)>{{ $st }}</option>
                            @endforeach
                        </select>
                    </label>
                </form>

                <div class="tw-stats" style="grid-template-columns:repeat(3,minmax(0,1fr))">
                    <div class="tw-stat" style="cursor:default"><span><x-lucide name="check-circle-2" />Đã nộp</span><strong>{{ $stats['submitted'] }}/{{ $students->count() }}</strong></div>
                    <div class="tw-stat" style="cursor:default"><span><x-lucide name="clock" />Đang làm</span><strong>{{ $stats['inProgress'] }}</strong></div>
                    <div class="tw-stat" style="cursor:default"><span><x-lucide name="calendar-days" />Chưa làm</span><strong>{{ $stats['notStarted'] }}</strong></div>
                </div>

                <div style="margin-top:8px">
                    @forelse ($students as $s)
                        <article class="tw-task">
                            <div>
                                <h3>{{ $s['name'] }}</h3>
                                <p>Điểm: {{ $s['score'] }} · {{ $s['time'] }}</p>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
                                <span class="tw-tag {{ ($s['tone'] ?? '') === 'success' ? 'tw-status-completed' : (($s['tone'] ?? '') === 'warning' ? 'tw-status-pending' : '') }}">{{ $s['status'] }}</span>
                                @if ($s['attemptId'])
                                    <a class="tw-secondary" href="{{ route('teacher.results.attempt', $s['attemptId']) }}">Xem lần nộp</a>
                                @endif
                            </div>
                        </article>
                    @empty
                        <p class="tw-empty">Chọn lớp và đề để xem kết quả.</p>
                    @endforelse
                </div>
            </section>
        @endif
    </div>

    @if ($tab === 'work')
        @include('teacher.work._script')
    @endif
@endsection
