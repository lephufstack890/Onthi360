@extends('layouts.admin')

@section('title', 'Chi tiết đánh giá')
@section('page-title', 'Chi tiết đánh giá')

@section('content')
    @php
        $evidence = $evidence ?? [];
        $reviewStatusMessage = match (session('status')) {
            'review-published' => 'Đã công bố đánh giá.',
            'review-rejected' => 'Đã từ chối đánh giá, đã ghi lý do.',
            'review-needs-revision' => 'Đã yêu cầu chỉnh sửa, đã ghi lý do.',
            'review-hidden' => 'Đã ẩn đánh giá, đã ghi lý do.',
            'review-replied' => 'Đã đăng phản hồi chính thức.',
            default => null,
        };
        $isPublished = $reviewModel->status->value === 'published';
    @endphp
    @if ($reviewStatusMessage)
        @include('partials.toast-flash', ['type' => 'success', 'message' => $reviewStatusMessage])
    @endif
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <a href="{{ route('admin.reviews.index') }}" class="text-sm text-slate-500 mb-4 inline-block">‹ Quay lại Đánh giá</a>

    <x-page-header :title="$targetLabel" :subtitle="'Người viết: '.($reviewModel->reviewer->name ?? '')" />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <p class="text-amber-500 mb-2">{{ str_repeat('★', (int) round($reviewModel->overall_rating)) }}</p>
                <p class="text-sm text-slate-700">{{ $reviewModel->comment }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h2 class="font-medium text-slate-700 mb-2">Bằng chứng đủ điều kiện trải nghiệm</h2>
                @forelse ($evidence as $e)
                    <p class="text-sm text-slate-500">{{ $e }}</p>
                @empty
                    <p class="text-sm text-slate-400">Chưa nối App\Services\ReviewEligibilityService để hiển thị bằng chứng thật.</p>
                @endforelse
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h2 class="font-medium text-slate-700 mb-2">Phản hồi chính thức (chỉ Admin)</h2>
                @if ($reviewModel->admin_reply)
                    <div class="rounded-lg bg-sky-50 border border-sky-100 p-3 mb-3">
                        <p class="text-sm text-slate-700">{{ $reviewModel->admin_reply }}</p>
                        <p class="text-xs text-slate-400 mt-1">Bởi {{ $reviewModel->adminReplier->name ?? 'Admin' }} · {{ $reviewModel->admin_reply_at?->format('d/m/Y H:i') }}</p>
                    </div>
                @endif
                @if ($isPublished)
                    <form method="POST" action="{{ route('admin.reviews.reply', $reviewModel->id) }}">
                        @csrf
                        <textarea name="reply" rows="3" required maxlength="2000" class="w-full rounded-lg border border-slate-200 text-sm p-2" placeholder="Viết phản hồi công khai...">{{ old('reply') }}</textarea>
                        <button type="submit" class="mt-2 px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">{{ $reviewModel->admin_reply ? 'Cập nhật phản hồi' : 'Đăng phản hồi' }}</button>
                    </form>
                @else
                    <p class="text-sm text-slate-400">Chỉ đăng phản hồi được sau khi đánh giá đã "Đã công bố" (9.4).</p>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-5 space-y-4">
            <h2 class="font-medium text-slate-700">Quyết định kiểm duyệt</h2>

            <form method="POST" action="{{ route('admin.reviews.publish', $reviewModel->id) }}">
                @csrf
                <button type="submit" class="w-full px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium">Công bố</button>
            </form>

            <form method="POST" action="{{ route('admin.reviews.request-revision', $reviewModel->id) }}" class="space-y-2" x-data="{ reason: '' }">
                @csrf
                <textarea name="reason" x-model="reason" rows="2" required class="w-full rounded-lg border border-slate-200 text-xs p-2" placeholder="Lý do cần chỉnh sửa (bắt buộc)..."></textarea>
                <button type="submit" :disabled="reason.trim().length === 0" class="w-full px-4 py-2 rounded-lg border border-amber-300 text-amber-700 text-sm font-medium disabled:opacity-40 disabled:cursor-not-allowed">Yêu cầu chỉnh sửa</button>
            </form>

            <form method="POST" action="{{ route('admin.reviews.reject', $reviewModel->id) }}" class="space-y-2" x-data="{ reason: '' }">
                @csrf
                <textarea name="reason" x-model="reason" rows="2" required class="w-full rounded-lg border border-slate-200 text-xs p-2" placeholder="Lý do từ chối (bắt buộc)..."></textarea>
                <button type="submit" :disabled="reason.trim().length === 0" class="w-full px-4 py-2 rounded-lg border border-rose-300 text-rose-600 text-sm font-medium disabled:opacity-40 disabled:cursor-not-allowed">Từ chối có lý do</button>
            </form>

            <form method="POST" action="{{ route('admin.reviews.hide', $reviewModel->id) }}" class="space-y-2" x-data="{ reason: '' }">
                @csrf
                <textarea name="reason" x-model="reason" rows="2" required class="w-full rounded-lg border border-slate-200 text-xs p-2" placeholder="Lý do ẩn (bắt buộc)..."></textarea>
                <button type="submit" :disabled="reason.trim().length === 0" class="w-full px-4 py-2 rounded-lg border border-slate-300 text-slate-600 text-sm font-medium disabled:opacity-40 disabled:cursor-not-allowed">Ẩn sau khi công bố</button>
            </form>
        </div>
    </div>
@endsection
