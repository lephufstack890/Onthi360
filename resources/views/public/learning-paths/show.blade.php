@extends('layouts.guest')

@section('title', $path->title.' — Lộ trình học Ôn Thi 360')
@section('meta-description', \Illuminate\Support\Str::limit($path->subtitle ?: ($path->goal_label.' · '.$path->gradeLabel().' · '.count($steps).' bậc'), 155))
@section('meta-image', $path->shareImageUrl() ?: ($path->coverUrl() ?: asset('og-image.png')))

@section('content')
{{-- ═══════════════ [PATH-SHOW] CHI TIẾT LỘ TRÌNH (B4) ═══════════════

     SỬA 15/9 (khách chốt: "cho cái ảnh ở trên, ở dưới là một bài viết giải thích về lộ trình
     và các bước, cuối là nút xem lớp của lộ trình đó").

     Bố cục đọc từ trên xuống:
       1. HERO — ảnh lộ trình (thumbnail quản trị tải lên) đi cặp với bảng thông tin chính:
          tên, mục tiêu đích, bốn con số, nhịp học và nút xem lớp. Gói vào một màn hình đầu.
       2. BÀI VIẾT — dành cho ai / học xong được gì (một hàng hai cột), rồi các bậc.
       3. NHẮC LẠI NÚT XEM LỚP sau khi đọc xong.

     ── Vì sao vẫn giữ thang bậc dựng bằng HTML ──
     Nó KHÔNG còn là nội dung chính nữa, mà là ảnh đỡ khi quản trị chưa tải ảnh lên. Lộ trình
     mới tạo mà chưa kịp nhờ thiết kế vẽ áp phích thì trang vẫn có hình, vẫn đúng số buổi —
     thay vì một ô trống. Tải ảnh lên là ảnh thật thay chỗ ngay.

     Dữ liệu: App\Services\Public\LearningPathService::showData(). --}}
@php
    $steps = $steps ?? [];
    $related = $related ?? [];
    $purchasableCount = $purchasableCount ?? 0;
    $stepCount = count($steps);

    $coverUrl = $path->coverUrl();

    /*
     * Chống lặp chữ. Quản trị hay điền trùng nhau giữa các ô (nhãn nhỏ đúng bằng tiêu đề, mô
     * tả đúng bằng mục tiêu...), in ra hết thì trang đọc như bị lỗi. So sánh bỏ dấu cách và
     * không phân biệt hoa thường rồi chỉ in cái nào thật sự khác.
     */
    $same = fn (?string $a, ?string $b) => $a !== null && $b !== null
        && mb_strtolower(trim($a)) === mb_strtolower(trim($b));

    $showEyebrow = filled($path->eyebrow) && ! $same($path->eyebrow, $path->title);
    $showBrand = ! $showEyebrow && filled($path->brand) && ! $same($path->brand, $path->title);

    $showSubtitle = filled($path->subtitle) && ! $same($path->subtitle, $path->title);
    $showDescription = filled($path->description)
        && ! $same($path->description, $path->subtitle)
        && ! $same($path->description, $path->goal_label);

    // Đường dẫn sang trang Lớp học, đã lọc sẵn theo lộ trình này (xem CourseController::index).
    $classesHref = route('courses.index', ['lo-trinh' => $path->id]);

    // Tổng số lớp đang mở của cả lộ trình — quyết định lời trên nút cuối bài.
    $openClassTotal = array_sum(array_map(fn (array $s) => (int) $s['openClassCount'], $steps));

    /*
     * Chiều cao bậc cho thang dự phòng: thấp nhất 168px, cao nhất 330px, chia đều theo số bậc.
     */
    /*
     * Số cột của lưới bậc bám theo SỐ BẬC THẬT, không để cứng 4 cột.
     * Lộ trình 2 bậc mà lưới 4 cột thì nửa hàng bỏ trống — đúng cái lỗi "trống bên phải" vừa
     * sửa ở khối kết quả đầu ra, chỉ là chuyển sang chỗ khác. Viết thành chuỗi class tĩnh để
     * Tailwind quét thấy và sinh CSS (ghép chuỗi động thì Tailwind bỏ sót).
     */
    $stepGrid = match (true) {
        $stepCount <= 1 => 'grid-cols-1',
        $stepCount === 2 => 'grid-cols-1 sm:grid-cols-2',
        $stepCount === 3 => 'grid-cols-1 sm:grid-cols-2 xl:grid-cols-3',
        default => 'grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4',
    };

    $stepMin = 168;
    $stepMax = 330;
    $stepGap = $stepCount > 1 ? ($stepMax - $stepMin) / ($stepCount - 1) : 0;
@endphp

<style>
    /* ══ Thang bậc dự phòng (chỉ dùng khi chưa có ảnh) ══ */
    .lp-board { position: relative; overflow: hidden; border-radius: 28px; border: 1px solid #E7EFF6;
        background: linear-gradient(158deg, #FFF8EE 0%, #FDFAF4 34%, #F1F8FD 100%);
        box-shadow: 0 10px 34px rgba(24, 74, 120, .07); padding: 22px 18px 18px; }
    .lp-board__blob { position: absolute; border-radius: 50%; filter: blur(52px); opacity: .55; pointer-events: none; }
    .lp-board__blob--warm { width: 320px; height: 320px; top: -130px; left: -90px; background: #FFD9A8; }
    .lp-board__blob--cool { width: 360px; height: 360px; bottom: -170px; right: -110px; background: #BFE2FA; }
    .lp-board__inner { position: relative; z-index: 1; }
    .lp-stairs-scroll { margin: 0 -6px; padding: 34px 6px 0; overflow-x: auto; }
    .lp-stairs-ground { position: relative; min-width: 700px; }
    .lp-stairs-ground::after { content: ''; position: absolute; left: 0; right: 0; bottom: -7px; height: 7px;
        border-radius: 999px; background: linear-gradient(90deg, rgba(31, 91, 146, .04), rgba(31, 91, 146, .16), rgba(31, 91, 146, .04)); }
    .lp-stairs { display: flex; align-items: flex-end; gap: 10px; min-width: 700px; }
    .lp-step { position: relative; flex: 1 1 0; min-width: 0; height: var(--h);
        display: flex; flex-direction: column; align-items: center; justify-content: flex-end;
        gap: 6px; padding: 16px 8px; border-radius: 18px 18px 8px 8px; color: #fff;
        background: var(--c); text-decoration: none; box-shadow: 0 10px 22px -8px rgba(20, 45, 80, .38); }
    .lp-step::before { content: ''; position: absolute; inset: 0 0 auto; height: 46%; border-radius: 18px 18px 0 0;
        background: linear-gradient(180deg, rgba(255, 255, 255, .20), rgba(255, 255, 255, 0)); pointer-events: none; }
    .lp-step__num { position: absolute; top: -17px; left: 50%; transform: translateX(-50%);
        display: grid; place-items: center; width: 34px; height: 34px; border-radius: 50%;
        background: #fff; color: var(--ink); font-weight: 900; font-size: 15px;
        border: 3px solid var(--c); box-shadow: 0 3px 8px rgba(20, 45, 80, .18); }
    .lp-step__body { display: flex; flex-direction: column; align-items: center; gap: 5px; width: 100%; }
    .lp-step__code { font-weight: 900; font-size: 13px; line-height: 1.1; text-align: center; text-transform: uppercase; }
    .lp-step__sub { font-weight: 700; font-size: 9.5px; letter-spacing: .10em; text-transform: uppercase; opacity: .88; }
    .lp-pill { display: inline-block; max-width: 100%; border-radius: 999px; background: #fff; color: var(--ink);
        padding: 4px 11px; font-size: 11px; font-weight: 800; line-height: 1.25; text-align: center;
        box-shadow: 0 2px 6px rgba(20, 45, 80, .16); }
    .lp-pill--sessions { font-size: 13.5px; font-weight: 900; padding: 4px 14px; }
    .lp-pace { margin: 18px auto 0; display: flex; width: fit-content; max-width: 100%; align-items: center;
        gap: 10px; border-radius: 999px; border: 1px solid #E7EFF6; background: #fff; padding: 9px 20px;
        box-shadow: 0 6px 16px rgba(24, 74, 120, .08); }
    .lp-pace__text { font-size: 13px; font-weight: 900; color: #123B68; letter-spacing: .01em; }

    /* ══ Bài viết ══
       SỬA 15/9 (khách: "cho full ra") — BỎ cột hẹp 880px. Trước đây cả bài nằm giữa một thẻ
       trắng khổng lồ, hai bên trống huơ. Giờ dàn hết bề ngang trang và chia thành từng khối
       riêng, khối nào cần đọc dài thì tự giới hạn độ dài dòng bằng max-width của chính nó —
       dàn rộng nhưng không để dòng chữ kéo 1700px không ai đọc nổi. */
    .lp-sec { border-radius: 24px; border: 1px solid #DDEAF0; background: #fff; padding: 20px;
        box-shadow: 0 2px 10px rgba(28, 91, 121, .04); }
    .lp-prose { font-size: 14.5px; line-height: 1.75; color: #4A6076; max-width: 68ch; }
    .lp-lead { font-size: 16px; line-height: 1.7; color: #33506B; font-weight: 500; max-width: 74ch; }
    .lp-h2 { display: flex; align-items: center; gap: 9px; font-size: 17px; font-weight: 900; color: #123B68; letter-spacing: -.01em; }
    .lp-h2__dot { display: grid; place-items: center; width: 28px; height: 28px; border-radius: 10px;
        background: #EAF3FC; color: #1F5B92; flex-shrink: 0; }

    /* ── Bậc dạng thẻ, xếp lưới cho kín bề ngang ──
       Trước là một danh sách dọc mảnh khảnh chạy giữa trang. Dạng thẻ vừa lấp kín chiều
       ngang vừa cho mỗi bậc một mảng màu riêng, nhìn ra ngay lộ trình có mấy chặng. */
    .lp-card { display: flex; flex-direction: column; overflow: hidden; border-radius: 22px;
        border: 1px solid var(--ring); background: #fff; box-shadow: 0 2px 10px rgba(28, 91, 121, .045);
        transition: transform .18s ease, box-shadow .18s ease; }
    .lp-card:hover { transform: translateY(-3px); box-shadow: 0 12px 26px rgba(28, 91, 121, .11); }
    .lp-card__top { display: flex; align-items: center; gap: 10px; background: var(--c); color: #fff; padding: 12px 14px; }
    .lp-card__num { display: grid; place-items: center; width: 34px; height: 34px; flex-shrink: 0;
        border-radius: 50%; background: rgba(255, 255, 255, .24); font-size: 15px; font-weight: 900;
        box-shadow: inset 0 0 0 1.5px rgba(255, 255, 255, .45); }
    .lp-card__code { font-size: 12.5px; font-weight: 900; letter-spacing: .04em; text-transform: uppercase; line-height: 1.15; }
    .lp-card__sub { font-size: 9.5px; font-weight: 700; letter-spacing: .10em; text-transform: uppercase; opacity: .85; }
    .lp-card__body { display: flex; flex-direction: column; flex: 1; padding: 14px; }
    .lp-card__title { font-size: 15px; font-weight: 900; color: #123B68; line-height: 1.35; }
    .lp-card__title a { color: inherit; text-decoration: none; }
    .lp-card__title a:hover { color: #126F91; }
    .lp-card__text { margin-top: 5px; font-size: 13px; line-height: 1.6; color: #5B7288; }

    /* ── Ô số liệu ── */
    .lp-stat { border-radius: 16px; border: 1px solid; padding: 11px 13px; }
    .lp-stat__label { display: flex; align-items: center; gap: 5px; font-size: 9.5px; font-weight: 800;
        letter-spacing: .06em; text-transform: uppercase; opacity: .85; }
    .lp-stat__value { display: block; margin-top: 4px; font-size: 21px; font-weight: 900; line-height: 1; }

    .lp-facts { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 9px; }
    .lp-fact { display: inline-flex; align-items: center; gap: 4px; border-radius: 8px;
        border: 1px solid #E3EDF4; background: #F6FAFD; padding: 3px 9px;
        font-size: 11px; font-weight: 700; color: #5B7288; }
    .lp-fact--ok { border-color: #CDE9DC; background: #F1FAF6; color: #2C7D5F; }
    .lp-fact--warn { border-color: #F3DCC5; background: #FFF6EC; color: #A9591F; }
    .lp-fact--price { border-color: #F2E4BD; background: #FFFBEF; color: #8A6A1C; }

    .lp-btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px;
        min-height: 44px; border-radius: 14px; padding: 0 22px;
        font-size: 13px; font-weight: 800; text-decoration: none; white-space: nowrap;
        transition: background-color .16s ease, border-color .16s ease, transform .16s ease; }
    .lp-btn:active { transform: scale(.985); }
    .lp-btn--main { background: #126F91; color: #fff; box-shadow: 0 6px 16px rgba(18, 111, 145, .22); }
    .lp-btn--main:hover { background: #0F5E7C; }
    .lp-btn--ghost { border: 1px solid #DDEAF0; background: #fff; color: #126F91; }
    .lp-btn--ghost:hover { background: #F2F8F9; border-color: #C9DFE8; }
    .lp-btn--small { min-height: 32px; padding: 0 12px; font-size: 11.5px; border-radius: 10px; }

    @media (max-width: 860px) {
        .lp-board { padding: 18px 14px 16px; border-radius: 22px; }
        .lp-stairs-scroll { overflow: visible; padding-top: 22px; }
        .lp-stairs-ground { min-width: 0; }
        .lp-stairs-ground::after { display: none; }
        .lp-stairs { flex-direction: column; align-items: stretch; min-width: 0; gap: 12px; }
        .lp-step { height: auto; flex-direction: row; align-items: center; justify-content: flex-start;
            gap: 12px; padding: 14px 16px; border-radius: 18px; }
        .lp-step::before { inset: 0 auto 0 0; width: 46%; height: auto; border-radius: 18px 0 0 18px;
            background: linear-gradient(90deg, rgba(255, 255, 255, .18), rgba(255, 255, 255, 0)); }
        .lp-step__num { position: static; transform: none; flex-shrink: 0; }
        .lp-step__body { align-items: flex-start; gap: 4px; }
        .lp-step__code, .lp-pill { text-align: left; }
    }

    @media (max-width: 560px) {
        .lp-stepwrap { padding-left: 0; }
        .lp-stepwrap::before { display: none; }
        .lp-stepitem__num { position: static; margin-bottom: 9px; }
    }
</style>

<div class="max-w-[1780px] w-full mx-auto px-3 sm:px-5 lg:px-6 2xl:px-10 py-3 sm:py-5">
<div class="flex flex-col gap-5">

    {{-- ══════ Đường dẫn ══════ --}}
    <nav aria-label="Đường dẫn" class="flex flex-wrap items-center gap-1.5 text-[11px] font-medium text-[#71869A]">
        <a href="{{ route('home') }}" class="transition-colors hover:text-[#126F91]">Trang chủ</a>
        <x-lucide name="chevron-right" class="h-3 w-3" />
        <a href="{{ route('learningPaths.index') }}" class="transition-colors hover:text-[#126F91]">Lộ trình học</a>
        <x-lucide name="chevron-right" class="h-3 w-3" />
        <span class="font-bold text-[#123B68]">{{ $path->title }}</span>
    </nav>

    {{-- ══════ 1. HERO — ẢNH + THÔNG TIN CHÍNH ══════
         SỬA 15/9 (khách: "ảnh hơi to, chia lại layout cho đẹp") — trước ảnh trải hết bề ngang
         nên cao gần 850px, chiếm trọn màn hình đầu, khách phải cuộn mới thấy tiêu đề và nút.

         Giờ ghép ảnh với bảng thông tin thành MỘT khối hai cột. Được hai việc cùng lúc: ảnh
         thu lại còn khoảng 60% bề ngang (cao vừa phải), và ngay màn hình đầu khách đã thấy đủ
         tên lộ trình, mục tiêu đích, bốn con số và nút bấm — không phải cuộn để biết có nên
         quan tâm hay không.

         Điện thoại thì xếp dọc: ảnh trên, thông tin dưới. --}}
    <section class="grid grid-cols-1 gap-4 lg:grid-cols-[1.32fr_1fr] xl:gap-5">

        {{-- Ảnh lộ trình do quản trị tải lên. Chưa có ảnh thì dựng tạm thang bậc bằng HTML. --}}
        @if ($coverUrl)
            {{-- self-start + h-auto: ảnh giữ NGUYÊN tỉ lệ, không cắt xén.
                 Dùng object-cover cho cao bằng cột bên cạnh thì tấm áp phích bị xén mất mép —
                 mà mép phải của nó lại đang là viên mục tiêu và cái cúp. --}}
            <figure class="self-start overflow-hidden rounded-3xl border border-[#DDEAF0] bg-white shadow-[0_6px_24px_rgba(24,74,120,.08)]">
                <img src="{{ $coverUrl }}" alt="Lộ trình {{ $path->title }} — {{ $path->gradeLabel() }}, {{ $stepCount }} bậc"
                     loading="eager" decoding="async" class="block h-auto w-full">
            </figure>
        @elseif ($stepCount > 0)
            <section class="lp-board">
                <span class="lp-board__blob lp-board__blob--warm"></span>
                <span class="lp-board__blob lp-board__blob--cool"></span>

                <div class="lp-board__inner">
                    <div class="lp-stairs-scroll">
                      <div class="lp-stairs-ground">
                        <div class="lp-stairs">
                            @foreach ($steps as $i => $step)
                                @php $height = (int) round($stepMin + $i * $stepGap); @endphp
                                <a href="{{ $step['href'] }}" title="{{ $step['title'] }}" class="lp-step"
                                   style="--c: {{ $step['color']['solid'] }}; --ink: {{ $step['color']['ink'] }}; --h: {{ $height }}px">
                                    <span class="lp-step__num">{{ $step['position'] }}</span>
                                    <span class="lp-step__body">
                                        <span class="lp-step__code">{{ $step['levelCode'] ?: 'BẬC '.$step['position'] }}</span>
                                        @if ($step['levelSubtitle'])
                                            <span class="lp-step__sub">{{ $step['levelSubtitle'] }}</span>
                                        @endif
                                        @if ($step['sessionCount'] > 0)
                                            <span class="lp-pill lp-pill--sessions">{{ $step['sessionCount'] }} buổi</span>
                                        @endif
                                    </span>
                                </a>
                            @endforeach
                        </div>
                      </div>
                    </div>
                </div>
            </section>
        @endif

        {{-- Bảng thông tin chính --}}
        <div class="flex flex-col rounded-3xl border border-[#DDEAF0] bg-white p-5 shadow-[0_2px_10px_rgba(28,91,121,.04)] sm:p-6">
            @if ($showEyebrow)
                <p class="text-[10.5px] font-black uppercase tracking-[.12em] text-[#2D7FA3]">{{ $path->eyebrow }}</p>
            @elseif ($showBrand)
                <span class="w-fit rounded-xl bg-[#F2864B] px-3 py-1 text-[12px] font-black tracking-[.10em] text-white shadow-[0_4px_12px_rgba(242,134,75,.3)]">{{ $path->brand }}</span>
            @endif

            <h1 class="{{ $showEyebrow || $showBrand ? 'mt-2.5' : '' }} text-[23px] font-black leading-tight tracking-tight text-[#123B68] sm:text-[27px]">{{ $path->title }}</h1>

            @if ($showSubtitle)
                <p class="mt-2 text-[14px] leading-relaxed text-[#5B7288]">{{ $path->subtitle }}</p>
            @endif

            {{-- Mục tiêu đích — thứ phụ huynh quyết định dựa vào, nên cho nó chỗ đứng riêng. --}}
            <div class="mt-4 flex items-center gap-3 rounded-2xl border border-[#F3DFA8] bg-[#FFF8E3] px-3.5 py-3">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-[#FFD974] to-[#F5A623] text-white shadow-[0_5px_14px_rgba(226,160,30,.3)]">
                    <x-lucide name="trophy" class="h-5 w-5" />
                </span>
                <span class="min-w-0">
                    <span class="block text-[9.5px] font-black uppercase tracking-[.12em] text-[#A9822C]">Mục tiêu đích</span>
                    <span class="mt-0.5 block text-[13.5px] font-black leading-snug text-[#7A5A14]">{{ $path->goal_label }}</span>
                </span>
            </div>

            {{-- Bốn con số phụ huynh hỏi đầu tiên --}}
            <div class="mt-3 grid grid-cols-2 gap-2.5">
                @foreach ([
                    ['label' => 'Khối lớp', 'value' => $path->gradeLabel(), 'icon' => 'graduation-cap', 'tone' => 'text-[#1F6BA0] bg-[#EAF5FD] border-[#CFE3F5]'],
                    ['label' => 'Số bậc', 'value' => $stepCount ?: '—', 'icon' => 'layers', 'tone' => 'text-[#5E36A0] bg-[#F3EDFC] border-[#E0D4F5]'],
                    ['label' => 'Tổng buổi', 'value' => $path->totalSessions() ?: '—', 'icon' => 'calendar-days', 'tone' => 'text-[#26785B] bg-[#EDFAF4] border-[#C9E9DC]'],
                    ['label' => 'Thời gian', 'value' => $path->totalSessions() > 0 ? '≈ '.$path->totalWeeks().' tuần' : '—', 'icon' => 'clock-3', 'tone' => 'text-[#A9591F] bg-[#FFF3E6] border-[#F6DDC2]'],
                ] as $stat)
                    <div class="lp-stat {{ $stat['tone'] }}">
                        <span class="lp-stat__label"><x-lucide :name="$stat['icon']" class="h-3 w-3" />{{ $stat['label'] }}</span>
                        <span class="lp-stat__value {{ mb_strlen((string) $stat['value']) > 6 ? '!text-[15px]' : '' }}">{{ $stat['value'] }}</span>
                    </div>
                @endforeach
            </div>

            <div class="mt-2.5 flex items-center gap-2 rounded-2xl border border-[#E7EFF6] bg-[#F8FBFD] px-3.5 py-2.5">
                <x-lucide name="calendar-days" class="h-4 w-4 shrink-0 text-[#E4572E]" />
                <span class="text-[12.5px] font-bold text-[#123B68]">{{ $path->paceLabel() }}</span>
            </div>

            {{-- Nút chính đẩy xuống đáy để hai cột thẳng chân nhau trên màn rộng. --}}
            <div class="mt-auto pt-4">
                <div class="flex flex-col gap-2 sm:flex-row">
                    <a href="{{ $classesHref }}" class="lp-btn lp-btn--main flex-1">
                        <x-lucide name="school" class="h-4 w-4" />Xem lớp của lộ trình này
                    </a>

                    @if ($purchasableCount > 0)
                        @php $firstBuyable = collect($steps)->first(fn ($s) => $s['buyHref'] !== null); @endphp
                        @if ($firstBuyable)
                            <a href="{{ $firstBuyable['buyHref'] }}" class="lp-btn lp-btn--ghost shrink-0">
                                <x-lucide name="banknote" class="h-4 w-4" />Đăng ký
                            </a>
                        @endif
                    @else
                        <a href="{{ route('info.index') }}" class="lp-btn lp-btn--ghost shrink-0">
                            <x-lucide name="phone" class="h-4 w-4" />Tư vấn
                        </a>
                    @endif
                </div>

                @if ($openClassTotal > 0)
                    <p class="mt-2 text-center text-[11.5px] font-semibold text-[#3FAE86] sm:text-left">
                        Đang có {{ $openClassTotal }} lớp mở — vào học được ngay
                    </p>
                @endif
            </div>
        </div>
    </section>

    {{-- ══════ 2. BÀI VIẾT ══════ --}}

    {{-- ── Dành cho ai  |  Học xong được gì ──
         Ghép hai mục thành một hàng hai cột: cả hai đều ngắn, để riêng thì mỗi mục một dải
         ngang mỏng dính, ghép lại thì kín bề ngang và đọc thành một cặp hỏi–đáp tự nhiên. --}}
    <div class="grid grid-cols-1 gap-4 {{ $path->outcomeList() !== [] ? 'lg:grid-cols-2' : '' }}">
        <section class="lp-sec">
            <h2 class="lp-h2">
                <span class="lp-h2__dot"><x-lucide name="target" class="h-3.5 w-3.5" /></span>
                Lộ trình này dành cho ai
            </h2>

            <p class="lp-prose mt-3">
                Dành cho học sinh <strong class="font-bold text-[#123B68]">{{ mb_strtolower($path->gradeLabel()) }}</strong>,
                với đích đến là <strong class="font-bold text-[#123B68]">{{ $path->goal_label }}</strong>.
                @if ($stepCount > 0)
                    Cả chặng chia thành {{ $stepCount }} bậc nối tiếp nhau, học xong bậc này mới sang bậc kế tiếp,
                    nên lúc nào cũng biết con đang ở đâu và còn bao xa nữa tới đích.
                @endif
            </p>

            @if ($showDescription)
                <div class="lp-prose mt-3 whitespace-pre-line">{{ $path->description }}</div>
            @endif
        </section>

        @if ($path->outcomeList() !== [])
            <section class="lp-sec">
                <h2 class="lp-h2">
                    <span class="lp-h2__dot"><x-lucide name="check-circle" class="h-3.5 w-3.5" /></span>
                    Học xong lộ trình, con sẽ
                </h2>

                <div class="mt-3 flex flex-col gap-2">
                    @foreach ($path->outcomeList() as $outcome)
                        <div class="flex items-start gap-2.5 rounded-2xl border border-[#D6EFE3] bg-[#F3FBF7] px-4 py-3">
                            <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-white text-[#3FAE86]">
                                <x-lucide name="check-circle" class="h-3 w-3" />
                            </span>
                            <span class="text-[13.5px] font-semibold leading-snug text-[#26785B]">{{ $outcome }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    {{-- ── Các bậc, xếp lưới cho kín bề ngang ── --}}
    @if ($stepCount > 0)
        <section>
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="lp-h2">
                    <span class="lp-h2__dot"><x-lucide name="layers" class="h-3.5 w-3.5" /></span>
                    {{ $stepCount }} bậc của lộ trình
                </h2>
                <p class="text-[11.5px] font-semibold text-[#8BA0B5]">Học theo thứ tự từ bậc 1 đến bậc {{ $stepCount }}</p>
            </div>

            <div class="grid gap-4 {{ $stepGrid }}">
                @foreach ($steps as $step)
                    <article class="lp-card"
                             style="--c: {{ $step['color']['solid'] }}; --soft: {{ $step['color']['soft'] }}; --ink: {{ $step['color']['ink'] }}; --ring: {{ $step['color']['ring'] }}">
                        <div class="lp-card__top">
                            <span class="lp-card__num">{{ $step['position'] }}</span>
                            <span class="min-w-0">
                                <span class="lp-card__code block truncate">{{ $step['levelCode'] ?: 'Bậc '.$step['position'] }}</span>
                                @if ($step['levelSubtitle'])
                                    <span class="lp-card__sub block truncate">{{ $step['levelSubtitle'] }}</span>
                                @endif
                            </span>
                        </div>

                        <div class="lp-card__body">
                            <h3 class="lp-card__title"><a href="{{ $step['href'] }}">{{ $step['title'] }}</a></h3>

                            @if ($step['outcome'])
                                <p class="lp-card__text">Kết thúc bậc này, con <strong class="font-bold text-[#123B68]">{{ mb_strtolower($step['outcome']) }}</strong>.</p>
                            @endif

                            <div class="lp-facts">
                                @if ($step['sessionCount'] > 0)
                                    <span class="lp-fact"><x-lucide name="calendar-days" class="h-3 w-3" />{{ $step['sessionCount'] }} buổi</span>
                                @endif
                                @if ($step['openClassCount'] > 0)
                                    <span class="lp-fact lp-fact--ok"><x-lucide name="school" class="h-3 w-3" />{{ $step['openClassCount'] }} lớp đang mở</span>
                                @else
                                    <span class="lp-fact lp-fact--warn"><x-lucide name="school" class="h-3 w-3" />Chưa có lớp mở</span>
                                @endif
                                @if ($step['priceLabel'])
                                    <span class="lp-fact lp-fact--price"><x-lucide name="banknote" class="h-3 w-3" />{{ $step['priceLabel'] }}</span>
                                @endif
                            </div>

                            {{-- Đẩy nút xuống đáy thẻ để các thẻ trong cùng hàng thẳng chân nhau. --}}
                            <div class="mt-auto flex flex-wrap items-center gap-2 pt-3.5">
                                @if ($step['myClassHref'])
                                    <a href="{{ $step['myClassHref'] }}" class="lp-btn lp-btn--small" style="background: #3FAE86; color: #fff">
                                        <x-lucide name="log-in" class="h-3 w-3" />Vào học
                                    </a>
                                @elseif ($step['buyHref'])
                                    <a href="{{ $step['buyHref'] }}" class="lp-btn lp-btn--main lp-btn--small">
                                        <x-lucide name="banknote" class="h-3 w-3" />Đăng ký bậc này
                                    </a>
                                @endif
                                <a href="{{ $step['href'] }}" class="lp-btn lp-btn--ghost lp-btn--small">
                                    Xem khoá học <x-lucide name="chevron-right" class="h-3 w-3" />
                                </a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ══════ 3. NHẮC LẠI NÚT XEM LỚP ══════
         Nút chính đã nằm ngay ở hero. Băng này là lần nhắc thứ hai, đặt đúng lúc khách vừa
         đọc xong các bậc — chỗ người ta thật sự quyết định. Cố ý làm mỏng hơn hero để không
         tranh chỗ với nó. --}}
    <section class="overflow-hidden rounded-3xl border border-[#CDE8EC] bg-gradient-to-r from-[#F4FBFC] via-[#F7FCFD] to-[#EFF7FB] px-5 py-5 sm:px-7">
        <div class="flex flex-col items-center gap-4 text-center lg:flex-row lg:justify-between lg:text-left">
            <div class="min-w-0">
                <h2 class="text-[17px] font-black text-[#123B68] sm:text-[19px]">Sẵn sàng bắt đầu?</h2>
                <p class="mt-1 max-w-2xl text-[13px] leading-relaxed text-[#5B7288]">
                    @if ($openClassTotal > 0)
                        Hiện có {{ $openClassTotal }} lớp đang mở thuộc các bậc của lộ trình. Xem lịch học, giáo viên phụ trách và sĩ số từng lớp trước khi ghi danh.
                    @else
                        Chưa có lớp nào đang mở. Xem danh sách lớp để biết khi nào trung tâm mở lớp mới, hoặc liên hệ để được báo trước.
                    @endif
                </p>
            </div>

            <div class="flex shrink-0 flex-wrap items-center justify-center gap-2">
                <a href="{{ $classesHref }}" class="lp-btn lp-btn--main">
                    <x-lucide name="school" class="h-4 w-4" />Xem lớp của lộ trình này
                    <x-lucide name="chevron-right" class="h-3.5 w-3.5" />
                </a>
                <a href="{{ route('access.activate') }}" class="lp-btn lp-btn--ghost">
                    <x-lucide name="key-round" class="h-3.5 w-3.5" />Đã có mã kích hoạt
                </a>
            </div>
        </div>
    </section>

    {{-- ══════ Lộ trình khác ══════ --}}
    @if (count($related) > 0)
        <section>
            <h2 class="type-section-title">Lộ trình khác</h2>
            <div class="mt-2.5 grid grid-cols-1 gap-2.5 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($related as $r)
                    <a href="{{ $r['href'] }}"
                       class="flex items-center justify-between gap-2 rounded-2xl border border-[#E6EFF4] bg-white px-4 py-3 transition-all hover:border-[#B8DFE8] hover:shadow-[0_4px_14px_rgba(28,91,121,.07)]">
                        <span class="min-w-0">
                            <span class="block truncate text-[13px] font-bold text-[#123B68]">{{ $r['title'] }}</span>
                            <span class="mt-0.5 block text-[10.5px] text-[#71869A]">{{ $r['gradeLabel'] }} · {{ $r['stepCount'] }} bậc</span>
                        </span>
                        <x-lucide name="chevron-right" class="h-3.5 w-3.5 shrink-0 text-[#8BA0B5]" />
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</div>
</div>

{{-- ══════ B8 · DỮ LIỆU CÓ CẤU TRÚC ══════
     Khai báo lộ trình là một Course có nhiều phần (hasPart) để Google hiểu đây là chương
     trình học nhiều bậc chứ không phải một bài viết thường. Chỉ khai những gì CÓ THẬT trong
     cơ sở dữ liệu — không bịa thêm đánh giá, số học viên hay ngày khai giảng. --}}
@php
    $ldSteps = [];
    foreach ($steps as $s) {
        $ldSteps[] = array_filter([
            '@type' => 'Course',
            'name' => $s['levelCode'] ? $s['levelCode'].' — '.$s['title'] : $s['title'],
            'description' => $s['outcome'] ?: null,
            'url' => $s['href'],
            'position' => $s['position'],
        ]);
    }

    $ld = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Course',
        'name' => $path->title,
        'description' => $path->subtitle ?: $path->goal_label,
        'url' => route('learningPaths.show', $path->slug),
        'image' => $path->shareImageUrl() ?: $path->coverUrl(),
        'educationalLevel' => $path->gradeLabel(),
        'teaches' => $path->outcomeList() ?: null,
        'provider' => [
            '@type' => 'Organization',
            'name' => 'Ôn Thi 360',
            'url' => route('home'),
        ],
        'hasPart' => $ldSteps ?: null,
    ]);
@endphp
<script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endsection
