<?php

namespace App\Services\Public;

use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\AssignmentRepositoryInterface;
use App\Repositories\Contracts\AttemptRepositoryInterface;
use App\Repositories\Contracts\ClassEnrollmentRepositoryInterface;
use App\Repositories\Contracts\ClassRoomRepositoryInterface;
use App\Repositories\Contracts\CompetitionRepositoryInterface;
use App\Repositories\Contracts\CourseRepositoryInterface;
use App\Repositories\Contracts\LeaderboardEntryRepositoryInterface;
use App\Repositories\Contracts\ParentLinkRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\RatingSummaryRepositoryInterface;
use App\Repositories\Contracts\TeacherProfileRepositoryInterface;
use App\Repositories\Contracts\TestimonialRepositoryInterface;

/**
 * home (PUB-01/02, 12.1: hero → lộ trình → năng lực chấm → khóa/tài liệu nổi bật → cuộc thi
 * → Giáo viên và chuyên gia → cam kết/FAQ) — trước đây route 'home' là closure
 * (Route::get('/', fn () => view('welcome'))) và welcome.blade.php TỰ khai báo mảng dữ liệu
 * minh họa cứng ngay trong view — không có Controller/Service thật nào đứng sau.
 *
 * "Nổi bật" trên trang chủ = TOP N của chính danh mục công khai thật (tái dùng lại đúng
 * CourseService/MaterialService/CompetitionService/TeacherService đã có cho từng trang con),
 * KHÔNG phải một tập dữ liệu ảo riêng cho trang chủ — để tránh trang chủ hứa hẹn nội dung mà
 * trang con thật không có, và tránh phải bảo trì luật lọc (published/public/is_featured...)
 * ở 2 nơi cùng lúc.
 */
class HomeService
{
    /** Số thẻ hiển thị cho mỗi khối "nổi bật" trên trang chủ (khóa học/tài liệu/giáo viên). */
    private const FEATURED_LIMIT = 4;

    /** "Cuộc thi sắp tới" chỉ cần 2 thẻ vì khối này đã có nền tối chiếm nhiều diện tích. */
    private const UPCOMING_COMPETITIONS_LIMIT = 2;

    public function __construct(
        private readonly CourseService $courseService,
        private readonly MaterialService $materialService,
        private readonly CompetitionService $competitionService,
        private readonly TeacherService $teacherService,
        private readonly CourseRepositoryInterface $courses,
        private readonly ClassRoomRepositoryInterface $classRooms,
        private readonly ClassEnrollmentRepositoryInterface $classEnrollments,
        private readonly TeacherProfileRepositoryInterface $teacherProfiles,
        private readonly RatingSummaryRepositoryInterface $ratingSummaries,
        private readonly LeaderboardEntryRepositoryInterface $leaderboardEntries,
        private readonly CompetitionRepositoryInterface $competitionsRepo,
        private readonly ProductRepositoryInterface $productsRepo,
        private readonly AssignmentRepositoryInterface $assignments,
        private readonly AttemptRepositoryInterface $attempts,
        private readonly ParentLinkRepositoryInterface $parentLinks,
        // SỬA 12/9 — khối [HOME-10] "Câu chuyện đồng hành" giờ lấy từ CSDL, do Admin đăng.
        private readonly TestimonialRepositoryInterface $testimonialsRepo,
    ) {}

