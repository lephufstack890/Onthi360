<?php

namespace App\Support;

use App\Models\Question;
use Illuminate\Support\Facades\DB;

/**
 * ĐỔI CHỖ MỘT CÂU HỎI TRONG DANH SÁCH — dùng chung cho Admin và Giáo viên.
 *
 * Thứ tự hiển thị chung (QuestionOrder::apply, cũng là thứ tự trang Luyện tập public): gom theo dạng bài →
 * display_order GIẢM dần → id TĂNG dần. Hoà số thì id nhỏ đứng trước, nên muốn chắc chắn câu này đứng trước câu
 * kia ở mọi nơi thì phải cho nó số LỚN HƠN HẲN, không để hoà:
 *   - $question nhận số = $before + 1;
 *   - nếu phía trên $before (cùng dạng bài) đã có câu nào số ≤ $before + 1 thì đẩy các câu đó lên 2 bậc để chúng
 *     vẫn nằm trên $question.
 * Thứ tự chỉ có nghĩa TRONG một dạng bài — 2 câu khác dạng thì từ chối.
 *
 * "Đưa xuống dưới" = đảo vai: đưa câu nằm NGAY DƯỚI lên trước câu này (moveBefore($dưới, $câuNày)).
 *
 * $ownerId (giáo viên): chỉ đụng tới các câu CỦA CHÍNH giáo viên đó, không bao giờ sửa số của câu người khác.
 * Admin truyền null = toàn kho.
 */
class QuestionReorder
{
    public const LIMIT = 65535;

    /** @return array{ok:bool, message?:string} */
    public static function moveBefore(Question $question, Question $before, ?int $ownerId = null): array
    {
        if ($question->id === $before->id) {
            return ['ok' => false, 'message' => 'Không thể đặt một câu trước chính nó.'];
        }
        if ($question->type !== $before->type) {
            return ['ok' => false, 'message' => 'Hai câu khác dạng bài — thứ tự chỉ đổi được trong cùng một dạng bài.'];
        }

        $beforeOrder = (int) $before->display_order;
        if ((int) $question->display_order > $beforeOrder) {
            return ['ok' => true]; // đã đứng trước hẳn rồi (số lớn hơn) — không cần làm gì
        }

        return DB::transaction(function () use ($question, $before, $beforeOrder, $ownerId): array {
            $typeValue = $before->type->value;

            // Phạm vi các câu được phép bị đổi số (giáo viên: chỉ câu của mình).
            $scope = fn () => Question::query()->where('id', '!=', $question->id)
                ->where('type', $typeValue)
                ->when($ownerId !== null, fn ($q) => $q->where('owner_id', $ownerId)->where('owner_type', 'teacher'));

            // Nhóm "đứng trước $before" theo thứ tự chung (cùng dạng bài, không tính chính $question).
            $above = fn () => $scope()->where(function ($q) use ($before, $beforeOrder) {
                $q->where('display_order', '>', $beforeOrder)
                    ->orWhere(function ($q) use ($before, $beforeOrder) {
                        $q->where('display_order', $beforeOrder)->where('id', '<', $before->id);
                    });
            });

            $newValue = $beforeOrder + 1;
            $minAbove = $above()->min('display_order');
            $needBump = $minAbove !== null && (int) $minAbove < $beforeOrder + 2;

            if ($needBump) {
                $maxAbove = (int) $above()->max('display_order');
                if (max($maxAbove + 2, $beforeOrder + 2) > self::LIMIT) {
                    return ['ok' => false, 'message' => 'Không đẩy thêm được: thứ tự đã chạm mốc '.self::LIMIT.'. Hãy đặt lại số nhỏ hơn cho các câu đứng đầu.'];
                }
                // Thứ tự quan trọng: tăng nhóm "số lớn hơn" TRƯỚC, rồi mới nâng nhóm hoà số.
                $scope()->where('display_order', '>', $beforeOrder)->increment('display_order', 2);
                $above()->where('display_order', $beforeOrder)->update(['display_order' => $beforeOrder + 2]);
            } elseif ($newValue > self::LIMIT) {
                return ['ok' => false, 'message' => 'Không đẩy thêm được: thứ tự đã chạm mốc '.self::LIMIT.'.'];
            }

            self::save($question, $newValue);

            return ['ok' => true];
        });
    }

    private static function save(Question $question, int $new): void
    {
        if ((int) $question->display_order === $new) {
            return;
        }
        Question::$auditReason = 'Đổi thứ tự hiển thị nhanh ở danh sách';
        try {
            $question->update(['display_order' => $new]);
        } finally {
            Question::$auditReason = null;
        }
    }
}
