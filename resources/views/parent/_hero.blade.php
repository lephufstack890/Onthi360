{{-- Hero() của source: ảnh nền + lớp phủ chuyển sắc xanh. Nhận $eyebrow, $title, $desc, tuỳ chọn $cta = ['label','href','icon']. --}}
<section class="pw-hero">
    <img src="{{ asset('assets/workspace-parent-hero.jpg') }}" alt="" decoding="async">
    <div class="pw-hero-in">
        <div style="min-width:0">
            <small>{{ $eyebrow ?? 'Đồng hành cùng con' }}</small>
            <h1>{{ $title }}</h1>
            @if (! empty($desc))<p>{{ $desc }}</p>@endif
        </div>
        @if (! empty($cta))
            <div><a class="pw-hero-btn" href="{{ $cta['href'] }}">@if (! empty($cta['icon']))<x-lucide :name="$cta['icon']" />@endif{{ $cta['label'] }}</a></div>
        @endif
    </div>
</section>
