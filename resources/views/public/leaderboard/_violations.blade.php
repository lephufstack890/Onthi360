{{-- SỬA 12/11 (khách gửi mẫu "Tài khoản vi phạm") — tab thay cho "Cuộc thi gần nhất" + "Lớp của tôi".
     Dữ liệu: LeaderboardService::violationsScope() — tài khoản ĐANG bị khóa do admin xử phạt
     (Quản trị › Bảng xếp hạng › Tài khoản vi phạm). Sắp xếp + phân trang 5 dòng chạy ở trình duyệt. --}}
@php
    $violations = $violations ?? [];
@endphp
<style>
.lbv { background: #fff; border: 1px solid #e3ecf3; border-radius: 18px; overflow: hidden; box-shadow: 0 4px 16px #193c5509; }
.lbv-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding: 20px 22px 14px; }
.lbv-head h2 { display: flex; align-items: center; gap: 9px; font-size: 20px; font-weight: 800; color: #0f3554; }
.lbv-head h2 svg { width: 22px; height: 22px; color: #216c84; }
.lbv-head p { margin-top: 4px; font-size: 12.5px; color: #6b7f8f; }
.lbv-chip { display: inline-flex; align-items: center; gap: 5px; padding: 4px 9px; border-radius: 7px; background: #fff1ec; color: #c2410c; font-size: 11.5px; font-weight: 700; white-space: nowrap; }
.lbv-chip svg { width: 12px; height: 12px; }
.lbv-hint { padding: 9px 22px; border-top: 1px solid #edf2f6; background: #f7fafc; font-size: 12px; color: #7a8d9b; }
.lbv-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.lbv table { width: 100%; min-width: 1180px; border-collapse: collapse; }
.lbv th { padding: 14px 16px; background: #f5f8fb; text-align: left; font-size: 11.5px; font-weight: 800; color: #3f5b70; white-space: nowrap; }
.lbv th button { display: inline-flex; align-items: center; gap: 4px; font: inherit; color: inherit; cursor: pointer; }
.lbv th button svg { width: 12px; height: 12px; opacity: .45; }
.lbv th button[data-on="1"] svg { opacity: 1; color: #126f91; }
.lbv td { padding: 16px; border-top: 1px solid #edf2f6; font-size: 13px; color: #17394f; vertical-align: middle; }
.lbv tbody tr:nth-child(even) td { background: #fafcfd; }
.lbv-acc { display: flex; align-items: center; gap: 12px; min-width: 250px; }
.lbv-av { display: grid; place-items: center; flex: 0 0 40px; width: 40px; height: 40px; border-radius: 50%; background: #eef4f8; color: #58758a; font-size: 13px; font-weight: 800; overflow: hidden; }
.lbv-av img { width: 100%; height: 100%; object-fit: cover; }
.lbv-av svg { width: 19px; height: 19px; }
.lbv-name { font-weight: 800; color: #17394f; }
.lbv-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 5px; }
.lbv-score { display: inline-block; padding: 4px 10px; border-radius: 8px; background: #eef7f4; color: #0f766e; font-weight: 800; }
.lbv-score + small { display: block; margin-top: 4px; font-size: 11px; color: #8aa0ad; }
.lbv-form { display: inline-block; padding: 5px 10px; border-radius: 8px; background: #fff4e0; color: #9a5b00; font-size: 12px; font-weight: 700; white-space: nowrap; }
.lbv-minus { display: inline-block; padding: 4px 9px; border-radius: 8px; background: #fff1ec; color: #c2410c; font-weight: 800; }
.lbv-none { display: inline-block; padding: 4px 9px; border-radius: 8px; background: #f1f5f9; color: #64748b; font-size: 12px; font-weight: 600; white-space: nowrap; }
.lbv-unlock small { display: block; font-size: 11px; color: #8aa0ad; }
.lbv-unlock b { font-weight: 600; white-space: nowrap; }
.lbv-foot { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 22px; border-top: 1px solid #edf2f6; font-size: 12.5px; color: #6b7f8f; }
.lbv-pages { display: flex; gap: 6px; }
.lbv-pages button { display: grid; place-items: center; min-width: 34px; height: 34px; border-radius: 9px; border: 1px solid #e3ecf3; background: #fff; color: #34506a; font-weight: 700; cursor: pointer; }
.lbv-pages button[aria-current="page"] { background: #e7f1f6; border-color: #c8dce5; color: #126f91; }
.lbv-pages button:disabled { opacity: .4; cursor: default; }
.lbv-pages svg { width: 15px; height: 15px; }
.lbv-note { display: flex; align-items: center; gap: 8px; padding: 12px 22px; border-top: 1px solid #edf2f6; background: #f7fafc; font-size: 12px; color: #7a8d9b; }
.lbv-note svg { width: 14px; height: 14px; flex: 0 0 auto; }
.lbv-empty { padding: 56px 20px; text-align: center; }
.lbv-empty span { display: inline-grid; place-items: center; width: 58px; height: 58px; border-radius: 18px; background: #ecfdf5; color: #059669; margin-bottom: 10px; }
.lbv-empty h3 { font-size: 16px; font-weight: 700; color: #17394f; }
.lbv-empty p { margin-top: 6px; font-size: 13px; color: #6b7f8f; }
@media (max-width: 640px) {
  .lbv-head { flex-direction: column; padding: 16px; }
  .lbv-foot { flex-direction: column; align-items: flex-start; padding: 12px 16px; }
  .lbv-hint, .lbv-note { padding-inline: 16px; }
}
</style>

<script>
    window.onthiViolationsBoard = function (rows) {
        const nf = new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 });
        const titles = { Grandmaster: ['HUYỀN THOẠI', 'legend'], Master: ['BẬC THẦY', 'master'], Expert: ['CHUYÊN GIA', 'expert'], Specialist: ['CAO THỦ', 'pro'], Pupil: ['HỌC VIÊN', 'pupil'], Newbie: ['TÂN BINH', 'new'] };
        return {
            rows: rows || [], sortKey: 'lockedTs', sortDir: -1, page: 1, pageSize: 5,
            fmt(v) { return nf.format(Number(v) || 0); },
            titleVi(r) { return (titles[r.badge] || [String(r.badge || '').toUpperCase()])[0]; },
            titleClass(r) { return 'lbx-t-' + ((titles[r.badge] || [0, 'new'])[1]); },
            sortBy(key) { if (this.sortKey === key) { this.sortDir *= -1; } else { this.sortKey = key; this.sortDir = key === 'name' || key === 'reason' ? 1 : -1; } this.page = 1; },
            get sorted() {
                const k = this.sortKey, d = this.sortDir;
                return [...this.rows].sort((a, b) => {
                    const x = a[k] ?? -Infinity, y = b[k] ?? -Infinity;
                    return (typeof x === 'string' ? x.localeCompare(y, 'vi') : (x > y) - (x < y)) * d;
                });
            },
            get totalPages() { return Math.max(1, Math.ceil(this.rows.length / this.pageSize)); },
            get start() { return (Math.min(this.page, this.totalPages) - 1) * this.pageSize; },
            get visible() { return this.sorted.slice(this.start, this.start + this.pageSize); },
            get pages() { return Array.from({ length: this.totalPages }, (_, i) => i + 1); },
            go(n) { this.page = Math.min(Math.max(1, n), this.totalPages); },
        };
    };
</script>

<section class="lbv" aria-labelledby="lbv-title" x-data="onthiViolationsBoard({{ Js::from($violations) }})">
    <div class="lbv-head">
        <div>
            <h2 id="lbv-title"><x-lucide name="shield-check" /> Tài khoản vi phạm</h2>
            <p>{{ count($violations) }} tài khoản đang bị khóa · Thời gian Việt Nam (UTC+7)</p>
        </div>
        <span class="lbv-chip"><x-lucide name="lock" /> Bị khóa</span>
    </div>

    @if (count($violations) > 0)
        <p class="lbv-hint">Vuốt ngang để xem đầy đủ hình phạt và thời điểm mở khóa.</p>
        <div class="lbv-wrap">
            <table>
                <caption class="lb-sr-only">Danh sách tài khoản đang bị khóa khỏi bảng xếp hạng</caption>
                <thead>
                    <tr>
                        @foreach ([['name', 'Tài khoản'], ['scoreAtLock', 'Điểm khi khóa'], ['lockedTs', 'Thời điểm khóa'], ['reason', 'Lý do khóa'], ['lockDays', 'Hình thức khóa'], ['points', 'Trừ điểm'], ['tokens', 'Trừ token'], ['unlockTs', 'Thời điểm mở khóa']] as $col)
                            @php [$key, $label] = $col; @endphp
                            <th scope="col" :aria-sort="sortKey === '{{ $key }}' ? (sortDir > 0 ? 'ascending' : 'descending') : 'none'">
                                <button type="button" @click="sortBy('{{ $key }}')" :data-on="sortKey === '{{ $key }}' ? 1 : 0">{{ $label }} <x-lucide name="arrow-up-down" /></button>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <template x-for="r in visible" :key="r.id">
                        <tr>
                            <td>
                                <div class="lbv-acc">
                                    <span class="lbv-av" aria-hidden="true">
                                        <img x-show="r.avatar" :src="r.avatar" alt="" loading="lazy">
                                        <span x-show="!r.avatar && r.named" x-text="r.initials"></span>
                                        <x-lucide name="user-round" x-show="!r.avatar && !r.named" />
                                    </span>
                                    <div>
                                        <div class="lbv-name" x-text="r.name"></div>
                                        <div class="lbv-tags">
                                            <span class="lbv-chip"><x-lucide name="lock" /> Bị khóa</span>
                                            <span class="lbx-title-chip" :class="titleClass(r)" x-text="titleVi(r)"></span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="lbv-score" x-text="r.scoreAtLock === null ? '—' : fmt(r.scoreAtLock)"></span>
                                <small>Trước mức trừ</small>
                            </td>
                            <td style="white-space:nowrap" x-text="r.lockedAt"></td>
                            <td style="min-width:200px" x-text="r.reason"></td>
                            <td><span class="lbv-form" x-text="r.lockLabel"></span></td>
                            <td>
                                <span class="lbv-minus" x-show="r.points > 0" x-text="'-' + fmt(r.points)"></span>
                                <span class="lbv-none" x-show="!(r.points > 0)">Không trừ</span>
                            </td>
                            <td>
                                <span class="lbv-minus" x-show="r.tokens > 0" x-text="'-' + fmt(r.tokens)"></span>
                                <span class="lbv-none" x-show="!(r.tokens > 0)">Không trừ</span>
                            </td>
                            <td class="lbv-unlock">
                                <small x-text="r.unlockAt ? 'Dự kiến mở khóa' : 'Khóa vĩnh viễn'"></small>
                                <b x-text="r.unlockAt || '—'"></b>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div class="lbv-foot">
            <span>Hiển thị <b x-text="(start + 1) + '–' + Math.min(start + pageSize, rows.length)"></b> / <span x-text="rows.length"></span> tài khoản · 5 dòng/trang</span>
            <nav class="lbv-pages" aria-label="Phân trang tài khoản vi phạm" x-show="totalPages > 1">
                <button type="button" aria-label="Trang trước" :disabled="page <= 1" @click="go(page - 1)"><x-lucide name="chevron-left" /></button>
                <template x-for="n in pages" :key="n">
                    <button type="button" :aria-current="page === n ? 'page' : null" @click="go(n)" x-text="n"></button>
                </template>
                <button type="button" aria-label="Trang sau" :disabled="page >= totalPages" @click="go(page + 1)"><x-lucide name="chevron-right" /></button>
            </nav>
        </div>
    @else
        <div class="lbv-empty">
            <span><x-lucide name="shield-check" style="width:27px;height:27px" /></span>
            <h3>Không có tài khoản nào đang bị khóa</h3>
            <p>Mọi tài khoản đều đang thi đấu đúng quy chế.</p>
        </div>
    @endif

    <div class="lbv-note">
        <x-lucide name="info" />
        <span>Tài khoản vi phạm quy chế bị tạm gỡ khỏi bảng xếp hạng trong thời gian khóa. "Điểm khi khóa" là điểm ghi nhận trước khi áp mức trừ; hết hạn khóa tài khoản tự trở lại bảng xếp hạng.</span>
    </div>
</section>
