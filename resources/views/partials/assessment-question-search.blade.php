{{-- SỬA 10/10 (khách: "tick từng câu hỏi lỡ nhiều câu hơi cực, thêm chỗ search theo tên và mã") — ô tìm
     trong danh sách chọn câu hỏi của đề. Dùng CHUNG cho form Chọn câu (admin) và Tạo đề (giáo viên).
     Phải nằm TRONG <form x-data="assessmentPicker(…)">. Ô này KHÔNG có name nên không bị gửi đi, và
     Enter không làm gửi form. Tìm không phân biệt hoa/thường và dấu, khớp theo tên HOẶC mã; gõ nhiều
     từ thì câu phải chứa đủ các từ. Câu đã tick vẫn giữ nguyên dù đang bị lọc ẩn. --}}
<style>
    .qs-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 10px; }
    .qs-field { position: relative; flex: 1 1 260px; min-width: 0; }
    .qs-field svg { position: absolute; left: 12px; top: 50%; width: 16px; height: 16px; margin-top: -8px; color: #94a3b8; pointer-events: none; }
    .qs-input { display: block; width: 100%; min-height: 40px; box-sizing: border-box; border: 1px solid #d6e3ef; border-radius: 12px; background: #f8fafb; padding: 9px 36px 9px 36px; font-size: 13px; color: #365b7a; -webkit-appearance: none; appearance: none; }
    .qs-input:focus { outline: 2px solid #9dc8d7; outline-offset: 1px; background-color: #fff; }
    .qs-clear { position: absolute; right: 6px; top: 50%; width: 28px; height: 28px; margin-top: -14px; border: 0; border-radius: 8px; background: transparent; color: #64748b; font-size: 14px; cursor: pointer; }
    .qs-clear:hover { background: #eaf5f8; color: #126f91; }
    .qs-only { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: #45657d; cursor: pointer; user-select: none; }
    .qs-count { font-size: 12px; color: #607a90; }
    .qs-empty { padding: 22px 8px; text-align: center; font-size: 13px; color: #607a90; }
</style>

<div class="qs-bar">
    <div class="qs-field">
        <x-lucide name="search" />
        <input type="search" class="qs-input" x-model="query" @keydown.enter.prevent @keydown.escape="query = ''"
               aria-label="Tìm câu hỏi theo tên hoặc mã" placeholder="Tìm theo tên hoặc mã câu hỏi… (có thể gõ không dấu)" autocomplete="off">
        <button type="button" class="qs-clear" x-show="query" x-cloak @click="query = ''" aria-label="Xóa tìm kiếm">✕</button>
    </div>
    <label class="qs-only"><input type="checkbox" x-model="onlySelected"> Chỉ câu đã chọn</label>
    <span class="qs-count" role="status"><span x-text="shown"></span> / <span x-text="Object.keys(meta).length"></span> câu</span>
</div>
