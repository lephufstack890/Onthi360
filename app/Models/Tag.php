<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Chuyên đề/tag phẳng, dùng chung cho mọi câu hỏi (Giai đoạn 6 — xem migration create_tags_table). */
class Tag extends Model
{
    /** SỬA 12/11 — loại chuyên đề (cột tags.kind). */
    public const KIND_CODING = 'coding';

    public const KIND_GENERAL = 'general'; // SỬA 12/11 — "Phổ thông" (trước gọi nhầm là "Thủ công")

    public const KINDS = [
        self::KIND_CODING => 'Lập trình',
        self::KIND_GENERAL => 'Phổ thông',
    ];

    protected $fillable = ['name', 'kind'];

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? 'Chưa phân loại';
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'question_tag');
    }
}
