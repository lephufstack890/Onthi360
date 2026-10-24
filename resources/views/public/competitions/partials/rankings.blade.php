{{-- SỬA 24/10 — "Bảng xếp hạng" (source: ContestRankings). Dữ liệu thật: ContestHubService::rankings().
     Người ngoài ban tổ chức chỉ thấy tên của chính mình (giữ chính sách ẩn danh cũ). --}}
<div class="hub-content-heading hub-ranking-heading">
    <div><h3>Bảng xếp hạng</h3><p>{{ $round['label'] }} · {{ count($rankings) }} học sinh có điểm</p></div>
    @if ($manager)
        <button type="button" class="contest-btn secondary hub-print-ranking" @disabled(! count($rankings)) onclick="window.print()"><x-lucide name="printer" />In bảng xếp hạng</button>
    @else
        <x-lucide name="trophy" />
    @endif
</div>
@if (! $resultsPublic)
    <div class="contest-empty"><x-lucide name="clock-3" /><h3>Kết quả chưa công bố</h3><p>Bảng xếp hạng sẽ hiện lúc {{ $contest['publishLabel'] }}.</p></div>
@else
    <p class="hub-assessment-note">{{ $rankingRule }}</p>
    @if ($myRank > 0)
        @php $mine = $rankings[$myRank - 1]; @endphp
        <div class="contest-own-rank"><x-lucide name="trophy" /><span>Kết quả của bạn<b>Hạng {{ $myRank }} / {{ count($rankings) }} · {{ $mine['scoreLabel'] }} điểm</b></span><x-lucide name="check-circle-2" /></div>
    @endif
    @if (count($rankings))
        <figure class="hub-distribution">
            <figcaption>Phân bố điểm <span>{{ count($rankings) }} thí sinh · Thang điểm 100</span></figcaption>
            <div class="hub-distribution-bars" role="img" aria-label="{{ collect($distribution)->map(fn ($g) => $g['label'].' điểm: '.$g['count'].' thí sinh')->implode(', ') }}">
                @foreach ($distribution as $g)
                    <div><b>{{ $g['count'] }}</b><div><i style="height: {{ $g['height'] }}%"></i></div><span>{{ $g['label'] }}</span></div>
                @endforeach
            </div>
        </figure>
        <div class="contest-table-scroll hub-ranking-table {{ $survey ? 'hub-survey-ranking' : '' }}">
            @include('public.competitions.partials.ranking-table', ['withOwn' => true])
        </div>
        <p class="contest-table-note">C1–C{{ count($questions) }} theo thứ tự câu hỏi gốc. {{ $survey ? 'Điểm từng câu lấy từ cùng lượt đạt điểm cao nhất, không cộng dồn qua các lượt.' : 'Ghép đúng câu giữa các mã đề hoán vị.' }}</p>
        @if (count($questionStats))
            @include('public.competitions.partials.question-stats', ['stats' => $questionStats, 'statsNote' => 'Theo bài nộp đã chấm'])
        @endif
    @else
        <div class="contest-empty"><x-lucide name="trophy" /><h3>Chưa có kết quả ở kỳ thi này</h3><p>Bảng xếp hạng xuất hiện khi học sinh có bài nộp đã được chấm.</p></div>
    @endif
@endif
