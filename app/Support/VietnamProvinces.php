<?php

namespace App\Support;

/**
 * Danh sách tỉnh/thành + khu vực dùng cho thuộc tính "tỉnh thành và khu vực" của người dùng
 * (note họp 13/8, mục 2: "để quảng cáo cho giáo viên" — lọc/gợi ý giáo viên theo khu vực).
 * Tập trung ở 1 nơi để mọi form (hồ sơ giáo viên/học sinh, sửa người dùng ở Admin) dùng
 * chung, tránh lệch danh sách giữa các nơi — giống tinh thần danh sách "grades" dùng chung
 * giữa Admin\ProductService và Admin\CourseService.
 */
final class VietnamProvinces
{
    /** @return array<int, string> 63 tỉnh/thành — tên hiển thị = giá trị lưu (đơn giản, dễ đọc). */
    public static function options(): array
    {
        return [
            'An Giang', 'Bà Rịa - Vũng Tàu', 'Bạc Liêu', 'Bắc Giang', 'Bắc Kạn', 'Bắc Ninh',
            'Bến Tre', 'Bình Dương', 'Bình Định', 'Bình Phước', 'Bình Thuận', 'Cà Mau',
            'Cần Thơ', 'Cao Bằng', 'Đà Nẵng', 'Đắk Lắk', 'Đắk Nông', 'Điện Biên', 'Đồng Nai',
            'Đồng Tháp', 'Gia Lai', 'Hà Giang', 'Hà Nam', 'Hà Nội', 'Hà Tĩnh', 'Hải Dương',
            'Hải Phòng', 'Hậu Giang', 'Hòa Bình', 'Hưng Yên', 'Khánh Hòa', 'Kiên Giang',
            'Kon Tum', 'Lai Châu', 'Lâm Đồng', 'Lạng Sơn', 'Lào Cai', 'Long An', 'Nam Định',
            'Nghệ An', 'Ninh Bình', 'Ninh Thuận', 'Phú Thọ', 'Phú Yên', 'Quảng Bình',
            'Quảng Nam', 'Quảng Ngãi', 'Quảng Ninh', 'Quảng Trị', 'Sóc Trăng', 'Sơn La',
            'Tây Ninh', 'Thái Bình', 'Thái Nguyên', 'Thanh Hóa', 'Thừa Thiên Huế',
            'Tiền Giang', 'TP. Hồ Chí Minh', 'Trà Vinh', 'Tuyên Quang', 'Vĩnh Long',
            'Vĩnh Phúc', 'Yên Bái',
        ];
    }

    /** @return array<string, string> value => nhãn hiển thị. */
    public static function regionOptions(): array
    {
        return [
            'mien_bac' => 'Miền Bắc',
            'mien_trung' => 'Miền Trung',
            'mien_nam' => 'Miền Nam',
        ];
    }
}
