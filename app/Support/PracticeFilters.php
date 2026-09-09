<?php

namespace App\Support;

use App\Repositories\Contracts\TagRepositoryInterface;

/**
 * SỬA 9/9 (7) (khách: "trang luyện tập ngoài public cũng vậy sửa giúp tôi nha").
 *
 * Dữ liệu cho BỘ LỌC luyện tập theo câu (dạng câu hỏi + chuyên đề). Trước đây 2 màn dựng bộ
 * lọc này bằng 2 đoạn mã riêng nên đã lệch nhau: màn học sinh thiếu dạng "Lập trình", màn công
 * khai thiếu "Câu nhiều phần", cả 2 đều mời chọn chuyên đề không có câu ở dạng đang chọn.
 * Gom về ĐÚNG MỘT chỗ để không lệch lại lần nữa:
 *   · App\Services\Student\PracticeByQuestionService::setupData()  (học sinh đã đăng nhập)
 *   · App\Services\Public\PracticeService::indexData()             (trang công khai)
 *
 * Số câu đếm bằng ĐÚNG điều kiện của QuestionRepository::idsForPractice() — xem
 * TagRepository::practiceCountsByType() / practiceTotalsByType().
 */
class PracticeFilters
{
    /**
     * Nhãn dành cho NGƯỜI HỌC — cố ý không dùng thẳng QuestionType::label() vì nhãn ở đó là
     * ngôn ngữ quản trị ("Lập trình (OJ)", "Nhiều phần (composite)").
     * Thứ tự khai báo ở đây cũng là thứ tự hiển thị trên màn chọn.
     */
    public const TYPE_META = [
        'mcq' => ['label' => 'Trắc nghiệm', 'icon' => '🔤', 'desc' => 'Chọn 1 đáp án đúng trong các lựa chọn'],
        'fill_blank' => ['label' => 'Điền đáp án', 'icon' => '✏️', 'desc' => 'Tự gõ câu trả lời của mình'],
        'coding' => ['label' => 'Lập trình', 'icon' => '💻', 'desc' => 'Viết code — chưa chấm tự động, chỉ ghi nhận bài làm'],
        'composite' => ['label' => 'Câu nhiều phần', 'icon' => '🧩', 'desc' => 'Một đề bài gồm nhiều ý nhỏ, chấm từng ý'],
    ];

    /**
     * @return array{practiceTypes: array<int, array<string, mixed>>, practiceTags: array<int, array<string, mixed>>, practiceTotal: int}
     */
    public static function options(TagRepositoryInterface $tags): array
    {
        $tagCounts = $tags->practiceCountsByType();
        $totals = $tags->practiceTotalsByType();

        $types = [];
        foreach (self::TYPE_META as $value => $info) {
            $types[] = $info + ['value' => $value, 'count' => (int) ($totals[$value] ?? 0)];
        }

        $list = [];
        foreach ($tagCounts as $id => $row) {
            $list[] = ['id' => $id, 'name' => $row['name'], 'counts' => $row['counts'], 'total' => $row['total']];
        }

        return [
            'practiceTypes' => $types,
            'practiceTags' => $list,
            'practiceTotal' => (int) array_sum($totals),
        ];
    }
}
