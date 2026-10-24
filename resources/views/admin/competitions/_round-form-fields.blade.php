{{-- SỬA 24/10 — mã đề hoán vị của kỳ thi (Đề 101, 102 …): cùng câu, cùng điểm, chỉ đổi thứ tự câu. Chỉ áp dụng CUỘC THI
     (khảo sát dùng một bộ câu chung). $mode/$count = giá trị đang lưu (null khi thêm mới). --}}
@if (\App\Models\CompetitionExam::supportsForms() && ($competition->type->value ?? '') === 'contest')
    <div>
        <label class="block text-xs text-slate-500 mb-1">Số mã đề hoán vị</label>
        <x-ws.select name="form_count">
            @foreach (range(1, \App\Support\ContestForms::MAX) as $n)
                <option value="{{ $n }}" @selected((int) ($count ?? 1) === $n)>{{ $n === 1 ? '1 — không hoán vị' : $n.' mã đề (Đề 101 → '.\App\Support\ContestForms::code($n).')' }}</option>
            @endforeach
        </x-ws.select>
    </div>
    <div>
        <label class="block text-xs text-slate-500 mb-1">Cách giao mã đề</label>
        <x-ws.select name="assignment_mode">
            <option value="random" @selected(($mode ?? 'random') === 'random')>Hệ thống giao ngẫu nhiên</option>
            <option value="choice" @selected(($mode ?? 'random') === 'choice')>Học sinh tự chọn mã đề</option>
        </x-ws.select>
    </div>
@endif
