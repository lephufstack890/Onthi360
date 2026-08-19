@extends('layouts.student')

@section('title', 'Chi tiết lớp')
@section('page-title', 'Chi tiết lớp')

@section('content')
    @php
        $tab = $tab ?? 'overview';
        $roadmap = $roadmap ?? [];
        $materials = $materials ?? [];
        $days = $days ?? [];
        $weekOffset = $weekOffset ?? 0;
        $weekStart = $weekStart ?? now();
        $weekEnd = $weekEnd ?? now();
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
        $ratingDistribution = $ratingSummary?->distribution ?? [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $ratingDistributionTotal = array_sum($ratingDistribution) ?: 1;
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
        {{-- DẠNG BẢNG theo tuần (Thứ Hai → Chủ Nhật, có ngày cụ thể) — cùng cách trình bày
             với student.schedule.index (trang thời khoá biểu gộp mọi lớp), chỉ khác là CHỈ
             lọc buổi học của LỚP NÀY. Mỗi buổi có ĐỦ 2 trạng thái độc lập: thời gian (Sắp
             diễn ra/Đang diễn ra/Đã kết thúc) và điểm danh CỦA CHÍNH học sinh này (Có
             mặt/Vắng/Vắng có phép/Đi trễ/Chưa điểm danh). Xem App\Services\Student\
             ClassRoomService::buildScheduleTab(). --}}
        <div class="flex items-center justify-between gap-3 mb-4 flex-wrap">
            <div class="flex items-center gap-2">
                <a href="{{ route('student.classes.show', ['class' => $classRoom->id, 'tab' => 'schedule', 'week' => $weekOffset - 1]) }}"
                   class="w-9 h-9 rounded-lg border border-slate-200 bg-white flex items-center justify-center text-slate-500 hover:bg-slate-50" aria-label="Tuần trước">‹</a>
                <p class="text-sm font-medium text-slate-700 min-w-[160px] text-center">
                    {{ $weekStart->format('d/m') }} – {{ $weekEnd->format('d/m/Y') }}
                </p>
                <a href="{{ route('student.classes.show', ['class' => $classRoom->id, 'tab' => 'schedule', 'week' => $weekOffset + 1]) }}"
                   class="w-9 h-9 rounded-lg border border-slate-200 bg-white flex items-center justify-center text-slate-500 hover:bg-slate-50" aria-label="Tuần sau">›</a>
            </div>
            @if ($weekOffset !== 0)
                <a href="{{ route('student.classes.show', ['class' => $classRoom->id, 'tab' => 'schedule']) }}" class="text-sm text-rose-600 font-medium">Về tuần này</a>
            @endif
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 overflow-x-auto">
            <table class="w-full border-collapse min-w-[980px] table-fixed">
                <thead>
                    <tr>
                        @foreach ($days as $day)
                            <th class="w-[14.2857%] align-top border-b border-slate-200 {{ ! $loop->last ? 'border-r' : '' }} p-3 text-left {{ $day['isToday'] ? 'bg-rose-50' : 'bg-slate-50' }}">
                                <p class="text-xs font-semibold uppercase tracking-wide {{ $day['isToday'] ? 'text-rose-600' : 'text-slate-500' }}">{{ $day['label'] }}</p>
                                <p class="text-sm font-medium text-slate-700">{{ $day['date']->format('d/m') }}</p>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        @foreach ($days as $day)
                            <td class="align-top border-slate-200 {{ ! $loop->last ? 'border-r' : '' }} p-2 {{ $day['isToday'] ? 'bg-rose-50/30' : '' }}">
                                <div class="space-y-2">
                                    @forelse ($day['sessions'] as $s)
                                        <div class="rounded-xl bg-slate-50 border border-slate-100 p-2.5">
                                            <p class="text-xs font-semibold text-slate-700 truncate" title="{{ $s['topic'] }}">{{ $s['topic'] ?? 'Buổi học' }}</p>
                                            @if (! empty($s['location']))
                                                <p class="text-xs text-slate-400 truncate" title="{{ $s['location'] }}">📍 {{ $s['location'] }}</p>
                                            @endif
                                            <p class="text-xs text-slate-400 mt-1">🕐 {{ $s['timeRangeLabel'] }}</p>
                                            <div class="flex flex-wrap items-center gap-1 mt-1.5">
                                                <x-status-badge :tone="$s['timeStatusTone']">{{ $s['timeStatusLabel'] }}</x-status-badge>
                                                <x-status-badge :tone="$s['attendanceTone']">{{ $s['attendanceLabel'] }}</x-status-badge>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-xs text-slate-300 italic px-1">—</p>
                                    @endforelse
                                </div>
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
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
        {{-- Thiết kế lại (trước đây chỉ là 1 khối trắng đơn sơ, không có điểm TB/phân phối
             sao, và mỗi review lại dùng nhầm <x-rating-summary :count="1"> — component này
             chỉ dành cho SỐ TỔNG HỢP nên với count=1 luôn hiện "Chưa đủ đánh giá để xếp
             hạng" thay vì hiện sao thật của từng review, đây là lỗi hiển thị đã sửa ở đây).
             $ratingDistribution là SỐ LƯỢT theo từng mức sao (không phải % — xem
             Admin\ReviewService::recomputeRatingSummary()), nên phải tự quy đổi ra % ở view
             này để vẽ thanh phân phối đúng tỉ lệ. --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-6 mb-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div class="flex flex-col items-center justify-center text-center sm:border-r sm:border-slate-100">
                    @if ($ratingCount >= 5)
                        <p class="text-4xl font-semibold text-slate-800">{{ number_format($ratingAverage, 1) }}</p>
                    @endif
                    <x-rating-summary :average="$ratingAverage" :count="$ratingCount" />
                </div>
                <div class="space-y-1.5 self-center">
                    @foreach ([5, 4, 3, 2, 1] as $star)
                        @php $starPct = round((($ratingDistribution[$star] ?? 0) / $ratingDistributionTotal) * 100); @endphp
                        <div class="flex items-center gap-2 text-xs text-slate-500">
                            <span class="w-10 shrink-0">{{ $star }} sao</span>
                            <div class="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden">
                                <div class="h-full bg-amber-400 rounded-full" style="width: {{ $starPct }}%"></div>
                            </div>
                            <span class="w-6 text-right shrink-0">{{ $ratingDistribution[$star] ?? 0 }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
            <p class="text-sm text-slate-500">Bạn đủ điều kiện đánh giá lớp này sau khi tham gia 2 buổi.</p>
            <a href="{{ route('reviews.form', ['type' => 'class', 'id' => $classRoom->id]) }}"
               class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium shrink-0">Viết đánh giá ›</a>
        </div>

        <div class="space-y-4">
            @forelse ($reviews as $r)
                @php $reviewStars = (int) round($r->overall_rating); @endphp
                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <div class="flex items-start gap-3">
                        <img src="https://ui-avatars.com/api/?name={{ urlencode($r->reviewer->name ?? 'Học viên') }}&background=e0f2fe&color=0369a1&size=64&bold=true"
                             alt="{{ $r->reviewer->name ?? 'Học viên' }}" class="w-9 h-9 rounded-full shrink-0">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <p class="text-sm font-medium text-slate-700">{{ $r->reviewer->name ?? 'Học viên' }}</p>
                                <p class="text-xs text-slate-400 shrink-0">{{ $r->published_at?->diffForHumans() }}</p>
                            </div>
                            <p class="text-amber-500 text-sm mt-0.5" aria-label="{{ $reviewStars }} trên 5 sao">
                                {{ str_repeat('★', $reviewStars) }}{{ str_repeat('☆', 5 - $reviewStars) }}
                            </p>
                            @if (! empty($r->comment))
                                <p class="text-sm text-slate-600 mt-2">{{ $r->comment }}</p>
                            @endif
                            @if (! empty($r->admin_reply))
                                <div class="mt-3 rounded-xl bg-slate-50 border border-slate-100 p-3">
                                    <p class="text-xs font-medium text-slate-500 mb-1">💬 Phản hồi từ Ban quản trị</p>
                                    <p class="text-sm text-slate-600">{{ $r->admin_reply }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <x-empty-state title="Chưa có đánh giá nào cho lớp này" description="Hãy là người đầu tiên chia sẻ trải nghiệm sau khi tham gia lớp." />
            @endforelse
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
