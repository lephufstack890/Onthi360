{{-- SỬA 24/10 — tab "Kết quả năng lực" của khảo sát (source: ContestResults + SurveyTrend). Chỉ dữ liệu thật của người xem. --}}
@php $sr = $surveyResult ?? ['count' => 0, 'best' => '—', 'latest' => '—', 'points' => [], 'polyline' => '', 'questionStats' => []]; @endphp
<div class="hub-content-heading"><div><h3>Kết quả năng lực</h3><p>{{ $round['label'] }} · Theo dõi từng lượt làm của bạn</p></div><x-lucide name="bar-chart-3" /></div>
<div class="contest-result-stats">
    <div><span>Số lượt làm</span><b>{{ $sr['count'] }}</b></div>
    <div><span>Điểm cao nhất</span><b>{{ $sr['best'] }}<small>/100</small></b></div>
    <div><span>Điểm gần nhất</span><b>{{ $sr['latest'] }}<small>/100</small></b></div>
</div>
@if ($sr['count'] > 0)
    <figure class="hub-trend">
        <figcaption>Tiến bộ qua các lượt làm <span>Thang điểm 100</span></figcaption>
        <svg viewBox="0 0 560 170" role="img" aria-label="Điểm qua từng lượt: {{ collect($sr['points'])->map(fn ($p) => $p['label'].': '.($p['score'] ?? 'chờ chấm'))->implode(', ') }}">
            @foreach ([0, 25, 50, 75, 100] as $s)
                <line x1="32" x2="534" y1="{{ 136 - $s * 1.08 }}" y2="{{ 136 - $s * 1.08 }}" stroke="#e4edf3" stroke-dasharray="3 4" />
                <text x="24" y="{{ 140 - $s * 1.08 }}" text-anchor="end" fill="#7f95a5" font-size="10">{{ $s }}</text>
            @endforeach
            @if (substr_count($sr['polyline'], ' ') >= 1)
                <polyline points="{{ $sr['polyline'] }}" fill="none" stroke="#248f86" stroke-width="2.5" stroke-linejoin="round" />
            @endif
            @foreach ($sr['points'] as $p)
                @if ($p['score'] !== null)
                    <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="4" fill="#248f86" stroke="white" stroke-width="2" />
                    <text x="{{ $p['x'] }}" y="{{ $p['y'] - 10 }}" text-anchor="middle" fill="#257b73" font-size="11" font-weight="600">{{ $p['score'] + 0 }}</text>
                @endif
                <text x="{{ $p['x'] }}" y="158" text-anchor="middle" fill="#6f8798" font-size="10">{{ $p['label'] }}{{ $p['score'] === null ? '*' : '' }}</text>
            @endforeach
        </svg>
    </figure>
    <div class="contest-score-history">
        @foreach ($attempts as $a)
            <div>
                <span>Lượt {{ $a['no'] }}<small>{{ $a['at'] }}</small></span>
                <div role="img" aria-label="Lượt {{ $a['no'] }}: {{ $a['scoreLabel'] }} điểm"><i style="width: {{ max(0, min(100, $a['normalized'] ?? 0)) }}%"></i></div>
                <b>{{ $a['scoreLabel'] }}</b>
            </div>
        @endforeach
    </div>
@else
    <div class="contest-empty"><x-lucide name="bar-chart-3" /><h3>Bắt đầu hành trình của bạn</h3><p>Hoàn thành khảo sát đầu tiên để xem điểm và theo dõi tiến bộ.</p></div>
@endif
<p class="contest-table-note">Mỗi lượt làm được lưu riêng. Xem thứ hạng theo điểm cao nhất trong mục Bảng xếp hạng.</p>
@if ($sr['count'] > 0 && count($sr['questionStats']))
    @include('public.competitions.partials.question-stats', ['stats' => $sr['questionStats'], 'statsNote' => 'Qua các lượt làm'])
@endif
