@extends('layouts.guest')

@section('title', 'Lộ trình học theo khối lớp — Ôn Thi 360')
@section('meta-description', 'Chọn khối lớp để xem các lộ trình học phù hợp: mỗi lộ trình chia thành nhiều bậc, có số buổi và mục tiêu rõ ràng, kèm các lớp đang mở.')

@section('content')
{{-- ═══════════════ [COURSES] MÀN LỚP HỌC ═══════════════
     SỬA 11/9 — dựng lại theo ĐÚNG source giao diện khách gửi:
     education-main/src/components/CoursesPage.jsx (COURSES-01 … COURSES-05).
     Bố cục/class chép nguyên; React state đổi sang Alpine; mọi nút gắn link thật.

     Dữ liệu lấy từ cơ sở dữ liệu (App\Services\Public\CourseService::indexData):
       · thẻ lớp   <- $courses (tiêu đề, mô tả, ảnh bìa, khối, mã lớp, sĩ số,
                     giáo viên phụ trách + trợ giảng, đánh giá, trạng thái ghi danh)
       · dải khóa  <- $subjects (môn/khóa có thật, thay 6 danh mục cứng của bản mẫu)
       · dải khối  <- $grades   (khối lớp có thật)
     Bản mẫu có các trường KHÔNG có nguồn dữ liệu trong hệ thống (học phí trọn khóa,
     địa chỉ phòng học, hình thức học, số bài học) — thay bằng số liệu thật tương ứng
     thay vì hiển thị con số bịa. --}}
@php
    $courses = $courses ?? [];
    $subjects = $subjects ?? [];
    $grades = $grades ?? [];
    $activeSubject = $activeSubject ?? null;

    // Ảnh minh hoạ mặc định khi khóa học chưa có ảnh bìa thật — xoay vòng 5 ảnh của bản mẫu
    // theo id nên mỗi thẻ có ảnh ổn định, không đổi mỗi lần tải lại trang.
    $fallbackCovers = ['course-img-1.png', 'course-img-2.png', 'course-img-3.png', 'course-img-4.png', 'course-img-5.png'];

    // Bảng màu 6 chip của bản mẫu, gán lần lượt cho các khóa học có thật.
    $chipTones = [
        ['icon' => 'book-open', 'iconTone' => 'text-[#2D7FA3]', 'surface' => 'bg-[#EAF5F8] border-[#C9DFE8]'],
        ['icon' => 'target', 'iconTone' => 'text-[#4C83B0]', 'surface' => 'bg-[#EEF5FF] border-[#D4E3F7]'],
        ['icon' => 'trophy', 'iconTone' => 'text-[#AF7C32]', 'surface' => 'bg-[#FFF7E3] border-[#F2E1B6]'],
        ['icon' => 'code-2', 'iconTone' => 'text-[#3B9374]', 'surface' => 'bg-[#EFF9F5] border-[#D4EDE2]'],
        ['icon' => 'graduation-cap', 'iconTone' => 'text-[#786BB1]', 'surface' => 'bg-[#F2F0FF] border-[#E0DBF7]'],
        ['icon' => 'globe', 'iconTone' => 'text-[#B86C6C]', 'surface' => 'bg-[#FFF1F0] border-[#F3D8D6]'],
    ];

    // Dữ liệu lọc/phân trang đưa sang Alpine — lọc ngay tại chỗ, không tải lại trang.
    $courseRows = [];
    foreach ($courses as $i => $c) {
        $courseRows[] = [
            'id' => $c['id'],
            'subject' => $c['subject'] ?? '',
            'grade' => $c['grade'] ?? '',
            /* SỬA 15/9 — trước chỉ gộp mã của LỚP ĐẦU TIÊN vào ô tìm kiếm, nên gõ mã của
               lớp thứ hai trở đi là không ra gì. Giờ gộp mã VÀ tên của mọi lớp trong khoá. */
            'search' => mb_strtolower(trim(preg_replace('/\s+/u', ' ',
                ($c['title'] ?? '').' '.strip_tags((string) ($c['subtitle'] ?? '')).' '.($c['subject'] ?? '').' '
                .collect($c['classes'] ?? [])->map(fn ($cl) => $cl['code'].' '.$cl['name'])->implode(' ')
            ))),
        ];
    }

    // B7 — bộ lọc theo lộ trình. Mỗi lộ trình mang theo danh sách id khoá học của nó nên
    // trang lọc ngay tại chỗ, không tải lại.
    $learningPathFilters = $learningPathFilters ?? [];
    $activeLearningPath = $activeLearningPath ?? null;

    $totalStudents = 0;
    $totalClasses = 0;
    foreach ($courses as $c) {
        $totalStudents += (int) ($c['studentCount'] ?? 0);
        $totalClasses += (int) ($c['classCount'] ?? 0);
    }
