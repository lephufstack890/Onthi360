<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\Teacher\AssessmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AssessmentController extends Controller
{
    public function __construct(private readonly AssessmentService $assessmentService) {}

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

        return view('teacher.assessments.import', $this->assessmentService->importStatusFor($user));
    }

    /**
     * teacher.assessments.reviewDraft (TEA-05 — rà soát), truyền $document + $drafts thật
     * từ App\Models\DraftQuestion (6.4). Nhận ?document=<id>; nếu không có, lấy tài liệu
     * "cần rà soát" gần nhất của giáo viên.
     */
    public function reviewDraft(Request $request): View
    {
        $user = Auth::user();
        $documentId = $request->filled('document') ? (int) $request->query('document') : null;

        return view('teacher.assessments.review-draft', $this->assessmentService->reviewDraftFor($user, $documentId));
    }
}
