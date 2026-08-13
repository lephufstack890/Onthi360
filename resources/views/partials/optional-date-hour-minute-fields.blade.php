{{--
  Mốc thời gian tùy chọn (Mở lúc / Đóng lúc) cho Bài tập & Đề (8.4). Để trống Ngày = không
  giới hạn mốc thời gian đó.

  LỊCH SỬ BUG: bản đầu dùng <input type="date"> (native), thử cả có x-model lẫn không có
  x-model — CẢ HAI đều bị trình duyệt thật của người dùng gửi lên "" (không giới hạn) dù đã
  chọn ngày, dù test bằng Playwright .fill() lại luôn thành công (không tái hiện được lỗi
  trong môi trường giả lập). Vì không thể xác nhận chắc chắn nguyên nhân native input, bỏ
  hẳn <input type="date">, thay bằng 3 dropdown Ngày/Tháng/Năm — cùng kiểu <select> đã xác
  nhận hoạt động đúng 100% qua log server ở Giờ/Phút. Dropdown không có rủi ro tương tác
  native-widget nên đây là hướng chắc chắn nhất.
--}}
<div x-data="{
        day: '{{ old($prefix.'_day', '') }}',
        month: '{{ old($prefix.'_month', now()->format('m')) }}',
        year: '{{ old($prefix.'_year', now()->format('Y')) }}',
        hour: '{{ old($prefix.'_hour', '00') }}',
        minute: '{{ old($prefix.'_minute', '00') }}',
    }">
    <p class="text-xs font-medium text-slate-500 mb-1">{{ $label }}</p>
    <div class="space-y-1.5">
        <div class="flex items-center rounded-lg border border-slate-200 bg-white focus-within:ring-2 focus-within:ring-rose-100 focus-within:border-rose-300 transition">
            <select name="{{ $prefix }}_day" x-model="day" aria-label="Ngày"
                    class="w-full border-0 bg-transparent text-xs p-2 focus:outline-none focus:ring-0">
                <option value="">— Ngày —</option>
                @for ($d = 1; $d <= 31; $d++)
                    @php $dd = sprintf('%02d', $d); @endphp
                    <option value="{{ $dd }}" @selected(old($prefix.'_day') === $dd)>{{ $dd }}</option>
                @endfor
            </select>
            <span class="text-slate-400 shrink-0 text-xs px-0.5">/</span>
            <select name="{{ $prefix }}_month" x-model="month" aria-label="Tháng"
                    class="w-full border-0 bg-transparent text-xs p-2 focus:outline-none focus:ring-0">
                @for ($m = 1; $m <= 12; $m++)
                    @php $mm = sprintf('%02d', $m); @endphp
                    <option value="{{ $mm }}" @selected(old($prefix.'_month', now()->format('m')) === $mm)>{{ $mm }}</option>
                @endfor
            </select>
            <span class="text-slate-400 shrink-0 text-xs px-0.5">/</span>
            <select name="{{ $prefix }}_year" x-model="year" aria-label="Năm"
                    class="w-full border-0 bg-transparent text-xs p-2 focus:outline-none focus:ring-0">
                @for ($y = (int) now()->format('Y'); $y <= (int) now()->format('Y') + 2; $y++)
                    <option value="{{ $y }}" @selected((string) old($prefix.'_year', now()->format('Y')) === (string) $y)>{{ $y }}</option>
                @endfor
            </select>
        </div>
        <div class="flex items-center rounded-lg border border-slate-200 bg-white focus-within:ring-2 focus-within:ring-rose-100 focus-within:border-rose-300 transition">
            <select name="{{ $prefix }}_hour" x-model="hour" aria-label="Giờ"
                    class="w-full border-0 bg-transparent text-xs p-2 focus:outline-none focus:ring-0">
                @for ($h = 0; $h < 24; $h++)
                    @php $hh = sprintf('%02d', $h); @endphp
                    <option value="{{ $hh }}" @selected(old($prefix.'_hour') === $hh)>{{ $hh }}</option>
                @endfor
            </select>
            <span class="text-slate-400 shrink-0 text-xs px-0.5">:</span>
            <select name="{{ $prefix }}_minute" x-model="minute" aria-label="Phút"
                    class="w-full border-0 bg-transparent text-xs p-2 focus:outline-none focus:ring-0">
                @foreach (range(0, 55, 5) as $m)
                    @php $mm = sprintf('%02d', $m); @endphp
                    <option value="{{ $mm }}" @selected(old($prefix.'_minute') === $mm)>{{ $mm }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <p class="text-[11px] mt-1" :class="day ? 'text-sky-600 font-medium' : 'text-slate-400'">
        <span x-show="day" x-cloak>Sẽ lưu: <span x-text="day"></span>/<span x-text="month"></span>/<span x-text="year"></span> lúc <span x-text="hour"></span>:<span x-text="minute"></span></span>
        <span x-show="!day" x-cloak>Chưa đặt — không giới hạn mốc thời gian này</span>
    </p>
</div>
