<script>
    /*
     * SỬA 8/10 — bảng xếp hạng dựng lại theo LeaderboardPage.jsx: state React (query, anonymous, page, pageSize,
     * expandedRank) chuyển sang Alpine. Danh sách hàng do máy chủ tính sẵn (LeaderboardService), ở đây chỉ lo
     * tìm kiếm không dấu, ẩn tên, mở chi tiết và phân trang 5/10 dòng.
     */
    window.onthiLeaderboardPage = function (config) {
        const nf = new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 });
        const normalize = (value) => String(value || '')
            .normalize('NFD').replace(/[̀-ͯ]/g, '')
            .replace(/đ/g, 'd').replace(/Đ/g, 'D')
            .replace(/#/g, '').replace(/\s+/g, ' ')
            .toLocaleLowerCase('vi').trim();

        return {
            rows: config.rows || [],
            query: '',
            provinceFilter: '', // SỬA 6/11 — lọc Tỉnh/Thành phố
            titleFilter: '',    // SỬA 6/11 — lọc Danh hiệu (tiếng Việt)
            page: 1,
            pageSize: config.pageSize || 10, // SỬA 4/11 — mặc định 10 / trang
            expanded: null,

            init() {
                this.$watch('query', () => { this.page = 1; this.expanded = null; });
                this.$watch('provinceFilter', () => { this.page = 1; this.expanded = null; });
                this.$watch('titleFilter', () => { this.page = 1; this.expanded = null; });
                this.$watch('pageSize', () => { this.page = 1; this.expanded = null; });
            },

            fmt(value) { return nf.format(Number(value) || 0); },

            // Tên thật luôn hiện; chỉ khi tài khoản đã bị xoá (không còn tên) mới dùng ảnh đại diện trung tính.
            isAnon(p) { return !p.named; },
            display(p) { return p.name; },
            avatarClass(p) { return this.isAnon(p) ? '' : 'lb-avatar-initials lb-avatar-tone-' + (p.rank % 3); },
            badgeClass(p) {
                if (p.rank === 1) return 'is-gold';
                return (p.badge === 'Specialist' || p.badge === 'Expert') ? 'is-green' : '';
            },
            rowClass(p) {
                return ['lb-row', p.rank <= 3 ? 'lb-row-rank-' + p.rank : '', p.rank % 2 === 0 ? 'is-alternate' : '',
                    this.expanded === p.rank ? 'is-expanded' : '', p.isYou ? 'is-you' : ''].filter(Boolean).join(' ');
            },

            get filtered() {
                const q = normalize(this.query), pv = this.provinceFilter, tt = this.titleFilter;
                if (!q && !pv && !tt) return this.rows;
                return this.rows.filter((p) => (!pv || (this.hasProvince(p) && p.sub === pv))
                    && (!tt || this.titleVi(p) === tt)
                    && (!q || normalize([this.display(p), p.sub, p.badge, this.titleVi(p), '#' + p.rank, p.rank].join(' ')).includes(q)));
            },
            get showPodium() { return this.rows.length > 0 && !this.query.trim() && !this.provinceFilter && !this.titleFilter; },
            get provinces() {
                return [...new Set(this.rows.filter((p) => this.hasProvince(p)).map((p) => p.sub))].sort((a, b) => a.localeCompare(b, 'vi'));
            },
            get titleOptions() {
                const order = ['HUYỀN THOẠI', 'BẬC THẦY', 'CHUYÊN GIA', 'CAO THỦ', 'HỌC VIÊN', 'TÂN BINH'];
                const have = new Set(this.rows.map((p) => this.titleVi(p)));
                return order.filter((t) => have.has(t)).concat([...have].filter((t) => !order.includes(t)));
            },
            get podium() { return this.rows.slice(0, 3); },
            // SỬA 6/11 — giao diện mới: thứ tự hiển thị 2 · 1 · 3; danh hiệu tiếng Việt; dòng phụ "Tỉnh • N bài đã thử".
            get podiumOrdered() { const p = this.podium; return p.length === 3 ? [p[1], p[0], p[2]] : p; },
            titleVi(p) {
                return ({ Grandmaster: 'HUYỀN THOẠI', Master: 'BẬC THẦY', Expert: 'CHUYÊN GIA', Specialist: 'CAO THỦ', Pupil: 'HỌC VIÊN', Newbie: 'TÂN BINH' })[p.badge] || String(p.badge || '').toUpperCase();
            },
            titleClass(p) {
                return 'lbx-t-' + (({ Grandmaster: 'legend', Master: 'master', Expert: 'expert', Specialist: 'pro', Pupil: 'pupil' })[p.badge] || 'new');
            },
            hasProvince(p) { return !!p.sub && !/bài đã thử$/.test(p.sub); },
            subLine(p) {
                const tried = p.attempted != null ? p.attempted + ' bài đã thử' : '';
                return this.hasProvince(p) ? (tried ? p.sub + ' • ' + tried : p.sub) : (p.sub || tried);
            },
            triedText(p) { return p.attempted != null ? p.attempted + ' bài đã thử' : p.ac + ' bài AC'; },
            get totalPages() { return Math.max(1, Math.ceil(this.filtered.length / this.pageSize)); },
            get currentPage() { return Math.min(this.page, this.totalPages); },
            get start() { return (this.currentPage - 1) * this.pageSize; },
            get visible() { return this.filtered.slice(this.start, this.start + this.pageSize); },
            // Tối đa 7 nút số quanh trang hiện tại (bảng có thể tới 500 dòng).
            get pageButtons() {
                const total = this.totalPages, cur = this.currentPage, span = 7;
                let from = Math.max(1, cur - Math.floor(span / 2));
                const to = Math.min(total, from + span - 1);
                from = Math.max(1, to - span + 1);
                const out = [];
                for (let n = from; n <= to; n++) out.push(n);
                return out;
            },

            goto(n) { this.page = Math.min(Math.max(1, n), this.totalPages); this.expanded = null; },
            toggle(rank) { this.expanded = this.expanded === rank ? null : rank; },
        };
    };
</script>
