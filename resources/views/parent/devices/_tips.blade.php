{{--
  SỬA 5/11 (khách: "thêm chỗ đề xuất phương pháp để quản lý con, ví dụ học nhiều thì được thưởng…") — khối "Gợi ý cho bố mẹ"
  ở trang Máy tính của con (parent.devices.index), đặt ngay dưới 4 ô số liệu hôm nay. Chỉ HIỂN THỊ gợi ý, không đổi cài đặt nào:
   · Gợi ý hôm nay: tự sinh từ số liệu thật của con hôm nay ($usage, $doneToday, $policy) — tối đa 3 gợi ý.
   · Phương pháp đồng hành: 4 nhóm (chơi game nhiều / lướt web nhiều / thưởng khi học tốt / thoả thuận gia đình).
   · Bảng thưởng gợi ý + bản thoả thuận mẫu có nút Sao chép.
  CSS thường, tiền tố cdg-.
--}}
@php
    $tg = (int) ($usage['game'] ?? 0);
    $ts = (int) ($usage['study'] ?? 0);
    $tb = (int) ($usage['browser'] ?? 0);
    $tLimit = $policy->daily_game_minutes;
    $tDone = (int) ($doneToday ?? 0);
    $tName = trim((string) ($childName ?? '')) !== '' ? $childName : 'con';
    $tSite = collect($usage['topSites'] ?? [])->first(fn ($s) => ! str_contains((string) $s['name'], 'onthi360'));

    $tips = [];
    if ($tDone >= 3 || $ts >= 60) {
        $tips[] = ['ok', 'trophy', 'Hôm nay '.$tName.' học rất chăm',
            'Đã học '.$ts.' phút và nộp '.$tDone.' bài/đề trên onthi360. Hãy khen thật cụ thể (ví dụ “Bố mẹ thấy con làm xong '.max($tDone, 1).' bài rồi, giỏi quá!”) và có thể thưởng nhỏ: cho thêm 15 phút chơi, được chọn phim hoặc món ăn tối nay.'];
    }
    if ($tg >= 60 && $tg > max($ts, 1) * 2) {
        $tips[] = ['warn', 'zap', 'Thời gian chơi game đang nhiều hơn học',
            'Hôm nay con chơi game '.$tg.' phút, học '.$ts.' phút. '.($tLimit === null
                ? 'Nên đặt “Số phút chơi game mỗi ngày” ở phần Cài đặt bên dưới (ví dụ 60 phút ngày đi học, 90–120 phút cuối tuần) và nói trước với con.'
                : 'Giới hạn hiện tại là '.$tLimit.' phút/ngày. Nếu muốn giảm, hãy giảm dần 10–15 phút mỗi tuần thay vì cắt hẳn, để con không phản ứng.')];
    } elseif ($tLimit === null && $tg > 0) {
        $tips[] = ['info', 'timer', 'Chưa đặt giới hạn giờ chơi game',
            'Con đã chơi '.$tg.' phút hôm nay. Thống nhất với con một con số cụ thể rồi đặt ở phần Cài đặt bên dưới — máy sẽ báo trước 5 phút trước khi đóng game.'];
    }
    if ($tb >= 90) {
        $tips[] = ['warn', 'globe', 'Con lướt web khá nhiều',
            'Hôm nay con dùng trang web ngoài onthi360 khoảng '.$tb.' phút'.($tSite ? ', nhiều nhất là '.$tSite['name'].' ('.$tSite['minutes'].' phút)' : '').'. Trước khi chặn, hãy hỏi con đang xem gì và cùng thống nhất giờ được dùng mạng xã hội.'];
    }
    if ($tDone === 0 && (int) now()->format('G') >= 17) {
        $tips[] = ['info', 'book-open', 'Hôm nay con chưa nộp bài nào',
            'Nhắc nhẹ con làm 1–2 bài trên onthi360 trước giờ chơi. Ngồi cạnh con 10 phút đầu thường hiệu quả hơn nhắc nhiều lần.'];
    }
    if ($tips === []) {
        $tips[] = $tg + $ts + $tb === 0
            ? ['info', 'clock', 'Chưa có số liệu hôm nay', 'Khi máy của con bật và có Chocon Desktop, gợi ý sẽ tự hiện theo thời gian học / chơi thực tế của con.']
            : ['ok', 'check-circle-2', 'Hôm nay mọi thứ khá cân bằng', 'Thời gian học và giải trí của con đang hợp lý. Một lời khen buổi tối sẽ giúp con duy trì thói quen tốt.'];
    }
    $tips = array_slice($tips, 0, 3);

    $methods = [
        'game' => ['Con chơi game nhiều', 'zap', [
            'Thống nhất trước số phút chơi mỗi ngày cùng con, ghi ra giấy, rồi đặt đúng con số đó trong phần Cài đặt.',
            'Ngày đi học ít hơn, cuối tuần nhiều hơn (ví dụ 60 phút và 120 phút).',
            'Giảm dần mỗi tuần 10–15 phút thay vì cấm hẳn — cấm đột ngột dễ khiến con chơi lén.',
            'Không chơi game trong giờ ăn và 1 tiếng trước khi ngủ.',
            'Thay một phần giờ chơi bằng hoạt động khác: thể thao, đi dạo, chơi cùng bố mẹ.',
            'Khi con tự giác dừng đúng giờ, hãy khen ngay để con thấy việc giữ lời được ghi nhận.',
        ]],
        'web' => ['Con lướt web, mạng xã hội nhiều', 'globe', [
            'Xem mục “Hôm nay con dùng gì” bên dưới để biết con dùng trang nào nhiều, rồi hỏi con một cách nhẹ nhàng.',
            'Thống nhất khung giờ được dùng mạng xã hội, ví dụ 20:00–20:30 sau khi học xong.',
            'Bàn ăn và phòng ngủ là “vùng không màn hình”.',
            'Bật “Tắt chế độ ẩn danh” để con không dùng tab ẩn danh.',
            'Nói chuyện với con về an toàn trên mạng: không chia sẻ thông tin cá nhân, không gặp người lạ quen qua mạng.',
            'Bố mẹ làm gương: hạn chế dùng điện thoại khi ở cùng con.',
        ]],
        'reward' => ['Thưởng khi con học tốt', 'trophy', [
            'Khen ngợi cụ thể vào nỗ lực (“Con kiên trì sửa bài đến khi đúng”) thay vì chỉ khen “giỏi”.',
            'Thưởng bằng thời gian chơi thêm: con bấm “Xin thêm giờ” trên máy, bố mẹ duyệt ngay trên điện thoại.',
            'Ưu tiên phần thưởng là trải nghiệm: đi chơi cuối tuần, chọn phim gia đình, chọn món ăn, được rủ bạn đến nhà.',
            'Tích sao mỗi ngày đạt mục tiêu, đủ số sao thì đổi một phần thưởng lớn hơn đã hứa trước.',
            'Giữ lời hứa thưởng đúng hẹn — đó là điều giúp cách này có hiệu quả lâu dài.',
            'Hạn chế thưởng bằng tiền hoặc mua thêm vật phẩm trong game.',
        ]],
        'deal' => ['Thoả thuận gia đình', 'users', [
            'Cùng con ngồi lại viết “Thoả thuận dùng máy tính” — con được góp ý thì con sẽ tự giác hơn.',
            'Ghi rõ: giờ học, số phút chơi, cách được thưởng thêm, và điều gì xảy ra nếu vi phạm.',
            'Hình phạt nên nhẹ, rõ ràng và áp dụng nhất quán (ví dụ trừ 15 phút chơi ngày hôm sau).',
            'Dán thoả thuận ở góc học tập; xem lại cùng con mỗi tháng và điều chỉnh khi con lớn hơn.',
            'Cho con biết máy đang được quản lý bằng Chocon và vì sao — minh bạch giúp con tin bố mẹ.',
        ]],
    ];

    $rewardTable = [
        ['Nộp đủ 3 bài/đề trong ngày', '+15 phút chơi hoặc được chọn món ăn tối'],
        ['Học đủ 5 ngày trong tuần', 'Một buổi đi chơi / xem phim cuối tuần'],
        ['Điểm cao một đề thi trên onthi360', 'Khen trước cả nhà + một món quà nhỏ (sách, đồ dùng học tập)'],
        ['Cả tháng giữ đúng thoả thuận', 'Phần thưởng lớn đã hứa từ đầu tháng'],
    ];

    $dealText = "THOẢ THUẬN DÙNG MÁY TÍNH CỦA GIA ĐÌNH\n"
        ."1. Ngày đi học: học trước, chơi sau. Chơi game tối đa ___ phút/ngày; cuối tuần tối đa ___ phút/ngày.\n"
        ."2. Không dùng máy trong giờ ăn và sau ___ giờ tối.\n"
        ."3. Mỗi ngày làm ít nhất ___ bài/đề trên onthi360.\n"
        ."4. Làm đủ bài được thưởng: _______________________.\n"
        ."5. Cả tuần giữ đúng thoả thuận được thưởng: _______________________.\n"
        ."6. Nếu vi phạm: _______________________.\n"
        ."7. Bố mẹ và con cùng xem lại thoả thuận này vào ngày ___ hằng tháng.\n"
        ."Chữ ký của con: __________        Chữ ký của bố mẹ: __________";
