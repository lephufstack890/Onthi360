<?php

namespace App\Services\Public;

use App\Models\ClassEnrollment;
use App\Models\Competition;
use App\Models\LeaderboardPenalty;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\CompetitionExamRepositoryInterface;
use App\Repositories\Contracts\CompetitionRepositoryInterface;
use App\Repositories\Contracts\LeaderboardEntryRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SỬA 8/10 (khách: "xây lại UI bảng xếp hạng public theo source mới rồi làm lại logic cho hợp lý với UI").
 *
 * Giao diện mới (education-main/src/components/LeaderboardPage.jsx) là bảng xếp hạng HỌC SINH — không còn
 * gắn với 1 cuộc thi như bản cũ — gồm 4 phạm vi: Toàn thời gian / Tháng này / Cuộc thi gần nhất / Lớp của tôi,
 * mỗi dòng có: hạng + biến động hạng, danh hiệu, tổng điểm, bài đã giải (AC), tỷ lệ đúng, chuỗi luyện tập,
 * số cuộc thi đã tham gia. Toàn bộ số liệu dưới đây đều tính từ dữ liệu làm bài THẬT:
 *
 *  · Nguồn         : attempt_answers (mọi lượt làm — luyện theo câu, đề luyện tập, bài giao, cuộc thi) của
 *                    những lượt đã nộp (attempts.status ≠ in_progress), chỉ tài khoản học sinh đang hoạt động.
 *  · Bài đã giải   : số CÂU KHÁC NHAU từng làm đúng (verdict = accepted hoặc score > 0). Nộp lại 1 câu
 *                    nhiều lần / làm lại ở nhiều đề thì vẫn chỉ tính 1.
 *  · Tổng điểm     : cộng điểm CAO NHẤT đạt được của từng câu đã giải (câu AC nhưng chưa ghi điểm thì lấy
 *                    điểm gốc của câu). Không cộng dồn các lần nộp lại → không thể "cày điểm" bằng cách nộp lặp.
 *  · Tỷ lệ đúng    : bài đã giải / số câu khác nhau đã được chấm.
 *  · Chuỗi luyện tập: số ngày LIÊN TIẾP (giờ Việt Nam) có nộp bài, tính tới hôm nay hoặc hôm qua.
 *  · Danh hiệu     : theo tổng số bài đã giải TOÀN THỜI GIAN (Newbie → Grandmaster), không đổi theo phạm vi.
 *  · Biến động hạng: so với thứ hạng cùng phạm vi cách đây 7 ngày; chưa có mặt hồi đó = trung tính.
 *  · Đồng hạng     : xét lần lượt tổng điểm → số bài giải → tỷ lệ đúng → ai đạt thành tích sớm hơn.
 *  · Cuộc thi      : lấy thẳng bảng xếp hạng chính thức (leaderboard_entries) của cuộc thi/kỳ thi đã công bố.
 *
 * HIỂN THỊ TÊN (SỬA 8/10, khách yêu cầu): mọi phạm vi đều hiện TÊN THẬT và tỉnh/thành của học sinh, không còn
 * chế độ ẩn danh; ô "Ẩn tên học sinh" của bản mẫu đã bỏ khỏi giao diện. (Khối "Top xuất sắc" ở trang chủ —
 * HomeService::topStudents — vẫn giữ ẩn danh, chưa đổi.)
 */
class LeaderboardService
{
    public const SCOPE_ALL = 'all-time';

    public const SCOPE_MONTH = 'month';

    public const SCOPE_CONTEST = 'contest';

    public const SCOPE_CLASS = 'class';

    /** SỬA 12/11 — tab "Tài khoản vi phạm" (App\Models\LeaderboardPenalty, admin cấu hình ở Quản trị › Bảng xếp hạng). */
    public const SCOPE_VIOLATIONS = 'violations';

    /**
     * Các tab hiện trên trang. SỬA 12/11 (khách: "bỏ 2 tab Cuộc thi gần nhất với Lớp của tôi, thay thành 1 tab Tài khoản
     * vi phạm") — 2 phạm vi cũ KHÔNG còn tab, nhưng vẫn mở được qua link cũ (?scope=contest / ?competition=… từ trang
     * cuộc thi, ?scope=class) để không làm gãy liên kết đang có — xem LEGACY_SCOPES.
     */
    public const SCOPES = [
        self::SCOPE_ALL => 'Toàn thời gian',
        self::SCOPE_MONTH => 'Tháng này',
        self::SCOPE_VIOLATIONS => 'Tài khoản vi phạm',
    ];

    private const LEGACY_SCOPES = [
        self::SCOPE_CONTEST => 'Cuộc thi',
        self::SCOPE_CLASS => 'Lớp của tôi',
    ];

    /** Số dòng tối đa đưa ra trang (tìm kiếm/phân trang chạy ở trình duyệt trên tập này). */
    private const DISPLAY_LIMIT = 500;

    /** So thứ hạng hiện tại với thứ hạng cách đây ngần này ngày để ra "biến động". */
    private const MOVEMENT_DAYS = 7;

    /** Bảng tính nặng (gộp toàn bộ lượt làm) nên nhớ tạm — tăng khi đổi cách tính để bỏ cache cũ. */
    private const CACHE_TTL = 300;

    private const CACHE_VERSION = 'v2';

    /** [số bài đã giải tối thiểu, danh hiệu] — từ cao xuống thấp. */
    private const TIERS = [
        [100, 'Grandmaster'],
        [60, 'Master'],
        [30, 'Expert'],
        [10, 'Specialist'],
        [3, 'Pupil'],
        [0, 'Newbie'],
    ];

