<?php

namespace App\Models;

use App\Enums\ClassMaterialStatus;
use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassMaterial extends Model
{
    use Auditable;

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
}