    public function indexData(): array
    {
        return [
            'stats' => $this->buildStats(),
            // CourseService::indexData(null) đã trả đúng hình dạng thẻ (id/title/meta/
            // average/count) mà <x-card-item> cần, sắp mới nhất trước — lấy $limit đầu làm
            // "nổi bật" (khóa học vừa phát hành gần đây nhất).
            'featuredCourses' => array_slice($this->courseService->indexData(null)['courses'], 0, self::FEATURED_LIMIT),
            'featuredMaterials' => $this->materialService->featuredData(self::FEATURED_LIMIT),
            // SỬA 9/9 (11) — 3 tab "Tài liệu nổi bật" ở trang chủ đổi qua lại NGAY TẠI CHỖ
            // (không tải lại trang), nên phải nạp sẵn cả 3 nhóm. Mỗi nhóm tối đa FEATURED_LIMIT
            // thẻ, cùng luật lọc với trang Tài liệu (đã phát hành + công khai).
            'featuredMaterialsByType' => [
                'sach' => $this->materialService->featuredData(self::FEATURED_LIMIT, \App\Enums\ProductType::Book),
                'chuyen-de' => $this->materialService->featuredData(self::FEATURED_LIMIT, \App\Enums\ProductType::Topic),
                'de-thi' => $this->materialService->featuredData(self::FEATURED_LIMIT, \App\Enums\ProductType::Exam),
            ],
            'upcomingCompetitions' => $this->competitionService->upcomingData(self::UPCOMING_COMPETITIONS_LIMIT),
            'featuredTeachers' => $this->teacherService->featuredData(self::FEATURED_LIMIT),
            'faqs' => $this->faqs(),
            // SỬA 11/9 — dựng lại trang chủ theo source giao diện khách (education-main).
            // 3 khối dưới đây trước là dữ liệu minh hoạ cứng trong bản mẫu React; ở đây lấy
            // thẳng từ cơ sở dữ liệu thật.
            'systemNotices' => $this->systemNotices(),
            'topStudents' => $this->topStudents(),
            'testimonials' => $this->testimonials(),
            'learningSpace' => $this->learningSpace(auth()->user()),
        ];
    }

    /**
     * 4 số liệu ở hero (12.1) — TÍNH TRỰC TIẾP từ dữ liệu thật, thay cho 4 chuỗi cố định
     * ('12.000+', '350+', '120+', '4.8/5') trong bản cũ.
     *
     * - "Học sinh đang học": đếm SỐ HỌC SINH KHÁC NHAU đang có ít nhất 1 ghi danh active
     *   (distinct student_id) — không đếm theo tổng số tài khoản role=student, vì một tài
     *   khoản có thể chưa từng vào lớp nào.
     * - "Giáo viên đã duyệt": TeacherProfileRepositoryInterface::countApproved() — đúng bằng
     *   con số đã hiển thị ở admin.featured-teachers.index nên không lệch số giữa 2 màn.
     * - "Khóa học & lớp": Course đã phát hành (status=published) + ClassRoom đang hoạt động
     *   (status=active) — gộp lại vì trang chủ chỉ cần 1 con số chung, không tách 2 khối.
     * - "Đánh giá trung bình": trung bình CÓ TRỌNG SỐ theo review_count trên TOÀN BỘ
     *   RatingSummary (mọi loại đối tượng: material/class_room/teacher/competition), cùng
     *   công thức aggregate() đã dùng ở CourseService — chỉ khác là tính cho CẢ nền tảng
     *   thay vì 1 khóa/lớp cụ thể.
     *
     * Để PUBLIC (không private) vì App\Services\Public\InfoService (trang Thông tin) dùng
     * lại ĐÚNG 4 số liệu này ở mục "Giới thiệu" — 1 nguồn tính duy nhất, tránh 2 trang hiện
     * 2 con số lệch nhau cho cùng một nền tảng.
     */
    public function buildStats(): array
    {
        $activeStudents = $this->classEnrollments->query()
            ->where('status', 'active')
            ->distinct()
            ->count('student_id');

        $approvedTeachers = $this->teacherProfiles->countApproved();

        $publishedCourses = $this->courses->query()->where('status', 'published')->count();
        $activeClassRooms = $this->classRooms->query()->where('status', 'active')->count();

        $ratingRow = $this->ratingSummaries->query()
            ->selectRaw('SUM(avg_rating * review_count) as weighted, SUM(review_count) as total')
            ->first();
        $platformAverage = ($ratingRow !== null && (int) $ratingRow->total > 0)
            ? round($ratingRow->weighted / $ratingRow->total, 1)
            : null;

        return [
            ['value' => $this->countLabel($activeStudents), 'label' => 'Học sinh đang học'],
            ['value' => $this->countLabel($approvedTeachers), 'label' => 'Giáo viên đã duyệt'],
            ['value' => $this->countLabel($publishedCourses + $activeClassRooms), 'label' => 'Khóa học & lớp'],
            ['value' => $platformAverage !== null ? number_format($platformAverage, 1).'/5' : '—', 'label' => 'Đánh giá trung bình'],
        ];
    }

