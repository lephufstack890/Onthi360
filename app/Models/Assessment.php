<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Concerns\Auditable;
use App\Enums\AssessmentType;
use App\Enums\ContentStatus;
use App\Enums\OwnerType;
use App\Enums\PublishAnswerRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assessment extends Model
{
    use HasFactory, Auditable, SoftDeletes;

    protected $fillable = [
        'title', 'type', 'total_points', 'duration_minutes', 'resubmission_policy',
        'publish_answer_rule', 'status', 'version', 'owner_type', 'owner_id', 'created_by',
    ];

    protected $casts = [
        'type' => AssessmentType::class,
        'status' => ContentStatus::class,
        'owner_type' => OwnerType::class,
        'publish_answer_rule' => PublishAnswerRule::class,
        'resubmission_policy' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(AssessmentItem::class)->orderBy('order');
    }

    /** Các material (chương/mục) trong sách/chuyên đề trỏ tới đề này qua type=assessment_ref. */
    public function materials(): HasMany
    {
        return $this->hasMany(Material::class, 'assessment_id');
    }

    public function questions()
    {
        return $this->items()->with('question');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
