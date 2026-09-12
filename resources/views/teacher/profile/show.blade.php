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
        $regionOptions = \App\Support\VietnamProvinces::regionOptions();
        $provinceOptions = \App\Support\VietnamProvinces::options();
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
            <div class="bg-white rounded-3xl border border-sky-100 overflow-hidden sticky top-24">
                <div class="h-16 bg-gradient-to-br from-[#0759a8] to-[#0976c9]"></div>
                <div class="px-6 pb-6 text-center">
                    <x-ws.avatar :name="$user->name ?? 'Giáo viên'" size="xl" />
                    <h2 class="font-semibold text-slate-800 mt-3">{{ $user->name ?? 'Giáo viên' }}</h2>
                    <p class="text-[13px] text-slate-400">{{ $user->email ?? '' }}</p>
                    <div class="flex items-center justify-center gap-1.5 flex-wrap mt-3">
                        <x-ws.badge :tone="$approvalTone">{{ $teacherProfile?->approval_status?->label() ?? 'Chưa có hồ sơ' }}</x-ws.badge>
                        @if ($teacherProfile?->isFeatured())
                            <x-ws.badge tone="info">⭐ Giáo viên nổi bật</x-ws.badge>
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
                    <p class="text-xs text-slate-400 leading-relaxed"><x-lucide name="lock" class="inline h-3.5 w-3.5 shrink-0 align-[-2px]" /> Trạng thái duyệt hồ sơ và ghi nhận "giáo viên nổi bật" do Admin quản lý, không tự sửa được ở đây.</p>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">
            {{-- Thông tin cá nhân --}}
            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                <h3 class="font-medium text-slate-700 mb-4 flex items-center gap-2"><span><x-lucide name="check-circle-2" class="h-4 w-4" /></span> Thông tin cá nhân</h3>

                @if ($errors->hasAny(['name', 'phone', 'province', 'region']))
                    @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', \Illuminate\Support\Arr::flatten($errors->only(['name', 'phone', 'province', 'region'])))])
                @endif

                <form method="POST" action="{{ route('teacher.profile.update') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="name">Họ tên</label>
                        <input id="name" name="name" type="text" value="{{ old('name', $user->name ?? '') }}" required
                               class="admin-input">
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="email">Email</label>
                        <input id="email" type="email" value="{{ $user->email ?? '' }}" disabled
                               class="w-full rounded-xl border border-sky-100 text-[13px] p-2.5 bg-slate-50 text-slate-400">
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="phone">Số điện thoại</label>
                        <input id="phone" name="phone" type="text" value="{{ old('phone', $user->phone ?? '') }}"
                               placeholder="Chưa cập nhật" class="admin-input">
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="province">Tỉnh/thành (để quảng cáo tới học sinh gần bạn)</label>
                        <x-ws.select id="province" name="province">
                            <option value="">— Chưa chọn —</option>
                            @foreach ($provinceOptions as $p)
                                <option value="{{ $p }}" @selected(old('province', $user->province ?? '') === $p)>{{ $p }}</option>
                            @endforeach
                        </x-ws.select>
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="region">Khu vực</label>
                        <x-ws.select id="region" name="region">
                            <option value="">— Chưa chọn —</option>
                            @foreach ($regionOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('region', $user->region ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </x-ws.select>
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700">Lưu thay đổi</button>
                    </div>
                </form>
            </div>

            {{-- Hồ sơ chuyên môn --}}
            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                <h3 class="font-medium text-slate-700 mb-4 flex items-center gap-2"><span><x-lucide name="graduation-cap" class="h-4 w-4" /></span> Hồ sơ chuyên môn</h3>

                @if ($errors->hasAny(['bio', 'subjects']))
                    @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', \Illuminate\Support\Arr::flatten($errors->only(['bio', 'subjects'])))])
                @endif

                <form method="POST" action="{{ route('teacher.profile.teacherProfile.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="subjects">Môn dạy (cách nhau bởi dấu phẩy)</label>
                        <input id="subjects" name="subjects" type="text"
                               value="{{ old('subjects', implode(', ', $teacherProfile?->subjects ?? [])) }}"
                               placeholder="Ví dụ: Toán, Tin học"
                               class="admin-input">
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="bio">Giới thiệu bản thân</label>
                        <textarea id="bio" name="bio" rows="4" maxlength="2000"
                                  placeholder="Kinh nghiệm giảng dạy, thế mạnh chuyên môn..."
                                  class="admin-input">{{ old('bio', $teacherProfile?->bio ?? '') }}</textarea>
                    </div>
                    <div>
                        <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700">Lưu hồ sơ chuyên môn</button>
                    </div>
                </form>
            </div>

            {{-- Đổi mật khẩu --}}
            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5" x-data="{ showCurrent: false, showNew: false, showConfirm: false }">
                <h3 class="font-medium text-slate-700 mb-1 flex items-center gap-2"><span><x-lucide name="lock" class="h-4 w-4" /></span> Đổi mật khẩu</h3>
                <p class="text-[13px] text-slate-400 mb-4">Cần nhập đúng mật khẩu hiện tại trước khi đổi.</p>

                @if ($errors->hasAny(['current_password', 'password']))
                    @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', \Illuminate\Support\Arr::flatten($errors->only(['current_password', 'password'])))])
                @endif

                <form method="POST" action="{{ route('teacher.profile.password') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @csrf
                    @method('PUT')
                    <div class="sm:col-span-2">
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="current_password">Mật khẩu hiện tại</label>
                        <div class="relative">
                            <input id="current_password" name="current_password" :type="showCurrent ? 'text' : 'password'" required
                                   class="w-full rounded-xl border border-sky-100 text-[13px] p-2.5 pr-10 hover:border-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-300 transition">
                            <button type="button" @click="showCurrent = !showCurrent" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-[13px]">
                                <span x-text="showCurrent ? '🙈' : '👁️'"></span>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="password">Mật khẩu mới</label>
                        <div class="relative">
                            <input id="password" name="password" :type="showNew ? 'text' : 'password'" required minlength="8"
                                   class="w-full rounded-xl border border-sky-100 text-[13px] p-2.5 pr-10 hover:border-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-300 transition">
                            <button type="button" @click="showNew = !showNew" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-[13px]">
                                <span x-text="showNew ? '🙈' : '👁️'"></span>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="password_confirmation">Nhập lại mật khẩu mới</label>
                        <div class="relative">
                            <input id="password_confirmation" name="password_confirmation" :type="showConfirm ? 'text' : 'password'" required minlength="8"
                                   class="w-full rounded-xl border border-sky-100 text-[13px] p-2.5 pr-10 hover:border-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-300 transition">
                            <button type="button" @click="showConfirm = !showConfirm" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-[13px]">
                                <span x-text="showConfirm ? '🙈' : '👁️'"></span>
                            </button>
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700">Đổi mật khẩu</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
