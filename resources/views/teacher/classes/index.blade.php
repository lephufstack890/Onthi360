@extends('layouts.teacher')

@section('title', 'Lớp học')
@section('page-title', 'Lớp học')

@section('content')
    {{--
      SỬA 12/10 — dựng lại theo TeacherClassesPanel của source mới (TeacherDashboard.jsx): thẻ từng lớp (ảnh bìa, mã lớp,
      tên, sĩ số · lịch học, nút "Vào lớp" / "Danh sách học sinh") rồi thẻ "Học sinh · {lớp}" có ô tìm và 3 lối tắt.
      GIỮ NGUYÊN logic bản cũ: nút "Tạo lớp mới" (chỉ khi hồ sơ đã duyệt), số yêu cầu vào lớp chờ duyệt, buổi tới /
      đang diễn ra / buổi vừa kết thúc chưa điểm danh, thanh "Hoàn thành chung". Danh sách học sinh lấy từ
      class_enrollments (đang học), số lượt giao / đã nộp từ practice_assignments của chính giáo viên.
    --}}
    @php
        $classes = $classes ?? [];
        $roster = $roster ?? [];
        $covers = $covers ?? [];
        $isTeacherApproved = auth()->user()->isTeacherApproved();
        $firstId = $classes[0]['id'] ?? null;
        $payload = [
            'classes' => array_map(fn ($c) => ['id' => $c['id'], 'name' => $c['name']], $classes),
            'roster' => (object) $roster,
            'first' => $firstId,
        ];
    @endphp

    @if (session('status') === 'class-created')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tạo lớp học mới.'])
    @endif

    <div class="tw-dashboard" data-section="TEACHER-CLASSES" x-data="twClasses(@js($payload))">
        @include('teacher._hero')

        <section class="tw-card">
            <div class="tw-heading">
                <div>
                    <h2><x-lucide name="users" />Lớp học bạn phụ trách</h2>
                    <p>{{ count($classes) }} lớp · quản lý lịch, điểm danh, học liệu và tiến độ từng lớp.</p>
                </div>
                @if ($isTeacherApproved)
                    <a class="tw-button" href="{{ route('teacher.classes.create') }}">+ Tạo lớp mới</a>
                @else
                    <span class="tw-tag" title="Hồ sơ giáo viên đang chờ Admin duyệt">⏳ Chờ duyệt để tạo lớp</span>
                @endif
            </div>
        </section>

        @if (empty($classes))
            <section class="tw-card"><p class="tw-empty">{{ $isTeacherApproved ? 'Bạn chưa có lớp nào. Tạo lớp mới để bắt đầu tổ chức dạy học.' : 'Bạn chưa được phân công lớp học. Hồ sơ đang chờ Admin duyệt — được duyệt rồi mới tạo lớp được.' }}</p></section>
        @else
            <div class="tw-class-grid">
                @foreach ($classes as $c)
                    <article class="tw-card">
                        <div class="tw-class-header">
                            <img src="{{ $covers[$c['id']] ?? asset('assets/course-img-1.jpg') }}" alt="" decoding="async" loading="lazy">
                            <div>
                                <small>{{ $c['code'] ?: $c['course'] }}</small>
                                <h2>{{ $c['name'] }}</h2>
                                <p>{{ $c['students'] }} học sinh{{ $c['scheduleNote'] ? ' · '.$c['scheduleNote'] : '' }}</p>
                            </div>
                        </div>

                        <div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:6px 10px;font-size:11px;color:#6a8094">
                            {{-- SỬA 16/9 — học sinh đang xin vào lớp chờ duyệt: hiện ngay ở danh sách để khỏi mở từng lớp. --}}
                            @if (! empty($c['pendingRequests']))
                                <span class="tw-tag tw-status-pending"><x-lucide name="user-plus" style="width:12px;height:12px" />{{ $c['pendingRequests'] }} chờ duyệt vào lớp</span>
                            @endif
                            @if ($c['nextSession'])
                                <span class="tw-tag">Buổi tới: {{ $c['nextSession'] }}</span>
                            @elseif ($c['inProgressSessionLabel'])
                                <span class="tw-tag tw-status-completed">Đang diễn ra ({{ $c['inProgressSessionLabel'] }}){{ $c['inProgressAttendanceTaken'] ? '' : ' — chưa điểm danh' }}</span>
                            @elseif ($c['lastSessionLabel'])
                                @if ($c['lastSessionAttendanceTaken'])
                                    <span class="tw-tag tw-status-completed">Buổi {{ $c['lastSessionLabel'] }} — đã điểm danh</span>
                                @else
                                    <span class="tw-tag tw-status-overdue">Buổi {{ $c['lastSessionLabel'] }} đã kết thúc, chưa điểm danh</span>
                                @endif
                            @endif
                        </div>

                        <div style="margin-top:10px">
                            <div style="display:flex;justify-content:space-between;gap:8px;font-size:11px;color:#6a8094">
                                <span>Hoàn thành chung (theo buổi học)</span>
                                <strong style="color:#176b50">{{ $c['completion'] }}%{{ $c['completionTotalSessions'] > 0 ? ' · '.$c['completionEndedSessions'].'/'.$c['completionTotalSessions'].' buổi' : '' }}</strong>
                            </div>
                            <div style="margin-top:5px;height:6px;border-radius:999px;background:#e6eef3;overflow:hidden"><div style="height:100%;width:{{ min(100, max(0, (int) $c['completion'])) }}%;background:#2f9272"></div></div>
                        </div>

                        <div class="tw-actions">
                            <a class="tw-button" href="{{ route('teacher.classes.show', $c['id']) }}">Vào lớp</a>
                            <button type="button" class="tw-secondary" @click="select({{ $c['id'] }})" :aria-pressed="current === {{ $c['id'] }} ? 'true' : 'false'">Danh sách học sinh</button>
                        </div>
                    </article>
                @endforeach
            </div>

            <section class="tw-card" data-roster>
                <div class="tw-heading">
                    <div>
                        <h2><x-lucide name="users" />Học sinh · <span x-text="currentName"></span></h2>
                        <p>Theo dõi đúng học sinh trong lớp phụ trách.</p>
                    </div>
                    <input type="search" aria-label="Tìm học sinh" placeholder="Tên hoặc tài khoản học sinh" x-model="query" @input="page = 1">
                </div>

                <template x-for="s in visible" :key="s.id">
                    <article class="tw-task">
                        <div><h3 x-text="s.name"></h3><p x-text="s.account + ' · Đang học'"></p></div>
                        <span class="tw-tag" x-text="s.assigned + ' lượt giao · ' + s.submitted + ' đã nộp'"></span>
                    </article>
                </template>
                <p class="tw-empty" x-show="total === 0" x-cloak x-text="rosterOfCurrent.length ? 'Không có học sinh phù hợp.' : 'Lớp này chưa có học sinh đang học.'"></p>

                @include('student._pager')

                <div class="tw-actions">
                    <a class="tw-button" href="{{ route('teacher.schedule.index') }}">Lịch &amp; điểm danh</a>
                    <a class="tw-button" href="{{ route('teacher.work.index', ['assign' => 1]) }}">Giao bài / đề</a>
                    <a class="tw-secondary" :href="'{{ route('teacher.notifications.index', ['tab' => 'compose']) }}&class=' + current">Soạn thông báo lớp</a>
                </div>
            </section>
        @endif
    </div>

    <script>
        function twClasses(data) {
            return window.swPager(0, 10, {
                classes: data.classes, roster: data.roster, current: data.first, query: '',
                get currentName() { var self = this, c = this.classes.find(function (x) { return x.id === self.current; }); return c ? c.name : ''; },
                get rosterOfCurrent() { return this.roster[this.current] || []; },
                get filtered() {
                    var q = this.query.trim().toLowerCase();
                    return this.rosterOfCurrent.filter(function (s) { return !q || (s.name + ' ' + s.account).toLowerCase().indexOf(q) !== -1; });
                },
                get total() { return this.filtered.length; },
                get visible() { return this.filtered.slice((this.page - 1) * this.size, this.page * this.size); },
                select: function (id) {
                    this.current = id; this.page = 1; this.query = '';
                    var el = this.$root.querySelector('[data-roster]'); if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
                },
            });
        }
    </script>
@endsection
