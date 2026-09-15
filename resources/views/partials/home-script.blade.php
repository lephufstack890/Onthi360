<script>
    function onthiHomePage(config) {
        return {
            // [HOME-06A] vai trò đang xem (chỉ có tác dụng khi người dùng giữ nhiều vai trò).
            activeRole: config.activeRole || 'student',
            heroImages: config.heroImages || [],
            heroAlts: config.heroAlts || [],
            reduce: false,
            timers: [],

            noticeCount: config.noticeCount,
            noticeIndex: 0,
            noticePaused: false,

            slideCount: config.slideCount,
            slideIndex: 0,
            slidePaused: false,

            grades: config.grades,
            gradeIndex: 0,
            goals: config.goals,
            goalIndex: 0,

            /* ═══ B2 · [HOME-04] ba ô chọn thu hẹp dần ═══
               Toàn bộ danh sách lộ trình đang hiển thị được nạp sẵn vào trang, nên mọi thao
               tác lọc chạy ngay tại chỗ — không gọi máy chủ, không tải lại trang.
               Ba ô chọn KHÔNG phải ba danh sách cố định: chúng sinh ra từ chính dữ liệu này,
               nên không bao giờ hiện một lựa chọn không có lộ trình nào đứng sau. */
            pathPicker: config.pathPicker || { paths: [], grades: [], indexHref: '#', showLanguage: false },
            /* Hai nút bấm-để-đổi, nên giữ ở dạng CHỈ SỐ trong vòng quay chứ không phải giá trị.
               Vị trí 0 luôn là "tất cả" — đó là trạng thái mặc định khi khách chưa chọn gì. */
            gradeCursor: 0,
            goalCursor: 0,

            mainTab: 'courses',
            mainTabPaused: false,
            courseIndex: 0,
            pathIndex: 0,
            courseCount: config.courseCount,
            pathCount: config.pathCount,

            sidebarTab: 'leaderboard',

            openFaq: null,

            init() {
                const mq = window.matchMedia('(prefers-reduced-motion: reduce)');
                this.reduce = mq.matches;
                mq.addEventListener && mq.addEventListener('change', (e) => { this.reduce = e.matches; });

                this.timers.push(setInterval(() => {
                    if (this.slidePaused || this.reduce) return;
                    this.slideIndex = (this.slideIndex + 1) % this.slideCount;
                    this.syncGoalWithSlide();
                }, 4500));

                this.timers.push(setInterval(() => {
                    if (this.mainTabPaused || this.reduce) return;
                    this.mainTab = this.mainTab === 'courses' ? 'path' : 'courses';
                }, 6000));

                this.timers.push(setInterval(() => {
                    if (this.noticePaused || this.reduce) return;
                    this.noticeIndex = (this.noticeIndex + 1) % this.noticeCount;
                }, 5200));
            },

            destroy() {
                this.timers.forEach(clearInterval);
            },

            /* Bản mẫu đồng bộ ô "Mục tiêu" bên dưới theo slide hero đang xem.
               SỬA 15/9 — chỉ còn áp dụng khi CHƯA có lộ trình nào (lúc đó ô mục tiêu vẫn chạy
               theo hai danh sách cũ của bản thiết kế). Có lộ trình thật rồi thì KHÔNG đồng bộ
               nữa: hero tự đổi slide mỗi 4,5 giây, đồng bộ vào là cứ vài giây lại giật mất
               lựa chọn khách vừa bấm. */
            syncGoalWithSlide() {
                if (this.hasPaths) return;
                if (this.slideIndex < this.goals.length) {
                    this.goalIndex = this.slideIndex;
                    this.goalCursor = this.slideIndex;
                }
            },

            selectSlide(i) {
                this.slideIndex = i;
                this.syncGoalWithSlide();
            },
            prevSlide() {
                this.slideIndex = (this.slideIndex - 1 + this.slideCount) % this.slideCount;
                this.syncGoalWithSlide();
            },
            nextSlide() {
                this.slideIndex = (this.slideIndex + 1) % this.slideCount;
                this.syncGoalWithSlide();
            },

            prevNotice() { this.noticeIndex = (this.noticeIndex - 1 + this.noticeCount) % this.noticeCount; },
            nextNotice() { this.noticeIndex = (this.noticeIndex + 1) % this.noticeCount; },

            /* ═══ B2 · Hai nút chọn của khối [HOME-04] ═══
               Giao diện y như cũ (bấm để đổi), nhưng nội dung là lộ trình CÓ THẬT.

               Chưa có lộ trình nào được đăng thì lùi về đúng hai danh sách cũ của bản thiết
               kế và nút vàng trỏ về trang Lớp học — khối này vẫn sống động như trước, không
               phải một ô chết. */
            get hasPaths() { return this.pathPicker.paths.length > 0; },

            /** Vòng quay khối lớp: "Tất cả khối" rồi tới từng lớp có lộ trình phủ tới. */
            get gradeCycle() {
                if (! this.hasPaths) return this.grades;
                return ['Tất cả khối'].concat(this.pathPicker.grades.map((g) => 'Lớp ' + g));
            },

            /** Vòng quay mục tiêu, đã thu hẹp theo khối đang chọn. */
            get goalCycle() {
                if (! this.hasPaths) return this.goals;
                return [''].concat(this.goalOptions);
            },

            get grade() { return this.gradeCycle[this.gradeCursor % this.gradeCycle.length] || ''; },
            get goal() { return this.goalCycle[this.goalCursor % this.goalCycle.length] || ''; },

            /** Giá trị dùng để lọc: 'all' hoặc số lớp. */
            get pickGrade() {
                if (! this.hasPaths || this.gradeCursor === 0) return 'all';
                return String(this.pathPicker.grades[(this.gradeCursor - 1) % this.pathPicker.grades.length]);
            },

            /** Giá trị dùng để lọc: 'all' hoặc đúng câu mục tiêu. */
            get pickGoal() {
                if (! this.hasPaths || this.goalCursor === 0) return 'all';
                return this.goalOptions[(this.goalCursor - 1) % this.goalOptions.length] ?? 'all';
            },

            cycleGrade() {
                this.gradeCursor = (this.gradeCursor + 1) % this.gradeCycle.length;
                /* Đổi khối xong, mục tiêu đang chọn có thể không còn thuộc khối mới. Phải đưa
                   về "Mọi mục tiêu", nếu không khách sẽ thấy "chưa có lộ trình" trong khi
                   thực ra là họ đang giữ một mục tiêu của khối khác. */
                if (this.pickGoal !== 'all' && this.goalOptions.indexOf(this.pickGoal) === -1) {
                    this.goalCursor = 0;
                }
            },

            cycleGoal() {
                this.goalCursor = (this.goalCursor + 1) % this.goalCycle.length;
            },

            get carouselIndex() { return this.mainTab === 'courses' ? this.courseIndex : this.pathIndex; },
            get carouselCount() { return this.mainTab === 'courses' ? this.courseCount : this.pathCount; },
            moveCarousel(dir) {
                // B5 — số lộ trình lấy từ cơ sở dữ liệu nên có thể bằng 0. Chia lấy dư cho 0
                // ra NaN và làm hỏng cả băng chuyền, phải chặn trước.
                if (this.carouselCount === 0) return;
                if (this.mainTab === 'courses') {
                    this.courseIndex = (this.courseIndex + dir + this.courseCount) % this.courseCount;
                } else {
                    this.pathIndex = (this.pathIndex + dir + this.pathCount) % this.pathCount;
                }
            },

            // Bản mẫu: luôn lấy tối đa 4 mục liên tiếp và quay vòng ở cuối danh sách.
            circular(total, start) {
                if (! total || total < 1) return [];
                const take = Math.min(4, total);
                return Array.from({ length: take }, (_, offset) => ({
                    original: (start + offset) % total,
                    offset: offset,
                }));
            },
            get visibleCourses() { return this.circular(this.courseCount, this.courseIndex); },
            get visiblePaths() { return this.circular(this.pathCount, this.pathIndex); },

            slotOf(slots, index) {
                const hit = slots.find((s) => s.original === index);
                return hit ? hit.offset : null;
            },

            offsetClass(offset) {
                return offset === 0 ? 'block' : (offset < 3 ? 'hidden sm:block' : 'hidden 2xl:block');
            },

            /* ── Lọc dần: mỗi bước chỉ xét các ô ĐỨNG TRƯỚC nó ── */
            matchGrade(p) {
                if (this.pickGrade === 'all') return true;
                const g = Number(this.pickGrade);
                return g >= p.gradeFrom && g <= p.gradeTo;
            },

            /** Các mục tiêu có thật trong khối đang chọn. */
            get goalOptions() {
                const seen = [];
                this.pathPicker.paths.forEach((p) => {
                    if (this.matchGrade(p) && p.goal && seen.indexOf(p.goal) === -1) seen.push(p.goal);
                });
                return seen;
            },

            /* Giao diện cũ chỉ có HAI nút (khối, mục tiêu) nên không lọc theo ngôn ngữ ở đây.
               Ô ngôn ngữ đang tắt toàn hệ thống (LearningPathService::SHOW_LANGUAGE) nên hiện
               không mất gì; ngày nào bật lại và muốn lọc cả ngôn ngữ ở trang chủ thì thêm một
               vòng quay nữa đúng theo khuôn hai cái trên. */

            /** Các lộ trình khớp cả ba ô. */
            get pickerMatches() {
                return this.pathPicker.paths.filter((p) => {
                    if (! this.matchGrade(p)) return false;
                    if (this.pickGoal !== 'all' && p.goal !== this.pickGoal) return false;
                    return true;
                });
            },

            /* Ba tình huống của nút bấm:
                 · khớp đúng 1  -> đi thẳng vào lộ trình đó
                 · khớp nhiều   -> sang trang danh sách ĐÃ LỌC SẴN theo đúng lựa chọn
                 · không khớp   -> null, giao diện đổi sang lời báo thay vì một nút bấm hụt */
            get pickerHref() {
                // Chưa đăng lộ trình nào: giữ nguyên hành vi cũ, nút trỏ về trang Lớp học.
                if (! this.hasPaths) return '{{ route('courses.index') }}';

                const hits = this.pickerMatches;
                /* Không khớp cái nào thì vẫn trỏ về trang danh sách lộ trình chứ không để link
                   chết — chữ trên nút đã nói rõ là chưa có, người bấm vào vẫn thấy được các
                   lộ trình khác thay vì rơi vào trang trắng. */
                if (hits.length === 0) return this.pathPicker.indexHref;
                if (hits.length === 1) return hits[0].href;

                const params = [];
                if (this.pickGrade !== 'all') params.push('khoi=' + encodeURIComponent(this.pickGrade));
                return this.pathPicker.indexHref + (params.length ? '?' + params.join('&') : '');
            },

            get pickerLabel() {
                if (! this.hasPaths) return 'Xem lộ trình';

                const n = this.pickerMatches.length;
                if (n === 0) return 'Chưa có lộ trình cho lựa chọn này';
                if (n === 1) return 'Xem lộ trình';
                return 'Xem ' + n + ' lộ trình';
            },

            toggleFaq(i) { this.openFaq = this.openFaq === i ? null : i; },
        };
    }
</script>
