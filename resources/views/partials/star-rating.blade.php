{{--
    SỬA 4/10 (khách gửi ảnh vẽ tay: "chỗ hiển thị số sao dạng ***** ngoài trang danh sách giáo
    viên chuyên gia ngoài public giúp tôi theo hình tôi vẽ") — DẢI 5 SAO.

    CÁCH LÀM: vẽ 5 sao xám làm nền, rồi phủ lên trên đúng 5 sao vàng nhưng bị cắt bớt theo tỉ lệ
    điểm. Nhờ vậy 4.8 sao ra đúng 4 sao vàng rưỡi-gần-đủ chứ không phải làm tròn thành 5 — người
    đọc nhìn là biết chưa tuyệt đối.

    KHÔNG dùng inset-y-0 (lớp đó chưa có trong bản CSS đã build). Dùng inset-0 rồi đặt width
    bằng style: khi khai cả left, right lẫn width thì trình duyệt bỏ qua right, nên width thắng.
    Lớp phủ phải có w-max, nếu không nó bị bóp theo bề ngang đã cắt và các ngôi sao co lại.

    Biến truyền vào: $rating (float|null), $starSize (lớp kích thước, mặc định h-4 w-4).
--}}
@php
    $srStars = 5;
    $srValue = max(0, min($srStars, (float) ($rating ?? 0)));
    $srPercent = $srStars > 0 ? $srValue / $srStars * 100 : 0;
    $srSize = $starSize ?? 'h-4 w-4';
@endphp

<span class="relative inline-block leading-none" role="img"
      aria-label="{{ $rating !== null ? number_format($srValue, 1).' trên '.$srStars.' sao' : 'Chưa có điểm đánh giá' }}">
    <span class="flex gap-0.5 text-slate-200" aria-hidden="true">
        @for ($i = 0; $i < $srStars; $i++)
            <x-lucide name="star" :class="$srSize" style="fill: currentColor" />
        @endfor
    </span>
    <span class="absolute inset-0 overflow-hidden" style="width: {{ $srPercent }}%" aria-hidden="true">
        <span class="flex w-max gap-0.5 text-amber-400">
            @for ($i = 0; $i < $srStars; $i++)
                <x-lucide name="star" :class="$srSize" style="fill: currentColor" />
            @endfor
        </span>
    </span>
</span>
