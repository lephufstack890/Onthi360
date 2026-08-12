{{--
  Route: admin.teacher-approvals.show
  Spec: 3.3 + 16 mục 4 (duyệt/từ chối phải ghi lý do + audit log).
  Dữ liệu thật ($profile) do App\Http\Controllers\Admin\TeacherApprovalController
  truyền vào. TODO: xử lý submit duyệt/từ chối gọi service cập nhật
  TeacherApprovalStatus + ghi AuditLog; $documents chờ bảng tài liệu minh chứng.
--}}
@extends('layouts.admin')

@section('title', 'Hồ sơ giáo viên')
@section('page-title', 'Hồ sơ giáo viên')

@section('content')
    @php
        $documents = $documents ?? [];
    @endphp

    <a href="{{ route('admin.teacher-approvals.index') }}" class="text-sm text-slate-500 mb-4 inline-block">‹ Quay lại hàng đợi</a>

    <x-page-header :title="$profile->user->name ?? ''" :subtitle="($profile->user->email ?? '').(($profile->subjects[0] ?? null) ? ' · '.$profile->subjects[0] : '')" />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-5 space-y-4">
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
            {{-- TODO: form POST tới route duyệt/từ chối --}}
            <form class="space-y-3">
                <button type="button" class="w-full px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium">Duyệt hồ sơ</button>
                <div>
                    <label class="block text-sm text-slate-600 mb-1">Lý do từ chối (bắt buộc nếu từ chối)</label>
                    <textarea rows="3" class="w-full rounded-lg border border-slate-200 text-sm p-2" placeholder="Nêu rõ lý do..."></textarea>
                </div>
                <button type="button" class="w-full px-4 py-2 rounded-lg border border-rose-300 text-rose-600 text-sm font-medium">Từ chối có lý do</button>
            </form>
        </div>
    </div>
@endsection
