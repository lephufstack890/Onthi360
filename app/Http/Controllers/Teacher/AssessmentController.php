<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\UploadedDocumentStatus;
use App\Http\Controllers\Controller;
use App\Models\UploadedDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AssessmentController extends Controller
{
    /**
     * teacher.assessments.create (TEA-04).
     * TODO: chưa có state "đề đang soạn" (draft assessment trong session/DB) — hiện luôn
     * bắt đầu với danh sách câu rỗng; nối App\Services\AssessmentBuilderService khi có,
     * để hỗ trợ "+ Thêm câu từ kho" thật và lưu nháp.
     */
    public function create(Request $request): View
    {
        return view('teacher.assessments.create', ['selected' => []]);
    }

    /** teacher.assessments.import (TEA-05 — tải) — trạng thái xử lý OCR thật (6.4). */
    public function import(Request $request): View
    {
        $user = Auth::user();

        $processingFiles = UploadedDocument::where('uploader_id', $user->id)
            ->whereIn('status', [
                UploadedDocumentStatus::Uploaded,
                UploadedDocumentStatus::Scanning,
                UploadedDocumentStatus::QueuedOcr,
                UploadedDocumentStatus::Processing,
                UploadedDocumentStatus::NeedsReview,
                UploadedDocumentStatus::Failed,
            ])
            ->latest()
            ->limit(20)
            ->get()
            ->map(function ($doc) {
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

        return view('teacher.assessments.import', ['processingFiles' => $processingFiles]);
    }

    /**
     * teacher.assessments.reviewDraft (TEA-05 — rà soát), truyền $document + $drafts thật
     * từ App\Models\DraftQuestion (6.4). Nhận ?document=<id>; nếu không có, lấy tài liệu
     * "cần rà soát" gần nhất của giáo viên.
     */
    public function reviewDraft(Request $request): View
    {
        $user = Auth::user();

        $document = null;
        if ($request->filled('document')) {
            $document = UploadedDocument::where('uploader_id', $user->id)->find($request->query('document'));
        }
        $document ??= UploadedDocument::where('uploader_id', $user->id)
            ->where('status', UploadedDocumentStatus::NeedsReview)
            ->latest()
            ->first();

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

        return view('teacher.assessments.review-draft', ['document' => $document, 'drafts' => $drafts]);
    }
}
