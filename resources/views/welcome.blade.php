@extends('layouts.guest')

@section('title', 'Luyện thi Tin học - Học cùng mục tiêu, Vươn xa ước mơ')
@section('meta-description', 'Ôn Thi 360 — nền tảng luyện thi Tin học: lớp học, bài tập tự luyện theo chuyên đề, tài liệu, cuộc thi và bảng xếp hạng cho học sinh lớp 6–12.')

@section('content')
    {{-- ═══════════════ TRANG CHỦ ═══════════════
         SỬA 9/9 (10) — dựng lại theo ĐÚNG source React khách gửi (education/src/App.jsx).
         Toàn bộ class/bố cục chép nguyên; phần động của React đổi sang Alpine (đã có sẵn trong
         dự án) và MỌI nút bấm đều được gắn LINK THẬT sang trang tương ứng theo yêu cầu của
         khách ("làm UI thì nhớ gắn link đầy đủ").

         Dữ liệu thật được nối vào khi đã có, chưa có thì dùng đúng nội dung mẫu để không vỡ bố cục:
           · Tài liệu nổi bật    <- $featuredMaterials   (Public\MaterialService::featuredData)
           · Cuộc thi & khảo sát <- $upcomingCompetitions (Public\CompetitionService::upcomingData)
           · Câu hỏi thường gặp  <- $faqs                 (Public\HomeService::faqs)
           · Thông báo quan trọng <- $bellItems           (thông báo của người đang đăng nhập)
         Ảnh nằm ở public/assets/ — chép nguyên từ source khách gửi. --}}
    @php
        $featuredMaterials = $featuredMaterials ?? [];
        $upcomingCompetitions = $upcomingCompetitions ?? [];
        $faqs = $faqs ?? [];
        $bellItems = $bellItems ?? [];

        $heroSlides = [
            [
                'id' => 'hsg',
                'tabTitle' => 'Giải cao HSG',
                'tag' => '🏆 Đấu trường đỉnh cao',
                'tagStyle' => 'bg-amber-100/90 text-amber-800 border-amber-300',
                'subtitle' => 'Luyện thi Tin học – Đồng hành cùng bạn chinh phục giải HSG & Olympic Tin học',
                'badges' => ['HSG Tin học lớp 9', 'HSG Quốc gia lớp 12', 'Olympic Tin học', 'Bồi dưỡng đội tuyển'],
                'defaultGoal' => 'Luyện thi HSG Tin học lớp 9',
                'bgImage' => asset('assets/hero-banner-hsg.jpg'),
                'floatingCard' => ['icon' => '🥇', 'title' => 'Giải Nhất HSG Quốc gia 2025', 'desc' => 'Điểm tuyệt đối phần Quy hoạch động & Đồ thị', 'badge' => 'Top 1 Toàn quốc'],
            ],
            [
                'id' => 'chuyen-tin',
                'tabTitle' => 'Đỗ Chuyên Tin',
                'tag' => '🎯 Mục tiêu trường Chuyên',
                'tagStyle' => 'bg-blue-100/90 text-blue-800 border-blue-300',
                'subtitle' => 'Luyện thi vào lớp 10 Chuyên Tin – Tự tin đỗ trường Chuyên top đầu cả nước',
                'badges' => ['Chuyên Khoa Học Tự Nhiên', 'Chuyên Sư Phạm', 'Chuyên Amsterdam', 'Chuyên Tin các tỉnh'],
                'defaultGoal' => 'Luyện thi vào lớp 10 chuyên Tin',
                'bgImage' => asset('assets/hero-banner-chuyen.jpg'),
                'floatingCard' => ['icon' => '🎓', 'title' => 'Thủ khoa Chuyên KHTN & CSP', 'desc' => 'Tự tin đỗ lớp 10 chuyên thuật toán & C++', 'badge' => 'Đỗ Chuyên 100%'],
            ],
            [
                'id' => 'tot-nghiep',
                'tabTitle' => 'Điểm cao Tốt nghiệp',
                'tag' => '⚡ Bứt phá điểm 9+',
                'tagStyle' => 'bg-emerald-100/90 text-emerald-800 border-emerald-300',
                'subtitle' => 'Tổng ôn cấp tốc THPT – Chinh phục điểm 9+ môn Tin học kỳ thi Tốt nghiệp 2025–2026',
                'badges' => ['Điểm 9+ THPT môn Tin', '100+ Đề thi thử trắc nghiệm', 'Lý thuyết trọng tâm', 'Chấm điểm tự động'],
                'defaultGoal' => 'Ôn thi tốt nghiệp môn Tin học',
                'bgImage' => asset('assets/hero-banner-totnghiep.jpg'),
                'floatingCard' => ['icon' => '💯', 'title' => '10/10 Điểm Tin Tốt Nghiệp THPT', 'desc' => 'Nắm chắc 100% ma trận & định dạng đề thi mới', 'badge' => 'Thủ khoa A00 / B00'],
            ],
            [
                'id' => 'du-hoc',
                'tabTitle' => 'Phỏng vấn du học',
                'tag' => '✈️ Vươn ra thế giới',
                'tagStyle' => 'bg-purple-100/90 text-purple-800 border-purple-300',
                'subtitle' => 'Luyện thuật toán quốc tế AP CS & USACO – Tự tin phỏng vấn học bổng Du học ngành Tech',
                'badges' => ['USACO Bronze / Silver / Gold', 'AP Computer Science A', 'Portfolio Tech quốc tế', 'Học bổng Du học $50k+'],
                'defaultGoal' => 'Học trước chương trình Tin học để du học',
                'bgImage' => asset('assets/hero-banner-duhoc.jpg'),
                'floatingCard' => ['icon' => '🌐', 'title' => 'Học bổng $50,000 Đại học Mỹ', 'desc' => 'USACO Gold Division & AP CS điểm 5/5', 'badge' => 'Tech Scholarship'],
            ],
        ];

        $quickLinks = [
            'Luyện thi HSG Tin học lớp 9',
            'Luyện thi HSG môn Tin học lớp 12',
            'Luyện thi vào lớp 10 chuyên Tin',
            'Ôn thi tốt nghiệp môn Tin học',
            'Học trước chương trình Tin học để du học',
        ];
        $grades = ['Lớp 6', 'Lớp 7', 'Lớp 8', 'Lớp 9', 'Lớp 10', 'Lớp 11', 'Lớp 12'];

        $featuredCourses = [
            ['title' => 'Bài tập tự luyện', 'desc' => 'Hệ thống bài tập tự luyện phong phú theo từng chuyên đề từ cơ bản đến nâng cao', 'btnText' => 'Luyện tập ngay', 'bgClass' => 'from-[#EAF4FE] to-[#D8EAFD] border-[#BAE0FD]', 'btnClass' => 'bg-[#38BDF8] hover:bg-[#0284C7] text-white', 'image' => 'course-img-1.png', 'url' => route('practice.index')],
            ['title' => 'Tài liệu', 'desc' => 'Sách, chuyên đề, bộ đề, giáo viên và chuyên gia uy tín hỗ trợ và đồng hành', 'btnText' => 'Khám phá', 'bgClass' => 'from-[#E8FBF6] to-[#D0F5E7] border-[#A7F3D0]', 'btnClass' => 'bg-[#2DD4BF] hover:bg-[#0D9488] text-white', 'image' => 'course-img-2.png', 'url' => route('materials.index')],
            ['title' => 'Lớp học', 'desc' => 'Lớp học chuyên nghiệp, quản lý và theo dõi tiến độ học sinh chuẩn mực', 'btnText' => 'Vào lớp học', 'bgClass' => 'from-[#F3EFFF] to-[#E5DEFF] border-[#DDD6FE]', 'btnClass' => 'bg-[#A78BFA] hover:bg-[#7C3AED] text-white', 'image' => 'course-img-3.png', 'url' => route('courses.index')],
            ['title' => 'Giáo viên & Chuyên gia', 'desc' => 'Đội ngũ giáo viên chuyên nghiệp & chuyên gia uy tín đồng hành tận tâm', 'btnText' => 'Xem đội ngũ', 'bgClass' => 'from-[#FFF7E6] to-[#FEEAD0] border-[#FED7AA]', 'btnClass' => 'bg-[#FB923C] hover:bg-[#EA580C] text-white', 'image' => 'course-img-4.png', 'url' => route('teachers.index')],
            ['title' => 'Cuộc thi', 'desc' => 'Cuộc thi, khảo sát được tổ chức thường xuyên và công bằng', 'btnText' => 'Tìm hiểu', 'bgClass' => 'from-[#E6F7FF] to-[#CCEFFF] border-[#BAE6FD]', 'btnClass' => 'bg-[#38BDF8] hover:bg-[#0284C7] text-white', 'image' => 'course-img-5.png', 'url' => route('competitions.index')],
        ];

        $learningSteps = [
            ['step' => '1. Lựa chọn mục tiêu', 'desc' => 'Chọn mục tiêu lớp phù hợp', 'img' => 'step-1.png'],
            ['step' => '2. Chọn lộ trình phù hợp', 'desc' => 'Học theo năng lực & mục tiêu', 'img' => 'step-2.png'],
            ['step' => '3. Luyện tập & học liệu', 'desc' => 'Bài tập, giáo trình, chuyên đề', 'img' => 'step-3.png'],
            ['step' => '4. Lớp học & giáo viên', 'desc' => 'Học cùng giáo viên, nhận hỗ trợ', 'img' => 'step-4.png'],
            ['step' => '5. Thi & Đánh giá', 'desc' => 'Cuộc thi, đánh giá phát năng lực', 'img' => 'step-5.png'],
        ];

        $audiencePills = [
            ['title' => 'Học sinh tự luyện', 'desc' => 'Luyện tập theo chuyên đề', 'img' => 'aud-1.png', 'url' => route('practice.index')],
            ['title' => 'Phụ huynh đồng hành', 'desc' => 'Theo dõi tiến độ và kết quả', 'img' => 'aud-2.png', 'url' => route('info.index')],
            ['title' => 'Lớp học chuyên nghiệp', 'desc' => 'Quản lý lớp, nhận xét học sinh', 'img' => 'aud-3.png', 'url' => route('courses.index')],
            ['title' => 'Giáo viên & Chuyên gia', 'desc' => 'Đồng hành, hỗ trợ cao cấp', 'img' => 'aud-4.png', 'url' => route('teachers.index')],
            ['title' => 'Hệ thống & cuộc thi', 'desc' => 'Học liệu chuẩn, đấu trường uy tín', 'img' => 'aud-5.png', 'url' => route('competitions.index')],
        ];

        // ── Tài liệu nổi bật ──
        // SỬA 9/9 (11) (khách: "chỗ trang chủ đoạn này chuyển tab k dc") — 3 tab Sách/Chuyên đề/
        // Bộ đề trước đây là 3 THẺ LINK sang trang Tài liệu (bấm là rời trang chủ, nên nhìn như
        // "không chuyển được"), lại còn trỏ ?tab=bo-de không tồn tại (đúng phải là de-thi).
        // Giờ nạp sẵn cả 3 nhóm rồi đổi qua lại ngay tại chỗ bằng Alpine.
        $featuredMaterialsByType = $featuredMaterialsByType ?? [];
        $sampleBooks = [
            ['title' => 'Lập trình căn bản với Python', 'tag' => 'Dành cho học sinh 6–10', 'pages' => '320 trang', 'highlight' => 'Nhiều bài tập minh họa', 'image' => asset('assets/book-img-1.png'), 'btnText' => 'Xem tài liệu', 'btnStyle' => 'bg-[#38BDF8] hover:bg-sky-500 text-white', 'url' => route('materials.index')],
            ['title' => 'Chuyên đề Cấu trúc dữ liệu và giải thuật', 'tag' => 'Dành cho HSG lớp 10–12', 'pages' => '200 trang', 'highlight' => 'Bài tập nâng cao', 'image' => asset('assets/book-img-2.png'), 'btnText' => 'Xem tài liệu', 'btnStyle' => 'bg-[#38BDF8] hover:bg-sky-500 text-white', 'url' => route('materials.index')],
            ['title' => 'Tuyển tập đề thi HSG Tin học các tỉnh', 'tag' => 'Cập nhật 2020 – 2025', 'pages' => '500+ đề thi', 'highlight' => 'Có lời giải chi tiết', 'image' => asset('assets/book-img-3.png'), 'btnText' => 'Xem chi tiết', 'btnStyle' => 'bg-[#FDBA74] hover:bg-amber-400 text-amber-950 font-bold', 'url' => route('materials.index')],
            ['title' => 'Bộ đề ôn thi vào lớp 10 chuyên Tin', 'tag' => 'Theo cấu trúc mới nhất', 'pages' => '300 đề luyện tập', 'highlight' => 'Có đáp án và lời giải', 'image' => asset('assets/book-img-4.png'), 'btnText' => 'Xem tài liệu', 'btnStyle' => 'bg-[#38BDF8] hover:bg-sky-500 text-white', 'url' => route('materials.index')],
        ];

        $toBookCard = function (array $m, int $i): array {
            return [
                'title' => $m['title'],
                'tag' => $m['badge'] ?? 'Công khai',
                'pages' => $m['meta'] ?? 'Miễn phí',
                'highlight' => ($m['count'] ?? 0) > 0 ? number_format($m['count']).' đánh giá' : 'Biên soạn bởi giáo viên',
                'image' => $m['image'] ?: asset('assets/book-img-'.($i % 4 + 1).'.png'),
                'btnText' => 'Xem tài liệu',
                'btnStyle' => $i === 2 ? 'bg-[#FDBA74] hover:bg-amber-400 text-amber-950 font-bold' : 'bg-[#38BDF8] hover:bg-sky-500 text-white',
                'url' => route('materials.show', $m['id']),
            ];
        };

        $materialTabs = [];
        foreach ([['sach', 'Sách'], ['chuyen-de', 'Chuyên đề'], ['de-thi', 'Bộ đề']] as [$key, $label]) {
            $items = [];
            foreach (array_slice($featuredMaterialsByType[$key] ?? [], 0, 4) as $i => $m) {
                $items[] = $toBookCard($m, $i);
            }
            $materialTabs[] = ['key' => $key, 'label' => $label, 'items' => $items, 'href' => route('materials.index', ['tab' => $key])];
        }

        // Chưa có tài liệu nào được phát hành -> hiện 4 thẻ mẫu ở tab đầu để bố cục không trống.
        $hasAnyMaterial = collect($materialTabs)->contains(fn ($t) => $t['items'] !== []);
        if (! $hasAnyMaterial) {
            $materialTabs[0]['items'] = $sampleBooks;
        }
        // Mở sẵn tab đầu tiên CÓ tài liệu, tránh vào trang thấy ngay một tab rỗng.
        $defaultMaterialTab = collect($materialTabs)->firstWhere(fn ($t) => $t['items'] !== [])['key'] ?? 'sach';

        // ── Cuộc thi ──
        $competitionCards = [];
        foreach (array_slice($upcomingCompetitions, 0, 3) as $i => $c) {
            $ok = in_array($c['statusTone'] ?? '', ['success', 'info'], true);
            $competitionCards[] = [
                'title' => $c['title'],
                'time' => $c['startsAt'] ? 'Bắt đầu: '.$c['startsAt']->format('d/m/Y') : $c['typeLabel'],
                'status' => $c['statusLabel'],
                'statusColor' => $ok ? 'text-emerald-600' : 'text-amber-500',
                'statusDot' => $ok ? 'bg-emerald-500' : 'bg-amber-400',
                'image' => asset('assets/contest-img-'.($i + 1).'.png'),
                'url' => route('competitions.show', $c['id']),
            ];
        }
        if ($competitionCards === []) {
            $competitionCards = [
                ['title' => 'Kỳ thi HSG Tin học cấp tỉnh năm 2025–2026', 'time' => 'Bắt đầu: 15/11/2025', 'status' => 'Đang nhận đăng ký', 'statusColor' => 'text-emerald-600', 'statusDot' => 'bg-emerald-500', 'image' => asset('assets/contest-img-1.png'), 'url' => route('competitions.index')],
                ['title' => 'Cuộc thi Lập trình Online Ôn Thi 360 lần 3', 'time' => 'Thời gian: 20/10/2025', 'status' => 'Sắp diễn ra', 'statusColor' => 'text-amber-500', 'statusDot' => 'bg-amber-400', 'image' => asset('assets/contest-img-2.png'), 'url' => route('competitions.index')],
                ['title' => 'Khảo sát năng lực Tin học', 'time' => 'Thời gian: 01/11 – 10/11/2025', 'status' => 'Đang mở', 'statusColor' => 'text-emerald-600', 'statusDot' => 'bg-emerald-500', 'image' => asset('assets/contest-img-3.png'), 'url' => route('competitions.index')],
            ];
        }

        $testimonials = [
            ['quote' => '“ Nhờ Ôn Thi 360, mình tự tin hơn rất nhiều trong học tập và đạt kết quả tốt ở kỳ thi HSG cấp tỉnh. Nền tảng giúp mình có lộ trình rõ ràng và bài tập chất lượng. ”', 'author' => 'Nguyễn Hà Phương', 'role' => 'Học sinh lớp 12', 'banner' => 'testi-banner-1.png', 'avatar' => 'testi-av-1.png'],
            ['quote' => '“ Tôi rất yên tâm khi con học tại Ôn Thi 360. Con tiến bộ rõ rệt, chúng tôi có thể theo dõi tiến độ và nhận được sự hỗ trợ tận tình từ đội ngũ giáo viên. ”', 'author' => 'Chị Trần Thị Mai', 'role' => 'Phụ huynh học sinh', 'banner' => 'testi-banner-2.png', 'avatar' => 'testi-av-2.png'],
            ['quote' => '“ Ôn Thi 360 là nền tảng hữu ích, giúp học sinh tiếp cận kiến thức Tin học một cách hệ thống, hiện đại và hiệu quả. ”', 'author' => 'Thầy Lê Minh Đức', 'role' => 'Giáo viên Tin học', 'banner' => 'testi-banner-3.png', 'avatar' => 'testi-av-3.png'],
        ];

        $faqItems = $faqs !== [] ? $faqs : [
            ['q' => 'Ôn Thi 360 có những lớp học nào?', 'a' => 'Ôn Thi 360 cung cấp đầy đủ các khóa học từ Lập trình cơ bản THCS, Bồi dưỡng HSG Tin học lớp 9, Ôn thi vào lớp 10 chuyên Tin, đến Luyện thi HSG Quốc gia và Thi tốt nghiệp THPT.'],
            ['q' => 'Tài liệu tại Ôn Thi 360 có phù hợp với chương trình giáo dục hiện hành?', 'a' => 'Toàn bộ giáo trình, chuyên đề và bộ đề thi đều được cập nhật bám sát chương trình GDPT mới nhất, được thẩm định bởi đội ngũ giáo viên trường chuyên và chuyên gia Tin học uy tín.'],
            ['q' => 'Làm thế nào để luyện tập theo chuyên đề hiệu quả?', 'a' => 'Học sinh nên theo đúng lộ trình: Đọc lý thuyết trọng tâm -> Làm bài tập tự luyện có chấm tự động -> Xem giải thích chi tiết -> Làm bài kiểm tra năng lực cuối mỗi chuyên đề.'],
            ['q' => 'Phụ huynh có thể theo dõi tiến độ học tập của con như thế nào?', 'a' => 'Phụ huynh có tài khoản đồng hành riêng để xem chi tiết thời gian học, số bài tập đã nộp, tỷ lệ làm đúng và nhận báo cáo tiến độ định kỳ hàng tuần từ hệ thống.'],
        ];

        $topStudents = [
            ['rank' => 1, 'name' => 'Nguyễn Minh Anh', 'class' => '10A1', 'score' => '9.8', 'avatar' => 'rank-1.png'],
            ['rank' => 2, 'name' => 'Trần Đức Duy', 'class' => '10A2', 'score' => '9.6', 'avatar' => 'rank-2.png'],
            ['rank' => 3, 'name' => 'Lê Phương Thảo', 'class' => '10A3', 'score' => '9.5', 'avatar' => 'rank-3.png'],
            ['rank' => 4, 'name' => 'Phạm Hoàng Nam', 'class' => '10A2', 'score' => '9.3', 'avatar' => 'rank-4.png'],
            ['rank' => 5, 'name' => 'Vũ Thị Mai', 'class' => '10A2', 'score' => '9.2', 'avatar' => 'rank-5.png'],
        ];

        // Thông báo cột phải: dùng thông báo thật của người đang đăng nhập nếu có.
        $noticeCards = [];
        foreach (array_slice($bellItems, 0, 4) as $n) {
            $noticeCards[] = ['title' => $n['title'], 'time' => $n['time'] ?? '', 'icon' => $n['icon'] ?? '⭐', 'url' => route('notifications.read', $n['id'])];
        }
        if ($noticeCards === []) {
            $noticeCards = [
                ['title' => 'Kỳ thi HSG Tin học cấp tỉnh sắp diễn ra', 'time' => '3 ngày trước', 'icon' => '⭐', 'url' => route('competitions.index')],
                ['title' => 'Lịch học lớp Toán Tin 10A1 tuần này', 'time' => '5 giờ trước', 'icon' => '📅', 'url' => route('courses.index')],
                ['title' => 'Bài tập mới: Cấu trúc dữ liệu cơ bản', 'time' => '1 ngày trước', 'icon' => '💡', 'url' => route('practice.index')],
                ['title' => 'Bạn đã nộp được một bài tập hôm nay!', 'time' => '2 ngày trước', 'icon' => '⭐', 'url' => route('practice.index')],
            ];
        }

        $tickerText = $upcomingCompetitions[0]['title'] ?? null;
        $tickerText = $tickerText ? $tickerText.' sắp diễn ra. Hãy chuẩn bị thật tốt!'
            : 'Kỳ thi HSG Tin học cấp tỉnh năm học 2025–2026 sắp diễn ra. Hãy chuẩn bị thật tốt!';
    @endphp

    <div class="max-w-[1780px] mx-auto px-3 sm:px-5 lg:px-6 2xl:px-10 py-3 sm:py-5">

        {{-- ═══ 2. THANH THÔNG BÁO HỆ THỐNG ═══ --}}
        <div class="mb-4 sm:mb-5 bg-gradient-to-r from-[#FFF9E6] via-[#F0F7FD] to-[#EAF4FE] border border-amber-200/80 rounded-2xl px-3.5 sm:px-5 py-2.5 sm:py-3 flex items-center justify-between shadow-[0_2px_6px_rgba(0,0,0,0.02)]">
            <div class="flex items-center gap-2 sm:gap-3 overflow-hidden text-xs sm:text-sm lg:text-base">
                <span class="text-amber-500 text-base sm:text-lg animate-pulse">📢</span>
                <span class="font-bold text-amber-800 shrink-0 text-xs sm:text-sm">Thông báo hệ thống</span>
                <span class="text-slate-300">|</span>
                <p class="text-slate-700 truncate font-medium text-xs sm:text-sm">{{ $tickerText }}</p>
            </div>
            <div class="flex items-center gap-1 sm:gap-2 text-slate-400 shrink-0 ml-2">
                <a href="{{ route('competitions.index') }}" aria-label="Xem cuộc thi" class="p-1 hover:text-blue-600 hover:bg-white rounded-full transition-colors cursor-pointer"><x-lucide name="chevron-left" class="w-4 h-4 sm:w-5 sm:h-5" /></a>
                <a href="{{ route('competitions.index') }}" aria-label="Xem cuộc thi" class="p-1 hover:text-blue-600 hover:bg-white rounded-full transition-colors cursor-pointer"><x-lucide name="chevron-right" class="w-4 h-4 sm:w-5 sm:h-5" /></a>
            </div>
        </div>

        {{-- ═══ 3. LƯỚI 2 CỘT: NỘI DUNG CHÍNH | CỘT PHẢI ═══ --}}
        <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] xl:grid-cols-[1fr_360px] 2xl:grid-cols-[1fr_390px] gap-5 xl:gap-6 items-start">

            <main class="flex flex-col gap-5 min-w-0">

                {{-- ═══ HERO: 4 slide tự chạy, dừng khi rê chuột ═══ --}}
                <section id="hero"
                         x-data="heroSlider(@js($heroSlides))"
                         @mouseenter="paused = true" @mouseleave="paused = false"
                         class="relative rounded-3xl border border-sky-200/90 shadow-[0_4px_24px_rgba(0,100,220,0.06)] overflow-hidden p-3.5 sm:p-6 lg:p-7 min-h-[420px] sm:min-h-[460px] lg:min-h-[480px] flex flex-col justify-between group/hero">

                    <template x-for="(slide, idx) in slides" :key="slide.id">
                        <img :src="slide.bgImage" :alt="slide.subtitle"
                             class="absolute inset-0 w-full h-full object-cover object-right pointer-events-none select-none z-0 transition-opacity duration-700 ease-in-out"
                             :class="current === idx ? 'opacity-100 scale-100' : 'opacity-0 scale-105'">
                    </template>

                    <div class="absolute inset-0 bg-gradient-to-r from-white/95 via-white/70 to-transparent pointer-events-none z-0"></div>

                    {{-- Tab chọn slide + nút điều hướng --}}
                    <div class="relative z-10 flex items-center justify-between gap-1.5 sm:gap-2 mb-2 pb-1 border-b border-sky-100/70">
                        <div class="flex-1 min-w-0 flex items-center gap-1 sm:gap-1.5 overflow-x-auto no-scrollbar py-0.5">
                            <template x-for="(slide, idx) in slides" :key="'tab-' + slide.id">
                                <button type="button" @click="select(idx)"
                                        class="relative px-2.5 sm:px-3.5 py-1 sm:py-1.5 rounded-full text-[11px] sm:text-xs font-bold transition-all cursor-pointer whitespace-nowrap shrink-0 flex items-center gap-1.5"
                                        :class="current === idx ? 'bg-[#0050A0] text-white shadow-md shadow-blue-900/20 scale-102' : 'bg-white/80 hover:bg-white text-slate-700 hover:text-blue-700 border border-slate-200/80'">
                                    <span x-text="slide.tabTitle"></span>
                                    <span x-show="current === idx" class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-ping"></span>
                                </button>
                            </template>
                        </div>

                        <div class="flex items-center gap-1 shrink-0 bg-white/90 backdrop-blur-xs px-2 py-1 rounded-full border border-sky-200/80 shadow-2xs">
                            <button type="button" @click="prev()" aria-label="Slide trước"
                                    class="w-5 h-5 sm:w-6 sm:h-6 rounded-full flex items-center justify-center text-slate-600 hover:text-blue-600 hover:bg-sky-50 transition-colors cursor-pointer">
                                <x-lucide name="chevron-left" class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                            </button>
                            <div class="flex items-center gap-1 px-1">
                                <template x-for="(slide, idx) in slides" :key="'dot-' + slide.id">
                                    <button type="button" @click="select(idx)" :aria-label="'Slide ' + (idx + 1)"
                                            class="h-1.5 rounded-full transition-all cursor-pointer"
                                            :class="current === idx ? 'w-4 bg-[#0050A0]' : 'w-1.5 bg-slate-300 hover:bg-slate-400'"></button>
                                </template>
                            </div>
                            <button type="button" @click="next()" aria-label="Slide sau"
                                    class="w-5 h-5 sm:w-6 sm:h-6 rounded-full flex items-center justify-center text-slate-600 hover:text-blue-600 hover:bg-sky-50 transition-colors cursor-pointer">
                                <x-lucide name="chevron-right" class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                            </button>
                        </div>
                    </div>

                    {{-- Nội dung slide --}}
                    <div class="relative z-10 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4 my-auto">
                        <div class="w-full max-w-full sm:max-w-[62%] lg:max-w-[58%] xl:max-w-[55%] flex flex-col gap-2">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] sm:text-xs font-bold border shadow-2xs"
                                      :class="slides[current].tagStyle" x-text="slides[current].tag"></span>
                                <span class="text-[10px] text-slate-400 font-medium hidden sm:inline">• Lộ trình chuẩn quốc gia &amp; quốc tế</span>
                            </div>

                            <div class="flex items-center gap-2">
                                <h1 class="text-2xl sm:text-4xl lg:text-[42px] font-black text-[#0050A0] tracking-tight leading-none drop-shadow-[0_1px_2px_rgba(255,255,255,0.9)] flex items-center gap-2">
                                    <span>Ôn Thi</span>
                                    <span class="text-[#F59E0B] relative inline-block">360<svg class="absolute -bottom-1 -left-2 w-[115%] h-5 text-[#F59E0B] pointer-events-none" viewBox="0 0 80 20" fill="none"><ellipse cx="40" cy="10" rx="36" ry="6" stroke="currentColor" stroke-width="2.5" stroke-dasharray="45 8" transform="rotate(-8 40 10)"></ellipse></svg></span>
                                </h1>
                            </div>

                            <h2 class="text-xs sm:text-base lg:text-lg font-bold text-[#0F3A7A] leading-snug" x-text="slides[current].subtitle"></h2>

                            <div class="flex flex-wrap items-center gap-1.5 sm:gap-2 mt-0.5">
                                <template x-for="badge in slides[current].badges" :key="badge">
                                    <span class="inline-flex items-center gap-1 sm:gap-1.5 px-2.5 sm:px-3 py-0.5 sm:py-1 rounded-full text-[10px] sm:text-xs font-bold bg-[#E8F8F0]/95 text-[#0D8A4E] border border-[#A7E8C5] shadow-2xs backdrop-blur-xs whitespace-nowrap shrink-0">
                                        <x-lucide name="check-circle" class="w-3 sm:w-3.5 h-3 sm:h-3.5 text-[#0D8A4E] fill-[#C7F3DC]" />
                                        <span x-text="badge"></span>
                                    </span>
                                </template>
                            </div>
                        </div>

                        {{-- Thẻ nổi bên phải --}}
                        <div class="hidden sm:flex flex-col gap-1.5 self-end lg:self-center bg-white/95 backdrop-blur-md p-3 sm:p-3.5 rounded-2xl border border-sky-200/90 shadow-[0_10px_30px_rgba(0,100,220,0.12)] max-w-[260px] transition-transform hover:-translate-y-1 duration-300">
                            <div class="flex items-center justify-between gap-2">
                                <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-amber-400 to-amber-500 text-white flex items-center justify-center text-base shadow-2xs shrink-0" x-text="slides[current].floatingCard.icon"></div>
                                <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-md bg-blue-50 text-blue-700 border border-blue-200" x-text="slides[current].floatingCard.badge"></span>
                            </div>
                            <div>
                                <h4 class="text-xs sm:text-sm font-bold text-slate-800 leading-tight" x-text="slides[current].floatingCard.title"></h4>
                                <p class="text-[11px] text-slate-500 font-medium leading-normal mt-0.5" x-text="slides[current].floatingCard.desc"></p>
                            </div>
                        </div>
                    </div>

                    {{-- Ô chọn mục tiêu + 5 nút nhanh --}}
                    <div class="relative z-10 w-full max-w-full lg:max-w-[720px] mt-3">
                        <form method="GET" action="{{ route('courses.index') }}" class="w-full bg-white/95 backdrop-blur-md rounded-2xl p-3 sm:p-4 border border-sky-100 shadow-[0_8px_30px_rgba(0,100,220,0.08)]">
                            <div class="flex items-center gap-2 text-xs sm:text-sm font-bold text-[#0B5CBA] mb-2 sm:mb-2.5">
                                <span class="w-4.5 h-4.5 sm:w-5 sm:h-5 rounded-md bg-[#0066CC] text-white flex items-center justify-center text-[10px] sm:text-xs shadow-2xs">🎯</span>
                                <span>Chọn mục tiêu học hoặc lộ trình của bạn</span>
                            </div>

                            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-2.5">
                                {{-- Lớp hiện tại --}}
                                <div x-data="{ open: false }" @click.outside="open = false" class="relative w-full sm:w-[130px] shrink-0">
                                    <input type="hidden" name="grade" :value="grade">
                                    <button type="button" @click="open = !open"
                                            class="w-full bg-[#F0F6FC] hover:bg-sky-100/70 border border-sky-200/90 rounded-xl px-3 py-2 flex items-center justify-between cursor-pointer transition-colors group">
                                        <span class="flex items-center gap-2 min-w-0">
                                            <x-lucide name="graduation-cap" class="w-4.5 h-4.5 text-[#0066CC] shrink-0" />
                                            <span class="min-w-0 text-left">
                                                <span class="block text-[10px] text-slate-400 font-medium leading-none">Lớp hiện tại</span>
                                                <span class="block text-xs sm:text-sm font-bold text-slate-800 leading-tight mt-0.5 truncate" x-text="grade"></span>
                                            </span>
                                        </span>
                                        <x-lucide name="chevron-down" class="w-3.5 h-3.5 text-slate-400 group-hover:text-blue-600 transition-colors shrink-0 ml-1" />
                                    </button>
                                    <div x-show="open" x-cloak x-transition class="absolute left-0 top-full mt-1 w-full bg-white rounded-xl border border-sky-100 shadow-xl py-1 z-30 max-h-56 overflow-y-auto">
                                        @foreach ($grades as $g)
                                            <button type="button" @click="grade = @js($g); open = false"
                                                    class="w-full text-left px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-sky-50 hover:text-blue-600 cursor-pointer">{{ $g }}</button>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Mục tiêu học --}}
                                <div x-data="{ open: false }" @click.outside="open = false" class="relative w-full sm:flex-1 min-w-0 sm:min-w-[180px] xl:min-w-[220px]">
                                    <input type="hidden" name="q" :value="goal">
                                    <button type="button" @click="open = !open"
                                            class="w-full bg-[#F0F6FC] hover:bg-sky-100/70 border border-sky-200/90 rounded-xl px-3 py-2 flex items-center justify-between cursor-pointer transition-colors group">
                                        <span class="flex items-center gap-2 min-w-0">
                                            <x-lucide name="target" class="w-4.5 h-4.5 text-[#0066CC] shrink-0" />
                                            <span class="min-w-0 flex-1 text-left">
                                                <span class="block text-[10px] text-slate-400 font-medium leading-none">Mục tiêu học</span>
                                                <span class="block text-xs sm:text-sm font-bold text-slate-800 leading-tight truncate mt-0.5" x-text="goal"></span>
                                            </span>
                                        </span>
                                        <x-lucide name="chevron-down" class="w-3.5 h-3.5 text-slate-400 shrink-0 ml-1 group-hover:text-blue-600 transition-colors" />
                                    </button>
                                    <div x-show="open" x-cloak x-transition class="absolute left-0 top-full mt-1 w-full bg-white rounded-xl border border-sky-100 shadow-xl py-1 z-30">
                                        @foreach ($quickLinks as $link)
                                            <button type="button" @click="goal = @js($link); open = false"
                                                    class="w-full text-left px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-sky-50 hover:text-blue-600 cursor-pointer">{{ $link }}</button>
                                        @endforeach
                                    </div>
                                </div>

                                <button type="submit" class="w-full sm:w-auto shrink-0 bg-gradient-to-r from-[#FBBF24] via-[#F59E0B] to-[#F59E0B] hover:brightness-105 active:scale-98 text-[#451A03] font-black text-xs sm:text-sm py-2 px-4 sm:px-5 rounded-full shadow-md shadow-amber-200/60 flex items-center justify-center gap-1.5 transition-all cursor-pointer whitespace-nowrap">
                                    <span>Tìm kiếm lớp học</span><span class="text-sm font-bold">→</span>
                                </button>
                            </div>
                        </form>

                        <div class="mt-2.5 sm:mt-3 flex items-center gap-1.5 sm:gap-2 overflow-x-auto sm:flex-wrap no-scrollbar pb-1">
                            @foreach ($quickLinks as $link)
                                <button type="button" @click="goal = @js($link)"
                                        class="border rounded-full px-3 sm:px-3.5 py-1 sm:py-1.5 text-xs sm:text-sm font-medium cursor-pointer transition-all shadow-2xs whitespace-nowrap shrink-0"
                                        :class="goal === @js($link) ? 'bg-white text-blue-700 font-bold border-blue-400 ring-2 ring-blue-100 shadow-xs' : 'bg-white/90 hover:bg-white text-slate-700 border-slate-200/90 hover:border-blue-300 hover:text-blue-600'">{{ $link }}</button>
                            @endforeach
                        </div>
                    </div>
                </section>

                {{-- ═══ CHƯƠNG TRÌNH HỌC NỔI BẬT ═══ --}}
                <section id="courses" class="bg-white rounded-3xl p-4 sm:p-6 border border-sky-100 shadow-[0_2px_10px_rgba(0,100,220,0.04)] relative">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2.5 sm:gap-3">
                            <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-blue-600 flex items-center justify-center text-white text-sm shadow-2xs">📺</div>
                            <div>
                                <h3 class="text-sm sm:text-lg font-bold text-slate-900 leading-tight">Chương trình học nổi bật</h3>
                                <p class="text-xs sm:text-sm text-slate-500 mt-0.5 hidden sm:block">Kết hợp giữa luyện tập, giáo trình, lớp học phù hợp, giáo viên và chuyên gia cao cấp</p>
                            </div>
                        </div>
                        <a href="{{ route('courses.index') }}" class="text-xs sm:text-sm font-bold text-blue-600 hover:text-blue-700 flex items-center gap-1 shrink-0">Xem tất cả <span>→</span></a>
                    </div>

                    <div class="relative">
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3.5 text-center">
                            @foreach ($featuredCourses as $course)
                                <div class="bg-gradient-to-b {{ $course['bgClass'] }} rounded-2xl border p-4 flex flex-col items-center justify-between hover:shadow-md transition-all duration-200 group">
                                    <div class="w-full h-24 sm:h-28 flex items-center justify-center mb-2.5">
                                        <img src="{{ asset('assets/'.$course['image']) }}" alt="{{ $course['title'] }}" class="h-full object-contain group-hover:scale-106 transition-transform duration-200">
                                    </div>
                                    <div class="flex-1 flex flex-col justify-between w-full">
                                        <div>
                                            <h4 class="text-xs sm:text-sm font-bold text-slate-900 leading-tight mb-1.5">{{ $course['title'] }}</h4>
                                            <p class="text-xs text-slate-600 leading-relaxed mb-3.5 line-clamp-2">{{ $course['desc'] }}</p>
                                        </div>
                                        <a href="{{ $course['url'] }}" class="w-full py-2 px-3 rounded-full text-xs font-bold {{ $course['btnClass'] }} shadow-2xs transition-opacity flex items-center justify-center gap-1 cursor-pointer">
                                            <span>{{ $course['btnText'] }}</span><span class="font-bold">→</span>
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>

                {{-- ═══ LỘ TRÌNH HỌC ═══ --}}
                <section id="path" class="bg-white rounded-3xl p-4 sm:p-6 border border-sky-100 shadow-[0_2px_10px_rgba(0,100,220,0.04)]">
                    <div class="flex items-center gap-2.5 sm:gap-3 mb-4">
                        <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-amber-400 flex items-center justify-center text-amber-950 text-sm shadow-2xs">🏅</div>
                        <div>
                            <h3 class="text-sm sm:text-lg font-bold text-slate-900 leading-tight">Lộ trình học chuyên nghiệp</h3>
                            <p class="text-xs sm:text-sm text-slate-500 mt-0.5 hidden sm:block">Định hướng rõ ràng - Tiết kiệm thời gian - Đảm bảo chinh phục mục tiêu</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3 items-center mb-4 py-1">
                        @foreach ($learningSteps as $step)
                            <div class="flex flex-col items-center text-center relative p-1 rounded-xl">
                                <img src="{{ asset('assets/'.$step['img']) }}" alt="{{ $step['step'] }}" class="w-10 h-10 sm:w-12 sm:h-12 object-contain mb-2">
                                <h5 class="text-xs sm:text-sm font-bold text-slate-900 leading-tight">{{ $step['step'] }}</h5>
                                <p class="text-[11px] sm:text-xs text-slate-500 mt-0.5 leading-snug">{{ $step['desc'] }}</p>
                                @unless ($loop->last)
                                    <span class="hidden md:block absolute -right-1.5 top-4.5 text-slate-300 font-bold text-base">›</span>
                                @endunless
                            </div>
                        @endforeach
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 pt-4 border-t border-slate-100">
                        @foreach ($audiencePills as $pill)
                            <a href="{{ $pill['url'] }}" class="bg-[#F0F7FD] hover:bg-sky-100/70 rounded-2xl p-2.5 flex items-center gap-2.5 border border-sky-100 cursor-pointer transition-colors">
                                <img src="{{ asset('assets/'.$pill['img']) }}" alt="{{ $pill['title'] }}" class="w-9 h-9 rounded-full object-cover shrink-0 shadow-2xs">
                                <div class="overflow-hidden text-left">
                                    <p class="text-xs sm:text-sm font-bold text-slate-900 truncate leading-tight">{{ $pill['title'] }}</p>
                                    <p class="text-[11px] text-slate-500 truncate leading-tight mt-0.5">{{ $pill['desc'] }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            </main>

            {{-- ═══ CỘT PHẢI ═══ --}}
            <aside id="leaderboard" class="flex flex-col gap-4" x-data="{ grade: 'Lớp 10' }">

                {{-- Thẻ 1: Hành trình học --}}
                <div class="bg-white rounded-3xl p-3.5 xl:p-5 border border-sky-100 shadow-[0_2px_8px_rgba(0,100,220,0.04)]">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-1.5 xl:gap-2">
                            <span class="w-5 h-5 rounded-md bg-blue-600 text-white flex items-center justify-center text-[10px] shadow-2xs font-bold">★</span>
                            <h4 class="text-xs xl:text-sm 2xl:text-base font-bold text-slate-800 leading-tight">Hành trình học của bạn</h4>
                        </div>
                        <div x-data="{ open: false }" @click.outside="open = false" class="relative">
                            <button type="button" @click="open = !open"
                                    class="bg-[#F0F6FC] hover:bg-sky-100/70 border border-sky-200/90 rounded-xl px-2 xl:px-2.5 py-1 flex items-center gap-1 cursor-pointer transition-colors text-[11px] font-semibold text-slate-700">
                                <span x-text="grade"></span>
                                <x-lucide name="chevron-down" class="w-3 h-3 text-slate-400" />
                            </button>
                            <div x-show="open" x-cloak x-transition class="absolute right-0 top-full mt-1 w-24 bg-white rounded-xl border border-sky-100 shadow-xl py-1 z-30 max-h-56 overflow-y-auto">
                                @foreach ($grades as $g)
                                    <button type="button" @click="grade = @js($g); open = false" class="w-full text-left px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-sky-50 hover:text-blue-600 cursor-pointer">{{ $g }}</button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="mb-3.5">
                        <div class="flex justify-between text-xs font-bold mb-1.5">
                            <span class="text-blue-600">Tiến độ tổng thể</span>
                            <span class="text-blue-600">65%</span>
                        </div>
                        <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                            <div class="bg-gradient-to-r from-sky-400 to-blue-600 h-2 rounded-full w-[65%] shadow-xs"></div>
                        </div>
                    </div>

                    <a href="{{ auth()->check() ? route('dashboard') : route('login') }}"
                       class="bg-gradient-to-r from-blue-50/70 to-sky-50/70 border border-blue-100 rounded-2xl p-2.5 xl:p-3 mb-3 flex items-center justify-between cursor-pointer hover:border-blue-200 transition-colors">
                        <div class="overflow-hidden">
                            <p class="text-[10px] xl:text-[11px] font-bold text-blue-600 uppercase tracking-wide">Tiếp tục học</p>
                            <p class="text-xs xl:text-sm font-bold text-slate-900 truncate mt-0.5">Bài 12: Cấu trúc dữ liệu và giải thuật</p>
                        </div>
                        <x-lucide name="chevron-right" class="w-4 h-4 text-blue-500 shrink-0 ml-1.5" />
                    </a>

                    <div class="grid grid-cols-3 gap-1.5 xl:gap-2.5 text-center">
                        @foreach ([['12', 'Bài đã xong', 'text-blue-600'], ['8', 'Đang học', 'text-emerald-600'], ['3', 'Chưa học', 'text-amber-500']] as $stat)
                            <div class="bg-slate-50 rounded-2xl p-2 xl:p-2.5 border border-slate-100">
                                <p class="text-base xl:text-lg 2xl:text-xl font-black {{ $stat[2] }} leading-tight">{{ $stat[0] }}</p>
                                <p class="text-[10px] xl:text-[11px] text-slate-500 font-medium leading-tight mt-0.5">{{ $stat[1] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Thẻ 2: Thông báo quan trọng --}}
                <div class="bg-white rounded-3xl p-3.5 xl:p-5 border border-sky-100 shadow-[0_2px_8px_rgba(0,100,220,0.04)]">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-xs xl:text-sm 2xl:text-base font-bold text-slate-800">Thông báo quan trọng</h4>
                        <a href="{{ auth()->check() ? route('dashboard') : route('competitions.index') }}" class="text-[11px] xl:text-xs font-semibold text-blue-600 hover:text-blue-700">Xem tất cả →</a>
                    </div>
                    <div class="flex flex-col gap-2">
                        @foreach ($noticeCards as $item)
                            <a href="{{ $item['url'] }}" class="flex items-start gap-2 text-xs xl:text-sm group cursor-pointer hover:bg-sky-50/70 p-1.5 xl:p-2 rounded-xl transition-colors">
                                <span class="text-sm xl:text-base mt-0.5">{{ $item['icon'] }}</span>
                                <div class="flex-1 overflow-hidden">
                                    <p class="text-xs xl:text-sm font-semibold text-slate-800 group-hover:text-blue-600 transition-colors leading-snug truncate">{{ $item['title'] }}</p>
                                    <p class="text-[10px] xl:text-[11px] text-slate-400 mt-0.5">{{ $item['time'] }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>

                {{-- Thẻ 3: Top học sinh xuất sắc --}}
                <div class="bg-white rounded-3xl p-3.5 xl:p-5 border border-sky-100 shadow-[0_2px_8px_rgba(0,100,220,0.04)]">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-1.5 xl:gap-2">
                            <span class="text-amber-400 text-base xl:text-lg">⭐</span>
                            <h4 class="text-xs xl:text-sm 2xl:text-base font-bold text-slate-800 leading-tight">Top học sinh xuất sắc</h4>
                        </div>
                        <a href="{{ route('leaderboard.index') }}" class="text-[11px] xl:text-xs font-semibold text-blue-600 hover:text-blue-700">Xem bảng xếp hạng →</a>
                    </div>

                    <div class="flex flex-col gap-2 mb-3.5">
                        @foreach ($topStudents as $st)
                            <div class="flex items-center justify-between py-1.5 px-2 rounded-xl hover:bg-sky-50 transition-colors text-xs sm:text-sm">
                                <div class="flex items-center gap-2.5">
                                    <span class="w-5 text-center font-bold text-slate-500 text-xs sm:text-sm">{{ $st['rank'] }}</span>
                                    <img src="{{ asset('assets/'.$st['avatar']) }}" alt="{{ $st['name'] }}" class="w-7 h-7 sm:w-8 sm:h-8 rounded-full border border-sky-200 object-cover shadow-2xs">
                                    <span class="font-semibold text-slate-800">{{ $st['name'] }}</span>
                                </div>
                                <div class="flex items-center gap-2.5">
                                    <span class="text-xs text-slate-400">{{ $st['class'] }}</span>
                                    <span class="text-xs sm:text-sm font-bold text-blue-600">{{ $st['score'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <a href="{{ route('leaderboard.index') }}" class="block overflow-hidden rounded-2xl border border-sky-100 cursor-pointer hover:shadow-md transition-shadow">
                        <img src="{{ asset('assets/achieve-banner.png') }}" alt="Cùng chinh phục thành tích cao hơn!" class="w-full h-auto object-cover">
                    </a>
                </div>

                {{-- Banner --}}
                <a href="{{ route('courses.index') }}" class="block bg-white rounded-3xl overflow-hidden border border-sky-100 shadow-[0_2px_8px_rgba(0,100,220,0.04)] cursor-pointer hover:shadow-md transition-all group">
                    <img src="{{ asset('assets/sidebar-plane.jpg') }}" alt="Cùng nhau kiến tạo tương lai số" class="w-full object-cover group-hover:scale-102 transition-transform duration-300">
                </a>

                {{-- Thẻ giáo viên --}}
                <a href="{{ route('teachers.index') }}" class="block bg-white rounded-3xl p-3.5 xl:p-4 border border-sky-100 shadow-[0_2px_8px_rgba(0,100,220,0.04)] text-center">
                    <div class="flex items-center gap-2.5 xl:gap-3 text-left mb-2.5 xl:mb-3">
                        <img src="{{ asset('assets/teacher-thanh.png') }}" alt="Thầy Nguyễn Tiến Thành" class="w-9 h-9 xl:w-11 xl:h-11 rounded-full border-2 border-sky-300 object-cover shrink-0 shadow-2xs">
                        <div class="overflow-hidden">
                            <h4 class="text-xs xl:text-sm font-bold text-slate-900 leading-tight truncate">Thầy Nguyễn Tiến Thành</h4>
                            <p class="text-[10px] xl:text-xs text-slate-500 leading-tight mt-0.5 truncate">GV THPT Chuyên Thái Bình</p>
                        </div>
                    </div>
                    <p class="text-[11px] xl:text-xs 2xl:text-sm font-bold text-blue-700 pt-2.5 xl:pt-3 border-t border-sky-100 leading-snug">Kiến thức là chìa khóa mở ra tương lai</p>
                </a>
            </aside>
        </div>

        {{-- ═══ 4. TÀI LIỆU NỔI BẬT ═══ --}}
        <section id="materials" x-data="{ tab: @js($defaultMaterialTab) }" class="mt-6 bg-white rounded-3xl p-5 sm:p-6 border border-sky-100 shadow-[0_2px_10px_rgba(0,100,220,0.04)]">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-blue-600 flex items-center justify-center text-white text-sm shadow-2xs"><x-lucide name="file-text" class="w-5 h-5 text-white" /></div>
                    <div>
                        <h3 class="text-base sm:text-lg font-bold text-slate-900 leading-tight">Tài liệu nổi bật</h3>
                        <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Sách, chuyên đề, đề thi chất lượng, được biên soạn bởi đội ngũ giáo viên và chuyên gia uy tín</p>
                    </div>
                </div>

                <div class="flex items-center gap-2.5">
                    <div class="inline-flex bg-slate-100 p-1 rounded-xl text-xs sm:text-sm font-medium">
                        @foreach ($materialTabs as $tab)
                            <button type="button" @click="tab = @js($tab['key'])"
                                    class="px-3.5 py-1.5 rounded-lg text-xs sm:text-sm transition-all cursor-pointer"
                                    :class="tab === @js($tab['key']) ? 'bg-blue-600 text-white shadow-2xs font-bold' : 'text-slate-600 hover:text-blue-600'">{{ $tab['label'] }}</button>
                        @endforeach
                    </div>
                    {{-- "Xem tất cả" bám theo tab đang mở, sang đúng tab đó ở trang Tài liệu. --}}
                    @foreach ($materialTabs as $tab)
                        <a href="{{ $tab['href'] }}" x-show="tab === @js($tab['key'])" x-cloak
                           class="text-xs sm:text-sm font-bold text-blue-600 hover:text-blue-700 ml-1">Xem tất cả →</a>
                    @endforeach
                </div>
            </div>

            @foreach ($materialTabs as $tab)
                <div x-show="tab === @js($tab['key'])" x-cloak>
                    @if ($tab['items'] === [])
                        <div class="rounded-2xl border-2 border-dashed border-sky-100 py-12 text-center">
                            <p class="text-3xl mb-2">📚</p>
                            <p class="text-sm text-slate-500">Mục <strong>{{ $tab['label'] }}</strong> chưa có tài liệu nào được phát hành.</p>
                            <a href="{{ $tab['href'] }}" class="inline-block mt-2 text-xs font-bold text-blue-600 hover:text-blue-700">Xem trang Tài liệu →</a>
                        </div>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                            @foreach ($tab['items'] as $b)
                                <div class="bg-white rounded-2xl border border-sky-100 p-3.5 flex flex-row items-center gap-3.5 hover:shadow-md hover:border-sky-200 transition-all duration-200 group">
                                    <div class="w-[38%] shrink-0 h-36 flex items-center justify-center">
                                        <img src="{{ $b['image'] }}" alt="{{ $b['title'] }}" class="h-full object-contain group-hover:scale-106 transition-transform duration-200">
                                    </div>
                                    <div class="w-[62%] flex flex-col justify-between h-full text-left">
                                        <div>
                                            <h4 class="text-xs sm:text-sm font-bold text-slate-900 leading-snug line-clamp-2 mb-2">{{ $b['title'] }}</h4>
                                            <div class="flex flex-col gap-1 text-xs text-slate-500 mb-3">
                                                <div class="flex items-center gap-1.5"><span class="text-blue-500 text-xs">👤</span><span class="truncate">{{ $b['tag'] }}</span></div>
                                                <div class="flex items-center gap-1.5"><span class="text-blue-500 text-xs">📄</span><span>{{ $b['pages'] }}</span></div>
                                                <div class="flex items-center gap-1.5"><span class="text-amber-500 text-xs">💡</span><span class="truncate">{{ $b['highlight'] }}</span></div>
                                            </div>
                                        </div>
                                        <a href="{{ $b['url'] }}" class="w-full py-2 px-3 rounded-full text-xs sm:text-sm font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer shadow-2xs {{ $b['btnStyle'] }}">
                                            <span>{{ $b['btnText'] }}</span><span class="font-bold">→</span>
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </section>

        {{-- ═══ 5. CUỘC THI | CÂU CHUYỆN ĐỒNG HÀNH ═══ --}}
        <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-5">
            <section id="contests" class="bg-white rounded-3xl p-5 sm:p-6 border border-sky-100 shadow-[0_2px_10px_rgba(0,100,220,0.04)] flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-blue-600 flex items-center justify-center text-white text-sm shadow-2xs"><x-lucide name="trophy" class="w-5 h-5 text-white" /></div>
                            <div>
                                <h3 class="text-base sm:text-lg font-bold text-slate-900 leading-tight">Cuộc thi &amp; khảo sát</h3>
                                <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Được tổ chức thường xuyên, công bằng và uy tín</p>
                            </div>
                        </div>
                        <a href="{{ route('competitions.index') }}" class="text-xs sm:text-sm font-bold text-blue-600 hover:text-blue-700">Xem tất cả →</a>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        @foreach ($competitionCards as $c)
                            <div class="bg-[#F8FBFE] rounded-2xl border border-sky-100/80 p-3 flex flex-col justify-between hover:shadow-md transition-all group">
                                <div>
                                    <div class="w-full h-28 overflow-hidden rounded-xl mb-2.5 flex items-center justify-center">
                                        <img src="{{ $c['image'] }}" alt="{{ $c['title'] }}" class="h-full object-contain group-hover:scale-106 transition-transform duration-200">
                                    </div>
                                    <h4 class="text-xs sm:text-sm font-bold text-slate-900 leading-snug line-clamp-2 mb-2">{{ $c['title'] }}</h4>
                                    <div class="flex items-center gap-1.5 text-xs text-slate-500 mb-1.5">
                                        <x-lucide name="calendar" class="w-3.5 h-3.5 text-slate-400" />
                                        <span class="truncate">{{ $c['time'] }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-xs font-semibold mb-3">
                                        <span class="w-2 h-2 rounded-full {{ $c['statusDot'] }}"></span>
                                        <span class="{{ $c['statusColor'] }}">{{ $c['status'] }}</span>
                                    </div>
                                </div>
                                <a href="{{ $c['url'] }}" class="w-full py-2 px-3 rounded-full text-xs sm:text-sm font-bold text-white bg-[#38BDF8] hover:bg-sky-500 shadow-2xs transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                                    <span>Xem cuộc thi</span><span class="font-bold">→</span>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section id="testimonials" class="bg-white rounded-3xl p-5 sm:p-6 border border-sky-100 shadow-[0_2px_10px_rgba(0,100,220,0.04)] flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-blue-600 flex items-center justify-center text-white text-sm shadow-2xs"><x-lucide name="heart" class="w-5 h-5 text-white fill-white" /></div>
                            <div>
                                <h3 class="text-base sm:text-lg font-bold text-slate-900 leading-tight">Câu chuyện đồng hành</h3>
                                <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Những câu chuyện thật, truyền cảm hứng thật</p>
                            </div>
                        </div>
                        <a href="{{ route('teachers.index') }}" class="text-xs sm:text-sm font-bold text-blue-600 hover:text-blue-700">Xem thêm câu chuyện →</a>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        @foreach ($testimonials as $t)
                            <div class="bg-[#F8FBFE] rounded-2xl border border-sky-100/80 overflow-hidden flex flex-col justify-between hover:shadow-md transition-all">
                                <div class="w-full h-28 overflow-hidden">
                                    <img src="{{ asset('assets/'.$t['banner']) }}" alt="{{ $t['author'] }}" class="w-full h-full object-cover">
                                </div>
                                <div class="p-3 flex-1 flex flex-col justify-between bg-white m-2 rounded-xl border border-sky-50 shadow-2xs">
                                    <p class="text-xs text-slate-600 italic leading-relaxed line-clamp-3 mb-2.5">{{ $t['quote'] }}</p>
                                    <div class="flex items-center gap-2.5 pt-2.5 border-t border-slate-100">
                                        <img src="{{ asset('assets/'.$t['avatar']) }}" alt="{{ $t['author'] }}" class="w-7 h-7 rounded-full object-cover border border-sky-200 shadow-2xs">
                                        <div class="overflow-hidden text-left">
                                            <p class="text-xs sm:text-sm font-bold text-slate-900 truncate leading-none">{{ $t['author'] }}</p>
                                            <p class="text-[11px] text-slate-400 truncate leading-none mt-1">{{ $t['role'] }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        </div>

        {{-- ═══ 6. CÂU HỎI THƯỜNG GẶP | CẦN HỖ TRỢ ═══ --}}
        <div id="support" class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-5 items-stretch">
            <section class="h-full bg-white rounded-3xl p-5 sm:p-6 border border-sky-100 shadow-[0_2px_10px_rgba(0,100,220,0.04)] flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-9 h-9 rounded-xl bg-blue-600 flex items-center justify-center text-white text-sm shadow-2xs"><x-lucide name="help-circle" class="w-5 h-5 text-white" /></div>
                        <div>
                            <h3 class="text-base sm:text-lg font-bold text-slate-900 leading-tight">Câu hỏi thường gặp</h3>
                            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Giải đáp nhanh những thắc mắc phổ biến</p>
                        </div>
                    </div>

                    <div x-data="{ openFaq: null }" class="border border-sky-100 rounded-2xl divide-y divide-sky-100 overflow-hidden bg-white">
                        @foreach ($faqItems as $idx => $faq)
                            <div class="transition-colors">
                                <button type="button" @click="openFaq = openFaq === {{ $idx }} ? null : {{ $idx }}"
                                        class="w-full text-left px-5 py-3.5 flex items-center justify-between text-xs sm:text-sm font-bold text-slate-800 hover:text-blue-600 transition-colors cursor-pointer">
                                    <span class="pr-2">{{ $idx + 1 }}. {{ $faq['q'] }}</span>
                                    <x-lucide name="chevron-down" class="w-4 h-4 text-slate-400 shrink-0 transition-transform duration-200"
                                              ::class="openFaq === {{ $idx }} ? 'rotate-180 text-blue-600' : ''" />
                                </button>
                                <div x-show="openFaq === {{ $idx }}" x-cloak x-transition
                                     class="px-5 pb-4 text-xs sm:text-sm text-slate-600 bg-sky-50/40 leading-relaxed border-t border-sky-50 pt-3">{{ $faq['a'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="h-full relative overflow-hidden rounded-3xl p-6 sm:p-7 border border-sky-100 shadow-[0_2px_10px_rgba(0,100,220,0.04)] flex flex-col justify-between">
                <img src="{{ asset('assets/support-banner-bg.jpg') }}" alt="Cần hỗ trợ?" class="absolute inset-0 w-full h-full object-cover object-right pointer-events-none select-none z-0">
                <div class="absolute top-5 right-5 sm:right-7 bg-white/95 backdrop-blur-xs px-3.5 py-2 rounded-2xl border border-sky-200 shadow-xs text-xs font-semibold text-sky-800 leading-tight text-center pointer-events-none z-10 hidden sm:block">Chúng tôi<br>luôn ở đây<br>cùng bạn!</div>

                <div class="relative z-10 max-w-[54%] flex flex-col justify-between h-full">
                    <div>
                        <div class="flex items-center gap-3 mb-2.5">
                            <div class="w-9 h-9 rounded-xl bg-blue-600 flex items-center justify-center text-white text-sm shadow-2xs shrink-0"><x-lucide name="headphones" class="w-5 h-5 text-white" /></div>
                            <div>
                                <h3 class="text-base sm:text-lg font-bold text-slate-900 leading-tight">Cần hỗ trợ?</h3>
                                <p class="text-xs sm:text-sm text-slate-500 mt-0.5 leading-snug">Đội ngũ tư vấn luôn sẵn sàng đồng hành cùng bạn trên hành trình chinh phục tri thức.</p>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2.5 my-3.5 text-xs sm:text-sm font-medium text-slate-700">
                            @foreach (['Tư vấn lộ trình học phù hợp', 'Hỗ trợ kỹ thuật, giải đáp thắc mắc', 'Đồng hành cùng học sinh và phụ huynh'] as $line)
                                <div class="flex items-center gap-2.5">
                                    <x-lucide name="check-circle" class="w-4 h-4 text-emerald-500 fill-emerald-100 shrink-0" />
                                    <span>{{ $line }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="pt-2">
                        <a href="{{ route('info.index') }}#lien-he" class="bg-[#0091FF] hover:bg-blue-600 text-white font-bold text-xs sm:text-sm py-2.5 px-6 rounded-full shadow-md inline-flex items-center gap-2 transition-all cursor-pointer">
                            <span>Liên hệ tư vấn</span><span class="text-sm font-bold">→</span>
                        </a>
                    </div>
                </div>
            </section>
        </div>
    </div>

    @push('scripts')
        <script>
            // Hero 4 slide: tự chạy 4.5 giây/slide, dừng khi rê chuột vào — đúng như source React.
            function heroSlider(slides) {
                return {
                    slides: slides,
                    current: 0,
                    paused: false,
                    grade: 'Lớp 10',
                    goal: slides[0].defaultGoal,
                    init() {
                        setInterval(() => {
                            if (! this.paused) {
                                this.current = (this.current + 1) % this.slides.length;
                            }
                        }, 4500);
                    },
                    select(idx) { this.current = idx; this.goal = this.slides[idx].defaultGoal; },
                    prev() { this.select((this.current - 1 + this.slides.length) % this.slides.length); },
                    next() { this.select((this.current + 1) % this.slides.length); },
                };
            }
        </script>
    @endpush
@endsection
