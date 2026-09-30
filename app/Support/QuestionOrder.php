<?php

namespace App\Support;

use App\Support\PracticeFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * THỨ TỰ HIỂN THỊ CÂU HỎI — một nơi duy nhất.
 *
 * SỬA 30/9 (6) (khách: "khi click bài tiếp theo thì nó hiển thị theo thứ tự hiển thị theo
 * dạng bài") — trước đây mỗi màn xếp một kiểu: danh sách bài tập của sản phẩm xếp theo id,
 * kho câu hỏi và trang Luyện tập xếp theo display_order rồi mới nhất trước, còn nút "Bài
 * tiếp theo" thì chưa có. Ba thứ tự khác nhau thì bấm "Bài tiếp theo" chắc chắn nhảy lung tung.
 *
 * Quy ước chốt lại:
 *   1. gom theo DẠNG BÀI, thứ tự dạng lấy đúng theo PracticeFilters::TYPE_META
 *      (Trắc nghiệm → Điền đáp án → Lập trình → Câu nhiều phần) để khớp dải tab dạng câu;
 *   2. trong cùng một dạng: câu có "thứ tự ưu tiên hiển thị" cao đứng trước
 *      (display_order giảm dần — xem migration add_display_order_to_questions_table);
 *   3. hoà nhau thì theo id tăng dần, để thứ tự luôn CỐ ĐỊNH (không phụ thuộc thứ tự
 *      cơ sở dữ liệu trả về, nếu không bấm tới bấm lui sẽ ra hai kết quả khác nhau).
 */
class QuestionOrder
{
    /** @return list<string> */
    public static function typeOrder(): array
    {
        return array_keys(PracticeFilters::TYPE_META);
    }

    /**
     * Gắn thứ tự chuẩn vào một truy vấn câu hỏi.
     *
     * @param  Builder<\App\Models\Question>  $query
     * @return Builder<\App\Models\Question>
     */
    public static function apply(Builder $query): Builder
    {
        $types = self::typeOrder();

        // Dựng CASE thay vì FIELD() cho khỏi phụ thuộc MySQL. Giá trị ghép vào câu lệnh là
        // hằng do MÌNH định nghĩa ở PracticeFilters, không phải dữ liệu người dùng gửi lên,
        // nhưng vẫn truyền qua binding cho đúng lệ.
        $case = 'CASE';
        $bindings = [];
        foreach ($types as $i => $type) {
            $case .= ' WHEN type = ? THEN '.$i;
            $bindings[] = $type;
        }
        $case .= ' ELSE '.count($types).' END';

        return $query
            ->orderByRaw($case, $bindings)
            ->orderByDesc('display_order')
            ->orderBy('id');
    }
}
