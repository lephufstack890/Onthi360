<?php

namespace App\Http\Controllers\Public;

use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ExamBundle\ExamBundleService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * SỬA 18/10 — trang BỘ ĐỀ cho người xem (route bundles.show, /bo-de/{product}): danh sách đề trong bộ + tiến độ của người
 * đang xem; đề "Chỉ trong bộ đề" bị khoá khi chưa mua. Ai cũng xem được danh sách (để biết bộ đề có gì trước khi mua).
 * Logic ở App\Services\ExamBundle\ExamBundleService::viewerData().
 */
class ExamBundleController extends Controller
{
    public function __construct(private readonly ExamBundleService $bundles) {}

    public function show(Request $request, int $product): View
    {
        abort_unless(ExamBundleService::ready(), 404);

        $model = Product::query()->findOrFail($product);
        $isAdmin = (bool) $request->user()?->hasAnyRole(\App\Models\Role::ADMIN, \App\Models\Role::SUPER_ADMIN);
        abort_unless($this->bundles->isBundle($model) && ($model->status === ContentStatus::Published || $isAdmin), 404);

        return view('public.bundles.show', $this->bundles->viewerData($model, $request->user()));
    }

    /**
     * SỬA 18/10 — tệp PDF của màn đọc bộ đề (bundles.read.file): kind = exam (PDF đề / xem trước) | question (đề bài 1 câu).
     * Chỉ đề / câu THUỘC bộ đề này; đề "Chỉ trong bộ đề" cần quyền bộ đề (ExamBundleService::canReadExam).
     */
    public function file(Request $request, int $product, string $kind, int $id)
    {
        abort_unless(ExamBundleService::ready(), 404);
        $model = Product::query()->findOrFail($product);
        abort_unless($this->bundles->isBundle($model), 404);

        if ($kind === 'exam') {
            $exam = $this->bundles->items($model)->firstWhere('id', $id);
            abort_if($exam === null || ! $this->bundles->canReadExam($request->user(), $model, $exam), 404);
            $path = $exam->pdf_path ?: $exam->preview_pdf_path;
            abort_unless(filled($path) && \Illuminate\Support\Facades\Storage::disk('local')->exists($path), 404);

            return \Illuminate\Support\Facades\Storage::disk('local')->response($path, 'de-'.$exam->id.'.pdf', ['Content-Type' => 'application/pdf']);
        }

        $exam = $this->bundles->examOfQuestion($model, $id);
        abort_if($exam === null || ! $this->bundles->canReadExam($request->user(), $model, $exam), 404);
        $info = \App\Models\Question::query()->findOrFail($id)->attachmentInfo('statement');
        abort_if($info === null, 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->response($info['path'], $info['filename']);
    }

    /**
     * SỬA 18/10 — bấm 1 ô câu hỏi ở màn đọc bộ đề (bundles.startQuestion): mở màn làm 1 câu của Luyện tập (giống bấm ô bài
     * tập ở trang đọc sách); "Bài trước / Bài tiếp theo" đi trong các câu CỦA ĐỀ ĐÓ. Quay lại = về đúng màn đọc.
     */
    public function startQuestion(Request $request, int $product, int $question): \Illuminate\Http\RedirectResponse
    {
        abort_unless(ExamBundleService::ready(), 404);
        $model = Product::query()->findOrFail($product);
        abort_unless($this->bundles->isBundle($model), 404);

        $exam = $this->bundles->examOfQuestion($model, $question);
        abort_if($exam === null, 404);
        abort_unless($this->bundles->canReadExam($request->user(), $model, $exam), 403);

        $data = $request->validate([
            'return_url' => ['nullable', 'string', 'max:2000'],
            'back_label' => ['nullable', 'string', 'max:60'],
        ]);
        $returnUrl = $data['return_url'] ?? null;
        if ($returnUrl !== null && (! str_starts_with($returnUrl, '/') || str_starts_with($returnUrl, '//'))) {
            $returnUrl = null; // chặn chuyển hướng ra ngoài
        }

        $siblings = $exam->items()->orderBy('order')->pluck('question_id')->map(fn ($v) => (int) $v)->all();
        app(\App\Services\Student\PracticeByQuestionService::class)
            ->startForQuestion($question, $returnUrl, $returnUrl !== null ? ($data['back_label'] ?? null) : null, $siblings);

        return redirect()->route('student.practiceByQuestion.play');
    }
}
