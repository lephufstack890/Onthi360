{{-- SỬA 6/11 (khách: "Điểm danh: danh sách chia các ngày, lọc từ ngày đến ngày, nút in, nút thêm buổi — mỗi buổi thêm một cột chia 2:
     điểm danh và nhận xét; nút thêm học sinh") — Tab 3 · Sổ điểm danh. Dữ liệu: ClassSpaceService::attendance(). Bấm ô để đổi trạng
     thái / ghi nhận xét, lưu ngay (teacher.classes.attendance.cell). Thêm / sửa buổi dùng lại teacher.schedule.store / update;
     thêm / duyệt / gỡ học sinh dùng lại các route cũ. CSS tiền tố att-. --}}
@php
    $at = $spaceData ?? [];
    $atCan = $space['can'] ?? [];
    $atCols = $at['cols'] ?? [];
    $atRows = $at['rows'] ?? [];
    $atCells = [];
    foreach ($atRows as $r) {
        foreach ($r['cells'] as $sid => $c) {
            $atCells[$sid.':'.$r['id']] = $c;
        }
    }
    $atQuery = array_filter(['range' => $at['range'] ?? null, 'from' => ($at['range'] ?? '') === 'custom' ? $at['from'] : null, 'to' => ($at['range'] ?? '') === 'custom' ? $at['to'] : null]);
    $atUrl = fn (array $extra = []) => route('teacher.classes.show', ['class' => $classRoom->id, 'tab' => 'attendance'] + $extra + $atQuery);
    $atPresets = ['recent' => '30 ngày gần đây', 'week' => 'Tuần này', 'month' => 'Tháng này', 'all' => 'Cả khoá'];
    $atAdded = session('members_added');
@endphp

@if (session('status') === 'session-created')
    @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã thêm buổi học — sổ điểm danh có thêm một cột.'])
@elseif (session('status') === 'session-updated')
    @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu thay đổi buổi học.'])
@elseif (session('status') === 'member-removed')
    @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã gỡ học sinh khỏi lớp và gửi thông báo cho em.'])
@elseif (session('status') === 'join-request-approved')
    @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã duyệt — học sinh vào học được ngay.'])
@elseif (session('status') === 'join-request-rejected')
    @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã từ chối yêu cầu và báo cho học sinh.'])
@endif
@if (is_array($atAdded) && ! empty($atAdded['added']))
    @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã thêm '.count($atAdded['added']).' học sinh vào lớp.'])
@endif

<div class="csp-card att" x-data="cspAtt(@js(['cells' => (object) $atCells, 'rows' => array_map(fn ($r) => ['id' => $r['id'], 'came' => $r['came'], 'marked' => $r['marked']], $atRows), 'cols' => array_column($atCols, 'id'), 'url' => route('teacher.classes.attendance.cell', $classRoom->id), 'csrf' => csrf_token(), 'can' => (bool) ($atCan['attendance'] ?? false)]))"
     @keydown.escape.window="menu = null; ed = null">
    <div class="att-print-title">
        <h2>Sổ điểm danh — {{ $className }}</h2>
        <p>{{ $at['from'] ? 'Từ '.\Carbon\Carbon::parse($at['from'])->format('d/m/Y') : 'Từ đầu khoá' }} {{ $at['to'] ? 'đến '.\Carbon\Carbon::parse($at['to'])->format('d/m/Y') : 'đến nay' }} · In ngày {{ now()->format('d/m/Y') }}</p>
    </div>

    <div class="csp-ch csp-noprint">
        <span class="csp-ic g"><x-lucide name="list-checks" /></span>
        <div class="grow"><h3>Sổ điểm danh</h3><p>Mỗi buổi 2 cột: <b>Điểm danh</b> &amp; <b>Nhận xét</b> — bấm vào ô để đổi trạng thái hoặc ghi nhận xét, tự lưu ngay.</p></div>
        @if (($atCan['students'] ?? false) && count($at['pending'] ?? []) > 0)
            <button type="button" class="csp-btn amb" @click="pend = true"><x-lucide name="user-plus" />Yêu cầu chờ duyệt <span class="csp-pill p-no" style="margin-left:2px">{{ count($at['pending']) }}</span></button>
        @endif
        @if ($atCan['students'] ?? false)
            @include('teacher.classes.space._add-students')
        @endif
        @if ($atCan['sessions'] ?? false)
            <button type="button" class="csp-btn pri" @click="$dispatch('csp-sess', null)"><x-lucide name="calendar-days" />Thêm buổi</button>
        @endif
    </div>

    @if (is_array($atAdded))
        <div class="tcx-addres csp-noprint" style="margin-bottom:12px">
            @if (! empty($atAdded['added']))<p class="ok"><x-lucide name="check-circle-2" />Đã thêm vào lớp: <b>{{ implode(', ', $atAdded['added']) }}</b></p>@endif
            @if (! empty($atAdded['already']))<p class="info"><x-lucide name="info" />Đã ở trong lớp từ trước: {{ implode(', ', $atAdded['already']) }}</p>@endif
            @if (! empty($atAdded['notFound']))<p class="warn"><x-lucide name="alert-triangle" />Không tìm thấy tài khoản: {{ implode(', ', $atAdded['notFound']) }}</p>@endif
            @if (! empty($atAdded['notStudent']))<p class="warn"><x-lucide name="alert-triangle" />Không phải tài khoản học sinh (hoặc đang bị khoá): {{ implode(', ', $atAdded['notStudent']) }}</p>@endif
        </div>
    @endif

    <div class="att-tool csp-noprint">
        <nav class="csp-seg" aria-label="Khoảng thời gian">
            @foreach ($atPresets as $k => $l)
                <a href="{{ route('teacher.classes.show', ['class' => $classRoom->id, 'tab' => 'attendance', 'range' => $k]) }}" @class(['on' => ($at['range'] ?? '') === $k])>{{ $l }}</a>
            @endforeach
        </nav>
        <form method="GET" action="{{ route('teacher.classes.show', $classRoom->id) }}" class="att-range">
            <input type="hidden" name="tab" value="attendance"><input type="hidden" name="range" value="custom">
            <label><x-lucide name="calendar-days" />Từ<input type="date" name="from" value="{{ $at['from'] }}" class="csp-inp"></label>
            <span>→</span>
            <label>Đến<input type="date" name="to" value="{{ $at['to'] }}" class="csp-inp"></label>
            <button type="submit" class="csp-btn sm">Lọc</button>
        </form>
        <label class="att-search"><x-lucide name="search" /><input type="search" x-model="q" placeholder="Tìm học sinh…" aria-label="Tìm học sinh"></label>
        <span style="flex:1"></span>
        <span class="att-legend"><span class="csp-pill p-present">Có mặt</span><span class="csp-pill p-late">Muộn</span><span class="csp-pill p-excused">Có phép</span><span class="csp-pill p-absent">Vắng</span></span>
        <a class="csp-btn" href="{{ route('teacher.classes.attendance.export', ['class' => $classRoom->id] + $atQuery) }}"><x-lucide name="download" />Xuất Excel</a>
        <button type="button" class="csp-btn" onclick="window.print()"><x-lucide name="printer" />In sổ</button>
    </div>

    @if (empty($atRows))
        <div class="csp-empty"><x-lucide name="users" /><b>Lớp chưa có học sinh</b><span>Bấm <b>Thêm học sinh</b> ở trên, hoặc duyệt yêu cầu vào lớp của các em.</span></div>
    @elseif (empty($atCols))
        <div class="csp-empty"><x-lucide name="calendar-days" /><b>Chưa có buổi học trong khoảng ngày này</b><span>Chọn khoảng khác (vd "Cả khoá") hoặc bấm <b>Thêm buổi</b> để tạo buổi đầu tiên.</span></div>
    @else
        <div class="att-wrap">
            <table class="att-t">
                <thead>
                    <tr>
                        <th rowspan="2" class="att-no">#</th>
                        <th rowspan="2" class="att-name">Học sinh</th>
                        @foreach ($atCols as $c)
                            <th colspan="2" class="att-sess">
                                <b>Buổi {{ $c['no'] }} · {{ $c['date'] }}</b>
                                <small>{{ $c['time'] }}@if ($c['topic'] !== '') · {{ \Illuminate\Support\Str::limit($c['topic'], 28) }}@endif</small>
                                <span class="att-hacts csp-noprint">
                                    @if ($atCan['sessions'] ?? false)<button type="button" @click="$dispatch('csp-sess', @js($c['edit']))" title="Sửa buổi"><x-lucide name="pencil" />Sửa</button>@endif
                                    @if ($c['started'] && ($atCan['attendance'] ?? false))<button type="button" @click="markAll({{ $c['id'] }})" title="Em nào chưa điểm danh thì đánh Có mặt"><x-lucide name="check" />Cả lớp có mặt</button>@endif
                                </span>
                            </th>
                        @endforeach
                        @if ($atCan['sessions'] ?? false)
                            <th rowspan="2" class="att-addcol csp-noprint"><button type="button" @click="$dispatch('csp-sess', null)"><x-lucide name="plus" /><span>Thêm buổi</span></button></th>
                        @endif
                        <th rowspan="2" class="att-tot">Đi học</th>
                    </tr>
                    <tr>
                        @foreach ($atCols as $c)<th class="att-sub">Điểm danh</th><th class="att-sub">Nhận xét</th>@endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($atRows as $i => $r)
                        <tr x-show="match(@js(mb_strtolower($r['name'])))" x-data="{ rm: false }">
                            <td class="att-no">{{ $i + 1 }}</td>
                            <td class="att-name">
                                <div class="csp-who">
                                    <span class="csp-av h{{ $r['hue'] }}">@if ($r['avatar'])<img src="{{ $r['avatar'] }}" alt="">@else{{ $r['initials'] }}@endif</span>
                                    <span style="min-width:0;display:grid"><b>{{ $r['name'] }}</b>@if ($r['contact'])<small class="att-contact">{{ $r['contact'] }}</small>@endif</span>
                                    @if ($atCan['students'] ?? false)
                                        <button type="button" class="att-rm csp-noprint" @click="rm = true" title="Gỡ khỏi lớp" aria-label="Gỡ {{ $r['name'] }} khỏi lớp"><x-lucide name="log-out" /></button>
                                    @endif
                                </div>
                                @if ($atCan['students'] ?? false)
                                    <form method="POST" action="{{ route('teacher.classes.members.remove', ['class' => $classRoom->id, 'student' => $r['id']]) }}" class="att-rmf csp-noprint" x-show="rm" x-cloak>
                                        @csrf
                                        <span>Gỡ {{ $r['name'] }} khỏi lớp? Em mất quyền vào lớp ngay, dữ liệu cũ vẫn giữ.</span>
                                        <input type="text" name="reason" maxlength="255" placeholder="Lý do (không bắt buộc)" class="csp-inp" style="height:32px;font-size:12px">
                                        <div><button type="button" class="csp-btn sm" @click="rm = false">Huỷ</button><button type="submit" class="csp-btn sm red">Gỡ khỏi lớp</button></div>
                                    </form>
                                @endif
                            </td>
                            @foreach ($atCols as $c)
                                @php $k = $c['id'].':'.$r['id']; @endphp
                                <td class="att-st">
                                    <button type="button" class="att-pill" :class="'p-' + (st('{{ $k }}') || 'none')" @click="openMenu('{{ $k }}', $event)" :disabled="!can" x-text="label('{{ $k }}')">{{ \App\Services\Teacher\ClassSpaceService::STATUS_LABELS[$r['cells'][$c['id']]['s'] ?? ''] ?? '—' }}</button>
                                </td>
                                <td class="att-nt">
                                    <button type="button" class="att-note" :class="{ empty: !note('{{ $k }}') }" @click="openNote('{{ $k }}', $event)" :disabled="!can" x-text="note('{{ $k }}') || '+ Nhận xét'">{{ $r['cells'][$c['id']]['n'] ?: '+ Nhận xét' }}</button>
                                </td>
                            @endforeach
                            @if ($atCan['sessions'] ?? false)<td class="att-addcol csp-noprint"></td>@endif
                            <td class="att-tot">
                                <b x-text="total({{ $r['id'] }}).came + '/' + total({{ $r['id'] }}).marked">{{ $r['came'] }}/{{ $r['marked'] }}</b>
                                <span class="csp-pill" :class="rateTone({{ $r['id'] }})" x-text="rate({{ $r['id'] }})"></span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td></td><td>Có mặt</td>
                        @foreach ($atCols as $c)<td class="att-c" x-text="colPresent({{ $c['id'] }}) + '/{{ count($atRows) }}'"></td><td></td>@endforeach
                        @if ($atCan['sessions'] ?? false)<td class="att-addcol csp-noprint"></td>@endif
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="att-foot csp-noprint">
            <span>Buổi {{ $at['firstNo'] }}–{{ $at['lastNo'] }} · {{ $at['totalSessions'] }} buổi trong khoảng đã chọn · {{ count($atRows) }} học sinh. Cột "Đi học" tính trên mọi buổi trong khoảng ngày (có mặt + muộn / số buổi đã điểm danh).</span>
            @if (($at['pages'] ?? 1) > 1)
                <nav class="csp-seg" aria-label="Trang buổi học">
                    @if ($at['page'] > 1)<a href="{{ $atUrl(['p' => $at['page'] - 1]) }}">‹ Buổi trước</a>@endif
                    @for ($p = 1; $p <= $at['pages']; $p++)<a href="{{ $atUrl(['p' => $p]) }}" @class(['on' => $p === $at['page']])>{{ $p }}</a>@endfor
                    @if ($at['page'] < $at['pages'])<a href="{{ $atUrl(['p' => $at['page'] + 1]) }}">Buổi sau ›</a>@endif
                </nav>
            @endif
        </div>
    @endif

    {{-- Menu chọn trạng thái --}}
    <div class="att-menu" x-show="menu" x-cloak :style="menu && ('top:' + menu.y + 'px;left:' + menu.x + 'px')" @click.outside="menu = null">
        <template x-for="o in opts" :key="o[0]">
            <button type="button" @click="pick(o[0])" :class="{ on: menu && st(menu.k) === o[0] }"><span class="csp-pill" :class="'p-' + o[0]" x-text="o[1]"></span><kbd x-text="o[2]"></kbd></button>
        </template>
    </div>
    {{-- Ô ghi nhận xét --}}
    <div class="att-ed" x-show="ed" x-cloak :style="ed && ('top:' + ed.y + 'px;left:' + ed.x + 'px')" @click.outside="ed = null">
        <textarea x-ref="edt" x-model="edText" rows="3" maxlength="1000" class="csp-inp" placeholder="Nhận xét buổi học của em…" @keydown.ctrl.enter="saveNote()" @keydown.meta.enter="saveNote()"></textarea>
        <div class="att-quick">
            <template x-for="t in quick" :key="t"><button type="button" @click="edText = edText ? edText + ' ' + t : t" x-text="t"></button></template>
        </div>
        <div class="att-ed-acts"><small>Ctrl + Enter để lưu</small><button type="button" class="csp-btn sm" @click="ed = null">Huỷ</button><button type="button" class="csp-btn sm pri" @click="saveNote()">Lưu</button></div>
    </div>

    {{-- Yêu cầu chờ duyệt --}}
    @if (($atCan['students'] ?? false) && count($at['pending'] ?? []) > 0)
        <template x-teleport="body">
            <div class="tam-ov" x-show="pend" x-transition.opacity x-cloak @click.self="pend = false">
                <div class="tam-dlg" x-show="pend" x-transition.scale.95 role="dialog" aria-modal="true">
                    <header class="tam-head" style="background:linear-gradient(120deg,#FFF7E6,#FFFDF5)">
                        <span class="tam-hic" style="background:linear-gradient(135deg,#F59E0B,#EA7A0B)"><x-lucide name="user-plus" /></span>
                        <div class="tam-htx"><h3>Yêu cầu vào lớp chờ duyệt</h3><p>Duyệt là em vào học được ngay và nhận thông báo.</p></div>
                        <button type="button" class="tam-x" @click="pend = false" aria-label="Đóng"><x-lucide name="x" /></button>
                    </header>
                    <div class="tam-body" style="display:grid;gap:10px;padding-top:14px;padding-bottom:16px">
                        @foreach ($at['pending'] as $pq)
                            <div class="csp-row" style="flex-wrap:wrap;margin:0">
                                <span class="csp-who" style="flex:1 1 200px"><span class="csp-av h{{ $pq['hue'] }}">@if ($pq['avatar'])<img src="{{ $pq['avatar'] }}" alt="">@else{{ $pq['initials'] }}@endif</span><span style="display:grid;min-width:0"><b>{{ $pq['name'] }}</b><small class="att-contact">{{ $pq['contact'] }}{{ $pq['at'] ? ' · xin vào '.$pq['at'] : '' }}</small></span></span>
                                <form method="POST" action="{{ route('teacher.classes.requests.approve', ['class' => $classRoom->id, 'enrollment' => $pq['enrollmentId']]) }}">@csrf<button type="submit" class="csp-btn sm grn"><x-lucide name="check" />Duyệt</button></form>
                                <form method="POST" action="{{ route('teacher.classes.requests.reject', ['class' => $classRoom->id, 'enrollment' => $pq['enrollmentId']]) }}" style="display:flex;gap:6px">@csrf<input type="text" name="reason" maxlength="255" placeholder="Lý do từ chối" class="csp-inp" style="height:32px;width:150px;font-size:12px"><button type="submit" class="csp-btn sm red"><x-lucide name="x" />Từ chối</button></form>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </template>
    @endif
</div>

{{-- Popup Thêm / Sửa buổi học (dùng chung ô và thẻ thời gian tsf- của form Tạo buổi học cũ) --}}
@if ($atCan['sessions'] ?? false)
    @include('teacher.classes.space._session-kit')
    <div x-data="cspSess(@js(['store' => route('teacher.schedule.store'), 'update' => route('teacher.schedule.update', ['session' => 0])]))" @csp-sess.window="show($event.detail)" @keydown.escape.window="open && close()">
        <template x-teleport="body">
            <div class="tam-ov" x-show="open" x-transition.opacity x-cloak @click.self="close()">
                <form class="tam-dlg att-sdlg" method="POST" :action="action" x-show="open" x-transition.scale.95 role="dialog" aria-modal="true"
                      @submit="if (dur <= 0) { $event.preventDefault(); tried = true; } else { submitting = true; }">
                    @csrf
                    <template x-if="id"><input type="hidden" name="_method" value="PUT"></template>
                    <input type="hidden" name="class_room_id" value="{{ $classRoom->id }}">
                    <input type="hidden" name="back_to_class" value="1">
                    <header class="tam-head" :style="id ? 'background:linear-gradient(120deg,#FFF7E6,#EEF4FF)' : 'background:linear-gradient(120deg,#EEF4FF,#F0F9FF)'">
                        <span class="tam-hic" :style="id ? 'background:linear-gradient(135deg,#F59E0B,#EA7A0B)' : 'background:linear-gradient(135deg,#2563EB,#1D4ED8)'"><x-lucide name="calendar-days" /></span>
                        <div class="tam-htx"><h3 x-text="id ? 'Sửa buổi học' : 'Thêm buổi học'"></h3><p>{{ $className }} · mỗi buổi là một cột trong sổ điểm danh</p></div>
                        <button type="button" class="tam-x" @click="close()" aria-label="Đóng"><x-lucide name="x" /></button>
                    </header>
                    <div class="tam-body" style="display:grid;gap:16px;padding:18px 20px 6px">
                        <div class="tsf-row">
                            <div class="tsf-f"><label class="tsf-lb" for="att-topic">Chủ đề buổi học</label><div class="tsf-in"><x-lucide name="book-open" /><input id="att-topic" x-ref="topic" type="text" name="topic" maxlength="255" x-model="topic" placeholder="Ví dụ: Vòng lặp for"></div></div>
                            <div class="tsf-f"><label class="tsf-lb" for="att-loc">Địa điểm / link học online</label><div class="tsf-in"><x-lucide name="map-pin" /><input id="att-loc" type="text" name="location" maxlength="255" x-model="location" placeholder="Phòng 201, hoặc link Google Meet / Zoom"></div></div>
                        </div>
                        <div class="tsf-time att-time">
                            <div class="tsf-slot is-a">
                                <div class="tsf-slot-h"><span class="tsf-dot"></span>Bắt đầu<small x-text="dayLabel(sd)"></small></div>
                                <div class="tsf-dt">
                                    <div class="tsf-in tsf-date"><input type="date" name="starts_date" required x-model="sd" @change="onStart()" aria-label="Ngày bắt đầu"></div>
                                    <div class="tsf-in tsf-tm"><x-lucide name="clock-3" />
                                        <select name="starts_hour" required x-model="sh" @change="onStart()" aria-label="Giờ bắt đầu">@for ($h = 0; $h < 24; $h++)<option value="{{ sprintf('%02d', $h) }}">{{ sprintf('%02d', $h) }}</option>@endfor</select><b>:</b>
                                        <select name="starts_minute" required x-model="sm" @change="onStart()" aria-label="Phút bắt đầu">@foreach (range(0, 55, 5) as $m)<option value="{{ sprintf('%02d', $m) }}">{{ sprintf('%02d', $m) }}</option>@endforeach</select>
                                    </div>
                                </div>
                            </div>
                            <span class="tsf-arrow" aria-hidden="true"><x-lucide name="arrow-right" /></span>
                            <div class="tsf-slot is-b" :class="{ bad: tried && dur <= 0 }">
                                <div class="tsf-slot-h"><span class="tsf-dot"></span>Kết thúc<small x-text="dayLabel(ed)"></small></div>
                                <div class="tsf-dt">
                                    <div class="tsf-in tsf-date"><input type="date" name="ends_date" required x-model="ed" @change="lastDur = dur" aria-label="Ngày kết thúc"></div>
                                    <div class="tsf-in tsf-tm"><x-lucide name="clock-3" />
                                        <select name="ends_hour" required x-model="eh" @change="lastDur = dur" aria-label="Giờ kết thúc">@for ($h = 0; $h < 24; $h++)<option value="{{ sprintf('%02d', $h) }}">{{ sprintf('%02d', $h) }}</option>@endfor</select><b>:</b>
                                        <select name="ends_minute" required x-model="em" @change="lastDur = dur" aria-label="Phút kết thúc">@foreach (range(0, 55, 5) as $m)<option value="{{ sprintf('%02d', $m) }}">{{ sprintf('%02d', $m) }}</option>@endforeach</select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="tsf-quick">
                            <span class="tsf-q-lb"><x-lucide name="timer" />Thời lượng nhanh</span>
                            <div class="tsf-chips"><template x-for="d in [45, 60, 90, 120, 150]" :key="d"><button type="button" :class="{ on: dur === d }" @click="setDur(d)" x-text="durLabel(d)"></button></template></div>
                            <span class="tsf-sum" :class="{ bad: dur <= 0 }" x-text="dur > 0 ? 'Buổi học dài ' + durLabel(dur) : 'Giờ kết thúc phải sau giờ bắt đầu'"></span>
                        </div>
                    </div>
                    <footer class="tam-foot">
                        <small><x-lucide name="info" /><span x-text="id ? 'Sửa buổi không làm mất điểm danh, nhận xét đã ghi.' : 'Tạo xong, sổ điểm danh có thêm một cột cho buổi này.'"></span></small>
                        <div class="tam-acts">
                            <button type="button" class="tam-btn ghost" @click="close()">Huỷ</button>
                            <button type="submit" class="tam-btn pri" style="background:linear-gradient(135deg,#2563EB,#1D4ED8)" :disabled="dur <= 0 || submitting"><x-lucide name="check" /><span x-text="submitting ? 'Đang lưu…' : (id ? 'Lưu thay đổi' : 'Thêm buổi')"></span></button>
                        </div>
                    </footer>
                </form>
            </div>
        </template>
    </div>
@endif

<style>
    .att-print-title { display: none; }
    .att-tool { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 14px; }
    .att-range { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin: 0; }
    .att-range label { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 700; color: #475569; }
    .att-range label svg { width: 15px; height: 15px; color: #64748B; }
    .att-range .csp-inp { height: 34px; padding: 0 8px; font-size: 12.5px; }
    .att-search { display: inline-flex; align-items: center; gap: 6px; height: 36px; padding: 0 10px; border: 1px solid #D9E3EE; border-radius: 11px; background: #fff; }
    .att-search svg { width: 15px; height: 15px; color: #94A3B8; }
    .att-search input { width: 160px; border: 0; outline: 0; background: none; font: inherit; font-size: 12.5px; box-shadow: none; }
    .att-legend { display: inline-flex; flex-wrap: wrap; gap: 6px; }
    .att-wrap { overflow-x: auto; border: 1px solid #E5EDF5; border-radius: 16px; }
    .att-t { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 12.5px; }
    .att-t th, .att-t td { padding: 8px 10px; border-bottom: 1px solid #EDF2F7; border-right: 1px solid #EDF2F7; vertical-align: middle; background: #fff; }
    .att-t thead th { background: #F7FAFD; font-size: 11.5px; font-weight: 800; color: #475569; }
    .att-t thead tr:first-child th { background: #F1F6FB; }
    .att-t tfoot td { background: #F7FAFD; font-weight: 800; color: #334155; font-size: 12px; }
    .att-no { width: 40px; text-align: center; color: #94A3B8; font-weight: 700; }
    .att-name { position: sticky; left: 0; z-index: 2; min-width: 210px; max-width: 250px; text-align: left; box-shadow: 1px 0 0 #E5EDF5; }
    .att-t td.att-name .csp-who b { font-size: 13px; }
    .att-contact { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 11px; font-weight: 600; color: #94A3B8; }
    .att-rm { display: grid; place-items: center; width: 26px; height: 26px; flex: none; margin-left: auto; padding: 0; border: 0; border-radius: 8px; background: none; color: #CBD5E1; cursor: pointer; }
    .att-rm:hover { background: #FFF5F5; color: #DC2626; } .att-rm svg { width: 14px; height: 14px; }
    .att-rmf { display: grid; gap: 6px; margin-top: 8px; padding: 8px; border-radius: 10px; background: #FEF2F2; font-size: 11.5px; font-weight: 600; color: #991B1B; white-space: normal; }
    .att-rmf div { display: flex; gap: 6px; }
    .att-sess { min-width: 220px; text-align: center !important; color: #0F2F57 !important; }
    .att-sess b { display: block; font-size: 12.5px; } .att-sess small { display: block; margin-top: 1px; font-weight: 600; color: #64748B; }
    .att-hacts { display: flex; justify-content: center; gap: 6px; margin-top: 5px; }
    .att-hacts button { display: inline-flex; align-items: center; gap: 4px; height: 24px; padding: 0 8px; border: 1px solid #DCE6F0; border-radius: 8px; background: #fff; font: inherit; font-size: 11px; font-weight: 700; color: #475569; cursor: pointer; }
    .att-hacts button:hover { border-color: #93C5FD; color: #1D4ED8; } .att-hacts svg { width: 12px; height: 12px; }
    .att-sub { text-align: center !important; font-size: 11px !important; color: #64748B !important; }
    .att-st { width: 92px; text-align: center; }
    .att-pill { min-width: 78px; height: 28px; padding: 0 10px; border: 0; border-radius: 999px; font: inherit; font-size: 11.5px; font-weight: 800; cursor: pointer; transition: transform .1s, box-shadow .15s; }
    .att-pill:hover:not(:disabled) { box-shadow: 0 0 0 3px rgba(37, 99, 235, .15); }
    .att-pill.p-none { background: #F8FAFC; color: #A3B1C2; border: 1px dashed #CBD5E1; }
    .att-pill:disabled { cursor: default; }
    .att-nt { width: 130px; max-width: 180px; }
    .att-note { display: block; width: 100%; min-height: 28px; padding: 4px 6px; border: 0; border-radius: 8px; background: none; font: inherit; font-size: 12px; line-height: 1.4; color: #334155; text-align: left; white-space: normal; word-break: break-word; cursor: pointer; }
    .att-note:hover:not(:disabled) { background: #F5F9FF; }
    .att-note.empty { color: #A3B1C2; font-style: italic; }
    .att-addcol { width: 104px; text-align: center; background: repeating-linear-gradient(45deg, #FAFCFF, #FAFCFF 8px, #F3F7FC 8px, #F3F7FC 16px) !important; }
    .att-addcol button { display: inline-flex; flex-direction: column; align-items: center; gap: 4px; padding: 6px; border: 0; background: none; font: inherit; font-size: 12px; font-weight: 800; color: #2563EB; cursor: pointer; }
    .att-addcol button svg { width: 18px; height: 18px; }
    .att-tot { width: 92px; text-align: center; white-space: nowrap; }
    .att-tot b { display: block; font-size: 13px; color: #0F2F57; }
    .att-c { text-align: center; }
    .att-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-top: 12px; font-size: 12px; color: #64748B; }
    .att-menu, .att-ed { position: fixed; z-index: 80; padding: 6px; border: 1px solid #E2E8F0; border-radius: 14px; background: #fff; box-shadow: 0 18px 40px rgba(15, 35, 60, .18); }
    .att-menu { display: grid; gap: 2px; width: 170px; }
    .att-menu button { display: flex; align-items: center; justify-content: space-between; padding: 6px 8px; border: 0; border-radius: 9px; background: none; cursor: pointer; }
    .att-menu button:hover, .att-menu button.on { background: #F1F5F9; }
    .att-menu kbd { font: inherit; font-size: 10.5px; font-weight: 700; color: #94A3B8; }
    .att-ed { width: 300px; padding: 10px; }
    .att-ed textarea { width: 100%; box-sizing: border-box; }
    .att-quick { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 8px; }
    .att-quick button { padding: 3px 8px; border: 1px solid #E2E8F0; border-radius: 999px; background: #F8FAFC; font: inherit; font-size: 11px; font-weight: 600; color: #475569; cursor: pointer; }
    .att-quick button:hover { border-color: #93C5FD; color: #1D4ED8; }
    .att-ed-acts { display: flex; align-items: center; gap: 6px; margin-top: 8px; } .att-ed-acts small { margin-right: auto; font-size: 11px; color: #94A3B8; }
    .att-sdlg { width: min(820px, 100%); }
    @media (max-width: 640px) { .att-name { min-width: 172px; max-width: 190px; } .att-name .csp-av { width: 26px; height: 26px; font-size: 10px; } .att-name .csp-who { gap: 6px; } .att-contact, .att-t .att-no { display: none; } .att-t td.att-name .csp-who b { font-size: 12px; white-space: normal; } .att-search input { width: 120px; } }
    @media (min-width: 700px) { .att-time { grid-template-columns: minmax(0, 1fr) 36px minmax(0, 1fr); gap: 12px; } .att-time .tsf-arrow { display: grid; place-items: center; align-self: center; width: 36px; height: 36px; border-radius: 50%; border: 1px solid #E2E8F0; background: #fff; color: #64748B; } .att-time .tsf-arrow svg { width: 16px; height: 16px; } }
    @media print {
        @page { size: A4 landscape; margin: 10mm; }
        .att-print-title { display: block; margin-bottom: 8px; } .att-print-title h2 { margin: 0; font-size: 16px; } .att-print-title p { margin: 2px 0 0; font-size: 11px; color: #475569; }
        .att-wrap { overflow: visible; border: 0; }
        .att-t th, .att-t td { padding: 4px 5px; border: 1px solid #CBD5E1; font-size: 10px; }
        .att-name { position: static; box-shadow: none; min-width: 0; }
        .att-pill { min-width: 0; height: auto; padding: 1px 6px; border: 0 !important; background: none !important; color: #000 !important; font-size: 10px; }
        .att-pill.p-none { color: #94A3B8 !important; }
        .att-note.empty { visibility: hidden; }
        .att-tot .csp-pill { display: none; }
        .att-contact { display: none; }
    }
</style>
<script>
    window.cspAtt = function (cfg) {
        const LBL = { present: 'Có mặt', late: 'Muộn', excused: 'Có phép', absent: 'Vắng' };
        const came = (s) => s === 'present' || s === 'late';
        return {
            cells: cfg.cells || {}, q: '', menu: null, ed: null, edText: '', pend: false, can: cfg.can, base: {},
            opts: [['present', 'Có mặt', 'C'], ['late', 'Muộn', 'M'], ['excused', 'Có phép', 'P'], ['absent', 'Vắng', 'V']],
            quick: ['Hăng hái phát biểu', 'Làm bài tốt', 'Hiểu bài nhanh', 'Cần cố gắng hơn', 'Chưa làm bài tập', 'Đi muộn'],
            init() {
                // Tổng "Đi học" của server tính trên CẢ khoảng ngày; trừ phần các cột đang hiện để cộng lại theo thao tác trên trang.
                (cfg.rows || []).forEach((r) => {
                    let c = 0, m = 0;
                    (cfg.cols || []).forEach((sid) => { const x = this.cells[sid + ':' + r.id]; if (x && x.s) { m++; if (came(x.s)) c++; } });
                    this.base[r.id] = { came: r.came - c, marked: r.marked - m };
                });
                this._kd = (e) => {
                    if (!this.menu || e.ctrlKey || e.metaKey || e.altKey) return;
                    const map = { c: 'present', m: 'late', p: 'excused', v: 'absent' };
                    const s = map[(e.key || '').toLowerCase()];
                    if (s) { e.preventDefault(); this.pick(s); }
                };
                window.addEventListener('keydown', this._kd);
            },
            // SỬA 6/11 — đổi tab không tải lại trang: gỡ phím tắt khi khối điểm danh bị thay bằng tab khác.
            destroy() { if (this._kd) window.removeEventListener('keydown', this._kd); document.documentElement.style.overflow = ''; },
            match(name) { const k = this.q.trim().toLowerCase(); return !k || name.includes(k); },
            st(k) { return this.cells[k] ? this.cells[k].s : null; },
            note(k) { return this.cells[k] ? this.cells[k].n : ''; },
            label(k) { return LBL[this.st(k)] || '—'; },
            pos(ev, w, h) {
                const r = ev.currentTarget.getBoundingClientRect();
                let x = Math.min(r.left, window.innerWidth - w - 8), y = r.bottom + 6;
                if (y + h > window.innerHeight - 8) y = Math.max(8, r.top - h - 6);
                return { x: Math.max(8, x), y };
            },
            openMenu(k, ev) { if (!this.can) return; this.ed = null; this.menu = { k, ...this.pos(ev, 170, 160) }; },
            pick(s) { if (!this.menu) return; const k = this.menu.k; this.menu = null; this.save(k, { status: s }); },
            openNote(k, ev) { if (!this.can) return; this.menu = null; this.edText = this.note(k); this.ed = { k, ...this.pos(ev, 300, 190) }; this.$nextTick(() => this.$refs.edt && this.$refs.edt.focus()); },
            saveNote() { if (!this.ed) return; const k = this.ed.k; this.ed = null; this.save(k, { note: this.edText }); },
            markAll(sid) {
                (cfg.rows || []).forEach((r) => { const k = sid + ':' + r.id; if (!this.st(k)) this.save(k, { status: 'present' }); });
            },
            save(k, payload) {
                const [sid, uid] = k.split(':').map(Number);
                const prev = this.cells[k] ? { ...this.cells[k] } : null;
                const next = { s: payload.status || (prev && prev.s) || 'present', n: payload.note !== undefined ? payload.note.trim() : (prev ? prev.n : '') };
                this.cells[k] = next;
                fetch(cfg.url, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ session_id: sid, student_id: uid, ...payload }),
                }).then((r) => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                  .then((j) => { if (j.cell) this.cells[k] = j.cell; })
                  .catch(() => {
                      if (prev) this.cells[k] = prev; else delete this.cells[k];
                      window.toast && window.toast('error', 'Chưa lưu được — kiểm tra mạng rồi thử lại.');
                  });
            },
            total(uid) {
                const b = this.base[uid] || { came: 0, marked: 0 };
                let c = b.came, m = b.marked;
                (cfg.cols || []).forEach((sid) => { const s = this.st(sid + ':' + uid); if (s) { m++; if (came(s)) c++; } });
                return { came: c, marked: m };
            },
            rate(uid) { const t = this.total(uid); return t.marked ? Math.round(t.came / t.marked * 100) + '%' : '—'; },
            rateTone(uid) { const t = this.total(uid); if (!t.marked) return 'p-gray'; const r = t.came / t.marked; return r >= 0.9 ? 'p-ok' : (r >= 0.7 ? 'p-ex' : 'p-no'); },
            colPresent(sid) { return (cfg.rows || []).filter((r) => came(this.st(sid + ':' + r.id))).length; },
        };
    };
    window.cspSess = function (cfg) {
        const o = window.tsfForm({ sd: '', sh: '', sm: '', ed: '', eh: '', em: '' });
        o.init = function () {};
        o.open = false; o.id = null; o.topic = ''; o.location = ''; o.submitting = false; o.action = cfg.store;
        o.show = function (s) {
            this.tried = false; this.submitting = false;
            if (s && s.id) {
                Object.assign(this, { id: s.id, topic: s.topic, location: s.location, sd: s.sd, sh: s.sh, sm: s.sm, ed: s.ed, eh: s.eh, em: s.em });
                this.action = cfg.update.replace(/\/0$/, '/' + s.id);
                this.lastDur = this.dur > 0 ? this.dur : 90;
            } else {
                const n = window.tsfForm({ sd: '', sh: '', sm: '', ed: '', eh: '', em: '' });
                Object.assign(this, { id: null, topic: '', location: '', sd: n.sd, sh: n.sh, sm: n.sm });
                this.action = cfg.store;
                this.setDur(90);
            }
            this.open = true; document.documentElement.style.overflow = 'hidden';
            this.$nextTick(() => this.$refs.topic && this.$refs.topic.focus());
        };
        o.close = function () { this.open = false; document.documentElement.style.overflow = ''; };
        return o;
    };
</script>
