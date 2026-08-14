{{--
  Route: parent.profile / parent.profile.update
  Spec: 10.3 — hồ sơ phụ huynh + danh sách con đã liên kết (chiều ngược lại với
  student.profile). Dữ liệu thật ($user, $children) do App\Http\Controllers\Parent\
  ProfileController truyền vào qua App\Services\Parent\ProfileService.
--}}
@extends('layouts.parent')

@section('title', 'Hồ sơ')
@section('page-title', 'Hồ sơ')

@section('content')
    @php
        $user = $user ?? auth()->user();
        $children = $children ?? collect();
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
            <div class="w-20 h-20 rounded-full bg-gradient-to-br from-violet-200 to-rose-100 mx-auto mb-3 flex items-center justify-center text-2xl">
                {{ mb_substr($user->name ?? 'P', 0, 1) }}
            </div>
            <h2 class="font-semibold text-slate-800">{{ $user->name ?? 'Phụ huynh' }}</h2>
            <p class="text-sm text-slate-400">{{ $user->email ?? '' }}</p>
        </div>

        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h3 class="font-medium text-slate-700 mb-4">Thông tin cá nhân</h3>
                <form method="POST" action="{{ route('parent.profile.update') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                <h3 class="font-medium text-slate-700 mb-2 flex items-center gap-2"><span>🧒</span> Con đã liên kết</h3>
                <p class="text-sm text-slate-400 mb-3">Chỉ con đã được admin xác minh mới xem được lịch, điểm danh, kết quả (10.3).</p>
                @forelse ($children as $link)
                    <div class="flex items-center justify-between bg-slate-50 rounded-lg px-4 py-3 text-sm mb-2">
                        <span class="text-slate-600">{{ $link->student->name ?? 'Học sinh' }}</span>
                        <x-status-badge :tone="$link->status->value === 'verified' ? 'success' : ($link->status->value === 'pending' ? 'warning' : 'neutral')">
                            {{ $link->status->value === 'verified' ? 'Đã xác minh' : ($link->status->value === 'pending' ? 'Chờ xác minh' : 'Đã hủy liên kết') }}
                        </x-status-badge>
                    </div>
                @empty
                    <div class="flex items-center justify-between bg-slate-50 rounded-lg px-4 py-3 text-sm">
                        <span class="text-slate-600">Chưa liên kết con nào</span>
                        <a href="{{ route('parent.children.index') }}" class="text-rose-600 font-medium">Gửi yêu cầu ›</a>
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
