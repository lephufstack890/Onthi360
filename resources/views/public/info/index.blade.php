{{--
  Route: info.index | Frame: PUB-11
  Spec: 4.1 (Giới thiệu, hướng dẫn, tin tức, chính sách, liên hệ, FAQ).
  Dữ liệu thật do App\Http\Controllers\Public\InfoController (qua App\Services\Public\InfoService)
  truyền vào — không còn mảng dữ liệu hardcode ngay trong view. 4 số liệu ở "Giới thiệu" dùng lại
  ĐÚNG kết quả HomeService::buildStats() (trang chủ) để không lệch số giữa 2 trang.
  Form "Liên hệ" gửi thật tới info.contact.store (App\Http\Controllers\Public\ContactController),
  lưu vào bảng contact_messages, admin xem/xử lý ở admin.contact-messages.index. Route này có
  throttle:5,1 (routes/web.php) — khi bị chặn quá tần suất, bootstrap/app.php bắt
  ThrottleRequestsException và quay lại đây kèm session('status') === 'contact-throttled'.
  TODO: tách route riêng cho từng mục nếu nội dung dài (info.about/info.faq/info.contact...);
  hiện gộp 1 trang, điều hướng bằng anchor link nội trang (#gioi-thieu, #huong-dan, ...).
  5 thẻ "Vì sao chọn" (mục $highlights) bấm được, trỏ đúng trang tính năng thật
  (App\Services\Public\InfoService::highlights()) — KHÁC với khối "Lợi ích" ($reasons) ngay bên
  dưới, vốn chỉ là danh sách tĩnh không bấm được; 2 khối cố tình đặt tiêu đề khác hẳn nhau để
  không ai nhầm khối tĩnh là thẻ bấm được. "Xem chi tiết" ở Chính sách trỏ tới
  info.policies.show (trang chi tiết thật).
--}}
@extends('layouts.guest')

@section('title', 'Thông tin')

@section('content')
    @php
        $sections = $sections ?? [];
        $guides = $guides ?? [];
        $policies = $policies ?? [];
        $highlights = $highlights ?? [];
        $reasons = $reasons ?? [];
        $stats = $stats ?? [];
        $contact = $contact ?? [];
        // Class Tailwind phải là chuỗi tĩnh đầy đủ (không ghép "bg-{{ $tone }}-50" lúc runtime)
        // để trình quét nội dung của Tailwind nhận diện được — cùng quy ước với x-icon-tile.
        $statToneClasses = [
            ['bg' => 'bg-rose-50/60', 'text' => 'text-rose-600'],
            ['bg' => 'bg-sky-50/60', 'text' => 'text-sky-600'],
            ['bg' => 'bg-violet-50/60', 'text' => 'text-violet-600'],
            ['bg' => 'bg-emerald-50/60', 'text' => 'text-emerald-600'],
        ];
    @endphp

    {{-- Hero --}}
    <div class="relative overflow-hidden bg-gradient-to-br from-sky-50 via-white to-rose-50">
        <div class="absolute -top-16 -right-16 w-64 h-64 rounded-full bg-rose-200/30 blur-3xl"></div>
        <div class="absolute -bottom-16 -left-16 w-64 h-64 rounded-full bg-sky-200/30 blur-3xl"></div>
        <div class="relative max-w-5xl mx-auto px-4 py-12 lg:py-16 text-center">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white text-sky-600 text-xs font-medium mb-4 shadow-sm">ℹ️ Thông tin</span>
            <h1 class="text-2xl lg:text-3xl font-semibold text-slate-800">Mọi điều cần biết về Ôn Thi 360</h1>
            <p class="text-slate-500 mt-3 max-w-lg mx-auto">Giới thiệu, hướng dẫn sử dụng theo từng vai trò, chính sách và cách liên hệ với chúng tôi.</p>
        </div>
    </div>

    <div class="max-w-5xl mx-auto px-4 py-10 lg:py-14">
        {{-- Điều hướng nhanh giữa các mục --}}
        <div class="flex flex-wrap justify-center gap-2 mb-12 text-sm sticky top-0 bg-slate-50/90 backdrop-blur py-3 z-10 -mx-4 px-4">
            @foreach ($sections as $s)
                <a href="#{{ $s['id'] }}" class="px-3 py-1.5 rounded-full border border-slate-200 bg-white text-slate-600 font-medium hover:border-rose-300 hover:text-rose-600 transition-colors">
                    {{ $s['icon'] }} {{ $s['label'] }}
                </a>
            @endforeach
        </div>

        {{-- Giới thiệu --}}
        <section id="gioi-thieu" class="mb-14 scroll-mt-20">
            <h2 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2"><span>📖</span> Giới thiệu</h2>
            <div class="bg-white rounded-2xl border border-slate-200 p-6">
                <p class="text-sm text-slate-600 leading-relaxed">
                    Ôn Thi 360 là nền tảng học có lộ trình, luyện tập và chấm bài tự động — nơi học sinh, giáo viên và
                    phụ huynh cùng đồng hành trên một hệ thống minh bạch, luôn nêu rõ lý do trước khi khóa nội dung.
                    Câu lập trình, trắc nghiệm và điền đáp án đều có thể trộn trong cùng một đề, chấm điểm tự động và
                    lưu lại lịch sử để ôn tập lâu dài.
                </p>
                @if (count($stats) > 0)
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6 pt-6 border-t border-slate-100 text-center">
                        @foreach ($stats as $i => $stat)
                            @php $tone = $statToneClasses[$i % count($statToneClasses)]; @endphp
                            <div class="rounded-xl {{ $tone['bg'] }} py-3">
                                <p class="text-xl font-semibold {{ $tone['text'] }}">{{ $stat['value'] }}</p>
                                <p class="text-xs text-slate-500 mt-1">{{ $stat['label'] }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            @if (count($highlights) > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mt-4">
                    @foreach ($highlights as $h)
                        {{-- 'route' rỗng (chưa có tính năng riêng) trỏ về đầu mục Giới thiệu thay vì link chính trang này. --}}
                        <a href="{{ $h['route'] ? route($h['route']) : '#gioi-thieu' }}"
                           class="block rounded-2xl bg-white border border-slate-200 p-5 text-center hover:shadow-md hover:-translate-y-0.5 hover:border-rose-200 transition-all">
                            <div class="flex justify-center">
                                <x-icon-tile :emoji="$h['emoji']" :tone="$h['tone']" />
                            </div>
                            <p class="font-medium text-slate-700 text-sm mt-3">{{ $h['title'] }}</p>
                            <p class="text-xs text-slate-400 mt-1 leading-relaxed">{{ $h['body'] }}</p>
                        </a>
                    @endforeach
                </div>
            @endif

            @if (count($reasons) > 0)
                <div class="bg-white rounded-2xl border border-slate-200 p-6 mt-4">
                    <h3 class="font-medium text-slate-700 mb-4 flex items-center gap-2">✨ Lợi ích khi đồng hành cùng Ôn Thi 360</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach ($reasons as $r)
                            <div class="flex items-center gap-2 text-sm text-slate-600 rounded-xl bg-slate-50 px-3 py-2.5">
                                {{ $r }}
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>

        {{-- Hướng dẫn sử dụng --}}
        @if (count($guides) > 0)
            <section id="huong-dan" class="mb-14 scroll-mt-20">
                <h2 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2"><span>🧭</span> Hướng dẫn sử dụng</h2>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                    @foreach ($guides as $g)
                        <div class="bg-white rounded-2xl border border-slate-200 p-5">
                            <div class="flex items-center gap-3 mb-3">
                                <x-icon-tile :emoji="$g['icon']" :tone="$g['tone']" />
                                <h3 class="font-medium text-slate-700">{{ $g['role'] }}</h3>
                            </div>
                            <ol class="space-y-2.5">
                                @foreach ($g['steps'] as $i => $step)
                                    <li class="flex items-start gap-2.5 text-sm text-slate-600">
                                        <span class="w-5 h-5 rounded-full bg-slate-100 text-slate-500 text-xs font-semibold flex items-center justify-center shrink-0 mt-0.5">{{ $i + 1 }}</span>
                                        {{ $step }}
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Chính sách --}}
        @if (count($policies) > 0)
            <section id="chinh-sach" class="mb-14 scroll-mt-20">
                <h2 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2"><span>📜</span> Chính sách</h2>
                <div class="bg-white rounded-2xl border border-slate-200 divide-y divide-slate-100">
                    @foreach ($policies as $p)
                        <div class="flex items-center justify-between gap-4 p-5 hover:bg-slate-50/60 transition-colors">
                            <div>
                                <p class="font-medium text-slate-700">{{ $p['title'] }}</p>
                                <p class="text-sm text-slate-400 mt-0.5">{{ $p['desc'] }}</p>
                            </div>
                            <a href="{{ route('info.policies.show', $p['slug']) }}" class="text-sm text-rose-600 font-medium shrink-0 whitespace-nowrap">Xem chi tiết ›</a>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Liên hệ --}}
        <section id="lien-he" class="scroll-mt-20">
            <h2 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2"><span>✉️</span> Liên hệ</h2>

            @if (session('status') === 'contact-sent')
                @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã gửi liên hệ! Chúng tôi sẽ phản hồi sớm nhất có thể.'])
            @elseif (session('status') === 'contact-throttled')
                @include('partials.toast-flash', ['type' => 'error', 'message' => 'Bạn vừa gửi liên hệ quá nhiều lần liên tiếp — vui lòng thử lại sau ít phút.'])
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <div class="lg:col-span-1 space-y-3">
                    @if ($contact['hotline'] ?? null)
                        <div class="bg-white rounded-2xl border border-slate-200 p-5 flex items-start gap-3">
                            <x-icon-tile emoji="📞" tone="emerald" />
                            <div>
                                <p class="text-sm font-medium text-slate-700">Hotline</p>
                                <p class="text-sm text-slate-500">{{ $contact['hotline'] }}</p>
                            </div>
                        </div>
                    @endif
                    @if ($contact['email'] ?? null)
                        <div class="bg-white rounded-2xl border border-slate-200 p-5 flex items-start gap-3">
                            <x-icon-tile emoji="📧" tone="sky" />
                            <div>
                                <p class="text-sm font-medium text-slate-700">Email hỗ trợ</p>
                                <p class="text-sm text-slate-500">{{ $contact['email'] }}</p>
                            </div>
                        </div>
                    @endif
                    @if ($contact['facebook'] ?? null)
                        <div class="bg-white rounded-2xl border border-slate-200 p-5 flex items-start gap-3">
                            <x-icon-tile emoji="📘" tone="violet" />
                            <div>
                                <p class="text-sm font-medium text-slate-700">Facebook</p>
                                <p class="text-sm text-slate-500">{{ $contact['facebook'] }}</p>
                            </div>
                        </div>
                    @endif
                    @if ($contact['zalo'] ?? null)
                        <div class="bg-white rounded-2xl border border-slate-200 p-5 flex items-start gap-3">
                            <x-icon-tile emoji="💬" tone="rose" />
                            <div>
                                <p class="text-sm font-medium text-slate-700">Zalo</p>
                                <p class="text-sm text-slate-500">{{ $contact['zalo'] }}</p>
                            </div>
                        </div>
                    @endif
                    @if ($contact['address'] ?? null)
                        <div class="bg-white rounded-2xl border border-slate-200 p-5 flex items-start gap-3">
                            <x-icon-tile emoji="📍" tone="amber" />
                            <div>
                                <p class="text-sm font-medium text-slate-700">Địa chỉ</p>
                                <p class="text-sm text-slate-500">{{ $contact['address'] }}</p>
                            </div>
                        </div>
                    @endif
                </div>

                <form method="POST" action="{{ route('info.contact.store') }}" class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-6 space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1">Họ tên</label>
                            <input type="text" name="name" value="{{ old('name') }}" class="w-full rounded-lg border {{ $errors->has('name') ? 'border-rose-300' : 'border-slate-200' }} text-sm p-2.5" placeholder="Tên của bạn">
                            @error('name')
                                <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1">Email</label>
                            <input type="email" name="email" value="{{ old('email') }}" class="w-full rounded-lg border {{ $errors->has('email') ? 'border-rose-300' : 'border-slate-200' }} text-sm p-2.5" placeholder="you@email.com">
                            @error('email')
                                <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Nội dung</label>
                        <textarea name="message" rows="4" class="w-full rounded-lg border {{ $errors->has('message') ? 'border-rose-300' : 'border-slate-200' }} text-sm p-3" placeholder="Bạn cần hỗ trợ điều gì?">{{ old('message') }}</textarea>
                        @error('message')
                            <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium hover:bg-rose-700 transition-colors">Gửi liên hệ</button>
                </form>
            </div>
        </section>
    </div>
@endsection
