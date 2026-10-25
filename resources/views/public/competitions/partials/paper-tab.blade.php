{{--
    SỬA 25/10 — tab đề của kỳ cho ban tổ chức (bản mẫu: ContestPaperTab trong ContestPaperSettings.jsx).
    Tên tab theo nguồn (khách): "Thiết lập đề" khi lấy từ nguồn câu hỏi, "Lấy đề có sẵn" khi lấy đề có sẵn.
    Dữ liệu: ContestHubService::roundSetup() ($setup). Nguồn câu hỏi: bộ soạn ma trận (partials.paper-builder) mở ngay
    trong tab; nguồn đề có sẵn: hộp thoại chọn đề (partials.exam-picker).
--}}
@php $s = $setup; @endphp
<section class="round-paper-tab" aria-label="Thiết lập nguồn đề">
    <div class="hub-content-heading">
        <div>
            <h3>{{ $s['fromBank'] ? 'Đề từ ngân hàng đề' : 'Ma trận đề khảo sát' }}</h3>
            <p>Nguồn đã chọn: {{ $s['fromBank'] ? 'Đề có sẵn' : 'Nguồn câu hỏi' }}</p>
        </div>
        <span class="hub-score-scale">{{ $s['status'] }}</span>
    </div>

    <div class="hub-exam-source">
        <x-lucide :name="$s['fromBank'] ? 'file-text' : 'list-checks'" />
        <div>
            <b>{{ $s['fromBank'] ? ($s['examTitle'] ?? 'Chọn đề phù hợp cho kỳ này') : 'Thiết lập ma trận để chọn câu hỏi' }}</b>
            <p>{{ $s['fromBank']
                ? ($survey ? 'Chọn một đề đã hoàn chỉnh trong ngân hàng, xem lịch sử sử dụng; mọi học sinh làm đề này ở mỗi lượt.'
                           : 'Chọn đề trong ngân hàng, xem lịch sử sử dụng; chia mã đề hoán vị, cho học sinh tự chọn hoặc nhận ngẫu nhiên.')
                : 'Chọn nguồn câu hỏi, chuyên đề, độ khó, số câu và điểm cho từng phần; sinh nhiều đề, duyệt từng đề rồi chốt.' }}</p>
        </div>
    </div>

    <div class="round-paper-summary">
        <div><small>Nguồn đề</small><b>{{ $s['fromBank'] ? 'Ngân hàng đề' : 'Ma trận câu hỏi' }}</b></div>
        <div><small>Đề đã áp dụng</small><b>{{ $s['count'] }} đề</b></div>
        <div><small>Cách giao đề</small><b>{{ $s['deliveryLabel'] }}</b></div>
    </div>

    @if ($s['forms'] !== [])
        <div class="paper-preview-tabs">
            @foreach ($s['forms'] as $f)
                <div><b>{{ $f['label'] }}</b><span>{{ $f['title'] }} · {{ $f['meta'] }}</span></div>
            @endforeach
        </div>
    @endif
    @if ($s['ready'])
        <p class="hub-assessment-note">Mỗi học sinh làm một đề trong bộ đề đã áp dụng; điểm tính trên thang 100.</p>
    @endif
    @if ($s['locked'])
        <p class="hub-assessment-note">Kỳ thi đã có bài làm — giữ nguyên đề để công bằng; vẫn sửa được tên và thời gian của kỳ.</p>
    @endif

    <div class="round-paper-actions">
        @if ($s['fromBank'])
            <button type="button" class="contest-btn primary" @click="openPicker()"><x-lucide name="file-text" />{{ $s['locked'] ? 'Xem đề đã chọn' : 'Chọn đề từ ngân hàng đề' }}</button>
        @else
            <button type="button" class="contest-btn primary" @click="builderOpen = !builderOpen"><x-lucide name="list-checks" /><span x-text="builderOpen ? 'Thu gọn ma trận' : 'Thiết lập ma trận'">Thiết lập ma trận</span></button>
            <button type="button" class="contest-btn secondary" @click="openPicker()"><x-lucide name="file-text" />Đổi nguồn đề</button>
        @endif
    </div>

    @if (! $s['fromBank'] && ! empty($round['paperStateUrl']))
        <div class="round-paper-builder" x-show="builderOpen" x-cloak>@include('public.competitions.partials.paper-builder')</div>
    @endif
</section>
