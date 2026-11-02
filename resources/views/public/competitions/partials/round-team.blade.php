{{--
    SỬA 2/11 — khối "Người tổ chức & làm bù" ở tab Tổng quan của ban tổ chức (dữ liệu: ContestRoundTeamService::panel()).
      · Giáo viên cùng tổ chức: chỉ người chủ trì kỳ / admin thêm, gỡ. Người được thêm sửa được kỳ (trừ xoá kỳ).
      · Làm bù: kỳ đã kết thúc → chọn học sinh chưa nộp bài, đặt hạn; bài làm bù chấm và xếp hạng như bình thường.
--}}
@php
    $t = $roundTeam;
    $mk = $t['makeup'];
    $box = 'margin:14px 0 4px;padding:12px 14px;border:1px solid #d6e3ec;border-radius:12px;background:#fff';
    $ico = 'display:grid;place-items:center;width:34px;height:34px;border-radius:10px;background:#eef4fb;color:#1d4f86;flex:none';
    $btnSm = 'height:28px;padding:0 10px;border:1px solid #e3c9c9;border-radius:8px;background:#fff;color:#a33b3b;font-size:12px;cursor:pointer';
    $input = 'height:36px;border:1px solid #d6e3ec;border-radius:9px;padding:0 10px;font-size:12px;color:#123b68;background:#fff';
    $muted = 'font-size:12px;line-height:1.5;color:#61798b';
    $stateColor = ['done' => '#1b7a43', 'doing' => '#126f91', 'expired' => '#a33b3b', 'waiting' => '#8a6d1d'];
@endphp
<div id="to-chuc-ky" style="{{ $box }}">
    <div style="display:flex;align-items:center;gap:10px 14px;flex-wrap:wrap">
        <span style="{{ $ico }}"><x-lucide name="users" style="width:18px;height:18px" /></span>
        <span style="flex:1 1 260px;min-width:0">
            <b style="display:block;font-size:13px;color:#123b68">Người tổ chức {{ $survey ? 'kỳ khảo sát' : 'kỳ thi' }} này</b>
            <span style="display:block;margin-top:2px;{{ $muted }}">Chỉ người chủ trì, giáo viên được thêm vào bên dưới và admin mới sửa được kỳ này (đề, học sinh, làm bù…). Giáo viên khác muốn tham gia phải được người chủ trì thêm vào.</span>
        </span>
    </div>

    <ul style="list-style:none;margin:10px 0 0;padding:0;display:grid;gap:6px">
        <li style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:13px;color:#123b68">
            <span style="padding:2px 8px;border-radius:999px;background:#e0f0f6;color:#126f91;font-size:11px;font-weight:600">Chủ trì</span>
            <b>{{ $t['ownerName'] }}</b>
            @if ($t['ownerIsLegacy'])<span style="{{ $muted }}">(kỳ cũ — giáo viên tổ chức cuộc thi quản lý)</span>@endif
        </li>
        @foreach ($t['managers'] as $m)
            <li style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:13px;color:#123b68">
                <span style="padding:2px 8px;border-radius:999px;background:#f1f5f9;color:#475569;font-size:11px;font-weight:600">Cùng tổ chức</span>
                <b>{{ $m['name'] }}</b>@if ($m['account'] !== '')<span style="{{ $muted }}">{{ $m['account'] }}</span>@endif
                @if ($t['canOwn'])
                    <form method="POST" action="{{ $m['removeUrl'] }}" style="margin-left:auto" onsubmit="return confirm('Gỡ {{ addslashes($m['name']) }} khỏi ban tổ chức kỳ này?');">
                        @csrf
                        <button type="submit" style="{{ $btnSm }}">Gỡ</button>
                    </form>
                @endif
            </li>
        @endforeach
    </ul>

    @if (! $t['supported'])
        <p style="margin:8px 0 0;{{ $muted }}">Máy chủ chưa chạy migration — chạy "php artisan migrate" để thêm người cùng tổ chức.</p>
    @elseif ($t['canOwn'])
        <form method="POST" action="{{ $t['addUrl'] }}" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px">
            @csrf
            <select name="user_id" required aria-label="Chọn giáo viên cùng tổ chức" style="{{ $input }};flex:1 1 240px;max-width:420px">
                <option value="">— Chọn giáo viên để thêm vào —</option>
                @foreach ($t['teacherOptions'] as $opt)
                    <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                @endforeach
            </select>
            <button type="submit" class="hub-primary-action" style="display:inline-flex;align-items:center;gap:6px" @disabled($t['teacherOptions'] === [])><x-lucide name="user-plus" style="width:15px;height:15px" />Thêm người cùng tổ chức</button>
        </form>
    @else
        <p style="margin:8px 0 0;{{ $muted }}">Bạn đang là người cùng tổ chức — chỉ người chủ trì hoặc admin thêm / gỡ người cùng tổ chức và xoá kỳ.</p>
    @endif
</div>

