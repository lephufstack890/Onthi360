<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\UploadedDocumentStatus;
use App\Http\Controllers\Controller;
use App\Services\Teacher\AssessmentService;
use App\Services\Teacher\DocumentImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssessmentController extends Controller
{
    public function __construct(
        private readonly AssessmentService $assessmentService,
        private readonly DocumentImportService $documentImportService,
    ) {}

    public function index(Request $request): View
    {
        return view('teacher.assessments.index', $this->assessmentService->listForTeacher(Auth::user()));
    }

    public function create(Request $request): View
    {
        return view('teacher.assessments.create', $this->assessmentService->createFormData(Auth::user()));
    }

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

        return view('teacher.assessments.import', $this->assessmentService->importStatusFor($user) + [
            'maxFileKb' => DocumentImportService::maxFileKb(),
        ]);
    }

    public function importStore(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:docx,pdf', 'max:'.DocumentImportService::maxFileKb()],
        ], [], ['file' => 'Tệp']);

        set_time_limit(300);

        $document = $this->documentImportService->import(Auth::user(), $request->file('file'));

        if ($document->status === UploadedDocumentStatus::Failed) {
            return redirect()->route('teacher.assessments.import')
                ->with('status', 'import-failed')
                ->with('importError', $document->error_log);
        }

        return redirect()->route('teacher.assessments.reviewDraft', ['document' => $document->id])
            ->with('status', 'import-parsed');
    }

    public function downloadDocument(Request $request, int $document): StreamedResponse
    {
        $documentModel = $this->documentImportService->findOwnedDocument(Auth::user(), $document);

        return Storage::disk('local')->download($documentModel->storage_path, $documentModel->original_filename);
    }

    public function reviewDraft(Request $request): View
    {
        $user = Auth::user();
        $documentId = $request->filled('document') ? (int) $request->query('document') : null;

        return view('teacher.assessments.review-draft', $this->assessmentService->reviewDraftFor($user, $documentId));
    }

    public function draftStore(Request $request, int $document): RedirectResponse
    {
        $documentModel = $this->documentImportService->findOwnedDocument(Auth::user(), $document);
        $this->documentImportService->addManualDraft($documentModel);

        return redirect()->route('teacher.assessments.reviewDraft', ['document' => $documentModel->id])
            ->with('status', 'draft-added');
    }

    public function draftUpdate(Request $request, int $draft): RedirectResponse
    {
        $draftModel = $this->documentImportService->findOwnedDraft(Auth::user(), $draft);
        $type = $request->input('type_guess', 'mcq');
        $data = $request->validate($this->draftValidationRules($type));

        $result = $this->documentImportService->reviewSave(Auth::user(), $draftModel, $type, $data);

        $redirect = redirect()->route('teacher.assessments.reviewDraft', ['document' => $draftModel->uploaded_document_id]);

        return $result['promoted']
            ? $redirect->with('status', 'draft-promoted-one')
            : $redirect->with('status', 'draft-saved-pending')->with('draftPendingReason', $result['reason']);
    }

    public function draftMerge(Request $request, int $draft): RedirectResponse
    {
        $draftModel = $this->documentImportService->findOwnedDraft(Auth::user(), $draft);
        $documentId = $draftModel->uploaded_document_id;
        $data = $request->validate(['merge_with_id' => ['required', 'integer']]);

        try {
            $this->documentImportService->mergeDrafts(Auth::user(), $draftModel, (int) $data['merge_with_id']);
        } catch (ValidationException $e) {
            return redirect()->route('teacher.assessments.reviewDraft', ['document' => $documentId])->withErrors($e->errors());
        }

        return redirect()->route('teacher.assessments.reviewDraft', ['document' => $documentId])->with('status', 'draft-merged');
    }

    public function draftDiscard(Request $request, int $draft): RedirectResponse
    {
        $draftModel = $this->documentImportService->findOwnedDraft(Auth::user(), $draft);
        $documentId = $draftModel->uploaded_document_id;

        $this->documentImportService->discardDraft($draftModel);

        return redirect()->route('teacher.assessments.reviewDraft', ['document' => $documentId])->with('status', 'draft-discarded');
    }

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
            'shift_count' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }

    private function draftValidationRules(string $type): array
    {
        $common = [
            'type_guess' => ['required', 'in:mcq,fill_blank,coding'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'points' => ['required', 'integer', 'min:1', 'max:100'],
        ];

        return match ($type) {
            'mcq' => $common + [
                'options' => ['nullable', 'array'],
                'options.*' => ['nullable', 'string', 'max:500'],
                'correct_option' => ['nullable', 'string', 'max:10'],
            ],
            'fill_blank' => $common + [
                'accepted_answers' => ['nullable', 'string', 'max:1000'],
                'case_sensitive' => ['nullable', 'boolean'],
            ],
            'coding' => $common + [
                'time_limit_ms' => ['nullable', 'integer', 'min:100', 'max:60000'],
                'memory_limit_mb' => ['nullable', 'integer', 'min:16', 'max:2048'],
                'test_cases' => ['nullable', 'string', 'max:20000'],
            ],
            default => $common,
        };
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
