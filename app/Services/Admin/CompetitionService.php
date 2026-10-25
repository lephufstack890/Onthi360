<?php

namespace App\Services\Admin;

use App\Enums\CompetitionOrganizerType;
use App\Enums\CompetitionStatus;
use App\Enums\CompetitionType;
use App\Models\Competition;
use App\Models\CompetitionExam;
use App\Models\CompetitionRegistration;
use App\Models\LeaderboardEntry;
use App\Models\User;
use App\Notifications\CompetitionJoinDecided;
use App\Repositories\Contracts\AssessmentRepositoryInterface;
use App\Repositories\Contracts\CompetitionExamRepositoryInterface;
use App\Repositories\Contracts\CompetitionRepositoryInterface;
use App\Repositories\Contracts\LeaderboardEntryRepositoryInterface;
use App\Repositories\Contracts\TeacherProfileRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Gom truy vấn/nhãn cho admin.competitions.* (ADM-05, 11.1: vòng đời cuộc thi).
 */
class CompetitionService
{
    public function __construct(
        private CompetitionRepositoryInterface $competitions,
        private TeacherProfileRepositoryInterface $teacherProfiles,
        private AssessmentRepositoryInterface $assessments,
        private CompetitionExamRepositoryInterface $competitionExams,
        private LeaderboardEntryRepositoryInterface $leaderboardEntries,
    ) {}

    /**
     * Nhãn hiển thị cho từng trạng thái (11.1) — dùng chung cho indexData()/showData() thay
     * vì lặp lại match() ở nhiều nơi. KHÔNG còn dùng để dựng dropdown "Trạng thái" trong form
     * thêm/sửa nữa — trạng thái giờ TỰ TÍNH theo giờ hiện tại (xem Competition::computedStatus()),
     * chỉ còn "Lưu trữ" là admin tự bấm (nút riêng, xem archive()).
     */
    private const STATUS_LABELS = [
        'upcoming' => 'Sắp diễn ra',
        'ongoing' => 'Đang diễn ra',
        'pending_publish' => 'Chờ công bố',
        'published' => 'Đã công bố',
        'archived' => 'Lưu trữ',
    ];

    private const STATUS_TONES = [
        'upcoming' => 'info',
        'ongoing' => 'warning',
        'published' => 'success',
    ];

    /** @return array{types: array, assessmentOptions: array, organizerTypes: array, teacherOptions: array} */
    private function formOptions(?int $keepAssessmentId = null): array
    {
        return [
            'types' => [CompetitionType::Contest->value => 'Cuộc thi', CompetitionType::Survey->value => 'Khảo sát'],
            'courseOptions' => $this->courseOptions(), // SỬA 24/10 — ô "Lộ trình học" = khoá học
            // Đề thi luôn thuộc Tài liệu (11.1) — cuộc thi chỉ THAM CHIẾU, không tạo đề riêng.
            // SỬA 11/10 (khách: "Đề/bộ bài tham chiếu chỉ đổ ra đề thuộc loại Đề thi") — chỉ liệt kê đề
            // có Loại = "Đề thi" (AssessmentType::Exam). Khi SỬA cuộc thi mà đề đang gắn thuộc loại khác
            // (dữ liệu cũ) thì vẫn giữ nó trong danh sách để lưu lại không làm mất liên kết ngầm.
            'assessmentOptions' => $this->examAssessmentOptions($keepAssessmentId),
            'organizerTypes' => [
                CompetitionOrganizerType::Internal->value => 'Nội bộ (nền tảng tự tổ chức)',
                CompetitionOrganizerType::External->value => 'Bên ngoài tổ chức',
            ],
            // Cố vấn/đồng hành (note họp 13/8, mục 1) chỉ chọn từ giáo viên đã được duyệt (3.3).
            'teacherOptions' => $this->teacherProfiles->approvedWithUser(200)
                ->map(fn ($tp) => ['id' => $tp->user->id, 'name' => $tp->user->name])
                ->values()->all(),
        ];
    }

    /** @return array{tabs: array, competitions: array} */
    /**
     * SỬA 19/9 (10) (khách: "học sinh đăng ký cuộc thi mà admin không biết cuộc thi nào đang
     * có đơn để duyệt") — TỔNG số đơn đang chờ duyệt của TOÀN BỘ cuộc thi.
     *
     * Dùng cho viên số đỏ cạnh mục "Cuộc thi" ở menu trái (partials/sidebar-admin), để quản
     * trị viên đang đứng ở bất kỳ màn nào cũng thấy có việc đang chờ — không phải mở từng cuộc
     * thi ra dò. Cùng khuôn với ContactMessageService::openCount() / OrderService::
     * awaitingApprovalCount() đang chạy cho 2 mục khác.
     *
     * Trả 0 khi máy chủ chưa chạy migration tạo bảng đăng ký — menu chỉ không có viên số, không
     * vỡ trang.
     */
    public function pendingRegistrationCount(): int
    {
        if (! CompetitionRegistration::supported()) {
            return 0;
        }

        return CompetitionRegistration::query()
            ->where('status', CompetitionRegistration::STATUS_PENDING)
            ->count();
    }

    /** SỬA 25/10 (khách: "trang cuộc thi trong admin cũng phân trang + bộ lọc như ngoài") — số dòng mỗi trang. */
    public const INDEX_PER_PAGE = 10;

