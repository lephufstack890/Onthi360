{{-- Alpine cho màn Cuộc thi — chuyển đúng useState/useMemo/useEffect của
     education-main/src/components/ContestsPage.jsx (lọc, phân trang 3 thẻ/trang, mở hộp chi tiết).

     Khác bản mẫu: bỏ state "registrationStates" (gửi đăng ký chờ BTC duyệt) vì hệ thống không
     có luồng đó — "đã tham gia" là dữ liệu THẬT đã tính sẵn ở máy chủ (attempts đã nộp), nên
     ở đây chỉ đọc, không tự đổi. Thêm đồng hồ đếm ngược chạy theo mốc giờ thật. --}}
<script>
    function onthiContestsPage(config) {
        return {
            rows: config.rows,
            deadline: config.deadline, // timestamp (giây) hoặc null
            activeTab: 'all',
            page: 1,
            pageSize: 3,
            openId: null,
            now: Math.floor(Date.now() / 1000),
            timer: null,

            init() {
                // Đồng hồ đếm ngược — chỉ chạy khi thật sự có mốc giờ để đếm.
                if (this.deadline) {
                    this.timer = setInterval(() => { this.now = Math.floor(Date.now() / 1000); }, 1000);
                }
                // Đổi bộ lọc mà trang hiện tại vượt quá số trang mới thì kéo về trang cuối.
                this.$watch('totalPages', (value) => { if (this.page > value) this.page = value; });
                // Mở hộp chi tiết thì khoá cuộn nền, đóng thì trả lại.
                this.$watch('openId', (value) => {
                    document.body.style.overflow = value === null ? '' : 'hidden';
                });
            },

            destroy() {
                if (this.timer) clearInterval(this.timer);
                document.body.style.overflow = '';
            },

            // ── Lọc ──
            matchesTab(row) {
                if (this.activeTab === 'all') return true;
                if (this.activeTab === 'surveys') return row.type !== 'contest';
                if (this.activeTab === 'participated') return row.participated === true;
                return row.statusValue === this.activeTab;
            },

            get matchedIds() {
                return this.rows.filter((row) => this.matchesTab(row)).map((row) => row.id);
            },

            get totalPages() {
                return Math.max(1, Math.ceil(this.matchedIds.length / this.pageSize));
            },

            get visibleIds() {
                const start = (this.page - 1) * this.pageSize;
                return this.matchedIds.slice(start, start + this.pageSize);
            },

            isVisible(id) {
                return this.visibleIds.includes(id);
            },

            setTab(tab) {
                this.activeTab = tab;
                this.page = 1;
            },

            openDetail(id) {
                this.openId = id;
            },

            // ── Đồng hồ đếm ngược ──
            get countdownLabel() {
                if (!this.deadline) return '—';
                const remaining = this.deadline - this.now;
                if (remaining <= 0) return '00 : 00 : 00';

                const days = Math.floor(remaining / 86400);
                const hours = Math.floor((remaining % 86400) / 3600);
                const minutes = Math.floor((remaining % 3600) / 60);
                const seconds = remaining % 60;
                const pad = (value) => String(value).padStart(2, '0');

                // Còn hơn 1 ngày thì hiện "N ngày HH : MM" cho dễ đọc; dưới 1 ngày mới đếm tới giây.
                if (days >= 1) return days + ' ngày ' + pad(hours) + ' : ' + pad(minutes);
                return pad(hours) + ' : ' + pad(minutes) + ' : ' + pad(seconds);
            },
        };
    }
</script>
