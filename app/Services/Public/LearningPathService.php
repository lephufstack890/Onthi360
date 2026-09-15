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

/**
 * B1 — Lộ trình học ở phía công khai.
 *
 * ── Một nguồn dữ liệu cho bốn chỗ ──
 * Trang chủ (ba ô chọn), trang /lo-trinh, trang /lo-trinh/{slug} và bộ lọc ở trang Lớp học
 * đều gọi vào đây. Viết riêng cho từng trang thì sớm muộn bốn chỗ hiện bốn kiểu khác nhau
 * cho cùng một lộ trình.
 *
 * ── Vì sao nạp hết rồi lọc trong bộ nhớ ──
 * Số lộ trình đang hiển thị chỉ vài cái (khách hình dung dưới 20). Nạp một lần kèm các bậc
 * rồi lọc tại chỗ rẻ hơn hẳn việc mỗi lần người dùng đổi ô chọn lại bắn một truy vấn. Khi
 * nào vượt vài trăm lộ trình thì đổi publishedWithCourses() sang truy vấn có điều kiện —
 * chỉ phải sửa đúng repository, không đụng tới các trang.
 */
class LearningPathService
{
    public function __construct(
        private readonly LearningPathRepositoryInterface $paths,
        private readonly ClassEnrollmentRepositoryInterface $classEnrollments,
    ) {}

    /** Mọi lộ trình đang hiển thị, đã nạp sẵn bậc + sản phẩm của bậc. */
    public function published(): Collection
    {
        $rows = $this->paths->publishedWithCourses();
        $rows->loadMissing('courses.product');

        return $rows;
    }

    /**
     * Dữ liệu cho ba ô chọn thu hẹp dần ở trang chủ (B2).
     *
     * Trả về NGUYÊN danh sách lộ trình dạng gọn để trình duyệt tự lọc — không tải lại trang
     * mỗi lần người dùng đổi lựa chọn. Ba ô chọn không phải ba danh sách cố định: chúng được
     * sinh ra từ chính danh sách này ở phía trình duyệt, nên không bao giờ hiện một khối lớp
     * hay một mục tiêu không có lộ trình nào đứng sau.
     */
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
            // Mọi lớp có lộ trình phủ tới, sắp tăng dần — ô chọn khối lớp dựng từ đây.
            'grades' => $this->coveredGrades($paths),
            'indexHref' => route('learningPaths.index'),
            // Ô ngôn ngữ chỉ có nghĩa khi khách còn dùng lộ trình cho môn Tin học. Bật/tắt
            // theo cùng một công tắc với khu quản trị để hai bên không lệch nhau.
            'showLanguage' => \App\Services\Admin\LearningPathService::SHOW_LANGUAGE,
        ];
    }

    /** B3 — trang /lo-trinh, lọc theo khối lớp và ngôn ngữ. */
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

    /**
     * B4 — trang /lo-trinh/{slug}: thang bậc dựng bằng dữ liệu.
     *
     * Màu của bậc sinh theo VỊ TRÍ qua LearningPathPalette, nên thêm hay bớt bậc là thang tự
     * đổi màu — không phải nhờ thiết kế vẽ lại ảnh, cũng không phải lưu mã màu vào cơ sở dữ
     * liệu rồi quên cập nhật.
     */
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
                // C1/C2 — nút mua thật. Chưa gắn sản phẩm hoặc giá 0 thì KHÔNG hiện nút mua,
                // trang chuyển sang mời liên hệ ghi danh thay vì đưa ra một nút bấm vào không ra gì.
                'price' => $course->learningPrice(),
                'priceLabel' => $course->learningPrice() !== null ? number_format((int) $course->learningPrice()).'đ' : null,
                'buyHref' => $course->isPurchasable() ? route('access.checkout', $course->product_id) : null,
                // Đã là học viên của bậc này rồi thì vào thẳng lớp đang học.
                'myClassHref' => $joinedClass !== null ? route('student.classes.show', $joinedClass->id) : null,
            ];
        })->values()->all();

        return [
            'path' => $path,
            'steps' => $steps,
            'ramp' => LearningPathPalette::ramp(),
            'totalPrice' => array_sum(array_map(fn (array $s) => (int) ($s['price'] ?? 0), $steps)),
            // Có bậc nào bán được không — quyết định hiện khối "Mua trọn lộ trình" hay không.
            'purchasableCount' => count(array_filter($steps, fn (array $s) => $s['buyHref'] !== null)),
            'related' => $this->published()
                ->reject(fn (LearningPath $p) => $p->id === $path->id)
                ->take(3)
                ->map(fn (LearningPath $p) => $this->card($p))
                ->values()->all(),
        ];
    }

    /**
     * B6 — dải "Bậc 2/6 của lộ trình ..." ở trang chi tiết khoá học.
     *
     * Một khoá dùng lại được ở nhiều lộ trình, nên trả về danh sách chứ không phải một. Trang
     * khoá học in dải đầu tiên và nêu số lộ trình còn lại.
     *
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
     * B7 — bộ lọc "theo lộ trình" ở trang Lớp học.
     *
     * Trả về từng lộ trình kèm danh sách id khoá học của nó, để trang lớp lọc ngay tại chỗ
     * bằng id khoá — không cần thêm truy vấn khi người dùng đổi lộ trình.
     *
     * @return list<array{id:int, title:string, courseIds:list<int>}>
     */
    public function filterOptions(): array
    {
        return $this->published()
            ->map(fn (LearningPath $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'gradeLabel' => $p->gradeLabel(),
                'courseIds' => $p->courses->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            ])
            // Lộ trình chưa có bậc nào thì lọc ra sẽ rỗng — bỏ khỏi danh sách cho đỡ hụt hẫng.
            ->filter(fn (array $row) => $row['courseIds'] !== [])
            ->values()
            ->all();
    }

    /** B5 — các bước hiện ở khối "Lộ trình học chuyên nghiệp" trang chủ. */
    public function homeStrip(int $limit = 5): array
    {
        return $this->published()
            ->take($limit)
            ->map(fn (LearningPath $p) => $this->card($p))
            ->values()
            ->all();
    }

    // ───────────────────────────── nội bộ ─────────────────────────────

    /** Thẻ lộ trình dùng chung cho trang danh sách, khối trang chủ và mục "lộ trình khác". */
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
            // Hai số thô để trang danh sách lọc theo khối ngay tại trình duyệt.
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

    /** Các lớp (6, 7, 8...) mà tập lộ trình này phủ tới, tăng dần và không trùng. */
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
