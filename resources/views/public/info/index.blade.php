@extends('layouts.guest')

@section('title', 'Thông tin & Hỗ trợ Kỹ thuật 24/7')
@section('meta-description', 'Trung tâm trợ giúp Ôn Thi 360 — giải đáp về khóa học, kích hoạt mã bản quyền, hệ thống chấm bài Online Judge, chính sách và kênh liên hệ chính thức.')

@section('content')
{{-- ═══════════════ [INFO] MÀN THÔNG TIN ═══════════════
     SỬA 11/9 — dựng lại theo ĐÚNG source giao diện khách gửi:
     education-main/src/components/InfoPage.jsx (hero + 2 cột FAQ / form hỗ trợ).
     Bố cục/class chép nguyên; React state đổi sang Alpine; mọi nút gắn link thật.

     Dữ liệu lấy từ App\Services\Public\InfoService::indexData:
       · FAQ hỗ trợ  <- $supportFaqs
       · form hỗ trợ -> POST thật tới info.contact.store (ghi vào bảng contact_messages),
                        KHÔNG phải chỉ đổi trạng thái ở trình duyệt như bản mẫu
       · số liệu     <- $stats   (cùng nguồn với trang chủ — HomeService::buildStats)
       · hướng dẫn   <- $guides  · chính sách <- $policies  · liên hệ <- $contact

     Bản mẫu chỉ có hero + FAQ + form. Các khối Giới thiệu / Hướng dẫn / Chính sách / Liên hệ
     bên dưới là nội dung THẬT đã có của hệ thống — giữ lại và dựng theo cùng ngôn ngữ thị
     giác của bản mẫu, không xoá đi. --}}
@php
    $supportFaqs = $supportFaqs ?? [];
    $stats = $stats ?? [];
    $guides = $guides ?? [];
    $policies = $policies ?? [];
    $highlights = $highlights ?? [];
    $reasons = $reasons ?? [];
    $contact = $contact ?? [];

    $statTones = [
        ['bg' => 'bg-sky-50', 'text' => 'text-[#0066CC]', 'icon' => 'users'],
        ['bg' => 'bg-emerald-50', 'text' => 'text-[#3B9374]', 'icon' => 'graduation-cap'],
        ['bg' => 'bg-violet-50', 'text' => 'text-[#786BB1]', 'icon' => 'book-open'],
        ['bg' => 'bg-amber-50', 'text' => 'text-[#AF7C32]', 'icon' => 'star'],
    ];

    $guideTones = [
        'rose' => ['bg' => 'bg-rose-50', 'border' => 'border-rose-100', 'text' => 'text-rose-600'],
        'sky' => ['bg' => 'bg-sky-50', 'border' => 'border-sky-100', 'text' => 'text-sky-600'],
        'emerald' => ['bg' => 'bg-emerald-50', 'border' => 'border-emerald-100', 'text' => 'text-emerald-600'],
    ];
@endphp

