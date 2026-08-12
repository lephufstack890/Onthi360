{{--
  Route: admin.teacher-approvals.show
  Spec: 3.3 + 16 mục 4 (duyệt/từ chối/tạm dừng phải ghi lý do + audit log — audit tự ghi qua
  App\Concerns\Auditable gắn ở App\Models\TeacherProfile, không cần gọi tay ở đây).
  Dữ liệu thật ($profile) do App\Http\Controllers\Admin\TeacherApprovalController truyền vào.
--}}
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

    <a href="{{ route('admin.teacher-approvals.index') }}" class="text-sm text-slate-500 mb-4 inline-block">‹ Quay lại hàng đợi</a>

    <x-page-header :title="$profile->user->name ?? ''" :subtitle="($profile->user->email ?? '').(($profile->subjects[0] ?? null) ? ' · '.$profile->subjects[0] : '')" />

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 mb-6 text-sm text-emerald-700">
            @switch(session('status'))
                @case('approved') Đã duyệt hồ sơ giáo viên. @break
                @case('rejected') Đã từ chối hồ sơ, đã ghi lý do. @break
                @case('suspended') Đã tạm dừng giáo viên, đã ghi lý do. @break
                @case('reinstated') Đã duyệt lại giáo viên. @break
                @default Đã cập nhật trạng thái. @break
            @endswitch
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 mb-6 text-sm text-rose-700">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-5 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="font-medium text-slate-700">Trạng thái hiện tại</h2>
                <x-status-badge :tone="$statusTone">{{ $status?->label() ?? '' }}</x-status-badge>
            </div>

            @if ($profile->rejection_reason)
                <div class="rounded-lg bg-rose-50 border border-rose-100 p-3 text-sm text-rose-700">
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
                <p class="text-sm text-slate-500">{{ $profile->bio ?: 'Chưa có thông tin giới thiệu.' }}</p>
            </div>
            <div>
                <h2 class="font-medium text-slate-700 mb-2">Tài liệu minh chứng</h2>
                @forelse ($documents as $doc)
                    <p class="text-sm text-rose-600 underline">{{ $doc }}</p>
                @empty
                    <p class="text-sm text-slate-400">Chưa có bảng lưu tài liệu minh chứng trong hệ thống.</p>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="font-medium text-slate-700 mb-4">Quyết định</h2>

            @if (in_array($status?->value, ['pending', 'suspended', 'rejected'], true))
                <form method="POST" action="{{ route('admin.teacher-approvals.approve', $profile->id) }}" class="mb-3">
                    @csrf
                    <button type="submit" class="w-full px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium">
                        {{ $status?->value === 'pending' ? 'Duyệt hồ sơ' : 'Duyệt lại' }}
                    </button>
                </form>
            @endif

            @if ($status?->value === 'pending')
                <form method="POST" action="{{ route('admin.teacher-approvals.reject', $profile->id) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-sm text-slate-600 mb-1">Lý do từ chối (bắt buộc)</label>
                        <textarea name="reason" rows="3" required class="w-full rounded-lg border border-slate-200 text-sm p-2" placeholder="Nêu rõ lý do..."></textarea>
                    </div>
                    <button type="submit" class="w-full px-4 py-2 rounded-lg border border-rose-300 text-rose-600 text-sm font-medium">
                        Từ chối có lý do
                    </button>
                </form>
            @endif

            @if ($status?->value === 'approved')
                <form method="POST" action="{{ route('admin.teacher-approvals.suspend', $profile->id) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-sm text-slate-600 mb-1">Lý do tạm dừng (bắt buộc)</label>
                        <textarea name="reason" rows="3" required class="w-full rounded-lg border border-slate-200 text-sm p-2" placeholder="Nêu rõ lý do..."></textarea>
                    </div>
                    <button type="submit" class="w-full px-4 py-2 rounded-lg border border-amber-300 text-amber-700 text-sm font-medium">
                        Tạm dừng
                    </button>
                </form>
            @endif
        </div>
    </div>
@endsection
