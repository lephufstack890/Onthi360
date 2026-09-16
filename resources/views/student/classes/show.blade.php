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

        // Bản mẫu có nút "Mở Meet lớp học" trỏ vào link cứng. Hệ thống KHÔNG có cột link phòng
        // học riêng, nhưng class_sessions.location vốn được định nghĩa là "phòng học HOẶC link
        // online" — nên buổi học kế tiếp có link http thì đó chính là phòng trực tuyến thật.
        $meetUrl = ($nextSession && filled($nextSession->location) && \Illuminate\Support\Str::startsWith($nextSession->location, ['http://', 'https://']))
            ? $nextSession->location
            : null;
        $meetRoomNote = ($nextSession && filled($nextSession->location) && $meetUrl === null) ? $nextSession->location : null;
    @endphp

    {{-- ═══════════ THẺ ĐẦU TRANG (header của bản mẫu) ═══════════ --}}
    <div class="mb-3.5 rounded-2xl border border-sky-100 bg-white px-3.5 py-3 shadow-[0_3px_14px_rgba(31,103,138,0.04)]">
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('student.courses.index') }}"
               class="inline-flex items-center gap-1.5 rounded-xl border border-[#DDEAF0] bg-white px-3 py-2 text-[13px] font-extrabold text-[#536D86] transition hover:border-[#9DC8D7] hover:bg-[#F3FAFC] hover:text-[#126F91]">
                <x-lucide name="arrow-left" class="h-4 w-4" />Danh sách lớp
            </a>
            <div class="hidden h-8 w-px bg-slate-200 sm:block"></div>

            <div class="flex min-w-0 flex-1 items-center gap-3">
                <img src="{{ $coverUrl }}" alt="" class="h-12 w-12 shrink-0 rounded-2xl border border-sky-100 object-cover shadow-sm">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[12px] font-black text-[#126F91]">{{ $classRoom->code }}</span>
                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-extrabold {{ $statusChip }}">
                            <x-lucide name="radio" class="h-3 w-3" />{{ $statusLabel }}
                        </span>
                    </div>
                    <h1 class="truncate text-[16px] font-black text-[#123B68]">{{ $className }}</h1>
                    <p class="truncate text-[13px] text-[#71869A]">{{ $courseTitle }}{{ $courseTitle ? ' · ' : '' }}{{ $teacherLabel }}</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                {{-- Điểm đánh giá lớp: không có trong bản mẫu nhưng là thông tin THẬT bản cũ đã
                     hiện, bỏ đi là mất dữ liệu — giữ lại ở dạng chip cho hợp bố cục mới. --}}
                <span class="hidden items-center rounded-xl bg-[#F4F9FC] px-3 py-2 sm:inline-flex">
                    <x-rating-summary :average="$ratingAverage" :count="$ratingCount" />
                </span>
                <span class="hidden items-center gap-2 rounded-xl bg-[#F4F9FC] px-3 py-2 text-[12px] font-bold text-[#536D86] lg:inline-flex">
                    <x-lucide name="calendar-days" class="h-4 w-4 text-[#2D7FA3]" />
                    <span>{{ $scheduleNote ?: $nextSessionLabel }}</span>
                </span>
            </div>
        </div>
    </div>

    <div class="grid gap-3.5 lg:grid-cols-[210px_minmax(0,1fr)] lg:gap-4 {{ $isOverview ? 'xl:grid-cols-[210px_minmax(0,1fr)_250px]' : '' }}">
        {{-- ═══════════ CỘT TRÁI ═══════════ --}}
        <aside class="space-y-3">
            <section class="rounded-2xl border border-sky-100 bg-white p-3.5 shadow-[0_3px_14px_rgba(31,103,138,0.04)]">
                <h2 class="mb-3 flex items-center gap-2 text-[15px] font-black text-[#123B68]">
                    <x-lucide name="book-open" class="h-4 w-4 text-[#2D7FA3]" />Trong lớp học
                </h2>
                <div class="space-y-1.5">
                    @foreach ($tabsData as $navItem)
                        @php $navActive = $navItem['active'] ?? false; @endphp
                        <a href="{{ $navItem['href'] }}"
                           @if ($navActive) aria-current="page" @endif
                           class="flex items-center justify-between gap-2 rounded-2xl px-3 py-2.5 text-[13px] transition {{ $navActive ? 'bg-[#EAF5F8] font-extrabold text-[#126F91]' : 'font-bold text-[#536D86] hover:bg-[#F4F9FC] hover:text-[#126F91]' }}">
                            <span class="flex min-w-0 items-center gap-2">
                                <x-lucide :name="$navIcons[$navItem['label']] ?? 'chevron-right'" class="h-4 w-4 shrink-0" />
                                <span class="truncate">{{ $navItem['label'] }}</span>
                            </span>
                            @if ($navItem['label'] === 'Thông báo' && $unreadNotifications > 0)
                                <span class="shrink-0 rounded-full bg-[#FFF4D6] px-1.5 py-0.5 text-[11px] font-extrabold text-[#9A6B1E]">{{ $unreadNotifications }}</span>
                            @else
                                <x-lucide name="chevron-right" class="h-4 w-4 shrink-0 {{ $navActive ? '' : 'text-[#B9C7D4]' }}" />
                            @endif
                        </a>
                    @endforeach
                </div>
            </section>

            <section class="rounded-2xl border border-sky-100 bg-white p-3.5 shadow-[0_3px_14px_rgba(31,103,138,0.04)]">
                <h2 class="mb-2 flex items-center gap-2 text-[15px] font-black text-[#123B68]">
                    <x-lucide name="calendar-days" class="h-4 w-4 text-[#2D7FA3]" />Lịch học
                </h2>
                <p class="text-[13px] font-extrabold leading-5 text-[#536D86]">{{ $scheduleNote ?: $nextSessionLabel }}</p>
                @if ($classLocation || $classFormat)
                    <p class="mt-1 flex items-center gap-1.5 text-[12px] font-semibold text-[#71869A]">
                        <x-lucide name="clock-3" class="h-3.5 w-3.5 shrink-0" />
                        {{ collect([$classLocation, $classFormat])->filter()->implode(' · ') }}
                    </p>
                @endif
            </section>

            <section class="rounded-2xl border border-[#DDEAF0] bg-gradient-to-br from-[#F0F8FA] to-[#FAFCFF] p-3.5 shadow-[0_3px_14px_rgba(31,103,138,0.04)]">
                <h2 class="mb-2 flex items-center gap-2 text-[15px] font-black text-[#123B68]">
                    <x-lucide name="users" class="h-4 w-4 text-[#2D7FA3]" />Sĩ số
                </h2>
                @if ($studentsCount !== null)
                    <p class="text-[24px] font-black text-[#126F91]">{{ $studentsCount }}@if ($classCapacity)<span class="text-[14px] font-extrabold text-[#71869A]"> / {{ $classCapacity }}</span>@endif</p>
                @elseif ($classCapacity)
                    <p class="text-[24px] font-black text-[#126F91]">{{ $classCapacity }}<span class="text-[14px] font-extrabold text-[#71869A]"> chỗ</span></p>
                    <p class="mt-0.5 text-[11px] font-semibold text-[#9DB2C0]">Sĩ số tối đa · xem danh sách ở tab Thành viên</p>
                @else
                    <p class="text-[13px] font-semibold text-[#71869A]">Xem danh sách ở tab Thành viên</p>
                @endif
                <p class="mt-1 text-[12px] font-semibold text-[#71869A]">Bạn đã tham gia lớp</p>
            </section>
        </aside>

        {{-- ═══════════ CỘT GIỮA ═══════════ --}}
        <section class="min-w-0 space-y-3">
            <section class="rounded-2xl border border-sky-100 bg-white p-3.5 shadow-[0_3px_14px_rgba(31,103,138,0.04)]">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-[12px] font-black uppercase tracking-[0.08em] text-[#2D7FA3]">Không gian học tập</p>
                        <h2 class="mt-1 text-[21px] font-black tracking-tight text-[#123B68]">Tiến trình lớp học</h2>
                        <p class="mt-1 text-[13px] text-[#71869A]">Theo dõi hoạt động giáo viên đã mở và học tiếp từ đúng vị trí.</p>
                    </div>
                    {{-- Bản mẫu in cứng 65%. Đây là tiến độ THẬT: số buổi đã kết thúc / tổng số
                         buổi đã xếp lịch (ClassRoomService::completionPercent()). --}}
                    <div class="min-w-[150px] rounded-2xl bg-[#EAF5F8] px-3 py-2">
                        <div class="flex items-center justify-between text-[12px] font-extrabold text-[#126F91]">
                            <span>Tiến độ lớp</span><span>{{ $overallPercent }}%</span>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-white">
                            <div class="h-full rounded-full bg-[#2D7FA3]" style="width: {{ $overallPercent }}%"></div>
                        </div>
                    </div>
                </div>
            </section>

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
        {{-- ═══════════ TỔNG QUAN — "Hoạt động học tập" + "Bài tập & tài nguyên" của bản mẫu ═══════════
             Bản mẫu có 4 hoạt động viết cứng, mỗi hoạt động kèm danh sách mục con. Nguồn thật
             tương đương ở tab này là $roadmap — bài tập của lớp do giáo viên giao
             (ClassRoomService::buildRoadmap(), đã có sẵn từ trước, KHÔNG sửa). Mỗi bài tập là
             một "hoạt động" trong băng chuyền; khối dưới in đúng mục con của bài tập đang chọn.
             Băng chuyền chỉ là trạng thái hiển thị phía trình duyệt (Alpine), không gọi mạng,
             không đổi route — bấm sang trái/phải không mất dữ liệu nào. --}}
        @if ($roadmapItems->isEmpty())
            <section class="rounded-2xl border border-sky-100 bg-white p-6 shadow-[0_3px_14px_rgba(31,103,138,0.04)]">
                <x-ws.empty-state title="Lớp chưa có bài tập nào" description="Giáo viên chưa giao bài tập cho lớp này. Khi có bài mới, hoạt động sẽ hiện ngay ở đây." />
            </section>
        @else
            <div x-data="{ i: 0, n: {{ $roadmapItems->count() }} }" class="space-y-3">
                <section class="rounded-2xl border border-sky-100 bg-white p-3.5 shadow-[0_3px_14px_rgba(31,103,138,0.04)]">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <div>
                            <h3 class="text-[16px] font-black text-[#123B68]">Hoạt động học tập</h3>
                            <p class="mt-0.5 text-[13px] text-[#71869A]">Chọn một hoạt động để xem bài tập và tài nguyên.</p>
                        </div>
                        <span class="shrink-0 rounded-full bg-[#F4F9FC] px-2.5 py-1 text-[12px] font-extrabold text-[#536D86]"><span x-text="i + 1">1</span>/{{ $roadmapItems->count() }}</span>
                    </div>

                    <div class="relative overflow-hidden rounded-2xl border border-[#C9DFE8] bg-gradient-to-r from-[#F0F8FA] via-white to-[#FFFBF1] p-3.5">
                        @foreach ($roadmapItems as $idx => $item)
                            @php
                                $done = $item['status'] === 'Đã làm';
                                $locked = $item['status'] === 'Giáo viên chưa mở';
                                $openedBy = match ($item['status']) {
                                    'Giáo viên chưa mở' => 'Giáo viên chưa mở hoạt động',
                                    'Đã làm' => 'Đã làm · có thể xem lại',
                                    'Đã mở' => 'Giáo viên đã mở bài tập',
                                    default => 'Đã hết hạn làm bài',
                                };
                                $typeLabel = match ($item['type']) {
                                    'coding' => 'Bài lập trình',
                                    'quiz' => 'Bài trắc nghiệm',
                                    'exam' => 'Đề thi',
                                    default => $item['type'] ?: 'Bài tập',
                                };
                                $itemPercent = $done ? 100 : 0;
                            @endphp
                            <div x-show="i === {{ $idx }}" @if ($idx > 0) x-cloak @endif>
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <span class="inline-flex rounded-full border border-white/70 bg-white/80 px-2.5 py-1 text-[11px] font-extrabold text-[#126F91]">{{ $openedBy }}</span>
                                        <h4 class="mt-2 text-[18px] font-black leading-6 text-[#123B68]">{{ $item['title'] }}</h4>
                                        <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px] font-bold text-[#536D86]">
                                            <span>{{ $typeLabel }}</span>
                                            <span class="text-[#9DB2C0]">·</span>
                                            <span>{{ $done ? 'Điểm: '.$item['result'] : 'Chưa làm' }}</span>
                                        </p>
                                    </div>
                                    <span class="shrink-0 rounded-full border px-2.5 py-1 text-[12px] font-extrabold {{ $chipTone($item['tone']) }}">{{ $item['status'] }}</span>
                                </div>

                                @if (! empty($item['shiftLabel']))
                                    {{-- Chia ca thi chống nghẽn — thông tin THẬT bản cũ đã hiện, giữ nguyên. --}}
                                    <p class="mt-2 flex items-center gap-1.5 text-[12px] font-bold text-amber-700">
                                        <x-lucide name="clock" class="h-3.5 w-3.5 shrink-0" />{{ $item['shiftLabel'] }} (chia ca thi chống nghẽn)
                                    </p>
                                @endif

                                <p class="mt-3 max-w-2xl text-[14px] leading-5 text-[#536D86]">
                                    {{ $locked ? 'Giáo viên chưa mở nội dung này. Khi mở, em sẽ làm được ngay tại đây.' : ($done ? 'Em đã hoàn thành bài này — có thể mở lại để xem lời giải và rút kinh nghiệm.' : 'Bài đang mở — em có thể vào làm ngay bây giờ.') }}
                                </p>

                                <div class="mt-4 flex items-center gap-3">
                                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-white/80">
                                        <div class="h-full rounded-full bg-[#2D7FA3]" style="width: {{ $itemPercent }}%"></div>
                                    </div>
                                    <span class="text-[13px] font-black text-[#126F91]">{{ $itemPercent }}%</span>
                                </div>
                            </div>
                        @endforeach

                        <div class="mt-4 flex items-center justify-between gap-3">
                            <button type="button" x-on:click="i = (i + n - 1) % n" aria-label="Hoạt động trước"
                                    class="grid h-9 w-9 place-items-center rounded-xl border border-white/80 bg-white/80 text-[#126F91] shadow-sm transition hover:bg-white">
                                <x-lucide name="chevron-left" class="h-5 w-5" />
                            </button>
                            <div class="flex flex-wrap items-center justify-center gap-1.5">
                                @foreach ($roadmapItems as $idx => $item)
                                    <button type="button" x-on:click="i = {{ $idx }}" aria-label="Chọn hoạt động {{ $idx + 1 }}"
                                            class="h-2 rounded-full transition-all"
                                            :class="i === {{ $idx }} ? 'w-6 bg-[#126F91]' : 'w-2 bg-[#B9D8E1]'"></button>
                                @endforeach
                            </div>
                            <button type="button" x-on:click="i = (i + 1) % n" aria-label="Hoạt động sau"
                                    class="grid h-9 w-9 place-items-center rounded-xl border border-white/80 bg-white/80 text-[#126F91] shadow-sm transition hover:bg-white">
                                <x-lucide name="chevron-right" class="h-5 w-5" />
                            </button>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-sky-100 bg-white p-3.5 shadow-[0_3px_14px_rgba(31,103,138,0.04)]">
                    @foreach ($roadmapItems as $idx => $item)
                        @php
                            $done = $item['status'] === 'Đã làm';
                            $locked = $item['status'] === 'Giáo viên chưa mở';
                            $openedBy = match ($item['status']) {
                                'Giáo viên chưa mở' => 'Giáo viên chưa mở hoạt động',
                                'Đã làm' => 'Đã làm · có thể xem lại',
                                'Đã mở' => 'Giáo viên đã mở bài tập',
                                default => 'Đã hết hạn làm bài',
                            };
                            $typeLabel = match ($item['type']) {
                                'coding' => 'Bài lập trình',
                                'quiz' => 'Bài trắc nghiệm',
                                'exam' => 'Đề thi',
                                default => $item['type'] ?: 'Bài tập',
                            };
                        @endphp
                        <div x-show="i === {{ $idx }}" @if ($idx > 0) x-cloak @endif>
                            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-3">
                                <div class="min-w-0">
                                    <p class="text-[12px] font-black text-[#2D7FA3]">{{ $openedBy }}</p>
                                    <h3 class="mt-1 text-[18px] font-black text-[#123B68]">{{ $item['title'] }}</h3>
                                </div>
                                <span class="rounded-full border px-2.5 py-1 text-[12px] font-extrabold {{ $chipTone($item['tone']) }}">{{ $item['status'] }}</span>
                            </div>

                            <div class="mt-4 flex items-center justify-between">
                                <h4 class="text-[15px] font-black text-[#123B68]">Bài tập &amp; tài nguyên</h4>
                                <span class="text-[12px] font-bold text-[#71869A]">1 mục</span>
                            </div>

                            <div class="mt-2.5 overflow-hidden rounded-2xl border border-[#E2EDF2]">
                                <div class="flex items-center gap-3 px-3.5 py-3">
                                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#EAF5F8] text-[#2D7FA3]">
                                        <x-lucide :name="$item['type'] === 'coding' ? 'code-2' : 'file-text'" class="h-4 w-4" />
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-[14px] font-extrabold text-[#123B68]">{{ $item['title'] }}</p>
                                        <p class="mt-0.5 truncate text-[13px] text-[#71869A]">{{ $typeLabel }} · {{ $done ? 'Điểm: '.$item['result'] : 'Chưa làm' }}</p>
                                    </div>
                                    @if ($locked)
                                        <span class="inline-flex shrink-0 cursor-not-allowed items-center gap-1 rounded-xl bg-slate-100 px-3 py-2 text-[12px] font-extrabold text-slate-400">
                                            Chưa mở<x-lucide name="lock" class="h-3.5 w-3.5" />
                                        </span>
                                    @else
                                        {{-- Giữ NGUYÊN đích đến cũ: khu Luyện tập (student.practice.index). --}}
                                        <a href="{{ route('student.practice.index') }}"
                                           class="inline-flex shrink-0 items-center gap-1 rounded-xl bg-[#EAF5F8] px-3 py-2 text-[12px] font-extrabold text-[#126F91] transition hover:bg-[#D9EEF3]">
                                            {{ $done ? 'Xem lại' : 'Làm bài' }}<x-lucide name="chevron-right" class="h-3.5 w-3.5" />
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </section>
            </div>
        @endif
        @endif
        </section>

        {{-- ═══════════ CỘT PHẢI (chỉ ở tab Tổng quan — các tab còn lại cần hết bề ngang cho
             bảng lịch / lưới tài liệu / danh sách thành viên) ═══════════ --}}
        @if ($isOverview)
            <aside class="space-y-3 lg:col-span-2 lg:grid lg:grid-cols-2 lg:gap-3 lg:space-y-0 xl:col-span-1 xl:block xl:space-y-3">
                <section class="rounded-2xl border border-[#C9DFE8] bg-gradient-to-br from-[#F0F8FA] to-[#FAFCFF] p-3.5 shadow-[0_3px_14px_rgba(31,103,138,0.04)]">
                    <div class="flex items-center gap-2 text-[#126F91]">
                        <x-lucide name="radio" class="h-4 w-4" />
                        <h2 class="text-[16px] font-black">Vào lớp trực tuyến</h2>
                    </div>
                    <p class="mt-2 text-[13px] leading-5 text-[#536D86]">Phòng học theo lịch của lớp.</p>
                    @if ($meetUrl)
                        {{-- Link THẬT của buổi học kế tiếp (class_sessions.location — cột này từ đầu
                             đã được định nghĩa là "phòng học hoặc link online"). --}}
                        <a href="{{ $meetUrl }}" target="_blank" rel="noopener noreferrer"
                           class="mt-3 flex items-center justify-between rounded-xl bg-[#126F91] px-3.5 py-2.5 text-[13px] font-black text-white transition hover:bg-[#0F607E]">
                            <span class="flex items-center gap-2"><x-lucide name="video" class="h-4 w-4" />Vào phòng học buổi tới</span>
                            <x-lucide name="external-link" class="h-4 w-4" />
                        </a>
                        <p class="mt-2 text-[11px] font-semibold text-[#71869A]">{{ $nextSessionLabel }}</p>
                    @elseif ($meetRoomNote)
                        <div class="mt-3 flex items-center gap-2 rounded-xl bg-white px-3.5 py-2.5 text-[13px] font-bold text-[#536D86]">
                            <x-lucide name="map-pin" class="h-4 w-4 shrink-0 text-[#2D7FA3]" />
                            <span class="min-w-0 truncate" title="{{ $meetRoomNote }}">{{ $meetRoomNote }}</span>
                        </div>
                        <p class="mt-2 text-[11px] font-semibold text-[#71869A]">{{ $nextSessionLabel }}</p>
                    @else
                        <p class="mt-3 rounded-xl bg-white px-3.5 py-2.5 text-[12px] font-semibold text-[#71869A]">
                            Buổi học kế tiếp chưa có phòng học/liên kết. Giáo viên điền vào buổi học là hiện ngay ở đây.
                        </p>
                    @endif
                </section>

                {{-- ẨN 16/9 — "Video bài giảng" của bản mẫu: hệ thống CHƯA có nguồn video ghi hình
                     theo buổi (không bảng, không cột nào lưu). Để nguyên khối ở đây, khi nào có dữ
                     liệu thì mở lại, KHÔNG xoá. In 2 video giả như bản mẫu là bịa dữ liệu.
                <section class="rounded-2xl border border-sky-100 bg-white p-3.5 shadow-[0_3px_14px_rgba(31,103,138,0.04)]">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="flex items-center gap-2 text-[16px] font-black text-[#123B68]">
                            <x-lucide name="play-circle" class="h-4 w-4 text-[#2D7FA3]" />Video bài giảng
                        </h2>
                    </div>
                </section>
                --}}

                <section class="rounded-2xl border border-sky-100 bg-white p-3.5 shadow-[0_3px_14px_rgba(31,103,138,0.04)]">
                    <h2 class="flex items-center gap-2 text-[16px] font-black text-[#123B68]">
                        <x-lucide name="file-text" class="h-4 w-4 text-[#2D7FA3]" />Tài nguyên lớp
                    </h2>
                    <div class="mt-3 space-y-2">
                        {{-- Bản mẫu là 2 ô chữ chết; ở đây trỏ đúng 2 tab có dữ liệu thật. --}}
                        <a href="{{ route('student.classes.show', ['class' => $classRoom->id, 'tab' => 'materials']) }}"
                           class="flex items-center justify-between gap-2 rounded-2xl bg-[#F4F9FC] px-3.5 py-3 text-[13px] font-bold text-[#536D86] transition hover:bg-[#EAF5F8] hover:text-[#126F91]">
                            <span class="flex min-w-0 items-center gap-2"><x-lucide name="book-open" class="h-4 w-4 shrink-0" />Tài liệu lớp học</span>
                            <x-lucide name="chevron-right" class="h-4 w-4 shrink-0" />
                        </a>
                        <a href="{{ route('student.classes.show', ['class' => $classRoom->id, 'tab' => 'schedule']) }}"
                           class="flex items-center justify-between gap-2 rounded-2xl bg-[#F4F9FC] px-3.5 py-3 text-[13px] font-bold text-[#536D86] transition hover:bg-[#EAF5F8] hover:text-[#126F91]">
                            <span class="flex min-w-0 items-center gap-2"><x-lucide name="layers" class="h-4 w-4 shrink-0" />Học liệu theo hoạt động</span>
                            <x-lucide name="chevron-right" class="h-4 w-4 shrink-0" />
                        </a>
                    </div>
                </section>
            </aside>
        @endif
    </div>
@endsection
