{{--
    SỬA 1/10 (khách: "thêm 1 cái field nữa cho chọn tỉnh thành và năm nha") — 2 ô phân loại mới,
    dùng CHUNG cho cả 3 form câu hỏi (admin Tạo, admin Sửa, giáo viên).

    Danh mục ở App\Support\ProvinceCatalog: 34 đơn vị hiện hành (sau sáp nhập 2025) + 29 tên cũ
    đã sáp nhập, chia thành 3 <optgroup>. Giữ tên cũ là CỐ Ý: kho này đầy đề thi các năm trước
    mang tên tỉnh đã sáp nhập ("Đề HSG Tin học Hải Dương 2019"), bỏ đi là không khai nổi nguồn đề.

    Cả 2 ô để trống được -> cột NULL, nằm nhóm "Chưa gán" của bộ lọc.

    Biến truyền vào: $province (mã đang chọn), $examYear (năm đang chọn).
--}}
@php
    $pyProvince = (string) old('province', (string) ($province ?? ''));
    $pyYear = (string) old('exam_year', (string) ($examYear ?? ''));
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="province">Tỉnh thành</label>
        <x-ws.select id="province" name="province">
            <option value="">— Chưa gán —</option>
            @foreach (\App\Support\ProvinceCatalog::groups() as $groupLabel => $options)
                <optgroup label="{{ $groupLabel }}">
                    @foreach ($options as $code => $label)
                        <option value="{{ $code }}" @selected($pyProvince === $code)>{{ $label }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </x-ws.select>
    </div>
    <div>
        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="exam_year">Năm</label>
        <x-ws.select id="exam_year" name="exam_year">
            <option value="">— Chưa gán —</option>
            @foreach (\App\Support\ProvinceCatalog::years() as $year)
                <option value="{{ $year }}" @selected($pyYear === (string) $year)>{{ $year }}</option>
            @endforeach
        </x-ws.select>
    </div>
</div>
