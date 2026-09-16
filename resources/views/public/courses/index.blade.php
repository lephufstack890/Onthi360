{{-- SỬA 15/9 — KHÁCH ĐỔI Ý: bỏ cách bán theo LỘ TRÌNH, quay lại đúng cách cũ "khoá học có
     các lớp học". File này đã được trả NGUYÊN VỀ BẢN GỐC (commit 2dd210c, 15/9 10:02) — tức là
     bản trước khi trang này bị đổi thành trình duyệt lộ trình.

     Bản có lộ trình (chip khối lớp đổ từ lộ trình, lưới thẻ lộ trình, chế độ lọc ?lo-trinh=)
     KHÔNG bị xoá, còn nguyên trong commit 7184796 — xem hướng dẫn bật lại đầy đủ ở
     App\Services\Public\LearningPathService::PUBLIC_ENABLED. --}}
@extends('layouts.guest')

@section('title', 'Lớp học Tin học 360')
@section('meta-description', 'Danh sách lớp học Tin học của Ôn Thi 360 — lọc theo khối lớp và khóa học, xem mã lớp, sĩ số, giáo viên phụ trách và đánh giá của học viên.')

@section('content')
{{-- ═══════════════ [COURSES] MÀN LỚP HỌC ═══════════════
     SỬA 11/9 — dựng lại theo ĐÚNG source giao diện khách gửi:
     education-main/src/components/CoursesPage.jsx (COURSES-01 … COURSES-05).
     Bố cục/class chép nguyên; React state đổi sang Alpine; mọi nút gắn link thật.

     Dữ liệu lấy từ cơ sở dữ liệu (App\Services\Public\CourseService::classIndexData):
       · thẻ lớp    <- $classes       (MỖI THẺ LÀ MỘT LỚP: tên lớp, mã lớp, khoá chứa nó,
                       lịch học, sĩ số, số buổi đã xếp, giáo viên + trợ giảng, đánh giá)
       · dải khoá   <- $courseFilters (khoá CÒN lớp đang mở, kèm số lớp của từng khoá)
       · dải khối   <- $grades        (khối lớp của các khoá đó)
     Bản mẫu có các trường KHÔNG có nguồn dữ liệu trong hệ thống (học phí trọn khóa,
     địa chỉ phòng học, hình thức học, số bài học) — thay bằng số liệu thật tương ứng
     thay vì hiển thị con số bịa. --}}
@php
    /*
     * SỬA 16/9 (khách: "trang lớp học ngoài public hiển thị dữ liệu lớp học mới đúng") —
     * MỖI THẺ LÀ MỘT LỚP HỌC đang mở, không còn gộp theo khoá và chỉ lấy lớp đầu tiên.
     * Nguồn: App\Services\Public\CourseService::classIndexData().
     */
    $classes = $classes ?? [];
    $courseFilters = $courseFilters ?? [];
    // Khoá chọn sẵn từ ?khoa= — nút "Xem lộ trình" ở trang chủ dẫn tới đây.
    $activeCourseId = $activeCourseId ?? null;
    $grades = $grades ?? [];
    $totalClasses = $totalClasses ?? count($classes);
    $totalStudents = $totalStudents ?? 0;
    // SỬA 16/9 — trạng thái người đang xem, quyết định nút ở chân mỗi thẻ lớp.
    $canRequestJoin = $canRequestJoin ?? false;
    $isGuest = $isGuest ?? true;

    /*
     * Dải chip MÔN HỌC (khối [COURSES-03]) đang ẨN: khách chốt trang chỉ cần hai bộ lọc
     * Khoá học và Khối lớp. ẨN CHỨ KHÔNG XOÁ — đổi cờ này thành true là hiện lại nguyên vẹn.
     */
    $showSubjectTabs = false;
    $subjects = $subjects ?? [];
    $activeSubject = $activeSubject ?? null;

    // Ảnh minh hoạ mặc định khi khoá chưa có ảnh bìa thật — xoay vòng 5 ảnh của bản mẫu theo
    // MÃ KHOÁ (không phải mã lớp) để mọi lớp cùng khoá dùng chung một ảnh, và trùng đúng ảnh
    // mà trang chi tiết khoá đang hiện.
    $fallbackCovers = ['course-img-1.png', 'course-img-2.png', 'course-img-3.png', 'course-img-4.png', 'course-img-5.png'];

    // Bảng màu 6 chip của bản mẫu, gán lần lượt cho các môn có thật.
    $chipTones = [
        ['icon' => 'book-open', 'iconTone' => 'text-[#2D7FA3]', 'surface' => 'bg-[#EAF5F8] border-[#C9DFE8]'],
        ['icon' => 'target', 'iconTone' => 'text-[#4C83B0]', 'surface' => 'bg-[#EEF5FF] border-[#D4E3F7]'],
        ['icon' => 'trophy', 'iconTone' => 'text-[#AF7C32]', 'surface' => 'bg-[#FFF7E3] border-[#F2E1B6]'],
        ['icon' => 'code-2', 'iconTone' => 'text-[#3B9374]', 'surface' => 'bg-[#EFF9F5] border-[#D4EDE2]'],
        ['icon' => 'graduation-cap', 'iconTone' => 'text-[#786BB1]', 'surface' => 'bg-[#F2F0FF] border-[#E0DBF7]'],
        ['icon' => 'globe', 'iconTone' => 'text-[#B86C6C]', 'surface' => 'bg-[#FFF1F0] border-[#F3D8D6]'],
    ];

    // Dữ liệu lọc/phân trang đưa sang Alpine — lọc ngay tại chỗ, không tải lại trang.
    $classRows = [];
    foreach ($classes as $c) {
        $classRows[] = [
            'id' => $c['id'],
            'courseId' => $c['courseId'],
            'subject' => $c['subject'] ?? '',
            'grade' => $c['grade'] ?? '',
            'search' => mb_strtolower(trim(
                ($c['name'] ?? '').' '.($c['code'] ?? '').' '.($c['courseTitle'] ?? '').' '.($c['teacherName'] ?? '')
            )),
        ];
    }
