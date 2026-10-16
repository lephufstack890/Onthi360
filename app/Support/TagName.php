<?php

namespace App\Support;

/**
 * SỬA 16/10 (khách: "nhập câu hỏi hoặc nhập đề bằng file ZIP thì tag CÓ DẤU, ví dụ 'nhập xuất', đừng kiểu 'nhap-xuat' —
 * tương tự toàn bộ trường hợp") — chuẩn hoá tên tag đọc từ taxonomy.tags / taxonomy.keywords của gói ZIP
 * (Kho câu hỏi admin + giáo viên, bài tập trong tài liệu, đề Luyện tập nhập ZIP):
 *   - tag đã có dấu ("Nhập xuất", "vòng lặp") -> giữ nguyên chữ, chỉ gọn khoảng trắng;
 *   - tag kiểu slug không dấu ("nhap-xuat", "vong_lap", "so nguyen to") -> tra từ điển cụm từ tin học / toán thường gặp
 *     (khớp cụm dài nhất trước) -> "nhập xuất", "vòng lặp", "số nguyên tố"; từ nào không có trong từ điển thì giữ nguyên
 *     nhưng gạch nối / gạch dưới đổi thành khoảng trắng (không bao giờ còn "nhap-xuat").
 * key() = khoá so khớp không dấu, không phân biệt hoa thường / gạch nối — để "nhap-xuat", "Nhap Xuat", "nhập xuất" coi là
 * CÙNG 1 tag (TagRepository::idsFromPackageNames(), migration 001230 gộp tag cũ).
 */
