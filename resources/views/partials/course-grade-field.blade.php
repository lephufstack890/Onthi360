{{-- SỬA 30/9 (khách: "chỗ chọn khối và lớp thì cho chọn nhiều") — ô "Khối lớp" của màn Thêm và
     Sửa khoá học: trước đây là 1 ô chọn duy nhất nên khoá dạy chung cho Lớp 6 + Lớp 7 phải bỏ
     trống hoặc chọn bừa 1 khối. Giờ là các ô tick, chọn bao nhiêu khối cũng được.

     Dùng chung cho cả 2 màn; bên gọi truyền $grades (danh sách khối) và $course (null khi thêm).
     Lưu xuống vẫn là 1 cột courses.grade dạng "Lớp 6, Lớp 7" — xem CourseService::gradeValue()
     và migration add_session_range_and_multi_grade_to_courses_table. --}}
@php
    $course = $course ?? null;
    $grades = $grades ?? [];
    // Giá trị đang chọn: ưu tiên dữ liệu vừa nhập hỏng (old), rồi tới dữ liệu đã lưu của khoá.
    $selectedGrades = old('grades', $course?->gradeList() ?? []);
    $selectedGrades = is_array($selectedGrades) ? $selectedGrades : [];
@endphp

<div>
    <label class="block text-[13px] font-medium text-slate-600 mb-1">Khối lớp</label>
    <div class="rounded-xl border border-sky-100 bg-[#F8FBFE] p-2.5">
        <div class="flex flex-wrap gap-2">
            @foreach ($grades as $g)
                <label class="inline-flex items-center gap-2 rounded-xl border border-sky-100 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:border-blue-200 hover:text-blue-600 cursor-pointer">
                    <input type="checkbox" name="grades[]" value="{{ $g }}" @checked(in_array($g, $selectedGrades, true))>
                    {{ $g }}
                </label>
            @endforeach
        </div>
        <p class="mt-2 text-[10px] leading-relaxed text-slate-400">
            Chọn được nhiều khối. Không tick khối nào = khoá không chỉ định khối (hiện "Mọi khối" ngoài trang công khai).
        </p>
    </div>
    @error('grades')<p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>@enderror
    @error('grades.*')<p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>@enderror
</div>
