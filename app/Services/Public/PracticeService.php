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
use App\Support\QuestionDifficulty;
use App\Support\QuestionOrder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PracticeService
{
    public function __construct(
        private AssessmentRepositoryInterface $assessments,
        private TagRepositoryInterface $tags,
        private AttemptRepositoryInterface $attempts,
    ) {}

    public function indexData(?User $viewer): array
    {
        $assessments = $this->assessments->query()
            ->where('type', 'practice')
            ->where('status', 'published')
            ->withCount(['items', 'answerKeys', 'codingItems'])
            ->latest()
            ->limit(30)
            ->get();

        $codingAssessmentIds = $this->assessmentIdsWithCoding($assessments->pluck('id')->all());

        // SỬA 2/10 — "Lượt làm" trên thẻ đề: SỐ THẬT, đếm lượt đã nộp của toàn hệ thống. Bản mẫu
        // để con số minh hoạ ("1,240 lượt làm") và tự ghi chú là dữ liệu minh hoạ — ở đây có dữ
        // liệu thật thì dùng thật, đề chưa ai làm hiện 0 chứ không bịa.
        $attemptCounts = $this->attemptCountsByAssessment($assessments->pluck('id')->all());

        $progressByAssessment = $viewer !== null
            ? $this->attempts->progressForUserAndAssessments($viewer->id, $assessments->pluck('id')->all())->keyBy('assessment_id')
            : collect();

        $items = $assessments->map(function ($a) use ($codingAssessmentIds, $progressByAssessment, $attemptCounts) {
            $row = $progressByAssessment->get($a->id);
            $best = $row !== null && $row->best_score !== null ? (float) $row->best_score : null;
            $total = (float) ($a->total_points ?: 0);
            $submitted = $row !== null ? (int) $row->submitted_count : 0;
            $inProgress = $row !== null && (int) $row->in_progress_count > 0;

            if ($submitted > 0) {
                $progressStatus = 'done';
                $progress = $best !== null && $total > 0 ? (int) round(min(100, max(0, $best / $total * 100))) : 100;
                $progressLabel = $best !== null ? 'Đã nộp · '.rtrim(rtrim(number_format($best, 2, ',', ''), '0'), ',').' điểm' : 'Đã nộp';
            } elseif ($inProgress) {
                $progressStatus = 'doing';
                $progress = 35;
                $progressLabel = 'Đang làm dở';
            } else {
                $progressStatus = 'open';
                $progress = 0;
                $progressLabel = 'Chưa làm';
            }

            return [
                'id' => $a->id,
                'title' => $a->title,
                'itemsCount' => $a->items_count > 0 ? $a->items_count : (int) ($a->answer_keys_count ?? 0),
                'totalPoints' => $a->total_points,
                'durationMinutes' => $a->duration_minutes,
                'hasCoding' => $codingAssessmentIds->contains($a->id) || (int) ($a->coding_items_count ?? 0) > 0,
                'progressStatus' => $progressStatus,
                'progress' => $progress,
                'progressLabel' => $progressLabel,
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
            ];
        })->all();

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
            'examBestScoreLabel' => $this->bestScoreLabel($progressByAssessment, $assessments),
            'problems' => $this->problemRows($viewer),
            // SỬA 23/9 (khách: "học sinh, giáo viên, admin, phụ huynh đều làm được luyện tập
            // hết") — ai đã đăng nhập cũng bấm được "Làm bài"/"Bắt đầu làm đề" ngay ở trang
            // Luyện tập công khai, không bị đẩy sang màn đăng nhập nữa. Route tương ứng cũng
            // đã bỏ ràng buộc vai trò, xem routes/web.php.
            'canTakeDirectly' => $viewer !== null,
        ], PracticeFilters::options($this->tags));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function problemRows(?User $viewer): array
    {
        // SỬA 30/9 (khách: "chưa có thứ tự ưu tiên hiển thị") — câu được đặt ưu tiên cao hiện
        // lên đầu trang Luyện tập.
        // SỬA 30/9 (6) — xếp qua App\Support\QuestionOrder: gom theo DẠNG BÀI rồi mới tới thứ
        // tự ưu tiên. Phải dùng chung đúng một thứ tự với hai nút "Bài trước / Bài tiếp theo"
        // ở màn làm bài, nếu không thì danh sách bày ra một kiểu mà bấm "Bài tiếp theo" lại
        // nhảy theo kiểu khác.
        $questions = QuestionOrder::apply(
            Question::query()
                ->where('status', 'published')
                ->whereNull('product_id')
                ->whereIn('type', array_keys(PracticeFilters::TYPE_META))
                ->with(['tags:id,name'])
        )->limit(60)->get();

        if ($questions->isEmpty()) {
            return [];
        }

        $questionIds = $questions->pluck('id')->all();

        $stats = AttemptAnswer::query()
            ->selectRaw('question_id, COUNT(*) as submissions, SUM(CASE WHEN verdict = ? OR score > 0 THEN 1 ELSE 0 END) as accepted', ['accepted'])
            ->whereIn('question_id', $questionIds)
            ->groupBy('question_id')
            ->get()
            ->keyBy('question_id');

        $mine = collect();
        if ($viewer !== null) {
            $selectMine = 'question_id, COUNT(*) as mine, SUM(CASE WHEN verdict = ? OR score > 0 THEN 1 ELSE 0 END) as mine_accepted';

            if (AttemptAnswer::supportsTestCounts()) {
                $selectMine .= ', MAX(passed_tests) as mine_passed_tests, MAX(total_tests) as mine_total_tests';
            }

            $mine = AttemptAnswer::query()
                ->selectRaw($selectMine, ['accepted'])
                ->whereIn('question_id', $questionIds)
                ->whereHas('attempt', fn ($q) => $q->where('user_id', $viewer->id))
                ->groupBy('question_id')
                ->get()
                ->keyBy('question_id');
        }

        return $questions->map(function (Question $q) use ($stats, $mine) {
            $row = $stats->get($q->id);
            $submissions = (int) ($row->submissions ?? 0);
            $accepted = (int) ($row->accepted ?? 0);
            $rate = $submissions > 0 ? round($accepted / $submissions * 100, 1) : 0.0;

            $mineRow = $mine->get($q->id);
            $mineCount = (int) ($mineRow->mine ?? 0);
            $mineAccepted = (int) ($mineRow->mine_accepted ?? 0);

            $minePassed = $mineRow->mine_passed_tests ?? null;
            $mineTotalTests = $mineRow->mine_total_tests ?? null;
            $mineTestPercent = ($mineTotalTests !== null && (int) $mineTotalTests > 0 && $minePassed !== null)
                ? (int) round((int) $minePassed / (int) $mineTotalTests * 100)
                : null;

            $status = 'todo';
            if ($mineAccepted > 0) {
                $status = 'ac';
            } elseif ($mineCount > 0) {
                $status = 'doing';
            }

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
                'userSubmissions' => $mineCount,
                'minePassedTests' => $minePassed !== null ? (int) $minePassed : null,
                'mineTotalTests' => $mineTotalTests !== null ? (int) $mineTotalTests : null,
                'mineTestPercent' => $mineTestPercent,
                'status' => $status,
                'subject' => $q->subject,
                'subjectLabel' => $q->subjectLabel(),
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
     * @param  \Illuminate\Support\Collection  $assessments
     */
    private function bestScoreLabel($progressByAssessment, $assessments): ?string
    {
        $best = null;

        foreach ($assessments as $a) {
            $row = $progressByAssessment->get($a->id);

            if ($row === null || $row->best_score === null || (int) $row->submitted_count === 0) {
                continue;
            }

            $ratio = (float) ($a->total_points ?: 0) > 0 ? (float) $row->best_score / (float) $a->total_points : 0.0;

            if ($best === null || $ratio > $best['ratio']) {
                $best = ['ratio' => $ratio, 'score' => (float) $row->best_score, 'total' => (float) $a->total_points];
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
