{{-- Hero() của source cho khu Học sinh: ảnh nền + lớp phủ xanh. $eyebrow, $title, $desc, tuỳ chọn $cta = ['label','href','icon']. --}}
<section class="pw-hero">
    <img src="{{ asset('assets/workspace-student-hero.jpg') }}" alt="" decoding="async">
    <div class="pw-hero-in">
        <div style="min-width:0">
            <small>{{ $eyebrow ?? 'Không gian học tập' }}</small>
            <h1>{{ $title }}</h1>
            @if (! empty($desc))<p>{{ $desc }}</p>@endif
        </div>
        @if (! empty($cta))
            <div><a class="pw-hero-btn" href="{{ $cta['href'] }}">@if (! empty($cta['icon']))<x-lucide :name="$cta['icon']" />@endif{{ $cta['label'] }}</a></div>
        @endif
    </div>
</section>