@endphp

<style>
    .cdg-tips { display: grid; gap: 10px; grid-template-columns: 1fr; }
    @media (min-width: 900px) { .cdg-tips { grid-template-columns: repeat({{ count($tips) }}, minmax(0, 1fr)); } }
    .cdg-tip { display: flex; gap: 10px; padding: 12px 14px; border-radius: 16px; border: 1px solid #DCE9F1; background: #F7FBFD; }
    .cdg-tip.ok { border-color: #CDEBDD; background: #F2FBF6; }
    .cdg-tip.warn { border-color: #F2E1BC; background: #FFFAF0; }
    .cdg-ic { display: grid; place-items: center; width: 34px; height: 34px; flex: none; border-radius: 11px; background: #E3F2F8; color: #126F91; }
    .cdg-tip.ok .cdg-ic { background: #DCF5EA; color: #1F7A55; }
    .cdg-tip.warn .cdg-ic { background: #FDEFD2; color: #B7791F; }
    .cdg-ic svg { width: 17px; height: 17px; }
    .cdg-tip b { display: block; font-size: 13px; color: #123B68; }
    .cdg-tip p { margin: 3px 0 0; font-size: 12.5px; line-height: 1.6; color: #4A6378; }

    .cdg-tabs { display: flex; flex-wrap: wrap; gap: 6px; margin: 4px 0 12px; }
    .cdg-tab { display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 12px; border-radius: 11px; border: 1px solid #DCE9F1; background: #fff; font-size: 12.5px; font-weight: 700; color: #4A6378; cursor: pointer; }
    .cdg-tab svg { width: 15px; height: 15px; }
    .cdg-tab.on { border-color: #126F91; background: #EAF5F8; color: #0D5B77; box-shadow: inset 0 0 0 1px #126F91; }
    .cdg-list { margin: 0; padding: 0; list-style: none; display: grid; gap: 8px; grid-template-columns: 1fr; }
    @media (min-width: 768px) { .cdg-list { grid-template-columns: 1fr 1fr; } }
    .cdg-list li { display: flex; gap: 8px; align-items: flex-start; padding: 9px 11px; border-radius: 12px; background: #F8FBFD; font-size: 12.5px; line-height: 1.6; color: #33495C; }
    .cdg-list li svg { width: 15px; height: 15px; flex: none; margin-top: 3px; color: #2F8A6B; }

    .cdg-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 12.5px; }
    .cdg-table th { padding: 8px 10px; text-align: left; font-size: 11px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: #6B8196; background: #F3F8FB; }
    .cdg-table th:first-child { border-radius: 10px 0 0 10px; } .cdg-table th:last-child { border-radius: 0 10px 10px 0; }
    .cdg-table td { padding: 9px 10px; border-bottom: 1px dashed #E3ECF2; color: #33495C; vertical-align: top; }
    .cdg-table td:first-child { font-weight: 700; color: #123B68; }
    .cdg-table tr:last-child td { border-bottom: 0; }
    .cdg-deal { margin: 0; padding: 12px 14px; border-radius: 14px; border: 1px dashed #BFE0EC; background: #FBFDFE; font-family: inherit; font-size: 12.5px; line-height: 1.75; color: #33495C; white-space: pre-wrap; }
    .cdg-copy { display: inline-flex; align-items: center; gap: 6px; margin-top: 10px; height: 34px; padding: 0 14px; border-radius: 11px; border: 1px solid #CFE3EC; background: #fff; color: #126F91; font-size: 12.5px; font-weight: 800; cursor: pointer; }
    .cdg-copy svg { width: 14px; height: 14px; }
    .cdg-note { margin: 10px 0 0; font-size: 11.5px; color: #7B8EA0; }
</style>

{{-- ===== Gợi ý hôm nay ===== --}}
<section class="pw-card">
    @include('parent._ct', ['icon' => 'lightbulb', 'title' => 'Gợi ý cho bố mẹ hôm nay'])
    <div class="cdg-tips">
        @foreach ($tips as [$tTone, $tIcon, $tTitle, $tText])
            <div class="cdg-tip {{ $tTone }}">
                <span class="cdg-ic"><x-lucide :name="$tIcon" /></span>
                <div><b>{{ $tTitle }}</b><p>{{ $tText }}</p></div>
            </div>
        @endforeach
    </div>
</section>

{{-- ===== Phương pháp đồng hành + Bảng thưởng ===== --}}
<div class="pw-cols2">
    <section class="pw-card" x-data="{ tab: 'game' }">
        @include('parent._ct', ['icon' => 'heart', 'title' => 'Phương pháp đồng hành cùng con'])
        <div class="cdg-tabs" role="tablist">
            @foreach ($methods as $mKey => [$mLabel, $mIcon])
                <button type="button" class="cdg-tab" :class="{ on: tab === '{{ $mKey }}' }" @click="tab = '{{ $mKey }}'" role="tab"><x-lucide :name="$mIcon" />{{ $mLabel }}</button>
            @endforeach
        </div>
        @foreach ($methods as $mKey => [$mLabel, $mIcon, $mItems])
            <ul class="cdg-list" x-show="tab === '{{ $mKey }}'" @if ($mKey !== 'game') style="display:none" @endif>
                @foreach ($mItems as $mItem)
                    <li><x-lucide name="check-circle-2" /><span>{{ $mItem }}</span></li>
                @endforeach
            </ul>
        @endforeach
    </section>

    <section class="pw-card" x-data="{ copied: false }">
        @include('parent._ct', ['icon' => 'award', 'title' => 'Bảng thưởng gợi ý'])
        <table class="cdg-table">
            <thead><tr><th>Khi con…</th><th>Phần thưởng gợi ý</th></tr></thead>
            <tbody>
                @foreach ($rewardTable as [$rWhen, $rGift])
                    <tr><td>{{ $rWhen }}</td><td>{{ $rGift }}</td></tr>
                @endforeach
            </tbody>
        </table>

        <p class="cd-sec">Thoả thuận gia đình mẫu</p>
        <pre class="cdg-deal" x-ref="deal">{{ $dealText }}</pre>
        <button type="button" class="cdg-copy"
                @click="navigator.clipboard && navigator.clipboard.writeText($refs.deal.innerText).then(() => { copied = true; setTimeout(() => copied = false, 2000); })">
            <x-lucide name="copy" /><span x-text="copied ? 'Đã sao chép' : 'Sao chép để in / gửi Zalo'">Sao chép để in / gửi Zalo</span>
        </button>
        <p class="cdg-note">Điền các chỗ trống cùng con, in ra và dán ở góc học tập. Phần thưởng nên thống nhất trước và giữ đúng lời hứa.</p>
    </section>
</div>
