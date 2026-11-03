{{--
    SỬA 3/11 (khách: "dạng PHỔ THÔNG — mỗi bài nhiều câu trắc nghiệm / điền từ…; lập trình mỗi bài 1 câu giữ nguyên") —
    danh sách "Bài phổ thông" hiện ở Luyện tập > Bài tập khi ô dạng bài chọn "Phổ thông" (partials/practice-catalog).
    Dữ liệu: App\Services\Public\GeneralSetCatalogService. Dùng chung ô tìm kiếm / chuyên đề / độ khó / "Chưa làm" của trang.
    Nút "Làm bài" mở trang chi tiết bài (practice.exam.show) — chỗ bấm "Bắt đầu làm bài" như mọi đề luyện tập. CSS tiền tố gst-.
--}}
@php $gstRows = app(\App\Services\Public\GeneralSetCatalogService::class)->rows(auth()->user()); @endphp
<style>
    .gst-list { overflow: hidden; border: 1px solid #DDEAF0; border-radius: 16px; background: #fff; box-shadow: 0 2px 10px rgba(28, 91, 121, .05); }
    .gst-head, .gst-row { display: grid; grid-template-columns: minmax(0, 1fr) 150px 110px 150px 130px; gap: 14px; align-items: center; padding: 12px 16px; }
    .gst-head { background: #F4F8FB; font-size: 11px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #365B7A; }
    .gst-row + .gst-row { border-top: 1px solid #E7EFF3; }
    .gst-row:nth-child(even) { background: #F9FCFD; }
    .gst-row:hover { background: #F2F9FC; }
    .gst-title { display: flex; align-items: flex-start; gap: 10px; min-width: 0; }
    .gst-ic { display: grid; place-items: center; width: 34px; height: 34px; flex: none; border-radius: 10px; background: #EEF2FF; color: #4F46E5; }
    .gst-ic.is-done { background: #2F8A6B; color: #fff; }
    .gst-ic svg { width: 17px; height: 17px; }
    .gst-title b { display: block; font-size: 15px; font-weight: 800; color: #123B68; line-height: 1.35; }
    .gst-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 5px 8px; margin-top: 5px; font-size: 11.5px; color: #607A90; }
    .gst-chip { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 999px; border: 1px solid #DDE7F3; background: #fff; font-size: 11px; font-weight: 700; color: #365B7A; }
    .gst-chip.is-q { border-color: #C7D2FE; background: #EEF2FF; color: #4338CA; }
    .gst-chip.is-done { border-color: #BBE5D2; background: #EAF7F0; color: #2F8A6B; }
    .gst-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; color: #64748B; }
    .gst-topics { display: flex; flex-direction: column; align-items: flex-start; gap: 4px; }
    .gst-topic { padding: 3px 9px; border-radius: 8px; border: 1px solid #D6E3EF; background: #F7FAFD; font-size: 12px; font-weight: 700; color: #345B78; }
    .gst-stars { display: flex; gap: 1px; color: #CBD5E1; } .gst-stars svg { width: 14px; height: 14px; } .gst-stars .on { color: #F59E0B; }
    .gst-diff small { display: block; margin-top: 2px; font-size: 11.5px; color: #607A90; }
    .gst-stat b { display: block; font-size: 15px; font-weight: 900; color: #123B68; }
    .gst-stat small { display: block; font-size: 11px; color: #607A90; }
    .gst-stat .me { color: #2F8A6B; font-weight: 700; }
    .gst-bar { height: 5px; margin: 4px 0; border-radius: 99px; background: #E2E8F0; overflow: hidden; } .gst-bar i { display: block; height: 100%; background: #2F8A6B; }
    .gst-go { display: flex; align-items: center; justify-content: center; gap: 6px; min-height: 40px; border-radius: 10px; background: #2F8A6B; color: #fff !important; font-size: 13px; font-weight: 800; text-decoration: none; }
    .gst-go:hover { background: #28795E; } .gst-go svg { width: 15px; height: 15px; }
    .gst-more { display: block; width: 100%; padding: 10px; border: 0; border-top: 1px solid #E7EFF3; background: #F4F8FB; font-size: 12px; font-weight: 800; color: #126F91; cursor: pointer; }
    .gst-empty { padding: 40px 16px; border: 1px dashed #C9DFE8; border-radius: 24px; background: #fff; text-align: center; }
    @media (max-width: 1023px) {
        .gst-head { display: none; }
        .gst-row { grid-template-columns: minmax(0, 1fr) auto; }
        .gst-title, .gst-topics { grid-column: 1 / -1; }
        .gst-topics { flex-direction: row; flex-wrap: wrap; }
        .gst-diff, .gst-stat { grid-column: span 1; }
        .gst-go { grid-column: 1 / -1; }
    }
</style>
<script>
    // Số bài đang hiện — để dòng "Hiển thị N bài tập" ở đầu trang đếm đúng khi chọn Phổ thông.
    (function () {
        const reg = () => { try { if (! Alpine.store('oiGeneralSets')) Alpine.store('oiGeneralSets', { count: 0 }); } catch (e) {} };
        if (window.Alpine && typeof Alpine.store === 'function') reg(); else document.addEventListener('alpine:init', reg);
    })();
    window.oiGeneralSetList = window.oiGeneralSetList || function (rows) {
        return {
            gstRows: rows,
            gstShow: 20,
            get gstFiltered() {
                const q = (this.searchQuery || '').trim().toLowerCase();
                const topic = this.selectedTopic, diff = this.selectedDifficulty;
                const list = this.gstRows.filter((r) =>
                    (!q || r.search.includes(q))
                    && (topic === 'all' || topic === undefined || r.tagIds.includes(Number(topic)))
                    && (diff === 'all' || diff === undefined || r.difficulty === diff)
                    && (!this.onlyUndone || r.myAttempts === 0));
                return list;
            },
        };
    };
</script>
<div x-show="activeTab === 'general' && problemScope !== 'assigned'" x-cloak x-data="oiGeneralSetList(@js($gstRows))"
     x-effect="if ($store.oiGeneralSets) $store.oiGeneralSets.count = gstFiltered.length">
    <template x-if="gstFiltered.length > 0">
        <div class="gst-list">
            <div class="gst-head"><span>Tên bài &amp; Mã</span><span>Chuyên đề</span><span>Độ khó</span><span>Kết quả</span><span style="text-align:right">Hành động</span></div>
            <template x-for="r in gstFiltered.slice(0, gstShow)" :key="r.id">
                <div class="gst-row">
                    <div class="gst-title">
                        <span class="gst-ic" :class="r.myAttempts > 0 && 'is-done'"><x-lucide name="list-checks" /></span>
                        <div class="min-w-0">
                            <b x-text="r.title"></b>
                            <div class="gst-meta">
                                <span class="gst-chip is-q"><x-lucide name="file-text" class="h-3 w-3" /><span x-text="r.questionCount + ' câu · ' + r.totalPoints + ' điểm'"></span></span>
                                <span x-show="r.typeSummary" x-text="r.typeSummary"></span>
                                <span x-show="r.myAttempts > 0" class="gst-chip is-done" x-text="'Đã làm ' + r.myAttempts + ' lần'"></span>
                            </div>
                            <div class="gst-meta"><span class="gst-code" x-show="r.code" x-text="'Mã: ' + r.code"></span><span>Phổ thông · Không giới hạn thời gian</span></div>
                        </div>
                    </div>
                    <div class="gst-topics">
                        <template x-for="t in r.topicLabels" :key="t"><span class="gst-topic" x-text="t"></span></template>
                        <span x-show="r.topicLabels.length === 0" class="gst-code">Chưa gắn chuyên đề</span>
                    </div>
                    <div class="gst-diff">
                        <span class="gst-stars"><template x-for="i in 5" :key="i"><span :class="i <= r.difficultyStars && 'on'"><x-lucide name="star" /></span></template></span>
                        <small x-text="r.difficultyLabel"></small>
                    </div>
                    <div class="gst-stat">
                        <b x-text="r.avgPercent === null ? '—' : r.avgPercent + '%'"></b>
                        <div class="gst-bar"><i :style="'width:' + (r.avgPercent || 0) + '%'"></i></div>
                        <small x-text="'Điểm TB · ' + r.attemptCount + ' lượt làm'"></small>
                        <small class="me" x-show="r.myBest" x-text="'Bạn: cao nhất ' + r.myBest"></small>
                    </div>
                    <a :href="r.href" class="gst-go"><x-lucide name="play" /><span x-text="r.myAttempts > 0 ? 'Làm lại' : 'Làm bài'"></span></a>
                </div>
            </template>
            <button type="button" class="gst-more" x-show="gstFiltered.length > gstShow" @click="gstShow += 20"
                    x-text="'Xem thêm (' + (gstFiltered.length - gstShow) + ' bài)'"></button>
        </div>
    </template>
    <div x-show="gstFiltered.length === 0" class="gst-empty">
        <x-lucide name="search" class="mx-auto h-9 w-9 text-[#9DC8D7]" />
        <h2 class="mt-3 text-sm font-black text-[#123B68]" x-text="gstRows.length === 0 ? 'Chưa có bài phổ thông nào' : 'Không có bài phổ thông phù hợp'"></h2>
        <p class="mt-1 text-xs text-[#607A90]" x-text="gstRows.length === 0 ? 'Các bài trắc nghiệm, điền từ… nhiều câu sẽ hiện ở đây khi được phát hành.' : 'Thử đổi chuyên đề, độ khó hoặc từ khoá tìm kiếm.'"></p>
    </div>
</div>
