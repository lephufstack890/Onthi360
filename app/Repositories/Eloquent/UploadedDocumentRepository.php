<?php

namespace App\Repositories\Eloquent;

use App\Models\UploadedDocument;
use App\Repositories\Contracts\UploadedDocumentRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class UploadedDocumentRepository extends EloquentRepository implements UploadedDocumentRepositoryInterface
{
    protected string $modelClass = UploadedDocument::class;

    public function forUploaderInStatuses(int $uploaderId, array $statuses, int $limit = 20): Collection
    {
        return $this->query()
            ->where('uploader_id', $uploaderId)
            ->whereIn('status', $statuses)
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function findForUploader(int $uploaderId, int $documentId): ?UploadedDocument
    {
        return $this->query()->where('uploader_id', $uploaderId)->find($documentId);
    }

    public function latestNeedsReviewForUploader(int $uploaderId): ?UploadedDocument
    {
        return $this->query()
            ->where('uploader_id', $uploaderId)
            ->where('status', 'needs_review')
            ->latest()
            ->first();
    }
}
