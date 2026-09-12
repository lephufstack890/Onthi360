@extends('layouts.guest')

@section('title', 'Luyện thi Tin học - Học cùng mục tiêu, Vươn xa ước mơ')
@section('meta-description', 'Ôn Thi 360 — nền tảng luyện thi Tin học: lớp học, bài tập tự luyện theo chuyên đề, tài liệu, cuộc thi và bảng xếp hạng cho học sinh lớp 6–12.')

@section('content')
{{-- ═══════════════ TRANG CHỦ ═══════════════
     SỬA 11/9 — dựng lại theo ĐÚNG source giao diện khách gửi:
     education-main/src/components/HomePage.jsx (sơ đồ khối HOME-01 … HOME-11).
     Toàn bộ class/bố cục chép nguyên; phần động của React đổi sang Alpine và MỌI nút bấm
     đều gắn LINK THẬT sang trang tương ứng ("làm UI thì nhớ gắn link đầy đủ").

     Dữ liệu lấy từ cơ sở dữ liệu (App\Services\Public\HomeService):
       · HOME-01 Thông báo hệ thống  <- $systemNotices       (cuộc thi/tài liệu/khoá học mới nhất)
       · HOME-06 Không gian học tập  <- $learningSpace       (số liệu thật của người đang đăng nhập)
       · HOME-07 Top xuất sắc        <- $topStudents         (leaderboard_entries, ẩn danh)
       · HOME-07 Thông báo           <- $bellItems
       · HOME-08 Tài liệu nổi bật    <- $featuredMaterials
       · HOME-09 Cuộc thi & khảo sát <- $upcomingCompetitions
       · HOME-11 Câu hỏi thường gặp  <- $faqs
     Nội dung marketing của bản mẫu (hero 4 mục tiêu, 5 thẻ chương trình, 5 bước lộ trình,
     3 câu chuyện đồng hành) là NỘI DUNG THIẾT KẾ, không phải dữ liệu nghiệp vụ — giữ nguyên
     theo source, ảnh nằm ở public/assets/. --}}
@php
    $systemNotices = $systemNotices ?? [];
    $featuredMaterials = $featuredMaterials ?? [];
    $upcomingCompetitions = $upcomingCompetitions ?? [];
    $faqs = $faqs ?? [];
    $topStudents = $topStudents ?? ['title' => null, 'rows' => []];
    $learningSpace = $learningSpace ?? ['guest' => true];
    $bellItems = $bellItems ?? [];

    // [HOME-03] 4 slide hero — chép đúng nội dung, màu và ảnh của source.
    $heroSlides = [
        [
            'id' => 'hsg', 'tabTitle' => 'Giải HSG', 'tag' => '🏆 Đấu trường đỉnh cao',
            'tagStyle' => 'bg-amber-100/90 text-amber-800 border-amber-300',
            'subtitle' => 'Chinh phục HSG & Olympic Tin học',
            'description' => 'Học theo chuyên đề trọng tâm, luyện đề phân cấp và từng bước tiến tới đội tuyển.',
            'panelStyle' => 'border-[#EBCF73] bg-gradient-to-br from-[#FFF2BA]/94 via-[#FFF9E2]/92 to-white/86',
            'panelIconStyle' => 'border-[#E8C75D] bg-[#FFE89A] text-[#9A6508]',
            'itemIconStyle' => 'bg-[#FFF0B8] text-[#A86D08]',
            'panelIcon' => 'trophy',
            'highlights' => [
                ['label' => 'HSG Tin học lớp 9', 'icon' => 'book-open'],
                ['label' => 'HSG Quốc gia lớp 12', 'icon' => 'trophy'],
                ['label' => 'Olympic Tin học', 'icon' => 'code'],
                ['label' => 'Bồi dưỡng đội tuyển', 'icon' => 'award'],
            ],
            'defaultGoal' => 'Luyện thi HSG Tin học lớp 9',
            'bgImage' => asset('assets/hero-banner-hsg.jpg'),
        ],
        [
            'id' => 'chuyen-tin', 'tabTitle' => 'Chuyên Tin', 'tag' => '🎯 Mục tiêu trường Chuyên',
            'tagStyle' => 'bg-blue-100/90 text-blue-800 border-blue-300',
            'subtitle' => 'Tự tin đỗ lớp 10 Chuyên Tin',
            'description' => 'Củng cố thuật toán, rèn kỹ năng làm bài và luyện đề theo từng trường mục tiêu.',
            'panelStyle' => 'border-[#AFCFED] bg-gradient-to-br from-[#DDEEFF]/94 via-[#EEF7FF]/92 to-white/86',
            'panelIconStyle' => 'border-[#A5C9EA] bg-[#CFE8FF] text-[#17669E]',
            'itemIconStyle' => 'bg-[#DBEEFF] text-[#176AAB]',
            'panelIcon' => 'target',
            'highlights' => [
                ['label' => 'Chuyên KHTN', 'icon' => 'code'],
                ['label' => 'Chuyên Sư phạm', 'icon' => 'book-open'],
                ['label' => 'Chuyên Amsterdam', 'icon' => 'target'],
                ['label' => 'Chuyên tỉnh/TP', 'icon' => 'graduation-cap'],
            ],
            'defaultGoal' => 'Luyện thi vào lớp 10 chuyên Tin',
            'bgImage' => asset('assets/hero-banner-chuyen.jpg'),
        ],
        [
            'id' => 'tot-nghiep', 'tabTitle' => 'Tốt nghiệp 9+', 'tag' => '⚡ Bứt phá điểm 9+',
            'tagStyle' => 'bg-emerald-100/90 text-emerald-800 border-emerald-300',
            'subtitle' => 'Bứt phá điểm 9+ môn Tin học',
            'description' => 'Hệ thống hóa lý thuyết, luyện đề bám cấu trúc kỳ thi 2025–2026 và theo dõi tiến bộ.',
            'panelStyle' => 'border-[#A9DDC9] bg-gradient-to-br from-[#D9F6EA]/94 via-[#ECFBF5]/92 to-white/86',
            'panelIconStyle' => 'border-[#98D7BE] bg-[#C9EFDF] text-[#14785A]',
            'itemIconStyle' => 'bg-[#D8F4E8] text-[#168062]',
            'panelIcon' => 'bar-chart',
            'highlights' => [
                ['label' => 'Mục tiêu 9+', 'icon' => 'target'],
                ['label' => '100+ đề bám cấu trúc', 'icon' => 'file-text'],
                ['label' => 'Lý thuyết trọng tâm', 'icon' => 'book-open'],
                ['label' => 'Chấm điểm tự động', 'icon' => 'bar-chart'],
            ],
            'defaultGoal' => 'Ôn thi tốt nghiệp môn Tin học',
            'bgImage' => asset('assets/hero-banner-totnghiep.jpg'),
        ],
        [
            'id' => 'du-hoc', 'tabTitle' => 'Du học & AP CS', 'tag' => '✈️ Vươn ra thế giới',
            'tagStyle' => 'bg-purple-100/90 text-purple-800 border-purple-300',
            'subtitle' => 'Sẵn sàng cho AP CS, USACO & học bổng',
            'description' => 'Xây nền thuật toán, luyện chuẩn quốc tế và hoàn thiện hồ sơ công nghệ có định hướng.',
            'panelStyle' => 'border-[#CDBDEB] bg-gradient-to-br from-[#E9E0FA]/94 via-[#F5F0FF]/92 to-white/86',
            'panelIconStyle' => 'border-[#C7B4E8] bg-[#DFD1F6] text-[#7050A5]',
            'itemIconStyle' => 'bg-[#EBE2FA] text-[#7653AD]',
            'panelIcon' => 'graduation-cap',
            'highlights' => [
                ['label' => 'USACO Bronze–Gold', 'icon' => 'code'],
                ['label' => 'AP Computer Science A', 'icon' => 'book-open'],
                ['label' => 'Portfolio công nghệ', 'icon' => 'file-text'],
                ['label' => 'Định hướng học bổng', 'icon' => 'graduation-cap'],
            ],
            'defaultGoal' => 'Học trước chương trình Tin học để du học',
            'bgImage' => asset('assets/hero-banner-duhoc.jpg'),
        ],
    ];

    // [HOME-05A] 5 thẻ "Chương trình học nổi bật" — mỗi thẻ dẫn sang đúng trang thật.
    $featuredPrograms = [
        ['title' => 'Bài tập tự luyện', 'desc' => 'Hệ thống bài tập tự luyện phong phú theo từng chuyên đề từ cơ bản đến nâng cao', 'btnText' => 'Luyện tập ngay', 'bgClass' => 'from-[#EAF4FE] to-[#D8EAFD] border-[#BAE0FD]', 'image' => asset('assets/course-img-1.png'), 'href' => route('practice.index')],
        ['title' => 'Tài liệu', 'desc' => 'Sách, chuyên đề, bộ đề, giáo viên và chuyên gia uy tín hỗ trợ và đồng hành', 'btnText' => 'Khám phá', 'bgClass' => 'from-[#E8FBF6] to-[#D0F5E7] border-[#A7F3D0]', 'image' => asset('assets/course-img-2.png'), 'href' => route('materials.index')],
        ['title' => 'Lớp học', 'desc' => 'Lớp học chuyên nghiệp, quản lý và theo dõi tiến độ học sinh chuẩn mực', 'btnText' => 'Vào lớp học', 'bgClass' => 'from-[#F3EFFF] to-[#E5DEFF] border-[#DDD6FE]', 'image' => asset('assets/course-img-3.png'), 'href' => route('courses.index')],
        ['title' => 'Giáo viên & Chuyên gia', 'desc' => 'Đội ngũ giáo viên chuyên nghiệp & chuyên gia uy tín đồng hành tận tâm', 'btnText' => 'Xem đội ngũ', 'bgClass' => 'from-[#FFF7E6] to-[#FEEAD0] border-[#FED7AA]', 'image' => asset('assets/course-img-4.png'), 'href' => route('teachers.index')],
        ['title' => 'Cuộc thi', 'desc' => 'Cuộc thi, khảo sát được tổ chức thường xuyên và công bằng', 'btnText' => 'Tìm hiểu', 'bgClass' => 'from-[#E6F7FF] to-[#CCEFFF] border-[#BAE6FD]', 'image' => asset('assets/course-img-5.png'), 'href' => route('competitions.index')],
    ];

    // [HOME-05B] 5 bước "Lộ trình học chuyên nghiệp".
    $learningSteps = [
        ['step' => '1. Lựa chọn mục tiêu', 'desc' => 'Chọn mục tiêu lớp phù hợp', 'img' => asset('assets/step-1.png'), 'href' => route('courses.index')],
        ['step' => '2. Chọn lộ trình phù hợp', 'desc' => 'Học theo năng lực & mục tiêu', 'img' => asset('assets/step-2.png'), 'href' => route('courses.index')],
        ['step' => '3. Luyện tập & học liệu', 'desc' => 'Bài tập, giáo trình, chuyên đề', 'img' => asset('assets/step-3.png'), 'href' => route('practice.index')],
        ['step' => '4. Lớp học & giáo viên', 'desc' => 'Học cùng giáo viên, nhận hỗ trợ', 'img' => asset('assets/step-4.png'), 'href' => route('teachers.index')],
        ['step' => '5. Thi & Đánh giá', 'desc' => 'Cuộc thi, đánh giá phát năng lực', 'img' => asset('assets/step-5.png'), 'href' => route('competitions.index')],
    ];

    // [HOME-10] 3 câu chuyện đồng hành — nội dung thiết kế của bản mẫu.
    $testimonials = [
        ['quote' => '“ Nhờ Ôn Thi 360, mình tự tin hơn rất nhiều trong học tập và đạt kết quả tốt ở kỳ thi HSG cấp tỉnh. Nền tảng giúp mình có lộ trình rõ ràng và bài tập chất lượng. ”', 'author' => 'Nguyễn Hà Phương', 'role' => 'Học sinh lớp 12', 'banner' => asset('assets/testi-banner-1.png'), 'avatar' => asset('assets/testi-av-1.png')],
        ['quote' => '“ Tôi rất yên tâm khi con học tại Ôn Thi 360. Con tiến bộ rõ rệt, chúng tôi có thể theo dõi tiến độ và nhận được sự hỗ trợ tận tình từ đội ngũ giáo viên. ”', 'author' => 'Chị Trần Thị Mai', 'role' => 'Phụ huynh học sinh', 'banner' => asset('assets/testi-banner-2.png'), 'avatar' => asset('assets/testi-av-2.png')],
        ['quote' => '“ Ôn Thi 360 là nền tảng hữu ích, giúp học sinh tiếp cận kiến thức Tin học một cách hệ thống, hiện đại và hiệu quả. ”', 'author' => 'Thầy Lê Minh Đức', 'role' => 'Giáo viên Tin học', 'banner' => asset('assets/testi-banner-3.png'), 'avatar' => asset('assets/testi-av-3.png')],
    ];

    // [HOME-02A] Menu trái — cùng bộ mục với header, dẫn sang link thật.
    $homeSideNav = [
        ['label' => 'Trang chủ', 'route' => 'home', 'icon' => 'home'],
        ['label' => 'Lớp học', 'route' => 'courses.index', 'icon' => 'book-open'],
        ['label' => 'Luyện tập', 'route' => 'practice.index', 'icon' => 'code'],
        ['label' => 'Tài liệu', 'route' => 'materials.index', 'icon' => 'file-text'],
        ['label' => 'Cuộc thi', 'route' => 'competitions.index', 'icon' => 'trophy'],
        ['label' => 'Bảng xếp hạng', 'route' => 'leaderboard.index', 'icon' => 'bar-chart'],
        ['label' => 'Giáo viên & chuyên gia', 'route' => 'teachers.index', 'icon' => 'users'],
        ['label' => 'Thông tin', 'route' => 'info.index', 'icon' => 'info'],
    ];

    $homeState = [
        'noticeCount' => max(count($systemNotices), 1),
        'slideCount' => count($heroSlides),
        'courseCount' => count($featuredPrograms),
        'pathCount' => count($learningSteps),
        'grades' => ['Lớp 10', 'Lớp 9', 'Lớp 11', 'Lớp 12'],
        'goals' => array_column($heroSlides, 'defaultGoal'),
        // SỬA 12/9 — vai trò đang xem ở khối [HOME-06A]; mặc định là vai trò chính của người
        // đang đăng nhập (khách thì không dùng tới).
        'activeRole' => $learningSpace['defaultRole'] ?? 'student',
        // Ảnh nền hero — chỉ tải ảnh của slide đang xem (xem [HOME-03]).
        'heroImages' => array_column($heroSlides, 'bgImage'),
        'heroAlts' => array_map(fn ($slide) => $slide['subtitle'].'. '.$slide['description'], $heroSlides),
    ];
