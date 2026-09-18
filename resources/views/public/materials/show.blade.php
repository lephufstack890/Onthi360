@extends('layouts.guest')

@section('title', $material->title)
@section('meta-description', 'Tài liệu '.$material->title.' trên Ôn Thi 360 — xem trước nội dung, mục lục từng phần và cách sử dụng để ôn tập.')

@section('content')
{{-- ═══════════════ [MATERIAL-SHOW] CHI TIẾT TÀI LIỆU ═══════════════
     SỬA 14/9 — CHỈ ĐỔI GIAO DIỆN: dựng lại theo đúng ngôn ngữ thị giác của các trang công
     khai đã làm (thẻ trắng bo 3xl viền sky, chữ type-*, icon lucide, tông xanh) thay cho
     bản cũ tông hồng/đỏ và emoji. Dữ liệu, route và mọi điều kiện hiển thị giữ nguyên. --}}
    @php
        $toc = $toc ?? [];
        $ratingAverage = $ratingAverage ?? null;
        $ratingCount = $ratingCount ?? 0;
        $owned = $owned ?? false;
        $coverUrl = $coverUrl ?? null;
        $badge = $material->price > 0 ? ['Cần kích hoạt', 'amber'] : ['Công khai', 'sky'];
        // Thẻ môn/khối/chuyên đề — dữ liệu có sẵn trên sản phẩm nhưng trước đây chưa hiện ở
        // trang chi tiết, dù rất hữu ích để người xem biết tài liệu có hợp với mình không.
        $tags = array_filter([$material->subject, $material->grade, $material->topic]);

        $badgeTone = [
            'amber' => 'border-amber-200 bg-amber-50 text-amber-700',
            'sky' => 'border-sky-200 bg-sky-50 text-sky-700',
        ][$badge[1]];
    @endphp

