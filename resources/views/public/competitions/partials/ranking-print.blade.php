{{-- SỬA 24/10 — bản in "Bảng xếp hạng gửi phụ huynh" (source: RankingPrintReport). Ẩn trên màn hình; JS chuyển khối này
     ra thẳng dưới <body> để CSS in (body:has(> .contest-ranking-print)) chỉ in đúng bảng. Chỉ ban tổ chức mới có. --}}
@if (count($rankings))
<article id="contest-ranking-print" class="contest-ranking-print" aria-hidden="true">
    <header>
        <p>ÔN THI 360 · THÔNG TIN KẾT QUẢ GỬI PHỤ HUYNH</p>
        <h1>Bảng xếp hạng {{ $survey ? 'khảo sát năng lực' : 'cuộc thi' }}</h1>
        <h2>{{ $contest['title'] }}</h2>
        <p><b>Kỳ thi:</b> {{ $round['label'] }} · {{ $round['state'] === 'completed' ? 'Đã kết thúc' : 'Kết quả tạm thời' }}</p>
    </header>
    <div class="ranking-print-meta">
        <p><b>Lộ trình:</b> {{ $contest['roadmap']['title'] ?? 'Chưa cập nhật' }}</p>
        <p><b>Người tổ chức:</b> {{ $contest['organizer'] }}</p>
        <p><b>Người lập báo cáo:</b> {{ $viewer['name'] }}</p>
        <p><b>Thời gian kỳ thi:</b> {{ $round['timeFrom'] }} – {{ $round['timeTo'] }} (GMT+7)</p>
        <p><b>Số học sinh có điểm:</b> {{ count($rankings) }} · <b>Lập lúc:</b> {{ now()->format('H:i d/m/Y') }} (GMT+7)</p>
    </div>
    <p class="ranking-print-source">Kết quả tổng hợp từ máy chủ Ôn Thi 360 tại thời điểm lập báo cáo.</p>
    <p class="ranking-print-rule">{{ $rankingRule }} {{ $survey ? 'Cả kỳ dùng một bộ câu hỏi chung, kể cả khi làm lại. Điểm từng câu thuộc lượt đạt điểm cao nhất, không ghép điểm từ nhiều lượt.' : 'Điểm từng câu được đối chiếu theo đề gốc giữa các mã đề hoán vị.' }}</p>
    @include('public.competitions.partials.ranking-table', ['withOwn' => false])
    <footer>
        <p>C1–C{{ count($questions) }}: thứ tự câu hỏi gốc · Thang điểm tổng: {{ $round['totalPoints'] }} · "—": chưa có điểm chi tiết. Chỉ học sinh có ít nhất một bài đã chấm được xếp hạng.</p>
        <p>{{ $round['state'] === 'completed' ? 'Kết quả tại thời điểm lập báo cáo.' : 'Kỳ thi chưa kết thúc; thứ hạng có thể thay đổi khi có kết quả mới.' }}</p>
    </footer>
</article>
@endif
