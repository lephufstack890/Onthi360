{{--
  Route: admin.access-rights.show / .revoke
  Spec: 7.1-7.5 + 10.4 (thu hồi phải có lý do + audit log — Auditable đã gắn ở AccessRight).
  Dữ liệu thật ($right, $statusLabel, $tone) do AccessRightController::show() truyền vào
  qua App\Services\Admin\AccessRightService::showData().
--}}
@extends('layouts.admin')

@section('title', 'Chi tiết quyền truy cập')
@section('page-title', 'Chi tiết quyền truy cập')

@section('content')
    <a href="{{ route('admin.access-rights.index') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Quyền truy cập</a>

    @if (in_array(session('status'), ['access-granted', 'access-revoked'], true))
        @include('partials.toast-flash', ['type' => 'success', 'message' => session('status') === 'access-granted' ? 'Đã cấp quyền truy cập.' : 'Đã thu hồi quyền, đã ghi lý do.'])
    @endif

    <x-page-header :title="$right->user->name ?? ''" :subtitle="($right->user->email ?? '').' · '.($right->product->title ?? '')">
        <x-slot:actions>
            <x-status-badge :tone="$tone">{{ $statusLabel }}</x-status-badge>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-5 space-y-3 text-sm">
            <div class="flex justify-between border-b border-slate-100 pb-3">
                <span class="text-slate-400">Phạm vi</span>
                <span class="text-slate-700 font-medium">{{ $right->scope->value === 'teacher_teaching' ? 'Dùng để dạy (không giới hạn lớp)' : 'Học cá nhân' }}</span>
            </div>
            <div class="flex justify-between border-b border-slate-100 pb-3">
                <span class="text-slate-400">Bắt đầu hiệu lực</span>
                <span class="text-slate-700">{{ $right->starts_at?->format('d/m/Y H:i') ?? '—' }}</span>
            </div>
            <div class="flex justify-between border-b border-slate-100 pb-3">
                <span class="text-slate-400">Hết hạn</span>
                <span class="text-slate-700">{{ $right->expires_at?->format('d/m/Y H:i') ?? 'Không giới hạn' }}</span>
            </div>
            <div class="flex justify-between border-b border-slate-100 pb-3">
                <span class="text-slate-400">Nguồn cấp</span>
                <span class="text-slate-700">{{ ['order' => 'Đơn hàng', 'gift' => 'Tặng', 'admin_grant' => 'Admin cấp trực tiếp', 'package' => 'Gói combo'][$right->source] ?? $right->source }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-400">Giới hạn số lớp (chỉ áp dụng quyền dạy)</span>
                <span class="text-slate-700">{{ $right->class_limit ?? 'Không giới hạn' }}</span>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-rose-200 p-5 space-y-3" x-data="{ open: false, reason: '' }">
            <h3 class="font-medium text-rose-700 flex items-center gap-2"><span>⚠️</span> Thu hồi quyền</h3>
            @if ($right->status->value === 'revoked')
                <p class="text-sm text-slate-400">Quyền này đã bị thu hồi.</p>
            @else
                <p class="text-sm text-slate-500">Bắt buộc nêu lý do (10.4) — người dùng sẽ mất quyền truy cập ngay sau khi xác nhận.</p>
                <button type="button" @click="open = !open" class="text-sm font-medium text-rose-600 hover:underline" x-text="open ? 'Đóng' : 'Thu hồi quyền này'"></button>
                <form x-show="open" x-cloak method="POST" action="{{ route('admin.access-rights.revoke', $right->id) }}" class="space-y-3 pt-2" onsubmit="return confirm('Xác nhận thu hồi quyền này?');">
                    @csrf
                    <div>
                        <label class="block text-sm text-slate-600 mb-1">Lý do thu hồi (bắt buộc)</label>
                        <textarea name="reason" x-model="reason" rows="3" required class="w-full rounded-lg border border-slate-200 text-sm p-2" placeholder="Nêu rõ lý do..."></textarea>
                    </div>
                    <button type="submit" :disabled="reason.trim().length === 0" class="w-full px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium disabled:opacity-40 disabled:cursor-not-allowed">Xác nhận thu hồi</button>
                </form>
            @endif
        </div>
    </div>
@endsection
