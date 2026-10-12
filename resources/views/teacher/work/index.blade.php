@extends('layouts.teacher')

@section('title', 'Bài & đề')
@section('page-title', 'Bài & đề')

@section('content')
    {{--
      SỬA 12/10 — mục "Bài & đề" của khu Giáo viên, dựng theo TeacherWorkPanel + TeacherAssignForm của source mới:
      danh sách lượt giao (mỗi học sinh một dòng) có lọc Loại / Trạng thái / Tìm kiếm, "Chi tiết lượt giao", "Xem bài nộp",
      nhận xét bài nộp và form "Giao bài / đề".

      LOGIC THẬT: lượt giao lấy từ practice_assignments qua PracticeAssignmentService::managed() (tiến độ tính từ bài làm
      thật); form giao bài POST về practice.assign (PracticeAssignmentService::assignMany) — gửi tối đa 50 học sinh/lần,
      lớp đông hơn thì tự chia nhiều lần. Nhận xét lưu qua teacher.work.feedback.
    --}}
    @php
        $rows = $rows ?? [];
        $classes = $classes ?? [];
        $roster = $roster ?? [];
        $catalog = $catalog ?? ['problem' => [], 'exam' => []];
        $scope = $scope ?? [];
        $payload = [
            'rows' => $rows,
            'classes' => $classes,
            'roster' => (object) $roster,
            'catalog' => $catalog,
            'scope' => [
                'canAssign' => (bool) ($scope['canAssign'] ?? false),
                'canProblem' => (bool) ($scope['canAssignProblem'] ?? false),
                'canExam' => (bool) ($scope['canAssignExam'] ?? false),
            ],
            'urls' => [
                'assign' => route('practice.assign'),
                'students' => route('practice.assign.students'),
                'feedback' => url('teacher/bai-va-de'),
                'self' => route('teacher.work.index'),
            ],
            'initialStatus' => $initialStatus ?? 'all',
            'openAssign' => (bool) ($openAssign ?? false),
        ];
    @endphp

    <div class="tw-dashboard" data-section="TEACHER-WORK" x-data="twWork(@js($payload))">
        @include('teacher._hero')

        {{-- ═════ Form giao bài / đề ═════ --}}
        <section class="tw-card" aria-label="Giao nội dung" x-show="assigning" x-cloak>
            <div class="tw-heading">
                <div><h2><x-lucide name="clipboard-check" />Giao bài &amp; đề</h2><p>Mỗi học sinh có một lượt giao riêng, dùng chung với kho luyện tập.</p></div>
            </div>
            <form class="tw-form" @submit.prevent="submit()" novalidate>
                <div class="tw-fields">
                    <label>Loại nội dung
                        <select aria-label="Loại nội dung" x-model="form.type" @change="form.subjectId = ''">
                            <template x-if="scope.canProblem"><option value="problem">Bài tập</option></template>
                            <template x-if="scope.canExam"><option value="exam">Đề</option></template>
                        </select>
                    </label>
                    <label>Tìm nội dung
                        <input type="search" x-model="form.search" placeholder="Tên hoặc mã bài / đề" autocomplete="off">
                    </label>
                    <label>Nội dung cần giao
                        <select aria-label="Nội dung cần giao" x-model="form.subjectId">
                            <option value="" x-text="'Chọn nội dung (' + options.length + ')'"></option>
                            <template x-for="o in options" :key="o.id"><option :value="String(o.id)" x-text="(o.code ? o.code + ' · ' : '') + o.title"></option></template>
                        </select>
                    </label>
                    <label>Lớp nhận
                        <select aria-label="Lớp nhận" x-model="form.classId" @change="form.recipient = 'all'; form.picked = []" x-init="$nextTick(() => { $el.value = form.classId })">
                            <template x-for="c in classes" :key="c.id"><option :value="String(c.id)" x-text="c.name"></option></template>
                            <option value="other">Học sinh khác (tìm theo tên / email)</option>
                        </select>
                    </label>
                    <label x-show="form.classId !== 'other'">Học sinh nhận
                        <select aria-label="Học sinh nhận" x-model="form.recipient">
                            <option value="all" x-text="'Cả lớp · ' + classStudents.length + ' học sinh'"></option>
                            <template x-for="s in classStudents" :key="s.id"><option :value="String(s.id)" x-text="s.name + ' · ' + s.account"></option></template>
                        </select>
                    </label>
                    <label>Hạn nộp (giờ Việt Nam)
                        <input type="datetime-local" x-model="form.deadline">
                    </label>
                </div>

                <div x-show="form.classId === 'other'" x-cloak>
                    <label>Tìm học sinh
                        <input type="search" x-model="form.studentQuery" @input.debounce.300ms="findStudents()" placeholder="Gõ tên, email hoặc số điện thoại" autocomplete="off">
                    </label>
                    <div class="tw-assign-pick" style="margin-top:6px" x-show="found.length" x-cloak>
                        <template x-for="s in found" :key="s.id">
                            <button type="button" :class="{ on: isPicked(s.id) }" @click="togglePick(s)" x-text="(isPicked(s.id) ? '✓ ' : '') + s.name + ' · ' + (s.email || s.phone || '')"></button>
                        </template>
                    </div>
                    <div class="tw-actions" x-show="form.picked.length" x-cloak>
                        <template x-for="s in form.picked" :key="s.id"><span class="tw-tag" x-text="s.name"></span></template>
                    </div>
                </div>

                <div class="tw-highlight" x-show="chosen" x-cloak>
                    <h3 x-text="chosen ? chosen.title : ''"></h3>
                    <p x-text="chosen ? ((form.type === 'exam' ? 'Đề' : 'Bài tập') + (chosen.code ? ' · ' + chosen.code : '') + ' · giao cho ' + recipientIds().length + ' học sinh') : ''"></p>
                </div>

                <p class="tw-muted">Nội dung được giao ngay sau khi lưu. Giao lại cùng nội dung cho cùng học sinh sẽ cập nhật hạn của lượt giao trước.</p>
                <p class="tw-error" role="alert" x-show="err" x-cloak x-text="err"></p>
                <p class="tw-success" role="status" x-show="msg" x-cloak x-text="msg"></p>
                <div class="tw-actions">
                    <button type="submit" class="tw-button" :disabled="busy" x-text="busy ? 'Đang lưu…' : (form.type === 'exam' ? 'Lưu giao đề' : 'Lưu giao bài')"></button>
                    <button type="button" class="tw-secondary" @click="assigning = false; err = ''; msg = ''">Hủy</button>
                </div>
            </form>
        </section>

        @include('teacher.work._rows', ['resultsOnly' => false])
    </div>

    @include('teacher.work._script')
    <script>
        function twWork(c) {
            var firstType = c.scope.canProblem ? 'problem' : (c.scope.canExam ? 'exam' : 'problem');
            return window.twWorkList(c, {
                catalog: c.catalog, classes: c.classes, roster: c.roster, scope: c.scope,
                emptyAll: 'Bạn chưa giao bài hay đề nào. Bấm “Giao bài / đề” để bắt đầu.',
                assigning: !!(c.openAssign && c.scope.canAssign), busy: false, err: '', msg: '', found: [],
                form: { type: firstType, search: '', subjectId: '', classId: c.classes.length ? String(c.classes[0].id) : 'other', recipient: 'all', deadline: '', studentQuery: '', picked: [] },
                get options() {
                    var q = this.form.search.trim().toLowerCase();
                    return (this.catalog[this.form.type] || []).filter(function (o) { return !q || (o.title + ' ' + o.code).toLowerCase().indexOf(q) !== -1; }).slice(0, 200);
                },
                get chosen() { var id = this.form.subjectId; return id ? (this.catalog[this.form.type] || []).find(function (o) { return String(o.id) === id; }) || null : null; },
                get classStudents() { return this.roster[this.form.classId] || []; },
                recipientIds: function () {
                    if (this.form.classId === 'other') { return this.form.picked.map(function (s) { return s.id; }); }
                    if (this.form.recipient === 'all') { return this.classStudents.map(function (s) { return s.id; }); }
                    return [parseInt(this.form.recipient, 10)];
                },
                isPicked: function (id) { return this.form.picked.some(function (s) { return s.id === id; }); },
                togglePick: function (s) {
                    if (this.isPicked(s.id)) { this.form.picked = this.form.picked.filter(function (x) { return x.id !== s.id; }); } else { this.form.picked.push(s); }
                },
                findStudents: function () {
                    var self = this, q = this.form.studentQuery.trim();
                    if (q.length < 2) { this.found = []; return; }
                    fetch(this.urls.students + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                        .then(function (res) { return res.ok ? res.json() : { students: [] }; })
                        .then(function (j) { self.found = j.students || []; })
                        .catch(function () { self.found = []; });
                },
                submit: async function () {
                    this.err = ''; this.msg = '';
                    var ids = this.recipientIds();
                    if (!this.form.subjectId) { this.err = 'Chọn nội dung cần giao.'; return; }
                    if (!ids.length) { this.err = this.form.classId === 'other' ? 'Chọn ít nhất 1 học sinh.' : 'Lớp này chưa có học sinh để giao.'; return; }
                    if (!this.form.deadline) { this.err = 'Vui lòng chọn ngày và giờ hạn nộp hợp lệ.'; return; }
                    this.busy = true;
                    try {
                        for (var i = 0; i < ids.length; i += 50) {
                            var res = await this.post(this.urls.assign, { type: this.form.type, subject_id: parseInt(this.form.subjectId, 10), student_ids: ids.slice(i, i + 50), deadline: this.form.deadline });
                            if (!res.ok) { this.err = this.firstError(res); this.busy = false; return; }
                        }
                        this.msg = 'Đã giao cho ' + ids.length + ' học sinh. Đang tải lại danh sách…';
                        var self = this;
                        setTimeout(function () { window.location.href = self.urls.self; }, 900);
                    } catch (e) {
                        this.err = 'Không kết nối được máy chủ. Vui lòng thử lại.'; this.busy = false;
                    }
                },
            });
        }
    </script>
@endsection
