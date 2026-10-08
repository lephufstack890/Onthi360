{{-- ═══════════ KHỐI "LUYỆN TẬP HÔM NAY" Ở ĐẦU TRANG LUYỆN TẬP ═══════════
     SỬA 8/10 (khách: "trang luyện tập public thêm UI mục khoanh đỏ — UI trong source mới; chỗ đó thiếu logic thì
     làm; dữ liệu lấy từ nhật ký khi học sinh làm bài để tổng hợp") — dựng theo education-main:
       · components/PracticeStatsChart.jsx  -> vòng tròn + chú giải + "Chi tiết"
       · components/PracticeDailyStats.jsx  -> hộp thoại "Hoạt động hôm nay"
       · utils/practiceStats.js             -> cách tính (xem App\Services\Public\PracticeDailyStatsService)

     Số liệu CHỈ của người đang đăng nhập, tính ở máy chủ từ lượt nộp + nhật ký làm bài
     (attempt_answers.activity_log); trang chỉ gom nhóm theo ô lọc Tất cả / Bài tập / Đề thi.
     Máy chủ không chạy được vite nên toàn bộ CSS viết riêng (tiền tố .pds-) thay vì class Tailwind mới.

     Biến vào: $dailyStats (từ PracticeService::indexData), $practiceTypes, $practiceTotal. --}}
@php
    $dailyStats = $dailyStats ?? ['signedIn' => false, 'date' => now()->format('d/m'), 'rows' => []];
    $practiceTypes = $practiceTypes ?? [];
    $practiceTotal = $practiceTotal ?? 0;
@endphp

<style>
    .pds-overview{position:relative;z-index:10;display:grid;grid-template-columns:minmax(0,1fr);width:100%;min-width:0;overflow:hidden;border:1px solid rgba(255,255,255,.25);border-radius:16px;background:rgba(255,255,255,.06);color:#fff;box-shadow:0 4px 12px rgba(9,46,76,.06);backdrop-filter:blur(2px)}
    .pds-overview>*+*{border-top:1px solid rgba(255,255,255,.2)}
    .pds-cell{display:flex;flex-direction:column;min-width:0;min-height:0;padding:14px}
    .pds-title{display:flex;align-items:center;justify-content:space-between;gap:8px;min-height:28px;margin:0;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#fff}
    @media (min-width:640px){
        .pds-overview{grid-template-columns:minmax(0,1fr) minmax(0,1fr);grid-auto-rows:236px}
        .pds-overview>*+*{border-top:0;border-left:1px solid rgba(255,255,255,.2)}
        .pds-cell{overflow-y:auto}
    }
    @media (min-width:1024px){.pds-overview{flex:none;width:31rem}}
    @media (min-width:1280px){.pds-overview{width:36rem}}

    /* Kho câu theo dạng */
    .pds-types{flex:1;margin-top:8px;font-size:11px;line-height:16px}
    .pds-types>div+div{margin-top:10px}
    .pds-types__row{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:4px}
    .pds-types__n{font-size:14px;font-weight:700;font-variant-numeric:tabular-nums;color:#fcd34d}
    .pds-types__n small{font-size:11px;font-weight:500;color:#e0f2fe}
    .pds-bar{height:6px;border-radius:999px;background:rgba(255,255,255,.2);overflow:hidden}
    .pds-bar>i{display:block;height:100%;border-radius:999px;background:#fbbf24}
    .pds-foot{display:flex;align-items:center;justify-content:space-between;gap:8px;min-height:28px;margin:8px 0 0;padding-top:4px;border-top:1px solid rgba(255,255,255,.15);font-size:11px;line-height:16px;color:#e0f2fe}
    .pds-foot strong{font-size:14px;font-weight:700;color:#fff;font-variant-numeric:tabular-nums}

    /* Luyện tập hôm nay */
    .pds-select{min-height:28px;max-width:84px;padding:0 4px;border:0;border-radius:6px;background:rgba(255,255,255,.15);color:#fff;font:inherit;font-size:11px;font-weight:600;text-transform:none;letter-spacing:0;cursor:pointer}
    .pds-select option{background:#fff;color:#123b68}
    .pds-select:focus-visible{outline:2px solid #fff;outline-offset:2px}
    .pds-ring-row{display:flex;align-items:center;gap:10px;margin-top:8px}
    .pds-ring{position:relative;flex:none;width:80px;height:80px}
    .pds-ring svg{display:block;width:100%;height:100%;transform:rotate(-90deg)}
    .pds-ring__c{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center}
    .pds-ring__c strong{font-size:24px;font-weight:800;line-height:1;font-variant-numeric:tabular-nums}
    .pds-ring__c span{margin-top:2px;font-size:10px;line-height:12px;color:#e0f2fe}
    .pds-legend{flex:1;min-width:0;margin:0;font-size:11px;line-height:16px}
    .pds-legend>div{display:flex;align-items:center;gap:6px}
    .pds-legend>div+div{margin-top:4px}
    .pds-legend dt{display:flex;flex:1;align-items:center;gap:6px;min-width:0;margin:0}
    .pds-legend dt i{flex:none;width:6px;height:6px;border-radius:999px}
    .pds-legend dd{margin:0;font-size:14px;font-weight:700;font-variant-numeric:tabular-nums}
    .pds-kpi{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:8px 0 0;padding-top:8px;border-top:1px solid rgba(255,255,255,.15)}
    .pds-kpi>div{display:flex;align-items:center;gap:6px;font-size:11px;line-height:16px}
    .pds-kpi dt{display:flex;flex:1;align-items:center;gap:6px;margin:0}
    .pds-kpi dt svg{flex:none;width:14px;height:14px;color:#a7f3d0}
    .pds-kpi dd{margin:0;font-size:14px;font-weight:700;font-variant-numeric:tabular-nums;color:#fcd34d}
    .pds-meta{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:4px;font-size:10px;line-height:16px;color:#e0f2fe}
    .pds-link{display:inline-flex;align-items:center;gap:2px;min-height:24px;padding:0;border:0;background:none;color:#fff;font:inherit;font-weight:600;cursor:pointer}
    .pds-link:hover{text-decoration:underline}
    .pds-link svg{width:12px;height:12px}
    .pds-note{margin:4px 0 0;font-size:10px;line-height:14px;color:#e0f2fe}
    .pds-note a{color:#fff;font-weight:700;text-decoration:underline}

    /* Hộp thoại chi tiết */
    .pds-dialog{width:calc(100% - 24px);max-width:48rem;height:min(600px,90vh);max-height:90vh;margin:auto;padding:0;overflow:hidden;border:0;border-radius:16px;background:#fff;color:#123b68;box-shadow:0 25px 50px -12px rgba(0,0,0,.35)}
    .pds-dialog[open]{display:flex;flex-direction:column}
    .pds-dialog::backdrop{background:rgba(2,6,23,.5)}
    @media (min-width:640px){.pds-dialog{height:min(460px,90vh)}}
    .pds-d-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex:none;padding:12px 16px;border-bottom:1px solid #e7eff3}
    .pds-d-head h2{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin:0;font-size:14px;font-weight:800;color:#123b68}
    .pds-d-head h2 svg{width:16px;height:16px;color:#126f91}
    .pds-d-date{padding:4px 8px;border-radius:6px;background:#f0f5f8;font-size:10px;font-weight:600;color:#607a90}
    .pds-d-head p{margin:4px 0 0;font-size:11px;color:#607a90}
    .pds-d-x{display:grid;place-items:center;flex:none;width:32px;height:32px;border:0;border-radius:8px;background:none;color:#607a90;cursor:pointer}
    .pds-d-x:hover{background:#f0f5f8}
    .pds-d-x svg{width:16px;height:16px}
    .pds-seg{display:flex;gap:4px;margin:12px 16px 0;padding:4px;border-radius:8px;background:#f3f7fa;flex:none}
    .pds-seg button{min-height:32px;padding:0 12px;border:0;border-radius:6px;background:none;color:#607a90;font:inherit;font-size:11px;font-weight:700;cursor:pointer}
    .pds-seg button.is-on{background:#fff;color:#126f91;box-shadow:0 1px 2px rgba(0,0,0,.08)}
    .pds-m{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;flex:none;margin:12px 16px 0}
    .pds-m>div{padding:8px 12px;border-radius:8px}
    .pds-m>div:first-child{grid-column:1/-1;display:flex;align-items:center;justify-content:space-between}
    .pds-m dt{margin:0;font-size:11px;font-weight:600;line-height:16px}
    .pds-m dd{margin:0;font-size:20px;font-weight:800;line-height:28px;font-variant-numeric:tabular-nums}
    @media (min-width:640px){.pds-m{grid-template-columns:repeat(5,minmax(0,1fr))}.pds-m>div:first-child{grid-column:auto;display:block}}
    .pds-t-blue{background:#edf5fd;color:#2467a5}.pds-t-green{background:#eff9f3;color:#287c51}.pds-t-teal{background:#eaf8f7;color:#157d7a}.pds-t-amber{background:#fff7e7;color:#976417}.pds-t-rose{background:#fff0f1;color:#b34659}
    .pds-d-sum{flex:none;margin:0;padding:8px 16px;font-size:11px;line-height:16px;color:#607a90}
    .pds-tabs{display:flex;gap:16px;flex:none;padding:0 16px;border-bottom:1px solid #e7eff3}
    .pds-tabs button{min-height:36px;border:0;border-bottom:2px solid transparent;background:none;color:#607a90;font:inherit;font-size:12px;font-weight:600;cursor:pointer}
    .pds-tabs button.is-on{border-bottom-color:#126f91;color:#126f91}
    .pds-d-body{flex:1;min-height:0;overflow-y:auto;overscroll-behavior:contain;padding:12px 16px;font-size:11px;line-height:20px;color:#607a90}
    .pds-d-body dl{margin:0}.pds-d-body dl>div+div{margin-top:12px}
    .pds-d-body dt{font-weight:700;color:#123b68}.pds-d-body dd{margin:0}
    .pds-list{margin:0;padding:0;list-style:none}
    .pds-list li{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:8px 0;border-top:1px solid #edf2f5}
    .pds-list li:first-child{padding-top:0;border-top:0}
    .pds-list p{margin:0;font-size:12px;font-weight:600;line-height:20px;color:#123b68}
    .pds-list__m{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-top:4px;font-size:10px}
    .pds-list__m b{padding:2px 6px;border-radius:4px;font-weight:600}
    .pds-list a{display:inline-flex;align-items:center;flex:none;gap:4px;min-height:32px;font-weight:600;color:#126f91;text-decoration:none}
    .pds-list a:hover{text-decoration:underline}
    .pds-list a svg{width:12px;height:12px}
    .pds-ok{display:flex;align-items:flex-start;gap:12px;padding:16px;border-radius:12px;background:#f5f9fb}
    .pds-ok svg{flex:none;width:20px;height:20px;margin-top:2px;color:#2f8a6b}
    .pds-ok p{margin:0}.pds-ok p:first-child{font-size:12px;font-weight:600;color:#123b68}.pds-ok p+p{margin-top:4px}
    .pds-d-foot{flex:none;padding:8px 16px;border-top:1px solid #e7eff3;background:#f8fbfc;font-size:10px;color:#607a90}
</style>

<div class="pds-overview" data-practice-overview>
    {{-- Ô 1 — Kho câu theo dạng (nội dung cũ, đặt vào khung 2 ô như bản mẫu). --}}
    <section class="pds-cell" aria-label="Kho câu theo dạng">
        <h2 class="pds-title">Kho câu theo dạng</h2>
        <div class="pds-types">
            @foreach (array_slice($practiceTypes, 0, 3) as $t)
                @php $pct = $practiceTotal > 0 ? round($t['count'] / $practiceTotal * 100) : 0; @endphp
                <div>
                    <div class="pds-types__row">
                        <span>{{ $t['label'] }}</span>
                        <span class="pds-types__n">{{ number_format($t['count']) }} <small>câu</small></span>
                    </div>
                    <div class="pds-bar" aria-hidden="true"><i style="width: {{ $pct }}%"></i></div>
                </div>
            @endforeach
        </div>
        <p class="pds-foot"><span>Tổng số câu trong kho</span><strong>{{ number_format($practiceTotal) }}</strong></p>
    </section>

    {{-- Ô 2 — Luyện tập hôm nay. --}}
    <section class="pds-cell" aria-labelledby="pds-title"
             x-data="oiDailyStats({{ Js::from($dailyStats) }})" x-effect="syncMode(practiceMode)">
        <div class="pds-title">
            <h2 id="pds-title" class="pds-title" style="min-height:0">Luyện tập hôm nay</h2>
            <select aria-label="Phạm vi biểu đồ" class="pds-select" x-model="filter">
                <option value="all">Tất cả</option>
                <option value="problem">Bài tập</option>
                <option value="exam">Đề thi</option>
            </select>
        </div>

        <div class="pds-ring-row">
            <div class="pds-ring" role="img" :aria-label="ringLabel">
                <svg viewBox="0 0 120 120" aria-hidden="true">
                    <circle cx="60" cy="60" r="50" fill="none" stroke="rgba(255,255,255,.16)" stroke-width="11" />
                    <circle cx="60" cy="60" r="50" pathLength="100" fill="none" stroke="#6EE7B7" stroke-width="11" :stroke-dasharray="dash(0)" :stroke-dashoffset="off(0)" />
                    <circle cx="60" cy="60" r="50" pathLength="100" fill="none" stroke="#FCD34D" stroke-width="11" :stroke-dasharray="dash(1)" :stroke-dashoffset="off(1)" />
                    <circle cx="60" cy="60" r="50" pathLength="100" fill="none" stroke="#FDA4AF" stroke-width="11" :stroke-dasharray="dash(2)" :stroke-dashoffset="off(2)" />
                    <circle cx="60" cy="60" r="50" pathLength="100" fill="none" stroke="#CBD5E1" stroke-width="11" :stroke-dasharray="dash(3)" :stroke-dashoffset="off(3)" />
                </svg>
                <div class="pds-ring__c" aria-hidden="true"><strong x-text="fmt(cur.total)">0</strong><span>lượt nộp</span></div>
            </div>
            <dl class="pds-legend">
                <template x-for="s in slices" :key="s.key">
                    <div :data-chart-stat="s.key">
                        <dt><i :style="'background-color:' + s.color"></i><span x-text="s.label"></span></dt>
                        <dd :style="'color:' + s.color" x-text="fmt(s.value)">0</dd>
                    </div>
                </template>
            </dl>
        </div>

        <dl class="pds-kpi">
            <template x-for="m in metrics" :key="m.key">
                <div :data-chart-stat="m.key">
                    <dt>
                        <span x-show="m.icon === 'check'"><x-lucide name="check-circle" /></span>
                        <span x-show="m.icon === 'file'"><x-lucide name="file-check" /></span>
                        <span x-text="m.label"></span>
                    </dt>
                    <dd x-text="fmt(m.value)">0</dd>
                </div>
            </template>
        </dl>

        <p class="pds-note" x-show="!signedIn" x-cloak><a href="{{ route('login') }}">Đăng nhập</a> để xem kết quả luyện tập của bạn hôm nay.</p>

        <div class="pds-meta">
            <time x-text="date + ' · Giờ Việt Nam'">{{ $dailyStats['date'] }} · Giờ Việt Nam</time>
            <button type="button" class="pds-link" @click="openDetail()">Chi tiết<x-lucide name="chevron-right" /></button>
        </div>

        {{-- Hộp thoại chi tiết (PracticeDailyStats.jsx). --}}
        <dialog x-ref="dlg" class="pds-dialog" aria-label="Chi tiết thống kê luyện tập" @click="if ($event.target === $refs.dlg) $refs.dlg.close()">
            <header class="pds-d-head">
                <div>
                    <h2><x-lucide name="calendar-days" />Hoạt động hôm nay<span class="pds-d-date" x-text="date"></span></h2>
                    <p>Kết quả của bạn · Tính theo giờ Việt Nam</p>
                </div>
                <button type="button" class="pds-d-x" @click="$refs.dlg.close()" aria-label="Đóng chi tiết thống kê"><x-lucide name="x" /></button>
            </header>

            <div class="pds-seg" role="group" aria-label="Phạm vi thống kê hôm nay">
                <button type="button" :class="dfilter === 'all' && 'is-on'" @click="dfilter = 'all'">Tất cả</button>
                <button type="button" :class="dfilter === 'problem' && 'is-on'" @click="dfilter = 'problem'">Bài tập</button>
                <button type="button" :class="dfilter === 'exam' && 'is-on'" @click="dfilter = 'exam'">Đề thi</button>
            </div>

            <dl class="pds-m" aria-live="polite">
                <template x-for="k in detailMetrics" :key="k.key">
                    <div :class="'pds-t-' + k.tone" :title="k.hint" :data-stat="k.key">
                        <dt x-text="k.label"></dt>
                        <dd x-text="fmt(k.value)">0</dd>
                    </div>
                </template>
            </dl>

            <p class="pds-d-sum" x-text="summaryLine"></p>

            <div class="pds-tabs" role="group" aria-label="Nội dung chi tiết">
                <button type="button" :class="tab === 'review' && 'is-on'" @click="tab = 'review'" x-text="'Lượt cần xem lại (' + flagged.length + ')'"></button>
                <button type="button" :class="tab === 'rules' && 'is-on'" @click="tab = 'rules'">Cách tính</button>
            </div>

            <div class="pds-d-body">
                <dl x-show="tab === 'rules'" x-cloak>
                    <div><dt>Lượt nộp và kết quả đúng</dt><dd>Mỗi bài tập hoặc đề thi luyện tập bạn nộp trong ngày tính 1 lượt; nộp lại cùng một bài trong cùng phiên thì lấy lần nộp mới nhất. Bài đúng là đúng toàn bộ (AC); đề đúng là đạt trọn điểm toàn đề. Lượt chờ chấm không tính vào lượt đúng.</dd></div>
                    <div><dt style="color:#976417">Bất thường / Nghi vấn cao</dt><dd>Bất thường: chuyển tab/cửa sổ (dưới 3 lần), mở Hướng dẫn hoặc Bài mẫu khi đang làm. Nghi vấn cao: nhận phím hoặc yêu cầu chụp/quay màn hình, hoặc rời tab/cửa sổ từ 3 lần trong một lượt nộp. Hai mức không đếm trùng; lượt đúng vẫn tính theo kết quả chấm.</dd></div>
                    <div><dt>Chưa có nhật ký</dt><dd>Lượt nộp không kèm nhật ký làm bài (làm trước khi hệ thống ghi nhật ký, hoặc đề thi — đề thi chưa ghi nhật ký) nên chưa đánh giá được dấu hiệu.</dd></div>
                    <div><dt>Phạm vi ghi nhận</dt><dd>Dữ liệu tổng hợp từ nhật ký bạn làm bài trên hệ thống, tính theo ngày hôm nay (giờ Việt Nam). Đây là tín hiệu cần đối chiếu, chưa xác nhận gian lận; trình duyệt không phát hiện được mọi cách chụp màn hình.</dd></div>
                </dl>

                <div x-show="tab === 'review'">
                    <ul class="pds-list" x-show="flagged.length > 0">
                        <template x-for="r in flagged" :key="r.type + ':' + r.id">
                            <li>
                                <div style="min-width:0">
                                    <p x-text="r.title"></p>
                                    <div class="pds-list__m">
                                        <b :class="r.risk === 'high' ? 'pds-t-rose' : 'pds-t-amber'" x-text="r.risk === 'high' ? 'Nghi vấn cao' : 'Bất thường'"></b>
                                        <span><span x-text="r.type === 'exam' ? 'Đề thi' : 'Bài tập'"></span> · <time :datetime="r.at" x-text="r.time"></time></span>
                                    </div>
                                </div>
                                <a :href="r.url">Xem nhật ký<x-lucide name="chevron-right" /></a>
                            </li>
                        </template>
                    </ul>
                    <div class="pds-ok" x-show="flagged.length === 0">
                        <x-lucide name="check-circle" />
                        <div>
                            <p x-text="!signedIn ? 'Đăng nhập để xem thống kê' : (stats(dfilter).total ? 'Chưa ghi nhận lượt cần xem lại' : 'Chưa có lượt nộp hôm nay')"></p>
                            <p x-text="!signedIn ? 'Thống kê luyện tập được tổng hợp theo tài khoản của bạn.' : (stats(dfilter).total ? 'Các lượt có tín hiệu bất thường sẽ xuất hiện tại đây để bạn đối chiếu nhật ký.' : 'Nộp bài tập hoặc đề thi để cập nhật kết quả tại đây.')"></p>
                        </div>
                    </div>
                </div>
            </div>

            <footer class="pds-d-foot">Tổng hợp từ nhật ký làm bài của bạn · Tín hiệu cần đối chiếu, chưa xác nhận gian lận.</footer>
        </dialog>
    </section>
</div>

<script>
    /*
     * Gom nhóm số liệu hôm nay theo ô lọc — port của summarizePracticeDay() (practiceStats.js). Mọi dòng đã được
     * máy chủ tính sẵn kết quả (ac/partial/wa/pending) và mức dấu hiệu (normal/unusual/high/unknown).
     */
    window.oiDailyStats = function (cfg) {
        const SLICES = [
            { key: 'normal', label: 'Chưa có dấu hiệu', color: '#6EE7B7' },
            { key: 'unusual', label: 'Bất thường', color: '#FCD34D' },
            { key: 'high', label: 'Nghi vấn cao', color: '#FDA4AF' },
            { key: 'unknown', label: 'Chưa có nhật ký', color: '#CBD5E1' },
        ];

        return {
            rows: Array.isArray(cfg.rows) ? cfg.rows : [],
            signedIn: !!cfg.signedIn,
            date: cfg.date || '',
            filter: 'problem',
            dfilter: 'all',
            tab: 'review',

            // Theo kiểu luyện đang mở (Bài tập / Đề thi) như bản mẫu; người dùng vẫn đổi được ở ô chọn.
            syncMode(mode) { this.filter = mode === 'exams' ? 'exam' : 'problem'; },

            fmt(n) { return Number(n || 0).toLocaleString('vi-VN'); },

            stats(f) {
                const list = this.rows.filter((r) => f === 'all' || r.type === f);
                const s = { total: list.length, problemCorrect: 0, examCorrect: 0, unusual: 0, high: 0, unknown: 0, pending: 0, rows: list };
                list.forEach((r) => {
                    if (r.pending) s.pending++;
                    else if (r.result === 'ac') s[r.type === 'exam' ? 'examCorrect' : 'problemCorrect']++;
                    if (r.risk === 'unusual' || r.risk === 'high' || r.risk === 'unknown') s[r.risk]++;
                });
                s.normal = s.total - s.unusual - s.high - s.unknown;
                s.unique = new Set(list.map((r) => r.type + ':' + r.subjectId)).size;
                return s;
            },

            get cur() { return this.stats(this.filter); },

            get slices() {
                const s = this.cur;
                return SLICES.map((x) => ({ ...x, value: s[x.key] }));
            },
            // Mỗi cung = phần trăm trên tổng; dựng offset cộng dồn như bản mẫu.
            seg(i) {
                const s = this.cur;
                let offset = 0;
                for (let k = 0; k < i; k++) offset += s.total ? s[SLICES[k].key] / s.total * 100 : 0;
                return { len: s.total ? s[SLICES[i].key] / s.total * 100 : 0, offset };
            },
            dash(i) { const l = this.seg(i).len; return l + ' ' + (100 - l); },
            off(i) { return -this.seg(i).offset; },
            get ringLabel() {
                return this.cur.total + ' lượt nộp hôm nay. ' + this.slices.map((x) => x.label + ': ' + x.value).join('. ');
            },

            get metrics() {
                const s = this.cur;
                if (this.filter === 'exam') return [
                    { key: 'examCorrect', label: 'Lượt đề đúng', icon: 'file', value: s.examCorrect },
                    { key: 'unique', label: 'Đề đã làm', icon: 'check', value: s.unique },
                ];
                if (this.filter === 'problem') return [
                    { key: 'problemCorrect', label: 'Lượt bài đúng', icon: 'check', value: s.problemCorrect },
                    { key: 'unique', label: 'Bài đã làm', icon: 'file', value: s.unique },
                ];
                return [
                    { key: 'problemCorrect', label: 'Lượt bài đúng', icon: 'check', value: s.problemCorrect },
                    { key: 'examCorrect', label: 'Lượt đề đúng', icon: 'file', value: s.examCorrect },
                ];
            },

            openDetail() {
                this.dfilter = this.filter;
                this.tab = 'review';
                this.$refs.dlg.showModal();
            },

            get detailMetrics() {
                const s = this.stats(this.dfilter);
                return [
                    { key: 'total', label: 'Lượt đã nộp', hint: 'Mỗi lần nộp tính 1 lượt', tone: 'blue', value: s.total },
                    { key: 'problemCorrect', label: 'Bài đúng', hint: 'Đúng toàn bộ · AC', tone: 'green', value: s.problemCorrect },
                    { key: 'examCorrect', label: 'Đề đúng', hint: 'Đạt trọn điểm toàn đề', tone: 'teal', value: s.examCorrect },
                    { key: 'unusual', label: 'Bất thường', hint: 'Có tín hiệu cần xem lại', tone: 'amber', value: s.unusual },
                    { key: 'high', label: 'Nghi vấn cao', hint: 'Cần đối chiếu nhật ký', tone: 'rose', value: s.high },
                ];
            },
            get flagged() {
                return this.stats(this.dfilter).rows.filter((r) => r.risk === 'unusual' || r.risk === 'high');
            },
            get summaryLine() {
                const s = this.stats(this.dfilter);
                if (!s.total) return this.signedIn ? 'Chưa có lượt nộp nào hôm nay.' : 'Đăng nhập để xem lượt nộp của bạn hôm nay.';
                const p = s.rows.filter((r) => r.type === 'problem').length;
                const e = s.rows.filter((r) => r.type === 'exam').length;
                return p + ' lượt bài tập · ' + e + ' lượt đề thi' + (s.pending ? ' · ' + s.pending + ' chờ chấm' : '') + (s.unknown ? ' · ' + s.unknown + ' chưa có nhật ký' : '');
            },
        };
    };
</script>
