<script>
    function onthiPracticePage(config) {
        return {
            problems: config.problems,
            exams: config.exams,
            problemPageSize: config.problemPageSize,
            examPageSize: config.examPageSize,

            practiceMode: 'problems',
            activeTab: 'all',          // 'all' hoặc mã dạng câu (mcq/fill_blank/coding/composite)
            selectedTopic: 'all',      // 'all' hoặc id chuyên đề
            selectedDifficulty: 'all',
            selectedExamType: 'all',
            searchQuery: '',
            problemPageIndex: 1,
            examPageIndex: 1,

            init() {
                this.$watch('searchQuery', () => { this.problemPageIndex = 1; this.examPageIndex = 1; });
            },

            changeMode(mode) {
                this.practiceMode = mode;
                this.searchQuery = '';
                if (mode === 'problems') {
                    this.selectedExamType = 'all';
                } else {
                    this.activeTab = 'all';
                    this.selectedTopic = 'all';
                    this.selectedDifficulty = 'all';
                }
            },

            setTab(v) { this.activeTab = v; this.problemPageIndex = 1; },
            setTopic(v) { this.selectedTopic = v; this.problemPageIndex = 1; },
            setDifficulty(v) { this.selectedDifficulty = v; this.problemPageIndex = 1; },
            setExamType(v) { this.selectedExamType = v; this.examPageIndex = 1; },

            resetProblemFilters() {
                this.activeTab = 'all';
                this.selectedTopic = 'all';
                this.selectedDifficulty = 'all';
                this.searchQuery = '';
                this.problemPageIndex = 1;
            },
            resetExamFilters() {
                this.selectedExamType = 'all';
                this.searchQuery = '';
                this.examPageIndex = 1;
            },

            get filteredProblems() {
                const q = this.searchQuery.trim().toLowerCase();
                return this.problems.filter((p) => {
                    const matchTab = this.activeTab === 'all' || p.type === this.activeTab;
                    const matchTopic = this.selectedTopic === 'all' || p.tagIds.includes(this.selectedTopic);
                    const matchDiff = this.selectedDifficulty === 'all' || p.difficulty === this.selectedDifficulty;
                    const matchSearch = !q || p.search.includes(q);
                    return matchTab && matchTopic && matchDiff && matchSearch;
                });
            },

            get filteredExams() {
                const q = this.searchQuery.trim().toLowerCase();
                return this.exams.filter((e) => {
                    const matchType = this.selectedExamType === 'all' || e.type === this.selectedExamType;
                    const matchSearch = !q || e.search.includes(q);
                    return matchType && matchSearch;
                });
            },

            get problemTotalPages() { return Math.max(1, Math.ceil(this.filteredProblems.length / this.problemPageSize)); },
            get examTotalPages() { return Math.max(1, Math.ceil(this.filteredExams.length / this.examPageSize)); },

            // Trang hiện tại không bao giờ vượt quá tổng số trang sau khi lọc.
            get problemPage() { return Math.min(this.problemPageIndex, this.problemTotalPages); },
            get examPage() { return Math.min(this.examPageIndex, this.examTotalPages); },

            get visibleProblemIds() {
                const start = (this.problemPage - 1) * this.problemPageSize;
                return this.filteredProblems.slice(start, start + this.problemPageSize).map((p) => p.id);
            },
            get visibleExamIds() {
                const start = (this.examPage - 1) * this.examPageSize;
                return this.filteredExams.slice(start, start + this.examPageSize).map((e) => e.id);
            },
        };
    }
</script>
