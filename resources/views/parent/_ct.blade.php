{{-- CardTitle() của source. $icon, $title, tuỳ chọn $action + $href --}}
<div class="pw-ct">
    <div><span class="pw-ct-ic"><x-lucide :name="$icon" /></span><h2>{{ $title }}</h2></div>
    @if (! empty($action) && ! empty($href))<a href="{{ $href }}">{{ $action }}<x-lucide name="chevron-right" /></a>@endif
</div>
