<?php

namespace App\Services\Admin;

use App\Enums\ContentStatus;
use App\Enums\PathLanguage;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\User;
use App\Repositories\Contracts\CourseRepositoryInterface;
use App\Repositories\Contracts\LearningPathRepositoryInterface;
use App\Support\LearningPathPalette;
use App\Support\LearningPathReadiness;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Khu quản trị Lộ trình học.
 *
 * Toàn bộ luật nghiệp vụ nằm ở đây; Controller chỉ nhận dữ liệu nhập và gọi sang. Giữ đúng
 * cách phân lớp của dự án để sau này sửa luật là biết ngay phải mở tệp nào.
 *
 * Luật "được phép đăng hay chưa" cố tình KHÔNG viết ở đây mà tách sang
 * App\Support\LearningPathReadiness — vì bộ luật đó chắc chắn còn đổi.
 */
class LearningPathService
{
    /**
     * Có hiện ô "Ngôn ngữ lập trình" hay không.
     *
     * SỬA 15/9 (khách yêu cầu) — TẮT. Lộ trình sau này còn dùng cho Toán, Văn và các môn
     * khác, lúc đó ngôn ngữ lập trình không còn nghĩa gì nên bỏ khỏi cả biểu mẫu lẫn danh
     * sách. Dữ liệu cũ (Python/C++ của 5 lộ trình Tin học) vẫn nằm nguyên trong CSDL.
     *
     * Đây là CÔNG TẮC DUY NHẤT: đổi thành true là ô nhập và cột trong danh sách hiện lại,
     * không phải sửa view.
     */
    public const SHOW_LANGUAGE = false;

    /**
     * Có hiện mục "Lộ trình" trên menu trái của khu Quản trị hay không.
     *
     * SỬA 15/9 (khách: "trong admin ẩn mục lộ trình đi") — TẮT, sau khi khách chốt quay lại
     * cách bán cũ "khoá học có các lớp học". ẨN CHỨ KHÔNG XOÁ, và cố ý CHỈ ẨN MỤC MENU:
     * các route admin.learning-paths.* vẫn chạy bình thường nên vẫn vào được bằng đường dẫn
     * /admin/learning-paths khi cần xem lại hay soạn tiếp, và dữ liệu lộ trình đã nhập không
     * bị ảnh hưởng gì.
     *
     * Đổi thành true là mục menu hiện lại ngay — xem partials/sidebar-admin.blade.php.
     * Phần lộ trình ở TRANG CÔNG KHAI là công tắc riêng, xem
     * App\Services\Public\LearningPathService::PUBLIC_ENABLED.
     */
    public const SHOW_ADMIN_MENU = false;

    /** Nơi cất ảnh lộ trình — cùng đĩa 'public' với ảnh của các khu khác. */
    private const DISK = 'public';
    private const DIR = 'learning-paths';

    public function __construct(
        private readonly LearningPathRepositoryInterface $paths,
        private readonly CourseRepositoryInterface $courses,
    ) {}

    public function indexData(): array
    {
        $rows = $this->paths->allForAdmin();
        $counts = $this->paths->countsByStatus();

        return [
            'paths' => $rows->map(fn (LearningPath $p) => $this->row($p))->all(),
            'stats' => [
                'total' => $rows->count(),
                'published' => $counts[ContentStatus::Published->value] ?? 0,
                'draft' => $counts[ContentStatus::Draft->value] ?? 0,
                'needsAttention' => $rows->filter(fn (LearningPath $p) => LearningPathReadiness::check($p) !== [])->count(),
            ],
        ];
    }

    public function createFormData(): array
    {
        return [
            'path' => null,
            'statuses' => $this->statuses(),
            'languages' => PathLanguage::options(),
        ];
    }

    public function editFormData(int $id): array
    {
        return [
            'path' => $this->paths->findOrFail($id),
            'statuses' => $this->statuses(),
            'languages' => PathLanguage::options(),
        ];
    }

