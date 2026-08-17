{{--
  Route: student.classes.show | Frame: STU-03
  Spec: 8.3 (header + tabs: Tổng quan · Lộ trình & Bài tập · Lịch học ·
  Tài liệu · Đánh giá · Thông báo · Thành viên). Mỗi bài có nguồn, loại,
  thời điểm mở, hạn nếu có, trạng thái cá nhân, kết quả gần nhất, lý do khóa.
  Dữ liệu thật do App\Http\Controllers\Student\ClassRoomController truyền vào.
  TODO: nối App\Services\AccessGateService cho AccessDecision từng bài (7.3);
  hiện $roadmap chỉ hiển thị theo Assignment thật, chưa có khái niệm "chương".
--}}
@extends('layouts.student')

@section('title', 'Chi tiết lớp')
@section('page-title', 'Chi tiết lớp')

@section('content')
    @php
        $tab = $tab ?? 'overview';
        $roadmap = $roadmap ?? [];
        $materials = $materials ?? [];
        $sessions = $sessions ?? [];
        $reviews = $reviews ?? collect();
        $notifications = $notifications ?? [];
        $teachers = $teachers ?? collect();
        $students = $students ?? collect();
        $overallPercent = $overallPercent ?? 0;

        $courseTitle = $classRoom->course->title ?? '';
        $className = $classRoom->name ?? '';
        $teacherLabel = isset($mainTeacher) && $mainTeacher ? 'GV '.$mainTeacher->name : 'Chưa phân công giáo viên';
        $nextSessionLabel = isset($nextSession) && $nextSession
            ? 'Buổi tới: '.$nextSession->starts_at->format('d/m H:i')
            : 'Chưa có buổi học sắp tới';
        $ratingAverage = $ratingSummary->avg_rating ?? 0;
        $ratingCount = $ratingSummary->review_count ?? 0;
    @endphp

    <a href="{{ route('student.courses.index') }}" class="text-sm text-slate-500 mb-4 inline-block">‹ Quay lại Khóa học của tôi</a>

    {{-- Header lớp --}}
    <div class="rounded-3xl bg-gradient-to-br from-sky-100 via-white to-rose-50 border border-slate-200 p-6 mb-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-4">
                <div class="w-14 h-14 rounded-2xl bg-white flex items-center justify-center text-3xl shrink-0 shadow-sm">🏫</div>
                <div>
                    <p class="text-xs font-medium text-sky-600 uppercase tracking-wide">{{ $courseTitle }}</p>
                    <h1 class="text-xl font-semibold text-slate-800 mt-1">{{ $className }}</h1>
                    <p class="text-sm text-slate-500 mt-1">{{ $teacherLabel }} · {{ $nextSessionLabel }}</p>
                    <div class="mt-2"><x-rating-summary :average="$ratingAverage" :count="$ratingCount" /></div>
                </div>
            </div>
            <div class="w-40">
                <x-progress-bar :percent="$overallPercent" label="Tiến độ chung" tone="brand" />
            </div>
        </div>
    </div>

    <x-tabs :tabs="$tabsData" />

    @if ($tab === 'roadmap')
        <div class="space-y-6">
            @forelse ($roadmap as $chap)
                <div>
                    <h3 class="font-medium text-slate-700 mb-3">{{ $chap['chapter'] }}</h3>
                    <div class="bg-white rounded-2xl border border-slate-200 divide-y divide-slate-100">
                        @foreach ($chap['items'] as $item)
                            <div class="flex items-center justify-between p-4">
                                <div class="flex items-center gap-3">
                                    <x-icon-tile emoji="{{ $item['type'] === 'coding' ? '💻' : '📝' }}" tone="{{ $item['tone'] === 'neutral' ? 'amber' : 'sky' }}" />
                                    <div>
                                        <p class="text-sm font-medium text-slate-700">{{ $item['title'] }}</p>
                                        <p class="text-xs text-slate-400">{{ $item['type'] }} · <x-status-badge :tone="$item['tone']">{{ $item['status'] }}</x-status-badge></p>
                                        @if (! empty($item['shiftLabel']))
                                            <p class="text-xs text-amber-600 mt-0.5">🕐 {{ $item['shiftLabel'] }} (chia ca thi chống nghẽn)</p>
                                        @endif
                                    </div>
                                </div>
                                <div class="text-right">
                                    @if ($item['status'] === 'Giáo viên chưa mở')
                                        <span class="text-xs text-slate-400">🔒 Giáo viên chưa mở nội dung này</span>
                                    @else
                                        <a href="{{ route('student.practice.index') }}" class="text-sm font-medium text-rose-600">
                                            {{ $item['result'] === 'Chưa làm' ? 'Làm bài ›' : 'Xem lại ›' }}
                                        </a>
                                        @if ($item['result'] && $item['result'] !== 'Chưa làm')
                                            <p class="text-xs text-slate-400 mt-0.5">{{ $item['result'] }}</p>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <x-empty-state title="Lớp chưa có bài tập nào" description="Giáo viên chưa giao bài tập cho lớp này." />
            @endforelse
        </div>
    @elseif ($tab === 'schedule')
        {{-- Mỗi buổi có ĐỦ 2 trạng thái độc lập: thời gian (Sắp diễn ra/Đang diễn ra/Đã
             kết thúc — trước đây chỉ có 2 mức nên buổi ĐANG diễn ra bị hiện nhầm "Đã qua")
             và điểm danh CỦA CHÍNH học sinh này (Có mặt/Vắng/Vắng có phép/Đi trễ/Chưa điểm
             danh — trước đây không hiển thị). Xem App\Services\Student\ClassRoomService
             ::buildScheduleTab(). --}}
        <div class="bg-white rounded-2xl border border-slate-200 divide-y divide-slate-100">
            @forelse ($sessions as $s)
                <div class="flex items-center justify-between p-4 text-sm">
                    <div>
                        <p class="text-slate-700">{{ $s['topic'] ?? 'Buổi học' }}</p>
                        <p class="text-xs text-slate-400 mt-0.5">{{ $s['location'] }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-slate-500">{{ $s['startsAt']?->format('d/m/Y H:i') }}</p>
                        <div class="flex items-center justify-end gap-1.5 mt-1">
                            <x-status-badge :tone="$s['timeStatusTone']">{{ $s['timeStatusLabel'] }}</x-status-badge>
                            <x-status-badge :tone="$s['attendanceTone']">{{ $s['attendanceLabel'] }}</x-status-badge>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-8"><x-empty-state title="Chưa có lịch học nào" /></div>
            @endforelse
        </div>
    @elseif ($tab === 'materials')
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse ($materials as $cm)
                <div class="rounded-2xl bg-white border border-slate-200 p-5">
                    <x-status-badge tone="success">Đang dùng</x-status-badge>
                    <h3 class="font-medium text-slate-800 mt-2">{{ $cm->material->title ?? 'Học liệu' }}</h3>
                </div>
            @empty
                <div class="col-span-full"><x-empty-state title="Lớp chưa gắn học liệu nào" /></div>
            @endforelse
        </div>
    @elseif ($tab === 'reviews')
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-sm text-slate-500 mb-3">Bạn đủ điều kiện đánh giá lớp này sau khi tham gia 2 buổi.</p>
            <a href="{{ route('reviews.form', ['type' => 'class', 'id' => $classRoom->id]) }}" class="text-sm text-rose-600 font-medium mb-4 inline-block">Viết đánh giá ›</a>
            <div class="divide-y divide-slate-100">
                @forelse ($reviews as $r)
                    <div class="py-3">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-slate-700">{{ $r->reviewer->name ?? 'Học viên' }}</p>
                            <x-rating-summary :average="$r->overall_rating" :count="1" />
                        </div>
                        <p class="text-sm text-slate-500 mt-1">{{ $r->comment }}</p>
                    </div>
                @empty
                    <x-empty-state title="Chưa có đánh giá nào cho lớp này" />
                @endforelse
            </div>
        </div>
    @elseif ($tab === 'notifications')
        {{-- Trước đây là dòng chữ TODO tĩnh hiển thị thẳng cho học sinh ("cần bảng
             notifications") — SAI, vì hạ tầng thông báo đã có thật (dùng chung với chuông
             toàn cục + student.notifications). Giờ lọc đúng thông báo trỏ về lớp NÀY, xem
             App\Services\Student\ClassRoomService::notificationsForClass(). --}}
        <div class="bg-white rounded-2xl border border-slate-200 divide-y divide-slate-100">
            @forelse ($notifications as $n)
                <div class="flex items-start gap-3 p-4 {{ ! $n['read'] ? 'bg-rose-50/40' : '' }}">
                    <x-icon-tile :emoji="$n['icon']" :tone="$n['tone']" />
                    <div class="flex-1">
                        <p class="text-sm text-slate-700">{{ $n['text'] }}</p>
                        <p class="text-xs text-slate-400 mt-1">{{ $n['time'] }}</p>
                    </div>
                    @if (! $n['read'])
                        <span class="w-2 h-2 rounded-full bg-rose-500 mt-2"></span>
                    @endif
                </div>
            @empty
                <div class="p-8">
                    <x-empty-state title="Chưa có thông báo nào cho lớp này" description="Thông báo về bài mới mở, lịch đổi hoặc thông báo từ giáo viên của lớp này sẽ hiện ở đây." />
                </div>
            @endforelse
        </div>
    @elseif ($tab === 'members')
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h3 class="font-medium text-slate-700 mb-3">Giáo viên</h3>
                <div class="space-y-2.5">
                    @foreach ($teachers as $t)
                        <div class="flex items-center gap-3">
                            <img src="https://ui-avatars.com/api/?name={{ urlencode($t->name) }}&background=e11d48&color=ffffff&size=64&bold=true"
                                 alt="{{ $t->name }}" class="w-8 h-8 rounded-full shrink-0">
                            <p class="text-sm text-slate-600">{{ $t->name }} <span class="text-xs text-slate-400">({{ $t->pivot->role ?? 'main' }})</span></p>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h3 class="font-medium text-slate-700 mb-3">Học sinh ({{ $students->count() }})</h3>
                <div class="space-y-2.5 max-h-64 overflow-y-auto">
                    @foreach ($students as $s)
                        <div class="flex items-center gap-3">
                            <img src="https://ui-avatars.com/api/?name={{ urlencode($s->name) }}&background=e0f2fe&color=0369a1&size=64&bold=true"
                                 alt="{{ $s->name }}" class="w-8 h-8 rounded-full shrink-0">
                            <p class="text-sm text-slate-600">{{ $s->name }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h3 class="font-medium text-slate-700 mb-2">Buổi học gần nhất</h3>
                <p class="text-sm text-slate-500">{{ $nextSessionLabel }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h3 class="font-medium text-slate-700 mb-2">Tiến độ tổng quan</h3>
                <x-progress-bar :percent="$overallPercent" tone="brand" />
            </div>
        </div>
    @endif
@endsection
