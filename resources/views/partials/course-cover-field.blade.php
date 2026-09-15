{{-- Ô tải ảnh đại diện (thumbnail) của khoá học, dùng chung cho màn Thêm và Sửa.

     SỬA 15/9 (khách: "trong thêm và sửa khoá học thêm cho tôi field thumbnail") — trước đây
     bảng courses đã CÓ SẴN cột cover_image_path và cả hai trang công khai đều đã biết hiển thị
     ảnh, chỉ thiếu đúng một chỗ: không có ô nào trong trang quản trị để tải ảnh lên.

     LƯU Ý cho ai sửa sau: form ở create/edit PHẢI có enctype="multipart/form-data", thiếu nó
     thì trình duyệt gửi lên mỗi cái tên tệp, không gửi nội dung — bấm lưu xong không báo lỗi
     gì mà ảnh vẫn không có. Tách partial để hai màn không lệch nhau.

     Bên gọi truyền $course (null khi thêm mới). --}}
@php
    $course = $course ?? null;
    $currentCoverUrl = $course?->coverUrl();
@endphp

<div>
    <label class="mb-1 block text-[13px] font-medium text-slate-600" for="cover">Ảnh đại diện (thumbnail)</label>

    <div class="flex flex-wrap items-start gap-3">
        @if ($currentCoverUrl)
            <img src="{{ $currentCoverUrl }}" alt="Ảnh đại diện hiện tại của khoá học"
                 class="h-24 w-40 shrink-0 rounded-xl border border-sky-100 object-cover">
        @else
            {{-- Chưa có ảnh riêng: vẽ ô trống chứ KHÔNG hiện ảnh mặc định, để người nhập phân
                 biệt được "khoá này chưa có ảnh" với "khoá này đã có ảnh rồi". --}}
            <div class="flex h-24 w-40 shrink-0 flex-col items-center justify-center gap-1 rounded-xl border border-dashed border-sky-200 bg-sky-50/60 text-sky-300">
                <x-lucide name="image" class="h-6 w-6" />
                <span class="text-[10px] font-bold">Chưa có ảnh</span>
            </div>
        @endif

        <div class="min-w-[220px] flex-1">
            <input id="cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp"
                   class="admin-input file:mr-3 file:rounded-xl file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-blue-700">
            <p class="mt-1 text-xs leading-relaxed text-slate-400">
                Ảnh ngang, nên theo tỉ lệ 16:9. JPG/PNG/WebP, tối đa 4MB.
                Ảnh này hiện ở thẻ khoá học ngoài trang Lớp học và ở đầu trang chi tiết khoá học.
                <strong class="font-semibold text-slate-500">Bỏ trống thì hệ thống dùng ảnh mặc định.</strong>
            </p>

            @if ($currentCoverUrl)
                <label class="mt-2 flex w-fit items-center gap-2 text-[11px] font-semibold text-rose-600">
                    <input type="checkbox" name="remove_cover" value="1" class="h-3.5 w-3.5 rounded border-slate-300 text-rose-600">
                    Gỡ ảnh hiện tại (quay về ảnh mặc định)
                </label>
            @endif

            @error('cover')<p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>@enderror
        </div>
    </div>
</div>
