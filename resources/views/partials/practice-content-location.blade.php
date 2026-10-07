{{-- Tỉnh/thành + khu vực — dựng theo education-main/src/components/ContentLocation.jsx: hai huy hiệu,
     chưa có thì hiện "Chưa có tỉnh/thành" / "Chưa có khu vực". Khu vực tô màu theo miền như bản mẫu.
     Tham số: $province, $region (nhãn tiếng Việt hoặc null). --}}
@php
    $clRegionTone = ['Miền Bắc' => 'is-north', 'Miền Trung' => 'is-central', 'Miền Nam' => 'is-south'][$region ?? ''] ?? '';
@endphp
<div class="oi-loc" aria-label="Địa phương">
    <span class="oi-loc-badge" title="Tỉnh/thành: {{ $province ?: 'Chưa cập nhật' }}">
        <x-lucide name="map-pin" /><span class="oi-loc-text">{{ $province ?: 'Chưa có tỉnh/thành' }}</span>
    </span>
    <span class="oi-loc-badge {{ $clRegionTone }}" title="Khu vực: {{ $region ?: 'Chưa cập nhật' }}">
        <x-lucide name="compass" /><span class="oi-loc-text">{{ $region ?: 'Chưa có khu vực' }}</span>
    </span>
</div>
