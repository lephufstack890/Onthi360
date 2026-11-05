{{--
  Route: parent.devices.index — mục "Máy tính của con" (Chocon Desktop).
  SỬA 4/11 — MỤC MỚI: thêm máy bằng mã ghép, khóa/mở khóa từ xa, đặt luật dùng máy, duyệt xin thêm giờ,
  xem hôm nay con học/chơi bao lâu. Luật lưu theo từng con, áp dụng cho mọi máy của con đó.
--}}
@extends('layouts.parent')

@section('title', 'Máy tính của con')
@section('page-title', 'Máy tính của con')

@section('content')
    @include('partials.parent-workspace-style')
    <style>
        .cd-grid { display: grid; gap: .75rem; grid-template-columns: 1fr; }
        @media (min-width: 768px) { .cd-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        .cd-field { display: grid; gap: 4px; font-size: 12px; color: #475569; }
        .cd-field > span { font-weight: 700; color: #334155; }
        .cd-field small { color: #94a3b8; }
        .cd-field input[type=number], .cd-field input[type=time], .cd-field input[type=text], .cd-field input[type=password], .cd-field textarea {
            min-height: 40px; border: 1px solid #bae6fd; border-radius: 12px; padding: 8px 12px; font: inherit; font-size: 13px; background: #fff; width: 100%;
        }
        .cd-field textarea { min-height: 72px; resize: vertical; }
        .cd-row { display: flex; gap: 8px; align-items: center; }
        .cd-check { display: flex; gap: 8px; align-items: flex-start; font-size: 12px; color: #334155; }
        .cd-check input { margin-top: 2px; width: 16px; height: 16px; flex: none; }
        .cd-check b { display: block; font-weight: 700; }
        .cd-check small { display: block; color: #64748b; }
        .pw-ok .cd-code, .cd-code { font: 800 34px/1.2 ui-monospace, Consolas, monospace; letter-spacing: .25em; color: #1d4ed8; margin: 8px 0 0; }
        .cd-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
        .cd-actions .pw-btn { margin-top: 0; }
        .pw-btn--danger { background: #dc2626; box-shadow: none; } .pw-btn--danger:hover { background: #b91c1c; }
        .cd-sec { margin: 1rem 0 .5rem; font-size: 11px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: #2563eb; }
        .cd-list { display: grid; gap: 6px; margin: 0; padding: 0; list-style: none; font-size: 12px; }
        .cd-list li { display: flex; justify-content: space-between; gap: 8px; padding: 8px 10px; border-radius: 10px; background: #f8fafc; color: #334155; }
        .cd-list li b { white-space: nowrap; color: #1e293b; }
        /* SỬA 5/11 — thẻ tổng quan nhiều con */
        .cd-kids { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); }
        .cd-kid { display: block; padding: 14px; border-radius: 18px; background: #fff; border: 2px solid #e2e8f0; text-decoration: none; color: #334155; transition: border-color .15s, box-shadow .15s, transform .15s; }
        .cd-kid:hover { border-color: #93c5fd; transform: translateY(-1px); box-shadow: 0 8px 20px -12px rgba(7, 87, 232, .45); }
        .cd-kid.is-on { border-color: #0757e8; box-shadow: 0 10px 24px -14px rgba(7, 87, 232, .7); }
        .cd-kid-top { display: flex; align-items: center; gap: 10px; }
        .cd-ava { width: 38px; height: 38px; border-radius: 999px; display: grid; place-items: center; flex: none; font-weight: 800; color: #fff; background: linear-gradient(135deg, #0757e8, #33c8ef); }
        .cd-kid-name { font-weight: 800; font-size: 14px; color: #0f172a; line-height: 1.2; }
        .cd-tag { display: inline-flex; align-items: center; gap: 5px; margin-top: 3px; font-size: 11px; font-weight: 700; color: #64748b; }
        .cd-dot { width: 8px; height: 8px; border-radius: 999px; background: #cbd5e1; }
        .cd-tag.ok .cd-dot { background: #10b981; } .cd-tag.ok { color: #047857; }
        .cd-tag.lock .cd-dot { background: #dc2626; } .cd-tag.lock { color: #b91c1c; }
        .cd-tag.warn .cd-dot { background: #f59e0b; } .cd-tag.warn { color: #b45309; }
        .cd-kid-nums { display: flex; gap: 12px; margin-top: 10px; font-size: 12px; color: #64748b; }
        .cd-kid-nums b { color: #0f172a; }
        .cd-badge { margin-left: auto; padding: 2px 8px; border-radius: 999px; background: #fef3c7; color: #b45309; font-size: 11px; font-weight: 800; white-space: nowrap; }
        .cd-bar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: space-between; margin-bottom: .75rem; }
        .cd-bar h3 { margin: 0; font-size: 14px; font-weight: 800; color: #0f172a; }
        .cd-bar form { margin: 0; }
        .pw-btn--ghost { background: #fff; color: #0757e8; border: 1px solid #bae6fd; box-shadow: none; }
        .cd-err { margin: 0 0 .75rem; padding: .75rem 1rem; border-radius: 12px; background: #fff1f2; color: #be123c; font-size: 12px; }
    </style>

    @php
        $statusText = match (session('status')) {
            'chocon-locked' => 'Đã khóa máy. Máy của con khóa sau vài giây.',
            'chocon-unlocked' => 'Đã mở khóa máy. Máy của con mở sau vài giây.',
            'chocon-locked-all' => 'Đã khóa máy của tất cả các con. Các máy khóa sau vài giây.',
            'chocon-unlocked-all' => 'Đã mở khóa máy của tất cả các con.',
            'chocon-policy-saved' => 'Đã lưu cài đặt. Máy của con áp dụng sau vài giây.',
            'chocon-pin-saved' => 'Đã đặt mã PIN phụ huynh cho tất cả các con.',
            'chocon-approved' => 'Đã duyệt thêm giờ chơi.',
            'chocon-rejected' => 'Đã từ chối yêu cầu thêm giờ.',
            'chocon-device-removed' => 'Đã gỡ liên kết máy tính.',
            default => null,
        };
    @endphp

    <div class="pw">
        @include('parent._hero', [
            'title' => 'Máy tính của con',
            'desc' => 'Quản lý máy tính khi con học online: giờ học, giờ chơi, trang web được vào, khóa máy từ xa.',
            'cta' => ['label' => 'Tải Chocon Desktop', 'href' => route('chocon.download.file'), 'icon' => 'download'],
        ])

        @if ($statusText)
            <div class="pw-ok"><b>{{ $statusText }}</b></div>
        @endif
        @if ($errors->any())
            <div class="cd-err">{{ $errors->first() }}</div>
        @endif

        @if (! $hasChildren)
            @include('parent._no-children')
        @else
            @if ($children->count() > 1)
                {{-- SỬA 5/11 — phụ huynh nhiều con: xem nhanh tất cả các con, bấm vào con nào để quản lý con đó. --}}
                @php $anyUnlocked = collect($overview)->contains(fn ($o) => ! $o['locked']); @endphp
                <section class="pw-card pw-card--soft">
                    <div class="cd-bar">
                        <h3>Các con của bạn ({{ $children->count() }})</h3>
                        <form method="POST" action="{{ route('parent.devices.lockAll') }}"
                              onsubmit="return confirm(@js($anyUnlocked ? 'Khóa máy của TẤT CẢ các con ngay bây giờ?' : 'Mở khóa máy của tất cả các con?'))">
                            @csrf
                            <input type="hidden" name="child" value="{{ $selectedId }}">
                            <input type="hidden" name="locked" value="{{ $anyUnlocked ? 1 : 0 }}">
                            <button class="pw-btn {{ $anyUnlocked ? 'pw-btn--danger' : 'pw-btn--ghost' }}" type="submit">
                                @if ($anyUnlocked)<x-lucide name="lock" />@else<x-lucide name="lock-open" />@endif
                                {{ $anyUnlocked ? 'Khóa máy tất cả các con' : 'Mở khóa tất cả' }}
                            </button>
                        </form>
                    </div>
                    <div class="cd-kids">
                        @foreach ($children as $c)
                            @php
                                $o = $overview[$c->id];
                                [$tone, $label] = match (true) {
                                    $o['devices'] === 0 => ['', 'Chưa có máy'],
                                    $o['locked'] => ['lock', 'Đang khóa'],
                                    $o['tray_off'] => ['warn', 'Đang bật · cần kiểm tra'],
                                    $o['online'] > 0 => ['ok', 'Đang dùng máy'],
                                    default => ['', 'Máy đang tắt'],
                                };
                            @endphp
                            <a href="{{ route('parent.devices.index', ['child' => $c->id]) }}" class="cd-kid {{ $c->id === $selectedId ? 'is-on' : '' }}">
                                <div class="cd-kid-top">
                                    <span class="cd-ava">{{ mb_strtoupper(mb_substr(trim(\Illuminate\Support\Str::afterLast(trim($c->name), ' ')) ?: $c->name, 0, 1)) }}</span>
                                    <div>
                                        <div class="cd-kid-name">{{ $c->name }}</div>
                                        <span class="cd-tag {{ $tone }}"><span class="cd-dot"></span>{{ $label }}</span>
                                    </div>
                                    @if ($o['pending'] > 0)
                                        <span class="cd-badge">{{ $o['pending'] }} xin giờ</span>
                                    @endif
                                </div>
                                <div class="cd-kid-nums">
                                    <span>Học <b>{{ $o['study'] }}'</b></span>
                                    <span>Game <b>{{ $o['game'] }}'</b>@if ($o['game_limit'] !== null)/{{ $o['game_limit'] }}'@endif</span>
                                    <span>{{ $o['devices'] }} máy</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- ===== Hôm nay ===== --}}
            <div class="pw-grid4">
                @include('parent._stat', ['label' => 'Học trên onthi360', 'value' => $usage['study'].' phút', 'note' => 'Đã nộp '.$doneToday.' bài/đề hôm nay', 'icon' => 'book-open', 'tone' => 'emerald'])
                @include('parent._stat', ['label' => 'Chơi game', 'value' => $usage['game'].' phút', 'note' => $policy->daily_game_minutes === null ? 'Chưa giới hạn' : 'Giới hạn '.$policy->daily_game_minutes.' phút/ngày', 'icon' => 'zap', 'tone' => $policy->daily_game_minutes !== null && $usage['game'] >= $policy->daily_game_minutes ? 'rose' : 'amber'])
                @include('parent._stat', ['label' => 'Trang web khác', 'value' => $usage['browser'].' phút', 'note' => 'Ngoài onthi360', 'icon' => 'globe', 'tone' => 'blue'])
                @include('parent._stat', ['label' => 'Ứng dụng khác', 'value' => $usage['other'].' phút', 'note' => 'Word, Zalo, phần mềm khác…', 'icon' => 'layers', 'tone' => 'violet'])
            </div>

            {{-- SỬA 5/11 (khách: "thêm chỗ đề xuất phương pháp quản lý con, học nhiều thì được thưởng…") — gợi ý theo số liệu hôm nay,
                 phương pháp đồng hành, bảng thưởng và thoả thuận gia đình mẫu. Chỉ hiển thị, không đổi cài đặt. --}}
            @include('parent.devices._tips')

            <div class="pw-cols2">
                {{-- ===== Máy tính + thêm máy ===== --}}
                <section class="pw-card">
                    @include('parent._ct', ['icon' => 'monitor', 'title' => 'Máy tính của '.$childName])

                    @if ($devices->isEmpty())
                        <p class="pw-sub">Chưa có máy nào. Cài Chocon Desktop trên máy của con rồi nhập mã ghép bên dưới.</p>
                    @else
                        <ul class="cd-list">
                            @foreach ($devices as $d)
                                <li>
                                    <div style="min-width:0">
                                        <b>{{ $d->name }}</b>
                                        <small style="display:block;color:#64748b">
                                            {{ $d->last_seen_at ? 'Hoạt động '.$d->last_seen_at->diffForHumans() : 'Chưa kết nối' }}
                                            · bản {{ $d->agent_version ?? '?' }}
                                            @if ($d->isOnline() && ! $d->tray_connected) · <span style="color:#b45309">biểu tượng bị tắt</span>@endif
                                        </small>
                                    </div>
                                    <div class="cd-row">
                                        <span class="pw-chip {{ $d->isOnline() ? 'ok' : 'off' }}">{{ $d->isOnline() ? ($d->locked ? 'Đang khóa' : 'Đang bật') : 'Mất kết nối' }}</span>
                                        <form method="POST" action="{{ route('parent.devices.destroy', $d) }}" onsubmit="return confirm(@js('Gỡ liên kết máy '.$d->name.'? Máy sẽ không còn bị quản lý.'))">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="pw-btn pw-btn--ghost" style="min-height:30px;padding:4px 10px" title="Gỡ liên kết"><x-lucide name="trash-2" /></button>
                                        </form>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <p class="cd-sec">Thêm máy tính</p>
                    @if ($pairing)
                        <div class="pw-ok">
                            <b>Mã ghép (dùng trước {{ $pairing['expires'] }})</b>
                            <p class="cd-code">{{ $pairing['code'] }}</p>
                            <p>Nhập mã này khi cài Chocon Desktop, hoặc bấm vào biểu tượng 360 ở góc màn hình → "Ghép với tài khoản phụ huynh".</p>
                        </div>
                    @else
                        <p class="pw-sub">1. Tải và cài Chocon Desktop trên máy của con (cần tài khoản quản trị Windows). 2. Lấy mã ghép ở đây và nhập vào phần mềm.</p>
                    @endif
                    <div class="cd-actions">
                        <form method="POST" action="{{ route('parent.devices.pairingCode') }}">
                            @csrf <input type="hidden" name="child" value="{{ $selectedId }}">
                            <button class="pw-btn" type="submit"><x-lucide name="key-round" />Lấy mã ghép</button>
                        </form>
                        <a class="pw-btn pw-btn--ghost" href="{{ route('chocon.download.file') }}"><x-lucide name="download" />Tải phần mềm</a>
                    </div>
                </section>

                {{-- ===== Khóa máy + xin thêm giờ ===== --}}
                <section class="pw-card">
                    @include('parent._ct', ['icon' => 'lock', 'title' => 'Khóa máy từ xa'])
                    @if ($policy->lock_now)
                        <div class="pw-warn"><b>Máy của {{ $childName }} đang bị khóa</b>@if ($policy->lock_message)<p>Lời nhắn: {{ $policy->lock_message }}</p>@endif</div>
                        <form method="POST" action="{{ route('parent.devices.lock') }}" class="cd-actions">
                            @csrf <input type="hidden" name="child" value="{{ $selectedId }}"><input type="hidden" name="locked" value="0">
                            <button class="pw-btn" type="submit"><x-lucide name="lock-keyhole" />Mở khóa</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('parent.devices.lock') }}">
                            @csrf <input type="hidden" name="child" value="{{ $selectedId }}"><input type="hidden" name="locked" value="1">
                            <label class="cd-field"><span>Lời nhắn hiện trên màn hình khóa</span>
                                <input type="text" name="lock_message" maxlength="200" placeholder="Ví dụ: Đến giờ ăn cơm rồi con!">
                            </label>
                            <div class="cd-actions"><button class="pw-btn pw-btn--danger" type="submit"><x-lucide name="lock" />Khóa máy ngay</button></div>
                        </form>
                    @endif

                    <p class="cd-sec">Xin thêm giờ hôm nay</p>
                    @forelse ($requests as $r)
                        <div class="cd-row" style="justify-content:space-between;padding:8px 0;border-top:1px solid #f1f5f9;font-size:12px">
                            <div style="min-width:0"><b>{{ $r->minutes }} phút</b> · {{ $r->created_at->format('H:i') }}@if ($r->reason)<br><small style="color:#64748b">{{ $r->reason }}</small>@endif</div>
                            @if ($r->status === 'pending')
                                <div class="cd-row">
                                    <form method="POST" action="{{ route('parent.devices.requests.decide', $r) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="pw-btn" style="margin:0" type="submit">Duyệt</button></form>
                                    <form method="POST" action="{{ route('parent.devices.requests.decide', $r) }}">@csrf<input type="hidden" name="decision" value="reject"><button class="pw-btn pw-btn--ghost" type="submit">Từ chối</button></form>
                                </div>
                            @else
                                <span class="pw-chip {{ $r->status === 'approved' ? 'ok' : 'off' }}">{{ $r->status === 'approved' ? 'Đã duyệt' : 'Đã từ chối' }}</span>
                            @endif
                        </div>
                    @empty
                        <p class="pw-sub">Hôm nay con chưa xin thêm giờ.</p>
                    @endforelse
                </section>
            </div>

            {{-- ===== Luật dùng máy ===== --}}
            <section class="pw-card">
                @include('parent._ct', ['icon' => 'settings', 'title' => 'Cài đặt dùng máy cho '.$childName])
                <form method="POST" action="{{ route('parent.devices.policy') }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="child" value="{{ $selectedId }}">

                    <p class="cd-sec" style="margin-top:0">Chế độ chỉ học</p>
                    <div class="cd-grid">
                        <label class="cd-check"><input type="hidden" name="study_only_mode" value="0"><input type="checkbox" name="study_only_mode" value="1" @checked(old('study_only_mode', $policy->study_only_mode))>
                            <span><b>Luôn bật chế độ chỉ học</b><small>Máy chỉ dùng để học: trình duyệt chỉ mở được onthi360 và các trang cho phép bên dưới, game và ứng dụng giải trí bị đóng.</small></span></label>
                        <label class="cd-check"><input type="hidden" name="study_only_in_study_hours" value="0"><input type="checkbox" name="study_only_in_study_hours" value="1" @checked(old('study_only_in_study_hours', $policy->study_only_in_study_hours))>
                            <span><b>Tự bật chế độ chỉ học trong giờ học</b><small>Theo khung "Giờ học" bên dưới (Thứ 2 – Thứ 6).</small></span></label>
                        <label class="cd-field" style="grid-column:1/-1"><span>Trang web được phép trong chế độ chỉ học</span>
                            <textarea name="allowed_domains" placeholder="Mỗi dòng một trang, ví dụ: vi.wikipedia.org">{{ old('allowed_domains', implode("\n", $policy->allowed_domains ?? [])) }}</textarea>
                            <small>onthi360.vn luôn được phép. Có thể ghi cả đường dẫn, hệ thống tự lấy tên trang.</small></label>
                    </div>

                    <p class="cd-sec">Giờ chơi & khung giờ</p>
                    <div class="cd-grid">
                        <label class="cd-field"><span>Số phút chơi game mỗi ngày</span>
                            <input type="number" name="daily_game_minutes" min="0" max="720" value="{{ old('daily_game_minutes', $policy->daily_game_minutes) }}" placeholder="Để trống = không giới hạn">
                            <small>0 = cấm chơi game. Máy báo trước 5 phút rồi tự đóng game.</small></label>
                        <div></div>
                        {{-- SỬA 5/11 (khách: "tạm thời bỏ những field này") — ẨN Giờ học / Giờ ngủ / Học xong mới được chơi / Trang web &
                             Phần mềm bị chặn. Chỉ ẩn khỏi giao diện, ô nhập vẫn còn (ẩn) nên bấm Lưu không làm mất giá trị đã đặt trước.
                             Muốn hiện lại: xoá các thẻ div cd-hidden bọc ngoài. --}}
                        <div class="cd-hidden" style="display:none" aria-hidden="true">
                        <div class="cd-field"><span>Giờ học (Thứ 2 – Thứ 6): không được chơi game</span>
                            <div class="cd-row"><input type="time" name="study_hours_start" value="{{ old('study_hours_start', $policy->study_hours_start) }}"> – <input type="time" name="study_hours_end" value="{{ old('study_hours_end', $policy->study_hours_end) }}"></div></div>
                        <div class="cd-field"><span>Giờ ngủ (mọi ngày): không được chơi game</span>
                            <div class="cd-row"><input type="time" name="sleep_start" value="{{ old('sleep_start', $policy->sleep_start) }}"> – <input type="time" name="sleep_end" value="{{ old('sleep_end', $policy->sleep_end) }}"></div>
                            <small>Ví dụ 22:00 – 06:00.</small></div>
                        </div>
                    </div>

                    <div class="cd-hidden" style="display:none" aria-hidden="true">
                    <p class="cd-sec">Học xong mới được chơi</p>
                    <div class="cd-grid">
                        <label class="cd-check" style="grid-column:1/-1"><input type="hidden" name="game_requires_study" value="0"><input type="checkbox" name="game_requires_study" value="1" @checked(old('game_requires_study', $policy->game_requires_study))>
                            <span><b>Chỉ được chơi game sau khi làm đủ bài trên onthi360</b><small>Hôm nay con đã nộp {{ $doneToday }} bài/đề.</small></span></label>
                        <label class="cd-field"><span>Số bài/đề phải nộp mỗi ngày</span>
                            <input type="number" name="study_goal_items" min="1" max="20" value="{{ old('study_goal_items', $policy->study_goal_items) }}"></label>
                        <label class="cd-field"><span>Thưởng phút chơi cho mỗi bài nộp</span>
                            <div class="cd-row"><input type="number" name="bonus_minutes_per_item" min="0" max="60" value="{{ old('bonus_minutes_per_item', $policy->bonus_minutes_per_item) }}"> <small style="white-space:nowrap">tối đa</small> <input type="number" name="bonus_cap_minutes" min="0" max="240" value="{{ old('bonus_cap_minutes', $policy->bonus_cap_minutes) }}"> <small>phút/ngày</small></div></label>
                    </div>
                    </div>

                    <div class="cd-hidden" style="display:none" aria-hidden="true"><p class="cd-sec">Chặn trang web & phần mềm</p></div>
                    <div class="cd-grid">
                        <div class="cd-hidden" style="display:none" aria-hidden="true">
                        <label class="cd-field"><span>Trang web bị chặn</span>
                            <textarea name="blocked_domains" placeholder="Mỗi dòng một trang, ví dụ: tiktok.com">{{ old('blocked_domains', implode("\n", $policy->blocked_domains ?? [])) }}</textarea>
                            <small>Chặn cả trang con (m.tiktok.com…).</small></label>
                        <label class="cd-field"><span>Phần mềm bị chặn</span>
                            <textarea name="blocked_processes" placeholder="Mỗi dòng một tên, ví dụ: discord">{{ old('blocked_processes', implode("\n", $policy->blocked_processes ?? [])) }}</textarea>
                            <small>Tên chương trình trong Task Manager, không cần đuôi .exe.</small></label>
                        </div>
                        <label class="cd-check" style="grid-column:1/-1"><input type="hidden" name="disable_private_browsing" value="0"><input type="checkbox" name="disable_private_browsing" value="1" @checked(old('disable_private_browsing', $policy->disable_private_browsing))>
                            <span><b>Tắt chế độ ẩn danh của trình duyệt</b><small>Để con không dùng tab ẩn danh lách chặn web.</small></span></label>
                    </div>

                    <div class="cd-actions"><button class="pw-btn" type="submit"><x-lucide name="save" />Lưu cài đặt</button></div>
                </form>
            </section>

            <div class="pw-cols2">
                {{-- ===== Hôm nay dùng gì ===== --}}
                <section class="pw-card">
                    @include('parent._ct', ['icon' => 'bar-chart-2', 'title' => 'Hôm nay con dùng gì'])
                    <p class="cd-sec" style="margin-top:0">Trang web</p>
                    <ul class="cd-list">
                        @forelse ($usage['topSites'] as $s)<li><span>{{ $s['name'] }}</span><b>{{ $s['minutes'] }} phút</b></li>
                        @empty<li><span>Chưa có dữ liệu</span></li>@endforelse
                    </ul>
                    <p class="cd-sec">Game & ứng dụng</p>
                    <ul class="cd-list">
                        @forelse ($usage['topApps'] as $a)<li><span>{{ $a['name'] }} @if ($a['category'] === 'game')<span class="pw-chip rose">game</span>@endif</span><b>{{ $a['minutes'] }} phút</b></li>
                        @empty<li><span>Chưa có dữ liệu</span></li>@endforelse
                    </ul>
                </section>

                {{-- ===== Sự kiện + PIN ===== --}}
                <section class="pw-card">
                    @include('parent._ct', ['icon' => 'bell', 'title' => 'Hoạt động gần đây'])
                    <ul class="cd-list">
                        @forelse ($usage['events'] as $e)<li><span>{{ $e['label'] }}</span><b>{{ $e['time'] }}</b></li>
                        @empty<li><span>Chưa có hoạt động.</span></li>@endforelse
                    </ul>

                    <p class="cd-sec">Mã PIN phụ huynh</p>
                    <p class="pw-sub">{{ $hasPin ? 'Đã đặt mã PIN.' : 'Chưa đặt mã PIN — nên đặt ngay.' }} Mã PIN dùng để mở khóa máy tại chỗ và để gỡ phần mềm. Gồm 6–8 chữ số, áp dụng cho tất cả các con.</p>
                    <form method="POST" action="{{ route('parent.devices.pin') }}">
                        @csrf <input type="hidden" name="child" value="{{ $selectedId }}">
                        <div class="cd-grid" style="margin-top:8px">
                            <label class="cd-field"><span>{{ $hasPin ? 'Mã PIN mới' : 'Mã PIN' }}</span><input type="password" name="pin" inputmode="numeric" pattern="[0-9]{6,8}" maxlength="8" required autocomplete="new-password"></label>
                            <label class="cd-field"><span>Nhập lại</span><input type="password" name="pin_confirmation" inputmode="numeric" pattern="[0-9]{6,8}" maxlength="8" required autocomplete="new-password"></label>
                        </div>
                        <div class="cd-actions"><button class="pw-btn" type="submit"><x-lucide name="key-round" />{{ $hasPin ? 'Đổi mã PIN' : 'Đặt mã PIN' }}</button></div>
                    </form>
                </section>
            </div>
        @endif
    </div>

    @if ($hasChildren)
        {{-- SỬA 5/11 — tự làm mới: máy vừa ghép / vừa khóa / con vừa xin thêm giờ là trang cập nhật ngay, không phải bấm F5.
             Không tải lại khi phụ huynh đang gõ trong form. --}}
        <script>
            (function () {
                const url = @js(route('parent.devices.status'));
                let last = null;
                const busy = () => { const a = document.activeElement; return a && ['INPUT', 'TEXTAREA', 'SELECT'].includes(a.tagName); };
                async function tick() {
                    if (document.hidden) return;
                    try {
                        const r = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                        const { sig } = await r.json();
                        if (last !== null && sig !== last && !busy()) { location.reload(); return; }
                        last = sig;
                    } catch (e) { /* mất mạng: thử lại lượt sau */ }
                }
                tick();
                setInterval(tick, 5000);
            })();
        </script>
    @endif
@endsection
