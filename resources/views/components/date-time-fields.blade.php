{{--
  x-date-time-fields | Component: 5 dropdown Ngày/Tháng/Năm/Giờ/Phút — thay HOÀN TOÀN cho
  <input type="datetime-local"|"date"|"time"> native. Lý do đổi tiếp sang dropdown cho cả
  phần NGÀY (trước đó mới đổi phần giờ sang dropdown, để lại <input type="date">) là vì
  input ngày/giờ native của trình duyệt bị phản ánh không bấm chọn được trên máy một số
  người dùng — dropdown thì luôn bấm-chọn được, không phụ thuộc widget native nào cả.

  Value submit qua 5 field "{name}_day/_month/_year/_hour/_minute" — được
  App\Http\Controllers\Admin\CompetitionController::combineDateTimeInputs() ghép lại thành
  1 field "{name}" chuẩn "Y-m-d\TH:i" NGAY TRƯỚC KHI validate(), nên phía validate()/Service
  không cần đổi gì (vẫn nhận đúng 1 field 'date' như cũ).

  Props:
  - name: tên field gốc (vd "starts_at") — dùng làm tiền tố cho 5 input con.
  - dayValue/monthValue/yearValue: "d"/"m"/"Y" hiện tại (chấp nhận không có số 0 phía
    trước, component tự pad) — vd old('{name}_day', $model->{name}?->format('d')).
  - hourValue/minuteValue: "H"/"i" hiện tại — vd old('{name}_hour', $model->{name}?->format('H')).
  - required: bắt buộc chọn đủ Ngày/Tháng/Năm (giờ/phút luôn có giá trị mặc định 00:00 nếu
    bỏ trống). Bỏ trống Ngày/Tháng/Năm bất kỳ 1 trong 3 → cả mốc thời gian này thành null.
  - hint: ghi chú nhỏ dưới các input (tùy chọn).
--}}
@props(['name', 'label' => null, 'dayValue' => null, 'monthValue' => null, 'yearValue' => null, 'hourValue' => null, 'minuteValue' => null, 'required' => false, 'hint' => null])
@php
    $dayValue = ($dayValue !== null && $dayValue !== '') ? str_pad((string) $dayValue, 2, '0', STR_PAD_LEFT) : null;
    $monthValue = ($monthValue !== null && $monthValue !== '') ? str_pad((string) $monthValue, 2, '0', STR_PAD_LEFT) : null;
    $yearValue = ($yearValue !== null && $yearValue !== '') ? (string) $yearValue : null;

    $days = array_map(fn ($d) => str_pad((string) $d, 2, '0', STR_PAD_LEFT), range(1, 31));
    $months = array_map(fn ($m) => str_pad((string) $m, 2, '0', STR_PAD_LEFT), range(1, 12));

    $currentYear = (int) now()->format('Y');
    $years = range($currentYear - 1, $currentYear + 3);
    // Dữ liệu cũ ngoài khoảng năm hiển thị mặc định (hiếm) vẫn thêm vào để không mất giá trị đang lưu.
    if ($yearValue !== null && ! in_array((int) $yearValue, $years, true)) {
        $years[] = (int) $yearValue;
        sort($years);
    }

    $hours = array_map(fn ($h) => str_pad((string) $h, 2, '0', STR_PAD_LEFT), range(0, 23));
    $minutes = ['00', '05', '10', '15', '20', '25', '30', '35', '40', '45', '50', '55'];
    $hourValue = ($hourValue !== null && $hourValue !== '') ? str_pad((string) $hourValue, 2, '0', STR_PAD_LEFT) : '00';
    $minuteValue = ($minuteValue !== null && $minuteValue !== '') ? str_pad((string) $minuteValue, 2, '0', STR_PAD_LEFT) : '00';
    // Dữ liệu cũ có thể lệch mốc 5 phút (vd "07") — vẫn thêm vào danh sách để hiển thị đúng
    // giá trị đang lưu thay vì âm thầm lệch về "00" khi mở lại form.
    if (! in_array($minuteValue, $minutes, true)) {
        $minutes[] = $minuteValue;
        sort($minutes);
    }
@endphp
<div>
    @if ($label)
        <label class="block text-sm font-medium text-slate-600 mb-1">{{ $label }}</label>
    @endif
    <div class="grid grid-cols-3 gap-2 mb-2">
        <x-select name="{{ $name }}_day">
            <option value="">Ngày</option>
            @foreach ($days as $d)
                <option value="{{ $d }}" @selected($dayValue === $d)>{{ $d }}</option>
            @endforeach
        </x-select>
        <x-select name="{{ $name }}_month">
            <option value="">Tháng</option>
            @foreach ($months as $m)
                <option value="{{ $m }}" @selected($monthValue === $m)>Tháng {{ $m }}</option>
            @endforeach
        </x-select>
        <x-select name="{{ $name }}_year">
            <option value="">Năm</option>
            @foreach ($years as $y)
                <option value="{{ $y }}" @selected($yearValue === (string) $y)>{{ $y }}</option>
            @endforeach
        </x-select>
    </div>
    <div class="grid grid-cols-2 gap-2">
        <x-select name="{{ $name }}_hour">
            @foreach ($hours as $h)
                <option value="{{ $h }}" @selected($hourValue === $h)>{{ $h }} giờ</option>
            @endforeach
        </x-select>
        <x-select name="{{ $name }}_minute">
            @foreach ($minutes as $m)
                <option value="{{ $m }}" @selected($minuteValue === $m)>{{ $m }} phút</option>
            @endforeach
        </x-select>
    </div>
    @if ($hint)
        <p class="text-xs text-slate-400 mt-1">{{ $hint }}</p>
    @endif
</div>
