{{--
  Route: register (GET) / POST register.
  Frame: ACC-01. TODO: chọn vai trò ban đầu (Học sinh/Phụ huynh/Giáo viên) — giáo viên vào thẳng luồng 3.3 Chưa đăng ký → Chờ duyệt.
--}}
@extends('layouts.guest')

@section('title', 'Đăng ký')

@section('content')
<div class="max-w-md mx-auto px-4 py-16">
    <div class="bg-white rounded-2xl border border-slate-200 p-8">
        <h1 class="text-xl font-semibold text-slate-800 mb-1">Đăng ký</h1>
        <p class="text-sm text-slate-500 mb-6">Tạo tài khoản để bắt đầu học hoặc theo dõi con.</p>

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 mb-4 text-sm text-rose-700">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="name">Họ tên</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus
                       class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required
                       class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="password">Mật khẩu</label>
                <input id="password" name="password" type="password" required
                       class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="password_confirmation">Nhập lại mật khẩu</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required
                       class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
            </div>
            {{-- TODO: chọn vai trò ban đầu — Học sinh / Phụ huynh / Giáo viên (3.1) --}}
            <button type="submit" class="w-full px-4 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium">
                Tạo tài khoản
            </button>
        </form>

        <p class="text-sm text-slate-500 mt-6">
            Đã có tài khoản?
            <a href="{{ route('login') }}" class="text-rose-600 font-medium">Đăng nhập</a>
        </p>
    </div>
</div>
@endsection
