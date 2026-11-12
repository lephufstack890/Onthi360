<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 12/11 (khách gửi 2 danh sách tag chuyên đề) — chạy: php artisan db:seed --class=TagCatalogSeeder
 *
 *  1. EXISTING_CODING: các tag ĐÃ CÓ trong database, khách xác nhận đều thuộc loại "Lập trình" → gán kind = 'coding'.
 *  2. Danh sách bổ sung: tag nào CHƯA có thì thêm mới (đã có thì bỏ qua, không đổi tên / loại). Tag mới chia 2 loại:
 *     NEW_CODING (thuật toán / lập trình) và NEW_GENERAL (Tin học phổ thông: ứng dụng, xã hội số, mạng, đa phương tiện…).
 *
 * So khớp tên không phân biệt hoa thường, đã chuẩn hoá Unicode (NFC). Chạy lại nhiều lần không tạo trùng.
 */
class TagCatalogSeeder extends Seeder
{
    private const EXISTING_CODING = [
            'Ba lô',
            'Bài toán kinh điển',
            'Bao hàm loại trừ',
            'Bất đẳng thức',
            'BFS',
            'Biểu thức',
            'Cây',
            'Cây Fenwick',
            'Cây phân đoạn',
            'Chia để trị',
            'Chia đôi danh sách',
            'Chia khối',
            'Chu kỳ',
            'Chứng minh thuật toán',
            'Cơ bản',
            'Cơ sở dữ liệu',
            'CSS',
            'Cửa sổ trượt',
            'Cực trị',
            'Dãy số',
            'DFS',
            'DSU',
            'Duyệt',
            'Duyệt cặp',
            'Duyệt đoạn',
            'Duyệt tuyến tính',
            'Đánh dấu',
            'Đệ quy',
            'Đếm',
            'Đếm bộ ba',
            'Đếm cặp',
            'Đếm cặp giao nhau',
            'Đếm lần đi qua đoạn',
            'Đếm phân phối',
            'Đếm theo điều kiện',
            'Đếm theo đoạn',
            'Đồ thị',
            'Đọc ghi tệp',
            'Đồng dư',
            'Đồng hồ bảy đoạn',
            'Đường đi ngắn nhất',
            'Đường kính cây',
            'Euclid',
            'Hai con trỏ',
            'Hàm',
            'Hàm Euler',
            'Hàng đợi ưu tiên',
            'hậu tố',
            'Hệ đếm',
            'Hình học',
            'Hình học tọa độ',
            'Hoán vị',
            'Hợp đoạn',
            'HTML',
            'Hướng nghiệp',
            'Kiểm tra dãy con theo thứ tự',
            'Kiểm tra dữ liệu',
            'Kiểu dữ liệu',
            'Kỹ thuật tối ưu',
            'Lập lịch',
            'Lập trình nhập môn',
            'Lát hình',
            'LCA',
            'LIS',
            'Loang vùng',
            'Logic',
            'Lũy thừa nhanh',
            'Mã ký tự',
            'Manacher',
            'Mảng',
            'Mảng băm',
            'Mảng đánh dấu',
            'Mảng hai chiều',
            'Mảng hiệu',
            'Mạng máy tính',
            'Mảng một chiều',
            'Map',
            'Mặt nạ bit',
            'Meet in the middle',
            'Miller–Rabin',
            'Mô hình hóa',
            'Mô phỏng',
            'Mở rộng tâm',
            'Ngăn xếp',
            'Ngày giờ',
            'Nghịch đảo modulo',
            'Nguyên lý ngăn kéo',
            'Nhân đôi nhanh',
            'Nhân đôi tiền tố',
            'Nhập xuất',
            'Phân tích thừa số',
            'Phát hiện chu trình',
            'Phát triển phần mềm',
            'Phép toán',
            'Python',
            'Quay lui',
            'Quét các đoạn liên tiếp',
            'Quy hoạch động',
            'Quy hoạch động bit',
            'Quy hoạch động một chiều',
            'Quy hoạch động tập con',
            'Quy hoạch động trên lưới',
            'Rẽ nhánh',
            'Rẽ nhánh / Logic',
            'Sàng',
            'Sàng đoạn',
            'Sàng Eratosthenes',
            'Sắp xếp',
            'Sắp xếp /  Tìm kiếm',
            'Sắp xếp đếm',
            'Sắp xếp trộn',
            'Sinh cấu hình',
            'Số học',
            'So khớp xâu',
            'Số nguyên lớn',
            'Số nguyên tố',
            'Số nguyên tố / Sàng Eratosthenes',
            'Số thực',
            'SQL',
            'Suy luận',
            'Suy luận toán học',
            'Tần suất',
            'Tập hợp',
            'Tham lam',
            'Thiết kế web',
            'Thời gian',
            'Thống kê',
            'thuộc tính',
            'Tích có hướng',
            'Tiền tố',
            'Tìm kiếm',
            'Tìm kiếm nhị phân',
            'Tìm kiếm nhị phân trên đáp án',
            'Tìm kiếm tuần tự',
            'Tổ hợp',
            'Tối ưu',
            'Tối ưu tổ hợp',
            'Tổng hậu tố',
            'Trí tuệ nhân tạo',
            'Truy hồi',
            'Truy vấn đoạn',
            'Truy vấn độc lập',
            'Truy vết',
            'Tư duy toán',
            'Vét cạn',
            'Vòng lặp',
            'Xâu',
            'Xâu con chung dài nhất',
            'Xâu đối xứng',
            'Xây dựng cấu hình',
            'Xử lý chữ số',
        ];

