{{-- SỬA 24/10 — bảng xếp hạng theo câu (dùng cho tab và bản in). $withOwn: tô dòng của người xem. --}}
<table aria-label="Bảng xếp hạng theo câu">
    <thead>
        <tr>
            <th>Hạng</th><th>Học sinh / Lớp</th>
            @if ($survey)<th>Lượt làm</th>@endif
            @foreach ($questions as $i => $q)<th>C{{ $i + 1 }}<small>/{{ rtrim(rtrim(number_format($q['points'], 2, '.', ''), '0'), '.') }}</small></th>@endforeach
            <th>{{ $survey ? 'Cao nhất' : 'Tổng điểm' }}</th>
            @if ($survey)<th>Gần nhất</th>@endif
            <th>{{ $survey ? 'Đạt điểm cao nhất' : 'Thời điểm nộp' }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rankings as $row)
            <tr @if (! empty($withOwn) && $row['isViewer']) data-own="true" @endif>
                <td><span class="contest-rank-medal rank-{{ $row['rank'] }}">{{ $row['rank'] }}</span></td>
                <td>{{ $row['name'] }}@if (! empty($withOwn) && $row['isViewer'])<small> (Bạn)</small>@endif
                    @if ($manager)<small class="hub-ranking-class">{{ $row['className'] ?: 'Chưa cập nhật lớp' }}</small>@endif
                </td>
                @if ($survey)<td>{{ $row['attemptCount'] }}<small class="hub-ranking-class">{{ $row['gradedCount'] }} đã chấm</small></td>@endif
                @foreach ($row['perQuestion'] as $s)<td>{{ $s === null ? '—' : rtrim(rtrim(number_format($s, 1, '.', ''), '0'), '.') }}</td>@endforeach
                <td><b>{{ $row['scoreLabel'] }}</b></td>
                @if ($survey)<td>{{ $row['latestLabel'] }}</td>@endif
                <td>{{ $row['atLabel'] }}@if (! $survey && $row['formLabel'])<small class="hub-ranking-class">{{ $row['formLabel'] }}</small>@endif</td>
            </tr>
        @endforeach
    </tbody>
</table>
