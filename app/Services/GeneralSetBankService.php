<?php

namespace App\Services;

use App\Enums\ContentStatus;
use App\Enums\OwnerType;
use App\Models\Assessment;
use App\Models\Question;
use App\Models\User;
use App\Services\Admin\ContentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * SỬA 3/11 (khách: "bài phổ thông vẫn thuộc Bài chung nhưng trong 1 bài có nhiều câu; lập trình giữ nguyên") — "Bài phổ
 * thông" hiện ĐÚNG 1 dòng ở tab "Bài chung" của Kho bài tập (admin + giáo viên); các câu con không hiện thành bài lẻ nữa
 * (QuestionRepository::hideGeneralSetChildren). Bấm vào bài mới thấy các câu bên trong. Phát hành / Xoá làm cho CẢ bài.
 */
class GeneralSetBankService
{
    public function __construct(private readonly ContentService $content) {}

    /**
     * @param  int|null  $teacherId  null = admin (mọi bài phổ thông); có id = chỉ bài của giáo viên đó.
     * @return list<array<string, mixed>>
     */
    public function rows(?int $teacherId = null, ?string $search = null): array
    {
        if (! Assessment::supportsPracticeKind()) {
            return [];
        }

        $sets = Assessment::query()
            ->where('practice_kind', Assessment::PRACTICE_KIND_GENERAL)
            ->when($teacherId !== null, fn ($q) => $q->where('owner_type', OwnerType::Teacher->value)->where('owner_id', $teacherId))
            ->when(filled($search), fn ($q) => $q->where(fn ($w) => $w->where('title', 'like', '%'.$search.'%')->orWhere('exam_code', 'like', '%'.$search.'%')))
            ->with(['items.question', 'creator'])
            ->latest()
            ->limit(200)
            ->get();

        $typeLabels = ['mcq' => 'Trắc nghiệm', 'fill_blank' => 'Điền từ', 'composite' => 'Câu nhiều ý'];

        return $sets->map(function (Assessment $a) use ($typeLabels) {
            $questions = $a->items->pluck('question')->filter();
            $types = [];
            foreach ($questions as $q) {
                $t = $q->type instanceof \BackedEnum ? $q->type->value : (string) $q->type;
                $types[$typeLabels[$t] ?? 'Khác'] = ($types[$typeLabels[$t] ?? 'Khác'] ?? 0) + 1;
            }
            $drafts = $questions->filter(fn ($q) => $q->status !== ContentStatus::Published)->count();
            $status = $a->status instanceof ContentStatus ? $a->status : ContentStatus::tryFrom((string) $a->status);
            [$label, $tone] = match ($status) {
                ContentStatus::Published => ['Phát hành', 'success'],
                ContentStatus::Archived => ['Lưu trữ', 'neutral'],
                ContentStatus::PendingReview => ['Chờ duyệt', 'warning'],
                default => ['Nháp', 'neutral'],
            };
            $isTeacher = $a->owner_type !== OwnerType::Shared;

            return [
                'id' => (int) $a->id,
                'title' => (string) $a->title,
                'code' => (string) ($a->exam_code ?? ''),
                'count' => $questions->count(),
                'typeSummary' => implode(' · ', array_map(fn ($l, $n) => $n.' '.$l, array_keys($types), $types)),
                'totalPoints' => rtrim(rtrim(number_format((float) $a->total_points, 2, ',', ''), '0'), ','),
                'status' => $label,
                'tone' => $tone,
                'published' => $status === ContentStatus::Published,
                'draftQuestions' => $drafts,
                'owner' => $isTeacher ? 'GV '.($a->creator->name ?? '') : 'Kho chung',
                'hasStatement' => filled($a->preview_pdf_path),
                'createdAt' => $a->created_at?->format('d/m/Y'),
            ];
        })->values()->all();
    }

    /** Admin "Phát hành" cả bài: phát hành các câu con còn nháp rồi phát hành bài. */
    public function publishForAdmin(Assessment $set): array
    {
        abort_unless($set->isGeneralSet(), 404);
        $res = $this->content->assessmentPublishAllQuestions($set);
        if ($res['failed'] !== []) {
            return ['ok' => false, 'message' => 'Chưa phát hành được: '.implode(' ', $res['failed'])];
        }

        return $this->content->assessmentPublish($set->fresh());
    }

    /** Admin "Ngừng phát hành": bài về Nháp (học sinh không thấy nữa), các câu con giữ nguyên. */
    public function unpublishForAdmin(Assessment $set): void
    {
        abort_unless($set->isGeneralSet(), 404);
        $set->forceFill(['status' => ContentStatus::Draft->value])->save();
    }

    /** Admin "Xoá" cả bài: xoá bài (đúng luật xoá đề sẵn có — có lượt làm thì không xoá) rồi xoá các câu con chỉ thuộc bài này. */
    public function destroyForAdmin(Assessment $set): array
    {
        abort_unless($set->isGeneralSet(), 404);
        $questionIds = $set->items()->pluck('question_id')->all();
        [$ok, $message] = $this->content->assessmentDestroy($set);
        if (! $ok) {
            return [false, $message];
        }
        foreach (Question::query()->whereIn('id', $questionIds)->get() as $q) {
            if (! $q->assessmentItems()->exists()) {
                $this->content->questionDestroy($q);
            }
        }

        return [true, 'Đã xoá bài phổ thông và '.count($questionIds).' câu bên trong.'];
    }

    /** Giáo viên xoá bài phổ thông CỦA MÌNH (chưa có lượt làm, chưa giao cho lớp). */
    public function destroyForTeacher(User $teacher, Assessment $set): array
    {
        abort_unless($set->isGeneralSet() && $set->owner_type === OwnerType::Teacher && (int) $set->owner_id === (int) $teacher->id, 403);
        if ($set->attempts()->exists()) {
            return [false, 'Không xoá được: bài đã có học sinh làm.'];
        }
        if ($set->assignments()->exists()) {
            return [false, 'Không xoá được: bài đang được giao cho lớp. Gỡ bài giao trước rồi hãy xoá.'];
        }

        $questionIds = $set->items()->pluck('question_id')->all();
        DB::transaction(function () use ($set, $questionIds) {
            foreach ([$set->preview_pdf_path, $set->solution_pdf_path] as $path) {
                if (filled($path)) {
                    Storage::disk('local')->delete($path);
                }
            }
            $set->items()->delete();
            $set->forceDelete();
            foreach (Question::query()->whereIn('id', $questionIds)->get() as $q) {
                if (! $q->assessmentItems()->exists() && ! DB::table('attempt_answers')->where('question_id', $q->id)->exists()) {
                    $q->delete();
                }
            }
        });

        return [true, 'Đã xoá bài phổ thông.'];
    }
}
