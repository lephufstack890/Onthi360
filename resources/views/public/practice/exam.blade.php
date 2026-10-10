@extends('layouts.guest')

@section('title', $exam->title)
@section('meta-description', \Illuminate\Support\Str::limit($exam->subtitle ?: ('Đề luyện tập '.$exam->title.' trên Ôn Thi 360 — '.$itemsCount.' câu, '.($exam->duration_minutes ? $exam->duration_minutes.' phút' : 'không giới hạn thời gian').'.'), 155))

@section('content')
@include('partials.practice-ui-fallback-style')
@include('partials.practice-assign-style')
<style>
    /* SỬA 7/10 — các khối mới của màn chi tiết đề (Giao đề / Đề được giao / Nhật ký). CSS thuần vì
       máy chủ không build lại Tailwind. */
    .oi-ed-btn { display: inline-flex; align-items: center; gap: 6px; min-height: 36px; padding: 0 12px; border-radius: 12px;
        border: 1px solid #DCE7EC; background: #fff; color: #45657D; font-size: 12px; font-weight: 600; line-height: 1; white-space: nowrap;
        cursor: pointer; text-decoration: none; transition: background .15s, border-color .15s; }
    .oi-ed-btn:hover { border-color: #9DC8D7; background: #EAF5F8; }
    .oi-ed-btn--assign { border-color: #B8DCC9; background: #EFF7F2; color: #326C54; }
    .oi-ed-btn--assign:hover { border-color: #8FC4A8; background: #E3F2EA; }
    .oi-ed-btn svg { width: 14px; height: 14px; flex: none; }
    .oi-ed-assigned { border: 1px solid #D3E6DC; background: #EFF7F2; border-radius: 12px; padding: 12px; }
    .oi-ed-assigned h2 { display: flex; align-items: center; gap: 6px; margin: 0; font-size: 13px; font-weight: 700; color: #326C54; }
    .oi-ed-assigned h2 svg { width: 16px; height: 16px; flex: none; }
    .oi-ed-assigned p { margin: 6px 0 0; font-size: 12px; line-height: 20px; color: #52687B; }
    .oi-ed-assigned p.is-small { margin-top: 6px; font-size: 11px; line-height: 16px; }
    .oi-ed-assigned p.is-late { color: #B54141; font-weight: 600; }
    .oi-ed-assigned-row { display: flex; align-items: flex-start; gap: 6px; }
    .oi-ed-assigned-row svg { width: 14px; height: 14px; flex: none; margin-top: 2px; }
    .oi-ed-historylink { display: inline-flex; align-items: center; gap: 6px; margin-top: 10px; font-size: 12px; font-weight: 600; color: #126F91; text-decoration: none; }
    .oi-ed-historylink:hover { text-decoration: underline; }
    .oi-ed-historylink svg { width: 14px; height: 14px; }
    .oi-ed-ratingbadge { display: none; align-items: center; gap: 4px; flex-shrink: 0; border-radius: 6px; background: #FFF7E6; padding: 4px 8px; font-size: 11px; font-weight: 600; color: #9B681E; }
    .oi-ed-ratingbadge span { color: #8B98A3; font-weight: 500; }
    .oi-ed-ratingbadge svg { width: 12px; height: 12px; }
    @media (min-width: 768px) { .oi-ed-ratingbadge { display: inline-flex; } }
    .oi-ed-fact { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .oi-ed-rate { margin-top: 12px; border-top: 1px solid #EEF3F6; padding-top: 10px; }
    .oi-ed-rate-title { margin: 0; font-size: 11px; font-weight: 700; color: #45657D; }
    .oi-ed-rate-stars { display: flex; gap: 2px; margin-top: 4px; }
    .oi-ed-rate-stars button { border: 0; background: transparent; padding: 2px; color: #C9D7E0; cursor: pointer; line-height: 0; }
    .oi-ed-rate-stars button svg { fill: none; }
    .oi-ed-rate-stars button.is-on { color: #C58B31; }
    .oi-ed-rate-stars button.is-on svg { fill: #E9B44A; }
    .oi-ed-rate-stars button:disabled { opacity: .6; cursor: default; }
    .oi-ed-rate-stars button:focus-visible { outline: 2px solid #9DC8D7; outline-offset: 2px; border-radius: 4px; }
    .oi-ed-rate-msg { margin: 4px 0 0; font-size: 11px; color: #326C54; }
    .oi-ed-rate-msg.is-error { color: #B54141; }
    @media (max-width: 639px) { .oi-ed-btn .oi-ed-label { display: none; } .oi-ed-btn { padding: 0 10px; } }
</style>
<script>
    // SỬA 7/10 — học sinh đã nộp đề chấm sao. Gửi POST JSON; thành công thì giữ số sao vừa chọn và báo
    // điểm trung bình mới, lỗi (chưa nộp, hết phiên…) thì hiện đúng lý do máy chủ trả về.
    function onthiExamRate(config) {
        return {
            mine: config.mine || 0,
            hover: 0,
            saving: false,
            msg: '',
            isError: false,
            async rate(n) {
                if (this.saving) { return; }
                this.saving = true;
                this.msg = '';
                this.isError = false;
                try {
                    const res = await fetch(config.url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': config.csrf, 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({ rating: n }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (res.ok && data.ok) {
                        this.mine = n;
                        this.msg = 'Cảm ơn bạn! Điểm trung bình hiện tại ' + String(data.avg).replace('.', ',') + '/5 (' + data.count + ' lượt).';
                    } else if (res.status === 422 && data.errors) {
                        const first = Object.values(data.errors)[0];
                        this.msg = Array.isArray(first) ? first[0] : 'Chưa lưu được đánh giá.';
                        this.isError = true;
                    } else {
                        this.msg = res.status === 419 ? 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.' : 'Chưa lưu được đánh giá. Vui lòng thử lại.';
                        this.isError = true;
                    }
                } catch (e) {
                    this.msg = 'Chưa lưu được đánh giá. Vui lòng kiểm tra kết nối và thử lại.';
                    this.isError = true;
                } finally {
                    this.saving = false;
                }
            },
        };
    }
</script>
@if ($canAssign)
<script>
    // Phiên bản rút gọn của onthiPracticePage() chỉ giữ phần popup giao đề, dùng chung partial
    // practice-assign-modal với trang danh sách. Giữ nguyên tên trạng thái/hàm để modal chạy được.
    function onthiExamAssign(config) {
        return {
            assignUrl: config.assignUrl,
            assignSearchUrl: config.assignSearchUrl || '',
            csrf: config.csrf,
            assign: { open: false, type: 'exam', id: 0, title: '', code: '', students: [], deadline: '', error: '', saving: false, saved: null },
            get assignLabel() { return 'Giao đề'; },
            nowLocal() {
                const d = new Date(Date.now() - new Date().getTimezoneOffset() * 60000);
                return d.toISOString().slice(0, 16);
            },
            openAssign() {
                this.assign = { open: true, type: 'exam', id: config.id, title: config.title, code: config.code, students: [], deadline: '', error: '', saving: false, saved: null };
            },
            closeAssign() { this.assign.open = false; },
            async submitAssign() {
                const a = this.assign;
                if (a.saving || a.saved) { return; }
                if (!a.students.length) { a.error = 'Chọn ít nhất 1 học sinh.'; return; }
                if (!a.deadline || !Number.isFinite(new Date(a.deadline).getTime())) { a.error = 'Vui lòng chọn ngày và giờ hạn nộp hợp lệ.'; return; }
                if (new Date(a.deadline).getTime() <= Date.now()) { a.error = 'Hạn nộp phải ở sau thời điểm hiện tại.'; return; }
                a.saving = true;
                a.error = '';
                try {
                    const res = await fetch(this.assignUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf, 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({ type: 'exam', subject_id: a.id, student_ids: a.students.map((s) => s.id), deadline: a.deadline }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (res.ok && data.ok) {
                        a.saved = data;
                    } else if (res.status === 422 && data.errors) {
                        const first = Object.values(data.errors)[0];
                        a.error = Array.isArray(first) ? first[0] : 'Dữ liệu chưa hợp lệ.';
                    } else if (res.status === 419) {
                        a.error = 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang rồi thử lại.';
                    } else if (res.status === 401 || res.status === 403) {
                        a.error = 'Bạn không có quyền giao nội dung này.';
                    } else {
                        a.error = 'Chưa lưu được lượt giao. Vui lòng thử lại.';
                    }
                } catch (e) {
                    a.error = 'Chưa lưu được lượt giao. Vui lòng kiểm tra kết nối và thử lại.';
                } finally {
                    a.saving = false;
                }
            },
        };
    }
</script>
@endif
{{-- ═══════════════ MÀN CHI TIẾT ĐỀ LUYỆN TẬP ═══════════════
     SỬA 2/10 (khách: "khi click vào xem chi tiết đề thi nó hiển thị ra màn UI mới giống như
     source mới đang click, UI đã có trong source mới rồi") — dựng theo
     education-main/src/components/ExamDetailPage.jsx: thanh đầu dính + nút Bắt đầu làm bài,
     cột trái xem trước đề, cột phải 3 khối (Thông tin đề thi / Kết quả của bạn / Cơ cấu điểm).

     KHÁC BẢN MẪU 3 CHỖ, đều có lý do:
       · Bản mẫu có điểm sao + số lượt đánh giá. Hệ thống CHƯA có đánh giá cho đề (bảng reviews
         không nhận target 'assessment') và bản mẫu cũng tự ghi đó là dữ liệu minh hoạ — nên bỏ
         hẳn thay vì vẽ 5 sao rỗng hoặc bịa điểm.
       · Bản mẫu xem trước đề bằng ảnh từng trang dựng sẵn. Ở đây đề là PDF thật, nên xem trước
         bằng trình xem PDF dùng chung (pdf-fit-viewer) và CHỈ hiện đúng khoảng trang admin đã
         khai ở ô "Xem thử từ trang… đến trang…" — phần còn lại phải vào phòng thi mới xem được.
       · Biểu đồ tròn: bản mẫu đổi giữa "điểm theo loại câu" (đã làm) và "cơ cấu điểm" (chưa
         làm). Ở đây luôn vẽ CƠ CẤU ĐIỂM của đề, vì điểm từng loại câu của lượt làm gần nhất cần
         một truy vấn nối attempt_answers với dạng câu — chưa có, và vẽ bừa thì sai số liệu. --}}
@php
    use App\Support\ExamCategory;

    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
    $latestScore = $latestScore ?? null;

    // Nền biểu đồ tròn: conic-gradient từng khúc theo tỉ lệ điểm của mỗi dạng câu, đúng cách
    // scoreChartBackground() của bản mẫu làm.
    $segments = [];
    $pos = 0.0;
    foreach ($structure as $part) {
        $start = $pos;
        $pos += $totalPoints > 0 ? max(0, min(100 - $pos, $part['maxScore'] / $totalPoints * 100)) : 0;
        $segments[] = $part['color'].' '.round($start, 2).'% '.round($pos, 2).'%';
    }
    $segments[] = '#E7EFF3 '.round($pos, 2).'% 100%';
    $chartBackground = $segments === [] ? '#E7EFF3' : 'conic-gradient('.implode(', ', $segments).')';
@endphp

@php
    $assignConfig = $canAssign ? [
        'id' => $exam->id,
        'title' => $exam->title,
        'code' => $exam->exam_code ?: '#'.$exam->id,
        'assignUrl' => route('practice.assign'),
        'assignSearchUrl' => route('practice.assign.students'),
        'csrf' => csrf_token(),
    ] : null;
@endphp
<div class="min-h-screen bg-[#F7F9FB] text-[#466278]"
     @if ($canAssign) x-data="onthiExamAssign({{ Js::from($assignConfig) }})" @endif>
    <header class="sticky top-0 z-30 border-b border-[#DDEAF0] bg-white/95 backdrop-blur-xl">
        <div class="w-full px-4 py-2.5 sm:px-6 lg:px-8">
            <div class="flex items-center gap-2.5">
                <a href="{{ $backHref }}" aria-label="Quay lại danh sách đề"
                   class="inline-flex min-h-9 shrink-0 items-center gap-1.5 rounded-xl border border-[#DCE7EC] bg-white px-2.5 text-[12px] font-semibold text-[#45657D] transition hover:border-[#9DC8D7] hover:bg-[#EAF5F8]">
                    <x-lucide name="arrow-left" class="h-4 w-4" /><span class="hidden sm:inline">Danh sách đề</span>
                </a>
                <div class="hidden h-6 w-px bg-slate-200 sm:block"></div>

                @if ($coverUrl)
                    <img src="{{ $coverUrl }}" alt="" class="h-9 w-9 shrink-0 rounded-lg border border-[#DDEAF0] bg-[#F8FBFC] object-cover">
                @else
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-[#DDEAF0] bg-[#F8FBFC] text-[#9DC8D7]"><x-lucide name="file-text" class="h-4 w-4" /></span>
                @endif

                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-[16px] font-bold leading-5 text-[#123B68]">{{ $exam->title }}</h1>
                    <p class="truncate text-[12px] text-[#61798B]">
                        {{ $exam->exam_code ?: '#'.$exam->id }} · {{ $examCategoryLabel ?: 'Đề luyện tập' }} · {{ $exam->duration_minutes ? $exam->duration_minutes.' phút' : 'Không giới hạn' }}
                    </p>
                </div>

                @if ($rating !== null && $reviewCount > 0)
                    <span class="oi-ed-ratingbadge" title="Đánh giá {{ number_format($rating, 1, ',', '') }}/5 từ {{ $reviewCount }} lượt">
                        <x-lucide name="star" class="oi-star is-on" />{{ number_format($rating, 1, ',', '') }} <span>({{ $reviewCount }})</span>
                    </span>
                @endif
                @if ($historyHref)
                    <a href="{{ $historyHref }}" class="oi-ed-btn" title="Nhật ký nộp bài">
                        <x-lucide name="clipboard-list" /><span class="oi-ed-label">Nhật ký</span>
                    </a>
                @endif
                @if ($canAssign)
                    <button type="button" class="oi-ed-btn oi-ed-btn--assign" @click="openAssign()" title="Giao đề cho học sinh">
                        <x-lucide name="send" /><span class="oi-ed-label">Giao đề</span>
                    </button>
                @endif

                <a href="{{ $takeHref }}"
                   class="hidden min-h-9 shrink-0 items-center gap-1.5 rounded-xl bg-[#126F91] px-3 text-[12px] font-semibold text-white transition hover:bg-[#0D5B77] sm:inline-flex">
                    <x-lucide name="play" class="h-3.5 w-3.5" />{{ $canTakeDirectly ? ($attemptCount > 0 ? 'Làm đề' : 'Bắt đầu làm bài') : 'Đăng nhập để làm' }}
                </a>
            </div>

            <a href="{{ $takeHref }}"
               class="mt-2 inline-flex min-h-9 w-full items-center justify-center gap-1.5 rounded-xl bg-[#126F91] px-3 text-[12px] font-semibold text-white transition hover:bg-[#0D5B77] sm:hidden">
                <x-lucide name="play" class="h-3.5 w-3.5" />{{ $canTakeDirectly ? ($attemptCount > 0 ? 'Làm đề' : 'Bắt đầu làm bài') : 'Đăng nhập để làm' }}
            </a>
        </div>
    </header>

    {{-- SỬA 2/10 (khách: "UI phải full ra như vậy nhé với khối chiều cao bằng nhau") —
         · bỏ tràn 1240px: khung trải hết bề ngang màn hình, chỉ chừa lề 16/24/32px;
         · items-stretch (thay items-start): hai cột cao BẰNG NHAU, cột trái kéo dài xuống
           đúng đáy cột phải thay vì hụt một khoảng như ảnh khách gửi. --}}
    <main class="grid w-full items-stretch gap-3 px-4 py-3.5 sm:px-6 lg:grid-cols-[minmax(0,1fr)_320px] lg:gap-4 lg:px-8 lg:py-4">
        {{-- ── CỘT TRÁI: XEM TRƯỚC ĐỀ ── --}}
        <section aria-label="Nội dung đề thi" class="order-2 flex min-w-0 flex-col rounded-2xl border border-[#DCE7EC] bg-white p-2.5 shadow-[0_3px_16px_rgba(28,91,121,0.04)] sm:p-3 lg:order-1">
            {{-- ── SỬA 2/10 lần 4 (khách: "chỗ xem trước pdf dài quá thì cho scroll nha, các khối
                 phải bằng nhau chứ không phải kéo dài thế") ─────────────────────────────────

                 BẪY Ở ĐÂY: trước đó khung xem trước là một flex item có overflow-y-auto, tưởng
                 là sẽ tự cuộn. Nhưng chiều cao của CẢ Ô LƯỚI cột trái được tính theo max-content
                 của nó, mà max-content của một flex item đang grow thì vẫn tính theo nội dung
                 thật — tức là theo chiều cao của toàn bộ tệp PDF. Nên trang bị kéo dài ra, và
                 overflow-y-auto chẳng bao giờ có dịp cuộn.

                 CÁCH SỬA: tách làm 2 lớp. Lớp ngoài giữ chỗ (flex-1, có chiều cao tối thiểu) nên
                 nó mới là thứ quyết định cột trái cao bao nhiêu. Lớp trong đặt absolute inset-0
                 nên ĐỨNG NGOÀI luồng tính chiều cao: tệp PDF dài tới đâu cũng không đẩy được ô
                 lưới, chỉ cuộn bên trong. Kết quả: hai cột luôn bằng nhau đúng chiều cao của cột
                 phải, đề dài thì cuộn trong khung. --}}
            <div class="relative min-h-[60dvh] flex-1 lg:min-h-[360px]">
                <div class="absolute inset-0 overflow-y-auto rounded-xl border border-[#DDEAF0] bg-[#E9F0F4] p-2 sm:p-4">
                @if ($previewUrl)
                    <div data-pdf-fit data-pdf-url="{{ $previewUrl }}" data-pdf-max-width="900" class="oi-doc-col"></div>
                @else
                    {{-- Không có bản xem trước: thay bằng CẤU TRÚC ĐỀ — nói đúng những gì hệ thống
                         biết chắc (số câu từng dạng, điểm từng dạng), không hé nội dung đề. --}}
                    <div class="mx-auto max-w-[900px] rounded-xl border border-[#DDEAF0] bg-white p-5 sm:p-7">
                        <div class="text-center">
                            <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-[#EAF5F8] text-[#126F91]"><x-lucide name="file-text" class="h-6 w-6" /></span>
                            <h2 class="mt-3 text-base font-bold text-[#123B68]">Đề này chưa mở bản xem trước</h2>
                            <p class="mx-auto mt-1 max-w-md text-[12px] leading-5 text-[#61798B]">
                                Nội dung đề mở ra khi bạn bấm <span class="font-bold text-[#126F91]">Bắt đầu làm bài</span>. Dưới đây là cấu trúc đề để bạn hình dung trước.
                            </p>
                        </div>

                        <div class="mt-5 divide-y divide-[#EEF3F6] border-t border-[#EEF3F6]">
                            @forelse ($structure as $part)
                                <div class="flex items-center justify-between gap-3 py-2.5">
                                    <span class="flex min-w-0 items-center gap-2 text-[13px] font-semibold text-[#45657D]">
                                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $part['color'] }}"></span>
                                        <span class="truncate">{{ $part['label'] }}</span>
                                    </span>
                                    <span class="shrink-0 text-[12px] text-[#61798B]">{{ $part['count'] }} câu · <strong class="font-bold text-[#123B68]">{{ $fmt($part['maxScore']) }} điểm</strong></span>
                                </div>
                            @empty
                                <p class="py-4 text-center text-[12px] text-[#61798B]">Đề này chưa gắn câu hỏi nào.</p>
                            @endforelse
                        </div>
                    </div>
                @endif
                </div>
            </div>

            @if ($previewUrl)
                {{-- $previewRange chỉ có khi bản xem trước là mấy trang CẮT TỪ ĐỀ PDF. Với tệp
                     PDF xem trước tải lên riêng thì không có "trang mấy–mấy" để nói.

                     Ghép câu bằng PHP chứ không kẹp @if giữa dòng chữ: directive Blade dính ngay
                     sau một chữ cái (…trước@if) KHÔNG được Blade nhận ra, @endif sau đó thành
                     thừa và trang vỡ 500 — đúng cái bẫy đã làm hỏng màn làm bài hôm trước. --}}
                {{-- SỬA 2/10 lần 5 — câu chữ phải khớp với sự thật MỚI: tệp PDF tải lên giờ
                     cũng chính là đề bài trong phòng thi, nên không còn "phần còn lại" nào mở ra
                     sau khi bấm nữa. Chỉ đề PDF cũ cắt theo khoảng trang mới đúng là xem một
                     phần. Hứa sai còn tệ hơn không hứa gì. --}}
                @php
                    $previewNote = $previewRange !== null
                        ? 'Đây là bản xem trước (trang '.$previewRange['from'].'–'.$previewRange['to'].'). Toàn bộ đề mở ra khi bạn bấm Bắt đầu làm bài.'
                        : 'Đây là đề bài của đề thi này. Bấm Bắt đầu làm bài để vào phòng thi, tính giờ và nộp bài.';
                @endphp
                <p class="mt-2 px-1 text-[11px] text-[#61798B]">{{ $previewNote }}</p>
            @endif
        </section>

        {{-- ── CỘT PHẢI ── --}}
        <aside class="order-1 flex flex-col gap-3 lg:order-2">
            {{-- Theo ExamAccessInfo.jsx: học sinh được giao đề này thì làm không mất phí. Bản mẫu còn
                 khối "Giá làm đề" — hệ thống chưa có giá đề nên không dựng, tránh hiện số giả. --}}
            @if ($assignment)
                <section class="oi-ed-assigned" aria-label="Quyền làm đề được giao">
                    <h2><x-lucide name="check-circle-2" />Đề được giao · Không thu phí</h2>
                    <p>Bạn không cần mua đề để thực hiện lượt được giáo viên giao.</p>
                    <p class="is-small">{{ $assignment['teacher'] }} · {{ $assignment['group'] }}</p>
                    <p class="is-small oi-ed-assigned-row {{ ($assignment['overdue'] ?? false) ? 'is-late' : '' }}">
                        <x-lucide name="calendar-days" />Hạn nộp: {{ $assignment['deadline'] ?: 'Theo lịch giáo viên' }}{{ ($assignment['overdue'] ?? false) ? ' · Đã quá hạn' : '' }}
                    </p>
                    <p class="is-small">
                        @if ($assignment['status'] === 'completed')
                            Trạng thái: hoàn thành{{ $assignment['resultLabel'] ? ' · '.$assignment['resultLabel'] : '' }}.
                        @elseif ($assignment['status'] === 'pending')
                            Trạng thái: đã nộp, đang chờ chấm.
                        @else
                            Trạng thái: chưa hoàn thành.
                        @endif
                    </p>
                </section>
            @endif

            <section class="rounded-2xl border border-[#F0D99D] bg-[#FFF9E9] p-3.5">
                <h2 class="text-[13px] font-bold text-[#0B3C78]">Thông tin đề thi</h2>
                {{-- SỬA 7/10 — Tỉnh/thành + Khu vực dạng huy hiệu, đúng ContentLocation của bản mẫu. --}}
                @include('partials.practice-content-location', ['province' => $provinceLabel, 'region' => $regionLabel])
                @if ($exam->subtitle)
                    <p class="mt-1.5 text-[12px] leading-[1.55] text-[#52687B]">{{ $exam->subtitle }}</p>
                @endif

                <dl class="mt-3 space-y-1.5 border-t border-[#EADDB9] pt-2.5 text-[11px] leading-4">
                    @foreach ([['Tác giả', $exam->author], ['Năm học', $exam->academic_year]] as [$label, $value])
                        <div class="grid grid-cols-[76px_minmax(0,1fr)] gap-1.5">
                            <dt class="text-[#7B6D50]">{{ $label }}</dt>
                            <dd class="min-w-0 {{ $value ? 'font-semibold text-[#123B68]' : 'text-[#887C6C]' }}">{{ $value ?: 'Chưa cập nhật' }}</dd>
                        </div>
                    @endforeach
                </dl>

                <div class="mt-3 space-y-2 border-t border-[#EADDB9] pt-3 text-[12px] text-[#52687B]">
                    <div class="oi-ed-fact"><span>Độ khó:</span>@include('partials.practice-difficulty-stars', ['level' => $difficultyLevel, 'label' => $difficultyLabel])</div>
                    <p class="flex items-center gap-2">
                        <x-lucide name="clock-3" class="h-3.5 w-3.5 text-[#A87530]" />
                        <span>Thời gian: <strong class="font-semibold text-[#123B68]">{{ $exam->duration_minutes ? $exam->duration_minutes.' phút' : 'Không giới hạn' }}</strong></span>
                    </p>
                    <p class="flex items-center gap-2">
                        <x-lucide name="list-checks" class="h-3.5 w-3.5 text-[#A87530]" />
                        <span>Số câu: <strong class="font-semibold text-[#123B68]">{{ $itemsCount }}</strong></span>
                    </p>
                    <p class="flex items-center gap-2">
                        <x-lucide name="award" class="h-3.5 w-3.5 text-[#A87530]" />
                        <span>Tổng điểm: <strong class="font-semibold text-[#123B68]">{{ $fmt($totalPoints) }}</strong></span>
                    </p>
                    <div class="oi-ed-fact"><span>Đánh giá:</span>@include('partials.practice-exam-rating', ['rating' => $rating, 'count' => $reviewCount])</div>
                </div>
            </section>

            <section class="rounded-2xl border border-[#DCE7EC] bg-white p-3.5 shadow-[0_3px_16px_rgba(28,91,121,0.04)]">
                <h2 class="text-[13px] font-bold text-[#0B3C78]">Kết quả của bạn</h2>
                @if ($canTakeDirectly)
                    <div class="mt-2.5 grid grid-cols-2 gap-2">
                        <div class="rounded-xl border border-[#C5DEE7] bg-[#E5F1F4] p-2.5">
                            <p class="text-[11px] font-medium text-[#456B7D]">Số lần đã làm</p>
                            <p class="mt-1 text-[16px] font-bold leading-5 text-[#0B3C78]">{{ $attemptCount }}</p>
                        </div>
                        <div class="rounded-xl border border-[#E4CF9B] bg-[#F4E8C7] p-2.5">
                            <p class="text-[11px] font-medium text-[#785C2A]">Điểm gần nhất</p>
                            <p class="mt-1 text-[16px] font-bold leading-5 text-[#6F4F18]">{{ $latestScore !== null ? $fmt($latestScore).'/'.$fmt($totalPoints) : 'Chưa có' }}</p>
                        </div>
                    </div>
                    @if ($latestSubmittedAt)
                        <p class="mt-2 text-[11px] text-[#61798B]">Nộp lúc {{ $latestSubmittedAt->format('H:i d/m/Y') }}</p>
                    @endif
                    @if ($canRate)
                        {{-- SỬA 7/10 — học sinh đã nộp đề chấm sao cho đề (1-5). Chấm lại thì ghi đè lượt cũ. --}}
                        <div class="oi-ed-rate" x-data="onthiExamRate({{ Js::from(['url' => $rateUrl, 'csrf' => csrf_token(), 'mine' => $myRating, 'avg' => $rating, 'count' => $reviewCount]) }})">
                            <p class="oi-ed-rate-title">Đánh giá đề này</p>
                            <div class="oi-ed-rate-stars" role="radiogroup" aria-label="Chấm sao cho đề">
                                <template x-for="n in 5" :key="n">
                                    <button type="button" role="radio" :aria-checked="mine === n" :aria-label="n + ' sao'"
                                            :class="(hover || mine) >= n ? 'is-on' : ''"
                                            :disabled="saving" @mouseenter="hover = n" @mouseleave="hover = 0" @click="rate(n)">
                                        <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"/></svg>
                                    </button>
                                </template>
                            </div>
                            <p class="oi-ed-rate-msg" x-show="msg" x-cloak :class="isError ? 'is-error' : ''" x-text="msg" role="status"></p>
                        </div>
                    @endif
                    @if ($historyHref)
                        <a href="{{ $historyHref }}" class="oi-ed-historylink"><x-lucide name="clipboard-list" />Xem nhật ký nộp bài</a>
                    @endif
                @else
                    <p class="mt-2 text-[12px] leading-5 text-[#61798B]">Đăng nhập để xem số lần đã làm và điểm gần nhất của bạn với đề này.</p>
                @endif
            </section>

            <section class="rounded-2xl border border-[#DCE7EC] bg-white p-3.5 shadow-[0_3px_16px_rgba(28,91,121,0.04)]">
                <h2 class="text-[13px] font-bold text-[#0B3C78]">Cơ cấu điểm của đề</h2>
                <div class="mt-3 flex flex-col items-center gap-3">
                    <div class="relative h-48 w-48 shrink-0 rounded-full sm:h-52 sm:w-52" style="background: {{ $chartBackground }}"
                         role="img" aria-label="Biểu đồ cơ cấu {{ $fmt($totalPoints) }} điểm của đề">
                        <div class="absolute inset-[19%] flex flex-col items-center justify-center rounded-full bg-white text-center">
                            <strong class="text-[18px] font-bold leading-5 text-[#123B68]">{{ $fmt($totalPoints) }}</strong>
                            <span class="text-[11px] text-[#61798B]">điểm cả đề</span>
                        </div>
                    </div>

                    <ul class="w-full space-y-1.5 text-[12px]">
                        @forelse ($structure as $part)
                            <li class="flex items-center justify-between gap-2">
                                <span class="flex min-w-0 items-center gap-2 text-[#52687B]">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $part['color'] }}"></span>{{ $part['label'] }}
                                </span>
                                <strong class="shrink-0 font-semibold text-[#123B68]">{{ $fmt($part['maxScore']) }} điểm</strong>
                            </li>
                        @empty
                            <li class="text-center text-[#61798B]">Đề này chưa gắn câu hỏi nào.</li>
                        @endforelse
                    </ul>
                </div>
                <p class="mt-2.5 text-center text-[11px] text-[#61798B]">Điểm bạn đạt được hiện ở trang kết quả sau khi nộp đề.</p>
            </section>
        </aside>
    </main>

    @if ($canAssign)
        @include('partials.practice-assign-modal')
    @endif
</div>
@endsection

@push('scripts')
    @include('partials.work-doc-col-style')
    @include('partials.pdf-fit-viewer')
@endpush
