{{-- SỬA 21/10 (khách: "thêm checker cho câu lập trình" — ĐỢT 1) — ô "Cách chấm" dùng chung cho mọi form tạo / sửa câu lập
     trình (Kho câu hỏi admin + giáo viên, bài tập trong tài liệu). Luật so khớp: App\Support\OutputChecker.
     Tham số: $checker (grading_config['checker'] đang lưu, null = Mặc định). Gửi 2 ô: checker_mode, checker_epsilon.
     CSS riêng tiền tố ock- (Tailwind không build lại trên máy chủ). --}}
@php
    $ockCfg = \App\Support\OutputChecker::normalize($checker ?? null);
    $ockMode = old('checker_mode', $ockCfg['mode'] ?? \App\Support\OutputChecker::DEFAULT);
    $ockEps = old('checker_epsilon', \App\Support\OutputChecker::formatEpsilon((float) ($ockCfg['epsilon'] ?? \App\Support\OutputChecker::DEFAULT_EPSILON)));
@endphp
@once
    <style>
        .ock { display: grid; gap: 6px; }
        .ock-lbl { display: block; font-size: 13px; font-weight: 500; color: #475569; }
        .ock-row { display: grid; grid-template-columns: minmax(0, 1fr); gap: 10px; }
        @media (min-width: 640px) { .ock-row.has-eps { grid-template-columns: minmax(0, 1fr) 180px; } }
        .ock-hint { margin: 0; font-size: 11.5px; line-height: 1.55; color: #64748B; }
        .ock-eps-lbl { display: block; font-size: 11.5px; font-weight: 600; color: #64748B; margin-bottom: 2px; }
        html.theme-dark .ock-lbl { color: #CBD5E1; }
        html.theme-dark .ock-hint, html.theme-dark .ock-eps-lbl { color: #94A3B8; }
    </style>
@endonce
<div class="ock" x-data="{ mode: @js($ockMode), hints: @js(\App\Support\OutputChecker::HINTS) }">
    <label class="ock-lbl" for="checker_mode">Cách chấm (so kết quả với đáp án)</label>
    <div class="ock-row" :class="mode === 'float' ? 'has-eps' : ''">
        <select id="checker_mode" name="checker_mode" x-model="mode" class="admin-input">
            @foreach (\App\Support\OutputChecker::MODES as $value => $label)
                <option value="{{ $value }}" @selected($ockMode === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <div x-show="mode === 'float'" x-cloak>
            <label class="ock-eps-lbl" for="checker_epsilon">Sai số ε</label>
            <input id="checker_epsilon" name="checker_epsilon" type="number" step="any" min="0" max="1"
                   value="{{ $ockEps }}" class="admin-input" :disabled="mode !== 'float'">
        </div>
    </div>
    <p class="ock-hint" x-text="hints[mode] || ''">{{ \App\Support\OutputChecker::HINTS[$ockMode] ?? '' }}</p>
</div>
