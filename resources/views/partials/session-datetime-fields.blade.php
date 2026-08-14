{{--
  Cặp trường Bắt đầu/Kết thúc dùng chung cho form tạo buổi học (teacher.schedule.*).
  Ngày dùng <input type="date"> (native, ổn định). Giờ dùng 2 dropdown Giờ/Phút gộp
  chung 1 khung viền (thay vì <input type="time"> — widget giờ gốc trình duyệt, đặc
  biệt Safari, khó bấm/chọn). Khối "Bắt đầu"/"Kết thúc" tô màu xanh/đỏ nhạt riêng để dễ
  phân biệt bằng mắt.
  PHẢI include bên trong 1 <form x-data="{ startsDate: '...', endsDate: '...' }"> (giá trị
  khởi tạo lấy từ old() ở nơi include) để tính năng tự điền ngày kết thúc theo ngày bắt đầu
  hoạt động (chỉ điền khi ngày kết thúc còn trống) và để không bị Alpine ghi đè mất giá trị
  cũ khi submit lỗi validation.
--}}
<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
    <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-3">
        <p class="text-xs font-semibold text-emerald-700 mb-2 flex items-center gap-1">🟢 Bắt đầu</p>
        <div class="flex gap-2">
            <div class="flex-1">
                <label class="sr-only" for="starts_date">Ngày bắt đầu</label>
                <input id="starts_date" name="starts_date" type="date" required
                       x-model="startsDate" @change="if (!endsDate) endsDate = startsDate"
                       class="w-full rounded-lg border border-slate-200 bg-white text-sm p-2.5 hover:border-emerald-300 focus:outline-none focus:ring-2 focus:ring-emerald-100 focus:border-emerald-400 transition">
            </div>
            <div class="flex items-center shrink-0 rounded-lg border border-slate-200 bg-white focus-within:ring-2 focus-within:ring-emerald-100 focus-within:border-emerald-400 transition">
                <label class="sr-only" for="starts_hour">Giờ bắt đầu</label>
                <select id="starts_hour" name="starts_hour" required
                        class="border-0 bg-transparent text-sm py-2.5 pl-2.5 pr-0.5 text-right appearance-none cursor-pointer focus:outline-none focus:ring-0">
                    @for ($h = 0; $h < 24; $h++)
                        @php $hh = sprintf('%02d', $h); @endphp
                        <option value="{{ $hh }}" @selected(old('starts_hour') === $hh)>{{ $hh }}</option>
                    @endfor
                </select>
                <span class="text-slate-400">:</span>
                <label class="sr-only" for="starts_minute">Phút bắt đầu</label>
                <select id="starts_minute" name="starts_minute" required
                        class="border-0 bg-transparent text-sm py-2.5 pl-0.5 pr-2.5 appearance-none cursor-pointer focus:outline-none focus:ring-0">
                    @foreach (range(0, 55, 5) as $m)
                        @php $mm = sprintf('%02d', $m); @endphp
                        <option value="{{ $mm }}" @selected(old('starts_minute') === $mm)>{{ $mm }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
    <div class="rounded-xl border border-rose-100 bg-rose-50/50 p-3">
        <p class="text-xs font-semibold text-rose-700 mb-2 flex items-center gap-1">🔴 Kết thúc</p>
        <div class="flex gap-2">
            <div class="flex-1">
                <label class="sr-only" for="ends_date">Ngày kết thúc</label>
                <input id="ends_date" name="ends_date" type="date" required
                       x-model="endsDate"
                       class="w-full rounded-lg border border-slate-200 bg-white text-sm p-2.5 hover:border-rose-300 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-400 transition">
            </div>
            <div class="flex items-center shrink-0 rounded-lg border border-slate-200 bg-white focus-within:ring-2 focus-within:ring-rose-100 focus-within:border-rose-400 transition">
                <label class="sr-only" for="ends_hour">Giờ kết thúc</label>
                <select id="ends_hour" name="ends_hour" required
                        class="border-0 bg-transparent text-sm py-2.5 pl-2.5 pr-0.5 text-right appearance-none cursor-pointer focus:outline-none focus:ring-0">
                    @for ($h = 0; $h < 24; $h++)
                        @php $hh = sprintf('%02d', $h); @endphp
                        <option value="{{ $hh }}" @selected(old('ends_hour') === $hh)>{{ $hh }}</option>
                    @endfor
                </select>
                <span class="text-slate-400">:</span>
                <label class="sr-only" for="ends_minute">Phút kết thúc</label>
                <select id="ends_minute" name="ends_minute" required
                        class="border-0 bg-transparent text-sm py-2.5 pl-0.5 pr-2.5 appearance-none cursor-pointer focus:outline-none focus:ring-0">
                    @foreach (range(0, 55, 5) as $m)
                        @php $mm = sprintf('%02d', $m); @endphp
                        <option value="{{ $mm }}" @selected(old('ends_minute') === $mm)>{{ $mm }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>
