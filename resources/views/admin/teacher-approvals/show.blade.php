@extends('layouts.admin')

@section('title', 'Hồ sơ giáo viên')
@section('page-title', 'Hồ sơ giáo viên')

@section('content')
    @php
        $documents = $documents ?? [];
        $status = $profile->approval_status;
        $statusTone = match ($status?->value) {
            'approved' => 'success',
            'pending' => 'warning',
            'suspended', 'rejected' => 'danger',
            default => 'neutral',
        };
    @endphp

    <a href="{{ route('admin.teacher-approvals.index') }}" class="text-[13px] text-slate-500 mb-4 inline-block">‹ Quay lại hàng đợi</a>

    <x-admin.page-header :title="$profile->user->name ?? ''" :subtitle="($profile->user->email ?? '').(($profile->subjects[0] ?? null) ? ' · '.$profile->subjects[0] : '')" />

    @php
        $statusMessage = match (session('status')) {
            'approved' => 'Đã duyệt hồ sơ giáo viên.',
            'rejected' => 'Đã từ chối hồ sơ, đã ghi lý do.',
            'suspended' => 'Đã tạm dừng giáo viên, đã ghi lý do.',
            'reinstated' => 'Đã duyệt lại giáo viên.',
            default => session('status') ? 'Đã cập nhật trạng thái.' : null,
        };
    @endphp
    @if ($statusMessage)
        @include('partials.toast-flash', ['type' => 'success', 'message' => $statusMessage])
    @endif

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-3xl border border-sky-100 p-5 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="font-medium text-slate-700">Trạng thái hiện tại</h2>
                <x-admin.badge :tone="$statusTone">{{ $status?->label() ?? '' }}</x-admin.badge>
            </div>

            @if ($profile->rejection_reason)
                <div class="rounded-xl bg-blue-50 border border-blue-100 p-3 text-[13px] text-blue-700">
                    <span class="font-medium">Lý do gần nhất:</span> {{ $profile->rejection_reason }}
                </div>
            @endif

            @if ($profile->approver)
                <p class="text-xs text-slate-400">
                    Xử lý gần nhất bởi {{ $profile->approver->name }}
                    @if ($profile->approved_at) · {{ $profile->approved_at->format('d/m/Y H:i') }} @endif
                </p>
            @endif

            <div>
                <h2 class="font-medium text-slate-700 mb-2">Giới thiệu</h2>
                <p class="text-[13px] text-slate-500">{{ $profile->bio ?: 'Chưa có thông tin giới thiệu.' }}</p>
            </div>
            <div>
                <h2 class="font-medium text-slate-700 mb-2">Tài liệu minh chứng</h2>
                @forelse ($documents as $doc)
                    <p class="text-[13px] text-blue-600 underline">{{ $doc }}</p>
                @empty
                    <p class="text-[13px] text-slate-400">Chưa có bảng lưu tài liệu minh chứng trong hệ thống.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
            <h2 class="font-medium text-slate-700 mb-4">Quyết định</h2>

            @if (in_array($status?->value, ['pending', 'suspended', 'rejected'], true))
                <form method="POST" action="{{ route('admin.teacher-approvals.approve', $profile->id) }}" class="mb-3">
                    @csrf
                    <button type="submit" class="w-full px-4 py-2 rounded-xl bg-emerald-600 text-white text-[13px] font-medium">
                        {{ $status?->value === 'pending' ? 'Duyệt hồ sơ' : 'Duyệt lại' }}
                    </button>
                </form>
            @endif

            @if ($status?->value === 'pending')
                <form method="POST" action="{{ route('admin.teacher-approvals.reject', $profile->id) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-[13px] text-slate-600 mb-1">Lý do từ chối (bắt buộc)</label>
                        <textarea name="reason" rows="3" required class="admin-input" placeholder="Nêu rõ lý do..."></textarea>
                    </div>
                    <button type="submit" class="w-full px-4 py-2 rounded-xl border border-blue-300 text-blue-600 text-[13px] font-medium">
                        Từ chối có lý do
                    </button>
                </form>
            @endif

            @if ($status?->value === 'approved')
                <form method="POST" action="{{ route('admin.teacher-approvals.suspend', $profile->id) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-[13px] text-slate-600 mb-1">Lý do tạm dừng (bắt buộc)</label>
                        <textarea name="reason" rows="3" required class="admin-input" placeholder="Nêu rõ lý do..."></textarea>
                    </div>
                    <button type="submit" class="w-full px-4 py-2 rounded-xl border border-amber-300 text-amber-700 text-[13px] font-medium">
                        Tạm dừng
                    </button>
                </form>
            @endif
        </div>
    </div>
@endsection
