{{--
  Route: login (GET hiển thị form) / POST login (xử lý đăng nhập).
  Frame: ACC-01. TODO: thêm SSO/số điện thoại khi 18 mục 1 được chốt.
--}}
@extends('layouts.guest')

@section('title', 'Đăng nhập')

@section('content')
<div class="max-w-md mx-auto px-4 py-16">
    <div class="bg-white rounded-2xl border border-slate-200 p-8">
        <h1 class="text-xl font-semibold text-slate-800 mb-1">Đăng nhập</h1>
        <p class="text-sm text-slate-500 mb-6">Vào Ôn Thi 360 để học, dạy hoặc theo dõi tiến độ của con.</p>

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 mb-4 text-sm text-rose-700">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                       class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="password">Mật khẩu</label>
                <input id="password" name="password" type="password" required
                       class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-500">
                <input type="checkbox" name="remember"> Ghi nhớ đăng nhập
            </label>
            <button type="submit" class="w-full px-4 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium">
                Đăng nhập
            </button>
        </form>

        <p class="text-sm text-slate-500 mt-6">
            Chưa có tài khoản?
            <a href="{{ route('register') }}" class="text-rose-600 font-medium">Đăng ký</a>
        </p>

        <div class="mt-6 pt-6 border-t border-slate-100 text-xs text-slate-400">
            Demo (chỉ môi trường local, xem docs/SETUP.md mục 5):
            admin@onthi360.test · teacher@onthi360.test · student@onthi360.test — mật khẩu <code>password</code>.
        </div>
    </div>
</div>
@endsection
