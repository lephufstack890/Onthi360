{{--
    SỬA 1/10 (khách: "thêm 1 cái field nữa cho chọn tỉnh thành và năm nha") — 2 ô phân loại,
    dùng CHUNG cho cả 3 form câu hỏi (admin Tạo, admin Sửa, giáo viên).

    SỬA 3/10 (khách: "chọn tỉnh thành thì để 2 option là Toàn quốc hoặc Các tỉnh khác thôi") —
    ô Tỉnh thành bỏ danh mục 63 đơn vị, chỉ còn 2 lựa chọn (App\Support\ProvinceCatalog::
    QUESTION_SCOPES). Ô tỉnh/thành của ĐỀ THI giữ nguyên danh mục đầy đủ, xem partial
    assessment-detail-fields — đề cần khai đúng nguồn, câu hỏi thì chỉ cần biết phạm vi dùng.

    Câu hỏi CŨ đã gán một tỉnh cụ thể ("HATINH") thì vẫn hiện đúng tên tỉnh đó, thêm thành một
    lựa chọn thứ ba để người sửa tự quyết giữ hay đổi. CỐ Ý không âm thầm đổi hết sang "Các tỉnh
    khác": đó là sửa dữ liệu của người khác mà không hỏi.

    Cả 2 ô để trống được -> cột NULL, nằm nhóm "Chưa gán" của bộ lọc.

    Biến truyền vào: $province (mã đang chọn), $examYear (năm đang chọn).
--}}
@php
    use App\Support\ProvinceCatalog;

    $pyProvince = (string) old('province', (string) ($province ?? ''));
    $pyYear = (string) old('exam_year', (string) ($examYear ?? ''));

    // Mã cũ (không thuộc 2 phạm vi mới) mà vẫn tra ra tên tỉnh -> giữ lại làm lựa chọn riêng.
    $pyLegacyLabel = $pyProvince !== '' && ! array_key_exists($pyProvince, ProvinceCatalog::QUESTION_SCOPES)
        ? ProvinceCatalog::label($pyProvince)
        : null;
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="province">Tỉnh thành</label>
        <x-ws.select id="province" name="province">
            <option value="">— Chưa gán —</option>
            @foreach (ProvinceCatalog::QUESTION_SCOPES as $code => $label)
                <option value="{{ $code }}" @selected($pyProvince === $code)>{{ $label }}</option>
            @endforeach
            @if ($pyLegacyLabel)
                <option value="{{ $pyProvince }}" selected>{{ $pyLegacyLabel }} (gán từ trước)</option>
            @endif
        </x-ws.select>
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
