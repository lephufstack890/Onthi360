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
            page: 1,
            pageSize: config.pageSize || 5,
            expanded: null,

            init() {
                this.$watch('query', () => { this.page = 1; this.expanded = null; });
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
                const q = normalize(this.query);
                if (!q) return this.rows;
                return this.rows.filter((p) => normalize([this.display(p), p.sub, p.badge, '#' + p.rank, p.rank].join(' ')).includes(q));
            },
            get showPodium() { return this.rows.length > 0 && !this.query.trim(); },
            get podium() { return this.rows.slice(0, 3); },
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
