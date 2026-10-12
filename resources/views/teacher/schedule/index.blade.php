@extends('layouts.teacher')

@section('title', 'Lịch & điểm danh')
@section('page-title', 'Lịch & điểm danh')

@section('content')
    {{-- SỬA 12/10 — dựng lại theo TeacherSessionPanel của source mới ("Lịch & điểm danh"): hero chung, thẻ lịch có lọc theo lớp
         và thời gian, mỗi buổi một dòng với trạng thái + nút "Điểm danh". GIỮ NGUYÊN logic bản cũ: form tạo buổi học mới
         (partials.session-datetime-fields), danh sách Sắp tới / Đã qua và trang điểm danh từng buổi. Thêm phân trang 10 dòng/trang. --}}
    @php
        $classRooms = $classRooms ?? collect();
        $upcoming = $upcoming ?? [];
        $past = $past ?? [];
        $toneClass = fn ($tone) => match ($tone) { 'success' => 'tw-status-completed', 'warning', 'danger' => 'tw-status-overdue', 'info' => 'tw-status-pending', default => '' };
        $payload = [
            'upcoming' => array_map(fn ($s) => ['id' => $s['id'], 'cls' => $s['className'], 'time' => $s['timeRangeLabel'], 'topic' => $s['topic'] ?? '—', 'status' => $s['timeStatusLabel'], 'sTone' => $toneClass($s['timeStatusTone'] ?? ''), 'att' => $s['attendanceSummary'], 'attTone' => $s['attendanceTaken'] ? 'tw-status-completed' : '', 'url' => route('teacher.schedule.attendance', $s['id'])], $upcoming),
            'past' => array_map(fn ($s) => ['id' => $s['id'], 'cls' => $s['className'], 'time' => $s['timeRangeLabel'], 'topic' => $s['topic'] ?? '—', 'status' => $s['timeStatusLabel'], 'sTone' => $toneClass($s['timeStatusTone'] ?? ''), 'att' => $s['attendanceSummary'], 'attTone' => $s['attendanceTaken'] ? 'tw-status-completed' : 'tw-status-pending', 'url' => route('teacher.schedule.attendance', $s['id'])], $past),
        ];
    @endphp

    @if (session('status') === 'session-created')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tạo buổi học mới.'])
    @endif
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="tw-dashboard" data-section="TEACHER-SCHEDULE" x-data="twSchedule(@js($payload), {{ $errors->any() ? 'true' : 'false' }})">
        @include('teacher._hero')

        <section class="tw-card">
            <div class="tw-heading">
                <div><h2><x-lucide name="calendar-days" />Lịch &amp; điểm danh</h2><p>Buổi học của mọi lớp bạn phụ trách. Chọn buổi để ghi nhận điểm danh và nhận xét từng học sinh. Giờ Việt Nam (GMT+7).</p></div>
                <button type="button" class="tw-button" @click="creating = !creating" x-text="creating ? 'Đóng form' : '+ Tạo buổi học mới'"></button>
            </div>

            <div x-show="creating" x-cloak class="tw-highlight" style="margin-bottom:4px">
        <form method="POST" action="{{ route('teacher.schedule.store') }}" x-data="{ startsDate: '{{ old('starts_date', '') }}', endsDate: '{{ old('ends_date', '') }}' }" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1" for="class_room_id">Lớp</label>
                    <x-ws.select id="class_room_id" name="class_room_id" required>
                        <option value="">Chọn lớp</option>
                        @foreach ($classRooms as $c)
                            <option value="{{ $c->id }}" @selected(old('class_room_id') == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </x-ws.select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1" for="topic">Chủ đề buổi học</label>
                    <input id="topic" type="text" name="topic" maxlength="255" value="{{ old('topic') }}" placeholder="Ví dụ: Ôn tập chương 3" class="admin-input">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1" for="location">Địa điểm/link</label>
                    <input id="location" type="text" name="location" maxlength="255" value="{{ old('location') }}" placeholder="Phòng học hoặc link online" class="admin-input">
                </div>
            </div>

            @include('partials.session-datetime-fields')

            <div>
                <button type="submit" class="tw-button">Tạo buổi học</button>
            </div>
        </form>
            </div>
        </section>

        <section class="tw-card">
            <div class="tw-heading">
                <div><h2><x-lucide name="clock" />Buổi học</h2><p x-text="total + ' buổi · ' + (tab === 'upcoming' ? 'sắp tới' : 'đã qua')"></p></div>
                <div class="tw-tabs">
                    <button type="button" class="tw-tab" :class="{ on: tab === 'upcoming' }" @click="tab = 'upcoming'; page = 1">Sắp tới <span class="n" x-text="upcoming.length"></span></button>
                    <button type="button" class="tw-tab" :class="{ on: tab === 'past' }" @click="tab = 'past'; page = 1">Đã qua <span class="n" x-text="past.length"></span></button>
                </div>
            </div>
            <div class="tw-filters" style="grid-template-columns:minmax(0,1fr)">
                <label>Tìm buổi học
                    <input type="search" x-model="q" @input="page = 1" placeholder="Tên lớp hoặc chủ đề">
                </label>
            </div>

            <template x-for="s in visible" :key="s.id">
                <article class="tw-task">
                    <div>
                        <h3 x-text="s.topic"></h3>
                        <p x-text="s.cls + ' · ' + s.time"></p>
                        <div class="tw-actions" style="margin-top:6px">
                            <span class="tw-tag" :class="s.sTone" x-text="s.status"></span>
                            <span class="tw-tag" :class="s.attTone" x-text="s.att"></span>
                        </div>
                    </div>
                    <a class="tw-button" :href="s.url">Điểm danh</a>
                </article>
            </template>
            <p class="tw-empty" x-show="total === 0" x-cloak x-text="tab === 'upcoming' ? 'Chưa có buổi học sắp tới.' : 'Chưa có buổi học nào trong quá khứ.'"></p>
            @include('student._pager')
        </section>
    </div>

    <script>
        function twSchedule(d, open) {
            return window.swPager(0, 10, {
                upcoming: d.upcoming, past: d.past, tab: 'upcoming', q: '', creating: open,
                get filtered() {
                    var q = this.q.trim().toLowerCase();
                    return (this.tab === 'upcoming' ? this.upcoming : this.past).filter(function (s) { return !q || (s.cls + ' ' + s.topic).toLowerCase().indexOf(q) !== -1; });
                },
                get total() { return this.filtered.length; },
                get visible() { return this.filtered.slice((this.page - 1) * this.size, this.page * this.size); },
            });
        }
    </script>
@endsection