@endphp

<div class="max-w-[1780px] w-full mx-auto px-3 sm:px-5 lg:px-6 2xl:px-10 py-3 sm:py-5">
<div x-data="onthiCoursesPage({{ Js::from(['rows' => $courseRows, 'pageSize' => 3, 'subject' => $activeSubject, 'learningPaths' => $learningPathFilters, 'learningPath' => $activeLearningPath]) }})" class="flex flex-col gap-5">

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

                <h1 class="text-xl font-black leading-tight tracking-tight text-white sm:text-2xl">Lộ trình học Tin học 360</h1>

                <p class="mt-2 max-w-xl text-xs leading-5 text-sky-100 sm:text-sm sm:leading-6">
                    Chọn khối lớp của con để xem các lộ trình phù hợp. Mỗi lộ trình chia thành nhiều bậc nối tiếp nhau,
                    có số buổi và mục tiêu rõ ràng — biết trước con đang ở đâu và còn bao xa nữa tới đích.
                </p>

                <div class="mt-4 flex flex-wrap items-center gap-2.5">
                    <a href="{{ route('access.activate') }}"
                       class="flex min-h-10 items-center gap-1.5 rounded-xl bg-[#FFF1C7] px-4 py-2 text-[11px] font-extrabold text-[#76551A] shadow-sm transition-all hover:bg-[#FFE6A1] active:scale-[.98]">
                        <x-lucide name="key-round" class="w-4 h-4" />
                        <span>Kích hoạt mã lớp học</span>
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

    {{-- ══════ [COURSES-01B] ĐANG XEM THEO MỘT LỘ TRÌNH ══════
         Chỉ hiện khi người dùng đi từ trang lộ trình sang (?lo-trinh=). Lúc đó họ đã chốt lộ
         trình rồi, việc cần là xem các khoá/lớp bên trong — nên đây là một dải báo trạng thái
         kèm lối thoát, không phải một dãy nút chọn nữa.

         SỬA 15/9 — dãy nút chọn lộ trình cũ đã bỏ: giờ lộ trình được chọn bằng cách bấm KHỐI
         LỚP rồi bấm thẻ lộ trình ở khối bên dưới, đúng luồng khách chốt. --}}
    @if (count($learningPathFilters) > 0)
        <div x-show="selectedPath !== 'all'" x-cloak
             class="flex flex-col gap-2.5 rounded-3xl border border-[#CDE8EC] bg-[#F4FBFC] p-3.5 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex min-w-0 items-center gap-2.5">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border border-[#CDE8EC] bg-white text-[#23869B]">
                    <x-lucide name="route" class="h-4 w-4" />
                </span>
                <span class="min-w-0">
                    <span class="block text-[10.5px] font-bold uppercase tracking-wide text-[#71869A]">Đang xem khoá học của lộ trình</span>
                    <span class="block truncate text-[13px] font-black text-[#123B68]" x-text="selectedPathTitle"></span>
                </span>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <template x-if="selectedPathHref">
                    <a :href="selectedPathHref"
                       class="inline-flex min-h-9 items-center gap-1 rounded-xl border border-[#C9DFE8] bg-white px-3 text-[11px] font-bold text-[#126F91] transition-colors hover:bg-[#F2F8F9]">
                        <x-lucide name="route" class="h-3.5 w-3.5" />Xem lộ trình
                    </a>
                </template>
                <button type="button" @click="setLearningPath('all')"
                        class="inline-flex min-h-9 items-center gap-1 rounded-xl border border-[#DDEAF0] bg-white px-3 text-[11px] font-bold text-[#536D86] transition-colors hover:bg-[#F5F8FA]">
                    <x-lucide name="x" class="h-3.5 w-3.5" />Bỏ lọc lộ trình
                </button>
            </div>
        </div>
    @endif

    {{-- ══════ [COURSES-02] BỘ LỌC ══════ --}}
    <div class="flex flex-col gap-3 rounded-3xl border border-[#DDEAF0] bg-white p-3.5 shadow-[0_2px_10px_rgba(28,91,121,0.04)] lg:flex-row lg:items-center lg:justify-between">
        <div class="relative flex-1">
            <x-lucide name="search" class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#71869A]" />
            <input type="text" aria-label="Tìm kiếm"
                   :placeholder="showPaths ? 'Tìm lộ trình theo tên, mục tiêu...' : 'Tìm khoá học, lớp học, mã lớp...'"
                   placeholder="Tìm lộ trình theo tên, mục tiêu..."
                   x-model="searchQuery"
                   class="min-h-11 w-full rounded-2xl border border-[#DDEAF0] bg-[#F8FAFB] py-2 pl-10 pr-4 text-xs text-slate-800 placeholder:text-[#8A9BAD] transition-all focus:border-[#9DC8D7] focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#EAF5F8]">
        </div>

        <div x-show="showPaths" x-cloak class="flex items-center gap-1.5 overflow-x-auto no-scrollbar">
            <span class="type-label shrink-0 text-[#536D86]">Lộ trình cho khối:</span>
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

    {{-- ══════ [COURSES-03] TAB CHUYÊN MỤC ══════
         Lọc theo MÔN chỉ áp cho danh sách khoá học. Ở chế độ lộ trình thì ẩn: lộ trình không
         gắn với một môn nào cả (một lộ trình có thể đi qua nhiều môn), bày ra là đánh lừa. --}}
    <div x-show="! showPaths" x-cloak class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-0.5">
        <button type="button" @click="setCategory('all')" :aria-pressed="selectedCategory === 'all'"
                class="flex min-h-10 items-center gap-1.5 rounded-2xl border px-3.5 py-2 text-[11px] font-bold transition-all whitespace-nowrap"
                :class="selectedCategory === 'all' ? 'border-[#126F91] bg-[#126F91] text-white shadow-[0_4px_10px_rgba(18,111,145,0.18)]' : 'bg-[#EAF5F8] border-[#C9DFE8] text-[#536D86] hover:brightness-[.98] hover:shadow-[0_2px_8px_rgba(28,91,121,0.06)]'">
            <span class="grid h-5 w-5 place-items-center rounded-lg" :class="selectedCategory === 'all' ? 'bg-white/15' : 'bg-white'">
                <x-lucide name="book-open" class="h-3.5 w-3.5" ::class="selectedCategory === 'all' ? 'text-white' : 'text-[#2D7FA3]'" />
            </span>
            <span>Tất cả lớp học</span>
        </button>
        @foreach ($subjects as $i => $subject)
            @php $tone = $chipTones[($i + 1) % count($chipTones)]; @endphp
            <button type="button" @click="setCategory(@js($subject))" :aria-pressed="selectedCategory === @js($subject)"
                    class="flex min-h-10 items-center gap-1.5 rounded-2xl border px-3.5 py-2 text-[11px] font-bold transition-all whitespace-nowrap"
                    :class="selectedCategory === @js($subject) ? 'border-[#126F91] bg-[#126F91] text-white shadow-[0_4px_10px_rgba(18,111,145,0.18)]' : '{{ $tone['surface'] }} text-[#536D86] hover:brightness-[.98] hover:shadow-[0_2px_8px_rgba(28,91,121,0.06)]'">
                <span class="grid h-5 w-5 place-items-center rounded-lg" :class="selectedCategory === @js($subject) ? 'bg-white/15' : 'bg-white'">
                    <x-lucide :name="$tone['icon']" class="h-3.5 w-3.5" ::class="selectedCategory === @js($subject) ? 'text-white' : '{{ $tone['iconTone'] }}'" />
                </span>
                <span>{{ $subject }}</span>
            </button>
        @endforeach
    </div>

    {{-- ══════ [COURSES-03B] LỘ TRÌNH CỦA KHỐI ĐANG CHỌN ══════
         SỬA 15/9 (khách chốt luồng) — chọn KHỐI LỚP thì trang hiện các LỘ TRÌNH của khối đó,
         không phải danh sách khoá. Bấm một lộ trình là sang thẳng trang chi tiết lộ trình.

         Vì sao đúng: phụ huynh không mua "khoá học số 3", họ chọn con đường cho con. Bắt họ
         nhìn danh sách khoá rời rạc trước khi biết chúng thuộc chặng nào là hỏi sai thứ tự. --}}
    <div x-show="showPaths" x-cloak class="flex flex-col gap-3">
        <div class="flex flex-wrap items-center justify-between gap-2 px-1">
            <div class="flex items-center gap-2">
                <span class="grid h-7 w-7 place-items-center rounded-lg bg-[#E9F7F8] text-[#23869B]">
                    <x-lucide name="route" class="h-4 w-4" />
                </span>
                <h2 class="text-[15px] font-black text-[#123B68]">
                    <template x-if="selectedGrade === 'all'"><span>Tất cả lộ trình</span></template>
                    <template x-if="selectedGrade !== 'all'"><span>Lộ trình cho <span x-text="selectedGrade"></span></span></template>
                </h2>
                <span class="rounded-lg bg-[#F5F8FA] px-2 py-0.5 text-[11px] font-bold text-[#536D86]"
                      x-text="filteredPaths.length + ' lộ trình'"></span>
            </div>

            <button type="button" x-show="selectedGrade !== 'all'" x-cloak @click="setGrade('all')"
                    class="inline-flex min-h-9 items-center gap-1 rounded-xl border border-[#DDEAF0] bg-white px-3 text-[11px] font-bold text-[#536D86] transition-colors hover:bg-[#F5F8FA]">
                <x-lucide name="list" class="h-3.5 w-3.5" />Xem tất cả lộ trình
            </button>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($learningPathFilters as $lp)
                <a href="{{ $lp['href'] }}"
                   x-show="filteredPaths.some((p) => p.id === {{ $lp['id'] }})" x-cloak
                   class="group flex h-full flex-col overflow-hidden rounded-3xl border border-[#DDEAF0] bg-white shadow-[0_4px_16px_rgba(28,91,121,0.05)] transition-all duration-300 hover:-translate-y-0.5 hover:border-[#B8DFE8] hover:shadow-[0_12px_28px_rgba(28,91,121,0.1)]">

                    {{-- Ảnh lộ trình quản trị tải lên; chưa có thì dựng dải màu các bậc. --}}
                    @if ($lp['coverUrl'])
                        <img src="{{ $lp['coverUrl'] }}" alt="Lộ trình {{ $lp['title'] }}" loading="lazy" decoding="async"
                             class="h-40 w-full object-cover transition-transform duration-300 group-hover:scale-[1.02]">
                    @else
                        <div class="flex h-40 w-full items-end gap-1 bg-gradient-to-br from-[#F3FAFC] to-[#E7F3F7] p-4">
                            @foreach (array_slice(\App\Support\LearningPathPalette::ramp(), 0, max(1, min(6, $lp['stepCount']))) as $i => $tone)
                                <span class="flex-1 rounded-t-lg" style="background: {{ $tone['solid'] }}; height: {{ 26 + $i * 13 }}%"></span>
                            @endforeach
                        </div>
                    @endif

                    <div class="flex min-w-0 flex-1 flex-col p-4">
                        @if ($lp['brand'])
                            <p class="text-[10px] font-black uppercase tracking-[.1em] text-[#2D7FA3]">{{ $lp['brand'] }}</p>
                        @endif

                        <h3 class="mt-1 text-[15px] font-black leading-snug text-[#123B68] transition-colors group-hover:text-[#126F91]">{{ $lp['title'] }}</h3>

                        @if ($lp['subtitle'])
                            <p class="mt-1 line-clamp-2 text-[11.5px] leading-relaxed text-[#536D86]">{{ $lp['subtitle'] }}</p>
                        @endif

                        <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
                            <span class="rounded-lg border border-[#CDE8EC] bg-[#E9F7F8] px-2 py-0.5 text-[10.5px] font-bold text-[#23869B]">{{ $lp['gradeLabel'] }}</span>
                            <span class="rounded-lg border border-[#DDEAF0] bg-[#F5F8FA] px-2 py-0.5 text-[10.5px] font-bold text-[#536D86]">{{ $lp['stepCount'] }} bậc</span>
                            @if ($lp['totalSessions'] > 0)
                                <span class="rounded-lg border border-[#DDEAF0] bg-[#F5F8FA] px-2 py-0.5 text-[10.5px] font-bold text-[#536D86]">{{ $lp['totalSessions'] }} buổi</span>
                            @endif
                        </div>

                        <div class="mt-3 rounded-xl border border-[#F2E4BD] bg-[#FFFBEF] px-2.5 py-2">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-[#A98436]">Mục tiêu đích</p>
                            <p class="mt-0.5 text-[11.5px] font-bold leading-snug text-[#765C18]">{{ $lp['goal'] }}</p>
                        </div>

                        <span class="mt-auto flex min-h-10 translate-y-3 items-center justify-center gap-1.5 rounded-xl bg-[#126F91] px-4 text-xs font-bold text-white transition-colors group-hover:bg-[#0F5E7C]">
                            Xem lộ trình <x-lucide name="chevron-right" class="h-3.5 w-3.5" />
                        </span>
                    </div>
                </a>
            @endforeach
        </div>

        {{-- Không có lộ trình nào khớp — nói rõ và cho lối quay lại, không để trang trắng. --}}
        <div x-show="filteredPaths.length === 0" x-cloak
             class="rounded-3xl border border-dashed border-[#C9DFE8] bg-white p-10 text-center">
            <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-[#EAF5F8] text-[#2D7FA3]">
                <x-lucide name="route" class="h-7 w-7" />
            </span>
            <h3 class="mt-3 text-sm font-black text-[#123B68]">
                <template x-if="selectedGrade !== 'all'"><span>Chưa có lộ trình cho <span x-text="selectedGrade"></span></span></template>
                <template x-if="selectedGrade === 'all'"><span>Chưa tìm thấy lộ trình phù hợp</span></template>
            </h3>
            <p class="mt-1 text-xs text-[#71869A]">Thử đổi khối lớp hoặc xoá từ khoá tìm kiếm.</p>
            <button type="button" @click="resetFilters()" class="mt-4 text-[11px] font-bold text-[#126F91] hover:underline">Xem tất cả lộ trình</button>
        </div>
    </div>

    {{-- Dòng đếm — chỉ có nghĩa khi đang xem danh sách khoá. --}}
    <div x-show="! showPaths" x-cloak class="flex items-center justify-between gap-3 px-1">
        <div class="flex items-center gap-2 text-[11px] text-[#71869A]">
            <x-lucide name="filter" class="h-3.5 w-3.5 text-[#2D7FA3]" />
            {{-- SỬA 15/9 — đếm cho đúng thứ đang hiển thị: mỗi thẻ là một KHOÁ HỌC, trong
                 khoá mới có các lớp. Trước ghi "N lớp học phù hợp" trong khi N là số khoá. --}}
            <span>Hiển thị <b class="text-[#536D86]" x-text="filtered.length"></b> khoá học @if ($totalOpenClasses ?? 0) · {{ $totalOpenClasses }} lớp đang mở @endif</span>
        </div>
        <span class="hidden text-[11px] text-[#8A9BAD] sm:inline">Cập nhật theo mục tiêu học tập của bạn</span>
    </div>

    {{-- ══════ [COURSES-04] LƯỚI KHOÁ HỌC ══════
         Ẩn khi đang ở chế độ xem lộ trình — khách chốt: chọn khối lớp thì hiện lộ trình,
         không hiện khoá. --}}
    <div x-show="! showPaths" x-cloak class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:gap-5 xl:grid-cols-3">
        @foreach ($courses as $i => $course)
            @php
                $cover = $course['image'] ?: asset('assets/'.$fallbackCovers[$course['id'] % count($fallbackCovers)]);
                $featured = ($course['average'] ?? 0) >= 4.5;

                /* SỬA 15/9 — nút lấy nguyên từ CourseService::mapCourseCard (nhãn + đường dẫn
                   + kiểu đi cùng nhau). Trước view tự đoán lại nhãn và màu từ trạng thái, nên
                   thêm một trạng thái mới là phải nhớ sửa ở hai nơi. */
                $cta = $course['cta'];
                $btnClass = match ($cta['tone']) {
                    'muted' => 'cursor-not-allowed bg-[#F1F4F6] text-[#8A9BAD]',
                    'go' => 'bg-gradient-to-r from-[#2D7FA3] to-[#3B9374] text-white shadow-[0_5px_12px_rgba(45,127,163,0.18)] hover:brightness-105',
                    'buy' => 'bg-gradient-to-r from-[#B8791C] to-[#D79A2B] text-white shadow-[0_5px_12px_rgba(184,121,28,0.2)] hover:brightness-105',
                    default => 'bg-gradient-to-r from-[#126F91] to-[#188DB0] text-white shadow-[0_5px_12px_rgba(18,111,145,0.18)] hover:from-[#0F607E] hover:to-[#147D9B]',
                };
                $btnIcon = match ($cta['tone']) {
                    'muted' => 'lock-keyhole',
                    'go' => 'play-circle',
                    'buy' => 'banknote',
                    default => 'user-plus',
                };
            @endphp
            <div x-show="visibleIds.includes({{ $course['id'] }})" x-cloak
                 :style="'order:' + visibleIds.indexOf({{ $course['id'] }})"
                 class="group flex min-h-full flex-col justify-between overflow-hidden rounded-3xl border border-[#DDEAF0] bg-white shadow-[0_4px_16px_rgba(28,91,121,0.05)] transition-all duration-300 hover:-translate-y-0.5 hover:border-[#C9DFE8] hover:shadow-[0_12px_28px_rgba(28,91,121,0.1)]">
                <div>
                    {{-- Ảnh thẻ --}}
                    <div class="relative h-44 overflow-hidden bg-[#F5F8FA] sm:h-48">
                        <div class="absolute inset-x-0 top-0 z-10 h-1 {{ $featured ? 'bg-gradient-to-r from-[#F6C453] via-[#F9D778] to-[#2D7FA3]' : 'bg-gradient-to-r from-[#78C7D5] to-[#B7E5E7]' }}"></div>
                        <img src="{{ $cover }}" alt="{{ $course['title'] }}"
                             class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.03]">
                        @if ($course['subject'])
                            <div class="absolute top-3 left-3 flex flex-wrap gap-1.5">
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-[#C9DFE8] bg-white/95 px-2.5 py-1 text-[11px] font-bold text-[#216F8E] shadow-sm backdrop-blur-sm">
                                    <span class="grid h-5 w-5 place-items-center rounded-md bg-[#EAF5F8]">
                                        <x-lucide :name="$featured ? 'trophy' : 'sparkles'" class="h-3.5 w-3.5 {{ $featured ? 'text-[#AF7C32]' : 'text-[#2D7FA3]' }}" />
                                    </span>
                                    {{ $course['subject'] }}
                                </span>
                            </div>
                        @endif
                        <div class="absolute bottom-3 left-3 right-3 flex items-center justify-between rounded-xl bg-slate-900/60 px-3 py-1.5 text-[11px] font-bold text-white backdrop-blur-sm">
                            <span class="flex items-center gap-1">
                                <x-lucide name="clock" class="w-3.5 h-3.5 text-amber-300" />
                                <span>{{ $course['classCount'] }} lớp đang mở</span>
                            </span>
                            <span class="flex items-center gap-1">
                                <x-lucide name="users" class="w-3.5 h-3.5 text-sky-300" />
                                <span>{{ number_format($course['studentCount']) }} học viên</span>
                            </span>
                        </div>
                    </div>

                    {{-- Thông tin --}}
                    <div class="p-4 sm:p-5">
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-lg border border-[#C9DFE8] bg-[#EAF5F8] px-2 py-1 text-[11px] font-extrabold text-[#216F8E]">
                                <x-lucide name="graduation-cap" class="h-3.5 w-3.5 text-[#2D7FA3]" />
                                {{ $course['grade'] ?: 'Mọi khối' }}
                            </span>
                            <div class="flex items-center gap-1 text-[11px] font-bold text-[#AF7C32]">
                                <x-lucide name="star" class="w-3.5 h-3.5 fill-amber-400 text-amber-400" />
                                <span>{{ $course['average'] !== null ? number_format($course['average'], 1) : '—' }}</span>
                                <span class="text-[11px] font-medium text-[#8A9BAD]">({{ $course['count'] }})</span>
                            </div>
                        </div>

                        {{-- SỬA 15/9 — nhãn cũ ghi "Khóa học: Toán" là SAI: $subject là MÔN
                             học, còn tên khoá học nằm ở dòng tiêu đề ngay bên dưới. --}}
                        @if ($course['subject'])
                            <div class="mb-1.5 flex min-w-0 items-center gap-1.5 text-[11px] text-[#536D86]">
                                <span class="grid h-5 w-5 shrink-0 place-items-center rounded-md bg-[#EEF5FF]"><x-lucide name="book-open" class="h-3.5 w-3.5 text-[#4C83B0]" /></span>
                                <span class="shrink-0">Môn:</span>
                                <button type="button" @click="setCategory(@js($course['subject']))" title="Lọc theo môn: {{ $course['subject'] }}"
                                        class="min-w-0 truncate text-left font-bold text-[#2D7FA3] underline-offset-2 transition hover:text-[#126F91] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#CBEAF1]">{{ $course['subject'] }}</button>
                            </div>
                        @endif

                        <h3 class="mb-1.5 line-clamp-2 text-sm font-extrabold leading-5 text-[#123B68] transition-colors group-hover:text-[#126F91] sm:text-[15px]">{{ $course['title'] }}</h3>

                        {{-- Mô tả khóa học lưu dưới dạng HTML (soạn ở admin) — bỏ thẻ và gộp khoảng trắng
                             để thẻ chỉ hiện phần chữ, không lộ "<p>" ra màn hình. --}}
                        <p class="type-body mb-3 line-clamp-2">{{ \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $course['subtitle']))) ?: 'Khóa học của Ôn Thi 360.', 160) }}</p>

                        {{-- ── Số liệu thật của khoá ──
                             SỬA 15/9 — bản cũ in cứng "Trực tuyến" và "Có chấm bài tự động"
                             cho MỌI thẻ; không có dữ liệu nào đứng sau hai dòng đó, nên chúng
                             chỉ làm thẻ dài ra chứ không nói thêm được gì. Thay bằng những con
                             số có thật: số buổi theo chương trình, số lớp, sĩ số, học phí. --}}
                        <div class="mb-3 grid grid-cols-2 gap-x-3 gap-y-2 border-y border-[#E7EFF3] py-3 text-[11px] text-[#536D86]">
                            @if ($course['sessionCount'] > 0)
                                <div class="flex min-w-0 items-center gap-1.5">
                                    <x-lucide name="calendar-days" class="h-3.5 w-3.5 shrink-0 text-[#2D7FA3]" />
                                    <span class="truncate">{{ $course['sessionCount'] }} buổi</span>
                                </div>
                            @endif
                            <div class="flex min-w-0 items-center gap-1.5">
                                <x-lucide name="school" class="h-3.5 w-3.5 shrink-0 text-[#3B9374]" />
                                <span class="truncate">{{ $course['classCount'] > 0 ? $course['classCount'].' lớp đang mở' : 'Chưa có lớp mở' }}</span>
                            </div>
                            <div class="flex min-w-0 items-center gap-1.5">
                                <x-lucide name="users" class="h-3.5 w-3.5 shrink-0 text-[#4C83B0]" />
                                <span class="truncate">{{ number_format($course['studentCount']) }} học viên</span>
                            </div>
                            @if ($course['priceLabel'])
                                <div class="flex min-w-0 items-center gap-1.5">
                                    <x-lucide name="banknote" class="h-3.5 w-3.5 shrink-0 text-[#AF7C32]" />
                                    <span class="truncate font-bold text-[#8A6A1C]">{{ $course['priceLabel'] }}</span>
                                </div>
                            @endif
                        </div>

                        {{-- ── Các lớp đang mở ──
                             SỬA 15/9 — bản cũ chỉ in mã của LỚP ĐẦU TIÊN ("Mã lớp · X"). Khoá
                             có ba lớp thì hai lớp kia biến mất, mà người đọc lại tưởng khoá chỉ
                             có đúng một lớp và ghi nhầm mã đó. Giờ liệt kê tối đa 3 lớp, còn
                             lại gom vào một dòng. --}}
                        @if (count($course['classes']) > 0)
                            <div class="mb-3">
                                <p class="type-meta mb-1.5">Lớp đang mở</p>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach (array_slice($course['classes'], 0, 3) as $cl)
                                        <span class="inline-flex items-center gap-1 rounded-lg border px-2 py-1 text-[10.5px] font-bold
                                                     {{ $cl['isMember'] ? 'border-[#CDE9DC] bg-[#F1FAF6] text-[#2C7D5F]' : 'border-[#DDEAF0] bg-[#F8FAFB] text-[#536D86]' }}"
                                              title="{{ $cl['name'] }} · {{ $cl['studentsCount'] }} học viên">
                                            @if ($cl['isMember'])
                                                <x-lucide name="check-circle" class="h-3 w-3" />
                                            @endif
                                            {{ $cl['code'] }}
                                        </span>
                                    @endforeach
                                    @if (count($course['classes']) > 3)
                                        <a href="{{ $course['href'] }}" class="inline-flex items-center rounded-lg border border-dashed border-[#C9DFE8] px-2 py-1 text-[10.5px] font-bold text-[#2D7FA3] hover:bg-[#F2F8F9]">
                                            +{{ count($course['classes']) - 3 }} lớp khác
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- ── Giáo viên phụ trách ──
                             SỬA 15/9 — bản cũ gắn ảnh teacher-thanh.png cho MỌI giáo viên và
                             ảnh testi-av-*.png cho trợ giảng: tên thật của người này đi kèm
                             mặt của người khác. Dùng x-ws.avatar vẽ chữ cái đầu ngay tại chỗ,
                             không gán nhầm mặt ai và không gửi tên ra dịch vụ ngoài.
                             Tên cũng gộp từ MỌI lớp của khoá, không chỉ lớp đầu tiên. --}}
                        <div class="flex items-center gap-2.5 rounded-2xl border border-[#DDEAF0] bg-[#F8FAFB] p-2.5">
                            @if (count($course['teacherNames']) > 0)
                                <div class="flex -space-x-2">
                                    @foreach (array_slice($course['teacherNames'], 0, 3) as $name)
                                        <span class="ring-2 ring-white rounded-full" title="{{ $name }}">
                                            <x-ws.avatar :name="$name" size="sm" />
                                        </span>
                                    @endforeach
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-[11px] font-bold text-slate-800">{{ $course['teacherNames'][0] }}</p>
                                    <p class="truncate text-[11px] text-[#71869A]">
                                        {{ count($course['teacherNames']) > 1
                                            ? 'và '.(count($course['teacherNames']) - 1).' giáo viên khác'
                                            : 'Giáo viên phụ trách' }}
                                    </p>
                                </div>
                            @else
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full border border-[#DDEAF0] bg-white text-[#8A9BAD]">
                                    <x-lucide name="user-round" class="h-4 w-4" />
                                </span>
                                <p class="text-[11px] font-bold text-[#71869A]">Đang phân công giáo viên</p>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Hành động cuối thẻ --}}
                <div class="p-4 pt-0">
                    <div class="flex items-center justify-between gap-3 border-t border-[#DDEAF0] pt-3">
                        <div class="min-w-0">
                            @if ($course['hasRight'])
                                <p class="type-meta">Bạn đã có quyền học</p>
                                <p class="text-[13px] font-black text-[#2C7D5F]">Chọn lớp để vào học</p>
                            @else
                                <p class="type-meta">Quy mô</p>
                                <p class="text-sm font-black text-[#123B68]">{{ $course['classCount'] }} lớp</p>
                            @endif
                            <p class="type-meta mt-0.5 max-w-[150px] truncate">{{ $course['meta'] }}</p>
                        </div>

                        {{-- Nhãn, đường dẫn và kiểu đều lấy từ $cta do service quyết định. --}}
                        @if ($cta['tone'] === 'muted')
                            <span class="flex min-h-10 shrink-0 items-center gap-1.5 rounded-xl px-3.5 py-2 text-[11px] font-extrabold {{ $btnClass }}">
                                <span>{{ $cta['label'] }}</span>
                                <x-lucide :name="$btnIcon" class="h-3.5 w-3.5" />
                            </span>
                        @else
                            <a href="{{ $cta['href'] }}"
                               class="flex min-h-10 shrink-0 items-center gap-1.5 rounded-xl px-3.5 py-2 text-[11px] font-extrabold transition-all focus:outline-none focus-visible:ring-4 focus-visible:ring-[#CBEAF1] active:scale-[.98] {{ $btnClass }}">
                                <span>{{ $cta['label'] }}</span>
                                <x-lucide :name="$btnIcon" class="h-3.5 w-3.5" />
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ══════ [COURSES-05] PHÂN TRANG ══════ --}}
    <nav aria-label="Phân trang khoá học" x-show="! showPaths && filtered.length > pageSize" x-cloak
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
    <div x-show="! showPaths && filtered.length === 0" x-cloak class="rounded-3xl border border-dashed border-[#C9DFE8] bg-white p-10 text-center">
        <x-lucide name="search" class="mx-auto h-9 w-9 text-[#9DC8D7]" />
        <h2 class="mt-3 text-sm font-black text-[#123B68]">Không tìm thấy khoá học phù hợp</h2>
        <p class="mt-1 text-xs text-[#71869A]">Thử đổi khối lớp, danh mục hoặc từ khóa tìm kiếm.</p>
        <button type="button" @click="resetFilters()" class="mt-4 text-[11px] font-bold text-[#126F91] hover:underline">Xóa bộ lọc</button>
    </div>
</div>
</div>
@endsection

@push('scripts')
    @include('partials.courses-script')
@endpush