@endphp

<div class="max-w-[1780px] w-full mx-auto px-3 sm:px-5 lg:px-6 2xl:px-10 py-3 sm:py-5">
<div x-data="onthiCoursesPage({{ Js::from(['rows' => $classRows, 'courses' => $courseFilters, 'course' => $activeCourseId, 'pageSize' => 9, 'subject' => $activeSubject]) }})" class="flex flex-col gap-5">

    {{-- SỬA 16/9 — kết quả của nút "Đăng ký học" (gửi yêu cầu chờ giáo viên duyệt). Không có
         dải này thì học sinh bấm xong không biết đã gửi được hay chưa. --}}
    @if (session('status') === 'class-join-requested')
        <div class="flex items-start gap-2.5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">
            <x-lucide name="check-circle-2" class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
            <p class="text-[13px] font-semibold leading-relaxed text-emerald-800">
                Đã gửi yêu cầu đăng ký. Giáo viên của lớp sẽ duyệt, được duyệt là bạn vào học ngay —
                kết quả sẽ báo ở chuông thông báo.
            </p>
        </div>
    @endif

    @if ($errors->any())
        <div class="flex items-start gap-2.5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3">
            <x-lucide name="alert-triangle" class="mt-0.5 h-4 w-4 shrink-0 text-rose-600" />
            <div class="min-w-0 text-[13px] font-semibold leading-relaxed text-rose-800">
                @foreach ($errors->all() as $message)
                    <p>{{ $message }}</p>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ══════ [COURSES-01] HERO LỚP HỌC ══════ --}}
    <div class="relative overflow-hidden rounded-3xl border border-sky-200/80 bg-gradient-to-r from-[#0B3C78] via-[#0050A0] to-[#0284C7] p-5 text-white shadow-[0_10px_35px_rgba(0,100,220,0.08)] sm:p-6 lg:p-7">
        <img src="{{ asset('assets/hero-courses.jpg') }}" alt="Minh họa lớp học Tin học 360"
             class="absolute inset-0 w-full h-full object-cover object-right pointer-events-none opacity-40 mix-blend-overlay">

        <div class="relative z-10 flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-2xl">
                <div class="mb-3 inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-white/15 px-3 py-1 text-[11px] font-bold text-sky-50 backdrop-blur">
                    <x-lucide name="sparkles" class="w-3.5 h-3.5" />
                    <span>Chương trình đào tạo chuẩn Chuyên & HSG</span>
                </div>

                <h1 class="text-xl font-black leading-tight tracking-tight text-white sm:text-2xl">Lớp học Tin học 360</h1>

                <p class="mt-2 max-w-xl text-xs leading-5 text-sky-100 sm:text-sm sm:leading-6">
                    Mỗi khóa học gồm các lớp theo khối, mục tiêu và hình thức học riêng; tích hợp chấm bài tự động,
                    giáo trình bản quyền và đội ngũ giáo viên trường Chuyên.
                </p>

                <div class="mt-4 flex flex-wrap items-center gap-2.5">
                    <a href="{{ route('access.activate') }}"
                       class="flex min-h-10 items-center gap-1.5 rounded-xl bg-[#FFF1C7] px-4 py-2 text-[11px] font-extrabold text-[#76551A] shadow-sm transition-all hover:bg-[#FFE6A1] active:scale-[.98]">
                        <x-lucide name="key-round" class="w-4 h-4" />
                        <span>Kích hoạt mã khoá học</span>
                    </a>
                    <div class="flex items-center gap-2 text-[11px] font-medium text-sky-100">
                        <x-lucide name="check-circle" class="w-4 h-4 text-emerald-400" />
                        <span>Chấm tự động OJ 24/7</span>
                    </div>
                </div>
            </div>

            <div class="relative z-10 w-full rounded-2xl border border-white/20 bg-slate-950/10 p-3 text-center shadow-lg backdrop-blur-md lg:w-72 lg:shrink-0">
                <p class="text-[11px] font-bold uppercase tracking-[.08em] text-sky-100">Quy mô hệ thống</p>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <div class="rounded-xl bg-white/10 p-2.5">
                        <p class="text-xl font-black text-white">{{ number_format($totalClasses) }}</p>
                        <p class="mt-0.5 text-[11px] text-sky-100">Lớp học</p>
                    </div>
                    <div class="rounded-xl bg-white/10 p-2.5">
                        <p class="text-xl font-black text-[#FFE08A]">{{ number_format($totalStudents) }}</p>
                        <p class="mt-0.5 text-[11px] text-sky-100">Học viên</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════ [COURSES-02] BỘ LỌC ══════
         SỬA 16/9 — hai dải lọc, CẢ HAI ĐỀU ĐỔ TỪ KHOÁ HỌC:
           · Khối lớp  — khối của khoá chứa lớp (bảng class_rooms không có cột khối riêng);
           · Khoá học  — chọn khoá thì chỉ còn lớp thuộc khoá đó.
         Dải "Khoá học" tự thu hẹp theo khối đang chọn (visibleCourses ở partials/courses-script)
         nên không bao giờ bấm một chip rồi ra danh sách rỗng. --}}
    <div class="flex flex-col gap-3 rounded-3xl border border-[#DDEAF0] bg-white p-3.5 shadow-[0_2px_10px_rgba(28,91,121,0.04)]">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="relative flex-1">
                <x-lucide name="search" class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#71869A]" />
                <input type="text" aria-label="Tìm kiếm lớp học" placeholder="Tìm theo tên lớp, mã lớp, khoá học, giáo viên..."
                       x-model="searchQuery"
                       class="min-h-11 w-full rounded-2xl border border-[#DDEAF0] bg-[#F8FAFB] py-2 pl-10 pr-4 text-xs text-slate-800 placeholder:text-[#8A9BAD] transition-all focus:border-[#9DC8D7] focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#EAF5F8]">
            </div>

            <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar">
                <span class="type-label shrink-0 text-[#536D86]">Khối lớp:</span>
                <button type="button" @click="setGrade('all')" :aria-pressed="selectedGrade === 'all'"
                        class="min-h-9 rounded-xl border px-3 py-1 text-[11px] font-bold transition-all whitespace-nowrap"
                        :class="selectedGrade === 'all' ? 'border-[#126F91] bg-[#126F91] text-white shadow-[0_4px_10px_rgba(18,111,145,0.18)]' : 'border-transparent bg-[#F5F8FA] text-[#536D86] hover:border-[#C9DFE8] hover:bg-white'">Tất cả</button>
                @foreach ($grades as $g)
                    <button type="button" @click="setGrade(@js($g))" :aria-pressed="selectedGrade === @js($g)"
                            class="min-h-9 rounded-xl border px-3 py-1 text-[11px] font-bold transition-all whitespace-nowrap"
                            :class="selectedGrade === @js($g) ? 'border-[#126F91] bg-[#126F91] text-white shadow-[0_4px_10px_rgba(18,111,145,0.18)]' : 'border-transparent bg-[#F5F8FA] text-[#536D86] hover:border-[#C9DFE8] hover:bg-white'">{{ $g }}</button>
                @endforeach
            </div>
        </div>

        @if (count($courseFilters) > 0)
            <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar border-t border-[#EDF3F6] pt-3">
                <span class="type-label shrink-0 text-[#536D86]">Khoá học:</span>
                <button type="button" @click="setCourse('all')" :aria-pressed="selectedCourse === 'all'"
                        class="min-h-9 shrink-0 rounded-xl border px-3 py-1 text-[11px] font-bold transition-all whitespace-nowrap"
                        :class="selectedCourse === 'all' ? 'border-[#126F91] bg-[#126F91] text-white shadow-[0_4px_10px_rgba(18,111,145,0.18)]' : 'border-transparent bg-[#F5F8FA] text-[#536D86] hover:border-[#C9DFE8] hover:bg-white'">Tất cả</button>
                @foreach ($courseFilters as $cf)
                    <button type="button" x-show="visibleCourses.some(c => c.id === {{ $cf['id'] }})" x-cloak
                            @click="setCourse({{ $cf['id'] }})" :aria-pressed="selectedCourse === {{ $cf['id'] }}"
                            class="inline-flex min-h-9 shrink-0 items-center gap-1.5 rounded-xl border px-3 py-1 text-[11px] font-bold transition-all whitespace-nowrap"
                            :class="selectedCourse === {{ $cf['id'] }} ? 'border-[#126F91] bg-[#126F91] text-white shadow-[0_4px_10px_rgba(18,111,145,0.18)]' : 'border-transparent bg-[#F5F8FA] text-[#536D86] hover:border-[#C9DFE8] hover:bg-white'">
                        <span class="max-w-[190px] truncate">{{ $cf['title'] }}</span>
                        <span class="rounded-md px-1.5 py-0.5 text-[10px] font-black"
                              :class="selectedCourse === {{ $cf['id'] }} ? 'bg-white/20 text-white' : 'bg-white text-[#2D7FA3]'">{{ $cf['classCount'] }}</span>
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ══════ [COURSES-03] TAB CHUYÊN MỤC ══════
         ĐANG ẨN (xem $showSubjectTabs ở đầu file) — khách chốt trang chỉ cần hai bộ lọc
         Khoá học và Khối lớp. Ẩn chứ không xoá. --}}
    @if ($showSubjectTabs)
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-0.5">
            <button type="button" @click="setCategory('all')" :aria-pressed="selectedCategory === 'all'"
                    class="flex min-h-10 items-center gap-1.5 rounded-2xl border px-3.5 py-2 text-[11px] font-bold transition-all whitespace-nowrap"
                    :class="selectedCategory === 'all' ? 'border-[#126F91] bg-[#126F91] text-white shadow-[0_4px_10px_rgba(18,111,145,0.18)]' : 'bg-[#EAF5F8] border-[#C9DFE8] text-[#536D86] hover:brightness-[.98]'">
                <span class="grid h-5 w-5 place-items-center rounded-lg" :class="selectedCategory === 'all' ? 'bg-white/15' : 'bg-white'">
                    <x-lucide name="book-open" class="h-3.5 w-3.5" ::class="selectedCategory === 'all' ? 'text-white' : 'text-[#2D7FA3]'" />
                </span>
                <span>Tất cả lớp học</span>
            </button>
            @foreach ($subjects as $i => $subject)
                @php $tone = $chipTones[($i + 1) % count($chipTones)]; @endphp
                <button type="button" @click="setCategory(@js($subject))" :aria-pressed="selectedCategory === @js($subject)"
                        class="flex min-h-10 items-center gap-1.5 rounded-2xl border px-3.5 py-2 text-[11px] font-bold transition-all whitespace-nowrap"
                        :class="selectedCategory === @js($subject) ? 'border-[#126F91] bg-[#126F91] text-white shadow-[0_4px_10px_rgba(18,111,145,0.18)]' : '{{ $tone['surface'] }} text-[#536D86] hover:brightness-[.98]'">
                    <span class="grid h-5 w-5 place-items-center rounded-lg" :class="selectedCategory === @js($subject) ? 'bg-white/15' : 'bg-white'">
                        <x-lucide :name="$tone['icon']" class="h-3.5 w-3.5" ::class="selectedCategory === @js($subject) ? 'text-white' : '{{ $tone['iconTone'] }}'" />
                    </span>
                    <span>{{ $subject }}</span>
                </button>
            @endforeach
        </div>
    @endif

    <div class="flex items-center justify-between gap-3 px-1">
        <div class="flex items-center gap-2 text-[11px] text-[#71869A]">
            <x-lucide name="filter" class="h-3.5 w-3.5 text-[#2D7FA3]" />
            <span>Hiển thị <b class="text-[#536D86]" x-text="filtered.length"></b> lớp học phù hợp</span>
        </div>
        <button type="button" @click="resetFilters()" x-show="selectedGrade !== 'all' || selectedCourse !== 'all' || searchQuery !== ''" x-cloak
                class="inline-flex items-center gap-1 text-[11px] font-bold text-[#126F91] hover:underline">
            <x-lucide name="x" class="h-3 w-3" />Bỏ lọc
        </button>
    </div>

    {{-- ══════ [COURSES-04] LƯỚI LỚP HỌC ══════
         SỬA 16/9 (khách: "copy UI trang lớp học của source mới") — chép ĐÚNG khối card của
         education-main-12/education-main/src/components/CoursesPage.jsx: ảnh + viên nhãn,
         dải [COURSES-04B] quan hệ khoá–lớp, mã lớp, dải [COURSES-04A] thông tin lớp có đường
         kẻ trên dưới, khối giáo viên, và chân thẻ học phí + nút.

         Bản mẫu có vài trường VIẾT CỨNG không có nguồn trong hệ thống — thay bằng số liệu thật
         tương ứng, không in số bịa:
           · "36 bài học"        -> số buổi ĐÃ XẾP LỊCH thật của lớp (class_sessions);
           · "1,250+ học viên"   -> sĩ số thật (ghi danh đang hoạt động);
           · "32 / 40 học sinh"  -> sĩ số thật / sĩ số tối đa quản trị nhập; chưa nhập thì chỉ in sĩ số thật;
           · "1.200.000đ"        -> giá sản phẩm gắn với khoá; khoá chưa mở bán thì giấu cả dòng học phí;
           · nút Zalo cứng       -> trang Liên hệ & Hỗ trợ của chính hệ thống. --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:gap-5 xl:grid-cols-3">
        @foreach ($classes as $cl)
            @php
                $cover = $cl['image'] ?: asset('assets/'.$fallbackCovers[$cl['courseId'] % count($fallbackCovers)]);
                $featured = ($cl['average'] ?? 0) >= 4.5;
                $seatsLabel = $cl['capacity']
                    ? number_format($cl['studentsCount']).' / '.number_format($cl['capacity']).' học sinh'
                    : number_format($cl['studentsCount']).' học sinh';
            @endphp
            <div x-show="visibleIds.includes({{ $cl['id'] }})" x-cloak
                 :style="'order:' + visibleIds.indexOf({{ $cl['id'] }})"
                 class="group flex min-h-full flex-col justify-between overflow-hidden rounded-3xl border border-[#DDEAF0] bg-white shadow-[0_4px_16px_rgba(28,91,121,0.05)] transition-all duration-300 hover:-translate-y-0.5 hover:border-[#C9DFE8] hover:shadow-[0_12px_28px_rgba(28,91,121,0.1)]">
                <div>
                    {{-- Ảnh thẻ — ảnh của KHOÁ chứa lớp, lớp không có ảnh riêng. --}}
                    <div class="relative h-44 overflow-hidden bg-[#F5F8FA] sm:h-48">
                        <div class="absolute inset-x-0 top-0 z-10 h-1 {{ $featured ? 'bg-gradient-to-r from-[#F6C453] via-[#F9D778] to-[#2D7FA3]' : 'bg-gradient-to-r from-[#78C7D5] to-[#B7E5E7]' }}"></div>
                        <img src="{{ $cover }}" alt="Lớp {{ $cl['name'] }}" loading="lazy" decoding="async"
                             class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.03]">

                        @if ($cl['tag'])
                            <div class="absolute left-3 top-3 flex flex-wrap gap-1.5">
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-[#C9DFE8] bg-white/95 px-2.5 py-1 text-[11px] font-bold text-[#216F8E] shadow-sm backdrop-blur-sm">
                                    <span class="grid h-5 w-5 place-items-center rounded-md bg-[#EAF5F8]">
                                        <x-lucide :name="$featured ? 'trophy' : 'sparkles'" class="h-3.5 w-3.5 {{ $featured ? 'text-[#AF7C32]' : 'text-[#2D7FA3]' }}" />
                                    </span>
                                    {{ $cl['tag'] }}
                                </span>
                            </div>
                        @endif

                        <div class="absolute bottom-3 left-3 right-3 flex items-center justify-between rounded-xl bg-slate-900/60 px-3 py-1.5 text-[11px] font-bold text-white backdrop-blur-sm">
                            <span class="flex items-center gap-1">
                                <x-lucide name="clock" class="h-3.5 w-3.5 text-amber-300" />
                                <span>{{ $cl['sessionsCount'] > 0 ? $cl['sessionsCount'].' buổi' : 'Chưa xếp lịch' }}</span>
                            </span>
                            <span class="flex items-center gap-1">
                                <x-lucide name="users" class="h-3.5 w-3.5 text-sky-300" />
                                <span>{{ number_format($cl['studentsCount']) }} học viên</span>
                            </span>
                        </div>
                    </div>

                    {{-- Thông tin lớp --}}
                    <div class="p-4 sm:p-5">
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-lg border border-[#C9DFE8] bg-[#EAF5F8] px-2 py-1 text-[11px] font-extrabold text-[#216F8E]">
                                <x-lucide name="graduation-cap" class="h-3.5 w-3.5 text-[#2D7FA3]" />
                                {{ $cl['grade'] ?: 'Mọi khối' }}
                            </span>
                            <div class="flex items-center gap-1 text-[11px] font-bold text-[#AF7C32]">
                                <x-lucide name="star" class="h-3.5 w-3.5 fill-amber-400 text-amber-400" />
                                <span>{{ $cl['average'] !== null ? number_format($cl['average'], 1) : '—' }}</span>
                                <span class="text-[11px] font-medium text-[#8A9BAD]">({{ $cl['count'] }})</span>
                            </div>
                        </div>

                        {{-- [COURSES-04B] QUAN HỆ KHÓA–LỚP — nói rõ thẻ này là một lớp thuộc khoá nào.
                             Bấm vào tên khoá là LỌC ngay danh sách theo khoá đó, đúng như bản mẫu. --}}
                        <div class="mb-1.5 flex min-w-0 items-center gap-1.5 text-[11px] text-[#536D86]">
                            <span class="grid h-5 w-5 shrink-0 place-items-center rounded-md bg-[#EEF5FF]">
                                <x-lucide name="book-open" class="h-3.5 w-3.5 text-[#4C83B0]" />
                            </span>
                            <span class="shrink-0">Khóa học:</span>
                            <button type="button" @click="setCourse({{ $cl['courseId'] }})"
                                    :aria-pressed="selectedCourse === {{ $cl['courseId'] }}"
                                    aria-label="Lọc các lớp thuộc khóa học {{ $cl['courseTitle'] }}"
                                    title="Lọc theo khóa học: {{ $cl['courseTitle'] }}"
                                    class="min-w-0 truncate text-left font-bold text-[#2D7FA3] underline-offset-2 transition hover:text-[#126F91] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#CBEAF1]">{{ $cl['courseTitle'] }}</button>
                        </div>

                        <p class="mb-2 text-[11px] font-semibold uppercase tracking-[.08em] text-[#8A9BAD]">Mã lớp · {{ $cl['code'] }}</p>

                        <h3 class="mb-1.5 line-clamp-2 text-sm font-extrabold leading-5 text-[#123B68] transition-colors group-hover:text-[#126F91] sm:text-[15px]">{{ $cl['name'] }}</h3>

                        @if ($cl['subtitle'])
                            <p class="type-body mb-3 line-clamp-2">{{ $cl['subtitle'] }}</p>
                        @endif

                        {{-- [COURSES-04A] THÔNG TIN LỚP — nơi học, hình thức, địa chỉ, lịch học, sĩ số.
                             Dòng nào chưa có dữ liệu thì bỏ hẳn dòng đó, không in ô rỗng cho đủ mẫu. --}}
                        <div class="mb-3 grid grid-cols-2 gap-x-3 gap-y-2 border-y border-[#E7EFF3] py-3 text-[11px] text-[#536D86]">
                            @if ($cl['location'])
                                <div class="flex min-w-0 items-center gap-1.5">
                                    <x-lucide name="map-pin" class="h-3.5 w-3.5 shrink-0 text-[#2D7FA3]" />
                                    <span class="truncate" title="{{ $cl['location'] }}">{{ $cl['location'] }}</span>
                                </div>
                            @endif

                            @if ($cl['format'])
                                <div class="flex min-w-0 items-center gap-1.5">
                                    <x-lucide name="play" class="h-3.5 w-3.5 shrink-0 text-[#3B9374]" />
                                    <span class="truncate" title="{{ $cl['format'] }}">{{ $cl['format'] }}</span>
                                </div>
                            @endif

                            @if ($cl['address'])
                                <div class="col-span-2 flex min-w-0 items-center gap-1.5">
                                    <x-lucide name="map-pin" class="h-3.5 w-3.5 shrink-0 text-[#71869A]" />
                                    <span class="truncate" title="{{ $cl['address'] }}">{{ $cl['address'] }}</span>
                                </div>
                            @endif

                            @if ($cl['scheduleNote'])
                                <div class="col-span-2 flex min-w-0 items-center gap-1.5">
                                    <x-lucide name="calendar" class="h-3.5 w-3.5 shrink-0 text-[#2D7FA3]" />
                                    <span class="shrink-0 font-semibold text-[#71869A]">Lịch học:</span>
                                    <span class="truncate text-[11px] font-bold text-[#376B98]" title="{{ $cl['scheduleNote'] }}">{{ $cl['scheduleNote'] }}</span>
                                </div>
                            @endif

                            <div class="col-span-2 grid grid-cols-2 items-center gap-2 text-[11px]">
                                <div class="flex min-w-0 items-center gap-1.5 text-[#536D86]">
                                    <x-lucide name="users" class="h-3.5 w-3.5 shrink-0 text-[#4C83B0]" />
                                    <span class="shrink-0 font-semibold text-[#71869A]">Sĩ số:</span>
                                    <span class="truncate font-bold text-[#376B98]">{{ $seatsLabel }}</span>
                                </div>
                                <div class="inline-flex max-w-full items-center justify-self-end gap-1 whitespace-nowrap rounded-lg border px-2 py-1 text-[10px] shadow-[0_2px_6px_rgba(45,127,163,0.1)] {{ $cl['isMember'] ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-amber-500 bg-amber-500 text-white' }}">
                                    <x-lucide :name="$cl['isMember'] ? 'check-circle-2' : 'user-check'" class="h-3.5 w-3.5 shrink-0 text-white" />
                                    <span class="font-extrabold text-white">{{ $cl['isMember'] ? 'Đã tham gia' : 'Chưa tham gia' }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- Khối giáo viên. Bản mẫu dùng ảnh thật của giáo viên; hệ thống chưa lưu
                             ảnh giáo viên nên vẽ chữ cái đầu bằng x-ws.avatar ngay tại chỗ —
                             KHÔNG gọi dịch vụ ảnh ngoài, tránh gửi họ tên thật ra máy chủ lạ. --}}
                        <div class="flex items-center gap-2.5 rounded-2xl border border-[#DDEAF0] bg-[#F8FAFB] p-2.5">
                            <x-ws.avatar :name="$cl['teacherName'] ?: 'Chưa phân công'" size="sm" />
                            <div class="overflow-hidden">
                                <p class="truncate text-[11px] font-bold text-slate-800">{{ $cl['teacherName'] ?: 'Chưa phân công' }}</p>
                                <p class="truncate text-[11px] text-[#71869A]">Giảng viên phụ trách</p>
                                @if (count($cl['assistantNames']) > 0)
                                    <p class="mt-1 truncate text-[11px] text-[#71869A]" title="{{ implode(', ', $cl['assistantNames']) }}">
                                        {{ count($cl['assistantNames']) }} trợ giảng đồng hành
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Chân thẻ --}}
                <div class="p-4 pt-0">
                    <div class="flex items-center justify-between gap-3 border-t border-[#DDEAF0] pt-3">
                        <div class="min-w-0">
                            @if ($cl['priceLabel'])
                                <p class="type-meta">Học phí trọn khóa</p>
                                <p class="text-sm font-black text-[#126F91]">{{ $cl['priceLabel'] }}</p>
                            @else
                                <a href="{{ route('info.index') }}"
                                   class="inline-flex min-h-9 items-center gap-1.5 rounded-xl border border-[#B8DCE6] bg-[#EAF5F8] px-3 py-2 text-[11px] font-extrabold text-[#126F91] transition-all hover:border-[#8FC7D6] hover:bg-[#DDF1F6] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#CBEAF1] active:scale-[.98]">
                                    <x-lucide name="message-circle" class="h-3.5 w-3.5" />
                                    <span>Liên hệ quản lý lớp</span>
                                </a>
                            @endif
                            @php
                                // SỬA 16/9 — không còn lối vào bằng mã lớp; 3 trạng thái thật:
                                // đang học / đã gửi yêu cầu chờ duyệt / chưa đăng ký.
                                $joinHint = $cl['isMember']
                                    ? 'Bạn đang học lớp này'
                                    : ($cl['isPending'] ? 'Đang chờ giáo viên duyệt' : 'Đăng ký, giáo viên duyệt là vào học');
                            @endphp
                            <p class="type-meta mt-0.5 max-w-[150px] truncate" title="{{ $joinHint }}">{{ $joinHint }}</p>
                        </div>

                        @if ($cl['isMember'])
                            <a href="{{ route('student.classes.show', $cl['id']) }}"
                               class="flex min-h-10 shrink-0 items-center gap-1.5 rounded-xl bg-gradient-to-r from-[#2D7FA3] to-[#3B9374] px-3.5 py-2 text-[11px] font-extrabold text-white shadow-[0_5px_12px_rgba(45,127,163,0.18)] transition-all hover:brightness-105 focus:outline-none focus-visible:ring-4 focus-visible:ring-[#CBEAF1] active:scale-[.98]">
                                <span>Vào học</span>
                                <x-lucide name="play" class="h-3.5 w-3.5" />
                            </a>
                        @elseif ($cl['isPending'])
                            {{-- SỬA 16/9 — đã gửi yêu cầu rồi: không cho bấm lại (server cũng chặn,
                                 xem Student\ClassRoomService::requestJoin()), chỉ báo trạng thái. --}}
                            <span class="flex min-h-10 shrink-0 cursor-default items-center gap-1.5 rounded-xl border border-[#F2E1B6] bg-[#FFF7E3] px-3.5 py-2 text-[11px] font-extrabold text-[#AF7C32]">
                                <x-lucide name="clock" class="h-3.5 w-3.5" />
                                <span>Đang chờ duyệt</span>
                            </span>
                        @elseif ($canRequestJoin)
                            {{-- SỬA 16/9 (khách yêu cầu) — bấm là GỬI YÊU CẦU, giáo viên duyệt mới
                                 vào học được. Không còn khâu nhập mã lớp. --}}
                            <form method="POST" action="{{ route('student.classes.requestJoin', $cl['id']) }}" class="shrink-0">
                                @csrf
                                <button type="submit"
                                        class="flex min-h-10 shrink-0 items-center gap-1.5 rounded-xl bg-gradient-to-r from-[#126F91] to-[#188DB0] px-3.5 py-2 text-[11px] font-extrabold text-white shadow-[0_5px_12px_rgba(18,111,145,0.18)] transition-all hover:from-[#0F607E] hover:to-[#147D9B] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#CBEAF1] active:scale-[.98]">
                                    <span>Đăng ký học</span>
                                    <x-lucide name="user-check" class="h-3.5 w-3.5" />
                                </button>
                            </form>
                        @elseif ($isGuest)
                            {{-- Khách chưa đăng nhập: đưa về đăng nhập rồi quay lại đúng trang này. --}}
                            <a href="{{ route('login') }}"
                               class="flex min-h-10 shrink-0 items-center gap-1.5 rounded-xl bg-gradient-to-r from-[#126F91] to-[#188DB0] px-3.5 py-2 text-[11px] font-extrabold text-white shadow-[0_5px_12px_rgba(18,111,145,0.18)] transition-all hover:from-[#0F607E] hover:to-[#147D9B] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#CBEAF1] active:scale-[.98]">
                                <span>Đăng ký học</span>
                                <x-lucide name="user-check" class="h-3.5 w-3.5" />
                            </a>
                        @else
                            {{-- Giáo viên/quản trị đang xem trang công khai: route gửi yêu cầu nằm
                                 trong nhóm role:student nên bấm vào sẽ 403 — đưa về trang khoá thay vì. --}}
                            <a href="{{ $cl['href'] }}"
                               class="flex min-h-10 shrink-0 items-center gap-1.5 rounded-xl border border-[#B8DCE6] bg-[#EAF5F8] px-3.5 py-2 text-[11px] font-extrabold text-[#126F91] transition-all hover:bg-[#DDF1F6] active:scale-[.98]">
                                <span>Xem khoá học</span>
                                <x-lucide name="chevron-right" class="h-3.5 w-3.5" />
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach

        @if (count($classes) === 0)
            <div class="md:col-span-2 xl:col-span-3">
                <div class="rounded-3xl border border-dashed border-[#C9DFE8] bg-white p-10 text-center">
                    <x-lucide name="search" class="mx-auto h-9 w-9 text-[#9DC8D7]" />
                    <h2 class="mt-3 text-sm font-black text-[#123B68]">Chưa có lớp nào đang mở</h2>
                    <p class="mt-1 text-xs text-[#71869A]">Quay lại sau hoặc để lại liên hệ để được báo khi có lớp mới.</p>
                    <a href="{{ route('info.index') }}" class="mt-4 inline-block text-[11px] font-bold text-[#126F91] hover:underline">Liên hệ tư vấn →</a>
                </div>
            </div>
        @endif
    </div>

    {{-- Không có lớp nào KHỚP BỘ LỌC (khác với "chưa có lớp nào" ở trên) — đúng khối rỗng của
         bản mẫu, kèm nút xoá bộ lọc. --}}
    <div x-show="filtered.length === 0 && {{ count($classes) }} > 0" x-cloak
         class="rounded-3xl border border-dashed border-[#C9DFE8] bg-white p-10 text-center">
        <x-lucide name="search" class="mx-auto h-9 w-9 text-[#9DC8D7]" />
        <h2 class="mt-3 text-sm font-black text-[#123B68]">Không tìm thấy lớp học phù hợp</h2>
        <p class="mt-1 text-xs text-[#71869A]">Thử đổi khối lớp, khoá học hoặc từ khóa tìm kiếm.</p>
        <button type="button" @click="resetFilters()" class="mt-4 text-[11px] font-bold text-[#126F91] hover:underline">Xóa bộ lọc</button>
    </div>

    {{-- ══════ [COURSES-05] PHÂN TRANG ══════ --}}
    <nav aria-label="Phân trang lớp học" x-show="filtered.length > pageSize" x-cloak
         class="flex flex-col items-center justify-between gap-2 rounded-2xl border border-[#DDEAF0] bg-white p-2.5 sm:flex-row">
        <span class="text-[11px] text-[#71869A]">Trang <b class="text-[#536D86]" x-text="page"></b> / <span x-text="totalPages"></span></span>
        <div class="flex items-center gap-1.5">
            <button type="button" aria-label="Trang trước" :disabled="page === 1" @click="currentPage = Math.max(1, page - 1)"
                    class="grid h-9 w-9 place-items-center rounded-xl border border-[#DDEAF0] text-[#536D86] transition hover:border-[#9DC8D7] hover:bg-[#EAF5F8] disabled:cursor-not-allowed disabled:opacity-40">
                <x-lucide name="chevron-left" class="h-4 w-4" />
            </button>
            <template x-for="page in totalPages" :key="page">
                <button type="button" :aria-label="'Trang ' + page" :aria-current="this.page === page ? 'page' : null" @click="currentPage = page"
                        class="grid h-9 min-w-9 place-items-center rounded-xl px-2 text-[11px] font-extrabold transition"
                        :class="this.page === page ? 'bg-[#126F91] text-white shadow-[0_3px_8px_rgba(18,111,145,0.18)]' : 'border border-transparent text-[#536D86] hover:border-[#C9DFE8] hover:bg-[#F8FAFB]'"
                        x-text="page"></button>
            </template>
            <button type="button" aria-label="Trang sau" :disabled="page === totalPages" @click="currentPage = Math.min(totalPages, page + 1)"
                    class="grid h-9 w-9 place-items-center rounded-xl border border-[#DDEAF0] text-[#536D86] transition hover:border-[#9DC8D7] hover:bg-[#EAF5F8] disabled:cursor-not-allowed disabled:opacity-40">
                <x-lucide name="chevron-right" class="h-4 w-4" />
            </button>
        </div>
    </nav>

    {{-- Không có kết quả --}}
    <div x-show="filtered.length === 0" x-cloak class="rounded-3xl border border-dashed border-[#C9DFE8] bg-white p-10 text-center">
        <x-lucide name="search" class="mx-auto h-9 w-9 text-[#9DC8D7]" />
        <h2 class="mt-3 text-sm font-black text-[#123B68]">Không tìm thấy lớp học phù hợp</h2>
        <p class="mt-1 text-xs text-[#71869A]">Thử đổi khối lớp, danh mục hoặc từ khóa tìm kiếm.</p>
        <button type="button" @click="resetFilters()" class="mt-4 text-[11px] font-bold text-[#126F91] hover:underline">Xóa bộ lọc</button>
    </div>
</div>
</div>
@endsection

@push('scripts')
    @include('partials.courses-script')
@endpush
