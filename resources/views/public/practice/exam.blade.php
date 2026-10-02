@extends('layouts.guest')

@section('title', $exam->title)
@section('meta-description', \Illuminate\Support\Str::limit($exam->subtitle ?: ('Đề luyện tập '.$exam->title.' trên Ôn Thi 360 — '.$itemsCount.' câu, '.($exam->duration_minutes ? $exam->duration_minutes.' phút' : 'không giới hạn thời gian').'.'), 155))

@section('content')
@include('partials.practice-ui-fallback-style')
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

<div class="min-h-screen bg-[#F7F9FB] text-[#466278]">
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

                <a href="{{ $takeHref }}"
                   class="hidden min-h-9 shrink-0 items-center gap-1.5 rounded-xl bg-[#126F91] px-3 text-[12px] font-semibold text-white transition hover:bg-[#0D5B77] sm:inline-flex">
                    <x-lucide name="play" class="h-3.5 w-3.5" />{{ $canTakeDirectly ? ($attemptCount > 0 ? 'Làm lại đề' : 'Bắt đầu làm bài') : 'Đăng nhập để làm' }}
                </a>
            </div>

            <a href="{{ $takeHref }}"
               class="mt-2 inline-flex min-h-9 w-full items-center justify-center gap-1.5 rounded-xl bg-[#126F91] px-3 text-[12px] font-semibold text-white transition hover:bg-[#0D5B77] sm:hidden">
                <x-lucide name="play" class="h-3.5 w-3.5" />{{ $canTakeDirectly ? ($attemptCount > 0 ? 'Làm lại đề' : 'Bắt đầu làm bài') : 'Đăng nhập để làm' }}
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
            <div class="min-h-[360px] flex-1 overflow-y-auto rounded-xl border border-[#DDEAF0] bg-[#E9F0F4] p-2 sm:p-4">
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

            @if ($previewUrl)
                {{-- $previewRange chỉ có khi bản xem trước là mấy trang CẮT TỪ ĐỀ PDF. Với tệp
                     PDF xem trước tải lên riêng thì không có "trang mấy–mấy" để nói.

                     Ghép câu bằng PHP chứ không kẹp @if giữa dòng chữ: directive Blade dính ngay
                     sau một chữ cái (…trước@if) KHÔNG được Blade nhận ra, @endif sau đó thành
                     thừa và trang vỡ 500 — đúng cái bẫy đã làm hỏng màn làm bài hôm trước. --}}
                @php
                    $previewNote = $previewRange !== null
                        ? 'Đây là bản xem trước (trang '.$previewRange['from'].'–'.$previewRange['to'].').'
                        : 'Đây là bản xem trước của đề.';
                @endphp
                <p class="mt-2 px-1 text-[11px] text-[#61798B]">
                    {{ $previewNote }} Toàn bộ đề mở ra khi bạn bấm <span class="font-bold text-[#126F91]">Bắt đầu làm bài</span>.
                </p>
            @endif
        </section>

        {{-- ── CỘT PHẢI ── --}}
        <aside class="order-1 flex flex-col gap-3 lg:order-2">
            <section class="rounded-2xl border border-[#F0D99D] bg-[#FFF9E9] p-3.5">
                <h2 class="text-[13px] font-bold text-[#0B3C78]">Thông tin đề thi</h2>
                @if ($exam->subtitle)
                    <p class="mt-1.5 text-[12px] leading-[1.55] text-[#52687B]">{{ $exam->subtitle }}</p>
                @endif

                <dl class="mt-3 space-y-1.5 border-t border-[#EADDB9] pt-2.5 text-[11px] leading-4">
                    @foreach ([['Tác giả', $exam->author], ['Tỉnh/thành', $provinceLabel], ['Năm học', $exam->academic_year]] as [$label, $value])
                        <div class="grid grid-cols-[76px_minmax(0,1fr)] gap-1.5">
                            <dt class="text-[#7B6D50]">{{ $label }}</dt>
                            <dd class="min-w-0 {{ $value ? 'font-semibold text-[#123B68]' : 'text-[#887C6C]' }}">{{ $value ?: 'Chưa cập nhật' }}</dd>
                        </div>
                    @endforeach
                </dl>

                <div class="mt-3 space-y-2 border-t border-[#EADDB9] pt-3 text-[12px] text-[#52687B]">
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
</div>
@endsection

@push('scripts')
    @include('partials.work-doc-col-style')
    @include('partials.pdf-fit-viewer')
@endpush
