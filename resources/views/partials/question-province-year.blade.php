{{--
    SỬA 1/10 (khách: "thêm 1 cái field nữa cho chọn tỉnh thành và năm nha") — 2 ô phân loại,
    dùng CHUNG cho cả 3 form câu hỏi (admin Tạo, admin Sửa, giáo viên).

    SỬA 3/10 — rút ô Tỉnh thành xuống 2 lựa chọn.
    SỬA 4/10 (khách: "các tỉnh khác không phải option mà khách muốn chi tiết toàn bộ các tỉnh
    thành luôn như ban đầu, chỉ thêm option toàn quốc là ok") — TRẢ LẠI danh mục đầy đủ: 6 thành
    phố trực thuộc trung ương, 28 tỉnh hiện hành, và nhóm tên cũ trước sáp nhập 2025 (xem
    App\Support\ProvinceCatalog::groups()). Thêm đúng một mục "Toàn quốc" ở trên cùng.

    Vì sao "Toàn quốc" đứng NGOÀI 3 nhóm: nó không phải một đơn vị hành chính. Nhét vào nhóm
    "Tỉnh" là nói sai, mà bỏ vào nhóm riêng một mình thì tốn một dòng tiêu đề cho một dòng nội
    dung — để trần ngay dưới "Chưa gán" là gọn và đúng nhất.

    Câu hỏi lỡ gán mã 'KHAC' trong ngày 3/10 vẫn hiện thành một lựa chọn riêng có ghi chú, để
    người sửa tự chọn tỉnh đúng rồi lưu lại. CỐ Ý không âm thầm đổi hộ.

    Cả 2 ô để trống được -> cột NULL, nằm nhóm "Chưa gán" của bộ lọc.

    Biến truyền vào: $province (mã đang chọn), $examYear (năm đang chọn).
--}}
@php
    use App\Support\ProvinceCatalog;

    $pyProvince = (string) old('province', (string) ($province ?? ''));
    $pyYear = (string) old('exam_year', (string) ($examYear ?? ''));

    // Mã đã bỏ khỏi danh sách nhưng câu này đang gán -> bày riêng, không để mất giá trị.
    $pyRetiredLabel = ProvinceCatalog::LEGACY_QUESTION_SCOPES[$pyProvince] ?? null;
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="province">Tỉnh thành</label>
        <x-ws.select id="province" name="province">
            <option value="">— Chưa gán —</option>
            @foreach (ProvinceCatalog::QUESTION_SCOPES as $code => $label)
                <option value="{{ $code }}" @selected($pyProvince === $code)>{{ $label }}</option>
            @endforeach
            @if ($pyRetiredLabel)
                <option value="{{ $pyProvince }}" selected>{{ $pyRetiredLabel }} (mục đã bỏ — chọn lại tỉnh giúp)</option>
            @endif
            @foreach (ProvinceCatalog::groups() as $groupLabel => $options)
                <optgroup label="{{ $groupLabel }}">
                    @foreach ($options as $code => $label)
                        <option value="{{ $code }}" @selected($pyProvince === $code)>{{ $label }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </x-ws.select>
        {{-- SỬA 7/10 — khu vực không có ô riêng: chọn Toàn quốc = áp dụng cả 3 miền. --}}
        <p class="mt-1 text-xs text-slate-400">Khu vực tự suy ra từ tỉnh/thành. Chọn <strong>Toàn quốc</strong> thì áp dụng cho tất cả các miền (Bắc, Trung, Nam).</p>
    </div>
    <div>
        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="exam_year">Năm</label>
        <x-ws.select id="exam_year" name="exam_year">
            <option value="">— Chưa gán —</option>
            @foreach (ProvinceCatalog::years() as $year)
                <option value="{{ $year }}" @selected($pyYear === (string) $year)>{{ $year }}</option>
            @endforeach
        </x-ws.select>
    </div>
</div>
