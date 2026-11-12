@extends('layouts.admin')

@section('title', 'Tài khoản vi phạm')
@section('page-title', 'Tài khoản vi phạm')

@section('content')
<style>
.vp-pick { position: relative; }
.vp-pick__box { display: flex; width: 100%; min-height: 46px; align-items: center; gap: 8px; padding: 6px 12px; border: 1px solid #dbeafe; border-radius: 14px; background: #fff; text-align: left; transition: border-color .15s, box-shadow .15s; }
.vp-pick__box:hover { border-color: #bfdbfe; }
.vp-pick__box.is-open, .vp-pick__box:focus-visible { outline: none; border-color: #60a5fa; box-shadow: 0 0 0 3px #dbeafe; }
.vp-pick__val { display: flex; flex: 1; min-width: 0; align-items: center; gap: 9px; }
.vp-pick__val img, .vp-pick__opt img { width: 28px; height: 28px; flex: 0 0 28px; border-radius: 50%; object-fit: cover; background: #eef4f8; }
.vp-pick__val b, .vp-pick__opt b { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 13px; font-weight: 700; color: #1e293b; }
.vp-pick__val small, .vp-pick__opt small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 11px; color: #94a3b8; }
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

<x-ws.page-header title="Tài khoản vi phạm" icon="ban"
                  subtitle="Khóa tài khoản khỏi bảng xếp hạng, trừ điểm / token. Tài khoản đang khóa hiện công khai ở tab “Tài khoản vi phạm”.">
    <x-slot:actions>
        <x-ws.btn :href="route('leaderboard.index', ['scope' => 'violations'])" variant="onhero-ghost" icon="external-link" target="_blank">Trang công khai</x-ws.btn>
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
            {{-- SỬA 12/11 (khách: "chọn tài khoản dạng select2, search để chọn, không cho nhập") — ô chọn có tìm kiếm,
                 gợi ý lấy từ admin.ranking.violations.accounts; gửi đi là ID tài khoản (input ẩn). --}}
            <x-ws.field label="Tài khoản" name="account" required hint="Bấm để chọn — gõ tên, email, SĐT hoặc ID để tìm học sinh.">
                <div class="vp-pick" x-data="onthiAccountPick(@js(route('admin.ranking.violations.accounts')), @js(old('account') ? ['id' => old('account'), 'name' => old('account_label', '#'.old('account')), 'meta' => ''] : null))"
                     @click.outside="close()" @keydown.escape.prevent="close()">
                    <input type="hidden" name="account" :value="selected ? selected.id : ''">
                    <input type="hidden" name="account_label" :value="selected ? selected.name : ''">
                    <button type="button" id="account" class="vp-pick__box" :class="{ 'is-open': open }" @click="toggle()" :aria-expanded="open" aria-haspopup="listbox">
                        <template x-if="selected">
                            <span class="vp-pick__val">
                                <img :src="selected.avatar" alt="" x-show="selected.avatar">
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
                                    <span class="min-w-0 flex-1"><b x-text="it.name"></b><small x-text="it.meta"></small></span>
                                    <span class="vp-pick__lock" x-show="it.locked">Đang khóa</span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
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
