{{--
    KHÔNG GIAN THI của học sinh — dựng theo ĐÚNG bản mẫu khách gửi
    (education-main29/src/components/ContestRoomPage.jsx): hero → 4 ô thống kê → "Hành trình
    cuộc thi" → "Bảng xếp hạng vòng thi hiện tại" + nút vào thi → cột phải "Kết quả cá nhân",
    thẻ teal "Kết quả của bạn", "Thông tin vòng thi", "Thông báo ban tổ chức".

    Bản mẫu chạy trên dữ liệu bịa; mọi con số ở đây do
    App\Services\Student\CompetitionService::roomData() lấy từ DB — xem docblock của hàm đó để
    biết từng mảng giả đã được thay bằng bảng thật nào.

    Quyền vào trang: roomData() tự chặn 403 nếu chưa được BTC duyệt. View KHÔNG tự kiểm tra lại.
--}}
@extends('layouts.student')

@section('title', 'Không gian thi · '.$competitionTitle)
@section('page-title', 'Không gian thi')

@section('content')
@php
    // Giá trị phòng hờ: trang này luôn được roomData() cấp đủ, nhưng nếu ai đó render view từ
    // chỗ khác thì vẫn ra trang rỗng có ý nghĩa thay vì lỗi "Undefined variable".
    $rounds = $rounds ?? [];
    $selectedRound = $selectedRound ?? null;
    $problems = $problems ?? [];
    $leaderboard = $leaderboard ?? [];
    $studentStats = $studentStats ?? [];
    $announcement = $announcement ?? ['title' => 'Cập nhật vòng thi', 'body' => '—', 'meta' => ''];
    $review = $selectedRound['review'] ?? ['status' => 'Chưa có kết quả', 'note' => '', 'tone' => 'locked', 'score' => '—', 'rank' => '—'];

    $toneClass = [
        'success' => 'border-emerald-100 bg-emerald-50 text-emerald-700',
        'current' => 'border-sky-100 bg-sky-50 text-sky-700',
        'review' => 'border-[#2F8F83] bg-[#2F8F83] text-white shadow-sm',
        'locked' => 'border-slate-100 bg-slate-50 text-slate-500',
    ];

    // Nút "Vào thi": chỉ mở khi vòng đang diễn ra, có đề, và học sinh chưa nộp bài vòng đó.
    // Chặn thật vẫn nằm ở AttemptService::competitionEntryDecision() — đây chỉ là giao diện.
    $canEnterExam = $selectedRound !== null
        && $selectedRound['status'] === 'current'
        && $selectedRound['assessmentId'] !== null
        && ! $selectedRound['attempted'];
    $examActionLabel = $selectedRound === null
        ? 'Chưa có vòng thi'
        : ($selectedRound['status'] === 'upcoming'
            ? 'Chờ đến giờ thi'
            : ($selectedRound['attempted'] ? 'Bạn đã nộp bài vòng này' : ($canEnterExam ? 'Vào thi' : 'Đã kết thúc')));
@endphp

