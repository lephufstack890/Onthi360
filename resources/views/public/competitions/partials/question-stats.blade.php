{{-- SỬA 24/10 — "Thống kê theo câu" (source: QuestionStatistics). $stats từ ContestHubService::questionStats(). --}}
<section class="hub-question-statistics" aria-label="Thống kê theo câu">
    <div class="hub-content-heading"><h3>Thống kê theo câu</h3><span class="hub-score-scale">{{ $statsNote ?? 'Theo bài nộp đã chấm' }}</span></div>
    <div class="contest-table-scroll">
        <table>
            <thead><tr><th>Câu hỏi</th><th>Có điểm</th><th>Điểm TB</th><th>Đúng trọn vẹn</th></tr></thead>
            <tbody>
                @foreach ($stats as $s)
                    <tr><td><b>C{{ $s['no'] }}</b> · {{ $s['title'] }}</td><td>{{ $s['count'] }}/{{ $s['total'] }}</td><td>{{ $s['average'] }}</td><td>{{ $s['rate'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="contest-table-note">Chỉ tính lượt có điểm chi tiết của câu; bài chưa lưu chi tiết không được tính là sai.</p>
</section>