    /**
     * Màn xếp bậc: các bậc đã xếp + danh sách khoá học còn có thể thêm vào.
     */
    public function stepsData(int $id): array
    {
        $path = $this->paths->findWithCourses($id);

        if ($path === null) {
            abort(404);
        }

        // C1 — nạp sẵn sản phẩm của từng bậc để hiện giá, tránh N+1 khi lộ trình nhiều bậc.
        $path->loadMissing('courses.product');

        $attachedIds = $path->courses->pluck('id')->all();

        $steps = $path->courses->values()->map(function (Course $course, int $index) {
            $tone = LearningPathPalette::step($index);

            return [
                'id' => $course->id,
                'position' => $index + 1,
                'title' => $course->title,
                'levelCode' => $course->level_code,
                'levelSubtitle' => $course->level_subtitle,
                'outcome' => $course->outcome,
                'sessionCount' => (int) $course->session_count,
                'openClassCount' => $course->classRooms()->where('status', 'active')->count(),
                'editHref' => route('admin.courses.edit', $course->id),
                // C1 — giá bán của bậc. null = chưa gắn sản phẩm, 0 = có sản phẩm nhưng chưa điền giá.
                'price' => $course->learningPrice(),
                'priceLabel' => $course->learningPrice() === null
                    ? 'Chưa gắn sản phẩm'
                    : number_format((int) $course->learningPrice()).'đ',
                'productHref' => $course->product_id ? route('admin.products.show', $course->product_id) : null,
                'color' => $tone,
            ];
        })->all();

        return [
            'path' => $path,
            'steps' => $steps,
            'issues' => LearningPathReadiness::check($path),
            // Khoá học chưa nằm trong lộ trình này — để chọn thêm vào.
            'availableCourses' => $this->courses->query()
                ->whereNotIn('id', $attachedIds ?: [0])
                ->orderBy('title')
                ->get(['id', 'title', 'level_code', 'session_count']),
        ];
    }

    public function store(?User $actor, array $data, ?UploadedFile $cover = null, ?UploadedFile $shareImage = null): LearningPath
    {
        $attributes = $this->attributesFrom($data);
        $attributes['created_by'] = $actor?->id;

        if ($cover !== null) {
            $attributes['cover_image_path'] = $cover->store(self::DIR, self::DISK);
        }

        if ($shareImage !== null) {
            $attributes['share_image_path'] = $shareImage->store(self::DIR, self::DISK);
        }
        $attributes['sort_order'] = $data['sort_order'] ?? ($this->paths->maxSortOrder() + 1);
        // Lộ trình mới luôn bắt đầu ở bản nháp — chưa có bậc nào thì không thể đăng được.
        $attributes['status'] = ContentStatus::Draft->value;

        return LearningPath::create($attributes);
    }

    public function update(LearningPath $path, array $data, ?UploadedFile $cover = null, ?UploadedFile $shareImage = null): LearningPath
    {
        $attributes = $this->attributesFrom($data);

        // Tải ảnh mới thì xoá ảnh cũ để không tồn rác trong storage.
        if ($cover !== null) {
            $this->forgetFile($path->cover_image_path);
            $attributes['cover_image_path'] = $cover->store(self::DIR, self::DISK);
        }

        if ($shareImage !== null) {
            $this->forgetFile($path->share_image_path);
            $attributes['share_image_path'] = $shareImage->store(self::DIR, self::DISK);
        }

        // Ô đánh dấu "gỡ ảnh" — cho phép quay về không có ảnh, không phải tải ảnh khác đè lên.
        if (! empty($data['remove_cover'])) {
            $this->forgetFile($path->cover_image_path);
            $attributes['cover_image_path'] = null;
        }

        if (! empty($data['remove_share_image'])) {
            $this->forgetFile($path->share_image_path);
            $attributes['share_image_path'] = null;
        }

        // Ô ngôn ngữ đang tắt nên biểu mẫu không gửi trường này lên — giữ nguyên giá trị cũ
        // thay vì xoá trắng dữ liệu của 5 lộ trình Tin học đã có.
        if (! array_key_exists('language', $data)) {
            unset($attributes['language']);
        }

        $path->update($attributes);

        return $path;
    }

