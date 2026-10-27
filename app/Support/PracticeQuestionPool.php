<?php

namespace App\Support;

use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * KHO CÂU CHO MỤC "BÀI TẬP CHUYÊN ĐỀ" Ở TRANG LUYỆN TẬP CÔNG KHAI.
 *
 * SỬA 3/10 (khách: "các câu hỏi được add vô làm đề thi trong đề thi luyện tập thì không được
 * hiển thị bên bài tập chuyên đề; câu hỏi nào không được add vô đề thi luyện tập thì hiển thị
 * bên bài tập chuyên đề").
 *
 * Trang Luyện tập có HAI mục: "Đề thi luyện tập" (các Assessment type=practice) và "Bài tập
 * chuyên đề" (từng câu lẻ). Trước đây một câu vừa nằm trong đề vừa đứng riêng ở danh sách
 * chuyên đề — người học gặp lại đúng bài đó hai lần ở hai chỗ. Giờ câu nào đã được xếp vào
 * một đề luyện tập thì CHỈ còn xuất hiện trong đề đó.
 *
 * SỬA 27/10 — thêm: câu nằm trong đề Loại "Đề thi" (type = 'exam', mọi trạng thái, trừ đề ma trận khảo sát) cũng ẩn.
 *
 * PHẠM VI (đề luyện tập): chỉ tính đề LUYỆN TẬP (type = 'practice') và ĐÃ PHÁT HÀNH (status = 'published'),
 * chưa bị xoá mềm. Cố ý không tính đề nháp: đề nháp chưa ai xem được, nếu cũng ẩn câu đi thì
 * câu ấy biến mất khỏi cả hai chỗ — không còn lối nào vào. Đề thuộc loại khác (bài kiểm tra
 * giao cho lớp…) cũng không tính, vì chúng không nằm trên trang Luyện tập công khai.
 *
 * Đề dạng PDF (assessment_answer_keys / assessment_coding_items) không đụng tới bảng
 * questions nên không ảnh hưởng gì ở đây.
 *
 * LUẬT NÀY PHẢI DÙNG CHUNG Ở MỌI CHỖ đụng tới kho câu luyện tập công khai — danh sách, số đếm
 * trên chip chuyên đề/dạng bài, nút "Làm bài ngay", hai nút chuyển bài, và lượt luyện theo
 * chuyên đề. Lọc một chỗ thôi thì danh sách bày ra 30 câu mà chip vẫn ghi 42, hoặc bấm "Bài
 * tiếp theo" lại nhảy vào câu không có trong danh sách.
 */
final class PracticeQuestionPool
{
    /**
     * Loại khỏi truy vấn những câu đã được xếp vào một đề luyện tập đã phát hành.
     *
     * Dùng được cho cả Eloquent\Builder lẫn Query\Builder (TagRepository đếm bằng DB::table).
     *
     * @template TQuery of \Illuminate\Database\Eloquent\Builder|QueryBuilder
     *
     * @param  TQuery  $query
     * @param  string  $questionIdColumn  Cột id của câu hỏi trong truy vấn gọi (đã kèm tên bảng).
     * @return TQuery
     */
    public static function excludeExamQuestions($query, string $questionIdColumn = 'questions.id')
    {
        return $query->whereNotExists(function (QueryBuilder $sub) use ($questionIdColumn) {
            $sub->selectRaw('1')
                ->from('assessment_items')
                ->join('assessments', 'assessments.id', '=', 'assessment_items.assessment_id')
                ->whereColumn('assessment_items.question_id', $questionIdColumn)
                ->whereNull('assessments.deleted_at')
                ->where(function (QueryBuilder $w) {
                    // Đề luyện tập: chỉ đề đã phát hành (như cũ).
                    $w->where(fn (QueryBuilder $p) => $p->where('assessments.type', 'practice')->where('assessments.status', 'published'))
                        // SỬA 27/10 (khách: "câu hỏi thuộc đề thi cũng không được hiển thị ở bài tập chuyên đề") — đề Loại
                        // "Đề thi" (cả bản nháp: đề đang soạn cho cuộc thi cũng không được lộ câu ra ngoài). TRỪ đề do ma trận
                        // của kỳ khảo sát tự sinh (competition_exam_papers) — đề đó bốc câu từ chính kho chung, nếu tính thì
                        // mỗi lần chốt đề ma trận lại làm hụt bài tập chuyên đề.
                        ->orWhere(function (QueryBuilder $e) {
                            $e->where('assessments.type', 'exam');
                            if (self::hasPaperTable()) {
                                $e->whereNotExists(fn (QueryBuilder $pp) => $pp->selectRaw('1')->from('competition_exam_papers')
                                    ->whereColumn('competition_exam_papers.assessment_id', 'assessments.id'));
                            }
                        });
                });
        });
    }

    private static function hasPaperTable(): bool
    {
        static $has = null;

        return $has ??= \Illuminate\Support\Facades\Schema::hasTable('competition_exam_papers');
    }
}
