{{-- SỬA 12/11 (khách: "không cần màn riêng — gộp sang Người dùng: nút Khóa ở từng dòng, bấm thì hiện popup xử lý đầy đủ thông tin") —
     2 popup dùng chung cho danh sách Admin › Người dùng:
       · Khóa tài khoản (khung trượt bên phải): lý do, thời gian khóa, trừ điểm / token, ẩn tên / ảnh công khai, ghi chú nội bộ.
         Gửi tới admin.users.lock (LeaderboardPenaltyController::lockUser) → users.status = suspended (không đăng nhập được,
         phiên đang mở bị đăng xuất), gỡ khỏi bảng xếp hạng, hiện ở tab công khai "Tài khoản vi phạm".
       · Mở khóa (hộp thoại giữa màn hình): mở sớm giữ nguyên mức trừ, hoặc hủy hình phạt (hoàn token, bỏ trừ điểm).
     Mở bằng sự kiện cửa sổ 'user-lock' / 'user-unlock' kèm dữ liệu dòng (nút ở admin/users/index). --}}
@php
    $lockReasons = \App\Models\LeaderboardPenalty::REASONS;
    $lockDayOptions = \App\Models\LeaderboardPenalty::LOCK_DAYS;
    $lockAgainId = session('lockUser');
@endphp
<style>
.ulk-mask { position: fixed; inset: 0; z-index: 80; background: rgba(15, 23, 42, .45); backdrop-filter: blur(3px); }
.ulk-drawer { position: fixed; z-index: 81; top: 0; right: 0; bottom: 0; display: flex; flex-direction: column; width: min(520px, 100vw); background: #fff; box-shadow: -24px 0 60px rgba(15, 23, 42, .22); animation: ulkIn .22s ease-out; }
@keyframes ulkIn { from { transform: translateX(100%); } to { transform: none; } }
@keyframes ulkPop { from { transform: translate(-50%, -46%); opacity: 0; } to { transform: translate(-50%, -50%); opacity: 1; } }
.ulk-hd { display: flex; align-items: center; gap: 12px; padding: 18px 22px; border-bottom: 1px solid #eef2f7; }
.ulk-hd__ic { display: grid; place-items: center; width: 42px; height: 42px; flex: 0 0 42px; border-radius: 13px; background: #fff1f2; color: #e11d48; }
.ulk-hd__ic.is-ok { background: #ecfdf5; color: #059669; }
.ulk-hd__ic svg { width: 21px; height: 21px; }
.ulk-hd h2 { font-size: 17px; font-weight: 800; color: #0f172a; }
.ulk-hd p { font-size: 12px; color: #64748b; }
.ulk-x { margin-left: auto; display: grid; place-items: center; width: 36px; height: 36px; border-radius: 11px; color: #64748b; }
.ulk-x:hover { background: #f1f5f9; color: #0f172a; }
.ulk-x svg { width: 19px; height: 19px; }
.ulk-bd { flex: 1; overflow-y: auto; padding: 18px 22px 22px; display: grid; gap: 18px; align-content: start; }
.ulk-user { display: flex; align-items: center; gap: 14px; padding: 14px; border: 1px solid #e2e8f0; border-radius: 18px; background: linear-gradient(135deg, #f8fbff, #fff); }
.ulk-user img { width: 52px; height: 52px; flex: 0 0 52px; border-radius: 50%; object-fit: cover; background: #eef4f8; box-shadow: 0 0 0 3px #fff, 0 0 0 4px #e2e8f0; }
.ulk-user b { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 15px; font-weight: 800; color: #0f172a; }
.ulk-user small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; color: #64748b; }
.ulk-user__tags { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 6px; }
.ulk-tag { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 999px; background: #f1f5f9; color: #475569; font-size: 10.5px; font-weight: 700; }
.ulk-tag svg { width: 11px; height: 11px; }
.ulk-flow { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 14px; background: #f8fafc; font-size: 12px; font-weight: 700; color: #475569; }
.ulk-flow span { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; }
.ulk-flow .is-on { background: #dcfce7; color: #047857; }
.ulk-flow .is-off { background: #ffe4e6; color: #be123c; }
.ulk-flow i { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
.ulk-flow svg { width: 16px; height: 16px; color: #94a3b8; }
.ulk-sec { display: grid; gap: 10px; }
.ulk-sec > h3 { display: flex; align-items: center; gap: 8px; font-size: 11px; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; color: #94a3b8; }
.ulk-sec > h3 em { display: grid; place-items: center; width: 20px; height: 20px; border-radius: 7px; background: #eef2ff; color: #4f46e5; font-size: 10.5px; font-style: normal; }
.ulk-pills { display: flex; flex-wrap: wrap; gap: 7px; }
.ulk-pill { position: relative; }
.ulk-pill input { position: absolute; opacity: 0; pointer-events: none; }
.ulk-pill span { display: inline-flex; align-items: center; min-height: 36px; padding: 7px 13px; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; font-size: 12.5px; font-weight: 600; color: #334155; cursor: pointer; transition: all .12s; }
.ulk-pill span:hover { border-color: #fda4af; }
.ulk-pill input:checked + span { border-color: #e11d48; background: #fff1f2; color: #be123c; box-shadow: 0 0 0 3px #ffe4e6; font-weight: 700; }
.ulk-pill input:focus-visible + span { outline: 2px solid #60a5fa; outline-offset: 2px; }
.ulk-days { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 7px; }
.ulk-days .ulk-pill span { justify-content: center; width: 100%; }
.ulk-days .ulk-pill input:checked + span { border-color: #f59e0b; background: #fffbeb; color: #b45309; box-shadow: 0 0 0 3px #fef3c7; }
.ulk-until { display: flex; align-items: center; gap: 8px; font-size: 12px; color: #64748b; }
.ulk-until svg { width: 14px; height: 14px; color: #f59e0b; }
.ulk-until b { color: #0f172a; }
.ulk-input { width: 100%; min-height: 42px; padding: 9px 12px; border: 1px solid #dbe3ee; border-radius: 12px; background: #fff; font-size: 13px; color: #0f172a; outline: none; transition: border-color .12s, box-shadow .12s; }
.ulk-input:focus { border-color: #60a5fa; box-shadow: 0 0 0 3px #dbeafe; }
.ulk-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.ulk-lbl { display: block; margin-bottom: 6px; font-size: 12px; font-weight: 700; color: #334155; }
.ulk-help { margin-top: 5px; font-size: 11px; color: #94a3b8; }
.ulk-num { position: relative; }
.ulk-num em { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); font-size: 11px; font-style: normal; font-weight: 700; color: #94a3b8; pointer-events: none; }
.ulk-num .ulk-input { padding-right: 52px; }
.ulk-quick { display: flex; gap: 5px; margin-top: 7px; }
.ulk-quick button { flex: 1; padding: 4px 0; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; font-size: 11px; font-weight: 700; color: #64748b; }
.ulk-quick button:hover { border-color: #fda4af; color: #be123c; }
.ulk-quick button.is-on { border-color: #e11d48; background: #fff1f2; color: #be123c; }
.ulk-toggle { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 14px; cursor: pointer; transition: border-color .12s; }
.ulk-toggle:hover { border-color: #cbd5e1; }
.ulk-toggle input { position: absolute; opacity: 0; pointer-events: none; }
.ulk-toggle__sw { position: relative; flex: 0 0 38px; height: 22px; border-radius: 999px; background: #cbd5e1; transition: background .15s; }
.ulk-toggle__sw::after { content: ""; position: absolute; top: 3px; left: 3px; width: 16px; height: 16px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(0, 0, 0, .2); transition: transform .15s; }
.ulk-toggle input:checked + .ulk-toggle__sw { background: #2563eb; }
.ulk-toggle input:checked + .ulk-toggle__sw::after { transform: translateX(16px); }
.ulk-toggle input:focus-visible + .ulk-toggle__sw { outline: 2px solid #60a5fa; outline-offset: 2px; }
.ulk-toggle b { display: block; font-size: 12.5px; color: #1e293b; }
.ulk-toggle small { font-size: 11px; color: #94a3b8; }
.ulk-note { display: grid; gap: 7px; padding: 14px; border: 1px solid #fecdd3; border-radius: 16px; background: #fff7f8; font-size: 12px; color: #9f1239; }
.ulk-note p { display: flex; align-items: flex-start; gap: 8px; }
.ulk-note svg { width: 14px; height: 14px; flex: 0 0 auto; margin-top: 1px; }
.ulk-err { display: flex; align-items: flex-start; gap: 8px; padding: 10px 12px; border-radius: 12px; background: #fff1f2; color: #be123c; font-size: 12.5px; font-weight: 600; }
.ulk-err svg { width: 15px; height: 15px; flex: 0 0 auto; margin-top: 1px; }
.ulk-ft { display: flex; gap: 10px; padding: 14px 22px; border-top: 1px solid #eef2f7; background: #f8fafc; }
.ulk-btn { display: inline-flex; flex: 1; align-items: center; justify-content: center; gap: 7px; min-height: 46px; padding: 10px 16px; border-radius: 14px; font-size: 13.5px; font-weight: 800; transition: all .12s; }
.ulk-btn svg { width: 16px; height: 16px; }
.ulk-btn--ghost { border: 1px solid #e2e8f0; background: #fff; color: #334155; }
.ulk-btn--ghost:hover { background: #f1f5f9; }
.ulk-btn--danger { background: #e11d48; color: #fff; box-shadow: 0 6px 16px rgba(225, 29, 72, .28); }
.ulk-btn--danger:hover { background: #be123c; }
.ulk-btn--ok { background: #059669; color: #fff; box-shadow: 0 6px 16px rgba(5, 150, 105, .25); }
.ulk-btn--ok:hover { background: #047857; }
.ulk-btn:disabled { opacity: .55; cursor: not-allowed; box-shadow: none; }
/* Hộp thoại mở khóa */
.ulk-dialog { position: fixed; z-index: 81; left: 50%; top: 50%; display: flex; flex-direction: column; width: min(480px, calc(100vw - 32px)); max-height: calc(100vh - 32px); overflow: hidden; border-radius: 24px; background: #fff; box-shadow: 0 30px 80px rgba(15, 23, 42, .3); transform: translate(-50%, -50%); animation: ulkPop .18s ease-out; }
.ulk-cur { display: grid; gap: 6px; padding: 12px 14px; border-radius: 14px; background: #f8fafc; font-size: 12px; color: #475569; }
.ulk-cur div { display: flex; justify-content: space-between; gap: 12px; }
.ulk-cur b { color: #0f172a; text-align: right; }
.ulk-choice { display: flex; gap: 12px; padding: 13px 14px; border: 1px solid #e2e8f0; border-radius: 14px; cursor: pointer; transition: all .12s; }
.ulk-choice input { margin-top: 2px; accent-color: #059669; }
.ulk-choice b { display: block; font-size: 13px; color: #0f172a; }
.ulk-choice small { font-size: 11.5px; color: #64748b; }
.ulk-choice.is-on { border-color: #10b981; background: #f0fdf4; box-shadow: 0 0 0 3px #dcfce7; }
@media (max-width: 520px) { .ulk-days { grid-template-columns: repeat(3, minmax(0, 1fr)); } .ulk-grid2 { grid-template-columns: 1fr; } }
</style>

<script>
    window.onthiUserLock = function (cfg) {
        const pad = (n) => String(n).padStart(2, '0');
        return {
            reasons: cfg.reasons, lockUrlTpl: cfg.lockUrl, unlockUrlTpl: cfg.unlockUrl, fallbackAvatar: cfg.avatar,
            user: null, mode: null, // 'lock' | 'unlock'
            reason: cfg.reasons[0], customReason: '', days: 7, points: 0, tokens: 0, hideName: false, hideAvatar: false, note: '',
            unlockMode: 'keep', unlockReason: '', error: cfg.error || '', busy: false,
            init() {
                if (cfg.reopen) {
                    const btn = document.querySelector('[data-lock-open="' + cfg.reopen + '"]');
                    if (btn) this.$nextTick(() => this.openLock(JSON.parse(btn.dataset.user), true));
                }
            },
            openLock(u, keepError) {
                this.user = u; this.mode = 'lock'; this.busy = false;
                if (!keepError) { this.error = ''; this.reason = this.reasons[0]; this.customReason = ''; this.days = 7; this.points = 0; this.tokens = 0; this.hideName = false; this.hideAvatar = false; this.note = ''; }
                document.documentElement.style.overflow = 'hidden';
            },
            openUnlock(u) {
                this.user = u; this.mode = 'unlock'; this.unlockMode = 'keep'; this.unlockReason = ''; this.error = ''; this.busy = false;
                document.documentElement.style.overflow = 'hidden';
            },
            close() { this.mode = null; document.documentElement.style.overflow = ''; },
            get finalReason() { return this.reason === '__other' ? this.customReason.trim() : this.reason; },
            get untilText() {
                if (!Number(this.days)) return 'Khóa vĩnh viễn — chỉ mở khi admin bấm Mở khóa';
                const d = new Date(Date.now() + Number(this.days) * 86400000);
                return 'Tự mở khóa lúc ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ' ' + pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + '/' + d.getFullYear();
            },
            get canLock() { return !!this.finalReason && !this.busy; },
            get lockUrl() { return this.user ? this.lockUrlTpl.replace('__ID__', this.user.id) : '#'; },
            get unlockUrl() { return this.user ? this.unlockUrlTpl.replace('__ID__', this.user.id) : '#'; },
            clampPct(v) { const n = Number(v); return Number.isFinite(n) ? Math.max(0, Math.min(100, n)) : 0; },
            get tokenPreview() { return Math.floor(Number(this.user && this.user.tokens || 0) * this.clampPct(this.tokens) / 100); },
            submitLock(e) {
                if (!this.finalReason) { e.preventDefault(); this.error = 'Hãy chọn hoặc nhập lý do khóa.'; return; }
                this.points = this.clampPct(this.points); this.tokens = this.clampPct(this.tokens);
                this.busy = true;
            },
            submitUnlock(e) {
                if (this.unlockMode === 'refund' && !this.unlockReason.trim()) { e.preventDefault(); this.error = 'Hủy hình phạt cần ghi lý do.'; return; }
                this.busy = true;
            },
        };
    };
</script>

<div x-data="onthiUserLock({{ Js::from([
        'reasons' => $lockReasons,
        'lockUrl' => route('admin.users.lock', ['user' => '__ID__']),
        'unlockUrl' => route('admin.users.unlock', ['user' => '__ID__']),
        'avatar' => asset('assets/user-avatar.png'),
        'reopen' => $lockAgainId,
        'error' => $errors->first('lock'),
    ]) }})"
     @user-lock.window="openLock($event.detail)" @user-unlock.window="openUnlock($event.detail)" @keydown.escape.window="close()">

    <div class="ulk-mask" x-show="mode" x-cloak x-transition.opacity @click="close()"></div>

    {{-- ══════ KHÓA TÀI KHOẢN ══════ --}}
    <template x-if="mode === 'lock' && user">
        <aside class="ulk-drawer" role="dialog" aria-modal="true" aria-labelledby="ulkLockTitle">
            <form method="POST" :action="lockUrl" style="display:flex;flex-direction:column;height:100%" @submit="submitLock($event)">
                @csrf
                <div class="ulk-hd">
                    <span class="ulk-hd__ic"><x-lucide name="lock" /></span>
                    <div>
                        <h2 id="ulkLockTitle">Khóa tài khoản</h2>
                        <p>Áp dụng ngay — đăng xuất và chặn đăng nhập.</p>
                    </div>
                    <button type="button" class="ulk-x" @click="close()" aria-label="Đóng"><x-lucide name="x" /></button>
                </div>

                <div class="ulk-bd">
                    <div class="ulk-err" x-show="error" x-cloak><x-lucide name="circle-alert" /><span x-text="error"></span></div>

                    {{-- Người bị khóa --}}
                    <div class="ulk-user">
                        <img :src="user.avatar || fallbackAvatar" alt="">
                        <div style="min-width:0;flex:1">
                            <b x-text="user.name"></b>
                            <small x-text="user.email"></small>
                            <div class="ulk-user__tags">
                                <template x-for="r in (user.roles || [])" :key="r"><span class="ulk-tag" x-text="r"></span></template>
                                <span class="ulk-tag"><x-lucide name="wallet-cards" /><span x-text="(user.tokens || 0).toLocaleString('vi-VN') + ' token'"></span></span>
                                <span class="ulk-tag" x-show="user.province"><x-lucide name="map-pin" /><span x-text="user.province"></span></span>
                            </div>
                        </div>
                    </div>
                    <div class="ulk-flow">
                        Trạng thái
                        <span class="is-on"><i></i>Hoạt động</span>
                        <x-lucide name="arrow-right" />
                        <span class="is-off"><i></i>Đã khóa</span>
                    </div>

                    {{-- 1. Lý do --}}
                    <section class="ulk-sec">
                        <h3><em>1</em>Lý do khóa</h3>
                        <div class="ulk-pills">
                            <template x-for="r in reasons" :key="r">
                                <label class="ulk-pill"><input type="radio" :value="r" x-model="reason"><span x-text="r"></span></label>
                            </template>
                            <label class="ulk-pill"><input type="radio" value="__other" x-model="reason"><span>+ Lý do khác</span></label>
                        </div>
                        <input type="text" class="ulk-input" x-show="reason === '__other'" x-model="customReason" maxlength="255" placeholder="Nhập lý do khóa…">
                        <input type="hidden" name="reason" :value="finalReason">
                    </section>

                    {{-- 2. Thời gian khóa --}}
                    <section class="ulk-sec">
                        <h3><em>2</em>Thời gian khóa</h3>
                        <div class="ulk-days">
                            @foreach ($lockDayOptions as $d)
                                <label class="ulk-pill"><input type="radio" name="lock_days" value="{{ $d }}" x-model.number="days"><span>{{ $d > 0 ? $d.' ngày' : 'Vĩnh viễn' }}</span></label>
                            @endforeach
                        </div>
                        <p class="ulk-until"><x-lucide name="clock-3" /><b x-text="untilText"></b></p>
                    </section>

                    {{-- 3. Mức trừ — SỬA 12/11 (khách: "trừ điểm và trừ token là trừ theo %") --}}
                    <section class="ulk-sec">
                        <h3><em>3</em>Mức trừ theo % (tuỳ chọn)</h3>
                        <div class="ulk-grid2">
                            <div>
                                <span class="ulk-lbl">Trừ điểm xếp hạng</span>
                                <div class="ulk-num"><input type="number" name="points_percent" min="0" max="100" step="1" class="ulk-input" x-model.number="points" @input="points = clampPct(points)"><em>%</em></div>
                                <div class="ulk-quick">
                                    <template x-for="v in [0, 10, 25, 50, 100]" :key="'p' + v"><button type="button" :class="Number(points) === v && 'is-on'" @click="points = v" x-text="v + '%'"></button></template>
                                </div>
                                <p class="ulk-help">Tính trên tổng điểm xếp hạng lúc khóa.</p>
                            </div>
                            <div>
                                <span class="ulk-lbl">Trừ token</span>
                                <div class="ulk-num"><input type="number" name="tokens_percent" min="0" max="100" step="1" class="ulk-input" x-model.number="tokens" @input="tokens = clampPct(tokens)"><em>%</em></div>
                                <div class="ulk-quick">
                                    <template x-for="v in [0, 10, 25, 50, 100]" :key="'t' + v"><button type="button" :class="Number(tokens) === v && 'is-on'" @click="tokens = v" x-text="v + '%'"></button></template>
                                </div>
                                <p class="ulk-help">≈ trừ <b x-text="tokenPreview.toLocaleString('vi-VN')"></b> / <span x-text="(user.tokens || 0).toLocaleString('vi-VN')"></span> token trong ví.</p>
                            </div>
                        </div>
                    </section>

                    {{-- 4. Công khai --}}
                    <section class="ulk-sec">
                        <h3><em>4</em>Hiển thị ở tab “Tài khoản vi phạm”</h3>
                        <label class="ulk-toggle">
                            <input type="hidden" name="hide_name" :value="hideName ? 1 : 0">
                            <input type="checkbox" x-model="hideName"><span class="ulk-toggle__sw"></span>
                            <span><b>Ẩn tên</b><small>Chỉ hiện mã tài khoản dạng VP-000123.</small></span>
                        </label>
                        <label class="ulk-toggle">
                            <input type="hidden" name="hide_avatar" :value="hideAvatar ? 1 : 0">
                            <input type="checkbox" x-model="hideAvatar"><span class="ulk-toggle__sw"></span>
                            <span><b>Ẩn ảnh đại diện</b><small>Hiện ảnh mặc định thay cho ảnh thật.</small></span>
                        </label>
                    </section>

                    {{-- 5. Ghi chú --}}
                    <section class="ulk-sec">
                        <h3><em>5</em>Ghi chú nội bộ</h3>
                        <textarea name="note" rows="2" class="ulk-input" x-model="note" maxlength="2000" placeholder="Bằng chứng, mã lượt thi… (không hiện công khai)"></textarea>
                    </section>

                    <div class="ulk-note">
                        <p><x-lucide name="log-out" />Đăng xuất ngay trên mọi thiết bị, không đăng nhập được tới khi mở khóa.</p>
                        <p><x-lucide name="trophy" />Gỡ khỏi bảng xếp hạng trong thời gian khóa.</p>
                        <p><x-lucide name="eye" />Hiện công khai ở tab “Tài khoản vi phạm” của trang Bảng xếp hạng.</p>
                    </div>
                </div>

                <div class="ulk-ft">
                    <button type="button" class="ulk-btn ulk-btn--ghost" @click="close()">Huỷ</button>
                    <button type="submit" class="ulk-btn ulk-btn--danger" :disabled="!canLock"><x-lucide name="lock" /><span x-text="busy ? 'Đang khóa…' : 'Khóa tài khoản'"></span></button>
                </div>
            </form>
        </aside>
    </template>

    {{-- ══════ MỞ KHÓA ══════ --}}
    <template x-if="mode === 'unlock' && user">
        <div class="ulk-dialog" role="dialog" aria-modal="true" aria-labelledby="ulkUnlockTitle">
            <form method="POST" :action="unlockUrl" @submit="submitUnlock($event)" style="display:flex;flex-direction:column;min-height:0">
                @csrf
                <div class="ulk-hd">
                    <span class="ulk-hd__ic is-ok"><x-lucide name="lock-open" /></span>
                    <div>
                        <h2 id="ulkUnlockTitle">Mở khóa tài khoản</h2>
                        <p>Người dùng đăng nhập lại được ngay.</p>
                    </div>
                    <button type="button" class="ulk-x" @click="close()" aria-label="Đóng"><x-lucide name="x" /></button>
                </div>
                <div class="ulk-bd">
                    <div class="ulk-err" x-show="error" x-cloak><x-lucide name="circle-alert" /><span x-text="error"></span></div>
                    <div class="ulk-user">
                        <img :src="user.avatar || fallbackAvatar" alt="">
                        <div style="min-width:0;flex:1"><b x-text="user.name"></b><small x-text="user.email"></small></div>
                    </div>
                    <div class="ulk-cur" x-show="user.lock">
                        <div><span>Lý do</span><b x-text="user.lock && user.lock.reason"></b></div>
                        <div><span>Hình thức</span><b x-text="user.lock && user.lock.label"></b></div>
                        <div><span>Khóa lúc</span><b x-text="user.lock && user.lock.since"></b></div>
                        <div><span>Dự kiến mở</span><b x-text="user.lock && (user.lock.until || 'Vĩnh viễn')"></b></div>
                        <div x-show="user.lock && (user.lock.tokens || user.lock.points)"><span>Đã trừ</span><b x-text="user.lock ? ((user.lock.points ? '-' + user.lock.points + ' điểm' + (user.lock.pointsPct ? ' (' + user.lock.pointsPct + '%)' : '') + ' ' : '') + (user.lock.tokens ? '-' + user.lock.tokens + ' token' + (user.lock.tokensPct ? ' (' + user.lock.tokensPct + '%)' : '') : '')) : ''"></b></div>
                    </div>
                    <label class="ulk-choice" :class="unlockMode === 'keep' && 'is-on'">
                        <input type="radio" name="mode" value="keep" x-model="unlockMode">
                        <span><b>Mở khóa sớm</b><small>Giữ nguyên điểm / token đã trừ.</small></span>
                    </label>
                    <label class="ulk-choice" :class="unlockMode === 'refund' && 'is-on'" x-show="user.lock">
                        <input type="radio" name="mode" value="refund" x-model="unlockMode">
                        <span><b>Hủy hình phạt (xử nhầm)</b><small>Hoàn lại token, bỏ trừ điểm, gỡ khỏi danh sách vi phạm.</small></span>
                    </label>
                    <div x-show="unlockMode === 'refund'">
                        <span class="ulk-lbl">Lý do hủy *</span>
                        <input type="text" name="reason" class="ulk-input" x-model="unlockReason" maxlength="500" placeholder="VD: khóa nhầm tài khoản">
                    </div>
                </div>
                <div class="ulk-ft">
                    <button type="button" class="ulk-btn ulk-btn--ghost" @click="close()">Huỷ</button>
                    <button type="submit" class="ulk-btn ulk-btn--ok" :disabled="busy"><x-lucide name="lock-open" /><span x-text="busy ? 'Đang mở…' : 'Mở khóa'"></span></button>
                </div>
            </form>
        </div>
    </template>
</div>