    /**
     * admin.competitions.index — danh sách có PHÂN TRANG + BỘ LỌC giống trang công khai: tên (khớp cả tên khoá học vì tên
     * hiển thị là "<loại> · <tên khoá>"), lộ trình (khoá học), loại sự kiện. Lọc ở máy chủ (?q=, ?lo_trinh=, ?loai=).
     *
     * @param  array{q?: ?string, lo_trinh?: mixed, loai?: ?string}  $filters
     */
    public function indexData(array $filters = []): array
    {
        /*
         * SỬA 4/10 (khách: "cho tách riêng đi đừng gộp với trang cuộc thi nha") — BỎ tab "Giáo
         * viên và chuyên gia". Màn đó giờ là một mục menu đứng riêng (xem partials/sidebar-admin).
         */
        $tabs = [
            ['label' => 'Cuộc thi', 'href' => route('admin.competitions.index'), 'active' => true, 'count' => $this->competitions->count()],
        ];

        $hasCourse = Competition::supportsCourse();
        $q = trim(mb_substr((string) ($filters['q'] ?? ''), 0, 100));
        $type = in_array($filters['loai'] ?? '', [CompetitionType::Contest->value, CompetitionType::Survey->value], true) ? (string) $filters['loai'] : '';
        $courseId = $hasCourse ? ((int) ($filters['lo_trinh'] ?? 0) ?: null) : null;

        $paginator = Competition::query()
            ->when($hasCourse, fn ($w) => $w->with('course:id,title')) // tên hiển thị theo khoá học (Competition::displayTitle())
            ->when($type !== '', fn ($w) => $w->where('type', $type))
            ->when($courseId !== null, fn ($w) => $w->where('course_id', $courseId))
            ->when($q !== '', function ($w) use ($q, $hasCourse) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
                $w->where(fn ($x) => $x->where('title', 'like', $like)
                    ->when($hasCourse, fn ($y) => $y->orWhereHas('course', fn ($c) => $c->where('title', 'like', $like))));
            })
            ->orderByDesc('starts_at')->orderByDesc('id')
            ->paginate(self::INDEX_PER_PAGE)
            ->withQueryString();

        $competitions = $paginator->getCollection()->map(function (Competition $c) {
            $statusValue = $c->computedStatus()->value;

            return [
                'id' => $c->id,
                'name' => $c->displayTitle(), // SỬA 25/10 — "<loại> · <tên khoá học>" khi gắn khoá học
                'image' => $c->imageUrl(), // SỬA 24/10 — cột Ảnh ở danh sách
                'type' => $c->type->value === 'contest' ? 'Cuộc thi' : 'Khảo sát',
                'startsAtLabel' => $c->starts_at?->format('d/m/Y H:i') ?? '— Chưa đặt —',
                'endsAtLabel' => $c->ends_at?->format('d/m/Y H:i') ?? '— Chưa đặt —',
                'status' => self::STATUS_LABELS[$statusValue] ?? $statusValue,
                'tone' => self::STATUS_TONES[$statusValue] ?? 'neutral',
            ];
        })->all();

        /*
         * SỬA 19/9 (10) — DẢI NHẮC đơn chờ duyệt: lấy trên TOÀN BỘ cuộc thi (không phụ thuộc trang / bộ lọc đang xem),
         * tối đa 20 cuộc thi có đơn mới nhất. pendingTotal = số đơn toàn hệ thống (cùng nguồn với viên số đỏ ở menu).
         */
        $pendingByCompetition = CompetitionRegistration::supported()
            ? CompetitionRegistration::query()
                ->selectRaw('competition_id, COUNT(*) as pending')
                ->where('status', CompetitionRegistration::STATUS_PENDING)
                ->groupBy('competition_id')
                ->pluck('pending', 'competition_id')
                ->all()
            : [];
        $needReview = $pendingByCompetition === [] ? [] : Competition::query()
            ->when($hasCourse, fn ($w) => $w->with('course:id,title'))
            ->whereIn('id', array_keys($pendingByCompetition))
            ->orderByDesc('id')->limit(20)->get()
            ->map(fn (Competition $c) => ['id' => $c->id, 'name' => $c->displayTitle(), 'pendingRegistrations' => (int) $pendingByCompetition[$c->id]])
            ->all();

        $courseOptions = $hasCourse
            ? \App\Models\Course::query()
                ->whereIn('id', Competition::query()->whereNotNull('course_id')->select('course_id'))
                ->orderBy('title')->get(['id', 'title'])
                ->map(fn ($co) => ['id' => (int) $co->id, 'title' => (string) $co->title])->all()
            : [];