    /**
     * [HOME-01] Thanh thông báo hệ thống — bản mẫu React để 3 tin cứng; ở đây lấy đúng 3
     * "tin đáng chú ý" từ dữ liệu thật: cuộc thi sắp diễn ra gần nhất, tài liệu mới phát
     * hành nhất, khoá học mới phát hành nhất. Thiếu nguồn nào thì bỏ tin đó, hết sạch thì
     * trả về 1 tin giới thiệu trung tính (thanh vẫn cân đối, không hiện khung trống).
     *
     * @return array<int, array{category:string,message:string,surfaceClass:string,borderClass:string,accentClass:string,categoryClass:string,iconClass:string,href:string}>
     */
    private function systemNotices(): array
    {
        $notices = [];

        $competition = $this->competitionsRepo->query()
            ->where('status', '!=', \App\Enums\CompetitionStatus::Archived->value)
            ->whereNotNull('starts_at')
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->first();

        if ($competition !== null) {
            $notices[] = [
                'category' => 'Kỳ thi',
                'message' => $competition->title.' — bắt đầu lúc '.$competition->starts_at->format('H:i \n\g\à\y d/m/Y').'. Hãy chuẩn bị thật tốt!',
                'surfaceClass' => 'from-[#FFF9E6] via-[#FCFBF5] to-[#F1F7FC]',
                'borderClass' => 'border-amber-200/80',
                'accentClass' => 'bg-amber-300',
                'categoryClass' => 'border-amber-200 bg-amber-50 text-amber-800',
                'iconClass' => 'text-amber-500',
                'href' => route('competitions.show', $competition->id),
            ];
        }

        $material = $this->productsRepo->query()
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->where('type', '!=', \App\Enums\ProductType::Course->value)
            ->latest()
            ->first();

        if ($material !== null) {
            $notices[] = [
                'category' => 'Học liệu mới',
                'message' => 'Tài liệu mới: “'.$material->title.'” đã có trong kho học liệu.',
                'surfaceClass' => 'from-[#EEF9F5] via-[#F8FCFB] to-[#F2F7FD]',
                'borderClass' => 'border-emerald-200/80',
                'accentClass' => 'bg-emerald-400',
                'categoryClass' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                'iconClass' => 'text-emerald-500',
                'href' => route('materials.show', $material->id),
            ];
        }

        $course = $this->courses->query()->where('status', 'published')->latest()->first();

        if ($course !== null) {
            $notices[] = [
                'category' => 'Lớp học',
                'message' => 'Khoá học “'.$course->title.'” đang mở lớp. Xem lịch để không bỏ lỡ buổi học.',
                'surfaceClass' => 'from-[#EFF8FF] via-[#F7FBFE] to-[#F2F8F6]',
                'borderClass' => 'border-sky-200/80',
                'accentClass' => 'bg-sky-400',
                'categoryClass' => 'border-sky-200 bg-sky-50 text-sky-800',
                'iconClass' => 'text-sky-500',
                'href' => route('courses.show', $course->id),
            ];
        }

        if ($notices === []) {
            $notices[] = [
                'category' => 'Giới thiệu',
                'message' => 'Chào mừng bạn đến với Ôn Thi 360 — học cùng mục tiêu, vươn xa ước mơ.',
                'surfaceClass' => 'from-[#EFF8FF] via-[#F7FBFE] to-[#F2F8F6]',
                'borderClass' => 'border-sky-200/80',
                'accentClass' => 'bg-sky-400',
                'categoryClass' => 'border-sky-200 bg-sky-50 text-sky-800',
                'iconClass' => 'text-sky-500',
                'href' => route('info.index'),
            ];
        }

        return $notices;
    }

