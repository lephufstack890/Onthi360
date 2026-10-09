@extends('layouts.admin')

@section('title', 'Hồ sơ của tôi')
@section('page-title', 'Hồ sơ của tôi')

@section('content')
    @php
        $user = $user ?? auth()->user();
    @endphp

    @if (session('status') === 'profile-updated')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu thông tin hồ sơ.'])
    @endif

    @include('partials.personal-profile', [
        'user' => $user,
        'action' => route('admin.profile.update'),
        'roleLabel' => 'Quản trị viên',
        'withLocation' => false,
        'profileUrl' => route('admin.profile.show'),
        'passwordUrl' => route('admin.password.edit'),
    ])

    <div class="mt-6 space-y-6">
        <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
            <h3 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span><x-lucide name="shield-check" class="h-4 w-4" /></span> Vai trò được gán</h3>
            <div class="flex items-center gap-1.5 flex-wrap">
                @forelse (($user->roles ?? collect()) as $role)
                    <x-ws.badge tone="info">{{ $role->label ?? $role->name }}</x-ws.badge>
                @empty
                    <x-ws.badge tone="neutral">Chưa gán vai trò</x-ws.badge>
                @endforelse
            </div>
            <p class="text-xs text-slate-400 leading-relaxed mt-3"><x-lucide name="lock" class="inline h-3.5 w-3.5 shrink-0 align-[-2px]" /> Mọi thay đổi hồ sơ ở trang này chỉ áp dụng cho tài khoản của chính bạn.</p>
        </div>
    </div>
@endsection
