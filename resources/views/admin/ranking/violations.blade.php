@extends('layouts.admin')

@section('title', 'Tài khoản vi phạm')
@section('page-title', 'Tài khoản vi phạm')

@section('content')
{{-- SỬA 12/11 — Quản trị › Bảng xếp hạng = màn xử phạt tài khoản vi phạm. Tài khoản đang khóa hiện ở tab "Tài khoản vi phạm"
     của trang Bảng xếp hạng công khai và bị gỡ khỏi bảng Toàn thời gian / Tháng này tới khi hết hạn khóa.
     Xem App\Services\Admin\LeaderboardPenaltyService. Bố cục làm lại theo yêu cầu khách ("xây lại layout cho đẹp"):
     số liệu · danh sách thẻ full ngang (tìm nhanh, lọc Đang khóa / Tất cả) · form xử phạt mở trong khung trượt bên phải. --}}
<style>
.vp-pick { position: relative; }
.vp-pick__box { display: flex; width: 100%; min-height: 46px; align-items: center; gap: 8px; padding: 6px 12px; border: 1px solid #dbeafe; border-radius: 14px; background: #fff; text-align: left; transition: border-color .15s, box-shadow .15s; }
.vp-pick__box:hover { border-color: #bfdbfe; }
.vp-pick__box.is-open, .vp-pick__box:focus-visible { outline: none; border-color: #60a5fa; box-shadow: 0 0 0 3px #dbeafe; }
.vp-pick__val { display: flex; flex: 1; min-width: 0; align-items: center; gap: 9px; }
.vp-pick__val img, .vp-pick__opt img, .vp-pick__ini { width: 36px; height: 36px; flex: 0 0 36px; border-radius: 50%; object-fit: cover; background: #eef4f8; }
.vp-pick__ini { display: grid; place-items: center; background: linear-gradient(135deg, #dbeafe, #e0e7ff); color: #1d4ed8; font-size: 12px; font-style: normal; font-weight: 800; letter-spacing: .02em; }
.vp-pick__opt { min-height: 52px; }
.vp-pick__val b, .vp-pick__opt b { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 13px; font-weight: 700; color: #1e293b; }
.vp-pick__val small, .vp-pick__opt small { display: block; margin-top: 1px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 11px; color: #94a3b8; }
.vp-pick__ph { flex: 1; font-size: 14px; color: #94a3b8; }
.vp-pick__x { display: grid; place-items: center; width: 22px; height: 22px; border-radius: 6px; color: #94a3b8; }
.vp-pick__x:hover { background: #f1f5f9; color: #e11d48; }
.vp-pick__drop { position: absolute; z-index: 40; left: 0; right: 0; top: calc(100% + 6px); overflow: hidden; border: 1px solid #dbeafe; border-radius: 14px; background: #fff; box-shadow: 0 12px 32px rgba(15, 52, 96, .14); }
.vp-pick__search { display: flex; align-items: center; gap: 8px; margin: 8px; padding: 0 10px; border: 1px solid #e2e8f0; border-radius: 10px; }
.vp-pick__search input { flex: 1; min-width: 0; height: 36px; border: 0; outline: none; box-shadow: none; padding: 0; font-size: 13px; background: transparent; }
.vp-pick__list { max-height: 260px; overflow-y: auto; padding: 0 6px 6px; }
.vp-pick__opt { display: flex; align-items: center; gap: 9px; padding: 7px 8px; border-radius: 10px; cursor: pointer; }
.vp-pick__opt.is-active { background: #eff6ff; }
.vp-pick__opt.is-sel b { color: #1d4ed8; }
.vp-pick__lock { flex: 0 0 auto; padding: 2px 7px; border-radius: 999px; background: #fff1f2; color: #e11d48; font-size: 10px; font-weight: 700; }
.vp-pick__note { padding: 12px 8px; text-align: center; font-size: 12px; color: #94a3b8; }
</style>
<script>
    /* SỬA 12/11 — ô chọn 1 tài khoản kiểu Select2 (Alpine): mở ra là có danh sách, gõ để tìm (chờ 250ms), ↑ ↓ Enter để chọn. */
    window.onthiAccountPick = function (url, initial) {
        return {
            url, selected: initial, open: false, q: '', items: [], active: 0, loading: false, error: '', timer: null, seq: 0,
            toggle() { this.open ? this.close() : this.show(); },
            show() {
                this.open = true;
                this.$nextTick(() => this.$refs.q && this.$refs.q.focus());
                if (!this.items.length) this.fetch();
            },
            close() { this.open = false; },
            clear() { this.selected = null; },
            search() { clearTimeout(this.timer); this.timer = setTimeout(() => this.fetch(), 250); },
            async fetch() {
                const seq = ++this.seq;
                this.loading = true; this.error = '';
                try {
                    const res = await fetch(this.url + '?q=' + encodeURIComponent(this.q.trim()), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!res.ok) throw new Error();
                    const data = await res.json();
                    if (seq !== this.seq) return;
                    this.items = data.items || []; this.active = 0;
                } catch (e) {
                    if (seq === this.seq) this.error = 'Không tải được danh sách, thử lại sau.';
                } finally {
                    if (seq === this.seq) this.loading = false;
                }
            },
            move(d) { if (this.items.length) this.active = (this.active + d + this.items.length) % this.items.length; },
            pick(it) { if (!it) return; this.selected = it; this.close(); },
        };
    };
    /* Không cho gửi form khi chưa chọn tài khoản. */
    document.addEventListener('submit', function (e) {
        var f = e.target, acc = f.querySelector && f.querySelector('.vp-pick input[name="account"]');
        if (acc && !acc.value) { e.preventDefault(); alert('Hãy chọn tài khoản cần xử phạt.'); }
    }, true);
</script>
<style>
/* SỬA 12/11 (khách: "xây lại màn đó layout cho đẹp") — bố cục mới: số liệu · danh sách full ngang dạng thẻ · form xử phạt trong khung trượt. */
.vpx-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.vpx-stat { display: flex; align-items: center; gap: 14px; padding: 16px 18px; border: 1px solid #e0ecf8; border-radius: 20px; background: #fff; box-shadow: 0 2px 8px rgba(0, 90, 180, .04); }
.vpx-stat__ic { display: grid; place-items: center; width: 44px; height: 44px; flex: 0 0 44px; border-radius: 14px; }
.vpx-stat__ic svg { width: 20px; height: 20px; }
.vpx-stat p { font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #94a3b8; }
.vpx-stat strong { display: block; margin-top: 2px; font-size: 24px; font-weight: 900; line-height: 1.1; color: #0f172a; }
.vpx-stat small { font-size: 11px; color: #64748b; }
.vpx-panel { overflow: hidden; border: 1px solid #e0ecf8; border-radius: 24px; background: #fff; box-shadow: 0 2px 8px rgba(0, 90, 180, .04); }
.vpx-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 18px; border-bottom: 1px solid #eef3f8; }
.vpx-seg { display: inline-flex; gap: 4px; padding: 4px; border-radius: 12px; background: #f1f5f9; }
.vpx-seg a { padding: 7px 14px; border-radius: 9px; font-size: 12px; font-weight: 700; color: #64748b; white-space: nowrap; }
.vpx-seg a.is-on { background: #fff; color: #1d4ed8; box-shadow: 0 1px 3px rgba(15, 23, 42, .08); }
.vpx-find { display: flex; align-items: center; gap: 8px; min-width: 240px; flex: 0 1 320px; height: 38px; padding: 0 12px; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; }
.vpx-find input { flex: 1; min-width: 0; border: 0; outline: none; box-shadow: none; padding: 0; font-size: 13px; background: transparent; }
.vpx-row { display: grid; grid-template-columns: minmax(220px, 1.3fr) minmax(200px, 1.4fr) minmax(190px, 1fr) auto; gap: 18px; align-items: center; padding: 16px 18px; border-bottom: 1px solid #f1f5f9; }
.vpx-row:last-child { border-bottom: 0; }
.vpx-row:hover { background: #fafcff; }
.vpx-acc { display: flex; align-items: center; gap: 12px; min-width: 0; }
.vpx-av { position: relative; width: 44px; height: 44px; flex: 0 0 44px; }
.vpx-av img { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; background: #eef4f8; }
.vpx-ini { display: grid; place-items: center; width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #dbeafe, #e0e7ff); color: #1d4ed8; font-size: 14px; font-weight: 800; }
.vpx-av i { position: absolute; right: -2px; bottom: -2px; display: grid; place-items: center; width: 18px; height: 18px; border: 2px solid #fff; border-radius: 50%; background: #e11d48; color: #fff; }
.vpx-av i svg { width: 9px; height: 9px; }
.vpx-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 14px; font-weight: 800; color: #0f172a; }
.vpx-sub { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 11.5px; color: #94a3b8; }
.vpx-reason { font-size: 13px; font-weight: 600; color: #334155; }
.vpx-note { margin-top: 3px; font-size: 11.5px; color: #94a3b8; }
.vpx-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 7px; }
.vpx-chip { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 8px; font-size: 11px; font-weight: 700; white-space: nowrap; }
.vpx-chip svg { width: 11px; height: 11px; }
.vpx-chip--lock { background: #fff7e6; color: #b45309; }
.vpx-chip--minus { background: #fff1f2; color: #e11d48; }
.vpx-chip--none { background: #f1f5f9; color: #94a3b8; }
.vpx-chip--score { background: #ecfdf5; color: #047857; }
.vpx-time { position: relative; display: grid; gap: 8px; padding-left: 16px; font-size: 12px; }
.vpx-time::before { content: ""; position: absolute; left: 4px; top: 6px; bottom: 6px; width: 2px; border-radius: 2px; background: #e2e8f0; }
.vpx-time div { position: relative; }
.vpx-time div::before { content: ""; position: absolute; left: -16px; top: 4px; width: 10px; height: 10px; border: 2px solid #fff; border-radius: 50%; background: #e11d48; box-shadow: 0 0 0 1px #fecdd3; }
.vpx-time div + div::before { background: #10b981; box-shadow: 0 0 0 1px #a7f3d0; }
.vpx-time small { display: block; font-size: 10.5px; color: #94a3b8; }
.vpx-time b { font-weight: 700; color: #334155; white-space: nowrap; }
.vpx-side { display: flex; flex-direction: column; align-items: flex-end; gap: 8px; }
.vpx-acts { display: flex; gap: 6px; }
.vpx-revoke { grid-column: 1 / -1; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 12px; border: 1px dashed #fecdd3; border-radius: 14px; background: #fff8f8; }
.vpx-revoke input { flex: 1 1 260px; }
.vpx-empty { padding: 56px 20px; text-align: center; }
.vpx-empty > span { display: inline-grid; place-items: center; width: 60px; height: 60px; margin-bottom: 12px; border-radius: 18px; background: #ecfdf5; color: #059669; }
.vpx-empty h3 { font-size: 15px; font-weight: 800; color: #0f172a; }
.vpx-cta { display: inline-flex; align-items: center; gap: 7px; margin-top: 16px; padding: 10px 18px; border: 1px solid #fecdd3; border-radius: 12px; background: #fff1f2; color: #e11d48; font-size: 13px; font-weight: 700; transition: background .15s, box-shadow .15s; }
.vpx-cta:hover { background: #ffe4e6; box-shadow: 0 4px 12px rgba(225, 29, 72, .12); }
.vpx-cta svg { width: 15px; height: 15px; }
.vpx-empty p { margin-top: 4px; font-size: 12.5px; color: #64748b; }
.vpx-help { display: flex; gap: 10px; padding: 12px 18px; border-top: 1px solid #eef3f8; background: #f8fbfe; font-size: 11.5px; line-height: 1.6; color: #64748b; }
.vpx-help svg { width: 15px; height: 15px; flex: 0 0 auto; margin-top: 2px; color: #3b82f6; }
/* Khung trượt form xử phạt */
@keyframes vpxIn { from { transform: translateX(100%); } to { transform: none; } }
.vpx-mask { position: fixed; inset: 0; z-index: 60; background: rgba(15, 23, 42, .4); backdrop-filter: blur(2px); }
.vpx-drawer { animation: vpxIn .2s ease-out; position: fixed; z-index: 61; top: 0; right: 0; bottom: 0; display: flex; flex-direction: column; width: min(480px, 100vw); background: #fff; box-shadow: -18px 0 48px rgba(15, 23, 42, .18); }
.vpx-drawer__hd { display: flex; align-items: center; gap: 12px; padding: 18px 20px; border-bottom: 1px solid #eef3f8; }
.vpx-drawer__hd > span { display: grid; place-items: center; width: 40px; height: 40px; border-radius: 12px; background: #fff1f2; color: #e11d48; }
.vpx-drawer__hd h2 { font-size: 16px; font-weight: 800; color: #0f172a; }
.vpx-drawer__hd p { font-size: 11.5px; color: #64748b; }
.vpx-drawer__hd > button { margin-left: auto; display: grid; place-items: center; width: 34px; height: 34px; border-radius: 10px; color: #64748b; }
.vpx-drawer__hd > button:hover { background: #f1f5f9; }
.vpx-drawer__bd { flex: 1; overflow-y: auto; padding: 18px 20px; display: grid; gap: 16px; align-content: start; }
.vpx-drawer__ft { display: flex; gap: 10px; padding: 14px 20px; border-top: 1px solid #eef3f8; background: #f8fbfe; }
.vpx-drawer__ft > * { flex: 1; }
.vpx-sec { font-size: 11px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #94a3b8; }
.vpx-days { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 6px; }
.vpx-days label { position: relative; }
.vpx-days input { position: absolute; opacity: 0; pointer-events: none; }
.vpx-days span { display: grid; place-items: center; height: 38px; border: 1px solid #e2e8f0; border-radius: 11px; font-size: 12px; font-weight: 700; color: #475569; cursor: pointer; transition: all .12s; }
.vpx-days span:hover { border-color: #93c5fd; }
.vpx-days input:checked + span { border-color: #f59e0b; background: #fffbeb; color: #b45309; box-shadow: 0 0 0 3px #fef3c7; }
.vpx-days input:focus-visible + span { outline: 2px solid #60a5fa; }
.vpx-switch { display: flex; align-items: flex-start; gap: 10px; padding: 12px; border: 1px solid #e2e8f0; border-radius: 14px; cursor: pointer; }
.vpx-switch b { display: block; font-size: 12.5px; color: #1e293b; }
.vpx-switch small { font-size: 11px; color: #94a3b8; }
@media (max-width: 1100px) {
  .vpx-row { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
  .vpx-side { grid-column: 1 / -1; flex-direction: row; justify-content: space-between; align-items: center; }
}
@media (max-width: 640px) {
  .vpx-stats { grid-template-columns: 1fr; }
  .vpx-row { grid-template-columns: 1fr; }
  .vpx-find { flex: 1 1 100%; min-width: 0; }
}
</style>
@php
    $penalties = $penalties ?? collect();
    $counts = $counts ?? ['locked' => 0, 'all' => 0, 'tokens' => 0];
    $tz = 'Asia/Ho_Chi_Minh';
    $fmtDate = fn ($d) => $d ? $d->copy()->setTimezone($tz)->format('H:i · d/m/Y') : '—';
    $nf = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
    $oldReason = old('reason', $reasons[0] ?? '');
    $isPreset = in_array($oldReason, $reasons, true);
    $oldDays = (int) old('lock_days', 7);
@endphp

@if (session('status') === 'penalty-created')
    @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã xử phạt — tài khoản đã hiện ở tab "Tài khoản vi phạm".'])
@elseif (session('status') === 'penalty-unlocked')
    @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã mở khóa sớm, tài khoản trở lại bảng xếp hạng.'])
@elseif (session('status') === 'penalty-revoked')
    @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã hủy hình phạt và hoàn token.'])
@endif

<div class="space-y-4" x-data="{ formOpen: @js($errors->any()), q: '', norm(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd'); }, hit(s) { return !this.q.trim() || this.norm(s).includes(this.norm(this.q.trim())); } }"
     @keydown.escape.window="formOpen = false">

    <x-ws.page-header title="Tài khoản vi phạm" icon="ban"
                      subtitle="Khóa tài khoản khỏi bảng xếp hạng, trừ điểm / token. Tài khoản đang khóa hiện công khai ở tab “Tài khoản vi phạm”.">
        <x-slot:actions>
            <x-ws.btn type="button" variant="onhero" icon="plus" x-on:click="formOpen = true">Xử phạt tài khoản</x-ws.btn>
            <x-ws.btn :href="route('leaderboard.index', ['scope' => 'violations'])" variant="onhero-ghost" icon="external-link" target="_blank">Trang công khai</x-ws.btn>
        </x-slot:actions>
    </x-ws.page-header>

    @if (! ($ready ?? true))
        <div class="rounded-3xl border border-amber-200 bg-amber-50 p-4 text-[13px] text-amber-800">
            Chưa có bảng dữ liệu <code>leaderboard_penalties</code>. Hãy chạy <code>php artisan migrate</code> rồi tải lại trang.
        </div>
    @endif

    {{-- Số liệu --}}
    <div class="vpx-stats">
        <div class="vpx-stat">
            <span class="vpx-stat__ic" style="background:#fff1f2;color:#e11d48"><x-lucide name="lock" /></span>
            <div><p>Đang bị khóa</p><strong>{{ number_format($counts['locked'], 0, ',', '.') }}</strong><small>Đang hiện ở trang công khai</small></div>
        </div>
        <div class="vpx-stat">
            <span class="vpx-stat__ic" style="background:#fffbeb;color:#d97706"><x-lucide name="history" /></span>
            <div><p>Tổng lượt xử phạt</p><strong>{{ number_format($counts['all'], 0, ',', '.') }}</strong><small>Kể cả đã mở khóa / đã hủy</small></div>
        </div>
        <div class="vpx-stat">
            <span class="vpx-stat__ic" style="background:#f5f3ff;color:#7c3aed"><x-lucide name="wallet-cards" /></span>
            <div><p>Token đã trừ</p><strong>{{ number_format($counts['tokens'], 0, ',', '.') }}</strong><small>Không tính hình phạt đã hủy</small></div>
        </div>
    </div>

    {{-- Danh sách --}}
    <section class="vpx-panel">
        <div class="vpx-bar">
            <nav class="vpx-seg" aria-label="Lọc trạng thái">
                <a href="{{ route('admin.ranking.violations.index', ['filter' => 'locked']) }}" class="{{ $filter === 'locked' ? 'is-on' : '' }}">Đang khóa · {{ $counts['locked'] }}</a>
                <a href="{{ route('admin.ranking.violations.index', ['filter' => 'all']) }}" class="{{ $filter === 'all' ? 'is-on' : '' }}">Tất cả · {{ $counts['all'] }}</a>
            </nav>
            <label class="vpx-find">
                <x-lucide name="search" class="h-4 w-4 text-slate-400" />
                <input type="search" x-model="q" placeholder="Tìm theo tên, email, mã, lý do…" aria-label="Tìm trong danh sách">
            </label>
        </div>

        @forelse ($penalties as $p)
            @php
                $status = $p->statusLabel();
                $tone = match ($status) { 'Bị khóa' => 'danger', 'Đã hủy' => 'neutral', default => 'success' };
                $search = implode(' ', [$p->user->name ?? '', $p->user->email ?? '', $p->user->phone ?? '', $p->accountCode(), $p->reason, $p->note]);
            @endphp
            <article class="vpx-row" x-data="{ revoke: false }" x-show="hit(@js($search))">
                {{-- Tài khoản --}}
                <div class="vpx-acc">
                    <span class="vpx-av">
                        @php $__av = \App\Services\Account\AvatarService::url($p->user->avatar_path ?? null); @endphp
                        @if ($__av)
                            <img src="{{ $__av }}" alt="" loading="lazy">
                        @else
                            <b class="vpx-ini">{{ \App\Services\Admin\LeaderboardPenaltyService::initials((string) ($p->user->name ?? '?')) }}</b>
                        @endif
                        @if ($p->isLocked())<i title="Đang khóa"><x-lucide name="lock" /></i>@endif
                    </span>
                    <div class="min-w-0">
                        <p class="vpx-name">{{ $p->user->name ?? 'Tài khoản đã xoá' }}</p>
                        <p class="vpx-sub">{{ $p->accountCode() }}@if ($p->user?->email) · {{ $p->user->email }}@endif</p>
                        <div class="vpx-chips">
                            <span class="vpx-chip vpx-chip--score" title="Điểm xếp hạng trước khi áp mức trừ"><x-lucide name="trophy" /> {{ $p->score_at_lock !== null ? $nf($p->score_at_lock) : '—' }} điểm</span>
                            @if ($p->hide_name)<span class="vpx-chip vpx-chip--none"><x-lucide name="eye-off" /> Ẩn tên</span>@endif
                        </div>
                    </div>
                </div>

                {{-- Lý do + mức phạt --}}
                <div class="min-w-0">
                    <p class="vpx-reason">{{ $p->reason }}</p>
                    @if ($p->note)<p class="vpx-note">{{ $p->note }}</p>@endif
                    <div class="vpx-chips">
                        <span class="vpx-chip vpx-chip--lock"><x-lucide name="lock-keyhole" /> {{ $p->lockLabel() }}</span>
                        @if ($p->points_deducted > 0)
                            <span class="vpx-chip vpx-chip--minus">-{{ $nf($p->points_deducted) }} điểm</span>
                        @else
                            <span class="vpx-chip vpx-chip--none">Không trừ điểm</span>
                        @endif
                        @if ($p->tokens_deducted > 0)
                            <span class="vpx-chip vpx-chip--minus">-{{ number_format($p->tokens_deducted, 0, ',', '.') }} token</span>
                        @else
                            <span class="vpx-chip vpx-chip--none">Không trừ token</span>
                        @endif
                    </div>
                    @if ($p->revoke_reason)<p class="vpx-note">Lý do hủy: {{ $p->revoke_reason }}</p>@endif
                </div>

                {{-- Mốc thời gian --}}
                <div class="vpx-time">
                    <div><small>Khóa lúc · bởi {{ $p->creator->name ?? '—' }}</small><b>{{ $fmtDate($p->locked_at) }}</b></div>
                    <div>
                        <small>{{ $p->unlocked_at ? 'Đã mở khóa sớm' : ($p->unlock_at ? ($p->isLocked() ? 'Dự kiến mở khóa' : 'Đã hết hạn') : 'Mở khóa') }}</small>
                        <b>{{ $p->unlocked_at ? $fmtDate($p->unlocked_at) : ($p->unlock_at ? $fmtDate($p->unlock_at) : 'Vĩnh viễn') }}</b>
                    </div>
                </div>

                {{-- Trạng thái + thao tác --}}
                <div class="vpx-side">
                    <x-ws.badge :tone="$tone">{{ $status }}</x-ws.badge>
                    <div class="vpx-acts">
                        @if ($p->isLocked())
                            <form method="POST" action="{{ route('admin.ranking.violations.unlock', $p->id) }}" onsubmit="return confirm('Mở khóa sớm tài khoản này? Điểm / token đã trừ giữ nguyên.')">
                                @csrf
                                <x-ws.btn size="sm" variant="success" icon="lock-open">Mở khóa</x-ws.btn>
                            </form>
                        @endif
                        @if (! $p->revoked_at)
                            <x-ws.btn size="sm" variant="ghost" type="button" icon="rotate-ccw" x-on:click="revoke = !revoke">Hủy phạt</x-ws.btn>
                        @endif
                    </div>
                </div>

                @if (! $p->revoked_at)
                    <form method="POST" action="{{ route('admin.ranking.violations.destroy', $p->id) }}" class="vpx-revoke" x-show="revoke" x-cloak x-transition.opacity>
                        @csrf
                        @method('DELETE')
                        <x-lucide name="circle-alert" class="h-4 w-4 shrink-0 text-rose-500" />
                        <input name="revoke_reason" class="admin-input" required maxlength="500" placeholder="Lý do hủy (VD: xử nhầm tài khoản)">
                        <x-ws.btn size="sm" variant="danger" icon="rotate-ccw">Hủy & hoàn {{ number_format($p->tokens_deducted, 0, ',', '.') }} token</x-ws.btn>
                        <x-ws.btn size="sm" variant="ghost" type="button" x-on:click="revoke = false">Thôi</x-ws.btn>
                    </form>
                @endif
            </article>
        @empty
            <div class="vpx-empty">
                <span><x-lucide name="shield-check" class="h-7 w-7" /></span>
                <h3>{{ $filter === 'locked' ? 'Không có tài khoản nào đang bị khóa' : 'Chưa xử phạt tài khoản nào' }}</h3>
                <p>Khi phát hiện gian lận, bấm “Xử phạt tài khoản” để khóa và trừ điểm / token.</p>
                <button type="button" class="vpx-cta" @click="formOpen = true"><x-lucide name="plus" /> Xử phạt tài khoản</button>
            </div>
        @endforelse

        <div class="vpx-help">
            <x-lucide name="info" />
            <p><b>Mở khóa</b>: tài khoản trở lại bảng xếp hạng ngay, điểm / token đã trừ giữ nguyên. <b>Hủy phạt</b>: dành cho xử nhầm — hoàn lại token, bỏ trừ điểm và gỡ khỏi danh sách công khai.</p>
        </div>
    </section>

    {{-- ── Khung trượt: form xử phạt ── --}}
    <div class="vpx-mask" x-show="formOpen" x-cloak x-transition.opacity @click="formOpen = false"></div>
    <aside class="vpx-drawer" x-show="formOpen" x-cloak role="dialog" aria-modal="true" aria-labelledby="vpx-form-title"
>
        <form method="POST" action="{{ route('admin.ranking.violations.store') }}" class="flex h-full flex-col"
              x-data="{ preset: @js($isPreset ? $oldReason : '__other'), custom: @js($isPreset ? '' : $oldReason) }"
              onsubmit="return this.querySelector('.vp-pick input[name=account]').value ? confirm('Xác nhận xử phạt tài khoản này?') : true">
            @csrf
            <div class="vpx-drawer__hd">
                <span><x-lucide name="ban" class="h-5 w-5" /></span>
                <div>
                    <h2 id="vpx-form-title">Xử phạt tài khoản</h2>
                    <p>Áp dụng ngay — hiện ở tab công khai “Tài khoản vi phạm”.</p>
                </div>
                <button type="button" @click="formOpen = false" aria-label="Đóng"><x-lucide name="x" class="h-5 w-5" /></button>
            </div>

            <div class="vpx-drawer__bd">
                <p class="vpx-sec">1 · Tài khoản</p>
            {{-- SỬA 12/11 (khách: "chọn tài khoản dạng select2, search để chọn, không cho nhập") — ô chọn có tìm kiếm,
                 gợi ý lấy từ admin.ranking.violations.accounts; gửi đi là ID tài khoản (input ẩn). --}}
            <x-ws.field label="Tài khoản" name="account" required hint="Gõ tên, email, SĐT hoặc ID để tìm học sinh.">
                <div class="vp-pick" x-data="onthiAccountPick(@js(route('admin.ranking.violations.accounts')), @js(old('account') ? ['id' => old('account'), 'name' => old('account_label', '#'.old('account')), 'meta' => ''] : null))"
                     @click.outside="close()" @keydown.escape.stop.prevent="close()">
                    <input type="hidden" name="account" :value="selected ? selected.id : ''">
                    <input type="hidden" name="account_label" :value="selected ? selected.name : ''">
                    <button type="button" id="account" class="vp-pick__box" :class="{ 'is-open': open }" @click="toggle()" :aria-expanded="open" aria-haspopup="listbox">
                        <template x-if="selected">
                            <span class="vp-pick__val">
                                <img :src="selected.avatar" alt="" x-show="selected.avatar">
                                <i class="vp-pick__ini" x-show="!selected.avatar" x-text="selected.initials || '?'"></i>
                                <span class="min-w-0"><b x-text="selected.name"></b><small x-text="selected.meta"></small></span>
                            </span>
                        </template>
                        <span class="vp-pick__ph" x-show="!selected">— Chọn tài khoản —</span>
                        <span class="vp-pick__x" x-show="selected" @click.stop="clear()" role="button" aria-label="Bỏ chọn"><x-lucide name="x" class="h-3.5 w-3.5" /></span>
                        <x-lucide name="chevron-down" class="h-4 w-4 shrink-0 text-slate-400" />
                    </button>
                    <div class="vp-pick__drop" x-show="open" x-cloak x-transition.opacity.duration.100ms>
                        <label class="vp-pick__search">
                            <x-lucide name="search" class="h-4 w-4 text-slate-400" />
                            <input type="text" x-ref="q" x-model="q" @input="search()" autocomplete="off" placeholder="Tìm theo tên, email, SĐT, ID…"
                                   @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter.prevent="pick(items[active])">
                        </label>
                        <ul class="vp-pick__list" role="listbox">
                            <li class="vp-pick__note" x-show="loading">Đang tìm…</li>
                            <li class="vp-pick__note" x-show="!loading && error" x-text="error"></li>
                            <li class="vp-pick__note" x-show="!loading && !error && items.length === 0">Không tìm thấy học sinh phù hợp</li>
                            <template x-for="(it, i) in items" :key="it.id">
                                <li role="option" class="vp-pick__opt" :class="{ 'is-active': i === active, 'is-sel': selected && selected.id == it.id }"
                                    @mouseenter="active = i" @click="pick(it)" :aria-selected="selected && selected.id == it.id">
                                    <img :src="it.avatar" alt="" x-show="it.avatar">
                                    <i class="vp-pick__ini" x-show="!it.avatar" x-text="it.initials"></i>
                                    <span class="min-w-0 flex-1"><b x-text="it.name"></b><small x-text="it.meta"></small></span>
                                    <span class="vp-pick__lock" x-show="it.locked">Đang khóa</span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </x-ws.field>

                <p class="vpx-sec">2 · Vi phạm</p>
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

                <x-ws.field label="Ghi chú nội bộ" name="note" hint="Bằng chứng, mã lượt thi… Không hiện công khai.">
                    <textarea id="note" name="note" rows="2" class="admin-input" placeholder="VD: trùng IP với tài khoản #1032 ở kỳ thi tháng 11">{{ old('note') }}</textarea>
                </x-ws.field>

                <p class="vpx-sec">3 · Hình phạt</p>
                <x-ws.field label="Thời gian khóa" name="lock_days" required hint="Gỡ khỏi bảng xếp hạng trong thời gian này, hết hạn tự mở.">
                    <div class="vpx-days">
                        @foreach ($lockDays as $d)
                            <label>
                                <input type="radio" name="lock_days" value="{{ $d }}" @checked($oldDays === $d)>
                                <span>{{ $d > 0 ? $d.' ngày' : 'Vĩnh viễn' }}</span>
                            </label>
                        @endforeach
                    </div>
                </x-ws.field>

                <div class="grid grid-cols-2 gap-3">
                    <x-ws.field label="Trừ điểm" name="points_deducted" hint="Trừ vào tổng điểm xếp hạng.">
                        <input id="points_deducted" name="points_deducted" type="number" min="0" step="0.01" class="admin-input" value="{{ old('points_deducted', 0) }}">
                    </x-ws.field>
                    <x-ws.field label="Trừ token" name="tokens_deducted" hint="Tối đa bằng số dư ví.">
                        <input id="tokens_deducted" name="tokens_deducted" type="number" min="0" step="1" class="admin-input" value="{{ old('tokens_deducted', 0) }}">
                    </x-ws.field>
                </div>

                <label class="vpx-switch">
                    <input type="hidden" name="hide_name" value="0">
                    <input type="checkbox" name="hide_name" value="1" class="mt-0.5 rounded border-slate-300" @checked(old('hide_name'))>
                    <span><b>Ẩn tên trên trang công khai</b><small>Chỉ hiện mã tài khoản dạng VP-000123 thay cho tên thật.</small></span>
                </label>
            </div>

            <div class="vpx-drawer__ft">
                <x-ws.btn type="button" variant="ghost" size="lg" x-on:click="formOpen = false">Huỷ</x-ws.btn>
                <x-ws.btn variant="danger" size="lg" icon="lock">Xử phạt</x-ws.btn>
            </div>
        </form>
    </aside>
</div>
@endsection
