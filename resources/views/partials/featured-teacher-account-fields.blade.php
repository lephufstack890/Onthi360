{{--
    SỬA 4/10 (khách: "thêm cả thông tin email sđt mật khẩu các thứ nữa nha giống thêm người dùng
    luôn… Vai trò thêm ở đây mặc định là giáo viên" + "tỉnh thành, khu vực nữa nhé") — phần TÀI
    KHOẢN của form thêm mới ở admin.featured-teachers.index.

    CHỈ dùng cho form THÊM MỚI. Form sửa không có khối này: đổi email/mật khẩu của một tài khoản
    đã tồn tại là việc của màn Người dùng, không phải màn vinh danh — gom vào đây thì cùng một
    thao tác lại có hai nơi làm được, hai nơi kiểm khác nhau.

    Vai trò để CỐ ĐỊNH là Giáo viên, không bày ô chọn: đây là màn vinh danh giáo viên, lỡ tay
    tạo ra một tài khoản admin từ đây thì không ai ngờ tới. Cần vai trò khác thì dùng màn
    Người dùng.
--}}
@php
    use App\Support\VietnamProvinces;

    // Chỉ lấy lại giá trị vừa gõ khi CHÍNH form này vừa gửi hỏng — xem ghi chú ở
    // partials/featured-teacher-fields về chuyện old() dùng chung cho cả trang.
    $ftaWasThisForm = (int) old('profile_id') === 0 && old('email') !== null;
    $ftaOld = fn (string $key) => $ftaWasThisForm ? old($key) : null;
@endphp

<div class="mb-4 rounded-xl border border-slate-200 bg-white p-3">
    <p class="mb-3 flex items-center gap-2 text-[13px] font-bold text-slate-700">
        <x-lucide name="user-cog" class="h-4 w-4 text-blue-600" />Tài khoản đăng nhập
        <span class="rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-bold text-[#0B3C78]">Vai trò: Giáo viên</span>
    </p>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
            <label class="mb-1 block text-[13px] font-medium text-slate-600" for="ft-email">Email <span class="text-rose-500">*</span></label>
            <input id="ft-email" name="email" type="email" maxlength="255" required class="admin-input"
                   value="{{ $ftaOld('email') }}" placeholder="VD: thaygiao@onthi360.vn">
        </div>

        <div>
            <label class="mb-1 block text-[13px] font-medium text-slate-600" for="ft-phone">Số điện thoại</label>
            <input id="ft-phone" name="phone" type="tel" maxlength="30" class="admin-input"
                   value="{{ $ftaOld('phone') }}" placeholder="VD: 0912345678">
        </div>

        {{-- Hai ô mật khẩu KHÔNG lấy lại giá trị cũ, kể cả khi form vừa gửi hỏng: không đổ mật
             khẩu ra lại HTML. Nhập lại hai ô là việc nhỏ, còn một trang có sẵn mật khẩu trong
             mã nguồn thì ai mở xem nguồn cũng đọc được. --}}
        <div>
            <label class="mb-1 block text-[13px] font-medium text-slate-600" for="ft-password">Mật khẩu <span class="text-rose-500">*</span></label>
            <input id="ft-password" name="password" type="password" minlength="8" required autocomplete="new-password" class="admin-input">
            <p class="mt-1 text-[11px] text-slate-400">Từ 8 ký tự trở lên.</p>
        </div>

        <div>
            <label class="mb-1 block text-[13px] font-medium text-slate-600" for="ft-password2">Nhập lại mật khẩu <span class="text-rose-500">*</span></label>
            <input id="ft-password2" name="password_confirmation" type="password" minlength="8" required autocomplete="new-password" class="admin-input">
        </div>

        <div>
            <label class="mb-1 block text-[13px] font-medium text-slate-600" for="ft-province">Tỉnh/thành</label>
            <x-ws.select id="ft-province" name="province">
                <option value="">— Không chọn —</option>
                @foreach (VietnamProvinces::options() as $provinceName)
                    <option value="{{ $provinceName }}" @selected($ftaOld('province') === $provinceName)>{{ $provinceName }}</option>
                @endforeach
            </x-ws.select>
        </div>

        <div>
            <label class="mb-1 block text-[13px] font-medium text-slate-600" for="ft-region">Khu vực</label>
            <x-ws.select id="ft-region" name="region">
                <option value="">— Không chọn —</option>
                @foreach (VietnamProvinces::regionOptions() as $regionValue => $regionLabel)
                    <option value="{{ $regionValue }}" @selected($ftaOld('region') === $regionValue)>{{ $regionLabel }}</option>
                @endforeach
            </x-ws.select>
        </div>
    </div>
</div>
