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

            // Bản mẫu đồng bộ ô "Mục tiêu" bên dưới theo slide đang xem.
            syncGoalWithSlide() {
                if (this.slideIndex < this.goals.length) {
                    this.goalIndex = this.slideIndex;
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

            get grade() { return this.grades[this.gradeIndex] || ''; },
            cycleGrade() { this.gradeIndex = (this.gradeIndex + 1) % this.grades.length; },
            get goal() { return this.goals[this.goalIndex] || ''; },
            cycleGoal() { this.goalIndex = (this.goalIndex + 1) % this.goals.length; },

            get carouselIndex() { return this.mainTab === 'courses' ? this.courseIndex : this.pathIndex; },
            get carouselCount() { return this.mainTab === 'courses' ? this.courseCount : this.pathCount; },
            moveCarousel(dir) {
                if (this.mainTab === 'courses') {
                    this.courseIndex = (this.courseIndex + dir + this.courseCount) % this.courseCount;
                } else {
                    this.pathIndex = (this.pathIndex + dir + this.pathCount) % this.pathCount;
                }
            },

            // Bản mẫu: luôn lấy tối đa 4 mục liên tiếp và quay vòng ở cuối danh sách.
            circular(total, start) {
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

            toggleFaq(i) { this.openFaq = this.openFaq === i ? null : i; },
        };
    }
</script>
