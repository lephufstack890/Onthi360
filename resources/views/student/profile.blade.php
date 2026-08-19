@extends('layouts.student')

@section('title', 'Hồ sơ')
@section('page-title', 'Hồ sơ')

@section('content')
    @php
        $user = $user ?? auth()->user();
        $parentLinks = $parentLinks ?? collect();
        $regionOptions = \App\Support\VietnamProvinces::regionOptions();
        $provinceOptions = \App\Support\VietnamProvinces::options();
    @endphp

    @if (session('status') === 'profile-updated')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu thông tin hồ sơ.'])
    @endif
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl border border-slate-200 p-6 text-center">
            <div class="w-20 h-20 rounded-full bg-gradient-to-br from-rose-200 to-amber-100 mx-auto mb-3 flex items-center justify-center text-2xl">
                {{ mb_substr($user->name ?? 'H', 0, 1) }}
            </div>
            <h2 class="font-semibold text-slate-800">{{ $user->name ?? 'Học sinh' }}</h2>
            <p class="text-sm text-slate-400">{{ $user->email ?? '' }}</p>
            <button type="button" class="mt-4 px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium">Đổi ảnh đại diện</button>
        </div>

        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h3 class="font-medium text-slate-700 mb-4">Thông tin cá nhân</h3>
                <form method="POST" action="{{ route('student.profile.update') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block text-sm text-slate-600 mb-1" for="name">Họ tên</label>
                        <input id="name" name="name" type="text" value="{{ old('name', $user->name ?? '') }}" required maxlength="255"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
                    <div>
                        <label class="block text-sm text-slate-600 mb-1" for="email">Email</label>
                        <input id="email" type="email" value="{{ $user->email ?? '' }}" class="w-full rounded-lg border border-slate-200 text-sm p-2.5" disabled>
                    </div>
                    <div>
                        <label class="block text-sm text-slate-600 mb-1" for="phone">Số điện thoại</label>
                        <input id="phone" name="phone" type="text" value="{{ old('phone', $user->phone ?? '') }}"
                               placeholder="Chưa cập nhật" class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
                    <div>
                        <label class="block text-sm text-slate-600 mb-1" for="province">Tỉnh/thành</label>
                        <x-select id="province" name="province">
                            <option value="">— Chưa chọn —</option>
                            @foreach ($provinceOptions as $p)
                                <option value="{{ $p }}" @selected(old('province', $user->province ?? '') === $p)>{{ $p }}</option>
                            @endforeach
                        </x-select>
                    </div>
                    <div>
                        <label class="block text-sm text-slate-600 mb-1" for="region">Khu vực</label>
                        <x-select id="region" name="region">
                            <option value="">— Chưa chọn —</option>
                            @foreach ($regionOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('region', $user->region ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </x-select>
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="mt-2 px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">Lưu thay đổi</button>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h3 class="font-medium text-slate-700 mb-2 flex items-center gap-2"><span>👨‍👩‍👧</span> Phụ huynh liên kết</h3>
                <p class="text-sm text-slate-400 mb-3">Phụ huynh đã liên kết sẽ xem được lịch, điểm danh, tiến độ và kết quả của bạn.</p>
                @forelse ($parentLinks as $link)
                    <div class="flex items-center justify-between bg-slate-50 rounded-lg px-4 py-3 text-sm mb-2">
                        <span class="text-slate-600">{{ $link->parent->name ?? 'Phụ huynh' }}</span>
                        <x-status-badge :tone="$link->isVerified() ? 'success' : 'warning'">{{ $link->isVerified() ? 'Đã xác minh' : 'Chờ xác minh' }}</x-status-badge>
                    </div>
                @empty
                    <div class="flex items-center justify-between bg-slate-50 rounded-lg px-4 py-3 text-sm">
                        <span class="text-slate-600">Chưa có phụ huynh liên kết</span>
                        <button type="button" class="text-rose-600 font-medium">Tạo mã liên kết</button>
                    </div>
                @endforelse
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h3 class="font-medium text-slate-700 mb-2 flex items-center gap-2"><span>⭐</span> Đánh giá của tôi</h3>
                <a href="{{ route('reviews.myReviews') }}" class="text-sm text-rose-600 font-medium">Xem các đánh giá tôi đã viết ›</a>
            </div>
        </div>
    </div>
@endsection