<div class="max-w-[1780px] w-full mx-auto px-3 sm:px-5 lg:px-6 2xl:px-10 py-3 sm:py-5">
<div x-data="onthiInfoPage()" class="flex flex-col gap-4">

    {{-- ══════ 1. HERO ══════ --}}
    <div id="gioi-thieu" class="relative overflow-hidden rounded-2xl border border-sky-200/90 bg-gradient-to-r from-[#0050A0] via-[#0284C7] to-[#38BDF8] p-5 text-white shadow-[0_8px_24px_rgba(0,100,220,0.08)] sm:p-6 scroll-mt-20">
        <img src="{{ asset('assets/hero-info.jpg') }}" alt=""
             class="absolute inset-0 h-full w-full object-cover object-right pointer-events-none opacity-35 mix-blend-overlay">

        <div class="relative z-10 max-w-2xl">
            <div class="mb-3 inline-flex items-center gap-1.5 rounded-full bg-amber-400 px-3 py-1 text-[11px] font-bold text-amber-950 shadow-sm">
                <x-lucide name="info" class="w-3.5 h-3.5" />
                <span>Trung tâm Trợ giúp & Thông tin Chính thức</span>
            </div>

            <h1 class="text-2xl font-bold leading-tight tracking-tight text-white">Thông tin & Hỗ trợ Kỹ thuật 24/7</h1>

            <p class="mt-2 max-w-2xl text-xs leading-relaxed text-sky-100 sm:text-sm">
                Giải đáp mọi thắc mắc về khóa học, kích hoạt mã bản quyền, hệ thống chấm bài Online Judge và hỗ trợ học viên.
            </p>

            <div class="mt-4 flex flex-wrap gap-2 text-[11px] font-bold text-sky-100">
                <a href="{{ route('access.activate') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-white/20 bg-white/10 px-2.5 py-1.5 transition-colors hover:bg-white/20">
                    <x-lucide name="key-round" class="h-3.5 w-3.5 text-amber-300" />Kích hoạt mã
                </a>
                <a href="#lien-he" class="inline-flex items-center gap-1.5 rounded-lg border border-white/20 bg-white/10 px-2.5 py-1.5 transition-colors hover:bg-white/20">
                    <x-lucide name="headphones" class="h-3.5 w-3.5 text-sky-200" />Liên hệ hỗ trợ
                </a>
                <a href="#chinh-sach" class="inline-flex items-center gap-1.5 rounded-lg border border-white/20 bg-white/10 px-2.5 py-1.5 transition-colors hover:bg-white/20">
                    <x-lucide name="shield-check" class="h-3.5 w-3.5 text-emerald-300" />Chính sách
                </a>
            </div>
        </div>
    </div>

    {{-- ══════ 2. HAI CỘT: FAQ & FORM HỖ TRỢ ══════ --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

        {{-- FAQ --}}
        <div class="rounded-2xl border border-sky-100 bg-white p-4 shadow-[0_2px_12px_rgba(0,100,220,0.06)]">
            <div class="mb-4 flex items-center gap-2.5">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-50 text-sky-700">
                    <x-lucide name="help-circle" class="h-5 w-5" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[#0B3C78]">Câu hỏi thường gặp (FAQ)</h3>
                    <p class="text-[11px] text-slate-500">Giải đáp nhanh các vấn đề phổ biến</p>
                </div>
            </div>

            <div class="divide-y divide-sky-100 overflow-hidden rounded-xl border border-sky-100">
                @foreach ($supportFaqs as $i => $f)
                    <div>
                        <button type="button" :aria-expanded="openFaq === {{ $i }}" @click="toggleFaq({{ $i }})"
                                class="flex min-h-12 w-full cursor-pointer items-center justify-between gap-3 p-3 text-left text-xs font-bold leading-5 text-slate-800 transition-colors hover:bg-sky-50/60 hover:text-blue-600">
                            <span>{{ $i + 1 }}. {{ $f['q'] }}</span>
                            <x-lucide name="chevron-down" class="w-4 h-4 transition-transform"
                                      ::class="openFaq === {{ $i }} ? 'rotate-180 text-blue-600' : 'text-slate-400'" />
                        </button>
                        <div x-show="openFaq === {{ $i }}" x-cloak
                             class="border-t border-sky-50 bg-sky-50/50 p-3 text-xs leading-relaxed text-slate-600">{{ $f['a'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Form gửi yêu cầu hỗ trợ — POST thật, lưu vào contact_messages --}}
        <div id="lien-he" class="rounded-2xl border border-sky-100 bg-white p-4 shadow-[0_2px_12px_rgba(0,100,220,0.06)] scroll-mt-20">
            <div class="mb-4 flex items-center gap-2.5">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-700">
                    <x-lucide name="headphones" class="h-5 w-5" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[#0B3C78]">Gửi yêu cầu hỗ trợ</h3>
                    <p class="text-[11px] text-slate-500">Đội ngũ kỹ thuật sẽ phản hồi trong thời gian sớm nhất</p>
                </div>
            </div>

            @if (session('status') === 'contact-sent')
                {{-- Trạng thái "đã gửi" của bản mẫu, nhưng hiện SAU KHI máy chủ ghi nhận thật --}}
                <div class="py-6 text-center">
                    <x-lucide name="check-circle" class="mx-auto mb-2 h-10 w-10 text-emerald-500" />
                    <h4 class="text-sm font-bold text-slate-800">Đã gửi yêu cầu thành công!</h4>
                    <p class="mt-1 text-xs text-slate-500">Chuyên viên hỗ trợ sẽ liên hệ với bạn ngay.</p>
                    {{-- Mã phiếu — để hai bên nhắc tới cùng một yêu cầu khi gọi lại hoặc trả lời email. --}}
                    @if (session('contact-ticket'))
                        <p class="mt-3 inline-flex items-center gap-1.5 rounded-xl border border-sky-200 bg-[#F0F6FC] px-3 py-1.5 text-[11px] text-slate-600">
                            <x-lucide name="ticket" class="h-3.5 w-3.5 text-sky-600" />
                            Mã phiếu của bạn: <strong class="font-bold tracking-wide text-[#0B3C78]">{{ session('contact-ticket') }}</strong>
                        </p>
                        <p class="mt-1.5 text-[11px] text-slate-400">Giữ lại mã này để tiện tra cứu khi liên hệ lại.</p>
                    @endif
                    <button type="button" @click="resend = true" x-show="!resend"
                            class="mt-3 block w-full text-[11px] font-bold text-[#126F91] hover:underline">Gửi thêm một yêu cầu khác</button>
                </div>
            @endif

            <form method="POST" action="{{ route('info.contact.store') }}"
                  class="flex flex-col gap-3 text-xs"
                  @if (session('status') === 'contact-sent') x-show="resend" x-cloak @endif>
                @csrf

                <div>
                    <label for="contact-name" class="mb-1 block text-[11px] font-bold text-slate-700">Họ và tên của bạn</label>
                    <input id="contact-name" name="name" type="text" required maxlength="150"
                           value="{{ old('name', auth()->user()->name ?? '') }}" placeholder="Nguyễn Văn A"
                           class="h-10 w-full rounded-xl border bg-[#F0F6FC] px-3 text-xs text-slate-700 outline-none placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-100 {{ $errors->has('name') ? 'border-rose-300' : 'border-sky-200' }}">
                    @error('name') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="contact-email" class="mb-1 block text-[11px] font-bold text-slate-700">Email liên hệ</label>
                    <input id="contact-email" name="email" type="email" required maxlength="255"
                           value="{{ old('email', auth()->user()->email ?? '') }}" placeholder="email@example.com"
                           class="h-10 w-full rounded-xl border bg-[#F0F6FC] px-3 text-xs text-slate-700 outline-none placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-100 {{ $errors->has('email') ? 'border-rose-300' : 'border-sky-200' }}">
                    @error('email') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
                </div>

                {{-- SỬA 13/9 — thêm số điện thoại và loại yêu cầu. Phụ huynh phần lớn chỉ tiện
                     nghe máy, còn loại yêu cầu giúp bộ phận hỗ trợ lọc và chuyển đúng người
                     thay vì phải đọc hết mới biết việc của ai. --}}
                <div>
                    <label for="contact-phone" class="mb-1 block text-[11px] font-bold text-slate-700">
                        Số điện thoại <span class="font-medium text-slate-400">(không bắt buộc)</span>
                    </label>
                    <input id="contact-phone" name="phone" type="tel" maxlength="30" inputmode="tel"
                           value="{{ old('phone') }}" placeholder="09xx xxx xxx"
                           class="h-10 w-full rounded-xl border bg-[#F0F6FC] px-3 text-xs text-slate-700 outline-none placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-100 {{ $errors->has('phone') ? 'border-rose-300' : 'border-sky-200' }}">
                    @error('phone') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="contact-topic" class="mb-1 block text-[11px] font-bold text-slate-700">Loại yêu cầu</label>
                    <select id="contact-topic" name="topic"
                            class="h-10 w-full rounded-xl border bg-[#F0F6FC] px-3 text-xs text-slate-700 outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-100 {{ $errors->has('topic') ? 'border-rose-300' : 'border-sky-200' }}">
                        <option value="">— Chọn để chúng tôi chuyển đúng bộ phận —</option>
                        @foreach (\App\Enums\SupportTopic::options() as $topicValue => $topicLabel)
                            <option value="{{ $topicValue }}" @selected(old('topic') === $topicValue)>{{ $topicLabel }}</option>
                        @endforeach
                    </select>
                    @error('topic') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="contact-message" class="mb-1 block text-[11px] font-bold text-slate-700">Nội dung cần hỗ trợ</label>
                    <textarea id="contact-message" name="message" rows="3" required maxlength="2000"
                              placeholder="Mô tả chi tiết vấn đề bạn gặp phải (lỗi nộp bài, kích hoạt mã...)"
                              class="w-full resize-y rounded-xl border bg-[#F0F6FC] p-3 text-xs leading-relaxed text-slate-700 outline-none placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-100 {{ $errors->has('message') ? 'border-rose-300' : 'border-sky-200' }}">{{ old('message') }}</textarea>
                    @error('message') <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p> @enderror
                </div>

                {{-- Bẫy máy gửi rác: ẩn khỏi mắt người và khỏi trình đọc màn hình, người thật
                     không bao giờ điền. Máy điền hết mọi ô nên sẽ tự lộ — xem ContactController.
                     Cách này thay cho CAPTCHA, vốn vừa phiền vừa khó với học sinh nhỏ tuổi. --}}
                <div class="absolute left-[-9999px] top-auto h-px w-px overflow-hidden" aria-hidden="true">
                    <label for="contact-website">Để trống ô này</label>
                    <input id="contact-website" name="website" type="text" tabindex="-1" autocomplete="off" value="">
                </div>

                <button type="submit"
                        class="flex min-h-10 cursor-pointer items-center justify-center gap-1.5 rounded-xl bg-[#126F91] px-3 py-2 text-[11px] font-bold text-white shadow-sm transition-colors hover:bg-[#0F5F7A]">
                    <x-lucide name="send" class="w-3.5 h-3.5" />
                    <span>Gửi yêu cầu hỗ trợ</span>
                </button>

                <p class="text-[10px] leading-relaxed text-slate-400">
                    Thông tin bạn gửi chỉ dùng để liên hệ hỗ trợ và <strong class="font-bold">chỉ quản trị viên đọc được</strong>,
                    không hiển thị ở bất kỳ trang công khai nào.
                </p>
            </form>

            {{-- Kênh liên hệ chính thức — dữ liệu thật từ InfoService::contact() --}}
            <div class="mt-4 grid grid-cols-1 gap-2 border-t border-sky-100 pt-4 sm:grid-cols-2">
                @if ($contact['hotline'] ?? null)
                    <a href="tel:{{ preg_replace('/\s+/', '', $contact['hotline']) }}"
                       class="flex items-center gap-2.5 rounded-xl border border-sky-100 bg-[#F8FBFE] p-2.5 transition-colors hover:border-sky-200 hover:bg-sky-50">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-sky-100 text-sky-700"><x-lucide name="phone" class="h-4 w-4" /></span>
                        <span class="min-w-0">
                            <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400">Hotline</span>
                            <span class="block truncate text-[11px] font-bold text-slate-700">{{ $contact['hotline'] }}</span>
                        </span>
                    </a>
                @endif
                @if ($contact['email'] ?? null)
                    <a href="mailto:{{ $contact['email'] }}"
                       class="flex items-center gap-2.5 rounded-xl border border-sky-100 bg-[#F8FBFE] p-2.5 transition-colors hover:border-sky-200 hover:bg-sky-50">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-emerald-100 text-emerald-700"><x-lucide name="mail" class="h-4 w-4" /></span>
                        <span class="min-w-0">
                            <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400">Email</span>
                            <span class="block truncate text-[11px] font-bold text-slate-700">{{ $contact['email'] }}</span>
                        </span>
                    </a>
                @endif
                @if ($contact['facebook'] ?? null)
                    <a href="https://{{ $contact['facebook'] }}" target="_blank" rel="noopener"
                       class="flex items-center gap-2.5 rounded-xl border border-sky-100 bg-[#F8FBFE] p-2.5 transition-colors hover:border-sky-200 hover:bg-sky-50">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-blue-100 text-blue-700"><x-lucide name="facebook" class="h-4 w-4" /></span>
                        <span class="min-w-0">
                            <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400">Facebook</span>
                            <span class="block truncate text-[11px] font-bold text-slate-700">{{ $contact['facebook'] }}</span>
                        </span>
                    </a>
                @endif
                @if ($contact['zalo'] ?? null)
                    <a href="https://{{ $contact['zalo'] }}" target="_blank" rel="noopener"
                       class="flex items-center gap-2.5 rounded-xl border border-sky-100 bg-[#F8FBFE] p-2.5 transition-colors hover:border-sky-200 hover:bg-sky-50">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-sky-100 text-sky-700"><x-lucide name="message-circle" class="h-4 w-4" /></span>
                        <span class="min-w-0">
                            <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400">Zalo</span>
                            <span class="block truncate text-[11px] font-bold text-slate-700">{{ $contact['zalo'] }}</span>
                        </span>
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- ══════ 3. GIỚI THIỆU — 4 số liệu thật của nền tảng ══════ --}}
    @if (count($stats) > 0)
        <section class="rounded-2xl border border-sky-100 bg-white p-4 shadow-[0_2px_12px_rgba(0,100,220,0.06)]">
            <div class="mb-4 flex items-center gap-2.5">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-50 text-sky-700">
                    <x-lucide name="bar-chart" class="h-5 w-5" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[#0B3C78]">Ôn Thi 360 hiện tại</h3>
                    <p class="text-[11px] text-slate-500">Số liệu đếm trực tiếp từ dữ liệu vận hành, không phải con số quảng bá</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2.5 lg:grid-cols-4">
                @foreach ($stats as $i => $stat)
                    @php $tone = $statTones[$i % count($statTones)]; @endphp
                    <div class="rounded-xl border border-sky-100 {{ $tone['bg'] }} p-3 text-center">
                        <span class="mx-auto mb-1.5 grid h-8 w-8 place-items-center rounded-lg bg-white {{ $tone['text'] }}">
                            <x-lucide :name="$tone['icon']" class="h-4 w-4" />
                        </span>
                        <p class="text-lg font-black leading-tight {{ $tone['text'] }}">{{ $stat['value'] }}</p>
                        <p class="mt-0.5 text-[11px] text-slate-500">{{ $stat['label'] }}</p>
                    </div>
                @endforeach
            </div>

            @if (count($reasons) > 0)
                <div class="mt-4 flex flex-wrap gap-1.5 border-t border-sky-100 pt-4">
                    @foreach ($reasons as $reason)
                        <span class="inline-flex items-center rounded-lg border border-sky-100 bg-[#F8FBFE] px-2.5 py-1.5 text-[11px] font-medium text-slate-600">{{ $reason }}</span>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    {{-- ══════ 4. HƯỚNG DẪN SỬ DỤNG THEO VAI TRÒ ══════ --}}
    @if (count($guides) > 0)
        <section id="huong-dan" class="rounded-2xl border border-sky-100 bg-white p-4 shadow-[0_2px_12px_rgba(0,100,220,0.06)] scroll-mt-20">
            <div class="mb-4 flex items-center gap-2.5">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
                    <x-lucide name="compass" class="h-5 w-5" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[#0B3C78]">Hướng dẫn sử dụng</h3>
                    <p class="text-[11px] text-slate-500">Ba bước đầu tiên cho từng vai trò</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                @foreach ($guides as $guide)
                    @php $tone = $guideTones[$guide['tone']] ?? $guideTones['sky']; @endphp
                    <div class="rounded-xl border {{ $tone['border'] }} {{ $tone['bg'] }} p-3.5">
                        <div class="mb-2.5 flex items-center gap-2">
                            <span class="grid h-8 w-8 place-items-center rounded-lg bg-white text-base">{{ $guide['icon'] }}</span>
                            <h4 class="text-xs font-bold {{ $tone['text'] }}">{{ $guide['role'] }}</h4>
                        </div>
                        <ol class="space-y-1.5">
                            @foreach ($guide['steps'] as $k => $step)
                                <li class="flex gap-2 text-[11px] leading-relaxed text-slate-600">
                                    <span class="grid h-4 w-4 shrink-0 place-items-center rounded-full bg-white text-[10px] font-bold {{ $tone['text'] }}">{{ $k + 1 }}</span>
                                    <span>{{ $step }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endforeach
            </div>

            @if (count($highlights) > 0)
                <div class="mt-4 grid grid-cols-1 gap-2 border-t border-sky-100 pt-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($highlights as $h)
                        @php $href = !empty($h['route']) && \Illuminate\Support\Facades\Route::has($h['route']) ? route($h['route']) : '#gioi-thieu'; @endphp
                        <a href="{{ $href }}"
                           class="flex items-start gap-2.5 rounded-xl border border-sky-100 bg-[#F8FBFE] p-2.5 transition-colors hover:border-sky-200 hover:bg-sky-50">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-white text-base">{{ $h['emoji'] }}</span>
                            <span class="min-w-0">
                                <span class="block text-[11px] font-bold text-[#0B3C78]">{{ $h['title'] }}</span>
                                <span class="mt-0.5 block text-[11px] leading-relaxed text-slate-500">{{ $h['body'] }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    {{-- ══════ 5. CHÍNH SÁCH ══════ --}}
    @if (count($policies) > 0)
        <section id="chinh-sach" class="rounded-2xl border border-sky-100 bg-white p-4 shadow-[0_2px_12px_rgba(0,100,220,0.06)] scroll-mt-20">
            <div class="mb-4 flex items-center gap-2.5">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-violet-50 text-violet-700">
                    <x-lucide name="shield-check" class="h-5 w-5" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[#0B3C78]">Chính sách</h3>
                    <p class="text-[11px] text-slate-500">Quy định áp dụng cho mọi vai trò khi dùng nền tảng</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                @foreach ($policies as $policy)
                    <a href="{{ route('info.policies.show', $policy['slug']) }}"
                       class="group flex flex-col justify-between rounded-xl border border-sky-100 bg-[#F8FBFE] p-3.5 transition-all hover:border-sky-200 hover:bg-white hover:shadow-sm">
                        <div>
                            <h4 class="text-xs font-bold text-[#0B3C78] transition-colors group-hover:text-blue-600">{{ $policy['title'] }}</h4>
                            <p class="mt-1.5 text-[11px] leading-relaxed text-slate-500">{{ $policy['desc'] }}</p>
                        </div>
                        <span class="mt-3 inline-flex items-center gap-1 text-[11px] font-bold text-[#126F91]">
                            Xem chi tiết <x-lucide name="chevron-right" class="h-3 w-3" />
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</div>
</div>
@endsection

@push('scripts')
    @include('partials.info-page-script')
@endpush
