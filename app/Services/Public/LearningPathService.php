<?php

namespace App\Services\Public;

use App\Enums\ContentStatus;
use App\Models\ClassRoom;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\User;
use App\Repositories\Contracts\ClassEnrollmentRepositoryInterface;
use App\Repositories\Contracts\LearningPathRepositoryInterface;
use App\Support\LearningPathPalette;
use Illuminate\Support\Collection;

class LearningPathService
{
    public const PUBLIC_ENABLED = false;

    public function __construct(
        private readonly LearningPathRepositoryInterface $paths,
        private readonly ClassEnrollmentRepositoryInterface $classEnrollments,
    ) {}

    public function published(): Collection
    {
        $rows = $this->paths->publishedWithCourses();
        $rows->loadMissing('courses.product');

        return $rows;
    }

    public function pickerPayload(): array
    {
        $paths = $this->published();

        return [
            'paths' => $paths->map(fn (LearningPath $p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'title' => $p->title,
                'brand' => $p->brand,
                'goal' => $p->goal_label,
                'language' => $p->language?->value,
                'languageLabel' => $p->languageLabel(),
                'gradeFrom' => (int) $p->grade_from,
                'gradeTo' => (int) $p->grade_to,
                'gradeLabel' => $p->gradeLabel(),
                'stepCount' => $p->courses->count(),
                'totalSessions' => $p->totalSessions(),
                'href' => route('learningPaths.show', $p->slug),
            ])->values()->all(),
            'grades' => $this->coveredGrades($paths),
            'indexHref' => route('learningPaths.index'),
            'showLanguage' => \App\Services\Admin\LearningPathService::SHOW_LANGUAGE,
        ];
    }

    public function indexData(?int $grade, ?string $language): array
    {
        $all = $this->published();

        $rows = $all
            ->when($grade !== null, fn (Collection $c) => $c->filter(fn (LearningPath $p) => $p->coversGrade($grade)))
            ->when($language !== null && $language !== '', fn (Collection $c) => $c->filter(fn (LearningPath $p) => $p->language?->value === $language));

        return [
            'paths' => $rows->map(fn (LearningPath $p) => $this->card($p))->values()->all(),
            'grades' => $this->coveredGrades($all),
            'languages' => $this->availableLanguages($all),
            'activeGrade' => $grade,
            'activeLanguage' => $language,
            'totalCount' => $all->count(),
            'showLanguage' => \App\Services\Admin\LearningPathService::SHOW_LANGUAGE,
        ];
    }

