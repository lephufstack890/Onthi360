{{-- Alpine cho màn "Xếp bậc" của lộ trình (A9).

     Hai cách đổi thứ tự, cố ý làm cả hai:
       · KÉO THẢ — nhanh, dùng trên máy tính.
       · NÚT LÊN/XUỐNG — kéo thả HTML5 không chạy trên màn hình cảm ứng, và cũng không dùng
         được bằng bàn phím. Không có nút này thì một số người không xếp bậc được.

     Thứ tự chỉ đổi trong trình duyệt; bấm "Lưu thứ tự" mới gửi lên máy chủ bằng form thường
     (không gọi API ngầm) — dễ đọc, dễ sửa, và nếu JavaScript hỏng thì trang vẫn còn dùng được
     bằng nút xoá/thêm bậc. --}}
<script>
    function learningPathSteps(config) {
        return {
            steps: config.steps,
            palette: config.palette,
            dragFrom: null,
            dirty: false,

            /** Màu của bậc theo VỊ TRÍ hiện tại, quay vòng khi nhiều bậc hơn số nấc màu. */
            colorAt(index) {
                return this.palette[index % this.palette.length];
            },

            move(from, to) {
                if (to < 0 || to >= this.steps.length || from === to) return;
                const moved = this.steps.splice(from, 1)[0];
                this.steps.splice(to, 0, moved);
                this.dirty = true;
            },

            moveUp(index) { this.move(index, index - 1); },
            moveDown(index) { this.move(index, index + 1); },

            onDragStart(index) { this.dragFrom = index; },
            onDrop(index) {
                if (this.dragFrom === null) return;
                this.move(this.dragFrom, index);
                this.dragFrom = null;
            },

            get totalSessions() {
                return this.steps.reduce((sum, s) => sum + (Number(s.sessionCount) || 0), 0);
            },

            get totalWeeks() {
                const perWeek = Math.max(1, Number(config.sessionsPerWeek) || 1);
                return Math.ceil(this.totalSessions / perWeek);
            },

            get totalHours() {
                return Math.round(this.totalSessions * (Number(config.hoursPerSession) || 0) * 10) / 10;
            },
        };
    }
</script>
