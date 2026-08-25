@extends('layouts.guest')

@section('title', $material->title)

@section('content')
    @php
        $toc = $toc ?? [];
        $ratingAverage = $ratingAverage ?? null;
        $ratingCount = $ratingCount ?? 0;
        $owned = $owned ?? false;
        $coverUrl = $coverUrl ?? null;
        $badge = $material->price > 0 ? ['Cần kích hoạt', 'warning'] : ['Công khai', 'info'];
        // Thẻ môn/khối/chuyên đề — dữ liệu có sẵn trên sản phẩm nhưng trước đây chưa hiện ở
        // trang chi tiết, dù rất hữu ích để người xem biết tài liệu có hợp với mình không.
        $tags = array_filter([$material->subject, $material->grade, $material->topic]);
    @endphp

    <div class="bg-gradient-to-br from-amber-50 via-white to-rose-50">
        <div class="max-w-6xl mx-auto px-4 pt-8 pb-2">
            <a href="{{ route('materials.index') }}" class="text-sm text-slate-500 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Tài liệu</a>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-4 pb-14 -mt-2">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-6">
                <div class="flex flex-col sm:flex-row gap-6 bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                    <div class="relative w-40 sm:w-48 mx-auto sm:mx-0 shrink-0">
                        <div class="w-full aspect-[3/4] rounded-2xl shadow-md overflow-hidden bg-slate-100">
                            <img src="{{ $coverUrl }}" alt="Bìa {{ $material->title }}" loading="lazy" class="w-full h-full object-cover">
                        </div>
                        @if ($owned)
                            <span title="Bạn đã sở hữu" class="absolute top-2 right-2 w-8 h-8 rounded-full bg-emerald-500 text-white flex items-center justify-center shadow text-base font-semibold ring-2 ring-white">✓</span>
                        @endif
                    </div>
                    <div class="flex-1 flex flex-col justify-center">
                        <div class="flex items-center gap-2 flex-wrap">
                            <x-status-badge :tone="$badge[1]">{{ $badge[0] }}</x-status-badge>
                            @if ($owned)
                                <x-status-badge tone="success">✓ Đã sở hữu</x-status-badge>
                            @endif
                        </div>
                        <h1 class="text-2xl lg:text-3xl font-semibold text-slate-800 mt-3 leading-snug">{{ $material->title }}</h1>
                        <div class="mt-2"><x-rating-summary :average="$ratingAverage" :count="$ratingCount" /></div>
                        @if (count($tags) > 0)
                            <div class="flex flex-wrap gap-2 mt-3">
                                @foreach ($tags as $tag)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-600">{{ $tag }}</span>
                                @endforeach
                            </div>
                        @endif
                        <div class="rich-content text-sm text-slate-500 mt-4 leading-relaxed">{!! $material->description ?: 'Chưa có mô tả chi tiết.' !!}</div>
                    </div>
                </div>

                @if (count($toc) > 0)
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                        <h2 class="font-medium text-slate-700 mb-3 flex items-center gap-2">
                            <span>📑</span> Mục lục <span class="text-slate-400 font-normal">({{ count($toc) }} phần)</span>
                        </h2>
                        <div class="divide-y divide-slate-100">
                            @foreach ($toc as $i => $chap)
                                @php $readable = $owned && ($chap['hasContent'] ?? false); @endphp
                                <div class="flex items-center gap-3 py-3">
                                    <span class="w-7 h-7 rounded-full bg-rose-50 text-rose-600 text-xs font-semibold flex items-center justify-center shrink-0">{{ $i + 1 }}</span>
                                    @if ($readable)
                                        <a href="{{ route('student.materials.read', $chap['id']) }}" class="text-sm text-slate-700 hover:text-rose-600">{{ $chap['title'] }}</a>
                                    @else
                                        <p class="text-sm text-slate-500 flex items-center gap-1">
                                            {{ $chap['title'] }}
                                            @if ($chap['hasContent'] ?? false)
                                                <span class="text-slate-300" title="Cần mua quyền để đọc">🔒</span>
                                            @endif
                                        </p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 h-fit sticky top-6">
                <p class="text-3xl font-semibold text-slate-800">{{ $material->price > 0 ? number_format($material->price).'đ' : 'Miễn phí' }}</p>
                <p class="text-sm text-slate-400 mb-5">Bản mềm — dùng ngay sau khi kích hoạt</p>

                @if ($material->has_print_option)
                    <p class="flex items-center gap-2 text-sm text-slate-600 mb-4 px-3 py-2.5 rounded-lg border border-slate-200">📦 Có tùy chọn mua kèm bản in</p>
                @endif

                @if ($owned)
                    <p class="flex items-center justify-center gap-2 text-center px-4 py-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm font-medium">✓ Bạn đã sở hữu tài liệu này</p>
                @elseif (auth()->check())
                    <a href="{{ route('access.checkout', $material->id) }}" class="block text-center px-4 py-3 rounded-lg bg-rose-600 text-white text-sm font-semibold hover:bg-rose-700 transition">
                        Đặt đơn / Mua quyền
                    </a>
                @else
                    <a href="{{ route('login') }}" class="block text-center px-4 py-3 rounded-lg bg-rose-600 text-white text-sm font-semibold hover:bg-rose-700 transition">
                        Đăng nhập để mua quyền
                    </a>
                @endif

                <div class="mt-5 pt-5 border-t border-slate-100 space-y-2.5 text-xs text-slate-400">
                    <p class="flex items-center gap-2">🔒 Thanh toán an toàn qua VNPAY hoặc chuyển khoản</p>
                    <p class="flex items-center gap-2">📱 Đọc mọi nơi — web, học trên điện thoại</p>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <style>
            .rich-content ul { list-style: disc; padding-left: 1.25rem; margin-bottom: 0.5rem; }
            .rich-content ol { list-style: decimal; padding-left: 1.25rem; margin-bottom: 0.5rem; }
            .rich-content p { margin-bottom: 0.5rem; }
            .rich-content a { color: #e11d48; text-decoration: underline; }
        </style>
    @endpush
@endsection
