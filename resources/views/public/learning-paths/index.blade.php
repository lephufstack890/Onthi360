@extends('layouts.guest')

@section('title', 'Lộ trình học Tin học — Ôn Thi 360')
@section('meta-description', 'Các lộ trình học Tin học của Ôn Thi 360: mỗi lộ trình gồm nhiều bậc, mỗi bậc là một khoá học có số buổi và mục tiêu rõ ràng. Lọc theo khối lớp để tìm lộ trình phù hợp.')

@section('content')
{{-- ═══════════════ [PATHS] DANH SÁCH LỘ TRÌNH (B3) ═══════════════
     Dữ liệu từ App\Services\Public\LearningPathService::indexData().
     Lọc chạy ngay tại chỗ bằng Alpine (không tải lại trang); đường dẫn ?khoi=&ngon-ngu=
     vẫn hoạt động để chia sẻ link đã lọc và để Google đọc được. --}}
@php
    $paths = $paths ?? [];
    $grades = $grades ?? [];
    $languages = $languages ?? [];
    $showLanguage = $showLanguage ?? false;
    $activeGrade = $activeGrade ?? null;
    $activeLanguage = $activeLanguage ?? null;
@endphp

{{-- Khung bao chuẩn của mọi trang công khai (cùng lớp với Lớp học, Tài liệu, Cuộc thi...):
     giới hạn bề ngang và chừa lề hai bên theo từng cỡ màn hình. layouts.guest KHÔNG tự chừa
     lề, mỗi trang phải tự bọc — thiếu là nội dung dính sát mép màn hình. --}}
