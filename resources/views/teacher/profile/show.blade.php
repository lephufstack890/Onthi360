{{--
  Route: teacher.profile.show | Spec: 3.3 (state machine duyệt hồ sơ giáo viên).
  Dữ liệu thật do App\Http\Controllers\Teacher\ProfileController truyền vào qua
  App\Services\Teacher\ProfileService. approval_status/is_featured/achievement_note CHỈ
  Admin đổi được (TeacherApprovalService/FeaturedTeacherService) — trang này chỉ hiển thị,
  không có form sửa các trường đó.
--}}
@extends('layouts.teacher')

@section('title', 'Hồ sơ của tôi')
@section('page-title', 'Hồ sơ của tôi')

@section('content')
    @php
        $user = $user ?? auth()->user();
        $approvalTone = match ($teacherProfile?->approval_status?->value) {
            'approved' => 'success',
            'pending' => 'warning',
            'rejected' => 'danger',
            'suspended' => 'warning',
            default => 'neutral',
        };
    @endphp

    @if (session('status') === 'profile-updated')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu thông tin hồ sơ.'])
    @elseif (session('status') === 'teacher-profile-updated')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu hồ sơ chuyên môn.'])
    @elseif (session('status') === 'password-updated')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã đổi mật khẩu thành công.'])
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Thẻ hồ sơ --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden sticky top-24">
                <div class="h-16 bg-gradient-to-br from-rose-600 to-rose-400"></div>
                <div class="px-6 pb-6 text-center">
                    <img src="https://ui-avatars.com/api/?name={{ urlencode($user->name ?? 'Giáo viên') }}&background=e11d48&color=ffffff&size=128&bold=true"
                         alt="{{ $user->name ?? 'Giáo viên' }}" class="w-20 h-20 rounded-full border-4 border-white shadow-md -mt-10 mx-auto">
                    <h2 class="font-semibold text-slate-800 mt-3">{{ $user->name ?? 'Giáo viên' }}</h2>
                    <p class="text-sm text-slate-400">{{ $user->email ?? '' }}</p>
                    <div class="flex items-center justify-center gap-1.5 flex-wrap mt-3">
                        <x-status-badge :tone="$approvalTone">{{ $teacherProfile?->approval_status?->label() ?? 'Chưa có hồ sơ' }}</x-status-badge>
                        @if ($teacherProfile?->isFeatured())
                            <x-status-badge tone="info">⭐ Giáo viên nổi bật</x-status-badge>
                        @endif
                    </div>
                </div>
                @if ($teacherProfile && in_array($teacherProfile->approval_status?->value, ['rejected', 'suspended'], true) && $teacherProfile->rejection_reason)
                    <div class="border-t border-slate-100 px-6 py-4">
                        <p class="text-xs font-medium text-slate-500 mb-1">Lý do:</p>
                        <p class="text-xs text-slate-400 leading-relaxed">{{ $teacherProfile->rejection_reason }}</p>
                    </div>
                @endif
                @if ($teacherProfile?->achievement_note)
                    <div class="border-t border-slate-100 px-6 py-4">
                        <p class="text-xs font-medium text-slate-500 mb-1">Ghi nhận thành tích:</p>
                        <p class="text-xs text-slate-400 leading-relaxed">{{ $teacherProfile->achievement_note }}</p>
                    </div>
                @endif
                <div class="border-t border-slate-100 px-6 py-4">
                    <p class="text-xs text-slate-400 leading-relaxed">🔒 Trạng thái duyệt hồ sơ và ghi nhận "giáo viên nổi bật" do Admin quản lý, không tự sửa được ở đây.</p>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">
            {{-- Thông tin cá nhân --}}
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h3 class="font-medium text-slate-700 mb-4 flex items-center gap-2"><span>🙂</span> Thông tin cá nhân</h3>

                @if ($errors->hasAny(['name', 'phone']))
                    @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', \Illuminate\Support\Arr::flatten($errors->only(['name', 'phone'])))])
                @endif

                <form method="POST" action="{{ route('teacher.profile.update') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="name">Họ tên</label>
                        <input id="name" name="name" type="text" value="{{ old('name', $user->name ?? '') }}" required
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="email">Email</label>
                        <input id="email" type="email" value="{{ $user->email ?? '' }}" disabled
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 bg-slate-50 text-slate-400">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="phone">Số điện thoại</label>
                        <input id="phone" name="phone" type="text" value="{{ old('phone', $user->phone ?? '') }}"
                               placeholder="Chưa cập nhật" class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">Lưu thay đổi</button>
                    </div>
                </form>
            </div>

            {{-- Hồ sơ chuyên môn --}}
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h3 class="font-medium text-slate-700 mb-4 flex items-center gap-2"><span>🎓</span> Hồ sơ chuyên môn</h3>

                @if ($errors->hasAny(['bio', 'subjects']))
                    @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', \Illuminate\Support\Arr::flatten($errors->only(['bio', 'subjects'])))])
                @endif

                <form method="POST" action="{{ route('teacher.profile.teacherProfile.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="subjects">Môn dạy (cách nhau bởi dấu phẩy)</label>
                        <input id="subjects" name="subjects" type="text"
                               value="{{ old('subjects', implode(', ', $teacherProfile?->subjects ?? [])) }}"
                               placeholder="Ví dụ: Toán, Tin học"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="bio">Giới thiệu bản thân</label>
                        <textarea id="bio" name="bio" rows="4" maxlength="2000"
                                  placeholder="Kinh nghiệm giảng dạy, thế mạnh chuyên môn..."
                                  class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">{{ old('bio', $teacherProfile?->bio ?? '') }}</textarea>
                    </div>
                    <div>
                        <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">Lưu hồ sơ chuyên môn</button>
                    </div>
                </form>
            </div>

            {{-- Đổi mật khẩu --}}
            <div class="bg-white rounded-2xl border border-slate-200 p-5" x-data="{ showCurrent: false, showNew: false, showConfirm: false }">
                <h3 class="font-medium text-slate-700 mb-1 flex items-center gap-2"><span>🔒</span> Đổi mật khẩu</h3>
                <p class="text-sm text-slate-400 mb-4">Cần nhập đúng mật khẩu hiện tại trước khi đổi.</p>

                @if ($errors->hasAny(['current_password', 'password']))
                    @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', \Illuminate\Support\Arr::flatten($errors->only(['current_password', 'password'])))])
                @endif

                <form method="POST" action="{{ route('teacher.profile.password') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @csrf
                    @method('PUT')
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="current_password">Mật khẩu hiện tại</label>
                        <div class="relative">
                            <input id="current_password" name="current_password" :type="showCurrent ? 'text' : 'password'" required
                                   class="w-full rounded-lg border border-slate-200 text-sm p-2.5 pr-10 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                            <button type="button" @click="showCurrent = !showCurrent" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-sm">
                                <span x-text="showCurrent ? '🙈' : '👁️'"></span>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="password">Mật khẩu mới</label>
                        <div class="relative">
                            <input id="password" name="password" :type="showNew ? 'text' : 'password'" required minlength="8"
                                   class="w-full rounded-lg border border-slate-200 text-sm p-2.5 pr-10 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                            <button type="button" @click="showNew = !showNew" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-sm">
                                <span x-text="showNew ? '🙈' : '👁️'"></span>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="password_confirmation">Nhập lại mật khẩu mới</label>
                        <div class="relative">
                            <input id="password_confirmation" name="password_confirmation" :type="showConfirm ? 'text' : 'password'" required minlength="8"
                                   class="w-full rounded-lg border border-slate-200 text-sm p-2.5 pr-10 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                            <button type="button" @click="showConfirm = !showConfirm" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-sm">
                                <span x-text="showConfirm ? '🙈' : '👁️'"></span>
                            </button>
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">Đổi mật khẩu</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
