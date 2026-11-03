<?php

namespace App\Http\Controllers;

use App\Enums\AttemptStatus;
use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\Role;
use App\Services\GeneralSetImportService;
use App\Support\UploadLimit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SỬA 3/11 — "Bài phổ thông" (1 bài nhiều câu trắc nghiệm / đúng sai / điền từ / câu nhiều ý) nhập bằng gói ZIP — xem
 * App\Services\GeneralSetImportService. Controller MỚI, không đụng các controller nhập ZIP cũ (Lập trình / câu lẻ giữ nguyên).
 */
class GeneralSetController extends Controller
{
    public function __construct(private readonly GeneralSetImportService $import) {}

    /** admin.content.generalSets.zipImport */
    public function adminImport(Request $request): RedirectResponse
    {
        $this->validateZip($request);

        try {
            $result = $this->import->importForAdmin(Auth::user(), $request->file('general_zip'));
        } catch (ValidationException $e) {
            return redirect()->route('admin.content.questions.create', ['nhap' => 'pho-thong'])->withErrors($e->errors());
        }

        return redirect()
            ->to(route('admin.content.show', ['content' => $result['assessment']->id, 'kind' => 'assessment']).'#assessment-items')
            ->with('status', 'assessment-zip-imported')
            ->with('zipImportSummary', 'Bài phổ thông "'.$result['assessment']->title.'" — '.$result['summary']);
    }

    /** teacher.questions.generalImport */
    public function teacherImport(Request $request): RedirectResponse
    {
        $this->validateZip($request);

        try {
            $result = $this->import->importForTeacher(Auth::user(), $request->file('general_zip'));
        } catch (ValidationException $e) {
            return redirect()->route('teacher.questions.create', ['nhap' => 'pho-thong'])->withErrors($e->errors());
        }

        return redirect()->route('teacher.assessments.index')
            ->with('status', 'general-set-imported')
            ->with('generalSetNotice', 'Đã tạo bài phổ thông "'.$result['assessment']->title.'" ('.$result['summary'].'). Bấm "Giao" ở bài này để giao cho lớp.');
    }

    /**
     * general-sets.file — đề cả bài (statement) / lời giải cả bài (solution) của bài phổ thông.
     * Đề: người đã có lượt làm bài; lời giải: người đã NỘP ít nhất 1 lượt (đáp án công bố ngay sau khi nộp). Admin và người
     * sở hữu bài luôn xem được.
     */
    public function file(Request $request, Assessment $assessment, string $which): StreamedResponse
    {
        abort_unless($assessment->isGeneralSet() && in_array($which, ['statement', 'solution'], true), 404);
        $user = $request->user();

        $staff = $user->hasAnyRole(Role::ADMIN, Role::SUPER_ADMIN) || $user->isRealAdmin()
            || ((string) ($assessment->owner_type?->value ?? $assessment->owner_type) === 'teacher' && (int) $assessment->owner_id === (int) $user->id);
        if (! $staff) {
            $attempts = Attempt::query()->where('user_id', $user->id)->where('assessment_id', $assessment->id);
            if ($which === 'solution') {
                $attempts->where('status', '!=', AttemptStatus::InProgress->value)->whereNotNull('submitted_at');
            }
            abort_unless($attempts->exists(), 403);
        }

        $path = $which === 'solution' ? $assessment->solution_pdf_path : $assessment->preview_pdf_path;
        abort_if(blank($path) || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, ($which === 'solution' ? 'loi-giai' : 'de-bai').'.pdf');
    }

    // ───── SỬA 3/11 — "Bài phổ thông" là 1 dòng ở tab Bài chung: Phát hành / Ngừng phát hành / Xoá CẢ bài ─────

    /** admin.content.generalSets.publish */
    public function adminPublish(Assessment $assessment): RedirectResponse
    {
        $r = app(\App\Services\GeneralSetBankService::class)->publishForAdmin($assessment);

        return back()->with('generalSetFlash', $r['ok'] ? ['success', 'Đã phát hành bài "'.$assessment->title.'" — học sinh thấy ở Luyện tập > Phổ thông.'] : ['error', (string) $r['message']]);
    }

    /** admin.content.generalSets.unpublish */
    public function adminUnpublish(Assessment $assessment): RedirectResponse
    {
        app(\App\Services\GeneralSetBankService::class)->unpublishForAdmin($assessment);

        return back()->with('generalSetFlash', ['success', 'Đã ngừng phát hành bài "'.$assessment->title.'" — học sinh không thấy nữa.']);
    }