    private const ANONYMOUS_LABEL = 'Học sinh';

    public function __construct(
        private CompetitionRepositoryInterface $competitions,
        private LeaderboardEntryRepositoryInterface $leaderboardEntries,
        private CompetitionExamRepositoryInterface $competitionExams,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function indexData(?string $scope, ?int $competitionId, ?int $examId, ?int $classId, ?User $viewer): array
    {
        // Link cũ từ trang cuộc thi (?competition=…&exam=…) chưa có scope → mở thẳng phạm vi cuộc thi.
        if (! is_string($scope) || (! isset(self::SCOPES[$scope]) && ! isset(self::LEGACY_SCOPES[$scope]))) {
            $scope = $competitionId !== null ? self::SCOPE_CONTEST : self::SCOPE_ALL;
        }

        $data = match ($scope) {
            self::SCOPE_VIOLATIONS => $this->violationsScope(),
            self::SCOPE_CONTEST => $this->contestScope($competitionId, $examId, $viewer),
            self::SCOPE_CLASS => $this->classScope($classId, $viewer),
            default => $this->globalScope($scope, $viewer),
        };

        $rows = $data['rows'];

        return [
            'scope' => $scope,
            'scopes' => self::SCOPES,
            'scopeLabel' => self::SCOPES[$scope] ?? self::LEGACY_SCOPES[$scope],
            'violations' => $data['violations'] ?? [],
            'rows' => $rows,
            'hero' => [
                'students' => $data['totalStudents'] ?? count($rows),
                'solved' => $data['totalSolved'] ?? array_sum(array_column($rows, 'ac')),
                'bestStreak' => $rows === [] ? 0 : max(array_column($rows, 'bestStreak')),
            ],
            'updatedAt' => $data['updatedAt'] ?? null,
            'rule' => $data['rule'],
            'namesLocked' => $data['namesLocked'],
            'you' => $data['you'] ?? null,
            'contest' => $data['contest'] ?? null,
            'classes' => $data['classes'] ?? [],
            'classId' => $data['classId'] ?? null,
            'className' => $data['className'] ?? null,
            'empty' => $data['empty'] ?? null,
            'displayLimit' => self::DISPLAY_LIMIT,
        ];
    }

    /**
     * SỬA 8/10 — khối "Top xuất sắc" ở trang chủ: đúng 5 học sinh hạng 1 → 5 của bảng Toàn thời gian
     * (cùng nguồn + cùng cache với trang Bảng xếp hạng nên hai nơi luôn khớp nhau), hiện tên thật.
     *
     * @return array{title: ?string, rows: array<int, array{rank:int, name:string, score:float, avatar:string}>}
     */
    public function topStudents(int $limit = 5): array
    {
        $rows = array_slice($this->globalScope(self::SCOPE_ALL, null)['rows'], 0, $limit);

        return [
            'title' => $rows === [] ? null : 'Toàn thời gian · Top '.count($rows),
            'rows' => array_map(fn (array $r) => [
                'rank' => (int) $r['rank'],
                'name' => (string) $r['name'],
                'score' => (float) $r['score'],
                // SỬA 10/10 — có ảnh đại diện thật (users.avatar_path) thì dùng; chưa có thì ảnh trung tính
                // của bộ giao diện, xoay theo hạng.
                'avatar' => $r['avatar'] ?? asset('assets/rank-avatar-'.((max(1, (int) $r['rank']) - 1) % 5 + 1).'.png'),
            ], $rows),
        ];
    }

    // ─────────────────────────── Toàn thời gian / Tháng này ───────────────────────────

    private function globalScope(string $scope, ?User $viewer): array
    {
        $base = Cache::remember(
            'leaderboard:'.self::CACHE_VERSION.':'.$scope,
            self::CACHE_TTL,
            fn () => $this->buildGlobal($scope),
        );

        $rows = $this->personalise($base['rows'], $viewer);

        return [
            'rows' => $rows,
            'totalStudents' => $base['totalStudents'],
            'totalSolved' => $base['totalSolved'],
            'updatedAt' => $base['updatedAt'],
            'you' => $this->youEntry($base['ranks'], $viewer),
            'namesLocked' => false,
            'rule' => $scope === self::SCOPE_MONTH
                ? 'Chỉ tính các bài được chấm trong tháng hiện tại. Mỗi câu chỉ tính điểm cao nhất, nên nộp lại nhiều lần không làm tăng điểm. '.$this->tieNote()
                : 'Tổng điểm là tổng điểm cao nhất của từng câu đã giải — nộp lại nhiều lần không tính thêm. Bài đã giải là số câu khác nhau làm đúng; tỷ lệ đúng = bài đã giải / số câu đã chấm; chuỗi luyện tập là số ngày liên tiếp có nộp bài. '.$this->tieNote(),
        ];
    }

    /** @return array{rows: array, totalStudents: int, totalSolved: int, updatedAt: string, ranks: array} */
    private function buildGlobal(string $scope): array
    {
        $now = Carbon::now();
        $from = $scope === self::SCOPE_MONTH ? $now->copy()->startOfMonth() : null;

        // SỬA 12/11 — tài khoản đang bị khóa không có mặt trên bảng; điểm bị trừ do vi phạm được trừ thẳng vào tổng điểm.
        $current = $this->rank($this->applyPenalties($this->metrics($from, null, null), $from, $now));
        $before = $now->copy()->subDays(self::MOVEMENT_DAYS);
        $previous = $this->rank($this->applyPenalties($this->metrics($from, $before, null), $from, $before))->pluck('rank', 'uid')->all();

        return [
            'rows' => $this->decorate($current->take(self::DISPLAY_LIMIT), $previous, true),
            'totalStudents' => $current->count(),
            'totalSolved' => (int) $current->sum('solved'),
            'updatedAt' => $now->toIso8601String(),
            // uid → [hạng, điểm] của TOÀN bảng, để báo "vị trí của bạn" kể cả khi ngoài top hiển thị.
            'ranks' => $current->mapWithKeys(fn ($r) => [$r->uid => [$r->rank, $r->points]])->all(),
        ];
    }

    // ─────────────────────────────── Cuộc thi gần nhất ───────────────────────────────

    private function contestScope(?int $competitionId, ?int $examId, ?User $viewer): array
    {
        $published = $this->competitions->query()
            ->where('status', 'published')
            ->withCount(['leaderboardEntries' => fn ($q) => $q->where('scope', 'competition')])
            ->having('leaderboard_entries_count', '>', 0)
            ->latest('publish_result_at')
            ->get();

        // SỬA 6/11 (khách: "Cuộc thi gần nhất không đổ dữ liệu") — trước đây CHỈ lấy cuộc thi đã công bố VÀ đã có bảng
        // chính thức (leaderboard_entries — chỉ sinh khi admin bấm tính lại bảng xếp hạng), nên đa số cuộc thi không hiện.
        // Giờ thêm các cuộc thi đã có bài nộp nhưng chưa có bảng chính thức → bảng TẠM TÍNH từ bài làm (ghi rõ "tạm tính").
        // Cuộc thi chính thức vẫn ưu tiên đúng như cũ. Không tính khảo sát, cuộc thi sắp diễn ra hoặc đã lưu trữ.
        $all = $published->map(fn (Competition $c) => [
            'model' => $c, 'live' => false, 'participants' => (int) $c->leaderboard_entries_count,
            'at' => ($c->publish_result_at ?? $c->ends_at ?? $c->starts_at ?? $c->created_at)?->getTimestamp() ?? 0,
        ])->concat($this->liveCompetitions($published->pluck('id')->map(fn ($v) => (int) $v)->all()))
            ->sortByDesc('at')->values();

        $boards = $all->map(fn (array $b) => [
            'id' => $b['model']->id,
            'title' => $b['model']->title.($b['live'] ? ' · tạm tính' : ''),
            'participants' => $b['participants'],
        ])->all();

        $pick = $competitionId !== null ? $all->first(fn (array $b) => (int) $b['model']->id === $competitionId) : null;
        $pick ??= $all->first();
        $selected = $pick['model'] ?? null;

        $base = [
            'namesLocked' => false,
            'rule' => 'Bảng xếp hạng chính thức của cuộc thi đã công bố kết quả: điểm là điểm bài thi theo thể lệ của ban tổ chức. Bài đã giải và tỷ lệ đúng tính trong riêng cuộc thi này.',
            'contest' => ['boards' => $boards, 'selectedId' => $selected?->id, 'examTabs' => [], 'selectedExamId' => null, 'title' => $selected?->title],
        ];

        if ($selected === null) {
            return $base + ['rows' => [], 'empty' => 'contest'];
        }

        $exams = $this->competitionExams->forCompetition($selected->id);
        $selectedExam = $examId !== null ? $exams->firstWhere('id', $examId) : null;

        if ($pick['live']) {
            return $this->liveContestBoard($selected, $exams, $selectedExam, $base, $viewer);
        }

        $raw = $selectedExam !== null
            ? $this->leaderboardEntries->entriesForCompetitionExam($selectedExam->id)
            : $this->leaderboardEntries->entriesForCompetition($selected->id);

        $shown = $raw->take(self::DISPLAY_LIMIT);
        $uids = $shown->pluck('user_id')->map(fn ($v) => (int) $v)->all();

        $scopeFilter = $selectedExam !== null
            ? ['attempts.competition_exam_id' => $selectedExam->id]
            : ['attempts.competition_id' => $selected->id];

        // Thống kê "bài giải / tỷ lệ đúng" CHỈ trong phạm vi cuộc thi/kỳ thi đang xem; không lọc theo vai trò
        // vì người đã có tên trong bảng chính thức thì luôn được tính.
        $inContest = $this->metrics(null, null, $uids, $scopeFilter, false)->keyBy(fn ($m) => (int) $m->uid);

        $ranked = $shown->values()->map(function ($e, $i) use ($inContest) {
            $m = $inContest->get((int) $e->user_id);
            $solved = (int) ($m->solved ?? 0);
            $attempted = (int) ($m->attempted ?? 0);

            return (object) [
                'uid' => (int) $e->user_id,
                'rank' => (int) ($e->rank ?: $i + 1),
                'points' => (float) $e->score,
                'solved' => $solved,
                'attempted' => $attempted,
                'last_at' => $e->computed_at,
            ];
        });

        $exam = $selectedExam;
        $base['contest']['examTabs'] = $exams->map(fn ($x) => ['id' => $x->id, 'title' => $x->displayTitle()])->all();
        $base['contest']['selectedExamId'] = $exam?->id;
        $base['contest']['title'] = $exam !== null ? $exam->displayTitle() : $selected->title;

        $rows = $this->personalise($this->decorate($ranked, [], true, false), $viewer);

        $mine = $viewer !== null ? $raw->firstWhere('user_id', $viewer->id) : null;

        return $base + [
            'rows' => $rows,
            'totalStudents' => $raw->count(),
            'totalSolved' => array_sum(array_column($rows, 'ac')),
            'updatedAt' => $raw->max('computed_at')?->toIso8601String(),
            'you' => $mine ? ['rank' => (int) $mine->rank, 'score' => (float) $mine->score, 'total' => $raw->count()] : null,
        ];
    }

    // ───────────────────────────────── Lớp của tôi ─────────────────────────────────

    private function classScope(?int $classId, ?User $viewer): array
    {
        $base = [
            'rows' => [],
            'namesLocked' => false,
            'rule' => 'Xếp hạng riêng các bạn trong lớp, tính từ toàn bộ bài đã làm. Cách tính điểm, bài đã giải và chuỗi luyện tập giống bảng Toàn thời gian.',
        ];

        if ($viewer === null) {
            return $base + ['empty' => 'guest'];
        }

        // Lớp mà người xem đang học (đã được duyệt) hoặc đang phụ trách.
        $enrolled = ClassEnrollment::query()
            ->where('student_id', $viewer->id)
            ->where('status', ClassEnrollment::STATUS_ACTIVE)
            ->with('classRoom:id,name')
            ->get()
            ->pluck('classRoom');
        $teaching = $viewer->classRoomsTeaching()->get(['class_rooms.id', 'class_rooms.name']);

        $classes = $enrolled->merge($teaching)->filter()->unique('id')->values();

        // SỬA 6/11 (khách: "Lớp của tôi không đổ dữ liệu") — trước chỉ học sinh / giáo viên của lớp mới có lớp để xem:
        //  · Phụ huynh: thêm các lớp con mình đang học (con đã liên kết, đã xác minh).
        //  · Quản trị viên: xem được mọi lớp đang có học sinh (chọn lớp ở ô "Lớp").
        $childIds = $viewer->hasRole(Role::PARENT)
            ? \App\Models\ParentLink::query()->where('parent_user_id', $viewer->id)
                ->where('status', \App\Enums\ParentLinkStatus::Verified->value)->pluck('student_user_id')->all()
            : [];
        if ($childIds !== []) {
            $classes = $classes->merge(ClassEnrollment::query()->whereIn('student_id', $childIds)
                ->where('status', ClassEnrollment::STATUS_ACTIVE)->with('classRoom:id,name')->get()->pluck('classRoom'))
                ->filter()->unique('id')->values();
        }
        if ($viewer->isRealAdmin()) {
            $classes = $classes->merge(\App\Models\ClassRoom::query()
                ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('class_enrollments')
                    ->whereColumn('class_enrollments.class_room_id', 'class_rooms.id')
                    ->where('class_enrollments.status', ClassEnrollment::STATUS_ACTIVE))
                ->orderBy('name')->get(['id', 'name']))
                ->filter()->unique('id')->values();
        }

        if ($classes->isEmpty()) {
            return $base + ['empty' => 'no-class'];
        }

        $class = $classId !== null ? $classes->firstWhere('id', $classId) : null;
        $class ??= $classes->first();

        $base['classes'] = $classes->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->all();
        $base['classId'] = $class->id;

        $payload = Cache::remember(
            'leaderboard:'.self::CACHE_VERSION.':class:'.$class->id,
            self::CACHE_TTL,
            function () use ($class) {
                $uids = ClassEnrollment::query()
                    ->where('class_room_id', $class->id)
                    ->where('status', ClassEnrollment::STATUS_ACTIVE)
                    ->pluck('student_id')
                    ->map(fn ($v) => (int) $v)
                    ->all();

                $now = Carbon::now();
                $current = $this->rank($this->metrics(null, null, $uids));
                $previous = $this->rank($this->metrics(null, $now->copy()->subDays(self::MOVEMENT_DAYS), $uids))->pluck('rank', 'uid')->all();

                return [
                    'rows' => $this->decorate($current->take(self::DISPLAY_LIMIT), $previous, true),
                    'totalStudents' => $current->count(),
                    'totalSolved' => (int) $current->sum('solved'),
                    'updatedAt' => $now->toIso8601String(),
                    'ranks' => $current->mapWithKeys(fn ($r) => [$r->uid => [$r->rank, $r->points]])->all(),
                ];
            },
        );

        $rows = $this->personalise($payload['rows'], $viewer);

        return $base + [
            'rows' => $rows,
            'totalStudents' => $payload['totalStudents'],
            'totalSolved' => $payload['totalSolved'],
            'updatedAt' => $payload['updatedAt'],
            'you' => $this->youEntry($payload['ranks'], $viewer),
            'empty' => $rows === [] ? 'class-empty' : null,
            'className' => $class->name,
        ];
    }