    /**
     * Đổi trạng thái hiển thị. Chỉ cho đăng khi lộ trình đủ điều kiện (A12).
     *
     * @throws ValidationException khi còn lỗi chặn — Controller bắt và trả về đúng màn cũ.
     */
    public function changeStatus(LearningPath $path, ContentStatus $status): LearningPath
    {
        if ($status === ContentStatus::Published) {
            $path->loadMissing('courses');
            $blockers = LearningPathReadiness::blockers($path);

            if ($blockers !== []) {
                throw ValidationException::withMessages([
                    'status' => 'Chưa đăng được: '.implode(' ', $blockers),
                ]);
            }
        }

        $path->update(['status' => $status]);

        return $path;
    }

    /** Thêm một khoá học vào cuối lộ trình. */
    public function attachCourse(LearningPath $path, int $courseId): void
    {
        if ($path->courses()->where('courses.id', $courseId)->exists()) {
            throw ValidationException::withMessages([
                'course_id' => 'Khoá học này đã có trong lộ trình rồi.',
            ]);
        }

        $nextOrder = (int) $path->courses()->max('learning_path_course.sort_order') + 1;

        $path->courses()->attach($courseId, ['sort_order' => $nextOrder]);
    }

    public function detachCourse(LearningPath $path, int $courseId): void
    {
        $path->courses()->detach($courseId);
        $this->renumber($path);
    }

    /**
     * Lưu lại thứ tự bậc sau khi kéo thả.
     *
     * Nhận nguyên mảng id theo thứ tự mới và ghi lại từ đầu — đơn giản và không bao giờ để
     * lại thứ tự trùng hoặc hổng số như cách cộng/trừ từng dòng.
     *
     * @param  list<int>  $orderedCourseIds
     */
    public function reorder(LearningPath $path, array $orderedCourseIds): void
    {
        $valid = $path->courses()->pluck('courses.id')->all();

        DB::transaction(function () use ($path, $orderedCourseIds, $valid) {
            $position = 1;

            foreach ($orderedCourseIds as $courseId) {
                $courseId = (int) $courseId;

                // Bỏ qua id lạ gửi lên từ trình duyệt.
                if (! in_array($courseId, $valid, true)) {
                    continue;
                }

                $path->courses()->updateExistingPivot($courseId, ['sort_order' => $position]);
                $position++;
            }
        });
    }

    public function destroy(?User $actor, LearningPath $path, ?string $reason = null): void
    {
        // Ghi lý do xoá mềm vào nhật ký thao tác, cùng quy ước với Course.
        LearningPath::$auditReason = $reason;
        $path->delete();
        LearningPath::$auditReason = null;
    }

    // ───────────────────────────── nội bộ ─────────────────────────────

    /**
     * Đánh số lại các bậc liên tục từ 1.
     *
     * Gọi sau khi GỠ một bậc: nếu không đánh lại thì thứ tự còn hổng số (1, 2, 4...) — chạy
     * vẫn đúng vì màn hình xếp theo sort_order, nhưng dữ liệu nhìn rất khó hiểu và dễ sai
     * khi sau này có chỗ khác đọc thẳng cột này (ví dụ "Bậc 2/6" ngoài trang công khai).
     *
     * Quan hệ courses() đã sắp sẵn theo learning_path_course.sort_order nên chỉ việc đánh
     * lại từ 1 theo đúng thứ tự đang có.
     */
    private function renumber(LearningPath $path): void
    {
        $ids = $path->courses()->pluck('courses.id')->all();

        DB::transaction(function () use ($path, $ids) {
            $position = 1;

            foreach ($ids as $courseId) {
                $path->courses()->updateExistingPivot((int) $courseId, ['sort_order' => $position]);
                $position++;
            }
        });

        // Bỏ bộ nhớ đệm quan hệ để lần đọc ngay sau đó lấy đúng thứ tự vừa ghi.
        $path->unsetRelation('courses');
    }

