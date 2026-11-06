{{-- SỬA 6/11 (khách: "in kết quả, nhận xét") — PHIẾU KẾT QUẢ HỌC TẬP (trang in riêng, không khung web). Một em (?student=) hoặc cả lớp
     (mỗi em 1 trang A4). Dữ liệu: ClassSpaceService::reportCards(). Mở là tự bật hộp thoại in. --}}
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Phiếu kết quả học tập — {{ $classRoom->name }}</title>
    @include('partials.brand-font')
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #EEF2F6; font-family: "Be Vietnam Pro", system-ui, sans-serif; color: #1E293B; font-size: 13px; }
        .bar { position: sticky; top: 0; display: flex; align-items: center; gap: 10px; padding: 12px 20px; background: #0F2F57; color: #fff; z-index: 2; }
        .bar b { flex: 1; font-size: 14px; }
        .bar button, .bar a { height: 36px; padding: 0 16px; border: 0; border-radius: 10px; background: #fff; color: #0F2F57; font: inherit; font-weight: 800; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
        .page { width: 210mm; min-height: 297mm; margin: 18px auto; padding: 16mm 15mm; background: #fff; box-shadow: 0 10px 30px rgba(15, 42, 74, .12); }
        .hd { display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 12px; border-bottom: 3px solid #176B50; }
        .brand { font-size: 20px; font-weight: 900; color: #1D4ED8; } .brand span { color: #F97316; }
        .hd small { display: block; color: #64748B; font-size: 11.5px; }
        h1 { margin: 18px 0 4px; text-align: center; font-size: 20px; letter-spacing: .02em; color: #0F2F57; }
        .sub { text-align: center; color: #475569; margin-bottom: 16px; }
        .info { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 20px; padding: 12px 14px; border: 1px solid #E2E8F0; border-radius: 10px; background: #F8FAFC; }
        .info div span { color: #64748B; } .info div b { color: #0F172A; }
        h2 { margin: 18px 0 8px; font-size: 14px; color: #176B50; text-transform: uppercase; letter-spacing: .04em; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 7px 9px; border: 1px solid #CBD5E1; text-align: left; }
        th { background: #F1F5F9; font-size: 12px; }
        td.c, th.c { text-align: center; }
        .sum { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 10px; }
        .sum div { padding: 10px; border: 1px solid #E2E8F0; border-radius: 10px; text-align: center; }
        .sum b { display: block; font-size: 20px; color: #0F2F57; } .sum span { font-size: 11.5px; color: #64748B; }
        .comment { min-height: 70px; padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 10px; line-height: 1.6; white-space: pre-line; }
        .sign { display: grid; grid-template-columns: 1fr 1fr; margin-top: 26px; text-align: center; }
        .sign p { margin: 0 0 60px; font-weight: 700; } .sign small { color: #64748B; }
        .empty { text-align: center; color: #94A3B8; }
        @media print {
            @page { size: A4 portrait; margin: 0; }
            body { background: #fff; } .bar { display: none; }
            .page { margin: 0; box-shadow: none; page-break-after: always; }
            .page:last-child { page-break-after: auto; }
        }
    </style>
</head>
<body>
    <div class="bar">
        <b>Phiếu kết quả học tập · {{ $classRoom->name }} · {{ count($cards) }} học sinh</b>
        <a href="{{ route('teacher.classes.show', ['class' => $classRoom->id, 'tab' => 'results']) }}">← Về lớp</a>
        <button type="button" onclick="window.print()">In phiếu</button>
    </div>
    @forelse ($cards as $card)
        @php
            $att = $card['att'];
            $attTotal = array_sum($att);
        @endphp
        <section class="page">
            <div class="hd">
                <div><div class="brand">Ôn Thi <span>360</span></div><small>Nền tảng học &amp; luyện thi trực tuyến</small></div>
                <div style="text-align:right"><small>Ngày in: {{ $printedAt }}</small><small>Mã lớp: {{ $classRoom->code }}</small></div>
            </div>
            <h1>PHIẾU KẾT QUẢ HỌC TẬP</h1>
            <div class="sub">{{ $classRoom->name }}</div>
            <div class="info">
                <div><span>Học sinh:</span> <b>{{ $card['name'] }}</b></div>
                <div><span>Lớp:</span> <b>{{ $classRoom->name }}</b></div>
                <div style="grid-column: 1 / -1"><span>Giáo viên:</span> <b>{{ implode(', ', $teachers) ?: '—' }}</b></div>
            </div>

            <div class="sum">
                <div><b>{{ $card['avg'] !== null ? number_format($card['avg'], 1) : '—' }}</b><span>Điểm trung bình (thang 10)</span></div>
                <div><b>{{ $card['rank'][0] ?? '—' }}</b><span>Xếp loại</span></div>
                <div><b>{{ $card['attRate'] !== null ? $card['attRate'].'%' : '—' }}</b><span>Tỉ lệ đi học</span></div>
            </div>

            <h2>Điểm các bài</h2>
            <table>
                <thead><tr><th class="c" style="width:40px">#</th><th>Bài / đề</th><th class="c" style="width:90px">Điểm</th><th>Nhận xét</th></tr></thead>
                <tbody>
                    @forelse ($cols as $i => $c)
                        @php $cell = $card['cells'][$c['id']] ?? null; @endphp
                        <tr>
                            <td class="c">{{ $i + 1 }}</td>
                            <td>{{ $c['title'] }}</td>
                            <td class="c"><b>{{ $cell && $cell['score'] !== null ? number_format($cell['score'], 1) : ($cell && $cell['pending'] ? 'Chờ chấm' : 'Chưa nộp') }}</b></td>
                            <td>{{ $cell['comment'] ?? '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">Lớp chưa có bài giao nào.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <h2>Chuyên cần</h2>
            <table>
                <thead><tr>@foreach ($statusLabels as $l)<th class="c">{{ $l }}</th>@endforeach<th class="c">Tổng buổi đã điểm danh</th></tr></thead>
                <tbody><tr>@foreach ($statusLabels as $k => $l)<td class="c">{{ $att[$k] ?? 0 }}</td>@endforeach<td class="c"><b>{{ $attTotal }}</b></td></tr></tbody>
            </table>

            <h2>Nhận xét của giáo viên</h2>
            <div class="comment">{{ $card['note'] !== '' ? $card['note'] : ' ' }}</div>

            <div class="sign">
                <div><p>Phụ huynh học sinh</p><small>(Ký, ghi rõ họ tên)</small></div>
                <div><p>Giáo viên phụ trách</p><small>(Ký, ghi rõ họ tên)</small></div>
            </div>
        </section>
    @empty
        <section class="page"><p class="empty">Lớp chưa có học sinh.</p></section>
    @endforelse
    <script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });</script>
</body>
</html>