    public function showData(string $slug, ?User $viewer = null): array
    {
        $path = $this->paths->findBySlugWithCourses($slug);

        if ($path === null || $path->status !== ContentStatus::Published) {
            abort(404);
        }

        $path->loadMissing(['courses.product', 'courses.classRooms']);

        $myClassRoomIds = $viewer !== null
            ? $this->classEnrollments->activeClassRoomIdsForUser($viewer->id)
            : [];

        $steps = $path->courses->values()->map(function (Course $course, int $index) use ($myClassRoomIds) {
            $openClasses = $course->classRooms->where('status', 'active');
            $joinedClass = $openClasses->first(fn (ClassRoom $c) => in_array($c->id, $myClassRoomIds, true));

            return [
                'position' => $index + 1,
                'courseId' => $course->id,
                'title' => $course->title,
                'levelCode' => $course->level_code,
                'levelSubtitle' => $course->level_subtitle,
                'outcome' => $course->outcome,
                'sessionCount' => (int) $course->session_count,
                'openClassCount' => $openClasses->count(),
                'color' => LearningPathPalette::step($index),
                'href' => route('courses.show', $course->id),
                'price' => $course->learningPrice(),
                'priceLabel' => $course->learningPrice() !== null ? number_format((int) $course->learningPrice()).'đ' : null,
                'buyHref' => $course->isPurchasable() ? route('access.checkout', $course->product_id) : null,
                'myClassHref' => $joinedClass !== null ? route('student.classes.show', $joinedClass->id) : null,
            ];
        })->values()->all();

        return [
            'path' => $path,
            'steps' => $steps,
            'ramp' => LearningPathPalette::ramp(),
            'totalPrice' => array_sum(array_map(fn (array $s) => (int) ($s['price'] ?? 0), $steps)),
            'purchasableCount' => count(array_filter($steps, fn (array $s) => $s['buyHref'] !== null)),
            'related' => $this->published()
                ->reject(fn (LearningPath $p) => $p->id === $path->id)
                ->take(3)
                ->map(fn (LearningPath $p) => $this->card($p))
                ->values()->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function stripsForCourse(Course $course): array
    {
        return $course->learningPaths()
            ->where('status', ContentStatus::Published->value)
            ->with(['courses'])
            ->get()
            ->map(function (LearningPath $path) use ($course) {
                $ordered = $path->courses->values();
                $index = $ordered->search(fn (Course $c) => $c->id === $course->id);

                if ($index === false) {
                    return null;
                }

                $prev = $index > 0 ? $ordered[$index - 1] : null;
                $next = $index < $ordered->count() - 1 ? $ordered[$index + 1] : null;

                return [
                    'pathTitle' => $path->title,
                    'pathHref' => route('learningPaths.show', $path->slug),
                    'position' => $index + 1,
                    'total' => $ordered->count(),
                    'color' => LearningPathPalette::step($index),
                    'prev' => $prev ? ['title' => $prev->title, 'href' => route('courses.show', $prev->id), 'levelCode' => $prev->level_code] : null,
                    'next' => $next ? ['title' => $next->title, 'href' => route('courses.show', $next->id), 'levelCode' => $next->level_code] : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function filterOptions(): array
    {
        return $this->published()
            ->map(fn (LearningPath $p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'title' => $p->title,
                'brand' => $p->brand,
                'subtitle' => $p->subtitle,
                'goal' => $p->goal_label,
                'gradeLabel' => $p->gradeLabel(),
                'gradeFrom' => (int) $p->grade_from,
                'gradeTo' => (int) $p->grade_to,
                'stepCount' => $p->courses->count(),
                'totalSessions' => $p->totalSessions(),
                'totalWeeks' => $p->totalWeeks(),
                'coverUrl' => $p->coverUrl(),
                'href' => route('learningPaths.show', $p->slug),
                'courseIds' => $p->courses->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                'search' => mb_strtolower(trim(implode(' ', array_filter([
                    $p->title, $p->brand, $p->subtitle, $p->goal_label, $p->gradeLabel(),
                    $p->courses->pluck('title')->implode(' '),
                ])))),
            ])
            ->filter(fn (array $row) => $row['courseIds'] !== [])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function gradeOptions(): array
    {
        return array_map(
            fn (int $grade) => 'Lớp '.$grade,
            $this->coveredGrades($this->published()),
        );
    }

    public function homeStrip(int $limit = 5): array
    {
        return $this->published()
            ->take($limit)
            ->map(fn (LearningPath $p) => $this->card($p))
            ->values()
            ->all();
    }

    private function card(LearningPath $path): array
    {
        return [
            'id' => $path->id,
            'slug' => $path->slug,
            'title' => $path->title,
            'brand' => $path->brand,
            'eyebrow' => $path->eyebrow,
            'subtitle' => $path->subtitle,
            'goal' => $path->goal_label,
            'gradeLabel' => $path->gradeLabel(),
            'gradeFrom' => (int) $path->grade_from,
            'gradeTo' => (int) $path->grade_to,
            'languageLabel' => $path->languageLabel(),
            'language' => $path->language?->value,
            'stepCount' => $path->courses->count(),
            'totalSessions' => $path->totalSessions(),
            'totalWeeks' => $path->totalWeeks(),
            'paceLabel' => $path->paceLabel(),
            'outcomes' => $path->outcomeList(),
            'coverUrl' => $path->coverUrl(),
            'href' => route('learningPaths.show', $path->slug),
            'levelCodes' => $path->courses->pluck('level_code')->filter()->values()->all(),
        ];
    }

    private function coveredGrades(Collection $paths): array
    {
        $grades = [];

        foreach ($paths as $path) {
            for ($g = (int) $path->grade_from; $g <= (int) $path->grade_to; $g++) {
                $grades[$g] = true;
            }
        }

        ksort($grades);

        return array_keys($grades);
    }

    /** @return array<string, string> mã ngôn ngữ => nhãn, chỉ những ngôn ngữ có thật. */
    private function availableLanguages(Collection $paths): array
    {
        $out = [];

        foreach ($paths as $path) {
            if ($path->language !== null) {
                $out[$path->language->value] = $path->language->label();
            }
        }

        return $out;
    }
}
