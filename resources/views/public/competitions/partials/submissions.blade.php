{{-- SỬA 24/10 — tab "Bài nộp của tôi" (source: ContestSubmissions). "Xem bài" mở trang kết quả lượt làm sẵn có. --}}
<div class="hub-content-heading"><div><h3>Bài nộp của tôi</h3><p>{{ $round['label'] }} · {{ count($attempts) }} lượt nộp</p></div><x-lucide name="file-text" /></div>
@if (count($attempts))
    <div class="contest-table-scroll">
        <table>
            <thead><tr><th>Lượt nộp</th><th>Thời điểm</th><th>Điểm</th><th>Trạng thái</th><th></th></tr></thead>
            <tbody>
                @foreach ($attempts as $a)
                    <tr>
                        <td><b>#{{ $a['no'] }}</b></td>
                        <td>{{ $a['at'] }}</td>
                        <td><b>{{ $a['scoreLabel'] }}</b></td>
                        <td><span class="contest-badge {{ $a['graded'] ? 'current' : 'upcoming' }}">{{ $a['graded'] ? 'Đã chấm' : 'Chờ chấm' }}</span></td>
                        <td><a class="hub-text-action" href="{{ $a['resultUrl'] }}" aria-label="Xem chi tiết lượt {{ $a['no'] }}">Xem bài<x-lucide name="arrow-up-right" /></a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <div class="contest-empty"><x-lucide name="file-text" /><h3>Bạn chưa nộp bài nào</h3><p>Kết quả và câu trả lời của kỳ thi đang chọn sẽ được lưu tại đây sau khi nộp bài.</p></div>
@endif