    /**
     * SỬA 6/11 — cuộc thi đã có bài nộp nhưng CHƯA có bảng xếp hạng chính thức (không gồm khảo sát / sắp diễn ra / lưu trữ).
     *
     * @param  array<int>  $exceptIds  cuộc thi đã có bảng chính thức
     * @return Collection<int, array{model: Competition, live: bool, participants: int, at: int}>
     */
    private function liveCompetitions(array $exceptIds): Collection
    {
        $stats = DB::table('attempts')
            ->whereNotNull('competition_id')
            ->where('status', '!=', 'in_progress')
            ->when($exceptIds !== [], fn ($q) => $q->whereNotIn('competition_id', $exceptIds))
            ->groupBy('competition_id')
            ->selectRaw('competition_id, COUNT(DISTINCT user_id) as n, MAX(updated_at) as last_at')
            ->get()
            ->keyBy('competition_id');

        if ($stats->isEmpty()) {
            return collect();
        }

        return $this->competitions->query()
            ->whereIn('id', $stats->keys()->all())
            ->whereNotIn('status', ['upcoming', 'archived'])
            ->where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', 'survey'))
            ->get()
            ->map(fn (Competition $c) => [
                'model' => $c,
                'live' => true,
                'participants' => (int) $stats[$c->id]->n,
                'at' => strtotime((string) $stats[$c->id]->last_at) ?: 0,
            ])
            ->values();
    }

