{{-- SỬA 6/11 (khách: "lớp phải là một giao diện riêng") — đầu không gian lớp: nút về danh sách lớp, dải thông tin lớp, 8 tab. --}}
@include('teacher.classes.space._style')
@php
    $cspDow = ['Chủ nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];
    $cspNext = isset($nextSession) && $nextSession ? 'Buổi tới: '.$cspDow[$nextSession->starts_at->dayOfWeek].' '.$nextSession->starts_at->format('d/m · H:i') : 'Chưa có buổi học sắp tới';
    $cspEnded = (int) ($completionEndedSessions ?? 0);
    $cspTotal = (int) ($completionTotalSessions ?? 0);
    $cspBadges = ['attendance' => (int) ($space['pendingCount'] ?? 0), 'results' => (int) ($space['gradingCount'] ?? 0)];
    $cspCoverText = mb_strtoupper(mb_substr(preg_replace('/^(lớp|lop)\s+/iu', '', $className) ?: 'L', 0, 3));
@endphp

<a href="{{ route('teacher.classes.index') }}" class="csp-back"><x-lucide name="chevron-left" />Về danh sách lớp</a>

<section class="csp-hero">
    <div class="csp-cover">
        @if ($tcxCover)<img src="{{ $tcxCover }}" alt="">@else{{ $cspCoverText }}@endif
    </div>
    <div class="csp-hmain">
        <div class="csp-chips">
            @if ($courseTitle !== '')<span class="csp-chip"><x-lucide name="graduation-cap" />{{ $courseTitle }}</span>@endif
            @if (! empty($space['roleLabel']))<span class="csp-chip gold">{{ $space['roleLabel'] }}</span>@endif
        </div>
        <h1>{{ $className }}</h1>
        <div class="csp-chips">
            <span class="csp-chip"><x-lucide name="users" />{{ $studentsCount }} học sinh</span>
            <span class="csp-chip"><x-lucide name="user-round" />{{ (int) ($space['teacherCount'] ?? 1) }} giáo viên</span>
            <span class="csp-chip"><x-lucide name="calendar-days" />{{ $cspNext }}</span>
            @if ($nextSession && $nextSession->location)<span class="csp-chip"><x-lucide name="map-pin" />{{ \Illuminate\Support\Str::limit($nextSession->location, 40) }}</span>@endif
        </div>
    </div>
    <div class="csp-hside">
        @if ($classRoom->code)<span class="csp-code">Mã lớp<b>{{ $classRoom->code }}</b></span>@endif
        <div class="csp-prog">
            <div><span>Tiến độ</span><span>{{ $cspTotal > 0 ? $cspEnded.' / '.$cspTotal.' buổi' : 'Chưa có buổi' }}</span></div>
            <div class="csp-bar"><i style="width: {{ $cspTotal > 0 ? min(100, round($cspEnded / $cspTotal * 100)) : 0 }}%"></i></div>
        </div>
    </div>
</section>

<nav class="csp-tabs" aria-label="Các mục của lớp">
    <p class="csp-tabs-h">Không gian lớp</p>
    @foreach (\App\Services\Teacher\ClassSpaceService::TABS as $key => [$label, $icon])
        <a href="{{ route('teacher.classes.show', ['class' => $classRoom->id, 'tab' => $key]) }}" @class(['csp-tab', 'on' => $tab === $key]) @if ($tab === $key) aria-current="page" @endif>
            <x-lucide :name="$icon" />{{ $label }}@if (($cspBadges[$key] ?? 0) > 0)<em>{{ $cspBadges[$key] }}</em>@endif
        </a>
    @endforeach
</nav>

@if (session('space_ok'))
    @include('partials.toast-flash', ['type' => 'success', 'message' => session('space_ok')])
@endif
@if (in_array($tab, ['overview', 'organizers', 'attendance', 'progress', 'results'], true) && $errors->any())
    @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
@endif
