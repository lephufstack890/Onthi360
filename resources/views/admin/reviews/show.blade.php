{{--
  Route: admin.reviews.show
  Spec: 9.4 (Admin xem bằng chứng entitlement/membership/hoạt động; công bố/cần chỉnh/từ chối/ẩn; phản hồi chính thức không sửa điểm sao).
  $reviewModel (Eloquent thật) do App\Http\Controllers\Admin\ReviewController truyền vào.
  TODO: $evidence chờ nối App\Services\ReviewEligibilityService; xử lý submit quyết định kiểm duyệt.
--}}
@extends('layouts.admin')

@section('title', 'Chi tiết đánh giá')
@section('page-title', 'Chi tiết đánh giá')

@section('content')
    @php
        $evidence = $evidence ?? [];
    @endphp

    <a href="{{ route('admin.reviews.index') }}" class="text-sm text-slate-500 mb-4 inline-block">‹ Quay lại Đánh giá</a>

    <x-page-header :title="$targetLabel" :subtitle="'Người viết: '.($reviewModel->reviewer->name ?? '')" />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <p class="text-amber-500 mb-2">{{ str_repeat('★', (int) round($reviewModel->overall_rating)) }}</p>
                <p class="text-sm text-slate-700">{{ $reviewModel->comment }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h2 class="font-medium text-slate-700 mb-2">Bằng chứng đủ điều kiện trải nghiệm (9.2)</h2>
                @forelse ($evidence as $e)
                    <p class="text-sm text-slate-500">{{ $e }}</p>
                @empty
                    <p class="text-sm text-slate-400">Chưa nối App\Services\ReviewEligibilityService để hiển thị bằng chứng thật.</p>
                @endforelse
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h2 class="font-medium text-slate-700 mb-2">Phản hồi chính thức (chỉ Admin)</h2>
                <textarea rows="3" class="w-full rounded-lg border border-slate-200 text-sm p-2" placeholder="Viết phản hồi công khai..."></textarea>
                <button type="button" class="mt-2 px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">Đăng phản hồi</button>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="font-medium text-slate-700 mb-4">Quyết định kiểm duyệt</h2>
            <div class="space-y-2">
                <button type="button" class="w-full px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium">Công bố</button>
                <button type="button" class="w-full px-4 py-2 rounded-lg border border-amber-300 text-amber-700 text-sm font-medium">Yêu cầu chỉnh sửa</button>
                <button type="button" class="w-full px-4 py-2 rounded-lg border border-rose-300 text-rose-600 text-sm font-medium">Từ chối có lý do</button>
                <button type="button" class="w-full px-4 py-2 rounded-lg border border-slate-300 text-slate-600 text-sm font-medium">Ẩn sau khi công bố</button>
            </div>
        </div>
    </div>
@endsection
