{{-- Ô chọn nhiều học sinh (Select2) dùng chung cho popup "Giao bài/Giao đề" (trang Luyện tập) và popup
     "Giao tài liệu" (trang Tài liệu). Tách ra từ practice-assign-modal ngày 9/10 để hai popup không phải
     chép lại 100 dòng script. Định nghĩa có điều kiện (`window.onthiStudentPicker || ...`) nên include
     nhiều lần vẫn an toàn. --}}
<script>
    /*
     * Ô chọn nhiều học sinh (Select2). Là component Alpine LỒNG trong popup nên đọc/ghi trực tiếp
     * `assign.students` và `assign.error` của component cha (onthiPracticePage / onthiExamAssign)
     * và dùng `assignSearchUrl` của cha. Hàm riêng của ô này đều mang tiền tố "pk" để không đụng tên.
     */
    window.onthiStudentPicker = window.onthiStudentPicker || function () {
        return {
            pkOpen: false,
            pkQuery: '',
            pkResults: [],
            pkLoading: false,
            pkError: '',
            pkActive: 0,
            pkLoaded: false,
            pkTimer: null,
            pkSeq: 0,

            init() {
                // Mỗi lần mở popup là một lượt chọn mới.
                this.$watch('assign.open', (open) => { if (open) { this.pkReset(); } });
            },

            pkReset() {
                this.pkOpen = false;
                this.pkQuery = '';
                this.pkResults = [];
                this.pkError = '';
                this.pkActive = 0;
                this.pkLoaded = false;
                this.pkSeq++;
                clearTimeout(this.pkTimer);
            },

            pkFocus() { if (this.$refs.pkInput) { this.$refs.pkInput.focus(); } },

            pkOpenList() {
                this.pkOpen = true;
                if (!this.pkLoaded) { this.pkFetch(this.pkQuery); }
            },

            pkClose() { this.pkOpen = false; },

            // Esc khi danh sách đang mở chỉ đóng danh sách; đóng rồi bấm Esc nữa mới đóng popup.
            pkEsc(event) {
                if (this.pkOpen) { event.stopPropagation(); this.pkOpen = false; }
            },

            pkOnInput() {
                this.pkOpen = true;
                this.pkActive = 0;
                clearTimeout(this.pkTimer);
                // Chờ người dùng gõ xong một nhịp rồi mới gọi máy chủ.
                this.pkTimer = setTimeout(() => this.pkFetch(this.pkQuery), 250);
            },

            async pkFetch(term) {
                const url = this.assignSearchUrl;
                if (!url) { this.pkError = 'Chưa có địa chỉ tìm học sinh.'; return; }

                const seq = ++this.pkSeq;
                this.pkLoading = true;
                this.pkError = '';

                try {
                    const res = await fetch(url + '?q=' + encodeURIComponent((term || '').trim()), {
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (seq !== this.pkSeq) { return; } // đã có lượt tìm mới hơn
                    if (!res.ok) { throw new Error('http ' + res.status); }
                    const data = await res.json();
                    this.pkResults = Array.isArray(data.students) ? data.students : [];
                    this.pkLoaded = true;
                    this.pkActive = 0;
                } catch (e) {
                    if (seq === this.pkSeq) { this.pkError = 'Không tải được danh sách học sinh.'; }
                } finally {
                    if (seq === this.pkSeq) { this.pkLoading = false; }
                }
            },

            pkIsSelected(id) { return this.assign.students.some((s) => s.id === id); },

            pkToggle(s) {
                this.assign.error = '';
                if (this.pkIsSelected(s.id)) { this.pkRemove(s.id); return; }
                if (this.assign.students.length >= 50) { this.assign.error = 'Mỗi lần giao tối đa 50 học sinh.'; return; }
                this.assign.students = [...this.assign.students, { id: s.id, name: s.name, email: s.email, phone: s.phone }];
            },

            pkRemove(id) {
                this.assign.students = this.assign.students.filter((s) => s.id !== id);
            },

            pkSelectAll() {
                const merged = [...this.assign.students];
                for (const s of this.pkResults) {
                    if (merged.length >= 50) { this.assign.error = 'Mỗi lần giao tối đa 50 học sinh.'; break; }
                    if (!merged.some((m) => m.id === s.id)) { merged.push({ id: s.id, name: s.name, email: s.email, phone: s.phone }); }
                }
                this.assign.students = merged;
            },

            pkClear() { this.assign.students = []; },

            pkMove(step) {
                if (!this.pkOpen) { this.pkOpenList(); return; }
                const n = this.pkResults.length;
                if (!n) { return; }
                this.pkActive = (this.pkActive + step + n) % n;
                this.$nextTick(() => {
                    const el = this.$refs.pkList && this.$refs.pkList.querySelectorAll('li')[this.pkActive];
                    if (el && el.scrollIntoView) { el.scrollIntoView({ block: 'nearest' }); }
                });
            },

            pkEnter() {
                if (!this.pkOpen) { this.pkOpenList(); return; }
                const s = this.pkResults[this.pkActive];
                if (s) { this.pkToggle(s); }
            },

            pkBackspace(event) {
                if (this.pkQuery === '' && this.assign.students.length) {
                    event.preventDefault();
                    this.assign.students = this.assign.students.slice(0, -1);
                }
            },

            pkInitial(s) {
                const parts = String(s.name || '?').trim().split(/\s+/);
                return (parts[parts.length - 1] || '?').charAt(0).toUpperCase();
            },

            pkSub(s) { return [s.email, s.phone].filter(Boolean).join(' · ') || 'Chưa có email / số điện thoại'; },
        };
    };
</script>