    private const NEW_CODING = [
            '0-1 BFS',
            '2-SAT',
            'Aho–Corasick',
            'Bảng băm',
            'Bảng thưa',
            'Bao lồi',
            'Băm xâu',
            'Bellman–Ford',
            'Biến',
            'Binary lifting',
            'Cầu',
            'Cấu trúc tuần tự',
            'Cây khung nhỏ nhất',
            'Cây phân đoạn bền vững',
            'Cây tìm kiếm nhị phân',
            'Chỉnh hợp',
            'Chuyển kiểu',
            'Convex Hull Trick',
            'Danh sách liên kết',
            'Dãy con chung dài nhất',
            'Diện tích đa giác',
            'Dijkstra',
            'Duyệt cây',
            'Đếm nghịch thế',
            'Điểm trong đa giác',
            'Đỉnh khớp',
            'Định lý Fermat nhỏ',
            'Định lý số dư Trung Hoa',
            'Độ phức tạp',
            'Đồ thị hai phía',
            'Đường đi Euler',
            'Euclid mở rộng',
            'Fast doubling',
            'FFT',
            'Floyd–Warshall',
            'Ghép cặp hai phía',
            'Giao đoạn thẳng',
            'Góc',
            'Gỡ lỗi',
            'Hàm Z',
            'Hàng đợi',
            'Hàng đợi đơn điệu',
            'Hàng đợi hai đầu',
            'Hằng',
            'Heap',
            'HLD',
            'Khoảng cách',
            'Kiểm thử',
            'KMP',
            'Kruskal',
            'Lazy propagation',
            'Luồng chi phí nhỏ nhất',
            'Luồng cực đại',
            'Lũy thừa ma trận',
            'Mảng hậu tố',
            'Memoization',
            'Mo',
            'Nén tọa độ',
            'Ngăn xếp đơn điệu',
            'NTT',
            'Phân rã trọng tâm',
            'Phép toán bit',
            'Phương trình Diophantine',
            'Prim',
            'Quét dòng',
            'Quy hoạch động chữ số',
            'Quy hoạch động trên cây',
            'Quy hoạch động trên đoạn',
            'Sắp xếp chèn',
            'Sắp xếp chọn',
            'Sắp xếp nhanh',
            'Sắp xếp nổi bọt',
            'Sắp xếp topo',
            'Set',
            'Số Catalan',
            'Sơ đồ khối',
            'Thành phần liên thông',
            'Thành phần liên thông mạnh',
            'Thuật toán',
            'Thước cặp quay',
            'Tối ưu chia để trị',
            'Trie',
            'Trie nhị phân',
            'Xác suất',
        ];