final class TagName
{
    /** Cụm không dấu (cách nhau 1 khoảng trắng) => tên có dấu. */
    private const PHRASES = [
        // Lập trình cơ bản
        'nhap xuat' => 'nhập xuất', 'nhap xuat du lieu' => 'nhập xuất dữ liệu', 'vao ra' => 'vào ra',
        'vong lap' => 'vòng lặp', 'vong lap for' => 'vòng lặp for', 'vong lap while' => 'vòng lặp while',
        're nhanh' => 'rẽ nhánh', 'cau truc re nhanh' => 'cấu trúc rẽ nhánh',
        'cau lenh' => 'câu lệnh', 'cau lenh dieu kien' => 'câu lệnh điều kiện', 'dieu kien' => 'điều kiện',
        're nhanh if' => 'rẽ nhánh if', 'bien' => 'biến', 'ham' => 'hàm', 'thu tuc' => 'thủ tục',
        'tham so' => 'tham số', 'toan tu' => 'toán tử', 'bieu thuc' => 'biểu thức', 'kieu du lieu' => 'kiểu dữ liệu',
        'lap trinh' => 'lập trình', 'lap trinh co ban' => 'lập trình cơ bản', 'thuat toan' => 'thuật toán',
        'cau truc du lieu' => 'cấu trúc dữ liệu', 'co ban' => 'cơ bản', 'nang cao' => 'nâng cao', 'mo phong' => 'mô phỏng',
        'tin hoc' => 'tin học', 'toan' => 'toán', 'toan hoc' => 'toán học', 'logic' => 'logic', 'chu thich' => 'chú thích',
        'gan' => 'gán', 'phep gan' => 'phép gán', 'dinh dang' => 'định dạng', 'dinh dang xuat' => 'định dạng xuất',
        // Dữ liệu
        'danh sach' => 'danh sách', 'mang' => 'mảng', 'mang mot chieu' => 'mảng một chiều', 'mang 1 chieu' => 'mảng 1 chiều',
        'mang hai chieu' => 'mảng hai chiều', 'mang 2 chieu' => 'mảng 2 chiều', 'ma tran' => 'ma trận',
        'xau' => 'xâu', 'xau ky tu' => 'xâu ký tự', 'xau ki tu' => 'xâu kí tự', 'chuoi' => 'chuỗi', 'chuoi ky tu' => 'chuỗi ký tự',
        'xu ly xau' => 'xử lý xâu', 'xu ly chuoi' => 'xử lý chuỗi', 'ky tu' => 'ký tự', 'ki tu' => 'kí tự',
        'tu dien' => 'từ điển', 'tap hop' => 'tập hợp', 'ngan xep' => 'ngăn xếp', 'hang doi' => 'hàng đợi',
        'danh sach lien ket' => 'danh sách liên kết', 'tep' => 'tệp', 'tep tin' => 'tệp tin', 'doc ghi tep' => 'đọc ghi tệp',
        'doc tep' => 'đọc tệp', 'ghi tep' => 'ghi tệp', 'day so' => 'dãy số', 'day con' => 'dãy con',
        'day con lien tiep' => 'dãy con liên tiếp', 'day con tang' => 'dãy con tăng', 'cong don' => 'cộng dồn',
        'mang cong don' => 'mảng cộng dồn', 'tong tien to' => 'tổng tiền tố', 'tien to' => 'tiền tố', 'hau to' => 'hậu tố',
        'hai con tro' => 'hai con trỏ', 'con tro' => 'con trỏ', 'cua so truot' => 'cửa sổ trượt',
        // Thuật toán
        'sap xep' => 'sắp xếp', 'tim kiem' => 'tìm kiếm', 'tim kiem nhi phan' => 'tìm kiếm nhị phân',
        'tim kiem tuan tu' => 'tìm kiếm tuần tự', 'chat nhi phan' => 'chặt nhị phân', 'nhi phan' => 'nhị phân',
        'de quy' => 'đệ quy', 'quay lui' => 'quay lui', 'vet can' => 'vét cạn', 'quy hoach dong' => 'quy hoạch động',
        'tham lam' => 'tham lam', 'chia de tri' => 'chia để trị', 'do thi' => 'đồ thị', 'cay' => 'cây',
        'duyet' => 'duyệt', 'duyet do thi' => 'duyệt đồ thị', 'duong di ngan nhat' => 'đường đi ngắn nhất',
        'loang' => 'loang', 'dem' => 'đếm', 'dem phan phoi' => 'đếm phân phối', 'so sanh' => 'so sánh',
        'dao nguoc' => 'đảo ngược', 'doi xung' => 'đối xứng', 'xu ly so' => 'xử lý số', 'bang bam' => 'bảng băm',
        // Số học / toán
        'so hoc' => 'số học', 'so nguyen' => 'số nguyên', 'so nguyen to' => 'số nguyên tố', 'nguyen to' => 'nguyên tố',
        'so chan' => 'số chẵn', 'so le' => 'số lẻ', 'chan le' => 'chẵn lẻ', 'so thuc' => 'số thực', 'so tu nhien' => 'số tự nhiên',
        'so chinh phuong' => 'số chính phương', 'so hoan hao' => 'số hoàn hảo', 'so doi xung' => 'số đối xứng',
        'so fibonacci' => 'số Fibonacci', 'chu so' => 'chữ số', 'tong chu so' => 'tổng chữ số', 'uoc' => 'ước', 'boi' => 'bội',
        'uoc so' => 'ước số', 'boi so' => 'bội số', 'uoc chung' => 'ước chung', 'uoc chung lon nhat' => 'ước chung lớn nhất',
        'boi chung nho nhat' => 'bội chung nhỏ nhất', 'phan so' => 'phân số', 'tong' => 'tổng', 'tich' => 'tích', 'hieu' => 'hiệu',
        'thuong' => 'thương', 'chia lay du' => 'chia lấy dư', 'phep chia' => 'phép chia', 'so du' => 'số dư',
        'lam tron' => 'làm tròn', 'luy thua' => 'lũy thừa', 'can bac hai' => 'căn bậc hai', 'gia tri tuyet doi' => 'giá trị tuyệt đối',
        'gia tri lon nhat' => 'giá trị lớn nhất', 'gia tri nho nhat' => 'giá trị nhỏ nhất', 'lon nhat' => 'lớn nhất',
        'nho nhat' => 'nhỏ nhất', 'trung binh' => 'trung bình', 'trung binh cong' => 'trung bình cộng', 'hinh hoc' => 'hình học',
        'to hop' => 'tổ hợp', 'chinh hop' => 'chỉnh hợp', 'hoan vi' => 'hoán vị', 'xac suat' => 'xác suất',
        'phuong trinh' => 'phương trình', 'bat phuong trinh' => 'bất phương trình', 'he dem' => 'hệ đếm',
        'he nhi phan' => 'hệ nhị phân', 'doi co so' => 'đổi cơ số', 'nam nhuan' => 'năm nhuận', 'ngay thang' => 'ngày tháng',
        'thoi gian' => 'thời gian', 'dien tich' => 'diện tích', 'chu vi' => 'chu vi', 'tam giac' => 'tam giác',
        'hinh chu nhat' => 'hình chữ nhật', 'hinh tron' => 'hình tròn', 'toa do' => 'tọa độ', 'khoang cach' => 'khoảng cách',
        // Khác
        'de thi' => 'đề thi', 'luyen tap' => 'luyện tập', 'on thi' => 'ôn thi', 'hoc sinh gioi' => 'học sinh giỏi',
        'trac nghiem' => 'trắc nghiệm', 'dung sai' => 'đúng sai', 'dien tu' => 'điền từ', 'tu luan' => 'tự luận',
    ];

