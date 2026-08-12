<?php

namespace App\Services\Teacher;

use App\Enums\UploadedDocumentStatus;
use App\Models\UploadedDocument;
use App\Models\User;
use App\Repositories\Contracts\UploadedDocumentRepositoryInterface;

/** Tổng hợp dữ liệu cho teacher.assessments.import/reviewDraft (TEA-04/05, 6.4). */
class AssessmentService
{
    public function __construct(
        private readonly UploadedDocumentRepositoryInterface $uploadedDocuments,
    ) {}

    /** teacher.assessments.import — trạng thái xử lý OCR thật (6.4). */
    public function importStatusFor(User $user): array
    {
        $statuses = [
            UploadedDocumentStatus::Uploaded,
            UploadedDocumentStatus::Scanning,
            UploadedDocumentStatus::QueuedOcr,
            UploadedDocumentStatus::Processing,
            UploadedDocumentStatus::NeedsReview,
            UploadedDocumentStatus::Failed,
        ];

        $processingFiles = $this->uploadedDocuments->forUploaderInStatuses($user->id, $statuses, 20)
            ->map(function (UploadedDocument $doc) {
                [$label, $tone, $progress] = match ($doc->status) {
                    UploadedDocumentStatus::Uploaded => ['Đã tải lên — đang chờ quét', 'info', 10],
                    UploadedDocumentStatus::Scanning => ['Đang quét virus...', 'info', 25],
                    UploadedDocumentStatus::QueuedOcr => ['Trong hàng chờ OCR...', 'info', 40],
                    UploadedDocumentStatus::Processing => ['Đang OCR...', 'info', 70],
                    UploadedDocumentStatus::NeedsReview => ['Cần rà soát', 'warning', 100],
                    UploadedDocumentStatus::Failed => ['Xử lý lỗi', 'danger', 100],
                    default => ['Không rõ', 'neutral', 0],
                };

                return [
                    'id' => $doc->id,
                    'name' => $doc->original_filename,
                    'status' => $label,
                    'tone' => $tone,
                    'progress' => $progress,
                ];
            })->all();

        return ['processingFiles' => $processingFiles];
    }

    /**
     * teacher.assessments.reviewDraft — rà soát; nhận ?document=<id>, nếu không có thì lấy
     * tài liệu "cần rà soát" gần nhất của giáo viên (6.4).
     */
    public function reviewDraftFor(User $user, ?int $documentId): array
    {
        $document = null;
        if ($documentId !== null) {
            $document = $this->uploadedDocuments->findForUploader($user->id, $documentId);
        }
        $document ??= $this->uploadedDocuments->latestNeedsReviewForUploader($user->id);

        $drafts = [];
        if ($document) {
            $drafts = $document->draftQuestions->values()->map(function ($d, $idx) {
                $confidenceLabel = match ($d->confidence) {
                    'high' => 'Cao',
                    'medium' => 'Trung bình',
                    'low' => 'Thấp — có công thức/ảnh',
                    default => (string) $d->confidence,
                };
                $tone = match ($d->confidence) {
                    'high' => 'success',
                    'medium' => 'warning',
                    'low' => 'danger',
                    default => 'neutral',
                };

                return [
                    'no' => $idx + 1,
                    'type' => $d->type_guess?->value ?? '',
                    'confidence' => $confidenceLabel,
                    'tone' => $tone,
                    'flagged' => $d->needsManualReview(),
                    'title' => $d->raw_text ? mb_substr($d->raw_text, 0, 160) : '(chưa có nội dung)',
                ];
            })->all();
        }

        return ['document' => $document, 'drafts' => $drafts];
    }
}
