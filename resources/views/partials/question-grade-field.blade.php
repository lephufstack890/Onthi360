{{-- SỬA 3/10 (khách: "chỗ chọn khối lớp thì cho chọn nhiều nha") — ô "Khối lớp" của 3 form câu
     hỏi (admin Tạo, admin Sửa, giáo viên): trước đây là 1 ô chọn duy nhất nên một câu dùng được
     cho cả lớp 8 và lớp 9 phải chọn bừa một khối.

     Dựng đúng khuôn đã làm cho khoá học hôm 30/9 (partials/course-grade-field) — cùng một yêu
     cầu của khách thì dùng cùng một cách, đừng đẻ khuôn thứ hai. Lưu xuống vẫn là MỘT cột
     questions.grade dạng "6,7" — xem migration change_question_grade_to_multi.

     Bên gọi truyền $grades (danh sách khối 6-12) và $question (null khi đang tạo mới). --}}
@php
    $question = $question ?? null;
    $grades = $grades ?? [];

    // Giá trị đang chọn: ưu tiên dữ liệu vừa nhập hỏng (old), rồi tới dữ liệu đã lưu của câu.
    $qgSelected = old('grades', $question?->gradeList() ?? []);
    $qgSelected = array_map('strval', is_array($qgSelected) ? $qgSelected : []);
@endphp

<div>
    <label class="block text-[13px] font-medium text-slate-600 mb-1">Khối lớp</label>
    <div class="rounded-xl border border-sky-100 bg-[#F8FBFE] p-2.5">
        <div class="flex flex-wrap gap-2">
            @foreach ($grades as $g)
                <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-sky-100 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:border-blue-200 hover:text-blue-600">
                    <input type="checkbox" name="grades[]" value="{{ $g }}" @checked(in_array((string) $g, $qgSelected, true))>
                    Lớp {{ $g }}
                </label>
            @endforeach
        </div>
        <p class="mt-2 text-[10px] leading-relaxed text-slate-400">
            Chọn được nhiều khối. Không tick khối nào = câu hỏi không chỉ định khối.
        </p>
    </div>
    @error('grades')<p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>@enderror
    @error('grades.*')<p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>@enderror
</div>
