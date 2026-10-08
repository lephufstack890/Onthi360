<?php

namespace App\Services\Public;

use App\Models\ClassEnrollment;
use App\Models\Competition;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\CompetitionExamRepositoryInterface;
use App\Repositories\Contracts\CompetitionRepositoryInterface;
use App\Repositories\Contracts\LeaderboardEntryRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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

    public const SCOPES = [
        self::SCOPE_ALL => 'Toàn thời gian',
        self::SCOPE_MONTH => 'Tháng này',
        self::SCOPE_CONTEST => 'Cuộc thi gần nhất',
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
        if (! is_string($scope) || ! isset(self::SCOPES[$scope])) {
            $scope = $competitionId !== null ? self::SCOPE_CONTEST : self::SCOPE_ALL;
        }

        $data = match ($scope) {
            self::SCOPE_CONTEST => $this->contestScope($competitionId, $examId, $viewer),
            self::SCOPE_CLASS => $this->classScope($classId, $viewer),
            default => $this->globalScope($scope, $viewer),
        };

        $rows = $data['rows'];

        return [
            'scope' => $scope,
            'scopes' => self::SCOPES,
            'scopeLabel' => self::SCOPES[$scope],
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
                // Ảnh đại diện trung tính của bộ giao diện, xoay theo hạng (hệ thống chưa có URL ảnh học sinh).
                'avatar' => asset('assets/rank-avatar-'.((max(1, (int) $r['rank']) - 1) % 5 + 1).'.png'),
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

        $current = $this->rank($this->metrics($from, null, null));
        $previous = $this->rank($this->metrics($from, $now->copy()->subDays(self::MOVEMENT_DAYS), null))->pluck('rank', 'uid')->all();

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

        $boards = $published->map(fn (Competition $c) => [
            'id' => $c->id,
            'title' => $c->title,
            'participants' => $c->leaderboard_entries_count,
        ])->all();

        $selected = $competitionId !== null ? $published->firstWhere('id', $competitionId) : $published->first();

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

    // ───────────────────────────── Tính số liệu & xếp hạng ─────────────────────────────

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
        return array_map(function (array $row) use ($viewer) {
            if ($viewer !== null && $row['uid'] === (int) $viewer->id) {
                $row['isYou'] = true;
                $row['name'] = (string) $viewer->name;
                $row['named'] = true;
                $row['initials'] = $this->initials((string) $viewer->name);
            }
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
