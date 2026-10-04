{{--
    SỬA 1/10 (khách: "chỗ lọc danh sách trong admin cũng cho lọc theo tỉnh thành và năm luôn nha.
    Giáo viên cũng tương tự nhé") — 2 ô lọc dùng CHUNG cho thanh bộ lọc của admin
    (admin/content/index.blade.php) và của giáo viên (teacher/questions/index.blade.php).

    Giá trị 'none' = CHƯA GÁN (cột NULL), để dò ra câu còn thiếu mà gán dần — xem
    QuestionRepository::applyQuestionBankFilters(). Cả 2 ô cùng nằm trong form GET của trang gọi.

    Biến truyền vào: $filters, $provinceGroups, $examYearOptions.
--}}
<div class="min-w-[150px]">
    <label class="block text-xs font-medium text-slate-500 mb-1" for="filter-province">Tỉnh thành</label>
    <x-ws.select id="filter-province" name="province">
        <option value="">Tất cả tỉnh thành</option>
        {{-- SỬA 4/10 — "Toàn quốc" lên đầu, rồi tới danh mục tỉnh thành đầy đủ bên dưới. --}}
        @foreach (\App\Support\ProvinceCatalog::QUESTION_SCOPES as $scopeCode => $scopeLabel)
            <option value="{{ $scopeCode }}" @selected(($filters['province'] ?? null) === $scopeCode)>{{ $scopeLabel }}</option>
        @endforeach
        {{-- Mã đã bỏ khỏi ô chọn nhưng còn trong dữ liệu — giữ ở bộ LỌC để admin dò ra những câu
             lỡ gán hôm 3/10 mà gán lại tỉnh cho đúng. Bỏ đi là không còn cách nào tìm ra chúng. --}}
        @foreach (\App\Support\ProvinceCatalog::LEGACY_QUESTION_SCOPES as $retiredCode => $retiredLabel)
            <option value="{{ $retiredCode }}" @selected(($filters['province'] ?? null) === $retiredCode)>{{ $retiredLabel }} (mục đã bỏ)</option>
        @endforeach
        @foreach ($provinceGroups as $groupLabel => $options)
            <optgroup label="{{ $groupLabel }}">
                @foreach ($options as $code => $label)
                    <option value="{{ $code }}" @selected(($filters['province'] ?? null) === $code)>{{ $label }}</option>
                @endforeach
            </optgroup>
        @endforeach
        <option value="none" @selected(($filters['province'] ?? null) === 'none')>Chưa gán tỉnh thành</option>
    </x-ws.select>
</div>
<div class="min-w-[120px]">
    <label class="block text-xs font-medium text-slate-500 mb-1" for="filter-exam-year">Năm</label>
    <x-ws.select id="filter-exam-year" name="exam_year">
        <option value="">Tất cả năm</option>
        @foreach ($examYearOptions as $year)
            <option value="{{ $year }}" @selected((string) ($filters['exam_year'] ?? '') === (string) $year)>{{ $year }}</option>
        @endforeach
        <option value="none" @selected(($filters['exam_year'] ?? null) === 'none')>Chưa gán năm</option>
    </x-ws.select>
</div>
