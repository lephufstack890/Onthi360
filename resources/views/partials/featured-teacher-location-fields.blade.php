{{--
    SỬA 4/10 (khách: "chỗ sửa chưa có sửa được tỉnh/thành và khu vực bổ sung giúp tôi luôn nha")
    — 2 ô Tỉnh/thành và Khu vực, DÙNG CHUNG cho form THÊM MỚI lẫn form SỬA.

    Tách ra làm một tệp vì mục "Toàn quốc" chỉ tồn tại ở màn này. Để hai bản chép tay ở hai form
    thì chỉ cần sửa một bên là hai chỗ lệch nhau ngay — thêm mới có "Toàn quốc" mà sửa thì không.

    Hai cột này nằm ở bảng users, nên form SỬA chỉ bày chúng khi hồ sơ có gắn tài khoản.

    Biến truyền vào: $locPrefix (để id không trùng nhau giữa các dòng), $locProvince, $locRegion.
--}}
@php
    use App\Support\VietnamProvinces;
@endphp

<div>
    <label class="mb-1 block text-[13px] font-medium text-slate-600" for="{{ $locPrefix }}-province">Tỉnh/thành</label>
    <x-ws.select id="{{ $locPrefix }}-province" name="province">
        <option value="">— Không chọn —</option>
        {{-- "Toàn quốc" cho thầy cô dạy/phụ trách trên phạm vi cả nước, không gắn một tỉnh nào.
             Để NGOÀI danh sách 63 tỉnh chứ không chèn vào giữa: nó không phải một đơn vị hành
             chính, trộn vào danh sách là nói sai.

             CỐ Ý chỉ thêm ở màn này, chưa đụng VietnamProvinces::options() — danh mục đó còn
             dùng cho màn Người dùng, Tài liệu, Khoá học; đổi ở gốc là đổi luôn cả ba chỗ mà
             khách chưa yêu cầu. --}}
        <option value="Toàn quốc" @selected($locProvince === 'Toàn quốc')>Toàn quốc</option>
        @foreach (VietnamProvinces::options() as $provinceName)
            <option value="{{ $provinceName }}" @selected($locProvince === $provinceName)>{{ $provinceName }}</option>
        @endforeach
    </x-ws.select>
</div>

<div>
    <label class="mb-1 block text-[13px] font-medium text-slate-600" for="{{ $locPrefix }}-region">Khu vực</label>
    <x-ws.select id="{{ $locPrefix }}-region" name="region">
        <option value="">— Không chọn —</option>
        @foreach (VietnamProvinces::regionOptions() as $regionValue => $regionLabel)
            <option value="{{ $regionValue }}" @selected($locRegion === $regionValue)>{{ $regionLabel }}</option>
        @endforeach
    </x-ws.select>
</div>