    private const NEW_GENERAL = [
            'An toàn thông tin',
            'An toàn và sức khỏe số',
            'Ảnh động',
            'Bảng tính',
            'Bảo vệ dữ liệu cá nhân',
            'Biên tập âm thanh',
            'Biên tập đa phương tiện',
            'Biên tập video',
            'Biểu diễn thông tin',
            'Biểu đồ',
            'Chỉnh sửa ảnh',
            'Công nghệ số và xã hội',
            'Cơ sở dữ liệu quan hệ',
            'Đánh giá thông tin',
            'Điện toán đám mây',
            'Hệ điều hành',
            'Học máy',
            'Internet',
            'Internet vạn vật',
            'Khoa học dữ liệu',
            'Lập trình trực quan',
            'Lịch sử máy tính',
            'Lưu trữ trực tuyến',
            'Mạng xã hội',
            'Nén dữ liệu',
            'Pháp luật số',
            'Phần cứng',
            'Phần mềm',
            'Phần mềm mô phỏng',
            'Phần mềm ứng dụng',
            'Phân tích dữ liệu',
            'Quản lí dự án',
            'Robot giáo dục',
            'Soạn thảo văn bản',
            'Sơ đồ tư duy',
            'Sử dụng thiết bị số',
            'Tệp và thư mục',
            'Thiết bị và giao thức mạng',
            'Thiết kế cơ sở dữ liệu',
            'Thiết kế đồ họa',
            'Thông tin và dữ liệu',
            'Thư điện tử',
            'Tìm kiếm thông tin',
            'Tổ chức thông tin',
            'Trao đổi và hợp tác số',
            'Trình chiếu',
            'Truy vấn cơ sở dữ liệu',
            'Văn hóa số',
        ];

    public function run(): void
    {
        if (! Schema::hasTable('tags')) {
            $this->command?->error('Chưa có bảng tags — chạy php artisan migrate trước.');

            return;
        }
        $hasKind = Schema::hasColumn('tags', 'kind');
        if (! $hasKind) {
            $this->command?->warn('Bảng tags chưa có cột kind (chạy php artisan migrate) — chỉ thêm tag, chưa gán loại.');
        }

        $key = fn (string $s) => mb_strtolower(trim(class_exists(\Normalizer::class) ? (\Normalizer::normalize($s, \Normalizer::FORM_C) ?: $s) : $s));

        // Tên đang có trong DB → id (chuẩn hoá để so khớp).
        $existing = [];
        foreach (DB::table('tags')->get(['id', 'name']) as $t) {
            $existing[$key((string) $t->name)] = (int) $t->id;
        }

        // 1) Tag cũ → Lập trình
        $marked = 0;
        if ($hasKind) {
            $ids = [];
            foreach (self::EXISTING_CODING as $name) {
                if (isset($existing[$key($name)])) {
                    $ids[] = $existing[$key($name)];
                }
            }
            if ($ids !== []) {
                $marked = DB::table('tags')->whereIn('id', array_unique($ids))->update(['kind' => 'coding', 'updated_at' => Carbon::now()]);
            }
        }

        // 2) Thêm tag còn thiếu
        $added = 0;
        $skipped = 0;
        $now = Carbon::now();
        foreach (['coding' => self::NEW_CODING, 'general' => self::NEW_GENERAL] as $kind => $names) {
            foreach ($names as $name) {
                if (isset($existing[$key($name)])) {
                    $skipped++;

                    continue;
                }
                $row = ['name' => $name, 'created_at' => $now, 'updated_at' => $now];
                if ($hasKind) {
                    $row['kind'] = $kind;
                }
                // insertOrIgnore: nếu DB coi là trùng (vd. collation bỏ dấu) thì bỏ qua thay vì lỗi.
                $n = DB::table('tags')->insertOrIgnore($row);
                $n > 0 ? $added++ : $skipped++;
                $existing[$key($name)] = 0;
            }
        }

        $this->command?->info("Tag chuyên đề: gán Lập trình cho {$marked} tag cũ · thêm mới {$added} tag · bỏ qua {$skipped} tag đã có.");
    }
}