    /**
     * SỬA 6/11 — bảng TẠM TÍNH cho cuộc thi chưa có bảng chính thức: xếp hạng từ bài làm trong cuộc thi / kỳ thi đang chọn,
     * cùng cách tính điểm với bảng Toàn thời gian (mỗi câu lấy điểm cao nhất), chỉ tài khoản học sinh. Nhớ tạm 2 phút.
     */
    private function liveContestBoard(Competition $competition, Collection $exams, $selectedExam, array $base, ?User $viewer): array
    {
        $filter = $selectedExam !== null
            ? ['attempts.competition_exam_id' => $selectedExam->id]
            : ['attempts.competition_id' => $competition->id];

        $payload = Cache::remember(
            'leaderboard:'.self::CACHE_VERSION.':live-contest:'.$competition->id.':'.($selectedExam?->id ?? 0),
            120,
            function () use ($filter) {
                $current = $this->rank($this->metrics(null, null, null, $filter));

                return [
                    'rows' => $this->decorate($current->take(self::DISPLAY_LIMIT), [], true, false),
                    'totalStudents' => $current->count(),
                    'totalSolved' => (int) $current->sum('solved'),
                    'updatedAt' => Carbon::now()->toIso8601String(),
                    'ranks' => $current->mapWithKeys(fn ($r) => [$r->uid => [$r->rank, $r->points]])->all(),
                ];
            },
        );

        $base['rule'] = 'Cuộc thi này chưa có bảng xếp hạng chính thức của ban tổ chức nên đây là bảng TẠM TÍNH từ bài làm của thí sinh: mỗi câu lấy điểm cao nhất, đồng điểm xét số bài giải rồi tỷ lệ đúng. Kết quả chính thức có thể khác sau khi ban tổ chức công bố.';
        $base['contest']['examTabs'] = $exams->map(fn ($x) => ['id' => $x->id, 'title' => $x->displayTitle()])->all();
        $base['contest']['selectedExamId'] = $selectedExam?->id;
        $base['contest']['title'] = ($selectedExam !== null ? $selectedExam->displayTitle() : $competition->title).' (tạm tính)';

        $rows = $this->personalise($payload['rows'], $viewer);

        return $base + [
            'rows' => $rows,
            'totalStudents' => $payload['totalStudents'],
            'totalSolved' => $payload['totalSolved'],
            'updatedAt' => $payload['updatedAt'],
            'you' => $this->youEntry($payload['ranks'], $viewer),
            'empty' => $rows === [] ? 'contest' : null,
        ];
    }

