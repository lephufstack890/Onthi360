{{--
  SỬA 29/9 (khách chốt: "bỏ file pdf sách đi, chỗ chương mỗi chương thêm từng file pdf...
  khi học sinh hoặc giáo viên mua xong hoặc giáo viên gắn vào lớp học thì từng file pdf sẽ
  ghép dài để lướt lên lướt xuống để đọc") — TRANG ĐỌC LIỀN MẠCH CẢ SẢN PHẨM.

  Dùng CHUNG cho student.products.read và teacher.products.read ($layoutView/$libraryRoute do
  App\Services\ProductReadService::buildReadData() truyền vào theo vai trò đang gọi) — cùng
  cách student/materials/read.blade.php đang dùng chung cho 2 vai trò.

  Phần vỏ (thanh đầu trang, khung đọc có thanh cuộn riêng, thanh tiến độ, nút thu/phóng, chế
  độ Tập trung) CHÉP ĐÚNG lớp CSS/Tailwind của trang đọc 1 bài đang chạy — KHÔNG đặt thêm lớp
  Tailwind mới, vì VPS không chạy được vite nên lớp chưa có trong public/build/assets/app-*.css
  sẽ không có tác dụng. Mấy thứ mới (vạch ngăn chương, mục lục) viết bằng CSS thường ở dưới.

  Bộ đọc: pdf.js tải LẦN LƯỢT từng tệp PDF của từng chương rồi vẽ nối tiếp vào cùng một dải
  cuộn — tải lười (chỉ tải chương sau khi người đọc cuộn gần hết phần đã tải) để sách 20 chương
  không phải tải vài chục MB ngay khi mở trang.
--}}
@extends($layoutView ?? 'layouts.student')

@section('title', $product->title)
@section('page-title', 'Đọc tài liệu')

@section('content')
    @php
        $parts = $parts ?? [];
        $watermarkText = $watermarkText ?? '';
        $chapterWord = $chapterWord ?? 'Phần';
        $libraryRoute = $libraryRoute ?? 'student.library.index';
        $typeLabels = ['book' => 'Sách', 'topic' => 'Chuyên đề', 'exam' => 'Bộ đề', 'course' => 'Khóa học'];

        // SỬA 29/9 (2) — dữ liệu cho cột bên phải (xem ProductReadService::buildReadData()).
        $exercises = $exercises ?? [];
        $attachments = $attachments ?? [];
        $access = $access ?? ['owned' => false, 'remainingLabel' => null];
        $isTeacherView = $isTeacherView ?? false;
        $doneExercises = collect($exercises)->where('status', 'done')->count();
        $exercisePercent = count($exercises) > 0 ? (int) round($doneExercises / count($exercises) * 100) : 0;

        /*
         * SỬA 3/10 (khách: "UI chỗ đọc tài liệu không giống… 2 cái phải đồng bộ") — màn này
         * dùng CHUNG khung Bài tập / Học liệu / CSS bù với màn đọc một bài
         * (student/materials/read), qua 4 partial reader-*. Bảng màu trạng thái vì thế phải
         * đúng bộ 4 phần tử mà partial đó cần.
         *
         * CÁCH ĐỌC KHÔNG ĐỔI: vẫn nối PDF mọi chương thành một dải cuộn liền mạch, đúng yêu cầu
         * 29/9 của khách. Chỉ thay lớp áo.
         */
        $statusMeta = [
            'done' => ['Đã làm', 'text-[#287B5F]', 'check-circle-2', 'border-[#CBE7D5] bg-[#F0F8F2] hover:bg-[#E8F4EC]'],
            'progress' => ['Đang làm', 'text-[#946A28]', 'play-circle', 'border-[#F0D9A9] bg-[#FFF7E7] hover:bg-[#FFF1D5]'],
            'open' => ['Chưa làm', 'text-[#126F91]', 'play-circle', 'border-[#C8E2EA] bg-[#EFF8FA] hover:bg-[#E6F3F6]'],
        ];

        $exerciseRows = [];
        foreach ($exercises as $ex) {
            $exerciseRows[] = [
                'id' => $ex['id'],
                'done' => $ex['status'] === 'done',
                'search' => mb_strtolower($ex['title'].' '.implode(' ', $ex['tags'])),
            ];
        }

        // Ảnh thu nhỏ ở thanh đầu: bìa sản phẩm; chưa có thì dùng ảnh nền chung của khu Tài liệu
        // chứ KHÔNG mượn ảnh của tài liệu khác.
        $readerCoverUrl = $product->cover_image_path
            ? asset('storage/'.$product->cover_image_path)
            : asset('assets/hero-materials.jpg');
    @endphp

    <style>
        @media print {
            body * { visibility: hidden !important; }
        }

        .reader-progress-track { height: 3px; background: #eef2f7; }
        .reader-progress-bar {
            height: 100%; width: 0%;
            background: linear-gradient(90deg, #fb7185, #e11d48);
            transition: width .12s linear;
        }

        .reader-page-wrap {
            position: relative;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .05), 0 16px 32px -12px rgba(15, 23, 42, .16);
            margin: 0 auto;
            overflow: hidden;
        }
        .reader-page-number {
            text-align: center;
            font-size: .7rem;
            color: #94a3b8;
            margin: 10px auto 26px auto;
        }
        .reader-page-error {
            text-align: center;
            font-size: .75rem;
            color: #e11d48;
            background: #fff1f2;
            border: 1px solid #ffe4e6;
            border-radius: 12px;
            padding: 14px;
            margin: 0 auto 26px auto;
            max-width: 640px;
        }
        .reader-toolbar-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 999px;
            border: none;
            background: transparent;
            color: #475569;
            font-size: 1rem;
            line-height: 1;
            cursor: pointer;
            transition: background .15s, color .15s;
        }
        .reader-toolbar-btn:hover { background: #fff1f2; color: #e11d48; }
        .reader-page-indicator {
            font-size: .75rem;
            font-weight: 500;
            color: #475569;
            min-width: 92px;
            text-align: center;
            font-variant-numeric: tabular-nums;
        }
        .reader-zoom-label {
            font-size: .7rem;
            color: #94a3b8;
            min-width: 40px;
            text-align: center;
            font-variant-numeric: tabular-nums;
        }

        /* ── Mới cho bản đọc liền mạch ── */
        .oi-part-divider {
            display: flex;
            align-items: center;
            gap: 10px;
            max-width: 720px;
            margin: 6px auto 18px auto;
            padding: 0 4px;
        }
        .oi-part-divider:first-child { margin-top: 0; }
        .oi-part-divider .oi-part-line { flex: 1; height: 1px; background: #cfe0ea; }
        .oi-part-divider .oi-part-name {
            font-size: .72rem;
            font-weight: 700;
            color: #126F91;
            background: #eaf5f8;
            border: 1px solid #d5e8ed;
            border-radius: 999px;
            padding: 4px 12px;
            text-align: center;
            max-width: 70%;
        }
        .oi-part-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: .78rem;
            color: #61798B;
            padding: 18px 0 26px 0;
        }
        .oi-spin {
            display: inline-block;
            width: 16px; height: 16px;
            border: 2px solid #cfe3ee;
            border-top-color: #2D7FA3;
            border-radius: 999px;
            animation: oi-spin-kf .7s linear infinite;
        }
        @keyframes oi-spin-kf { to { transform: rotate(360deg); } }

        .oi-side-scroll { max-height: min(640px, calc(100dvh - 300px)); overflow-y: auto; }
        .oi-toc-item {
            display: block;
            width: 100%;
            text-align: left;
            border: 1px solid #e3eef3;
            background: #fff;
            border-radius: 12px;
            padding: 9px 11px;
            margin-bottom: 7px;
            cursor: pointer;
            transition: border-color .15s, background .15s;
        }
        .oi-toc-item:hover { border-color: #9DC8D7; background: #F3FAFC; }
        .oi-toc-item.is-current { border-color: #2D7FA3; background: #EAF5F8; }
        .oi-toc-item.is-sub { margin-left: 14px; }
        .oi-toc-label {
            display: block;
            font-size: .62rem;
            font-weight: 800;
            letter-spacing: .03em;
            text-transform: uppercase;
            color: #7FA5B8;
            margin-bottom: 2px;
        }
        .oi-toc-title { display: block; font-size: .8rem; font-weight: 600; color: #123B68; line-height: 1.35; }
        .oi-toc-state { display: block; font-size: .65rem; color: #93A9B7; margin-top: 2px; }

@include('partials.reader-ui-fallback-style')
    </style>

    <div x-data="onthiMaterialReader({{ Js::from(['exercises' => $exerciseRows, 'perPage' => 3]) }})"
         class="bg-[#F7F9FB] text-[#466278]">

        {{-- ══════ THANH ĐẦU TRANG ══════
             SỬA 3/10 — dựng y thanh đầu của màn đọc một bài: nền trang trí (ảnh hero mờ + 2
             quầng màu), ảnh thu nhỏ, số trang PDF, 2 viên quyền. --}}
        <header x-show="! focus"
                class="sticky top-0 z-30 isolate relative min-h-[74px] overflow-hidden border-b border-[#CFE5E5] bg-white/95 shadow-[0_3px_14px_rgba(45,96,145,0.07)] backdrop-blur-xl">
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 z-0 overflow-hidden">
                <img src="{{ asset('assets/hero-materials.jpg') }}" alt="" loading="eager" decoding="async"
                     class="absolute inset-0 h-full w-full object-cover object-right" style="opacity: .48">
                <div class="absolute inset-0" style="background-image: linear-gradient(to right, rgba(255,255,255,.98), rgba(255,255,255,.86), rgba(255,255,255,.16))"></div>
                <div class="absolute -right-20 -top-28 h-64 w-64 rounded-full blur-3xl" style="background-color: rgba(189,234,222,.58)"></div>
                <div class="absolute -bottom-24 h-48 w-48 rounded-full blur-3xl" style="right: 24%; background-color: rgba(255,228,167,.38)"></div>
            </div>
            <div class="relative z-10 h-[3px] bg-gradient-to-r from-[#123B68] via-[#2D7FA3] to-[#E6B44A]" aria-hidden="true"></div>
            <div class="relative z-10 mx-auto flex w-full max-w-[1780px] flex-wrap items-center gap-3 px-3 py-2.5 sm:px-5 sm:py-3 lg:px-6 2xl:px-10">
                <a href="{{ route($libraryRoute) }}" aria-label="Quay lại kho tài liệu"
                   class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-[#DDEAF0] bg-white text-[#466278] transition hover:border-[#9DC8D7] hover:bg-[#F0F8FB] hover:text-[#123B68]">
                    <x-lucide name="arrow-left" class="h-4 w-4" />
                </a>
                <div class="hidden h-6 w-px bg-slate-200 sm:block"></div>

                <img src="{{ $readerCoverUrl }}" alt="" decoding="async"
                     class="h-9 w-9 shrink-0 rounded-lg border border-[#DDEAF0] bg-white object-cover">

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1 rounded-full bg-[#EAF5F8] px-2.5 py-1 text-[10px] font-extrabold text-[#126F91]">
                            <x-lucide name="book-open" class="h-3 w-3" />Đang đọc tài liệu
                        </span>
                        {{-- Số trang do pdf.js đếm được sau khi tải xong, script điền vào đây. --}}
                        <span id="reader-page-count" class="hidden text-[11px] text-[#61798B] sm:inline"></span>
                        <span class="hidden rounded-lg bg-[#F8FBFE] px-2 py-1 font-mono text-[10px] font-bold text-[#61798B] lg:inline-flex">
                            {{ $typeLabels[$product->type->value] ?? '' }}
                        </span>
                    </div>
                    <h1 class="mt-1 truncate text-[14px] font-semibold text-[#123B68] sm:text-base">{{ $product->title }}</h1>
                </div>

                @if ($access['owned'])
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-[#B7DDCD] bg-[#DFF2E9] px-2.5 py-1.5 text-[11px] font-semibold text-[#287B5F]" title="Đã sở hữu">
                        <x-lucide name="check-circle-2" class="h-3 w-3" /><span>Đã sở hữu</span>
                    </span>
                    @if ($access['remainingLabel'])
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-[#F2E1B6] bg-[#FFF7E3] px-2.5 py-1.5 text-[11px] font-semibold text-[#8E6B2E]" title="Thời hạn còn lại">
                            <x-lucide name="clock" class="h-3 w-3" /><span>{{ $access['remainingLabel'] }}</span>
                        </span>
                    @endif
                @endif

                <a href="{{ route($libraryRoute) }}" aria-label="Đóng trình đọc"
                   class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-slate-400 transition hover:bg-[#FFF1F1] hover:text-[#B42318]">
                    <x-lucide name="x" class="h-4 w-4" />
                </a>
            </div>
        </header>

        <button type="button" @click="focus = false" x-show="focus" x-cloak
                class="fixed right-4 top-4 z-40 inline-flex items-center gap-1.5 rounded-xl border border-[#B7DDCD] bg-white/95 px-3 py-2 text-[12px] font-semibold text-[#287B5F] shadow-[0_6px_18px_rgba(45,96,145,0.12)] backdrop-blur transition hover:bg-[#DFF2E9]">
            <x-lucide name="minimize-2" class="h-3.5 w-3.5" /><span>Khôi phục</span>
        </button>

        <main class="mx-auto w-full max-w-[1780px] px-3 py-4 sm:px-5 lg:px-6 lg:py-6 2xl:px-10">
            @if (empty($parts))
                <div class="rounded-[28px] border border-[#D5E8ED] bg-white p-8 text-center">
                    <x-ws.empty-state title="Tài liệu này chưa có nội dung đọc"
                                      description="Các chương/phần chưa được tải file PDF lên. Vui lòng liên hệ bộ phận hỗ trợ." />
                </div>
            @else
                <div class="grid items-stretch gap-3 lg:grid-cols-[minmax(0,1fr)_340px]">

                    {{-- ══════ CỘT TRÁI: DẢI PDF LIỀN MẠCH ══════ --}}
                    <section class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-[28px] border border-[#D5E8ED] bg-[#F2F8FA] shadow-[0_7px_26px_rgba(45,96,145,0.055)]">
                        {{-- ══ THANH CÔNG CỤ ══
                             SỬA 3/10 — dựng y bản mẫu: ô nhảy tới trang và ô chọn Chương/phần ở
                             bên trái, bên phải CHỈ còn nút Tập trung. Đã bỏ 3 thẻ thu/phóng và
                             dải tiến độ đọc (bản mẫu không có); script vẫn an toàn vì mọi chỗ
                             bám vào các id đó đều kiểm tra null trước. --}}
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[#E5EEF3] px-3 py-2 sm:px-4">
                            <div class="flex min-w-0 flex-wrap items-center gap-2">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-[#E9F7F8] text-[#23869B]"><x-lucide name="file-text" class="h-3.5 w-3.5" /></span>

                                <form id="reader-page-form" class="flex items-center gap-1 text-[10px]" aria-label="Chuyển đến trang">
                                    <label for="reader-page-input" class="text-[#61798B]">Trang</label>
                                    <input id="reader-page-input" type="number" min="1" inputmode="numeric" value="1" aria-label="Số trang"
                                           class="h-6 w-12 rounded-md border border-[#DDEAF0] bg-[#F8FBFE] text-center text-[10px] font-bold text-[#123B68] outline-none transition focus:border-[#2D7FA3] focus:ring-2 focus:ring-[#DDF1F6]">
                                    <span class="text-[#61798B]">/ <span id="reader-page-total">…</span></span>
                                    <button type="submit" aria-label="Đi đến trang" title="Đi đến trang"
                                            class="grid h-6 w-6 place-items-center rounded-md bg-[#2F9E72] text-white transition hover:bg-[#278761]">
                                        <x-lucide name="arrow-right" class="h-3 w-3" />
                                    </button>
                                </form>

                                @if (count($parts) > 0)
                                    {{-- Ở màn này mọi chương nằm trong CÙNG một dải cuộn, nên chọn
                                         chương là cuộn tới chỗ đó chứ không chuyển trang. Bấm hộ
                                         đúng nút trong Mục lục để dùng lại y nguyên phần xử lý
                                         nhảy chương đã có của bộ đọc, khỏi viết bản thứ hai. --}}
                                    <label for="material-section-select" class="shrink-0 text-[12px] font-semibold text-[#466278]">{{ $chapterWord }}</label>
                                    <select id="material-section-select" aria-label="Chọn chương hoặc phần để đọc"
                                            class="h-9 w-[260px] max-w-full rounded-lg border border-[#DDEAF0] bg-[#F8FBFE] px-3 text-[13px] font-semibold text-[#123B68] outline-none transition focus:border-[#2D7FA3] focus:ring-2 focus:ring-[#DDF1F6]">
                                        @foreach ($parts as $i => $part)
                                            <option value="{{ $i }}">{{ $part['label'] ? $part['label'].' · ' : '' }}{{ $part['title'] }}</option>
                                        @endforeach
                                    </select>
                                @endif

                                <span class="hidden truncate text-[11px] font-semibold text-[#61798B] sm:block" id="reader-part-label"></span>
                                <span class="hidden" id="reader-page-indicator"></span>
                            </div>

                            <button type="button" @click="focus = true" x-show="! focus"
                                    class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-xl border border-[#DDEAF0] bg-[#F8FBFE] px-3 text-[11px] font-semibold text-[#2D7FA3] transition hover:border-[#B8D7E1] hover:bg-[#EAF5F8] hover:text-[#126F91]">
                                <x-lucide name="maximize-2" class="h-3.5 w-3.5" /><span>Tập trung</span>
                            </button>
                        </div>

                        <div class="bg-[#EAF4F8] p-3 sm:p-5">
                            <div id="reader-scroll-box" role="region" tabindex="0" aria-label="Vùng đọc PDF"
                                 class="material-pdf-scroll h-[min(760px,calc(100dvh-185px))] min-h-[520px] overflow-y-auto rounded-2xl border border-[#D6E2EA] bg-[#EAF4F8] p-2 sm:min-h-[680px] sm:p-3">
                                <div id="product-pdf-viewer"
                                     data-parts="{{ json_encode($parts, JSON_UNESCAPED_UNICODE) }}"
                                     data-watermark="{{ $watermarkText }}"
                                     class="select-none">
                                    <div class="oi-part-loading"><span class="oi-spin"></span> Đang tải nội dung…</div>
                                </div>
                                <div id="reader-tail"></div>
                            </div>

                            <p class="mt-2.5 select-none text-center text-[11px] text-slate-400">
                                <x-lucide name="lock" class="inline h-3 w-3 shrink-0 align-[-2px]" /> Nội dung chỉ xem trên web — không hỗ trợ tải về hoặc in trực tiếp.
                            </p>
                        </div>
                    </section>

                    {{-- ══════ CỘT PHẢI ══════
                         SỬA 3/10 (khách: "2 cái phải đồng bộ") — dựng lại y cột phải của màn đọc
                         một bài: khung BÀI TẬP kiểu mới ở trên, MỤC LỤC và HỌC LIỆU thành mục
                         gấp/mở ở dưới, thay cho 3 thẻ chuyển qua lại như trước.

                         Khung Bài tập và Học liệu lấy từ partial DÙNG CHUNG với màn kia, nên sửa
                         một lần là cả hai cùng đổi. Mục lục thì riêng của màn này (màn kia dùng ô
                         chọn Chương/phần vì mỗi chương là một trang riêng).

                         Mục lục để MỞ SẴN: ở màn đọc liền mạch nó là thứ người đọc dùng nhiều
                         nhất để nhảy chương. --}}
                    <aside class="flex h-full flex-col lg:sticky" :class="focus ? 'lg:top-4' : 'lg:top-[76px]'">
                        @include('partials.reader-exercise-panel')

                        <section x-data="{ open: true }"
                                 class="mt-3 overflow-hidden rounded-[28px] border border-[#DDEAF0] bg-white shadow-[0_7px_26px_rgba(45,96,145,0.055)]">
                            <button type="button" @click="open = ! open" :aria-expanded="open"
                                    class="flex w-full items-center justify-between gap-3 px-3 py-3 text-left sm:px-4">
                                <span class="flex items-center gap-2 text-[#126F91]">
                                    <x-lucide name="list" class="h-4 w-4" /><span class="text-xs font-semibold">Mục lục</span>
                                </span>
                                <span class="flex items-center gap-2">
                                    <span class="rounded-xl bg-[#EAF5F8] px-2.5 py-1.5 text-[10px] font-semibold text-[#126F91]">{{ count($parts) }}</span>
                                    <x-lucide name="chevron-down" class="h-4 w-4 text-[#9AAEBC] transition" ::class="open ? 'rotate-180' : ''" />
                                </span>
                            </button>

                            {{-- Giữ NGUYÊN id/lớp mà bộ đọc bám vào (#reader-toc, .oi-toc-item,
                                 data-part-index, data-state-for) — đổi là hỏng phần nhảy chương. --}}
                            <div x-show="open" x-cloak class="max-h-[420px] overflow-y-auto border-t border-[#E5EEF3] p-3" id="reader-toc">
                                @foreach ($parts as $i => $part)
                                    <button type="button" class="oi-toc-item{{ $part['sub'] ? ' is-sub' : '' }}" data-part-index="{{ $i }}">
                                        @if ($part['label'] && ! $part['sub'])
                                            <span class="oi-toc-label">{{ $part['label'] }}</span>
                                        @endif
                                        <span class="oi-toc-title">{{ $part['title'] }}</span>
                                        <span class="oi-toc-state" data-state-for="{{ $i }}">Chưa tải</span>
                                    </button>
                                @endforeach
                            </div>
                        </section>

                        @include('partials.reader-attachments')
                    </aside>
                </div>
            @endif
        </main>
    </div>
@endsection

@push('scripts')
    @include('partials.material-reader-script')

    {{--
      Bộ đọc nhiều tệp. Giữ đúng cách nhúng pdf.js đã kiểm chứng ở trang đọc 1 bài (SỬA 25/8
      (5)): pdfjs-dist 4.0.379 chỉ còn bản ES module (.mjs) nên phải <script type="module"> +
      import() động, không dùng được đuôi .js.

      Khác trang đọc 1 bài:
        · nhiều tệp -> mảng parts, tải LẦN LƯỢT (tải lười theo cuộn), vẽ nối tiếp 1 dải;
        · số trang đếm DỒN cả quyển (trang 41 là trang 41 của cả sách, không phải trang 3 của
          chương 4), nhãn chương hiện cạnh số trang;
        · bấm mục lục: nếu chương đó chưa tải thì tải tuần tự tới đó rồi tự cuộn đến.
    --}}
    <script type="module">
        (async function () {
            var container = document.getElementById('product-pdf-viewer');
            if (!container) {
                return;
            }

            var parts;
            try {
                parts = JSON.parse(container.dataset.parts || '[]');
            } catch (err) {
                parts = [];
            }
            if (!parts.length) {
                return;
            }

            var pdfjsLib;
            try {
                pdfjsLib = await import('https://cdn.jsdelivr.net/npm/pdfjs-dist@4.0.379/legacy/build/pdf.min.mjs');
            } catch (err) {
                console.error('Khong tai duoc thu vien pdf.js:', err);
                container.innerHTML = '<p class="reader-page-error">Không tải được thư viện đọc PDF — kiểm tra kết nối mạng rồi tải lại trang.</p>';
                return;
            }
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.0.379/legacy/build/pdf.worker.min.mjs';

            var watermarkText = container.dataset.watermark || '';
            var scrollBox = document.getElementById('reader-scroll-box');
            var progressBarEl = document.getElementById('reader-progress-bar');
            var pageIndicatorEl = document.getElementById('reader-page-indicator');
            var partLabelEl = document.getElementById('reader-part-label');
            var zoomInBtn = document.getElementById('reader-zoom-in');
            var zoomOutBtn = document.getElementById('reader-zoom-out');
            var zoomLabelEl = document.getElementById('reader-zoom-label');
            var tocEl = document.getElementById('reader-toc');
            // SỬA 3/10 — 4 thẻ của thanh công cụ mới (ô nhảy trang + ô chọn chương).
            var pageInputEl = document.getElementById('reader-page-input');
            var pageTotalEl = document.getElementById('reader-page-total');
            var pageFormEl = document.getElementById('reader-page-form');
            var pageCountEl = document.getElementById('reader-page-count');
            var sectionSelectEl = document.getElementById('material-section-select');

            var SCALE_DEFAULT = 1.4, SCALE_MIN = 0.8, SCALE_MAX = 2.4, SCALE_STEP = 0.2;
            var scale = SCALE_DEFAULT;

            var docs = new Array(parts.length); // tài liệu pdf.js của từng phần (undefined = chưa tải)
            var entries = [];                   // mỗi TRANG của cả quyển: { partIndex, page, wrapper, label, canvas, rendering, failed }
            var nextToLoad = 0;
            var loadedCount = 0;
            var busy = false;
            var failedPart = {};
            var syncTimer = null; // hẹn quét lại vùng nhìn (xem syncVisible())

            function sleep(ms) {
                return new Promise(function (r) { setTimeout(r, ms); });
            }
            async function waitIdle() {
                var guard = 0;
                while (busy && guard < 400) {
                    guard++;
                    await sleep(100);
                }
            }

            // Chặn menu chuột phải + phím tắt lưu/in (hàng rào cho người dùng thường, KHÔNG
            // chặn được chụp màn hình — đã báo trước với khách).
            container.addEventListener('contextmenu', function (e) { e.preventDefault(); });
            document.addEventListener('keydown', function (e) {
                var key = (e.key || '').toLowerCase();
                if ((e.ctrlKey || e.metaKey) && (key === 'p' || key === 's')) {
                    e.preventDefault();
                }
            });

            function buildWatermarkOverlay(width, height) {
                var overlay = document.createElement('div');
                overlay.style.position = 'absolute';
                overlay.style.inset = '0';
                overlay.style.overflow = 'hidden';
                overlay.style.pointerEvents = 'none';
                if (!watermarkText) {
                    return overlay;
                }
                for (var r = 0; r < 6; r++) {
                    for (var c = 0; c < 3; c++) {
                        var span = document.createElement('span');
                        span.textContent = watermarkText;
                        span.style.position = 'absolute';
                        span.style.left = ((c + 0.5) * (width / 3)) + 'px';
                        span.style.top = ((r + 0.5) * (height / 6)) + 'px';
                        span.style.transform = 'translate(-50%, -50%) rotate(-30deg)';
                        span.style.color = 'rgba(120,120,120,0.24)';
                        span.style.fontSize = '12px';
                        span.style.whiteSpace = 'nowrap';
                        overlay.appendChild(span);
                    }
                }
                return overlay;
            }

            function setTocState(index, text, markCurrent) {
                if (!tocEl) {
                    return;
                }
                if (text !== null) {
                    var stateEl = tocEl.querySelector('[data-state-for="' + index + '"]');
                    if (stateEl) {
                        stateEl.textContent = text;
                    }
                }
                if (markCurrent === true) {
                    var items = tocEl.querySelectorAll('.oi-toc-item');
                    for (var i = 0; i < items.length; i++) {
                        items[i].classList.toggle('is-current', String(i) === String(index));
                    }
                    // SỬA 3/10 — cuộn tới chương nào thì ô chọn ở thanh công cụ nhảy theo, để
                    // hai chỗ không bao giờ nói hai chương khác nhau.
                    if (sectionSelectEl && String(sectionSelectEl.value) !== String(index)) {
                        sectionSelectEl.value = String(index);
                    }
                }
            }

            function setZoomLabel() {
                if (zoomLabelEl) {
                    zoomLabelEl.textContent = Math.round((scale / SCALE_DEFAULT) * 100) + '%';
                }
            }

            function setIndicator(current, partIndex) {
                if (pageIndicatorEl) {
                    var suffix = nextToLoad < parts.length ? '+' : '';
                    pageIndicatorEl.textContent = 'Trang ' + current + ' / ' + entries.length + suffix;
                }
                /*
                 * SỬA 3/10 — ô nhập số trang ở thanh công cụ mới. Dải đọc tải lười từng chương
                 * nên tổng số trang CÒN TĂNG; thêm dấu + để người đọc biết đây chưa phải con số
                 * cuối. Không ghi đè lúc người dùng đang gõ dở (ô đang được chọn).
                 */
                var more = nextToLoad < parts.length ? '+' : '';
                if (pageTotalEl) {
                    pageTotalEl.textContent = entries.length + more;
                }
                if (pageInputEl) {
                    pageInputEl.max = String(entries.length);
                    if (document.activeElement !== pageInputEl) {
                        pageInputEl.value = String(current);
                    }
                }
                if (pageCountEl && entries.length > 0) {
                    pageCountEl.textContent = entries.length + more + ' trang PDF';
                    pageCountEl.classList.remove('hidden');
                }
                if (partLabelEl && parts[partIndex]) {
                    var p = parts[partIndex];
                    partLabelEl.textContent = '· ' + (p.label ? p.label + ' — ' : '') + p.title;
                }
            }

            /*
             * Vẽ 1 trang. CHỈ gọi cho trang đang ở gần vùng nhìn — xem syncVisible(): đọc liền
             * mạch cả quyển sách có thể là hàng trăm trang, vẽ hết ra canvas cùng lúc thì mỗi
             * trang tốn vài MB bộ nhớ, đủ làm treo/đóng tab trình duyệt. Trang ở xa chỉ giữ lại
             * cái khung rỗng đúng kích thước (giữ nguyên chiều dài thanh cuộn), vẽ lại khi cuộn tới.
             */
            async function renderEntry(entry) {
                if (entry.canvas || entry.rendering || entry.failed) {
                    return;
                }
                entry.rendering = true;
                try {
                    var vp = entry.page.getViewport({ scale: scale });
                    var canvas = document.createElement('canvas');
                    canvas.width = vp.width;
                    canvas.height = vp.height;
                    entry.wrapper.appendChild(canvas);
                    entry.wrapper.appendChild(buildWatermarkOverlay(vp.width, vp.height));
                    await entry.page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
                    entry.canvas = canvas;
                } catch (err) {
                    // Lỗi 1 trang (font CID thiếu CMap, ảnh không giải mã được, trang quá khổ...)
                    // KHÔNG được làm sập cả dải: đánh dấu trang đó rồi đi tiếp.
                    console.error('Loi hien thi trang:', err);
                    entry.failed = true;
                    entry.wrapper.innerHTML = '';
                } finally {
                    entry.rendering = false;
                }
            }

            function releaseEntry(entry) {
                if (!entry.canvas) {
                    return;
                }
                entry.wrapper.innerHTML = '';
                entry.canvas = null;
            }

            /* Đánh số trang DỒN cả quyển (trang 41 là trang 41 của cả sách, không phải trang 3 của chương 4). */
            function renumber() {
                for (var i = 0; i < entries.length; i++) {
                    entries[i].label.textContent = 'Trang ' + (i + 1);
                }
            }

            /* Thêm 1 phần vào dải: vạch ngăn + khung rỗng cho từng trang (chưa vẽ). */
            async function appendPart(index) {
                var part = parts[index];
                var doc = docs[index];

                var divider = document.createElement('div');
                divider.className = 'oi-part-divider';
                divider.dataset.partAnchor = String(index);
                var lineL = document.createElement('span');
                lineL.className = 'oi-part-line';
                var name = document.createElement('span');
                name.className = 'oi-part-name';
                name.textContent = (part.label ? part.label + ' · ' : '') + part.title;
                var lineR = document.createElement('span');
                lineR.className = 'oi-part-line';
                divider.appendChild(lineL);
                divider.appendChild(name);
                divider.appendChild(lineR);
                container.appendChild(divider);

                for (var i = 1; i <= doc.numPages; i++) {
                    var page;
                    try {
                        page = await doc.getPage(i);
                    } catch (err) {
                        console.error('Khong doc duoc trang', i, part.title, err);
                        continue;
                    }
                    var vp = page.getViewport({ scale: scale });

                    var wrapper = document.createElement('div');
                    wrapper.className = 'reader-page-wrap';
                    wrapper.style.width = vp.width + 'px';
                    wrapper.style.height = vp.height + 'px';
                    container.appendChild(wrapper);

                    var label = document.createElement('p');
                    label.className = 'reader-page-number';
                    label.textContent = 'Trang';
                    container.appendChild(label);

                    entries.push({ partIndex: index, page: page, wrapper: wrapper, label: label, canvas: null, rendering: false, failed: false });
                }

                renumber();
                setTocState(index, doc.numPages + ' trang', false);
            }

            /* Tải 1 phần (fetch + pdf.js) rồi nối vào dải. */
            async function loadNextPart() {
                if (busy || nextToLoad >= parts.length) {
                    return false;
                }
                busy = true;
                var index = nextToLoad;
                var part = parts[index];

                var tail = document.createElement('div');
                tail.className = 'oi-part-loading';
                var spin = document.createElement('span');
                spin.className = 'oi-spin';
                tail.appendChild(spin);
                tail.appendChild(document.createTextNode(' Đang tải ' + (part.label ? part.label + ' · ' : '') + part.title + '…'));
                container.appendChild(tail);
                setTocState(index, 'Đang tải…', false);

                try {
                    var res = await fetch(part.url, { credentials: 'same-origin' });
                    if (!res.ok) {
                        throw new Error('http-' + res.status);
                    }
                    var buf = await res.arrayBuffer();
                    docs[index] = await pdfjsLib.getDocument({
                        data: buf,
                        cMapUrl: 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.0.379/cmaps/',
                        cMapPacked: true,
                        standardFontDataUrl: 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.0.379/standard_fonts/',
                    }).promise;
                    tail.remove();
                    await appendPart(index);
                    loadedCount++;
                } catch (err) {
                    console.error('Khong tai duoc tep cua phan:', part.title, err);
                    tail.remove();
                    failedPart[index] = true;
                    var errBox = document.createElement('p');
                    errBox.className = 'reader-page-error';
                    errBox.textContent = 'Không tải được "' + part.title + '" — bỏ qua phần này, các phần sau vẫn đọc bình thường.';
                    container.appendChild(errBox);
                    setTocState(index, 'Lỗi tải', false);
                }

                nextToLoad = index + 1;
                busy = false;
                syncVisible();
                updateCurrentFromScroll();
                return true;
            }

            /* Tải tuần tự cho tới phần $target (dùng khi bấm mục lục vào chương chưa tải). */
            async function loadUntil(target) {
                while (nextToLoad <= target && nextToLoad < parts.length) {
                    if (busy) {
                        await sleep(120);
                        continue;
                    }
                    await loadNextPart();
                }
            }

            /* Nội dung ngắn hơn khung đọc thì tải tiếp cho đỡ trống (tối đa 3 phần). */
            async function fillViewport() {
                var guard = 0;
                while (scrollBox && nextToLoad < parts.length && guard < 3
                       && scrollBox.scrollHeight <= scrollBox.clientHeight + 200) {
                    guard++;
                    await loadNextPart();
                }
            }

            /* Vẽ các trang gần vùng nhìn, thả canvas của trang đã đi xa. */
            function syncVisible() {
                if (!scrollBox || !entries.length) {
                    return;
                }
                var boxRect = scrollBox.getBoundingClientRect();
                var pad = boxRect.height * 1.5;
                var started = 0;
                var pending = false;
                for (var i = 0; i < entries.length; i++) {
                    var entry = entries[i];
                    var r = entry.wrapper.getBoundingClientRect();
                    var near = r.bottom > boxRect.top - pad && r.top < boxRect.bottom + pad;
                    if (near) {
                        // Mỗi lượt chỉ khởi động vài trang để không dồn hàng chục lệnh vẽ cùng lúc.
                        if (!entry.canvas && !entry.rendering && !entry.failed) {
                            if (started < 4) {
                                started++;
                                renderEntry(entry);
                            } else {
                                pending = true;
                            }
                        }
                    } else {
                        releaseEntry(entry);
                    }
                }

                // Còn trang gần vùng nhìn chưa vẽ -> hẹn quét lại, không đợi lần cuộn tiếp theo.
                if (pending && !syncTimer) {
                    syncTimer = setTimeout(function () {
                        syncTimer = null;
                        syncVisible();
                    }, 260);
                }
            }

            function updateProgress() {
                if (!progressBarEl || !scrollBox) {
                    return;
                }
                var scrollable = scrollBox.scrollHeight - scrollBox.clientHeight;
                var pct = scrollable > 0 ? Math.min(100, Math.max(0, (scrollBox.scrollTop / scrollable) * 100)) : 0;
                // Tiến độ tính trên phần ĐÃ TẢI nên nhân thêm tỉ lệ phần đã tải của cả quyển —
                // cuộn hết phần đã tải mà mới tải 1/3 quyển thì không thể báo 100%.
                var share = parts.length ? (nextToLoad / parts.length) : 1;
                progressBarEl.style.width = (pct * share) + '%';
            }

            function updateCurrentFromScroll() {
                if (!entries.length || !scrollBox) {
                    return;
                }
                // Trang "đang đọc" = trang ĐẦU TIÊN còn phủ qua mốc (mép trên khung + 24px).
                // KHÔNG dùng "trang cuối cùng nằm trên mốc": ở chỗ giáp ranh 2 chương, vạch ngăn
                // chương sau nằm ngay mốc thì trang cuối của chương TRƯỚC vẫn thoả điều kiện đó
                // -> thanh trên ghi sai tên chương ngay sau khi bấm mục lục (đã gặp khi kiểm thử).
                var anchor = scrollBox.getBoundingClientRect().top + 24;
                var current = entries.length, partIndex = entries[entries.length - 1].partIndex;
                for (var i = 0; i < entries.length; i++) {
                    if (entries[i].wrapper.getBoundingClientRect().bottom > anchor) {
                        current = i + 1;
                        partIndex = entries[i].partIndex;
                        break;
                    }
                }
                setIndicator(current, partIndex);
                setTocState(partIndex, null, true);
            }

            var ticking = false;
            var lastHeavy = 0;
            if (scrollBox) {
                scrollBox.addEventListener('scroll', function () {
                    if (ticking) {
                        return;
                    }
                    ticking = true;
                    requestAnimationFrame(function () {
                        updateProgress();
                        ticking = false;

                        // Việc nặng (quét toàn bộ trang để vẽ/thả canvas) giới hạn ~7 lần/giây,
                        // không chạy mỗi khung hình — cả quyển có thể hàng trăm trang.
                        var now = Date.now();
                        if (now - lastHeavy < 140) {
                            return;
                        }
                        lastHeavy = now;
                        updateCurrentFromScroll();
                        syncVisible();

                        if (!busy && nextToLoad < parts.length
                            && scrollBox.scrollTop + scrollBox.clientHeight >= scrollBox.scrollHeight - 1200) {
                            loadNextPart().then(updateProgress);
                        }
                    });
                });
            }

            if (pageFormEl) {
                pageFormEl.addEventListener('submit', function (event) {
                    event.preventDefault();
                    var target = Math.min(entries.length, Math.max(1, Math.trunc(Number(pageInputEl ? pageInputEl.value : 1) || 1)));
                    var entry = entries[target - 1];
                    if (entry && entry.wrapper) {
                        entry.wrapper.scrollIntoView({ block: 'start', behavior: 'smooth' });
                    }
                });
            }

            /*
             * Ô chọn chương: bấm hộ đúng nút trong Mục lục thay vì viết lại phần nhảy chương.
             * Phần đó còn phải TẢI chương chưa tải xong rồi mới cuộn (loadUntil) — chép lại là
             * có ngày hai bản lệch nhau.
             */
            if (sectionSelectEl && tocEl) {
                sectionSelectEl.addEventListener('change', function () {
                    var btn = tocEl.querySelector('.oi-toc-item[data-part-index="' + sectionSelectEl.value + '"]');
                    if (btn) {
                        btn.click();
                    }
                });
            }

            if (tocEl) {
                tocEl.addEventListener('click', async function (e) {
                    var btn = e.target.closest ? e.target.closest('.oi-toc-item') : null;
                    if (!btn) {
                        return;
                    }
                    var index = parseInt(btn.dataset.partIndex, 10);
                    if (isNaN(index)) {
                        return;
                    }
                    await loadUntil(index);
                    if (failedPart[index]) {
                        return;
                    }
                    var anchor = container.querySelector('[data-part-anchor="' + index + '"]');
                    if (anchor) {
                        anchor.scrollIntoView({ block: 'start' });
                        updateCurrentFromScroll();
                        syncVisible();
                    }
                });
            }

            /* Đổi tỉ lệ: tính lại kích thước mọi khung rồi vẽ lại phần đang xem — KHÔNG tải lại mạng. */
            async function applyScale() {
                await waitIdle();
                busy = true;

                var keepIndex = 0;
                var anchorTop = scrollBox ? scrollBox.getBoundingClientRect().top + 24 : 0;
                for (var i = 0; i < entries.length; i++) {
                    if (entries[i].wrapper.getBoundingClientRect().top <= anchorTop) {
                        keepIndex = i;
                    }
                }

                for (var j = 0; j < entries.length; j++) {
                    var entry = entries[j];
                    entry.wrapper.innerHTML = '';
                    entry.canvas = null;
                    entry.failed = false;
                    var vp = entry.page.getViewport({ scale: scale });
                    entry.wrapper.style.width = vp.width + 'px';
                    entry.wrapper.style.height = vp.height + 'px';
                }

                if (entries[keepIndex]) {
                    entries[keepIndex].wrapper.scrollIntoView({ block: 'start' });
                }

                busy = false;
                syncVisible();
                updateProgress();
                updateCurrentFromScroll();
            }

            if (zoomInBtn) {
                zoomInBtn.addEventListener('click', function () {
                    if (scale >= SCALE_MAX) {
                        return;
                    }
                    scale = Math.min(SCALE_MAX, Math.round((scale + SCALE_STEP) * 100) / 100);
                    setZoomLabel();
                    applyScale();
                });
            }
            if (zoomOutBtn) {
                zoomOutBtn.addEventListener('click', function () {
                    if (scale <= SCALE_MIN) {
                        return;
                    }
                    scale = Math.max(SCALE_MIN, Math.round((scale - SCALE_STEP) * 100) / 100);
                    setZoomLabel();
                    applyScale();
                });
            }

            container.innerHTML = '';
            setZoomLabel();
            await loadNextPart();
            await fillViewport();
            syncVisible();
            updateProgress();
            updateCurrentFromScroll();
        })();
    </script>
@endpush