    /** Xoá một tệp ảnh cũ nếu còn. Bỏ qua im lặng khi tệp đã biến mất. */
    private function forgetFile(?string $path): void
    {
        if ($path !== null && $path !== '' && Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    private function row(LearningPath $path): array
    {
        $issues = LearningPathReadiness::check($path);
        $status = $path->status ?? ContentStatus::Draft;

        return [
            'id' => $path->id,
            'title' => $path->title,
            'brand' => $path->brand,
            'gradeLabel' => $path->gradeLabel(),
            'language' => $path->languageLabel(),
            'languageTone' => $path->language?->tone() ?? 'neutral',
            'goal' => $path->goal_label,
            'stepCount' => $path->courses->count(),
            'totalSessions' => $path->totalSessions(),
            'totalWeeks' => $path->totalWeeks(),
            'status' => $status->value,
            'statusLabel' => $this->statuses()[$status->value] ?? $status->value,
            'statusTone' => $this->statusTone($status),
            'published' => $status === ContentStatus::Published,
            'coverUrl' => $path->coverUrl(),
            'issueCount' => count($issues),
            'hasBlocker' => ! LearningPathReadiness::canPublish($path),
            'editHref' => route('admin.learning-paths.edit', $path->id),
            'stepsHref' => route('admin.learning-paths.steps', $path->id),
        ];
    }

    private function attributesFrom(array $data): array
    {
        $gradeFrom = (int) $data['grade_from'];
        $gradeTo = (int) $data['grade_to'];

        // Nhập ngược (từ 8 đến 6) thì tự đảo lại thay vì báo lỗi — người nhập không sai ý,
        // chỉ gõ nhầm ô.
        if ($gradeFrom > $gradeTo) {
            [$gradeFrom, $gradeTo] = [$gradeTo, $gradeFrom];
        }

        return [
            'title' => $data['title'],
            'slug' => $this->slugFrom($data),
            'brand' => $data['brand'] ?? null,
            'eyebrow' => $data['eyebrow'] ?? null,
            'subtitle' => $data['subtitle'] ?? null,
            'description' => $data['description'] ?? null,
            'grade_from' => $gradeFrom,
            'grade_to' => $gradeTo,
            'language' => $data['language'] ?? null,
            'goal_label' => $data['goal_label'],
            'sessions_per_week' => (int) ($data['sessions_per_week'] ?? 2),
            'hours_per_session' => (float) ($data['hours_per_session'] ?? 2),
            'outcomes' => $this->outcomesFrom($data['outcomes'] ?? null),
        ];
    }

    private function slugFrom(array $data): string
    {
        $slug = trim((string) ($data['slug'] ?? ''));

        return $slug !== '' ? Str::slug($slug) : Str::slug($data['title']);
    }

    /**
     * Ba dòng kết quả đầu ra nhập trong một ô nhiều dòng — mỗi dòng một ý.
     *
     * @return list<string>
     */
    private function outcomesFrom(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map(fn (string $line) => trim($line), preg_split('/\r\n|\r|\n/', $raw) ?: []),
            fn (string $line) => $line !== '',
        ));
    }

    /** @return array<string, string> */
    private function statuses(): array
    {
        return [
            ContentStatus::Draft->value => 'Bản nháp',
            ContentStatus::Published->value => 'Đang hiển thị',
            ContentStatus::Archived->value => 'Lưu trữ',
        ];
    }

    private function statusTone(ContentStatus $status): string
    {
        return match ($status) {
            ContentStatus::Published => 'success',
            ContentStatus::Archived => 'neutral',
            default => 'warning',
        };
    }
}