<div class="max-w-[1780px] w-full mx-auto px-3 sm:px-5 lg:px-6 2xl:px-10 py-3 sm:py-5">
<div class="flex flex-col gap-5"
     x-data="{
        rows: {{ Js::from($paths) }},
        grade: {{ Js::from($activeGrade === null ? 'all' : (string) $activeGrade) }},
        language: {{ Js::from($activeLanguage ?? 'all') }},
        get filtered() {
            return this.rows.filter((p) => {
                const okGrade = this.grade === 'all' || (Number(this.grade) >= p.gradeFrom && Number(this.grade) <= p.gradeTo);
                const okLang = this.language === 'all' || p.language === this.language;
                return okGrade && okLang;
            });
        },
     }">

    {{-- ══════ [PATHS-01] HERO ══════ --}}
    <div class="relative overflow-hidden rounded-3xl border border-sky-200/80 bg-gradient-to-r from-[#0B3C78] via-[#0050A0] to-[#0284C7] p-5 text-white shadow-[0_10px_35px_rgba(0,100,220,0.08)] sm:p-6 lg:p-7">
        <div class="relative z-10 flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-2xl">
                <div class="mb-3 inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-white/15 px-3 py-1 text-[11px] font-bold text-sky-50 backdrop-blur">
                    <x-lucide name="route" class="h-3.5 w-3.5" />
                    <span>Học theo bậc, không học lẻ từng lớp</span>
                </div>

                <h1 class="text-xl font-black leading-tight tracking-tight text-white sm:text-2xl">Lộ trình học</h1>

                <p class="mt-2 max-w-xl text-xs leading-5 text-sky-100 sm:text-sm sm:leading-6">
                    Mỗi lộ trình chia thành nhiều bậc nối tiếp nhau. Mỗi bậc là một khoá học có số buổi và mục tiêu rõ ràng,
                    học xong bậc này mới sang bậc kế tiếp — biết trước con mình đang ở đâu và còn bao xa nữa tới đích.
                </p>
            </div>

            <div class="relative z-10 w-full rounded-2xl border border-white/20 bg-slate-950/10 p-3 text-center shadow-lg backdrop-blur-md lg:w-64 lg:shrink-0">
                <p class="text-[11px] font-bold uppercase tracking-[.08em] text-sky-100">Đang mở</p>
                <p class="mt-1 text-3xl font-black text-white">{{ count($paths) }}</p>
                <p class="mt-0.5 text-[11px] text-sky-100">lộ trình</p>
            </div>
        </div>
    </div>

    {{-- ══════ [PATHS-02] BỘ LỌC ══════ --}}
    @if (count($paths) > 0)
        <div class="flex flex-col gap-3 rounded-3xl border border-[#DDEAF0] bg-white p-3.5 shadow-[0_2px_10px_rgba(28,91,121,0.04)] lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar">
                <span class="type-label shrink-0 text-[#536D86]">Khối lớp:</span>
                <button type="button" @click="grade = 'all'" :aria-pressed="grade === 'all'"
                        class="min-h-9 whitespace-nowrap rounded-xl border px-3 py-1 text-[11px] font-bold transition-all"
                        :class="grade === 'all' ? 'border-[#126F91] bg-[#126F91] text-white shadow-[0_4px_10px_rgba(18,111,145,0.18)]' : 'border-transparent bg-[#F5F8FA] text-[#536D86] hover:border-[#C9DFE8] hover:bg-white'">Tất cả</button>
                @foreach ($grades as $g)
                    <button type="button" @click="grade = @js((string) $g)" :aria-pressed="grade === @js((string) $g)"
                            class="min-h-9 whitespace-nowrap rounded-xl border px-3 py-1 text-[11px] font-bold transition-all"
                            :class="grade === @js((string) $g) ? 'border-[#126F91] bg-[#126F91] text-white shadow-[0_4px_10px_rgba(18,111,145,0.18)]' : 'border-transparent bg-[#F5F8FA] text-[#536D86] hover:border-[#C9DFE8] hover:bg-white'">Lớp {{ $g }}</button>
                @endforeach
            </div>

            @if ($showLanguage && count($languages) > 1)
                <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar">
                    <span class="type-label shrink-0 text-[#536D86]">Ngôn ngữ:</span>
                    <button type="button" @click="language = 'all'"
                            class="min-h-9 whitespace-nowrap rounded-xl border px-3 py-1 text-[11px] font-bold transition-all"
                            :class="language === 'all' ? 'border-[#126F91] bg-[#126F91] text-white' : 'border-transparent bg-[#F5F8FA] text-[#536D86] hover:border-[#C9DFE8] hover:bg-white'">Tất cả</button>
                    @foreach ($languages as $value => $label)
                        <button type="button" @click="language = @js($value)"
                                class="min-h-9 whitespace-nowrap rounded-xl border px-3 py-1 text-[11px] font-bold transition-all"
                                :class="language === @js($value) ? 'border-[#126F91] bg-[#126F91] text-white' : 'border-transparent bg-[#F5F8FA] text-[#536D86] hover:border-[#C9DFE8] hover:bg-white'">{{ $label }}</button>
                    @endforeach
                </div>
            @endif

            <p class="flex shrink-0 items-center gap-1.5 text-[11px] font-medium text-[#71869A]">
                <x-lucide name="filter" class="h-3.5 w-3.5 text-[#2D7FA3]" />
                <span>Có <b class="text-[#536D86]" x-text="filtered.length"></b> lộ trình phù hợp</span>
            </p>
        </div>
    @endif

    {{-- ══════ [PATHS-03] DANH SÁCH ══════ --}}
    @if (count($paths) === 0)
        <div class="rounded-3xl border border-dashed border-[#CFE3EC] bg-white p-10 text-center">
            <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-[#EAF5F8] text-[#2D7FA3]">
                <x-lucide name="route" class="h-7 w-7" />
            </span>
            <p class="mt-3 text-sm font-bold text-[#123B68]">Chưa có lộ trình nào được đăng</p>
            <p class="mt-1 text-xs text-[#71869A]">Trong lúc chờ, bạn có thể xem danh sách lớp học đang mở.</p>
            <a href="{{ route('courses.index') }}" class="mt-4 inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-[#126F91] px-4 text-xs font-bold text-white transition-colors hover:bg-[#0F5E7C]">
                Xem lớp học <x-lucide name="chevron-right" class="h-3.5 w-3.5" />
            </a>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 2xl:grid-cols-3">
            @foreach ($paths as $p)
                <article x-show="filtered.some((f) => f.id === {{ $p['id'] }})" x-cloak
                         class="group flex h-full flex-col overflow-hidden rounded-3xl border border-[#DDEAF0] bg-white shadow-[0_2px_10px_rgba(28,91,121,0.04)] transition-all hover:-translate-y-0.5 hover:border-[#B8DFE8] hover:shadow-[0_8px_24px_rgba(28,91,121,0.08)]">

                    {{-- Ảnh lộ trình do quản trị tải lên; chưa có thì dựng dải màu các bậc. --}}
                    @if ($p['coverUrl'])
                        <img src="{{ $p['coverUrl'] }}" alt="{{ $p['title'] }}" loading="lazy" decoding="async"
                             class="h-36 w-full object-cover">
                    @else
                        <div class="flex h-36 w-full items-end gap-1 bg-gradient-to-br from-[#F3FAFC] to-[#E7F3F7] p-4">
                            @foreach (array_slice(\App\Support\LearningPathPalette::ramp(), 0, max(1, min(6, $p['stepCount']))) as $i => $tone)
                                <span class="flex-1 rounded-t-lg" style="background: {{ $tone['solid'] }}; height: {{ 26 + $i * 14 }}%"></span>
                            @endforeach
                        </div>
                    @endif

                    <div class="flex min-w-0 flex-1 flex-col p-4">
                        @if ($p['eyebrow'])
                            <p class="text-[10px] font-black uppercase tracking-[.09em] text-[#2D7FA3]">{{ $p['eyebrow'] }}</p>
                        @endif

                        <h2 class="mt-1 text-[15px] font-black leading-snug text-[#123B68]">
                            <a href="{{ $p['href'] }}" class="transition-colors hover:text-[#126F91]">{{ $p['title'] }}</a>
                        </h2>

                        @if ($p['subtitle'])
                            <p class="mt-1 line-clamp-2 text-[11.5px] leading-relaxed text-[#536D86]">{{ $p['subtitle'] }}</p>
                        @endif

                        <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
                            <span class="rounded-lg border border-[#CDE8EC] bg-[#E9F7F8] px-2 py-0.5 text-[10.5px] font-bold text-[#23869B]">{{ $p['gradeLabel'] }}</span>
                            <span class="rounded-lg border border-[#DDEAF0] bg-[#F5F8FA] px-2 py-0.5 text-[10.5px] font-bold text-[#536D86]">{{ $p['stepCount'] }} bậc</span>
                            @if ($p['totalSessions'] > 0)
                                <span class="rounded-lg border border-[#DDEAF0] bg-[#F5F8FA] px-2 py-0.5 text-[10.5px] font-bold text-[#536D86]">{{ $p['totalSessions'] }} buổi</span>
                            @endif
                            @if ($showLanguage && $p['language'])
                                <span class="rounded-lg border border-[#E4DBF7] bg-[#F4F0FF] px-2 py-0.5 text-[10.5px] font-bold text-[#6A53A8]">{{ $p['languageLabel'] }}</span>
                            @endif
                        </div>

                        <div class="mt-3 rounded-xl border border-[#F2E4BD] bg-[#FFFBEF] px-2.5 py-2">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-[#A98436]">Mục tiêu đích</p>
                            <p class="mt-0.5 text-[11.5px] font-bold leading-snug text-[#765C18]">{{ $p['goal'] }}</p>
                        </div>

                        <div class="mt-auto pt-3">
                            <a href="{{ $p['href'] }}"
                               class="flex min-h-10 w-full items-center justify-center gap-1.5 rounded-xl bg-[#126F91] px-4 text-xs font-bold text-white transition-all hover:bg-[#0F5E7C] active:scale-[.98]">
                                Xem lộ trình <x-lucide name="chevron-right" class="h-3.5 w-3.5" />
                            </a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <div x-show="filtered.length === 0" x-cloak
             class="rounded-3xl border border-dashed border-[#CFE3EC] bg-white p-10 text-center">
            <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-[#EAF5F8] text-[#2D7FA3]">
                <x-lucide name="filter" class="h-7 w-7" />
            </span>
            <p class="mt-3 text-sm font-bold text-[#123B68]">Chưa có lộ trình cho lựa chọn này</p>
            <p class="mt-1 text-xs text-[#71869A]">Thử bỏ bớt bộ lọc để xem các lộ trình khác.</p>
            <button type="button" @click="grade = 'all'; language = 'all'"
                    class="mt-4 inline-flex min-h-10 items-center gap-1.5 rounded-xl border border-[#C9DFE8] bg-white px-4 text-xs font-bold text-[#126F91] transition-colors hover:bg-[#F2F8F9]">
                Xem tất cả lộ trình
            </button>
        </div>
    @endif
</div>
</div>
@endsection
