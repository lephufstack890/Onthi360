<?php

namespace App\Services\Public;

use App\Enums\ReviewTargetType;
use App\Models\Course;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\ClassEnrollmentRepositoryInterface;
use App\Repositories\Contracts\CourseRepositoryInterface;
use App\Repositories\Contracts\RatingSummaryRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Danh mục Khóa học công khai (PUB-03/04, 4.1 "Khóa học = khám phá chương trình" + 8.1
 * "Tách rõ khóa và lớp"). Chỉ hiển thị khóa đã phát hành (status=published); rating hiển
 * thị là TỔNG HỢP rating các lớp thuộc khóa — Course không có review target riêng (9.1 chỉ
 * định nghĩa 4 loại: material/class_room/teacher/competition, không có "course").
 *
 * courses.show trước đây có bug thật: nút CTA chỉ kiểm tra auth()->check() — bất kỳ ai ĐÃ
 * đăng nhập (kể cả học sinh CHƯA từng tham gia lớp nào của khóa này) đều thấy "Xem lớp học
 * của tôi" trỏ thẳng vào /dashboard, không hề kiểm tra học sinh đó có thực sự đã ở trong 1
 * lớp thuộc khóa này hay chưa. Sửa lại: học sinh CHỈ thấy "Xem lớp học của tôi" khi có ít
 * nhất 1 ClassEnrollment còn active ở 1 lớp thuộc khóa này; ngược lại thấy CTA "Nhập mã lớp
 * để tham gia" (join-by-code — xem App\Services\Student\ClassRoomService::joinByCode()).
 * Vai trò khác (giáo viên/phụ huynh/admin) giữ nguyên hành vi cũ (trỏ /dashboard) — ngoài
 * phạm vi bug được báo cáo.
 */
class CourseService
{
    public function __construct(
        private CourseRepositoryInterface $courses,
        private RatingSummaryRepositoryInterface $ratingSummaries,
        private ClassEnrollmentRepositoryInterface $classEnrollments,
    ) {}

    /** courses.index — danh mục khóa học công khai đã phát hành, lọc theo môn (?subject=) tùy chọn. */
    public function indexData(?string $subject): array
    {
        $query = $this->courses->query()
            ->where('status', 'published')
            ->with(['classRooms' => fn ($q) => $q->where('status', 'active')->select('id', 'course_id')]);

        if (filled($subject)) {
            $query->where('subject', $subject);
        }

        $courses = $query->latest()->limit(60)->get();

        // 1 câu truy vấn duy nhất cho rating của TẤT CẢ lớp thuộc mọi khóa trong trang này —
        // tránh N+1 (mỗi khóa 1 câu) khi có nhiều khóa học công khai.
        $allClassRoomIds = $courses->flatMap(fn ($c) => $c->classRooms->pluck('id'))->unique()->values()->all();
        $ratingsByClassRoomId = $this->ratingSummariesByClassRoomId($allClassRoomIds);

        $subjects = $this->courses->query()
            ->where('status', 'published')
            ->whereNotNull('subject')
            ->distinct()
            ->orderBy('subject')
            ->pluck('subject')
            ->all();

        return [
            'courses' => $courses->map(fn (Course $c) => $this->mapCourseCard($c, $ratingsByClassRoomId))->all(),
            'subjects' => $subjects,
            'activeSubject' => $subject,
        ];
    }

    /**
     * courses.show — chi tiết 1 khóa học + các lớp đang triển khai (8.1: khóa ≠ lớp).
     * $user null nếu khách chưa đăng nhập (trang này công khai, ai cũng xem được).
     */
    public function showData(int $courseId, ?User $user): array
    {
        $course = $this->courses->query()
            ->where('status', 'published')
            ->with(['classRooms' => fn ($q) => $q->where('status', 'active')->withCount('students')->with('teachers')])
            ->findOrFail($courseId);

        $classRoomIds = $course->classRooms->pluck('id')->all();
        $ratingsByClassRoomId = $this->ratingSummariesByClassRoomId($classRoomIds);
        [$average, $count] = $this->aggregate($classRoomIds, $ratingsByClassRoomId);

        // Chỉ tính "đã tham gia lớp nào của khóa này chưa" cho ĐÚNG vai trò học sinh — quan hệ
        // ClassEnrollment (bảng class_enrollments) chỉ có ý nghĩa với student_id, không áp
        // dụng cho giáo viên/phụ huynh/admin xem trang này.
        $isStudent = $user !== null && $user->hasRole(Role::STUDENT);
        $myEnrolledClassRoomIds = $isStudent
            ? $this->classEnrollments->activeClassRoomIdsForUser($user->id)
            : [];
        $myClassRoomIdsInThisCourse = array_values(array_intersect($classRoomIds, $myEnrolledClassRoomIds));

        $classes = $course->classRooms->map(fn ($classRoom) => [
            'id' => $classRoom->id,
            'name' => $classRoom->name,
            'teacher' => $classRoom->teachers->first()->name ?? 'Chưa phân công',
            'studentsCount' => $classRoom->students_count,
            'isMember' => in_array($classRoom->id, $myEnrolledClassRoomIds, true),
        ])->values()->all();

        return [
            'course' => $course,
            'classes' => $classes,
            'ratingAverage' => $average,
            'ratingCount' => $count,
            'isStudent' => $isStudent,
            'myClassRoomIdsInThisCourse' => $myClassRoomIdsInThisCourse,
        ];
    }

    private function mapCourseCard(Course $course, Collection $ratingsByClassRoomId): array
    {
        $classRoomIds = $course->classRooms->pluck('id')->all();
        [$average, $count] = $this->aggregate($classRoomIds, $ratingsByClassRoomId);

        $metaParts = array_filter([
            count($classRoomIds) > 0 ? count($classRoomIds).' lớp đang triển khai' : 'Chưa có lớp triển khai',
            $course->subject,
            $course->grade,
        ]);

        return [
            'id' => $course->id,
            'title' => $course->title,
            'meta' => implode(' · ', $metaParts),
            'average' => $average,
            'count' => $count,
        ];
    }

    /** @return Collection<int, \App\Models\RatingSummary> keyed theo class_room id. */
    private function ratingSummariesByClassRoomId(array $classRoomIds): Collection
    {
        if ($classRoomIds === []) {
            return collect();
        }

        return $this->ratingSummaries->query()
            ->where('target_type', ReviewTargetType::ClassRoom)
            ->whereIn('target_id', $classRoomIds)
            ->get()
            ->keyBy('target_id');
    }

    /**
     * Course không có rating riêng (9.1) — tổng hợp trung bình có trọng số từ RatingSummary
     * của các lớp (class_room) thuộc khóa, đúng tinh thần 4.1 "rating khóa/lớp nếu công khai".
     *
     * @param  array<int, int>  $classRoomIds
     * @return array{0: ?float, 1: int}
     */
    private function aggregate(array $classRoomIds, Collection $ratingsByClassRoomId): array
    {
        $summaries = collect($classRoomIds)->map(fn ($id) => $ratingsByClassRoomId->get($id))->filter();

        $totalCount = (int) $summaries->sum('review_count');

        if ($totalCount === 0) {
            return [null, 0];
        }

        $weightedSum = $summaries->sum(fn ($s) => $s->avg_rating * $s->review_count);

        return [round($weightedSum / $totalCount, 1), $totalCount];
    }
}
