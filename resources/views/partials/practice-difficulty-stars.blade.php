{{-- Độ khó dạng sao — dựng theo education-main/src/components/DifficultyStars.jsx.
     Tham số: $level (1-5 hoặc null), $label (nhãn, tuỳ chọn), $showLabel (mặc định false).
     Chưa xếp độ khó thì hiện "Chưa xếp độ khó" đúng như bản mẫu. --}}
@php
    $dsLevel = isset($level) && is_numeric($level) && (int) $level >= 1 && (int) $level <= 5 ? (int) $level : null;
    $dsLabel = $label ?? null;
    $dsShowLabel = $showLabel ?? false;
@endphp
@if ($dsLevel === null)
    <span class="oi-muted-note">Chưa xếp độ khó</span>
@else
    <span class="oi-stars" role="img" aria-label="Độ khó {{ $dsLevel }} trên 5 sao{{ $dsLabel ? ': '.$dsLabel : '' }}" title="{{ $dsLevel }}/5{{ $dsLabel ? ' · '.$dsLabel : '' }}">
        @for ($i = 0; $i < 5; $i++)
            <x-lucide name="star" class="oi-star {{ $i < $dsLevel ? 'is-on' : 'is-off' }}" />
        @endfor
        <span class="oi-stars-num">{{ $dsLevel }}/5</span>
        @if ($dsShowLabel && $dsLabel)<span class="oi-stars-label">{{ $dsLabel }}</span>@endif
    </span>
@endif
