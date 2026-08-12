{{--
  Route: register (GET) / POST register.
  Frame: ACC-01. Vai trò ban đầu do người dùng chọn (3.1) — chỉ Học sinh/
  Phụ huynh/Giáo viên được tự đăng ký; Admin/Editor/Super Admin KHÔNG có ở
  đây, chỉ tạo được qua khu quản trị (App\Services\Admin\UserService), xem
  App\Services\Auth\AuthService::SELF_REGISTERABLE_ROLES. Chọn Giáo viên sẽ
  tạo hồ sơ ở trạng thái "Chờ duyệt" (3.3) — vào thẳng hàng đợi
  admin.teacher-approvals, chưa mua/dạy được gì cho tới khi Admin duyệt.
--}}
@extends('layouts.guest')

@section('title', 'Đăng ký')

@section('content')
<div class="max-w-lg mx-auto px-4 py-16" x-data="{ role: '{{ old('role', 'student') }}' }">
    <div class="bg-white rounded-2xl border border-slate-200 p-8">
        <h1 class="text-xl font-semibold text-slate-800 mb-1">Đăng ký</h1>
        <p class="text-sm text-slate-500 mb-6">Tạo tài khoản để bắt đầu học, dạy hoặc theo dõi con.</p>

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
                <label class="block text-sm font-medium text-slate-600 mb-2">Bạn đăng ký với vai trò gì?</label>
                <div class="grid grid-cols-3 gap-2">
                    <label class="flex flex-col items-center gap-1.5 px-2 py-3 rounded-xl border-2 cursor-pointer text-center"
                           :class="role === 'student' ? 'border-rose-500 bg-rose-50' : 'border-slate-200'">
                        <input type="radio" name="role" value="student" x-model="role" class="hidden">
                        <span class="text-xl">🧑‍🎓</span>
                        <span class="text-xs font-medium text-slate-700">Học sinh</span>
                    </label>
                    <label class="flex flex-col items-center gap-1.5 px-2 py-3 rounded-xl border-2 cursor-pointer text-center"
                           :class="role === 'teacher' ? 'border-rose-500 bg-rose-50' : 'border-slate-200'">
                        <input type="radio" name="role" value="teacher" x-model="role" class="hidden">
                        <span class="text-xl">🍎</span>
                        <span class="text-xs font-medium text-slate-700">Giáo viên</span>
                    </label>
                    <label class="flex flex-col items-center gap-1.5 px-2 py-3 rounded-xl border-2 cursor-pointer text-center"
                           :class="role === 'parent' ? 'border-rose-500 bg-rose-50' : 'border-slate-200'">
                        <input type="radio" name="role" value="parent" x-model="role" class="hidden">
                        <span class="text-xl">👨‍👩‍👧</span>
                        <span class="text-xs font-medium text-slate-700">Phụ huynh</span>
                    </label>
                </div>
                <p class="text-xs text-slate-400 mt-2" x-show="role === 'teacher'" x-cloak>
                    Hồ sơ giáo viên cần được Admin duyệt (3.3) trước khi bạn mua/kích hoạt quyền dạy và gắn học liệu riêng tư vào lớp.
                </p>
            </div>

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

            {{-- Chỉ hiện khi chọn Giáo viên — giúp hồ sơ chờ duyệt có sẵn thông tin để Admin xem xét. --}}
            <div x-show="role === 'teacher'" x-cloak class="space-y-4 pt-2 border-t border-slate-100">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="subjects">Môn/chuyên môn dạy</label>
                    <input id="subjects" name="subjects" type="text" value="{{ old('subjects') }}"
                           placeholder="VD: Tin học, Toán" class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                    <p class="text-xs text-slate-400 mt-1">Nhiều môn thì ngăn cách bằng dấu phẩy.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="bio">Giới thiệu ngắn (không bắt buộc)</label>
                    <textarea id="bio" name="bio" rows="3" class="w-full rounded-lg border border-slate-200 text-sm p-2.5"
                              placeholder="Kinh nghiệm giảng dạy, thành tích...">{{ old('bio') }}</textarea>
                </div>
            </div>

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
