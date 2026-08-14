{{--
  Route: materials.show | Frame: PUB-06
  Spec: 7.5 (màn mua theo vai trò), 9 (rating/review).
  Dữ liệu thật do App\Http\Controllers\Public\MaterialController truyền vào qua
  App\Services\Public\MaterialService::showData() — ảnh bìa dùng picsum.photos tạm (chưa
  có cover_image_path thật được upload cho phần lớn tài liệu). Lựa chọn quyền theo vai trò
  (học cá nhân / dùng để dạy) do chính access.checkout xử lý (App\Services\Access\
  AccessService::checkoutData() đã tính $canTeach) — trang này chỉ cần 1 CTA "Đặt đơn".
--}}
@extends('layouts.guest')

@section('title', 'Chi tiết tài liệu')

@section('content')
    @php
        $toc = $toc ?? [];
        $ratingAverage = $ratingAverage ?? null;
        $ratingCount = $ratingCount ?? 0;
        $badge = $material->price > 0 ? ['Cần kích hoạt', 'warning'] : ['Công khai', 'info'];
    @endphp

    <div class="max-w-6xl mx-auto px-4 py-10">
        <a href="{{ route('materials.index') }}" class="text-sm text-slate-500 mb-6 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Tài liệu</a>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-6">
                <div class="flex flex-col sm:flex-row gap-5">
                    <img src="https://picsum.photos/seed/{{ \Illuminate\Support\Str::slug($material->title) }}/320/420" alt=""
                         class="w-40 sm:w-48 rounded-2xl shadow-md object-cover aspect-[3/4] mx-auto sm:mx-0">
                    <div class="flex-1">
                        <x-status-badge :tone="$badge[1]">{{ $badge[0] }}</x-status-badge>
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

                @auth
                    <a href="{{ route('access.checkout', $material->id) }}" class="block text-center px-4 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium">
                        Đặt đơn / Mua quyền
                    </a>
                @else
                    <a href="{{ route('login') }}" class="block text-center px-4 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium">
                        Đăng nhập để mua quyền
                    </a>
                @endauth

                <div class="mt-4 pt-4 border-t border-slate-100 space-y-2 text-xs text-slate-400">
                    <p class="flex items-center gap-2">🔒 Thanh toán an toàn qua VNPAY hoặc chuyển khoản</p>
                    <p class="flex items-center gap-2">📱 Đọc mọi nơi — web, học trên điện thoại</p>
                </div>
            </div>
        </div>
    </div>
@endsection
