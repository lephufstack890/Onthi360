@extends('layouts.admin')

@section('title', 'Tài khoản vi phạm')
@section('page-title', 'Tài khoản vi phạm')

@section('content')
{{-- SỬA 12/11 (khách: "trong admin thì có thể config để trừng phạt, khi trừng phạt thì nó hiển thị ra danh sách của tab đó") —
     xử phạt tài khoản trên bảng xếp hạng. Tài khoản đang khóa hiện ở tab "Tài khoản vi phạm" của trang Bảng xếp hạng công khai,
     đồng thời bị gỡ khỏi bảng Toàn thời gian / Tháng này tới khi hết hạn khóa. Xem App\Services\Admin\LeaderboardPenaltyService. --}}
@php
    $penalties = $penalties ?? collect();
    $counts = $counts ?? ['locked' => 0, 'all' => 0, 'tokens' => 0];
    $tz = 'Asia/Ho_Chi_Minh';
    $fmtDate = fn ($d) => $d ? $d->copy()->setTimezone($tz)->format('H:i d/m/Y') : '—';
    $nf = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
    $oldReason = old('reason', $reasons[0] ?? '');
    $isPreset = in_array($oldReason, $reasons, true);
@endphp

@if (session('status') === 'penalty-created')
    @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã xử phạt — tài khoản đã hiện ở tab "Tài khoản vi phạm".'])
@elseif (session('status') === 'penalty-unlocked')
    @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã mở khóa sớm, tài khoản trở lại bảng xếp hạng.'])
@elseif (session('status') === 'penalty-revoked')
    @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã hủy hình phạt và hoàn token.'])
@endif

<x-ws.page-header title="Tài khoản vi phạm" icon="ban" :back="route('admin.ranking.index')" back-label="Quay lại Bảng xếp hạng"
                  subtitle="Khóa tài khoản khỏi bảng xếp hạng, trừ điểm / token. Tài khoản đang khóa hiện công khai ở tab “Tài khoản vi phạm”.">
    <x-slot:actions>
        <x-ws.btn :href="route('leaderboard.index', ['scope' => 'violations'])" variant="onhero-ghost" icon="eye" target="_blank">Xem trang công khai</x-ws.btn>
    </x-slot:actions>
</x-ws.page-header>

@if (! ($ready ?? true))
    <div class="rounded-3xl border border-amber-200 bg-amber-50 p-4 text-[13px] text-amber-800">
        Chưa có bảng dữ liệu <code>leaderboard_penalties</code>. Hãy chạy <code>php artisan migrate</code> rồi tải lại trang.
    </div>
@endif

<div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
    <x-ws.stat label="Đang bị khóa" :value="$counts['locked']" tone="rose" icon="lock" hint="Đang hiện ở tab công khai" />
    <x-ws.stat label="Tổng lượt xử phạt" :value="$counts['all']" tone="amber" icon="history" />
    <x-ws.stat label="Token đã trừ" :value="number_format($counts['tokens'], 0, ',', '.')" tone="violet" icon="wallet-cards" hint="Không tính hình phạt đã hủy" />
</div>

