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

    /**
     * SỬA 27/10 (khách: "bài tập của tài liệu đổ ra sắp xếp theo thứ tự đàng hoàng" — lưới T1B086, T1B090, T1B085… lộn xộn)
     * — bài tập của TÀI LIỆU (sách / chuyên đề / bộ đề) xếp theo đúng thứ tự trong sách, KHÔNG gom theo dạng bài:
     *   1. theo chương / phần / đề (thứ tự chương ở trang quản lý tài liệu); bài chưa gắn chương xếp cuối;
     *   2. trong chương: theo mã bài kiểu số tự nhiên (T1B2 < T1B10, BAI9 < BAI10);
     *   3. hoà nhau thì theo id. Dùng chung cho cột Bài tập màn đọc và 2 nút Bài trước / Bài tiếp theo.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Question>  $questions  (cần các cột id, code, material_id)
     * @return \Illuminate\Support\Collection<int, \App\Models\Question>
     */
    public static function sortMaterialExercises(\Illuminate\Support\Collection $questions): \Illuminate\Support\Collection
    {
        $chapterIds = $questions->pluck('material_id')->filter()->unique()->values()->all();
        $orders = $chapterIds === [] ? [] : \App\Models\Material::query()->whereIn('id', $chapterIds)->pluck('order', 'id')->all();
        $key = fn ($q) => $q->material_id !== null && array_key_exists((int) $q->material_id, $orders)
            ? [0, (int) $orders[(int) $q->material_id], (int) $q->material_id]
            : [1, 0, 0];

        return $questions->sort(function ($a, $b) use ($key) {
            $c = $key($a) <=> $key($b);
            if ($c !== 0) {
                return $c;
            }
            $c = strnatcasecmp((string) $a->code, (string) $b->code);

            return $c !== 0 ? $c : ((int) $a->id <=> (int) $b->id);
        })->values();
    }
}
