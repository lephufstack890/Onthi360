<?php

namespace App\Services\Public;

use App\Enums\ReviewTargetType;
use App\Models\Course;
use App\Models\Role;
use App\Models\User;
use App\Models\AccessRight;
use App\Repositories\Contracts\AccessRightRepositoryInterface;
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
        // B6 (15/9) — dải "Bậc 2/6 của lộ trình ..." ở đầu trang chi tiết khoá học.
        private LearningPathService $learningPaths,
        // SỬA 15/9 — nút trên thẻ khoá phải biết người xem ĐÃ CÓ QUYỀN học khoá đó chưa.
        private AccessRightRepositoryInterface $accessRights,
    ) {}

    /** courses.index — danh mục khóa học công khai đã phát hành, lọc theo môn (?subject=) tùy chọn. */
    public function indexData(?string $subject, ?User $viewer = null, ?int $learningPathId = null): array
    {
        // SỬA 11/9 — thẻ lớp học ở giao diện mới (education-main/src/components/CoursesPage.jsx)
        // cần thêm: mã lớp, sĩ số, giáo viên phụ trách và trợ giảng. Nạp sẵn trong CÙNG 1 câu
        // truy vấn quan hệ (withCount + with) để không sinh N+1 khi danh mục có nhiều khóa.
        $query = $this->courses->query()
            ->where('status', 'published')
            ->with(['classRooms' => fn ($q) => $q->where('status', 'active')
                ->select('id', 'course_id', 'code', 'name')
                ->withCount('students')
                ->with(['teachers:id,name'])]);

        // C1 — giá bán nằm ở sản phẩm gắn với khoá. Nạp sẵn để không sinh N+1 khi vẽ nút.
        if (Course::supportsProduct()) {
            $query->with('product:id,price');
        }

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

        // "Vào học" chỉ hiện khi HỌC SINH ĐANG XEM thật sự còn ghi danh active ở 1 lớp của khóa
        // — cùng luật với showData() bên dưới, không suy ra từ mỗi auth()->check().
        $myClassRoomIds = ($viewer !== null && $viewer->hasRole(Role::STUDENT))
            ? $this->classEnrollments->query()
                ->where('student_id', $viewer->id)
                ->where('status', 'active')
                ->pluck('class_room_id')
                ->all()
            : [];

        /*
         * Các sản phẩm người xem ĐANG CÒN QUYỀN. Lấy một lần cho cả trang rồi so trong bộ
         * nhớ — hỏi từng khoá một thì mỗi thẻ là một truy vấn.
         */
        $myActiveProductIds = $viewer !== null
            ? $this->accessRights->forUserWithProduct($viewer->id)
                ->filter(fn (AccessRight $ar) => $ar->isCurrentlyActive())
                ->pluck('product_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->all()
            : [];

        return [
            'courses' => $courses->map(fn (Course $c) => $this->mapCourseCard($c, $ratingsByClassRoomId, $myClassRoomIds, $myActiveProductIds))->all(),
            // Đếm cho đúng: trang liệt kê KHOÁ HỌC, mỗi khoá có nhiều lớp.
            'totalOpenClasses' => $courses->sum(fn (Course $c) => $c->classRooms->count()),
            'subjects' => $subjects,
            'activeSubject' => $subject,
            /*
             * SỬA 15/9 — dải lọc "Khối lớp" giờ SINH TỪ LỘ TRÌNH, không từ cột courses.grade.
             * Trang này liệt kê lộ trình, nên khối nào không có lộ trình thì đừng mời người ta
             * bấm vào; ngược lại lộ trình "Khối 6–8" phải ra đủ Lớp 6, 7, 8 kể cả khi không
             * khoá nào ghi "Lớp 7".
             * Xem App\Services\Public\LearningPathService::gradeOptions().
             */
            'grades' => $this->learningPaths->gradeOptions(),
            /*
             * B7 (15/9, khách nói thẳng "người ta chọn lớp theo lộ trình") — bộ lọc theo lộ
             * trình bên cạnh lọc môn và lọc khối đang có.
             *
             * Trả về từng lộ trình kèm DANH SÁCH ID KHOÁ HỌC của nó, để trang lọc ngay tại
             * chỗ bằng id khoá — người dùng đổi lộ trình không phải tải lại trang. Lộ trình
             * chưa xếp bậc nào bị loại sẵn trong service, không để lọc ra danh sách rỗng.
             */
            'learningPathFilters' => $this->learningPaths->filterOptions(),
            // Lộ trình chọn sẵn từ ?lo-trinh= trên đường dẫn (null = không lọc).
            'activeLearningPath' => $learningPathId,
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
            /*
             * B6 — khoá này là bậc mấy của lộ trình nào.
             * Là DANH SÁCH chứ không phải một, vì một khoá dùng lại được ở nhiều lộ trình
             * (xem migration create_learning_path_course_table). Trang in dải đầu tiên và
             * nêu số lộ trình còn lại.
             */
            'pathStrips' => $this->learningPaths->stripsForCourse($course),
            /*
             * C2 — nút mua khoá học. Null khi khoá chưa gắn sản phẩm hoặc sản phẩm chưa có
             * giá: lúc đó trang giữ nguyên lối vào bằng mã lớp như trước.
             */
            'buyHref' => $course->isPurchasable() ? route('access.checkout', $course->product_id) : null,
            'priceLabel' => $course->isPurchasable() ? number_format((int) $course->learningPrice()).'đ' : null,
            'chooseClassHref' => route('access.chooseClass', $course->id),
        ];
    }

    /**
     * Một thẻ trên trang /khoa-hoc.
     *
     * ── SỬA 15/9: trang này liệt kê KHOÁ HỌC, không phải lớp ──
     * Cấu trúc hệ thống là Lộ trình → Khoá học → Lớp học. Mỗi thẻ ở đây là một KHOÁ, bên
     * trong có nhiều lớp. Bản cũ lấy lớp ĐẦU TIÊN rồi in "Mã lớp · X" và tên giáo viên của
     * riêng lớp đó như thể là của cả khoá — khoá có ba lớp thì hai lớp còn lại biến mất, còn
     * người đọc thì tưởng khoá chỉ có một lớp và ghi nhầm mã. Giờ trả về DANH SÁCH lớp đang
     * mở để giao diện liệt kê đủ.
     *
     * ── Nút bấm bám đúng khối C (bán khoá học) ──
     * Trước chỉ có ba trạng thái đoán từ việc đã ghi danh hay chưa. Giờ đã có sản phẩm, giá
     * và màn tự chọn lớp nên nút phải nói đúng việc tiếp theo của từng người:
     *   đã ở trong lớp        -> Vào học
     *   có quyền, còn lớp mở  -> Chọn lớp   (không bắt mua lại)
     *   có quyền, chưa có lớp -> báo chưa mở lớp
     *   chưa có quyền, bán được -> Đăng ký kèm giá
     *   chưa gắn sản phẩm     -> Xem khoá học (vào bằng mã lớp như trước)
     *
     * @param  list<int>  $myClassRoomIds     lớp người xem đang học
     * @param  list<int>  $myActiveProductIds sản phẩm người xem còn quyền
     */
    private function mapCourseCard(
        Course $course,
        Collection $ratingsByClassRoomId,
        array $myClassRoomIds = [],
        array $myActiveProductIds = [],
    ): array {
        $classRoomIds = $course->classRooms->pluck('id')->all();
        [$average, $count] = $this->aggregate($classRoomIds, $ratingsByClassRoomId);

        $classCount = count($classRoomIds);
        $studentCount = (int) $course->classRooms->sum('students_count');

        $metaParts = array_filter([
            $classCount > 0 ? $classCount.' lớp đang mở' : 'Chưa có lớp mở',
            $course->subject,
            $course->grade,
        ]);

        // Danh sách lớp đang mở — đủ cả, không chỉ lớp đầu tiên.
        $classes = $course->classRooms->map(fn ($classRoom) => [
            'id' => $classRoom->id,
            'code' => $classRoom->code,
            'name' => $classRoom->name,
            'studentsCount' => (int) $classRoom->students_count,
            'teachers' => $classRoom->teachers->pluck('name')->values()->all(),
            'isMember' => in_array($classRoom->id, $myClassRoomIds, true),
        ])->values()->all();

        // Giáo viên của KHOÁ = gộp giáo viên của mọi lớp, bỏ trùng.
        $teacherNames = $course->classRooms
            ->flatMap(fn ($classRoom) => $classRoom->teachers->pluck('name'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $myEnrolledInThisCourse = array_values(array_intersect($classRoomIds, $myClassRoomIds));
        $hasRight = $course->product_id !== null && in_array((int) $course->product_id, $myActiveProductIds, true);

        /*
         * Một chỗ duy nhất quyết định nút: nhãn, đường dẫn và kiểu hiển thị đi cùng nhau.
         * Tách ra ba chỗ thì sớm muộn nhãn nói một đằng, link dẫn một nẻo.
         */
        $cta = match (true) {
            // Đã học rồi thì vào thẳng lớp; học nhiều lớp cùng khoá thì để tự chọn.
            count($myEnrolledInThisCourse) === 1 => [
                'label' => 'Vào học',
                'href' => route('student.classes.show', $myEnrolledInThisCourse[0]),
                'tone' => 'go',
            ],
            count($myEnrolledInThisCourse) > 1 => [
                'label' => 'Vào học',
                'href' => route('student.courses.index'),
                'tone' => 'go',
            ],
            // Đã mua rồi: việc tiếp theo là chọn lớp, KHÔNG phải mua lại.
            $hasRight && $classCount > 0 => [
                'label' => 'Chọn lớp',
                'href' => route('access.chooseClass', $course->id),
                'tone' => 'go',
            ],
            $hasRight => [
                'label' => 'Chờ mở lớp',
                'href' => route('courses.show', $course->id),
                'tone' => 'muted',
            ],
            $classCount === 0 => [
                'label' => 'Chưa mở lớp',
                'href' => route('courses.show', $course->id),
                'tone' => 'muted',
            ],
            $course->isPurchasable() => [
                'label' => 'Đăng ký · '.number_format((int) $course->learningPrice()).'đ',
                'href' => route('access.checkout', $course->product_id),
                'tone' => 'buy',
            ],
            default => [
                'label' => 'Xem khoá học',
                'href' => route('courses.show', $course->id),
                'tone' => 'primary',
            ],
        };

        return [
            'id' => $course->id,
            'title' => $course->title,
            'meta' => implode(' · ', $metaParts),
            'average' => $average,
            'count' => $count,
            'subtitle' => $course->description,
            'subject' => $course->subject,
            'grade' => $course->grade,
            'image' => $course->cover_image_path ? asset('storage/'.$course->cover_image_path) : null,
            'classCount' => $classCount,
            'classes' => $classes,
            'studentCount' => $studentCount,
            'teacherNames' => $teacherNames,
            // Số buổi theo chương trình — con số thật, thay hai dòng "Trực tuyến" và
            // "Có chấm bài tự động" viết cứng trong bản cũ (mọi thẻ đều giống hệt nhau).
            'sessionCount' => (int) $course->session_count,
            'priceLabel' => $course->isPurchasable() ? number_format((int) $course->learningPrice()).'đ' : null,
            'hasRight' => $hasRight,
            'href' => route('courses.show', $course->id),
            'cta' => $cta,
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
