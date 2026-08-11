<?php

namespace App\Models;

use App\Enums\UploadedDocumentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UploadedDocument extends Model
{
    protected $fillable = [
        'uploader_id', 'original_filename', 'storage_path', 'mime_type', 'size_bytes',
        'status', 'virus_scan_status', 'error_log',
    ];

    protected $casts = [
        'status' => UploadedDocumentStatus::class,
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function ocrResult(): HasOne
    {
        return $this->hasOne(OcrResult::class);
    }

    public function draftQuestions(): HasMany
    {
        return $this->hasMany(DraftQuestion::class)->orderBy('order');
    }
}
