@extends('layouts.guest')

@section('title', 'Đăng ký')

@section('content')
<div class="bg-gradient-to-br from-rose-50 via-white to-sky-50 min-h-[calc(100vh-4rem)]" x-data="{ role: '{{ old('role', 'student') }}' }">
    <div class="max-w-6xl mx-auto px-4 py-12 lg:py-20">
        <div class="grid lg:grid-cols-2 gap-10 lg:gap-16 items-center">
            <div class="hidden lg:block">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white text-rose-600 text-xs font-medium shadow-sm">✨ Tham gia Ôn Thi 360</span>

                <div x-show="role === 'student'" x-cloak>
                    <h1 class="text-3xl font-semibold text-slate-800 mt-5 leading-snug">Bắt đầu luyện tập ngay<br>hôm nay.</h1>
                    <p class="text-slate-500 mt-4 max-w-md leading-relaxed">Vào lớp bằng mã giáo viên cung cấp, hoặc luyện đề công khai ngay — không cần chờ ai duyệt.</p>
                    <div class="space-y-3 mt-8">
                        <div class="flex items-center gap-3"><x-icon-tile emoji="📝" tone="rose" /><p class="text-sm text-slate-600">Luyện đề lập trình, trắc nghiệm, điền đáp án — chấm tự động</p></div>
                        <div class="flex items-center gap-3"><x-icon-tile emoji="🎯" tone="rose" /><p class="text-sm text-slate-600">Vào lớp bằng mã giáo viên cung cấp, theo lộ trình rõ ràng</p></div>
                        <div class="flex items-center gap-3"><x-icon-tile emoji="📊" tone="rose" /><p class="text-sm text-slate-600">Theo dõi tiến độ và kết quả của chính mình bất cứ lúc nào</p></div>
                    </div>
                </div>

                <div x-show="role === 'teacher'" x-cloak>
                    <h1 class="text-3xl font-semibold text-slate-800 mt-5 leading-snug">Mở lớp dạy học<br>chuyên nghiệp.</h1>
                    <p class="text-slate-500 mt-4 max-w-md leading-relaxed">Tạo lớp, giao bài kiểm tra có hạn nộp, nhập đề nhanh bằng Word/PDF/OCR.</p>
                    <div class="space-y-3 mt-8">
                        <div class="flex items-center gap-3"><x-icon-tile emoji="🍎" tone="sky" /><p class="text-sm text-slate-600">Tạo lớp, gắn học liệu còn quyền dạy, giao bài có hạn nộp</p></div>
                        <div class="flex items-center gap-3"><x-icon-tile emoji="⏳" tone="sky" /><p class="text-sm text-slate-600">Hồ sơ cần Admin duyệt trước khi được dạy chính thức (3.3)</p></div>
                        <div class="flex items-center gap-3"><x-icon-tile emoji="📊" tone="sky" /><p class="text-sm text-slate-600">Theo dõi tiến độ, điểm số từng học sinh trong lớp</p></div>
                    </div>
                </div>

                <div x-show="role === 'parent'" x-cloak>
                    <h1 class="text-3xl font-semibold text-slate-800 mt-5 leading-snug">Đồng hành cùng con<br>trong học tập.</h1>
                    <p class="text-slate-500 mt-4 max-w-md leading-relaxed">Liên kết tài khoản của con để theo dõi lịch học, điểm danh và kết quả gần đây.</p>
                    <div class="space-y-3 mt-8">
                        <div class="flex items-center gap-3"><x-icon-tile emoji="👨‍👩‍👧" tone="emerald" /><p class="text-sm text-slate-600">Nhận mã liên kết từ con để theo dõi tài khoản học sinh</p></div>
                        <div class="flex items-center gap-3"><x-icon-tile emoji="🔔" tone="emerald" /><p class="text-sm text-slate-600">Nhận thông báo khi quyền học của con sắp hết hạn</p></div>
                        <div class="flex items-center gap-3"><x-icon-tile emoji="📅" tone="emerald" /><p class="text-sm text-slate-600">Xem lịch học, điểm danh và kết quả gần đây của con</p></div>
                    </div>
                </div>

                <div class="grid grid-cols-4 gap-4 mt-10 pt-8 border-t border-slate-200 max-w-md">
                    <div><p class="text-lg font-semibold text-rose-600">12k+</p><p class="text-xs text-slate-400 mt-0.5">học sinh</p></div>
                    <div><p class="text-lg font-semibold text-rose-600">340+</p><p class="text-xs text-slate-400 mt-0.5">giáo viên</p></div>
                    <div><p class="text-lg font-semibold text-rose-600">98%</p><p class="text-xs text-slate-400 mt-0.5">hài lòng</p></div>
                    <div><p class="text-lg font-semibold text-rose-600">24/7</p><p class="text-xs text-slate-400 mt-0.5">luyện tập</p></div>
                </div>
            </div>

            {{-- Form đăng ký --}}
            <div class="w-full max-w-md mx-auto">
                <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-8">
                    <h2 class="text-xl font-semibold text-slate-800">Tạo tài khoản</h2>
                    <p class="text-sm text-slate-500 mt-1 mb-6">Chỉ mất chưa đến 1 phút để bắt đầu.</p>

                    @if ($errors->any())
                        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
                    @endif

                    <form method="POST" action="{{ route('register') }}" class="space-y-4" x-data="{ showPassword: false, showPasswordConfirm: false }">
                        @csrf

                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-2">Bạn đăng ký với vai trò gì?</label>
                            <div class="grid grid-cols-3 gap-2">
                                <label class="flex flex-col items-center gap-1.5 px-2 py-3 rounded-xl border-2 cursor-pointer text-center transition"
                                       :class="role === 'student' ? 'border-rose-500 bg-rose-50 shadow-sm' : 'border-slate-200 hover:border-rose-200'">
                                    <input type="radio" name="role" value="student" x-model="role" class="hidden">
                                    <span class="text-xl">🧑‍🎓</span>
                                    <span class="text-xs font-medium text-slate-700">Học sinh</span>
                                </label>
                                <label class="flex flex-col items-center gap-1.5 px-2 py-3 rounded-xl border-2 cursor-pointer text-center transition"
                                       :class="role === 'teacher' ? 'border-sky-500 bg-sky-50 shadow-sm' : 'border-slate-200 hover:border-sky-200'">
                                    <input type="radio" name="role" value="teacher" x-model="role" class="hidden">
                                    <span class="text-xl">🍎</span>
                                    <span class="text-xs font-medium text-slate-700">Giáo viên</span>
                                </label>
                                <label class="flex flex-col items-center gap-1.5 px-2 py-3 rounded-xl border-2 cursor-pointer text-center transition"
                                       :class="role === 'parent' ? 'border-emerald-500 bg-emerald-50 shadow-sm' : 'border-slate-200 hover:border-emerald-200'">
                                    <input type="radio" name="role" value="parent" x-model="role" class="hidden">
                                    <span class="text-xl">👨‍👩‍👧</span>
                                    <span class="text-xs font-medium text-slate-700">Phụ huynh</span>
                                </label>
                            </div>
                            <p class="text-xs text-slate-400 mt-2 flex items-center gap-1" x-show="role === 'teacher'" x-cloak>
                                ⏳ Hồ sơ giáo viên cần được Admin duyệt (3.3) trước khi bạn mua/kích hoạt quyền dạy và gắn học liệu riêng tư vào lớp.
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1" for="name">Họ tên</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">🙂</span>
                                <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus
                                       class="w-full rounded-lg border border-slate-200 text-sm py-2.5 pl-9 pr-3 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1" for="email">Email</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">📧</span>
                                <input id="email" name="email" type="email" value="{{ old('email') }}" required
                                       class="w-full rounded-lg border border-slate-200 text-sm py-2.5 pl-9 pr-3 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1" for="password">Mật khẩu</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">🔒</span>
                                <input id="password" name="password" :type="showPassword ? 'text' : 'password'" required
                                       class="w-full rounded-lg border border-slate-200 text-sm py-2.5 pl-9 pr-10 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                                <button type="button" @click="showPassword = !showPassword"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-sm">
                                    <span x-text="showPassword ? '🙈' : '👁️'"></span>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1" for="password_confirmation">Nhập lại mật khẩu</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">🔒</span>
                                <input id="password_confirmation" name="password_confirmation" :type="showPasswordConfirm ? 'text' : 'password'" required
                                       class="w-full rounded-lg border border-slate-200 text-sm py-2.5 pl-9 pr-10 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                                <button type="button" @click="showPasswordConfirm = !showPasswordConfirm"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-sm">
                                    <span x-text="showPasswordConfirm ? '🙈' : '👁️'"></span>
                                </button>
                            </div>
                        </div>

                        {{-- Chỉ hiện khi chọn Giáo viên — giúp hồ sơ chờ duyệt có sẵn thông tin để Admin xem xét. --}}
                        <div x-show="role === 'teacher'" x-cloak class="space-y-4 pt-3 border-t border-slate-100">
                            <div>
                                <label class="block text-sm font-medium text-slate-600 mb-1" for="subjects">Môn/chuyên môn dạy</label>
                                <input id="subjects" name="subjects" type="text" value="{{ old('subjects') }}"
                                       placeholder="VD: Tin học, Toán" class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                                <p class="text-xs text-slate-400 mt-1">Nhiều môn thì ngăn cách bằng dấu phẩy.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-600 mb-1" for="bio">Giới thiệu ngắn (không bắt buộc)</label>
                                <textarea id="bio" name="bio" rows="3" class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition"
                                          placeholder="Kinh nghiệm giảng dạy, thành tích...">{{ old('bio') }}</textarea>
                            </div>
                        </div>

                        <button type="submit" class="w-full px-4 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">
                            Tạo tài khoản
                        </button>
                    </form>

                    <p class="text-sm text-slate-500 mt-6 text-center">
                        Đã có tài khoản?
                        <a href="{{ route('login') }}" class="text-rose-600 font-medium">Đăng nhập</a>
                    </p>
                </div>
                <p class="text-center text-xs text-slate-400 mt-6">🔒 Dữ liệu của bạn được bảo mật theo chính sách của Ôn Thi 360.</p>
            </div>
        </div>
    </div>
</div>
@endsection
