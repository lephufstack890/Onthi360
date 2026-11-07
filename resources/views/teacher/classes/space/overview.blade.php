{{-- SỬA 6/11 — Tab 1 · Tổng quan lớp (ClassSpaceService::overview). --}}
@php
    $ov = $spaceData ?? [];
    $cspUrl = fn ($t) => route('teacher.classes.show', ['class' => $classRoom->id, 'tab' => $t]);
@endphp
<div class="csp-grid csp-stats">
    <div class="csp-card" style="display:flex;align-items:center;gap:14px;margin:0">
        <span class="csp-ic" style="width:46px;height:46px;border-radius:14px"><x-lucide name="users" /></span>
        <div><small class="csp-mut">Sĩ số</small><b class="csp-big">{{ $ov['students'] ?? 0 }}</b><span class="csp-sub">học sinh đang học</span></div>
    </div>
    <div class="csp-card" style="display:flex;align-items:center;gap:14px;margin:0">
        <span class="csp-ic g" style="width:46px;height:46px;border-radius:14px"><x-lucide name="list-checks" /></span>
        <div><small class="csp-mut">Tỉ lệ đi học</small><b class="csp-big">{{ $ov['attRate'] !== null ? $ov['attRate'].'%' : '—' }}</b><span class="csp-sub">tính trên các buổi đã điểm danh</span></div>
    </div>
    <div class="csp-card" style="display:flex;align-items:center;gap:14px;margin:0">
        <span class="csp-ic v" style="width:46px;height:46px;border-radius:14px"><x-lucide name="layers" /></span>
        <div><small class="csp-mut">Tiến trình đã mở</small><b class="csp-big">{{ $ov['stepsOpen'] ?? 0 }} / {{ $ov['stepsTotal'] ?? 0 }} bước</b><span class="csp-sub" style="color:#7C3AED">{{ $ov['currentStep'] ? 'Đang học: '.$ov['currentStep'] : 'Chưa mở bước nào' }}</span></div>
    </div>
    <div class="csp-card" style="display:flex;align-items:center;gap:14px;margin:0">
        <span class="csp-ic o" style="width:46px;height:46px;border-radius:14px"><x-lucide name="clipboard-list" /></span>
        <div><small class="csp-mut">Bài chờ chấm</small><b class="csp-big">{{ $ov['grading'] ?? 0 }}</b><span class="csp-sub" style="color:#EA580C">{{ ($ov['closingSoon'] ?? 0) > 0 ? $ov['closingSoon'].' bài sắp hết hạn (3 ngày)' : 'Không có bài sắp hết hạn' }}</span></div>
    </div>
</div>

<div class="csp-grid csp-2" style="margin-top:14px">
    <div class="csp-card" style="margin:0">
        <div class="csp-ch"><span class="csp-ic"><x-lucide name="calendar-days" /></span><div class="grow"><h3>Buổi học sắp tới</h3><p>{{ count($ov['upcoming'] ?? []) }} buổi gần nhất</p></div><a href="{{ $cspUrl('attendance') }}" class="csp-btn sm">Mở sổ điểm danh</a></div>
        @forelse ($ov['upcoming'] ?? [] as $s)
            <div class="csp-row">
                <span class="csp-day"><small>{{ $s['dow'] }}</small><b>{{ $s['day'] }}</b></span>
                <div style="flex:1;min-width:0"><b style="color:#0F2F57">{{ $s['topic'] }}</b><div class="csp-mut" style="font-size:12px">{{ $s['time'] }}{{ $s['location'] ? ' · '.$s['location'] : '' }}</div></div>
                <span class="csp-pill p-{{ $s['tone'] }}">{{ $s['status'] }}</span>
            </div>
        @empty
            <div class="csp-empty"><x-lucide name="calendar-days" /><span>Chưa có buổi học sắp tới — thêm buổi ở tab <a href="{{ $cspUrl('attendance') }}">Điểm danh</a>.</span></div>
        @endforelse
    </div>
    <div class="csp-card" style="margin:0">
        <div class="csp-ch"><span class="csp-ic o"><x-lucide name="target" /></span><div class="grow"><h3>Cần chú ý</h3><p>Vắng nhiều, điểm thấp, chưa nộp bài</p></div></div>
        @forelse ($ov['attention'] ?? [] as $p)
            <div class="csp-row" style="flex-wrap:wrap">
                <span class="csp-who"><span class="csp-av h{{ $p['hue'] }}">@if ($p['avatar'])<img src="{{ $p['avatar'] }}" alt="">@else{{ $p['initials'] }}@endif</span><b>{{ $p['name'] }}</b></span>
                <span style="flex:1"></span>
                @foreach ($p['items'] as $it)<span class="csp-pill p-{{ $it['tone'] }}">{{ $it['text'] }}</span>@endforeach
            </div>
        @empty
            <div class="csp-empty"><x-lucide name="check-circle-2" /><span>Chưa có học sinh nào cần chú ý.</span></div>
        @endforelse
    </div>