        return [
            'tabs' => $tabs,
            'competitions' => $competitions,
            'paginator' => $paginator,
            'filters' => ['q' => $q, 'loai' => $type, 'lo_trinh' => $courseId],
            'courseOptions' => $courseOptions,
            'needReview' => $needReview,
            'pendingTotal' => $this->pendingRegistrationCount(),
            'pendingListed' => array_sum(array_column($needReview, 'pendingRegistrations')),
        ];
    }

    /**
     * SỬA 11/10 — danh sách đề cho ô "Đề/bộ bài tham chiếu": chỉ đề Loại "Đề thi".
     *
     * @return array<int, \App\Models\Assessment>
     */
    private function examAssessmentOptions(?int $keepId = null): array
    {
        return $this->assessments->query()
            ->where(function ($q) use ($keepId) {
                $q->where('type', \App\Enums\AssessmentType::Exam->value);
                if ($keepId !== null) {
                    $q->orWhere('id', $keepId);
                }
            })
            ->orderBy('title')
            ->get(['id', 'title'])
            ->all();
    }

    /**
     * SỬA 11/10 — chặn gửi tay id đề không phải "Đề thi" (trừ khi chính là đề đang gắn sẵn).
     *
     * @throws ValidationException
     */
    private function assertExamAssessment(mixed $assessmentId, ?int $currentId = null): void
    {
        if (blank($assessmentId) || (int) $assessmentId === (int) $currentId) {
            return;
        }

        $ok = $this->assessments->query()
            ->whereKey((int) $assessmentId)
            ->where('type', \App\Enums\AssessmentType::Exam->value)
            ->exists();

        if (! $ok) {
            throw ValidationException::withMessages(['assessment_id' => 'Chỉ chọn được đề có Loại là "Đề thi".']);
        }
    }

    /** admin.competitions.create — dữ liệu tĩnh cho form. */
    public function createFormData(): array
    {
        return $this->formOptions();
    }

    /**
     * Note họp 13/8, mục 1: cuộc thi tổ chức BỞI BÊN NGOÀI bắt buộc phải nêu rõ đơn vị tổ
     * chức + có ít nhất 1 giáo viên cố vấn/đồng hành để tăng uy tín — kiểm tra ở tầng
     * Service (không chỉ ở form) để không ai lách qua request thẳng (16 mục 3).
     *
     * @throws ValidationException
     */
    private function assertOrganizerDataValid(array $data): void
    {
        if (($data['organizer_type'] ?? CompetitionOrganizerType::Internal->value) !== CompetitionOrganizerType::External->value) {
            return;
        }

        if (trim($data['organizer_name'] ?? '') === '') {
            throw ValidationException::withMessages([
                'organizer_name' => 'Cuộc thi do bên ngoài tổ chức phải nêu rõ tên đơn vị tổ chức.',
            ]);
        }

        if (empty($data['advisor_teacher_ids'])) {
            throw ValidationException::withMessages([
                'advisor_teacher_ids' => 'Cuộc thi do bên ngoài tổ chức phải có ít nhất 1 giáo viên cố vấn/đồng hành để tăng uy tín.',
            ]);
        }
    }

    /**
     * "đúng logic" cho lịch cuộc thi: mốc sau phải >= mốc trước — chỉ kiểm khi CẢ HAI mốc
     * liên quan đều được nhập (bỏ trống 1 hoặc cả 2 mốc vẫn hợp lệ, giữ đúng hành vi cũ:
     * cuộc thi "chưa đặt lịch cụ thể", giống tinh thần isWithinWindow() ở tầng Public
     * Service — cột nào null thì không chặn thêm).
     *
     * @throws ValidationException
     */
    private function assertDatesValid(array $data): void
    {
        $startsAt = $data['starts_at'] ?? null;
        $endsAt = $data['ends_at'] ?? null;
        $publishAt = $data['publish_result_at'] ?? null;

        if ($startsAt && $endsAt && strtotime($endsAt) < strtotime($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => 'Thời điểm kết thúc phải sau (hoặc bằng) thời điểm bắt đầu.',
            ]);
        }

        if ($endsAt && $publishAt && strtotime($publishAt) < strtotime($endsAt)) {
            throw ValidationException::withMessages([
                'publish_result_at' => 'Thời điểm công bố kết quả phải sau (hoặc bằng) thời điểm kết thúc.',
            ]);
        }
    }

    /** SỬA 24/10 — khoá học cho ô "Lộ trình học" (khách: "lộ trình học = khoá học"). */
    private function courseOptions(): array
    {
        if (! Competition::supportsCourse()) {
            return [];
        }

        return \App\Models\Course::query()->orderBy('title')->get(['id', 'title'])
            ->map(fn ($c) => ['id' => (int) $c->id, 'title' => (string) $c->title])->all();
    }

    /** SỬA 24/10 (khách: "khảo sát khi tham gia sẽ nhập mật khẩu") — khảo sát phải có mật khẩu tham gia. */
    private function assertHubFields(array $data, ?Competition $existing): void
    {
        if (! Competition::supportsHubFields() || ($data['type'] ?? null) !== CompetitionType::Survey->value) {
            return;
        }

        $typed = trim((string) ($data['entry_password'] ?? ''));
        $clear = ! empty($data['clear_entry_password']);
        $keeps = $existing !== null && $existing->hasEntryPassword() && ! $clear;

        if ($typed === '' && ! $keeps && ! $clear) {
            throw ValidationException::withMessages([
                'entry_password' => 'Khảo sát cần mật khẩu tham gia — nhập mật khẩu để giáo viên gửi cho học sinh.',
            ]);
        }
    }

    /** SỬA 24/10 — lưu các trường của trang Cuộc thi mới: mô tả, nhãn mùa giải, lộ trình, ảnh bìa, mật khẩu khảo sát. */
    private function applyHubFields(Competition $competition, array $data): void
    {
        if (! Competition::supportsHubFields()) {
            return;
        }

        $attrs = [
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'edition_label' => trim((string) ($data['edition_label'] ?? '')) ?: null,
        ];
        if (Competition::supportsCourse()) {
            $attrs['course_id'] = ($data['course_id'] ?? null) ?: null;
        }

        $password = trim((string) ($data['entry_password'] ?? ''));
        if ($password !== '') {
            $attrs['entry_password'] = $password;
        } elseif (! empty($data['clear_entry_password'])) {
            $attrs['entry_password'] = null;
        }

        $image = $data['image'] ?? null;
        // SỬA 24/10 — ảnh bìa: tải ảnh riêng (ưu tiên) > chọn từ catalog > "Bỏ ảnh đang dùng". Ảnh tải lên cũ bị thay
        // thì xoá file khỏi đĩa; ảnh catalog chỉ là "catalog:<id>", không có file.
        $catalogId = \App\Support\CompetitionCover::normalize($data['cover_catalog'] ?? null);
        $keepCurrentCatalog = $catalogId !== null && $competition->image_path === \App\Support\CompetitionCover::marker($catalogId);
        if ($image instanceof \Illuminate\Http\UploadedFile || ! empty($data['remove_image']) || ($catalogId !== null && ! $keepCurrentCatalog)) {
            $old = $competition->image_path;
            if ($old && ! \App\Support\CompetitionCover::isCatalog($old) && \Illuminate\Support\Facades\Storage::disk('public')->exists($old)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($old);
            }
            $attrs['image_path'] = match (true) {
                $image instanceof \Illuminate\Http\UploadedFile => \App\Support\ImageOptimizer::store($image, 'competitions/covers', 'public'),
                ! empty($data['remove_image']) && $catalogId === null => null,
                default => \App\Support\CompetitionCover::marker($catalogId),
            };
        }

        $competition->forceFill($attrs)->save();
    }

    /** admin.competitions.store — slug tự sinh từ tiêu đề (giống Course/Product). */
    public function store(array $data): Competition
    {
        $this->assertOrganizerDataValid($data);
        $this->assertExamAssessment($data['assessment_id'] ?? null);
        $this->assertDatesValid($data);
        $this->assertHubFields($data, null);

        $baseSlug = Str::slug($data['title']);
        $slug = $baseSlug;
        $suffix = 2;
        while ($this->competitions->query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        // Trạng thái TỰ TÍNH theo giờ hiện tại (không còn nhận từ dropdown do admin tự chọn) —
        // cuộc thi mới tạo chưa thể "Lưu trữ" nên archived luôn false ở đây (xem
        // Competition::computeStatusFor()).
        $status = Competition::computeStatusFor($data['starts_at'] ?? null, $data['ends_at'] ?? null, $data['publish_result_at'] ?? null);

        $competition = $this->competitions->create([
            'title' => $data['title'],
            'slug' => $slug,
            'type' => $data['type'],
            'organizer_type' => $data['organizer_type'] ?? CompetitionOrganizerType::Internal->value,
            'organizer_name' => $data['organizer_type'] === CompetitionOrganizerType::External->value ? trim($data['organizer_name']) : null,
            'assessment_id' => ($data['assessment_id'] ?? null) ?: null, // SỬA 20/10 — form không còn ô này
            'rules' => $data['rules'] ?? null,
            'starts_at' => $data['starts_at'] ?: null,
            'ends_at' => $data['ends_at'] ?: null,
            'publish_result_at' => $data['publish_result_at'] ?: null,
            'status' => $status->value,
            'ranking_rule' => $this->buildRankingRule($data),
        ]);

        $competition->advisors()->sync(array_map('intval', $data['advisor_teacher_ids'] ?? []));
        $this->applyHubFields($competition, $data);

        return $competition;
    }

    /** admin.competitions.edit — cuộc thi hiện tại + option form. Slug KHÔNG cho sửa (giữ link). */
    public function editFormData(int $competitionId): array
    {
        $competition = $this->competitions->query()->with('advisors')->findOrFail($competitionId);

        return array_merge($this->formOptions($competition->assessment_id !== null ? (int) $competition->assessment_id : null), [
            'competition' => $competition,
            'selectedAdvisorIds' => $competition->advisors->pluck('id')->all(),
        ]);
    }

    public function update(Competition $competition, array $data): Competition
    {
        $this->assertOrganizerDataValid($data);
        $this->assertExamAssessment($data['assessment_id'] ?? null, $competition->assessment_id);
        $this->assertDatesValid($data);
        $this->assertHubFields($data, $competition);

        // SỬA 24/10 (khách: "khi sửa là lưu cái mới luôn, không có lưu trữ hay tính lại theo giờ") — Lưu = ghi đúng dữ liệu
        // mới trong form, KHÔNG tự tính lại trạng thái. Trạng thái hiển thị ở trang Cuộc thi tính theo giờ của từng kỳ thi
        // (ContestHubService). Cuộc thi lỡ ở "Lưu trữ" từ trước thì Lưu là mở lại luôn (đã bỏ chức năng lưu trữ).
        $status = $competition->status === CompetitionStatus::Archived ? CompetitionStatus::Upcoming : $competition->status;

        $updated = $this->competitions->update($competition, [
            'title' => $data['title'],
            'type' => $data['type'],
            'organizer_type' => $data['organizer_type'] ?? CompetitionOrganizerType::Internal->value,
            'organizer_name' => $data['organizer_type'] === CompetitionOrganizerType::External->value ? trim($data['organizer_name']) : null,
            'assessment_id' => ($data['assessment_id'] ?? null) ?: null, // SỬA 20/10 — form không còn ô này
            'rules' => $data['rules'] ?? null,
            'status' => $status->value,
            'ranking_rule' => $this->buildRankingRule($data),
        ]
        // Form sửa không còn ô Bắt đầu / Kết thúc / Công bố — ô nào không gửi lên thì GIỮ giá trị cũ, không xoá về trống.
        + (! empty($data['dates_in_form'])
            ? ['starts_at' => ($data['starts_at'] ?? null) ?: null, 'ends_at' => ($data['ends_at'] ?? null) ?: null]
            : array_filter(['starts_at' => $data['starts_at'] ?? null, 'ends_at' => $data['ends_at'] ?? null], fn ($v) => $v !== null && $v !== ''))
        + array_filter(['publish_result_at' => $data['publish_result_at'] ?? null], fn ($v) => $v !== null && $v !== ''));

        $updated->advisors()->sync(array_map('intval', $data['advisor_teacher_ids'] ?? []));
        $this->applyHubFields($updated, $data);

        return $updated;
    }

    /**
     * SỬA 24/10 (khách: "xoá là xoá toàn bộ cuộc thi + kỳ thi + đề liên quan, file đồ xoá hết để tránh dữ liệu rác").
     *
     * Xoá VĨNH VIỄN, theo thứ tự:
     *  1. Lượt làm bài của cuộc thi (attempts) — bài làm, điểm từng câu, lượt chấm Judge0 rơi theo cascade.
     *  2. Cuộc thi — kỳ thi, đơn đăng ký, giáo viên tổ chức, bảng xếp hạng rơi theo cascade; ảnh bìa trên đĩa.
     *  3. Thông báo "đơn tham gia cuộc thi" đã gửi cho học sinh (link sẽ chết).
     *  4. Đề của các kỳ thi (kèm ảnh bìa + PDF qua ContentService::assessmentDestroy) — CHỈ khi đề loại "Đề thi" và
     *     không còn dùng ở chỗ khác (cuộc thi khác, bài giao lớp, buổi học, tài liệu, bộ đề bán, luyện tập được giao,
     *     lượt làm ngoài cuộc thi). Đề đang dùng chỗ khác thì GIỮ, báo lại tên. Câu hỏi trong Kho câu hỏi không xoá
     *     (dùng chung cho nhiều đề).
     *
     * @return array{deleted: int, kept: array<int, string>}
     */
    public function destroy(Competition $competition): array
    {
        $id = (int) $competition->id;
        $examIds = CompetitionExam::query()->where('competition_id', $id)->pluck('id')->map(fn ($v) => (int) $v)->all();
        $assessmentIds = CompetitionExam::query()->where('competition_id', $id)->whereNotNull('assessment_id')->pluck('assessment_id')
            ->push($competition->assessment_id)
            // SỬA 24/10 — cả đề sinh từ ma trận của các kỳ.
            ->merge(\App\Models\CompetitionExamPaper::supported() && $examIds !== []
                ? \App\Models\CompetitionExamPaper::query()->whereIn('competition_exam_id', $examIds)->whereNotNull('assessment_id')->pluck('assessment_id')
                : [])
            // SỬA 24/10 — đề cũ của đề ma trận đã chốt lại / đã xoá (chỉ còn bài làm trỏ tới).
            ->merge($examIds !== []
                ? \App\Models\Attempt::query()->whereIn('competition_exam_id', $examIds)->whereNotNull('assessment_id')->distinct()->pluck('assessment_id')
                : [])
            ->filter()->map(fn ($v) => (int) $v)->unique()->values()->all();
        $imagePath = Competition::supportsHubFields() ? $competition->image_path : null;

        DB::transaction(function () use ($competition, $id, $examIds) {
            \App\Models\Attempt::query()
                ->where(fn ($q) => $q->where('competition_id', $id)->orWhereIn('competition_exam_id', $examIds === [] ? [0] : $examIds))
                ->delete();
            // Ghi rõ từng bảng (không chỉ trông vào cascade của CSDL) để chắc chắn không sót dòng rác.
            LeaderboardEntry::query()->where('competition_id', $id)
                ->orWhereIn('competition_exam_id', $examIds === [] ? [0] : $examIds)->delete();
            CompetitionRegistration::query()->where('competition_id', $id)->delete();
            if (\App\Models\CompetitionExamPaper::supported() && $examIds !== []) {
                \App\Models\CompetitionExamPaper::query()->whereIn('competition_exam_id', $examIds)->delete();
            }
            CompetitionExam::query()->where('competition_id', $id)->delete();
            $competition->advisors()->detach();
            $competition->delete();
        });

        if ($imagePath && ! \App\Support\CompetitionCover::isCatalog($imagePath) && \Illuminate\Support\Facades\Storage::disk('public')->exists($imagePath)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($imagePath);
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('notifications')) {
            // Lọc bằng PHP (đọc url trong data) thay cho LIKE — dấu "\/" trong JSON khác nhau giữa các CSDL.
            $ids = DB::table('notifications')->where('type', CompetitionJoinDecided::class)->get(['id', 'data'])
                ->filter(fn ($n) => preg_match('#/competitions/'.$id.'(/|$|\?)#', (string) (json_decode((string) $n->data, true)['url'] ?? '')))
                ->pluck('id')->all();
            if ($ids !== []) {
                DB::table('notifications')->whereIn('id', $ids)->delete();
            }
        }

        $deleted = 0;
        $kept = [];
        $content = app(ContentService::class);
        foreach (\App\Models\Assessment::query()->whereIn('id', $assessmentIds === [] ? [0] : $assessmentIds)->get() as $assessment) {
            if ($this->assessmentUsedElsewhere($assessment)) {
                $kept[] = $assessment->title;

                continue;
            }
            [$ok] = $content->assessmentDestroy($assessment);
            $ok ? $deleted++ : $kept[] = $assessment->title;
        }

        return ['deleted' => $deleted, 'kept' => $kept];
    }

    /** SỬA 24/10 — đề còn được dùng ở chỗ khác (ngoài cuộc thi vừa xoá) thì không xoá. */
    private function assessmentUsedElsewhere(\App\Models\Assessment $assessment): bool
    {
        $aid = (int) $assessment->id;
        $has = fn (string $table, string $column = 'assessment_id', ?callable $extra = null) => \Illuminate\Support\Facades\Schema::hasTable($table)
            && \Illuminate\Support\Facades\Schema::hasColumn($table, $column)
            && DB::table($table)->where($column, $aid)->when($extra, $extra)->exists();

        return ($assessment->type?->value ?? $assessment->type) !== \App\Enums\AssessmentType::Exam->value
            || $has('competition_exams')
            || $has('competition_exam_papers')
            || $has('competitions')
            || $has('attempts')
            || $has('assignments')
            || $has('session_resources')
            || $has('materials')
            || $has('product_assessments')
            || $has('practice_assignments', 'subject_id', fn ($q) => $q->where('type', 'exam'));
    }

    /**
     * admin.competitions.archive — Competition KHÔNG có deleted_at (không xóa mềm được, giống
     * Material) — "gỡ" một cuộc thi khỏi lưu hành dùng status=archived, đúng bước cuối vòng đời
     * 11.1 (Sắp diễn ra → Đang diễn ra → Chờ công bố → Đã công bố → Lưu trữ), không xóa bản ghi.
     */
    public function archive(Competition $competition): Competition
    {
        return $this->competitions->update($competition, ['status' => CompetitionStatus::Archived->value]);
    }

    /**
     * SỬA 19/8 — admin.competitions.unarchive: đảo ngược archive(). Trước đây "Lưu trữ" là
     * hành động MỘT CHIỀU tuyệt đối theo đúng chủ đích ban đầu (đã ghi rõ ở archive() — chỉ
     * "Lưu trữ" mới được phép chọn tay, còn lại đều tự tính theo giờ) — nhưng thực tế phát
     * sinh: Admin bấm nhầm nút "Lưu trữ cuộc thi" (hoặc lưu trữ để test rồi muốn mở lại) thì
     * KHÔNG CÓ đường lui nào, cuộc thi kẹt ở "Lưu trữ" vĩnh viễn dù sửa lại starts_at/ends_at
     * thế nào cũng vô ích (xem update(): $archived truyền vào computeStatusFor() lấy từ
     * $competition->status CŨ, nên 1 khi đã archived thì mọi lần update() sau đều tiếp tục ép
     * về archived, không tự "sống lại" theo giờ mới nhập — đây là nguồn gốc thắc mắc "sửa ngày
     * mà trạng thái không đổi").
     * KHÔNG có 1 "trạng thái trước khi lưu trữ" nào được lưu riêng để khôi phục — Competition
     * chỉ có 1 cột status, ghi đè archived là mất trạng thái cũ. Cách đúng để "mở lại" là TÍNH
     * LẠI trạng thái từ chính starts_at/ends_at/publish_result_at đang có, y hệt lúc save bình
     * thường (archived=false) — quay lại đúng vòng đời 11.1 tự nhiên từ đây trở đi.
     */
    public function unarchive(Competition $competition): Competition
    {
        $status = Competition::computeStatusFor(
            $competition->starts_at,
            $competition->ends_at,
            $competition->publish_result_at,
            false,
        );

        return $this->competitions->update($competition, ['status' => $status->value]);
    }

    /** @return array{competition: Competition, exams: array, assessmentOptions: array} */
    public function showData(int $competitionId): array
    {
        $competition = $this->competitions->query()
            ->withCount('leaderboardEntries')
            ->with(['assessment', 'advisors'])
            ->findOrFail($competitionId);

        return array_merge(
            ['competition' => $competition],
            $this->examSittingsData($competition),
            // SỬA 19/9 — khối "Đơn đăng ký" ngay trong màn chi tiết cuộc thi, không đẻ trang riêng:
            // admin đang xem cuộc thi nào thì duyệt đơn của cuộc thi đó, đúng chỗ.
            $this->registrationsData($competition),
        );
    }

    /**
     * SỬA 19/9 (khách: "click đăng ký tham gia thì admin sẽ duyệt") — danh sách đơn đăng ký của
     * MỘT cuộc thi, chia sẵn thành "chờ duyệt" và "đã xử lý" để màn quản trị khỏi tự lọc.
     *
     * @return array{registrationsReady: bool, pendingRegistrations: array, decidedRegistrations: array, approvedCount: int}
     */
    public function registrationsData(Competition $competition): array
    {
        // Máy chủ chưa chạy migration thì trả rỗng + cờ để view báo rõ, thay vì vỡ trang bằng
        // lỗi SQL "table not found" (xem CompetitionRegistration::supported()).
        if (! CompetitionRegistration::supported()) {
            return ['registrationsReady' => false, 'pendingRegistrations' => [], 'decidedRegistrations' => [], 'approvedCount' => 0];
        }

        $rows = CompetitionRegistration::query()
            ->where('competition_id', $competition->id)
            ->with(['student:id,name,email', 'approvedBy:id,name'])
            ->orderByDesc('requested_at')
            ->get();

        $map = fn (CompetitionRegistration $r) => [
            'id' => $r->id,
            'student' => $r->student?->name ?? 'Người dùng đã xoá',
            'email' => $r->student?->email ?? '',
            'requestedAt' => $r->requested_at?->format('d/m/Y H:i') ?? '—',
            'decidedAt' => $r->approved_at?->format('d/m/Y H:i') ?? '—',
            'decidedBy' => $r->approvedBy?->name ?? '—',
            'status' => $r->status,
            'statusLabel' => match ($r->status) {
                CompetitionRegistration::STATUS_APPROVED => 'Đã duyệt',
                CompetitionRegistration::STATUS_REJECTED => 'Từ chối',
                CompetitionRegistration::STATUS_WITHDRAWN => 'Đã rút',
                default => 'Chờ duyệt',
            },
            'tone' => match ($r->status) {
                CompetitionRegistration::STATUS_APPROVED => 'success',
                CompetitionRegistration::STATUS_REJECTED => 'danger',
                default => 'warning',
            },
            'rejectReason' => $r->reject_reason,
        ];

        /*
         * SỬA 19/9 (11) (khách: "có duyệt thì có kick nữa") — tách riêng nhóm ĐÃ DUYỆT.
         *
         * Trước đây mọi đơn không-chờ-duyệt bị gộp chung vào một khối <details> "Đã xử lý",
         * nên muốn gỡ một thí sinh ra khỏi cuộc thi thì phải bung khối đó, lẫn lộn giữa người
         * đang thi thật với người đã bị từ chối. Giờ ba nhóm rõ ràng: chờ duyệt / đã duyệt
         * (có nút gỡ) / còn lại.
         */
        return [
            'registrationsReady' => true,
            'pendingRegistrations' => $rows->where('status', CompetitionRegistration::STATUS_PENDING)->map($map)->values()->all(),
            'approvedRegistrations' => $rows->where('status', CompetitionRegistration::STATUS_APPROVED)->map($map)->values()->all(),
            'decidedRegistrations' => $rows
                ->whereNotIn('status', [CompetitionRegistration::STATUS_PENDING, CompetitionRegistration::STATUS_APPROVED])
                ->map($map)->values()->all(),
            'approvedCount' => $rows->where('status', CompetitionRegistration::STATUS_APPROVED)->count(),
        ];
    }

    /**
     * admin.competitions.registrations.approve — DUYỆT đơn. Duyệt xong học sinh vào được không
     * gian thi và mở được đề của cuộc thi (chặn thật ở AttemptService::competitionEntryDecision()).
     *
     * @throws ValidationException khi đơn không còn ở trạng thái chờ duyệt (tránh 2 admin bấm
     *                             cùng lúc, hoặc bấm lại nút cũ trên trang đã cũ).
     */
    public function approveRegistration(User $admin, int $competitionId, int $registrationId): CompetitionRegistration
    {
        $registration = $this->findPendingRegistration($competitionId, $registrationId);

        $registration->update([
            'status' => CompetitionRegistration::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $admin->id,
            'reject_reason' => null,
        ]);

        $registration->student?->notify(new CompetitionJoinDecided($registration->competition, true));

        return $registration;
    }

    /**
     * admin.competitions.registrations.reject — TỪ CHỐI đơn.
     *
     * KHÔNG xoá dòng: giữ lại để biết đã từng xin và vì sao bị từ chối, và vì
     * unique(competition_id, student_id) nên học sinh xin lại sẽ ghi đè đúng dòng này
     * (Student\CompetitionService::requestJoin()).
     */
    public function rejectRegistration(User $admin, int $competitionId, int $registrationId, ?string $reason = null): CompetitionRegistration
    {
        $registration = $this->findPendingRegistration($competitionId, $registrationId);

        $registration->update([
            'status' => CompetitionRegistration::STATUS_REJECTED,
            'approved_at' => now(),
            'approved_by' => $admin->id,
            'reject_reason' => $reason,
        ]);

        $registration->student?->notify(new CompetitionJoinDecided($registration->competition, false, $reason));

        return $registration;
    }

    /**
     * admin.competitions.registrations.revoke (SỬA 19/9 (11), khách: "có duyệt cuộc thi thì có
     * kick nữa") — GỠ một thí sinh ĐÃ DUYỆT ra khỏi cuộc thi.
     *
     * Chuyển đơn về trạng thái "từ chối" kèm lý do, đúng bộ dữ liệu mà mọi chốt chặn đang đọc:
     * AttemptService::isApprovedForCompetition() và Student\CompetitionService::examContext()
     * đều chỉ cho qua khi status = approved, nên gỡ xong là thí sinh mất quyền vào phòng thi
     * NGAY, kể cả đang mở sẵn trang (lần lưu bài / vào vòng kế tiếp sẽ bị chặn).
     *
     * CỐ Ý KHÔNG đụng tới bài đã nộp và dòng xếp hạng đã ghi: đó là dữ liệu THẬT của một lượt
     * thi đã diễn ra, xoá kèm ở đây là âm thầm phá sổ sách. Muốn bỏ kết quả thì làm riêng ở
     * màn Bảng xếp hạng — có chủ đích, có dấu vết.
     *
     * Đơn bị gỡ vẫn xin lại được (Student\CompetitionService::requestJoin() cho gửi lại sau
     * khi bị từ chối) và lại phải chờ duyệt — đúng ý "gỡ" chứ không phải "cấm vĩnh viễn".
     *
     * @throws ValidationException nếu đơn không tồn tại hoặc KHÔNG ở trạng thái đã duyệt.
     */
    public function revokeRegistration(User $admin, int $competitionId, int $registrationId, ?string $reason = null): CompetitionRegistration
    {
        if (! CompetitionRegistration::supported()) {
            throw ValidationException::withMessages([
                'registration' => 'Máy chủ chưa chạy migration cho tính năng này — chạy "php artisan migrate" rồi thử lại.',
            ]);
        }

        $registration = CompetitionRegistration::query()
            ->where('competition_id', $competitionId)
            ->with(['student', 'competition'])
            ->find($registrationId);

        if ($registration === null) {
            throw ValidationException::withMessages(['registration' => 'Không tìm thấy đơn đăng ký này trong cuộc thi.']);
        }

        if (! $registration->isApproved()) {
            throw ValidationException::withMessages([
                'registration' => 'Chỉ gỡ được thí sinh ĐANG ở trạng thái đã duyệt — tải lại trang để xem trạng thái mới nhất.',
            ]);
        }

        $reason = trim((string) $reason) !== '' ? trim((string) $reason) : null;

        $registration->update([
            'status' => CompetitionRegistration::STATUS_REJECTED,
            'approved_at' => now(),
            'approved_by' => $admin->id,
            'reject_reason' => $reason ?? 'Bị ban tổ chức gỡ khỏi cuộc thi.',
        ]);

        $registration->student?->notify(new CompetitionJoinDecided($registration->competition, false, $reason, true));

        return $registration;
    }

    private function findPendingRegistration(int $competitionId, int $registrationId): CompetitionRegistration
    {
        if (! CompetitionRegistration::supported()) {
            throw ValidationException::withMessages([
                'registration' => 'Máy chủ chưa chạy migration cho tính năng này — chạy "php artisan migrate" rồi thử lại.',
            ]);
        }

        $registration = CompetitionRegistration::query()
            ->where('competition_id', $competitionId)
            ->with(['student', 'competition'])
            ->find($registrationId);

        if ($registration === null) {
            throw ValidationException::withMessages(['registration' => 'Không tìm thấy đơn đăng ký này trong cuộc thi.']);
        }

        if (! $registration->isPending()) {
            throw ValidationException::withMessages(['registration' => 'Đơn này đã được xử lý rồi — tải lại trang để xem trạng thái mới nhất.']);
        }

        return $registration;
    }

    /** @return array{exams: array, assessmentOptions: array} */
    public function examSittingsData(Competition $competition): array
    {
        $exams = $this->competitionExams->forCompetition($competition->id)->map(fn (CompetitionExam $e) => [
            'id' => $e->id,
            'title' => $e->displayTitle(),
            'hasCustomTitle' => $e->title !== null,
            'assessmentId' => $e->assessment_id,
            'assessmentTitle' => $e->assessment->title ?? '— Không gắn đề —',
            'startsAt' => $e->starts_at,
            'endsAt' => $e->ends_at,
            'assignmentMode' => $e->assignmentMode(), // SỬA 24/10 — mã đề hoán vị
            'formCount' => $e->formCount(),
            'entriesCount' => $e->leaderboardEntries()->count(),
        ])->all();

        // SỬA 20/10 (khách: "Đề tham chiếu trong kỳ thi chỉ lấy loại Đề thi") — chỉ đề Loại "Đề thi"; đề đang gắn sẵn ở các
        // kỳ thi của cuộc thi này (kể cả loại khác, gắn từ trước) vẫn có trong danh sách để form sửa không bị đổi đề ngầm.
        $keepIds = array_values(array_filter(array_map(fn ($e) => $e['assessmentId'], $exams)));

        return [
            'exams' => $exams,
            'assessmentOptions' => $this->assessments->query()
                ->where(function ($q) use ($keepIds) {
                    $q->where('type', \App\Enums\AssessmentType::Exam->value);
                    if ($keepIds !== []) {
                        $q->orWhereIn('id', $keepIds);
                    }
                })
                ->orderBy('title')->get(['id', 'title'])->all(),
        ];
    }

    /** @return array<string, array> */
    public function examValidationRules(): array
    {
        return [
            'assessment_id' => ['required', 'integer', 'exists:assessments,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            // SỬA 24/10 — mã đề hoán vị (chỉ cuộc thi); không gửi = giữ nguyên giá trị cũ.
            'assignment_mode' => ['nullable', 'string', 'in:random,choice'],
            'form_count' => ['nullable', 'integer', 'min:1', 'max:'.\App\Support\ContestForms::MAX],
        ];
    }

    /**
     * "đúng logic" cho lịch kỳ thi — cùng quy tắc với assertDatesValid() ở cấp cuộc thi:
     * kết thúc phải >= bắt đầu, chỉ kiểm khi cả 2 mốc đều được nhập.
     *
     * @throws ValidationException
     */
    private function assertExamDatesValid(array $data): void
    {
        $startsAt = $data['starts_at'] ?? null;
        $endsAt = $data['ends_at'] ?? null;

        if ($startsAt && $endsAt && strtotime($endsAt) < strtotime($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => 'Thời điểm kết thúc kỳ thi phải sau (hoặc bằng) thời điểm bắt đầu.',
            ]);
        }
    }

    /** admin.competitions.exams.store — thêm 1 kỳ thi mới, order tự tăng sau kỳ thi cuối cùng. */
    public function storeExam(Competition $competition, array $data): CompetitionExam
    {
        $this->assertExamDatesValid($data);
        $this->assertExamAssessment($data['assessment_id'] ?? null); // SỬA 20/10 — chỉ đề Loại "Đề thi"

        $nextOrder = ((int) $this->competitionExams->query()->where('competition_id', $competition->id)->max('order')) + 1;

        return $this->competitionExams->create([
            'competition_id' => $competition->id,
            'assessment_id' => $data['assessment_id'],
            'title' => trim($data['title'] ?? '') ?: null,
            'order' => $nextOrder,
            'starts_at' => $data['starts_at'] ?: null,
            'ends_at' => $data['ends_at'] ?: null,
        ] + $this->examFormAttributes($data));
    }

    /**
     * @throws ValidationException nếu đổi đề tham chiếu của 1 kỳ thi ĐÃ có lượt xếp hạng —
     * đổi đề lúc này sẽ làm dữ liệu đã tính (theo đề cũ) không còn khớp với đề mới, tương tự
     * lý do deleteExam() chặn xoá khi đã có dữ liệu.
     */
    public function updateExam(CompetitionExam $exam, array $data): CompetitionExam
    {
        $this->assertExamDatesValid($data);
        $this->assertExamAssessment($data['assessment_id'] ?? null, $exam->assessment_id); // SỬA 20/10 — giữ đề cũ thì cho qua

        if ((int) $data['assessment_id'] !== $exam->assessment_id && $exam->leaderboardEntries()->exists()) {
            throw ValidationException::withMessages([
                'assessment_id' => 'Kỳ thi này đã có dữ liệu xếp hạng — không thể đổi đề tham chiếu (sẽ làm sai lệch dữ liệu đã tính).',
            ]);
        }

        return $this->competitionExams->update($exam, [
            'assessment_id' => $data['assessment_id'],
            'title' => trim($data['title'] ?? '') ?: null,
            'starts_at' => $data['starts_at'] ?: null,
            'ends_at' => $data['ends_at'] ?: null,
        ] + $this->examFormAttributes($data));
    }

    /** SỬA 24/10 — 2 trường mã đề hoán vị của kỳ thi; chưa migrate / không gửi thì bỏ qua. */
    private function examFormAttributes(array $data): array
    {
        if (! CompetitionExam::supportsForms()) {
            return [];
        }

        $out = [];
        if (($data['assignment_mode'] ?? null) !== null) {
            $out['assignment_mode'] = $data['assignment_mode'];
        }
        if (($data['form_count'] ?? null) !== null) {
            $out['form_count'] = (int) $data['form_count'];
        }

        return $out;
    }

    /**
     * admin.competitions.exams.destroy — kỳ thi đã có lượt xếp hạng riêng (scope=
     * competition_exam) thì KHÔNG cho xoá trực tiếp (mất dữ liệu đã tính mà không cảnh
     * báo) — admin cần biết trước khi xoá, giống cách Material/Assessment chặn xoá khi
     * đang có phụ thuộc.
     *
     * @throws ValidationException
     */
    public function deleteExam(CompetitionExam $exam): void
    {
        if ($exam->leaderboardEntries()->exists()) {
            throw ValidationException::withMessages([
                'exam' => 'Kỳ thi này đã có dữ liệu xếp hạng — không thể xoá trực tiếp.',
            ]);
        }

        $this->competitionExams->delete($exam);
    }

    /**
     * admin.competitions.recompute-aggregate — "Tính tổng từ các kỳ thi": cộng điểm mọi kỳ
     * thi (scope=competition_exam) theo user_id thành bảng TỔNG của cuộc thi (scope=
     * competition), thay cho việc tự bịa 1 công thức tính điểm "sống" chạy ngầm trong
     * pipeline chấm bài (Attempt/grading) — vốn nằm ngoài phạm vi lần dựng luồng khách vãng
     * lai này và rủi ro hơn nhiều. Thao tác này MINH BẠCH (admin bấm mới chạy, không tự
     * động), có thể chạy lại bất cứ lúc nào sau khi có kỳ thi mới hoặc điểm mới.
     *
     * @throws ValidationException nếu cuộc thi chưa có kỳ thi nào
     */
    public function recomputeAggregateFromExams(Competition $competition): int
    {
        $examIds = $this->competitionExams->forCompetition($competition->id)->pluck('id');

        if ($examIds->isEmpty()) {
            throw ValidationException::withMessages([
                'exams' => 'Cuộc thi chưa có kỳ thi nào để tính tổng.',
            ]);
        }

        $totals = $this->leaderboardEntries->query()
            ->where('scope', 'competition_exam')
            ->whereIn('competition_exam_id', $examIds)
            ->selectRaw('user_id, sum(score) as total_score')
            ->groupBy('user_id')
            ->orderByDesc('total_score')
            ->get();

        $now = now();
        $rows = $totals->values()->map(fn ($row, int $i) => [
            'scope' => 'competition',
            'competition_id' => $competition->id,
            'competition_exam_id' => null,
            'class_room_id' => null,
            'topic' => null,
            'user_id' => $row->user_id,
            'score' => $row->total_score,
            'rank' => $i + 1,
            'tie_break' => null,
            'computed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        // Xoá bảng tổng cũ rồi ghi lại trong 1 transaction — 11.2: bảng tổng luôn phản ánh
        // đúng dữ liệu kỳ thi con hiện có tại thời điểm bấm, không cộng dồn lịch sử cũ.
        DB::transaction(function () use ($competition, $rows) {
            $this->leaderboardEntries->query()
                ->where('scope', 'competition')
                ->where('competition_id', $competition->id)
                ->delete();

            if ($rows !== []) {
                LeaderboardEntry::query()->insert($rows);
            }
        });

        return count($rows);
    }

    /**
     * 11.2: "Nêu công thức điểm, penalty, đồng điểm, kỳ tính..." — ranking_rule là JSON tự do
     * theo schema; ở đây expose thành 3 ô mô tả (text) thay vì bắt admin tự viết JSON tay.
     */
    private function buildRankingRule(array $data): ?array
    {
        $rule = array_filter([
            'scoring_note' => trim($data['scoring_note'] ?? ''),
            'penalty_note' => trim($data['penalty_note'] ?? ''),
            'tie_break_note' => trim($data['tie_break_note'] ?? ''),
        ]);

        return $rule === [] ? null : $rule;
    }
}
