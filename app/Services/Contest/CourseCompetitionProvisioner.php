<?php

namespace App\Services\Contest;

use App\Enums\CompetitionOrganizerType;
use App\Enums\CompetitionStatus;
use App\Enums\CompetitionType;
use App\Models\Competition;
use App\Models\Course;
use App\Support\CompetitionNaming;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * SỬA 25/10 (khách: "thêm khoá học (lộ trình) thì khảo sát và cuộc thi tự sinh ra luôn; thời gian bắt đầu / kết thúc tự
 * đặt, kỳ thi tự thêm thủ công; dữ liệu cũ thì seed cho đầy đủ") — mỗi khoá học có đúng:
 *
 *   1 CUỘC THI  "Cuộc thi đánh giá năng lực · <tên khoá>"   (type = contest)
 *   1 KHẢO SÁT  "Khảo sát năng lực · <tên khoá>"            (type = survey, có sẵn mật khẩu ngẫu nhiên 6 số)
 *
 * gắn competitions.course_id. Chưa có kỳ thi, chưa đặt thời gian — admin / giáo viên tự thêm kỳ trong phòng thi.
 * Gọi ở 2 nơi: App\Observers\CourseCompetitionObserver (khoá học MỚI) và CourseCompetitionSeeder (khoá học có sẵn).
 * Chạy lại bao nhiêu lần cũng được: khoá đã có cuộc thi / khảo sát cùng loại thì bỏ qua (kể cả đã lưu trữ).
 */
class CourseCompetitionProvisioner
{
    /** Ảnh bìa mặc định (catalog App\Support\CompetitionCover). */
    private const COVER = ['contest' => 'catalog:cup', 'survey' => 'catalog:survey'];

    /** Đã chạy migration course_id chưa (chưa thì không làm gì — tránh lỗi khi deploy dở). */
    public function ready(): bool
    {
        return Competition::supportsCourse();
    }

    /**
     * Tạo phần còn thiếu cho 1 khoá học.
     *
     * @return list<Competition> các cuộc thi / khảo sát VỪA tạo (rỗng nếu đã đủ)
     */
    public function ensureFor(Course $course): array
    {
        if (! $this->ready() || $course->trashed()) {
            return [];
        }

        $existing = Competition::query()->where('course_id', $course->id)->pluck('type')
            ->map(fn ($t) => $t instanceof CompetitionType ? $t->value : (string) $t)->all();

        $created = [];
        foreach ([CompetitionType::Contest, CompetitionType::Survey] as $type) {
            if (! in_array($type->value, $existing, true)) {
                $created[] = $this->create($course, $type);
            }
        }

        return $created;
    }

    /** Mọi khoá học hiện có (dùng cho seeder). @return array{courses:int, created:int} */
    public function ensureAll(): array
    {
        if (! $this->ready()) {
            return ['courses' => 0, 'created' => 0];
        }

        $courses = 0;
        $created = 0;
        Course::query()->orderBy('id')->chunkById(100, function ($chunk) use (&$courses, &$created) {
            foreach ($chunk as $course) {
                $courses++;
                $created += count($this->ensureFor($course));
            }
        });

        return ['courses' => $courses, 'created' => $created];
    }

    /** Gọi từ observer: lỗi ở đây KHÔNG được làm hỏng việc tạo khoá học — chỉ ghi log. */
    public function ensureQuietly(Course $course): void
    {
        try {
            $this->ensureFor($course);
        } catch (\Throwable $e) {
            Log::warning('Không tự sinh được cuộc thi / khảo sát cho khoá học', ['course_id' => $course->id, 'error' => $e->getMessage()]);
        }
    }

    private function create(Course $course, CompetitionType $type): Competition
    {
        $title = CompetitionNaming::forCourse($type, $course->title) ?? CompetitionNaming::prefix($type);

        return DB::transaction(function () use ($course, $type, $title) {
            $attrs = [
                'title' => Str::limit($title, 250, ''),
                'slug' => $this->uniqueSlug($title),
                'type' => $type->value,
                'organizer_type' => CompetitionOrganizerType::Internal->value,
                'course_id' => $course->id,
                'status' => CompetitionStatus::Upcoming->value, // chưa đặt thời gian → Sắp diễn ra
            ];
            if (Competition::supportsHubFields()) {
                $attrs['image_path'] = self::COVER[$type->value];
                $attrs['description'] = $type === CompetitionType::Survey
                    ? 'Hiểu năng lực hiện tại, xác định kiến thức cần củng cố và theo dõi tiến bộ qua từng lượt làm bài.'
                    : 'Thử sức với các kỳ thi, rèn tư duy giải quyết vấn đề và tranh tài cùng các bạn.';
                if ($type === CompetitionType::Survey) {
                    // Khảo sát luôn cần mật khẩu — đặt sẵn 6 số ngẫu nhiên, admin xem / đổi ở trang quản trị cuộc thi.
                    $attrs['entry_password'] = (string) random_int(100000, 999999);
                }
            }

            return Competition::create($attrs);
        });
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'cuoc-thi';
        $slug = $base;
        $n = 2;
        while (Competition::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }
}
