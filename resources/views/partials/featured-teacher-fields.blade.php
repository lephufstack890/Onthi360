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

    /*
     * BA TRẠNG THÁI, mỗi trạng thái một câu gợi ý khác nhau — viết chung một câu cho cả ba thì
     * câu đó sai ở hai chỗ:
     *   · $ftIsNew      : form THÊM MỚI, đang tạo cả tài khoản lẫn hồ sơ. Họ tên bắt buộc và
     *                     chính là tên tài khoản.
     *   · $ftHasAccount : đang sửa hồ sơ của một tài khoản có thật. Để trống tên thì rơi về
     *                     tên tài khoản.
     *   · còn lại       : hồ sơ trưng bày cũ, không gắn tài khoản nào.
     */
    $ftIsNew = $nameRequired ?? false;
    // Form THÊM MỚI đã có 2 ô này trong khối tài khoản ở trên -> không bày lại lần hai.
    $ftShowLocation = ! ($nameRequired ?? false) && ($t['hasAccount'] ?? true);
    $ftAvatar = $t['avatarPath'] ?? null;        // ảnh đang hiện (của trang này, hoặc của tài khoản)
    $ftOwnAvatar = $t['ownAvatar'] ?? null;      // ảnh riêng của trang này — chỉ ảnh này mới gỡ được
    $ftNameRequired = $ftIsNew;
    $ftHasAccount = $t['hasAccount'] ?? true;
@endphp

<input type="hidden" name="profile_id" value="{{ $t['profile_id'] }}">

