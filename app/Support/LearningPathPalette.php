<?php

namespace App\Support;

/**
 * Dải màu các bậc của lộ trình.
 *
 * Nhìn ảnh thiết kế khách gửi: 6 bậc đi theo dải cam → vàng → lục → lam → chàm → tím. Tức là
 * màu đến TỪ THỨ TỰ BẬC, không phải thuộc tính riêng của từng khoá học. Vì vậy không có cột
 * màu trong CSDL — sinh ra ở đây.
 *
 * Lợi ích: khách thêm bậc thứ 7 thì màu tự giãn, không ai phải chọn tay và không bao giờ có
 * hai bậc cạnh nhau trùng màu. Muốn đổi bảng màu toàn hệ thống thì sửa đúng tệp này.
 */
final class LearningPathPalette
{
    /**
     * Sáu nấc màu theo đúng thứ tự trong ảnh. Mỗi nấc gồm: màu nền đậm (thân bậc), nền nhạt
     * và màu chữ (viên nhãn), để cả trang công khai lẫn màn quản trị dùng chung.
     *
     * @var list<array{solid: string, soft: string, ink: string, ring: string}>
     */
    private const RAMP = [
        ['solid' => '#F2864B', 'soft' => '#FFF1E7', 'ink' => '#B4571F', 'ring' => '#F8C7A5'],
        ['solid' => '#F3B944', 'soft' => '#FFF8E6', 'ink' => '#9A7016', 'ring' => '#F7DFA3'],
        ['solid' => '#3FAE86', 'soft' => '#EBF8F3', 'ink' => '#26785B', 'ring' => '#A9DEC9'],
        ['solid' => '#3E9BDD', 'soft' => '#EAF5FD', 'ink' => '#1F6BA0', 'ring' => '#A6D4F2'],
        ['solid' => '#4C6FE0', 'soft' => '#EDF1FE', 'ink' => '#2F4BAB', 'ring' => '#B2C3F5'],
        ['solid' => '#8B5CD6', 'soft' => '#F3EDFC', 'ink' => '#5E36A0', 'ring' => '#CBB2ED'],
    ];

    /**
     * Màu của bậc thứ $index (đếm từ 0).
     *
     * Nhiều hơn 6 bậc thì quay vòng lại từ đầu — vẫn đẹp và vẫn không có hai bậc liền nhau
     * trùng màu, miễn là dải có từ 2 nấc trở lên.
     *
     * @return array{solid: string, soft: string, ink: string, ring: string}
     */
    public static function step(int $index): array
    {
        return self::RAMP[$index % count(self::RAMP)];
    }

    /** Số nấc màu có sẵn — dùng khi cần báo cho người dựng giao diện biết giới hạn. */
    public static function size(): int
    {
        return count(self::RAMP);
    }

    /**
     * Cả dải màu, để đưa sang phía trình duyệt.
     *
     * Màn xếp bậc cho kéo thả đổi thứ tự ngay tại chỗ, nên màu phải đổi theo vị trí mới mà
     * chưa tải lại trang. Truyền nguyên dải sang thay vì chép lại bảng màu trong JavaScript —
     * giữ đúng một nguồn sự thật.
     *
     * @return list<array{solid: string, soft: string, ink: string, ring: string}>
     */
    public static function ramp(): array
    {
        return self::RAMP;
    }
}
