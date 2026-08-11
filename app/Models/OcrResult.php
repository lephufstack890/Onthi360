<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OcrResult extends Model
{
    protected $fillable = ['uploaded_document_id', 'extracted_text_path', 'confidence_map', 'pages'];

    protected $casts = [
        'confidence_map' => 'array',
        'pages' => 'array',
    ];

    public function uploadedDocument(): BelongsTo
    {
        return $this->belongsTo(UploadedDocument::class);
    }
}