    // ───────────────────────────── Tính số liệu & xếp hạng ─────────────────────────────

    // ─────────────────────────────── Tài khoản vi phạm ───────────────────────────────

    /** Xóa bảng nhớ tạm của Toàn thời gian / Tháng này — gọi sau khi admin xử phạt / mở khóa / hủy hình phạt. */
    public static function forgetCache(): void
    {
        foreach ([self::SCOPE_ALL, self::SCOPE_MONTH] as $scope) {
            Cache::forget('leaderboard:'.self::CACHE_VERSION.':'.$scope);
        }
    }

    private static function penaltiesReady(): bool
    {
        static $ready = null;

        if ($ready === null) {
            try {
                $ready = class_exists(LeaderboardPenalty::class) && Schema::hasTable('leaderboard_penalties');
            } catch (\Throwable $e) {
                report($e);
                $ready = false;
            }
        }

        return $ready;
    }

    /**
     * Bỏ tài khoản đang bị khóa (tại thời điểm $at) và trừ điểm phạt vào tổng điểm.
     * Bảng Tháng này chỉ trừ các hình phạt phát sinh trong tháng.
     */
    private function applyPenalties(Collection $metrics, ?Carbon $from, Carbon $at): Collection
    {
        if ($metrics->isEmpty() || ! self::penaltiesReady()) {
            return $metrics;
        }

        // Tính năng xử phạt KHÔNG được làm sập bảng xếp hạng: lỗi gì cũng ghi log rồi trả bảng gốc.
        try {
            return $this->applyPenaltiesUnsafe($metrics, $from, $at);
        } catch (\Throwable $e) {
            report($e);

            return $metrics;
        }
    }

    private function applyPenaltiesUnsafe(Collection $metrics, ?Carbon $from, Carbon $at): Collection
    {
        $locked = array_flip(LeaderboardPenalty::query()->currentlyLocked($at)->pluck('user_id')->map(fn ($id) => (int) $id)->all());
        $deductions = LeaderboardPenalty::query()->effective()
            ->where('locked_at', '<=', $at)
            ->when($from, fn ($q) => $q->where('locked_at', '>=', $from))
            ->where('points_deducted', '>', 0)
            ->groupBy('user_id')
            ->selectRaw('user_id, SUM(points_deducted) as pts')
            ->pluck('pts', 'user_id')
            ->all();

        if ($locked === [] && $deductions === []) {
            return $metrics;
        }

        return $metrics
            ->reject(fn ($m) => isset($locked[(int) $m->uid]))
            ->map(function ($m) use ($deductions) {
                $minus = (float) ($deductions[(int) $m->uid] ?? 0);
                if ($minus > 0) {
                    $m = clone $m;
                    $m->points = max(0, round((float) $m->points - $minus, 2));
                }

                return $m;
            })
            ->values();
    }

