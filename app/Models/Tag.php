<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Chuyên đề/tag phẳng, dùng chung cho mọi câu hỏi (Giai đoạn 6 — xem migration create_tags_table). */
class Tag extends Model
{
    protected $fillable = ['name'];

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'question_tag');
    }
}
