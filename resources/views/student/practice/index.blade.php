@extends('layouts.student')

@section('title', 'Luyện tập')
@section('page-title', 'Luyện tập')

@section('content')
    @php
        $tab = $tab ?? 'self';
        $tabs = $tabs ?? [];
        $items = $items ?? [];
        $type = $type ?? null;
        $topic = $topic ?? null;
        $filtersApply = $filtersApply ?? false;
        $availableTopics = $availableTopics ?? [];
        // Khoá mới do PracticeService bổ sung 18/9 cho khối "Luyện tập của tôi".
        $counts = $counts ?? ['self' => 0, 'class' => 0, 'assigned' => 0, 'saved' => 0, 'history' => 0];
        $nextDueAt = $nextDueAt ?? null;
        $lastSubmittedAt = $lastSubmittedAt ?? null;
        $quickStartHref = $quickStartHref ?? null;

        // Bản mẫu in số dạng 2 chữ số ("03") — giữ đúng cách trình bày đó.
        $pad2 = fn (int $n) => $n < 10 ? '0'.$n : (string) $n;

        $baseParams = array_filter(['tab' => $tab !== 'self' ? $tab : null]);
        $typeHref = fn (?string $val) => route('student.practice.index', array_filter($baseParams + ['type' => $val, 'topic' => $topic]));
        $topicHref = fn (?string $val) => route('student.practice.index', array_filter($baseParams + ['type' => $type, 'topic' => $val]));

        $chipClass = fn (bool $active) => $active
            ? 'inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full text-[13px] font-medium bg-blue-600 text-white shadow-sm shadow-blue-200 transition'
            : 'inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full text-[13px] font-medium bg-slate-50 text-slate-600 border border-sky-100 hover:border-blue-300 hover:text-blue-600 hover:bg-sky-50 transition';
        $topicChipClass = fn (bool $active) => $active
            ? 'px-3 py-1.5 rounded-full text-xs font-medium bg-slate-800 text-white transition'
            : 'px-3 py-1.5 rounded-full text-xs font-medium bg-white text-slate-500 border border-sky-100 hover:border-slate-400 hover:text-slate-700 transition';

        $typeFilters = [
            ['value' => null, 'label' => 'Tất cả', 'icon' => 'layers'],
            ['value' => 'coding', 'label' => 'Lập trình', 'icon' => 'code-2'],
            ['value' => 'mcq', 'label' => 'Trắc nghiệm', 'icon' => 'text-cursor-input'],
            ['value' => 'fill_blank', 'label' => 'Điền đáp án', 'icon' => 'pencil'],
        ];

        // Bảng màu theo LOẠI ĐỀ (App\Enums\AssessmentType) để mắt phân biệt nhanh Tự luyện/
        // Bài giao/Đề thi/Đề thi đấu trên card — dùng tone có sẵn của <x-ws.icon-tile> (rose/sky/
        // violet/amber/emerald) + class Tailwind ghi trực tiếp dạng chữ (không ghép chuỗi động)
        // để Tailwind quét/build CSS được bình thường.
        $cardAccent = fn (?string $assessmentType) => match ($assessmentType) {
            'assignment' => ['tone' => 'sky', 'bar' => 'from-sky-400 to-sky-300', 'hover' => 'hover:border-sky-200'],
            'exam' => ['tone' => 'amber', 'bar' => 'from-amber-400 to-amber-300', 'hover' => 'hover:border-amber-200'],
            'competition_paper' => ['tone' => 'violet', 'bar' => 'from-violet-400 to-violet-300', 'hover' => 'hover:border-violet-200'],
            default => ['tone' => 'rose', 'bar' => 'from-rose-400 to-rose-300', 'hover' => 'hover:border-blue-200'],
        };
    @endphp

    {{-- ══════════════════════════════════════════════════════════════════════════
         SỬA 18/9 (khách: "làm lại UI trang luyện tập dựa vào source mới copy lại UI,
         đổ dữ liệu database vào trước") — phần HIỂN THỊ của trang được chép lại theo
         bản mẫu education-main/src/components/RoleWorkspace.jsx:
           · <Hero> -> <x-ws.page-header> (component này vốn đã rút gọn từ đúng <Hero> đó)
           · nhánh active === "Luyện tập" -> khối "Luyện tập của tôi" bên dưới:
             CardTitle + 3 ô số liệu + hàng nút, giữ nguyên cỡ chữ/bo góc/bảng màu của mẫu.
         Khác bản mẫu ĐÚNG MỘT CHỖ: mọi con số và dòng ghi chú lấy từ database thật
         (PracticeService) thay vì số minh hoạ 03/08/42 viết cứng trong JSX.

         Các khối đã ẩn theo yêu cầu 24/8 (dải tab, hộp lọc, lưới danh sách đề) vẫn nằm
         nguyên bên dưới dạng ghi chú — KHÔNG mở lại, chờ khách yêu cầu.
         ══════════════════════════════════════════════════════════════════════════ --}}

    <x-ws.page-header title="Luyện tập" icon="pen-line"
                      subtitle="Từng bước rõ ràng, mọi kết quả đều được lưu lại để bạn tiếp tục đúng lúc.">
        <x-slot:actions>
            <a href="{{ route('student.practiceByQuestion.setup') }}"
               class="inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-white px-3 py-2 text-xs font-bold text-blue-700 shadow-sm transition-colors hover:bg-sky-50 lg:min-h-11">
                <x-lucide name="sparkles" class="h-3.5 w-3.5" />Vào luyện tập
            </a>
        </x-slot:actions>
    </x-ws.page-header>

    {{-- ══════ LUYỆN TẬP CỦA TÔI (chép từ bản mẫu) ══════ --}}
    <section class="mt-4 rounded-3xl border border-sky-100 bg-white p-4 shadow-[0_2px_8px_rgba(0,90,180,.04)] sm:p-5">
        {{-- CardTitle của bản mẫu: ô icon bo góc + tiêu đề + link hành động bên phải --}}
        <div class="mb-3 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <span class="rounded-lg bg-blue-50 p-1.5 text-blue-600"><x-lucide name="pen-line" class="h-3.5 w-3.5" /></span>
                <h2 class="text-sm font-bold text-slate-800">Luyện tập của tôi</h2>
            </div>
            <a href="{{ route('practice.index') }}" class="text-xs font-bold text-blue-600 transition hover:underline">
                Xem kho bài <x-lucide name="chevron-right" class="inline h-3 w-3" />
            </a>
        </div>

        <div class="grid gap-3 md:grid-cols-3">
            {{-- 1. Bài được giao — đếm assignment đang mở của các lớp học sinh đang theo học --}}
            <div class="rounded-2xl bg-blue-50 p-4">
                <p class="text-[10px] font-bold uppercase text-blue-600">Bài được giao</p>
                <p class="mt-1 text-2xl font-black text-blue-600">{{ $pad2($counts['assigned']) }}</p>
                <p class="mt-2 text-[10px] text-slate-500">
                    @if ($nextDueAt)
                        {{-- Không nhét chữ tiếng Việt vào chuỗi format của date(): dấu thoát chỉ
                             che được 1 BYTE nên chữ có dấu sẽ vỡ. Tách ra hai lần format cho chắc. --}}
                        Hạn gần nhất: {{ $nextDueAt->format('H:i') }} ngày {{ $nextDueAt->format('d/m') }}
                    @else
                        Chưa có bài nào đang mở
                    @endif
                </p>
            </div>

            {{-- 2. Đã lưu — hệ thống CHƯA có bảng bookmark (xem PracticeService: 'saved' => 0),
                 nên in đúng 0 và nói thẳng là chưa bật, không mượn con số của mục khác. --}}
            <div class="rounded-2xl bg-amber-50 p-4">
                <p class="text-[10px] font-bold uppercase text-amber-600">Đã lưu</p>
                <p class="mt-1 text-2xl font-black text-amber-600">{{ $pad2($counts['saved']) }}</p>
                <p class="mt-2 text-[10px] text-slate-500">Chưa bật tính năng lưu bài</p>
            </div>

            {{-- 3. Lịch sử nộp — đếm attempt đã nộp của chính người đang xem --}}
            <div class="rounded-2xl bg-emerald-50 p-4">
                <p class="text-[10px] font-bold uppercase text-emerald-600">Lịch sử nộp</p>
                <p class="mt-1 text-2xl font-black text-emerald-600">{{ $pad2($counts['history']) }}</p>
                <p class="mt-2 text-[10px] text-slate-500">
                    @if ($lastSubmittedAt)
                        Nộp gần nhất: {{ $lastSubmittedAt->format('H:i d/m/Y') }}
                    @else
                        Bạn chưa nộp bài nào
                    @endif
                </p>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            @if ($quickStartHref)
                <a href="{{ $quickStartHref }}"
                   class="inline-flex min-h-9 items-center gap-1.5 rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-[11px] font-bold text-blue-700 transition hover:border-blue-300 hover:bg-blue-100">
                    <x-lucide name="layers" class="h-3.5 w-3.5" />Làm đề hỗn hợp
                </a>
            @endif
            <span class="px-3 py-2 text-[10px] text-slate-500">Tự luyện · Theo lớp · Bài giao · Đã lưu · Lịch sử</span>
        </div>
    </section>

    {{-- ══════ LỐI VÀO "LUYỆN TẬP THEO CÂU" (giữ nguyên đường đi cũ, chỉ đổi kiểu) ══════ --}}
    <a href="{{ route('student.practiceByQuestion.setup') }}"
       class="group mt-4 mb-6 flex items-center justify-between gap-4 rounded-3xl border border-sky-100 bg-white p-4 shadow-[0_2px_8px_rgba(0,90,180,.04)] transition-all duration-200 hover:border-sky-200 hover:shadow-md lg:p-5">
        <div class="flex items-center gap-3">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-sky-50 text-sky-600"><x-lucide name="sparkles" class="h-5 w-5" /></span>
            <div class="min-w-0">
                <h3 class="text-sm font-bold text-slate-800">Luyện tập theo câu</h3>
                <p class="mt-0.5 text-[11px] text-slate-500">Chọn chuyên đề, luyện từng câu một, biết đúng/sai ngay lập tức.</p>
            </div>
        </div>
        <span class="inline-flex shrink-0 items-center gap-1 text-xs font-bold text-blue-600 transition-all group-hover:gap-2">
            Bắt đầu <x-lucide name="chevron-right" class="h-3.5 w-3.5" />
        </span>
    </a>

    {{-- <x-ws.tabs :tabs="$tabs" /> --}}

    {{-- SỬA 24/8 — khách yêu cầu ẨN hộp lọc "Loại câu hỏi"/"Chuyên đề" ở tab Tự luyện/Theo
         lớp/Bài được giao (lọc DANH SÁCH ĐỀ ở đây, "Chuyên đề" tạm dựa vào QuestionBank::name
         chứ chưa phải Tag thật — xem PracticeService::buildIndexData()). Lý do ẩn: lọc theo
         chuyên đề/dạng câu hỏi THẬT (dùng Tag) đã có sẵn ở lối "Luyện tập theo câu" ngay phía
         trên (CTA 🧠, dẫn vào student.practiceByQuestion.setup — luyện từng câu, bấm Next) nên
         khỏi trùng 2 nơi. CHỈ COMMENT LẠI — controller/service filter theo ?type=&?topic= vẫn
         chạy nguyên, chưa đụng gì, dán lại nguyên khối dưới đây để hiện lại nếu cần dùng tiếp. --}}
    {{--
    @if ($filtersApply)
        <div class="bg-white rounded-3xl border border-sky-100 p-4 lg:p-5 mb-6 space-y-4">
            <div>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Loại câu hỏi</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($typeFilters as $tf)
                        <a href="{{ $typeHref($tf['value']) }}" class="{{ $chipClass($type === $tf['value']) }}">
                            <span><x-lucide :name="$tf['icon']" class="h-3.5 w-3.5" /></span> {{ $tf['label'] }}
                        </a>
                    @endforeach
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full text-[13px] border border-dashed border-sky-100 text-slate-300 bg-slate-50 cursor-not-allowed"
                          title="Chưa có dữ liệu độ khó cho câu hỏi để lọc">
                        <span><x-lucide name="lock" class="h-4 w-4" /></span> Độ khó
                    </span>
                </div>
            </div>

            @if (count($availableTopics) > 0)
                <div class="pt-3 border-t border-slate-100">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Chuyên đề</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ $topicHref(null) }}" class="{{ $topicChipClass($topic === null) }}">Tất cả</a>
                        @foreach ($availableTopics as $t)
                            <a href="{{ $topicHref($t) }}" class="{{ $topicChipClass($topic === $t) }}">{{ $t }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif
    --}}

    {{-- <div class="flex items-center justify-between mb-3">
        <p class="text-[13px] text-slate-400">{{ count($items) }} bài phù hợp</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse ($items as $it)
            @php $accent = $cardAccent($it['type'] ?? null); @endphp
            <a href="{{ $it['takeRoute'] ?? route('student.practice.index') }}"
               class="group relative flex flex-col h-full rounded-3xl bg-white border border-sky-100 p-5 pt-6 overflow-hidden transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5 {{ $accent['hover'] }}">
                <span class="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r {{ $accent['bar'] }}"></span>

                <div class="flex items-start justify-between gap-2 mb-3">
                    <x-ws.icon-tile :emoji="$it['typeIcon'] ?? '📝'" :tone="$accent['tone']" />
                    <x-ws.badge :tone="$it['tone']">{{ $it['status'] }}</x-ws.badge>
                </div>

                <div class="mb-2">
                    <x-ws.badge tone="info">{{ $it['typeLabel'] ?? $it['type'] }}</x-ws.badge>
                </div>

                <h3 class="font-semibold text-slate-800 leading-snug line-clamp-2">{{ $it['title'] }}</h3>
                <p class="text-xs text-slate-400 mt-1.5">
                    {{ $it['source'] }}{{ $it['difficulty'] ? ' · Độ khó: '.$it['difficulty'] : '' }}
                </p>

                @if (! empty($it['topics'] ?? []))
                    <div class="flex flex-wrap gap-1 mt-2">
                        @foreach ($it['topics'] as $t)
                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-500"><x-lucide name="book-open" class="inline h-3.5 w-3.5 shrink-0 align-[-2px]" /> {{ $t }}</span>
                        @endforeach
                    </div>
                @endif

                <div class="mt-auto pt-4 flex items-center justify-end">
                    <span class="inline-flex items-center gap-1 text-[13px] font-medium text-blue-600 group-hover:gap-2 transition-all">
                        {{ $tab === 'history' ? 'Xem kết quả' : 'Làm bài' }}
                        <span aria-hidden="true">→</span>
                    </span>
                </div>
            </a>
        @empty
            <div class="col-span-full">
                <x-ws.empty-state title="Chưa có bài phù hợp bộ lọc" description="Thử bỏ bộ lọc hoặc khám phá thêm bài luyện tập công khai." actionLabel="Khám phá Luyện tập công khai" :actionHref="route('practice.index')" />
            </div>
        @endforelse
    </div> --}}
@endsection
