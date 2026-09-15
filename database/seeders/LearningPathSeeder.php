<?php

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Enums\PathLanguage;
use App\Models\Course;
use App\Models\LearningPath;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Năm lộ trình THẬT của khách (A11).
 *
 * Nguồn: bộ ảnh lộ trình khách gửi — education-main/docs/roadmaps + ảnh "SASH PYTHON THI ĐẤU
 * THCS". Đây là nội dung có thật của khách, không phải dữ liệu bịa như seeder câu chuyện
 * đồng hành, nên tạo thẳng ở trạng thái đủ dùng.
 *
 * NHƯNG vẫn để BẢN NHÁP, không tự đăng: các bậc chỉ mới có khung (tên, mã bậc, số buổi),
 * chưa có lớp nào mở và chưa có giá. Khách vào xem, bổ sung rồi tự bấm đăng.
 *
 * Chạy lại nhiều lần không nhân đôi dữ liệu: khớp theo slug.
 *
 * ── Vì sao chỉ lộ trình đầu có đủ 6 bậc ──
 * Ảnh "SASH Python Thi đấu THCS" là bộ duy nhất khách ghi rõ từng bậc kèm số buổi. Bốn lộ
 * trình còn lại trong bộ SVG cũ chỉ có tên 5 chặng, chưa có số buổi — tạo khung lộ trình
 * trước, khách tự xếp bậc sau ở màn "Xếp bậc".
 */
