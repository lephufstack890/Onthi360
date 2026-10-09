<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SỬA 9/10 — một lượt giao TÀI LIỆU (sản phẩm) cho một học sinh ở trang /tai-lieu.
 * Xem migration create_material_assignments_table.
 */
class MaterialAssignment extends Model
{
    protected $fillable = [
        'assigned_by', 'student_id', 'product_id', 'deadline_at', 'access_days',
        'access_expires_at', 'note', 'opened_at', 'access_right_id',
    ];

    protected $casts = [
        'deadline_at' => 'datetime',
        'access_expires_at' => 'datetime',
        'opened_at' => 'datetime',
        'access_days' => 'integer',
        'product_id' => 'integer',
    ];

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
