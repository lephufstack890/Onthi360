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
    /*
     * ══════ CÔNG TẮC TOÀN BỘ PHẦN LỘ TRÌNH CÔNG KHAI ══════
     *
     * SỬA 15/9 — khách đổi ý: tạm KHÔNG bán/giới thiệu theo lộ trình nữa, quay lại cách cũ
     * "khoá học chứa các lớp học". Đặt false để TẮT mọi lối vào lộ trình ở phía người dùng:
     *
     *   · 2 trang /lo-trinh và /lo-trinh/{slug}  -> trả 404 (Public\LearningPathController)
     *   · mục "Lộ trình" trên thanh menu          -> ẩn (partials/nav-public, welcome)
     *   · liên kết "Lộ trình học" ở chân trang    -> ẩn (partials/footer)
     *   · sitemap.xml                             -> không liệt kê (Public\SitemapController)
     *   · khối [HOME-05B] ở trang chủ             -> quay về 5 thẻ giới thiệu như bản gốc
     *   · ba ô chọn [HOME-04] ở trang chủ         -> quay về nút "Xem lộ trình" tĩnh như cũ
     *
     * ẨN CHỨ KHÔNG XOÁ — đúng yêu cầu "sau này dùng thì mở ra". TOÀN BỘ mã lộ trình vẫn còn
     * nguyên: service này, Public\LearningPathController, 2 view public/learning-paths/*, và
     * cả khu quản trị Lộ trình (admin/learning-paths/*) vẫn chạy bình thường để quản trị viên
     * tiếp tục soạn nội dung. Bật lại: đổi đúng MỘT dòng dưới đây thành true.
     *
     * ── BA FILE KHÔNG NẰM SAU CÔNG TẮC NÀY ──
     * Trang Lớp học công khai và ba ô chọn ở trang chủ bị lộ trình viết đè lên gần như toàn
     * bộ, không tách được bằng một câu if. Ba file dưới đây đã được TRẢ NGUYÊN VỀ BẢN GỐC
     * bằng git (commit 2dd210c, 15/9 10:02 — ngay trước khi bắt đầu làm lộ trình công khai):
     *
     *     resources/views/public/courses/index.blade.php
     *     resources/views/partials/courses-script.blade.php
     *     resources/views/partials/home-script.blade.php
     *
     * Bản có lộ trình của ba file đó KHÔNG MẤT, nằm nguyên trong commit 7184796. Muốn bật lại
     * đầy đủ thì ngoài việc đổi hằng dưới thành true, chạy thêm:
     *
     *     git checkout 7184796 -- resources/views/public/courses/index.blade.php \
     *         resources/views/partials/courses-script.blade.php \
     *         resources/views/partials/home-script.blade.php
     *
     * và mở lại tham số ?lo-trinh= trong Public\CourseController::index (cũng ở commit đó).
     */
    public const PUBLIC_ENABLED = false;

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
     * B7 — lộ trình dùng ở trang Lớp học.
     *
     * ── SỬA 15/9 (khách chốt luồng chọn) ──
     * Trang Lớp học giờ đi theo hai bước: chọn KHỐI LỚP trước, rồi hiện các LỘ TRÌNH của khối
     * đó để bấm vào xem chi tiết. Vì vậy mỗi dòng ở đây phải đủ dựng một tấm thẻ lộ trình
     * (tên, mục tiêu, số bậc, ảnh...), chứ không chỉ đủ để lọc như bản đầu.
     *
     * Vẫn giữ courseIds: khi người dùng đi ngược từ trang lộ trình sang (?lo-trinh=), trang
     * Lớp học lọc khoá theo đúng danh sách id này.
     *
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
                // Chuỗi để ô tìm kiếm dò — gộp sẵn ở đây cho trình duyệt khỏi phải ghép lại.
                'search' => mb_strtolower(trim(implode(' ', array_filter([
                    $p->title, $p->brand, $p->subtitle, $p->goal_label, $p->gradeLabel(),
                    $p->courses->pluck('title')->implode(' '),
                ])))),
            ])
            // Lộ trình chưa có bậc nào thì bấm vào là một trang trống — bỏ khỏi danh sách.
            ->filter(fn (array $row) => $row['courseIds'] !== [])
            ->values()
            ->all();
    }

    /**
     * Các khối lớp có lộ trình phủ tới, dạng nhãn "Lớp 6".
     *
     * SỬA 15/9 (khách: "chỗ khối lớp đổ khối lớp theo lộ trình") — trước dải lọc khối ở trang
     * Lớp học lấy từ cột courses.grade, nên hiện ra cả những khối chưa có lộ trình nào (bấm
     * vào là rỗng), lại thiếu những khối nằm giữa một khoảng (lộ trình "Khối 6–8" thì phải ra
     * đủ Lớp 6, 7, 8 dù không khoá nào ghi "Lớp 7").
     *
     * @return list<string>
     */
    public function gradeOptions(): array
    {
        return array_map(
            fn (int $grade) => 'Lớp '.$grade,
            $this->coveredGrades($this->published()),
        );
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
