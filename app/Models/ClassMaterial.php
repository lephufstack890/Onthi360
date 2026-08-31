<?php

namespace App\Models;

use App\Enums\ClassMaterialStatus;
use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassMaterial extends Model
{
    use Auditable;

    /** Đọc bởi App\Concerns\Auditable nếu có set lý do trước save()/delete() (10.4, 16 mục 4). */
    public static ?string $auditReason = null;

    protected $fillable = [
        'class_room_id', 'material_id', 'product_id', 'release_version', 'status',
        'added_by', 'added_at', 'removed_at',
    ];

    protected $casts = [
        'status' => ClassMaterialStatus::class,
        'added_at' => 'datetime',
        'removed_at' => 'datetime',
    ];

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function isUsable(): bool
    {
        return $this->status === ClassMaterialStatus::Active;
    }

    /**
     * SỬA 31/8 (khách yêu cầu, "gắn cả sản phẩm vào lớp"): material_id=null nghĩa là dòng
     * này gắn NGUYÊN 1 sản phẩm (sách/chuyên đề/bộ đề, xem migration
     * make_material_id_nullable_on_class_materials_table) thay vì 1 chương/mục lẻ như
     * trước 31/8 — dùng ở Blade/Service để biết cách hiển thị (product->title thay vì
     * material->title) mà không phải so sánh null rải rác nhiều nơi.
     */
    public function isWholeProduct(): bool
    {
        return $this->material_id === null;
    }
}
