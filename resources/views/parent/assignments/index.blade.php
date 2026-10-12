{{--
  Route: parent.assignments.index — mục "Bài được giao" (source: ParentContent → AssignmentInboxPanel audience="parent").
  SỬA 12/10 — MỤC MỚI. Gom bài/đề luyện tập giáo viên giao riêng + bài của lớp cho con đã xác minh.
  Chỉ ĐỌC (phụ huynh không làm bài thay con). Lọc theo trạng thái chạy ngay trên trình duyệt (Alpine).
--}}
@extends('layouts.parent')

@section('title', 'Bài được giao')
@section('page-title', 'Bài được giao')

@section('content')
    @include('partials.parent-workspace-style')
    @php
        $single = count($viewChildren) === 1 ? $viewChildren[0] : null;
        $statusMap = [
            'open' => ['Chưa nộp', 'blue'], 'overdue' => ['Quá hạn · Chưa nộp', 'rose'],
            'pending' => ['Chờ chấm', 'wait'], 'completed' => ['Đã hoàn thành', 'ok'],
        ];
        $cnt = $assignmentCounts;
    @endphp

    <div class="pw">
        @include('parent._hero', [
            'title' => 'Bài được giao',
            'desc' => ($single ? $single['name'].' đang có ' : 'Các con đang có ').$cnt['total'].' bài/đề được giao. Theo dõi hạn nộp và kết quả ở đây.',
            'cta' => ['label' => 'Xem kết quả', 'href' => route('parent.results.index', ['child' => $selected]), 'icon' => 'trophy'],
        ])

        @if (! $hasChildren)
            @include('parent._no-children')
        @else
            @if ($multi)
                <section class="pw-card pw-card--soft"><div class="pw-sum-top"><div><p class="pw-eyebrow">Theo dõi theo từng con</p><p class="pw-sub">Chọn một con để lọc bài được giao.</p></div>@include('parent._picker')</div></section>
            @endif

            <div class="pw-grid4">
                @include('parent._stat', ['label' => 'Tổng số', 'value' => $cnt['total'], 'note' => 'Bài và đề được giao', 'icon' => 'clipboard-list', 'tone' => 'blue'])
                @include('parent._stat', ['label' => 'Đã hoàn thành', 'value' => $cnt['completed'], 'note' => 'Đã nộp và có kết quả', 'icon' => 'check-circle', 'tone' => 'emerald'])
                @include('parent._stat', ['label' => 'Chờ chấm', 'value' => $cnt['pending'], 'note' => 'Đã nộp, đang chờ kết quả', 'icon' => 'clock', 'tone' => 'violet'])
                @include('parent._stat', ['label' => 'Chưa nộp', 'value' => $cnt['open'] + $cnt['overdue'], 'note' => $cnt['overdue'].' bài đã quá hạn', 'icon' => 'alert-triangle', 'tone' => $cnt['overdue'] > 0 ? 'rose' : 'amber'])
            </div>

            @php
                $items = array_map(fn ($a) => [
                    'title' => $a['title'], 'kind' => $a['kind'], 'child' => $a['child'], 'by' => $a['by'],
                    'deadline' => $a['deadline'] ?? 'Không giới hạn', 'submittedAt' => $a['submittedAt'], 'late' => (bool) $a['late'],
                    'status' => $a['status'], 'label' => $statusMap[$a['status']][0], 'tone' => $statusMap[$a['status']][1],
                    'result' => $a['result'] ?? ($a['status'] === 'pending' ? 'Chờ chấm' : '—'),
                ], $assignments);
            @endphp
            {{-- Lọc theo trạng thái + phân trang 10 dòng/trang, chạy ngay trên trình duyệt. --}}
            <section class="pw-card" x-data="pwAssign(@js($items), {{ $multi ? 'true' : 'false' }})">
                @include('parent._ct', ['icon' => 'clipboard-check', 'title' => 'Danh sách bài được giao'])
                <div class="pw-tabs" role="tablist" aria-label="Lọc theo trạng thái">
                    <button type="button" :class="{ on: f === 'all' }" @click="setF('all')">Tất cả ({{ $cnt['total'] }})</button>
                    <button type="button" :class="{ on: f === 'todo' }" @click="setF('todo')">Chưa nộp ({{ $cnt['open'] + $cnt['overdue'] }})</button>
                    <button type="button" :class="{ on: f === 'pending' }" @click="setF('pending')">Chờ chấm ({{ $cnt['pending'] }})</button>
                    <button type="button" :class="{ on: f === 'completed' }" @click="setF('completed')">Đã hoàn thành ({{ $cnt['completed'] }})</button>
                </div>

                <template x-for="(a, idx) in visible" :key="idx">
                    <article class="pw-task">
                        <div style="min-width:0">
                            <h3 x-text="a.title"></h3>
                            <small x-text="a.kind + (multi ? ' · ' + a.child : '') + (a.by ? ' · ' + a.by : '')"></small>
                        </div>
                        <div><span class="pw-chip" :class="a.tone" x-text="a.label"></span><small x-show="a.late" style="color:#be123c">Nộp muộn</small></div>
                        <div>
                            <small style="margin:0">Hạn nộp</small>
                            <b style="font-size:12px;color:#334155" x-text="a.deadline"></b>
                            <small x-show="a.submittedAt" x-text="'Nộp: ' + a.submittedAt"></small>
                        </div>
                        <div class="rt" x-text="a.result"></div>
                    </article>
                </template>
                <p class="pw-empty" x-show="total === 0" x-cloak>{{ count($assignments) === 0 ? 'Chưa có bài hay đề nào được giao.' : 'Không có bài nào ở trạng thái này.' }}</p>

                @include('parent._pager')
            </section>

            <script>
                function pwAssign(items, multi) {
                    return window.pwPager(items.length, 10, {
                        items: items, multi: multi, f: 'all',
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
        @endif
    </div>
@endsection
