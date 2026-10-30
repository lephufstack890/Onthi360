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

    {{-- SỬA 30/10 (khách: "admin, giáo viên làm thử đề lúc nào cũng được — kể cả kỳ sắp diễn ra — để kiểm tra chấm") — chỉ ban
         tổ chức thấy. Lượt làm thử không tính vào bảng xếp hạng, người tham gia, bài nộp, nhật ký của kỳ. --}}
    @if (! empty($testRun))
        <form method="POST" action="{{ $testRun['url'] }}" target="_blank"
              style="display:flex;flex-wrap:wrap;align-items:center;gap:10px 14px;margin:14px 0 4px;padding:12px 14px;border:1px dashed #9cc7d8;border-radius:12px;background:#f3f9fc">
            @csrf
            <span style="display:grid;place-items:center;width:34px;height:34px;border-radius:10px;background:#e0f0f6;color:#126f91;flex:none"><x-lucide name="play-circle" style="width:18px;height:18px" /></span>
            <span style="flex:1 1 260px;min-width:0">
                <b style="display:block;font-size:13px;color:#123b68">Làm thử đề (dành cho ban tổ chức)</b>
                <span style="display:block;margin-top:2px;font-size:12px;line-height:1.5;color:#61798b">
                    @if ($testRun['options'] === [])
                        Chưa có đề để làm thử — chọn / chốt đề ở tab {{ $s['tabLabel'] }} trước.
                    @else
                        Vào làm như học sinh để kiểm tra đề và máy chấm, lúc nào cũng được. Không cần mật khẩu; bài làm thử không tính vào bảng xếp hạng, người tham gia hay nhật ký của kỳ.
                    @endif
                </span>
            </span>
            @if (count($testRun['options']) > 1)
                <select name="assessment_id" aria-label="Chọn đề làm thử" style="height:36px;max-width:260px;border:1px solid #d6e3ec;border-radius:9px;padding:0 10px;font-size:12px;color:#123b68;background:#fff">
                    @foreach ($testRun['options'] as $tid => $tlabel)
                        <option value="{{ $tid }}">{{ $tlabel }}</option>
                    @endforeach
                </select>
            @elseif (count($testRun['options']) === 1)
                <input type="hidden" name="assessment_id" value="{{ array_key_first($testRun['options']) }}">
            @endif
            <button type="submit" class="hub-primary-action" @disabled($testRun['options'] === []) style="display:inline-flex;align-items:center;gap:6px">
                <x-lucide name="play-circle" style="width:15px;height:15px" />Làm thử đề
            </button>
        </form>
    @endif

    <div class="hub-preparation">
        <h4>{{ $ended ? 'Cấu hình của kỳ' : 'Trước khi học sinh bắt đầu' }}</h4>
        <div data-ready="{{ $s['ready'] ? 'true' : 'false' }}">
            <x-lucide name="check-circle-2" />
            <p><b>{{ $s['ready'] ? 'Bộ đề đã áp dụng' : 'Bộ đề chưa sẵn sàng' }}</b>
                <span>{{ $s['ready']
                    ? $s['deliveryLabel'].' · '.($survey ? 'Mỗi lượt làm một đề; điểm tính theo thang điểm của đề.' : 'Mỗi học sinh làm một đề; đề đã nhận được giữ cố định.')
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