    /** admin.content.generalSets.destroy */
    public function adminDestroy(Assessment $assessment): RedirectResponse
    {
        [$ok, $msg] = app(\App\Services\GeneralSetBankService::class)->destroyForAdmin($assessment);

        return back()->with('generalSetFlash', [$ok ? 'success' : 'error', $msg]);
    }

    /** teacher.questions.generalSets.destroy */
    public function teacherDestroy(Request $request, Assessment $assessment): RedirectResponse
    {
        [$ok, $msg] = app(\App\Services\GeneralSetBankService::class)->destroyForTeacher($request->user(), $assessment);

        return back()->with('generalSetFlash', [$ok ? 'success' : 'error', $msg]);
    }

    // ───── SỬA 3/11 (khách: "tab Phổ thông thì form Phổ thông, file nhập như nào form như vậy") — FORM NHẬP TAY bài phổ thông ─────
    // Form (partials/general-set-form) được đóng thành ĐÚNG 1 gói ZIP Phổ thông (App\Support\GeneralSetFormPackage) rồi đi lại
    // đúng importForAdmin() / importForTeacher() ở trên — không thêm luật nhập nào mới.

    /** admin.content.generalSets.formStore */
    public function adminForm(Request $request): RedirectResponse
    {
        return $this->fromForm($request, 'admin.content.questions.create', function ($zip) {
            $result = $this->import->importForAdmin(Auth::user(), $zip);

            return redirect()
                ->to(route('admin.content.show', ['content' => $result['assessment']->id, 'kind' => 'assessment']).'#assessment-items')
                ->with('status', 'assessment-zip-imported')
                ->with('zipImportSummary', 'Bài phổ thông "'.$result['assessment']->title.'" — '.$result['summary']);
        });
    }

    /** teacher.questions.generalForm */
    public function teacherForm(Request $request): RedirectResponse
    {
        return $this->fromForm($request, 'teacher.questions.create', function ($zip) {
            $result = $this->import->importForTeacher(Auth::user(), $zip);

            return redirect()->route('teacher.assessments.index')
                ->with('status', 'general-set-imported')
                ->with('generalSetNotice', 'Đã tạo bài phổ thông "'.$result['assessment']->title.'" ('.$result['summary'].'). Bấm "Giao" ở bài này để giao cho lớp.');
        });
    }

    private function fromForm(Request $request, string $backRoute, \Closure $import): RedirectResponse
    {
        $back = fn (array $errors) => redirect()->route($backRoute, ['nhap' => 'pho-thong'])
            ->withErrors(['general_form' => $errors])
            ->withInput($request->except(['statement_pdf', 'solution_pdf', 'q_image']));

        $v = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:20'],
            'grades' => ['nullable', 'array'],
            'grades.*' => ['integer', 'between:1,12'],
            'province' => ['nullable', 'string', 'max:60'],
            'exam_year' => ['nullable', 'integer', 'between:1990,2100'],
            'difficulty' => ['nullable', 'string', 'max:20'],
            'tags' => ['nullable', 'string', 'max:500'],
            'source' => ['nullable', 'string', 'max:120'],
            'questions_json' => ['required', 'string', 'max:2000000'],
            'statement_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:51200'],
            'solution_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:51200'],
            'q_image' => ['nullable', 'array'],
            'q_image.*' => ['file', 'image', 'max:20480'],
        ], [], [
            'title' => 'Tên bài', 'code' => 'Mã bài', 'questions_json' => 'Danh sách câu', 'statement_pdf' => 'Đề cả bài (PDF)',
            'solution_pdf' => 'Lời giải cả bài (PDF)', 'q_image.*' => 'Ảnh minh hoạ', 'exam_year' => 'Năm',
        ]);
        if ($v->fails()) {
            return $back($v->errors()->all());
        }

        $built = null;
        try {
            $built = \App\Support\GeneralSetFormPackage::build($v->validated(), $request->file('statement_pdf'), $request->file('solution_pdf'), (array) $request->file('q_image', []));

            return $import($built['zip']);
        } catch (ValidationException $e) {
            // Lỗi của bộ đọc gói ZIP (vd "Câu 3: …") — chuyển sang ô lỗi của form.
            return $back(collect($e->errors())->flatten()->map(fn ($m) => str_replace('question.json: ', '', (string) $m))->unique()->values()->all());
        } finally {
            \App\Support\GeneralSetFormPackage::cleanup($built);
        }
    }

    private function validateZip(Request $request): void
    {
        $request->validate([
            'general_zip' => ['required', 'file', 'mimes:zip', 'max:'.UploadLimit::zipKilobytes()],
        ], ['general_zip.max' => 'Gói ZIP vượt quá '.UploadLimit::zipLabel().' mà máy chủ nhận được.'], ['general_zip' => 'Gói ZIP bài phổ thông']);
    }
}
