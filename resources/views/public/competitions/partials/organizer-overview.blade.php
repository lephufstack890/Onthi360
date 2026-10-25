{{--
    SỬA 25/10 — tab "Tổng quan" của ban tổ chức (bản mẫu: ContestOrganizerOverview.jsx): 3 thẻ Bộ đề · Học sinh · Bài nộp
    + mục "Trước khi học sinh bắt đầu". Dữ liệu: ContestHubService::roundData() → $setup, $organizerOverview.
--}}
@php
    $s = $setup;
    $o = $organizerOverview ?? ['participants' => 0, 'pending' => 0, 'records' => 0, 'submitters' => 0, 'password' => null];
    $rankTab = $survey ? 'ranking' : 'results';
    $ended = $round['state'] === 'completed';
@endphp
<section class="hub-organizer-overview" aria-label="Tổng quan tổ chức">
    <div class="hub-content-heading"><div><h3>Tổng quan tổ chức</h3><p>{{ $ended ? 'Đối chiếu bài nộp và xem báo cáo của kỳ đã kết thúc.' : ($survey ? 'Chuẩn bị đề và theo dõi bài nộp trong kỳ này.' : 'Chuẩn bị đề, quản lý học sinh và theo dõi bài nộp trong kỳ này.') }}</p></div></div>

    <div class="hub-management-cards">
        <button type="button" @click="showTab('paper')"><x-lucide name="file-text" /><span>Bộ đề<strong>{{ $s['ready'] ? $s['count'].' đề đã sẵn sàng' : 'Cần thiết lập đề' }}</strong><small>{{ $s['fromBank'] ? 'Từ ngân hàng đề' : 'Theo ma trận câu hỏi' }}</small></span><x-lucide name="arrow-right" /></button>
        {{-- SỬA 25/10 — khảo sát không có tab Học sinh (vào bằng mật khẩu): thẻ này mở Bảng xếp hạng. --}}
        <button type="button" @click="showTab('{{ $survey ? $rankTab : 'registrations' }}')"><x-lucide name="user-check" /><span>{{ $survey ? 'Người tham gia' : 'Học sinh' }}<strong>{{ $o['participants'] }} học sinh</strong><small>{{ $survey ? 'Vào khảo sát bằng mật khẩu' : $o['pending'].' đăng ký chờ duyệt' }}</small></span><x-lucide name="arrow-right" /></button>
        <button type="button" @click="showTab('{{ $rankTab }}')"><x-lucide name="list-checks" /><span>Bài nộp<strong>{{ $o['records'] }} lượt đã nộp</strong><small>{{ $o['submitters'] }} học sinh đã nộp bài</small></span><x-lucide name="arrow-right" /></button>
    </div>

    <div class="hub-preparation">
        <h4>{{ $ended ? 'Cấu hình của kỳ' : 'Trước khi học sinh bắt đầu' }}</h4>
        <div data-ready="{{ $s['ready'] ? 'true' : 'false' }}">
            <x-lucide name="check-circle-2" />
            <p><b>{{ $s['ready'] ? 'Bộ đề đã áp dụng' : 'Bộ đề chưa sẵn sàng' }}</b>
                <span>{{ $s['ready']
                    ? $s['deliveryLabel'].' · '.($survey ? 'Mỗi lượt làm một đề; điểm tính trên thang 100.' : 'Mỗi học sinh làm một đề; đề đã nhận được giữ cố định.')
                    : 'Mở tab '.$s['tabLabel'].' để '.($s['fromBank'] ? 'chọn đề có sẵn cho kỳ.' : 'thiết lập ma trận rồi sinh và chốt đề.') }}</span></p>
            <button type="button" @click="showTab('paper')">Mở tab {{ $s['tabLabel'] }}<x-lucide name="arrow-right" /></button>
        </div>
        <div data-ready="{{ $survey || $o['pending'] === 0 ? 'true' : 'false' }}">
            <x-lucide name="user-check" />
            <p><b>{{ $survey ? 'Tham gia bằng mật khẩu' : ($o['pending'] ? $o['pending'].' đăng ký cần xét duyệt' : 'Tham gia bằng đăng ký được duyệt') }}</b>
                <span>
                    @if ($survey)
                        @if ($o['password'] !== null)
                            Mật khẩu: <code class="hub-pass-code" x-data="{ show: false }" @click="show = !show" :title="show ? '' : 'Bấm để hiện'"><i x-show="show" x-cloak>{{ $o['password'] }}</i><i x-show="!show">••••••</i></code>.
                        @else
                            Chưa đặt mật khẩu — học sinh vào thẳng; admin đặt mật khẩu ở trang quản trị cuộc thi.
                        @endif
                        Học sinh chỉ cần nhập đúng mật khẩu là vào làm được — gửi mật khẩu cho các em trước khi khảo sát.
                    @else
                        Duyệt đăng ký hoặc thêm học sinh từ lớp trước khi các em vào thi.
                    @endif
                </span></p>
            @unless ($survey)
                <button type="button" @click="showTab('registrations')">Mở danh sách học sinh<x-lucide name="arrow-right" /></button>
            @endunless
        </div>
    </div>
</section>
