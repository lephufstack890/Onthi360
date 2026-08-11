<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Enums\ContentStatus;
use App\Enums\OwnerType;
use App\Enums\ProductType;
use App\Enums\Visibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type', 'title', 'slug', 'description', 'cover_image_path', 'subject', 'grade', 'topic',
        'price', 'has_print_option', 'status', 'visibility', 'owner_type', 'owner_id',
        'created_by', 'duration_months',
    ];

    protected $casts = [
        'type' => ProductType::class,
        'status' => ContentStatus::class,
        'visibility' => Visibility::class,
        'owner_type' => OwnerType::class,
        'has_print_option' => 'boolean',
        'price' => 'integer',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class)->whereNull('parent_id')->orderBy('order');
    }

    public function accessRights(): HasMany
    {
        return $this->hasMany(AccessRight::class);
    }

    public function isPublished(): bool
    {
        return $this->status === ContentStatus::Published;
    }
}
