{{--
  Route: materials.show | Frame: PUB-06
  Spec: 7.5 (màn mua theo vai trò), 9 (rating/review).
  TODO controller: truyền $material thật, mục lục, lựa chọn quyền (học cá nhân / dùng để dạy theo vai trò user).
--}}
@extends('layouts.guest')

@section('title', 'Chi tiết tài liệu')

@section('content')
    @php
        $material = ['title' => 'Sách: Ôn thi Tin học 10', 'price' => '199.000đ', 'toc' => ['Chương 1: Nhập môn', 'Chương 2: Cấu trúc điều khiển', 'Chương 3: Hàm và đệ quy']];
    @endphp

    <div class="max-w-5xl mx-auto px-4 py-10">
        <a href="{{ route('materials.index') }}" class="text-sm text-slate-500 mb-4 inline-block">‹ Quay lại Tài liệu</a>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <x-page-header :title="$material['title']" />
                <x-rating-summary :average="4.7" :count="88" />

                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <h2 class="font-medium text-slate-700 mb-3">Mục lục</h2>
                    <ul class="list-disc list-inside text-sm text-slate-500 space-y-1">
                        @foreach ($material['toc'] as $chap)
                            <li>{{ $chap }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <p class="text-2xl font-semibold text-slate-800 mb-1">{{ $material['price'] }}</p>
                <p class="text-sm text-slate-400 mb-4">Bản mềm — có thể chọn mua kèm bản in</p>
                <label class="flex items-center gap-2 text-sm text-slate-600 mb-4">
                    <input type="checkbox"> Mua kèm bản in (+50.000đ)
                </label>
                <a href="{{ route('login') }}" class="block text-center px-4 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium">
                    Mua quyền học cá nhân
                </a>
                {{-- TODO: nếu user hiện tại là giáo viên đã duyệt, hiện thêm CTA "Mua quyền dùng để dạy" (7.5) --}}
            </div>
        </div>
    </div>
@endsection