@endphp

<div class="max-w-[1780px] w-full mx-auto px-3 sm:px-5 lg:px-6 2xl:px-10 py-3 sm:py-5">
<div x-data="onthiHomePage({{ Js::from($homeState) }})" class="home-typography flex flex-col gap-4 sm:gap-5 animate-fadeIn">

    {{-- ══════ [HOME-01] THANH THÔNG BÁO HỆ THỐNG ══════
         SỬA 12/9 — source mới đổi hẳn tông: nền xanh đặc #285B78, chữ trắng, vạch vàng cố
         định bên trái và viên phân loại cố định (không còn đổi màu theo từng loại tin nữa). --}}
    @if (count($systemNotices) > 0)
        <div @mouseenter="noticePaused = true" @mouseleave="noticePaused = false"
             @focusin="noticePaused = true" @focusout="noticePaused = false"
             aria-label="Thông báo hệ thống"
             class="relative flex items-center justify-between overflow-hidden rounded-2xl border border-[#4A7890] bg-[#285B78] px-3.5 py-2.5 text-white shadow-[0_3px_10px_rgba(40,91,120,0.13)] transition-colors duration-500 sm:px-5 sm:py-3">
            <div class="flex items-center gap-2 sm:gap-3 overflow-hidden text-xs sm:text-[13px] min-w-0">
                <span class="absolute bottom-2 left-0 top-2 w-1 rounded-r-full bg-amber-300" aria-hidden="true"></span>
                <span class="flex h-5 w-5 shrink-0 items-center justify-center text-amber-300 animate-pulse" aria-hidden="true">
                    <x-lucide name="bell" class="h-4 w-4" />
                </span>
                <span class="shrink-0 text-xs font-bold text-white sm:text-sm">Thông báo hệ thống</span>
                <span class="text-sky-200">|</span>
                @foreach ($systemNotices as $i => $n)
                    <span x-show="noticeIndex === {{ $i }}" x-cloak
                          class="hidden shrink-0 items-center rounded-full border border-white/20 bg-white/10 px-2 py-0.5 text-[10px] font-bold leading-tight text-sky-50 animate-fadeIn sm:inline-flex">{{ $n['category'] }}</span>
                @endforeach
                @foreach ($systemNotices as $i => $n)
                    <a x-show="noticeIndex === {{ $i }}" x-cloak href="{{ $n['href'] }}" aria-live="polite"
                       class="min-w-0 truncate text-xs font-medium text-white animate-fadeIn hover:underline sm:text-sm">{{ $n['message'] }}</a>
                @endforeach
            </div>
            <div class="ml-2 flex shrink-0 items-center gap-1 text-sky-200 sm:gap-2">
                <button type="button" aria-label="Thông báo trước" @click="prevNotice()"
                        class="cursor-pointer rounded-full p-1 transition-colors hover:bg-white/15 hover:text-white">
                    <x-lucide name="chevron-left" class="w-4 h-4 sm:w-5 sm:h-5" />
                </button>
                <span class="hidden min-w-7 text-center text-[10px] font-semibold tabular-nums text-sky-200 sm:inline"
                      x-text="(noticeIndex + 1) + '/' + noticeCount"></span>
                <button type="button" aria-label="Thông báo tiếp theo" @click="nextNotice()"
                        class="cursor-pointer rounded-full p-1 transition-colors hover:bg-white/15 hover:text-white">
                    <x-lucide name="chevron-right" class="w-4 h-4 sm:w-5 sm:h-5" />
                </button>
            </div>
        </div>
    @endif

    {{-- ══════ [HOME-LAYOUT] LƯỚI CHÍNH 3 CỘT ══════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-[210px_1fr_280px] xl:grid-cols-[240px_1fr_310px] 2xl:grid-cols-[260px_1fr_360px] gap-3.5 sm:gap-4 xl:gap-5 2xl:gap-6 items-start">

        {{-- ══════ [HOME-02] MENU TRÁI ══════ --}}
        <aside class="hidden lg:flex flex-col gap-3.5 xl:gap-4 shrink-0">
            {{-- [HOME-02A] Danh sách điều hướng --}}
            <div class="bg-white rounded-3xl p-3 border border-sky-100 shadow-[0_2px_8px_rgba(0,100,220,0.04)] flex flex-col gap-1.5">
                @foreach ($homeSideNav as $item)
                    @php $isSelected = request()->routeIs($item['route']); @endphp
                    <a href="{{ route($item['route']) }}"
                       class="flex items-center gap-3 px-3.5 py-2.5 rounded-2xl text-xs 2xl:text-[13px] font-semibold leading-snug transition-all text-left cursor-pointer {{ $isSelected ? 'bg-[#E6F3FF] text-[#0066CC] font-bold shadow-2xs' : 'text-slate-700 hover:bg-sky-50 hover:text-blue-600' }}">
                        <x-lucide :name="$item['icon']" class="w-4 h-4 xl:w-4.5 xl:h-4.5 shrink-0 {{ $isSelected ? 'text-[#0066CC]' : 'text-blue-600' }}" />
                        <span class="whitespace-nowrap truncate">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>

            {{-- [HOME-02B] Banner “Cùng nhau kiến tạo tương lai số” --}}
            <a href="{{ route('courses.index') }}"
               class="bg-white rounded-3xl overflow-hidden border border-sky-100 shadow-[0_2px_8px_rgba(0,100,220,0.04)] cursor-pointer hover:shadow-md transition-all group block">
                <img src="{{ asset('assets/sidebar-plane.jpg') }}" alt="Cùng nhau kiến tạo tương lai số"
                     class="w-full object-cover group-hover:scale-102 transition-transform duration-300">
            </a>

            {{-- [HOME-02C] Thẻ giáo viên tiêu biểu --}}
            <a href="{{ route('teachers.index') }}"
               class="relative block min-h-[190px] xl:min-h-[205px] overflow-hidden rounded-3xl border border-sky-200/90 bg-gradient-to-br from-white via-[#F3FAFF] to-[#E4F3FF] p-3.5 shadow-[0_8px_24px_rgba(0,100,220,0.09)] transition-all duration-300 hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-[0_12px_30px_rgba(0,100,220,0.14)] cursor-pointer group">
                <span class="absolute -right-8 -top-8 h-24 w-24 rounded-full bg-sky-200/35 blur-sm transition-transform duration-500 group-hover:scale-125"></span>
                <span class="absolute -bottom-10 -left-8 h-24 w-24 rounded-full bg-blue-100/60 blur-md"></span>

                <div class="relative z-10 flex min-h-[160px] xl:min-h-[175px] flex-col">
                    <div class="flex items-center gap-2.5 text-left">
                        <div class="relative shrink-0">
                            <span class="absolute inset-0 rounded-full bg-gradient-to-br from-amber-300 to-blue-500 blur-[2px] opacity-70"></span>
                            <img src="{{ asset('assets/testi-av-3.png') }}" alt="Giáo viên tiêu biểu"
                                 class="relative w-11 h-11 rounded-full border-2 border-white object-cover shadow-md transition-transform duration-300 group-hover:scale-105">
                            <span class="absolute -bottom-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full border-2 border-white bg-blue-600 text-white">
                                <x-lucide name="award" class="h-2.5 w-2.5" />
                            </span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h5 class="type-card-title text-[#073B78] group-hover:text-blue-600 transition-colors">Giáo viên tiêu biểu</h5>
                            <p class="type-meta mt-0.5 text-slate-500">Đội ngũ đã được<br>thẩm định và duyệt</p>
                        </div>
                    </div>

                    <div class="my-3 h-px bg-gradient-to-r from-transparent via-sky-300 to-transparent"></div>

                    <div class="relative flex-1 px-2 text-center">
                        <x-lucide name="sparkles" class="absolute -left-0.5 top-0 h-3.5 w-3.5 text-amber-400" />
                        <span class="absolute -right-1 -top-3 select-none text-4xl font-black leading-none text-blue-100">“</span>
                        <p class="font-script relative text-[17px] xl:text-[19px] font-bold leading-[1.15] text-[#07549A] transition-colors group-hover:text-blue-700">
                            Kiến thức là chìa khóa<br>mở ra tương lai
                        </p>
                        <svg class="mx-auto mt-1 h-2 w-24 text-sky-500" viewBox="0 0 100 8" fill="none" aria-hidden="true">
                            <path d="M3 6C25 2 58 1 97 3" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        </svg>
                    </div>
                </div>
            </a>
        </aside>

        {{-- ══════ [HOME-CENTER] CỘT GIỮA ══════ --}}
        <main class="flex flex-col gap-4 sm:gap-5 min-w-0">

            {{-- ══════ [HOME-03] HERO ══════ --}}
            <section id="hero" @mouseenter="slidePaused = true" @mouseleave="slidePaused = false"
                     @focusin="slidePaused = true" @focusout="slidePaused = false"
                     class="relative rounded-3xl border border-sky-200/90 shadow-[0_12px_40px_rgba(0,100,220,0.09)] overflow-hidden p-4 sm:p-6 lg:p-7 min-h-[340px] sm:min-h-[380px] lg:min-h-[390px] flex flex-col justify-between group/hero">
                {{-- SỬA 12/9 — source mới CHỈ tải ảnh nền của slide đang hiển thị (trước đây in cả 4
                     ảnh chồng lên nhau rồi mờ dần). Dùng ĐÚNG MỘT thẻ ảnh và đổi src theo slide —
                     giống cách bản mẫu remount ảnh bằng key. Nhẹ hơn hẳn ở lần mở trang đầu tiên. --}}
                <img :src="heroImages[slideIndex]" :alt="heroAlts[slideIndex]"
                     src="{{ $heroSlides[0]['bgImage'] }}" alt="{{ $heroSlides[0]['subtitle'] }}. {{ $heroSlides[0]['description'] }}"
                     loading="eager" decoding="async" fetchpriority="high"
                     class="absolute inset-0 z-0 h-full w-full object-cover object-right pointer-events-none select-none">

                <div class="absolute inset-0 z-0 pointer-events-none bg-[linear-gradient(90deg,rgba(255,255,255,0.96)_0%,rgba(255,255,255,0.82)_72%,rgba(255,255,255,0.3)_100%)] sm:bg-gradient-to-r sm:from-white/95 sm:via-white/70 sm:to-transparent"></div>

                {{-- Nhãn chủ đề của slideshow --}}
                <div class="relative z-10 mb-3 max-w-full overflow-x-auto no-scrollbar">
                    <div class="flex w-max min-w-full items-center gap-0.5 rounded-2xl border border-white/80 bg-white/62 p-1 shadow-[0_3px_14px_rgba(31,91,139,0.07)] backdrop-blur-md sm:w-full">
                        @foreach ($heroSlides as $i => $slide)
                            <button type="button" @click="selectSlide({{ $i }})" :aria-pressed="slideIndex === {{ $i }}"
                                    class="relative flex h-7 min-w-[104px] flex-1 shrink-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-xl px-2.5 text-[11px] font-semibold transition-all cursor-pointer xl:text-xs"
                                    :class="slideIndex === {{ $i }} ? 'bg-[#07549A] text-white shadow-[0_3px_9px_rgba(7,84,154,0.22)]' : 'text-[#4F6C85] hover:bg-white/75 hover:text-[#07549A]'">
                                <span>{{ $slide['tabTitle'] }}</span>
                                <span x-show="slideIndex === {{ $i }}" x-cloak class="hidden h-1.5 w-1.5 rounded-full bg-amber-300 sm:inline-block"></span>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Nội dung slide --}}
                <div class="relative z-10 grid w-full my-auto">
                    @foreach ($heroSlides as $i => $slide)
                        <div :aria-hidden="slideIndex !== {{ $i }}"
                             class="col-start-1 row-start-1 flex w-full max-w-[92%] flex-col gap-2 transition-opacity duration-500 sm:max-w-[64%] lg:max-w-[60%] xl:max-w-[58%]"
                             :class="slideIndex === {{ $i }} ? 'visible opacity-100' : 'invisible pointer-events-none opacity-0'">
                            <div class="flex min-w-0 items-center gap-2">
                                <span class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap px-2.5 py-0.5 rounded-full text-[10px] sm:text-xs font-bold border shadow-2xs {{ $slide['tagStyle'] }}">{{ $slide['tag'] }}</span>
                                <span class="hidden min-w-0 truncate text-[10px] font-medium text-slate-400 2xl:inline">• Lộ trình chuẩn quốc gia & quốc tế</span>
                            </div>

                            <div class="flex items-center gap-2">
                                <h1 class="type-hero-brand text-[#0050A0] drop-shadow-[0_1px_2px_rgba(255,255,255,0.9)] flex items-center gap-2">
                                    <span>Ôn Thi</span>
                                    <span class="text-[#F59E0B] relative inline-block">360
                                        <svg class="absolute -bottom-1 -left-2 w-[115%] h-5 text-[#F59E0B] pointer-events-none" viewBox="0 0 80 20" fill="none">
                                            <ellipse cx="40" cy="10" rx="36" ry="6" stroke="currentColor" stroke-width="2.5" stroke-dasharray="45 8" transform="rotate(-8 40 10)" />
                                        </svg>
                                    </span>
                                </h1>
                            </div>

                            <h2 class="type-hero-title text-[#0F3A7A]">{{ $slide['subtitle'] }}</h2>
                            <p class="type-body max-w-[34rem] font-medium text-[#526E88]">{{ $slide['description'] }}</p>

                            <div class="mt-1.5 max-w-[34rem] rounded-2xl border p-2.5 shadow-[0_5px_18px_rgba(38,91,128,0.1)] backdrop-blur-md {{ $slide['panelStyle'] }}">
                                <div class="mb-2 flex items-center justify-between gap-2 border-b border-white/80 pb-2">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg border shadow-sm {{ $slide['panelIconStyle'] }}">
                                            <x-lucide :name="$slide['panelIcon']" class="h-3.5 w-3.5" />
                                        </span>
                                        <span class="truncate text-[10px] font-extrabold uppercase tracking-[0.055em] text-[#31536F]">Nội dung lộ trình</span>
                                    </div>
                                    <span class="shrink-0 rounded-full border border-white/90 bg-white/68 px-2 py-0.5 text-[10px] font-bold text-[#607A91]">4 trọng tâm</span>
                                </div>
                                <div class="grid grid-cols-2 gap-1.5">
                                    @foreach ($slide['highlights'] as $h)
                                        <div class="flex min-w-0 items-center gap-1.5 rounded-lg border border-white/70 bg-white/52 px-1.5 py-1">
                                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md {{ $slide['itemIconStyle'] }}">
                                                <x-lucide :name="$h['icon']" class="h-3 w-3" />
                                            </span>
                                            <span class="text-[10px] font-semibold leading-[1.3] text-[#294D69] sm:text-[10.5px]">{{ $h['label'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Điều khiển slideshow --}}
                <div class="absolute bottom-3.5 right-4 sm:bottom-4 sm:right-5 z-20 flex items-center gap-1 rounded-full border border-white/80 bg-white/82 px-1.5 py-1 shadow-[0_5px_18px_rgba(15,58,122,0.16)] backdrop-blur-md">
                    <button type="button" @click="prevSlide()" aria-label="Ảnh trước"
                            class="flex h-7 w-7 items-center justify-center rounded-full text-[#42617E] transition-all hover:bg-white hover:text-[#07549A] hover:shadow-sm active:scale-95 cursor-pointer">
                        <x-lucide name="chevron-left" class="h-4 w-4" />
                    </button>
                    <div class="flex items-center gap-1 px-0.5" aria-label="Vị trí ảnh trình chiếu">
                        @foreach ($heroSlides as $i => $slide)
                            <button type="button" @click="selectSlide({{ $i }})" aria-label="Chuyển đến ảnh {{ $i + 1 }}"
                                    class="h-1.5 rounded-full transition-all duration-300 cursor-pointer"
                                    :class="slideIndex === {{ $i }} ? 'w-5 bg-[#07549A]' : 'w-1.5 bg-[#A9BCCB] hover:bg-[#6E8CA5]'"></button>
                        @endforeach
                    </div>
                    <button type="button" @click="nextSlide()" aria-label="Ảnh tiếp theo"
                            class="flex h-7 w-7 items-center justify-center rounded-full bg-[#07549A] text-white shadow-sm transition-all hover:bg-[#06447D] active:scale-95 cursor-pointer">
                        <x-lucide name="chevron-right" class="h-4 w-4" />
                    </button>
                </div>
            </section>

            {{-- ══════ [HOME-04] TÌM MỤC TIÊU ══════ --}}
            <section class="rounded-[22px] border border-[#DFEBF0] bg-white px-3.5 py-3 sm:px-4 shadow-[0_5px_20px_rgba(45,96,145,0.045)]">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-[#CDE8EC] bg-[#E9F7F8] text-[#23869B]">
                            <x-lucide name="target" class="h-[18px] w-[18px]" />
                        </div>
                        <div class="min-w-0">
                            <h3 class="type-section-title">Chọn mục tiêu hoặc lộ trình của bạn</h3>
                            <p class="mt-0.5 truncate text-[11px] font-normal leading-snug text-[#71869A]">Gợi ý phù hợp theo năng lực và định hướng học tập</p>
                        </div>
                    </div>

                    <button type="button" @click="cycleGrade()" aria-label="Đổi lớp hiện tại"
                            class="group flex h-8 shrink-0 items-center gap-1.5 rounded-xl border border-[#DCE8ED] bg-[#F8FAFB] px-2.5 text-left transition-all hover:border-[#BFDDE4] hover:bg-[#F2F8F9]">
                        <x-lucide name="graduation-cap" class="h-3.5 w-3.5 text-[#23869B]" />
                        <span class="text-[11px] font-bold leading-none text-[#123B68]" x-text="grade"></span>
                        <x-lucide name="chevron-down" class="h-3 w-3 text-[#8BA0B5] transition-colors group-hover:text-[#126F91]" />
                    </button>
                </div>

                <div class="mt-2.5 flex flex-col gap-2 sm:flex-row sm:items-center">
                    <button type="button" @click="cycleGoal()" aria-label="Đổi mục tiêu học"
                            class="group flex min-h-[42px] h-auto min-w-0 flex-1 items-center gap-2 rounded-xl border border-[#DFEAEE] bg-[#F8FAFB] px-2.5 py-2 text-left transition-all hover:border-[#C6E0E6] hover:bg-[#F3F8F9]">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-[#E7F5F6] text-[#23869B]">
                            <x-lucide name="target" class="h-3.5 w-3.5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[10px] font-semibold leading-none text-[#71869A]">Mục tiêu</span>
                            <span class="mt-1 block whitespace-normal break-words text-xs sm:text-[13px] font-semibold leading-tight text-[#123B68]" x-text="goal"></span>
                        </span>
                        <x-lucide name="chevron-down" class="h-3.5 w-3.5 shrink-0 text-[#8BA0B5] transition-colors group-hover:text-[#126F91]" />
                    </button>

                    <a href="{{ route('courses.index') }}"
                       class="flex h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl border border-[#ECD78F] bg-[#FFF4C7] px-4 text-xs font-bold text-[#765C18] shadow-[0_2px_7px_rgba(183,143,37,0.09)] transition-all hover:border-[#DFC56F] hover:bg-[#FFEDAA] active:scale-[0.98]">
                        <span>Xem lộ trình</span>
                        <x-lucide name="chevron-right" class="h-3.5 w-3.5" />
                    </a>
                </div>
            </section>

            {{-- ══════ [HOME-05] NỘI DUNG HỌC ══════ --}}
            <div @mouseenter="mainTabPaused = true" @mouseleave="mainTabPaused = false"
                 class="mt-3.5 rounded-[28px] border border-[#DFEBF4] bg-white p-3.5 sm:p-4 shadow-[0_7px_26px_rgba(45,96,145,0.055)]">
                <div class="mb-2.5 flex flex-col gap-2 border-b border-[#E7EFF5] pb-2.5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex w-full items-center gap-1 rounded-2xl bg-[#F3F8FA] p-1 sm:w-fit overflow-x-auto no-scrollbar">
                        <button type="button" @click="mainTab = 'courses'"
                                class="flex items-center gap-2 whitespace-nowrap rounded-xl border px-3 py-2 text-xs sm:text-[13px] font-bold leading-snug transition-all cursor-pointer"
                                :class="mainTab === 'courses' ? 'border-[#CBE7EE] bg-white text-[#123B68] shadow-[0_2px_8px_rgba(34,126,151,0.08)]' : 'border-transparent text-[#71869A] hover:bg-white/70 hover:text-[#123B68]'">
                            <img src="{{ asset('assets/badge-courses.png') }}" alt="" class="w-[18px] h-[18px] object-contain">
                            <span>Chương trình học nổi bật</span>
                        </button>
                        <button type="button" @click="mainTab = 'path'"
                                class="flex items-center gap-2 whitespace-nowrap rounded-xl border px-3 py-2 text-xs sm:text-[13px] font-bold leading-snug transition-all cursor-pointer"
                                :class="mainTab === 'path' ? 'border-[#CBE7EE] bg-white text-[#123B68] shadow-[0_2px_8px_rgba(34,126,151,0.08)]' : 'border-transparent text-[#71869A] hover:bg-white/70 hover:text-[#123B68]'">
                            <img src="{{ asset('assets/badge-path.png') }}" alt="" class="w-[18px] h-[18px] object-contain">
                            <span>Lộ trình học chuyên nghiệp</span>
                        </button>
                    </div>

                    <div class="flex w-full items-center justify-end gap-1.5 sm:w-auto">
                        <span class="mr-0.5 min-w-7 text-center text-[11px] font-semibold leading-snug text-[#71869A]"
                              x-text="(carouselIndex + 1) + '/' + carouselCount"></span>
                        <a :href="mainTab === 'courses' ? '{{ route('courses.index') }}' : '{{ route('practice.index') }}'"
                           href="{{ route('courses.index') }}"
                           class="hidden shrink-0 items-center gap-1 rounded-full px-2 py-1.5 text-[11.5px] font-bold leading-snug text-[#126F91] transition-colors hover:bg-[#EFF9FB] hover:text-[#0B6487] sm:flex cursor-pointer">
                            Xem tất cả <x-lucide name="chevron-right" class="h-3.5 w-3.5" />
                        </a>
                    </div>
                </div>

                {{-- [HOME-05A] Tab “Chương trình học nổi bật” --}}
                <section id="courses" x-show="mainTab === 'courses'" x-cloak class="relative overflow-hidden sm:h-[198px]">
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3 2xl:grid-cols-4">
                        @foreach ($featuredPrograms as $i => $program)
                            <a href="{{ $program['href'] }}" title="{{ $program['desc'] }}"
                               x-show="slotOf(visibleCourses, {{ $i }}) !== null"
                               :class="offsetClass(slotOf(visibleCourses, {{ $i }}))"
                               :style="'order:' + (slotOf(visibleCourses, {{ $i }}) ?? 0)"
                               class="group block animate-fadeIn overflow-hidden rounded-2xl border bg-gradient-to-b {{ $program['bgClass'] }} transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md cursor-pointer">
                                <div class="relative h-[104px] overflow-hidden border-b border-white/70 bg-white/35">
                                    <img src="{{ $program['image'] }}" alt="{{ $program['title'] }}"
                                         class="h-full w-full object-contain p-0.5 transition-transform duration-300 group-hover:scale-[1.025]">
                                    <div class="absolute inset-x-0 bottom-0 h-7 bg-gradient-to-t from-white/35 to-transparent"></div>
                                </div>
                                <div class="flex h-[84px] flex-col px-3 py-2.5 text-left">
                                    <h4 class="text-[13.5px] font-bold leading-snug text-[#123B68] line-clamp-1">{{ $program['title'] }}</h4>
                                    <p class="mt-1 line-clamp-1 text-[11px] font-normal leading-[1.45] text-[#536D86]">{{ $program['desc'] }}</p>
                                    <span class="mt-auto inline-flex items-center gap-0.5 pt-1 text-[11.5px] font-bold leading-snug text-[#126F91] transition-colors group-hover:text-[#0B6487]">
                                        <span>{{ $program['btnText'] }}</span>
                                        <x-lucide name="chevron-right" class="h-3 w-3" />
                                    </span>
                                </div>
                            </a>
                        @endforeach
                    </div>

                    <button type="button" @click="moveCarousel(-1)" aria-label="Xem mục trước"
                            class="absolute left-2 top-[37px] z-20 flex h-8 w-8 items-center justify-center rounded-full border border-white/90 bg-white/90 text-[#126F91] shadow-[0_3px_12px_rgba(38,80,100,0.14)] backdrop-blur-sm transition-all hover:bg-white hover:scale-105 cursor-pointer">
                        <x-lucide name="chevron-left" class="h-4 w-4" />
                    </button>
                    <button type="button" @click="moveCarousel(1)" aria-label="Xem mục tiếp theo"
                            class="absolute right-2 top-[37px] z-20 flex h-8 w-8 items-center justify-center rounded-full border border-white/90 bg-white/90 text-[#126F91] shadow-[0_3px_12px_rgba(38,80,100,0.14)] backdrop-blur-sm transition-all hover:bg-white hover:scale-105 cursor-pointer">
                        <x-lucide name="chevron-right" class="h-4 w-4" />
                    </button>
                </section>

                {{-- [HOME-05B] Tab “Lộ trình học chuyên nghiệp” --}}
                <section id="path" x-show="mainTab === 'path'" x-cloak class="relative overflow-hidden sm:h-[198px]">
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3 2xl:grid-cols-4">
                        @foreach ($learningSteps as $i => $step)
                            <a href="{{ $step['href'] }}" title="{{ $step['desc'] }}"
                               x-show="slotOf(visiblePaths, {{ $i }}) !== null"
                               :class="offsetClass(slotOf(visiblePaths, {{ $i }}))"
                               :style="'order:' + (slotOf(visiblePaths, {{ $i }}) ?? 0)"
                               class="group relative block h-[188px] animate-fadeIn overflow-hidden rounded-2xl border border-[#DDEAF2] bg-[#F8FBFD] text-left transition-all hover:-translate-y-0.5 hover:border-[#B8DFE8] hover:bg-[#F3FAFC] hover:shadow-sm cursor-pointer">
                                <div class="relative flex h-[104px] items-center justify-center overflow-hidden border-b border-white bg-gradient-to-br from-[#F3FAFC] to-[#E7F3F7]">
                                    <img src="{{ $step['img'] }}" alt="{{ $step['step'] }}"
                                         class="h-full w-full object-contain p-2 transition-transform duration-300 group-hover:scale-[1.025]">
                                    <span class="absolute right-2 top-2 flex h-6 w-6 items-center justify-center rounded-full border border-white bg-white/90 text-[10px] font-bold text-[#126F91] shadow-sm">{{ $i + 1 }}</span>
                                </div>
                                <div class="flex h-[84px] min-w-0 flex-col px-3 py-2.5">
                                    <h5 class="text-[13.5px] font-bold leading-snug text-[#123B68] line-clamp-1">{{ $step['step'] }}</h5>
                                    <p class="mt-1 line-clamp-2 text-[11px] font-normal leading-[1.45] text-[#536D86]">{{ $step['desc'] }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>

                    <button type="button" @click="moveCarousel(-1)" aria-label="Xem mục trước"
                            class="absolute left-2 top-[37px] z-20 flex h-8 w-8 items-center justify-center rounded-full border border-white/90 bg-white/90 text-[#126F91] shadow-[0_3px_12px_rgba(38,80,100,0.14)] backdrop-blur-sm transition-all hover:bg-white hover:scale-105 cursor-pointer">
                        <x-lucide name="chevron-left" class="h-4 w-4" />
                    </button>
                    <button type="button" @click="moveCarousel(1)" aria-label="Xem mục tiếp theo"
                            class="absolute right-2 top-[37px] z-20 flex h-8 w-8 items-center justify-center rounded-full border border-white/90 bg-white/90 text-[#126F91] shadow-[0_3px_12px_rgba(38,80,100,0.14)] backdrop-blur-sm transition-all hover:bg-white hover:scale-105 cursor-pointer">
                        <x-lucide name="chevron-right" class="h-4 w-4" />
                    </button>
                </section>
            </div>
        </main>

        {{-- ══════ [HOME-RIGHT] SIDEBAR PHẢI ══════ --}}
        <aside id="leaderboard" class="flex flex-col gap-3.5 xl:gap-4 lg:row-span-2">

            {{-- ══════ [HOME-06] KHÔNG GIAN HỌC TẬP ══════ --}}
            <div class="bg-white rounded-3xl p-3.5 xl:p-4 border border-[#DDEAF0] shadow-[0_4px_16px_rgba(52,91,120,0.045)]">
                <div class="flex items-center justify-between mb-2.5">
                    <div class="flex items-center gap-1.5 xl:gap-2">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl border border-[#CFE6EC] bg-[#EAF5F8] text-[#2D7FA3]">
                            <x-lucide name="bar-chart-2" class="h-4 w-4" />
                        </span>
                        <h4 class="type-card-title text-slate-800">Không gian học tập</h4>
                    </div>
                    @if (empty($learningSpace['guest']))
                        {{-- SỬA 12/9 — source mới đổi nhãn vai trò thành hộp chọn ngữ cảnh. Ở đây chỉ
                             hiện hộp chọn khi CÓ THẬT nhiều lựa chọn (phụ huynh nhiều con / giáo viên
                             nhiều lớp); chọn xong đi thẳng sang trang của lựa chọn đó. --}}
                        @foreach (($learningSpace['panels'] ?? []) as $roleKey => $panel)
                            @if (count($panel['contexts'] ?? []) > 1)
                                <div x-show="activeRole === '{{ $roleKey }}'" x-cloak class="relative max-w-[132px]">
                                    <label for="journey-context-{{ $roleKey }}" class="sr-only">{{ $panel['contextLabel'] }}</label>
                                    <select id="journey-context-{{ $roleKey }}" @change="if ($event.target.value) window.location.href = $event.target.value"
                                            class="type-label w-full appearance-none rounded-xl border border-[#DCE8ED] bg-[#F5F8FA] py-1 pl-2 pr-6 text-[#536D7E] outline-none transition-colors hover:bg-[#EEF5F7] focus:border-[#A9D2DF]">
                                        @foreach ($panel['contexts'] as $ctx)
                                            <option value="{{ $ctx['href'] }}">{{ $ctx['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <x-lucide name="chevron-down" class="pointer-events-none absolute right-1.5 top-1/2 h-3 w-3 -translate-y-1/2 text-slate-400" />
                                </div>
                            @else
                                <span x-show="activeRole === '{{ $roleKey }}'" x-cloak
                                      class="type-label bg-[#F5F8FA] border border-[#DCE8ED] rounded-xl px-2 py-0.5 flex items-center gap-1 text-[#536D7E]">{{ $panel['roleLabel'] }}</span>
                            @endif
                        @endforeach
                    @endif
                </div>

                @if (! empty($learningSpace['guest']))
                    {{-- [HOME-06G] Trạng thái khách --}}
                    <div class="rounded-2xl border border-sky-100 bg-gradient-to-br from-[#F5FAFD] to-[#EEF7FB] p-3.5 text-center">
                        <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-2xl border border-sky-100 bg-white text-[#3E88A6] shadow-[0_2px_8px_rgba(64,125,151,0.08)]">
                            <x-lucide name="lock-keyhole" class="h-5 w-5" />
                        </span>
                        <p class="type-card-title mt-2.5 text-[#245B7A]">Đăng nhập để xem hành trình</p>
                        <p class="type-body mt-1 text-slate-500">Tiến độ, lịch học và báo cáo sẽ được cá nhân hóa theo vai trò của bạn.</p>
                        <a href="{{ route('login') }}"
                           class="mt-3 inline-flex items-center justify-center gap-1.5 rounded-xl border border-[#B9DCE8] bg-white px-3.5 py-2 text-xs font-bold text-[#216F8E] shadow-[0_2px_7px_rgba(64,125,151,0.08)] transition-colors hover:border-[#8FC6D8] hover:bg-[#F7FCFE]">
                            Đăng nhập ngay <x-lucide name="chevron-right" class="h-3.5 w-3.5" />
                        </a>
                    </div>
                @else
                    {{-- ══════ [HOME-06A] BỘ CHỌN VAI TRÒ ══════
                         SỬA 12/9 — khối mới của source. Chỉ hiện khi người đang đăng nhập thật sự
                         giữ từ 2 vai trò trở lên; mỗi tab là SỐ LIỆU THẬT của đúng vai trò đó, không
                         phải bản xem thử. Giữ 1 vai trò thì khối này không xuất hiện. --}}
                    @if (! empty($learningSpace['multiRole']))
                        <div role="tablist" aria-label="Vai trò xem hành trình học" class="mb-3 grid {{ count($learningSpace['panels']) === 3 ? 'grid-cols-3' : 'grid-cols-2' }} gap-1 rounded-2xl bg-[#F3F7F9] p-1">
                            @foreach ($learningSpace['panels'] as $roleKey => $panel)
                                <button type="button" role="tab" :aria-selected="activeRole === '{{ $roleKey }}'"
                                        @click="activeRole = '{{ $roleKey }}'"
                                        class="flex min-w-0 items-center justify-center gap-1 rounded-xl px-1.5 py-1.5 text-[10px] font-bold transition-all"
                                        :class="activeRole === '{{ $roleKey }}' ? 'bg-white text-[#245B7A] shadow-[0_2px_7px_rgba(64,105,125,0.12)]' : 'text-[#78909E] hover:bg-white/70 hover:text-[#39728B]'">
                                    <x-lucide :name="$panel['roleIcon']" class="h-3.5 w-3.5 shrink-0"
                                              ::class="activeRole === '{{ $roleKey }}' ? '{{ $panel['roleIconClass'] }}' : 'text-[#9AB0BA]'" />
                                    <span class="truncate">{{ $panel['roleLabel'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif

                    @foreach ($learningSpace['panels'] as $roleKey => $panel)
                        <div x-show="activeRole === '{{ $roleKey }}'" @if (! $loop->first) x-cloak @endif>
                            {{-- Thanh tiến độ --}}
                            <div class="mb-2.5">
                                <div class="flex justify-between text-xs font-bold mb-1">
                                    <span class="text-[#2E718F]">{{ $panel['progressLabel'] }}</span>
                                    <span class="text-[#2E718F]">{{ $panel['progress'] }}%</span>
                                </div>
                                <div class="w-full bg-[#E8F0F3] rounded-full h-1.5 overflow-hidden">
                                    <div class="bg-gradient-to-r from-[#6CC7C4] to-[#3EA7B2] h-1.5 rounded-full transition-[width] duration-500" style="width: {{ $panel['progress'] }}%"></div>
                                </div>
                            </div>

                            {{-- [HOME-06B] Thẻ hành động --}}
                            <a href="{{ $panel['nextHref'] }}"
                               class="bg-[#F5FAFB] border border-[#DCECEF] rounded-2xl p-2 xl:p-2.5 mb-2.5 flex items-center justify-between cursor-pointer hover:border-[#C5E1E7] transition-colors">
                                <span class="overflow-hidden block">
                                    <span class="type-meta font-bold text-[#2E7E94] uppercase tracking-wide block">{{ $panel['nextLabel'] }}</span>
                                    <span class="type-card-title truncate mt-0.5 block">{{ $panel['nextTitle'] }}</span>
                                    <span class="type-meta mt-0.5 text-slate-500 truncate block">{{ $panel['nextMeta'] }}</span>
                                </span>
                                <x-lucide name="chevron-right" class="w-4 h-4 text-[#6F9CAC] shrink-0 ml-1.5" />
                            </a>

                            {{-- [HOME-06C] Chỉ số tóm tắt --}}
                            <div class="grid grid-cols-3 gap-1.5 text-center">
                                @foreach ($panel['stats'] as $stat)
                                    <div class="bg-[#F8FAFB] rounded-xl p-1.5 xl:p-2 border border-[#E7EDF0]">
                                        <p class="text-base xl:text-lg font-extrabold leading-tight {{ $stat['valueClass'] }}">{{ $stat['value'] }}</p>
                                        <p class="type-meta mt-0.5 text-slate-500 truncate">{{ $stat['label'] }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>

            {{-- ══════ [HOME-07] XẾP HẠNG & THÔNG BÁO ══════ --}}
            <div class="bg-white rounded-3xl p-3.5 xl:p-4 border border-[#DDEAF0] shadow-[0_4px_16px_rgba(52,91,120,0.045)]">
                <div class="flex items-center justify-between mb-3 border-b border-slate-100 pb-2">
                    <div class="flex items-center gap-1 bg-[#F2F6F8] p-0.5 rounded-xl w-full">
                        <button type="button" @click="sidebarTab = 'leaderboard'" :aria-selected="sidebarTab === 'leaderboard'"
                                class="flex-1 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center justify-center gap-1"
                                :class="sidebarTab === 'leaderboard' ? 'border border-[#CDE4EA] bg-white text-[#216F8E] shadow-[0_1px_4px_rgba(44,102,124,0.07)]' : 'border border-transparent text-[#6D8293] hover:bg-white/60 hover:text-[#2D718D]'">
                            <x-lucide name="trophy" class="h-3.5 w-3.5 text-[#B77A22]" />
                            <span>Top xuất sắc</span>
                        </button>
                        <button type="button" @click="sidebarTab = 'notifications'" :aria-selected="sidebarTab === 'notifications'"
                                class="flex-1 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center justify-center gap-1"
                                :class="sidebarTab === 'notifications' ? 'border border-[#CDE4EA] bg-white text-[#216F8E] shadow-[0_1px_4px_rgba(44,102,124,0.07)]' : 'border border-transparent text-[#6D8293] hover:bg-white/60 hover:text-[#2D718D]'">
                            <x-lucide name="bell" class="h-3.5 w-3.5 text-[#5E8098]" />
                            <span>Thông báo</span>
                        </button>
                    </div>
                </div>

                <div x-show="sidebarTab === 'leaderboard'">
                    @if (count($topStudents['rows']) > 0)
                        <p class="type-meta mb-1.5 truncate text-slate-400">{{ $topStudents['title'] }}</p>
                        <div class="flex flex-col gap-1.5 mb-2.5">
                            @foreach ($topStudents['rows'] as $st)
                                @php
                                    $rankClass = match ((int) $st['rank']) {
                                        1 => 'border-[#E8CD77] bg-[#FFF4D7] text-[#A76D0B]',
                                        2 => 'border-[#C9D6DF] bg-[#F0F5F7] text-[#5D7485]',
                                        3 => 'border-[#E4C4A7] bg-[#FFF0E4] text-[#A66A42]',
                                        default => 'border-[#DDE8EE] bg-[#F7FAFB] text-[#6D8495]',
                                    };
                                @endphp
                                <a href="{{ route('leaderboard.index') }}"
                                   class="flex items-center justify-between py-1 px-1.5 rounded-xl hover:bg-sky-50 transition-colors text-xs cursor-pointer">
                                    <span class="flex items-center gap-2 min-w-0">
                                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border text-[10px] font-extrabold {{ $rankClass }}">{{ $st['rank'] }}</span>
                                        <img src="{{ $st['avatar'] }}" alt="" decoding="async"
                                             class="w-7 h-7 rounded-full border border-sky-200 object-cover shadow-2xs">
                                        <span class="font-semibold text-slate-800 truncate max-w-[100px]">{{ $st['name'] }}</span>
                                    </span>
                                    <span class="flex items-center gap-2 shrink-0">
                                        <span class="text-[10px] text-slate-400">điểm</span>
                                        <span class="text-xs font-bold text-[#2E6FA7]">{{ rtrim(rtrim(number_format($st['score'], 2, '.', ''), '0'), '.') }}</span>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <p class="type-body py-4 text-center text-slate-400">Chưa có bảng xếp hạng nào được công bố.</p>
                    @endif

                    <a href="{{ route('leaderboard.index') }}" class="block overflow-hidden rounded-2xl border border-sky-100 cursor-pointer hover:shadow-md transition-shadow">
                        <img src="{{ asset('assets/achieve-banner.png') }}" alt="Cùng chinh phục thành tích cao hơn!" class="w-full h-auto object-cover">
                    </a>
                </div>

                <div x-show="sidebarTab === 'notifications'" x-cloak class="flex flex-col gap-1.5 py-1">
                    @forelse ($bellItems as $n)
                        <a href="{{ route('notifications.read', $n['id']) }}" class="flex items-start gap-2 text-xs group cursor-pointer hover:bg-sky-50/70 p-2 rounded-xl transition-colors">
                            <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-[#EAF5F8] text-[#2D7FA3]">{{ $n['icon'] ?? '🔔' }}</span>
                            <span class="flex-1 overflow-hidden">
                                <span class="block text-xs font-semibold text-slate-800 group-hover:text-blue-600 transition-colors leading-snug">{{ $n['title'] }}</span>
                                <span class="type-meta mt-0.5 block text-slate-400">{{ $n['time'] ?? '' }}</span>
                            </span>
                        </a>
                    @empty
                        <p class="type-body py-4 text-center text-slate-400">Chưa có thông báo nào.</p>
                    @endforelse
                </div>
            </div>

            {{-- ══════ [HOME-08] TÀI LIỆU NỔI BẬT ══════ --}}
            <section id="materials" class="bg-white rounded-3xl p-3.5 xl:p-4 border border-[#DDEAF0] shadow-[0_4px_16px_rgba(52,91,120,0.045)]">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="w-8 h-8 rounded-xl border border-[#D5E6EF] bg-[#EAF3F8] flex items-center justify-center text-[#3A77A2] shrink-0">
                            <x-lucide name="file-text" class="w-4 h-4" />
                        </div>
                        <div class="min-w-0">
                            <h3 class="type-card-title">Tài liệu nổi bật</h3>
                            <p class="type-meta mt-0.5 truncate text-slate-500">Chọn lọc cho mục tiêu của bạn</p>
                        </div>
                    </div>
                    <a href="{{ route('materials.index') }}" class="type-action text-[#3A7598] hover:text-[#285F7D] whitespace-nowrap cursor-pointer">Tất cả →</a>
                </div>

                <div class="flex flex-col gap-2">
                    @forelse (array_slice($featuredMaterials, 0, 2) as $book)
                        <a href="{{ route('materials.show', $book['id']) }}"
                           class="group w-full flex items-center gap-2.5 rounded-2xl border border-[#E1EBF0] bg-[#F8FAFB] p-2 text-left transition-all hover:border-[#C9DFE8] hover:bg-[#F3F8FA] hover:shadow-sm cursor-pointer">
                            <img src="{{ $book['image'] ?: asset('assets/book-img-1.png') }}" alt="{{ $book['title'] }}"
                                 class="w-12 h-14 rounded-xl object-cover border border-sky-100 shrink-0">
                            <span class="min-w-0 flex-1 block">
                                <span class="type-card-title line-clamp-2 group-hover:text-blue-600 block">{{ $book['title'] }}</span>
                                <span class="type-meta mt-1 truncate text-slate-500 block">{{ $book['meta'] }}</span>
                            </span>
                            <x-lucide name="chevron-right" class="w-3.5 h-3.5 text-slate-400 group-hover:text-blue-600 shrink-0" />
                        </a>
                    @empty
                        <p class="type-body py-3 text-center text-slate-400">Chưa có tài liệu nào được phát hành.</p>
                    @endforelse
                </div>

                <a href="{{ route('materials.index') }}"
                   class="mt-2.5 block w-full text-center rounded-xl border border-[#DCEAF0] bg-[#F1F7FA] py-2 text-xs font-bold text-[#2F718F] transition-colors hover:bg-[#E8F3F6] cursor-pointer">
                    Khám phá kho tài liệu
                </a>
            </section>

            {{-- ══════ [HOME-09] CUỘC THI & KHẢO SÁT ══════ --}}
            <section id="contests" class="bg-white rounded-3xl p-3.5 xl:p-4 border border-[#DDEAF0] shadow-[0_4px_16px_rgba(52,91,120,0.045)]">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="w-8 h-8 rounded-xl border border-[#F0DEB2] bg-[#FFF6DF] flex items-center justify-center text-[#B47A22] shrink-0">
                            <x-lucide name="trophy" class="w-4 h-4" />
                        </div>
                        <div class="min-w-0">
                            <h3 class="type-card-title">Cuộc thi & khảo sát</h3>
                            <p class="type-meta mt-0.5 truncate text-slate-500">Sự kiện đang và sắp diễn ra</p>
                        </div>
                    </div>
                    <a href="{{ route('competitions.index') }}" class="type-action text-[#3A7598] hover:text-[#285F7D] whitespace-nowrap cursor-pointer">Tất cả →</a>
                </div>

                <div class="flex flex-col gap-2">
                    @forelse (array_slice($upcomingCompetitions, 0, 2) as $i => $c)
                        @php
                            $toneClasses = match ($c['statusTone']) {
                                'success' => ['text-emerald-600', 'bg-emerald-500'],
                                'info' => ['text-amber-500', 'bg-amber-400'],
                                default => ['text-slate-500', 'bg-slate-400'],
                            };
                        @endphp
                        <a href="{{ route('competitions.show', $c['id']) }}"
                           class="group w-full overflow-hidden rounded-2xl border border-[#E7E5DE] bg-[#FAFBFB] text-left transition-all hover:border-[#E6D6AD] hover:bg-[#FFFCF4] hover:shadow-sm cursor-pointer block">
                            <span class="flex items-stretch">
                                <img src="{{ asset('assets/contest-img-'.(($i % 3) + 1).'.png') }}" alt="{{ $c['title'] }}" class="w-16 min-h-18 object-cover shrink-0">
                                <span class="min-w-0 flex-1 p-2.5 block">
                                    <span class="type-card-title line-clamp-2 group-hover:text-blue-600 block">{{ $c['title'] }}</span>
                                    <span class="type-meta mt-1 flex items-center gap-1 truncate text-slate-500">
                                        <x-lucide name="calendar" class="w-3 h-3 shrink-0" />
                                        <span class="truncate">{{ $c['startsAt'] ? 'Bắt đầu: '.$c['startsAt']->format('d/m/Y') : $c['typeLabel'] }}</span>
                                    </span>
                                    <span class="type-meta mt-1 flex items-center gap-1 font-bold {{ $toneClasses[0] }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $toneClasses[1] }}"></span>
                                        {{ $c['statusLabel'] }}
                                    </span>
                                </span>
                            </span>
                        </a>
                    @empty
                        <p class="type-body py-3 text-center text-slate-400">Chưa có cuộc thi nào sắp diễn ra.</p>
                    @endforelse
                </div>

                <a href="{{ route('competitions.index') }}"
                   class="mt-2.5 block w-full text-center rounded-xl border border-[#F0E2BD] bg-[#FFF8E8] py-2 text-xs font-bold text-[#976921] transition-colors hover:bg-[#FFF3D4] cursor-pointer">
                    Xem lịch cuộc thi
                </a>
            </section>
        </aside>

        {{-- ══════ [HOME-10] CÂU CHUYỆN ĐỒNG HÀNH ══════ --}}
        <section id="testimonials" class="lg:col-span-2 bg-white rounded-3xl p-4 sm:p-5 border border-sky-100 shadow-[0_2px_10px_rgba(0,100,220,0.04)] flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8.5 h-8.5 rounded-xl bg-blue-600 flex items-center justify-center text-white text-sm shadow-2xs">
                            <x-lucide name="heart" class="w-4.5 h-4.5 text-white fill-white" />
                        </div>
                        <div>
                            <h3 class="type-section-title">Câu chuyện đồng hành</h3>
                            <p class="type-body mt-0.5 text-slate-500">Những câu chuyện thật, truyền cảm hứng thật</p>
                        </div>
                    </div>
                    <a href="{{ route('teachers.index') }}" class="text-xs font-bold text-blue-600 hover:text-blue-700 cursor-pointer">Xem thêm câu chuyện →</a>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                    @foreach ($testimonials as $t)
                        <div class="bg-[#F8FBFE] rounded-2xl border border-sky-100/80 overflow-hidden flex flex-col justify-between hover:shadow-md transition-all">
                            <div class="w-full h-22 sm:h-24 overflow-hidden">
                                <img src="{{ $t['banner'] }}" alt="{{ $t['author'] }}" class="w-full h-full object-cover">
                            </div>
                            <div class="p-2.5 flex-1 flex flex-col justify-between bg-white m-1.5 rounded-xl border border-sky-50 shadow-2xs">
                                <p class="type-body mb-2 line-clamp-3 italic text-slate-600">{{ $t['quote'] }}</p>
                                <div class="flex items-center gap-2 pt-2 border-t border-slate-100">
                                    <img src="{{ $t['avatar'] }}" alt="{{ $t['author'] }}" class="w-6.5 h-6.5 rounded-full object-cover border border-sky-200 shadow-2xs">
                                    <div class="overflow-hidden text-left">
                                        <p class="type-card-title truncate">{{ $t['author'] }}</p>
                                        <p class="type-meta mt-0.5 truncate text-slate-400">{{ $t['role'] }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- [HOME-10A] Chân khối đồng hành --}}
                <div class="relative mt-4 overflow-hidden rounded-2xl border border-[#DDEEF4] bg-gradient-to-r from-[#F3FAFC] via-white to-[#F8F5FF] px-3.5 py-2.5">
                    <svg class="pointer-events-none absolute inset-x-0 bottom-0 h-8 w-full text-[#DCEFF4]" viewBox="0 0 720 56" fill="none" aria-hidden="true">
                        <path d="M0 42C96 10 158 10 244 36C326 60 401 57 482 26C568 -7 639 8 720 35" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        <path d="M0 51C96 19 158 19 244 45C326 69 401 66 482 35C568 2 639 17 720 44" stroke="currentColor" stroke-width="1" stroke-linecap="round" opacity=".7" />
                    </svg>
                    <div class="relative flex items-center gap-2.5">
                        <div class="flex shrink-0 -space-x-2">
                            @foreach ($testimonials as $t)
                                <img src="{{ $t['avatar'] }}" alt="" class="h-7 w-7 rounded-full border-2 border-white object-cover shadow-[0_2px_6px_rgba(55,104,123,0.12)]">
                            @endforeach
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="type-card-title truncate text-[#245B7A]">Đồng hành để tiến bộ mỗi ngày</p>
                            <p class="type-meta mt-0.5 truncate text-slate-500">Học sinh, gia đình và thầy cô cùng chung một mục tiêu.</p>
                        </div>
                        <x-lucide name="heart" class="h-4 w-4 shrink-0 text-[#74B6C5]" />
                    </div>
                </div>
            </div>
        </section>

        {{-- ══════ [HOME-11] HỖ TRỢ TOÀN CHIỀU NGANG ══════ --}}
        <div id="support" class="lg:col-span-3 grid grid-cols-1 lg:grid-cols-2 gap-4 items-stretch">
            {{-- [HOME-11A] Câu hỏi thường gặp --}}
            <section class="h-full bg-white rounded-3xl p-4 sm:p-5 border border-sky-100 shadow-[0_2px_10px_rgba(0,100,220,0.04)] flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-2.5 mb-3">
                        <div class="w-8.5 h-8.5 rounded-xl bg-blue-600 flex items-center justify-center text-white text-sm shadow-2xs">
                            <x-lucide name="help-circle" class="w-4.5 h-4.5 text-white" />
                        </div>
                        <div>
                            <h3 class="type-section-title">Câu hỏi thường gặp</h3>
                            <p class="type-body mt-0.5 text-slate-500">Giải đáp nhanh những thắc mắc phổ biến</p>
                        </div>
                    </div>

                    <div class="border border-sky-100 rounded-2xl divide-y divide-sky-100 overflow-hidden bg-white">
                        @foreach ($faqs as $i => $faq)
                            <div class="transition-colors">
                                <button type="button" @click="toggleFaq({{ $i }})"
                                        class="type-card-title w-full text-left px-4 py-2.5 flex items-center justify-between text-slate-800 hover:text-blue-600 transition-colors cursor-pointer">
                                    <span class="pr-2">{{ $i + 1 }}. {{ $faq['q'] }}</span>
                                    <x-lucide name="chevron-down" class="w-3.5 h-3.5 text-slate-400 shrink-0 transition-transform duration-200"
                                              ::class="openFaq === {{ $i }} ? 'rotate-180 text-blue-600' : ''" />
                                </button>
                                <div x-show="openFaq === {{ $i }}" x-cloak
                                     class="type-body px-4 pb-3 text-slate-600 bg-sky-50/40 border-t border-sky-50">{{ $faq['a'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- [HOME-11B] Liên hệ tư vấn và hỗ trợ --}}
            <section class="h-full relative overflow-hidden rounded-3xl p-4 sm:p-5 border border-sky-100 shadow-[0_2px_10px_rgba(0,100,220,0.04)] flex flex-col justify-between min-h-[300px] sm:min-h-[320px]">
                <img src="{{ asset('assets/support-banner-bg.jpg') }}" alt="Cần hỗ trợ?"
                     class="absolute inset-0 w-full h-full object-cover object-[80%_center] sm:object-right pointer-events-none select-none z-0">
                <div class="absolute inset-0 bg-gradient-to-r from-white/95 via-white/85 to-white/40 sm:from-white/80 sm:via-white/50 sm:to-transparent z-0 pointer-events-none"></div>

                <div class="absolute top-4 right-4 sm:right-6 bg-white/95 backdrop-blur-xs px-3 py-1.5 rounded-2xl border border-sky-200 shadow-xs text-[11px] font-semibold text-sky-800 leading-tight text-center pointer-events-none z-10 hidden sm:block">
                    Chúng tôi<br>luôn ở đây<br>cùng bạn!
                </div>

                <div class="relative z-10 w-full sm:max-w-[58%] flex flex-col justify-between h-full">
                    <div>
                        <div class="flex items-center gap-2.5 mb-2">
                            <div class="w-8.5 h-8.5 rounded-xl bg-blue-600 flex items-center justify-center text-white text-sm shadow-2xs shrink-0">
                                <x-lucide name="headphones" class="w-4.5 h-4.5 text-white" />
                            </div>
                            <div>
                                <h3 class="type-section-title">Cần hỗ trợ?</h3>
                                <p class="type-body mt-0.5 text-slate-600">Đội ngũ tư vấn luôn sẵn sàng đồng hành cùng bạn trên hành trình chinh phục tri thức.</p>
                            </div>
                        </div>

                        <div class="flex flex-col gap-1.5 my-2.5 text-xs font-semibold text-slate-800">
                            @foreach (['Tư vấn lộ trình học phù hợp', 'Hỗ trợ kỹ thuật, giải đáp thắc mắc', 'Đồng hành cùng học sinh và phụ huynh'] as $line)
                                <div class="flex items-center gap-2 bg-white/50 sm:bg-transparent px-2 sm:px-0 py-0.5 sm:py-0 rounded-xl">
                                    <x-lucide name="check-circle-2" class="w-3.5 h-3.5 text-emerald-500 fill-emerald-100 shrink-0" />
                                    <span>{{ $line }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="pt-1.5">
                        <a href="{{ route('info.index') }}#lien-he"
                           class="bg-[#0091FF] hover:bg-blue-600 text-white font-bold text-xs py-2 px-5 rounded-full shadow-md inline-flex items-center gap-1.5 transition-all cursor-pointer">
                            <span>Liên hệ tư vấn</span>
                            <span class="text-xs font-bold">→</span>
                        </a>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
</div>
@endsection

@push('scripts')
    @include('partials.home-script')
@endpush
