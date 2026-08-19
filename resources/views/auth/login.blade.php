@extends('layouts.guest')

@section('title', 'Đăng nhập')

@section('content')
<div class="bg-gradient-to-br from-sky-50 via-white to-rose-50 min-h-[calc(100vh-4rem)]">
    <div class="max-w-6xl mx-auto px-4 py-12 lg:py-20">
        <div class="grid lg:grid-cols-2 gap-10 lg:gap-16 items-center">
            <div class="hidden lg:block">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white text-rose-600 text-xs font-medium shadow-sm">👋 Chào mừng trở lại</span>
                <h1 class="text-3xl font-semibold text-slate-800 mt-5 leading-snug">
                    Học, dạy và theo dõi tiến độ<br>— tất cả trong một nơi.
                </h1>
                <p class="text-slate-500 mt-4 max-w-md leading-relaxed">
                    Đăng nhập để tiếp tục lộ trình học của bạn, quản lý lớp đang dạy, hoặc theo dõi kết quả của con.
                </p>

                <div class="space-y-3 mt-8">
                    <div class="flex items-center gap-3">
                        <x-icon-tile emoji="✅" tone="emerald" />
                        <p class="text-sm text-slate-600">Chấm bài lập trình, trắc nghiệm, điền đáp án tự động</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <x-icon-tile emoji="📊" tone="sky" />
                        <p class="text-sm text-slate-600">Tiến độ và kết quả minh bạch, xem lại bất cứ lúc nào</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <x-icon-tile emoji="🔒" tone="violet" />
                        <p class="text-sm text-slate-600">Bảo vệ dữ liệu học sinh, mọi thao tác quản trị đều có lý do rõ ràng</p>
                    </div>
                </div>

                <div class="grid grid-cols-4 gap-4 mt-10 pt-8 border-t border-slate-200 max-w-md">
                    <div><p class="text-lg font-semibold text-rose-600">12k+</p><p class="text-xs text-slate-400 mt-0.5">học sinh</p></div>
                    <div><p class="text-lg font-semibold text-rose-600">340+</p><p class="text-xs text-slate-400 mt-0.5">giáo viên</p></div>
                    <div><p class="text-lg font-semibold text-rose-600">98%</p><p class="text-xs text-slate-400 mt-0.5">hài lòng</p></div>
                    <div><p class="text-lg font-semibold text-rose-600">24/7</p><p class="text-xs text-slate-400 mt-0.5">luyện tập</p></div>
                </div>
            </div>

            {{-- Form đăng nhập --}}
            <div class="w-full max-w-md mx-auto" x-data="{ email: '{{ old('email') }}', password: '', showPassword: false }">
                <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-8">
                    <h2 class="text-xl font-semibold text-slate-800">Đăng nhập</h2>
                    <p class="text-sm text-slate-500 mt-1 mb-6">Vào Ôn Thi 360 để học, dạy hoặc theo dõi tiến độ của con.</p>

                    @if ($errors->any())
                        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
                    @endif

                    <form method="POST" action="{{ route('login') }}" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1" for="email">Email</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">📧</span>
                                <input id="email" name="email" type="email" x-model="email" required autofocus
                                       class="w-full rounded-lg border border-slate-200 text-sm py-2.5 pl-9 pr-3 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1" for="password">Mật khẩu</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">🔒</span>
                                <input id="password" name="password" x-model="password" :type="showPassword ? 'text' : 'password'" required
                                       class="w-full rounded-lg border border-slate-200 text-sm py-2.5 pl-9 pr-10 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                                <button type="button" @click="showPassword = !showPassword"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-sm">
                                    <span x-text="showPassword ? '🙈' : '👁️'"></span>
                                </button>
                            </div>
                        </div>
                        <label class="flex items-center gap-2 text-sm text-slate-500 cursor-pointer">
                            <input type="checkbox" name="remember" class="rounded border-slate-300 text-rose-600 focus:ring-rose-200"> Ghi nhớ đăng nhập
                        </label>
                        <button type="submit" class="w-full px-4 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">
                            Đăng nhập
                        </button>
                    </form>

                    <p class="text-sm text-slate-500 mt-6 text-center">
                        Chưa có tài khoản?
                        <a href="{{ route('register') }}" class="text-rose-600 font-medium">Đăng ký ngay</a>
                    </p>

                    <div class="mt-6 pt-6 border-t border-slate-100">
                        <div class="flex flex-wrap gap-2">
                            <button type="button" @click="email = 'admin@onthi360.test'; password = 'password'"
                                    class="px-2.5 py-1 rounded-full border border-slate-200 text-xs text-slate-500 hover:border-rose-200 hover:text-rose-600">🛠️ Admin</button>
                            <button type="button" @click="email = 'teacher@onthi360.test'; password = 'password'"
                                    class="px-2.5 py-1 rounded-full border border-slate-200 text-xs text-slate-500 hover:border-rose-200 hover:text-rose-600">🍎 Giáo viên</button>
                            <button type="button" @click="email = 'student@onthi360.test'; password = 'password'"
                                    class="px-2.5 py-1 rounded-full border border-slate-200 text-xs text-slate-500 hover:border-rose-200 hover:text-rose-600">🧑‍🎓 Học sinh</button>
                        </div>
                    </div>
                </div>
                <p class="text-center text-xs text-slate-400 mt-6">🔒 Thông tin đăng nhập của bạn được bảo mật.</p>
            </div>
        </div>
    </div>
</div>
@endsection
