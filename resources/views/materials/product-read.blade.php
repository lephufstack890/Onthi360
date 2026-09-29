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
        // 3 trạng thái bài tập — dùng y bảng màu của trang đọc 1 bài.
        $statusMeta = [
            'done' => ['Đã hoàn thành', 'border-emerald-200 bg-emerald-50 text-emerald-700', 'check-circle-2'],
            'progress' => ['Đang làm', 'border-amber-200 bg-amber-50 text-amber-700', 'play-circle'],
            'open' => ['Sẵn sàng', 'border-sky-200 bg-sky-50 text-sky-700', 'play-circle'],
        ];
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
    </style>

    <div x-data="{ focus: false, tab: 'toc', q: '', filter: 'all' }" class="reader-ui bg-[#F7F9FB] text-[#466278]">

        {{-- ══════ THANH ĐẦU TRANG ══════ --}}
        <header x-show="! focus"
                class="sticky top-0 z-30 border-b border-[#DFEBF0] bg-white/95 shadow-[0_3px_14px_rgba(45,96,145,0.07)] backdrop-blur-xl">
            <div class="h-[3px] bg-gradient-to-r from-[#123B68] via-[#2D7FA3] to-[#E6B44A]" aria-hidden="true"></div>
            <div class="flex flex-wrap items-center gap-3 py-2.5 sm:py-3">
                <a href="{{ route($libraryRoute) }}" aria-label="Quay lại kho tài liệu"
                   class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-[#DDEAF0] bg-white text-[#466278] transition hover:border-[#9DC8D7] hover:bg-[#F0F8FB] hover:text-[#123B68]">
                    <x-lucide name="arrow-left" class="h-4 w-4" />
                </a>
                <div class="hidden h-6 w-px bg-slate-200 sm:block"></div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1 rounded-full bg-[#EAF5F8] px-2.5 py-1 text-[10px] font-extrabold text-[#126F91]">
                            <x-lucide name="book-open" class="h-3 w-3" />Đang đọc tài liệu
                        </span>
                        <span class="hidden rounded-lg bg-[#F8FBFE] px-2 py-1 font-mono text-[10px] font-bold text-[#61798B] lg:inline-flex">
                            {{ $typeLabels[$product->type->value] ?? '' }}
                        </span>
                    </div>
                    <h1 class="mt-1 truncate text-[14px] font-semibold text-[#123B68] sm:text-base">{{ $product->title }}</h1>
                </div>

                <span class="inline-flex items-center gap-1.5 rounded-full border border-[#B7DDCD] bg-[#DFF2E9] px-2.5 py-1.5 text-[11px] font-semibold text-[#287B5F]">
                    <x-lucide name="check-circle-2" class="h-3 w-3" /><span>{{ count($parts) }} tệp nội dung</span>
                </span>
                @if ($access['owned'] && $access['remainingLabel'])
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-[#F2E1B6] bg-[#FFF7E3] px-2.5 py-1.5 text-[11px] font-semibold text-[#8E6B2E]" title="Thời hạn còn lại">
                        <x-lucide name="clock" class="h-3 w-3" /><span>{{ $access['remainingLabel'] }}</span>
                    </span>
                @endif

                <a href="{{ route($libraryRoute) }}" aria-label="Đóng trình đọc"
                   class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-slate-400 transition hover:bg-[#FFF1F1] hover:text-[#B42318]">
                    <x-lucide name="x" class="h-4 w-4" />
                </a>
            </div>
        </header>

        <main class="py-4 lg:py-6">
            @if (empty($parts))
                <div class="rounded-[28px] border border-[#D5E8ED] bg-white p-8 text-center">
                    <x-ws.empty-state title="Tài liệu này chưa có nội dung đọc"
                                      description="Các chương/phần chưa được tải file PDF lên. Vui lòng liên hệ bộ phận hỗ trợ." />
                </div>
            @else
                <div class="grid items-stretch gap-3 lg:grid-cols-[minmax(0,1.52fr)_minmax(320px,.74fr)]">

                    {{-- ══════ CỘT TRÁI: DẢI PDF LIỀN MẠCH ══════ --}}
                    <section class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-[28px] border border-[#D5E8ED] bg-[#F2F8FA] shadow-[0_7px_26px_rgba(45,96,145,0.055)]">
                        <div class="flex items-center justify-between gap-2 border-b border-[#E5EEF3] px-3 py-2 sm:px-4">
                            <div class="flex min-w-0 items-center gap-2">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-[#E9F7F8] text-[#23869B]"><x-lucide name="file-text" class="h-3.5 w-3.5" /></span>
                                <p class="shrink-0 text-[13px] font-semibold text-[#123B68]">PDF</p>
                                <span class="reader-page-indicator" id="reader-page-indicator">…</span>
                                <span class="hidden truncate text-[11px] font-semibold text-[#61798B] sm:block" id="reader-part-label"></span>
                            </div>

                            <div class="flex shrink-0 items-center gap-1.5">
                                <button type="button" class="reader-toolbar-btn" id="reader-zoom-out" title="Thu nhỏ">−</button>
                                <span class="reader-zoom-label" id="reader-zoom-label">100%</span>
                                <button type="button" class="reader-toolbar-btn" id="reader-zoom-in" title="Phóng to">+</button>

                                <button type="button" @click="focus = ! focus"
                                        class="ml-1 inline-flex items-center gap-1.5 rounded-lg border border-[#DDEAF0] bg-white px-2.5 py-1.5 text-[11px] font-semibold text-[#466278] transition hover:border-[#9DC8D7] hover:bg-[#F0F8FB] hover:text-[#126F91]">
                                    <x-lucide name="maximize-2" class="h-3.5 w-3.5" />
                                    <span x-text="focus ? 'Thoát tập trung' : 'Tập trung'">Tập trung</span>
                                </button>
                            </div>
                        </div>

                        <div class="reader-progress-track">
                            <div class="reader-progress-bar" id="reader-progress-bar"></div>
                        </div>

                        <div class="p-3 sm:p-5">
                            <p class="mb-3 select-none text-center text-xs text-slate-400">
                                <x-lucide name="lock" class="inline h-3.5 w-3.5 shrink-0 align-[-2px]" /> Nội dung chỉ xem trên web — không hỗ trợ tải về hoặc in trực tiếp.
                            </p>

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
                        </div>
                    </section>

                    {{-- ══════ CỘT PHẢI: MỤC LỤC · BÀI TẬP · HỌC LIỆU ══════
                         SỬA 29/9 (2) (khách: "chưa thấy chỗ làm bài tập với học liệu, hiển thị đầy
                         đủ, thiết kế bố cục sao cho hợp lý") — 3 thẻ trong CÙNG 1 cột thay vì xếp
                         dọc chồng nhau: cột phải chỉ cao bằng khung đọc PDF, xếp dọc thì mục lục
                         sách 20 chương đã chiếm hết chỗ, bài tập bị đẩy xuống dưới không ai thấy.
                         Bài tập giữ NGUYÊN bố cục thẻ của trang đọc 1 bài (tìm kiếm + 3 bộ lọc +
                         nút Làm bài) để học sinh không phải học 2 cách trình bày. --}}
                    <aside class="flex min-h-0 flex-col overflow-hidden rounded-[28px] border border-[#D5E8ED] bg-white shadow-[0_7px_26px_rgba(45,96,145,0.055)]">
                        <div class="border-b border-[#E5EEF3] p-3 sm:px-4">
                            <div class="flex gap-1 rounded-xl border border-[#DCE9EE] bg-[#F2F6F8] p-1">
                                @foreach ([['toc', 'Mục lục', 'list', count($parts)], ['ex', 'Bài tập', 'target', count($exercises)], ['media', 'Học liệu', 'library', count($attachments)]] as [$tKey, $tLabel, $tIcon, $tCount])
                                    <button type="button" @click="tab = '{{ $tKey }}'"
                                            class="flex-1 rounded-lg px-2 py-2 text-[10px] font-bold transition"
                                            :class="tab === '{{ $tKey }}' ? 'bg-[#EAF5F8] text-[#126F91] shadow-[0_2px_7px_rgba(64,105,125,0.1)]' : 'text-[#61798B] hover:bg-white hover:text-[#126F91]'">
                                        <x-lucide :name="$tIcon" class="mx-auto h-3.5 w-3.5 sm:mr-1.5 sm:inline" /><span class="hidden sm:inline">{{ $tLabel }}</span>
                                        <span class="ml-0.5">({{ $tCount }})</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- ─── Thẻ 1: MỤC LỤC (giữ nguyên id/lớp mà script bộ đọc bám vào) ─── --}}
                        <div x-show="tab === 'toc'" class="oi-side-scroll p-3" id="reader-toc">
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

                        {{-- ─── Thẻ 2: BÀI TẬP ─── --}}
                        <div x-show="tab === 'ex'" x-cloak class="flex min-h-0 flex-col">
                            @if (count($exercises) > 0)
                                <div class="border-b border-[#E5EEF3] px-3.5 py-3 sm:px-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-2 text-[#126F91]">
                                            <x-lucide name="target" class="h-4 w-4" /><span class="text-xs font-semibold">Tiến độ bài tập</span>
                                        </div>
                                        <span class="rounded-xl bg-[#EAF5F8] px-2.5 py-1.5 text-[10px] font-semibold text-[#126F91]">{{ $doneExercises }}/{{ count($exercises) }}</span>
                                    </div>
                                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-[#EAF0F5]">
                                        <div class="h-full rounded-full bg-gradient-to-r from-[#2F9E72] to-[#68C69A]" style="width: {{ $exercisePercent }}%"></div>
                                    </div>
                                </div>

                                <div class="space-y-2.5 p-3.5 sm:p-4">
                                    <div class="relative">
                                        <x-lucide name="search" class="absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[#9AAEBC]" />
                                        <input x-model="q" placeholder="Tìm bài tập, hashtag..." aria-label="Tìm bài tập"
                                               class="w-full rounded-xl border border-[#DDEAF0] bg-[#F8FAFB] py-2.5 pl-9 pr-3 text-[11px] text-[#183D5E] outline-none transition placeholder:text-[#9AAEBC] focus:border-[#2D7FA3] focus:ring-2 focus:ring-[#DDF1F6]">
                                    </div>

                                    <div class="flex gap-1 rounded-xl border border-[#DCE9EE] bg-[#F2F6F8] p-1">
                                        @foreach ([['all', 'Tất cả', 'filter'], ['todo', 'Chưa xong', 'play-circle'], ['done', 'Đã xong', 'check-circle-2']] as [$fKey, $fLabel, $fIcon])
                                            <button type="button" @click="filter = '{{ $fKey }}'"
                                                    class="flex-1 rounded-lg px-2 py-2 text-[10px] font-bold transition"
                                                    :class="filter === '{{ $fKey }}' ? 'bg-[#EAF5F8] text-[#126F91] shadow-[0_2px_7px_rgba(64,105,125,0.1)]' : 'text-[#61798B] hover:bg-white hover:text-[#126F91]'">
                                                <x-lucide :name="$fIcon" class="mx-auto h-3.5 w-3.5 sm:mr-1.5 sm:inline" /><span class="hidden sm:inline">{{ $fLabel }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="oi-side-scroll space-y-2.5 px-3.5 pb-4 sm:px-4">
                                    @foreach ($exercises as $ex)
                                        @php
                                            [$exStatusLabel, $exStatusClass, $exStatusIcon] = $statusMeta[$ex['status']] ?? $statusMeta['open'];
                                            $exSearch = mb_strtolower($ex['title'].' '.implode(' ', $ex['tags']));
                                        @endphp
                                        <div x-show="(filter === 'all' || (filter === 'done' ? '{{ $ex['status'] }}' === 'done' : '{{ $ex['status'] }}' !== 'done'))
                                                     && (q.trim() === '' || @js($exSearch).includes(q.trim().toLowerCase()))"
                                             class="flex w-full items-stretch gap-2 rounded-2xl border border-[#F0E1BC] bg-[#FFFAF0] p-2.5 text-left transition hover:border-[#E8CF91] hover:bg-[#FFF4D8]">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-start gap-3">
                                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#E9F7F8] text-[#23869B]">
                                                        <x-lucide name="file-text" class="h-4 w-4" />
                                                    </span>
                                                    <div class="min-w-0 flex-1">
                                                        <p class="text-[14px] font-semibold leading-5 text-[#123B68]">{{ $ex['title'] }}</p>
                                                        @if (count($ex['tags']) > 0)
                                                            <p class="mt-1.5 flex flex-wrap gap-1">
                                                                @foreach ($ex['tags'] as $tag)
                                                                    <span class="inline-flex items-center gap-0.5 rounded-full bg-[#FFF7E3] px-1.5 py-0.5 text-[10px] font-medium text-[#806F55]">
                                                                        <x-lucide name="hash" class="h-2.5 w-2.5" />{{ $tag }}
                                                                    </span>
                                                                @endforeach
                                                            </p>
                                                        @endif
                                                        <p class="mt-2 flex flex-wrap items-center gap-2 text-[11px] text-[#61798B]">
                                                            <span>{{ $ex['difficultyLabel'] }}</span>
                                                            <span class="h-1 w-1 rounded-full bg-[#B8C8D3]"></span>
                                                            <span>{{ $ex['points'] }} điểm</span>
                                                        </p>
                                                    </div>
                                                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full border text-[9px] font-extrabold {{ $exStatusClass }}" title="{{ $exStatusLabel }}">
                                                        <x-lucide :name="$exStatusIcon" class="h-3 w-3" />
                                                    </span>
                                                </div>
                                            </div>

                                            @if ($isTeacherView)
                                                {{-- Giáo viên KHÔNG có nút "Làm bài" (đúng như "Tài liệu của tôi" bên giáo
                                                     viên): chỉ xem đề bài, học sinh mới là người làm. --}}
                                                <a href="{{ route('access.resource.exerciseAttachment', [$product->id, $ex['id'], 'statement']) }}"
                                                   target="_blank" rel="noopener" title="Xem đề bài"
                                                   class="inline-flex shrink-0 items-center justify-center gap-1 self-center rounded-xl border border-[#DDEAF0] bg-white px-2.5 py-2 text-[12px] font-semibold text-[#466278] transition hover:border-[#9DC8D7] hover:bg-[#F0F8FB] hover:text-[#126F91]">
                                                    <x-lucide name="file-text" class="h-3.5 w-3.5" /><span>Xem đề</span>
                                                </a>
                                            @else
                                                {{-- Giữ NGUYÊN đường đi cũ của nút Làm bài: POST kèm return_url tương đối
                                                     để làm xong quay lại đúng trang đọc này. --}}
                                                <form method="POST" action="{{ route('student.practiceByQuestion.startExercise', $ex['id']) }}" class="shrink-0 self-center">
                                                    @csrf
                                                    <input type="hidden" name="return_url" value="{{ request()->getRequestUri() }}">
                                                    <button type="submit" title="Làm bài"
                                                            class="inline-flex shrink-0 items-center justify-center gap-1 rounded-xl border border-[#2F9E72] bg-[#2F9E72] px-2.5 py-2 text-[13px] font-semibold text-white transition hover:border-[#278761] hover:bg-[#278761] active:scale-[0.98]">
                                                        <x-lucide name="play-circle" class="h-3.5 w-3.5" /><span>Làm bài</span>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="p-4">
                                    <div class="rounded-2xl border border-dashed border-[#C9DFE8] bg-[#F8FBFE] p-6 text-center text-[11px] text-[#61798B]">
                                        Tài liệu này chưa gắn bài tập nào.
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- ─── Thẻ 3: HỌC LIỆU (audio/ảnh của từng chương + tệp gắn sản phẩm) ─── --}}
                        <div x-show="tab === 'media'" x-cloak class="oi-side-scroll p-3.5 sm:p-4">
                            @forelse ($attachments as $item)
                                <div class="mb-2.5 rounded-2xl border border-[#DDEAF0] bg-[#F8FBFC] p-3">
                                    <div class="flex items-start gap-2">
                                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-[#E9F7F8] text-[#23869B]">
                                            <x-lucide :name="$item['kind'] === 'audio' ? 'volume-2' : ($item['kind'] === 'image' ? 'image' : 'paperclip')" class="h-3.5 w-3.5" />
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-[12.5px] font-semibold text-[#123B68]">{{ $item['title'] }}</p>
                                            @if ($item['chapterTitle'])
                                                <p class="truncate text-[10.5px] text-[#7FA5B8]">{{ $item['chapterTitle'] }}</p>
                                            @endif
                                        </div>
                                    </div>

                                    @if ($item['kind'] === 'audio')
                                        {{-- Nghe ngay trong trang (bài nghe-hiểu), không phải tải về. --}}
                                        <audio controls preload="none" src="{{ $item['url'] }}" class="mt-2 w-full"></audio>
                                    @elseif ($item['kind'] === 'image')
                                        <a href="{{ $item['url'] }}" target="_blank" rel="noopener" class="mt-2 block overflow-hidden rounded-xl border border-[#DDEAF0]">
                                            <img src="{{ $item['url'] }}" alt="{{ $item['title'] }}" loading="lazy" class="w-full">
                                        </a>
                                    @else
                                        <a href="{{ $item['url'] }}" target="_blank" rel="noopener"
                                           class="mt-2 inline-flex items-center gap-1.5 rounded-xl border border-[#DDEAF0] bg-white px-3 py-1.5 text-[11.5px] font-semibold text-[#466278] transition hover:border-[#9DC8D7] hover:bg-[#F0F8FB] hover:text-[#126F91]">
                                            <x-lucide name="download" class="h-3.5 w-3.5" />Mở tệp
                                        </a>
                                    @endif
                                </div>
                            @empty
                                <div class="rounded-2xl border border-dashed border-[#C9DFE8] bg-[#F8FBFE] p-6 text-center text-[11px] text-[#61798B]">
                                    Tài liệu này chưa có học liệu audio/ảnh hay tệp đính kèm nào.
                                </div>
                            @endforelse
                        </div>
                    </aside>
                </div>
            @endif
        </main>
    </div>
@endsection

@push('scripts')
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
