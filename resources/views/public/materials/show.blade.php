{{--
  Route: materials.show | Frame: PUB-06
  Spec: 7.5 (màn mua theo vai trò), 9 (rating/review).
  TODO controller: truyền $material thật, mục lục, lựa chọn quyền (học cá
  nhân / dùng để dạy theo vai trò user) — hiện là dữ liệu minh họa để
  dựng UI; ảnh bìa dùng picsum.photos tạm.
--}}
@extends('layouts.guest')

@section('title', 'Chi tiết tài liệu')

@section('content')
    @php
        $material = [
            'title' => 'Sách: Ôn thi Tin học 10',
            'price' => 199000,
            'printPrice' => 50000,
            'description' => 'Biên soạn theo khung năng lực Tin học lớp 10, bám sát cấu trúc đề kiểm tra định kỳ và ôn thi học sinh giỏi. Có bài tập thực hành sau mỗi chương và lời giải chi tiết.',
            'highlights' => ['12 chương, hơn 300 câu hỏi luyện tập', 'Có bản in chất lượng cao, giao tận nhà', 'Cập nhật miễn phí khi có bản chỉnh sửa mới'],
            'toc' => [
                ['title' => 'Chương 1: Nhập môn Tin học', 'lessons' => 5],
                ['title' => 'Chương 2: Cấu trúc điều khiển', 'lessons' => 7],
                ['title' => 'Chương 3: Hàm và đệ quy', 'lessons' => 6],
                ['title' => 'Chương 4: Cấu trúc dữ liệu cơ bản', 'lessons' => 8],
            ],
        ];
    @endphp

    <div class="max-w-6xl mx-auto px-4 py-10">
        <a href="{{ route('materials.index') }}" class="text-sm text-slate-500 mb-6 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Tài liệu</a>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-6">
                <div class="flex flex-col sm:flex-row gap-5">
                    <img src="https://picsum.photos/seed/{{ \Illuminate\Support\Str::slug($material['title']) }}/320/420" alt=""
                         class="w-40 sm:w-48 rounded-2xl shadow-md object-cover aspect-[3/4] mx-auto sm:mx-0">
                    <div class="flex-1">
                        <x-status-badge tone="warning">Cần kích hoạt</x-status-badge>
                        <h1 class="text-xl lg:text-2xl font-semibold text-slate-800 mt-2">{{ $material['title'] }}</h1>
                        <div class="mt-2"><x-rating-summary :average="4.7" :count="88" /></div>
                        <p class="text-sm text-slate-500 mt-3 leading-relaxed">{{ $material['description'] }}</p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <h2 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span>✨</span> Điểm nổi bật</h2>
                    <ul class="space-y-2">
                        @foreach ($material['highlights'] as $h)
                            <li class="flex items-center gap-2 text-sm text-slate-600"><span class="text-emerald-500">✓</span>{{ $h }}</li>
                        @endforeach
                    </ul>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <h2 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span>📑</span> Mục lục</h2>
                    <div class="divide-y divide-slate-100">
                        @foreach ($material['toc'] as $i => $chap)
                            <div class="flex items-center justify-between py-3">
                                <div class="flex items-center gap-3">
                                    <span class="w-7 h-7 rounded-full bg-rose-50 text-rose-600 text-xs font-semibold flex items-center justify-center shrink-0">{{ $i + 1 }}</span>
                                    <p class="text-sm text-slate-700">{{ $chap['title'] }}</p>
                                </div>
                                <span class="text-xs text-slate-400 shrink-0">{{ $chap['lessons'] }} bài</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5 h-fit sticky top-6">
                <p class="text-2xl font-semibold text-slate-800">{{ number_format($material['price']) }}đ</p>
                <p class="text-sm text-slate-400 mb-4">Bản mềm — dùng ngay sau khi kích hoạt</p>

                <label class="flex items-center justify-between gap-2 text-sm text-slate-600 mb-4 px-3 py-2.5 rounded-lg border border-slate-200 cursor-pointer">
                    <span class="flex items-center gap-2"><input type="checkbox"> Mua kèm bản in</span>
                    <span class="text-slate-400">+{{ number_format($material['printPrice']) }}đ</span>
                </label>

                <a href="{{ route('login') }}" class="block text-center px-4 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium">
                    Mua quyền học cá nhân
                </a>
                {{-- TODO: nếu user hiện tại là giáo viên đã duyệt, hiện thêm CTA "Mua quyền dùng để dạy" (7.5) --}}

                <div class="mt-4 pt-4 border-t border-slate-100 space-y-2 text-xs text-slate-400">
                    <p class="flex items-center gap-2">🔒 Thanh toán an toàn qua VNPAY hoặc chuyển khoản</p>
                    <p class="flex items-center gap-2">📱 Đọc mọi nơi — web, học trên điện thoại</p>
                </div>
            </div>
        </div>
    </div>
@endsection
