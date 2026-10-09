{{--
    SỬA 9/10 (khách: "các câu phải sắp xếp theo thứ tự in đúng file đề PDF để người ta biết câu nào
    mà làm") — THỨ TỰ CÂU TRONG ĐỀ.

    Lỗi cũ: form chỉ có các ô tick trong kho câu hỏi; trình duyệt gửi các ô tick THEO THỨ TỰ TRÊN
    MÀN HÌNH của kho (câu mới nhất lên đầu), không phải thứ tự người ra đề tick, và cũng không có
    chỗ nào để sắp lại. Hậu quả: "Câu 1, Câu 2…" ở màn làm bài lệch với thứ tự các bài trong file
    đề PDF, học sinh không biết câu nào ứng với bài nào.

    Giờ: tick câu nào thì câu đó nối vào CUỐI danh sách "Thứ tự câu trong đề" ở đây, và có nút ▲ ▼
    để dời lên/xuống cho khớp file PDF. Các ô tick KHÔNG còn tên (name) — thứ tự gửi lên máy chủ
    là thứ tự của danh sách này, qua các input ẩn question_ids[] bên dưới.

    Dùng chung cho form Tạo đề (giáo viên) và form Chọn câu cho đề (admin). Phải nằm TRONG thẻ
    <form> có x-data="assessmentPicker(…)".
--}}
<style>
    [x-cloak] { display: none !important; }
    .qo-box { margin-bottom: 14px; border: 1px solid #DDEAF0; border-radius: 16px; background: #F4F9FB; padding: 12px 14px; }
    .qo-title { margin: 0; font-size: 13px; font-weight: 700; color: #123B68; }
    .qo-hint { margin: 2px 0 10px; font-size: 12px; line-height: 1.5; color: #607A90; }
    .qo-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }
    .qo-row { display: flex; align-items: center; gap: 10px; border: 1px solid #DDEAF0; border-radius: 12px; background: #fff; padding: 7px 10px; }
    .qo-no { display: grid; place-items: center; flex: none; width: 26px; height: 26px; border-radius: 999px; background: #EAF5F8; color: #126F91; font-size: 11px; font-weight: 800; }
    .qo-name { min-width: 0; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 13px; color: #334E68; }
    .qo-pts { flex: none; font-size: 11px; font-weight: 700; color: #607A90; }
    .qo-btn { display: grid; place-items: center; flex: none; width: 28px; height: 28px; border: 1px solid #DDEAF0; border-radius: 8px; background: #fff; color: #45657D; font-size: 12px; line-height: 1; cursor: pointer; }
    .qo-btn:hover:not(:disabled) { border-color: #126F91; color: #126F91; background: #EAF5F8; }
    .qo-btn:disabled { opacity: .35; cursor: not-allowed; }
</style>

<template x-for="id in order" :key="'h' + id">
    <input type="hidden" name="question_ids[]" :value="id">
</template>

<div class="qo-box" x-show="order.length" x-cloak>
    <p class="qo-title">Thứ tự câu trong đề</p>
    <p class="qo-hint">Xếp đúng thứ tự các bài trong <strong>file đề PDF</strong> — học sinh sẽ thấy "Câu 1, Câu 2…" theo đúng thứ tự này. Tick thêm câu thì câu đó nối vào cuối; bấm ▲ ▼ để dời.</p>
    <ol class="qo-list">
        <template x-for="(id, i) in order" :key="'r' + id">
            <li class="qo-row">
                <span class="qo-no" x-text="i + 1"></span>
                <span class="qo-name" x-text="meta[id] ? meta[id].title : ('Câu #' + id)"></span>
                <span class="qo-pts" x-text="(meta[id] ? meta[id].points : 0) + ' đ'"></span>
                <button type="button" class="qo-btn" title="Dời lên" aria-label="Dời lên" :disabled="i === 0" @click="move(i, -1)">▲</button>
                <button type="button" class="qo-btn" title="Dời xuống" aria-label="Dời xuống" :disabled="i === order.length - 1" @click="move(i, 1)">▼</button>
                <button type="button" class="qo-btn" title="Bỏ khỏi đề" aria-label="Bỏ khỏi đề" @click="toggle(id, false)">✕</button>
            </li>
        </template>
    </ol>
</div>

<script>
    window.assessmentPicker = function (initial, meta) {
        meta = meta || {};
        var seen = {};
        var start = (initial || []).map(Number).filter(function (id) {
            if (!meta[id] || seen[id]) return false;
            seen[id] = true;
            return true;
        });

        return {
            meta: meta,
            order: start,
            // SỬA 10/10 — ô tìm theo tên/mã (xem assessment-question-search). Chỉ lọc HIỂN THỊ, không đụng tới order.
            query: '',
            onlySelected: false,
            norm: function (s) {
                return String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'D').toLowerCase().replace(/\s+/g, ' ').trim();
            },
            matches: function (id) {
                id = Number(id);
                if (this.onlySelected && this.order.indexOf(id) === -1) return false;
                var q = this.norm(this.query);
                if (!q) return true;
                var hay = this.meta[id] ? String(this.meta[id].search || '') : '';
                return q.split(' ').every(function (w) { return hay.indexOf(w) !== -1; });
            },
            get shown() {
                var self = this;
                return Object.keys(this.meta).filter(function (id) { return self.matches(id); }).length;
            },
            has: function (id) { return this.order.indexOf(Number(id)) !== -1; },
            toggle: function (id, on) {
                id = Number(id);
                var at = this.order.indexOf(id);
                if (on && at === -1) this.order.push(id);
                else if (!on && at !== -1) this.order.splice(at, 1);
            },
            move: function (from, step) {
                var to = from + step;
                if (to < 0 || to >= this.order.length) return;
                var item = this.order.splice(from, 1)[0];
                this.order.splice(to, 0, item);
            },
            get count() { return this.order.length; },
            get total() {
                var self = this;
                return this.order.reduce(function (sum, id) { return sum + (self.meta[id] ? Number(self.meta[id].points) || 0 : 0); }, 0);
            }
        };
    };
</script>