<div id="lam-bu" style="{{ $box }}">
    <div style="display:flex;align-items:center;gap:10px 14px;flex-wrap:wrap">
        <span style="{{ $ico }};background:#fff4e0;color:#9a6200"><x-lucide name="clock-3" style="width:18px;height:18px" /></span>
        <span style="flex:1 1 260px;min-width:0">
            <b style="display:block;font-size:13px;color:#123b68">Cho học sinh làm bù</b>
            <span style="display:block;margin-top:2px;{{ $muted }}">
                @if ($mk['ended'])
                    Kỳ đã kết thúc. Chọn học sinh quên / chưa làm bài và đặt hạn — các em vào làm như lúc kỳ đang mở{{ $survey ? ' (không cần nhập mật khẩu)' : '' }}. Bài làm bù được chấm, xếp hạng và in bảng điểm như bình thường.
                @else
                    Khi kỳ kết thúc, bạn có thể cho học sinh quên / chưa làm bài được làm bù ở đây.
                @endif
            </span>
        </span>
    </div>

    @if (! $mk['supported'])
        <p style="margin:8px 0 0;{{ $muted }}">Máy chủ chưa chạy migration — chạy "php artisan migrate" để dùng tính năng làm bù.</p>
    @else
        @if ($mk['rows'] !== [])
            <div style="margin-top:10px;overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:12px;color:#123b68">
                    <thead><tr style="text-align:left;color:#61798b">
                        <th style="padding:6px 8px;border-bottom:1px solid #e5edf3">Học sinh</th>
                        <th style="padding:6px 8px;border-bottom:1px solid #e5edf3">Hạn làm bù</th>
                        <th style="padding:6px 8px;border-bottom:1px solid #e5edf3">Trạng thái</th>
                        <th style="padding:6px 8px;border-bottom:1px solid #e5edf3"></th>
                    </tr></thead>
                    <tbody>
                        @foreach ($mk['rows'] as $r)
                            <tr>
                                <td style="padding:6px 8px;border-bottom:1px solid #f0f4f7"><b>{{ $r['name'] }}</b>@if ($r['account'] !== '')<br><span style="color:#61798b">{{ $r['account'] }}</span>@endif</td>
                                <td style="padding:6px 8px;border-bottom:1px solid #f0f4f7;white-space:nowrap">{{ $r['deadline'] }}@if ($r['addedBy'])<br><span style="color:#61798b">bởi {{ $r['addedBy'] }}</span>@endif</td>
                                <td style="padding:6px 8px;border-bottom:1px solid #f0f4f7;white-space:nowrap;font-weight:600;color:{{ $stateColor[$r['state']] }}">{{ $r['stateLabel'] }}</td>
                                <td style="padding:6px 8px;border-bottom:1px solid #f0f4f7;text-align:right">
                                    @if ($r['state'] !== 'done')
                                        <form method="POST" action="{{ $r['removeUrl'] }}" onsubmit="return confirm('Huỷ quyền làm bù của {{ addslashes($r['name']) }}?');">
                                            @csrf
                                            <button type="submit" style="{{ $btnSm }}">Huỷ</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($mk['ended'])
            {{-- SỬA 2/11 (khách: "cho chọn học sinh đơn giản thôi với thời gian bù là được") — tìm tên → tick → chọn hạn → Cho làm bù. --}}
            <form method="POST" action="{{ $mk['addUrl'] }}" style="margin-top:12px;display:grid;gap:10px" x-data="{ q: '' }">
                @csrf
                @if ($mk['candidates'] !== [])
                    <input type="search" x-model="q" placeholder="Tìm tên học sinh…" aria-label="Tìm tên học sinh" style="{{ $input }};width:100%;max-width:360px">
                    <div style="max-height:240px;overflow:auto;border:1px solid #e5edf3;border-radius:10px">
                        @foreach ($mk['candidates'] as $cnd)
                            <div data-k="{{ mb_strtolower($cnd['name'].' '.$cnd['account'].' '.$cnd['className']) }}" x-show="q === '' || $el.dataset.k.includes(q.toLowerCase())" style="border-bottom:1px solid #f0f4f7">
                                <label style="display:flex;align-items:center;gap:8px;padding:7px 10px;font-size:13px;color:#123b68;cursor:pointer">
                                    <input type="checkbox" name="student_ids[]" value="{{ $cnd['id'] }}" @checked(in_array((string) $cnd['id'], (array) old('student_ids', []), true))>
                                    <span><b>{{ $cnd['name'] }}</b>@if ($cnd['className'] !== '' || $cnd['account'] !== '') <small style="color:#8597a6">· {{ $cnd['className'] !== '' ? $cnd['className'] : $cnd['account'] }}</small>@endif</span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p style="margin:0;{{ $muted }}">Không còn học sinh nào chưa nộp bài để cho làm bù.</p>
                @endif

                <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:end">
                    <label style="display:grid;gap:4px;font-size:12px;color:#123b68">
                        <span>Thời gian làm bù đến</span>
                        <input type="datetime-local" name="deadline_at" required value="{{ old('deadline_at', $mk['defaultDeadline']) }}" min="{{ $mk['minDeadline'] }}" style="{{ $input }}">
                    </label>
                    <button type="submit" class="hub-primary-action" @disabled($mk['candidates'] === []) style="display:inline-flex;align-items:center;gap:6px"><x-lucide name="clock-3" style="width:15px;height:15px" />Cho làm bù</button>
                </div>
            </form>
            <script>
                window.makeupPicker = window.makeupPicker || function (cfg) {
                    return {
                        q: '', hits: [], picked: [], loading: false, seq: 0,
                        async search() {
                            const q = this.q.trim(); if (q === '') { this.hits = []; return; }
                            const my = ++this.seq; this.loading = true;
                            try {
                                const r = await fetch(cfg.url + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                                const data = r.ok ? await r.json() : { students: [] };
                                if (my !== this.seq) return;
                                this.hits = (data.students || []).filter((s) => !this.picked.some((p) => p.id === s.id));
                            } catch (e) { if (my === this.seq) this.hits = []; }
                            if (my === this.seq) this.loading = false;
                        },
                        add(s) { if (!this.picked.some((p) => p.id === s.id)) this.picked.push(s); this.q = ''; this.hits = []; },
                        remove(id) { this.picked = this.picked.filter((p) => p.id !== id); },
                    };
                };
            </script>
        @endif
    @endif
</div>
