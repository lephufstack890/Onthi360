@extends('layouts.teacher')

@section('title', 'Tổng quan')
@section('page-title', 'Tổng quan')

@section('content')
    {{--
      SỬA 24/9 (khách: "trong giáo viên xây lại trang tổng quan cho giống UI source mới, logic
      vẫn giữ nguyên").

      DỰNG LẠI THEO education-main/src/components/RoleWorkspace.jsx — hàm TeacherOverview() cùng
      Stat()/CardTitle()/Progress(). Bám đúng thứ tự khối của bản mẫu:

        1. Thẻ "Tổng quan giảng dạy" nền #F7FBFD, con số to bên phải
        2. Dải 3 thẻ số liệu (Stat)
        3. Lưới [1.45fr 1fr]: trái "Bài cần mở tiến độ", phải "Việc cần xử lý"
        4. Dải ngang "Lịch giảng dạy sắp tới", 3 cột

      LOGIC KHÔNG ĐỔI: vẫn đúng các khoá của Teacher\DashboardService::buildFor() (upcoming,
      toOpen, attentionStudents, accessExpiring) và isTeacherApproved(). Không thêm truy vấn nào
      ở view, không đổi một câu truy vấn nào ở service.

      MỘT CHỖ CỐ Ý LÀM KHÁC BẢN MẪU: thẻ đầu của bản mẫu có thanh tiến độ "86% học viên theo kịp
      lộ trình". Hệ thống mình CHƯA tính được con số đó (attentionStudents vẫn đang là mảng rỗng
      — xem TODO trong DashboardService), nên bỏ thanh đó đi thay vì vẽ một phần trăm bịa. Muốn
      có thì phải thêm truy vấn thật, mà lần này khách dặn giữ nguyên logic.
    --}}
    @php
        $name = $name ?? (auth()->user()->name ?? 'thầy/cô');
        $upcoming = $upcoming ?? [];
        $toOpen = $toOpen ?? [];
        $attentionStudents = $attentionStudents ?? [];
        $accessExpiring = $accessExpiring ?? null;
        $isTeacherApproved = auth()->user()->isTeacherApproved();

        $upcomingCount = count($upcoming);
        $toOpenCount = count($toOpen);
        $attentionCount = count($attentionStudents);

        // Stat() của bản mẫu: 4 bộ màu blue / emerald / amber / violet.
        $statTones = [
            'blue' => 'bg-blue-50 text-blue-600 border-blue-100',
            'emerald' => 'bg-emerald-50 text-emerald-600 border-emerald-100',
            'amber' => 'bg-amber-50 text-amber-600 border-amber-100',
            'violet' => 'bg-violet-50 text-violet-600 border-violet-100',
        ];

        $stats = [
            ['Buổi dạy sắp tới', $upcomingCount, $upcomingCount > 0 ? 'Theo lịch các lớp bạn phụ trách' : 'Chưa có buổi nào được xếp lịch', 'calendar-days', 'blue'],
            ['Bài chờ mở tiến độ', $toOpenCount, $toOpenCount > 0 ? 'Cần bạn mở cho học sinh làm' : 'Không có bài nào đang chờ', 'clipboard-list', 'amber'],
            ['Học sinh cần chú ý', $attentionCount, $attentionCount > 0 ? 'Đang chậm tiến độ' : 'Chưa có ai cần theo dõi thêm', 'users', 'emerald'],
        ];

        // Ảnh bìa xoay vòng theo vị trí để mỗi bài luôn nhận đúng một ảnh.
        $covers = ['course-img-1.png', 'course-img-2.png', 'course-img-3.png', 'course-img-4.png', 'course-img-5.png'];

        // "Việc cần xử lý" — gom từ dữ liệu THẬT đang có, không bịa thêm mục nào.
        $todos = [];
        if (! $isTeacherApproved) {
            $todos[] = ['Hồ sơ chờ duyệt', 'Được duyệt rồi mới tạo lớp và giao bài thật được.', 'Xem hồ sơ', route('teacher.profile.show'), 'bg-amber-50 text-amber-700', 'clock'];
        }
        if ($accessExpiring) {
            $todos[] = ['Quyền dạy sắp hết hạn', '"'.$accessExpiring['product'].'" — còn '.$accessExpiring['daysLeft'].' ngày.', 'Xem quyền', route('access.myAccess'), 'bg-amber-50 text-amber-700', 'clock'];
        }
        if ($toOpenCount > 0) {
            $todos[] = ['Mở bài cho lớp', $toOpenCount.' bài đang ở trạng thái nháp hoặc hẹn giờ.', 'Giao đề', route('teacher.assessments.index'), 'bg-blue-50 text-blue-700', 'clipboard-list'];
        }
        if ($attentionCount > 0) {
            $todos[] = ['Nhắc học sinh', $attentionCount.' học sinh đang chậm tiến độ.', 'Lớp học', route('teacher.classes.index'), 'bg-emerald-50 text-emerald-700', 'users'];
        }

        // Đếm SAU KHI dựng xong danh sách, không cộng tay từng loại: cộng tay thì quên một
        // loại là con số to trên thẻ nói 2 việc trong khi cột bên phải liệt kê 3 — đúng kiểu
        // lệch nhỏ mà người dùng nhìn phát ra ngay còn mình thì không.
        $todoCount = count($todos);
    @endphp

    @if (session('warning'))
        @include('partials.toast-flash', ['type' => 'warning', 'message' => session('warning')])
    @endif

    <div class="teacher-dashboard space-y-4">

        @unless ($isTeacherApproved)
            <div class="flex flex-wrap items-center gap-4 rounded-2xl border border-amber-100 bg-amber-50 p-4">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-amber-100 text-amber-700"><x-lucide name="clock" class="h-5 w-5" /></span>
                <p class="flex-1 text-[13px] text-amber-800">
                    Hồ sơ giáo viên của bạn đang <strong>chờ Admin duyệt</strong> (3.3). Sau khi được duyệt, bạn mới có thể
                    tạo lớp học và giao bài kiểm tra thật cho học sinh — trong lúc chờ, bạn vẫn có thể chuẩn bị câu hỏi/đề.
                </p>
            </div>
        @endunless

        {{-- ══════ 1. TỔNG QUAN GIẢNG DẠY ══════ --}}
        <section class="teacher-dashboard-summary rounded-3xl border border-sky-100 p-4 sm:p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-[.12em] text-blue-600">Tổng quan giảng dạy</p>
                    <h2 class="mt-1 text-base font-bold text-slate-800">
                        Chào {{ $name }}, {{ $upcomingCount > 0 ? 'có '.$upcomingCount.' buổi dạy sắp tới' : 'chưa có buổi dạy nào sắp tới' }}
                    </h2>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $toOpenCount }} bài chờ mở tiến độ · {{ $attentionCount }} học sinh cần chú ý
                    </p>
                </div>
                <div class="shrink-0 text-right">
                    <p class="text-[11px] font-bold text-slate-400">Việc cần xử lý</p>
                    <strong class="mt-1 block text-2xl font-black text-blue-700">{{ $todoCount }}</strong>
                </div>
            </div>

            <div class="mt-3 flex flex-wrap justify-between gap-2 border-t border-white pt-3 text-[11px] font-medium text-slate-500">
                <span>{{ $upcomingCount > 0 ? 'Buổi gần nhất: '.($upcoming[0]['time'] ?? '—') : 'Lịch dạy đang trống' }}</span>
                <span>{{ $todoCount > 0 ? 'Xem danh sách việc ở cột bên phải' : 'Không còn việc nào tồn đọng' }}</span>
            </div>
        </section>

        {{-- ══════ 2. DẢI THẺ SỐ LIỆU ══════ --}}
        <div class="grid gap-3 sm:grid-cols-3">
            @foreach ($stats as [$label, $value, $note, $icon, $tone])
                <div class="rounded-2xl border border-sky-100 bg-white p-3.5 shadow-[0_2px_8px_rgba(0,90,180,.04)]">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">{{ $label }}</p>
                            <p class="mt-1 text-xl font-black text-slate-800">{{ $value }}</p>
                        </div>
                        <span class="rounded-xl border p-2 {{ $statTones[$tone] }}"><x-lucide :name="$icon" class="h-4 w-4" /></span>
                    </div>
                    <p class="mt-2 text-[11px] font-medium text-slate-500">{{ $note }}</p>
                </div>
            @endforeach
        </div>

        {{-- ══════ 3. HAI CỘT ══════ --}}
        <div class="teacher-dashboard-grid">

            <section class="min-w-0 rounded-3xl border border-sky-100 bg-white p-4 sm:p-5">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="rounded-lg bg-blue-50 p-1.5 text-blue-600"><x-lucide name="clipboard-list" class="h-3.5 w-3.5" /></span>
                        <h2 class="text-sm font-bold text-slate-800">Bài cần mở tiến độ</h2>
                    </div>
                    <a href="{{ route('teacher.assessments.index') }}" class="text-xs font-bold text-blue-600 hover:underline">Quản lý đề <x-lucide name="chevron-right" class="inline h-3 w-3" /></a>
                </div>

                @if ($toOpenCount > 0)
                    <div class="teacher-dashboard-list divide-y divide-slate-100">
                        @foreach ($toOpen as $i => $t)
                            <article class="flex items-center gap-3 py-3">
                                <img src="{{ asset('assets/'.$covers[$i % count($covers)]) }}" alt="" decoding="async"
                                     class="h-12 w-14 shrink-0 rounded-xl object-cover">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-xs font-bold text-slate-800">{{ $t['title'] }}</p>
                                    <p class="mt-1 truncate text-[11px] text-slate-500">{{ $t['class'] }}{{ $t['chapter'] ? ' · '.$t['chapter'] : '' }}</p>
                                </div>
                                <a href="{{ route('teacher.assessments.index') }}" class="shrink-0 text-[11px] font-bold text-blue-600 hover:underline">Mở ngay</a>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-2xl bg-slate-50 px-4 py-8 text-center">
                        <span class="mx-auto grid h-10 w-10 place-items-center rounded-xl bg-white text-emerald-600 shadow-sm"><x-lucide name="check-circle" class="h-5 w-5" /></span>
                        <p class="mt-2 text-xs font-bold text-slate-700">Không có bài nào đang chờ mở</p>
                        <p class="mt-1 text-[11px] text-slate-500">Tạo đề mới rồi giao cho lớp ở mục Bài tập &amp; Đề.</p>
                    </div>
                @endif
            </section>

            <aside class="min-w-0 rounded-3xl border border-sky-100 bg-white p-4 sm:p-5">
                <div class="mb-3 flex items-center gap-2">
                    <span class="rounded-lg bg-blue-50 p-1.5 text-blue-600"><x-lucide name="clock" class="h-3.5 w-3.5" /></span>
                    <h2 class="text-sm font-bold text-slate-800">Việc cần xử lý</h2>
                </div>

                @if (count($todos) > 0)
                    <div class="teacher-dashboard-list divide-y divide-slate-100">
                        @foreach ($todos as [$title, $note, $actionLabel, $actionHref, $tone, $icon])
                            <div class="flex items-center gap-3 py-3">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl {{ $tone }}"><x-lucide :name="$icon" class="h-4 w-4" /></span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-bold text-slate-800">{{ $title }}</p>
                                    <p class="mt-1 text-[11px] text-slate-500">{{ $note }}</p>
                                </div>
                                <a href="{{ $actionHref }}" class="shrink-0 text-[11px] font-bold text-blue-600 hover:underline">{{ $actionLabel }}</a>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-2xl bg-slate-50 px-4 py-8 text-center">
                        <span class="mx-auto grid h-10 w-10 place-items-center rounded-xl bg-white text-emerald-600 shadow-sm"><x-lucide name="check-circle" class="h-5 w-5" /></span>
                        <p class="mt-2 text-xs font-bold text-slate-700">Không còn việc nào tồn đọng</p>
                        <p class="mt-1 text-[11px] text-slate-500">Mọi thứ đang gọn gàng.</p>
                    </div>
                @endif
            </aside>
        </div>

        {{-- ══════ 4. LỊCH GIẢNG DẠY SẮP TỚI ══════ --}}
        <section class="rounded-3xl border border-sky-100 bg-white p-4 sm:p-5">
            <div class="mb-3 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="rounded-lg bg-blue-50 p-1.5 text-blue-600"><x-lucide name="calendar-days" class="h-3.5 w-3.5" /></span>
                    <h2 class="text-sm font-bold text-slate-800">Lịch giảng dạy sắp tới</h2>
                </div>
                <a href="{{ route('teacher.schedule.index') }}" class="text-xs font-bold text-blue-600 hover:underline">Mở lịch <x-lucide name="chevron-right" class="inline h-3 w-3" /></a>
            </div>

            @if ($upcomingCount > 0)
                <div class="grid gap-2 md:grid-cols-3">
                    @foreach ($upcoming as $u)
                        @php($parts = explode(' ', (string) ($u['time'] ?? ''), 2))
                        <article class="flex items-center gap-3 rounded-2xl border border-slate-100 bg-[#F8FAFB] p-3">
                            <div class="w-12 shrink-0 rounded-xl bg-sky-50 px-1.5 py-1.5 text-center text-blue-700">
                                <p class="text-[10px] font-bold">{{ $parts[0] ?? '—' }}</p>
                                <p class="mt-0.5 text-xs font-black">{{ $parts[1] ?? '—' }}</p>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-xs font-bold text-slate-800">{{ $u['topic'] !== '' ? $u['topic'] : 'Buổi học' }}</p>
                                <p class="mt-1 truncate text-[11px] text-slate-500">{{ $u['class'] }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="rounded-2xl bg-slate-50 px-4 py-8 text-center">
                    <span class="mx-auto grid h-10 w-10 place-items-center rounded-xl bg-white text-[#126F91] shadow-sm"><x-lucide name="calendar-days" class="h-5 w-5" /></span>
                    <p class="mt-2 text-xs font-bold text-slate-700">Chưa có buổi dạy nào sắp tới</p>
                    <p class="mt-1 text-[11px] text-slate-500">Xếp lịch cho lớp ở mục Lịch dạy.</p>
                </div>
            @endif
        </section>
    </div>
@endsection

@push('scripts')
    {{-- Nền riêng của thẻ tổng quan — chép từ education-main/src/index.css
         (.teacher-dashboard-summary). Mấy tiện ích Tailwind bản mẫu dùng mà bản CSS build sẵn
         trên máy chủ chưa có cũng viết tay luôn ở đây. --}}
    <style>
        .teacher-dashboard-summary { background-color: #F7FBFD; }

        .teacher-dashboard-grid { display: grid; gap: 1rem; }
        @media (min-width: 1280px) {
            .teacher-dashboard-grid { grid-template-columns: 1.45fr 1fr; }
        }

        .teacher-dashboard-list > :first-child { padding-top: .25rem; }
        .teacher-dashboard-list > :last-child { padding-bottom: .25rem; }
    </style>
@endpush
