{{--
  Route: student.profile | Frame: STU-11 (phần hồ sơ + liên kết phụ huynh)
  TODO controller: truyền $user thật + danh sách liên kết phụ huynh (ParentLink).
--}}
@extends('layouts.student')

@section('title', 'Hồ sơ')
@section('page-title', 'Hồ sơ')

@section('content')
    {{-- Dữ liệu thật do App\Http\Controllers\Student\ProfileController truyền vào. --}}
    @php
        $user = $user ?? auth()->user();
        $parentLinks = $parentLinks ?? collect();
    @endphp

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
                <form class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-slate-600 mb-1">Họ tên</label>
                        <input type="text" value="{{ $user->name ?? '' }}" class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                    </div>
                    <div>
                        <label class="block text-sm text-slate-600 mb-1">Email</label>
                        <input type="email" value="{{ $user->email ?? '' }}" class="w-full rounded-lg border border-slate-200 text-sm p-2.5" disabled>
                    </div>
                    <div>
                        <label class="block text-sm text-slate-600 mb-1">Số điện thoại</label>
                        <input type="text" class="w-full rounded-lg border border-slate-200 text-sm p-2.5" placeholder="Chưa cập nhật">
                    </div>
                    <div>
                        <label class="block text-sm text-slate-600 mb-1">Trường/lớp hiện tại</label>
                        <input type="text" class="w-full rounded-lg border border-slate-200 text-sm p-2.5" placeholder="Chưa cập nhật">
                    </div>
                </form>
                <button type="button" class="mt-4 px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">Lưu thay đổi</button>
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
