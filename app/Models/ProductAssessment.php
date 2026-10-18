<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SỬA 18/10 — 1 dòng "ghi nhớ" của bộ đề với 1 đề luyện tập (bảng product_assessments). Xem
 * App\Services\ExamBundle\ExamBundleService để hiểu cách tính danh sách đề của bộ.
 *
 * @property int $product_id
 * @property int $assessment_id
 * @property string $source      'manual' (admin ghim tay) | 'auto' (đề do bộ lọc gom — dòng chỉ để nhớ thứ tự / loại)
 * @property bool $excluded      admin đã bấm "Bỏ khỏi bộ đề"
 * @property int|null $sort_order thứ tự tay (nhỏ đứng trước); null = theo thứ tự mặc định
 */
class ProductAssessment extends Model
{
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_AUTO = 'auto';

    protected $table = 'product_assessments';

    protected $fillable = ['product_id', 'assessment_id', 'source', 'excluded', 'sort_order'];

    protected $casts = [
        'excluded' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }
}
