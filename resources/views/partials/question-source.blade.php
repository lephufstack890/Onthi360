{{-- SỬA 10/10 (khách: "thêm 1 field Nguồn cho nhập vào") — ô Nguồn của câu hỏi, dùng CHUNG cho 3 form
     (admin Tạo, admin Sửa, giáo viên). Ghi vào cột questions.source_name; để trống = trang Luyện tập
     hiện nhãn mặc định. Biến vào: $source (giá trị đang lưu, null khi tạo mới). --}}
<div>
    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="source_name">Nguồn</label>
    <input id="source_name" name="source_name" type="text" maxlength="255" autocomplete="off"
           value="{{ old('source_name', $source ?? '') }}"
           placeholder="Ví dụ: Đề thi HSG tỉnh Nghệ An 2024, Sách Chuyên đề Quy hoạch động…"
           class="w-full min-h-11 rounded-2xl border border-sky-100 bg-white px-3.5 py-2.5 text-xs font-medium text-slate-700 outline-none transition hover:border-sky-200 focus:border-blue-300 focus:ring-2 focus:ring-blue-200">
    <p class="mt-1 text-xs text-slate-400">Không bắt buộc. Hiện ở dòng “Nguồn:” của bài trên trang Luyện tập.</p>
</div>