class LearningPathSeeder extends Seeder
{
    public function run(): void
    {
        $paths = [
            [
                'title' => 'Python Thi đấu THCS',
                'brand' => 'SASH',
                'eyebrow' => 'LỘ TRÌNH TIẾP CẬN LẬP TRÌNH',
                'subtitle' => 'Từ tư duy thuật toán đến tự tin tạo sản phẩm nhỏ bằng Python',
                'grade_from' => 6,
                'grade_to' => 8,
                'language' => PathLanguage::Python->value,
                'goal_label' => 'HSG lớp 9 · Thi tuyển sinh 10 Chuyên Tin',
                'sessions_per_week' => 2,
                'hours_per_session' => 2,
                'outcomes' => [
                    'Hiểu cách máy tính giải quyết vấn đề',
                    'Viết được chương trình Python nhỏ',
                    'Hình thành thói quen tự học và thử nghiệm',
                ],
                // 6 bậc đọc thẳng từ ảnh: mã bậc · nhãn phụ · câu kết quả · số buổi.
                'steps' => [
                    ['PRE-CODE', 'STARTER', 'Làm quen code', 10],
                    ['FOUNDATION A', 'CORE', 'Viết code đúng', 14],
                    ['FOUNDATION B', 'PROBLEM SOLVING', 'Biết giải bài', 18],
                    ['INTERMEDIATE', 'ALGORITHMIC', 'Chọn thuật toán', 22],
                    ['INTENSIVE', 'COMPETITIVE', 'Thi & tối ưu', 26],
                    ['ADVANCED', 'GOAL READY', 'Chinh phục mục tiêu', 30],
                ],
            ],
            [
                'title' => 'C++ Nền tảng Thi đấu THCS',
                'brand' => 'SASH',
                'eyebrow' => 'LỘ TRÌNH NỀN TẢNG THI ĐẤU',
                'subtitle' => 'Xây chắc C++ và thuật toán để chuẩn bị HSG lớp 9 hoặc Chuyên Tin',
                'grade_from' => 6,
                'grade_to' => 8,
                'language' => PathLanguage::Cpp->value,
                'goal_label' => 'HSG lớp 9 THCS · Chuyên Tin',
                'sessions_per_week' => 2,
                'hours_per_session' => 2,
                'outcomes' => [
                    'Nắm chắc C++ dùng trong thi thuật toán',
                    'Giải được bài cơ bản đến khá',
                    'Có nền tảng 1–2 năm để tăng tốc ở lớp 9',
                ],
                'steps' => [],
            ],
            [
                'title' => 'C++ Nước rút lớp 9',
                'brand' => 'SASH',
                'eyebrow' => 'LỘ TRÌNH NƯỚC RÚT LỚP 9',
                'subtitle' => 'Tập trung theo dạng đề để chinh phục HSG THCS hoặc kỳ thi vào Chuyên Tin',
                'grade_from' => 9,
                'grade_to' => 9,
                'language' => PathLanguage::Cpp->value,
                'goal_label' => 'HSG lớp 9 THCS · Chuyên Tin',
                'sessions_per_week' => 2,
                'hours_per_session' => 2,
                'outcomes' => [
                    'Biết rõ điểm mạnh, điểm yếu trước kỳ thi',
                    'Có chiến thuật phân bổ thời gian',
                    'Sẵn sàng theo nhánh HSG hoặc Chuyên Tin',
                ],
                'steps' => [],
            ],
            [
                'title' => 'Python đa mục tiêu lớp 10',
                'brand' => 'SASH',
                'eyebrow' => 'LỘ TRÌNH PYTHON ĐA MỤC TIÊU',
                'subtitle' => 'Một nền tảng Python dùng cho nhiều hướng đi khác nhau',
                'grade_from' => 10,
                'grade_to' => 10,
                'language' => PathLanguage::Python->value,
                'goal_label' => 'Lập trình · HSG tỉnh · CSP / hồ sơ CS',
                'sessions_per_week' => 2,
                'hours_per_session' => 2,
                'outcomes' => [],
                'steps' => [],
            ],
            [
                'title' => 'C++ THPT',
                'brand' => 'SASH',
                'eyebrow' => 'LỘ TRÌNH C++ THPT',
                'subtitle' => 'C++ và thuật toán cho học sinh THPT',
                'grade_from' => 10,
                'grade_to' => 12,
                'language' => PathLanguage::Cpp->value,
                'goal_label' => 'Lập trình · HSG tỉnh · hồ sơ CS / AP',
                'sessions_per_week' => 2,
                'hours_per_session' => 2,
                'outcomes' => [],
                'steps' => [],
            ],
        ];

        foreach ($paths as $index => $definition) {
            $steps = $definition['steps'];
            unset($definition['steps']);

            $slug = Str::slug($definition['title']);

            $path = LearningPath::updateOrCreate(
                ['slug' => $slug],
                $definition + [
                    'slug' => $slug,
                    'status' => ContentStatus::Draft->value,
                    'sort_order' => $index + 1,
                ],
            );

            foreach ($steps as $position => [$levelCode, $levelSubtitle, $outcome, $sessionCount]) {
                $course = $this->courseForStep($path, $levelCode, $levelSubtitle, $outcome, $sessionCount);

                // syncWithoutDetaching để chạy lại seeder không tạo bậc trùng.
                $path->courses()->syncWithoutDetaching([
                    $course->id => ['sort_order' => $position + 1],
                ]);
            }
        }

        $this->command?->info('Đã tạo '.count($paths).' lộ trình (bản nháp) kèm các bậc có sẵn số buổi.');
        $this->command?->warn('Vào Quản trị → Lộ trình để bổ sung bậc, mở lớp rồi mới bấm Đăng.');
    }

    /**
     * Tạo (hoặc lấy lại) khoá học đóng vai một bậc.
     *
     * Đặt tên khoá theo "Tên lộ trình · Mã bậc" để khách nhìn danh sách khoá học là biết ngay
     * khoá nào thuộc lộ trình nào, và để hai lộ trình khác nhau không đụng slug của nhau.
     */
    private function courseForStep(LearningPath $path, string $levelCode, string $levelSubtitle, string $outcome, int $sessionCount): Course
    {
        $title = $path->title.' · '.$levelCode;

        return Course::updateOrCreate(
            ['slug' => Str::slug($title)],
            [
                'title' => $title,
                'description' => $outcome,
                'subject' => 'Tin học',
                'grade' => $path->gradeLabel(),
                'status' => ContentStatus::Draft->value,
                'level_code' => $levelCode,
                'level_subtitle' => $levelSubtitle,
                'outcome' => $outcome,
                'session_count' => $sessionCount,
            ],
        );
    }
}