    /**
     * Tổng điểm xếp hạng hiện tại (Toàn thời gian, đã trừ các hình phạt cũ) + danh hiệu của 1 tài khoản — dùng khi admin
     * xử phạt để ghi "Điểm khi khóa" (điểm TRƯỚC khi áp mức trừ lần này).
     *
     * @return array{score: float, badge: string}
     */
    public function standingFor(int $userId): array
    {
        $m = $this->metrics(null, null, [$userId], [], false)->first();
        $points = (float) ($m->points ?? 0);
        if (self::penaltiesReady()) {
            $points -= (float) LeaderboardPenalty::query()->effective()->where('user_id', $userId)->sum('points_deducted');
        }

        return ['score' => max(0, round($points, 2)), 'badge' => $this->tier((int) ($m->solved ?? 0))];
    }

    /** Tab "Tài khoản vi phạm": các tài khoản ĐANG bị khóa, mới khóa trước. */
    private function violationsScope(): array
    {
        $rows = [];
        try {
            $rows = $this->violationRows();
        } catch (\Throwable $e) {
            report($e);
        }

        return [
            'rows' => [],
            'violations' => $rows,
            'totalStudents' => 0,
            'totalSolved' => 0,
            'namesLocked' => false,
            'rule' => 'Danh sách tài khoản đang bị khóa khỏi bảng xếp hạng do vi phạm quy chế. Hết hạn khóa, tài khoản tự trở lại bảng xếp hạng; điểm đã trừ không được hoàn lại.',
            'updatedAt' => Carbon::now()->toIso8601String(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function violationRows(): array
    {
        $rows = [];
        if (self::penaltiesReady()) {
            $now = Carbon::now();
            $rows = LeaderboardPenalty::query()->currentlyLocked($now)
                ->with('user:id,name,province,avatar_path')
                ->orderByDesc('locked_at')->orderByDesc('id')
                ->limit(self::DISPLAY_LIMIT)
                ->get()
                ->map(function (LeaderboardPenalty $p) {
                    $hidden = $p->hide_name || $p->user === null;
                    $tz = 'Asia/Ho_Chi_Minh';

                    return [
                        'id' => $p->id,
                        'name' => $hidden ? $p->accountCode() : (string) $p->user->name,
                        'named' => ! $hidden,
                        'initials' => $hidden ? '' : $this->initials((string) $p->user->name),
                        'avatar' => ($hidden || $p->hide_avatar) ? asset('assets/user-avatar.png') : (\App\Services\Account\AvatarService::url($p->user->avatar_path) ?? asset('assets/user-avatar.png')),
                        'sub' => $hidden ? null : ($p->user->province ?: null),
                        'badge' => $p->badge_at_lock ?: 'Newbie',
                        'scoreAtLock' => $p->score_at_lock,
                        'lockedAt' => $p->locked_at->copy()->setTimezone($tz)->format('H:i d/m/Y'),
                        'lockedTs' => $p->locked_at->getTimestamp(),
                        'reason' => $p->reason,
                        'lockLabel' => $p->lockLabel(),
                        'lockDays' => $p->lock_days ?: 100000,
                        'points' => (float) $p->points_deducted,
                        'tokens' => (int) $p->tokens_deducted,
                        'unlockAt' => $p->unlock_at?->copy()->setTimezone($tz)->format('H:i d/m/Y'),
                        'unlockTs' => $p->unlock_at?->getTimestamp() ?? PHP_INT_MAX,
                    ];
                })->all();
        }

        return $rows;
    }

    /**
     * Gộp theo học sinh: mỗi (học sinh, câu hỏi) lấy điểm cao nhất + cờ "đã giải", rồi cộng lại.
     *
     * @param  array<int>|null  $userIds
     * @param  array<string, int>  $attemptFilter  cột attempts.* => giá trị (vd. attempts.competition_id)
     * @return Collection<int, object{uid:int, points:float, solved:int, attempted:int, last_at:string}>
     */
    private function metrics(?Carbon $from, ?Carbon $to, ?array $userIds, array $attemptFilter = [], bool $studentsOnly = true): Collection
    {
        if ($userIds !== null && $userIds === []) {
            return collect();
        }

        $stamp = 'COALESCE(attempt_answers.graded_at, attempt_answers.updated_at)';

        $inner = DB::table('attempt_answers')
            ->join('attempts', 'attempts.id', '=', 'attempt_answers.attempt_id')
            ->join('questions', 'questions.id', '=', 'attempt_answers.question_id')
            ->join('users', 'users.id', '=', 'attempts.user_id')
            ->whereNull('users.deleted_at')
            ->where('attempts.status', '!=', 'in_progress')
            // Lượt đã được chấm: verdict đã chốt, hoặc đã có điểm (trắc nghiệm / điền đáp án).
            ->where(function ($w) {
                $w->whereNotIn('attempt_answers.verdict', ['pending', 'queued', 'judging'])
                    ->orWhereNotNull('attempt_answers.score');
            })
            ->when($studentsOnly, function ($q) {
                $q->where('users.status', 'active')->whereExists(function ($e) {
                    $e->select(DB::raw(1))->from('role_user')
                        ->join('roles', 'roles.id', '=', 'role_user.role_id')
                        ->whereColumn('role_user.user_id', 'users.id')
                        ->where('roles.name', Role::STUDENT);
                });
            })
            ->when($userIds !== null, fn ($q) => $q->whereIn('attempts.user_id', $userIds))
            ->when($from, fn ($q) => $q->whereRaw($stamp.' >= ?', [$from->toDateTimeString()]))
            ->when($to, fn ($q) => $q->whereRaw($stamp.' <= ?', [$to->toDateTimeString()]));

        foreach ($attemptFilter as $column => $value) {
            $inner->where($column, $value);
        }

        $inner->groupBy('attempts.user_id', 'attempt_answers.question_id')
            ->selectRaw(
                'attempts.user_id as uid, attempt_answers.question_id as qid, '
                ."MAX(CASE WHEN attempt_answers.score > 0 THEN attempt_answers.score WHEN attempt_answers.verdict = 'accepted' THEN questions.points ELSE 0 END) as best, "
                ."MAX(CASE WHEN attempt_answers.verdict = 'accepted' OR attempt_answers.score > 0 THEN 1 ELSE 0 END) as ok, "
                ."MAX($stamp) as last_at"
            );

        return DB::query()->fromSub($inner, 't')
            ->selectRaw('uid, SUM(CASE WHEN ok = 1 THEN best ELSE 0 END) as points, SUM(ok) as solved, COUNT(*) as attempted, MAX(last_at) as last_at')
            ->groupBy('uid')
            ->get();
    }

    /**
     * Xếp hạng: chỉ người đã giải ≥ 1 câu. Đồng điểm xét số bài giải → tỷ lệ đúng → ai đạt sớm hơn → id.
     *
     * @return Collection<int, object{uid:int, rank:int, points:float, solved:int, attempted:int, last_at:string}>
     */
    private function rank(Collection $metrics): Collection
    {
        return $metrics
            ->filter(fn ($m) => (int) $m->solved >= 1)
            ->map(fn ($m) => (object) [
                'uid' => (int) $m->uid,
                'points' => round((float) $m->points, 2),
                'solved' => (int) $m->solved,
                'attempted' => (int) $m->attempted,
                'last_at' => (string) $m->last_at,
            ])
            ->sort(function ($a, $b) {
                $accA = $a->attempted > 0 ? $a->solved / $a->attempted : 0;
                $accB = $b->attempted > 0 ? $b->solved / $b->attempted : 0;

                return [$b->points, $b->solved, $accB, $a->last_at, $a->uid] <=> [$a->points, $a->solved, $accA, $b->last_at, $b->uid];
            })
            ->values()
            ->map(function ($m, $i) {
                $m->rank = $i + 1;

                return $m;
            });
    }

    /**
     * Biến các dòng đã xếp hạng thành dữ liệu cho giao diện.
     *
     * @param  Collection<int, object>  $ranked
     * @param  array<int, int>  $previousRanks  uid → hạng cách đây MOVEMENT_DAYS ngày
     * @param  bool|null  $named  true = kèm tên thật + tỉnh/thành; null/false = ẩn danh (hiện không còn nơi nào dùng)
     */
    private function decorate(Collection $ranked, array $previousRanks, ?bool $named, bool $withMovement = true): array
    {
        if ($ranked->isEmpty()) {
            return [];
        }

        $uids = $ranked->pluck('uid')->all();
        $named = (bool) $named;

        $lifetime = $this->metrics(null, null, $uids, [], false)->keyBy(fn ($m) => (int) $m->uid);
        $streaks = $this->streaks($uids);
        $contests = $this->contestCounts($uids);
        $users = $named ? User::query()->whereIn('id', $uids)->get(['id', 'name', 'province'])->keyBy('id') : collect();

        return $ranked->map(function ($r) use ($previousRanks, $named, $lifetime, $streaks, $contests, $users, $withMovement) {
            $solvedAllTime = (int) ($lifetime->get($r->uid)->solved ?? $r->solved);
            $streak = $streaks[$r->uid] ?? ['current' => 0, 'best' => 0];
            $user = $users->get($r->uid);
            $name = $named && $user ? (string) $user->name : self::ANONYMOUS_LABEL.' #'.$r->rank;
            $province = $named && $user && filled($user->province) ? (string) $user->province : null;
            $accuracy = $r->attempted > 0 ? (int) round($r->solved / $r->attempted * 100) : 0;
            $joined = (int) ($contests[$r->uid] ?? 0);
            $last = $r->last_at !== '' && $r->last_at !== null ? Carbon::parse($r->last_at)->format('d/m/Y') : '—';

            $movement = null;
            if ($withMovement && isset($previousRanks[$r->uid])) {
                $movement = (int) $previousRanks[$r->uid] - $r->rank; // dương = lên hạng
            }

            return [
                'uid' => $r->uid, // chỉ dùng nội bộ để nhận ra dòng của người xem — được bỏ trước khi ra trang.
                'rank' => $r->rank,
                'name' => $name,
                'named' => $named && $user !== null,
                'initials' => $named && $user ? $this->initials((string) $user->name) : '',
                'sub' => $province ?? ($r->attempted.' bài đã thử'),
                'attempted' => (int) $r->attempted, // SỬA 6/11 — dòng phụ "Tỉnh • N bài đã thử" ở giao diện mới
                'badge' => $this->tier($solvedAllTime),
                'movement' => $movement,
                'score' => $r->points,
                'ac' => $r->solved,
                'accuracy' => max(0, min(100, $accuracy)),
                'streak' => $streak['current'],
                'bestStreak' => $streak['best'],
                'contests' => $joined,
                'isYou' => false,
                'details' => [
                    $named ? ['Tỉnh / thành', $province ?? 'Chưa cập nhật'] : ['Bài đã thử', $r->attempted.' bài'],
                    ['Cuộc thi đã tham gia', $joined.' cuộc thi'],
                    ['Chuỗi hiện tại', $streak['current'].' ngày'],
                    ['Chuỗi dài nhất', $streak['best'].' ngày'],
                    ['Hoạt động gần nhất', $last],
                ],
            ];
        })->values()->all();
    }

    /**
     * Đánh dấu dòng của người đang xem (hiện tên thật của CHÍNH họ) và bỏ uid khỏi dữ liệu ra trang.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function personalise(array $rows, ?User $viewer): array
    {
        // SỬA 10/10 (khách: "cập nhật avatar rồi mà trong bảng xếp hạng không chịu update") — bảng chỉ
        // hiện chữ cái đầu, chưa bao giờ đọc users.avatar_path. Ảnh đại diện lấy Ở ĐÂY (mỗi lượt xem, 1
        // truy vấn nhẹ theo id) chứ KHÔNG đưa vào dữ liệu nhớ tạm 5 phút của decorate(): đổi ảnh xong
        // là thấy ngay, không phải đợi hết hạn nhớ tạm. Chỉ dòng ĐÃ hiện tên thật mới kèm ảnh — dòng ẩn
        // danh không lộ ảnh của ai.
        $namedIds = [];
        foreach ($rows as $row) {
            if (! empty($row['named']) || ($viewer !== null && $row['uid'] === (int) $viewer->id)) {
                $namedIds[] = (int) $row['uid'];
            }
        }
        $avatars = $namedIds === []
            ? []
            : User::query()->whereIn('id', array_unique($namedIds))->pluck('avatar_path', 'id')->all();

        return array_map(function (array $row) use ($viewer, $avatars) {
            if ($viewer !== null && $row['uid'] === (int) $viewer->id) {
                $row['isYou'] = true;
                $row['name'] = (string) $viewer->name;
                $row['named'] = true;
                $row['initials'] = $this->initials((string) $viewer->name);
                $avatars[$row['uid']] = $viewer->avatar_path;
            }
            $row['avatar'] = \App\Services\Account\AvatarService::url($avatars[$row['uid']] ?? null);
            unset($row['uid']);

            return $row;
        }, $rows);
    }

    private function youEntry(array $ranks, ?User $viewer): ?array
    {
        if ($viewer === null || ! isset($ranks[$viewer->id])) {
            return null;
        }

        return ['rank' => $ranks[$viewer->id][0], 'score' => $ranks[$viewer->id][1], 'total' => count($ranks)];
    }

    private function tier(int $solved): string
    {
        foreach (self::TIERS as [$min, $label]) {
            if ($solved >= $min) {
                return $label;
            }
        }

        return 'Newbie';
    }

    private function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return mb_strtoupper(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), array_slice($words, -2))));
    }

    private function tieNote(): string
    {
        return 'Đồng điểm thì xét số bài giải, rồi tỷ lệ đúng, rồi ai đạt thành tích sớm hơn. Biến động so với 7 ngày trước.';
    }

    // ───────────────────────────── Chuỗi ngày & số cuộc thi ─────────────────────────────

    /**
     * Chuỗi ngày liên tiếp có nộp bài (giờ Việt Nam) trong 400 ngày gần nhất.
     *
     * @param  array<int>  $userIds
     * @return array<int, array{current:int, best:int}>
     */
    private function streaks(array $userIds): array
    {
        $stamp = 'COALESCE(attempt_answers.graded_at, attempt_answers.updated_at)';
        $since = Carbon::now()->subDays(400)->startOfDay()->toDateTimeString();

        $days = DB::table('attempt_answers')
            ->join('attempts', 'attempts.id', '=', 'attempt_answers.attempt_id')
            ->whereIn('attempts.user_id', $userIds)
            ->where('attempts.status', '!=', 'in_progress')
            ->where(function ($w) {
                $w->where('attempt_answers.submission_count', '>', 0)
                    ->orWhereNotNull('attempt_answers.graded_at')
                    ->orWhereNotNull('attempt_answers.score');
            })
            ->whereRaw($stamp.' >= ?', [$since])
            ->selectRaw("attempts.user_id as uid, DATE($stamp) as d")
            ->distinct()
            ->get()
            ->groupBy('uid');

        $today = Carbon::now()->startOfDay();
        $out = [];

        foreach ($userIds as $uid) {
            $dates = ($days->get($uid) ?? collect())
                ->pluck('d')
                ->map(fn ($d) => Carbon::parse($d)->startOfDay()->getTimestamp())
                ->unique()
                ->sort()
                ->values()
                ->all();

            $best = 0;
            $run = 0;
            $prev = null;
            foreach ($dates as $ts) {
                $run = ($prev !== null && $ts - $prev === 86400) ? $run + 1 : 1;
                $best = max($best, $run);
                $prev = $ts;
            }

            // Chuỗi "hiện tại" còn hiệu lực khi ngày cuối là hôm nay hoặc hôm qua.
            $current = 0;
            if ($prev !== null && $today->getTimestamp() - $prev <= 86400) {
                $current = $run;
            }

            $out[$uid] = ['current' => $current, 'best' => $best];
        }

        return $out;
    }

    /**
     * Số cuộc thi khác nhau mỗi học sinh đã nộp bài.
     *
     * @param  array<int>  $userIds
     * @return array<int, int>
     */
    private function contestCounts(array $userIds): array
    {
        return DB::table('attempts')
            ->whereIn('user_id', $userIds)
            ->whereNotNull('competition_id')
            ->where('status', '!=', 'in_progress')
            ->selectRaw('user_id as uid, COUNT(DISTINCT competition_id) as total')
            ->groupBy('user_id')
            ->pluck('total', 'uid')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
