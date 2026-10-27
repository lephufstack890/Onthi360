<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SỬA 27/10 — một bài tập DÙNG KÉ: tài liệu product_id dùng bài question_id (bài gốc thuộc tài liệu khác), đặt ở chương
 * material_id. Không chép bài / tệp. Xem migration 001310 và App\Support\ProductExerciseLinks.
 */
class ProductExerciseLink extends Model
{
    protected $fillable = ['product_id', 'question_id', 'material_id', 'created_by'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
