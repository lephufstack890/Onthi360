{{--
    SỬA 4/10 (khách liệt kê "các trường thông tin của giáo viên") — 6 ô nhập, DÙNG CHUNG cho cả
    form Thêm và form Sửa ở admin.featured-teachers.index. Một bản duy nhất để hai form không
    bao giờ lệch nhau (thêm trường mới mà quên sửa form kia là lỗi rất dễ sót).

    Biến truyền vào: $t (một dòng từ FeaturedTeacherService::row()), $submitLabel.
    Thẻ <form> và @csrf do nơi gọi lo — form Thêm và form Sửa khác method lẫn địa chỉ.
--}}
@php
    /*
     * old() DÙNG CHUNG CHO CẢ TRANG, mà mọi dòng ở đây đặt tên ô giống hệt nhau (display_name,
     * workplace…). Nếu cứ gọi old() thẳng thì một form nhập sai sẽ đổ giá trị của người đó vào
     * form của TẤT CẢ những người còn lại khi trang vẽ lại — người xem tưởng dữ liệu bị lẫn.
     *
     * Gửi kèm profile_id ẩn để biết lần gửi hỏng vừa rồi là của ai; chỉ ĐÚNG hồ sơ đó mới lấy
     * lại giá trị vừa gõ, các hồ sơ khác vẫn hiện dữ liệu đã lưu.
     */
    $ftWasThisRow = (int) old('profile_id') === (int) $t['profile_id'];
    $ftOld = fn (string $key, $stored) => $ftWasThisRow ? old($key, $stored) : $stored;
@endphp

<input type="hidden" name="profile_id" value="{{ $t['profile_id'] }}">

<div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
    <div>
        <label class="mb-1 block text-[13px] font-medium text-slate-600" for="dn-{{ $t['profile_id'] }}">Họ tên hiển thị</label>
        <input id="dn-{{ $t['profile_id'] }}" name="display_name" type="text" maxlength="120" class="admin-input"
               value="{{ $ftOld('display_name', $t['displayName']) }}" placeholder="{{ $t['accountName'] }}">
        <p class="mt-1 text-[11px] text-slate-400">Để trống thì lấy tên tài khoản: <b>{{ $t['accountName'] }}</b>. Ô này không đổi tên đăng nhập của giáo viên.</p>
    </div>

    <div>
        <label class="mb-1 block text-[13px] font-medium text-slate-600" for="wp-{{ $t['profile_id'] }}">Đơn vị công tác</label>
        <input id="wp-{{ $t['profile_id'] }}" name="workplace" type="text" maxlength="160" class="admin-input"
               value="{{ $ftOld('workplace', $t['workplace']) }}" placeholder="VD: THPT Chuyên Trần Phú, Hải Phòng">
    </div>

    <div>
        <label class="mb-1 block text-[13px] font-medium text-slate-600" for="rt-{{ $t['profile_id'] }}">Vai trò</label>
        <input id="rt-{{ $t['profile_id'] }}" name="role_title" type="text" maxlength="120" class="admin-input"
               value="{{ $ftOld('role_title', $t['roleTitle']) }}" placeholder="VD: Giáo viên Tin học · Tổ trưởng chuyên môn">
        <p class="mt-1 text-[11px] text-slate-400">Để trống thì trang công khai tự ghép "Giáo viên {{ $t['subject'] ?: '…' }}" từ môn đã duyệt.</p>
    </div>

    <div>
        <label class="mb-1 block text-[13px] font-medium text-slate-600" for="dr-{{ $t['profile_id'] }}">Số sao xếp hạng</label>
        <input id="dr-{{ $t['profile_id'] }}" name="display_rating" type="number" step="0.1" min="0" max="5" class="admin-input"
               value="{{ $ftOld('display_rating', $t['displayRating']) }}" placeholder="VD: 4.8">
        {{-- Nói thẳng số này từ đâu ra: trang công khai đang ghi "đánh giá đã xác thực" cho con
             số tính từ review đã kiểm duyệt. Số gõ tay ở đây hiện ra với nhãn khác hẳn. --}}
        <p class="mt-1 text-[11px] text-slate-400">Để trống thì trang công khai dùng điểm trung bình thật từ đánh giá đã kiểm duyệt.</p>
    </div>

    <div class="sm:col-span-2">
        <label class="mb-1 block text-[13px] font-medium text-slate-600" for="an-{{ $t['profile_id'] }}">Thành tích tiêu biểu</label>
        <textarea id="an-{{ $t['profile_id'] }}" name="achievement_note" rows="4" maxlength="2000" class="admin-input"
                  placeholder="Mỗi dòng một thành tích, VD:&#10;Bồi dưỡng 12 học sinh giỏi quốc gia&#10;Tác giả 3 đầu sách tham khảo">{{ $ftOld('achievement_note', $t['achievement']) }}</textarea>
        <p class="mt-1 text-[11px] text-slate-400">Mỗi dòng (hoặc mỗi dấu <code>;</code>) thành một gạch đầu dòng ngoài trang công khai.</p>
    </div>

    <div class="sm:col-span-2">
        <label class="flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-900">
            <input type="checkbox" name="is_expert" value="1" @checked($ftWasThisRow ? old('is_expert') : $t['expert'])
                   class="h-4 w-4 rounded border-amber-300 text-amber-600">
            <x-lucide name="badge-check" class="h-4 w-4" />
            Là chuyên gia — gắn huy hiệu nổi bật và luôn đứng đầu danh sách công khai
        </label>
    </div>
</div>

<div class="mt-3 flex gap-2">
    <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700">{{ $submitLabel }}</button>
    {{-- Dùng CHUNG một tên biến `open` cho cả form Thêm lẫn form Sửa. Nếu mỗi form một tên
         (editing / adding) thì nút Huỷ ở form này sẽ gọi tới biến không tồn tại bên form kia
         và Alpine ném ReferenceError, nút chết lặng. --}}
    <button type="button" @click="open = false" class="rounded-xl border border-sky-100 px-4 py-2 text-xs text-slate-500">Huỷ</button>
</div>