    /**
     * [HOME-07] "Top xuất sắc" — lấy từ bảng xếp hạng THẬT của cuộc thi đã công bố gần nhất.
     * Tên người học được ẩn danh y hệt App\Services\Public\LeaderboardService (bảo vệ dữ liệu
     * trẻ em: chưa có cột "đồng ý hiển thị công khai" nên áp dụng cho mọi người, không ngoại
     * lệ) — trang chủ KHÔNG được lộ nhiều hơn trang Bảng xếp hạng.
     *
     * @return array{title:?string, rows: array<int, array{rank:int,name:string,score:float}>}
     */
    /**
     * [HOME-10] "Câu chuyện đồng hành" — trước đây ghi cứng trong welcome.blade.php, giờ do
     * Admin đăng ở Quản trị → Câu chuyện đồng hành.
     *
     * Trả về MẢNG RỖNG khi chưa có câu chuyện nào được bật hiển thị; view tự lùi về 3 câu mẫu
     * của bộ giao diện để khối không bị trống (xem welcome.blade.php).
     *
     * Khoá 'verified' quyết định câu chuyện đó có được gắn schema.org/Review gửi Google hay
     * không — xem partials/seo-testimonials.blade.php.
     */
    private function testimonials(): array
    {
        /*
         * Chặn lỗi lúc TRIỂN KHAI: mã nguồn mới lên máy chủ trước, `php artisan migrate` chạy
         * sau — trong khoảng giữa đó bảng testimonials chưa tồn tại. Không có lớp chặn này thì
         * TRANG CHỦ CÔNG KHAI sẽ lỗi 500 cho mọi khách vào xem. Chưa có bảng thì coi như chưa
         * có câu chuyện nào, view tự lùi về 3 câu mẫu của bộ giao diện.
         * Kết quả được nhớ trong 1 lần chạy để không hỏi lược đồ CSDL nhiều lần mỗi trang.
         */
        static $tableExists = null;

        if ($tableExists === null) {
            $tableExists = \Illuminate\Support\Facades\Schema::hasTable('testimonials');
        }

        if (! $tableExists) {
            return [];
        }

        return $this->testimonialsRepo
            ->publishedForHome(\App\Services\Admin\TestimonialService::HOME_LIMIT)
            ->map(fn ($t) => [
                'quote' => $t->quote,
                'author' => $t->author_name,
                'role' => trim($t->author_role.($t->author_org ? ' · '.$t->author_org : ''), ' ·'),
                'avatar' => $t->avatarUrl(),
                'banner' => $t->bannerUrl(),
                'rating' => $t->rating,
                'verified' => $t->isVerified(),
                'publishedAt' => $t->published_at?->toDateString(),
            ])
            ->all();
    }

    private function topStudents(): array
    {
        $competition = $this->competitionsRepo->query()
            ->where('status', 'published')
            ->withCount('leaderboardEntries')
            ->having('leaderboard_entries_count', '>', 0)
            ->latest('publish_result_at')
            ->first();

        if ($competition === null) {
            return ['title' => null, 'rows' => []];
        }

        $rows = $this->leaderboardEntries->entriesForCompetition($competition->id)
            ->take(5)
            ->map(fn ($e) => [
                'rank' => (int) $e->rank,
                'name' => 'Học viên đã xác thực',
                'score' => (float) $e->score,
                // SỬA 12/9 — source mới thêm ảnh đại diện vào từng dòng xếp hạng. Tên vẫn ẩn
                // danh (bảo vệ dữ liệu học sinh) nên ảnh dùng bộ avatar trung tính của bản mẫu,
                // xoay theo thứ hạng để mỗi hạng luôn ra cùng một ảnh.
                'avatar' => asset('assets/rank-avatar-'.((max(1, (int) $e->rank) - 1) % 5 + 1).'.png'),
            ])
            ->values()
            ->all();

        return ['title' => $competition->title, 'rows' => $rows];
    }

