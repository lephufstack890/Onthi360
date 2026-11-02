@extends('layouts.teacher')

@section('title', 'Điểm danh')
@section('page-title', 'Điểm danh')

{{--
    SỬA 3/11 (khách: "làm lại trang chi tiết điểm danh của giáo viên theo UI này cho đẹp") — CHỈ ĐỔI GIAO DIỆN: dải đầu trang có ảnh
    lớp học, bảng điểm danh nút tròn lớn, thẻ Tổng kết buổi học, khối Hoạt động buổi học (phát / thu hồi, bài giao, thêm bài giao,
    tạo hoạt động). Mọi form, tên ô, route, luật giữ nguyên như bản cũ (_to_delete_claude/before-attendance-ui/attendance.blade.php).
    CSS riêng tiền tố tda- (Tailwind không build lại trên máy chủ).
--}}
@section('content')
    @php
        $rows = $rows ?? [];
        $sessionResources = $sessionResources ?? [];
        $materialOptions = $materialOptions ?? [];
        $questionOptions = $questionOptions ?? [];
        $assessmentOptions = $assessmentOptions ?? [];
        $sessionActivities = $sessionActivities ?? [];
        $looseResources = $looseResources ?? [];
        $publishedCount = collect($sessionActivities)->where('published', true)->count();
        $resourceIcons = ['assessment' => 'clipboard-list', 'material' => 'book-open', 'question' => 'help-circle', 'video' => 'play', 'link' => 'external-link', 'note' => 'file-text'];
        $tdaSub = trim(($classRoom->name ?? '').' – '.($session->starts_at?->format('d/m/Y H:i') ?? '').($session->topic ? ' · '.$session->topic : ''), ' –');
        $tdaStatuses = ['present' => 'Có mặt', 'absent' => 'Vắng', 'excused' => 'Vắng có phép', 'late' => 'Đi trễ'];

        // SỬA 3/11 — nút "Quay lại" về đúng trang trước (Lịch / chi tiết lớp / Tổng quan…). Trang trước được nhớ theo từng buổi học
        // trong session, nên sau khi bấm Lưu (trang tự tải lại) nút vẫn quay về đúng chỗ cũ. Không có thì về Lịch như trước.
        $tdaBackKey = 'tda_back_'.$session->id;
        $tdaPrev = (string) url()->previous();
        $tdaPrevPath = rtrim((string) parse_url($tdaPrev, PHP_URL_PATH), '/');
        if ($tdaPrev !== ''
            && parse_url($tdaPrev, PHP_URL_HOST) === request()->getHost()
            && $tdaPrevPath !== rtrim('/'.request()->path(), '/')
            && ! str_contains($tdaPrevPath, '/login')) {
            session()->put($tdaBackKey, $tdaPrev);
        }
        $tdaBackUrl = session($tdaBackKey, route('teacher.schedule.index'));
    @endphp

    <style>
        .tda { --ink: #0F2A4A; --muted: #64748B; --line: #E3ECF5; }
        .tda a { text-decoration: none; }
        .tda-back { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 10px; font-size: 12.5px; font-weight: 700; color: #334155; }
        .tda-back:hover { color: #1D4ED8; } .tda-back svg { width: 15px; height: 15px; }
        .tda-hero { position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: center; min-height: 104px; padding: 16px 22px; border-radius: 18px; color: #fff; margin-bottom: 14px;
            background: linear-gradient(90deg, #1D4ED8 0%, rgba(37, 99, 235, .92) 38%, rgba(59, 130, 246, .35) 70%, rgba(59, 130, 246, .15) 100%), url('{{ asset('assets/hero-courses.jpg') }}') right center / cover no-repeat, #2563EB;
            box-shadow: 0 10px 26px rgba(29, 78, 216, .22); }
        .tda-kicker { align-self: flex-start; padding: 3px 10px; border-radius: 999px; background: rgba(255, 255, 255, .2); font-size: 10.5px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
        .tda-hero h1 { margin: 6px 0 0; font-size: 22px; font-weight: 800; letter-spacing: -.01em; color: #fff; }
        .tda-hero p { display: flex; align-items: center; gap: 7px; margin: 4px 0 0; font-size: 13px; font-weight: 600; color: rgba(255, 255, 255, .92); }
        .tda-hero p svg { width: 15px; height: 15px; }
        .tda-card { border: 1px solid var(--line); border-radius: 16px; background: #fff; box-shadow: 0 4px 14px rgba(15, 42, 74, .05); }
        .tda-card + .tda-card { margin-top: 14px; }
        .tda-pad { padding: 14px 16px; }
        .tda-table-wrap { overflow-x: auto; }
        .tda-table { width: 100%; min-width: 900px; border-collapse: collapse; }
        .tda-table th { padding: 11px 14px; border-bottom: 1px solid #EDF2F7; text-align: left; font-size: 11px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: #475569; background: #FAFCFE; }
        .tda-table th.c, .tda-table td.c { text-align: center; }
        .tda-table td { padding: 10px 14px; border-bottom: 1px solid #F1F5F9; vertical-align: top; }
        .tda-table tr:last-child td { border-bottom: 0; }
        .tda-table tbody tr:hover td { background: #FBFDFF; }
        .tda-table th + th, .tda-table td + td { border-left: 1px solid #F1F5F9; }
        .tda-stu { display: flex; align-items: center; gap: 10px; }
        .tda-stu b { display: block; font-size: 13px; font-weight: 700; color: var(--ink); }
        .tda-stu small { display: block; margin-top: 2px; font-size: 11px; font-weight: 700; color: #16A34A; }
        .tda-radio { appearance: none; -webkit-appearance: none; width: 22px; height: 22px; margin: 4px 0 0; border: 2px solid #CBD5E1; border-radius: 50%; background: #fff; cursor: pointer; transition: border-color .15s, box-shadow .15s; }
        .tda-radio:hover { border-color: #93C5FD; }
        .tda-radio:checked { border-color: #2563EB; background: radial-gradient(circle, #2563EB 0 5px, #fff 6px); box-shadow: 0 0 0 4px rgba(37, 99, 235, .12); }
        .tda-radio.is-absent:checked { border-color: #DC2626; background: radial-gradient(circle, #DC2626 0 5px, #fff 6px); box-shadow: 0 0 0 4px rgba(220, 38, 38, .12); }
        .tda-radio.is-excused:checked { border-color: #D97706; background: radial-gradient(circle, #D97706 0 5px, #fff 6px); box-shadow: 0 0 0 4px rgba(217, 119, 6, .12); }
        .tda-radio.is-late:checked { border-color: #7C3AED; background: radial-gradient(circle, #7C3AED 0 5px, #fff 6px); box-shadow: 0 0 0 4px rgba(124, 58, 237, .12); }
        .tda-ta { box-sizing: border-box; width: 100%; min-width: 210px; min-height: 58px; padding: 8px 10px; border: 1px solid #DCE6F2; border-radius: 10px; background: #fff; font-size: 12.5px; line-height: 1.45; color: var(--ink); resize: vertical; }
        .tda textarea, .tda input, .tda select, .tda button { font-family: inherit; }
        .tda-ta:focus, .tda-in:focus { outline: 0; border-color: #3B82F6; box-shadow: 0 0 0 3px rgba(59, 130, 246, .15); }
        .tda-ta.is-amber:focus { border-color: #F59E0B; box-shadow: 0 0 0 3px rgba(245, 158, 11, .15); }
        .tda-empty { padding: 22px; text-align: center; font-size: 13px; color: var(--muted); }
        .tda-btn { display: inline-flex; align-items: center; gap: 6px; height: 36px; padding: 0 14px; border: 0; border-radius: 11px; font-size: 12.5px; font-weight: 800; cursor: pointer; white-space: nowrap; }
        .tda-btn svg { width: 15px; height: 15px; }
        .tda-btn.is-blue { background: linear-gradient(135deg, #2563EB, #1D4ED8); color: #fff; box-shadow: 0 6px 16px rgba(37, 99, 235, .25); }
        .tda-btn.is-line { background: #fff; color: #334155; border: 1px solid #DCE6F2; }
        .tda-btn.is-line:hover { border-color: #93C5FD; color: #1D4ED8; }
        .tda-btn.is-dark { background: #1E293B; color: #fff; } .tda-btn.is-dark:disabled { opacity: .5; cursor: not-allowed; }
        .tda-btn.is-green { background: #16A34A; color: #fff; }
        .tda-btn.is-green-line { background: #fff; color: #15803D; border: 1px solid #86EFAC; }
        .tda-btn.is-violet { background: linear-gradient(135deg, #8B5CF6, #7C3AED); color: #fff; box-shadow: 0 6px 16px rgba(124, 58, 237, .25); }
        .tda-icon-btn { display: grid; place-items: center; width: 34px; height: 34px; border: 0; border-radius: 10px; background: transparent; color: #94A3B8; cursor: pointer; }
        .tda-icon-btn:hover { background: #FEF2F2; color: #DC2626; } .tda-icon-btn svg { width: 16px; height: 16px; }
        .tda-save { margin: 12px 0 14px; }
        .tda-head { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 12px; }
        .tda-ic { display: grid; place-items: center; width: 40px; height: 40px; flex: none; border-radius: 12px; background: #EDE7FE; color: #7C3AED; }
        .tda-ic svg { width: 19px; height: 19px; }
        .tda-head h3 { margin: 0; font-size: 15px; font-weight: 800; color: var(--ink); }
        .tda-head p { margin: 2px 0 0; font-size: 12px; color: var(--muted); }
        .tda-pills { display: flex; gap: 6px; margin-left: auto; }
        .tda-pill { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 999px; background: #F1F5F9; color: #334155; font-size: 11.5px; font-weight: 800; }
        .tda-pill.is-green { background: #E3F6EC; color: #15803D; } .tda-pill svg { width: 13px; height: 13px; }
        .tda-summary { box-sizing: border-box; width: 100%; min-height: 70px; margin-top: 12px; padding: 10px 12px; border: 1px solid #DCE6F2; border-radius: 12px; font-size: 13px; color: var(--ink); resize: vertical; }
        .tda-summary:focus { outline: 0; border-color: #8B5CF6; box-shadow: 0 0 0 3px rgba(139, 92, 246, .15); }
        .tda-acts { display: grid; gap: 10px; margin-top: 12px; }
        .tda-act { border: 1px solid #E3ECF5; border-radius: 14px; overflow: hidden; background: #fff; }
        .tda-act.is-live { border-color: #86EFAC; background: #F3FBF6; }
        .tda-act-top { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; padding: 12px 14px; }
        .tda-act-title { flex: 1 1 260px; min-width: 0; }
        .tda-act-title .row { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 8px; }
        .tda-act-title b { font-size: 14px; font-weight: 800; color: var(--ink); }
        .tda-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 9px; border-radius: 999px; font-size: 11px; font-weight: 800; }
        .tda-badge svg { width: 12px; height: 12px; }
        .tda-badge.is-live { background: #DCFCE7; color: #15803D; } .tda-badge.is-draft { background: #E2E8F0; color: #475569; }
        .tda-act-title .cnt { font-size: 11.5px; color: var(--muted); padding-left: 8px; border-left: 1px solid #CBD5E1; }
        .tda-act-title .note { margin: 3px 0 0; font-size: 12px; color: #475569; }
        .tda-act-title .when { display: flex; align-items: center; gap: 4px; margin: 3px 0 0; font-size: 11.5px; font-weight: 700; color: #15803D; }
        .tda-act-title .when svg { width: 13px; height: 13px; }
        .tda-act-body { padding: 0 14px 12px; }
        .tda-res { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border: 1px solid #EDF2F7; border-radius: 12px; background: #fff; }
        .tda-res + .tda-res { margin-top: 6px; }
        .tda-res-ic { display: grid; place-items: center; width: 32px; height: 32px; flex: none; border-radius: 10px; background: #F1F5F9; color: #475569; }
        .tda-res-ic svg { width: 16px; height: 16px; }
        .tda-res-txt { flex: 1; min-width: 0; }
        .tda-res-txt b { display: block; font-size: 13px; font-weight: 700; color: var(--ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .tda-res-txt small { display: block; font-size: 11.5px; color: var(--muted); }
        .tda-warn { display: inline-flex; align-items: center; gap: 4px; margin-left: 6px; padding: 1px 7px; border-radius: 6px; background: #FEF3C7; color: #B45309; font-size: 10.5px; font-weight: 800; }
        .tda-warn svg { width: 12px; height: 12px; }
        .tda-link { border: 0; background: none; font-size: 12px; font-weight: 700; color: #94A3B8; cursor: pointer; }
        .tda-link:hover { color: #DC2626; }
        .tda-addres { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 8px; margin-top: 10px; }
        .tda-addres > div { flex: 1 1 240px; min-width: 0; }
        .tda-label { display: block; margin-bottom: 4px; font-size: 11.5px; font-weight: 700; color: #334155; }
        .tda-in { box-sizing: border-box; width: 100%; height: 38px; padding: 0 12px; border: 1px solid #DCE6F2; border-radius: 10px; background: #fff; font-size: 12.5px; color: var(--ink); }
        .tda-new { margin-top: 12px; padding: 12px 14px; border: 1.5px dashed #C4B5FD; border-radius: 14px; background: #FAF7FF; }
        .tda-new-title { display: flex; align-items: center; gap: 6px; margin: 0 0 8px; font-size: 13px; font-weight: 800; color: #6D28D9; }
        .tda-new-title svg { width: 15px; height: 15px; }
        .tda-new-row { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px; }
        .tda-new-row > div { flex: 1 1 220px; min-width: 0; }
        .tda-loose { margin-top: 12px; padding: 12px 14px; border: 1px solid #FDE68A; border-radius: 14px; background: #FFFBEB; }
        .tda-loose b.t { font-size: 13px; color: #B45309; } .tda-loose p { margin: 2px 0 8px; font-size: 11.5px; color: #B45309; }
        .tda-noact { padding: 20px; border: 1.5px dashed #D6E3EF; border-radius: 14px; text-align: center; font-size: 13px; color: var(--muted); }
        @media (max-width: 640px) { .tda-hero { padding: 14px 16px; min-height: 90px; } .tda-hero h1 { font-size: 19px; } .tda-pills { margin-left: 0; } }
    </style>

    <div class="tda">
    <a href="{{ $tdaBackUrl }}" class="tda-back"><x-lucide name="arrow-left" />Quay lại</a>

    <section class="tda-hero">
        <span class="tda-kicker">Không gian giảng dạy</span>
        <h1>Điểm danh</h1>
        <p><x-lucide name="calendar-days" />{{ $tdaSub }}</p>
    </section>

    @if (session('status') === 'attendance-saved')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu điểm danh.'])
    @elseif (session('status') === 'summary-saved')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu tổng kết buổi học.'])
    @elseif (session('status') === 'resource-added')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã gắn tài nguyên vào buổi học.'])
    @elseif (session('status') === 'resource-removed')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã gỡ tài nguyên khỏi buổi học.'])
    @elseif (session('status') === 'activity-created')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tạo hoạt động. Thêm tài nguyên vào rồi bấm ▶ để phát cho học sinh.'])
    @elseif (session('status') === 'activity-published')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã phát hoạt động — học sinh đã thấy được.'])
    @elseif (session('status') === 'activity-unpublished')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã thu hồi hoạt động — học sinh không còn thấy nữa.'])
    @elseif (session('status') === 'activity-deleted')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã xoá hoạt động cùng tài nguyên bên trong.'])
    @endif
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    {{-- ══════ BẢNG ĐIỂM DANH ══════ --}}
    <form method="POST" action="{{ route('teacher.schedule.attendance.save', $session->id) }}">
        @csrf
        <div class="tda-card tda-table-wrap">
            <table class="tda-table">
                <thead>
                    <tr>
                        <th>Học sinh</th>
                        @foreach ($tdaStatuses as $label)<th class="c">{{ $label }}</th>@endforeach
                        <th>Nhận xét</th>
                        <th>Em cần học thêm</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td>
                                <div class="tda-stu">
                                    <x-ws.avatar :name="$r['name']" size="md" />
                                    <div>
                                        <b>{{ $r['name'] }}</b>
                                        @if ($r['source'] === 'auto')<small>✓ Tự động (đã vào làm bài)</small>@endif
                                    </div>
                                </div>
                            </td>
                            @foreach ($tdaStatuses as $value => $label)
                                <td class="c">
                                    <input type="radio" name="status[{{ $r['studentId'] }}]" value="{{ $value }}" @checked($r['status'] === $value)
                                           class="tda-radio is-{{ $value }}" aria-label="{{ $label }} — {{ $r['name'] }}">
                                </td>
                            @endforeach
                            <td>
                                <textarea name="note[{{ $r['studentId'] }}]" rows="2" class="tda-ta" placeholder="Học tập tích cực, tiếp thu tốt trong buổi học…">{{ $r['note'] }}</textarea>
                            </td>
                            {{-- SỬA 9/9 (12) — "Em cần học thêm" là ô nhập chữ như Nhận xét. --}}
                            <td>
                                <textarea name="needs_more_practice[{{ $r['studentId'] }}]" rows="2" maxlength="1000" class="tda-ta is-amber"
                                          placeholder="VD: cần ôn lại vòng lặp for, làm thêm bài mảng 1 chiều…">{{ $r['needsMorePractice'] }}</textarea>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="tda-empty">Lớp này chưa có học sinh nào.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (count($rows) > 0)
            <div class="tda-save"><button type="submit" class="tda-btn is-blue"><x-lucide name="save" />Lưu điểm danh</button></div>
        @endif
    </form>

    {{-- ══════ TỔNG KẾT BUỔI HỌC ══════ --}}
    <section class="tda-card tda-pad" style="margin-top:{{ count($rows) > 0 ? '0' : '14px' }}">
        <div class="tda-head">
            <span class="tda-ic"><x-lucide name="file-text" /></span>
            <div><h3>Tổng kết buổi học</h3><p>Buổi học hôm nay đã dạy gì, học sinh tiếp thu ra sao, cần lưu ý gì cho buổi sau…</p></div>
        </div>
        <form method="POST" action="{{ route('teacher.schedule.summary.save', $session->id) }}">
            @csrf
            <textarea name="summary" rows="3" class="tda-summary" placeholder="Nhập nội dung tổng kết buổi học…">{{ $session->summary }}</textarea>
            <button type="submit" class="tda-btn is-line" style="margin-top:10px"><x-lucide name="bookmark" />Lưu tổng kết</button>
        </form>
    </section>

    {{-- ══════ HOẠT ĐỘNG BUỔI HỌC ══════
         Buổi học → nhiều HOẠT ĐỘNG → mỗi hoạt động nhiều TÀI NGUYÊN. Học sinh chỉ thấy hoạt động đã phát (nút ▶ / Thu hồi —
         App\Models\SessionActivity::published_at); thêm tài nguyên KHÔNG tự phát cho học sinh. --}}
    <section class="tda-card tda-pad">
        <div class="tda-head">
            <span class="tda-ic" style="background:#EEF2FF;color:#4F46E5"><x-lucide name="zap" /></span>
            <div style="flex:1 1 280px;min-width:0"><h3>Hoạt động buổi học</h3><p>Mỗi hoạt động gồm các bài giao của buổi này. Học sinh <b>chỉ thấy hoạt động đã phát</b> — soạn xong thì bấm nút ▶ để phát.</p></div>
            <div class="tda-pills">
                <span class="tda-pill">{{ count($sessionActivities) }} hoạt động</span>
                @if ($publishedCount > 0)<span class="tda-pill is-green"><x-lucide name="check-circle-2" />{{ $publishedCount }} đã phát</span>@endif
            </div>
        </div>

        <div class="tda-acts">
            @forelse ($sessionActivities as $activity)
                <div class="tda-act {{ $activity['published'] ? 'is-live' : '' }}">
                    <div class="tda-act-top">
                        <div class="tda-act-title">
                            <div class="row">
                                <b>{{ $activity['title'] }}</b>
                                @if ($activity['published'])
                                    <span class="tda-badge is-live"><x-lucide name="check-circle-2" />Học sinh đang thấy</span>
                                @else
                                    <span class="tda-badge is-draft">Đang soạn — học sinh chưa thấy</span>
                                @endif
                                <span class="cnt">{{ count($activity['resources']) }} tài nguyên</span>
                            </div>
                            @if (! empty($activity['note']))<p class="note">{{ $activity['note'] }}</p>@endif
                            @if ($activity['published'] && $activity['publishedAt'])
                                <p class="when"><x-lucide name="clock-3" />Đã phát lúc {{ $activity['publishedAt']->format('H:i d/m/Y') }}</p>
                            @endif
                        </div>
                        {{-- Nút ▶ / Thu hồi — công tắc cho học sinh thấy hay không. --}}
                        <form method="POST" action="{{ route('teacher.schedule.activities.publish', [$session->id, $activity['id']]) }}">
                            @csrf
                            @if ($activity['published'])
                                <button type="submit" class="tda-btn is-green-line" title="Thu hồi — học sinh sẽ không thấy nữa"><x-lucide name="rotate-ccw" />Thu hồi</button>
                            @else
                                <button type="submit" class="tda-btn is-green" title="Phát cho học sinh xem"><x-lucide name="play" />Phát cho học sinh</button>
                            @endif
                        </form>
                        <form method="POST" action="{{ route('teacher.schedule.activities.destroy', [$session->id, $activity['id']]) }}"
                              onsubmit="return confirm('Xoá hoạt động &quot;{{ addslashes($activity['title']) }}&quot; cùng {{ count($activity['resources']) }} tài nguyên bên trong?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="tda-icon-btn" title="Xoá hoạt động" aria-label="Xoá hoạt động"><x-lucide name="trash-2" /></button>
                        </form>
                    </div>

                    <div class="tda-act-body">
                        @forelse ($activity['resources'] as $res)
                            <div class="tda-res">
                                <span class="tda-res-ic"><x-lucide :name="$resourceIcons[$res['type']] ?? 'file-text'" /></span>
                                <div class="tda-res-txt">
                                    <b>{{ $res['title'] }}</b>
                                    <small>{{ $res['typeLabel'] }}
                                        @if (! empty($res['needsPublish']))
                                            <span class="tda-warn"><x-lucide name="alert-triangle" />Đề chưa phát hành — học sinh chưa làm được</span>
                                        @endif
                                    </small>
                                </div>
                                <form method="POST" action="{{ route('teacher.schedule.resources.delete', [$session->id, $res['id']]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="tda-link">Gỡ</button>
                                </form>
                            </div>
                        @empty
                            <p style="margin:0;font-size:12px;color:#94A3B8">Chưa có tài nguyên nào — chọn bài giao bên dưới để thêm.</p>
                        @endforelse

                        {{-- Thêm tài nguyên vào ĐÚNG hoạt động này --}}
                        <form method="POST" action="{{ route('teacher.schedule.resources.save', $session->id) }}" class="tda-addres">
                            @csrf
                            <input type="hidden" name="type" value="assessment">
                            <input type="hidden" name="activity_id" value="{{ $activity['id'] }}">
                            <div>
                                <label class="tda-label" for="assessment_{{ $activity['id'] }}">Thêm bài giao vào hoạt động này</label>
                                <x-ws.select id="assessment_{{ $activity['id'] }}" name="assessment_id" class="w-full">
                                    @if (empty($assessmentOptions))
                                        <option value="">— Bạn chưa tạo đề nào —</option>
                                    @else
                                        @foreach ($assessmentOptions as $opt)
                                            <option value="{{ $opt['id'] }}">{{ $opt['title'] }}</option>
                                        @endforeach
                                    @endif
                                </x-ws.select>
                            </div>
                            <button type="submit" class="tda-btn is-dark" @disabled(empty($assessmentOptions))><x-lucide name="plus" />Thêm</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="tda-noact">
                    <b style="display:block;color:#334155">Buổi học chưa có hoạt động nào.</b>
                    Tạo hoạt động đầu tiên ở ô bên dưới, thêm bài giao vào, rồi bấm ▶ để phát cho học sinh.
                </div>
            @endforelse
        </div>

        {{-- Tạo hoạt động mới --}}
        <form method="POST" action="{{ route('teacher.schedule.activities.store', $session->id) }}" class="tda-new">
            @csrf
            <p class="tda-new-title"><x-lucide name="plus" />Tạo hoạt động mới</p>
            <div class="tda-new-row">
                <div>
                    <label class="tda-label" for="activity_title">Tên hoạt động</label>
                    <input id="activity_title" name="title" type="text" maxlength="255" required class="tda-in"
                           placeholder="VD: Khởi động đầu giờ / Luyện tập tại lớp / Bài về nhà">
                </div>
                <div>
                    <label class="tda-label" for="activity_note">Ghi chú (tuỳ chọn)</label>
                    <input id="activity_note" name="note" type="text" maxlength="1000" class="tda-in" placeholder="VD: Làm trong 15 phút đầu">
                </div>
                <button type="submit" class="tda-btn is-violet"><x-lucide name="send" />Tạo hoạt động</button>
            </div>
        </form>

        {{-- Tài nguyên gắn từ trước khi có tính năng Hoạt động — giữ lại để không mất dữ liệu cũ. --}}
        @if (! empty($looseResources))
            <div class="tda-loose">
                <b class="t">Tài nguyên chưa thuộc hoạt động nào ({{ count($looseResources) }})</b>
                <p>Đây là tài nguyên gắn trước khi có mục Hoạt động. Học sinh <b>không thấy</b> những mục này — tạo hoạt động rồi thêm lại nếu còn cần.</p>
                @foreach ($looseResources as $res)
                    <div class="tda-res">
                        <span class="tda-res-ic"><x-lucide :name="$resourceIcons[$res['type']] ?? 'file-text'" /></span>
                        <div class="tda-res-txt"><b>{{ $res['title'] }}</b><small>{{ $res['typeLabel'] }}</small></div>
                        <form method="POST" action="{{ route('teacher.schedule.resources.delete', [$session->id, $res['id']]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="tda-link">Gỡ</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
    </div>
@endsection
