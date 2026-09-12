{{-- Ảnh đại diện bằng CHỮ CÁI ĐẦU, vẽ ngay tại chỗ.

     SỬA 12/9 — thay cho https://ui-avatars.com/... mà các màn quản trị đang dùng. Ngoài việc
     lệ thuộc dịch vụ ngoài (mất mạng là vỡ giao diện, tải chậm), cách cũ còn GỬI HỌ TÊN THẬT
     của người dùng sang máy chủ bên thứ ba ở mọi lần tải trang — dữ liệu này phần lớn là của
     học sinh, không nên để lọt ra ngoài. Ở đây không gọi ra ngoài một chút nào.

     Màu nền suy ra từ chính tên nên mỗi người luôn ra cùng một màu, không đổi mỗi lần tải. --}}
@props(['name' => '', 'size' => 'md'])
@php
    $clean = trim(preg_replace('/\s+/u', ' ', (string) $name));
    $parts = $clean !== '' ? preg_split('/\s+/u', $clean) : [];
    // Tiếng Việt đọc theo "Họ ... Tên", chữ cuối mới là tên gọi -> lấy chữ đầu + chữ cuối.
    $initials = $clean === ''
        ? '?'
        : mb_strtoupper(mb_substr($parts[0], 0, 1).(count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''), 'UTF-8');

    $palette = [
        'border-blue-200 bg-blue-50 text-blue-700',
        'border-emerald-200 bg-emerald-50 text-emerald-700',
        'border-amber-200 bg-amber-50 text-amber-700',
        'border-violet-200 bg-violet-50 text-violet-700',
        'border-sky-200 bg-sky-50 text-sky-700',
        'border-rose-200 bg-rose-50 text-rose-700',
    ];
    $tone = $palette[crc32($clean) % count($palette)];

    $sizes = [
        'sm' => 'h-7 w-7 text-[10px]',
        'md' => 'h-9 w-9 text-[11px]',
        'lg' => 'h-12 w-12 text-sm',
        'xl' => 'h-16 w-16 text-lg',
    ];
@endphp
<span role="img" aria-label="Ảnh đại diện của {{ $clean !== '' ? $clean : 'người dùng' }}"
      {{ $attributes->merge(['class' => 'inline-grid shrink-0 place-items-center rounded-full border font-black select-none '.$tone.' '.($sizes[$size] ?? $sizes['md'])]) }}>
    {{ $initials }}
</span>
