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
    /* SỬA 11/10 — ô NHẬP điểm từng câu (số nguyên hoặc thập phân), dùng ở cả danh sách thứ tự lẫn từng dòng trong kho câu hỏi. */
    .qo-pin { display: inline-flex; align-items: center; gap: 4px; flex: none; font-size: 11px; font-weight: 700; color: #607A90; }
    .qo-in { width: 74px; height: 30px; box-sizing: border-box; border: 1px solid #BFD9E4; border-radius: 9px; background: #fff; padding: 0 8px; text-align: center; font-size: 13px; font-weight: 700; color: #123B68; -moz-appearance: textfield; }
    .qo-in::-webkit-outer-spin-button, .qo-in::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .qo-in:focus { outline: none; border-color: #126F91; box-shadow: 0 0 0 3px rgba(18,111,145,.15); }
    .qo-in.is-off { background: #F4F9FB; color: #9BB1C2; border-color: #DDEAF0; }
    .qo-btn { display: grid; place-items: center; flex: none; width: 28px; height: 28px; border: 1px solid #DDEAF0; border-radius: 8px; background: #fff; color: #45657D; font-size: 12px; line-height: 1; cursor: pointer; }
    .qo-btn:hover:not(:disabled) { border-color: #126F91; color: #126F91; background: #EAF5F8; }
    .qo-btn:disabled { opacity: .35; cursor: not-allowed; }
</style>

<template x-for="id in order" :key="'h' + id">
    <input type="hidden" name="question_ids[]" :value="id">
</template>

<div class="qo-box" x-show="order.length" x-cloak>
    <p class="qo-title">Thứ tự câu trong đề</p>
    <p class="qo-hint">Xếp đúng thứ tự các bài trong <strong>file đề PDF</strong> — học sinh sẽ thấy "Câu 1, Câu 2…" theo đúng thứ tự này. Tick thêm câu thì câu đó nối vào cuối; bấm ▲ ▼ để dời. <strong>Điểm từng câu do bạn nhập</strong> (số nguyên hoặc thập phân, vd 2 hoặc 1.5) — tổng điểm đề và điểm chấm bài đều tính theo số này.</p>
    <ol class="qo-list">
        <template x-for="(id, i) in order" :key="'r' + id">
            <li class="qo-row">
                <span class="qo-no" x-text="i + 1"></span>
                <span class="qo-name" x-text="meta[id] ? meta[id].title : ('Câu #' + id)"></span>
                <label class="qo-pin">
                    <input type="number" class="qo-in" inputmode="decimal" min="0" max="1000" step="any" required
                           :name="'points[' + id + ']'" x-model="pts[id]" aria-label="Điểm của câu này">
                    đ
                </label>
                <button type="button" class="qo-btn" title="Dời lên" aria-label="Dời lên" :disabled="i === 0" @click="move(i, -1)">▲</button>
                <button type="button" class="qo-btn" title="Dời xuống" aria-label="Dời xuống" :disabled="i === order.length - 1" @click="move(i, 1)">▼</button>
                <button type="button" class="qo-btn" title="Bỏ khỏi đề" aria-label="Bỏ khỏi đề" @click="toggle(id, false)">✕</button>
            </li>
        </template>
    </ol>
</div>

<script>
    // saved = điểm ĐÃ NHẬP/đã chốt trong đề, dạng { question_id: điểm } (rỗng nếu chưa có).
    // Câu chưa có điểm riêng thì ô nhập hiện số gợi ý meta[id].points để người ra đề sửa.
    window.assessmentPicker = function (initial, meta, saved) {
        meta = meta || {};
        saved = saved || {};
        var seen = {};
        var start = (initial || []).map(Number).filter(function (id) {
            if (!meta[id] || seen[id]) return false;
            seen[id] = true;
            return true;
        });

        var pts = {};
        Object.keys(meta).forEach(function (id) {
            var v = saved[id];
            pts[id] = (v !== undefined && v !== null && v !== '') ? String(v) : String(meta[id].points);
        });

        return {
            meta: meta,
            // SỬA 11/10 — điểm NGƯỜI DÙNG NHẬP cho từng câu (chuỗi, để gõ dở "1." vẫn không bị nuốt).
            pts: pts,
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
            // Tổng điểm đề = cộng điểm đã nhập của các câu đang chọn (làm tròn 2 chữ số, tránh 0.1+0.2).
            get total() {
                var self = this;
                var sum = this.order.reduce(function (acc, id) {
                    var n = parseFloat(String(self.pts[id]).replace(',', '.'));
                    return acc + (isFinite(n) && n > 0 ? n : 0);
                }, 0);
                return Math.round(sum * 100) / 100;
            }
        };
    };
</script>