    /** Chữ Việt có dấu => không dấu (tự chứa, không phụ thuộc thư viện ngoài). */
    private const VI_ASCII = [
        'à' => 'a', 'á' => 'a', 'ạ' => 'a', 'ả' => 'a', 'ã' => 'a', 'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ậ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a',
        'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ặ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'è' => 'e', 'é' => 'e', 'ẹ' => 'e', 'ẻ' => 'e', 'ẽ' => 'e',
        'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ệ' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ì' => 'i', 'í' => 'i', 'ị' => 'i', 'ỉ' => 'i', 'ĩ' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ọ' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ộ' => 'o', 'ổ' => 'o', 'ỗ' => 'o',
        'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ợ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ù' => 'u', 'ú' => 'u', 'ụ' => 'u', 'ủ' => 'u', 'ũ' => 'u',
        'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ự' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ỳ' => 'y', 'ý' => 'y', 'ỵ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y',
        'đ' => 'd',
    ];

    /** Tên tag hiển thị từ 1 giá trị trong gói ZIP ('' nếu rỗng). */
    public static function fromPackage(string $raw): string
    {
        $raw = self::squash($raw);
        if ($raw === '' || self::hasMarks($raw)) {
            return $raw;
        }

        $words = preg_split('/[\s\-_]+/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lower = array_map(fn ($w) => mb_strtolower($w, 'UTF-8'), $words);
        $out = [];
        $n = count($words);
        for ($i = 0; $i < $n;) {
            $hit = null;
            for ($len = min(5, $n - $i); $len >= 1; $len--) {
                $phrase = implode(' ', array_slice($lower, $i, $len));
                if (isset(self::PHRASES[$phrase])) {
                    $hit = [self::PHRASES[$phrase], $len];
                    break;
                }
            }
            if ($hit) {
                $out[] = $hit[0];
                $i += $hit[1];
            } else {
                $out[] = $words[$i];
                $i++;
            }
        }

        return self::squash(implode(' ', $out));
    }

    /** Khoá so khớp: không dấu, chữ thường, gạch nối / gạch dưới / dấu câu => 1 khoảng trắng. */
    public static function key(string $name): string
    {
        $s = strtr(mb_strtolower($name, 'UTF-8'), self::VI_ASCII);
        $s = preg_replace('/[^a-z0-9+#]+/u', ' ', $s) ?? $s;

        return trim($s);
    }

    /** Có chữ Việt có dấu (hoặc đ) hay không. */
    public static function hasMarks(string $name): bool
    {
        $lower = mb_strtolower($name, 'UTF-8');

        return strtr($lower, self::VI_ASCII) !== $lower;
    }

    private static function squash(string $s): string
    {
        return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    }
}
