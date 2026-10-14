{{-- SỬA 14/10 (khách: "chỗ tạo tài liệu + cập nhật, chọn khối lớp cho chọn nhiều") — ô "Khối lớp" của màn Tạo và Sửa tài
     liệu: trước là 1 ô chọn duy nhất, giờ là các ô tick (cùng khuôn partials/course-grade-field của khoá học).

     Bên gọi truyền $grades (danh sách khối) và $product (null khi tạo). Lưu xuống vẫn là 1 cột products.grade dạng
     "Lớp 6, Lớp 7" — xem ProductService (CourseService::gradeValue()) và migration 001200. --}}
@php
    $product = $product ?? null;
    $grades = $grades ?? [];
    // Đang chọn: ưu tiên dữ liệu vừa nhập bị lỗi (old) — kể cả khi bỏ tick hết —, rồi tới dữ liệu đã lưu.
    $selectedGrades = old('_token') !== null
        ? (array) old('grades', [])
        : ($product?->gradeList() ?? []);
@endphp

<div>
    <span class="apx-lbl">Khối lớp</span>
    <div class="rounded-xl border border-sky-100 bg-[#F8FBFE] p-2.5">
        <div class="flex flex-wrap gap-2">
            @foreach ($grades as $g)
                <label class="inline-flex items-center gap-2 rounded-xl border border-sky-100 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:border-blue-200 hover:text-blue-600 cursor-pointer">
                    <input type="checkbox" name="grades[]" value="{{ $g }}" @checked(in_array($g, $selectedGrades, true))>
                    {{ $g }}
                </label>
            @endforeach
        </div>
        <p class="mt-2 text-[10px] leading-relaxed text-slate-400">Chọn được nhiều khối. Không tick khối nào = không chỉ định khối.</p>
    </div>
    @error('grades')<p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>@enderror
    @error('grades.*')<p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>@enderror
</div>
