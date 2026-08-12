<?php

namespace App\Repositories\Contracts;

use App\Models\UploadedDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface UploadedDocumentRepositoryInterface extends BaseRepositoryInterface
{

    public function forUploaderInStatuses(int $uploaderId, array $statuses, int $limit = 20): Collection;

    public function findForUploader(int $uploaderId, int $documentId): ?UploadedDocument;

    public function latestNeedsReviewForUploader(int $uploaderId): ?UploadedDocument;
}
