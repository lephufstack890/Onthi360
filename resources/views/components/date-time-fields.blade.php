{{--
  x-date-time-fields | Component: input Ngày ("date") + 2 dropdown Giờ/Phút — thay cho
  <input type="datetime-local"> gộp VÀ thay cho <input type="time"> (cả 2 đều bị phản ánh là
  khó/không bấm chọn được giờ trên máy một số người dùng — widget giờ native của trình
  duyệt không nhất quán giữa các máy). Dropdown thì luôn bấm-chọn được, không phụ thuộc
  widget native của trình duyệt.

  Value submit qua 3 field "{name}_date" + "{name}_hour" + "{name}_minute" — được
  App\Http\Controllers\Admin\CompetitionController::combineDateTimeInputs() ghép lại thành
  1 field "{name}" chuẩn "Y-m-d\TH:i" NGAY TRƯỚC KHI validate(), nên phía validate()/Service
  không cần đổi gì (vẫn nhận đúng 1 field 'date' như cũ).

  Props:
  - name: tên field gốc (vd "starts_at") — dùng làm tiền tố cho 3 input con.
  - dateValue: "Y-m-d" hiện tại, vd old('{name}_date', $model->{name}?->format('Y-m-d')).
  - hourValue/minuteValue: "H"/"i" hiện tại (chấp nhận không có số 0 phía trước, component
    tự pad) — vd old('{name}_hour', $model->{name}?->format('H')).
  - required: bắt buộc chọn ngày (giờ/phút luôn có giá trị mặc định 00:00 nếu bỏ trống).
  - hint: ghi chú nhỏ dưới các input (tùy chọn).
--}}
@props(['name', 'label' => null, 'dateValue' => null, 'hourValue' => null, 'minuteValue' => null, 'required' => false, 'hint' => null])
@php
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
    <div class="grid grid-cols-3 gap-2">
        <input type="date" name="{{ $name }}_date" value="{{ $dateValue }}" @required($required)
               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
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
