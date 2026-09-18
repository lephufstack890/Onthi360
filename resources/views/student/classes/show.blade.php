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
        // SỬA 16/9 (lỗi khách báo) — $nextSession chỉ là buổi SẮP TỚI (starts_at >= now) nên
        // buổi ĐANG DIỄN RA không lọt vào, trang hiện "Chưa có buổi học sắp tới" và thẻ vào lớp
        // trực tuyến không thấy phòng học. $currentSession (mới) là buổi đang chạy, không có thì
        // là buổi sắp tới — xem ClassSessionRepository::currentOrNextForClassRoom().
        $currentSession = $currentSession ?? null;
        $liveSession = $currentSession ?: ($nextSession ?? null);
        $liveIsRunning = $liveSession
            && $liveSession->starts_at !== null
            && $liveSession->ends_at !== null
            && now()->gte($liveSession->starts_at) && now()->lte($liveSession->ends_at);
        $nextSessionLabel = $liveSession && $liveSession->starts_at !== null
            ? ($liveIsRunning
                ? 'Đang diễn ra: '.$liveSession->starts_at->format('d/m H:i')
                : 'Buổi tới: '.$liveSession->starts_at->format('d/m H:i'))
            : 'Chưa có buổi học sắp tới';
        $ratingAverage = $ratingSummary->avg_rating ?? 0;
        $ratingCount = $ratingSummary->review_count ?? 0;
        $ratingDistribution = $ratingSummary?->distribution ?? [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $ratingDistributionTotal = array_sum($ratingDistribution) ?: 1;

        /* ═══════════════════════════════════════════════════════════════════════════════
           SỬA 16/9 — DỰNG LẠI UI theo bản mẫu khách gửi
           (education-main-12/education-main/src/components/ClassroomPage.jsx).

           LOGIC GIỮ NGUYÊN 100%: vẫn đúng $tab / $weekOffset lấy từ Student\ClassRoomController,
           vẫn đúng các khoá dữ liệu ClassRoomService::buildShowData() trả về, vẫn đúng route cũ,
           không thêm một truy vấn nào. Chỗ nào bản mẫu VIẾT CỨNG dữ liệu không có nguồn thật thì
           thay bằng dữ liệu thật tương ứng (ghi rõ ở từng khối), KHÔNG in số bịa.

           Khác biệt bố cục duy nhất so với bản mẫu: bản mẫu là trang đứng một mình nên có
           <header> riêng và lưới 3 cột 220px / 1fr / 260px. Ở đây trang nằm TRONG khu làm việc
           học sinh (layouts/workspace đã có sẵn thanh điều hướng 230px bên trái), nên header của
           bản mẫu trở thành thẻ đầu trang, và lưới 3 cột chỉ bung đủ từ xl trở lên.
           ═══════════════════════════════════════════════════════════════════════════════ */

        // Ảnh lớp: bản mẫu dùng course.image. Lấy ảnh bìa THẬT của khoá, chưa có thì xoay vòng 5
        // ảnh minh hoạ của bản mẫu theo MÃ KHOÁ — đúng ảnh mà trang Lớp học công khai và trang
        // chi tiết khoá đang hiện, để học sinh nhận ra cùng một lớp.
        $fallbackCovers = ['course-img-1.png', 'course-img-2.png', 'course-img-3.png', 'course-img-4.png', 'course-img-5.png'];
        $coverUrl = ($classRoom->course?->coverUrl())
            ?: asset('assets/'.$fallbackCovers[((int) ($classRoom->course_id ?? 0)) % count($fallbackCovers)]);

        // Chip "Đang học" của bản mẫu là chữ chết; ở đây đọc trạng thái thật của lớp.
        $isArchived = ($classRoom->status ?? 'active') === 'archived';
        $statusLabel = $isArchived ? 'Đã kết thúc' : 'Đang học';
        $statusChip = $isArchived ? 'bg-slate-100 text-slate-500' : 'bg-emerald-50 text-emerald-700';

        // Lịch học: ghi chú định kỳ quản trị nhập (class_rooms.schedule->note). Chưa nhập thì
        // dùng mốc buổi học kế tiếp có thật thay cho dòng "Thứ Ba & Thứ Năm · 19:30 – 21:30" cứng.
        $scheduleNote = $classRoom->schedule['note'] ?? null;

        // 4 trường mô tả lớp (migration add_display_fields_to_class_rooms_table). Máy chủ chưa
        // chạy migrate thì coi như chưa nhập — giấu dòng, không vỡ trang.
        $supportsDisplay = \App\Models\ClassRoom::supportsDisplayFields();
        $classLocation = $supportsDisplay ? ($classRoom->location ?? null) : null;
        $classFormat = $supportsDisplay ? ($classRoom->format ?? null) : null;
        $classCapacity = $supportsDisplay ? ($classRoom->capacity ?? null) : null;

        // Cột trái của bản mẫu ("Trong lớp học") chính là chỗ đặt dải tab cũ: mỗi tab một dòng,
        // vẫn đúng $tabsData (label/href/active) do buildShowData() dựng, không tự chế thêm tab.
        $navIcons = [
            'Tổng quan' => 'target',
            'Lộ trình & Bài tập' => 'route',
            'Lịch học' => 'calendar-days',
            'Tài liệu' => 'book-open',
            'Học liệu' => 'book-open',
            'Đánh giá' => 'star',
            'Thông báo' => 'bell',
            'Thành viên' => 'users',
        ];

        // Bản mẫu in cứng huy hiệu "2" cạnh Thông báo. $notifications CHỈ được nạp khi đang ở
        // tab Thông báo (buildShowData() nạp theo tab để khỏi truy vấn thừa) — nên chỉ hiện số
        // khi thật sự có dữ liệu, không đoán và không thêm truy vấn mới.
        $unreadNotifications = collect($notifications)->where('read', false)->count();

        // Bản mẫu in cứng "Điểm danh 28/35" và "Sĩ số 28 / 35". Sĩ số thực tế chỉ có ở tab
        // Thành viên ($students), sĩ số tối đa là trường quản trị nhập — in đúng thứ đang có.
        $studentsCount = $tab === 'members' ? $students->count() : null;

        $isOverview = ! in_array($tab, ['roadmap', 'schedule', 'materials', 'reviews', 'notifications', 'members'], true);

        // Bản mẫu có 4 "hoạt động" viết cứng. Nguồn thật tương đương ở tab Tổng quan là
        // $roadmap (bài tập của lớp — ClassRoomService::buildRoadmap()). Trải phẳng để đưa vào
        // băng chuyền hoạt động của bản mẫu.
        $roadmapItems = collect($roadmap)->flatMap(fn ($chap) => $chap['items'] ?? [])->values();

        // toneClasses() của bản mẫu: live → xanh lá, done → xanh biển, còn lại → hổ phách.
        $chipTone = fn ($tone) => match ($tone) {
            'info' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'success' => 'border-sky-200 bg-sky-50 text-[#126F91]',
            default => 'border-amber-200 bg-amber-50 text-amber-700',
        };

        // SỬA 16/9 (lỗi khách báo: "có hoạt động rồi mà nó vẫn không hiện") — băng HOẠT ĐỘNG
        // BUỔI HỌC thật (session_activities đã bấm phát), nguồn đúng với thứ khách nhìn thấy ở
        // tab Lịch học. Xem ClassRoomService::buildActivityFeed().
        $activityFeed = $activityFeed ?? ['items' => [], 'initialIndex' => 0];
        $feedItems = collect($activityFeed['items'] ?? []);
        $feedInitial = (int) ($activityFeed['initialIndex'] ?? 0);

        // toneClasses() của bản mẫu áp cho trạng thái THỜI GIAN của buổi học:
        //   đang diễn ra (warning) -> live/xanh lá · đã kết thúc (neutral) -> done/xanh biển
        //   sắp diễn ra (info)     -> upcoming/hổ phách
        $sessionChipTone = fn ($tone) => match ($tone) {
            'warning' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'neutral' => 'border-sky-200 bg-sky-50 text-[#126F91]',
            default => 'border-amber-200 bg-amber-50 text-amber-700',
        };

        // Icon theo loại tài nguyên buổi học (App\Enums\SessionResourceType).
        $resourceIcons = [
            'material' => 'book-open',
            'question' => 'circle-help',
            'assessment' => 'file-check-2',
            'video' => 'video',
            'link' => 'external-link',
            'note' => 'sticky-note',
        ];

        // Bản mẫu có nút "Mở Meet lớp học" trỏ vào link cứng. Hệ thống KHÔNG có cột link phòng
        // học riêng, nhưng class_sessions.location vốn được định nghĩa là "phòng học HOẶC link
        // online" — nên buổi học kế tiếp có link http thì đó chính là phòng trực tuyến thật.
        $meetUrl = ($liveSession && filled($liveSession->location) && \Illuminate\Support\Str::startsWith($liveSession->location, ['http://', 'https://']))
            ? $liveSession->location
            : null;
        $meetRoomNote = ($liveSession && filled($liveSession->location) && $meetUrl === null) ? $liveSession->location : null;


        /* ═══════════════════════════════════════════════════════════════════
           SỬA 18/9 — DỰNG LẠI MÀN LỚP HỌC theo bản mẫu
           education-main/src/components/ClassroomPage.jsx (ảnh khách gửi).
           Dữ liệu: App\Services\Student\ClassRoomService::buildClassroomData().
           Khách dặn "mục ghi hình buổi học tạm thời bỏ đi" -> không dựng khối đó
           (hệ thống cũng chưa có nguồn video ghi hình).
           ═══════════════════════════════════════════════════════════════════ */
        $classroom = $classroom ?? [];
        $lessons = $classroom['lessons'] ?? [];
        $lesson = $classroom['selected'] ?? null;
        $acts = $classroom['activities'] ?? [];
        $meetUrl2 = $classroom['meetUrl'] ?? null;
        $roomNote2 = $classroom['roomNote'] ?? null;
        $classNotifications = $classroom['notifications'] ?? [];
        $classDocs = $classroom['classMaterials'] ?? [];

        // Hoạt động mở sẵn: cái đang diễn ra, không có thì cái cuối cùng đã tổ chức.
        $actInitial = 0;
        foreach ($acts as $idx => $a) {
            if ($a['state'] === 'current') { $actInitial = $idx; break; }
            $actInitial = $idx;
        }

        // Đếm số mục từng nhóm của TỪNG hoạt động, đưa sang Alpine để nhóm tab biết
        // nhóm nào rỗng mà khoá lại (bản mẫu khoá tab khi count = 0).
        $actCounts = [];
        foreach ($acts as $a) {
            $actCounts[] = [
                'exercise' => count($a['items']['exercise']),
                'document' => count($a['items']['document']),
                'material' => count($a['items']['material']),
            ];
        }

        $doneActs = collect($acts)->where('state', 'completed')->count();
        $actPercent = count($acts) > 0 ? (int) round($doneActs / count($acts) * 100) : 0;

        $unreadClassNotifications = collect($classNotifications)->where('read', false)->count();

        $contentTabs = [
            ['exercise', 'Bài tập', 'code-2'],
            ['document', 'Tài liệu', 'file-text'],
            ['material', 'Học liệu', 'play-circle'],
        ];
    @endphp

{{-- Thẻ bọc, thanh đầu trang và lưới chính chép theo bản mẫu
     (education-main/src/components/ClassroomPage.jsx).

     SỬA 18/9 (khách: "bỏ padding vs margin cho nó full ra cho đẹp") — BỎ 3 thứ của bản mẫu:
       · max-w-[1240px] + mx-auto : bản mẫu là TRANG ĐỨNG MỘT MÌNH nên tự kẹp bề ngang và căn
         giữa. Ở đây trang nằm trong khu làm việc học sinh, khung ngoài (layouts/workspace) đã
         kẹp max-w-[1780px] rồi — kẹp thêm lần nữa là thừa hai dải lề trống hai bên;
       · px-4 sm:px-6 : khung ngoài cũng đã có px-3/px-5/px-6/2xl:px-10 — để nguyên là padding
         chồng padding, nội dung bị thụt vào gấp đôi;
       · min-h-screen : bản mẫu chiếm trọn màn hình; ở đây phía trên còn thanh của khu làm việc
         nên ép 100vh là luôn dư ra một dải trắng phải cuộn.
     Padding DỌC (py) giữ nguyên — đó là khoảng thở giữa các khối, không phải lề trang. --}}
<div class="classroom-ui bg-[#F7F9FB] text-[#466278]">
    {{-- ══════ THANH ĐẦU TRANG ══════ --}}
    <header class="sticky top-0 z-30 border-b border-[#DDEAF0] bg-white/95 backdrop-blur-xl">
        <div class="flex items-center gap-2.5 py-2.5">
            <a href="{{ route('student.courses.index') }}"
               class="classroom-back-button inline-flex shrink-0 items-center gap-1.5 rounded-xl px-2.5 py-1.5 text-[12px] font-semibold">
                <x-lucide name="arrow-left" class="h-4 w-4" />Danh sách lớp
            </a>
            <div class="hidden h-6 w-px bg-slate-200 sm:block"></div>
            <img src="{{ $coverUrl }}" alt="" decoding="async" class="h-9 w-9 shrink-0 rounded-lg border border-[#DDEAF0] object-cover">
            <div class="min-w-0 flex-1">
                <div class="flex min-w-0 items-center gap-2">
                    <h1 class="truncate text-[16px] font-semibold leading-5 text-[#123B68]">{{ $className }}</h1>
                    <span class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $statusChip }}">
                        <x-lucide name="radio" class="h-2.5 w-2.5" />{{ $statusLabel }}
                    </span>
                </div>
                <div class="flex min-w-0 flex-wrap items-center gap-2 text-[12px] text-[#61798B]">
                    <p class="min-w-0 truncate">{{ $classRoom->code }} · {{ $teacherLabel }}</p>
                    @if ($ratingCount > 0)
                        <span class="classroom-course-rating inline-flex shrink-0 items-center gap-1 rounded-md px-1.5 py-0.5"
                              aria-label="{{ number_format($ratingAverage, 1) }} sao từ {{ $ratingCount }} đánh giá">
                            <x-lucide name="star" class="h-3 w-3 fill-current" />
                            <span class="font-semibold">{{ number_format($ratingAverage, 1) }}</span>
                            <span class="classroom-course-rating-count">({{ $ratingCount }})</span>
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </header>

    <main class="grid gap-3 py-3.5 lg:grid-cols-[220px_minmax(0,1fr)] lg:gap-4 lg:py-4">
        {{-- ══════ CỘT TRÁI ══════ --}}
        <aside class="space-y-3">
            @if ($lesson)
                @php
                    $att = $lesson['attendance'];
                    $attPresent = in_array($att['tone'], ['present', 'late'], true);
                @endphp
                <div class="{{ $attPresent ? 'classroom-attendance-present' : 'rounded-lg border border-[#DDEAF0] bg-white' }} flex w-full items-center gap-2 rounded-lg p-1.5 text-left">
                    <span class="{{ $attPresent ? 'classroom-attendance-icon' : 'bg-[#EAF5F8] text-[#126F91]' }} grid h-6 w-6 shrink-0 place-items-center rounded-md">
                        <x-lucide :name="$attPresent ? 'user-check' : 'circle'" class="h-3 w-3" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[11px] font-semibold {{ $attPresent ? 'text-white' : 'text-[#123B68]' }}">{{ $att['label'] }}</span>
                        <span class="mt-0.5 block text-[9px] {{ $attPresent ? 'text-white/85' : 'text-[#61798B]' }}">
                            Buổi {{ $lesson['number'] }}@if ($att['timeLabel']) · {{ $att['timeLabel'] }}@endif
                        </span>
                    </span>
                </div>
            @endif

            <section class="classroom-side-card classroom-schedule-card rounded-2xl border border-[#E6D39A] bg-[#FFF8E7] p-3.5">
                <h2 class="flex items-center gap-2 text-[14px] font-semibold text-[#123B68]"><x-lucide name="calendar-days" class="h-4 w-4 text-[#2D7FA3]" />Lịch học</h2>
                <p class="mt-2 text-[13px] font-medium leading-5 text-[#466278]">{{ $scheduleNote ?: $nextSessionLabel }}</p>
                @if ($classLocation || $classFormat)
                    <p class="mt-1 flex items-center gap-1.5 text-[12px] text-[#61798B]">
                        <x-lucide name="clock-3" class="h-3.5 w-3.5 shrink-0" />{{ collect([$classLocation, $classFormat])->filter()->implode(' · ') }}
                    </p>
                @endif
            </section>

            <section class="classroom-side-card rounded-2xl border bg-white p-3.5">
                <div class="flex items-center gap-2">
                    <x-lucide name="video" class="h-4 w-4 text-[#2D7FA3]" />
                    <h2 class="text-[14px] font-semibold text-[#123B68]">Lớp trực tuyến</h2>
                </div>
                <p class="mt-1.5 text-[13px] leading-5 text-[#61798B]">Phòng học trực tiếp theo lịch của lớp.</p>
                @if ($meetUrl2)
                    <a href="{{ $meetUrl2 }}" target="_blank" rel="noopener noreferrer"
                       class="classroom-meet-button mt-2.5 flex items-center justify-between rounded-xl px-3 py-2 text-[13px] font-semibold text-white">
                        <span>Tham gia phòng học</span><x-lucide name="external-link" class="h-3.5 w-3.5" />
                    </a>
                @elseif ($roomNote2)
                    <p class="mt-2.5 flex items-center gap-1.5 rounded-xl bg-[#F4F9FC] px-3 py-2 text-[12px] font-semibold text-[#466278]">
                        <x-lucide name="map-pin" class="h-3.5 w-3.5 shrink-0 text-[#2D7FA3]" /><span class="min-w-0 truncate">{{ $roomNote2 }}</span>
                    </p>
                @else
                    <p class="mt-2.5 rounded-xl bg-[#F4F9FC] px-3 py-2 text-[12px] text-[#61798B]">Buổi này chưa có phòng học hay liên kết.</p>
                @endif
            </section>

            {{-- ẨN 18/9 theo yêu cầu khách ("mục ghi hình buổi học tạm thời bỏ đi") — khối
                 "Ghi hình buổi học" của bản mẫu. Hệ thống cũng chưa có cột nào lưu video ghi
                 hình theo buổi, nên bật lại thì phải thêm nguồn dữ liệu trước. --}}

            @if (count($classDocs) > 0)
                <section class="classroom-side-card classroom-class-materials-card rounded-2xl border bg-white p-3.5">
                    <h2 class="flex items-center gap-2 text-[14px] font-semibold text-[#123B68]"><x-lucide name="file-text" class="h-4 w-4 text-[#B57A2B]" />Tài liệu lớp</h2>
                    <div class="mt-2.5 space-y-1">
                        @foreach ($classDocs as $doc)
                            <a href="{{ route('student.classes.show', ['class' => $classRoom->id, 'tab' => 'materials']) }}"
                               class="classroom-resource-link flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-left text-[12px] font-medium">
                                <span class="min-w-0 truncate">{{ $doc['title'] }}</span><x-lucide name="chevron-right" class="h-3.5 w-3.5 shrink-0" />
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Dải điều hướng các mục còn lại của lớp. Bản mẫu không có (nó chỉ dựng đúng màn
                 buổi học), nhưng Lịch học đầy đủ / Tài liệu / Đánh giá / Thông báo / Thành viên
                 vẫn phải vào được từ đây, nếu không là mất đường. --}}
            <section class="classroom-side-card rounded-2xl border bg-white p-3.5">
                <h2 class="mb-2 flex items-center gap-2 text-[14px] font-semibold text-[#123B68]"><x-lucide name="book-open" class="h-4 w-4 text-[#2D7FA3]" />Trong lớp học</h2>
                <div class="space-y-1">
                    @foreach ($tabsData as $navItem)
                        @php $navActive = $navItem['active'] ?? false; @endphp
                        <a href="{{ $navItem['href'] }}" @if ($navActive) aria-current="page" @endif
                           class="flex items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-[12px] font-medium transition {{ $navActive ? 'bg-[#EAF5F8] font-semibold text-[#126F91]' : 'text-[#466278] hover:bg-[#F4F9FC]' }}">
                            <span class="flex min-w-0 items-center gap-2">
                                <x-lucide :name="$navIcons[$navItem['label']] ?? 'chevron-right'" class="h-3.5 w-3.5 shrink-0" />
                                <span class="truncate">{{ $navItem['label'] }}</span>
                            </span>
                            @if ($navItem['label'] === 'Thông báo' && $unreadClassNotifications > 0)
                                <span class="shrink-0 rounded-full bg-[#FFF4D6] px-1.5 py-0.5 text-[10px] font-bold text-[#9A6B1E]">{{ $unreadClassNotifications }}</span>
                            @else
                                <x-lucide name="chevron-right" class="h-3.5 w-3.5 shrink-0 text-[#7890A0]" />
                            @endif
                        </a>
                    @endforeach
                </div>
            </section>
        </aside>

        {{-- ══════ CỘT PHẢI ══════ --}}
        <section class="min-w-0 space-y-4">
    @if ($tab === 'roadmap')
        <div class="space-y-6">
            @forelse ($roadmap as $chap)
                <div>
                    <h3 class="font-medium text-slate-700 mb-3">{{ $chap['chapter'] }}</h3>
                    <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] divide-y divide-slate-100">
                        @foreach ($chap['items'] as $item)
                            <div class="flex items-center justify-between p-4">
                                <div class="flex items-center gap-3">
                                    <x-ws.icon-tile emoji="{{ $item['type'] === 'coding' ? '💻' : '📝' }}" tone="{{ $item['tone'] === 'neutral' ? 'amber' : 'sky' }}" />
                                    <div>
                                        <p class="text-[13px] font-medium text-slate-700">{{ $item['title'] }}</p>
                                        <p class="text-xs text-slate-400">{{ $item['type'] }} · <x-ws.badge :tone="$item['tone']">{{ $item['status'] }}</x-ws.badge></p>
                                        @if (! empty($item['shiftLabel']))
                                            <p class="text-xs text-amber-600 mt-0.5"><x-lucide name="clock" class="inline h-3.5 w-3.5 shrink-0 align-[-2px]" /> {{ $item['shiftLabel'] }} (chia ca thi chống nghẽn)</p>
                                        @endif
                                    </div>
                                </div>
                                <div class="text-right">
                                    @if ($item['status'] === 'Giáo viên chưa mở')
                                        <span class="text-xs text-slate-400"><x-lucide name="lock" class="inline h-3.5 w-3.5 shrink-0 align-[-2px]" /> Giáo viên chưa mở nội dung này</span>
                                    @else
                                        <a href="{{ route('student.practice.index') }}" class="text-[13px] font-medium text-blue-600">
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
                <x-ws.empty-state title="Lớp chưa có bài tập nào" description="Giáo viên chưa giao bài tập cho lớp này." />
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
                   class="w-9 h-9 rounded-xl border border-sky-100 bg-white flex items-center justify-center text-slate-500 hover:bg-slate-50" aria-label="Tuần trước">‹</a>
                <p class="text-[13px] font-medium text-slate-700 min-w-[160px] text-center">
                    {{ $weekStart->format('d/m') }} – {{ $weekEnd->format('d/m/Y') }}
                </p>
                <a href="{{ route('student.classes.show', ['class' => $classRoom->id, 'tab' => 'schedule', 'week' => $weekOffset + 1]) }}"
                   class="w-9 h-9 rounded-xl border border-sky-100 bg-white flex items-center justify-center text-slate-500 hover:bg-slate-50" aria-label="Tuần sau">›</a>
            </div>
            @if ($weekOffset !== 0)
                <a href="{{ route('student.classes.show', ['class' => $classRoom->id, 'tab' => 'schedule']) }}" class="text-[13px] text-blue-600 font-medium">Về tuần này</a>
            @endif
        </div>

        <div class="bg-white rounded-3xl border border-sky-100 overflow-x-auto">
            <table class="w-full border-collapse min-w-[980px] table-fixed">
                <thead>
                    <tr>
                        @foreach ($days as $day)
                            <th class="w-[14.2857%] align-top border-b border-sky-100 {{ ! $loop->last ? 'border-r' : '' }} p-3 text-left {{ $day['isToday'] ? 'bg-blue-50' : 'bg-slate-50' }}">
                                <p class="text-xs font-semibold uppercase tracking-wide {{ $day['isToday'] ? 'text-blue-600' : 'text-slate-500' }}">{{ $day['label'] }}</p>
                                <p class="text-[13px] font-medium text-slate-700">{{ $day['date']->format('d/m') }}</p>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        @foreach ($days as $day)
                            <td class="align-top border-sky-100 {{ ! $loop->last ? 'border-r' : '' }} p-2 {{ $day['isToday'] ? 'bg-blue-50/30' : '' }}">
                                <div class="space-y-2">
                                    @forelse ($day['sessions'] as $s)
                                        <div class="rounded-xl bg-slate-50 border border-slate-100 p-2.5">
                                            <p class="text-xs font-semibold text-slate-700 truncate" title="{{ $s['topic'] }}">{{ $s['topic'] ?? 'Buổi học' }}</p>
                                            @if (! empty($s['location']))
                                                <p class="text-xs text-slate-400 truncate" title="{{ $s['location'] }}"><x-lucide name="map-pin" class="inline h-3.5 w-3.5 shrink-0 align-[-2px]" /> {{ $s['location'] }}</p>
                                            @endif
                                            <p class="text-xs text-slate-400 mt-1"><x-lucide name="clock" class="inline h-3.5 w-3.5 shrink-0 align-[-2px]" /> {{ $s['timeRangeLabel'] }}</p>
                                            <div class="flex flex-wrap items-center gap-1 mt-1.5">
                                                <x-ws.badge :tone="$s['timeStatusTone']">{{ $s['timeStatusLabel'] }}</x-ws.badge>
                                                <x-ws.badge :tone="$s['attendanceTone']">{{ $s['attendanceLabel'] }}</x-ws.badge>
                                            </div>

                                            {{-- SỬA 9/9 (5) — ô lịch chỉ hiện SỐ hoạt động cho gọn;
                                                 danh sách đầy đủ + nút Làm bài nằm ở khu "Hoạt động
                                                 buổi học" ngay dưới bảng, rộng rãi dễ bấm hơn. --}}
                                            @if (! empty($s['activities']))
                                                <p class="mt-1.5 inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-violet-100 text-violet-700 text-[10px] font-bold">
                                                    <x-lucide name="layers" class="inline h-3.5 w-3.5 shrink-0 align-[-2px]" /> {{ count($s['activities']) }} hoạt động
                                                </p>
                                            @endif
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

        {{-- ═══════════ HOẠT ĐỘNG BUỔI HỌC ═══════════
             SỬA 9/9 (5) (khách: "học sinh thấy được hoạt động, thiết kế UI thân thiện dễ nhìn, và
             học sinh có thể click vào làm được đề trong hoạt động"). Chỉ hiện hoạt động giáo viên
             ĐÃ BẤM PHÁT (lọc từ Student\ClassRoomService bằng scopePublished()). --}}
        @php
            $activityDays = collect($days)
                ->flatMap(fn ($day) => collect($day['sessions'])
                    ->filter(fn ($s) => ! empty($s['activities']))
                    ->map(fn ($s) => $s + ['dayLabel' => $day['label'] ?? '', 'isToday' => $day['isToday'] ?? false])
                    ->all())
                ->values();
        @endphp

        <div class="mt-6">
            <div class="flex items-center gap-2.5 mb-3">
                <span class="w-9 h-9 rounded-xl bg-violet-100 text-violet-600 flex items-center justify-center text-lg"><x-lucide name="layers" class="h-4 w-4" /></span>
                <div>
                    <h3 class="font-semibold text-slate-800">Hoạt động buổi học</h3>
                    <p class="text-xs text-slate-400">Bài giao thầy cô đã phát cho tuần này — bấm để làm ngay.</p>
                </div>
            </div>

            @forelse ($activityDays as $sess)
                <div class="rounded-3xl border border-sky-100 bg-white overflow-hidden mb-3">
                    <div class="flex flex-wrap items-center gap-2 px-4 py-2.5 bg-slate-50/80 border-b border-slate-100">
                        <span class="text-[13px] font-semibold text-slate-700">{{ $sess['topic'] ?: 'Buổi học' }}</span>
                        <span class="text-xs text-slate-400">
                            {{ $sess['startsAt']?->format('d/m/Y') }} · {{ $sess['timeRangeLabel'] }}
                        </span>
                        @if ($sess['isToday'])
                            <span class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-600 text-[10px] font-bold">Hôm nay</span>
                        @endif
                    </div>

                    <div class="p-4 space-y-3">
                        @foreach ($sess['activities'] as $activity)
                            <div class="rounded-xl border border-violet-100 bg-violet-50/40 p-3">
                                <p class="text-[13px] font-bold text-violet-800 flex items-center gap-1.5">
                                    <span><x-lucide name="layers" class="h-4 w-4" /></span> {{ $activity['title'] }}
                                </p>
                                @if (! empty($activity['note']))
                                    <p class="text-xs text-slate-500 mt-0.5">{{ $activity['note'] }}</p>
                                @endif

                                <div class="mt-2.5 space-y-2">
                                    @forelse ($activity['resources'] as $res)
                                        <div class="flex flex-wrap items-center gap-3 rounded-xl bg-white border border-sky-100 px-3 py-2.5">
                                            <span class="text-lg shrink-0"><x-lucide name="file-check-2" class="h-4 w-4" /></span>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-[13px] font-medium text-slate-700 truncate" title="{{ $res['title'] }}">{{ $res['title'] }}</p>
                                                <p class="text-[11px] text-slate-400">{{ $res['typeLabel'] }}</p>
                                            </div>
                                            @if (! empty($res['assessmentId']))
                                                <a href="{{ route('student.assessment.take', $res['assessmentId']) }}"
                                                   class="shrink-0 inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-blue-600 text-white text-[13px] font-semibold shadow-sm hover:bg-blue-700 transition">
                                                    ▶ Làm bài
                                                </a>
                                            @else
                                                <span class="shrink-0 text-[11px] text-slate-400 italic">Thầy cô chưa mở làm bài</span>
                                            @endif
                                        </div>
                                    @empty
                                        <p class="text-xs text-slate-400">Hoạt động này chưa có bài nào.</p>
                                    @endforelse
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="rounded-3xl border-2 border-dashed border-sky-100 py-10 text-center">
                    <p class="text-3xl mb-2">🌤️</p>
                    <p class="text-[13px] text-slate-500">Tuần này chưa có hoạt động nào được phát.</p>
                    <p class="text-xs text-slate-400 mt-1">Khi thầy cô phát hoạt động, bài giao sẽ hiện ở đây để em làm.</p>
                </div>
            @endforelse
        </div>
    @elseif ($tab === 'materials')
        {{-- SỬA 31/8 (khách yêu cầu — "chi tiết lớp có tab Học liệu để xem TRONG lớp thôi,
             tài liệu tự mua xem ở trang Tài liệu, không liên quan"): $materials giờ là mảng
             "thẻ" đã dựng sẵn qua LibraryService::productCard() (App\Services\Student\
             ClassRoomService::buildShowData()) — CHỈ gồm sản phẩm giáo viên đã gắn NGUYÊN
             vào LỚP NÀY, không phải danh sách "Tài liệu của tôi" (trang riêng, gộp mọi sản
             phẩm đã mua từ mọi lớp/tự mua — 2 trang cố ý KHÔNG liên quan nhau). Bố cục thẻ
             tài nguyên/bài tập y hệt student/materials/mine.blade.php để học sinh quen mắt,
             không phải học 2 cách trình bày khác nhau cho cùng 1 loại dữ liệu. --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            @forelse ($materials as $p)
                <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                    <div class="flex items-start gap-3">
                        <div class="w-12 h-14 rounded-xl overflow-hidden shrink-0 bg-gradient-to-br from-sky-100 to-blue-50 flex items-center justify-center">
                            @if ($p['coverPath'])
                                <img src="{{ asset('storage/'.$p['coverPath']) }}" alt="Bìa {{ $p['title'] }}" class="w-full h-full object-cover">
                            @else
                                <span class="text-lg"><x-lucide name="book-open" class="h-4 w-4" /></span>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-medium text-slate-800 leading-snug">{{ $p['title'] }}</h3>
                            <span class="inline-flex mt-1"><x-ws.badge tone="success">Đang dùng ở lớp này</x-ws.badge></span>
                        </div>
                    </div>

                    @if (count($p['resources']) > 0)
                        <div class="mt-4">
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-2">Tài nguyên đính kèm</p>
                            <div class="space-y-2">
                                @foreach ($p['resources'] as $res)
                                    <a href="{{ route('access.resource', ['product' => $p['id'], 'kind' => $res['kind']]) }}" target="_blank" rel="noopener"
                                       class="flex items-center gap-2 px-3 py-2 rounded-xl border border-sky-100 text-[13px] text-slate-600 hover:border-blue-200 hover:text-blue-600 transition">
                                        <span>{{ $res['icon'] }}</span> {{ $res['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if (count($p['exercises']) > 0)
                        <div class="mt-4">
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-2"><x-lucide name="layers" class="inline h-3.5 w-3.5 shrink-0 align-[-2px]" /> Bài tập</p>
                            <div class="space-y-2">
                                @foreach ($p['exercises'] as $ex)
                                    <div class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-xl border border-sky-100">
                                        <div class="min-w-0">
                                            <p class="text-[13px] text-slate-700 truncate">{{ $ex['title'] }}</p>
                                            <p class="text-xs text-slate-400">{{ $ex['points'] }} điểm · {{ $ex['summary'] }}</p>
                                        </div>
                                        <form action="{{ route('student.practiceByQuestion.startExercise', $ex['id']) }}" method="POST" class="shrink-0">
                                            @csrf
                                            {{-- Xem ghi chú đầy đủ ở student/materials/mine.blade.php: url()->full() là
                                                 URL TUYỆT ĐỐI nên bị PracticeByQuestionController::startExercise() coi
                                                 là không hợp lệ (chỉ nhận path bắt đầu bằng 1 dấu '/'), khiến sau khi
                                                 làm xong LUÔN quay về "Tài liệu của tôi" dù đang bấm từ tab Học liệu
                                                 của lớp này — request()->getRequestUri() mới là path+query tương đối
                                                 đúng, quay lại ĐÚNG tab Học liệu của ĐÚNG lớp này. --}}
                                            <input type="hidden" name="return_url" value="{{ request()->getRequestUri() }}">
                                            <button type="submit" class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 hover:bg-blue-700 transition">Làm bài ›</button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                            <p class="text-xs text-slate-400 mt-2">Bài tập chưa có chấm tự động — bài làm sẽ được ghi nhận, chưa báo đúng/sai ngay.</p>
                        </div>
                    @endif

                    @if (count($p['resources']) === 0 && count($p['exercises']) === 0)
                        <p class="text-[13px] text-slate-400 mt-4">Chưa có tài nguyên/bài tập nào cho học liệu này.</p>
                    @endif
                </div>
            @empty
                <div class="col-span-full"><x-ws.empty-state title="Lớp chưa gắn học liệu nào" description="Giáo viên sẽ gắn sách/chuyên đề/bộ đề vào lớp khi cần — mục này sẽ hiện ra ngay khi có." /></div>
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
        <div class="bg-white rounded-3xl border border-sky-100 p-6 mb-4">
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
            <p class="text-[13px] text-slate-500">Bạn đủ điều kiện đánh giá lớp này sau khi tham gia 2 buổi.</p>
            <a href="{{ route('reviews.form', ['type' => 'class', 'id' => $classRoom->id]) }}"
               class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 shrink-0">Viết đánh giá ›</a>
        </div>

        <div class="space-y-4">
            @forelse ($reviews as $r)
                @php $reviewStars = (int) round($r->overall_rating); @endphp
                <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                    <div class="flex items-start gap-3">
                        <x-ws.avatar :name="$r->reviewer->name ?? 'Học viên'" size="md" />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <p class="text-[13px] font-medium text-slate-700">{{ $r->reviewer->name ?? 'Học viên' }}</p>
                                <p class="text-xs text-slate-400 shrink-0">{{ $r->published_at?->diffForHumans() }}</p>
                            </div>
                            <p class="text-amber-500 text-[13px] mt-0.5" aria-label="{{ $reviewStars }} trên 5 sao">
                                {{ str_repeat('★', $reviewStars) }}{{ str_repeat('☆', 5 - $reviewStars) }}
                            </p>
                            @if (! empty($r->comment))
                                <p class="text-[13px] text-slate-600 mt-2">{{ $r->comment }}</p>
                            @endif
                            @if (! empty($r->admin_reply))
                                <div class="mt-3 rounded-xl bg-slate-50 border border-slate-100 p-3">
                                    <p class="text-xs font-medium text-slate-500 mb-1"><x-lucide name="message-circle" class="inline h-3.5 w-3.5 shrink-0 align-[-2px]" /> Phản hồi từ Ban quản trị</p>
                                    <p class="text-[13px] text-slate-600">{{ $r->admin_reply }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <x-ws.empty-state title="Chưa có đánh giá nào cho lớp này" description="Hãy là người đầu tiên chia sẻ trải nghiệm sau khi tham gia lớp." />
            @endforelse
        </div>
    @elseif ($tab === 'notifications')
        {{-- Trước đây là dòng chữ TODO tĩnh hiển thị thẳng cho học sinh ("cần bảng
             notifications") — SAI, vì hạ tầng thông báo đã có thật (dùng chung với chuông
             toàn cục + student.notifications). Giờ lọc đúng thông báo trỏ về lớp NÀY, xem
             App\Services\Student\ClassRoomService::notificationsForClass(). --}}
        <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] divide-y divide-slate-100">
            @forelse ($notifications as $n)
                <div class="flex items-start gap-3 p-4 {{ ! $n['read'] ? 'bg-blue-50/40' : '' }}">
                    <x-ws.icon-tile :emoji="$n['icon']" :tone="$n['tone']" />
                    <div class="flex-1">
                        <p class="text-[13px] text-slate-700">{{ $n['text'] }}</p>
                        <p class="text-xs text-slate-400 mt-1">{{ $n['time'] }}</p>
                    </div>
                    @if (! $n['read'])
                        <span class="w-2 h-2 rounded-full bg-blue-500 mt-2"></span>
                    @endif
                </div>
            @empty
                <div class="p-8">
                    <x-ws.empty-state title="Chưa có thông báo nào cho lớp này" description="Thông báo về bài mới mở, lịch đổi hoặc thông báo từ giáo viên của lớp này sẽ hiện ở đây." />
                </div>
            @endforelse
        </div>
    @elseif ($tab === 'members')
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                <h3 class="font-medium text-slate-700 mb-3">Giáo viên</h3>
                <div class="space-y-2.5">
                    @foreach ($teachers as $t)
                        <div class="flex items-center gap-3">
                            <x-ws.avatar :name="$t->name" size="sm" />
                            <p class="text-[13px] text-slate-600">{{ $t->name }} <span class="text-xs text-slate-400">({{ $t->pivot->role ?? 'main' }})</span></p>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                <h3 class="font-medium text-slate-700 mb-3">Học sinh ({{ $students->count() }})</h3>
                <div class="space-y-2.5 max-h-64 overflow-y-auto">
                    @foreach ($students as $s)
                        <div class="flex items-center gap-3">
                            <x-ws.avatar :name="$s->name" size="sm" />
                            <p class="text-[13px] text-slate-600">{{ $s->name }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        {{-- ═══════════ MÀN LỚP HỌC (bản mẫu ClassroomPage.jsx) ═══════════
             Xoay quanh MỘT buổi học: chọn buổi ở ô "Đang xem" -> dải hoạt động của buổi đó ->
             nội dung & học liệu của hoạt động đang chọn.
             Chỉ hiện hoạt động giáo viên ĐÃ BẤM PHÁT — luật cũ, không đổi. --}}
        @if ($lesson === null)
            <div class="classroom-side-card rounded-2xl border bg-white p-10 text-center">
                <x-lucide name="calendar-days" class="mx-auto h-9 w-9 text-[#9DC8D7]" />
                <p class="mt-3 text-[14px] font-semibold text-[#123B68]">Lớp chưa có buổi học nào</p>
                <p class="mt-1 text-[13px] text-[#61798B]">Giáo viên xếp lịch buổi đầu tiên là màn học tập hiện ra ngay ở đây.</p>
            </div>
        @else
            <div x-data="{
                    menu: false,
                    notice: false,
                    act: {{ $actInitial }},
                    tab: 'exercise',
                    counts: {{ Js::from($actCounts) }},
                    pickTab() {
                        const c = this.counts[this.act] || {};
                        if ((c[this.tab] || 0) > 0) return;
                        this.tab = ['exercise', 'document', 'material'].find((t) => (c[t] || 0) > 0) || 'exercise';
                    },
                    setAct(i) { this.act = i; this.pickTab(); },
                 }" x-init="pickTab()" class="space-y-4">

                {{-- ── CHỌN BUỔI HỌC ── --}}
                <section class="classroom-lesson-switcher-card relative z-20 flex flex-wrap items-center justify-between gap-3 rounded-xl border p-3">
                    <div class="min-w-0">
                        <p class="text-[13px] font-semibold text-[#123B68]">Chọn buổi học</p>
                        <p class="mt-0.5 text-[11px] text-[#61798B]">Mặc định hiển thị buổi được tổ chức gần nhất.</p>
                    </div>

                    <div class="flex w-full items-center justify-end gap-2 sm:w-auto">
                        @if (count($classNotifications) > 0)
                            <button type="button" @click="notice = ! notice" :aria-expanded="notice"
                                    class="classroom-teacher-notice-trigger relative grid h-10 w-10 shrink-0 place-items-center rounded-lg"
                                    aria-label="Mở thông báo của lớp" title="Thông báo của lớp">
                                <x-lucide name="bell" class="h-4 w-4" />
                                @if ($unreadClassNotifications > 0)
                                    <span class="classroom-teacher-notice-badge absolute right-1 top-1 grid h-3.5 min-w-3.5 place-items-center rounded-full px-0.5 text-[9px] font-bold">{{ $unreadClassNotifications }}</span>
                                @endif
                            </button>
                        @endif

                        <div class="classroom-lesson-selector relative min-w-0 flex-1 sm:min-w-[280px] sm:flex-none">
                            <button type="button" @click="menu = ! menu" :aria-expanded="menu" aria-haspopup="listbox"
                                    class="classroom-lesson-selector-trigger flex w-full items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-left">
                                <span class="classroom-lesson-selector-icon grid h-7 w-7 shrink-0 place-items-center rounded-md"><x-lucide name="calendar-days" class="h-3.5 w-3.5" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-[10px] font-medium uppercase tracking-[0.06em] text-[#61798B]">Đang xem</span>
                                    <span class="block truncate text-[13px] font-semibold text-[#123B68]">Buổi {{ $lesson['number'] }} · {{ $lesson['title'] }}</span>
                                </span>
                                <x-lucide name="chevron-down" class="h-4 w-4 shrink-0 text-[#61798B] transition-transform" ::class="menu ? 'rotate-180' : ''" />
                            </button>

                            <div x-show="menu" x-cloak @click.outside="menu = false" @keydown.escape.window="menu = false"
                                 class="classroom-lesson-menu absolute right-0 top-[calc(100%+8px)] z-50 max-h-[60vh] w-[360px] max-w-[calc(100vw-32px)] overflow-y-auto rounded-2xl p-1.5"
                                 role="listbox" aria-label="Danh sách buổi học">
                                <div class="px-2.5 pb-1.5 pt-1 text-[11px] font-semibold uppercase tracking-[0.07em] text-[#6F8798]">Các buổi đã tổ chức</div>
                                @foreach ($lessons as $item)
                                    <a href="{{ route('student.classes.show', ['class' => $classRoom->id, 'buoi' => $item['id']]) }}"
                                       role="option" aria-selected="{{ $item['isSelected'] ? 'true' : 'false' }}"
                                       class="classroom-lesson-option flex w-full items-center gap-3 rounded-xl px-2.5 py-2.5 text-left {{ $item['isSelected'] ? 'is-active' : '' }}">
                                        <span class="classroom-lesson-number grid h-9 w-9 shrink-0 place-items-center rounded-xl text-[13px] font-semibold">{{ $item['number'] }}</span>
                                        <span class="min-w-0 flex-1">
                                            <span class="flex items-center gap-2">
                                                <span class="truncate text-[14px] font-semibold text-[#123B68]">Buổi {{ $item['number'] }} · {{ $item['title'] }}</span>
                                                @if ($item['isLatest'])
                                                    <span class="shrink-0 rounded-full bg-emerald-100 px-1.5 py-0.5 text-[11px] font-semibold text-emerald-700">Gần nhất</span>
                                                @endif
                                            </span>
                                            <span class="mt-0.5 block text-[12px] text-[#61798B]">{{ $item['dateLabel'] }} · {{ $item['timeLabel'] }}</span>
                                        </span>
                                        <x-lucide name="check-circle-2" class="h-4 w-4 shrink-0 {{ $item['isSelected'] ? 'text-[#2F8F6F]' : 'text-transparent' }}" />
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </section>

                {{-- ── THÔNG BÁO CỦA LỚP ── --}}
                @if (count($classNotifications) > 0)
                    <section x-show="notice" x-cloak class="classroom-teacher-notice rounded-xl border p-3" role="status">
                        <div class="flex items-start gap-2.5">
                            <span class="classroom-teacher-notice-icon grid h-8 w-8 shrink-0 place-items-center rounded-lg"><x-lucide name="bell" class="h-4 w-4" /></span>
                            <div class="min-w-0 flex-1 space-y-2.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-[13px] font-semibold text-[#123B68]">Thông báo của lớp</h2>
                                    @if ($unreadClassNotifications > 0)
                                        <span class="classroom-teacher-notice-new rounded-full px-1.5 py-0.5 text-[10px] font-bold">Mới</span>
                                    @endif
                                </div>
                                @foreach (array_slice($classNotifications, 0, 3) as $n)
                                    <div>
                                        <p class="text-[12px] leading-5 text-[#466278]">{{ $n['text'] }}</p>
                                        <p class="mt-1 text-[10px] text-[#6F8798]">{{ $n['time'] }}</p>
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" @click="notice = false" class="classroom-teacher-notice-dismiss shrink-0 rounded-md px-2 py-1 text-[11px] font-semibold">Đã đọc</button>
                        </div>
                    </section>
                @endif

                {{-- ── BUỔI HỌC + DẢI HOẠT ĐỘNG ── --}}
                <section class="classroom-session-activity-card relative overflow-hidden rounded-2xl border bg-[#F8FBFC] p-3"
                         style="background-image: linear-gradient(135deg, rgba(248,250,251,0.76), rgba(255,255,255,0.89)), url('{{ $coverUrl }}'); background-size: cover; background-position: center;">
                    <div class="pointer-events-none absolute inset-0 bg-white/10" aria-hidden="true"></div>
                    <div class="relative z-10">
                        <div class="mb-3 flex flex-wrap items-end justify-between gap-2.5">
                            <div class="min-w-0">
                                <h2 class="text-[16px] font-semibold leading-6 text-[#123B68]">Buổi {{ $lesson['number'] }} · {{ $lesson['title'] }}</h2>
                                <p class="mt-0.5 text-[12px] text-[#61798B]">{{ $lesson['dateLabel'] }} · {{ $lesson['timeLabel'] }}</p>
                            </div>
                            @if (count($acts) > 0)
                                <div class="classroom-inline-progress inline-flex items-center gap-2 rounded-full border px-2.5 py-1.5"
                                     aria-label="Đã tổ chức {{ $doneActs }} trên {{ count($acts) }} hoạt động">
                                    <span class="text-[11px] font-medium text-[#5E7B6E]">Tiến độ</span>
                                    <span class="h-1.5 w-16 overflow-hidden rounded-full bg-white"><span class="block h-full rounded-full bg-[#2F9B78]" style="width: {{ $actPercent }}%"></span></span>
                                    <span class="text-[12px] font-semibold text-[#2F8A6B]">{{ $doneActs }}/{{ count($acts) }}</span>
                                </div>
                            @endif
                        </div>

                        @if (count($acts) === 0)
                            <div class="rounded-xl border border-dashed border-[#D7E4EA] bg-[#F8FAFB] px-4 py-8 text-center text-[13px] text-[#61798B]">
                                Buổi này chưa có hoạt động nào được phát. Khi thầy cô bấm phát, hoạt động sẽ hiện ngay ở đây.
                            </div>
                        @else
                            <div class="classroom-activity-timeline">
                                @foreach ($acts as $idx => $a)
                                    <button type="button" @click="setAct({{ $idx }})" data-state="{{ $a['state'] }}"
                                            :aria-pressed="act === {{ $idx }}"
                                            @if ($a['state'] === 'current') aria-current="step" @endif
                                            class="classroom-lesson-activity relative flex min-w-0 flex-col rounded-xl border p-3 text-left">
                                        <div class="flex items-start justify-between gap-2">
                                            <span class="classroom-activity-icon grid h-8 w-8 shrink-0 place-items-center rounded-lg">
                                                <x-lucide :name="count($a['items']['exercise']) > 0 ? 'list-checks' : 'book-open'" class="h-4 w-4" />
                                            </span>
                                            <span class="classroom-activity-status rounded-full border px-2 py-1 text-[11px] font-semibold {{ $a['state'] === 'completed' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($a['state'] === 'current' ? 'border-sky-200 bg-sky-50 text-[#126F91]' : 'border-slate-200 bg-slate-50 text-slate-500') }}">{{ $a['statusLabel'] }}</span>
                                        </div>
                                        <div class="mt-2 flex items-center justify-between gap-2">
                                            <span class="classroom-activity-sequence">Hoạt động {{ $a['index'] }}</span>
                                            <span class="type-meta shrink-0">{{ $a['timeLabel'] }}</span>
                                        </div>
                                        <h3 class="mt-1.5 line-clamp-2 text-[14px] font-semibold leading-5">{{ $a['title'] }}</h3>
                                        @if ($a['note'])
                                            <p class="mt-1 line-clamp-2 text-[13px] leading-[1.55]">{{ $a['note'] }}</p>
                                        @endif
                                        <span x-show="act === {{ $idx }}" x-cloak
                                              class="classroom-activity-viewing mt-auto inline-flex items-center justify-center gap-1.5 rounded-lg px-2 py-1.5 text-[12px] font-semibold">
                                            <x-lucide name="check-circle-2" class="h-3.5 w-3.5" />Đang xem
                                        </span>
                                    </button>
                                @endforeach
                            </div>

                            {{-- ── NỘI DUNG & HỌC LIỆU ── --}}
                            <div class="classroom-activity-content mt-3 border-t border-[#E2EDF2] pt-3">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <h3 class="text-[14px] font-semibold text-[#123B68]">Nội dung &amp; học liệu</h3>
                                    <span class="text-[12px] text-[#61798B]"><span x-text="(counts[act].exercise + counts[act].document + counts[act].material)">0</span> mục</span>
                                </div>

                                <div class="classroom-content-tabs mt-3 flex items-center gap-1 rounded-xl border p-1" role="tablist" aria-label="Phân loại nội dung hoạt động">
                                    @foreach ($contentTabs as [$tabKey, $tabLabel, $tabIcon])
                                        <button type="button" role="tab" @click="tab = '{{ $tabKey }}'"
                                                :aria-selected="tab === '{{ $tabKey }}'" :disabled="counts[act].{{ $tabKey }} === 0"
                                                class="classroom-content-tab inline-flex min-w-0 flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-[13px] font-medium"
                                                :class="tab === '{{ $tabKey }}' ? 'is-active' : ''">
                                            <x-lucide :name="$tabIcon" class="h-3.5 w-3.5" /><span>{{ $tabLabel }}</span>
                                            <span class="classroom-tab-count rounded-full px-1.5 py-0.5 text-[11px]" x-text="counts[act].{{ $tabKey }}">0</span>
                                        </button>
                                    @endforeach
                                </div>

                                <div class="mt-3" role="tabpanel">
                                    @foreach ($acts as $idx => $a)
                                        {{-- BÀI TẬP --}}
                                        <div x-show="act === {{ $idx }} && tab === 'exercise'" x-cloak class="classroom-exercise-list grid gap-2.5 sm:grid-cols-2">
                                            @foreach ($a['items']['exercise'] as $item)
                                                @php $locked = empty($item['assessmentId']); @endphp
                                                <article class="classroom-exercise-item rounded-xl border p-3 {{ $locked ? 'is-locked' : '' }}">
                                                    <div class="flex items-start justify-between gap-2">
                                                        <span class="classroom-problem-code rounded-md px-2 py-1 font-mono text-[11px] font-semibold">{{ $item['code'] ?: $item['typeLabel'] }}</span>
                                                        <span class="rounded-full px-2 py-1 text-[11px] font-semibold {{ $locked ? 'bg-slate-100 text-slate-500' : 'bg-emerald-50 text-emerald-700' }}">{{ $locked ? 'Chưa mở' : 'Đang mở' }}</span>
                                                    </div>
                                                    <h4 class="mt-2 text-[14px] font-semibold leading-5 text-[#123B68]">{{ $item['title'] }}</h4>
                                                    <p class="mt-0.5 text-[13px] leading-5 text-[#61798B]">{{ $item['note'] ?: $item['typeLabel'] }}</p>
                                                    @if ($item['scoreLabel'])
                                                        {{-- Điểm THẬT của chính em này ở đề đó (chỉ hiện khi đã nộp bài). --}}
                                                        <span class="classroom-exercise-score {{ $item['scoreTone'] }} mt-2 inline-flex items-center rounded-lg px-2 py-1 text-[11px] font-bold">{{ $item['scoreLabel'] }}</span>
                                                    @endif
                                                    @if ($locked)
                                                        <span class="mt-2.5 inline-flex w-full cursor-not-allowed items-center justify-center gap-1 rounded-lg px-3 py-2 text-[13px] font-semibold">Chưa mở</span>
                                                    @else
                                                        {{-- Giữ NGUYÊN đường đi cũ của nút làm bài (student.assessment.take). --}}
                                                        <a href="{{ route('student.assessment.take', $item['assessmentId']) }}"
                                                           class="mt-2.5 inline-flex w-full items-center justify-center gap-1 rounded-lg px-3 py-2 text-[13px] font-semibold">
                                                            {{ $item['scoreLabel'] ? 'Xem kết quả' : 'Làm bài' }}<x-lucide name="chevron-right" class="h-3.5 w-3.5" />
                                                        </a>
                                                    @endif
                                                </article>
                                            @endforeach
                                        </div>

                                        {{-- TÀI LIỆU + HỌC LIỆU: cùng kiểu danh sách của bản mẫu --}}
                                        @foreach (['document', 'material'] as $groupKey)
                                            <div x-show="act === {{ $idx }} && tab === '{{ $groupKey }}'" x-cloak class="classroom-media-list space-y-2">
                                                @foreach ($a['items'][$groupKey] as $item)
                                                    @php $hasLink = ! empty($item['url']); @endphp
                                                    <article class="classroom-media-item flex items-center gap-3 rounded-xl border p-2.5 {{ $hasLink || $groupKey === 'document' ? '' : 'is-locked' }}">
                                                        <span class="grid h-14 w-[72px] shrink-0 place-items-center rounded-lg bg-[#EAF5F8] text-[#2D7FA3]">
                                                            <x-lucide :name="$groupKey === 'material' ? 'play-circle' : 'file-text'" class="h-5 w-5" />
                                                        </span>
                                                        <div class="min-w-0 flex-1">
                                                            <span class="classroom-media-label inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold">{{ $item['typeLabel'] }}</span>
                                                            <h4 class="mt-1 truncate text-[14px] font-semibold text-[#123B68]" title="{{ $item['title'] }}">{{ $item['title'] }}</h4>
                                                            @if ($item['note'])
                                                                <p class="mt-0.5 truncate text-[13px] text-[#61798B]">{{ $item['note'] }}</p>
                                                            @endif
                                                        </div>
                                                        @if ($hasLink)
                                                            <a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer"
                                                               class="inline-flex shrink-0 items-center gap-1 rounded-lg px-3 py-2 text-[13px] font-semibold">
                                                                Xem<x-lucide name="external-link" class="h-3.5 w-3.5" />
                                                            </a>
                                                        @else
                                                            <span class="inline-flex shrink-0 items-center gap-1 rounded-lg px-3 py-2 text-[13px] font-semibold text-[#61798B]">Xem tại lớp</span>
                                                        @endif
                                                    </article>
                                                @endforeach
                                            </div>
                                        @endforeach
                                    @endforeach

                                    <div x-show="(counts[act].exercise + counts[act].document + counts[act].material) === 0" x-cloak
                                         class="rounded-xl border border-dashed border-[#D7E4EA] bg-[#F8FAFB] px-4 py-6 text-center text-[13px] text-[#61798B]">
                                        Hoạt động này chưa gắn nội dung nào.
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </section>
            </div>
        @endif
        @endif
        </section>
    </main>
</div>
@endsection
