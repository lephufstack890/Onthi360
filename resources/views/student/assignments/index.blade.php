{{--
  Route: student.assignments.index — mục "Bài được giao" (source: StudentContent → AssignmentInboxPanel audience="student").
  SỬA 12/10 — MỤC MỚI. Gom bài/đề giáo viên giao riêng ở trang Luyện tập + bài của lớp, có nút vào làm bài.
  Lọc theo trạng thái + phân trang 10 dòng/trang chạy ngay trên trình duyệt (Alpine).
--}}
@extends('layouts.student')

@section('title', 'Bài được giao')
@section('page-title', 'Bài được giao')

@section('content')
    @include('partials.parent-workspace-style')
    <style>
        .pw-task.stu { align-items: center; }
        @media (min-width: 768px) { .pw-task.stu { grid-template-columns: minmax(0, 1.6fr) minmax(0, .8fr) minmax(0, 1fr) minmax(0, .6fr) 104px; } }
        .pw-task.stu .pw-btn { margin-top: 0; white-space: nowrap; }
        .pw-task.stu .go { display: flex; justify-content: flex-end; }
        .pw-next { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; }
        .pw-next h3 { margin: 2px 0 0; font-size: 14px; font-weight: 700; color: #1e293b; overflow-wrap: anywhere; }
    </style>
    @php
        $statusMap = [
            'open' => ['Chưa nộp', 'blue'], 'overdue' => ['Quá hạn · Chưa nộp', 'rose'],
            'pending' => ['Chờ chấm', 'wait'], 'completed' => ['Đã hoàn thành', 'ok'],
        ];
        $cnt = $counts;
        $items = array_map(fn ($a) => [
            'title' => $a['title'], 'kind' => $a['kind'], 'by' => $a['by'],
            'deadline' => $a['deadline'] ?? 'Không giới hạn', 'submittedAt' => $a['submittedAt'], 'late' => (bool) $a['late'],
            'status' => $a['status'], 'label' => $statusMap[$a['status']][0], 'tone' => $statusMap[$a['status']][1],
            'result' => $a['result'] ?? ($a['status'] === 'pending' ? 'Chờ chấm' : '—'),
            'url' => $a['url'],
            'cta' => in_array($a['status'], ['open', 'overdue'], true) ? 'Làm bài' : 'Xem lại',
            'primary' => in_array($a['status'], ['open', 'overdue'], true),
        ], $assignments);
    @endphp

    <div class="pw">
        @include('student._hero', [
            'title' => 'Bài được giao',
            'desc' => 'Bạn đang có '.$cnt['total'].' bài/đề được giao. Theo dõi hạn nộp, làm bài và xem kết quả ngay tại đây.',
            'cta' => ['label' => 'Xem kết quả', 'href' => route('student.results.index'), 'icon' => 'trophy'],
        ])

        <div class="pw-grid4">
            @include('parent._stat', ['label' => 'Tổng số', 'value' => $cnt['total'], 'note' => 'Bài và đề được giao', 'icon' => 'clipboard-list', 'tone' => 'blue'])
            @include('parent._stat', ['label' => 'Đã hoàn thành', 'value' => $cnt['completed'], 'note' => 'Đã nộp và có kết quả', 'icon' => 'check-circle', 'tone' => 'emerald'])
            @include('parent._stat', ['label' => 'Chờ chấm', 'value' => $cnt['pending'], 'note' => 'Đã nộp, đang chờ kết quả', 'icon' => 'clock', 'tone' => 'violet'])
            @include('parent._stat', ['label' => 'Chưa nộp', 'value' => $cnt['open'] + $cnt['overdue'], 'note' => $cnt['overdue'].' bài đã quá hạn', 'icon' => 'alert-triangle', 'tone' => $cnt['overdue'] > 0 ? 'rose' : 'amber'])
        </div>

        @if ($next)
            <section class="pw-card pw-card--soft">
                <div class="pw-next">
                    <div style="min-width:0">
                        <p class="pw-eyebrow">Nên làm tiếp</p>
                        <h3>{{ $next['title'] }}</h3>
                        <p class="pw-sub">{{ $next['kind'] }}{{ $next['deadline'] ? ' · hạn '.$next['deadline'] : '' }}</p>
                    </div>
                    @if ($next['url'])<a class="pw-btn" style="margin-top:0" href="{{ $next['url'] }}"><x-lucide name="play" />Làm bài ngay</a>@endif
                </div>
            </section>
        @endif

        <section class="pw-card" x-data="stuAssign(@js($items))">
            @include('parent._ct', ['icon' => 'clipboard-check', 'title' => 'Danh sách bài được giao'])
            <div class="pw-tabs" role="tablist" aria-label="Lọc theo trạng thái">
                <button type="button" :class="{ on: f === 'all' }" @click="setF('all')">Tất cả ({{ $cnt['total'] }})</button>
                <button type="button" :class="{ on: f === 'todo' }" @click="setF('todo')">Chưa nộp ({{ $cnt['open'] + $cnt['overdue'] }})</button>
                <button type="button" :class="{ on: f === 'pending' }" @click="setF('pending')">Chờ chấm ({{ $cnt['pending'] }})</button>
                <button type="button" :class="{ on: f === 'completed' }" @click="setF('completed')">Đã hoàn thành ({{ $cnt['completed'] }})</button>
            </div>

            <template x-for="(a, idx) in visible" :key="idx">
                <article class="pw-task stu">
                    <div style="min-width:0">
                        <h3 x-text="a.title"></h3>
                        <small x-text="a.kind + (a.by ? ' · ' + a.by : '')"></small>
                    </div>
                    <div><span class="pw-chip" :class="a.tone" x-text="a.label"></span><small x-show="a.late" style="color:#be123c;display:block">Nộp muộn</small></div>
                    <div>
                        <small style="margin:0">Hạn nộp</small>
                        <b style="font-size:12px;color:#334155" x-text="a.deadline"></b>
                        <small x-show="a.submittedAt" style="display:block" x-text="'Nộp: ' + a.submittedAt"></small>
                    </div>
                    <div class="rt" x-text="a.result"></div>
                    <div class="go">
                        <a x-show="a.url" :href="a.url" class="pw-btn" :class="{ 'pw-btn--ghost': !a.primary }" x-text="a.cta"></a>
                    </div>
                </article>
            </template>
            <p class="pw-empty" x-show="total === 0" x-cloak>{{ count($assignments) === 0 ? 'Chưa có bài hay đề nào được giao. Khi giáo viên giao bài, bài sẽ hiện ở đây.' : 'Không có bài nào ở trạng thái này.' }}</p>

            @include('parent._pager')
        </section>

        <script>
            function stuAssign(items) {
                return window.pwPager(items.length, 10, {
                    items: items, f: 'all',
                    get filtered() {
                        var f = this.f;
                        return this.items.filter(function (a) { return f === 'all' || (f === 'todo' ? (a.status === 'open' || a.status === 'overdue') : a.status === f); });
                    },
                    get visible() { return this.filtered.slice((this.page - 1) * this.size, this.page * this.size); },
                    get total() { return this.filtered.length; },
                    setF(v) { this.f = v; this.page = 1; },
                });
            }
        </script>
    </div>
@endsection
