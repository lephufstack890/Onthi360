{{-- SỬA 8/9 (7) — bộ icon nét mảnh cho menu công khai (header + sidebar trang chủ), vẽ bằng SVG
     nội tuyến theo đúng bản design; không kéo thêm thư viện icon nào vào dự án.
     Dùng: <x-nav-icon name="home" /> — tên lạ thì rơi về chấm tròn trung tính. --}}
@props(['name' => 'dot', 'class' => 'w-4 h-4'])

@php
    $paths = [
        'home' => '<path d="M4 10.5 12 4l8 6.5V19a1 1 0 0 1-1 1h-4v-5H9v5H5a1 1 0 0 1-1-1v-8.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>',
        'class' => '<rect x="3.5" y="5" width="17" height="13" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 9.5h8M8 13h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
        'code' => '<path d="m9 8-4 4 4 4M15 8l4 4-4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
        'doc' => '<path d="M6 4h7l5 5v11a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M13 4v5h5M8.5 13h7M8.5 16.5h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
        'trophy' => '<path d="M8 4h8v5a4 4 0 1 1-8 0V4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M8 6H5.5v1.5A2.5 2.5 0 0 0 8 10M16 6h2.5v1.5A2.5 2.5 0 0 1 16 10M10 13h4l.5 3h-5l.5-3ZM8 19h8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
        'chart' => '<path d="M5 19V11M12 19V5M19 19v-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
        'users' => '<circle cx="9" cy="9" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M4 19a5 5 0 0 1 10 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M16 7.5a2.8 2.8 0 0 1 0 5.5M17 19a4.6 4.6 0 0 0-1.6-3.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
        'info' => '<circle cx="12" cy="12" r="8.2" stroke="currentColor" stroke-width="1.8"/><path d="M12 11v5M12 8.2v.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
        'bell' => '<path d="M6.5 10a5.5 5.5 0 0 1 11 0c0 4 1.5 5.5 1.5 5.5H5S6.5 14 6.5 10Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M10 18.5a2 2 0 0 0 4 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
        'dot' => '<circle cx="12" cy="12" r="4" fill="currentColor"/>',
    ];
@endphp

<svg viewBox="0 0 24 24" fill="none" {{ $attributes->merge(['class' => $class]) }} aria-hidden="true">
    {!! $paths[$name] ?? $paths['dot'] !!}
</svg>
