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
    @endif

    @include('partials.personal-profile', [
        'user' => $user,
        'action' => route('teacher.profile.update'),
        'roleLabel' => 'Giáo viên',
        'withLocation' => true,
        'profileUrl' => route('teacher.profile.show'),
        'passwordUrl' => route('teacher.password.edit'),
    ])

    <div class="mt-6 space-y-6">
            {{-- Trạng thái hồ sơ giáo viên (chuyển từ thẻ bên trái cũ) --}}
            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                <h3 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span><x-lucide name="shield-check" class="h-4 w-4" /></span> Trạng thái hồ sơ giáo viên</h3>
                <div class="flex items-center gap-1.5 flex-wrap">
                    <x-ws.badge :tone="$approvalTone">{{ $teacherProfile?->approval_status?->label() ?? 'Chưa có hồ sơ' }}</x-ws.badge>
                    @if ($teacherProfile?->isFeatured())
                        <x-ws.badge tone="info">⭐ Giáo viên nổi bật</x-ws.badge>
                    @endif
                </div>
                @if ($teacherProfile && in_array($teacherProfile->approval_status?->value, ['rejected', 'suspended'], true) && $teacherProfile->rejection_reason)
                    <p class="text-xs font-medium text-slate-500 mt-3 mb-1">Lý do:</p>
                    <p class="text-xs text-slate-400 leading-relaxed">{{ $teacherProfile->rejection_reason }}</p>
                @endif
                @if ($teacherProfile?->achievement_note)
                    <p class="text-xs font-medium text-slate-500 mt-3 mb-1">Ghi nhận thành tích:</p>
                    <p class="text-xs text-slate-400 leading-relaxed">{{ $teacherProfile->achievement_note }}</p>
                @endif
                <p class="text-xs text-slate-400 leading-relaxed mt-3"><x-lucide name="lock" class="inline h-3.5 w-3.5 shrink-0 align-[-2px]" /> Trạng thái duyệt hồ sơ và ghi nhận "giáo viên nổi bật" do Admin quản lý, không tự sửa được ở đây.</p>
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
    </div>
@endsection
