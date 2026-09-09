{{-- SỬA 9/9 (7) — bộ não của BỘ LỌC luyện tập theo câu, dùng CHUNG cho 2 màn:
     · student/practice/by-question-setup.blade.php (học sinh đã đăng nhập)
     · public/practice/index.blade.php              (trang công khai)
     Cần biến $practiceTypes / $practiceTotal (App\Support\PracticeFilters::options()).
     Đặt chung 1 chỗ để 2 màn không lệch logic lọc như trước. --}}
<script>
    function practiceSetup(tags, initialType, initialSelected) {
        return {
            tags: tags || [],
            type: initialType || '',
            selected: (initialSelected || []).map(Number),

            typeLabels: @js(collect($practiceTypes)->pluck('label', 'value')->all()),
            typeTotals: @js(collect($practiceTypes)->pluck('count', 'value')->all()),
            grandTotal: {{ (int) $practiceTotal }},

            // Số câu của 1 chuyên đề theo dạng đang chọn ('' = cộng tất cả các dạng).
            countFor(tag) {
                return this.type === '' ? tag.total : (tag.counts[this.type] || 0);
            },

            // Chỉ hiện chuyên đề THẬT SỰ có câu ở dạng đang chọn.
            get visibleTags() {
                return this.tags.filter((t) => this.countFor(t) > 0);
            },

            get typeLabel() {
                return this.typeLabels[this.type] || '';
            },

            // Ước lượng số câu sẽ luyện. Không chọn chuyên đề nào = toàn bộ câu của dạng đó.
            // Có chọn thì cộng theo chuyên đề — 1 câu gắn nhiều chuyên đề có thể bị đếm trùng,
            // nên đây là con số THAM KHẢO để người học khỏi chọn ra 0 câu; số câu thật do server
            // quyết định khi bắt đầu (QuestionRepository::idsForPractice()).
            get matchCount() {
                if (this.selected.length === 0) {
                    return this.type === '' ? this.grandTotal : (this.typeTotals[this.type] || 0);
                }

                return this.tags
                    .filter((t) => this.selected.includes(t.id))
                    .reduce((sum, t) => sum + this.countFor(t), 0);
            },

            setType(value) {
                this.type = value;
                // Bỏ tick những chuyên đề vừa bị ẩn: giữ lại sẽ gửi lên server 1 bộ lọc chắc
                // chắn ra 0 câu mà người học không nhìn thấy để sửa.
                this.selected = this.selected.filter((id) => {
                    const tag = this.tags.find((t) => t.id === id);
                    return tag && this.countFor(tag) > 0;
                });
            },

            toggle(id) {
                this.selected = this.selected.includes(id)
                    ? this.selected.filter((x) => x !== id)
                    : [...this.selected, id];
            },
        };
    }
</script>
