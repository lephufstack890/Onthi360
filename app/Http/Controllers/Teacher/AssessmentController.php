<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\Teacher\AssessmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AssessmentController extends Controller
{
    public function __construct(private readonly AssessmentService $assessmentService) {}

    /** teacher.assessments.index (TEA-04) — đề do chính giáo viên tạo (6.3, 8.4). */
    public function index(Request $request): View
    {
        return view('teacher.assessments.index', $this->assessmentService->listForTeacher(Auth::user()));
    }

    /**
     * teacher.assessments.create (TEA-04) — chọn câu từ kho riêng của giáo viên, trộn được
     * nhiều kiểu câu trong cùng một đề (6.3).
     */
    public function create(Request $request): View
    {
        return view('teacher.assessments.create', $this->assessmentService->createFormData(Auth::user()));
    }

    /**
     * teacher.assessments.store — action=draft chỉ lưu đề; action=assign còn phát hành +
     * giao ngay cho 1 lớp (8.4: không hỗ trợ ngoại lệ từng học sinh).
     */
    public function store(Request $request): RedirectResponse
    {
        $action = $request->input('action', 'draft');
        $data = $request->validate($this->storeRules($action));

        $assessment = $this->assessmentService->store(Auth::user(), $data);

        if ($action === 'assign') {
            try {
                $data['opens_at'] = $this->combineOptionalDateTime($data, 'opens');
                $data['closes_at'] = $this->combineOptionalDateTime($data, 'closes');

                $assignment = $this->assessmentService->assignToClass(Auth::user(), $assessment, $data);
            } catch (ValidationException $e) {
                return redirect()->route('teacher.assessments.index')
                    ->withErrors($e->errors())
                    ->with('status', 'assessment-created-not-assigned');
            }

            return redirect()->route('teacher.assessments.index')->with('status', 'assessment-assigned');
        }

        return redirect()->route('teacher.assessments.index')->with('status', 'assessment-created');
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

    /** teacher.assessments.publish — phát hành riêng, không giao lớp ngay (6.2). */
    public function publish(Request $request, int $assessment): RedirectResponse
    {
        $assessmentModel = $this->assessmentService->findOwned(Auth::user(), $assessment);

        try {
            $this->assessmentService->publish($assessmentModel);
        } catch (ValidationException $e) {
            return redirect()->route('teacher.assessments.index')->withErrors($e->errors());
        }

        return redirect()->route('teacher.assessments.index')->with('status', 'assessment-published');
    }

    /** teacher.assessments.assign — "Giao đề" cho một đề đã có sẵn (8.4). */
    public function assign(Request $request, int $assessment): RedirectResponse
    {
        $assessmentModel = $this->assessmentService->findOwned(Auth::user(), $assessment);
        $data = $request->validate($this->assignRules());

        try {
            $data['opens_at'] = $this->combineOptionalDateTime($data, 'opens');
            $data['closes_at'] = $this->combineOptionalDateTime($data, 'closes');

            $assignment = $this->assessmentService->assignToClass(Auth::user(), $assessmentModel, $data);
        } catch (ValidationException $e) {
            return redirect()->route('teacher.assessments.index')->withErrors($e->errors());
        }

        return redirect()->route('teacher.assessments.index')->with('status', 'assessment-assigned');
    }

    private function storeRules(string $action): array
    {
        $common = [
            'title' => ['required', 'string', 'max:255'],
            'question_ids' => ['required', 'array', 'min:1'],
            'question_ids.*' => ['integer'],
            'points_override' => ['nullable', 'array'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'max_resubmissions' => ['nullable', 'integer', 'min:1', 'max:10'],
            'publish_answer_rule' => ['nullable', 'in:never,after_deadline,immediately'],
            'action' => ['required', 'in:draft,assign'],
        ];

        return $action === 'assign' ? array_merge($common, $this->assignRules()) : $common;
    }

    private function assignRules(): array
    {
        return [
            'class_room_id' => ['required', 'integer', 'exists:class_rooms,id'],
            'opens_day' => ['nullable', 'numeric', 'digits_between:1,2', 'between:1,31'],
            'opens_month' => ['nullable', 'numeric', 'digits_between:1,2', 'between:1,12'],
            'opens_year' => ['nullable', 'numeric', 'digits_between:4,4', 'between:2000,2100'],
            'opens_hour' => ['nullable', 'numeric', 'digits_between:1,2', 'between:0,23'],
            'opens_minute' => ['nullable', 'numeric', 'digits_between:1,2', 'between:0,59'],
            'closes_day' => ['nullable', 'numeric', 'digits_between:1,2', 'between:1,31'],
            'closes_month' => ['nullable', 'numeric', 'digits_between:1,2', 'between:1,12'],
            'closes_year' => ['nullable', 'numeric', 'digits_between:4,4', 'between:2000,2100'],
            'closes_hour' => ['nullable', 'numeric', 'digits_between:1,2', 'between:0,23'],
            'closes_minute' => ['nullable', 'numeric', 'digits_between:1,2', 'between:0,59'],
            'due_at' => ['nullable', 'date'],
            'instructions' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Ghép Ngày/Tháng/Năm (Ngày để trống = coi là "chưa đặt mốc", trả null) + Giờ/Phút
     * (mặc định 00:00 nếu chưa đổi) thành 1 chuỗi datetime cho AssessmentService::
     * assignToClass() (vẫn Carbon::parse() như cũ). Ném ValidationException nếu Ngày có
     * nhưng Ngày/Tháng/Năm ghép lại không phải ngày thật (vd 30/02).
     *
     * @throws ValidationException
     */
    private function combineOptionalDateTime(array $data, string $prefix): ?string
    {
        $day = $data[$prefix.'_day'] ?? null;

        if (blank($day)) {
            return null;
        }

        $month = (int) ($data[$prefix.'_month'] ?? now()->format('m'));
        $year = (int) ($data[$prefix.'_year'] ?? now()->format('Y'));
        $day = (int) $day;
        $hour = (int) ($data[$prefix.'_hour'] ?? '00');
        $minute = (int) ($data[$prefix.'_minute'] ?? '00');

        if (! checkdate($month, $day, $year)) {
            throw ValidationException::withMessages([
                $prefix.'_day' => 'Ngày/Tháng/Năm không hợp lệ.',
            ]);
        }

        return sprintf('%04d-%02d-%02d %02d:%02d:00', $year, $month, $day, $hour, $minute);
    }
}
