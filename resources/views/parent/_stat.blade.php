{{-- Stat() của source. $label, $value, $note, $icon, $tone (blue|emerald|amber|violet|rose) --}}
<div class="pw-stat">
    <div class="pw-stat-top">
        <div style="min-width:0"><p class="pw-stat-lb">{{ $label }}</p><p class="pw-stat-v">{{ $value }}</p></div>
        <span class="pw-stat-ic pw-t-{{ $tone ?? 'blue' }}"><x-lucide :name="$icon" /></span>
    </div>
    <p class="pw-stat-nt">{{ $note }}</p>
</div>
