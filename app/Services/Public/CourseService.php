<?php

namespace App\Services\Public;

use App\Enums\ReviewTargetType;
use App\Models\ClassRoom;
use App\Models\Course;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\ClassEnrollmentRepositoryInterface;
use App\Repositories\Contracts\ClassRoomRepositoryInterface;
use App\Repositories\Contracts\CourseRepositoryInterface;
use App\Repositories\Contracts\RatingSummaryRepositoryInterface;
use Carbon\Carbon;
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
    /*
     * ══════ HAI CÔNG TẮC KHỐI Ở TRANG CHI TIẾT KHOÁ HỌC ══════
     *
     * SỬA 15/9 (khách: "Các lớp đang triển khai ẩn nha, này cũng ẩn Có mã lớp?") — ẩn 2 khối
     * cuối trang /khoa-hoc/{id}. ẨN CHỨ KHÔNG XOÁ: đổi thành true là hiện lại nguyên vẹn.
     *
     * ── LƯU Ý KHI TẮT/BẬT ──
     * Nút chính ở bảng thông tin đầu trang NEO XUỐNG đúng 2 khối này (#lop-dang-mo và
     * #tham-gia-lop). Tắt khối mà quên nút thì nút bấm vào không nhảy đi đâu cả — người dùng
     * tưởng trang hỏng. Vì vậy view tự đổi đích của nút theo 2 hằng này, xem khối tính
     * $primaryCta trong public/courses/show.blade.php.
     *
     * Ẩn khối "Có mã lớp?" cũng ẩn theo ô nhập mã lớp VÀ dải "Chọn lớp" của luồng mua khoá.
     * Học sinh vẫn nhập được mã ở khu học sinh (student/courses/index) nên không mất đường nào.
     */
    public const SHOW_CLASS_LIST = false;

    public const SHOW_JOIN_BY_CODE = false;

    public function __construct(
        private CourseRepositoryInterface $courses,
        private RatingSummaryRepositoryInterface $ratingSummaries,
        private ClassEnrollmentRepositoryInterface $classEnrollments,
        // SỬA 16/9 — trang Lớp học công khai giờ liệt kê THẲNG từng lớp, không còn gộp theo khoá.
        private ClassRoomRepositoryInterface $classRooms,
    ) {}

    /** courses.index — danh mục khóa học công khai đã phát hành, lọc theo môn (?subject=) tùy chọn. */
    /**
     * PUB-03 — trang /khoa-hoc: MỖI THẺ LÀ MỘT LỚP HỌC.
     *
     * SỬA 16/9 (khách: "trang lớp học ngoài public hiển thị dữ liệu lớp học mới đúng") — trước
     * đây trang mang tên "Lớp học" nhưng mỗi thẻ lại là một KHOÁ HỌC, và chỉ lấy đúng lớp ĐẦU
     * TIÊN của khoá để in mã lớp. Khoá có 3 lớp thì 2 lớp kia coi như không tồn tại với người
     * đang tìm lớp để đăng ký. Giờ liệt kê thẳng từng lớp đang mở.
     *
     * Hai bộ lọc, cả hai đều ĐỔ TỪ KHOÁ HỌC:
     *   · Khoá học — chọn khoá thì chỉ còn lớp thuộc khoá đó;
     *   · Khối lớp — khối của KHOÁ chứa lớp (bảng class_rooms không có cột khối riêng).
     * Danh sách lựa chọn chỉ gồm khoá/khối THẬT SỰ CÒN LỚP ĐANG MỞ, nên không bao giờ bấm vào
     * một lựa chọn rồi ra 0 kết quả.
     *
     * @return array{classes: array<int, array<string, mixed>>, courseFilters: array<int, array<string, mixed>>, grades: array<int, string>, totalClasses: int, totalStudents: int}
     */
    public function classIndexData(?User $viewer = null, ?int $preselectCourseId = null): array
    {
        $rows = $this->classRooms->query()
            ->where('class_rooms.status', 'active')
            ->whereHas('course', fn ($q) => $q->where('status', 'published'))
            ->with([
                'course:id,title,slug,subject,grade,cover_image_path,description,level_code,product_id',
                'teachers:id,name',
            ])
            ->withCount(['students', 'sessions'])
            ->orderBy('course_id')
            ->orderBy('name')
            ->limit(120)
            ->get();

        // 1 câu truy vấn cho đánh giá của TẤT CẢ lớp trong trang — tránh N+1.
        $ratings = $this->ratingSummariesByClassRoomId($rows->pluck('id')->all());

        // Lớp mà HỌC SINH ĐANG XEM còn ghi danh active — để thẻ đổi sang "Vào học".
        $myClassRoomIds = ($viewer !== null && $viewer->hasRole(Role::STUDENT))
            ? $this->classEnrollments->query()
                ->where('student_id', $viewer->id)
                ->where('status', 'active')
                ->pluck('class_room_id')
                ->all()
            : [];

        // SỬA 16/9 (khách yêu cầu: "bấm đăng ký học thì giáo viên duyệt") — lớp học sinh này
        // ĐANG CHỜ DUYỆT, để thẻ hiện "Đang chờ duyệt" thay vì mời bấm đăng ký lần nữa.
        $pendingClassRoomIds = ($viewer !== null && $viewer->hasRole(Role::STUDENT))
            ? $this->classEnrollments->pendingClassRoomIdsForUser($viewer->id)
            : [];

        $classes = $rows->map(fn (ClassRoom $c) => $this->mapClassCard($c, $ratings, $myClassRoomIds, $pendingClassRoomIds))->values()->all();

        // Bộ lọc dựng TỪ CHÍNH các lớp đang hiển thị, không truy vấn lại bảng courses: như vậy
        // lựa chọn nào hiện ra cũng chắc chắn có lớp đứng sau.
        $courseFilters = $rows
            ->groupBy('course_id')
            ->map(fn ($group) => [
                'id' => (int) $group->first()->course_id,
                'title' => (string) ($group->first()->course->title ?? ''),
                'grade' => (string) ($group->first()->course->grade ?? ''),
                'classCount' => $group->count(),
            ])
            ->sortBy('title')
            ->values()
            ->all();

        $grades = collect($courseFilters)
            ->pluck('grade')
            ->filter(fn (string $g) => $g !== '')
            ->unique()
            // Xếp theo SỐ chứ không theo chữ — theo chữ thì "Lớp 10" đứng trước "Lớp 6".
            ->sortBy(fn (string $g) => ((int) preg_replace('/\D/', '', $g)) ?: 99)
            ->values()
            ->all();

        /*
         * SỬA 16/9 (khách: "ngoài trang chủ bấm Xem lộ trình thì vào trang lớp học hiển thị
         * đúng lớp học của lộ trình đó") — ?khoa=<id> chọn sẵn bộ lọc khoá ngay khi mở trang.
         * Cần tham số trên ĐƯỜNG DẪN chứ không chỉ bấm tại chỗ: người ta còn gửi link đó cho
         * nhau, link phải mở ra đúng danh sách đã lọc sẵn.
         * Mã khoá lạ (không còn lớp nào đang mở) -> bỏ qua, hiện tất cả, KHÔNG để trang trắng.
         */
        $activeCourseId = ($preselectCourseId !== null
            && collect($courseFilters)->contains('id', $preselectCourseId))
            ? $preselectCourseId
            : null;

        return [
            'classes' => $classes,
            'courseFilters' => $courseFilters,
            'activeCourseId' => $activeCourseId,
            'grades' => $grades,
            'totalClasses' => count($classes),
            'totalStudents' => (int) $rows->sum('students_count'),
            // Chỉ tài khoản HỌC SINH mới gửi được yêu cầu (route student.classes.requestJoin nằm
            // trong nhóm role:student). Khách chưa đăng nhập -> mời đăng nhập; giáo viên/quản trị
            // -> không hiện nút để khỏi bấm vào rồi ăn 403.
            'canRequestJoin' => $viewer !== null && $viewer->hasRole(Role::STUDENT),
            'isGuest' => $viewer === null,
        ];
    }

    /** Một thẻ lớp học trên trang /khoa-hoc. Mọi số liệu lấy từ CSDL, không có giá trị viết cứng. */
    private function mapClassCard(ClassRoom $classRoom, Collection $ratings, array $myClassRoomIds, array $pendingClassRoomIds = []): array
    {
        $course = $classRoom->course;
        $summary = $ratings->get($classRoom->id);
        $teachers = $classRoom->teachers;

        return [
            'id' => $classRoom->id,
            'name' => $classRoom->name,
            'code' => $classRoom->code,
            // Lịch học là ghi chú tự do quản trị nhập (class_rooms.schedule = {"note": "..."}).
            'scheduleNote' => $classRoom->schedule['note'] ?? null,
            'studentsCount' => (int) $classRoom->students_count,
            'sessionsCount' => (int) $classRoom->sessions_count,
            'teacherName' => $teachers->first()?->name,
            'assistantNames' => $teachers->skip(1)->pluck('name')->values()->all(),
            'average' => ($summary !== null && (int) $summary->review_count > 0)
                ? round((float) $summary->avg_rating, 1)
                : null,
            'count' => (int) ($summary?->review_count ?? 0),
            'isMember' => in_array($classRoom->id, $myClassRoomIds, true),
            // SỬA 16/9 — đã gửi yêu cầu, đang chờ giáo viên duyệt.
            'isPending' => in_array($classRoom->id, $pendingClassRoomIds, true),
            // Thông tin thừa hưởng từ khoá — lớp không có ảnh/môn/khối riêng.
            // 4 trường mô tả lớp — chỉ có khi máy chủ đã chạy migration
            // add_display_fields_to_class_rooms_table (xem ClassRoom::supportsDisplayFields).
            'location' => ClassRoom::supportsDisplayFields() ? $classRoom->location : null,
            'address' => ClassRoom::supportsDisplayFields() ? $classRoom->address : null,
            'format' => ClassRoom::supportsDisplayFields() ? $classRoom->format : null,
            'capacity' => ClassRoom::supportsDisplayFields() ? $classRoom->capacity : null,
            'courseId' => (int) $classRoom->course_id,
            'courseTitle' => (string) ($course->title ?? ''),
            // Viên nhãn góc ảnh: nhãn khoá quản trị đặt, không có thì lấy môn học.
            'tag' => (string) ($course?->level_code ?: ($course?->subject ?? '')),
            // Câu mô tả ngắn dưới tên lớp — lấy chữ thật từ mô tả khoá, cắt gọn.
            'subtitle' => \Illuminate\Support\Str::limit(trim(strip_tags((string) ($course?->description ?? ''))), 120),
            // Học phí: chỉ có khi khoá đã gắn sản phẩm VÀ sản phẩm đã điền giá.
            'priceLabel' => $course !== null && $course->isPurchasable()
                ? number_format((int) $course->learningPrice()).'đ'
                : null,
            'subject' => (string) ($course->subject ?? ''),
            'grade' => (string) ($course->grade ?? ''),
            'image' => $course?->coverUrl(),
            'href' => route('courses.show', $classRoom->course_id),
        ];
    }

    public function indexData(?string $subject, ?User $viewer = null): array
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

        return [
            'courses' => $courses->map(fn (Course $c) => $this->mapCourseCard($c, $ratingsByClassRoomId, $myClassRoomIds))->all(),
            'subjects' => $subjects,
            'activeSubject' => $subject,
            // Khối lớp có thật trong dữ liệu — dải lọc "Khối lớp" của giao diện mới dựng từ đây,
            // không phải danh sách cứng, nên không bao giờ lọc ra 0 kết quả một cách vô nghĩa.
            'grades' => $this->courses->query()
                ->where('status', 'published')
                ->whereNotNull('grade')
                ->distinct()
                ->orderBy('grade')
                ->pluck('grade')
                ->all(),
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
            ->with(['classRooms' => fn ($q) => $q->where('status', 'active')
                ->withCount(['students', 'sessions'])
                ->withMin('sessions', 'starts_at')
                ->withMax('sessions', 'ends_at')
                ->with('teachers')])
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
            // SỬA 15/9 — số liệu cho bảng thông tin ở đầu trang chi tiết khoá học (bản dựng
            // theo mẫu khách gửi). Gom ở service thay vì tính trong view để trang chỉ còn việc
            // in ra, và để chỗ tính duy nhất một nơi nếu sau này đổi cách đếm.
            ...$this->headlineFigures($course),
            'ratingAverage' => $average,
            'ratingCount' => $count,
            'isStudent' => $isStudent,
            'myClassRoomIdsInThisCourse' => $myClassRoomIdsInThisCourse,
            /*
             * KHỐI C (mua khoá học) — GIỮ LẠI khi gỡ lộ trình công khai ngày 15/9.
             *
             * Phần này không dính gì tới lộ trình: nó chỉ trả lời "khoá này có bán không, mua
             * xong chọn lớp ở đâu" — đúng thứ khách muốn giữ ("trong khoá học có các lớp học").
             *
             * 'buyHref' null khi khoá chưa gắn sản phẩm HOẶC sản phẩm chưa điền giá. Lúc đó
             * trang chi tiết khoá tự quay về lối vào bằng mã lớp y như trước, không hiện nút
             * mua nào — xem public/courses/show.blade.php.
             */
            'buyHref' => $course->isPurchasable() ? route('access.checkout', $course->product_id) : null,
            'priceLabel' => $course->isPurchasable() ? number_format((int) $course->learningPrice()).'đ' : null,
            'chooseClassHref' => route('access.chooseClass', $course->id),
        ];
    }

    /**
     * Dữ liệu cho khối [HOME-04] "Chọn mục tiêu hoặc lộ trình của bạn" ở trang chủ.
     *
     * SỬA 15/9 (khách: "ngoài trang chủ ... đổ dữ liệu các khoá học ra, click Xem lộ trình thì
     * hiển thị ra màn đó") — hai nút bấm-để-đổi giờ sinh từ KHOÁ HỌC THẬT, và nút vàng dẫn
     * thẳng sang trang chi tiết đúng khoá đang chọn.
     *
     * Nạp cả danh sách một lần rồi lọc tại trình duyệt (xem partials/home-script): số khoá
     * công khai chỉ vài chục, đổi lựa chọn mà phải tải lại trang thì mất hẳn cảm giác mượt.
     *
     * 'goal' ưu tiên câu kết quả quản trị đặt cho khoá (courses.outcome) vì đó đúng là "mục
     * tiêu"; khoá chưa đặt thì lấy tên khoá, KHÔNG bịa ra câu mục tiêu nào.
     *
     * @return array{grades: array<int, string>, courses: array<int, array<string, mixed>>}
     */
    public function pickerPayload(): array
    {
        $rows = $this->courses->query()
            ->where('status', 'published')
            ->withCount(['classRooms' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('title')
            ->get(['id', 'title', 'grade', 'outcome'])
            ->map(fn (Course $c) => [
                'id' => $c->id,
                'grade' => $c->grade ?: 'Chưa phân khối',
                'goal' => $c->outcome ?: $c->title,
                'title' => $c->title,
                'href' => route('courses.show', $c->id),
                'openClasses' => (int) $c->class_rooms_count,
            ])
            ->values()
            ->all();

        // Xếp khối lớp theo SỐ chứ không theo chữ — xếp theo chữ thì "Lớp 10" đứng trước "Lớp 6".
        $grades = collect($rows)
            ->pluck('grade')
            ->unique()
            ->sortBy(fn (string $g) => ((int) preg_replace('/\D/', '', $g)) ?: 99)
            ->values()
            ->all();

        return ['grades' => $grades, 'courses' => $rows];
    }

    /**
     * Bốn con số in ở bảng thông tin đầu trang chi tiết khoá học + dòng nhịp học.
     *
     * TẤT CẢ lấy từ dữ liệu THẬT, không có số viết cứng nào:
     *   · học viên      — cộng students_count của các lớp đang mở;
     *   · tổng buổi     — ưu tiên courses.session_count (số buổi THEO CHƯƠNG TRÌNH do quản trị
     *                     nhập); chưa nhập thì lấy lớp có nhiều buổi đã xếp lịch nhất
     *                     (class_sessions). Không có cả hai thì trả 0 và view tự giấu ô đó đi
     *                     thay vì in số 0 vô nghĩa;
     *   · số tuần       — khoảng cách buổi đầu tới buổi cuối của lớp dài nhất, làm tròn lên.
     *                     Lớp chưa xếp lịch thì không có số tuần, view giấu dòng nhịp học.
     *
     * @return array{totalStudents:int, openClassCount:int, sessionTotal:int, weekSpan:int, sessionsPerWeek:?float}
     */
    private function headlineFigures(Course $course): array
    {
        $classRooms = $course->classRooms;

        $sessionTotal = (int) ($course->session_count ?? 0);
        if ($sessionTotal <= 0) {
            $sessionTotal = (int) $classRooms->max('sessions_count');
        }

        // Lớp dài nhất quyết định độ dài khoá — lớp mới mở xếp lịch chưa đủ không kéo con số xuống.
        $weekSpan = 0;
        foreach ($classRooms as $classRoom) {
            $first = $classRoom->sessions_min_starts_at;
            $last = $classRoom->sessions_max_ends_at;
            if ($first === null || $last === null) {
                continue;
            }

            $days = Carbon::parse($first)->diffInDays(Carbon::parse($last));
            $weekSpan = max($weekSpan, (int) ceil(($days + 1) / 7));
        }

        return [
            'totalStudents' => (int) $classRooms->sum('students_count'),
            'openClassCount' => $classRooms->count(),
            'sessionTotal' => $sessionTotal,
            'weekSpan' => $weekSpan,
            'sessionsPerWeek' => $weekSpan > 0 && $sessionTotal > 0
                ? round($sessionTotal / $weekSpan, 1)
                : null,
        ];
    }

    private function mapCourseCard(Course $course, Collection $ratingsByClassRoomId, array $myClassRoomIds = []): array
    {
        $classRoomIds = $course->classRooms->pluck('id')->all();
        [$average, $count] = $this->aggregate($classRoomIds, $ratingsByClassRoomId);

        $metaParts = array_filter([
            count($classRoomIds) > 0 ? count($classRoomIds).' lớp đang triển khai' : 'Chưa có lớp triển khai',
            $course->subject,
            $course->grade,
        ]);

        $firstClassRoom = $course->classRooms->first();
        $teachers = $firstClassRoom?->teachers ?? collect();
        $studentCount = (int) $course->classRooms->sum('students_count');

        // "Vào học" khi học sinh đã ở trong 1 lớp của khóa; "Đã đóng" khi khóa chưa mở lớp nào
        // đang hoạt động; còn lại là "Đăng ký học". Không có trạng thái nào được suy đoán thêm.
        $myEnrolledInThisCourse = array_values(array_intersect($classRoomIds, $myClassRoomIds));

        $enrollmentStatus = 'Đăng ký học';
        if ($classRoomIds === []) {
            $enrollmentStatus = 'Đã đóng';
        } elseif ($myEnrolledInThisCourse !== []) {
            $enrollmentStatus = 'Vào học';
        }

        /*
         * SỬA 14/9 (khách yêu cầu "bấm Vào học thì vào thẳng lớp luôn") — đường dẫn cho NÚT
         * trên thẻ. Chỉ khác 'href' đúng ở trạng thái "Vào học": học sinh đã ở trong lớp rồi
         * thì bắt xem lại trang giới thiệu khoá là thừa một cú bấm.
         * Đang học nhiều lớp cùng một khoá thì không tự chọn hộ — đưa về danh sách lớp của
         * học sinh để tự vào đúng lớp muốn học (cùng luật với showData()).
         */
        $ctaHref = match (true) {
            $enrollmentStatus !== 'Vào học' => route('courses.show', $course->id),
            count($myEnrolledInThisCourse) === 1 => route('student.classes.show', $myEnrolledInThisCourse[0]),
            default => route('student.courses.index'),
        };

        return [
            'id' => $course->id,
            'title' => $course->title,
            'meta' => implode(' · ', $metaParts),
            'average' => $average,
            'count' => $count,
            // ── các trường bổ sung cho thẻ lớp học của giao diện mới ──
            'subtitle' => $course->description,
            'subject' => $course->subject,
            'grade' => $course->grade,
            'image' => $course->cover_image_path ? asset('storage/'.$course->cover_image_path) : null,
            'classCount' => count($classRoomIds),
            'classCode' => $firstClassRoom?->code,
            'className' => $firstClassRoom?->name,
            'studentCount' => $studentCount,
            'teacherName' => $teachers->first()?->name,
            'assistantNames' => $teachers->skip(1)->pluck('name')->values()->all(),
            'enrollmentStatus' => $enrollmentStatus,
            'href' => route('courses.show', $course->id),
            'ctaHref' => $ctaHref,
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