    /**
     * [HOME-06] "Không gian học tập" — bản mẫu React để số liệu cứng theo 3 vai trò; ở đây
     * tính từ dữ liệu thật của CHÍNH người đang đăng nhập. Khách chưa đăng nhập trả về
     * ['guest' => true] để view hiện đúng khối mời đăng nhập của bản mẫu.
     *
     * Mọi con số đều đếm trực tiếp, không ước lượng: không có dữ liệu thì hiện 0 chứ không
     * bịa ra một tỉ lệ đẹp.
     */
    private function learningSpace(?User $viewer): array
    {
        if ($viewer === null) {
            return ['guest' => true];
        }

        /*
         * SỬA 12/9 — source mới thêm dải chọn vai trò [HOME-06A] ngay trên thanh tiến độ
         * (bản mẫu cho đổi tuỳ ý vì là demo). Trên web thật KHÔNG thể cho xem số liệu của vai
         * trò mình không có, nên dải này CHỈ hiện khi người đang đăng nhập thật sự giữ từ 2
         * vai trò trở lên (ví dụ vừa là giáo viên vừa là phụ huynh) — mỗi tab là số liệu thật
         * của đúng vai trò đó. Giữ 1 vai trò thì vẫn hiện đúng một nhãn như trước.
         */
        $panels = [];

        if ($viewer->hasRole(Role::STUDENT)) {
            $panels['student'] = $this->studentPanel($viewer);
        }

        if ($viewer->hasRole(Role::PARENT)) {
            $panels['parent'] = $this->parentPanel($viewer);
        }

        if ($viewer->hasRole(Role::TEACHER)) {
            $panels['teacher'] = $this->teacherPanel($viewer);
        }

        // Không khớp vai trò nào ở trên (quản trị, biên tập...) thì vẫn hiện góc nhìn học sinh
        // như trước đây, để khối không bị trống.
        if ($panels === []) {
            $panels['student'] = $this->studentPanel($viewer);
        }

        $defaultRole = array_key_first($panels);
        $active = $panels[$defaultRole];

        return $active + [
            'guest' => false,
            'panels' => $panels,
            'defaultRole' => $defaultRole,
            'multiRole' => count($panels) > 1,
        ];
    }

    /** Nhãn + biểu tượng của từng tab vai trò — khớp bảng của source (JOURNEY_ROLE_VIEWS). */
    private const ROLE_VIEW_META = [
        'student' => ['label' => 'Học sinh', 'icon' => 'graduation-cap', 'iconClass' => 'text-[#2D7FA3]'],
        'parent' => ['label' => 'Phụ huynh', 'icon' => 'heart', 'iconClass' => 'text-[#4C88A1]'],
        'teacher' => ['label' => 'Giáo viên', 'icon' => 'users', 'iconClass' => 'text-[#5B77A8]'],
    ];

