
@props(['name', 'label' => null, 'dayValue' => null, 'monthValue' => null, 'yearValue' => null, 'hourValue' => null, 'minuteValue' => null, 'required' => false, 'hint' => null])
@php
    $dayValue = ($dayValue !== null && $dayValue !== '') ? str_pad((string) $dayValue, 2, '0', STR_PAD_LEFT) : null;
    $monthValue = ($monthValue !== null && $monthValue !== '') ? str_pad((string) $monthValue, 2, '0', STR_PAD_LEFT) : null;
    $yearValue = ($yearValue !== null && $yearValue !== '') ? (string) $yearValue : null;

    $days = array_map(fn ($d) => str_pad((string) $d, 2, '0', STR_PAD_LEFT), range(1, 31));
    $months = array_map(fn ($m) => str_pad((string) $m, 2, '0', STR_PAD_LEFT), range(1, 12));

    $currentYear = (int) now()->format('Y');
    $years = range($currentYear - 1, $currentYear + 3);
    if ($yearValue !== null && ! in_array((int) $yearValue, $years, true)) {
        $years[] = (int) $yearValue;
        sort($years);
    }

    $hours = array_map(fn ($h) => str_pad((string) $h, 2, '0', STR_PAD_LEFT), range(0, 23));
    $minutes = ['00', '05', '10', '15', '20', '25', '30', '35', '40', '45', '50', '55'];
    $hourValue = ($hourValue !== null && $hourValue !== '') ? str_pad((string) $hourValue, 2, '0', STR_PAD_LEFT) : '00';
    $minuteValue = ($minuteValue !== null && $minuteValue !== '') ? str_pad((string) $minuteValue, 2, '0', STR_PAD_LEFT) : '00';
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
