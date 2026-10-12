{{--
  Route: student.assignments.index — "Bài & đề được giao".
  SỬA 12/10 — DỰNG LẠI THEO education-main (source mới): StudentAssignmentInbox.jsx — 3 ô số (Bài tập / Đề / Hoàn thành),
  lọc theo loại + trạng thái, thẻ từng mục có "Chi tiết" mở rộng, "Ẩn khỏi danh sách" / "Hiện lại".
  Thêm so với source: nút vào làm bài thật + phân trang 10 mục/trang chạy trên trình duyệt.
--}}
@extends('layouts.student')

@section('title', 'Bài & đề được giao')
@section('page-title', 'Bài & đề được giao')

@section('content')
    @php
        $assignments = $assignments ?? [];
        $counts = $counts ?? [];
        $items = array_map(fn ($a) => [
            'id' => $a['id'], 'kind' => $a['kind'], 'state' => $a['state'], 'title' => $a['title'],
            'className' => $a['className'], 'teacher' => $a['teacher'], 'meta' => $a['meta'],
            'assignedAt' => $a['assignedAt'], 'deadline' => $a['deadline'], 'overdue' => (bool) $a['overdue'],
            'late' => (bool) $a['late'], 'result' => $a['result'], 'verdict' => $a['verdict'],
            'submittedAt' => $a['submittedAt'], 'description' => $a['description'], 'url' => $a['url'],
        ], $assignments);
        $total = $counts['total'] ?? 0;
    @endphp

    <div class="sw" data-section="STUDENT-ASSIGNMENTS">
        @include('student._hero')

        <section class="student-overview-card">
            <div class="sw-split">
                <div>
                    <h2 class="sw-h2"><x-lucide name="clipboard-check" />Bài &amp; đề được giao cho bạn</h2>
                    <p class="sw-sub">Bài tập là từng bài luyện; đề gồm nhiều câu hỏi và có thời lượng làm bài.</p>
                </div>
            </div>
            <dl class="sw-stat3">
                <div class="sw-t-blue"><dt><x-lucide name="book-open" />Bài tập</dt><dd>{{ $counts['exercises'] ?? 0 }}</dd><p>{{ $counts['completedExercises'] ?? 0 }} bài hoàn thành</p></div>
                <div class="sw-t-violet"><dt><x-lucide name="file-text" />Đề</dt><dd>{{ $counts['exams'] ?? 0 }}</dd><p>{{ $counts['completedExams'] ?? 0 }} đề hoàn thành</p></div>
                <div class="sw-t-emerald"><dt><x-lucide name="check-circle-2" />Hoàn thành</dt><dd>{{ $counts['completed'] ?? 0 }}/{{ $total }}</dd><p>Bài và đề đã hoàn thành</p></div>
            </dl>
        </section>

        <section class="student-overview-card" x-data="stuAssign(@js($items))" x-init="init()">
            <div class="sw-filters">
                <div class="sw-seg" role="group" aria-label="Loại bài được giao">
                    <template x-for="k in kinds" :key="k[0]">
                        <button type="button" class="sw-act" :class="{ on: kind === k[0] }" :aria-pressed="kind === k[0]" @click="kind = k[0]; reset()" x-text="k[1]"></button>
                    </template>
                </div>
                <label class="sw-select-l">Trạng thái
                    <select aria-label="Trạng thái bài và đề" x-model="state" @change="reset()">
                        <option value="all">Tất cả trạng thái</option>
                        <option value="new">Chưa làm</option>
                        <option value="active">Đang làm</option>
                        <option value="submitted">Đã nộp · Chờ chấm</option>
                        <option value="completed">Đã hoàn thành</option>
                    </select>
                </label>
            </div>
            <div class="sw-split" style="margin-top:8px;align-items:center">
                <p class="sw-note" role="status"><span x-text="filtered.length"></span> mục phù hợp · Bài và đề chờ chấm chưa tính hoàn thành</p>
                <button type="button" class="sw-act" @click="kind = 'all'; state = 'all'; reset()">Bỏ bộ lọc</button>
            </div>

            <div class="sw-list">
                <template x-for="item in visible" :key="item.id">
                    <article class="sw-item" :id="'assignment-card-' + item.id" :aria-label="item.title">
                        <div class="sw-item-top">
                            <span class="sw-ic" :class="{ 'is-exam': item.kind === 'exam' }">
                                <svg x-show="item.kind === 'exam'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>
                                <svg x-show="item.kind !== 'exam'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 7v14"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/></svg>
                            </span>
                            <div class="sw-item-main">
                                <div class="sw-item-head">
                                    <div><span class="sw-kind" :class="{ 'is-exam': item.kind === 'exam' }" x-text="item.kind === 'exam' ? 'Đề' : 'Bài tập'"></span><h3 x-text="item.title"></h3></div>
                                    <span class="sw-state" :class="item.state" x-text="stateLabel(item.state)"></span>
                                </div>
                                <p class="m" x-text="item.className + ' · ' + item.teacher"></p>
                                <p class="m2" x-text="item.meta"></p>
                            </div>
                        </div>
                        <div class="sw-dates">
                            <p>Hạn nộp: <b :style="item.overdue ? 'color:#b05235' : ''" x-text="(item.overdue ? 'Quá hạn · ' : '') + (item.deadline || 'Không có hạn')"></b></p>
                            <p x-show="item.submittedAt">Đã nộp: <b x-text="item.submittedAt"></b> · <span x-text="item.late ? 'Nộp muộn' : 'Nộp đúng hạn'"></span></p>
                            <p x-show="!item.submittedAt" x-text="item.state === 'active' ? 'Đang làm dở · Chưa nộp' : 'Chưa bắt đầu làm'"></p>
                        </div>
                        <div class="sw-res" x-show="item.result">
                            <b><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>Điểm: <span x-text="item.result"></span></b>
                            <span x-show="item.verdict" x-text="item.verdict"></span>
                        </div>
                        <p class="sw-wait" x-show="item.state === 'submitted'">Đã nhận bài nộp · Điểm chưa được công bố</p>
                        <div class="sw-foot">
                            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:4px">
                                <button type="button" class="sw-act" :aria-expanded="open === item.id" @click="open = (open === item.id ? null : item.id)">
                                    <span x-text="open === item.id ? 'Thu gọn' : (item.result ? 'Xem kết quả' : (item.state === 'submitted' ? 'Xem bài nộp' : (item.kind === 'exam' ? 'Chi tiết đề' : 'Chi tiết bài')))"></span>
                                    <svg class="sw-chev" :class="{ up: open === item.id }" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                </button>
                                <a class="student-overview-action" x-show="item.url" :href="item.url"
                                   x-text="item.state === 'new' ? 'Làm bài' : (item.state === 'active' ? 'Tiếp tục làm' : 'Xem bài nộp')"></a>
                            </div>
                            <button type="button" class="student-assignment-hide" @click="hide(item.id)">Ẩn khỏi danh sách</button>
                        </div>
                        <div class="sw-detail" x-show="open === item.id" x-cloak>
                            <p x-text="item.description"></p>
                            <p class="s">Giao lúc <span x-text="item.assignedAt || 'không rõ'"></span></p>
                            <p class="s" x-show="item.submittedAt" x-text="item.state === 'completed' ? 'Đã hoàn thành và công bố kết quả.' : 'Đã nộp; đang chờ giáo viên công bố kết quả.'"></p>
                        </div>
                    </article>
                </template>
            </div>

            <div class="sw-empty" x-show="filtered.length === 0" x-cloak>
                <p x-text="items.length === 0 ? 'Chưa có bài hay đề nào được giao. Khi giáo viên giao bài, bài sẽ hiện ở đây.' : 'Không có bài hoặc đề phù hợp với bộ lọc.'"></p>
                <button type="button" class="sw-act" x-show="items.length > 0" @click="kind = 'all'; state = 'all'; hiddenIds = []; reset()">Xem tất cả bài &amp; đề</button>
            </div>

            @include('student._pager')

            <details class="sw-hidden" x-show="hiddenIds.length > 0" x-cloak>
                <summary>Đã ẩn <span x-text="hiddenIds.length"></span> mục · Hiện lại</summary>
                <div class="sw-seg">
                    <template x-for="item in items.filter(i => hiddenIds.includes(i.id))" :key="item.id">
                        <button type="button" class="sw-act" @click="unhide(item.id)">Hiện lại: <span x-text="item.title"></span></button>
                    </template>
                    <button type="button" class="sw-act" @click="hiddenIds = []; reset()">Hiện lại tất cả</button>
                </div>
                <p class="sw-note" style="margin-top:4px">Ẩn không xoá bài giao hoặc thay đổi kết quả.</p>
            </details>
        </section>
    </div>
