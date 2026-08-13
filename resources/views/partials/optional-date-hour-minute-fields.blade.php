{{--
  Field mốc thời gian TÙY CHỌN (opens_at/closes_at của Assignment — 8.4: giao đề) dùng
  chung cho teacher.assessments.create + teacher.assessments.index (popover "Giao cho
  lớp"). Khác với partials.session-datetime-fields (buổi học, luôn BẮT BUỘC nhập): ở đây
  Ngày để trống nghĩa là "không giới hạn mốc thời gian này" — Giờ/Phút chỉ có tác dụng khi
  Ngày đã chọn. Không gắn id/for cho input — partial này bị @include lặp lại nhiều lần
  trong 1 trang (mỗi dòng đề trong teacher.assessments.index có form riêng), gắn id cố
  định sẽ tạo ra id trùng lặp trên cùng 1 trang.

  Cần truyền vào: $prefix (vd 'opens'/'closes' — dùng làm tên field {$prefix}_date/
  {$prefix}_hour/{$prefix}_minute) và $label (vd 'Mở lúc (tùy chọn)').
--}}
<div>
    <p class="text-xs font-medium text-slate-500 mb-1">{{ $label }}</p>
    <div class="grid grid-cols-2 gap-1.5">
        <input type="date" name="{{ $prefix }}_date" value="{{ old($prefix.'_date') }}"
               class="w-full rounded-lg border border-slate-200 text-xs p-2 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
        <div class="flex items-center shrink-0 rounded-lg border border-slate-200 bg-white focus-within:ring-2 focus-within:ring-rose-100 focus-within:border-rose-300 transition">
            <select name="{{ $prefix }}_hour" aria-label="Giờ"
                    class="w-full border-0 bg-transparent text-xs p-2 focus:outline-none focus:ring-0">
                @for ($h = 0; $h < 24; $h++)
                    @php $hh = sprintf('%02d', $h); @endphp
                    <option value="{{ $hh }}" @selected(old($prefix.'_hour') === $hh)>{{ $hh }}</option>
                @endfor
            </select>
            <span class="text-slate-400 shrink-0 text-xs">:</span>
            <select name="{{ $prefix }}_minute" aria-label="Phút"
                    class="w-full border-0 bg-transparent text-xs p-2 focus:outline-none focus:ring-0">
                @foreach (range(0, 55, 5) as $m)
                    @php $mm = sprintf('%02d', $m); @endphp
                    <option value="{{ $mm }}" @selected(old($prefix.'_minute') === $mm)>{{ $mm }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>
