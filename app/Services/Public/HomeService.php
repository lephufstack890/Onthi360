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
use Illuminate\Support\Facades\Route;

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
        // B2/B5 (15/9) — khối [HOME-04] ba ô chọn và khối [HOME-05B] lấy lộ trình thật.
        private readonly LearningPathService $learningPathService,
        // SỬA 15/9 — bảng "Không gian học tập" cho vai trò quản trị. Dùng lại đúng bộ đếm của
        // bảng điều khiển quản trị, không viết lại truy vấn (xem DashboardService::pendingCounts).
        private readonly \App\Services\Admin\DashboardService $adminDashboard,
    ) {}

    public function indexData(): array
    {
        return [
            'stats' => $this->buildStats(),
            // SỬA 12/10 — khối [HOME-09A] "Thống kê hệ thống" (source mới), số liệu thật từ CSDL.
            'systemStats' => $this->systemStats(),
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
            /*
             * B2 — [HOME-04] "Chọn mục tiêu hoặc lộ trình": ba ô chọn thu hẹp dần.
             * B5 — [HOME-05B] thay 5 bước viết cứng bằng lộ trình thật.
             *
             * SỬA 15/9 — khách tạm dừng phần lộ trình công khai nên 2 khoá này trả về RỖNG,
             * và welcome.blade.php tự quay về đúng bản gốc: [HOME-05B] hiện lại 5 thẻ giới
             * thiệu viết cứng, [HOME-04] hiện lại nút "Xem lộ trình" tĩnh trỏ sang Lớp học.
             *
             * Để nguyên 2 dòng gọi service (chỉ bọc trong công tắc) chứ không xoá — bật
             * PUBLIC_ENABLED lên true là trang chủ có lại lộ trình thật ngay, không phải viết
             * lại gì. Khi tắt thì cũng KHÔNG chạy truy vấn nào cho lộ trình.
             */
            /*
             * SỬA 15/9 — [HOME-04] giờ đổ KHOÁ HỌC thật (khách chốt bỏ lộ trình). Xem
             * Public\CourseService::pickerPayload().
             */
            'coursePicker' => $this->courseService->pickerPayload(),
            'pathPicker' => LearningPathService::PUBLIC_ENABLED
                ? $this->learningPathService->pickerPayload()
                : ['paths' => [], 'grades' => [], 'indexHref' => route('courses.index'), 'showLanguage' => false],
            'learningPathCards' => LearningPathService::PUBLIC_ENABLED
                ? $this->learningPathService->homeStrip(self::FEATURED_LIMIT)
                : [],
        ];
    }

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
     * @return array{members: int, students: int, parents: int, teachers: int, exams: int, exercises: int, dailyAttempts: list<int>}|null
     */
    public function systemStats(): ?array
    {
        try {
            $stats = \Illuminate\Support\Facades\Cache::remember('home.system_stats.v2', 600, function () {
                $accounts = fn () => \App\Models\User::query()->where('status', 'active');
                $byRole = fn (string $role) => $accounts()->whereHas('roles', fn ($q) => $q->where('name', $role))->count();

                $students = $byRole(Role::STUDENT);
                $parents = $byRole(Role::PARENT);
                $teachers = $byRole(Role::TEACHER);
                $members = $accounts()->whereHas('roles', fn ($q) => $q->whereIn('name', [Role::STUDENT, Role::PARENT, Role::TEACHER]))->count();

                $tz = \App\Services\Public\PracticeDailyStatsService::TIME_ZONE;
                $today = \Carbon\Carbon::now($tz);
                $daily = [];

                for ($ago = 6; $ago >= 0; $ago--) {
                    $day = $today->copy()->subDays($ago);
                    $from = $day->copy()->startOfDay()->setTimezone(config('app.timezone'));
                    $to = $day->copy()->endOfDay()->setTimezone(config('app.timezone'));

                    $problems = \App\Models\AttemptAnswer::query()
                        ->whereHas('attempt', fn ($a) => $a->whereNull('assessment_id'))
                        ->whereBetween('updated_at', [$from, $to])
                        ->where(function ($w) {
                            $w->where('submission_count', '>', 0)->orWhereNotNull('graded_at')->orWhereNotNull('score');
                        })
                        ->count();

                    $examAttempts = \App\Models\Attempt::query()
                        ->whereNotNull('assessment_id')
                        ->whereNotNull('submitted_at')
                        ->where('source', '!=', 'staff_test') // SỬA 30/10 — bỏ lượt LÀM THỬ của ban tổ chức (ContestTestRunController)
                        ->whereBetween('submitted_at', [$from, $to])
                        ->whereHas('assessment', fn ($q) => $q->where('type', 'practice'))
                        ->count();

                    $daily[] = $problems + $examAttempts;
                }

                return [
                    'members' => $members,
                    'students' => $students,
                    'parents' => $parents,
                    'teachers' => $teachers,
                    'dailyAttempts' => $daily,
                ];
            });

            try {
                $catalog = app(PracticeService::class);
                $stats['exams'] = $catalog->catalogExamCount();
                $stats['exercises'] = $catalog->catalogProblemCount();
            } catch (\Throwable $e) {
                report($e);
                $stats['exams'] = $stats['exams'] ?? 0;
                $stats['exercises'] = $stats['exercises'] ?? 0;
            }

            return $stats;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
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
        try {
            return app(LeaderboardService::class)->topStudents(5);
        } catch (\Throwable $e) {
            // Khối phụ ở trang chủ — lỗi truy vấn không được làm hỏng cả trang.
            report($e);

            return ['title' => null, 'rows' => []];
        }
    }

    private function learningSpace(?User $viewer): array
    {
        if ($viewer === null) {
            return ['guest' => true];
        }

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

        if ($viewer->hasAnyRole(Role::ADMIN, Role::SUPER_ADMIN)) {
            $panels['admin'] = $this->adminPanel();
        }

        if ($viewer->hasRole(Role::EDITOR) && ! $viewer->hasAnyRole(Role::ADMIN, Role::SUPER_ADMIN)) {
            $panels = ['editor' => $this->editorPanel($viewer)] + $panels;
        }

        if ($panels === []) {
            $panels['student'] = $this->studentPanel($viewer);
        }

        if (isset($panels['admin']) && count($panels) > 1) {
            $panels = ['admin' => $panels['admin']] + $panels;
        }

        $defaults = [
            'workspaceHref' => null,
            'workspaceLabel' => '',
            'showProgress' => true,
            'progressLabel' => 'Tiến độ tổng thể',
            'progress' => 0,
            'summaryLabel' => '',
            'summaryValue' => '',
            'summaryTone' => 'ok',
            'contextLabel' => '',
            'contexts' => [],
            'stats' => [],
        ];

        foreach ($panels as $key => $panel) {
            $workspace = self::ROLE_WORKSPACE[$key] ?? null;

            $panels[$key] = $panel + [
                'workspaceHref' => ($workspace !== null && Route::has($workspace['route']))
                    ? route($workspace['route'])
                    : null,
                'workspaceLabel' => $workspace['label'] ?? '',
            ] + $defaults;
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

    private const ROLE_WORKSPACE = [
        'student' => ['route' => 'student.dashboard', 'label' => 'Vào không gian học tập'],
        'parent' => ['route' => 'parent.dashboard', 'label' => 'Vào khu phụ huynh'],
        'teacher' => ['route' => 'teacher.dashboard', 'label' => 'Vào khu giáo viên'],
        'admin' => ['route' => 'admin.dashboard', 'label' => 'Vào bảng điều khiển'],
        'editor' => ['route' => 'dashboard', 'label' => 'Vào khu biên tập'],
    ];

    private const ROLE_VIEW_META = [
        'student' => ['label' => 'Học sinh', 'icon' => 'graduation-cap', 'iconClass' => 'text-[#2D7FA3]'],
        'parent' => ['label' => 'Phụ huynh', 'icon' => 'heart', 'iconClass' => 'text-[#4C88A1]'],
        'teacher' => ['label' => 'Giáo viên', 'icon' => 'users', 'iconClass' => 'text-[#5B77A8]'],
        'admin' => ['label' => 'Quản trị', 'icon' => 'shield-check', 'iconClass' => 'text-[#8A6A1C]'],
        'editor' => ['label' => 'Biên tập viên', 'icon' => 'pen-line', 'iconClass' => 'text-[#1F7A5C]'],
    ];

    private function editorPanel(User $viewer): array
    {
        $views = $edits = $deletes = 0;
        $first = null;
        foreach (\App\Support\AdminPermissions::MODULES as $key => $m) {
            if (! in_array('view', $m['actions'], true) || ! \App\Support\AdminPermissions::can($viewer, $key, 'view')) {
                continue;
            }
            $views++;
            $first ??= $m;
            if (array_filter(['create', 'update'], fn ($a) => in_array($a, $m['actions'], true) && \App\Support\AdminPermissions::can($viewer, $key, $a)) !== []) {
                $edits++;
            }
            if (in_array('delete', $m['actions'], true) && \App\Support\AdminPermissions::can($viewer, $key, 'delete')) {
                $deletes++;
            }
        }
        $firstHref = ($first !== null && ! empty($first['home']) && Route::has($first['home'])) ? route($first['home']) : route('dashboard');

        return [
            'roleKey' => 'editor',
            'roleIcon' => self::ROLE_VIEW_META['editor']['icon'],
            'roleIconClass' => self::ROLE_VIEW_META['editor']['iconClass'],
            'roleLabel' => 'Biên tập viên',
            'contextLabel' => 'Khu biên tập',
            'contexts' => [],
            'guest' => false,

            'showProgress' => false,
            'summaryLabel' => 'Mục được cấp quyền',
            'summaryValue' => $views > 0 ? $views.' mục' : 'Chưa được cấp',
            'summaryTone' => $views > 0 ? 'ok' : 'warn',

            'nextLabel' => $views > 0 ? 'Tiếp tục làm việc' : 'Chưa có quyền',
            'nextTitle' => $first['label'] ?? 'Chưa được cấp mục nào',
            'nextMeta' => $views > 0 ? 'Mở khu biên tập' : 'Liên hệ quản trị viên để được cấp quyền',
            'nextHref' => $firstHref,

            'stats' => [
                ['value' => (string) $views, 'label' => 'Được xem', 'valueClass' => 'text-[#3E79A4]'],
                ['value' => (string) $edits, 'label' => 'Thêm / sửa', 'valueClass' => 'text-[#3B9374]'],
                ['value' => (string) $deletes, 'label' => 'Được xoá', 'valueClass' => 'text-[#AF7C32]'],
            ],
        ];
    }

    private function adminPanel(): array
    {
        $pending = $this->adminDashboard->pendingCounts();
        $total = array_sum($pending);

        $queues = [
            'orders' => ['label' => 'đơn hàng chờ duyệt', 'route' => 'admin.orders.index'],
            'support' => ['label' => 'yêu cầu hỗ trợ mới', 'route' => 'admin.contact-messages.index'],
            'teachers' => ['label' => 'giáo viên chờ duyệt', 'route' => 'admin.teacher-approvals.index'],
            'reviews' => ['label' => 'đánh giá chờ kiểm duyệt', 'route' => 'admin.reviews.index'],
        ];

        $topKey = null;
        foreach ($queues as $key => $queue) {
            if ($pending[$key] > 0 && ($topKey === null || $pending[$key] > $pending[$topKey])) {
                $topKey = $key;
            }
        }

        return [
            'roleKey' => 'admin',
            'roleIcon' => self::ROLE_VIEW_META['admin']['icon'],
            'roleIconClass' => self::ROLE_VIEW_META['admin']['iconClass'],
            'roleLabel' => 'Quản trị',
            'contextLabel' => 'Khu quản trị',
            'contexts' => [],
            'guest' => false,

            'showProgress' => false,
            'summaryLabel' => 'Việc đang chờ xử lý',
            'summaryValue' => $total > 0 ? $total.' việc' : 'Đã xử lý hết',
            'summaryTone' => $total > 0 ? 'warn' : 'ok',

            'nextLabel' => $total > 0 ? 'Cần xử lý trước' : 'Hàng chờ',
            'nextTitle' => $topKey !== null
                ? $pending[$topKey].' '.$queues[$topKey]['label']
                : 'Không còn việc nào tồn đọng',
            'nextMeta' => $topKey !== null ? 'Mở khu quản trị để xử lý' : 'Mọi hàng chờ đều trống',
            'nextHref' => $topKey !== null ? route($queues[$topKey]['route']) : route('admin.dashboard'),

            'stats' => [
                ['value' => (string) $pending['orders'], 'label' => 'Đơn chờ duyệt', 'valueClass' => 'text-[#3E79A4]'],
                ['value' => (string) $pending['support'], 'label' => 'Hỗ trợ mới', 'valueClass' => 'text-[#AF7C32]'],
                ['value' => (string) $pending['teachers'], 'label' => 'GV chờ duyệt', 'valueClass' => 'text-[#3B9374]'],
            ],
        ];
    }

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
                // SỬA 15/9 — trước mọi lớp đều trỏ về CÙNG trang danh sách, nên hộp chọn lớp
                // bấm cái nào cũng ra một chỗ. Giờ vào thẳng đúng lớp đã chọn.
                ->map(fn ($room) => ['label' => $room->name, 'href' => route('teacher.classes.show', $room->id)])
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

            $childClassRoomIds = $this->classEnrollments->query()
                ->whereIn('student_id', $childIds)
                ->where('status', 'active')
                ->pluck('class_room_id');

            $assignedToChildren = $this->assignments->query()
                ->whereIn('class_room_id', $childClassRoomIds)
                ->where('status', 'published')
                ->count();

            $childContexts = \App\Models\User::query()
                ->whereIn('id', $childIds)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($child) => ['label' => $child->name, 'href' => route('parent.children.show', $child->id)])
                ->all();

            return [
                'roleKey' => 'parent',
                'roleIcon' => self::ROLE_VIEW_META['parent']['icon'],
                'roleIconClass' => self::ROLE_VIEW_META['parent']['iconClass'],
                'contextLabel' => 'Con đang theo dõi',
                'contexts' => $childContexts,
                'guest' => false,
                'roleLabel' => 'Phụ huynh',
                /*
                 * SỬA 15/9 — CON SỐ CŨ SAI NGHĨA.
                 * Trước tính phần trăm = bài nộp tuần này / TỔNG bài đã nộp từ trước tới nay.
                 * Con học chăm cả năm nộp 200 bài, tuần này nộp thêm 5 bài thì ra 2% — nhìn
                 * như con đang sa sút, trong khi thực tế là ngược lại. Càng học lâu con số
                 * càng tệ đi, đó là dấu hiệu công thức sai chứ không phải dữ liệu xấu.
                 *
                 * Giờ đo thứ phụ huynh thật sự muốn biết: trong số bài được giao, con đã nộp
                 * bao nhiêu phần.
                 */
                'progressLabel' => 'Bài đã nộp trên tổng số được giao',
                'progress' => $this->percent($submitted, max($assignedToChildren, 1)),
                'nextLabel' => 'Cần đồng hành',
                'nextTitle' => $weekCount > 0 ? 'Các con đã nộp '.$weekCount.' bài trong tuần' : 'Tuần này chưa có bài nộp mới',
                'nextMeta' => $childIds->count().' học sinh đang theo dõi',
                'nextHref' => route('parent.children.index'),
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
            // SỬA 3/11 (SEO — khách: "search Google không thấy web") — câu hỏi có từ khoá người ta hay tìm (luyện thi Tin học,
            // HSG Tin học, Chuyên Tin, tốt nghiệp THPT môn Tin học, lập trình C++ / Python). Nội dung nằm sẵn trong HTML nên
            // Google đọc được; còn khai báo FAQPage ở welcome.blade.php. 4 câu cũ giữ nguyên ở dưới.
            ['q' => 'Tin học Onthi360 là gì?', 'a' => 'Tin học Onthi360 (tinhoc.onthi360.vn) là nền tảng học và luyện thi môn Tin học trực tuyến: bài tập lập trình chấm tự động, tài liệu và bộ đề, cuộc thi – khảo sát năng lực, lớp học cho giáo viên và học sinh.'],
            ['q' => 'Có luyện thi HSG Tin học lớp 9, lớp 12 không?', 'a' => 'Có. Kho bài tập thuật toán chia theo chuyên đề và độ khó, lọc đề theo tỉnh và năm; bài lập trình được chấm bằng bộ test giống kỳ thi học sinh giỏi Tin học thật.'],
            ['q' => 'Ôn thi vào lớp 10 Chuyên Tin trên Onthi360 thế nào?', 'a' => 'Chọn lộ trình theo trường mục tiêu (Chuyên KHTN, Chuyên Sư phạm, Chuyên Amsterdam…), luyện đề tuyển sinh và bài tập thuật toán theo từng giai đoạn, làm bài có giới hạn thời gian như phòng thi.'],
            ['q' => 'Có ôn thi tốt nghiệp THPT môn Tin học không?', 'a' => 'Có. Câu hỏi trắc nghiệm và đề luyện tập theo cấu trúc đề thi tốt nghiệp THPT môn Tin học; làm xong biết ngay đúng sai và điểm số để ôn lại phần còn yếu.'],
            ['q' => 'Học lập trình C++, Python trên web được không?', 'a' => 'Được. Viết và chạy code ngay trên trình duyệt, nộp bài để hệ thống chấm tự động theo từng test, xem lại lịch sử nộp bài. Hệ thống hỗ trợ C++ và Python.'],
            ['q' => 'Giáo viên dùng Onthi360 để làm gì?', 'a' => 'Tạo lớp và giao bài, tổ chức cuộc thi, kỳ khảo sát năng lực, theo dõi điểm và nhật ký làm bài của học sinh, in bảng tổng hợp điểm và xếp giải.'],
            ['q' => 'Bài công khai có cần đăng nhập không?', 'a' => 'Khách xem được; cần đăng nhập để bắt đầu, nộp bài và lưu kết quả.'],
            ['q' => 'Quyền học và quyền dạy khác nhau thế nào?', 'a' => 'Quyền dạy của giáo viên không tự cấp quyền học cho học sinh, và ngược lại — mỗi quyền có phạm vi và thời hạn riêng, luôn hiển thị rõ trên từng học liệu.'],
            ['q' => 'Vì sao một bài học lại bị khóa?', 'a' => 'Hệ thống luôn nêu đúng lý do: thiếu quyền học liệu, giáo viên chưa mở theo tiến độ lớp, hoặc quyền đã hết hạn — không khóa mà không giải thích.'],
            ['q' => 'Chấm bài code (OJ) hoạt động thế nào?', 'a' => 'Bài code được chấm bằng bộ test/luật rõ ràng, kế thừa năng lực từ Quinhdao OJ — không gọi là "AI chấm" khi hệ thống thực chất dùng luật/test case.'],
        ];
    }
}
