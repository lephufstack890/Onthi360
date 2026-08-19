@extends('layouts.guest')

@section('title', 'Chi tiết tài liệu')

@section('content')
    @php
        $toc = $toc ?? [];
        $ratingAverage = $ratingAverage ?? null;
        $ratingCount = $ratingCount ?? 0;
        $owned = $owned ?? false;
        $badge = $material->price > 0 ? ['Cần kích hoạt', 'warning'] : ['Công khai', 'info'];

        // Bìa placeholder theo thương hiệu: chọn 1 trong vài cặp gradient cố định dựa trên
        // hash tiêu đề, để mỗi tài liệu có 1 màu ổn định (không đổi mỗi lần load) nhưng danh
        // sách nhiều tài liệu vẫn có màu sắc đa dạng, không lặp 1 màu nhàm chán.
        $coverPalettes = [
            ['from-rose-500', 'to-rose-700'],
            ['from-amber-500', 'to-orange-600'],
            ['from-sky-500', 'to-blue-700'],
            ['from-emerald-500', 'to-teal-700'],
            ['from-violet-500', 'to-purple-700'],
        ];
        $coverPalette = $coverPalettes[crc32($material->title) % count($coverPalettes)];
    @endphp

    <div class="max-w-6xl mx-auto px-4 py-10">
        <a href="{{ route('materials.index') }}" class="text-sm text-slate-500 mb-6 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Tài liệu</a>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-6">
                <div class="flex flex-col sm:flex-row gap-5">
                    <div class="relative w-40 sm:w-48 mx-auto sm:mx-0 shrink-0">
                        <div class="w-full aspect-[3/4] rounded-2xl shadow-md bg-gradient-to-br {{ $coverPalette[0] }} {{ $coverPalette[1] }} flex flex-col items-center justify-center p-4 text-center overflow-hidden">
                            <span class="text-4xl mb-3 opacity-90" aria-hidden="true">📘</span>
                            <p class="text-white text-sm font-semibold leading-snug line-clamp-4">{{ $material->title }}</p>
                        </div>
                        @if ($owned)
                            <span title="Bạn đã sở hữu" class="absolute top-2 right-2 w-8 h-8 rounded-full bg-emerald-500 text-white flex items-center justify-center shadow text-base font-semibold ring-2 ring-white">✓</span>
                        @endif
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <x-status-badge :tone="$badge[1]">{{ $badge[0] }}</x-status-badge>
                            @if ($owned)
                                <x-status-badge tone="success">✓ Đã sở hữu</x-status-badge>
                            @endif
                        </div>
                        <h1 class="text-xl lg:text-2xl font-semibold text-slate-800 mt-2">{{ $material->title }}</h1>
                        <div class="mt-2"><x-rating-summary :average="$ratingAverage" :count="$ratingCount" /></div>
                        <p class="text-sm text-slate-500 mt-3 leading-relaxed">{{ $material->description ?: 'Chưa có mô tả chi tiết.' }}</p>
                    </div>
                </div>

                @if (count($toc) > 0)
                    <div class="bg-white rounded-2xl border border-slate-200 p-5">
                        <h2 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span>📑</span> Mục lục</h2>
                        <div class="divide-y divide-slate-100">
                            @foreach ($toc as $i => $chap)
                                <div class="flex items-center gap-3 py-3">
                                    <span class="w-7 h-7 rounded-full bg-rose-50 text-rose-600 text-xs font-semibold flex items-center justify-center shrink-0">{{ $i + 1 }}</span>
                                    <p class="text-sm text-slate-700">{{ $chap['title'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5 h-fit sticky top-6">
                <p class="text-2xl font-semibold text-slate-800">{{ $material->price > 0 ? number_format($material->price).'đ' : 'Miễn phí' }}</p>
                <p class="text-sm text-slate-400 mb-4">Bản mềm — dùng ngay sau khi kích hoạt</p>

                @if ($material->has_print_option)
                    <p class="flex items-center gap-2 text-sm text-slate-600 mb-4 px-3 py-2.5 rounded-lg border border-slate-200">📦 Có tùy chọn mua kèm bản in</p>
                @endif

                @if ($owned)
                    <p class="flex items-center justify-center gap-2 text-center px-4 py-2.5 rounded-lg bg-emerald-50 text-emerald-700 text-sm font-medium">✓ Bạn đã sở hữu tài liệu này</p>
                @elseif (auth()->check())
                    <a href="{{ route('access.checkout', $material->id) }}" class="block text-center px-4 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium">
                        Đặt đơn / Mua quyền
                    </a>
                @else
                    <a href="{{ route('login') }}" class="block text-center px-4 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium">
                        Đăng nhập để mua quyền
                    </a>
                @endif

                <div class="mt-4 pt-4 border-t border-slate-100 space-y-2 text-xs text-slate-400">
                    <p class="flex items-center gap-2">🔒 Thanh toán an toàn qua VNPAY hoặc chuyển khoản</p>
                    <p class="flex items-center gap-2">📱 Đọc mọi nơi — web, học trên điện thoại</p>
                </div>
            </div>
        </div>
    </div>
@endsection