    /** Góc nhìn giáo viên — số liệu thật của các lớp người này đang dạy. */
    private function teacherPanel(User $viewer): array
    {
        {
            $classRoomIds = $this->classRooms->query()
                ->whereHas('teachers', fn ($q) => $q->where('users.id', $viewer->id))
                ->where('status', 'active')
                ->pluck('id');

            $studentCount = $this->classEnrollments->query()
                ->whereIn('class_room_id', $classRoomIds)
                ->where('status', 'active')
                ->distinct()
                ->count('student_id');

            $assignmentCount = $this->assignments->query()->whereIn('class_room_id', $classRoomIds)->count();
            $waitingGrade = $this->attempts->query()
                ->whereIn('class_room_id', $classRoomIds)
                ->whereNotNull('submitted_at')
                ->where('is_provisional', true)
                ->count();

            $classContexts = $this->classRooms->query()
                ->whereIn('id', $classRoomIds)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($room) => ['label' => $room->name, 'href' => route('teacher.classes.index')])
                ->all();

            return [
                'roleKey' => 'teacher',
                'roleIcon' => self::ROLE_VIEW_META['teacher']['icon'],
                'roleIconClass' => self::ROLE_VIEW_META['teacher']['iconClass'],
                'contextLabel' => 'Lớp đang quản lý',
                'contexts' => $classContexts,
                'guest' => false,
                'roleLabel' => 'Giáo viên',
                'progressLabel' => 'Bài đã chấm xong',
                'progress' => $this->percent($this->attempts->query()->whereIn('class_room_id', $classRoomIds)->whereNotNull('submitted_at')->where('is_provisional', false)->count(), $this->attempts->query()->whereIn('class_room_id', $classRoomIds)->whereNotNull('submitted_at')->count()),
                'nextLabel' => 'Cần theo dõi',
                'nextTitle' => $waitingGrade > 0 ? $waitingGrade.' bài đang chờ chấm xong' : 'Không còn bài nào chờ chấm',
                'nextMeta' => $classRoomIds->count().' lớp đang dạy',
                'nextHref' => route('teacher.classes.index'),
                'stats' => [
                    ['value' => (string) $classRoomIds->count(), 'label' => 'Lớp đang dạy', 'valueClass' => 'text-[#3E79A4]'],
                    ['value' => (string) $studentCount, 'label' => 'Học sinh', 'valueClass' => 'text-[#3B9374]'],
                    ['value' => (string) $assignmentCount, 'label' => 'Bài đã giao', 'valueClass' => 'text-[#AF7C32]'],
                ],
            ];
        }
    }

    /** Góc nhìn phụ huynh — số liệu thật của các con đã liên kết và được xác minh. */
    private function parentPanel(User $viewer): array
    {
        {
            $childIds = $this->parentLinks->query()->where('parent_user_id', $viewer->id)->where('status', 'verified')->pluck('student_user_id');

            $submitted = $this->attempts->query()->whereIn('user_id', $childIds)->whereNotNull('submitted_at')->count();
            $classCount = $this->classEnrollments->query()
                ->whereIn('student_id', $childIds)
                ->where('status', 'active')
                ->distinct()
                ->count('class_room_id');
            $weekCount = $this->attempts->query()
                ->whereIn('user_id', $childIds)
                ->whereNotNull('submitted_at')
                ->where('submitted_at', '>=', now()->subDays(7))
                ->count();

            // Tên các con — đọc thẳng từ bảng users theo đúng danh sách đã liên kết & xác minh
            // ở trên (HomeService chưa có repo người dùng riêng, và đây là truy vấn đọc đơn giản).
            $childContexts = \App\Models\User::query()
                ->whereIn('id', $childIds)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($child) => ['label' => $child->name, 'href' => route('dashboard')])
                ->all();

            return [
                'roleKey' => 'parent',
                'roleIcon' => self::ROLE_VIEW_META['parent']['icon'],
                'roleIconClass' => self::ROLE_VIEW_META['parent']['iconClass'],
                'contextLabel' => 'Con đang theo dõi',
                'contexts' => $childContexts,
                'guest' => false,
                'roleLabel' => 'Phụ huynh',
                'progressLabel' => 'Bài đã nộp trong 7 ngày',
                'progress' => $this->percent($weekCount, max($submitted, 1)),
                'nextLabel' => 'Cần đồng hành',
                'nextTitle' => $weekCount > 0 ? 'Các con đã nộp '.$weekCount.' bài trong tuần' : 'Tuần này chưa có bài nộp mới',
                'nextMeta' => $childIds->count().' học sinh đang theo dõi',
                'nextHref' => route('dashboard'),
                'stats' => [
                    ['value' => (string) $childIds->count(), 'label' => 'Con đang theo dõi', 'valueClass' => 'text-[#3E79A4]'],
                    ['value' => (string) $classCount, 'label' => 'Lớp đang học', 'valueClass' => 'text-[#3B9374]'],
                    ['value' => (string) $weekCount, 'label' => 'Bài tuần này', 'valueClass' => 'text-[#AF7C32]'],
                ],
            ];
        }
    }

    /** Góc nhìn học sinh — mặc định, cũng dùng cho vai trò không có bảng riêng. */
    private function studentPanel(User $viewer): array
    {
        $classRoomIds = $this->classEnrollments->query()
            ->where('student_id', $viewer->id)
            ->where('status', 'active')
            ->pluck('class_room_id');

        $assignedTotal = $this->assignments->query()
            ->whereIn('class_room_id', $classRoomIds)
            ->where('status', 'published')
            ->count();

        $doneTotal = $this->attempts->query()
            ->where('user_id', $viewer->id)
            ->whereNotNull('submitted_at')
            ->whereNotNull('assignment_id')
            ->distinct()
            ->count('assignment_id');

        $inProgress = $this->attempts->query()
            ->where('user_id', $viewer->id)
            ->whereNull('submitted_at')
            ->count();

        $nextAssignment = $this->assignments->query()
            ->whereIn('class_room_id', $classRoomIds)
            ->where('status', 'published')
            ->whereNotNull('due_at')
            ->where('due_at', '>', now())
            ->with('assessment')
            ->orderBy('due_at')
            ->first();

        return [
            'roleKey' => 'student',
            'roleIcon' => self::ROLE_VIEW_META['student']['icon'],
            'roleIconClass' => self::ROLE_VIEW_META['student']['iconClass'],
            'contextLabel' => null,
            'contexts' => [],
            'guest' => false,
            'roleLabel' => 'Học sinh',
            'progressLabel' => 'Tiến độ tổng thể',
            'progress' => $this->percent($doneTotal, $assignedTotal),
            'nextLabel' => 'Tiếp tục học',
            'nextTitle' => $nextAssignment?->assessment?->title ?? 'Chưa có bài nào sắp đến hạn',
            'nextMeta' => $nextAssignment?->due_at !== null
                ? 'Hạn nộp '.$nextAssignment->due_at->format('H:i d/m/Y')
                : 'Xem toàn bộ bài tập trong khu học tập',
            'nextHref' => route('dashboard'),
            'stats' => [
                ['value' => (string) $doneTotal, 'label' => 'Bài đã xong', 'valueClass' => 'text-[#3E79A4]'],
                ['value' => (string) $inProgress, 'label' => 'Đang làm', 'valueClass' => 'text-[#3B9374]'],
                ['value' => (string) max($assignedTotal - $doneTotal, 0), 'label' => 'Chưa làm', 'valueClass' => 'text-[#AF7C32]'],
            ],
        ];
    }

    /** Tỉ lệ phần trăm làm tròn, mẫu số 0 thì trả 0 (không chia cho 0, không hiện "NaN%"). */
    private function percent(int $done, int $total): int
    {
        return $total > 0 ? (int) round($done / $total * 100) : 0;
    }

    /**
     * "12.000+" khi >= 1.000 (làm tròn xuống hàng trăm gần nhất — không cam kết đúng
     * TỪNG NGƯỜI mỗi lần tải lại trang, chỉ là một mốc tăng trưởng); số nhỏ hơn hiện
     * nguyên kèm dấu "+". Hệ thống mới/số liệu = 0 hiện "—" thay vì "0" — tránh trang chủ
     * trông như lỗi/trống dữ liệu khi thực chất chỉ là chưa có ai (nhất quán với cách
     * "Đánh giá trung bình" đã xử lý ngay bên dưới).
     */
    private function countLabel(int $count): string
    {
        if ($count <= 0) {
            return '—';
        }

        if ($count >= 1000) {
            return number_format((int) (floor($count / 100) * 100)).'+';
        }

        return ((string) $count).'+';
    }

    /** Nội dung FAQ (12.1 mục 9) — thông tin chính sách tĩnh, không phải dữ liệu nghiệp vụ cần truy vấn. */
    private function faqs(): array
    {
        return [
            ['q' => 'Bài công khai có cần đăng nhập không?', 'a' => 'Khách xem được; cần đăng nhập để bắt đầu, nộp bài và lưu kết quả.'],
            ['q' => 'Quyền học và quyền dạy khác nhau thế nào?', 'a' => 'Quyền dạy của giáo viên không tự cấp quyền học cho học sinh, và ngược lại — mỗi quyền có phạm vi và thời hạn riêng, luôn hiển thị rõ trên từng học liệu.'],
            ['q' => 'Vì sao một bài học lại bị khóa?', 'a' => 'Hệ thống luôn nêu đúng lý do: thiếu quyền học liệu, giáo viên chưa mở theo tiến độ lớp, hoặc quyền đã hết hạn — không khóa mà không giải thích.'],
            ['q' => 'Chấm bài code (OJ) hoạt động thế nào?', 'a' => 'Bài code được chấm bằng bộ test/luật rõ ràng, kế thừa năng lực từ Quinhdao OJ — không gọi là "AI chấm" khi hệ thống thực chất dùng luật/test case.'],
        ];
    }
}