<script>
        function stuAssign(items) {
            var labels = { new: 'Chưa làm', active: 'Đang làm', submitted: 'Đã nộp · Chờ chấm', completed: 'Đã hoàn thành' };
            return window.swPager(items.length, 10, {
                items: items, kind: 'all', state: 'all', open: null, hiddenIds: [],
                kinds: [['all', 'Tất cả'], ['exercise', 'Bài tập'], ['exam', 'Đề']],
                stateLabel: function (s) { return labels[s] || s; },
                get filtered() {
                    var self = this;
                    return this.items.filter(function (i) {
                        return self.hiddenIds.indexOf(i.id) === -1 && (self.kind === 'all' || i.kind === self.kind) && (self.state === 'all' || i.state === self.state);
                    });
                },
                get total() { return this.filtered.length; },
                get visible() { return this.filtered.slice((this.page - 1) * this.size, this.page * this.size); },
                reset: function () { this.open = null; this.page = 1; },
                hide: function (id) { this.hiddenIds.push(id); this.open = null; if (this.page > this.pages) { this.page = this.pages; } },
                unhide: function (id) { this.hiddenIds = this.hiddenIds.filter(function (x) { return x !== id; }); },
                init: function () {
                    var want = new URLSearchParams(location.search).get('open');
                    if (!want) { return; }
                    var idx = this.items.findIndex(function (i) { return i.id === want; });
                    if (idx < 0) { return; }
                    this.open = want;
                    this.page = Math.floor(idx / this.size) + 1;
                    var self = this;
                    this.$nextTick(function () { var el = document.getElementById('assignment-card-' + want); if (el) { el.scrollIntoView({ block: 'center' }); } });
                },
            });
        }
    </script>
@endsection
