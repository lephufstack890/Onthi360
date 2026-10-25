{{-- SỬA 25/10 — "Hướng dẫn & thể lệ" cuối tab Thông tin / Tổng quan (dùng chung cho học sinh và ban tổ chức). --}}
<details class="hub-rules">
    <summary>{{ $survey ? 'Hướng dẫn & thể lệ khảo sát' : 'Hướng dẫn & thể lệ cuộc thi' }}<x-lucide name="chevron-down" /></summary>
    <p>{{ $contest['description'] }}</p>
    @if ($contest['rules'] !== '' && $contest['rules'] !== $contest['description'])
        <p style="white-space:pre-line">{{ $contest['rules'] }}</p>
    @endif
    <ul>
        <li>{{ $survey ? 'Không giới hạn số lượt trong thời gian kỳ khảo sát mở. Chỉ cần nhập đúng mật khẩu (nhận từ giáo viên) trước khi làm bài.' : 'Mỗi học sinh nộp một lần cho mỗi kỳ thi. Giáo viên hoặc admin tổ chức duyệt đăng ký cho toàn cuộc thi.' }}</li>
        <li>{{ $rankingRule }}</li>
        @if ($contest['publishLabel'])
            <li>Bảng xếp hạng công bố lúc {{ $contest['publishLabel'] }}.</li>
        @endif
        <li>Thời gian mở và đóng kỳ theo múi giờ Việt Nam (GMT+7).</li>
    </ul>
    <p class="hub-rule-organizer"><x-lucide name="shield-check" />Ban tổ chức: {{ $contest['organizer'] }}</p>
</details>
