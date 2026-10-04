<?php

namespace App\Services\Public;

use App\Enums\QuestionType;
use App\Models\Assessment;
use App\Models\AssessmentItem;
use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Models\User;
use App\Repositories\Contracts\AssessmentRepositoryInterface;
use App\Repositories\Contracts\AttemptRepositoryInterface;
use App\Repositories\Contracts\TagRepositoryInterface;
use App\Support\PracticeFilters;
use App\Support\PracticeQuestionPool;
use App\Support\QuestionDifficulty;
use App\Support\QuestionOrder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PracticeService
{
    /*
     * SỬA 3/10 (khách: "trang luyện tập mỗi lần click vào khách complain là chậm, sợ sau này
     * nhiều người dùng không ổn") — NHỚ TẠM phần dữ liệu GIỐNG NHAU VỚI MỌI KHÁCH.
     *
     * Trang này trước nay dựng lại từ đầu cho TỪNG lượt mở, kể cả phần chẳng liên quan gì tới
     * người đang xem: 60 câu kèm thứ tự ưu tiên, tổng lượt nộp / lượt đúng của từng câu, số
     * lượt làm của từng đề, danh sách chuyên đề. 100 khách mở trang là 100 lần chạy y hệt.
     *
     * Phần RIÊNG của người đang xem (đã làm câu nào, điểm bao nhiêu) VẪN truy vấn tươi mỗi
     * lượt — không bao giờ nhớ tạm, nếu không người này sẽ thấy tiến độ của người khác.
     *
     * 60 giây là cố ý ngắn: đủ để gánh được lúc đông người (trong 1 phút dù 1000 lượt mở thì
     * cũng chỉ 1 lần chạy thật), mà câu hỏi/đề vừa phát hành thì chậm nhất 1 phút là thấy.
     * Muốn thấy ngay thì chạy `php artisan cache:clear`.
     */
    private const CACHE_TTL = 60;

    /** Đổi số này khi sửa HÌNH DẠNG dữ liệu nhớ tạm, để bản cũ trong cache không gây lỗi. */
    /*
     * v2 (3/10) — đổi vì NỘI DUNG nhớ tạm đổi: kho "Bài tập chuyên đề" nay bỏ các câu đã nằm
     * trong đề luyện tập. Không đổi số thì sau khi lên mã mới, bản cũ trong cache vẫn được
     * phục vụ cho tới khi hết 60 giây — khách mở trang ngay sẽ tưởng chưa sửa gì.
     */
    private const CACHE_VERSION = 'v2';

    public function __construct(
        private AssessmentRepositoryInterface $assessments,
        private TagRepositoryInterface $tags,
        private AttemptRepositoryInterface $attempts,
    ) {}

    public function indexData(?User $viewer): array
    {
        /*
         * SỬA 3/10 — phần GIỐNG NHAU với mọi khách lấy từ nhớ tạm (xem examBaseRows()): danh
         * sách đề, số câu, ảnh bìa, số lượt làm… Trước đây mỗi lượt mở trang là một lần quét
         * bảng attempts để đếm lượt làm, dù con số đó y hệt nhau cho tất cả mọi người.
         */
        $base = Cache::remember(
            'practice:exams:'.self::CACHE_VERSION,
            self::CACHE_TTL,
            fn () => $this->examBaseRows()
        );

        $assessmentIds = array_column($base['rows'], 'id');

        // Phần RIÊNG của người đang xem — luôn tươi, không bao giờ nhớ tạm.
        $progressByAssessment = $viewer !== null && $assessmentIds !== []
            ? $this->attempts->progressForUserAndAssessments($viewer->id, $assessmentIds)->keyBy('assessment_id')
            : collect();

        $items = array_map(function (array $row) use ($progressByAssessment) {
            $progress = $this->examProgressFor($progressByAssessment->get($row['id']), (float) ($row['totalPoints'] ?: 0));

            return $row + $progress;
        }, $base['rows']);

        /*
         * SỬA 2/10 — dải chip lọc dựng TỪ LOẠI ĐỀ CÓ THẬT trong danh sách, không phải danh sách
         * cố định: bày chip "Olympic" mà không đề nào thuộc loại đó thì bấm vào ra bảng rỗng.
         */
        $categoryChips = collect($items)
            ->filter(fn (array $it) => $it['category'] !== null)
            ->groupBy('category')
            ->map(fn ($group, $code) => [
                'value' => $code,
                'label' => $group->first()['categoryLabel'],
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();

        return array_merge([
            'items' => $items,
            'examCategoryChips' => $categoryChips,
            // 3 ô tổng quan ở đầu tab (bản mẫu: Kho đề thi / Đang luyện / Điểm cao nhất).
            'examTotal' => count($items),
            'examDoingCount' => collect($items)->where('progressStatus', 'doing')->count(),
            'examBestScoreLabel' => $this->bestScoreLabel($progressByAssessment, $base['totalPoints']),
            'problems' => $this->problemRows($viewer),
            // SỬA 23/9 (khách: "học sinh, giáo viên, admin, phụ huynh đều làm được luyện tập
            // hết") — ai đã đăng nhập cũng bấm được "Làm bài"/"Bắt đầu làm đề" ngay ở trang
            // Luyện tập công khai, không bị đẩy sang màn đăng nhập nữa. Route tương ứng cũng
            // đã bỏ ràng buộc vai trò, xem routes/web.php.
            'canTakeDirectly' => $viewer !== null,
            // Danh sách chuyên đề/dạng câu cũng như nhau với mọi khách.
        ], Cache::remember(
            'practice:filters:'.self::CACHE_VERSION,
            self::CACHE_TTL,
            fn () => PracticeFilters::options($this->tags)
        ));
    }

    /**
     * SỬA 3/10 — phần thẻ đề KHÔNG phụ thuộc người xem, để đưa vào nhớ tạm.
     *
     * Trả về mảng thuần (không phải model) để cất vào cache cho an toàn: model Eloquent mang
     * theo cả quan hệ và trạng thái, cất đi rồi lấy ra dễ sinh chuyện.
     *
     * @return array{rows: list<array<string, mixed>>, totalPoints: array<int, float>}
     */
    private function examBaseRows(): array
    {
        $assessments = $this->assessments->query()
            ->where('type', 'practice')
            ->where('status', 'published')
            ->withCount(['items', 'answerKeys', 'codingItems'])
            ->latest()
            ->limit(30)
            ->get();

        $assessmentIds = $assessments->pluck('id')->all();
        $codingAssessmentIds = $this->assessmentIdsWithCoding($assessmentIds);

        // SỬA 2/10 — "Lượt làm" trên thẻ đề: SỐ THẬT, đếm lượt đã nộp của toàn hệ thống. Bản mẫu
        // để con số minh hoạ ("1,240 lượt làm") và tự ghi chú là dữ liệu minh hoạ — ở đây có dữ
        // liệu thật thì dùng thật, đề chưa ai làm hiện 0 chứ không bịa.
        $attemptCounts = $this->attemptCountsByAssessment($assessmentIds);

        $rows = $assessments->map(fn ($a) => [
            'id' => $a->id,
            'title' => $a->title,
            'itemsCount' => $a->items_count > 0 ? $a->items_count : (int) ($a->answer_keys_count ?? 0),
            'totalPoints' => $a->total_points,
            'durationMinutes' => $a->duration_minutes,
            'hasCoding' => $codingAssessmentIds->contains($a->id) || (int) ($a->coding_items_count ?? 0) > 0,
            /*
             * SỬA 2/10 (khách: "update lại UI trang luyện tập public ở tab đề thi luyện tập"
             * theo bản mẫu mới) — 6 trường mô tả đề + ảnh bìa, xem migration
             * add_detail_fields_to_assessments_table. Đề cũ chưa nhập thì để null và nơi
             * hiển thị tự rơi về câu thay thế, KHÔNG bịa dữ liệu.
             */
            'subtitle' => $a->subtitle,
            'author' => $a->author,
            'provinceLabel' => $a->provinceLabel(),
            'academicYear' => $a->academic_year,
            'category' => $a->exam_category,
            'categoryLabel' => $a->examCategoryLabel(),
            'coverUrl' => $a->coverUrl(),
            'examCode' => $a->exam_code,
            'attemptCount' => (int) ($attemptCounts[$a->id] ?? 0),
            // Nút chính của thẻ đề giờ dẫn sang MÀN CHI TIẾT ĐỀ (bản mẫu: "Xem chi tiết đề"),
            // chỗ bấm "Bắt đầu làm bài" mới mở modal làm đề.
            'detailHref' => route('practice.exam.show', $a->id),
        ])->all();

        return [
            'rows' => $rows,
            'totalPoints' => $assessments->pluck('total_points', 'id')->map(fn ($p) => (float) $p)->all(),
        ];
    }

    /**
     * Tiến độ của NGƯỜI ĐANG XEM với một đề. Tách khỏi examBaseRows() vì đây đúng là phần
     * không được nhớ tạm.
     *
     * @return array{progressStatus: string, progress: int, progressLabel: string}
     */
    private function examProgressFor($row, float $total): array
    {
        $best = $row !== null && $row->best_score !== null ? (float) $row->best_score : null;
        $submitted = $row !== null ? (int) $row->submitted_count : 0;
        $inProgress = $row !== null && (int) $row->in_progress_count > 0;

        if ($submitted > 0) {
            return [
                'progressStatus' => 'done',
                'progress' => $best !== null && $total > 0 ? (int) round(min(100, max(0, $best / $total * 100))) : 100,
                'progressLabel' => $best !== null ? 'Đã nộp · '.rtrim(rtrim(number_format($best, 2, ',', ''), '0'), ',').' điểm' : 'Đã nộp',
            ];
        }

        if ($inProgress) {
            return ['progressStatus' => 'doing', 'progress' => 35, 'progressLabel' => 'Đang làm dở'];
        }

        return ['progressStatus' => 'open', 'progress' => 0, 'progressLabel' => 'Chưa làm'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function problemRows(?User $viewer): array
    {
        /*
         * SỬA 3/10 — phần GIỐNG NHAU với mọi khách (60 câu đã xếp thứ tự + tổng lượt nộp/lượt
         * đúng của từng câu) lấy từ nhớ tạm. Đây là phần nặng nhất của trang:
         *   · câu lệnh xếp thứ tự dùng CASE trên cột type nên máy chủ KHÔNG dùng được chỉ số
         *     nào, phải xếp tay toàn bộ câu hỏi đã phát hành rồi mới cắt lấy 60 — càng nhiều
         *     câu hỏi càng lâu, mà kết quả thì y hệt nhau cho tất cả mọi người;
         *   · phép đếm trên attempt_answers quét theo lượt nộp của TOÀN hệ thống.
         */
        $base = Cache::remember(
            'practice:problems:'.self::CACHE_VERSION,
            self::CACHE_TTL,
            fn () => $this->problemBaseRows()
        );

        if ($base === [] || $viewer === null) {
            return $base;
        }

        // Phần RIÊNG của người đang xem — luôn tươi.
        return $this->overlayViewerProgress($base, $viewer);
    }

    /**
     * SỬA 3/10 — hàng bài tập KHÔNG phụ thuộc người xem (để nhớ tạm).
     *
     * Các ô "của tôi" để sẵn giá trị của người CHƯA làm gì; overlayViewerProgress() đắp lại khi
     * có người đăng nhập. Nhờ vậy khách vãng lai dùng thẳng bản nhớ tạm, không thêm truy vấn nào.
     *
     * @return array<int, array<string, mixed>>
     */
    private function problemBaseRows(): array
    {
        // SỬA 30/9 (khách: "chưa có thứ tự ưu tiên hiển thị") — câu được đặt ưu tiên cao hiện
        // lên đầu trang Luyện tập.
        // SỬA 30/9 (6) — xếp qua App\Support\QuestionOrder: gom theo DẠNG BÀI rồi mới tới thứ
        // tự ưu tiên. Phải dùng chung đúng một thứ tự với hai nút "Bài trước / Bài tiếp theo"
        // ở màn làm bài, nếu không thì danh sách bày ra một kiểu mà bấm "Bài tiếp theo" lại
        // nhảy theo kiểu khác.
        $questions = QuestionOrder::apply(
            // SỬA 3/10 (khách: "câu được add vô đề thi luyện tập thì không hiển thị bên bài
            // tập chuyên đề") — xem App\Support\PracticeQuestionPool.
            PracticeQuestionPool::excludeExamQuestions(
                Question::query()
                    ->where('status', 'published')
                    ->whereNull('product_id')
                    ->whereIn('type', array_keys(PracticeFilters::TYPE_META))
                    // SỬA 4/10 — nạp gộp người soạn để đổ cột "Tác giả"; chỉ lấy id+name, không
                // kéo cả hàng users (có email, số điện thoại… không việc gì ra trang công khai).
                ->with(['tags:id,name', 'creator:id,name'])
            )
        )->limit(60)->get();

        if ($questions->isEmpty()) {
            return [];
        }

        $stats = AttemptAnswer::query()
            ->selectRaw('question_id, COUNT(*) as submissions, SUM(CASE WHEN verdict = ? OR score > 0 THEN 1 ELSE 0 END) as accepted', ['accepted'])
            ->whereIn('question_id', $questions->pluck('id')->all())
            ->groupBy('question_id')
            ->get()
            ->keyBy('question_id');

        return $questions->map(function (Question $q) use ($stats) {
            $row = $stats->get($q->id);
            $submissions = (int) ($row->submissions ?? 0);
            $accepted = (int) ($row->accepted ?? 0);
            $rate = $submissions > 0 ? round($accepted / $submissions * 100, 1) : 0.0;

            $meta = $q->metadata ?? [];

            $difficultyKey = QuestionDifficulty::resolve($meta, (int) $q->points);

            $limits = $q->grading_config['limits'] ?? [];

            return [
                'id' => $q->id,
                'code' => $q->code,
                'title' => $q->title,
                'typeKey' => $q->type instanceof QuestionType ? $q->type->value : (string) $q->type,
                'typeLabel' => PracticeFilters::TYPE_META[$q->type instanceof QuestionType ? $q->type->value : (string) $q->type]['label'] ?? 'Câu hỏi',
                'tagIds' => $q->tags->pluck('id')->all(),
                'topicLabel' => $q->tags->first()?->name ?? 'Chưa gắn chuyên đề',
                'difficulty' => $difficultyKey,
                'difficultyLevel' => QuestionDifficulty::stars($difficultyKey),
                'difficultyLabel' => QuestionDifficulty::label($difficultyKey),
                'points' => (int) $q->points,
                'timeLimit' => isset($limits['time_ms']) ? round($limits['time_ms'] / 1000, 1).'s' : '—',
                'memoryLimit' => isset($limits['memory_mb']) ? $limits['memory_mb'].'MB' : '—',
                'submissionCount' => $submissions,
                'acceptedCount' => $accepted,
                'acRate' => $rate,
                // 5 ô dưới đây là "của tôi" — giá trị của người chưa làm gì; có người đăng nhập
                // thì overlayViewerProgress() đắp lại.
                'userSubmissions' => 0,
                'minePassedTests' => null,
                'mineTotalTests' => null,
                'mineTestPercent' => null,
                'status' => 'todo',
                'subject' => $q->subject,
                'subjectLabel' => $q->subjectLabel(),
                // Chưa gán người soạn (dữ liệu cũ, nhập bằng script) thì để rỗng, nơi hiển thị
                // tự hiện "—" chứ không bịa tên.
                'authorName' => $q->creator?->name ?? '',
                'grade' => $q->grade,
                // SỬA 1/10 (khách: "ngoài trang luyện tập public thêm 2 cột tỉnh thành và năm
                // luôn nha đổ dữ liệu ra luôn nha") — đọc từ CỘT questions.province/exam_year
                // (xem migration add_province_exam_year_to_questions_table), không phải metadata.
                // Chưa gán -> provinceLabel()/examYearLabel() trả "—", cột vẫn có chỗ, không rỗng trơn.
                'provinceLabel' => $q->provinceLabel(),
                'examYearLabel' => $q->examYearLabel(),
            ];
        })->values()->all();
    }

    /**
     * SỬA 3/10 — đắp tiến độ của NGƯỜI ĐANG XEM lên các hàng bài tập đã nhớ tạm.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function overlayViewerProgress(array $rows, User $viewer): array
    {
        $selectMine = 'question_id, COUNT(*) as mine, SUM(CASE WHEN verdict = ? OR score > 0 THEN 1 ELSE 0 END) as mine_accepted';

        if (AttemptAnswer::supportsTestCounts()) {
            $selectMine .= ', MAX(passed_tests) as mine_passed_tests, MAX(total_tests) as mine_total_tests';
        }

        $mine = AttemptAnswer::query()
            ->selectRaw($selectMine, ['accepted'])
            ->whereIn('question_id', array_column($rows, 'id'))
            ->whereHas('attempt', fn ($q) => $q->where('user_id', $viewer->id))
            ->groupBy('question_id')
            ->get()
            ->keyBy('question_id');

        if ($mine->isEmpty()) {
            return $rows;
        }

        foreach ($rows as $i => $row) {
            $mineRow = $mine->get($row['id']);

            if ($mineRow === null) {
                continue;
            }

            $mineCount = (int) ($mineRow->mine ?? 0);
            $mineAccepted = (int) ($mineRow->mine_accepted ?? 0);

            $minePassed = $mineRow->mine_passed_tests ?? null;
            $mineTotalTests = $mineRow->mine_total_tests ?? null;

            $rows[$i]['userSubmissions'] = $mineCount;
            $rows[$i]['minePassedTests'] = $minePassed !== null ? (int) $minePassed : null;
            $rows[$i]['mineTotalTests'] = $mineTotalTests !== null ? (int) $mineTotalTests : null;
            $rows[$i]['mineTestPercent'] = ($mineTotalTests !== null && (int) $mineTotalTests > 0 && $minePassed !== null)
                ? (int) round((int) $minePassed / (int) $mineTotalTests * 100)
                : null;

            $rows[$i]['status'] = match (true) {
                $mineAccepted > 0 => 'ac',
                $mineCount > 0 => 'doing',
                default => 'todo',
            };
        }

        return $rows;
    }

    /**
     * SỬA 2/10 (khách: "khi click vào xem chi tiết đề thi nó hiển thị ra màn UI mới giống như
     * source mới") — dữ liệu cho MÀN CHI TIẾT ĐỀ, dựng theo
     * education-main/src/components/ExamDetailPage.jsx.
     *
     * CHỈ nhận đề LUYỆN TẬP đã phát hành (khách: "áp dụng cho đề thi của luyện tập thôi nha") —
     * đề bài giao / đề thi thật / đề cuộc thi có đường đi và luật quyền riêng, lọt vào đây là
     * lộ nội dung ra trang công khai.
     *
     * @return array<string, mixed>
     */
    public function examDetailData(?User $viewer, int $assessmentId): array
    {
        $exam = $this->assessments->query()
            ->where('type', 'practice')
            ->where('status', 'published')
            ->with(['items.question'])
            ->findOrFail($assessmentId);

        $items = $exam->items;

        /*
         * "Cơ cấu điểm của đề" — gom điểm theo DẠNG CÂU. Điểm lấy points_override (điểm đã chốt
         * vào đề) chứ không phải questions.points: đó mới là con số máy chấm dùng, xem
         * AttemptService::maxPointsFor().
         */
        $structure = [];
        foreach ($items as $item) {
            $type = $item->question?->type?->value ?? 'other';
            $meta = self::QUESTION_KIND_META[$type] ?? self::QUESTION_KIND_META['other'];
            $structure[$type] ??= ['key' => $type, 'label' => $meta['label'], 'color' => $meta['color'], 'maxScore' => 0.0, 'count' => 0];
            $structure[$type]['maxScore'] += (float) ($item->points_override ?? $item->question?->points ?? 0);
            $structure[$type]['count']++;
        }
        $structure = array_values($structure);

        $totalPoints = (float) array_sum(array_column($structure, 'maxScore')) ?: (float) $exam->total_points;

        // ── Kết quả của CHÍNH người đang xem ──
        $previewRange = $this->examPreviewRange($exam);

        $attemptCount = 0;
        $latest = null;

        if ($viewer !== null) {
            $attempts = $this->attempts->query()
                ->where('user_id', $viewer->id)
                ->where('assessment_id', $exam->id)
                ->whereNotNull('submitted_at')
                ->orderByDesc('submitted_at')
                ->get();

            $attemptCount = $attempts->count();
            $latest = $attempts->first();
        }

        return [
            'exam' => $exam,
            'examCategoryLabel' => $exam->examCategoryLabel(),
            'provinceLabel' => $exam->provinceLabel(),
            'coverUrl' => $exam->coverUrl(),
            'itemsCount' => $items->count(),
            'totalPoints' => $totalPoints,
            'structure' => $structure,
            'attemptCount' => $attemptCount,
            'latestScore' => $latest?->total_score !== null ? (float) $latest->total_score : null,
            'latestSubmittedAt' => $latest?->submitted_at,
            /*
             * Xem trước đề — HAI nguồn, theo thứ tự ưu tiên:
             *   1. TỆP PDF XEM TRƯỚC người ra đề tự tải lên ở form đề (cách chính, dùng được cho
             *      mọi đề Luyện tập vì không phụ thuộc đề có phải PDF hay không);
             *   2. khoảng trang xem thử cắt từ chính đề PDF (preview_page_from/to ở màn "Quản lý
             *      đề PDF") — chỉ đề PDF mới có.
             * Không có nguồn nào thì KHÔNG mở xem trước, màn chi tiết hiện cấu trúc đề. Cố ý: đề
             * là tài sản, mở phần nào là do người ra đề quyết chứ không mở mặc định.
             */
            'previewUrl' => ($exam->preview_pdf_path !== null || $previewRange !== null)
                ? route('practice.exam.preview', $exam->id)
                : null,
            // Chỉ có ý nghĩa với nguồn (2); nguồn (1) là tệp rời nên không nói "trang mấy–mấy".
            'previewRange' => $exam->preview_pdf_path === null ? $previewRange : null,
            'canTakeDirectly' => $viewer !== null,
            'takeHref' => $viewer !== null ? route('student.assessment.take', $exam->id) : route('login'),
            'backHref' => route('practice.index', ['tab' => 'de-thi']),
        ];
    }

    /**
     * SỬA 2/10 — cắt ĐÚNG khoảng trang xem thử ra một tệp PDF mới rồi gửi đi.
     *
     * Dùng lại khuôn FPDI của PdfBulkImportService::… (nơi đã cắt PDF nhiều đề thành từng đề),
     * nên không thêm thư viện nào mới.
     *
     * Vì sao cắt thật: gửi cả tệp rồi nhờ trình xem chỉ hiện vài trang thì CẢ ĐỀ đã nằm trong
     * máy người xem — mở công cụ mạng của trình duyệt là lấy được trọn đề. "Xem trước" lúc đó
     * chỉ là tấm rèm.
     *
     * Trang đứng ngoài khoảng (đề ít trang hơn khai báo) thì bỏ qua lặng lẽ, miễn còn ít nhất 1
     * trang; không trang nào hợp lệ -> 404.
     */
    public function streamExamPreview(int $assessmentId): \Symfony\Component\HttpFoundation\Response
    {
        $exam = $this->assessments->query()
            ->where('type', 'practice')
            ->where('status', 'published')
            ->findOrFail($assessmentId);

        /*
         * SỬA 2/10 lần 3 — có tệp PDF xem trước do người ra đề tải lên thì GỬI THẲNG tệp đó,
         * khỏi cắt gì cả: tệp này đã do chính người ra đề chọn nội dung, cắt thêm là sửa ý họ.
         */
        if ($exam->preview_pdf_path !== null) {
            abort_unless(Storage::disk('local')->exists($exam->preview_pdf_path), 404);

            return Storage::disk('local')->response(
                $exam->preview_pdf_path,
                $exam->preview_pdf_original_name ?: 'xem-truoc-de-'.$exam->id.'.pdf',
            );
        }

        $range = $this->examPreviewRange($exam);
        abort_if($range === null, 404);

        $source = Storage::disk('local')->path($exam->pdf_path);
        abort_unless(is_file($source), 404);

        try {
            $pdf = new \setasign\Fpdi\Fpdi();
            $pageCount = $pdf->setSourceFile($source);
            $added = 0;

            for ($page = $range['from']; $page <= min($range['to'], $pageCount); $page++) {
                $templateId = $pdf->importPage($page);
                $size = $pdf->getTemplateSize($templateId);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);
                $added++;
            }

            $body = $added > 0 ? $pdf->Output('S') : null;
        } catch (Throwable $e) {
            // PDF hỏng/có mật khẩu (bản FPDI miễn phí không đọc được) — ghi log rồi trả 404 chứ
            // KHÔNG để lỗi 500 làm trắng cả màn chi tiết đề.
            Log::warning('Không cắt được bản xem trước đề #'.$assessmentId.': '.$e->getMessage());
            abort(404);
        }

        // Khoảng trang khai vượt quá số trang thật của đề -> không có trang nào để gửi.
        // Việc kiểm tra này nằm NGOÀI try: abort() ném HttpException, mà HttpException cũng là
        // Throwable nên nếu để bên trong sẽ bị chính khối catch ở trên bắt lại và ghi một dòng
        // log sai sự thật ("không cắt được PDF") trong khi tệp vẫn đọc được bình thường.
        abort_if($body === null, 404);

        return response($body, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="xem-truoc-de-'.$exam->id.'.pdf"',
        ]);
    }

    /**
     * Khoảng trang xem thử đã khai cho đề PDF, hoặc null khi không có bản xem trước.
     *
     * @return array{from:int, to:int}|null
     */
    public function examPreviewRange(Assessment $exam): ?array
    {
        if (blank($exam->pdf_path) || ! $exam->isPdfMode()) {
            return null;
        }

        $from = (int) ($exam->preview_page_from ?? 0);
        $to = (int) ($exam->preview_page_to ?? 0);

        if ($from < 1 || $to < $from) {
            return null;
        }

        return ['from' => $from, 'to' => $to];
    }

    /**
     * Nhãn + màu cho từng dạng câu ở biểu đồ cơ cấu điểm. Màu chép từ bảng màu đang dùng khắp
     * trang Luyện tập để biểu đồ không lạc tông.
     */
    private const QUESTION_KIND_META = [
        'coding' => ['label' => 'Lập trình', 'color' => '#126F91'],
        'mcq' => ['label' => 'Trắc nghiệm', 'color' => '#2F8A6B'],
        'fill_blank' => ['label' => 'Điền đáp án', 'color' => '#B68032'],
        'composite' => ['label' => 'Câu nhiều phần', 'color' => '#7A5AA8'],
        'other' => ['label' => 'Dạng khác', 'color' => '#6B8295'],
    ];

    /**
     * SỬA 2/10 — số lượt ĐÃ NỘP của từng đề, tính trên toàn hệ thống. Một truy vấn GROUP BY cho
     * cả danh sách thay vì bắn N câu đếm.
     *
     * Chỉ đếm lượt đã nộp (submitted_at khác null): lượt đang làm dở chưa phải là "lượt làm".
     *
     * @param  array<int, int>  $assessmentIds
     * @return array<int, int>  assessment_id => số lượt
     */
    private function attemptCountsByAssessment(array $assessmentIds): array
    {
        if ($assessmentIds === []) {
            return [];
        }

        return \App\Models\Attempt::query()
            ->whereIn('assessment_id', $assessmentIds)
            ->whereNotNull('submitted_at')
            ->selectRaw('assessment_id, COUNT(*) as aggregate')
            ->groupBy('assessment_id')
            ->pluck('aggregate', 'assessment_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * SỬA 2/10 — ô "Điểm cao nhất" ở dải tổng quan: điểm tốt nhất của CHÍNH người đang xem trên
     * các đề luyện tập, dạng "86/100". Bản mẫu để cứng "86/100" làm số minh hoạ; ở đây có dữ
     * liệu thật nên dùng thật, chưa làm đề nào thì trả null để view hiện "—" chứ không bịa số.
     *
     * @param  \Illuminate\Support\Collection  $progressByAssessment  keyBy assessment_id
     * @param  array<int, float>  $totalPointsById  tổng điểm từng đề (SỬA 3/10: mảng thuần
     *         thay cho collection model, để examBaseRows() cất được vào nhớ tạm)
     */
    private function bestScoreLabel($progressByAssessment, array $totalPointsById): ?string
    {
        $best = null;

        foreach ($totalPointsById as $assessmentId => $totalPoints) {
            $row = $progressByAssessment->get($assessmentId);

            if ($row === null || $row->best_score === null || (int) $row->submitted_count === 0) {
                continue;
            }

            $ratio = $totalPoints > 0 ? (float) $row->best_score / $totalPoints : 0.0;

            if ($best === null || $ratio > $best['ratio']) {
                $best = ['ratio' => $ratio, 'score' => (float) $row->best_score, 'total' => $totalPoints];
            }
        }

        if ($best === null) {
            return null;
        }

        $fmt = fn (float $v) => rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',');

        return $fmt($best['score']).'/'.$fmt($best['total']);
    }

    /** @param array<int, int> $assessmentIds
     * @return \Illuminate\Support\Collection<int, int> */
    private function assessmentIdsWithCoding(array $assessmentIds): \Illuminate\Support\Collection
    {
        if ($assessmentIds === []) {
            return collect();
        }

        return AssessmentItem::query()
            ->whereIn('assessment_id', $assessmentIds)
            ->whereHas('question', fn ($q) => $q->where('type', 'coding'))
            ->distinct()
            ->pluck('assessment_id');
    }
}