<div class="grid grid-cols-1 gap-4 xl:grid-cols-[400px_1fr]">
    {{-- ── Form xử phạt ── --}}
    <x-ws.card title="Xử phạt tài khoản" icon="ban">
        <form method="POST" action="{{ route('admin.ranking.violations.store') }}" class="space-y-3.5"
              x-data="{ preset: @js($isPreset ? $oldReason : '__other'), custom: @js($isPreset ? '' : $oldReason) }">
            @csrf
            <x-ws.field label="Tài khoản" name="account" required hint="Nhập ID, email hoặc số điện thoại của tài khoản.">
                <input id="account" name="account" type="text" class="admin-input" required value="{{ old('account') }}" placeholder="VD: 1024 hoặc hocsinh@gmail.com">
            </x-ws.field>

            <x-ws.field label="Lý do khóa" name="reason" required>
                <select class="admin-input" x-model="preset">
                    @foreach ($reasons as $r)
                        <option value="{{ $r }}">{{ $r }}</option>
                    @endforeach
                    <option value="__other">Lý do khác…</option>
                </select>
                <input type="text" class="admin-input mt-2" x-show="preset === '__other'" x-model="custom" maxlength="255" placeholder="Nhập lý do">
                <input type="hidden" name="reason" :value="preset === '__other' ? custom : preset">
            </x-ws.field>

            <x-ws.field label="Hình thức khóa" name="lock_days" required hint="Tài khoản bị gỡ khỏi bảng xếp hạng trong thời gian khóa, hết hạn tự mở.">
                <select id="lock_days" name="lock_days" class="admin-input">
                    @foreach ($lockDays as $d)
                        <option value="{{ $d }}" @selected((int) old('lock_days', 7) === $d)>{{ $d > 0 ? 'Khóa '.$d.' ngày' : 'Khóa vĩnh viễn' }}</option>
                    @endforeach
                </select>
            </x-ws.field>

            <div class="grid grid-cols-2 gap-3">
                <x-ws.field label="Trừ điểm" name="points_deducted" hint="Trừ vào tổng điểm xếp hạng. 0 = không trừ.">
                    <input id="points_deducted" name="points_deducted" type="number" min="0" step="0.01" class="admin-input" value="{{ old('points_deducted', 0) }}">
                </x-ws.field>
                <x-ws.field label="Trừ token" name="tokens_deducted" hint="Trừ vào ví, tối đa bằng số dư.">
                    <input id="tokens_deducted" name="tokens_deducted" type="number" min="0" step="1" class="admin-input" value="{{ old('tokens_deducted', 0) }}">
                </x-ws.field>
            </div>

            <x-ws.field label="Ghi chú nội bộ" name="note">
                <textarea id="note" name="note" rows="2" class="admin-input" placeholder="Bằng chứng, mã lượt thi… (không hiện công khai)">{{ old('note') }}</textarea>
            </x-ws.field>

            <label class="flex items-start gap-2 text-[12px] text-slate-600">
                <input type="hidden" name="hide_name" value="0">
                <input type="checkbox" name="hide_name" value="1" class="mt-0.5 rounded border-slate-300" @checked(old('hide_name'))>
                <span>Ẩn tên trên trang công khai (chỉ hiện mã tài khoản dạng <b>VP-000123</b>)</span>
            </label>

            <x-ws.btn variant="danger" icon="lock" class="w-full" onclick="return confirm('Xác nhận xử phạt tài khoản này?')">Xử phạt</x-ws.btn>
        </form>
    </x-ws.card>

    {{-- ── Danh sách ── --}}
    <div class="min-w-0 space-y-3">
        <div class="flex flex-wrap gap-2">
            <x-ws.btn size="sm" :variant="$filter === 'locked' ? 'primary' : 'ghost'" :href="route('admin.ranking.violations.index', ['filter' => 'locked'])">Đang khóa ({{ $counts['locked'] }})</x-ws.btn>
            <x-ws.btn size="sm" :variant="$filter === 'all' ? 'primary' : 'ghost'" :href="route('admin.ranking.violations.index', ['filter' => 'all'])">Tất cả ({{ $counts['all'] }})</x-ws.btn>
        </div>

        <x-ws.table :columns="['Tài khoản', 'Điểm khi khóa', 'Thời điểm khóa', 'Lý do', 'Hình thức', 'Trừ điểm', 'Trừ token', 'Mở khóa', 'Trạng thái', '']" min-width="min-w-[1100px]">
            @forelse ($penalties as $p)
                @php
                    $status = $p->statusLabel();
                    $tone = match ($status) { 'Bị khóa' => 'danger', 'Đã hủy' => 'neutral', default => 'success' };
                @endphp
                <tr class="align-top">
                    <td class="px-4 py-3">
                        <p class="font-bold text-slate-700">{{ $p->user->name ?? 'Tài khoản đã xoá' }}</p>
                        <p class="text-[11px] text-slate-400">{{ $p->accountCode() }} · {{ $p->user->email ?? '' }}</p>
                        @if ($p->hide_name)<x-ws.badge class="mt-1">Ẩn tên công khai</x-ws.badge>@endif
                    </td>
                    <td class="px-4 py-3 font-semibold text-slate-600">{{ $p->score_at_lock !== null ? $nf($p->score_at_lock) : '—' }}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-slate-500">{{ $fmtDate($p->locked_at) }}<br><span class="text-[10px] text-slate-400">bởi {{ $p->creator->name ?? '—' }}</span></td>
                    <td class="px-4 py-3 text-slate-600 max-w-[220px]">
                        {{ $p->reason }}
                        @if ($p->note)<p class="mt-1 text-[11px] text-slate-400">{{ $p->note }}</p>@endif
                        @if ($p->revoke_reason)<p class="mt-1 text-[11px] text-slate-400">Hủy: {{ $p->revoke_reason }}</p>@endif
                    </td>
                    <td class="px-4 py-3"><x-ws.badge tone="warning">{{ $p->lockLabel() }}</x-ws.badge></td>
                    <td class="px-4 py-3 font-semibold text-rose-600">{{ $p->points_deducted > 0 ? '-'.$nf($p->points_deducted) : 'Không trừ' }}</td>
                    <td class="px-4 py-3 font-semibold text-rose-600">{{ $p->tokens_deducted > 0 ? '-'.number_format($p->tokens_deducted, 0, ',', '.') : 'Không trừ' }}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-slate-500">{{ $p->unlocked_at ? $fmtDate($p->unlocked_at) : ($p->unlock_at ? $fmtDate($p->unlock_at) : 'Vĩnh viễn') }}</td>
                    <td class="px-4 py-3"><x-ws.badge :tone="$tone">{{ $status }}</x-ws.badge></td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex flex-col items-end gap-1.5" x-data="{ open: false }">
                            @if ($p->isLocked())
                                <form method="POST" action="{{ route('admin.ranking.violations.unlock', $p->id) }}" onsubmit="return confirm('Mở khóa sớm tài khoản này?')">
                                    @csrf
                                    <x-ws.btn size="sm" variant="success" icon="lock-open">Mở khóa</x-ws.btn>
                                </form>
                            @endif
                            @if (! $p->revoked_at)
                                <x-ws.btn size="sm" variant="danger" type="button" icon="rotate-ccw" x-on:click="open = !open">Hủy hình phạt</x-ws.btn>
                                <form method="POST" action="{{ route('admin.ranking.violations.destroy', $p->id) }}" x-show="open" x-cloak class="flex w-56 flex-col gap-1.5">
                                    @csrf
                                    @method('DELETE')
                                    <input name="revoke_reason" class="admin-input" required maxlength="500" placeholder="Lý do hủy (VD: xử nhầm)">
                                    <x-ws.btn size="sm" variant="danger">Xác nhận hủy · hoàn token</x-ws.btn>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="px-4 py-8 text-center text-slate-400">{{ $filter === 'locked' ? 'Không có tài khoản nào đang bị khóa.' : 'Chưa xử phạt tài khoản nào.' }}</td></tr>
            @endforelse
        </x-ws.table>
        <p class="text-[11px] text-slate-400">
            <b>Mở khóa</b>: tài khoản trở lại bảng xếp hạng ngay, điểm / token đã trừ giữ nguyên.
            <b>Hủy hình phạt</b>: dành cho xử nhầm — hoàn lại token, bỏ trừ điểm, gỡ khỏi danh sách công khai.
        </p>
    </div>
</div>
@endsection
