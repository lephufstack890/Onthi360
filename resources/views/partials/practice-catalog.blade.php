{{-- ═══════════ KHO LUYỆN TẬP (dùng chung) ═══════════
     SỬA 18/9 (khách: "trang luyện tập trong học sinh xây tương tự trang luyện tập ngoài
     public"). Trước đây toàn bộ khối này nằm thẳng trong public/practice/index.blade.php;
     tách ra partial để HAI trang dùng CHUNG một bản, khỏi sửa một chỗ lệch chỗ kia:
       · public/practice/index.blade.php   (khách vãng lai)
       · student/practice/index.blade.php  (khu học sinh)

     Dữ liệu vẫn do App\Services\Public\PracticeService::indexData($viewer) cấp — trang học
     sinh gọi cùng service đó nên hai bên luôn cùng một kho bài, cùng cách tính tỷ lệ AC.

     Biến điều khiển (tuỳ chọn):
       $showCatalogHero = false  -> ẩn hero tối đầu trang (khu học sinh đã có banner riêng). --}}
@php
    $showCatalogHero = $showCatalogHero ?? true;

    /*
     * SỬA 1/10 (khách: "khi thoát ra thì nó về trang luyện tập public thì ok rồi nhưng mà nó
     * phải hiển thị ở tab đề thi luyện tập") — tab mở đầu tiên quyết định Ở PHÍA MÁY CHỦ.
     *
     * Lần trước chỉ đọc ?tab bằng JS trong init() của Alpine; cách đó phụ thuộc vào lúc Alpine
     * khởi tạo và vào bộ nhớ đệm của view/trình duyệt. Quyết ở đây thì giá trị nằm sẵn trong
     * HTML máy chủ trả về: Alpine nhận đúng tab ngay từ lúc dựng, và khối không được chọn còn
     * được ẩn sẵn bằng style nên không chớp nháy trong lúc chờ Alpine.
     */
    $catalogMode = request()->query('tab') === 'de-thi' || request()->query('tab') === 'exams'
        ? 'exams'
        : 'problems';
@endphp

{{-- SỬA 1/10 (khách: "ngoài trang luyện tập public thêm 2 cột tỉnh thành và năm luôn nha") —
     Bảng bài tập trước đây là lưới 5 cột, giờ 7 cột. VÌ SAO viết CSS thường chứ không đổi class
     Tailwind tuỳ ý (lg:grid-cols-[...]): máy chủ KHÔNG chạy được vite, mọi class phải có sẵn
     trong public/build/assets/app-*.css đã build — class tuỳ ý với bộ số MỚI không có trong đó
     nên sẽ không ra style nào, bảng vỡ thành 1 cột. Dùng đúng mốc 1024px để khớp breakpoint lg.

     Hai cột mới hẹp (Tỉnh thành 120px, Năm 64px) và các cột cũ co lại một chút, để cột Tên bài
     vẫn còn ~290px ở màn 1024px thay vì bị bóp mất. --}}
{{-- SỬA 8/10 (khách: "trang luyện tập public tạm thời ẩn cột Tỉnh thành với Tác giả đi") — công tắc TẠM THỜI.
     false = ẩn 2 cột ở bảng bài tập (giữ nguyên dữ liệu + mã, chỉ không in ra). Mặc định true (trang học sinh
     dùng chung partial này vẫn như cũ); trang công khai public/practice/index truyền false. Muốn hiện lại ở
     trang công khai: bỏ tham số đó đi. --}}
@php $showProblemProvinceAuthor = $showProblemProvinceAuthor ?? true; @endphp
{{-- SỬA 8/10 (khách: "ẩn nút Lịch sử làm bài của tôi ở trang luyện tập public") — công tắc nút đi sang Lịch sử làm bài.
     Mặc định true (trang luyện tập của học sinh dùng chung partial này vẫn có nút); trang công khai truyền false. --}}
@php $showMyHistoryButton = $showMyHistoryButton ?? true; @endphp

<style>
    @media (min-width: 1024px) {
        .oi-prob-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 148px 120px 64px 100px 132px 140px;
            align-items: center;
            gap: .625rem;
        }

        /* SỬA 4/10 (khách: "ngoài luyện tập public thêm cột tác giả đổ ra nữa nha") — ở khoảng
           1024–1279px thì ẨN cột Tác giả. Ở 1024px, 7 cột cũ đã ăn gần hết bề ngang, chen thêm
           một cột nữa thì cột Tên bài chỉ còn hơn 100px — tên bài bị bóp thành 3 dòng, đổi một
           cột mới lấy cột quan trọng nhất là lỗ. Mobile KHÔNG dính luật này: ở đó mỗi cột là
           một ô xếp dọc có nhãn riêng nên Tác giả vẫn hiện bình thường. */
        .oi-prob-author { display: none; }
    }

    @media (min-width: 1280px) {
        .oi-prob-grid {
            grid-template-columns: minmax(0, 1fr) 140px 112px 64px 120px 96px 124px 132px;
        }

        .oi-prob-author { display: block; }
    }

    /* SỬA 8/10 — bảng bài tập khi ẨN cột Tỉnh thành + Tác giả: còn 6 cột (Tên · Chuyên đề · Năm · Độ khó ·
       Tỷ lệ AC · Hành động). Thêm class oi-prob-grid--lite nên thắng quy tắc 7/8 cột ở trên và ở tab Bài được giao. */
    @media (min-width: 1024px) {
        .oi-prob-grid.oi-prob-grid--lite { grid-template-columns: minmax(0, 1fr) 148px 64px 100px 132px 140px; }
        .oi-prob-grid.oi-prob-grid--lite.oi-prob-grid--asg { grid-template-columns: minmax(0, 1fr) 148px 64px 100px 200px 140px; }
    }
    @media (min-width: 1280px) {
        .oi-prob-grid.oi-prob-grid--lite { grid-template-columns: minmax(0, 1fr) 140px 64px 96px 124px 132px; }
        .oi-prob-grid.oi-prob-grid--lite.oi-prob-grid--asg { grid-template-columns: minmax(0, 1fr) 140px 64px 96px 190px 132px; }
    }

    /* SỬA 8/10 — nút sắp xếp ở đầu cột (PracticeSortButton của source mới). */
    .oi-sort-btn { display: inline-flex; align-items: center; gap: .375rem; padding: 0; margin: 0; border: 0; background: none; font: inherit; letter-spacing: inherit; text-transform: inherit; color: inherit; text-align: left; cursor: pointer; transition: color .15s; }
    .oi-sort-btn:hover, .oi-sort-btn:focus-visible { color: #126F91; outline: none; }
    .oi-sort-btn > svg { flex: none; width: .875rem; height: .875rem; color: #8BA0AF; }
    .oi-sort-btn.is-active > svg { color: #126F91; }
    .oi-sort-btn--compact { gap: .25rem; padding: .375rem .5rem; border: 1px solid #D6E3EF; border-radius: .5rem; background: #EEF4FA; font-size: 10px; font-weight: 700; letter-spacing: 0; text-transform: none; color: #365B7A; }
    .oi-sort-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem; margin-bottom: .5rem; padding: .5rem .75rem; border: 1px solid #DDEAF0; border-radius: .75rem; background: #fff; }
    .oi-sort-bar > span { font-size: 11px; font-weight: 700; color: #45657D; }
    .oi-sort-bar > div { display: flex; flex-wrap: wrap; align-items: center; gap: .375rem; }
    @media (min-width: 1024px) { .oi-sort-bar { display: none; } }

    /* SỬA 8/10 — nhãn "dạng bài" nằm sát bên "Mã: …" (source mới). */
    .oi-code-line { display: flex; flex-wrap: wrap; align-items: center; column-gap: .5rem; row-gap: .25rem; margin-top: .25rem; font-size: 11px; color: #6B8295; }
    .oi-code-line .oi-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
    .oi-type-chip { display: inline-flex; flex: none; align-items: center; white-space: nowrap; padding: .125rem .375rem; border-radius: .375rem; background: #EEF4FA; font-size: 10px; font-weight: 600; color: #365B7A; }

    /* SỬA 8/10 — công tắc "Chỉ hiện bài chưa làm". */
    .oi-count-wrap { display: flex; align-items: center; gap: .75rem; margin-left: auto; }
    .oi-undone { display: inline-flex; align-items: center; gap: .5rem; min-height: 2rem; padding: .25rem .625rem .25rem .375rem; border: 1px solid #D6E3EF; border-radius: 999px; background: #F8FBFC; font: inherit; font-size: 11px; font-weight: 700; color: #45657D; cursor: pointer; transition: background .15s, border-color .15s, color .15s; }
    .oi-undone:hover { border-color: #B9CCDC; background: #EEF4FA; }
    .oi-undone:focus-visible { outline: 2px solid #CBEAF1; outline-offset: 2px; }
    .oi-undone-track { position: relative; flex: none; width: 2rem; height: 1.125rem; border-radius: 999px; background: #C5D3DE; transition: background .15s; }
    .oi-undone-knob { position: absolute; top: 2px; left: 2px; width: 14px; height: 14px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(18,59,104,.3); transition: transform .15s; }
    .oi-undone.is-on { border-color: #BFE3D3; background: #EFF9F5; color: #237052; }
    .oi-undone.is-on .oi-undone-track { background: #2F8A6B; }
    .oi-undone.is-on .oi-undone-knob { transform: translateX(14px); }
</style>

{{-- Bù các class Tailwind tuỳ ý MỚI chưa có trong bản CSS đã build (xem đầu tệp partial). --}}
@include('partials.practice-ui-fallback-style')
{{-- SỬA 7/10 — CSS riêng cho giao bài / bài được giao / bài đã giao (xem tệp). --}}
@include('partials.practice-assign-style')

@php
    $items = $items ?? [];
    $problems = $problems ?? [];
    $practiceTypes = $practiceTypes ?? [];
    $practiceTags = $practiceTags ?? [];
    $practiceTotal = $practiceTotal ?? 0;
    $canTakeDirectly = $canTakeDirectly ?? false;

    /*
     * SỬA 7/10 (khách: "học sinh có thêm phần bài được giao; giáo viên giao bài và xem nhật ký")
     * — quyền của người xem do Public\PracticeAssignmentService::scopeFor() cấp. Mặc định "khách
     * vãng lai" để trang nào include partial này mà chưa truyền biến vẫn chạy như cũ.
     */
    $assignScope = $assignScope ?? ['role' => 'guest', 'canViewAssigned' => false, 'canManage' => false, 'canAssign' => false];
    $managedAssignments = $managedAssignments ?? ['problem' => null, 'exam' => null];
    $assignRole = $assignScope['role'];
    $fmtScore = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
    $assignStatusLabels = ['completed' => 'Hoàn thành', 'pending' => 'Chờ chấm', 'incomplete' => 'Chưa hoàn thành'];

    // Phạm vi mở đầu (?scope=assigned|managed) — chỉ nhận khi người xem THỰC SỰ có quyền đó, nếu
    // không thì rơi về "Tất cả" thay vì hiện tab rỗng/không được phép.
    $requestedScope = request()->query('scope');
    $initialScope = match (true) {
        $requestedScope === 'assigned' && $assignScope['canViewAssigned'] => 'assigned',
        $requestedScope === 'managed' && $assignScope['canManage'] => 'managed',
        default => 'all',
    };

    // SỬA 30/9 (khách: "độ khó phân thành 1-5 sao: Cơ bản, Dễ, Khá, Khó, Rất khó") — dải chip
    // này trước đây gõ tay 4 mức, lệch với bộ lọc/độ khó ở kho. Giờ sinh thẳng từ
    // App\Support\QuestionDifficulty::LEVELS (nơi DUY NHẤT định nghĩa độ khó) — thêm/bớt mức
    // chỉ sửa 1 chỗ, không bao giờ có chuyện chip lọc ra 0 bài vì tên mức không khớp.
    $difficultyChipStyles = [
        'basic' => ['circle', 'text-[#4A7D9B] bg-[#F0F8FB] border-[#D5E8ED]'],
        'easy' => ['check-circle', 'text-[#397C68] bg-[#EFF9F5] border-[#D4EDE2]'],
        'fair' => ['trending-up', 'text-[#376B98] bg-[#EEF5FF] border-[#D4E3F7]'],
        'hard' => ['flame', 'text-[#8E6B2E] bg-[#FFF7E3] border-[#F2E1B6]'],
        'expert' => ['award', 'text-[#A15B5B] bg-[#FFF1F0] border-[#F3D8D6]'],
    ];
    $difficulties = [
        ['id' => 'all', 'label' => 'Mọi độ khó', 'icon' => null, 'color' => 'border-[#DDEAF0] bg-[#F8FAFB] text-[#45657D]'],
    ];
    foreach (\App\Support\QuestionDifficulty::LEVELS as $dKey => $dLabel) {
        [$dIcon, $dColor] = $difficultyChipStyles[$dKey] ?? ['circle', 'border-[#DDEAF0] bg-[#F8FAFB] text-[#45657D]'];
        $difficulties[] = ['id' => $dKey, 'label' => $dLabel, 'icon' => $dIcon, 'color' => $dColor];
    }

    // Dải chuyên đề: "Tất cả" + các chuyên đề có thật trong kho (tối đa 12 chuyên đề đầu).
    $topicChipIcons = ['route', 'git-branch', 'database', 'calculator', 'route', 'text-cursor-input'];

    // Hàng dữ liệu đưa sang Alpine để lọc/phân trang ngay tại chỗ.
    $problemRows = [];
    foreach ($problems as $p) {
        $problemRows[] = [
            'id' => $p['id'],
            'type' => $p['typeKey'],
            'tagIds' => $p['tagIds'],
            'difficulty' => $p['difficulty'],
            'status' => $p['status'],
            'search' => mb_strtolower(trim($p['title'].' '.$p['code'])),
            // SỬA 8/10 — dữ liệu để sắp xếp bảng theo Chuyên đề / Độ khó / Tỷ lệ AC.
            'titleText' => (string) $p['title'],
            // SỬA 8/10 (khách: "nút tắt/bật chưa làm") — đã làm = có ít nhất 1 lượt nộp hoặc đã AC/đang làm dở.
            'attempted' => ((int) ($p['userSubmissions'] ?? 0)) > 0 || ($p['status'] ?? 'todo') !== 'todo',
            'year' => (int) preg_replace('/\D+/', '', (string) ($p['examYearLabel'] ?? '')),
            'topicLabel' => (string) ($p['topicLabel'] ?? ''),
            'difficultyLevel' => (int) ($p['difficultyLevel'] ?? 0),
            'acRate' => (float) ($p['acRate'] ?? 0),
            // SỬA 7/10 — phục vụ 2 tab "Tất cả" / "Bài được giao".
            'inCatalog' => (bool) ($p['inCatalog'] ?? true),
            'assigned' => ($p['assignment'] ?? null) !== null,
            'assignStatus' => $p['assignment']['status'] ?? null,
            'deadlineTs' => $p['assignment']['deadlineTs'] ?? 0,
        ];
    }
    $examRows = [];
    // Thứ tự máy chủ trả về là MỚI NHẤT TRƯỚC (PracticeService dùng ->latest()), nên 'order'
    // chính là thứ tự "mới nhất trước" — ô sắp xếp lấy luôn nó làm lựa chọn mặc định.
    $loopOrder = 0;
    foreach ($items as $it) {
        // SỬA 2/10 — 'type' giờ là LOẠI ĐỀ (hsg_quoc_gia/chuyen_tin/…) cho dải chip mới, không
        // còn là coding/quiz. Thêm 'attempts' + 'order' cho ô sắp xếp, và ô tìm kiếm quét cả mô
        // tả + mã đề đúng như bản mẫu ("${exam.title} ${exam.subtitle} ${exam.id}").
        $examRows[] = [
            'id' => $it['id'],
            'type' => $it['category'] ?? 'none',
            'attempts' => (int) $it['attemptCount'],
            'order' => $loopOrder++,
            'title' => mb_strtolower(trim($it['title'])),
            'search' => mb_strtolower(trim($it['title'].' '.($it['subtitle'] ?? '').' '.($it['examCode'] ?? ''))),
            // SỬA 7/10 — bộ lọc tỉnh/thành + 2 tab "Tất cả đề" / "Đề được giao".
            'province' => $it['provinceLabel'] ?? '',
            'inCatalog' => (bool) ($it['inCatalog'] ?? true),
            'assigned' => ($it['assignment'] ?? null) !== null,
            'assignStatus' => $it['assignment']['status'] ?? null,
            'deadlineTs' => $it['assignment']['deadlineTs'] ?? 0,
        ];
    }

    $acCount = 0;
    $doingCount = 0;
    foreach ($problems as $p) {
        if ($p['status'] === 'ac') { $acCount++; }
        if ($p['status'] === 'doing') { $doingCount++; }
    }
@endphp


<div x-data="onthiPracticePage({{ Js::from([
        'problems' => $problemRows,
        'exams' => $examRows,
        'problemPageSize' => 5,
        'examPageSize' => 4,
        'initialMode' => $catalogMode,
        // SỬA 7/10 — quyền + phạm vi mở đầu + địa chỉ/mã CSRF để popup giao bài gọi máy chủ.
        'role' => $assignRole,
        'canViewAssigned' => $assignScope['canViewAssigned'],
        'canManage' => $assignScope['canManage'],
        'canAssign' => $assignScope['canAssign'],
        'initialProblemScope' => $catalogMode === 'problems' ? $initialScope : 'all',
        'initialExamScope' => $catalogMode === 'exams' ? $initialScope : 'all',
        'assignUrl' => $assignScope['canAssign'] ? route('practice.assign') : '',
        'assignSearchUrl' => $assignScope['canAssign'] ? route('practice.assign.students') : '',
        'csrf' => csrf_token(),
    ]) }})" class="flex flex-col gap-4 animate-fadeIn">

    {{-- ══════ [PRACTICE-01] HERO LUYỆN TẬP ══════ --}}
    @if ($showCatalogHero)
    <div class="relative overflow-hidden rounded-3xl border border-white/20 bg-gradient-to-r from-[#123B68] via-[#155A86] to-[#2A8B8A] p-5 text-white shadow-[0_12px_28px_rgba(18,59,104,0.1)] sm:p-6">
        <img src="{{ asset('assets/hero-practice.jpg') }}" alt="Kho luyện tập Ôn Thi 360"
             class="absolute inset-0 w-full h-full object-cover object-right pointer-events-none opacity-45 mix-blend-overlay">

        <div class="relative z-10 flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-2xl">
                <div class="mb-3 inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-white/15 px-3 py-1 text-[11px] font-bold text-sky-50 backdrop-blur">
                    <x-lucide name="flame" class="w-3.5 h-3.5" />
                    <span>Đấu trường Luyện tập Online Judge 24/7</span>
                </div>

                <h1 class="text-2xl font-black leading-tight tracking-tight text-white sm:text-2xl">Kho bài tập Thuật toán & Lập trình</h1>

                <p class="mt-2 max-w-xl text-xs leading-5 text-sky-100 sm:text-sm sm:leading-6">
                    {{ number_format($practiceTotal) }} câu hỏi bám sát đề thi HSG Quốc gia, Tuyển sinh 10 Chuyên Tin
                    và chuẩn Olympic Tin học quốc tế. Hệ thống chấm bài tự động tức thì.
                </p>

                <div class="mt-4 flex flex-wrap items-center gap-2.5">
                    {{-- SỬA 24/9 (khách: "bấm vào đó thì vào 1 câu bất ngờ random cho người ta
                         làm") — trước đây nút này dẫn sang màn chọn chuyên đề, mà tên nút là
                         "Làm bài NGAY". Giờ bốc thẳng một câu ngẫu nhiên (ưu tiên câu chưa từng
                         làm đúng) — xem Student\PracticeByQuestionController::random().

                         Dùng form POST chứ không phải thẻ <a>: hành động này GHI phiên luyện vào
                         session. Khách chưa đăng nhập thì vẫn là liên kết sang trang đăng nhập. --}}
                    @if ($canTakeDirectly)
                        <form method="POST" action="{{ route('student.practiceByQuestion.random') }}">
                            @csrf
                            <button type="submit"
                                    class="flex min-h-10 items-center gap-1.5 rounded-lg bg-white px-3.5 py-2 text-[11px] font-black text-[#126F91] shadow-[0_5px_12px_rgba(4,45,105,0.14)] transition hover:-translate-y-0.5 hover:bg-[#F4FBFF] focus:outline-none focus-visible:ring-4 focus-visible:ring-white/40 active:scale-[.98]">
                                <x-lucide name="shuffle" class="h-3.5 w-3.5" />Làm bài ngay<x-lucide name="chevron-right" class="h-3.5 w-3.5" />
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}"
                           class="flex min-h-10 items-center gap-1.5 rounded-lg bg-white px-3.5 py-2 text-[11px] font-black text-[#126F91] shadow-[0_5px_12px_rgba(4,45,105,0.14)] transition hover:-translate-y-0.5 hover:bg-[#F4FBFF] focus:outline-none focus-visible:ring-4 focus-visible:ring-white/40 active:scale-[.98]">
                            <x-lucide name="code-2" class="h-3.5 w-3.5" />Làm bài ngay<x-lucide name="chevron-right" class="h-3.5 w-3.5" />
                        </a>
                    @endif
                    <div class="flex min-h-9 items-center gap-1.5 rounded-lg border border-white/20 bg-white/15 px-2.5 py-1 text-[10px] font-bold backdrop-blur-sm">
                        <x-lucide name="check-circle" class="w-4 h-4 text-emerald-300" />
                        <span>Đã hoàn thành: {{ $acCount }} bài</span>
                    </div>
                    <div class="flex min-h-9 items-center gap-1.5 rounded-lg border border-white/20 bg-white/15 px-2.5 py-1 text-[10px] font-bold backdrop-blur-sm">
                        <x-lucide name="trending-up" class="w-4 h-4 text-amber-300" />
                        <span>Đang làm dở: {{ $doingCount }} bài</span>
                    </div>
                </div>
            </div>

            {{-- SỬA 8/10 (khách: "thêm UI mục khoanh đỏ trong source mới — Luyện tập hôm nay; dữ liệu lấy từ nhật ký
                 làm bài") — khung bên phải thành 2 ô như PracticePage.jsx: "Kho câu theo dạng" (nội dung cũ) +
                 "Luyện tập hôm nay" (mới). Xem partials/practice-daily-stats và PracticeDailyStatsService. --}}
            @include('partials.practice-daily-stats')
        </div>
    </div>
    @endif

    {{-- ══════ [PRACTICE-01A] CHỌN KIỂU LUYỆN ══════ --}}
    <div class="flex flex-col gap-3 rounded-2xl border border-[#DDEAF0] bg-white p-3 shadow-[0_2px_10px_rgba(28,91,121,0.04)] sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <p class="text-[11px] font-bold uppercase tracking-[.08em] text-[#607A90]">Không gian luyện tập</p>
            <p class="mt-0.5 text-sm font-extrabold text-[#123B68]">Chọn cách bạn muốn rèn luyện hôm nay</p>
        </div>
        <div role="tablist" aria-label="Kiểu luyện tập" class="grid grid-cols-2 gap-1 rounded-xl border border-[#DDEAF0] bg-[#F5F8FA] p-1 sm:w-auto">
            <button type="button" role="tab" :aria-selected="practiceMode === 'problems'" @click="changeMode('problems')"
                    class="flex min-h-10 items-center justify-center gap-1.5 rounded-lg px-3 text-[11px] font-extrabold transition"
                    :class="practiceMode === 'problems' ? 'bg-[#126F91] text-white shadow-[0_3px_8px_rgba(18,111,145,0.16)]' : 'text-[#45657D] hover:bg-white'">
                <x-lucide name="code-2" class="h-3.5 w-3.5" />Bài tập chuyên đề
            </button>
            <button type="button" role="tab" :aria-selected="practiceMode === 'exams'" @click="changeMode('exams')"
                    class="flex min-h-10 items-center justify-center gap-1.5 rounded-lg px-3 text-[11px] font-extrabold transition"
                    :class="practiceMode === 'exams' ? 'bg-[#126F91] text-white shadow-[0_3px_8px_rgba(18,111,145,0.16)]' : 'text-[#45657D] hover:bg-white'">
                <x-lucide name="file-text" class="h-3.5 w-3.5" />Đề thi luyện tập
            </button>
        </div>
    </div>

    {{-- ══════════════ CHẾ ĐỘ: BÀI TẬP CHUYÊN ĐỀ ══════════════ --}}
    <div x-show="practiceMode === 'problems'" @if ($catalogMode !== 'problems') style="display: none" @endif class="flex flex-col gap-4">

        {{-- [PRACTICE-02] TABS & BỘ LỌC --}}
        <div class="flex flex-col gap-3 rounded-2xl border border-[#DDEAF0] bg-white p-3 shadow-[0_2px_10px_rgba(28,91,121,0.04)]">
            <div class="flex items-center justify-between gap-3 border-b border-[#E7EFF3] pb-3">
                @include('partials.practice-scope-tabs', ['kind' => 'problem'])

                {{-- SỬA 8/10 (khách: "thêm nút tắt bật chưa làm để người ta làm những bài chưa làm") — công tắc chỉ hiện bài chưa làm (chỉ hiện khi đã đăng nhập). --}}
                <div class="oi-count-wrap">
                @auth
                <button type="button" role="switch" x-show="problemScope !== 'managed'" :aria-checked="onlyUndone ? 'true' : 'false'"
                        @click="toggleOnlyUndone()" class="oi-undone" :class="{ 'is-on': onlyUndone }" title="Chỉ hiện những bài bạn chưa làm">
                    <span class="oi-undone-track" aria-hidden="true"><span class="oi-undone-knob"></span></span>
                    <span>Chưa làm</span>
                </button>
                @endauth

                <span x-show="problemScope !== 'managed'" class="hidden text-[11px] font-medium text-[#607A90] sm:inline">
                    Hiển thị <strong x-text="filteredProblems.length"></strong> bài tập
                </span>
                </div>
            </div>

            {{-- SỬA 7/10 — chip lọc trạng thái của tab "Bài được giao". --}}
            @if ($assignScope['canViewAssigned'])
                @include('partials.practice-assign-status-chips', ['kind' => 'problem'])
            @endif

            <div x-show="problemScope !== 'managed'" class="flex flex-col items-stretch justify-between gap-3 md:flex-row md:items-center">
                <div class="relative flex-1">
                    <x-lucide name="search" class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#607A90]" />
                    <input type="text" aria-label="Tìm kiếm bài tập" placeholder="Tìm theo tên bài hoặc mã bài (VD: DP_LIS)..."
                           x-model="searchQuery"
                           class="min-h-11 w-full rounded-xl border border-[#D5E3E9] bg-[#F8FAFB] py-2 pl-10 pr-4 text-[13px] font-medium text-[#183D5E] placeholder:text-[#8193A3] focus:border-[#2D7FA3] focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#DDF1F6]">
                </div>

                {{-- SỬA 7/10 — dải tab theo DẠNG CÂU (trắc nghiệm/điền đáp án/lập trình…) nhường chỗ
                     cho thanh phạm vi theo bản mẫu mới; bộ lọc dạng câu vẫn còn, thu gọn thành ô chọn. --}}
                @if (count($practiceTypes) > 0)
                    <select aria-label="Lọc theo dạng bài" x-model="activeTab" @change="problemPageIndex = 1"
                            class="oi-select shrink-0 md:w-44" style="width:auto;min-height:44px">
                        <option value="all">Mọi dạng bài</option>
                        @foreach ($practiceTypes as $t)
                            <option value="{{ $t['value'] }}">{{ $t['label'] }}</option>
                        @endforeach
                    </select>
                @endif

                <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar">
                    <span class="type-label shrink-0 text-[#45657D]">Độ khó:</span>
                    @foreach ($difficulties as $d)
                        <button type="button" @click="setDifficulty(@js($d['id']))" :aria-pressed="selectedDifficulty === @js($d['id'])"
                                class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border px-2.5 py-1 text-[11px] font-bold transition-all whitespace-nowrap"
                                :class="selectedDifficulty === @js($d['id']) ? 'border-[#123B68] bg-[#123B68] text-white' : '{{ $d['color'] }} hover:brightness-[.98]'">
                            @if ($d['icon'])
                                {{-- SỬA 18/9 — LỖI CŨ (console báo "Alpine Expression Error: Invalid or
                                     unexpected token"): @js(...) đặt trong thuộc tính của COMPONENT
                                     Blade (<x-lucide>) KHÔNG được biên dịch, chuỗi "@js($d['id'])"
                                     lọt nguyên vào biểu thức Alpine -> lỗi cú pháp ở mọi nút độ khó.
                                     Chuyển binding sang thẻ <span> thường bọc ngoài icon. --}}
                                <span :class="selectedDifficulty === @js($d['id']) ? 'text-white' : ''"><x-lucide :name="$d['icon']" class="h-3.5 w-3.5" /></span>
                            @endif
                            {{ $d['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <div x-show="problemScope !== 'managed'" class="flex flex-col gap-4">
        {{-- [PRACTICE-03] CHUYÊN ĐỀ — bản mẫu mới bọc băng chuyên đề thành carousel có 2 nút
             cuộn hai bên để danh sách nhiều thẻ vẫn gọn. Cuộn bằng $refs của Alpine, không
             thêm mã script mới. --}}
        <div class="relative">
            <button type="button" aria-label="Cuộn chuyên đề sang trái" title="Cuộn sang trái"
                    @click="$refs.topicRail.scrollBy({ left: -260, behavior: 'smooth' })"
                    class="absolute left-0 top-1/2 z-10 hidden h-8 w-8 -translate-y-1/2 place-items-center rounded-full border border-[#D6E3EF] bg-white/95 text-[#123B68] shadow-[0_3px_10px_rgba(18,59,104,0.1)] transition hover:bg-[#EEF4FA] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#CBEAF1] sm:grid">
                <x-lucide name="chevron-left" class="h-4 w-4" />
            </button>
            <div x-ref="topicRail" class="flex items-center gap-1.5 overflow-x-auto scroll-smooth no-scrollbar py-0.5 sm:px-9">
            <button type="button" @click="setTopic('all')" :aria-pressed="selectedTopic === 'all'"
                    class="inline-flex min-h-9 items-center gap-1 rounded-xl border px-2.5 py-1 text-[11px] font-bold transition-all whitespace-nowrap"
                    :class="selectedTopic === 'all' ? 'border-[#123B68] bg-[#123B68] text-white' : 'border-[#D6E3EF] bg-[#EEF4FA] text-[#365B7A] hover:border-[#B9CCDC] hover:bg-[#F5F8FC]'">
                <span class="grid h-4 w-4 place-items-center rounded-md" :class="selectedTopic === 'all' ? 'bg-white/60' : 'bg-white'">
                    <x-lucide name="code-2" class="h-3 w-3" ::class="selectedTopic === 'all' ? 'text-[#126F91]' : 'text-[#2D7FA3]'" />
                </span>
                Tất cả chuyên đề
            </button>
            @foreach (array_slice($practiceTags, 0, 12) as $i => $tag)
                @php $icon = $topicChipIcons[$i % count($topicChipIcons)]; @endphp
                <button type="button" @click="setTopic({{ $tag['id'] }})" :aria-pressed="selectedTopic === {{ $tag['id'] }}"
                        class="inline-flex min-h-9 items-center gap-1 rounded-xl border px-2.5 py-1 text-[11px] font-bold transition-all whitespace-nowrap"
                        :class="selectedTopic === {{ $tag['id'] }} ? 'border-[#123B68] bg-[#123B68] text-white' : 'border-[#D6E3EF] bg-[#EEF4FA] text-[#365B7A] hover:border-[#B9CCDC] hover:bg-[#F5F8FC]'">
                    <span class="grid h-4 w-4 place-items-center rounded-md" :class="selectedTopic === {{ $tag['id'] }} ? 'bg-white/60' : 'bg-white'">
                        <x-lucide :name="$icon" class="h-3 w-3" ::class="selectedTopic === {{ $tag['id'] }} ? 'text-[#126F91]' : 'text-[#4C83B0]'" />
                    </span>
                    {{ $tag['name'] }}
                </button>
            @endforeach
            </div>
            <button type="button" aria-label="Cuộn chuyên đề sang phải" title="Cuộn sang phải"
                    @click="$refs.topicRail.scrollBy({ left: 260, behavior: 'smooth' })"
                    class="absolute right-0 top-1/2 z-10 hidden h-8 w-8 -translate-y-1/2 place-items-center rounded-full border border-[#D6E3EF] bg-white/95 text-[#123B68] shadow-[0_3px_10px_rgba(18,59,104,0.1)] transition hover:bg-[#EEF4FA] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#CBEAF1] sm:grid">
                <x-lucide name="chevron-right" class="h-4 w-4" />
            </button>
        </div>

        {{-- [PRACTICE-04] DANH SÁCH BÀI — dựng lại theo bản mẫu MỚI (education-main/src/
             components/PracticePage.jsx, 16/9): bảng 5 cột trên desktop, xếp thẻ trên mobile.

             Khác bản dựng cũ: cột "Trạng thái" rộng 120px bị bỏ — huy hiệu AC thu nhỏ nằm ngay
             cạnh tên bài, còn "Đã làm N lần / Chưa làm" thành viên thuốc bên phải tiêu đề.

             KHÁC bản mẫu 1 chỗ, có chủ ý: bản mẫu có nút "Nhật ký nộp bài" mở hộp thoại lịch sử
             nộp. Trang công khai KHÔNG có nguồn dữ liệu đó (lịch sử nộp là dữ liệu riêng của
             từng học sinh, chỉ có trong khu đã đăng nhập) — dựng nút mở hộp thoại rỗng là bịa
             tính năng, nên chỗ đó đặt chip DẠNG CÂU HỎI thật, giữ nguyên nhịp bố cục. --}}
        {{--
          SỬA 23/9 (khách: "người ta muốn xem lại kết quả thì xem ở đâu") — trang công khai
          trước đây KHÔNG có lối nào dẫn tới lịch sử làm bài, nộp xong đóng tab là coi như mất
          dấu. Người đã đăng nhập giờ có nút đi thẳng sang Lịch sử làm bài (danh sách mọi lượt
          đã nộp kèm điểm và nút xem lại). Khách chưa đăng nhập thì không hiện, vì lịch sử là
          dữ liệu riêng của từng người.
        --}}
        @if ($showMyHistoryButton)
        @auth
            <div class="mb-3 flex justify-end">
                <a href="{{ route('student.practice.index', ['tab' => 'history']) }}"
                   class="inline-flex min-h-9 items-center gap-1.5 rounded-xl border border-[#D6E3EF] bg-white px-3 py-2 text-[12px] font-bold text-[#126F91] transition hover:bg-[#EAF5F8]">
                    <x-lucide name="history" class="h-3.5 w-3.5" />Lịch sử làm bài của tôi
                </a>
            </div>
        @endauth
        @endif

        {{-- SỬA 8/10 — thanh sắp xếp cho mobile (lg:hidden), như "Sắp xếp danh sách" của PracticePage.jsx. --}}
        <div class="oi-sort-bar"><span>Sắp xếp danh sách</span><div><button type="button" class="oi-sort-btn oi-sort-btn--compact" :class="{ 'is-active': problemSort.key === 'topic' }" :aria-pressed="problemSort.key === 'topic' ? 'true' : 'false'" title="Sắp xếp theo Chuyên đề" @click="toggleProblemSort('topic')"><span>Chuyên đề</span><x-lucide name="arrow-up-down" x-show="problemSort.key !== 'topic'" /><x-lucide name="chevron-up" x-show="problemSort.key === 'topic' && problemSort.direction === 'asc'" x-cloak /><x-lucide name="chevron-down" x-show="problemSort.key === 'topic' && problemSort.direction === 'desc'" x-cloak /></button><button type="button" class="oi-sort-btn oi-sort-btn--compact" :class="{ 'is-active': problemSort.key === 'year' }" :aria-pressed="problemSort.key === 'year' ? 'true' : 'false'" title="Sắp xếp theo Năm" @click="toggleProblemSort('year')"><span>Năm</span><x-lucide name="arrow-up-down" x-show="problemSort.key !== 'year'" /><x-lucide name="chevron-up" x-show="problemSort.key === 'year' && problemSort.direction === 'asc'" x-cloak /><x-lucide name="chevron-down" x-show="problemSort.key === 'year' && problemSort.direction === 'desc'" x-cloak /></button><button type="button" class="oi-sort-btn oi-sort-btn--compact" :class="{ 'is-active': problemSort.key === 'difficulty' }" :aria-pressed="problemSort.key === 'difficulty' ? 'true' : 'false'" title="Sắp xếp theo Độ khó" @click="toggleProblemSort('difficulty')"><span>Độ khó</span><x-lucide name="arrow-up-down" x-show="problemSort.key !== 'difficulty'" /><x-lucide name="chevron-up" x-show="problemSort.key === 'difficulty' && problemSort.direction === 'asc'" x-cloak /><x-lucide name="chevron-down" x-show="problemSort.key === 'difficulty' && problemSort.direction === 'desc'" x-cloak /></button><button type="button" class="oi-sort-btn oi-sort-btn--compact" :class="{ 'is-active': problemSort.key === 'acRate' }" :aria-pressed="problemSort.key === 'acRate' ? 'true' : 'false'" title="Sắp xếp theo Tỷ lệ AC" @click="toggleProblemSort('acRate')"><span>Tỷ lệ AC</span><x-lucide name="arrow-up-down" x-show="problemSort.key !== 'acRate'" /><x-lucide name="chevron-up" x-show="problemSort.key === 'acRate' && problemSort.direction === 'asc'" x-cloak /><x-lucide name="chevron-down" x-show="problemSort.key === 'acRate' && problemSort.direction === 'desc'" x-cloak /></button></div></div>
        <div class="divide-y divide-[#E7EFF3] overflow-hidden rounded-2xl border border-[#DDEAF0] bg-white shadow-[0_2px_10px_rgba(28,91,121,0.05)]">
            <div :class="problemScope === 'assigned' ? 'oi-prob-grid--asg' : ''" class="hidden bg-[#F4F8FB] px-4 py-2.5 text-[11px] font-bold uppercase tracking-[.06em] text-[#365B7A] lg:grid oi-prob-grid {{ $showProblemProvinceAuthor ? '' : 'oi-prob-grid--lite' }}">
                <span>Tên bài tập &amp; Mã</span>
                <button type="button" class="oi-sort-btn" :class="{ 'is-active': problemSort.key === 'topic' }" :aria-pressed="problemSort.key === 'topic' ? 'true' : 'false'" title="Sắp xếp theo Chuyên đề" @click="toggleProblemSort('topic')"><span>Chuyên đề</span><x-lucide name="arrow-up-down" x-show="problemSort.key !== 'topic'" /><x-lucide name="chevron-up" x-show="problemSort.key === 'topic' && problemSort.direction === 'asc'" x-cloak /><x-lucide name="chevron-down" x-show="problemSort.key === 'topic' && problemSort.direction === 'desc'" x-cloak /></button>
                @if ($showProblemProvinceAuthor)<span>Tỉnh thành</span>@endif
                <button type="button" class="oi-sort-btn" :class="{ 'is-active': problemSort.key === 'year' }" :aria-pressed="problemSort.key === 'year' ? 'true' : 'false'" title="Sắp xếp theo Năm" @click="toggleProblemSort('year')"><span>Năm</span><x-lucide name="arrow-up-down" x-show="problemSort.key !== 'year'" /><x-lucide name="chevron-up" x-show="problemSort.key === 'year' && problemSort.direction === 'asc'" x-cloak /><x-lucide name="chevron-down" x-show="problemSort.key === 'year' && problemSort.direction === 'desc'" x-cloak /></button>
                @if ($showProblemProvinceAuthor)<span class="oi-prob-author">Tác giả</span>@endif
                <button type="button" class="oi-sort-btn" :class="{ 'is-active': problemSort.key === 'difficulty' }" :aria-pressed="problemSort.key === 'difficulty' ? 'true' : 'false'" title="Sắp xếp theo Độ khó" @click="toggleProblemSort('difficulty')"><span>Độ khó</span><x-lucide name="arrow-up-down" x-show="problemSort.key !== 'difficulty'" /><x-lucide name="chevron-up" x-show="problemSort.key === 'difficulty' && problemSort.direction === 'asc'" x-cloak /><x-lucide name="chevron-down" x-show="problemSort.key === 'difficulty' && problemSort.direction === 'desc'" x-cloak /></button>
                <span x-show="problemScope === 'assigned'" x-cloak>Kết quả / Chú ý</span>
                <span x-show="problemScope !== 'assigned'"><button type="button" class="oi-sort-btn" :class="{ 'is-active': problemSort.key === 'acRate' }" :aria-pressed="problemSort.key === 'acRate' ? 'true' : 'false'" title="Sắp xếp theo Tỷ lệ AC" @click="toggleProblemSort('acRate')"><span>Tỷ lệ AC</span><x-lucide name="arrow-up-down" x-show="problemSort.key !== 'acRate'" /><x-lucide name="chevron-up" x-show="problemSort.key === 'acRate' && problemSort.direction === 'asc'" x-cloak /><x-lucide name="chevron-down" x-show="problemSort.key === 'acRate' && problemSort.direction === 'desc'" x-cloak /></button></span>
                <span class="text-right">Hành động</span>
            </div>

            @foreach ($problems as $prob)
                @php
                    // SỬA 18/9 — trước đây mọi dòng đều trỏ về màn "chọn chuyên đề" chung, bấm
                    // bài nào cũng ra cùng một trang. Giờ kèm ?question=<id> để mở ĐÚNG bài vừa
                    // bấm (xem Student\PracticeByQuestionController::setup()).
                    $openHref = $canTakeDirectly
                        ? route('student.practiceByQuestion.setup', ['question' => $prob['id']])
                        : route('login');
                    /*
                     * SỬA 1/10 (khách: "cột hành động để mặc định là Làm bài luôn, đừng Luyện
                     * lại hay gì hết") — nút này trước đây đổi chữ theo trạng thái
                     * ("Luyện lại" khi đã AC, "Tiếp tục" khi đang làm dở) và đổi cả màu.
                     * Giờ MỌI DÒNG đều là một nút "Làm bài" duy nhất, cùng một màu.
                     *
                     * Không mất thông tin gì: trạng thái đã nằm ngay ở cột Tên bài — dấu tích
                     * xanh "Đã AC" và viên "Đã làm N lần" / "Chưa làm". Mà đằng nào cả ba nhãn
                     * cũ cũng dẫn về đúng một chỗ, nên ba chữ khác nhau cho cùng một hành động
                     * chỉ làm người ta phải nghĩ thêm.
                     */
                    $ctaLabel = 'Làm bài';
                    /*
                     * SỬA 3/10 (khách: "nút làm bài trong luyện tập public để màu xanh lá cây
                     * như nút luyện lại hôm bữa") — đổi từ xanh mòng két #126F91 về đúng cặp
                     * xanh lá #2F8A6B / #28795E mà nút "Luyện lại" dùng trước ngày 1/10.
                     * Hai lớp này đã có sẵn trong CSS đã build (các màn làm bài đang dùng)
                     * nên không cần build lại Vite.
                     */
                    $ctaClass = 'bg-[#2F8A6B] hover:bg-[#28795E]';
                    // SỬA 7/10 — lượt giao dành cho người đang xem (null nếu bài không được giao).
                    $asg = $prob['assignment'] ?? null;
                @endphp
                <div x-show="visibleProblemIds.includes({{ $prob['id'] }})" x-cloak
                     :style="{ order: visibleProblemIds.indexOf({{ $prob['id'] }}) }"
                     {{-- Sọc chẵn/lẻ tính theo VỊ TRÍ SAU KHI LỌC, không dùng even:/odd: của CSS —
                          danh sách được sắp lại bằng thuộc tính order nên nth-child sẽ sọc sai. --}}
                     :class="[visibleProblemIds.indexOf({{ $prob['id'] }}) % 2 === 0 ? 'bg-[#FCFEFF]' : 'bg-[#F7FBFC]', problemScope === 'assigned' ? 'oi-prob-grid--asg' : '']"
                     class="grid grid-cols-1 gap-x-2.5 gap-y-2 border-l-2 border-transparent p-2.5 transition-all hover:border-l-[#2D7FA3] hover:bg-[#F8FBFC] sm:px-4 oi-prob-grid {{ $showProblemProvinceAuthor ? '' : 'oi-prob-grid--lite' }} lg:items-center lg:gap-2.5">

                    {{-- Tên bài --}}
                    <div class="min-w-0">
                        <div class="flex min-w-0 items-center gap-2">
                            @if ($prob['status'] === 'ac')
                                <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-[#237052] text-white shadow-[0_3px_8px_rgba(35,112,82,0.2)]" title="Đã AC" aria-label="Đã AC">
                                    <x-lucide name="check-circle-2" class="h-4 w-4" />
                                </span>
                            @endif
                            <a href="{{ $openHref }}" title="{{ $prob['title'] }}"
                               class="min-w-0 flex-1 line-clamp-2 text-left text-[14px] font-semibold leading-5 text-[#123B68] transition-colors hover:text-[#126F91] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#CBEAF1] sm:text-[15px]">{{ $prob['title'] }}</a>
                            <span x-show="problemScope !== 'assigned'" class="mt-0.5 shrink-0 whitespace-nowrap rounded-full px-2 py-1 text-[10px] font-bold {{ $prob['userSubmissions'] > 0 ? 'bg-[#EFF9F5] text-[#2F8A6B]' : 'bg-[#F2F6F8] text-[#607A90]' }}">
                                {{ $prob['userSubmissions'] > 0 ? 'Đã làm '.$prob['userSubmissions'].' lần' : 'Chưa làm' }}
                            </span>
                            @if ($asg)
                                {{-- SỬA 7/10 — trạng thái của lượt giao (thay cho "Đã làm N lần" ở tab Bài được giao). --}}
                                <span x-show="problemScope === 'assigned'" x-cloak class="oi-asg-badge is-{{ $asg['status'] }}">
                                    @if ($asg['status'] === 'completed')<x-lucide name="check-circle-2" class="h-3 w-3" />@endif
                                    {{ $assignStatusLabels[$asg['status']] ?? 'Chưa hoàn thành' }}
                                </span>
                            @endif
                        </div>

                        {{-- SỬA 7/10 — Tỉnh/thành + Khu vực ngay dưới tên bài, như ContentLocation.jsx (mt-1.5). --}}
                        <div class="mt-1.5">
                            @include('partials.practice-content-location', ['province' => $prob['locProvince'] ?? null, 'region' => $prob['regionLabel'] ?? null])
                        </div>

                        {{-- SỬA 7/10 (khách: giao diện giáo viên chưa giống source mới) — theo PracticePage.jsx
                             dòng "QuickAssignButton · Nhật ký nộp bài · Nguồn · thông tin lượt giao" nằm CHUNG
                             MỘT HÀNG dưới tên bài, không đặt cạnh tên bài. --}}
                        <p class="mt-1 flex flex-wrap items-center gap-2 text-[11px] font-medium text-[#2F7F67]">
                            @if ($assignScope['canAssign'])
                                <button type="button" class="oi-assign-chip" aria-haspopup="dialog" aria-label="Giao bài: {{ $prob['title'] }}"
                                        @click.stop="openAssign('problem', {{ $prob['id'] }}, @js($prob['title']), @js($prob['code']))">
                                    <x-lucide name="send" />Giao bài
                                </button>
                            @endif
                            @auth
                                <a href="{{ route('practice.history.problem', $prob['id']) }}" title="Nhật ký nộp bài"
                                   class="oi-log-link inline-flex min-h-7 shrink-0 items-center gap-1 rounded-lg border border-[#D6E3EF] bg-[#EEF4FA] px-2 py-1 text-[10px] font-bold text-[#365B7A] shadow-[0_2px_6px_rgba(18,59,104,0.08)]">
                                    <x-lucide name="clipboard-list" class="h-3 w-3" />Nhật ký nộp bài
                                </a>
                            @endauth
                            <span class="flex min-w-0 items-center gap-1 truncate">
                                <x-lucide name="sparkles" class="h-3 w-3 shrink-0 text-[#3B9374]" />
                                <span class="truncate">Nguồn: Kho {{ $prob['subjectLabel'] ?: 'Kho luyện tập Ôn Thi 360' }}</span>
                            </span>
                            @if ($asg)
                                <span x-show="problemScope === 'assigned'" x-cloak class="oi-asg-meta" style="margin-top:0" title="Giao bởi {{ $asg['teacher'] }}">
                                    <span><x-lucide name="users" /><span>{{ $asg['teacher'] }}</span></span>
                                    <span class="{{ $asg['overdue'] ? 'is-overdue' : '' }}"><x-lucide name="calendar-days" />Hạn {{ $asg['deadline'] }}{{ $asg['overdue'] ? ' · Quá hạn' : '' }}</span>
                                </span>
                            @endif
                        </p>

                        {{-- SỬA 8/10 (khách: "dạng bài cho sát bên mã") — nhãn dạng bài đứng ngay sau "Mã: …" như source mới. --}}
                        <div class="oi-code-line">
                            <span class="oi-mono">Mã: {{ $prob['code'] }}</span>
                            <span class="oi-type-chip" aria-label="Dạng bài: {{ $prob['typeLabel'] }}">{{ $prob['typeLabel'] }}</span>
                            <span class="oi-mono" style="display:inline-flex;align-items:center;gap:.5rem"><span aria-hidden="true">•</span><span>{{ $prob['timeLimit'] }} / {{ $prob['memoryLimit'] }}</span></span>
                        </div>
                    </div>

                    {{-- Chuyên đề / độ khó / tỷ lệ AC — trên mobile là 3 ô có nhãn, lên lg thì
                         hoà vào đúng 3 cột của bảng nhờ lg:contents. --}}
                    <div class="col-span-1 grid grid-cols-2 gap-2 lg:contents">
                        <div class="min-w-0 rounded-lg border border-[#E7EFF3] bg-[#F8FBFC] px-2.5 py-2 lg:rounded-none lg:border-0 lg:bg-transparent lg:p-0">
                            <span class="mb-0.5 block text-[10px] font-bold uppercase tracking-wide text-[#6B8295] lg:hidden">Chuyên đề</span>
                            <span class="block truncate rounded-lg border border-[#D6E3EF] bg-[#EEF4FA] px-2 py-0.5 text-[12px] font-semibold text-[#365B7A] lg:inline-block" title="{{ $prob['topicLabel'] }}">{{ $prob['topicLabel'] }}</span>
                        </div>

                        {{-- SỬA 1/10 — 2 ô mới. Trên mobile là ô có nhãn như các ô khác, lên lg
                             thì hoà vào đúng 2 cột của bảng nhờ lg:contents ở thẻ cha. Câu chưa
                             gán hiện "—" (Question::provinceLabel()/examYearLabel()). --}}
                        @if ($showProblemProvinceAuthor)
                        <div class="min-w-0 rounded-lg border border-[#E7EFF3] bg-[#F8FBFC] px-2.5 py-2 lg:rounded-none lg:border-0 lg:bg-transparent lg:p-0">
                            <span class="mb-0.5 block text-[10px] font-bold uppercase tracking-wide text-[#6B8295] lg:hidden">Tỉnh thành</span>
                            <span class="block truncate text-[12px] font-semibold text-[#365B7A]" title="{{ $prob['provinceLabel'] ?? '—' }}">{{ $prob['provinceLabel'] ?? '—' }}</span>
                        </div>
                        @endif

                        <div class="min-w-0 rounded-lg border border-[#E7EFF3] bg-[#F8FBFC] px-2.5 py-2 lg:rounded-none lg:border-0 lg:bg-transparent lg:p-0">
                            <span class="mb-0.5 block text-[10px] font-bold uppercase tracking-wide text-[#6B8295] lg:hidden">Năm</span>
                            <span class="block text-[12px] font-semibold text-[#365B7A]">{{ $prob['examYearLabel'] ?? '—' }}</span>
                        </div>

                        @if ($showProblemProvinceAuthor)
                        {{-- SỬA 4/10 — cột Tác giả (người bấm nút tạo câu hỏi, cột questions.created_by).
                             truncate + title: tên tài khoản có thể dài, cắt gọn nhưng rê chuột vẫn đọc
                             được đủ. Chưa gán người soạn (dữ liệu cũ) thì hiện "—", không bịa tên. --}}
                        <div class="oi-prob-author min-w-0 rounded-lg border border-[#E7EFF3] bg-[#F8FBFC] px-2.5 py-2 lg:rounded-none lg:border-0 lg:bg-transparent lg:p-0">
                            <span class="mb-0.5 block text-[10px] font-bold uppercase tracking-wide text-[#6B8295] lg:hidden">Tác giả</span>
                            <span class="block truncate text-[12px] font-semibold text-[#365B7A]" title="{{ $prob['authorName'] ?: '—' }}">{{ $prob['authorName'] ?: '—' }}</span>
                        </div>
                        @endif

                        <div class="min-w-0 rounded-lg border border-[#E7EFF3] bg-[#F8FBFC] px-2.5 py-2 lg:rounded-none lg:border-0 lg:bg-transparent lg:p-0">
                            <span class="mb-0.5 block text-[10px] font-bold uppercase tracking-wide text-[#6B8295] lg:hidden">Độ khó</span>
                            <div class="flex flex-col items-start gap-0.5">
                                <div class="inline-flex items-center gap-0.5" role="img" aria-label="Độ khó {{ $prob['difficultyLevel'] }} trên 5 sao">
                                    @for ($star = 0; $star < 5; $star++)
                                        <x-lucide name="star" class="h-3.5 w-3.5 {{ $star < $prob['difficultyLevel'] ? 'fill-amber-400 text-amber-500' : 'text-slate-200' }}" />
                                    @endfor
                                </div>
                                <span class="text-[11px] font-semibold text-[#607A90]">{{ $prob['difficultyLabel'] ?? 'Khá' }}</span>
                            </div>
                        </div>

                        <div class="col-span-2 min-w-0 rounded-lg border border-[#E7EFF3] bg-[#F8FBFC] px-2.5 py-2 lg:col-span-1 lg:rounded-none lg:border-0 lg:bg-transparent lg:p-0">
                            <span class="mb-0.5 block text-[10px] font-bold uppercase tracking-wide text-[#6B8295] lg:hidden" x-text="problemScope === 'assigned' ? 'Kết quả / Chú ý' : 'Tỷ lệ AC'">Tỷ lệ AC</span>
                            <div x-show="problemScope !== 'assigned'">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-black text-[#123B68]">{{ $prob['acRate'] }}%</span>
                                <span class="text-[9px] font-bold uppercase tracking-wide text-[#6B8295]">AC</span>
                            </div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-[#EAF0F3]" aria-label="Tỷ lệ AC {{ $prob['acRate'] }}%">
                                <div class="h-full rounded-full bg-[#2F8A6B] transition-all" style="width: {{ $prob['acRate'] }}%"></div>
                            </div>
                            <p class="mt-1 text-[9px] text-[#6B8295]">{{ number_format($prob['acceptedCount']) }}/{{ number_format($prob['submissionCount']) }} lượt toàn hệ thống</p>
                            {{--
                                SỬA 19/9 (9) (khách: "vẫn 0%") — KẾT QUẢ TỐT NHẤT CỦA CHÍNH BẠN.

                                "Tỷ lệ AC" ở trên là chỉ số của CẢ HỆ THỐNG: số lượt được chấp nhận
                                chia tổng số lượt. Bài chưa ai giải trọn vẹn thì nó đứng yên 0% —
                                đúng về số học nhưng chẳng nói được gì với người đang làm. Dòng này
                                mới là thứ họ cần: lần nộp tốt nhất của chính họ qua bao nhiêu test.

                                Ẩn khi chưa có số liệu (chưa nộp / không phải câu lập trình / máy chủ
                                chưa chạy migration) thay vì hiện "0/0 test".
                            --}}
                            @if (($prob['mineTestPercent'] ?? null) !== null)
                                <p class="mt-1 flex items-center gap-1 text-[9px] font-bold {{ $prob['mineTestPercent'] === 100 ? 'text-[#2F8A6B]' : 'text-[#2C6BB0]' }}">
                                    <x-lucide name="user-check" class="h-3 w-3 shrink-0" />
                                    Bạn: {{ $prob['minePassedTests'] }}/{{ $prob['mineTotalTests'] }} test · {{ $prob['mineTestPercent'] }}%
                                </p>
                            @endif
                            </div>{{-- /problemScope !== 'assigned' --}}
                            @if ($asg)
                                @php
                                    $asgRatio = ($asg['status'] === 'completed' && $asg['score'] !== null && ($asg['maxScore'] ?? 0) > 0) ? $asg['score'] / $asg['maxScore'] : null;
                                    $asgTone = $asg['status'] !== 'completed' ? 'is-none' : ($asgRatio === null ? 'is-none' : ($asgRatio >= 0.999 ? 'is-ac' : ($asgRatio > 0 ? 'is-partial' : 'is-wa')));
                                @endphp
                                {{-- SỬA 7/10 — "Kết quả / Chú ý" của bản mẫu (AssignmentResult). Phần "dấu hiệu
                                     rời tab/chụp màn hình" chưa có: hệ thống chỉ ghi các sự kiện đó trên trình
                                     duyệt người làm bài, chưa gửi về máy chủ. --}}
                                <div x-show="problemScope === 'assigned'" x-cloak class="oi-asg-result">
                                    <strong class="{{ $asgTone }}">
                                        @if ($asg['status'] === 'completed' && $asg['score'] !== null)
                                            {{ $fmtScore($asg['score']) }}<small>/{{ $fmtScore($asg['maxScore']) }} điểm</small>
                                        @elseif ($asg['status'] === 'pending')
                                            <span class="is-none">Chờ chấm</span>
                                        @else
                                            <span class="is-none">Chưa có điểm</span>
                                        @endif
                                    </strong>
                                    @if ($asg['status'] === 'completed' && $asg['resultLabel'])
                                        <span class="{{ $asgTone }}" style="font-size:10px;font-weight:600">{{ $asg['resultLabel'] }}</span>
                                    @endif
                                    <p>{{ $asg['submittedAt'] ? 'Lần nộp gần nhất · '.$asg['submittedAt'] : 'Chưa có bài nộp để đối chiếu' }}</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Hành động --}}
                    <div class="col-span-1 flex items-center justify-end gap-2 border-t border-[#E7EFF3] pt-2 lg:col-auto lg:flex-col lg:items-stretch lg:gap-1 lg:border-t-0 lg:pt-0">
                        <a href="{{ $openHref }}"
                           class="flex min-h-9 min-w-0 flex-1 items-center justify-center gap-1 rounded-lg px-2 py-1.5 text-[12px] font-bold text-white shadow-none transition-colors active:scale-[.98] lg:w-full {{ $ctaClass }}">
                            <x-lucide name="code" class="h-3.5 w-3.5" />
                            <span>{{ $ctaLabel }}</span>
                        </a>
                    </div>
                </div>
            @endforeach

            {{-- Phân trang nằm NGAY TRONG khung danh sách, trên dải nền nhạt — đúng bản mẫu mới. --}}
            <div x-show="problemTotalPages > 1" x-cloak class="border-t border-[#E7EFF3] bg-[#F4F8FB] px-3 sm:px-4">
                <nav aria-label="Phân trang bài tập chuyên đề"
                     class="flex flex-col items-center justify-between gap-2 py-2 sm:flex-row">
                    <span class="text-[11px] text-[#607A90]">Trang <b class="text-[#45657D]" x-text="problemPage"></b> / <span x-text="problemTotalPages"></span></span>
                    <div class="flex items-center gap-1.5">
                        <button type="button" aria-label="Trang trước" :disabled="problemPage === 1" @click="problemPageIndex = Math.max(1, problemPage - 1)"
                                class="grid h-9 w-9 place-items-center rounded-lg border border-[#DDEAF0] bg-white text-[#45657D] transition hover:border-[#9DC8D7] hover:bg-[#EAF5F8] disabled:cursor-not-allowed disabled:opacity-40">
                            <x-lucide name="chevron-left" class="h-4 w-4" />
                        </button>
                        <template x-for="n in problemTotalPages" :key="'pp' + n">
                            <button type="button" :aria-label="'Trang ' + n" :aria-current="problemPage === n ? 'page' : null" @click="problemPageIndex = n"
                                    class="grid h-9 min-w-9 place-items-center rounded-lg px-2 text-[11px] font-extrabold transition"
                                    :class="problemPage === n ? 'bg-[#126F91] text-white shadow-[0_3px_8px_rgba(18,111,145,0.16)]' : 'text-[#45657D] hover:bg-white'"
                                    x-text="n"></button>
                        </template>
                        <button type="button" aria-label="Trang sau" :disabled="problemPage === problemTotalPages" @click="problemPageIndex = Math.min(problemTotalPages, problemPage + 1)"
                                class="grid h-9 w-9 place-items-center rounded-lg border border-[#DDEAF0] bg-white text-[#45657D] transition hover:border-[#9DC8D7] hover:bg-[#EAF5F8] disabled:cursor-not-allowed disabled:opacity-40">
                            <x-lucide name="chevron-right" class="h-4 w-4" />
                        </button>
                    </div>
                </nav>
            </div>
        </div>

        <div x-show="filteredProblems.length === 0" x-cloak class="rounded-3xl border border-dashed border-[#C9DFE8] bg-white p-10 text-center">
            <x-lucide name="search" class="mx-auto h-9 w-9 text-[#9DC8D7]" />
            <h2 class="mt-3 text-sm font-black text-[#123B68]">Không có bài tập phù hợp</h2>
            <p class="mt-1 text-xs text-[#607A90]" x-text="problemScope === 'assigned' ? 'Chưa có bài được giao phù hợp. Thử đổi trạng thái, chuyên đề, độ khó hoặc từ khóa tìm kiếm.' : 'Hãy đổi chuyên đề hoặc mức độ để xem kho bài khác.'">Hãy đổi chuyên đề hoặc mức độ để xem kho bài khác.</p>
            <button type="button" @click="resetProblemFilters()" class="mt-4 text-[11px] font-bold text-[#126F91] hover:underline">Xóa bộ lọc</button>
        </div>
        </div>{{-- /problemScope !== 'managed' --}}

        @if ($assignScope['canManage'])
            <div x-show="problemScope === 'managed'" x-cloak>
                @include('partials.practice-assign-manager', ['type' => 'problem', 'managed' => $managedAssignments['problem'] ?? [], 'assignRole' => $assignRole])
            </div>
        @endif
    </div>

    {{-- ══════════════ CHẾ ĐỘ: ĐỀ THI LUYỆN TẬP ══════════════ --}}
    <div x-show="practiceMode === 'exams'" @if ($catalogMode !== 'exams') x-cloak @endif class="flex flex-col gap-4">

        {{-- SỬA 7/10 — thanh phạm vi theo bản mẫu mới: Tất cả đề / Đề được giao / Đề đã giao. --}}
        <div class="rounded-2xl border border-[#DDEAF0] bg-white p-3 shadow-[0_2px_10px_rgba(28,91,121,0.04)]" style="display:flex;flex-direction:column;gap:12px">
            @include('partials.practice-scope-tabs', ['kind' => 'exam'])
            @if ($assignScope['canViewAssigned'])
                @include('partials.practice-assign-status-chips', ['kind' => 'exam'])
            @endif
        </div>

        <div x-show="examScope !== 'managed'" class="flex flex-col gap-4">

        {{-- [PRACTICE-05] BỘ LỌC ĐỀ THI — SỬA 7/10: theo bản mẫu mới là ô tìm kiếm + 3 ô chọn
             (tỉnh/thành, cuộc thi, sắp xếp). Ô "Giá làm đề" của bản mẫu CHƯA dựng: hệ thống chưa
             có giá cho đề luyện tập (bản mẫu tự ghi giá chỉ để minh hoạ).

             Danh sách tỉnh/thành và cuộc thi dựng từ dữ liệu CÓ THẬT của các đề trong kho — bày
             một lựa chọn mà không đề nào thuộc về nó thì bấm vào ra bảng rỗng. --}}
        @php
            $examProvinceOptions = collect($items)->where('inCatalog', true)->pluck('provinceLabel')->filter()->unique()->sort()->values()->all();
            $examHasNoProvince = collect($items)->where('inCatalog', true)->contains(fn ($it) => blank($it['provinceLabel'] ?? null));
        @endphp
        <section aria-label="Bộ lọc đề thi" class="oi-exam-filters" x-show="examScope === 'all'">
            <label class="block min-w-0">
                <span class="oi-field-label">Tìm kiếm đề thi</span>
                <span class="relative block">
                    <x-lucide name="search" class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#607A90]" />
                    <input type="text" aria-label="Tìm kiếm đề thi" placeholder="Tên đề hoặc mã đề..." x-model="searchQuery"
                           class="min-h-10 w-full min-w-0 rounded-xl border border-[#DDEAF0] bg-[#F8FAFB] py-2 pl-9 pr-3 text-xs text-slate-800 placeholder:text-[#6B8295] focus:border-[#9DC8D7] focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#EAF5F8]">
                </span>
            </label>
            <div class="oi-exam-filter-selects">
                <label class="block min-w-0">
                    <span class="oi-field-label">Tỉnh/thành</span>
                    <select aria-label="Tỉnh/thành" x-model="selectedExamProvince" @change="examPageIndex = 1" class="oi-select">
                        <option value="all">Tất cả tỉnh/thành</option>
                        @foreach ($examProvinceOptions as $prov)
                            <option value="{{ $prov }}">{{ $prov }}</option>
                        @endforeach
                        @if ($examHasNoProvince)
                            <option value="unknown">Chưa cập nhật</option>
                        @endif
                    </select>
                </label>
                <label class="block min-w-0">
                    <span class="oi-field-label">Cuộc thi</span>
                    <select aria-label="Cuộc thi" x-model="selectedExamType" @change="examPageIndex = 1" class="oi-select">
                        <option value="all">Tất cả cuộc thi</option>
                        @foreach ($examCategoryChips ?? [] as $chip)
                            <option value="{{ $chip['value'] }}">{{ $chip['label'] }} ({{ $chip['count'] }})</option>
                        @endforeach
                    </select>
                </label>
                <label class="block min-w-0">
                    <span class="oi-field-label">Sắp xếp</span>
                    <select aria-label="Sắp xếp đề thi" x-model="examSort" class="oi-select">
                        <option value="default">Mới nhất trước</option>
                        <option value="attempts">Nhiều lượt làm nhất</option>
                        <option value="title">Tên đề A → Z</option>
                    </select>
                </label>
            </div>
        </section>

        {{-- SỬA 7/10 — dòng "N / M đề phù hợp" + "Xóa bộ lọc" đúng bản mẫu (PracticePage.jsx), nằm ngay dưới ô lọc. --}}
        <div x-show="examScope === 'all'" class="flex flex-wrap items-center justify-between gap-2 px-1">
            <p role="status" aria-live="polite" class="flex items-center gap-2 text-[11px] text-[#607A90]">
                <x-lucide name="file-text" class="h-3.5 w-3.5 text-[#2D7FA3]" />
                <span><b class="text-[#45657D]" x-text="filteredExams.length"></b> / {{ collect($items)->where('inCatalog', true)->count() }} đề phù hợp</span>
            </p>
            <button type="button" x-show="searchQuery || selectedExamProvince !== 'all' || selectedExamType !== 'all'" x-cloak @click="resetExamFilters()"
                    class="min-h-9 rounded-lg px-2.5 text-[11px] font-semibold text-[#126F91] hover:bg-[#EAF5F8]">Xóa bộ lọc</button>
        </div>

        {{-- Tab "Đề được giao" chỉ cần ô tìm kiếm (trạng thái đã có dãy chip ở trên). --}}
        <div x-show="examScope === 'assigned'" x-cloak class="relative min-w-0">
            <x-lucide name="search" class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#607A90]" />
            <input type="text" aria-label="Tìm kiếm đề được giao" placeholder="Tìm đề được giao theo tên hoặc mã đề..." x-model="searchQuery"
                   class="min-h-10 w-full rounded-xl border border-[#DDEAF0] bg-white py-2 pl-10 pr-4 text-xs text-slate-800 placeholder:text-[#6B8295] focus:border-[#9DC8D7] focus:outline-none focus:ring-4 focus:ring-[#EAF5F8]">
        </div>

        {{-- ══════ TỔNG QUAN ĐỀ THI — ĐANG ẨN ══════
             SỬA 4/10 (khách khoanh vùng trên ảnh: "phần này còn thừa, không cần thiết cho lắm,
             bỏ đi hoặc thu gọn… hiện tại khách muốn ẩn đi").

             ẨN chứ KHÔNG XOÁ: đổi $showExamOverview thành true là hiện lại nguyên vẹn, không
             phải dựng lại. Khối này gồm 2 phần khách đã khoanh vào: dòng "Hiển thị N đề thi
             luyện tập" và 3 ô Kho đề thi / Đang luyện / Điểm cao nhất.

             Dữ liệu cho 3 ô ($examTotal, $examDoingCount, $examBestScoreLabel) vẫn do
             Public\PracticeService tính và truyền sang như cũ — không gỡ ở tầng dữ liệu, vì gỡ
             rồi mà mai khách đổi ý thì phải lần lại cả service. --}}
        @php
            $showExamOverview = false;
        @endphp

        @if ($showExamOverview)
            <div class="flex items-center justify-between gap-3 px-1">
                <div class="flex items-center gap-2 text-[11px] text-[#607A90]">
                    <x-lucide name="file-text" class="h-3.5 w-3.5 text-[#4C83B0]" />
                    <span>Hiển thị <b class="text-[#45657D]" x-text="filteredExams.length"></b> đề thi luyện tập</span>
                </div>
                <span class="hidden text-[11px] text-[#6B8295] sm:inline">Mỗi đề mô phỏng một lượt thi hoàn chỉnh</span>
            </div>

            {{-- [PRACTICE-06] TỔNG QUAN ĐỀ THI
                 SỬA 2/10 — 3 ô đúng bản mẫu mới: Kho đề thi / Đang luyện / Điểm cao nhất. Bản mẫu để
                 cứng "86/100" cho ô thứ ba và tự ghi chú là số minh hoạ; ở đây lấy điểm tốt nhất THẬT
                 của chính người đang xem, chưa làm đề nào thì hiện "—" chứ không bịa số. --}}
            @php
                $examTotal = $examTotal ?? count($items);
                $examDoingCount = $examDoingCount ?? 0;
                $examBestScoreLabel = $examBestScoreLabel ?? null;
            @endphp
            <div class="grid gap-2 sm:grid-cols-3">
                <div class="rounded-xl border border-[#DDEAF0] bg-white p-3 shadow-[0_2px_8px_rgba(28,91,121,0.04)]">
                    <div class="flex items-center gap-2">
                        <span class="grid h-8 w-8 place-items-center rounded-lg bg-[#EAF5F8] text-[#126F91]"><x-lucide name="file-text" class="h-4 w-4" /></span>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wide text-[#607A90]">Kho đề thi</p>
                            <p class="text-lg font-bold leading-5 text-[#123B68]">{{ $examTotal }}</p>
                        </div>
                    </div>
                    <p class="mt-2 text-[11px] text-[#45657D]">Đề luyện tập đã phát hành</p>
                </div>
                <div class="rounded-xl border border-[#DDEAF0] bg-white p-3 shadow-[0_2px_8px_rgba(28,91,121,0.04)]">
                    <div class="flex items-center gap-2">
                        <span class="grid h-8 w-8 place-items-center rounded-lg bg-[#FFF7E3] text-[#B68032]"><x-lucide name="clock" class="h-4 w-4" /></span>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wide text-[#607A90]">Đang luyện</p>
                            <p class="text-lg font-bold leading-5 text-[#123B68]">{{ $examDoingCount }}</p>
                        </div>
                    </div>
                    <p class="mt-2 text-[11px] text-[#45657D]">Tiếp tục từ nơi bạn đã dừng</p>
                </div>
                <div class="rounded-xl border border-[#DDEAF0] bg-white p-3 shadow-[0_2px_8px_rgba(28,91,121,0.04)]">
                    <div class="flex items-center gap-2">
                        <span class="grid h-8 w-8 place-items-center rounded-lg bg-[#EFF9F5] text-[#2F8A6B]"><x-lucide name="award" class="h-4 w-4" /></span>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wide text-[#607A90]">Điểm cao nhất</p>
                            <p class="text-lg font-bold leading-5 text-[#123B68]">{{ $examBestScoreLabel ?: '—' }}</p>
                        </div>
                    </div>
                    <p class="mt-2 text-[11px] text-[#45657D]">{{ $examBestScoreLabel ? 'Kết quả tốt nhất của bạn' : 'Chưa có kết quả nào của bạn' }}</p>
                </div>
            </div>
        @endif

        <div class="flex items-center justify-between gap-3 px-1 pt-1">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[.08em] text-[#607A90]">Danh sách đề thi</p>
                <p class="mt-0.5 text-sm font-bold text-[#123B68]">Chọn một phiên thi để bắt đầu</p>
            </div>
            <span class="hidden text-[11px] text-[#6B8295] sm:inline"><span x-text="filteredExams.length"></span> đề phù hợp</span>
        </div>

        {{-- [PRACTICE-07] CARD ĐỀ THI
             SỬA 2/10 — dựng lại theo bản mẫu mới (education-main/src/components/PracticePage.jsx).
             Khác bản cũ 4 chỗ:
               · ảnh bìa là ẢNH THẬT của đề (assessments.cover_image_path). Bản cũ lấy ảnh sách
                 theo số thứ tự thẻ — ảnh không liên quan gì tới đề, nhìn như đề có ảnh riêng mà
                 thật ra không phải. Đề chưa có ảnh thì vẽ khối trống, KHÔNG mượn ảnh của thứ khác;
               · dòng mô tả lấy assessments.subtitle thật, thay cho chuỗi ghép "N câu · X điểm";
               · ô thứ ba trong lưới 3 ô là "Lượt làm" (số thật, đếm lượt đã nộp) đúng bản mẫu;
               · nút chính là "Xem chi tiết đề" dẫn sang MÀN CHI TIẾT, chỗ đó mới có nút
                 "Bắt đầu làm bài" mở modal làm đề.

             KHÔNG dựng khối điểm sao của bản mẫu: hệ thống chưa có đánh giá cho đề (bảng reviews
             không nhận target 'assessment'), mà bản mẫu cũng tự ghi điểm sao là dữ liệu minh hoạ
             — vẽ 5 ngôi sao rỗng hoặc bịa điểm đều tệ hơn là không vẽ. --}}
        <div x-show="examScope !== 'assigned'" class="grid grid-cols-1 items-stretch gap-3 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($items as $exam)
                @php
                    $tone = $exam['progressStatus'] === 'done'
                        ? ['border' => 'border-[#CFE5D9]', 'badge' => 'border-[#D4EDE2] bg-[#EFF9F5] text-[#397C68]', 'text' => 'text-[#2F8A6B]', 'bar' => '#2F8A6B', 'label' => 'Đã hoàn thành']
                        : ($exam['progressStatus'] === 'doing'
                            ? ['border' => 'border-[#E7D6AB]', 'badge' => 'border-[#F2E1B6] bg-[#FFF7E3] text-[#8E6B2E]', 'text' => 'text-[#126F91]', 'bar' => '#B68032', 'label' => 'Đang làm dở']
                            : ['border' => 'border-[#BFDCE5]', 'badge' => 'border-[#D4EDE2] bg-[#EFF9F5] text-[#397C68]', 'text' => 'text-[#126F91]', 'bar' => '#126F91', 'label' => 'Đang mở']);
                @endphp
                <article x-show="visibleExamIds.includes({{ $exam['id'] }})" x-cloak
                         :style="{ order: visibleExamIds.indexOf({{ $exam['id'] }}) }"
                         class="group flex h-full min-h-[430px] flex-col overflow-hidden rounded-2xl border bg-white shadow-[0_2px_12px_rgba(28,91,121,0.06)] transition hover:-translate-y-0.5 hover:shadow-[0_8px_18px_rgba(28,91,121,0.09)] {{ $tone['border'] }}">
                    {{-- SỬA 2/10 lần 6 (khách: "ảnh bìa nó không full ra nhỉ, cho full ra cho đẹp")
                         — trước đây khung cao cố định 128px, có đệm 2px và ảnh để object-contain
                         nên ảnh ngang bị thu nhỏ nằm giữa, chừa hai mép trắng như ảnh khách gửi.

                         Giờ khung lấy TỈ LỆ 16:9 đúng bằng tỉ lệ đã khuyến nghị ở ô tải ảnh, bỏ
                         đệm, và đổi sang object-cover: ảnh phủ kín mép-tới-mép. Chọn 16:9 chứ
                         không giữ chiều cao cứng là có lý do — ảnh 16:9 đặt vào khung 16:9 thì
                         object-cover KHÔNG cắt gì cả, mà mấy ảnh bìa này chữ kín mặt, cắt hụt
                         một dòng là mất tên đề. --}}
                    {{-- SỬA 7/10 — nút "Giao đề" cho giáo viên/admin (bản mẫu: QuickAssignButton trên đầu thẻ). --}}
                    @if ($assignScope['canAssign'])
                        <div style="display:flex;justify-content:flex-end;background:#F8FBFC;padding:8px 12px 0">
                            <button type="button" class="oi-assign-chip" aria-haspopup="dialog" aria-label="Giao đề: {{ $exam['title'] }}"
                                    @click.stop="openAssign('exam', {{ $exam['id'] }}, @js($exam['title']), @js($exam['examCode'] ?: '#'.$exam['id']))">
                                <x-lucide name="send" />Giao đề
                            </button>
                        </div>
                    @endif
                    <div class="relative aspect-[16/9] w-full shrink-0 overflow-hidden bg-[#F8FBFC]">
                        @if ($exam['coverUrl'])
                            <img src="{{ $exam['coverUrl'] }}" alt="Ảnh bìa đề {{ $exam['title'] }}" loading="lazy" decoding="async"
                                 class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.03]">
                        @else
                            <div class="flex h-full w-full flex-col items-center justify-center gap-1 text-[#9DC8D7]">
                                <x-lucide name="file-text" class="h-8 w-8" />
                                <span class="text-[10px] font-bold">Chưa có ảnh bìa</span>
                            </div>
                        @endif
                        <span class="absolute left-3 top-3 rounded-lg border px-2 py-1 text-[11px] font-bold {{ $tone['badge'] }}">{{ $tone['label'] }}</span>
                        <span class="absolute bottom-2 left-3 rounded-md bg-white/90 px-2 py-1 font-mono text-[10px] font-bold text-[#45657D]">{{ $exam['examCode'] ?: '#'.$exam['id'] }}</span>
                    </div>

                    <div class="flex min-h-0 flex-1 flex-col p-3">
                        <div class="flex min-h-5 items-center justify-between gap-2">
                            <div class="flex min-w-0 items-center gap-1.5 text-[11px] font-bold text-[#2D7FA3]">
                                <x-lucide name="file-text" class="h-3.5 w-3.5 shrink-0" /><span class="truncate">{{ $exam['categoryLabel'] ?: 'Đề luyện tập' }}</span>
                            </div>
                            <span class="shrink-0 text-[10px] font-bold text-[#6B8295]">Thi mô phỏng</span>
                        </div>

                        <h3 class="mt-1 line-clamp-2 h-10 overflow-hidden text-sm font-bold leading-5 text-[#123B68]">{{ $exam['title'] }}</h3>
                        <p class="type-body mt-1 line-clamp-2 h-8 overflow-hidden text-[11px]">
                            {{ $exam['subtitle'] ?: $exam['itemsCount'].' câu · '.($exam['totalPoints'] ?: '—').' điểm' }}
                        </p>

                        {{-- SỬA 7/10 (khách: "thiếu Độ khó, số sao đánh giá, tỉnh thành khu vực") — theo
                             PracticePage.jsx: Độ khó (sao) → Đánh giá (sao + điểm + số lượt) → Tỉnh/thành và
                             Khu vực. Giá làm đề (ExamAccessInfo) tạm bỏ theo yêu cầu. --}}
                        <div class="oi-meta-row">
                            <span class="oi-meta-label">Độ khó</span>
                            @include('partials.practice-difficulty-stars', ['level' => $exam['difficultyLevel'], 'label' => $exam['difficultyLabel']])
                        </div>
                        <div style="margin-top:6px">
                            @include('partials.practice-exam-rating', ['rating' => $exam['rating'], 'count' => $exam['reviewCount']])
                        </div>
                        @include('partials.practice-content-location', ['province' => $exam['provinceLabel'], 'region' => $exam['regionLabel']])
                        @if ($exam['academicYear'])
                            <p class="mt-1.5 inline-flex items-center gap-1 text-[10px] text-[#6B8295]"><x-lucide name="calendar-days" class="h-3 w-3" />{{ $exam['academicYear'] }}</p>
                        @endif

                        <div class="mt-2.5 grid min-h-[72px] grid-cols-3 gap-1 rounded-xl border border-[#E7EFF3] bg-[#F8FBFC] p-1.5 text-center">
                            <div>
                                <x-lucide name="timer" class="mx-auto h-3.5 w-3.5 text-[#2D7FA3]" />
                                <p class="mt-0.5 text-[11px] font-bold text-[#45657D]">{{ $exam['durationMinutes'] ? $exam['durationMinutes'].' phút' : 'Không giới hạn' }}</p>
                                <p class="text-[9px] text-[#6B8295]">Thời lượng</p>
                            </div>
                            <div>
                                <x-lucide name="code-2" class="mx-auto h-3.5 w-3.5 text-[#427EA1]" />
                                <p class="mt-0.5 text-[11px] font-bold text-[#45657D]">{{ $exam['itemsCount'] }} bài</p>
                                <p class="text-[9px] text-[#6B8295]">Cấu trúc đề</p>
                            </div>
                            <div>
                                <x-lucide name="trending-up" class="mx-auto h-3.5 w-3.5 text-[#3B9374]" />
                                <p class="mt-0.5 text-[11px] font-bold text-[#45657D]">{{ number_format($exam['attemptCount']) }}</p>
                                <p class="text-[9px] text-[#6B8295]">Lượt làm</p>
                            </div>
                        </div>

                        <div class="mt-2.5 min-h-[34px]">
                            <div class="flex items-center justify-between gap-2 text-[10px]">
                                <span class="font-bold text-[#45657D]">Tiến độ của bạn</span>
                                <span class="font-bold {{ $tone['text'] }}">{{ $exam['progressLabel'] }}</span>
                            </div>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-[#EAF0F3]">
                                <div class="h-full rounded-full" style="width: {{ $exam['progress'] }}%; background-color: {{ $tone['bar'] }}"></div>
                            </div>
                        </div>

                        <div class="mt-auto border-t border-[#E7EFF3] pt-2.5">
                            {{-- SỬA 3/10 (khách: "nút xem chi tiết đề cũng đổi thành màu đó luôn") —
                                 cùng cặp xanh lá #2F8A6B / #28795E với nút "Làm bài" ở danh sách bài tập.
                                 Bóng đổ cũng phải đổi theo cho khỏi lệch tông: bóng cũ pha màu mòng két
                                 rgba(18,111,145). Dùng lớp bóng xanh lá rgba(35,112,82,0.2) VÌ LỚP NÀY
                                 ĐÃ CÓ SẴN trong CSS đã build (huy hiệu "Đã AC" đang dùng) — tự chế một
                                 cỡ bóng mới thì Tailwind chưa sinh ra lớp đó, nút sẽ mất bóng cho tới
                                 khi build lại. --}}
                            <div style="display:flex;align-items:center;gap:8px">
                            <a href="{{ $exam['detailHref'] }}"
                               class="flex min-h-10 min-w-0 flex-1 items-center justify-center gap-1.5 rounded-lg bg-[#2F8A6B] px-3 py-2 text-[11px] font-extrabold text-white shadow-[0_3px_8px_rgba(35,112,82,0.2)] transition hover:-translate-y-0.5 hover:bg-[#28795E] active:scale-[.98]">
                                Xem chi tiết đề<x-lucide name="chevron-right" class="h-3.5 w-3.5" />
                            </a>
                            {{-- SỬA 9/10 (khách: "thêm nút Nhật ký làm đề, UI lấy từ source mới") — nút CÓ CHỮ cạnh
                                 "Xem chi tiết đề", đúng PracticePage.jsx (historyPath("exam", id)). Thay nút vuông chỉ có
                                 icon trước đây. Hiện với mọi người; khách chưa đăng nhập bấm vào sẽ được chuyển sang trang
                                 đăng nhập (route nhật ký nằm trong nhóm auth) rồi quay lại đúng trang này. --}}
                            <a href="{{ route('practice.history.exam', $exam['id']) }}" aria-label="Nhật ký làm đề: {{ $exam['title'] }}" class="oi-log-btn">
                                <x-lucide name="clipboard-list" class="h-3.5 w-3.5" />Nhật ký làm đề
                            </a>
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        {{-- ══════ SỬA 7/10 — DANH SÁCH "ĐỀ ĐƯỢC GIAO" (AssignedExamList / AssignedWorkRow của bản mẫu) ══════ --}}
        <div x-show="examScope === 'assigned'" x-cloak class="oi-asg-list" style="display:flex;flex-direction:column">
            <div class="assigned-work-grid assigned-work-header" style="order:-1" aria-hidden="true">
                <div>Đề thi</div><div>Người giao / Lớp</div><div>Hạn nộp</div><div>Kết quả / Chú ý</div><span class="assigned-work-action-label">Thao tác</span>
            </div>
            @foreach ($items as $exam)
                @php $asg = $exam['assignment'] ?? null; @endphp
                @if ($asg)
                    @php
                        [$dDate, $dTime] = array_pad(explode(' · ', (string) $asg['deadline'], 2), 2, '');
                        $asgRatio = ($asg['status'] === 'completed' && $asg['score'] !== null && ($asg['maxScore'] ?? 0) > 0) ? $asg['score'] / $asg['maxScore'] : null;
                        $asgTone = $asg['status'] !== 'completed' ? 'is-none' : ($asgRatio === null ? 'is-none' : ($asgRatio >= 0.999 ? 'is-ac' : ($asgRatio > 0 ? 'is-partial' : 'is-wa')));
                        [$stTone, $stLabel] = match (true) {
                            $asg['status'] === 'completed' => ['green', 'Hoàn thành'],
                            $asg['status'] === 'pending' => ['amber', 'Chờ chấm'],
                            $asg['overdue'] => ['red', 'Quá hạn'],
                            default => ['neutral', 'Chưa hoàn thành'],
                        };
                    @endphp
                    <div x-show="visibleExamIds.includes({{ $exam['id'] }})" x-cloak
                         :style="{ order: visibleExamIds.indexOf({{ $exam['id'] }}) }"
                         :class="visibleExamIds.indexOf({{ $exam['id'] }}) % 2 ? 'is-alternate' : ''"
                         class="assigned-work-grid assigned-work-row">
                        <div class="assigned-work-identity">
                            <a href="{{ $exam['detailHref'] }}" class="assigned-work-title">{{ $exam['title'] }}</a>
                            <div class="assigned-work-meta">
                                <code>{{ $exam['examCode'] ?: '#'.$exam['id'] }}</code>
                                <span>{{ $exam['itemsCount'] }} bài</span>
                                <span>{{ $exam['durationMinutes'] ? $exam['durationMinutes'].' phút' : 'Không giới hạn' }}</span>
                            </div>
                        </div>
                        <div class="assigned-work-owner">
                            <span class="assigned-work-mobile-label">Người giao</span>
                            <strong>{{ $asg['teacher'] }}</strong>
                            <span class="assigned-work-group"><x-lucide name="users" />{{ $asg['group'] }}</span>
                            @if ($asg['assignedAt'])<small>Giao ngày {{ $asg['assignedAt'] }}</small>@endif
                        </div>
                        <div class="assigned-work-due">
                            <span class="assigned-work-mobile-label">Hạn nộp</span>
                            <strong class="{{ $asg['overdue'] ? 'is-overdue' : '' }}"><x-lucide name="calendar-days" />{{ $dDate }}</strong>
                            @if ($dTime)<small>{{ $dTime }}</small>@endif
                            <span class="managed-status {{ $stTone }}" style="margin-top:5px">{{ $stLabel }}</span>
                        </div>
                        <div class="assigned-work-result">
                            <span class="assigned-work-mobile-label">Kết quả / Chú ý</span>
                            <div class="oi-asg-result">
                                <strong class="{{ $asgTone }}">
                                    @if ($asg['status'] === 'completed' && $asg['score'] !== null)
                                        {{ $fmtScore($asg['score']) }}<small>/{{ $fmtScore($asg['maxScore']) }} điểm</small>
                                    @elseif ($asg['status'] === 'pending')
                                        <span class="is-none">Chờ chấm</span>
                                    @else
                                        <span class="is-none">Chưa có điểm</span>
                                    @endif
                                </strong>
                                @if ($asg['status'] === 'completed' && $asg['resultLabel'])
                                    <span class="{{ $asgTone }}" style="font-size:10px;font-weight:600">{{ $asg['resultLabel'] }}</span>
                                @endif
                                <p>{{ $asg['submittedAt'] ? 'Lần nộp gần nhất · '.$asg['submittedAt'] : 'Chưa có bài nộp để đối chiếu' }}</p>
                            </div>
                        </div>
                        <div class="assigned-work-actions">
                            <a href="{{ route('student.assessment.take', $exam['id']) }}" class="assigned-work-primary {{ $asg['status'] === 'completed' ? 'is-complete' : '' }}">
                                <x-lucide name="file-text" />{{ $asg['status'] === 'completed' ? 'Làm lại' : 'Làm đề' }}
                            </a>
                            <a href="{{ $exam['detailHref'] }}" class="is-secondary"><x-lucide name="file-text" />Xem đề</a>
                            @auth
                                <a href="{{ route('practice.history.exam', $exam['id']) }}" class="is-secondary"><x-lucide name="clipboard-list" />Nhật ký</a>
                            @endauth
                        </div>
                    </div>
                @endif
            @endforeach
        </div>

        {{-- Phân trang đề thi --}}
        <nav aria-label="Phân trang đề thi luyện tập" x-show="examTotalPages > 1" x-cloak
             class="mt-3 flex flex-col items-center justify-between gap-2 rounded-xl border border-[#DDEAF0] bg-[#F8FAFB] p-2 sm:flex-row">
            <span class="text-[11px] text-[#607A90]">Trang <b class="text-[#45657D]" x-text="examPage"></b> / <span x-text="examTotalPages"></span></span>
            <div class="flex items-center gap-1.5">
                <button type="button" aria-label="Trang trước" :disabled="examPage === 1" @click="examPageIndex = Math.max(1, examPage - 1)"
                        class="grid h-9 w-9 place-items-center rounded-lg border border-[#DDEAF0] bg-white text-[#45657D] transition hover:border-[#9DC8D7] hover:bg-[#EAF5F8] disabled:cursor-not-allowed disabled:opacity-40">
                    <x-lucide name="chevron-left" class="h-4 w-4" />
                </button>
                <template x-for="n in examTotalPages" :key="'ep' + n">
                    <button type="button" :aria-label="'Trang ' + n" :aria-current="examPage === n ? 'page' : null" @click="examPageIndex = n"
                            class="grid h-9 min-w-9 place-items-center rounded-lg px-2 text-[11px] font-extrabold transition"
                            :class="examPage === n ? 'bg-[#126F91] text-white shadow-[0_3px_8px_rgba(18,111,145,0.16)]' : 'text-[#45657D] hover:bg-white'"
                            x-text="n"></button>
                </template>
                <button type="button" aria-label="Trang sau" :disabled="examPage === examTotalPages" @click="examPageIndex = Math.min(examTotalPages, examPage + 1)"
                        class="grid h-9 w-9 place-items-center rounded-lg border border-[#DDEAF0] bg-white text-[#45657D] transition hover:border-[#9DC8D7] hover:bg-[#EAF5F8] disabled:cursor-not-allowed disabled:opacity-40">
                    <x-lucide name="chevron-right" class="h-4 w-4" />
                </button>
            </div>
        </nav>

        <div x-show="filteredExams.length === 0" x-cloak class="rounded-3xl border border-dashed border-[#C9DFE8] bg-white p-10 text-center">
            <x-lucide name="search" class="mx-auto h-9 w-9 text-[#9DC8D7]" />
            <h2 class="mt-3 text-sm font-black text-[#123B68]">Không có đề thi phù hợp</h2>
            <p class="mt-1 text-xs text-[#607A90]">Thử đổi loại đề hoặc từ khóa tìm kiếm.</p>
            <button type="button" @click="resetExamFilters()" class="mt-4 text-[11px] font-bold text-[#126F91] hover:underline">Xóa bộ lọc</button>
        </div>
        </div>{{-- /examScope !== 'managed' --}}

        @if ($assignScope['canManage'])
            <div x-show="examScope === 'managed'" x-cloak>
                @include('partials.practice-assign-manager', ['type' => 'exam', 'managed' => $managedAssignments['exam'] ?? [], 'assignRole' => $assignRole])
            </div>
        @endif
    </div>

    {{-- SỬA 7/10 — popup Giao bài / Giao đề (chỉ dựng cho giáo viên + admin). --}}
    @if ($assignScope['canAssign'])
        @include('partials.practice-assign-modal')
    @endif
</div>

{{-- SỬA 18/9 (khách: "tab kho bài tập không thấy dữ liệu, click chuyển Bài tập chuyên đề /
     Đề thi không được") — NGUYÊN NHÂN: khối markup ở trên chạy bằng Alpine
     x-data="onthiPracticePage(...)", mà hàm đó lại được @push ở RIÊNG
     public/practice/index.blade.php. Trang học sinh include partial này nhưng không có cái
     @push đó -> onthiPracticePage không tồn tại -> Alpine chết ngay khi khởi tạo: mọi khối
     x-show/x-cloak nằm im (trông như "không có dữ liệu") và 2 nút đổi chế độ bấm không ăn.

     Đưa @push vào CHÍNH partial: hàm luôn đi kèm markup, trang nào include cũng chạy được,
     không phải nhớ thêm một dòng ở mỗi nơi dùng. --}}
@push('scripts')
    @include('partials.practice-page-script')
@endpush
