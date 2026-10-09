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

    @include('partials.personal-profile', [
        'user' => $user,
        'action' => route('parent.profile.update'),
        'roleLabel' => 'Phụ huynh',
        'withLocation' => true,
        'profileUrl' => route('parent.profile'),
        'passwordUrl' => route('parent.password.edit'),
    ])

    <div class="mt-6 space-y-6">
            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                <h3 class="font-medium text-slate-700 mb-2 flex items-center gap-2"><span><x-lucide name="users" class="h-4 w-4" /></span> Con đã liên kết</h3>
                <p class="text-[13px] text-slate-400 mb-3">Chỉ con đã được admin xác minh mới xem được lịch, điểm danh, kết quả (10.3).</p>
                @forelse ($children as $link)
                    <div class="flex items-center justify-between bg-slate-50 rounded-xl px-4 py-3 text-[13px] mb-2">
                        <span class="text-slate-600">{{ $link->student->name ?? 'Học sinh' }}</span>
                        <x-ws.badge :tone="$link->status->value === 'verified' ? 'success' : ($link->status->value === 'pending' ? 'warning' : 'neutral')">
                            {{ $link->status->value === 'verified' ? 'Đã xác minh' : ($link->status->value === 'pending' ? 'Chờ xác minh' : 'Đã hủy liên kết') }}
                        </x-ws.badge>
                    </div>
                @empty
                    <div class="flex items-center justify-between bg-slate-50 rounded-xl px-4 py-3 text-[13px]">
                        <span class="text-slate-600">Chưa liên kết con nào</span>
                        <a href="{{ route('parent.children.index') }}" class="text-blue-600 font-medium">Gửi yêu cầu ›</a>
                    </div>
                @endforelse
            </div>

            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                <h3 class="font-medium text-slate-700 mb-2 flex items-center gap-2"><span><x-lucide name="star" class="h-4 w-4" /></span> Đánh giá của tôi</h3>
                <a href="{{ route('reviews.myReviews') }}" class="text-[13px] text-blue-600 font-medium">Xem các đánh giá tôi đã viết ›</a>
            </div>
    </div>
@endsection
