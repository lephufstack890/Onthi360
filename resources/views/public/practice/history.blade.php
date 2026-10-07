@extends('layouts.guest')

@section('title', 'Nhật ký nộp bài · '.$subject['title'])
@section('meta-description', 'Nhật ký nộp bài của '.$subject['title'].' trên Ôn Thi 360.')

@section('content')
@include('partials.practice-history-style')
{{-- ═══════════════ NHẬT KÝ NỘP BÀI ═══════════════
     SỬA 7/10 (khách: "giáo viên xem nhật ký nộp bài của học sinh — giao diện nhật ký trong source
     mới check và dựng cho tôi") — dựng theo education-main/src/components/SubmissionHistoryPage.jsx:
     thanh đầu, dải tổng quan 4 ô, bộ lọc (tìm kiếm / từ ngày / đến ngày / kết quả / chỉ bài của tôi),
     bảng lượt nộp, bấm "Xem bài" mở chi tiết, phân trang 10/20/50.

     Dữ liệu do Public\PracticeHistoryService cấp (lượt nộp THẬT, đã lọc theo quyền xem của người
     đang đăng nhập). Lọc/sắp xếp/phân trang chạy ngay trên trình duyệt (tối đa 500 lượt mới nhất).

     BÀI MẪU (SỬA 7/10): ADMIN thấy cột "Bài mẫu" với nút "Chỉ định bài mẫu" ở từng lượt nộp của BÀI TẬP;
     lượt được chọn thành bài mẫu của bài và hiện ở tab "Bài mẫu" của màn làm bài (xem
     Public\PracticeSampleService). Giáo viên/học sinh không có nút, chỉ thấy nhãn "Bài mẫu".

     KHÁC BẢN MẪU: không có cột "Hoạt động" (rời tab, phím chụp màn hình…) và
     nguồn "Minh họa" — các sự kiện hoạt động hiện chỉ được ghi trong trình duyệt người làm bài, chưa
     gửi về máy chủ nên chưa có dữ liệu để hiển thị. Ô thứ 4 của dải tổng quan vì vậy là "Đang chờ
     chấm" thay cho "Lượt cần xem xét". --}}
@php
    $isExam = $type === 'exam';
    $roleLabel = match ($role) {
        'admin' => 'Quản trị viên',
        'teacher' => 'Giáo viên',
        default => 'Xem nhật ký của bạn',
    };
    // SỬA 7/10 — bài mẫu: chỉ BÀI TẬP có, và chỉ ADMIN mới thấy nút chỉ định (máy chủ cũng kiểm lại quyền).
    $canDesignate = ! $isExam && ($canDesignate ?? false);
    $sampleId = $sampleId ?? null;
    $historyPayload = [
        'type' => $type,
        'rows' => $rows,
        'canSeeOthers' => $canSeeOthers,
        'canDesignate' => $canDesignate,
        'sampleId' => $isExam ? null : $sampleId,
        'sampleUrl' => $sampleUrl ?? '',
        'sampleClearUrl' => $sampleClearUrl ?? '',
        'csrf' => csrf_token(),
    ];
    $resultFilters = [
        ['id' => 'all', 'label' => 'Tất cả', 'icon' => 'clipboard-list'],
        ['id' => 'ac', 'label' => 'Đúng · AC', 'icon' => 'check-circle-2'],
        ['id' => 'partial', 'label' => 'Đúng một phần', 'icon' => 'trending-up'],
        ['id' => 'wa', 'label' => 'Sai · WA', 'icon' => 'circle-x'],
        ['id' => 'pending', 'label' => 'Chờ chấm', 'icon' => 'clock'],
    ];
@endphp

<div class="submission-history" x-data="onthiHistoryPage({{ Js::from($historyPayload) }})">
    <header class="submission-topbar">
        <div class="submission-topbar-inner">
            <a class="submission-button submission-back" href="{{ $subject['backHref'] }}">
                <x-lucide name="arrow-left" class="h-4 w-4" /><span>Luyện tập</span>
            </a>
            <div class="submission-heading">
                <p>NHẬT KÝ NỘP BÀI <span> / {{ $isExam ? 'LUYỆN THEO ĐỀ' : 'LUYỆN THEO BÀI' }}</span></p>
                <h1>{{ $subject['title'] }}</h1>
            </div>
            <span class="submission-role"><x-lucide name="shield-check" class="h-4 w-4" />{{ $roleLabel }}</span>
        </div>
    </header>

    <main class="submission-main">
        <div class="submission-context">
            <span><x-lucide name="clipboard-list" class="h-4 w-4" />{{ $subject['code'] }}</span>
        </div>

        <section class="submission-overview" aria-label="Tổng quan lượt nộp">
            <img class="submission-overview-image" src="{{ asset('assets/hero-practice.jpg') }}" alt="" decoding="async">
            <div class="submission-overview-content">
                <div class="submission-overview-heading">
                    <h2>Tổng quan lượt nộp</h2>
                    <p>Theo dõi kết quả và những lượt cần xem lại.</p>
                </div>
                <div class="submission-stats">
                    <div><span class="stat-icon"><x-lucide name="clipboard-list" class="h-4 w-4" /></span><div><strong x-text="rows.length"></strong><p>Lượt nộp</p></div></div>
                    <div><span class="stat-icon"><x-lucide name="users" class="h-4 w-4" /></span><div><strong x-text="submitterCount"></strong><p>Người nộp</p></div></div>
                    <div><span class="stat-icon"><x-lucide name="award" class="h-4 w-4" /></span><div><strong x-text="bestLabel"></strong><p>Điểm cao nhất</p></div></div>
                    <div><span class="stat-icon"><x-lucide name="clock" class="h-4 w-4" /></span><div><strong x-text="resultCounts.pending"></strong><p>Đang chờ chấm</p></div></div>
                </div>
            </div>
        </section>

        <section class="submission-filter-panel" aria-label="Bộ lọc nhật ký">
            <div class="submission-filter-topline">
                <h2>Nhật ký nộp bài</h2>
                <button type="button" role="switch" :aria-checked="onlyMine" aria-label="Chỉ bài của tôi" class="submission-mine-toggle"
                        x-show="canSeeOthers" x-cloak @click="onlyMine = !onlyMine" title="Chỉ hiện lượt nộp của chính bạn">
                    <x-lucide name="user-round" class="h-4 w-4" /><span>Chỉ bài của tôi</span>
                    <span class="submission-switch-track" aria-hidden="true"><span></span></span>
                </button>
            </div>
            <div class="submission-filters">
                <label class="submission-search">
                    <x-lucide name="search" class="h-4 w-4" />
                    <input aria-label="Tìm người nộp hoặc mã lượt nộp" placeholder="Tìm học sinh, tài khoản, mã lượt nộp…" x-model="query">
                </label>
                <label class="date-filter">Từ<input aria-label="Từ ngày" type="date" x-model="from" :max="to || null"></label>
                <label class="date-filter">Đến<input aria-label="Đến ngày" type="date" x-model="to" :min="from || null"></label>
            </div>
            <div class="submission-filter-bottomline">
                <div class="submission-result-filters" role="group" aria-label="Lọc kết quả làm bài">
                    @foreach ($resultFilters as $f)
                        <button type="button" class="result-{{ $f['id'] }}" :aria-pressed="resultFilter === '{{ $f['id'] }}'" @click="resultFilter = '{{ $f['id'] }}'">
                            <x-lucide name="{{ $f['icon'] }}" class="h-3.5 w-3.5" />{{ $f['label'] }}<b x-text="resultCounts['{{ $f['id'] }}']"></b>
                        </button>
                    @endforeach
                </div>
                <button type="button" class="submission-reset" x-show="hasFilters" x-cloak @click="resetFilters()">
                    <x-lucide name="x" class="h-3.5 w-3.5" />Xóa bộ lọc
                </button>
            </div>
        </section>

        <p class="submission-notice" :class="noticeError ? 'is-error' : ''" role="status" x-show="notice" x-cloak x-text="notice"></p>

        <div class="submission-filter-summary" role="status">
            <span>Hiển thị <strong x-text="filtered.length"></strong> / <span x-text="rows.length"></span> lượt nộp</span>
            @if ($truncated)
                <span>Chỉ hiện 500 lượt nộp mới nhất.</span>
            @endif
        </div>

        <section class="submission-list" aria-label="Danh sách lượt nộp">
            <p class="submission-table-hint" x-show="visible.length > 0" x-cloak>Vuốt ngang để xem đủ điểm và thao tác bài làm.</p>

            <div class="submission-empty" x-show="visible.length === 0" x-cloak>
                <x-lucide name="clipboard-list" />
                <h3>Chưa có lượt nộp phù hợp</h3>
                <p x-text="rows.length ? 'Thử thay đổi tên, thời gian hoặc bộ lọc.' : 'Các lượt nộp sẽ xuất hiện tại đây sau khi làm bài.'"></p>
                <button type="button" x-show="hasFilters" x-cloak @click="resetFilters()">Đặt lại bộ lọc</button>
            </div>

            <div class="submission-table-scroll" tabindex="0" role="region" aria-label="Bảng lượt nộp, cuộn ngang trên màn hình nhỏ" x-show="visible.length > 0" x-cloak>
                <table class="submission-table">
                    <caption class="sr-only">Nhật ký nộp bài của {{ $subject['title'] }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">Người nộp</th>
                            <th scope="col" :aria-sort="oldest ? 'ascending' : 'descending'">
                                <button type="button" class="submission-sort" @click="oldest = !oldest">Thời gian nộp <x-lucide name="arrow-up-down" class="h-3.5 w-3.5" /></button>
                            </th>
                            <th scope="col">Kết quả</th>
                            <th scope="col" class="score-column">Điểm</th>
                            <th scope="col">{{ $isExam ? 'Chi tiết' : 'Bài làm' }}</th>
                            @if ($canDesignate)
                                <th scope="col">Bài mẫu <span class="admin-column-label">Admin</span></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="record in visible" :key="record.id">
                            <tr class="submission-row" :class="selectedId === record.id ? 'selected' : ''">
                                <td>
                                    <button type="button" class="submission-person" :aria-expanded="selectedId === record.id" @click="toggle(record.id)">
                                        <strong x-text="record.submitter"></strong>
                                        <span x-text="record.account || 'Tài khoản chưa xác định'"></span>
                                    </button>
                                </td>
                                <td>
                                    <time x-text="record.submittedAt"></time>
                                    <span class="table-secondary" x-text="record.submittedDate"></span>
                                </td>
                                <td>
                                    <span class="submission-result-badge" :class="'result-' + record.result">
                                        <span aria-hidden="true"></span><b x-text="resultLabels[record.result]" style="font-weight:700"></b>
                                    </span>
                                </td>
                                <td class="score-column" :class="'result-' + record.result">
                                    <strong class="table-score" x-text="scoreText(record.score)"></strong><span class="table-score-max">/<span x-text="scoreText(record.maxScore)"></span></span>
                                </td>
                                <td>
                                    <button type="button" class="submission-view" :aria-label="'Xem bài của ' + record.submitter" :aria-expanded="selectedId === record.id" @click="toggle(record.id)">
                                        {{ $isExam ? 'Xem chi tiết' : 'Xem bài' }}<x-lucide name="chevron-right" class="h-3.5 w-3.5" />
                                    </button>
                                    @if (! $isExam && ! $canDesignate)
                                        {{-- Người không phải admin: chỉ thấy nhãn đánh dấu lượt nộp đang là bài mẫu. --}}
                                        <span class="sample-label" x-show="sampleId === record.id" x-cloak><x-lucide name="star" class="h-3.5 w-3.5" />Bài mẫu</span>
                                    @endif
                                </td>
                                @if ($canDesignate)
                                    <td>
                                        <template x-if="sampleId === record.id">
                                            <span class="sample-cell">
                                                <span class="sample-label"><x-lucide name="star" class="h-3.5 w-3.5" />Bài mẫu</span>
                                                <button type="button" class="sample-clear" :disabled="sampleBusy" @click="clearSample()" title="Bỏ chỉ định bài mẫu">Bỏ chỉ định</button>
                                            </span>
                                        </template>
                                        <template x-if="sampleId !== record.id">
                                            <button type="button" class="submission-button sample-action" :disabled="!record.hasResponse || sampleBusy"
                                                    :title="record.hasResponse ? 'Chỉ định bài của ' + record.submitter + ' làm bài mẫu' : 'Lượt nộp này chưa lưu nội dung bài làm'"
                                                    @click="chooseSample(record)">
                                                <x-lucide name="star" class="h-3.5 w-3.5" />Chỉ định bài mẫu
                                            </button>
                                        </template>
                                    </td>
                                @endif
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <nav class="submission-pagination" aria-label="Phân trang lượt nộp" x-show="filtered.length > 0" x-cloak>
                <div class="submission-page-info">
                    <span><strong x-text="rangeLabel"></strong> / <span x-text="filtered.length"></span> lượt nộp</span>
                    <label>Số dòng
                        <select aria-label="Số dòng mỗi trang" x-model.number="pageSize" @change="page = 1; selectedId = null">
                            <option value="10">10</option><option value="20">20</option><option value="50">50</option>
                        </select>
                    </label>
                </div>
                <div class="submission-page-controls">
                    <span class="submission-page-count">Trang <span x-text="currentPage"></span> / <span x-text="totalPages"></span></span>
                    <button type="button" aria-label="Trang trước" :disabled="currentPage === 1" @click="goTo(currentPage - 1)"><x-lucide name="chevron-left" class="h-3.5 w-3.5" /></button>
                    <template x-for="(n, i) in pageNumbers" :key="'pg' + n">
                        <span style="display:contents">
                            <span class="submission-page-gap" aria-hidden="true" x-show="i > 0 && n - pageNumbers[i - 1] > 1">…</span>
                            <button type="button" :aria-label="'Trang ' + n" :aria-current="n === currentPage ? 'page' : null" @click="goTo(n)" x-text="n"></button>
                        </span>
                    </template>
                    <button type="button" aria-label="Trang sau" :disabled="currentPage === totalPages" @click="goTo(currentPage + 1)"><x-lucide name="chevron-right" class="h-3.5 w-3.5" /></button>
                </div>
            </nav>
        </section>

        {{-- Chi tiết lượt nộp đang chọn --}}
        <template x-if="selected">
            <section class="submission-detail" aria-label="Chi tiết lượt nộp">
                <div class="submission-detail-caption">
                    <h2>Chi tiết bài nộp</h2>
                    <button type="button" @click="selectedId = null" aria-label="Thu gọn chi tiết"><x-lucide name="x" class="h-4 w-4" />Thu gọn</button>
                </div>
                <div class="submission-detail-heading">
                    <div>
                        <p class="submission-eyebrow">CHI TIẾT LƯỢT NỘP</p>
                        <h2 x-text="selected.submitter"></h2>
                        <p x-text="selected.account || 'Chưa xác định tài khoản'"></p>
                        <code x-text="'#' + selected.id"></code>
                    </div>
                    <div class="submission-large-score" :class="'result-' + selected.result">
                        <strong><span x-text="scoreText(selected.score)"></span><small>/<span x-text="scoreText(selected.maxScore)"></span></small></strong>
                        <span class="submission-result-badge" :class="'result-' + selected.result"><span aria-hidden="true"></span><b x-text="resultLabels[selected.result]" style="font-weight:700"></b></span>
                    </div>
                </div>
                <div class="submission-milestones">
                    <div x-show="selected.startedAt" x-cloak><span>Bắt đầu</span><strong x-text="selected.startedAt"></strong></div>
                    <div><span>Nộp bài</span><strong><span x-text="selected.submittedAt"></span> <span x-text="selected.submittedDate"></span></strong></div>
                    <div><span>Chấm xong</span><strong x-text="selected.gradedAt || (selected.result === 'pending' ? 'Đang chờ chấm' : '—')"></strong></div>
                    <div x-show="selected.duration" x-cloak><span>Thời gian làm</span><strong x-text="selected.duration"></strong></div>
                    <div x-show="selected.tests" x-cloak><span>Số test</span><strong x-text="selected.tests"></strong></div>
                </div>

                <div class="submission-detail-body">
                    @if ($isExam)
                        <template x-if="selected.items && selected.items.length">
                            <div class="submission-questions">
                                <template x-for="(item, idx) in selected.items" :key="'it' + idx">
                                    <details>
                                        <summary>
                                            <span class="question-number" x-text="idx + 1"></span>
                                            <div>
                                                <strong x-text="item.label"></strong>
                                                <p><span x-text="resultLabels[item.result]"></span><span x-show="item.tests" x-cloak> · <span x-text="item.tests"></span></span></p>
                                            </div>
                                            <b :class="'result-' + item.result"><span x-text="scoreText(item.score)"></span><small> / <span x-text="scoreText(item.max)"></span></small></b>
                                            <x-lucide name="chevron-right" class="h-4 w-4" />
                                        </summary>
                                        <div class="question-expanded">
                                            <template x-if="item.response">
                                                <div>
                                                    <p x-show="item.language" x-cloak>Ngôn ngữ: <strong x-text="item.language"></strong></p>
                                                    <pre x-text="item.response"></pre>
                                                </div>
                                            </template>
                                            <template x-if="!item.response">
                                                <p class="submission-muted">Phần này không lưu nội dung bài làm riêng.</p>
                                            </template>
                                        </div>
                                    </details>
                                </template>
                            </div>
                        </template>
                        <template x-if="!selected.items || !selected.items.length">
                            <p class="submission-muted">Lượt thi này chưa lưu chi tiết từng câu.</p>
                        </template>
                    @else
                        <div class="submission-answer-toolbar"><span x-text="selected.language || 'Câu trả lời'"></span><span class="sample-label" x-show="sampleId === selected.id" x-cloak><x-lucide name="star" class="h-3.5 w-3.5" />Bài mẫu</span><span x-show="selected.verdictLabel" x-cloak x-text="selected.verdictLabel"></span></div>
                        <pre class="submission-code" x-text="selected.response ? selected.response : 'Chưa lưu nội dung bài làm cho lượt nộp này.'"></pre>
                    @endif
                </div>
            </section>
        </template>
        <p class="submission-open-hint" x-show="!selected && visible.length > 0" x-cloak>
            Chọn “{{ $isExam ? 'Xem chi tiết' : 'Xem bài' }}” để mở {{ $isExam ? 'kết quả từng câu' : 'bài làm và kết quả' }}.
        </p>

        <p class="submission-storage-note">
            @if ($role === 'admin')
                Quản trị viên xem được mọi lượt nộp{{ $canDesignate ? ' và là người duy nhất chỉ định được bài mẫu cho bài tập' : '' }}.
            @elseif ($role === 'teacher')
                Bạn xem được lượt nộp của chính mình và của các học sinh bạn đã giao {{ $isExam ? 'đề' : 'bài' }} này.
            @else
                Bạn chỉ xem được lượt nộp của chính mình.
            @endif
        </p>
    </main>
</div>
@endsection

@push('scripts')
<script>
    /*
     * SỬA 7/10 — trạng thái trang Nhật ký nộp bài: lọc / sắp xếp / phân trang chạy trên trình duyệt
     * (dữ liệu máy chủ đã giới hạn 500 lượt mới nhất và đã lọc theo quyền xem).
     */
    function onthiHistoryPage(config) {
        return {
            rows: config.rows || [],
            type: config.type,
            canSeeOthers: !!config.canSeeOthers,
            // SỬA 7/10 — bài mẫu (chỉ admin chỉ định được).
            canDesignate: !!config.canDesignate,
            sampleId: config.sampleId || null,
            sampleUrl: config.sampleUrl || '',
            sampleClearUrl: config.sampleClearUrl || '',
            csrf: config.csrf || '',
            sampleBusy: false,
            notice: '',
            noticeError: false,
            query: '',
            resultFilter: 'all',
            onlyMine: false,
            from: '',
            to: '',
            oldest: false,
            selectedId: null,
            page: 1,
            pageSize: 10,
            resultLabels: { ac: 'Đúng · AC', partial: 'Đúng một phần', wa: 'Sai · WA', pending: 'Chờ chấm' },

            init() {
                ['query', 'resultFilter', 'onlyMine', 'from', 'to', 'oldest'].forEach((key) => {
                    this.$watch(key, () => { this.page = 1; this.selectedId = null; });
                });
            },

            scoreText(v) {
                if (v === null || v === undefined || Number.isNaN(Number(v))) { return '—'; }
                return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 }).format(Number(v));
            },

            // Các lượt khớp ô tìm kiếm / ngày / "chỉ bài của tôi" — CHƯA lọc theo kết quả, để số đếm trên chip kết quả đúng.
            get matching() {
                const q = this.query.trim().toLocaleLowerCase('vi');
                return this.rows.filter((r) => {
                    const hay = (r.submitter + ' ' + (r.account || '') + ' ' + r.id).toLocaleLowerCase('vi');
                    return (!q || hay.includes(q))
                        && (!this.from || r.submittedDay >= this.from)
                        && (!this.to || r.submittedDay <= this.to)
                        && (!this.onlyMine || r.mine);
                });
            },
            get resultCounts() {
                const c = { all: this.matching.length, ac: 0, partial: 0, wa: 0, pending: 0 };
                this.matching.forEach((r) => { c[r.result] = (c[r.result] || 0) + 1; });
                return c;
            },
            get filtered() {
                const list = this.matching.filter((r) => this.resultFilter === 'all' || r.result === this.resultFilter);
                return [...list].sort((a, b) => (a.submittedTs - b.submittedTs) * (this.oldest ? 1 : -1));
            },
            get hasFilters() {
                return !!(this.query || this.resultFilter !== 'all' || this.onlyMine || this.from || this.to);
            },
            resetFilters() {
                this.query = ''; this.resultFilter = 'all'; this.onlyMine = false; this.from = ''; this.to = '';
            },

            get submitterCount() { return new Set(this.rows.map((r) => r.userId)).size; },
            get bestLabel() {
                const ratios = this.rows.filter((r) => r.score !== null && r.maxScore > 0).map((r) => r.score / r.maxScore * 100);
                return ratios.length ? this.scoreText(Math.max(...ratios)) + '%' : '—';
            },

            get totalPages() { return Math.max(1, Math.ceil(this.filtered.length / this.pageSize)); },
            get currentPage() { return Math.min(this.page, this.totalPages); },
            get visible() {
                const start = (this.currentPage - 1) * this.pageSize;
                return this.filtered.slice(start, start + this.pageSize);
            },
            get rangeLabel() {
                if (!this.filtered.length) { return '0'; }
                const start = (this.currentPage - 1) * this.pageSize + 1;
                return start + '–' + Math.min(this.currentPage * this.pageSize, this.filtered.length);
            },
            get pageNumbers() {
                const total = this.totalPages;
                const start = Math.max(1, Math.min(this.currentPage - 1, total - 2));
                const set = new Set([1, total]);
                for (let i = 0; i < Math.min(3, total); i++) { set.add(start + i); }
                return [...set].sort((a, b) => a - b);
            },
            goTo(n) { this.page = Math.min(Math.max(1, n), this.totalPages); this.selectedId = null; },

            get selected() { return this.visible.find((r) => r.id === this.selectedId) || null; },
            toggle(id) { this.selectedId = this.selectedId === id ? null : id; },

            // ── Bài mẫu (chỉ admin) ──
            async sampleRequest(method, url, body) {
                const res = await fetch(url, {
                    method,
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: body ? JSON.stringify(body) : undefined,
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    let message = 'Chưa lưu được bài mẫu. Vui lòng thử lại.';
                    if (res.status === 422 && data.errors) {
                        const first = Object.values(data.errors)[0];
                        message = Array.isArray(first) ? first[0] : message;
                    } else if (res.status === 419) {
                        message = 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang rồi thử lại.';
                    } else if (res.status === 401 || res.status === 403) {
                        message = 'Chỉ quản trị viên mới được chỉ định bài mẫu.';
                    }
                    throw new Error(message);
                }
                return data;
            },

            async chooseSample(record) {
                if (!this.canDesignate || this.sampleBusy || !record.hasResponse) { return; }
                this.sampleBusy = true;
                this.notice = '';
                try {
                    await this.sampleRequest('POST', this.sampleUrl, { record: record.id });
                    this.sampleId = record.id;
                    this.noticeError = false;
                    this.notice = 'Đã chỉ định bài của ' + record.submitter + ' làm bài mẫu. Học sinh sẽ thấy ở tab “Bài mẫu” của bài này.';
                } catch (e) {
                    this.noticeError = true;
                    this.notice = e.message;
                } finally {
                    this.sampleBusy = false;
                }
            },

            async clearSample() {
                if (!this.canDesignate || this.sampleBusy) { return; }
                this.sampleBusy = true;
                this.notice = '';
                try {
                    await this.sampleRequest('DELETE', this.sampleClearUrl);
                    this.sampleId = null;
                    this.noticeError = false;
                    this.notice = 'Đã bỏ chỉ định bài mẫu. Tab “Bài mẫu” quay về code mẫu của câu hỏi (nếu có).';
                } catch (e) {
                    this.noticeError = true;
                    this.notice = e.message;
                } finally {
                    this.sampleBusy = false;
                }
            },
        };
    }
</script>
@endpush
