{{-- Bốn ô mô tả lớp in ra THẺ LỚP ngoài trang công khai, dùng chung cho màn Thêm và Sửa lớp.

     SỬA 16/9 — bản thiết kế thẻ lớp (education-main-12/.../CoursesPage.jsx) in rõ hình thức
     học, nơi học, địa chỉ và sĩ số "x/y". Bảng class_rooms trước đây không có chỗ nào lưu
     những thứ đó, nên thẻ phải bỏ trống đúng mấy dòng mà bản thiết kế nhấn mạnh nhất.

     Bỏ trống ô nào thì thẻ tự giấu dòng tương ứng, không in ô rỗng.
     Bên gọi truyền $classRoom (null khi thêm mới). --}}
@php
    $classRoom = $classRoom ?? null;
@endphp

@unless (\App\Models\ClassRoom::supportsDisplayFields())
    {{-- Chưa chạy `php artisan migrate`: 4 ô này chưa lưu được. Nói thẳng lý do và cách sửa,
         thay vì để quản trị nhập xong bấm lưu rồi thấy không có gì thay đổi. --}}
    <div class="flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5">
        <x-lucide name="alert-triangle" class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
        <p class="text-[11.5px] leading-relaxed text-amber-800">
            Máy chủ chưa cập nhật cơ sở dữ liệu nên chưa lưu được nhóm "Thông tin hiển thị".
            Chạy <code class="rounded bg-white px-1 py-0.5 font-mono text-[11px]">php artisan migrate</code> rồi tải lại trang.
            Các ô khác vẫn lưu bình thường.
        </p>
    </div>
@endunless

<div class="rounded-2xl border border-sky-100 bg-[#F8FBFE] p-3.5">
    <div class="mb-3 flex items-start gap-2.5">
        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl border border-sky-100 bg-white text-blue-600">
            <x-lucide name="eye" class="h-4 w-4" />
        </span>
        <div class="min-w-0">
            <p class="text-[13px] font-bold text-slate-800">Thông tin hiển thị trên thẻ lớp</p>
            <p class="mt-0.5 text-[11px] leading-relaxed text-slate-500">
                Bốn ô này in thẳng lên thẻ lớp ở trang Lớp học công khai. Bỏ trống ô nào thì thẻ tự giấu dòng đó.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
            <label class="mb-1 block text-[11px] font-bold text-slate-600" for="location">Nơi học</label>
            <input id="location" name="location" type="text" maxlength="60" class="admin-input"
                   value="{{ old('location', $classRoom->location ?? '') }}" placeholder="Trực tuyến"
                   @disabled(! \App\Models\ClassRoom::supportsDisplayFields())>
            <p class="mt-1 text-[10px] text-slate-400">Ví dụ: Trực tuyến, Tại trung tâm, Kết hợp.</p>
            @error('location')<p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="mb-1 block text-[11px] font-bold text-slate-600" for="format">Hình thức học</label>
            <input id="format" name="format" type="text" maxlength="60" class="admin-input"
                   value="{{ old('format', $classRoom->format ?? '') }}" placeholder="Live + ghi hình"
                   @disabled(! \App\Models\ClassRoom::supportsDisplayFields())>
            <p class="mt-1 text-[10px] text-slate-400">Ví dụ: Live, Live + ghi hình, Ghi hình.</p>
            @error('format')<p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>@enderror
        </div>

        <div class="sm:col-span-2">
            <label class="mb-1 block text-[11px] font-bold text-slate-600" for="address">Địa chỉ / phòng học</label>
            <input id="address" name="address" type="text" maxlength="160" class="admin-input"
                   value="{{ old('address', $classRoom->address ?? '') }}" placeholder="Phòng HSG 360 · Thái Bình"
                   @disabled(! \App\Models\ClassRoom::supportsDisplayFields())>
            <p class="mt-1 text-[10px] text-slate-400">Lớp học trực tuyến thường bỏ trống ô này.</p>
            @error('address')<p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="mb-1 block text-[11px] font-bold text-slate-600" for="capacity">Sĩ số tối đa</label>
            <input id="capacity" name="capacity" type="number" min="1" max="9999" class="admin-input"
                   value="{{ old('capacity', $classRoom->capacity ?? '') }}" placeholder="40"
                   @disabled(! \App\Models\ClassRoom::supportsDisplayFields())>
            <p class="mt-1 text-[10px] leading-relaxed text-slate-400">
                Thẻ in dạng <strong class="font-semibold text-slate-500">"12 / 40 học sinh"</strong>.
                Sĩ số thực tế luôn đếm từ danh sách ghi danh, không nhập tay.
            </p>
            @error('capacity')<p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>@enderror
        </div>
    </div>
</div>