</div>

<div class="csp-grid csp-2e" style="margin-top:14px">
    <div class="csp-card" style="margin:0">
        <div class="csp-ch"><span class="csp-ic v"><x-lucide name="layers" /></span><div class="grow"><h3>Tiến trình dạy học</h3><p>Các bước đã mở và tỉ lệ học sinh hoàn thành</p></div><a href="{{ $cspUrl('progress') }}" class="csp-btn sm">Quản lý</a></div>
        @forelse ($ov['steps'] ?? [] as $st)
            <div style="margin-top:10px">
                <div style="display:flex;justify-content:space-between;gap:10px;font-weight:700;font-size:13px"><span style="color:#0F2F57">{{ $st['title'] }}</span><span class="csp-mut" style="font-size:12px">{{ $st['pct'] !== null ? $st['pct'].'% hoàn thành' : 'Không có bài tập' }}</span></div>
                <div class="csp-pbar"><i style="width: {{ $st['pct'] ?? 0 }}%"></i></div>
            </div>
        @empty
            <div class="csp-empty"><x-lucide name="layers" /><span>Chưa mở bước nào — tạo tiến trình ở tab <a href="{{ $cspUrl('progress') }}">Tiến trình dạy học</a>.</span></div>
        @endforelse
    </div>
    <div class="csp-card" style="margin:0">
        <div class="csp-ch"><span class="csp-ic o"><x-lucide name="megaphone" /></span><div class="grow"><h3>Thông báo gần đây</h3><p>Gửi tới học sinh &amp; phụ huynh</p></div><a href="{{ $cspUrl('notices') }}" class="csp-btn sm">Gửi thông báo</a></div>
        @forelse ($ov['notices'] ?? [] as $n)
            <div class="csp-row"><span style="font-size:18px">📣</span><div style="min-width:0"><b style="color:#0F2F57">{{ $n['title'] ?: 'Thông báo lớp' }}</b><div class="csp-mut" style="font-size:12px">{{ $n['meta'] }}</div></div></div>
        @empty
            <div class="csp-empty"><x-lucide name="megaphone" /><span>Lớp chưa có thông báo nào.</span></div>
        @endforelse
    </div>
</div>
<style>
    .csp-mut { color: #64748B; font-weight: 700; }
    .csp-big { display: block; font-size: 22px; line-height: 1.25; font-weight: 800; color: #0F2F57; }
    .csp-sub { display: block; font-size: 12px; font-weight: 700; color: #16A34A; }
    .csp-day { display: grid; place-items: center; width: 50px; height: 50px; flex: none; border-radius: 13px; background: #EEF4FF; color: #1D4ED8; line-height: 1.1; text-align: center; }
    .csp-day small { font-size: 11px; font-weight: 700; } .csp-day b { font-size: 18px; }
    .csp-pbar { height: 8px; margin-top: 6px; border-radius: 9px; background: #EEF2F7; overflow: hidden; }
    .csp-pbar i { display: block; height: 100%; border-radius: 9px; background: linear-gradient(90deg, #7C3AED, #A78BFA); }
    .csp-empty a { color: #1D4ED8; font-weight: 700; }
</style>
