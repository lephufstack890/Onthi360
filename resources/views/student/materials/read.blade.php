{{--
  SỬA 27/8 ("giáo viên đọc tài liệu bị 403" / "xem học liệu đã gắn lớp như nào"): trang này
  giờ dùng CHUNG cho cả student.materials.read và teacher.materials.read (xem
  App\Http\Controllers\Teacher\MaterialController — cùng gọi MaterialReadService::
  buildReadData(), chỉ khác $routePrefix) — $layoutView/$readRoute do service đó truyền vào
  theo đúng vai trò đang gọi, mặc định giữ nguyên 'layouts.student'/'student.materials.read'
  như cũ nếu thiếu (không đổi gì cho luồng học sinh hiện có).
--}}
@extends($layoutView ?? 'layouts.student')

@section('title', $material->title)
@section('page-title', 'Đọc bài')

@section('content')
    @php
        $prev = $prev ?? null;
        $next = $next ?? null;
        $watermarkText = $watermarkText ?? '';
        $readRoute = $readRoute ?? 'student.materials.read';
    @endphp

    {{-- ═══════════════ MÀN ĐỌC TÀI LIỆU ═══════════════
         SỬA 18/9 (khách yêu cầu: "trang đọc tài liệu thì nó sẽ hiển thị UI đó cũng dựa vào
         source mới copy lại UI đó nha") — dựng lại phần vỏ theo
         education-main/src/components/MaterialReaderPage.jsx: thanh đầu trang dính, hai cột
         (PDF bên trái · Bài tập bên phải), thanh công cụ PDF, chế độ Tập trung.

         GIỮ NGUYÊN BỘ MÁY ĐỌC: khối <style> và toàn bộ <script type="module"> pdf.js ở dưới
         không sửa một ký tự. Sáu thẻ mà script bám vào theo id — material-pdf-viewer,
         reader-progress-bar, reader-page-indicator, reader-zoom-in, reader-zoom-out,
         reader-zoom-label — vẫn còn đủ, chỉ đổi chỗ đứng và lớp CSS.

         KHÁC bản mẫu, có chủ ý:
           · bản mẫu dựng trang tài liệu bằng chữ viết sẵn trong mã nguồn; ở đây là PDF THẬT
             của tài liệu, đọc qua pdf.js — nên phần ruột giữ nguyên bộ đọc cũ;
           · bản mẫu in "320 trang" ở thanh đầu; hệ thống không lưu số trang, pdf.js chỉ biết
             sau khi tải xong nên chỗ đó dùng chính ô chỉ số trang của bộ đọc. --}}
    <style>
        @media print {
            body * { visibility: hidden !important; }
        }

        .reader-shell {
            margin: -1rem;
            min-height: calc(100vh - 4rem);
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 55%, #f1f5f9 100%);
            display: flex;
            flex-direction: column;
        }
        @media (min-width: 1024px) {
            .reader-shell { margin: -1.5rem; }
        }

        .reader-topbar {
            position: sticky;
            top: 0;
            z-index: 30;
            background: rgba(255, 255, 255, .92);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-bottom: 1px solid #e2e8f0;
        }
        .reader-progress-track { height: 3px; background: #eef2f7; }
        .reader-progress-bar {
            height: 100%; width: 0%;
            background: linear-gradient(90deg, #fb7185, #e11d48);
            transition: width .12s linear;
        }
        .reader-code-badge {
            display: inline-block;
            font-size: .65rem;
            font-weight: 600;
            letter-spacing: .02em;
            color: #e11d48;
            background: #fff1f2;
            border: 1px solid #ffe4e6;
            border-radius: 999px;
            padding: 2px 9px;
            margin-bottom: 4px;
        }

        .reader-scroll-area { flex: 1; padding: 28px 16px 120px 16px; }

        .reader-page-wrap {
            position: relative;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .05), 0 16px 32px -12px rgba(15, 23, 42, .16);
            margin: 0 auto;
            overflow: hidden;
            opacity: 0;
            transform: translateY(10px);
            animation: reader-page-in .4s ease forwards;
        }
        @keyframes reader-page-in {
            to { opacity: 1; transform: translateY(0); }
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
        }

        .reader-toolbar-dock {
            position: sticky;
            bottom: 18px;
            z-index: 30;
            display: flex;
            justify-content: center;
            pointer-events: none;
        }
        .reader-toolbar {
            pointer-events: auto;
            display: inline-flex;
            align-items: center;
            gap: 2px;
            background: rgba(255, 255, 255, .97);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid #e2e8f0;
            border-radius: 999px;
            box-shadow: 0 10px 30px -8px rgba(15, 23, 42, .22);
            padding: 6px;
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
            text-decoration: none;
            transition: background .15s, color .15s;
        }
        .reader-toolbar-btn:hover { background: #fff1f2; color: #e11d48; }
        .reader-toolbar-btn[aria-disabled="true"] { opacity: .3; pointer-events: none; }
        .reader-toolbar-sep { width: 1px; height: 20px; background: #e2e8f0; margin: 0 5px; }
        .reader-page-indicator {
            font-size: .75rem;
            font-weight: 500;
            color: #475569;
            min-width: 78px;
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
    </style>
    @php
        $access = $access ?? ['owned' => false, 'remainingLabel' => null];
        $exercises = $exercises ?? [];
        $doneExercises = collect($exercises)->where('status', 'done')->count();
        $exercisePercent = count($exercises) > 0 ? (int) round($doneExercises / count($exercises) * 100) : 0;

        // View này dùng CHUNG cho student.materials.read và teacher.materials.read (xem
        // MaterialReadService::buildReadData()). Nút Quay lại/Đóng phải về đúng kho tài liệu của
        // vai trò đang xem, nếu không giáo viên bấm là văng sang route của học sinh -> 403.
        $libraryRoute = str_starts_with($readRoute, 'teacher.') ? 'teacher.library.index' : 'student.library.index';

        $statusMeta = [
            'done' => ['Đã hoàn thành', 'border-emerald-200 bg-emerald-50 text-emerald-700', 'check-circle-2'],
            'progress' => ['Đang làm', 'border-amber-200 bg-amber-50 text-amber-700', 'play-circle'],
            'open' => ['Sẵn sàng', 'border-sky-200 bg-sky-50 text-sky-700', 'play-circle'],
        ];
    @endphp

    <div x-data="{ focus: false, q: '', filter: 'all' }" class="reader-ui bg-[#F7F9FB] text-[#466278]">

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
                        <span class="hidden rounded-lg bg-[#F8FBFE] px-2 py-1 font-mono text-[10px] font-bold text-[#61798B] lg:inline-flex">{{ $material->code ?: '#'.$material->id }}</span>
                    </div>
                    <h1 class="mt-1 truncate text-[14px] font-semibold text-[#123B68] sm:text-base">{{ $material->title }}</h1>
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

        <main class="py-4 lg:py-6">
            <div class="grid items-stretch gap-3 lg:grid-cols-[minmax(0,1.52fr)_minmax(320px,.74fr)]">

                {{-- ══════ CỘT TRÁI: PDF ══════ --}}
                <section class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-[28px] border border-[#D5E8ED] bg-[#F2F8FA] shadow-[0_7px_26px_rgba(45,96,145,0.055)]">
                    <div class="flex items-center justify-between gap-2 border-b border-[#E5EEF3] px-3 py-2 sm:px-4">
                        <div class="flex min-w-0 items-center gap-2">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-[#E9F7F8] text-[#23869B]"><x-lucide name="file-text" class="h-3.5 w-3.5" /></span>
                            <p class="shrink-0 text-[13px] font-semibold text-[#123B68]">PDF</p>
                            {{-- Ô chỉ số trang của bộ đọc (id giữ nguyên cho script pdf.js). --}}
                            <span class="reader-page-indicator" id="reader-page-indicator">…</span>
                        </div>

                        <div class="flex shrink-0 items-center gap-1.5">
                            {{-- Thu/phóng: giữ nguyên 3 thẻ mà script pdf.js bám vào. --}}
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

                        {{-- SỬA 18/9 (khách: "chỗ hiển thị pdf dài quá thì cho scroll") — vùng đọc có
                             THANH CUỘN RIÊNG, chép đúng lớp của bản mẫu (MaterialReaderPage.jsx:
                             material-pdf-scroll + h-[min(760px,calc(100dvh-185px))]). Trước đây cả
                             trang cuộn nên cột Bài tập bên phải bị kéo đi mất. --}}
                        <div id="reader-scroll-box" role="region" tabindex="0" aria-label="Vùng đọc PDF"
                             class="material-pdf-scroll h-[min(760px,calc(100dvh-185px))] min-h-[520px] overflow-y-auto rounded-2xl border border-[#D6E2EA] bg-[#EAF4F8] p-2 sm:min-h-[680px] sm:p-3">
                        <div id="material-pdf-viewer" data-pdf-url="{{ $pdfUrl }}" data-watermark="{{ $watermarkText }}" class="select-none">
                            <div class="flex items-center justify-center py-16 text-[13px] text-slate-400">
                                <span class="mr-2 inline-block h-5 w-5 animate-spin rounded-full border-2 border-blue-200 border-t-blue-600"></span>
                                Đang tải nội dung…
                            </div>
                        </div>
                        </div>
                    </div>

                    {{-- Điều hướng BÀI trước/sau trong cùng sản phẩm — giữ nguyên đường đi cũ. --}}
                    @if ($prev || $next)
                        <div class="flex items-center justify-between gap-2 border-t border-[#E5EEF3] bg-white px-3 py-2.5 sm:px-4">
                            @if ($prev)
                                <a href="{{ route($readRoute, $prev->id) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-[#DDEAF0] bg-white px-3 py-1.5 text-[12px] font-semibold text-[#466278] transition hover:border-[#9DC8D7] hover:bg-[#F0F8FB] hover:text-[#126F91]">
                                    <x-lucide name="chevron-left" class="h-3.5 w-3.5" />Bài trước
                                </a>
                            @else
                                <span></span>
                            @endif
                            @if ($next)
                                <a href="{{ route($readRoute, $next->id) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-[#DDEAF0] bg-white px-3 py-1.5 text-[12px] font-semibold text-[#466278] transition hover:border-[#9DC8D7] hover:bg-[#F0F8FB] hover:text-[#126F91]">
                                    Bài sau<x-lucide name="chevron-right" class="h-3.5 w-3.5" />
                                </a>
                            @endif
                        </div>
                    @endif
                </section>

                {{-- ══════ CỘT PHẢI: BÀI TẬP ══════ --}}
                <aside class="flex h-full flex-col lg:sticky" :class="focus ? 'lg:top-4' : 'lg:top-[76px]'">
                    <section class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-[28px] border border-[#D5EAD9] bg-[#F1FAF5] shadow-[0_7px_26px_rgba(45,96,145,0.055)]">
                        <div class="border-b border-[#E5EEF3] px-3 py-3 sm:px-4">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2 text-[#126F91]" title="Bài tập gắn với tài liệu">
                                    <x-lucide name="target" class="h-4 w-4" /><span class="text-xs font-semibold">Bài tập</span>
                                </div>
                                <span class="rounded-xl bg-[#EAF5F8] px-2.5 py-1.5 text-[10px] font-semibold text-[#126F91]" title="Tiến độ hoàn thành">{{ $doneExercises }}/{{ count($exercises) }}</span>
                            </div>
                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-[#EAF0F5]">
                                <div class="h-full rounded-full bg-gradient-to-r from-[#2F9E72] to-[#68C69A]" style="width: {{ $exercisePercent }}%"></div>
                            </div>
                        </div>

                        <div class="flex min-h-0 flex-1 flex-col space-y-2.5 p-3.5 sm:p-4">
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

                            <div class="min-h-0 flex-1 space-y-2.5 overflow-y-auto pr-0.5">
                                @forelse ($exercises as $ex)
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

                                        {{-- Giữ NGUYÊN đường đi cũ của nút Làm bài ở "Tài liệu của tôi":
                                             POST kèm return_url tương đối để làm xong quay lại đúng trang này. --}}
                                        <form method="POST" action="{{ route('student.practiceByQuestion.startExercise', $ex['id']) }}" class="shrink-0 self-center">
                                            @csrf
                                            <input type="hidden" name="return_url" value="{{ request()->getRequestUri() }}">
                                            <button type="submit" title="Làm bài"
                                                    class="inline-flex shrink-0 items-center justify-center gap-1 rounded-xl border border-[#2F9E72] bg-[#2F9E72] px-2.5 py-2 text-[13px] font-semibold text-white transition hover:border-[#278761] hover:bg-[#278761] active:scale-[0.98]">
                                                <x-lucide name="play-circle" class="h-3.5 w-3.5" /><span>Làm bài</span>
                                            </button>
                                        </form>
                                    </div>
                                @empty
                                    <div class="rounded-2xl border border-dashed border-[#C9DFE8] bg-[#F8FBFE] p-6 text-center text-[11px] text-[#61798B]">
                                        Tài liệu này chưa gắn bài tập nào.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </section>
                </aside>
            </div>
        </main>
    </div>
@endsection

@push('scripts')
    {{--
      SỬA 25/8 (5) — "không tải được bộ đọc nội dung" (KHÔNG PHẢI lỗi trang lẻ đã sửa ở SỬA
      25/8 (3), mà pdf.js CHƯA HỀ tải được — đã xác minh trực tiếp bằng `npm pack
      pdfjs-dist@4.0.379`: bản 4.0.379 KHÔNG CÒN file .js (UMD/gán window.pdfjsLib) nào nữa —
      kể cả thư mục legacy/build/ cũng chỉ có .mjs (ES module): legacy/build/pdf.min.mjs,
      legacy/build/pdf.worker.min.mjs. SỬA 25/8 (4) trước đó đổi đúng CDN (jsdelivr) nhưng vẫn
      trỏ nhầm đuôi .js (không tồn tại) nên vẫn 404 — trình duyệt nhận về trang lỗi 404 với
      Content-Type không phải JS, bị "X-Content-Type-Options: nosniff" chặn thực thi.
      Fix ĐÚNG: dùng `<script type="module">` + `import()` động trỏ thẳng vào file .mjs thật —
      cách dùng CHÍNH THỨC cho pdf.js từ v4.x khi nhúng qua CDN không qua bundler.

      SỬA 25/8 (6) — thêm cho giao diện mới: theo dõi cuộn trang để cập nhật thanh tiến độ
      đọc + "Trang X / Y" ở thanh công cụ nổi, và 2 nút phóng to/thu nhỏ (render lại đúng
      trang đang xem ở tỉ lệ mới, không nhảy về đầu tài liệu).
    --}}
    <script type="module">
        (async function () {
            var container = document.getElementById('material-pdf-viewer');
            if (!container) {
                return;
            }

            var pdfjsLib;
            try {
                pdfjsLib = await import('https://cdn.jsdelivr.net/npm/pdfjs-dist@4.0.379/legacy/build/pdf.min.mjs');
            } catch (err) {
                console.error('Không tải được thư viện pdf.js:', err);
                container.innerHTML = '<p class="text-center text-[13px] text-blue-500 py-10">Không tải được thư viện đọc PDF — vui lòng kiểm tra kết nối mạng rồi tải lại trang.</p>';
                return;
            }

            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.0.379/legacy/build/pdf.worker.min.mjs';

            var pdfUrl = container.dataset.pdfUrl;
            var watermarkText = container.dataset.watermark || '';

            var progressBarEl = document.getElementById('reader-progress-bar');
            var pageIndicatorEl = document.getElementById('reader-page-indicator');
            var zoomInBtn = document.getElementById('reader-zoom-in');
            var zoomOutBtn = document.getElementById('reader-zoom-out');
            var zoomLabelEl = document.getElementById('reader-zoom-label');

            var SCALE_DEFAULT = 1.4, SCALE_MIN = 0.8, SCALE_MAX = 2.4, SCALE_STEP = 0.2;
            var scale = SCALE_DEFAULT;
            var pdfDoc = null;
            var totalPages = 0;
            var currentPage = 1;
            var pageWrappers = [];

            // Chặn menu chuột phải (Lưu ảnh/Save as...) và các phím tắt tải/in phổ biến. Đây
            // là hàng rào cho người dùng thông thường — KHÔNG chặn được người biết dùng công cụ
            // lập trình viên của trình duyệt, và KHÔNG chặn được chụp màn hình dưới mọi hình
            // thức (giới hạn chung của mọi trình duyệt/hệ điều hành, đã báo trước với khách).
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

                var cols = 3;
                var rows = 6;
                for (var r = 0; r < rows; r++) {
                    for (var c = 0; c < cols; c++) {
                        var span = document.createElement('span');
                        span.textContent = watermarkText;
                        span.style.position = 'absolute';
                        span.style.left = ((c + 0.5) * (width / cols)) + 'px';
                        span.style.top = ((r + 0.5) * (height / rows)) + 'px';
                        span.style.transform = 'translate(-50%, -50%) rotate(-30deg)';
                        span.style.color = 'rgba(120,120,120,0.24)';
                        span.style.fontSize = '12px';
                        span.style.whiteSpace = 'nowrap';
                        overlay.appendChild(span);
                    }
                }

                return overlay;
            }

            function setZoomLabel() {
                if (zoomLabelEl) {
                    zoomLabelEl.textContent = Math.round((scale / SCALE_DEFAULT) * 100) + '%';
                }
            }

            function setPageIndicator(current) {
                if (pageIndicatorEl && totalPages) {
                    pageIndicatorEl.textContent = 'Trang ' + current + ' / ' + totalPages;
                }
            }

            // SỬA 18/9 — vùng đọc PDF giờ có thanh cuộn RIÊNG (#reader-scroll-box) thay vì cuộn
            // cả trang, nên thanh tiến độ và số trang phải bám theo khung đó. Không có khung
            // (bố cục cũ) thì vẫn rơi về cuộn cả trang như trước.
            var scrollBox = document.getElementById('reader-scroll-box');

            function updateReadingProgress() {
                if (!progressBarEl) {
                    return;
                }
                var scrollTop, scrollable;
                if (scrollBox) {
                    scrollTop = scrollBox.scrollTop;
                    scrollable = scrollBox.scrollHeight - scrollBox.clientHeight;
                } else {
                    var doc = document.documentElement;
                    scrollTop = window.scrollY;
                    scrollable = doc.scrollHeight - window.innerHeight;
                }
                var pct = scrollable > 0 ? Math.min(100, Math.max(0, (scrollTop / scrollable) * 100)) : 0;
                progressBarEl.style.width = pct + '%';
            }

            function updateCurrentPageFromScroll() {
                if (!pageWrappers.length) {
                    return;
                }
                var current = 1;
                // Mốc so sánh là MÉP TRÊN của khung cuộn (không còn là mép trên cửa sổ).
                var anchor = scrollBox ? scrollBox.getBoundingClientRect().top + 24 : 160;
                for (var i = 0; i < pageWrappers.length; i++) {
                    if (pageWrappers[i].getBoundingClientRect().top <= anchor) {
                        current = i + 1;
                    }
                }
                currentPage = current;
                setPageIndicator(current);
            }

            var scrollTicking = false;
            (scrollBox || window).addEventListener('scroll', function () {
                if (scrollTicking) {
                    return;
                }
                scrollTicking = true;
                requestAnimationFrame(function () {
                    updateReadingProgress();
                    updateCurrentPageFromScroll();
                    scrollTicking = false;
                });
            });

            // SỬA 25/8 (3) — "không tải được toàn bộ nội dung đọc": trước đây renderNext()
            // KHÔNG có .catch() ở cả pdf.getPage() lẫn page.render() — hễ 1 TRANG BẤT KỲ lỗi
            // (vd font nhúng dạng CID cần dữ liệu CMap ngoài mà trước đây chưa cấu hình, ảnh
            // trong trang không giải mã được, kích cỡ trang vượt giới hạn canvas của trình
            // duyệt...) thì promise bị reject ÂM THẦM, renderNext(pageNum + 1) KHÔNG BAO GIỜ
            // được gọi tiếp — toàn bộ các trang SAU trang lỗi biến mất trắng, không có thông
            // báo gì. Fix: bắt lỗi ở TỪNG trang, hiện placeholder cho đúng trang đó rồi VẪN
            // tiếp tục renderNext() sang trang kế.
            function renderAllPages(pdf, scrollToPage) {
                container.innerHTML = '';
                pageWrappers = [];
                totalPages = pdf.numPages;
                setPageIndicator(scrollToPage || currentPage);

                var renderNext = function (pageNum) {
                    if (pageNum > pdf.numPages) {
                        return;
                    }

                    var renderPageError = function (err) {
                        console.error('Lỗi hiển thị trang ' + pageNum + ':', err);

                        var errBox = document.createElement('p');
                        errBox.className = 'reader-page-error';
                        errBox.style.maxWidth = '640px';
                        errBox.textContent = 'Không hiển thị được trang ' + pageNum + ' — đã bỏ qua, tiếp tục các trang khác.';
                        container.appendChild(errBox);

                        renderNext(pageNum + 1);
                    };

                    pdf.getPage(pageNum).then(function (page) {
                        var viewport = page.getViewport({ scale: scale });

                        var wrapper = document.createElement('div');
                        wrapper.className = 'reader-page-wrap';
                        wrapper.style.width = viewport.width + 'px';
                        wrapper.style.animationDelay = (Math.min(pageNum, 6) * 0.03) + 's';

                        var canvas = document.createElement('canvas');
                        canvas.width = viewport.width;
                        canvas.height = viewport.height;
                        wrapper.appendChild(canvas);
                        wrapper.appendChild(buildWatermarkOverlay(viewport.width, viewport.height));
                        container.appendChild(wrapper);
                        pageWrappers.push(wrapper);

                        var pageLabel = document.createElement('p');
                        pageLabel.className = 'reader-page-number';
                        pageLabel.textContent = pageNum + ' / ' + pdf.numPages;
                        container.appendChild(pageLabel);

                        var ctx = canvas.getContext('2d');
                        page.render({ canvasContext: ctx, viewport: viewport }).promise.then(function () {
                            if (scrollToPage && pageNum === scrollToPage) {
                                wrapper.scrollIntoView({ block: 'start' });
                            }
                            renderNext(pageNum + 1);
                        }, renderPageError);
                    }, renderPageError);
                };

                renderNext(1);
            }

            if (zoomInBtn) {
                zoomInBtn.addEventListener('click', function () {
                    if (!pdfDoc || scale >= SCALE_MAX) {
                        return;
                    }
                    scale = Math.min(SCALE_MAX, Math.round((scale + SCALE_STEP) * 100) / 100);
                    setZoomLabel();
                    renderAllPages(pdfDoc, currentPage);
                });
            }
            if (zoomOutBtn) {
                zoomOutBtn.addEventListener('click', function () {
                    if (!pdfDoc || scale <= SCALE_MIN) {
                        return;
                    }
                    scale = Math.max(SCALE_MIN, Math.round((scale - SCALE_STEP) * 100) / 100);
                    setZoomLabel();
                    renderAllPages(pdfDoc, currentPage);
                });
            }

            fetch(pdfUrl, { credentials: 'same-origin' })
                .then(function (res) {
                    if (!res.ok) {
                        throw new Error('fetch-failed');
                    }
                    return res.arrayBuffer();
                })
                .then(function (buf) {
                    return pdfjsLib.getDocument({
                        data: buf,
                        cMapUrl: 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.0.379/cmaps/',
                        cMapPacked: true,
                        standardFontDataUrl: 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.0.379/standard_fonts/',
                    }).promise;
                })
                .then(function (pdf) {
                    pdfDoc = pdf;
                    renderAllPages(pdf);
                })
                .catch(function (err) {
                    console.error('Không tải được tài liệu PDF:', err);
                    container.innerHTML = '<p class="text-center text-[13px] text-blue-500 py-10">Không tải được nội dung bài học. Vui lòng thử lại sau.</p>';
                });
        })();
    </script>
@endpush
