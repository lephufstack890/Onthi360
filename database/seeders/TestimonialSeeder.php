<?php

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;

/**
 * Câu chuyện đồng hành mẫu cho khối [HOME-10] trang chủ.
 *
 * ĐỌC KỸ TRƯỚC KHI ĐĂNG:
 * Toàn bộ nội dung dưới đây là VIẾT MẪU để anh/chị thấy trước bố cục và độ dài phù hợp — KHÔNG
 * phải lời của người thật. Vì vậy seeder tạo chúng ở trạng thái BẢN NHÁP (chưa hiện ở trang
 * chủ) và đánh dấu is_sample = true.
 *
 * Cách dùng đúng: vào Quản trị → Câu chuyện đồng hành, sửa từng câu thành câu chuyện có thật
 * (đã xin phép người kể), rồi bấm "Hiển thị". Đăng lời chứng thực bịa lên trang chủ vừa làm
 * mất niềm tin của phụ huynh, vừa rủi ro nếu gắn kèm dữ liệu đánh giá cho Google.
 *
 * ── Vì sao viết theo kiểu này (tối ưu tìm kiếm) ──
 * Mỗi câu chuyện đều nêu: (1) xuất phát điểm, (2) việc cụ thể đã làm ở Ôn Thi 360, (3) kết quả
 * đo được. Cách viết đó vừa thuyết phục người đọc, vừa chứa tự nhiên các cụm từ người ta thật
 * sự gõ khi tìm kiếm — "ôn thi HSG Tin học", "luyện thi chuyên Tin lớp 10", "học lập trình
 * Pascal C++ lớp 9", "chấm bài tự động online judge" — mà không nhồi từ khoá lộ liễu.
 * Mỗi câu 220–320 ký tự: đủ dài để Google hiểu ngữ cảnh, đủ ngắn để thẻ không bị cắt chữ.
 */
class TestimonialSeeder extends Seeder
{
    public function run(): void
    {
        $samples = [
            [
                'quote' => 'Con trai tôi bắt đầu từ con số 0 về lập trình. Sau hai học kỳ theo lộ trình Tin học lớp 9 ở Ôn Thi 360, cháu tự viết được chương trình C++ và đỗ vào lớp chuyên Tin của trường. Tôi thích nhất là mỗi bài nộp đều được chấm ngay, biết sai ở đâu để sửa luôn.',
                'author_name' => 'Phụ huynh em Minh Anh',
                'author_role' => 'Phụ huynh học sinh lớp 9',
                'author_org' => 'THCS Trưng Vương, Hà Nội',
                'rating' => 5,
            ],
            [
                'quote' => 'Em ôn thi học sinh giỏi Tin học cấp tỉnh trong 4 tháng với bộ chuyên đề Cấu trúc dữ liệu và Quy hoạch động. Ngân hàng bài tập phân theo mức độ nên em biết mình đang ở đâu, không còn cảm giác học tràn lan. Kết quả em đạt giải Nhì cấp tỉnh.',
                'author_name' => 'Trần Gia Huy',
                'author_role' => 'Học sinh lớp 11',
                'author_org' => 'THPT Chuyên Thái Bình',
                'rating' => 5,
            ],
            [
                'quote' => 'Tôi dùng Ôn Thi 360 để giao bài và theo dõi tiến độ cho hai lớp bồi dưỡng Tin học. Việc chấm tự động giúp tôi tiết kiệm phần lớn thời gian chấm tay, còn bảng kết quả theo từng chuyên đề cho thấy rõ lớp đang yếu ở phần nào để dạy lại đúng chỗ đó.',
                'author_name' => 'Thầy Nguyễn Tiến Thành',
                'author_role' => 'Giáo viên Tin học',
                'author_org' => 'THPT Chuyên Thái Bình',
                'rating' => 5,
            ],
            [
                'quote' => 'Trước đây em học thuật toán bằng cách chép lời giải nên thi là quên. Ở đây mỗi bài đều có bộ test chấm ngay và gợi ý hướng làm, em buộc phải tự nghĩ. Sau ba tháng luyện tập theo câu, tốc độ giải bài của em nhanh hơn hẳn khi vào phòng thi thật.',
                'author_name' => 'Lê Hoàng Nam',
                'author_role' => 'Học sinh lớp 12',
                'author_org' => 'THPT Chuyên Vĩnh Phúc',
                'rating' => 5,
            ],
            [
                'quote' => 'Tôi ở xa nên trước đây rất khó tìm lớp bồi dưỡng Tin học cho con. Học trực tuyến ở Ôn Thi 360, con vẫn có lộ trình rõ ràng và thầy cô phản hồi bài đều đặn. Mỗi tuần tôi vào xem tiến độ một lần là nắm được con làm được bao nhiêu bài, còn thiếu phần nào.',
                'author_name' => 'Phụ huynh em Khánh Linh',
                'author_role' => 'Phụ huynh học sinh lớp 10',
                'author_org' => 'Nghệ An',
                'rating' => 5,
            ],
            [
                'quote' => 'Em ôn thi vào lớp 10 chuyên Tin và lo nhất phần thuật toán cơ bản. Bộ đề thi thử bám sát cấu trúc đề tỉnh, làm xong có ngay thống kê câu nào sai nhiều. Nhờ vậy em biết dành thời gian cho phần sắp xếp và tìm kiếm thay vì học dàn đều.',
                'author_name' => 'Phạm Khánh Chi',
                'author_role' => 'Học sinh lớp 9',
                'author_org' => 'THCS Cầu Giấy, Hà Nội',
                'rating' => 5,
            ],
        ];

        foreach ($samples as $index => $sample) {
            Testimonial::updateOrCreate(
                // Khớp theo tên + vai trò để chạy lại seeder không tạo trùng.
                [
                    'author_name' => $sample['author_name'],
                    'author_role' => $sample['author_role'],
                ],
                $sample + [
                    // BẢN NHÁP, không phải Published — xem ghi chú ở đầu lớp.
                    'status' => ContentStatus::Draft->value,
                    'published_at' => null,
                    'verified_at' => null,
                    'is_sample' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }

        $this->command?->info('Đã tạo '.count($samples).' câu chuyện đồng hành MẪU ở dạng bản nháp.');
        $this->command?->warn('Hãy vào Quản trị → Câu chuyện đồng hành, sửa thành câu chuyện THẬT rồi mới bấm Hiển thị.');
    }
}
