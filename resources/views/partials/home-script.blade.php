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

            /*
             * [HOME-04] SỬA 15/9 — hai nút "Khối lớp" và "Mục tiêu" đổ từ KHOÁ HỌC THẬT
             * (config.coursePicker, xem Public\CourseService::pickerPayload()).
             *
             * Hai danh sách viết cứng cũ được GIỮ LẠI làm lối dự phòng: khi hệ thống chưa có
             * khoá nào đã phát hành, hai nút vẫn có nội dung như bản thiết kế thay vì trơ ra
             * rỗng. Xem getter grades/goals bên dưới.
             */
            pickerCourses: (config.coursePicker && config.coursePicker.courses) || [],
            pickerGrades: (config.coursePicker && config.coursePicker.grades) || [],
            fallbackGrades: config.grades || [],
            fallbackGoals: config.goals || [],
            coursesHref: config.coursesHref || '#',
            gradeIndex: 0,
            goalIndex: 0,
            // SỬA 30/9 (2) — hai ô chọn ở [HOME-04] đổi từ "bấm-xoay-vòng" sang THẢ DANH SÁCH.
            // Mỗi lúc chỉ mở được một bảng: mở bảng này thì đóng bảng kia (xem openGrade/openGoal).
            gradeOpen: false,
            goalOpen: false,

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
                /*
                 * SỬA 15/9 — khi ô "Mục tiêu" đổ khoá học thật thì KHÔNG đồng bộ theo slide
                 * nữa: slide thứ 3 chẳng liên quan gì tới khoá thứ 3, nhảy như vậy sẽ tự đổi
                 * lựa chọn của người dùng ngay dưới tay họ và nút vàng trỏ sang khoá khác.
                 */
                if (this.hasCourses) return;
                // Bảng chọn đang mở thì để yên, đừng đổi dòng đang sáng ngay dưới con trỏ.
                if (this.goalOpen) return;

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

            // Có khoá học thật thì dùng, không thì rơi về danh sách viết cứng của bản thiết kế.
            get hasCourses() { return this.pickerCourses.length > 0; },
            get grades() { return this.hasCourses ? this.pickerGrades : this.fallbackGrades; },

            /*
             * Các khoá thuộc khối lớp đang chọn. Khối nào không có khoá nào (không xảy ra vì
             * danh sách khối sinh RA TỪ khoá, nhưng vẫn phòng) thì trả cả danh sách, để nút
             * "Mục tiêu" không bao giờ rỗng.
             */
            get coursesForGrade() {
                /*
                 * SỬA 30/9 (2) — so theo MẢNG khối (gradeList) do máy chủ gửi kèm. Từ hôm
                 * cho phép một khoá gắn nhiều khối, trường grade có thể là "Lớp 8, Lớp 9";
                 * so nguyên chuỗi với "Lớp 8" sẽ không khớp, và danh sách lộ trình rơi về
                 * hiện TẤT CẢ khoá — đúng là thứ khách phàn nàn.
                 */
                const hit = this.pickerCourses.filter((c) => this.gradesOf(c).includes(this.grade));
                return hit.length > 0 ? hit : [];
            },

            gradesOf(course) {
                if (Array.isArray(course.gradeList) && course.gradeList.length > 0) return course.gradeList;
                return String(course.grade || '').split(',').map((g) => g.trim()).filter(Boolean);
            },
            get goals() { return this.goalRows.map((r) => r.goal); },

            /*
             * Từng dòng của bảng "Lộ trình" thả xuống: tên lộ trình + dòng phụ (tên khoá và
             * số lớp đang mở) để người xem biết mình đang chọn cái gì. Chưa có khoá nào đã
             * phát hành thì rơi về danh sách viết cứng của bản thiết kế, không có dòng phụ.
             */
            get goalRows() {
                if (! this.hasCourses) {
                    return this.fallbackGoals.map((g) => ({ goal: g, sub: '' }));
                }

                return this.coursesForGrade.map((c) => {
                    const bits = [];
                    if (c.title && c.title !== c.goal) bits.push(c.title);
                    if (c.openClasses > 0) bits.push(c.openClasses + ' lớp đang mở');

                    return { goal: c.goal, sub: bits.join(' · ') };
                });
            },

            get grade() { return this.grades[this.gradeIndex] || ''; },
            get goal() { return this.goals[this.goalIndex] || ''; },

            openGrade() { this.gradeOpen = ! this.gradeOpen; this.goalOpen = false; },
            openGoal() { this.goalOpen = ! this.goalOpen; this.gradeOpen = false; },

            // Đổi khối lớp -> danh sách lộ trình đổi theo, nên đưa con trỏ về đầu, tránh trỏ
            // vào một vị trí không còn tồn tại ở khối mới.
            pickGrade(i) {
                if (i < 0 || i >= this.grades.length) return;
                this.gradeIndex = i;
                this.goalIndex = 0;
                this.gradeOpen = false;
            },
            pickGoal(i) {
                if (i < 0 || i >= this.goals.length) return;
                this.goalIndex = i;
                this.goalOpen = false;
            },

            // Khoá học ứng với cặp (khối lớp, mục tiêu) đang chọn — cũng là đích của nút vàng.
            get pickedCourse() {
                if (! this.hasCourses) return null;
                const list = this.coursesForGrade;
                if (list.length === 0) return null;
                return list[this.goalIndex] || list[0] || null;
            },
            /*
             * Nút vàng dẫn sang TRANG CHI TIẾT KHOÁ HỌC đang chọn.
             *
             * SỬA 16/9 — có thử đổi sang trang Lớp học đã lọc sẵn (?khoa=<id>) rồi khách đổi ý,
             * trả về như cũ. Muốn dùng lại chỉ cần đổi dòng return thành:
             *     return this.coursesHref + '?khoa=' + this.pickedCourse.id;
             * Trang Lớp học vẫn ĐỌC ĐƯỢC tham số ?khoa= (xem Public\CourseController::index),
             * nên link kiểu đó gửi cho nhau vẫn mở ra đúng danh sách đã lọc.
             */
            get pickerHref() { return this.pickedCourse ? this.pickedCourse.href : this.coursesHref; },

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
