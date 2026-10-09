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

    @include('partials.personal-profile', [
        'user' => $user,
        'action' => route('student.profile.update'),
        'roleLabel' => 'Học sinh',
        'withLocation' => true,
        'profileUrl' => route('student.profile'),
        'passwordUrl' => route('student.password.edit'),
    ])

    <div class="mt-6 space-y-6">
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
@endsection
