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
        <div class="bg-white rounded-3xl border border-sky-100 p-6 text-center">
            <div class="w-20 h-20 rounded-full bg-gradient-to-br from-sky-200 to-blue-100 mx-auto mb-3 flex items-center justify-center text-2xl">
                {{ mb_substr($user->name ?? 'H', 0, 1) }}
            </div>
            <h2 class="font-semibold text-slate-800">{{ $user->name ?? 'Học sinh' }}</h2>
            <p class="text-[13px] text-slate-400">{{ $user->email ?? '' }}</p>
            <button type="button" class="mt-4 px-4 py-2 rounded-xl border border-sky-100 text-slate-600 text-[13px] font-medium">Đổi ảnh đại diện</button>
        </div>

        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                <h3 class="font-medium text-slate-700 mb-4">Thông tin cá nhân</h3>
                <form method="POST" action="{{ route('student.profile.update') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block text-[13px] text-slate-600 mb-1" for="name">Họ tên</label>
                        <input id="name" name="name" type="text" value="{{ old('name', $user->name ?? '') }}" required maxlength="255"
                               class="admin-input">
                    </div>
                    <div>
                        <label class="block text-[13px] text-slate-600 mb-1" for="email">Email</label>
                        <input id="email" type="email" value="{{ $user->email ?? '' }}" class="admin-input" disabled>
                    </div>
                    <div>
                        <label class="block text-[13px] text-slate-600 mb-1" for="phone">Số điện thoại</label>
                        <input id="phone" name="phone" type="text" value="{{ old('phone', $user->phone ?? '') }}"
                               placeholder="Chưa cập nhật" class="admin-input">
                    </div>
                    <div>
                        <label class="block text-[13px] text-slate-600 mb-1" for="province">Tỉnh/thành</label>
                        <x-ws.select id="province" name="province">
                            <option value="">— Chưa chọn —</option>
                            @foreach ($provinceOptions as $p)
                                <option value="{{ $p }}" @selected(old('province', $user->province ?? '') === $p)>{{ $p }}</option>
                            @endforeach
                        </x-ws.select>
                    </div>
                    <div>
                        <label class="block text-[13px] text-slate-600 mb-1" for="region">Khu vực</label>
                        <x-ws.select id="region" name="region">
                            <option value="">— Chưa chọn —</option>
                            @foreach ($regionOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('region', $user->region ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </x-ws.select>
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="mt-2 inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700">Lưu thay đổi</button>
                    </div>
                </form>
            </div>

            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                <h3 class="font-medium text-slate-700 mb-2 flex items-center gap-2"><span>👨‍👩‍👧</span> Phụ huynh liên kết</h3>
                <p class="text-[13px] text-slate-400 mb-3">Phụ huynh đã liên kết sẽ xem được lịch, điểm danh, tiến độ và kết quả của bạn.</p>
                @forelse ($parentLinks as $link)
                    <div class="flex items-center justify-between bg-slate-50 rounded-xl px-4 py-3 text-[13px] mb-2">
                        <span class="text-slate-600">{{ $link->parent->name ?? 'Phụ huynh' }}</span>
                        <x-ws.badge :tone="$link->isVerified() ? 'success' : 'warning'">{{ $link->isVerified() ? 'Đã xác minh' : 'Chờ xác minh' }}</x-ws.badge>
                    </div>
                @empty
                    <div class="flex items-center justify-between bg-slate-50 rounded-xl px-4 py-3 text-[13px]">
                        <span class="text-slate-600">Chưa có phụ huynh liên kết</span>
                        <button type="button" class="text-blue-600 font-medium">Tạo mã liên kết</button>
                    </div>
                @endforelse
            </div>

            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                <h3 class="font-medium text-slate-700 mb-2 flex items-center gap-2"><span><x-lucide name="star" class="h-4 w-4" /></span> Đánh giá của tôi</h3>
                <a href="{{ route('reviews.myReviews') }}" class="text-[13px] text-blue-600 font-medium">Xem các đánh giá tôi đã viết ›</a>
            </div>
        </div>
    </div>
@endsection
