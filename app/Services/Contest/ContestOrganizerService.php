<?php

namespace App\Services\Contest;

use App\Models\ClassEnrollment;
use App\Models\ClassRoom;
use App\Models\Competition;
use App\Models\CompetitionExam;
use App\Models\CompetitionRegistration;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\CompetitionService as AdminCompetitionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * SỬA 24/10 — tab "Học sinh" của BAN TỔ CHỨC trong phòng thi mới (source: ContestParticipants.jsx + contestAccess.js).
 *
 * Ai quản lý được một cuộc thi: quản trị viên, hoặc giáo viên nằm trong danh sách "Giáo viên tổ chức / cố vấn"
 * của cuộc thi (bảng competition_advisors). Giáo viên chỉ thêm được học sinh đang học (active) ở lớp mình dạy;
 * quản trị viên thêm được mọi tài khoản học sinh đang hoạt động.
 *
 * Duyệt / từ chối đơn dùng lại đúng Admin\CompetitionService (có gửi thông báo cho học sinh) — không viết lại luật.
 */
class ContestOrganizerService
{
    /** Số học sinh tối đa trả về cho một lần tìm ở hộp thoại "Thêm học sinh". */
    private const SEARCH_LIMIT = 40;

    public function __construct(private readonly AdminCompetitionService $adminCompetitions) {}