<div class="max-w-[1780px] w-full mx-auto px-3 sm:px-5 lg:px-6 2xl:px-10 py-3 sm:py-5">
<div class="flex flex-col gap-4 sm:gap-5">

    {{-- SỬA 25/8 (2): flash sau khi đặt đơn thành công ở access.checkout.store — "đặt xong
         thì chuyển qua trang tài liệu luôn" (xem AccessController::store()). Token trả ngay
         -> có quyền đọc tức thì; Offline -> vẫn chờ admin duyệt như trước. --}}
    @if (session('status') === 'access-granted')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đặt mua thành công — token đã được trừ và bạn có thể đọc bài ngay bây giờ!'])
    @endif
    @if (session('status') === 'order-placed')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tạo đơn '.session('orderNo').' — chờ admin duyệt, bạn sẽ nhận mã kích hoạt qua email/thông báo.'])
    @endif

    {{-- ══════ 1. ĐƯỜNG DẪN QUAY LẠI ══════ --}}
    <a href="{{ route('materials.index') }}"
       class="inline-flex w-fit items-center gap-1.5 rounded-xl border border-sky-100 bg-white px-3 py-1.5 text-[11px] font-bold text-slate-600 shadow-2xs transition-colors hover:border-sky-200 hover:text-blue-700">
        <x-lucide name="arrow-left" class="h-3.5 w-3.5" />Quay lại Tài liệu
    </a>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_340px] lg:gap-5 2xl:grid-cols-[minmax(0,1fr)_380px]">

        {{-- ══════ 2. THÔNG TIN TÀI LIỆU ══════ --}}
        <section class="rounded-3xl border border-sky-100 bg-white p-4 shadow-[0_2px_10px_rgba(0,100,220,0.04)] sm:p-5">
            <div class="flex flex-col gap-5 sm:flex-row">
                {{-- Ảnh bìa --}}
                <div class="relative mx-auto w-40 shrink-0 sm:mx-0 sm:w-48">
                    <div class="aspect-[3/4] w-full overflow-hidden rounded-2xl border border-sky-100 bg-[#F8FBFE] shadow-[0_6px_18px_rgba(0,100,220,0.10)]">
                        <img src="{{ $coverUrl }}" alt="Bìa {{ $material->title }}" loading="lazy" decoding="async"
                             class="h-full w-full object-cover">
                    </div>
                    @if ($owned)
                        <span title="Bạn đã sở hữu"
                              class="absolute -right-2 -top-2 grid h-8 w-8 place-items-center rounded-full bg-emerald-500 text-white shadow-md ring-2 ring-white">
                            <x-lucide name="check-circle-2" class="h-4 w-4" />
                        </span>
                    @endif
                </div>

                {{-- Tên, đánh giá, thẻ phân loại, mô tả --}}
                <div class="flex min-w-0 flex-1 flex-col justify-center">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-bold {{ $badgeTone }}">{{ $badge[0] }}</span>
                        @if ($owned)
                            <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[11px] font-bold text-emerald-700">
                                <x-lucide name="check-circle-2" class="h-3 w-3" />Đã sở hữu
                            </span>
                        @endif
                    </div>

                    <h1 class="type-hero-title mt-2.5 text-[22px] leading-snug text-[#0F3A7A] sm:text-[26px]">{{ $material->title }}</h1>

                    <div class="mt-2"><x-rating-summary :average="$ratingAverage" :count="$ratingCount" /></div>

                    @if (count($tags) > 0)
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @foreach ($tags as $tag)
                                <span class="inline-flex items-center rounded-xl border border-sky-100 bg-[#F8FBFE] px-2.5 py-1 text-[11px] font-bold text-[#536D7E]">{{ $tag }}</span>
                            @endforeach
                        </div>
                    @endif

                    <div class="rich-content type-body mt-4 leading-relaxed text-slate-600">{!! $material->description ?: 'Chưa có mô tả chi tiết.' !!}</div>
                </div>
            </div>

            {{-- SỬA 28/8 ("ẩn mục lục + tài nguyên đính kèm khỏi trang tài liệu công khai"):
                 2 khối "Mục lục" và "Tài nguyên đính kèm" trước đây hiện ngay ở đây (kể cả
                 khách chưa mua, chỉ khoá 🔒 phần chưa mua được) — theo yêu cầu, trang công
                 khai giờ CHỈ hiện thông tin sách + giá bình thường, KHÔNG lộ mục lục hay
                 danh sách file đính kèm nữa. Toàn bộ nội dung này giờ CHỈ xem được trong
                 khu vực học sinh, SAU KHI đã mua (xem student.library.index, mục "Tài liệu
                 của tôi" — App\Services\Student\LibraryService) — KHÔNG xoá dữ liệu/route
                 phía sau ($toc, MaterialService::showData() vẫn tính như cũ, chỉ bỏ hiển
                 thị ở trang này). --}}
        </section>

        {{-- ══════ 3. HỘP MUA QUYỀN ══════ --}}
        <aside class="h-fit rounded-3xl border border-sky-100 bg-white p-4 shadow-[0_2px_10px_rgba(0,100,220,0.04)] sm:p-5 lg:sticky lg:top-20">
            <p class="type-meta text-slate-400">Bản mềm</p>
            <p class="mt-0.5 text-[28px] font-black leading-none text-[#0B3C78]">
                {{ $material->price > 0 ? number_format($material->price).'đ' : 'Miễn phí' }}
            </p>
            <p class="type-meta mt-1.5 text-slate-400">Dùng ngay sau khi kích hoạt</p>

            @if ($material->has_print_option)
                <p class="mt-3 flex items-center gap-2 rounded-2xl border border-sky-100 bg-[#F8FBFE] px-3 py-2.5 text-[12px] font-medium text-slate-600">
                    <x-lucide name="package" class="h-4 w-4 shrink-0 text-[#2D7FA3]" />Có tuỳ chọn mua kèm bản in
                </p>
            @endif

            <div class="mt-4">
                @if ($owned)
                    {{-- SỬA 14/9 — đã sở hữu thì cho bấm vào đọc luôn, thay vì chỉ báo một dòng
                         chữ rồi để người dùng tự đi tìm. Đích theo vai trò, xem ghi chú ở
                         public/materials/index.blade.php. --}}
                    @php
                        $ownedReadHref = match (true) {
                            (bool) auth()->user()?->hasRole(\App\Models\Role::STUDENT) => route('student.library.index'),
                            (bool) auth()->user()?->hasRole(\App\Models\Role::TEACHER) => route('teacher.library.index'),
                            default => null,
                        };

                        // SỬA 18/9 (khách báo: "click vào đọc ngay nó không ra trang đó") — ưu tiên
                        // mở THẲNG trình đọc ở bài đầu tiên có PDF (MaterialService::showData()).
                        // Sản phẩm chưa có bài nào đọc được thì mới lùi về danh sách như cũ.
                        $ownedReadHref = ($readHref ?? null) ?: $ownedReadHref;
                    @endphp
                    @if ($ownedReadHref)
                        <a href="{{ $ownedReadHref }}"
                           class="flex min-h-12 w-full items-center justify-center gap-1.5 rounded-2xl bg-emerald-600 px-4 text-[13px] font-bold text-white shadow-[0_5px_12px_rgba(59,147,116,0.2)] transition-colors hover:bg-emerald-700">
                            <x-lucide name="book-open" class="h-4 w-4" />Vào đọc ngay
                        </a>
                    @else
                        <p class="flex items-center justify-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-center text-[13px] font-bold text-emerald-700">
                            <x-lucide name="check-circle-2" class="h-4 w-4 shrink-0" />Bạn đã sở hữu tài liệu này
                        </p>
                    @endif
                @elseif (auth()->check())
                    <a href="{{ route('access.checkout', $material->id) }}"
                       class="flex min-h-12 w-full items-center justify-center gap-1.5 rounded-2xl bg-[#126F91] px-4 text-[13px] font-bold text-white shadow-[0_5px_12px_rgba(18,111,145,0.18)] transition-colors hover:bg-[#0F5F7A]">
                        <x-lucide name="wallet-cards" class="h-4 w-4" />Đặt đơn / Mua quyền
                    </a>
                @else
                    <a href="{{ route('login') }}"
                       class="flex min-h-12 w-full items-center justify-center gap-1.5 rounded-2xl bg-[#126F91] px-4 text-[13px] font-bold text-white shadow-[0_5px_12px_rgba(18,111,145,0.18)] transition-colors hover:bg-[#0F5F7A]">
                        <x-lucide name="log-in" class="h-4 w-4" />Đăng nhập để mua quyền
                    </a>
                @endif
            </div>

            <div class="mt-5 space-y-2.5 border-t border-sky-100 pt-4">
                <p class="flex items-start gap-2 text-[11px] leading-relaxed text-slate-500">
                    <x-lucide name="shield-check" class="mt-px h-3.5 w-3.5 shrink-0 text-[#3B9374]" />
                    Thanh toán an toàn qua VNPAY hoặc chuyển khoản
                </p>
                <p class="flex items-start gap-2 text-[11px] leading-relaxed text-slate-500">
                    <x-lucide name="smartphone" class="mt-px h-3.5 w-3.5 shrink-0 text-[#2D7FA3]" />
                    Đọc mọi nơi — trên web và trên điện thoại
                </p>
                <p class="flex items-start gap-2 text-[11px] leading-relaxed text-slate-500">
                    <x-lucide name="key-round" class="mt-px h-3.5 w-3.5 shrink-0 text-[#AF7C32]" />
                    Đã có mã? <a href="{{ route('access.activate') }}" class="font-bold text-blue-600 hover:underline">Kích hoạt ngay</a>
                </p>
            </div>
        </aside>
    </div>
</div>
</div>

    @push('scripts')
        <style>
            .rich-content ul { list-style: disc; padding-left: 1.25rem; margin-bottom: 0.5rem; }
            .rich-content ol { list-style: decimal; padding-left: 1.25rem; margin-bottom: 0.5rem; }
            .rich-content p { margin-bottom: 0.5rem; }
            .rich-content a { color: #126F91; text-decoration: underline; }
            {{-- SỬA 25/8 (9 — "mục lục dạng dropdown"): ẩn phần con TRƯỚC khi Alpine.js kịp
                 khởi tạo (script Alpine tải kiểu "defer" nên có 1 khoảng trễ nhỏ) — tránh
                 nháy hiện toàn bộ rồi mới đóng lại. Xem partials/materials-toc-item.blade.php. --}}
            [x-cloak] { display: none !important; }
        </style>
    @endpush
@endsection