<div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
    <div>
        <label class="mb-1 block text-[13px] font-medium text-slate-600" for="dn-{{ $t['profile_id'] }}">
            Họ tên hiển thị @if ($ftNameRequired)<span class="text-rose-500">*</span>@endif
        </label>
        <input id="dn-{{ $t['profile_id'] }}" name="display_name" type="text" maxlength="120" class="admin-input"
               value="{{ $ftOld('display_name', $t['displayName']) }}"
               placeholder="{{ ! $ftIsNew && $ftHasAccount ? $t['accountName'] : 'VD: TS. Nguyễn Văn An' }}"
               @required($ftNameRequired)>
        @if ($ftIsNew)
            <p class="mt-1 text-[11px] text-slate-400">Dùng cho cả tên tài khoản lẫn tên hiển thị trên trang vinh danh.</p>
        @elseif ($ftHasAccount)
            <p class="mt-1 text-[11px] text-slate-400">Để trống thì lấy tên tài khoản: <b>{{ $t['accountName'] }}</b>. Ô này không đổi tên đăng nhập của giáo viên.</p>
        @else
            <p class="mt-1 text-[11px] text-slate-400">Hồ sơ này không gắn tài khoản nào nên bắt buộc nhập tên.</p>
        @endif
    </div>

    <div>
        <label class="mb-1 block text-[13px] font-medium text-slate-600" for="wp-{{ $t['profile_id'] }}">Đơn vị công tác</label>
        <input id="wp-{{ $t['profile_id'] }}" name="workplace" type="text" maxlength="160" class="admin-input"
               value="{{ $ftOld('workplace', $t['workplace']) }}" placeholder="VD: THPT Chuyên Trần Phú, Hải Phòng">
    </div>

    <div>
        <label class="mb-1 block text-[13px] font-medium text-slate-600" for="rt-{{ $t['profile_id'] }}">Vai trò hiển thị</label>
        <input id="rt-{{ $t['profile_id'] }}" name="role_title" type="text" maxlength="120" class="admin-input"
               value="{{ $ftOld('role_title', $t['roleTitle']) }}" placeholder="VD: Giáo viên Tin học · Tổ trưởng chuyên môn">
        @if ($ftIsNew)
            <p class="mt-1 text-[11px] text-slate-400">Dòng chữ hiện dưới tên ngoài trang công khai. Khác với vai trò tài khoản (luôn là Giáo viên).</p>
        @elseif ($ftHasAccount)
            <p class="mt-1 text-[11px] text-slate-400">Để trống thì trang công khai tự ghép "Giáo viên {{ $t['subject'] ?: '…' }}" từ môn đã duyệt.</p>
        @else
            <p class="mt-1 text-[11px] text-slate-400">VD: Chuyên gia Tin học · Giảng viên mời.</p>
        @endif
    </div>

    <div>
        <label class="mb-1 block text-[13px] font-medium text-slate-600" for="dr-{{ $t['profile_id'] }}">Số sao xếp hạng</label>
        <input id="dr-{{ $t['profile_id'] }}" name="display_rating" type="number" step="0.1" min="0" max="5" class="admin-input"
               value="{{ $ftOld('display_rating', $t['displayRating']) }}" placeholder="VD: 4.8">
        {{-- Nói thẳng số này từ đâu ra: trang công khai đang ghi "đánh giá đã xác thực" cho con
             số tính từ review đã kiểm duyệt. Số gõ tay ở đây hiện ra với nhãn khác hẳn. --}}
        @if ($ftIsNew || $ftHasAccount)
            <p class="mt-1 text-[11px] text-slate-400">Để trống thì trang công khai dùng điểm trung bình thật từ đánh giá đã kiểm duyệt.</p>
        @else
            <p class="mt-1 text-[11px] text-slate-400">Hồ sơ không gắn tài khoản thì không có đánh giá thật — để trống thì ô sao ngoài trang hiện dấu "—".</p>
        @endif
    </div>

    {{-- SỬA 4/10 (khách: "chỗ sửa chưa có sửa được tỉnh/thành và khu vực bổ sung giúp tôi luôn
         nha") — 2 cột này nằm ở bảng users nên chỉ bày khi hồ sơ CÓ gắn tài khoản.

         Vẫn CỐ Ý không đưa email và mật khẩu vào form sửa: đó là thông tin đăng nhập, đổi chúng
         là việc của màn Người dùng. Gom vào đây thì cùng một thao tác có hai nơi làm được, hai
         nơi kiểm khác nhau — tỉnh/thành và khu vực thì không phải thông tin đăng nhập. --}}
    @if ($ftShowLocation)
        @include('partials.featured-teacher-location-fields', [
            'locPrefix' => 'loc-'.$t['profile_id'],
            'locProvince' => $ftOld('province', $t['province'] ?? null),
            'locRegion' => $ftOld('region', $t['region'] ?? null),
        ])
    @endif

    {{-- SỬA 4/10 (khách: "thêm cho tôi 1 field avatar nữa nha cả thêm và cập nhật nha") — ô này
         nằm trong partial DÙNG CHUNG nên có mặt ở cả 3 form: thêm mới, thêm từ tài khoản có sẵn,
         và sửa. Thẻ <form> bọc ngoài BẮT BUỘC có enctype="multipart/form-data", nếu không trình
         duyệt chỉ gửi TÊN tệp chứ không gửi tệp, và việc tải ảnh hỏng lặng lẽ. --}}
    {{-- SỬA 4/10 (khách: "thêm thứ tự hiển thị nữa để điều chỉnh ai hiển thị trước cả thêm và
         sửa nha", rồi chốt lại: "số lớn đứng trước số nhỏ đứng sau nha mặc định là 0 nha")
         — SỐ LỚN ĐỨNG TRƯỚC, mặc định 0.

         Nói rõ giới hạn của nó ngay dưới ô, đừng để khách tự phát hiện: số này xếp TRONG TỪNG
         NHÓM chuyên gia / giáo viên thường, không vượt qua được luật "chuyên gia luôn đứng đầu"
         mà chính khách đã chốt sáng nay. --}}
    <div>
        <label class="mb-1 block text-[13px] font-medium text-slate-600" for="so-{{ $t['profile_id'] }}">Thứ tự hiển thị</label>
        <input id="so-{{ $t['profile_id'] }}" name="sort_order" type="number" min="0" max="9999" step="1" class="admin-input"
               value="{{ $ftOld('sort_order', $t['sortOrder'] ?? 0) }}" placeholder="0">
        <p class="mt-1 text-[11px] text-slate-400">
            Số càng lớn càng đứng trước, để trống là 0 (xuống cuối). Chuyên gia vẫn luôn xếp trên giáo viên thường.
        </p>
    </div>

    <div>
        <label class="mb-1 block text-[13px] font-medium text-slate-600" for="av-{{ $t['profile_id'] }}">Ảnh đại diện</label>
        <div class="flex flex-wrap items-start gap-3">
            @if ($ftAvatar)
                <img src="{{ asset('storage/'.$ftAvatar) }}" alt="Ảnh đại diện hiện tại"
                     class="h-16 w-16 shrink-0 rounded-xl border border-sky-100 object-cover">
            @else
                <span class="grid h-16 w-16 shrink-0 place-items-center rounded-xl border border-dashed border-sky-200 bg-sky-50 text-[#23869B]">
                    <x-lucide name="user-cog" class="h-6 w-6" />
                </span>
            @endif

            <div class="min-w-0 flex-1">
                <input id="av-{{ $t['profile_id'] }}" name="avatar" type="file" accept="image/jpeg,image/png,image/webp"
                       class="admin-input file:mr-3 file:rounded-xl file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-blue-700">
                <p class="mt-1 text-[11px] text-slate-400">
                    Ảnh vuông cho đẹp (VD 512x512). JPG/PNG/WebP, tối đa {{ \App\Support\UploadLimit::label(4096) }}.
                    @unless ($ftIsNew) Không chọn tệp mới thì giữ nguyên ảnh cũ. @endunless
                </p>

                {{-- Chỉ gỡ được ảnh do trang này tải lên. Ảnh người dùng tự đặt cho tài khoản của
                     họ thì màn vinh danh không có quyền xoá — muốn thay thì tải ảnh riêng đè lên. --}}
                @if ($ftOwnAvatar)
                    <label class="mt-2 flex items-center gap-2 text-[11px] font-medium text-slate-600">
                        <input type="checkbox" name="remove_avatar" value="1" class="h-4 w-4 rounded border-sky-200 text-rose-600">
                        Xoá ảnh hiện tại (quay về ảnh mặc định)
                    </label>
                @endif
            </div>
        </div>
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
