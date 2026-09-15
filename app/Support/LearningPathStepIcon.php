<?php

namespace App\Support;

/**
 * Icon của từng bậc trên áp phích thang bậc.
 *
 * ── Vì sao KHÔNG dùng icon của môn Tin học ──
 * Lộ trình đầu tiên là Python nên rất dễ chọn bừa các icon lập trình (dấu ngoặc nhọn, sơ đồ
 * thuật toán...). Nhưng khách đã nói rõ lộ trình sau này còn dùng cho Toán, Văn, Tiếng Anh và
 * các môn khác — lúc đó một thang bậc Ngữ văn mà đầy icon code thì nhìn rất vô duyên, và sẽ
 * phải đi sửa lại giao diện cho từng môn.
 *
 * Vì vậy bộ icon ở đây tả CHẶNG ĐƯỜNG HỌC chứ không tả môn học:
 *   bắt đầu → học nền → luyện có mục tiêu → tiến bộ → thành thạo → về đích.
 * Bộ này đúng với mọi môn, nên thêm môn mới không phải sửa gì ở đây.
 *
 * ── Vì sao không lưu icon vào cơ sở dữ liệu ──
 * Icon đến từ VỊ TRÍ của bậc, giống hệt màu (xem LearningPathPalette). Thêm hay bớt một bậc
 * là cả dãy tự chạy lại; lưu vào CSDL thì phải nhớ đi sửa từng bản ghi mỗi lần đổi thứ tự.
 */
final class LearningPathStepIcon
{
    /**
     * Các chặng, theo đúng thứ tự. Quay vòng khi lộ trình có nhiều bậc hơn số chặng.
     *
     * @var list<string>
     */
    private const STAGES = [
        'lightbulb',    // bắt đầu — làm quen
        'book-open',    // học nền
        'target',       // luyện có mục tiêu
        'trending-up',  // tiến bộ
        'award',        // thành thạo
    ];

    /** Bậc cuối luôn là cúp: đó là đích đến, không phải một chặng như các bậc khác. */
    private const FINAL_STAGE = 'trophy';

    /**
     * @param  int  $index  vị trí bậc, đếm từ 0
     * @param  int  $total  tổng số bậc của lộ trình
     */
    public static function forPosition(int $index, int $total): string
    {
        // Lộ trình chỉ có một bậc thì bậc đó vừa là điểm bắt đầu vừa là đích — dùng cúp sẽ
        // hứa hẹn quá tay, nên giữ icon khởi đầu.
        if ($total > 1 && $index === $total - 1) {
            return self::FINAL_STAGE;
        }

        return self::STAGES[$index % count(self::STAGES)];
    }
}