<div class="mx-auto max-w-[1240px] space-y-3.5">

    {{-- ══════ 1. HERO ══════ --}}
    <header class="relative overflow-hidden rounded-2xl border border-[#0B4E6B] bg-gradient-to-r from-[#064C99] via-[#0066CC] to-[#0891B2] px-4 py-3 shadow-[0_2px_10px_rgba(18,59,104,0.08)] sm:px-5 sm:py-3.5">
        <img src="{{ $image }}" alt="" aria-hidden="true" decoding="async"
             class="pointer-events-none absolute inset-0 h-full w-full object-cover object-right opacity-30">
        <div class="relative z-10">
            <a href="{{ $backUrl }}"
               class="mb-4 inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-3 py-2 text-[11px] font-bold text-white transition hover:bg-white/20">
                <x-lucide name="arrow-left" class="h-3.5 w-3.5" />Rời không gian thi
            </a>

            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                <div class="min-w-0 max-w-3xl">
                    <div class="flex flex-wrap items-center gap-1.5 text-[10px] font-bold">
                        <span class="rounded-full bg-amber-300 px-2.5 py-1 text-amber-950">Không gian thi</span>
                        <span class="rounded-full border border-white/20 bg-white/10 px-2.5 py-1 text-sky-50">{{ $editionLabel }}</span>
                        <span class="rounded-full border border-emerald-200/30 bg-emerald-300/15 px-2.5 py-1 text-emerald-50">{{ $statusLabel }}</span>
                    </div>
                    <h1 class="mt-3 text-xl font-black leading-tight text-white sm:text-2xl">{{ $competitionTitle }}</h1>
                    <p class="mt-1.5 text-[13px] leading-5 text-sky-100">Theo dõi hành trình thi, kết quả từng vòng và bài đang chờ bạn hoàn thành.</p>
                </div>

                <div class="grid grid-cols-2 gap-2 sm:min-w-[330px]">
                    <div class="rounded-2xl border border-[#74CBBD] bg-[#07545D] p-3 shadow-[0_7px_18px_rgba(2,39,47,0.28)]">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-[#D7FFF5]">Vòng đang xem</p>
                        <p class="mt-1 truncate text-sm font-black text-white">{{ $selectedRound['shortLabel'] ?? '—' }}</p>
                        <p class="mt-0.5 text-[10px] font-semibold text-[#F0FFFB]">{{ $selectedRound['date'] ?? 'Chưa cập nhật' }}</p>
                    </div>
                    <div class="rounded-2xl border border-[#74CBBD] bg-[#07545D] p-3 shadow-[0_7px_18px_rgba(2,39,47,0.28)]">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-[#D7FFF5]">Thời lượng</p>
                        <p class="mt-1 text-sm font-black text-white">
                            {{ ($selectedRound['durationMinutes'] ?? null) !== null ? $selectedRound['durationMinutes'].' phút' : 'Không giới hạn' }}
                        </p>
                        <p class="mt-0.5 text-[10px] font-semibold text-[#F0FFFB]">{{ count($problems) }} câu hỏi</p>
                    </div>
                </div>
            </div>
        </div>
    </header>

    {{-- ══════ 2. 4 Ô THỐNG KÊ ══════ --}}
    <section class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <article class="group relative overflow-hidden rounded-2xl border border-sky-100 bg-gradient-to-br from-white via-white to-[#EAF5F8] p-3.5 shadow-[0_4px_14px_rgba(28,91,121,0.06)] transition duration-200 hover:-translate-y-0.5 hover:border-sky-200 sm:p-4">
            <span class="pointer-events-none absolute -right-7 -top-7 h-20 w-20 rounded-full bg-sky-100/70 transition duration-200 group-hover:scale-110"></span>
            <div class="relative z-10 flex items-start justify-between gap-2">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[.08em] text-[#61798B]">Tiến độ cuộc thi</p>
                    <p class="mt-1.5 text-2xl font-semibold leading-none tracking-tight text-[#123B68]">{{ $completedRounds }}<span class="text-base text-[#8AA0AF]">/{{ count($rounds) }}</span></p>
                    <p class="mt-1.5 text-[11px] text-[#61798B]">vòng đã kết thúc</p>
                </div>
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#DDF2F5] text-[#126F91] shadow-sm"><x-lucide name="target" class="h-4 w-4" /></span>
            </div>
        </article>

        <article class="group relative overflow-hidden rounded-2xl border border-amber-100 bg-gradient-to-br from-white via-white to-[#FFF8E7] p-3.5 shadow-[0_4px_14px_rgba(156,112,32,0.06)] transition duration-200 hover:-translate-y-0.5 hover:border-amber-200 sm:p-4">
            <span class="pointer-events-none absolute -right-7 -top-7 h-20 w-20 rounded-full bg-amber-100/80 transition duration-200 group-hover:scale-110"></span>
            <div class="relative z-10 flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-[.08em] text-[#8F7A58]">Điểm vòng này</p>
                    <p class="mt-1.5 truncate text-2xl font-semibold leading-none tracking-tight text-[#123B68]">{{ $review['score'] }}</p>
                    <p class="mt-1.5 truncate text-[11px] text-[#8F7A58]">{{ $review['status'] }}</p>
                </div>
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#FFF0C7] text-[#B57A2B] shadow-sm"><x-lucide name="medal" class="h-4 w-4" /></span>
            </div>
        </article>

        <article class="group relative overflow-hidden rounded-2xl border border-emerald-100 bg-gradient-to-br from-white via-white to-[#EDF8F3] p-3.5 shadow-[0_4px_14px_rgba(47,143,131,0.06)] transition duration-200 hover:-translate-y-0.5 hover:border-emerald-200 sm:p-4">
            <span class="pointer-events-none absolute -right-7 -top-7 h-20 w-20 rounded-full bg-emerald-100/80 transition duration-200 group-hover:scale-110"></span>
            <div class="relative z-10 flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-[.08em] text-[#5E7B6E]">Xếp hạng</p>
                    <p class="mt-1.5 truncate text-2xl font-semibold leading-none tracking-tight text-[#123B68]">{{ $review['rank'] }}</p>
                    <p class="mt-1.5 text-[11px] text-[#5E7B6E]">theo vòng đang xem</p>
                </div>
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#DFF2E9] text-[#2F8F6F] shadow-sm"><x-lucide name="trophy" class="h-4 w-4" /></span>
            </div>
        </article>

        <article class="group relative overflow-hidden rounded-2xl border border-violet-100 bg-gradient-to-br from-white via-white to-[#F4F0FA] p-3.5 shadow-[0_4px_14px_rgba(118,87,165,0.06)] transition duration-200 hover:-translate-y-0.5 hover:border-violet-200 sm:p-4">
            <span class="pointer-events-none absolute -right-7 -top-7 h-20 w-20 rounded-full bg-violet-100/80 transition duration-200 group-hover:scale-110"></span>
            <div class="relative z-10 flex items-start justify-between gap-2">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[.08em] text-[#7657A5]">Thí sinh</p>
                    <p class="mt-1.5 text-2xl font-semibold leading-none tracking-tight text-[#123B68]">{{ number_format($selectedRound['participants'] ?? 0, 0, ',', '.') }}</p>
                    <p class="mt-1.5 text-[11px] text-[#7657A5]">có kết quả ở vòng này</p>
                </div>
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#EEE8F7] text-[#7657A5] shadow-sm"><x-lucide name="users" class="h-4 w-4" /></span>
            </div>
        </article>
    </section>

    <div class="grid items-start gap-4 xl:grid-cols-[minmax(0,1.7fr)_minmax(320px,.65fr)]">
        <div class="min-w-0 space-y-4">

            {{-- ══════ 3. HÀNH TRÌNH CUỘC THI ══════ --}}
            <section class="rounded-2xl border border-sky-100 bg-white p-3 shadow-[0_3px_12px_rgba(28,91,121,0.05)] sm:p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[.12em] text-[#2D7FA3]">Hành trình cuộc thi</p>
                        <h2 class="mt-1 text-sm font-black text-[#123B68]">Các vòng thi của bạn</h2>
                    </div>
                    <span class="rounded-full bg-sky-50 px-2.5 py-1 text-[10px] font-bold text-[#126F91]">{{ $completedRounds }}/{{ count($rounds) }} hoàn tất</span>
                </div>

                <div class="mt-4 grid gap-2.5 sm:grid-cols-2 xl:grid-cols-3" aria-label="Các vòng thi của bạn">
                    @forelse ($rounds as $r)
                        @php $active = $selectedRound !== null && $r['id'] === $selectedRound['id']; @endphp
                        {{-- Chọn vòng = đổi URL (?vong=) chứ không dùng JS: dữ liệu của vòng (bảng xếp
                             hạng, điểm từng câu) lấy từ DB nên phải tải lại ở máy chủ. --}}
                        <a href="{{ route('student.competitions.room', ['competition' => $competition->id, 'vong' => $r['id']]) }}"
                           @if ($active) aria-current="true" @endif
                           class="relative flex min-w-0 flex-col rounded-xl border p-3 text-left transition {{ $active ? 'border-[#126F91] bg-[#F1FAFC] shadow-[0_4px_14px_rgba(18,111,145,0.12)]' : 'border-sky-100 bg-white hover:border-sky-200 hover:bg-sky-50/40' }}">
                            <div class="flex items-start justify-between gap-2">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-[11px] font-black {{ $r['status'] === 'completed' ? 'bg-emerald-500 text-white' : ($r['status'] === 'current' ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-400') }}">
                                    @if ($r['status'] === 'completed')
                                        <x-lucide name="check-circle-2" class="h-4 w-4" />
                                    @else
                                        {{ $r['order'] }}
                                    @endif
                                </span>
                                <span class="rounded-full border px-2 py-1 text-[10px] font-semibold {{ $toneClass[$r['review']['tone']] ?? $toneClass['locked'] }}">{{ $r['review']['status'] }}</span>
                            </div>
                            <div class="mt-2 flex items-center justify-between gap-2">
                                <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Vòng {{ $r['order'] }}</span>
                                <span class="shrink-0 text-[10px] text-slate-400">{{ $r['date'] }}</span>
                            </div>
                            <h3 class="mt-1.5 line-clamp-2 text-[12px] font-bold text-[#123B68]">{{ $r['label'] }}</h3>
                            <p class="mt-1 line-clamp-2 text-[11px] leading-5 text-slate-500">{{ $r['review']['note'] }}</p>
                            <span class="mt-2.5 inline-flex items-center justify-center gap-1.5 rounded-lg px-2 py-1.5 text-[12px] font-semibold {{ $active ? 'bg-[#126F91] text-white' : 'bg-slate-50 text-slate-600' }}">
                                {{ $active ? 'Đang xem' : $r['review']['score'] }}
                            </span>
                        </a>
                    @empty
                        <div class="sm:col-span-2 xl:col-span-3 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-3 py-8 text-center text-[11px] text-slate-500">
                            Cuộc thi này chưa được ban tổ chức gắn vòng thi nào.
                        </div>
                    @endforelse
                </div>
            </section>

            {{-- ══════ 4. BẢNG XẾP HẠNG VÒNG ĐANG XEM ══════ --}}
            <section class="rounded-2xl border border-sky-100 bg-white p-3 shadow-[0_3px_12px_rgba(28,91,121,0.05)] sm:p-4">
                <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-[.12em] text-[#2D7FA3]">Bảng xếp hạng vòng thi</p>
                        <h2 class="mt-1 truncate text-sm font-black text-[#123B68]">{{ $selectedRound['label'] ?? 'Chưa có vòng thi' }}</h2>
                    </div>
                    <span class="shrink-0 rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-bold text-amber-700">
                        {{ $selectedRound === null ? '—' : ($selectedRound['status'] === 'current' ? 'Đang diễn ra' : ($selectedRound['status'] === 'upcoming' ? 'Chưa mở' : 'Đã kết thúc')) }}
                    </span>
                </div>

                <div class="mt-4 rounded-2xl border border-sky-100 bg-gradient-to-b from-[#F3FAFC] to-white p-3.5 shadow-[0_8px_24px_rgba(28,91,121,0.08)] sm:p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="inline-flex items-center gap-1.5 rounded-full bg-sky-100 px-2.5 py-1 text-[10px] font-bold text-[#126F91]">
                                <x-lucide name="bar-chart-3" class="h-3.5 w-3.5" />BXH theo vòng
                            </div>
                            <h3 class="mt-2 text-lg font-black text-[#123B68]">{{ $selectedRound['label'] ?? 'Vòng thi' }}</h3>
                            <p class="mt-1 text-[11px] text-slate-500">Thứ hạng và điểm từng câu được tính riêng cho vòng này.</p>
                        </div>
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <div class="rounded-lg border border-[#D7E6E9] bg-white px-2.5 py-1.5 text-right shadow-sm">
                                <p class="text-[8px] font-bold uppercase tracking-wide text-slate-400">Thí sinh</p>
                                <p class="mt-0.5 text-[13px] font-black text-[#3D7886]">{{ number_format($selectedRound['participants'] ?? 0, 0, ',', '.') }}</p>
                            </div>
                            <a href="{{ $leaderboardUrl }}"
                               class="inline-flex min-h-10 items-center gap-1.5 rounded-xl border border-[#CFE3DC] bg-[#EAF5F1] px-3 py-2 text-[11px] font-semibold text-[#397C68] transition hover:border-[#A9D3C4] hover:bg-[#DFF0E9]">
                                <x-lucide name="bar-chart-3" class="h-3.5 w-3.5" />Bảng xếp hạng đầy đủ
                            </a>
                        </div>
                    </div>

                    @if ($leaderboard !== [])
                        <div class="mt-4 overflow-x-auto rounded-xl border border-[#D7E6E9] bg-[#F8FBFB]">
                            <table class="w-full min-w-[720px] border-collapse text-left">
                                <thead>
                                    <tr class="border-b border-[#D7E6E9] bg-[#EDF5F4] text-[10px] font-bold uppercase tracking-wide text-[#607783]">
                                        <th scope="col" class="w-14 px-3 py-2.5 text-center">Hạng</th>
                                        <th scope="col" class="min-w-[190px] px-3 py-2.5">Thí sinh</th>
                                        @foreach ($problems as $p)
                                            <th scope="col" class="min-w-[64px] px-3 py-2.5 text-center" title="{{ $p['title'] }}">Câu {{ $p['order'] }}</th>
                                        @endforeach
                                        <th scope="col" class="min-w-[92px] px-3 py-2.5 text-right">Tổng điểm</th>
                                        <th scope="col" class="min-w-[180px] px-3 py-2.5">Ghi chú</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#E3ECEE]">
                                    @foreach ($leaderboard as $i => $row)
                                        <tr class="{{ $row['isViewer'] ? 'bg-[#EAF6FB]' : ($i === 0 ? 'bg-[#FFF8E8]' : ($i === 1 ? 'bg-[#F4F6F7]' : ($i === 2 ? 'bg-[#FFF5EC]' : 'bg-[#F8FBFB]'))) }}">
                                            <td class="px-3 py-3 text-center">
                                                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full text-[11px] font-bold {{ $i === 0 ? 'bg-[#E8C76A] text-[#6F5312]' : ($i === 1 ? 'bg-[#C9D0D4] text-[#566168]' : ($i === 2 ? 'bg-[#D9A06B] text-[#70451F]' : 'bg-[#E8F0F1] text-[#607783]')) }}">{{ $row['rank'] }}</span>
                                            </td>
                                            <td class="px-3 py-3">
                                                <div class="flex items-center gap-2.5">
                                                    <img src="{{ $row['avatar'] }}" alt="" loading="lazy" decoding="async"
                                                         class="h-8 w-8 shrink-0 rounded-full border-2 object-cover shadow-sm {{ $i === 0 ? 'border-[#E8C76A]' : ($i === 1 ? 'border-[#C9D0D4]' : ($i === 2 ? 'border-[#D9A06B]' : 'border-[#D7E6E9]')) }}">
                                                    <div class="min-w-0">
                                                        <p class="truncate text-[12px] font-semibold text-[#2B4B5F]">{{ $row['name'] }}@if ($row['isViewer']) <span class="font-black text-[#126F91]">· Bạn</span>@endif</p>
                                                        <p class="mt-0.5 truncate text-[10px] text-[#82919A]">{{ $row['note'] }}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            @foreach ($row['problemScores'] as $ps)
                                                <td class="px-3 py-3 text-center text-[11px] font-semibold text-[#5E7480]">
                                                    {{ $ps === null ? '—' : rtrim(rtrim(number_format($ps, 2, ',', '.'), '0'), ',') }}
                                                </td>
                                            @endforeach
                                            <td class="px-3 py-3 text-right text-[12px] font-bold text-[#3D7886]">{{ rtrim(rtrim(number_format($row['score'], 2, ',', '.'), '0'), ',') }} điểm</td>
                                            <td class="px-3 py-3 align-top">
                                                <span class="inline-flex rounded-full border px-2 py-1 text-[10px] font-semibold {{ $row['auditFlagged'] ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-emerald-100 bg-emerald-50 text-emerald-700' }}">{{ $row['auditLabel'] }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="mt-2 text-[10px] leading-4 text-slate-400">
                            Tên thí sinh khác được ẩn danh theo quy định bảo vệ dữ liệu người học — bạn luôn nhìn thấy dòng của chính mình.
                        </p>
                    @else
                        <div class="mt-4 rounded-xl border border-dashed border-slate-200 bg-[#F8FBFB] px-3 py-8 text-center text-[11px] leading-5 text-slate-500">
                            {{ ($selectedRound['status'] ?? 'upcoming') === 'upcoming'
                                ? 'Vòng thi chưa diễn ra — bảng xếp hạng mở sau khi có kết quả.'
                                : 'Bảng xếp hạng của vòng này chưa được chấm xong.' }}
                        </div>
                    @endif

                    {{-- Dải "Kết quả của bạn" --}}
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-sky-100 bg-white px-3 py-2.5">
                        <div class="flex items-center gap-2">
                            <span class="grid h-8 w-8 place-items-center rounded-lg bg-[#E1F2F5] text-[#126F91]"><x-lucide name="trophy" class="h-4 w-4" /></span>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Kết quả của bạn</p>
                                <p class="mt-0.5 text-[11px] font-black text-[#123B68]">{{ $review['status'] }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 text-right">
                            <div>
                                <p class="text-[9px] text-slate-400">Điểm</p>
                                <p class="text-[12px] font-black text-amber-700">{{ $review['score'] }}</p>
                            </div>
                            <div>
                                <p class="text-[9px] text-slate-400">Hạng</p>
                                <p class="text-[12px] font-black text-[#126F91]">{{ $review['rank'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Nút vào thi --}}
                <div class="mt-5 rounded-2xl border border-sky-100 bg-gradient-to-r from-[#F1FAFC] via-white to-[#F5FBF8] p-4 shadow-[0_6px_18px_rgba(28,91,121,0.07)] sm:p-5">
                    @if ($canEnterExam)
                        {{-- SỬA 19/9 (2) — vào PHÒNG THI RIÊNG của cuộc thi (student.competitions.exam)
                             thay vì màn làm bài chung. Truyền id vòng thi chứ không phải id đề: một đề
                             có thể được dùng lại ở nhiều vòng/nhiều cuộc thi, chỉ id vòng mới nói được
                             đang thi cái gì. --}}
                        <a href="{{ route('student.competitions.exam', ['competition' => $competition->id, 'exam' => $selectedRound['id']]) }}"
                           class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#126F91] px-5 py-3 text-[12px] font-bold text-white shadow-sm transition hover:bg-[#0F607E]">
                            <x-lucide name="play" class="h-4 w-4" />{{ $examActionLabel }}
                        </a>
                    @else
                        <span class="inline-flex min-h-12 w-full cursor-not-allowed items-center justify-center gap-2 rounded-xl bg-slate-100 px-5 py-3 text-[12px] font-bold text-slate-400">
                            <x-lucide name="lock-keyhole" class="h-4 w-4" />{{ $examActionLabel }}
                        </span>
                    @endif
                </div>
            </section>
        </div>

        {{-- ══════ 5. CỘT PHẢI ══════ --}}
        <aside class="min-w-0 space-y-4">

            {{-- Kết quả cá nhân --}}
            <section class="rounded-2xl border border-[#D8E7E7] bg-[#F8FBFB] p-3 shadow-[0_3px_12px_rgba(28,91,121,0.05)] sm:p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[.12em] text-[#2D7C80]">Kết quả cá nhân</p>
                        <h2 class="mt-1 text-sm font-black text-[#244B61]">Thống kê của bạn</h2>
                    </div>
                    <span class="shrink-0 rounded-xl border border-[#CFE3DC] bg-white px-2.5 py-2 text-right">
                        <span class="block text-[9px] text-slate-400">Vòng đang xem</span>
                        <span class="mt-0.5 block max-w-[110px] truncate text-[11px] font-black text-[#2F8F83]">{{ $selectedRound['shortLabel'] ?? '—' }}</span>
                    </span>
                </div>

                <div class="mt-3 grid grid-cols-2 gap-2">
                    @foreach ($studentStats as $stat)
                        <article class="rounded-xl border p-2.5 {{ $stat['tone'] === 'teal' ? 'border-[#CBE5DE] bg-[#EEF9F5]' : ($stat['tone'] === 'amber' ? 'border-[#EADDBB] bg-[#FFF9EC]' : ($stat['tone'] === 'blue' ? 'border-[#D2E6EC] bg-[#F0F8FA]' : 'border-[#D9E5E1] bg-[#F3F9F6]')) }}">
                            <p class="text-[9px] font-bold uppercase tracking-wide text-[#78909A]">{{ $stat['label'] }}</p>
                            <p class="mt-1 text-base font-black {{ $stat['tone'] === 'amber' ? 'text-[#9A7428]' : 'text-[#2F7D73]' }}">{{ $stat['value'] }}</p>
                            <p class="mt-0.5 text-[10px] text-[#81929A]">{{ $stat['meta'] }}</p>
                        </article>
                    @endforeach
                </div>

                <div class="mt-3 flex items-center gap-2 rounded-xl border border-[#D7E7E8] bg-white/75 px-3 py-2.5 text-[11px] text-slate-600">
                    <x-lucide name="info" class="h-4 w-4 shrink-0 text-[#2D7C80]" />
                    <span>{{ $review['note'] !== '' ? $review['note'] : 'Kết quả và nhận xét sẽ được cập nhật trong hồ sơ dự thi.' }}</span>
                </div>
            </section>

            {{-- Thẻ điểm teal --}}
            <section class="rounded-2xl border border-[#1E6659] bg-[#176B5D] p-4 text-white shadow-[0_7px_18px_rgba(23,107,93,0.18)]">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-[.12em] text-[#BDEDE2]">Kết quả của bạn</p>
                        <h2 class="mt-1 text-sm font-black text-white">{{ ($selectedRound['status'] ?? '') === 'current' ? 'Điểm hiện tại' : 'Điểm sau khi nộp bài' }}</h2>
                    </div>
                    <span class="shrink-0 rounded-xl border border-white/20 bg-white/10 px-2.5 py-2 text-right">
                        <span class="block text-[9px] text-[#BDEDE2]">Vòng thi</span>
                        <span class="mt-0.5 block max-w-[110px] truncate text-[11px] font-black text-white">{{ $selectedRound['shortLabel'] ?? '—' }}</span>
                    </span>
                </div>
                <div class="mt-3 flex items-end justify-between gap-4">
                    <div>
                        <p class="text-[10px] text-[#DDF8F2]">Điểm</p>
                        <p class="mt-0.5 text-2xl font-black tracking-tight text-white">{{ $review['score'] }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] text-[#DDF8F2]">Thứ hạng</p>
                        <p class="mt-0.5 text-sm font-black text-white">{{ $review['rank'] }}</p>
                    </div>
                </div>
                <div class="mt-3 flex items-center justify-between gap-2 border-t border-white/15 pt-2.5 text-[10px]">
                    <span class="text-[#DDF8F2]">{{ $review['status'] }}</span>
                    <span class="font-semibold text-[#BDEDE2]">Theo vòng đang xem</span>
                </div>
            </section>

            {{-- Thông tin vòng thi --}}
            <section class="rounded-2xl border border-sky-100 bg-white p-4">
                <div class="flex items-center gap-2">
                    <x-lucide name="calendar-days" class="h-4 w-4 shrink-0 text-[#126F91]" />
                    <h2 class="text-sm font-black text-[#123B68]">Thông tin vòng thi</h2>
                </div>
                <div class="mt-3 space-y-2 text-[11px]">
                    <div class="flex items-start justify-between gap-3">
                        <span class="shrink-0 text-slate-400">Thời gian</span>
                        <b class="text-right text-slate-700">{{ $selectedRound['timeRange'] ?? 'Chưa xếp lịch' }}</b>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-slate-400">Số câu hỏi</span>
                        <b class="text-right text-slate-700">{{ count($problems) }} câu</b>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-slate-400">Thí sinh có kết quả</span>
                        <b class="text-right text-slate-700">{{ number_format($selectedRound['participants'] ?? 0, 0, ',', '.') }}</b>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <span class="shrink-0 text-slate-400">Công bố kết quả</span>
                        <b class="max-w-[180px] text-right text-amber-700">{{ $awardLabel }}</b>
                    </div>
                </div>
                <div class="mt-3 rounded-xl border border-amber-100 bg-amber-50 px-3 py-2.5 text-[10px] leading-4 text-amber-800">
                    <x-lucide name="clock-3" class="mr-1 inline h-3.5 w-3.5" />{{ $hasActiveRound ? 'Hãy hoàn thành vòng đang mở để bảo toàn thứ hạng.' : 'Kết quả và nhận xét được lưu lại trong hồ sơ cuộc thi.' }}
                </div>
            </section>

            {{-- Thông báo ban tổ chức --}}
            <section class="rounded-2xl border border-[#EAD9A8] bg-[#FFF8E8] p-4 shadow-[0_4px_14px_rgba(164,126,44,0.08)]">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#E9C86A] text-[#6F5312]"><x-lucide name="megaphone" class="h-4 w-4" /></span>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[.1em] text-[#9A7928]">Từ ban tổ chức</p>
                            <h2 class="mt-0.5 text-sm font-black text-[#6F5312]">Thông báo ban tổ chức</h2>
                        </div>
                    </div>
                </div>
                <div class="mt-3 rounded-xl border border-[#EAD9A8] bg-white/60 px-3 py-2.5">
                    <p class="text-[11px] font-bold text-[#765A20]">{{ $announcement['title'] }}</p>
                    <p class="mt-1 whitespace-pre-line text-[11px] leading-5 text-[#806F4D]">{{ $announcement['body'] }}</p>
                </div>
                <div class="mt-2 flex items-center justify-between gap-2 text-[10px] text-[#9A8453]">
                    <span>Nguồn: thông tin cuộc thi trong hệ thống</span>
                    <span class="font-semibold">{{ $announcement['meta'] }}</span>
                </div>
            </section>
        </aside>
    </div>
</div>
@endsection
