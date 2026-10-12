@extends('layouts.teacher')

@section('title', 'Thông báo')
@section('page-title', 'Thông báo')

@section('content')
    {{--
      SỬA 12/10 — dựng lại theo TeacherNoticePanel của source mới: tab "Thông báo lớp" (soạn, lưu nháp, đăng, lịch sử có lọc
      Bản nháp / Đã đăng) và tab "Hộp thư" (thông báo hệ thống gửi tới giáo viên — GIỮ NGUYÊN như bản cũ, kể cả
      "Đánh dấu đã đọc tất cả"). Đăng thông báo lớp = gửi thông báo thật tới học sinh trong lớp (và phụ huynh đã liên kết nếu
      chọn) — xem Teacher\TeacherWorkspaceService::saveAnnouncement().
    --}}
    @php
        $tab = $tab ?? 'compose';
        $items = $items ?? [];
        $unreadCount = $unreadCount ?? 0;
        $classes = $classes ?? [];
        $notices = $notices ?? [];
        $recipientCounts = $recipientCounts ?? [];
        $editing = $editing ?? null;
        $preClass = $preClass ?? 0;
        $selectedClass = (int) old('class_room_id', $editing['classId'] ?? ($preClass ?: ($classes[0]['id'] ?? 0)));
        if (! in_array($selectedClass, array_column($classes, 'id'), true)) {
            $selectedClass = $classes[0]['id'] ?? 0;
        }
        $audience = old('audience', $editing['audience'] ?? 'students');
        $status = session('notice_status');
        $payload = [
            'notices' => $notices,
            'counts' => (object) $recipientCounts,
            'classId' => $selectedClass,
            'audience' => $audience,
            'filter' => $initialFilter ?? 'all',
            'editId' => $editing['id'] ?? null,
        ];
    @endphp

    <div class="tw-dashboard" data-section="TEACHER-NOTICES" x-data="twNotices(@js($payload))">
        @include('teacher._hero')

        <div class="tw-tabs" role="tablist" aria-label="Thông báo">
            <a class="tw-tab" href="{{ route('teacher.notifications.index', ['tab' => 'compose']) }}" @if ($tab === 'compose') aria-current="page" @endif><x-lucide name="message-square-text" />Thông báo lớp</a>
            <a class="tw-tab" href="{{ route('teacher.notifications.index', ['tab' => 'inbox']) }}" @if ($tab === 'inbox') aria-current="page" @endif><x-lucide name="bell" />Hộp thư @if ($unreadCount > 0)<span class="n">{{ $unreadCount }}</span>@endif</a>
        </div>

        @if ($tab === 'compose')
            <section class="tw-card">
                <div class="tw-heading">
                    <div><h2><x-lucide name="message-square-text" />Thông báo lớp học</h2><p>Soạn nội dung, lưu nháp hoặc đăng ngay — thông báo được gửi tới học sinh trong lớp (và phụ huynh đã liên kết nếu chọn).</p></div>
                </div>

                @if ($status === 'published')
                    <p class="tw-success" role="status">Đã đăng thông báo và gửi tới người nhận.</p>
                @elseif ($status === 'draft')
                    <p class="tw-success" role="status">Đã lưu bản nháp thông báo.</p>
                @elseif ($status === 'deleted')
                    <p class="tw-success" role="status">Đã xóa bản nháp.</p>
                @endif
                @if ($errors->any())
                    <p class="tw-error" role="alert">{{ $errors->first() }}</p>
                @endif

                @if (empty($classes))
                    <p class="tw-empty">Bạn chưa có lớp nào để gửi thông báo.</p>
                @else
                    <form class="tw-form" method="POST" action="{{ route('teacher.notices.store') }}" novalidate style="margin-top:12px">
                        @csrf
                        @if ($editing)
                            <input type="hidden" name="id" value="{{ $editing['id'] }}">
                            <p class="tw-muted">Đang viết tiếp bản nháp: <b>{{ $editing['title'] ?: 'Chưa có tiêu đề' }}</b></p>
                        @endif
                        <div class="tw-fields">
                            <label>Lớp nhận
                                <select name="class_room_id" aria-label="Lớp nhận" x-model.number="classId">
                                    @foreach ($classes as $c)
                                        <option value="{{ $c['id'] }}" @selected($selectedClass === $c['id'])>{{ $c['name'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>Người nhận
                                <select name="audience" aria-label="Người nhận" x-model="audience">
                                    <option value="students">Học sinh trong lớp</option>
                                    <option value="families">Học sinh &amp; phụ huynh đã liên kết</option>
                                </select>
                            </label>
                        </div>
                        <p class="tw-muted">Phạm vi: <span x-text="scopeText"></span></p>
                        <label>Tiêu đề
                            <input type="text" name="title" maxlength="120" value="{{ old('title', $editing['title'] ?? '') }}" placeholder="Ví dụ: Chuẩn bị buổi học ngày mai">
                        </label>
                        <label>Nội dung
                            <textarea name="body" rows="4" maxlength="3000" placeholder="Nhập lời nhắn cho lớp…">{{ old('body', $editing['body'] ?? '') }}</textarea>
                        </label>
                        <div class="tw-actions" style="margin-top:0">
                            <button class="tw-button" type="submit" name="action" value="publish" :disabled="counts[classId] && counts[classId].students === 0">Đăng thông báo</button>
                            <button class="tw-secondary" type="submit" name="action" value="draft">Lưu nháp</button>
                            @if ($editing)
                                <a class="tw-secondary" href="{{ route('teacher.notifications.index', ['tab' => 'compose']) }}">Hủy chỉnh sửa</a>
                            @endif
                        </div>
                    </form>
                @endif

                <div class="tw-notice-history">
                    <div class="tw-heading">
                        <h3 x-text="'Thông báo đã tạo · ' + (filter === 'all' ? total : total + '/' + notices.length)"></h3>
                        <select aria-label="Lọc thông báo" x-model="filter" @change="page = 1" x-show="notices.length" x-cloak>
                            <option value="all">Tất cả</option><option value="draft">Bản nháp</option><option value="published">Đã đăng</option>
                        </select>
                    </div>
                    <template x-for="n in visible" :key="n.id">
                        <article class="tw-row" :class="{ 'tw-notice-editing': editId === n.id }">
                            <div class="tw-heading">
                                <h3 x-text="n.title || 'Bản nháp chưa có tiêu đề'"></h3>
                                <span class="tw-tag" :class="n.state === 'published' ? 'tw-status-completed' : 'tw-status-pending'" x-text="n.state === 'draft' ? 'Bản nháp' : 'Đã đăng'"></span>
                            </div>
                            <p x-text="n.class + ' · ' + (n.audience === 'families' ? 'Học sinh & phụ huynh đã liên kết' : 'Học sinh') + ' · ' + (n.state === 'published' ? 'Đăng: ' + n.publishedAt : 'Cập nhật: ' + n.updatedAt)"></p>
                            <p x-show="n.state === 'published'" x-text="'Đã gửi tới ' + n.students + ' học sinh' + (n.audience === 'families' ? ' · ' + n.parents + ' phụ huynh' : '')"></p>
                            <p class="tw-notice-body" x-text="n.body || 'Chưa nhập nội dung.'"></p>
                            <div class="tw-actions" x-show="n.state === 'draft'">
                                <a class="tw-secondary" :href="'{{ route('teacher.notifications.index', ['tab' => 'compose']) }}&edit=' + n.id" x-text="editId === n.id ? 'Đang chỉnh sửa' : 'Viết tiếp'"></a>
                                <form method="POST" :action="'{{ url('teacher/notices') }}/' + n.id" @submit="if (!confirm('Xóa bản nháp này?')) { $event.preventDefault(); }">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="tw-secondary">Xóa nháp</button>
                                </form>
                            </div>
                        </article>
                    </template>
                    <p class="tw-muted" x-show="total === 0" x-cloak x-text="notices.length ? 'Không có thông báo ở trạng thái này.' : 'Thông báo của bạn sẽ xuất hiện tại đây sau khi lưu.'"></p>
                    @include('student._pager')
                </div>
            </section>
        @else
            <section class="tw-card">
                <div class="tw-heading">
                    <div>
                        <h2><x-lucide name="bell" />Hộp thư</h2>
                        <p>{{ $unreadCount > 0 ? "Bạn có {$unreadCount} thông báo chưa đọc." : 'Bạn đã xem hết thông báo.' }}</p>
                    </div>
                    @if ($unreadCount > 0)
                        <form method="POST" action="{{ route('notifications.readAll') }}">
                            @csrf
                            <button type="submit" class="tw-secondary">Đánh dấu đã đọc tất cả</button>
                        </form>
                    @endif
                </div>
                @forelse ($items as $n)
                    <a href="{{ route('notifications.read', $n['id']) }}" class="sw-notice {{ ! $n['read'] ? 'unread' : '' }}">
                        <span class="sw-tile">{{ $n['icon'] }}</span>
                        <span class="sw-notice-b">
                            <span style="display:flex;justify-content:space-between;gap:16px"><b style="font-size:12px;color:#1e293b">{{ $n['title'] }}</b><time>{{ $n['time'] }}</time></span>
                            <p>{{ $n['text'] }}</p>
                        </span>
                        @if (! $n['read'])<span class="sw-new"></span>@endif
                    </a>
                @empty
                    <p class="tw-empty">Chưa có thông báo nào.</p>
                @endforelse
            </section>
        @endif
    </div>

    <script>
        function twNotices(d) {
            return window.swPager(0, 10, {
                notices: d.notices, counts: d.counts, classId: d.classId, audience: d.audience, filter: d.filter, editId: d.editId,
                get scopeText() {
                    var c = this.counts[this.classId] || { students: 0, parents: 0 };
                    return c.students + ' học sinh' + (this.audience === 'families' ? ' và ' + c.parents + ' phụ huynh đã liên kết' : '') + (c.students === 0 ? '. Lớp chưa có học sinh đang học — chỉ lưu nháp được.' : '.');
                },
                get filtered() { var f = this.filter; return this.notices.filter(function (n) { return f === 'all' || n.state === f; }); },
                get total() { return this.filtered.length; },
                get visible() { return this.filtered.slice((this.page - 1) * this.size, this.page * this.size); },
            });
        }
    </script>
@endsection