    public function canManage(?User $user, Competition $competition): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->hasRole(Role::ADMIN) || $user->hasRole(Role::SUPER_ADMIN)) {
            return true;
        }

        if (! $user->hasRole(Role::TEACHER)) {
            return false;
        }

        // SỬA 25/10 — giáo viên tổ chức của cuộc thi, HOẶC giáo viên chủ trì ít nhất 1 kỳ trong cuộc thi (quản lý danh
        // sách học sinh, duyệt đăng ký của cả cuộc thi).
        return $this->isAdvisor($user, $competition) || $this->organizesRound($user, $competition);
    }

    /**
     * SỬA 25/10 (khách: "vai trò admin và giáo viên có nút thêm kỳ thi") — mọi giáo viên / admin đang hoạt động được thêm
     * kỳ vào bất kỳ cuộc thi / khảo sát nào và trở thành NGƯỜI CHỦ TRÌ kỳ đó (bản mẫu: canCreateContestRound).
     */
    public function canCreateRound(?User $user): bool
    {
        return $user !== null && ($this->isAdmin($user) || $user->hasRole(Role::TEACHER));
    }

    /**
     * Ai quản lý được 1 kỳ (sửa, xoá, chọn đề, thiết lập ma trận): admin mọi kỳ; giáo viên kỳ mình chủ trì; kỳ cũ chưa có
     * người chủ trì thì giáo viên tổ chức của cuộc thi (bản mẫu: canManageRound).
     */
    public function canManageRound(?User $user, Competition $competition, CompetitionExam $exam): bool
    {
        if ($user === null) {
            return false;
        }
        if ($this->isAdmin($user)) {
            return true;
        }
        if (! $user->hasRole(Role::TEACHER)) {
            return false;
        }

        $organizerId = CompetitionExam::supportsRoundSetup() ? $exam->organizer_id : null;

        return $organizerId !== null ? (int) $organizerId === (int) $user->id : $this->isAdvisor($user, $competition);
    }

    private function isAdvisor(User $user, Competition $competition): bool
    {
        return $competition->relationLoaded('advisors')
            ? $competition->advisors->contains('id', $user->id)
            : $competition->advisors()->where('users.id', $user->id)->exists();
    }

    private function organizesRound(User $user, Competition $competition): bool
    {
        if (! CompetitionExam::supportsRoundSetup()) {
            return false;
        }

        return $competition->relationLoaded('examSittings')
            ? $competition->examSittings->contains(fn ($e) => (int) $e->organizer_id === (int) $user->id)
            : CompetitionExam::query()->where('competition_id', $competition->id)->where('organizer_id', $user->id)->exists();
    }

    public function isAdmin(User $user): bool
    {
        return $user->hasRole(Role::ADMIN) || $user->hasRole(Role::SUPER_ADMIN);
    }

    /** @return array<int, array{id:int, name:string, code:string}> lớp hiện trong ô lọc "Lớp học". */
    public function classOptions(User $user): array
    {
        $query = ClassRoom::query()->orderBy('name');

        if (! $this->isAdmin($user)) {
            $query->whereHas('teachers', fn ($q) => $q->where('users.id', $user->id));
        }

        return $query->limit(300)->get(['id', 'name', 'code'])
            ->map(fn (ClassRoom $c) => ['id' => (int) $c->id, 'name' => (string) $c->name, 'code' => (string) ($c->code ?? '')])
            ->all();
    }

    /**
     * Tìm học sinh để thêm vào cuộc thi (JSON cho hộp thoại).
     *
     * @return array<int, array{id:int, name:string, account:string, className:string, added:bool}>
     */
    public function searchStudents(User $user, Competition $competition, string $q, ?int $classId): array
    {
        $this->assertCanManage($user, $competition);

        $query = $this->studentScope($user, $classId);

        $q = trim($q);
        if ($q !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
            $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like));
        }

        $students = $query->orderBy('name')->limit(self::SEARCH_LIMIT)->get(['id', 'name', 'email', 'phone']);
        $ids = $students->pluck('id')->map(fn ($id) => (int) $id)->all();

        $approved = CompetitionRegistration::supported() && $ids !== []
            ? CompetitionRegistration::query()
                ->where('competition_id', $competition->id)
                ->whereIn('student_id', $ids)
                ->where('status', CompetitionRegistration::STATUS_APPROVED)
                ->pluck('student_id')->map(fn ($id) => (int) $id)->all()
            : [];

        $classNames = $this->classNamesFor($ids);

        return $students->map(fn (User $s) => [
            'id' => (int) $s->id,
            'name' => (string) $s->name,
            'account' => (string) ($s->email ?: $s->phone ?: ''),
            'className' => $classNames[(int) $s->id] ?? '',
            'added' => in_array((int) $s->id, $approved, true),
        ])->values()->all();
    }

    /**
     * Thêm học sinh vào cuộc thi = duyệt tham gia ngay (khảo sát: vào danh sách theo dõi, vẫn phải nhập mật khẩu).
     *
     * @param  array<int, int|string>  $studentIds
     * @return array{added:int, approved:int, skipped:int}
     */
    public function addStudents(User $user, Competition $competition, array $studentIds, bool $eventClosed): array
    {
        $this->assertCanManage($user, $competition);

        if (! CompetitionRegistration::supported()) {
            throw ValidationException::withMessages(['students' => 'Máy chủ chưa chạy migration — chạy "php artisan migrate" rồi thử lại.']);
        }

        if ($eventClosed) {
            throw ValidationException::withMessages(['students' => 'Sự kiện đã kết thúc, không thể thêm học sinh.']);
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $studentIds))));
        if ($ids === []) {
            throw ValidationException::withMessages(['students' => 'Chọn ít nhất một học sinh.']);
        }

        // Chỉ nhận học sinh trong phạm vi được quản lý (chặn gửi tay id ngoài phạm vi).
        $allowed = $this->studentScope($user, null)->whereIn('users.id', $ids)->pluck('users.id')->map(fn ($id) => (int) $id)->all();
        if (count($allowed) !== count($ids)) {
            throw ValidationException::withMessages(['students' => 'Có học sinh không còn hoạt động hoặc không thuộc lớp bạn quản lý. Hãy mở lại danh sách.']);
        }

        $existing = CompetitionRegistration::query()
            ->where('competition_id', $competition->id)
            ->whereIn('student_id', $ids)
            ->get()->keyBy('student_id');

        $added = 0;
        $approved = 0;
        $now = now();
        $hub = CompetitionRegistration::supportsHubFields();

        foreach ($ids as $id) {
            $row = $existing->get($id);
            if ($row !== null && $row->isApproved()) {
                continue;
            }

            $values = [
                'status' => CompetitionRegistration::STATUS_APPROVED,
                'requested_at' => $row?->requested_at ?? $now,
                'approved_at' => $now,
                'approved_by' => $user->id,
                'reject_reason' => null,
            ];
            if ($hub) {
                $values['source'] = CompetitionRegistration::SOURCE_ORGANIZER;
                $values['added_by'] = $user->id;
            }

            CompetitionRegistration::updateOrCreate(['competition_id' => $competition->id, 'student_id' => $id], $values);
            $row === null ? $added++ : $approved++;
        }

        return ['added' => $added, 'approved' => $approved, 'skipped' => count($ids) - $added - $approved];
    }

    public function review(User $user, Competition $competition, int $registrationId, bool $approve, ?string $reason = null): void
    {
        $this->assertCanManage($user, $competition);

        $approve
            ? $this->adminCompetitions->approveRegistration($user, $competition->id, $registrationId)
            : $this->adminCompetitions->rejectRegistration($user, $competition->id, $registrationId, $reason);
    }

    /**
     * SỬA 24/10 (khách: "có thể kick học sinh ra khỏi danh sách") — gỡ học sinh khỏi danh sách cuộc thi / khảo sát
     * (đánh dấu withdrawn, ẩn khỏi tab "Học sinh"). Bài đã nộp giữ nguyên. Thêm lại bằng "Thêm học sinh" như thường.
     */
    public function removeStudent(User $user, Competition $competition, int $registrationId): string
    {
        $this->assertCanManage($user, $competition);
        $registration = CompetitionRegistration::query()->where('competition_id', $competition->id)
            ->with('student:id,name')->findOrFail($registrationId);
        $registration->forceFill(['status' => CompetitionRegistration::STATUS_WITHDRAWN])->save();

        return $registration->student?->name ?? 'học sinh';
    }

    /** id học sinh → tên lớp đang học (lớp đầu tiên tìm thấy). @return array<int, string> */
    public function classNamesFor(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        $names = [];
        ClassEnrollment::query()
            ->whereIn('student_id', $studentIds)
            ->where('status', ClassEnrollment::STATUS_ACTIVE)
            ->with('classRoom:id,name')
            ->orderBy('id')
            ->get(['id', 'student_id', 'class_room_id'])
            ->each(function (ClassEnrollment $e) use (&$names) {
                $names[(int) $e->student_id] ??= (string) ($e->classRoom->name ?? '');
            });

        return $names;
    }

    /**
     * SỬA 25/10 (khách: "UI phòng thi như source mới — Các kỳ tôi tổ chức, nút Thêm kỳ thi / Thêm kỳ khảo sát, modal
     * như source") — dữ liệu cho 3 hộp thoại của ban tổ chức trong phòng thi:
     *   · Thêm kỳ (tên, thời gian, nguồn câu hỏi) — người tạo là NGƯỜI CHỦ TRÌ kỳ.
     *   · Sửa kỳ (tên, thời gian) / Xoá kỳ.
     *   · Lấy đề có sẵn (chọn đề trong ngân hàng + mã đề hoán vị / cách giao đề) — xem setRoundExam().
     * Mỗi người chỉ nhận dữ liệu của các kỳ MÌNH quản lý được (canManageRound()).
     */
    public function roundEditor(Competition $competition, User $viewer): array
    {
        $exams = CompetitionExam::query()->where('competition_id', $competition->id)
            ->with(CompetitionExam::supportsRoundSetup() ? ['organizer:id,name'] : [])->orderBy('order')->get();
        $fallbackHost = $this->organizerLabel($competition);
        $mine = $exams->filter(fn (CompetitionExam $e) => $this->canManageRound($viewer, $competition, $e))->values();

        $attempted = \App\Models\Attempt::query()->whereIn('competition_exam_id', $exams->pluck('id')->all() ?: [0])
            ->distinct()->pluck('competition_exam_id')->map(fn ($v) => (int) $v)->all();

        return [
            'isSurvey' => $competition->isSurvey(),
            'contestTitle' => $competition->displayTitle(),
            'organizerName' => (string) $viewer->name,
            'storeUrl' => route('competitions.manage.rounds.store', $competition->id),
            'formsSupported' => CompetitionExam::supportsForms(),
            'maxForms' => \App\Support\ContestForms::MAX,
            'maxBank' => self::MAX_BANK, // SỬA 25/10 — số đề tối đa chọn cho 1 kỳ
            'options' => $mine->isNotEmpty() ? $this->examOptions($viewer, $mine, $competition) : [],
            'bankTypeLabel' => self::bankTypeLabel($competition), // SỬA 25/10 — "Luyện tập" (khảo sát) / "Đề thi" (cuộc thi)
            'rounds' => $mine->mapWithKeys(fn (CompetitionExam $e) => [(int) $e->id => [
                'id' => (int) $e->id,
                'title' => (string) ($e->title ?? ''),
                'host' => $this->roundHostName($e, $fallbackHost),
                'startsAt' => $e->starts_at?->format('Y-m-d\\TH:i') ?? '',
                'endsAt' => $e->ends_at?->format('Y-m-d\\TH:i') ?? '',
                'source' => $e->sourceType($competition),
                'assessmentId' => $e->assessment_id !== null ? (int) $e->assessment_id : '',
                'assessmentIds' => $e->bankAssessmentIds(), // SỬA 25/10 — kỳ nhiều đề
                'assignmentMode' => $e->assignmentMode(),
                'formCount' => $e->formCount(),
                'locked' => in_array((int) $e->id, $attempted, true),
                'updateUrl' => route('competitions.manage.rounds.update', ['competition' => $competition->id, 'exam' => $e->id]),
                'deleteUrl' => route('competitions.manage.rounds.destroy', ['competition' => $competition->id, 'exam' => $e->id]),
                'examUrl' => route('competitions.manage.rounds.exam', ['competition' => $competition->id, 'exam' => $e->id]),
            ]])->all(),
        ];
    }

    /**
     * SỬA 25/10 (khách: "lấy từ ngân hàng đề: khảo sát lấy nguồn đề loại Luyện tập, cuộc thi lấy nguồn đề loại Đề thi")
     * — Loại đề (Assessment.type) được chọn cho kỳ của cuộc thi này. Đổi luật ở ĐÂY là đổi cả danh sách lẫn kiểm tra.
     */
    /** SỬA 25/10 — tối đa bấy nhiêu đề ngân hàng cho 1 kỳ (học sinh được bốc ngẫu nhiên 1 đề). */
    public const MAX_BANK = 10;

    public static function bankTypeFor(Competition $competition): \App\Enums\AssessmentType
    {
        // SỬA 27/10 (khách: "sửa lại lấy từ loại Đề thi") — khảo sát và cuộc thi đều lấy đề Loại "Đề thi". Kỳ khảo sát đang gắn
        // đề Luyện tập (chọn theo luật cũ) vẫn giữ và làm bài bình thường (AttemptService::roundContext()).
        return \App\Enums\AssessmentType::Exam;
    }

    public static function bankTypeLabel(Competition $competition): string
    {
        return 'Đề thi';
    }

    /**
     * Đề có sẵn để chọn: Assessment đúng Loại của cuộc thi (bankTypeFor(): khảo sát → "Luyện tập", cuộc thi → "Đề thi"),
     * trừ đề do tab Thiết lập đề tự sinh, + lịch sử dùng của CHÍNH người xem ("Bạn đã dùng · N kỳ khác" như source).
     * Đề đang gắn ở kỳ của người xem luôn có trong danh sách.
     *
     * @param  Collection<int, CompetitionExam>  $mine
     */
    private function examOptions(User $viewer, Collection $mine, Competition $competition): array
    {
        $bankType = self::bankTypeFor($competition)->value;
        $paperIds = \App\Models\CompetitionExamPaper::supported()
            ? \App\Models\CompetitionExamPaper::query()->whereNotNull('assessment_id')->pluck('assessment_id')->all()
            : [];
        $keep = $mine->flatMap(fn (CompetitionExam $e) => $e->bankAssessmentIds())->unique()->values()->all();

        $options = \App\Models\Assessment::query()
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('type', $bankType)->whereNotIn('id', $paperIds === [] ? [0] : $paperIds))
                ->orWhereIn('id', $keep === [] ? [0] : $keep))
            ->withCount('items')
            ->orderBy('title')->limit(800)->get(['id', 'title', 'duration_minutes', 'content_mode', 'total_points']);

        // Kỳ (ở mọi cuộc thi) do người xem chủ trì đang dùng từng đề.
        $usage = [];
        if (CompetitionExam::supportsRoundSetup()) {
            CompetitionExam::query()->with('competition:id,title,type,course_id')
                ->where('organizer_id', $viewer->id)->whereNotNull('assessment_id')
                ->get(['id', 'competition_id', 'assessment_id', 'title', 'starts_at'])
                ->each(function (CompetitionExam $e) use (&$usage) {
                    foreach ($e->bankAssessmentIds() as $aid) $usage[$aid][] = [ // SỬA 25/10 — tính cả kỳ nhiều đề
                        'roundId' => (int) $e->id,
                        'label' => $e->displayTitle(),
                        'date' => $e->starts_at?->format('d/m/Y') ?? 'Chưa xếp lịch',
                        'contest' => $e->competition?->displayTitle() ?? '',
                    ];
                });
        }

        return $options->map(fn ($a) => [
            'id' => (int) $a->id,
            'title' => (string) $a->title,
            'duration' => $a->duration_minutes,
            'questions' => (int) $a->items_count,
            'points' => round((float) $a->total_points, 2), // SỬA 27/10 — tổng điểm thật của đề (không mặc định 100)
            'isPdf' => $a->isPdfMode(),
            'uses' => $usage[(int) $a->id] ?? [],
        ])->all();
    }

    /**
     * Thêm (exam = null) hoặc sửa tên / thời gian một kỳ. Đề của kỳ chọn sau ở tab "Lấy đề có sẵn" (setRoundExam) hoặc
     * "Thiết lập đề" (ma trận) — đúng luồng source: "Tạo kỳ & chọn đề" / "Tạo kỳ & lập ma trận".
     */
    public function saveRound(User $user, Competition $competition, ?CompetitionExam $exam, array $data): CompetitionExam
    {
        $exam === null ? $this->assertCanCreateRound($user) : $this->assertCanManageRound($user, $competition, $exam);

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'Nhập tên kỳ '.($competition->isSurvey() ? 'khảo sát' : 'thi').'.']);
        }
        $starts = filled($data['starts_at'] ?? null) ? \Illuminate\Support\Carbon::parse($data['starts_at']) : null;
        $ends = filled($data['ends_at'] ?? null) ? \Illuminate\Support\Carbon::parse($data['ends_at']) : null;
        if ($starts === null || $ends === null) {
            throw ValidationException::withMessages(['starts_at' => 'Chọn thời gian bắt đầu và kết thúc.']);
        }
        if ($ends->lte($starts)) {
            throw ValidationException::withMessages(['ends_at' => 'Thời gian kết thúc phải sau thời gian bắt đầu.']);
        }

        $attrs = ['title' => \Illuminate\Support\Str::limit($title, 160, ''), 'starts_at' => $starts, 'ends_at' => $ends];

        if ($exam !== null) {
            $exam->update($attrs);

            return $exam;
        }

        if ($ends->isPast()) {
            throw ValidationException::withMessages(['ends_at' => 'Thời gian kết thúc phải ở trong tương lai.']);
        }

        $attrs['competition_id'] = $competition->id;
        $attrs['order'] = (int) CompetitionExam::query()->where('competition_id', $competition->id)->max('order') + 1;
        if (CompetitionExam::supportsRoundSetup()) {
            $attrs['organizer_id'] = $user->id;
            // Cuộc thi luôn lấy đề có sẵn; khảo sát chọn 1 trong 2 nguồn.
            $attrs['source_type'] = $competition->isSurvey() && ($data['source_type'] ?? '') === CompetitionExam::SOURCE_EXAM
                ? CompetitionExam::SOURCE_EXAM
                : ($competition->isSurvey() ? CompetitionExam::SOURCE_QUESTIONS : CompetitionExam::SOURCE_EXAM);
        }

        return CompetitionExam::create($attrs);
    }

    /**
     * SỬA 25/10 — tab "Lấy đề có sẵn": chọn đề trong ngân hàng cho kỳ (+ cuộc thi: số mã đề hoán vị, cách giao đề).
     * Khảo sát được đổi qua lại 2 nguồn: chọn "Từ nguồn câu hỏi" thì bỏ đề có sẵn, đề ma trận đã chốt được dùng lại;
     * chọn "Từ đề có sẵn" thì các đề ma trận chuyển về bản nháp (không giao cho học sinh nữa, không xoá).
     * Cuộc thi đã có bài làm thì không đổi đề (mỗi học sinh nộp 1 lần — đổi đề là bất công); khảo sát không khoá.
     */
    public function setRoundExam(User $user, Competition $competition, CompetitionExam $exam, array $data): CompetitionExam
    {
        $this->assertCanManageRound($user, $competition, $exam);
        $survey = $competition->isSurvey();
        $source = $survey && ($data['source_type'] ?? CompetitionExam::SOURCE_EXAM) === CompetitionExam::SOURCE_QUESTIONS
            ? CompetitionExam::SOURCE_QUESTIONS : CompetitionExam::SOURCE_EXAM;

        // SỬA 25/10 (khách: "chọn được nhiều đề trong 1 kỳ, học sinh làm thì random 1 đề") — nhận assessment_ids[] (vẫn
        // nhận assessment_id cũ). Đề đầu là assessment_id của kỳ; ≥ 2 đề thì mỗi học sinh được bốc ngẫu nhiên 1 đề.
        $ids = $source === CompetitionExam::SOURCE_EXAM
            ? collect((array) ($data['assessment_ids'] ?? []))->push($data['assessment_id'] ?? null)
                ->filter(fn ($v) => filled($v))->map(fn ($v) => (int) $v)->unique()->values()->all()
            : [];
        if ($source === CompetitionExam::SOURCE_EXAM && $ids === []) {
            throw ValidationException::withMessages(['assessment_id' => 'Chọn ít nhất một đề trong ngân hàng đề.']);
        }
        if (count($ids) > self::MAX_BANK) {
            throw ValidationException::withMessages(['assessment_id' => 'Chọn tối đa '.self::MAX_BANK.' đề cho một kỳ.']);
        }
        if (count($ids) > 1 && ! \App\Models\CompetitionExamAssessment::supported()) {
            throw ValidationException::withMessages(['assessment_id' => 'Máy chủ chưa chạy migration cho tính năng nhiều đề — báo quản trị viên chạy "php artisan migrate".']);
        }
        $current = $exam->bankAssessmentIds();
        $added = array_values(array_diff($ids, $current)); // đề đang gắn sẵn (kể cả loại khác, dữ liệu cũ) giữ được
        if ($added !== [] && \App\Models\Assessment::query()->whereIn('id', $added)->where('type', self::bankTypeFor($competition)->value)->count() !== count($added)) {
            throw ValidationException::withMessages(['assessment_id' => 'Chỉ chọn được đề có Loại là "'.self::bankTypeLabel($competition).'".']);
        }
        $same = count($ids) === count($current) && array_diff($ids, $current) === [];
        if (! $survey && ! $same && \App\Models\Attempt::query()->where('competition_exam_id', $exam->id)->exists()) {
            throw ValidationException::withMessages(['assessment_id' => 'Kỳ thi đã có bài làm — không đổi đề được nữa.']);
        }
        $multi = count($ids) > 1;

        $attrs = ['assessment_id' => $ids[0] ?? null];
        if (CompetitionExam::supportsRoundSetup()) {
            $attrs['source_type'] = $source;
        }
        if (CompetitionExam::supportsForms() && ! $survey) {
            // Nhiều đề: mỗi đề là một đề riêng, giao ngẫu nhiên — không chia mã đề hoán vị nữa.
            $attrs['assignment_mode'] = ! $multi && ($data['assignment_mode'] ?? 'random') === 'choice' ? 'choice' : 'random';
            $attrs['form_count'] = $multi ? 1 : max(1, min(\App\Support\ContestForms::MAX, (int) ($data['form_count'] ?? 1)));
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($exam, $attrs, $source, $ids) {
            $exam->update($attrs);
            $exam->syncBankAssessments($ids);
            if (\App\Models\CompetitionExamPaper::supported()) {
                $papers = \App\Models\CompetitionExamPaper::query()->where('competition_exam_id', $exam->id);
                $source === CompetitionExam::SOURCE_EXAM
                    // Đề có sẵn: đề ma trận thôi giao (về bản nháp), không xoá.
                    ? $papers->where('status', \App\Models\CompetitionExamPaper::STATUS_PUBLISHED)->update(['status' => \App\Models\CompetitionExamPaper::STATUS_DRAFT])
                    : null;
            }
        });

        return $exam;
    }

    /** Xoá kỳ chưa có bài làm (kèm đề sinh từ ma trận của kỳ). */
    public function deleteRound(User $user, Competition $competition, CompetitionExam $exam): void
    {
        $this->assertCanManageRound($user, $competition, $exam);

        if (\App\Models\Attempt::query()->where('competition_exam_id', $exam->id)->exists()) {
            throw ValidationException::withMessages(['exam' => 'Kỳ thi đã có bài làm của học sinh — không xoá được.']);
        }

        if (\App\Models\CompetitionExamPaper::supported()) {
            $papers = app(\App\Services\Contest\ContestPaperService::class);
            foreach (\App\Models\CompetitionExamPaper::query()->where('competition_exam_id', $exam->id)->get() as $paper) {
                $papers->deletePaper($paper);
            }
        }
        \App\Models\LeaderboardEntry::query()->where('competition_exam_id', $exam->id)->delete();
        $exam->delete();
    }

    /** Người chủ trì kỳ: người tạo kỳ; kỳ cũ chưa ghi thì là ban tổ chức của cuộc thi ($fallback). */
    public function roundHostName(CompetitionExam $exam, string $fallback): string
    {
        $name = CompetitionExam::supportsRoundSetup() && $exam->organizer_id !== null ? $exam->organizer?->name : null;

        return $name ?: $fallback;
    }

    /** Tên ban tổ chức của cả cuộc thi: đơn vị ngoài (nếu có) → giáo viên tổ chức → mặc định. */
    public function organizerLabel(Competition $c): string
    {
        if ($c->isExternallyOrganized() && trim((string) $c->organizer_name) !== '') {
            return trim((string) $c->organizer_name);
        }

        $names = $c->advisors->pluck('name')->filter()->take(2)->all();

        return $names !== [] ? implode(', ', $names) : 'Ban tổ chức Ôn Thi 360';
    }

    private function assertCanManage(User $user, Competition $competition): void
    {
        abort_unless($this->canManage($user, $competition), 403, 'Bạn không có quyền quản lý cuộc thi này.');
    }

    private function assertCanCreateRound(User $user): void
    {
        abort_unless($this->canCreateRound($user), 403, 'Chỉ giáo viên hoặc admin được thêm kỳ.');
    }

    public function assertCanManageRound(?User $user, Competition $competition, CompetitionExam $exam): void
    {
        abort_unless($this->canManageRound($user, $competition, $exam), 403, 'Chỉ người chủ trì kỳ này (hoặc admin) được thay đổi.');
    }

    /** Tài khoản học sinh đang hoạt động trong phạm vi người quản lý. */
    private function studentScope(User $user, ?int $classId): Builder
    {
        $query = User::query()
            ->whereHas('roles', fn ($r) => $r->where('name', Role::STUDENT))
            ->where(fn ($w) => $w->whereNull('status')->orWhere('status', 'active'));

        $classIds = null;
        if (! $this->isAdmin($user)) {
            $classIds = ClassRoom::query()->whereHas('teachers', fn ($q) => $q->where('users.id', $user->id))->pluck('id')->all();
        }
        if ($classId !== null) {
            $classIds = $classIds === null ? [$classId] : array_values(array_intersect($classIds, [$classId]));
        }

        if ($classIds !== null) {
            $query->whereIn('users.id', ClassEnrollment::query()
                ->select('student_id')
                ->whereIn('class_room_id', $classIds === [] ? [0] : $classIds)
                ->where('status', ClassEnrollment::STATUS_ACTIVE));
        }

        return $query;
    }
}
