<?php

namespace App\Services\Contest;

use App\Enums\ContentStatus;
use App\Models\Assessment;
use App\Models\AssessmentItem;
use App\Models\Attempt;
use App\Models\CompetitionExam;
use App\Models\CompetitionExamPaper;
use App\Models\CompetitionRegistration;
use App\Models\Question;
use App\Models\User;
use App\Support\QuestionDifficulty;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * SỬA 24/10 (khách: "giáo viên / admin thiết lập đề sinh ngẫu nhiên; 1 kỳ thi có thể gồm nhiều đề khác nhau") —
 * tab "Thiết lập đề" trong phòng thi (source mới: SurveyPaperBuilder.jsx + utils/surveyMatrix.js + surveyPapers.js).
 *
 * Quy trình: 1. chọn ma trận (chuyên đề / độ khó / số câu / điểm, lọc thêm ngân hàng, tag, cách chọn câu, khoảng điểm
 * gốc) → 2. sinh đề dự kiến từ Kho câu hỏi (đã phát hành), duyệt, thay từng câu → 3. chốt đề. Chốt = tạo/cập nhật một
 * Assessment thật (Đề thi, câu hỏi rời, tổng 100 điểm) nên làm bài / chấm / xếp hạng dùng NGUYÊN hạ tầng cũ.
 *
 * Một kỳ có thể có NHIỀU đề (Đề 1, Đề 2 …). Học sinh được giao ngẫu nhiên 1 đề đã chốt, giữ cố định cho mọi lượt làm lại
 * (lưu ở competition_registrations.exam_forms["paper:<id kỳ>"]). Đề khoá nội dung khi đã có học sinh làm; muốn đổi câu
 * thì thêm đề mới. "Đã dùng" = câu từng được chốt ở đề của KỲ KHÁC (kể cả bản đã bị thay) — bật "Không lấy câu đã dùng"
 * thì chỉ lấy câu chưa từng dùng.
 */
class ContestPaperService
{
    public const POLICIES = [
        'prefer_new' => 'Ưu tiên chưa dùng / ít dùng',
        'new_only' => 'Chỉ câu chưa dùng',
        'prefer_used' => 'Ưu tiên câu đã dùng nhiều',
        'used_only' => 'Chỉ câu đã dùng',
        'random' => 'Ngẫu nhiên, không ưu tiên lịch sử',
    ];

    private const MAX_ROWS = 20;

    private const MAX_QUESTIONS = 100;

    private const TYPE_LABELS = ['coding' => 'Lập trình', 'mcq' => 'Trắc nghiệm', 'fill_blank' => 'Điền đáp án', 'composite' => 'Nhiều phần'];

    /** @var array<int, array<string, mixed>>|null */
    private ?array $bankCache = null;

    // ───────────────────────────── Ngân hàng câu hỏi ─────────────────────────────

    /** Câu hỏi đủ điều kiện: đã phát hành, thuộc Kho câu hỏi chung (không phải bài tập riêng của sản phẩm). */
    public function bank(): array
    {
        if ($this->bankCache !== null) {
            return $this->bankCache;
        }

        $out = [];
        Question::query()
            ->where('status', ContentStatus::Published->value)
            ->whereNull('product_id')
            ->with(['tags:id,name', 'bank:id,name'])
            ->orderBy('id')
            ->get(['id', 'code', 'title', 'type', 'points', 'metadata', 'bank_id'])
            ->each(function (Question $q) use (&$out) {
                $type = $q->type?->value ?? (string) $q->type;
                $out[(int) $q->id] = [
                    'id' => (int) $q->id,
                    'code' => (string) ($q->code ?? ''),
                    'title' => trim((string) $q->title) !== '' ? (string) $q->title : 'Câu #'.$q->id,
                    'type' => $type,
                    'typeLabel' => self::TYPE_LABELS[$type] ?? 'Câu hỏi',
                    'points' => (float) $q->points,
                    'difficulty' => QuestionDifficulty::resolve(is_array($q->metadata) ? $q->metadata : [], (int) $q->points),
                    'bankId' => $q->bank_id !== null ? (int) $q->bank_id : null,
                    'bankName' => $q->bank?->name ?? 'Kho chung',
                    'tagIds' => $q->tags->pluck('id')->map(fn ($v) => (int) $v)->all(),
                    'tags' => $q->tags->pluck('name')->all(),
                ];
            });

        return $this->bankCache = $out;
    }

    /** Danh mục cho ô chọn: chuyên đề (tag), ngân hàng, độ khó, cách chọn câu. */
    public function meta(): array
    {
        $bank = $this->bank();
        $tags = [];
        $banks = [];
        foreach ($bank as $q) {
            foreach ($q['tagIds'] as $i => $id) {
                $tags[$id] = $q['tags'][$i] ?? ('Tag #'.$id);
            }
            $banks[$q['bankId'] ?? 0] = $q['bankName'];
        }
        asort($tags, SORT_LOCALE_STRING);
        asort($banks, SORT_LOCALE_STRING);

        return [
            'topics' => array_map(fn ($id, $name) => ['id' => $id, 'name' => $name], array_keys($tags), $tags),
            'banks' => array_map(fn ($id, $name) => ['id' => $id, 'name' => $name], array_keys($banks), $banks),
            'difficulties' => array_map(fn ($k, $v) => ['key' => $k, 'label' => $v], array_keys(QuestionDifficulty::LEVELS), QuestionDifficulty::LEVELS),
            'policies' => array_map(fn ($k, $v) => ['key' => $k, 'label' => $v], array_keys(self::POLICIES), self::POLICIES),
            'bankCount' => count($bank),
        ];
    }

    /** Câu nào đã được chốt ở bao nhiêu KỲ KHÁC. @return array<int, int> */
    public function usage(CompetitionExam $exam): array
    {
        $byExam = [];
        CompetitionExamPaper::query()
            ->where('competition_exam_id', '!=', $exam->id)
            ->whereNotNull('used_question_ids')
            ->get(['competition_exam_id', 'used_question_ids'])
            ->each(function ($p) use (&$byExam) {
                foreach ((array) $p->used_question_ids as $qid) {
                    $byExam[(int) $p->competition_exam_id][(int) $qid] = true;
                }
            });

        $counts = [];
        foreach ($byExam as $ids) {
            foreach (array_keys($ids) as $qid) {
                $counts[$qid] = ($counts[$qid] ?? 0) + 1;
            }
        }

        return $counts;
    }

    // ───────────────────────────── Ma trận ─────────────────────────────

    public function defaultMatrix(): array
    {
        return [['id' => Str::lower(Str::random(8)), 'topic' => null, 'difficulty' => 'basic', 'count' => 4, 'points' => 100,
            'bank' => null, 'tags' => [], 'tagMatch' => 'any', 'usagePolicy' => 'prefer_new', 'minPoints' => null, 'maxPoints' => null]];
    }

    /** Chuẩn hoá ma trận gửi từ trình duyệt — giá trị lạ về mặc định, không tin dữ liệu client. */
    public function normalizeMatrix(mixed $raw): array
    {
        $rows = [];
        foreach (array_slice(is_array($raw) ? array_values($raw) : [], 0, self::MAX_ROWS + 1) as $r) {
            if (! is_array($r)) {
                continue;
            }
            $num = fn ($v) => ($v === null || $v === '' || ! is_numeric($v)) ? null : round((float) $v, 2);
            $id = substr((string) preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($r['id'] ?? '')), 0, 40);
            $rows[] = [
                'id' => $id !== '' ? $id : Str::lower(Str::random(8)),
                'topic' => (isset($r['topic']) && is_numeric($r['topic']) && (int) $r['topic'] > 0) ? (int) $r['topic'] : null,
                'difficulty' => QuestionDifficulty::filterKey($r['difficulty'] ?? null) ?? 'basic',
                'count' => is_numeric($r['count'] ?? null) ? (int) $r['count'] : 0,
                'points' => is_numeric($r['points'] ?? null) ? round((float) $r['points'], 2) : 0.0,
                'bank' => (isset($r['bank']) && is_numeric($r['bank']) && (int) $r['bank'] > 0) ? (int) $r['bank'] : null,
                'tags' => array_slice(array_values(array_unique(array_filter(array_map('intval', (array) ($r['tags'] ?? []))))), 0, 30),
                'tagMatch' => ($r['tagMatch'] ?? 'any') === 'all' ? 'all' : 'any',
                'usagePolicy' => array_key_exists((string) ($r['usagePolicy'] ?? ''), self::POLICIES) ? (string) $r['usagePolicy'] : 'prefer_new',
                'minPoints' => $num($r['minPoints'] ?? null),
                'maxPoints' => $num($r['maxPoints'] ?? null),
            ];
        }

        return $rows;
    }

    private function effective(array $row, bool $excludeUsed): array
    {
        return $excludeUsed ? ['usagePolicy' => 'new_only'] + $row : $row;
    }

    private function matches(array $q, array $row, array $usage): bool
    {
        $uses = $usage[$q['id']] ?? 0;

        return ($row['topic'] === null || in_array($row['topic'], $q['tagIds'], true))
            && $q['difficulty'] === $row['difficulty']
            && ($row['bank'] === null || $q['bankId'] === $row['bank'] || ($row['bank'] === 0 && $q['bankId'] === null))
            && ($row['tags'] === [] || ($row['tagMatch'] === 'all'
                ? array_diff($row['tags'], $q['tagIds']) === []
                : array_intersect($row['tags'], $q['tagIds']) !== []))
            && ($row['minPoints'] === null || $q['points'] >= $row['minPoints'])
            && ($row['maxPoints'] === null || $q['points'] <= $row['maxPoints'])
            && ($row['usagePolicy'] !== 'new_only' || $uses === 0)
            && ($row['usagePolicy'] !== 'used_only' || $uses > 0);
    }

    /** @return array<int, array<string, mixed>> */
    private function candidates(array $row, array $usage, bool $excludeUsed): array
    {
        $row = $this->effective($row, $excludeUsed);

        return array_values(array_filter($this->bank(), fn ($q) => $this->matches($q, $row, $usage)));
    }

    /** Số câu khớp từng phần (hiện ngay dưới mỗi phần). */
    public function counts(array $matrix, bool $excludeUsed, array $usage): array
    {
        $out = [];
        foreach ($matrix as $row) {
            $list = $this->candidates($row, $usage, $excludeUsed);
            $unused = count(array_filter($list, fn ($q) => ($usage[$q['id']] ?? 0) === 0));
            $withHistory = $excludeUsed ? count($this->candidates(['usagePolicy' => 'random'] + $row, $usage, false)) : count($list);
            $out[$row['id']] = [
                'available' => count($list),
                'unused' => $unused,
                'used' => count($list) - $unused,
                'historyExcluded' => max(0, $withHistory - count($list)),
            ];
        }

        return $out;
    }

    /** Lỗi của ma trận (giống matrixIssues của bản mẫu). @return array<int, string> */
    public function issues(array $matrix, bool $excludeUsed, array $usage): array
    {
        if ($matrix === [] || count($matrix) > self::MAX_ROWS) {
            return ['Ma trận cần từ 1 đến '.self::MAX_ROWS.' phần.'];
        }

        $issues = [];
        $tagNames = array_column($this->meta()['topics'], 'name', 'id');
        foreach ($matrix as $i => $row) {
            $n = $i + 1;
            if ($row['count'] < 1 || $row['count'] > self::MAX_QUESTIONS) {
                $issues[] = "Phần {$n}: số câu phải là số nguyên từ 1–".self::MAX_QUESTIONS.'.';
            }
            if ($row['points'] < $row['count'] * 0.01 || $row['points'] > 100) {
                $issues[] = "Phần {$n}: nhập điểm hợp lệ (tối thiểu 0,01 điểm mỗi câu, tối đa 100).";
            }
            if ($row['minPoints'] !== null && $row['maxPoints'] !== null && $row['minPoints'] > $row['maxPoints']) {
                $issues[] = "Phần {$n}: khoảng điểm gốc không hợp lệ.";
            }
            $available = count($this->candidates($row, $usage, $excludeUsed));
            if ($row['count'] > $available) {
                $issues[] = "Phần {$n} · ".($row['topic'] !== null ? ($tagNames[$row['topic']] ?? 'Chuyên đề') : 'Mọi chuyên đề')
                    .' · '.QuestionDifficulty::label($row['difficulty']).": cần {$row['count']} câu, ngân hàng có {$available} câu khớp toàn bộ điều kiện.";
            }
        }

        if (array_sum(array_column($matrix, 'count')) > self::MAX_QUESTIONS) {
            $issues[] = 'Mỗi đề tối đa '.self::MAX_QUESTIONS.' câu.';
        }
        if (abs(array_sum(array_column($matrix, 'points')) - 100) > 0.00001) {
            $issues[] = 'Tổng điểm các phần phải bằng 100.';
        }
        if ($issues === [] && $this->allocate($matrix, $excludeUsed, $usage, false) === null) {
            $issues[] = 'Các phần đang dùng chung nguồn câu hỏi và không đủ câu khác nhau cho toàn đề. Hãy giảm số câu, mở rộng điều kiện hoặc bổ sung ngân hàng.';
        }

        return $issues;
    }

    /**
     * Phân câu cho từng phần, không trùng câu giữa các phần. Phần ít câu khớp được phân trước để phần điều kiện rộng không
     * "ăn mất" câu duy nhất của phần hẹp; thử lại vài lần với thứ tự ngẫu nhiên khác nếu kẹt.
     *
     * @return array<string, array<int, int>>|null  id phần => danh sách id câu
     */
    private function allocate(array $matrix, bool $excludeUsed, array $usage, bool $random, array $exclude = []): ?array
    {
        $maxUses = $usage === [] ? 0 : max($usage);
        $lists = [];
        foreach ($matrix as $row) {
            $lists[$row['id']] = $this->candidates($row, $usage, $excludeUsed);
        }

        for ($try = 0; $try < ($random ? 8 : 1); $try++) {
            $order = $matrix;
            usort($order, fn ($a, $b) => count($lists[$a['id']]) <=> count($lists[$b['id']]));
            $taken = array_fill_keys($exclude, true);
            $result = [];
            $ok = true;

            foreach ($order as $row) {
                $policy = $this->effective($row, $excludeUsed)['usagePolicy'];
                $pool = array_values(array_filter($lists[$row['id']], fn ($q) => ! isset($taken[$q['id']])));
                $scored = array_map(function ($q) use ($policy, $usage, $maxUses, $random) {
                    $uses = $usage[$q['id']] ?? 0;
                    $pref = $policy === 'prefer_new' ? $uses : ($policy === 'prefer_used' ? $maxUses - $uses : 0);

                    return [$pref, $random ? random_int(0, 1_000_000) : $q['id'], $q['id']];
                }, $pool);
                sort($scored);
                $pick = array_slice(array_column($scored, 2), 0, $row['count']);
                if (count($pick) < $row['count']) {
                    $ok = false;
                    break;
                }
                foreach ($pick as $id) {
                    $taken[$id] = true;
                }
                $result[$row['id']] = $pick;
            }

            if ($ok) {
                return $result;
            }
        }

        return null;
    }

    /** Chia điểm một phần đều cho các câu, phần lẻ dồn vào các câu đầu (làm tròn 0,01). @return array<int, float> */
    private function split(float $points, int $count): array
    {
        $cents = (int) round($points * 100);
        $base = intdiv($cents, max(1, $count));

        return array_map(fn ($i) => ($base + ($i < $cents % max(1, $count) ? 1 : 0)) / 100, range(0, max(0, $count - 1)));
    }

    /** Sinh đề dự kiến. @return array<int, array{id:int, points:float, row:string}> */
    public function generate(array $matrix, bool $excludeUsed, array $usage): array
    {
        $issues = $this->issues($matrix, $excludeUsed, $usage);
        if ($issues !== []) {
            $this->fail(implode(' ', $issues));
        }

        $alloc = $this->allocate($matrix, $excludeUsed, $usage, true) ?? $this->fail('Chưa phân được câu cho đủ các phần. Hãy thử lại hoặc mở rộng điều kiện.');
        $out = [];
        foreach ($matrix as $row) {
            foreach ($alloc[$row['id']] as $i => $qid) {
                $out[] = ['id' => $qid, 'points' => $this->split($row['points'], $row['count'])[$i], 'row' => $row['id']];
            }
        }

        return $out;
    }

    /** Thay 1 câu bằng câu khác khớp toàn bộ điều kiện của phần đó (giữ nguyên điểm). */
    public function replace(array $matrix, bool $excludeUsed, array $usage, array $questions, int $questionId): array
    {
        $questions = $this->normalizeQuestions($questions);
        $current = collect($questions)->firstWhere('id', $questionId);
        $row = $current ? collect($matrix)->firstWhere('id', $current['row']) : null;
        if ($current === null || $row === null) {
            $this->fail('Cần sinh lại đề theo ma trận hiện tại.');
        }

        $alloc = $this->allocate([['count' => 1] + $row], $excludeUsed, $usage, true, array_column($questions, 'id'));
        if ($alloc === null) {
            $this->fail('Chưa có câu thay thế khớp toàn bộ điều kiện của phần này. Hãy bổ sung ngân hàng hoặc điều chỉnh ma trận.');
        }

        return array_map(fn ($q) => $q['id'] === $questionId ? ['id' => $alloc[$row['id']][0]] + $q : $q, $questions);
    }

    public function normalizeQuestions(mixed $raw): array
    {
        $out = [];
        foreach (array_slice(is_array($raw) ? array_values($raw) : [], 0, self::MAX_QUESTIONS) as $q) {
            if (is_array($q) && is_numeric($q['id'] ?? null) && is_numeric($q['points'] ?? null)) {
                $out[] = ['id' => (int) $q['id'], 'points' => round((float) $q['points'], 2), 'row' => (string) ($q['row'] ?? '')];
            }
        }

        return $out;
    }

    /** Đề dự kiến phải khớp đúng ma trận trước khi chốt (chặn dữ liệu sửa tay / ngân hàng đã đổi). */
    private function assertPreviewValid(array $matrix, bool $excludeUsed, array $usage, array $questions): void
    {
        $issues = $this->issues($matrix, $excludeUsed, $usage);
        if ($issues !== []) {
            $this->fail(implode(' ', $issues));
        }
        $ids = array_column($questions, 'id');
        if ($questions === [] || count($questions) !== array_sum(array_column($matrix, 'count')) || count(array_unique($ids)) !== count($ids)) {
            $this->fail('Danh sách câu hỏi thiếu hoặc trùng. Hãy sinh lại đề.');
        }
        $bank = $this->bank();
        foreach ($matrix as $row) {
            $items = array_values(array_filter($questions, fn ($q) => $q['row'] === $row['id']));
            $eff = $this->effective($row, $excludeUsed);
            if (count($items) !== $row['count']
                || array_filter($items, fn ($q) => ! isset($bank[$q['id']]) || ! $this->matches($bank[$q['id']], $eff, $usage)) !== []
                || abs(array_sum(array_column($items, 'points')) - $row['points']) > 0.00001) {
                $this->fail('Đề dự kiến chưa khớp ma trận hoặc ngân hàng / lịch sử sử dụng đã thay đổi. Hãy sinh lại đề.');
            }
        }
    }

    // ───────────────────────────── Đề của kỳ ─────────────────────────────

    public function lockReason(CompetitionExamPaper $paper): ?string
    {
        if ($paper->assessment_id !== null && Attempt::query()->where('assessment_id', $paper->assessment_id)->exists()) {
            return 'Đã có học sinh làm đề này — nội dung đã khoá. Muốn đổi câu hỏi, hãy thêm đề mới cho kỳ.';
        }

        return null;
    }

    public function createPaper(CompetitionExam $exam): CompetitionExamPaper
    {
        $n = CompetitionExamPaper::query()->where('competition_exam_id', $exam->id)->count() + 1;

        return CompetitionExamPaper::create([
            'competition_exam_id' => $exam->id,
            'label' => 'Đề '.$n,
            'matrix' => $this->defaultMatrix(),
            'exclude_used' => true,
            'duration_minutes' => 45,
            'status' => CompetitionExamPaper::STATUS_DRAFT,
        ]);
    }

    public function saveDraft(CompetitionExamPaper $paper, array $input): CompetitionExamPaper
    {
        $this->assertUnlocked($paper);
        [$matrix, $excludeUsed, $questions, $duration, $label] = $this->input($input);

        $paper->update([
            'label' => $label ?? $paper->label,
            'matrix' => $matrix,
            'exclude_used' => $excludeUsed,
            'duration_minutes' => $duration,
            'draft' => ['questions' => $questions],
        ]);

        return $paper;
    }

    /** Chốt đề: tạo / cập nhật Assessment thật, thay toàn bộ câu theo đề dự kiến. */
    public function publish(CompetitionExamPaper $paper, User $user, array $input): CompetitionExamPaper
    {
        $this->assertUnlocked($paper);
        [$matrix, $excludeUsed, $questions, $duration, $label] = $this->input($input);
        $exam = $paper->exam()->with('competition')->firstOrFail();
        $this->assertPreviewValid($matrix, $excludeUsed, $this->usage($exam), $questions);

        DB::transaction(function () use ($paper, $user, $exam, $matrix, $excludeUsed, $questions, $duration, $label) {
            $label = $label ?? $paper->label;
            $title = Str::limit(($exam->competition?->title ?? 'Khảo sát').' · '.$exam->displayTitle().' · '.$label, 250, '');
            $assessment = $paper->assessment_id !== null ? Assessment::query()->find($paper->assessment_id) : null;
            $attrs = [
                'title' => $title,
                'type' => \App\Enums\AssessmentType::Exam->value,
                'content_mode' => \App\Enums\AssessmentContentMode::Structured->value,
                'status' => ContentStatus::Published->value,
                'duration_minutes' => $duration,
                'total_points' => 100,
            ];
            if ($assessment === null) {
                $assessment = Assessment::create($attrs + [
                    'owner_type' => \App\Enums\OwnerType::Shared->value,
                    'created_by' => $user->id,
                    'publish_answer_rule' => 'never',
                ]);
            } else {
                $assessment->update($attrs);
            }

            AssessmentItem::query()->where('assessment_id', $assessment->id)->delete();
            foreach (array_values($questions) as $i => $q) {
                AssessmentItem::create(['assessment_id' => $assessment->id, 'question_id' => $q['id'], 'order' => $i + 1, 'points_override' => $q['points']]);
            }

            $paper->update([
                'label' => $label,
                'assessment_id' => $assessment->id,
                'matrix' => $matrix,
                'exclude_used' => $excludeUsed,
                'duration_minutes' => $duration,
                'draft' => null,
                'status' => CompetitionExamPaper::STATUS_PUBLISHED,
                'version' => (int) $paper->version + 1,
                'published_at' => now(),
                'published_by' => $user->id,
                'used_question_ids' => array_values(array_unique(array_merge((array) $paper->used_question_ids, array_column($questions, 'id')))),
            ]);
        });

        return $paper->refresh();
    }

    public function deletePaper(CompetitionExamPaper $paper): void
    {
        $this->assertUnlocked($paper);
        $assessment = $paper->assessment_id !== null ? Assessment::query()->find($paper->assessment_id) : null;
        $paper->delete();
        if ($assessment !== null) {
            app(\App\Services\Admin\ContentService::class)->assessmentDestroy($assessment);
        }
    }

    /** @return array{0: array, 1: bool, 2: array, 3: int, 4: ?string} */
    private function input(array $input): array
    {
        $label = trim((string) ($input['label'] ?? ''));

        return [
            $this->normalizeMatrix($input['matrix'] ?? []),
            filter_var($input['excludeUsed'] ?? false, FILTER_VALIDATE_BOOLEAN),
            $this->normalizeQuestions($input['questions'] ?? []),
            max(5, min(600, (int) ($input['duration'] ?? 45))),
            $label !== '' ? Str::limit($label, 120, '') : null,
        ];
    }

    private function assertUnlocked(CompetitionExamPaper $paper): void
    {
        if (($reason = $this->lockReason($paper)) !== null) {
            $this->fail($reason);
        }
    }

    // ───────────────────────────── Giao đề cho học sinh ─────────────────────────────

    /** @return Collection<int, CompetitionExamPaper> */
    public function publishedPapers(CompetitionExam $exam): Collection
    {
        if (! CompetitionExamPaper::supported()) {
            return collect();
        }

        return CompetitionExamPaper::query()
            ->where('competition_exam_id', $exam->id)
            ->where('status', CompetitionExamPaper::STATUS_PUBLISHED)
            ->whereNotNull('assessment_id')
            ->orderBy('id')
            ->get();
    }

    /**
     * Đề của học sinh ở kỳ này. $assign = true: chưa có thì bốc ngẫu nhiên 1 đề đã chốt và GIỮ cố định (cần có dòng
     * đăng ký — khảo sát có ngay sau khi nhập mật khẩu, cuộc thi có khi được duyệt).
     */
    public function paperFor(?User $user, CompetitionExam $exam, bool $assign = false): ?CompetitionExamPaper
    {
        $papers = $this->publishedPapers($exam);
        if ($papers->isEmpty() || $user === null || ! CompetitionRegistration::supportsHubFields()) {
            return null;
        }

        $reg = CompetitionRegistration::query()->where('competition_id', $exam->competition_id)->where('student_id', $user->id)->first();
        $key = 'paper:'.$exam->id;
        $saved = $reg !== null ? (int) (((array) $reg->exam_forms)[$key] ?? 0) : 0;
        if ($saved > 0 && ($found = $papers->firstWhere('id', $saved)) !== null) {
            return $found;
        }
        if (! $assign || $reg === null) {
            return null;
        }

        $pick = $papers->values()[random_int(0, $papers->count() - 1)];
        $forms = (array) $reg->exam_forms;
        $forms[$key] = $pick->id;
        $reg->forceFill(['exam_forms' => $forms])->save();

        return $pick;
    }

    /** Đề (assessment) học sinh làm ở kỳ: đề ma trận được giao, không có thì đề gắn sẵn của kỳ. */
    public function assessmentIdFor(User $user, CompetitionExam $exam): ?int
    {
        $paper = $this->paperFor($user, $exam, true);

        return $paper !== null ? (int) $paper->assessment_id : ($exam->assessment_id !== null ? (int) $exam->assessment_id : null);
    }

    // ───────────────────────────── Dữ liệu cho tab "Thiết lập đề" ─────────────────────────────

    public function builderState(CompetitionExam $exam): array
    {
        $usage = $this->usage($exam);
        $papers = CompetitionExamPaper::query()->where('competition_exam_id', $exam->id)->with(['publishedBy:id,name', 'assessment.items'])->orderBy('id')->get();

        return [
            'meta' => $this->meta(),
            'existing' => $exam->assessment_id !== null ? ($exam->assessment?->title ?? 'Đề #'.$exam->assessment_id) : null,
            'papers' => $papers->map(function (CompetitionExamPaper $p) use ($usage) {
                $applied = $p->isPublished() && $p->assessment !== null
                    ? $p->assessment->items->sortBy('order')->map(fn ($it) => ['id' => (int) $it->question_id, 'points' => (float) $it->points_override, 'row' => ''])->values()->all()
                    : [];
                $matrix = $this->normalizeMatrix($p->matrix ?: $this->defaultMatrix());
                $draft = $this->normalizeQuestions($p->draft['questions'] ?? []);

                return [
                    'id' => $p->id,
                    'label' => $p->label,
                    'status' => $p->status,
                    'version' => (int) $p->version,
                    'publishedAt' => $p->published_at?->format('H:i d/m/Y'),
                    'publishedBy' => $p->publishedBy?->name,
                    'lock' => $this->lockReason($p),
                    'duration' => (int) $p->duration_minutes,
                    'excludeUsed' => (bool) $p->exclude_used,
                    'matrix' => $matrix,
                    'questions' => $draft !== [] ? $draft : $this->withRows($applied, $matrix),
                    'hasDraft' => $draft !== [],
                    'applied' => $this->preview($applied, $usage),
                    'preview' => $draft !== [] ? $this->preview($draft, $usage) : $this->preview($applied, $usage),
                    'counts' => $this->counts($matrix, (bool) $p->exclude_used, $usage),
                    'issues' => $this->issues($matrix, (bool) $p->exclude_used, $usage),
                ];
            })->all(),
        ];
    }

    /** Gắn lại "thuộc phần nào" cho câu đã chốt (để sửa tiếp từ đề đang áp dụng). */
    private function withRows(array $questions, array $matrix): array
    {
        $bank = $this->bank();
        foreach ($questions as $i => $q) {
            foreach ($matrix as $row) {
                if (isset($bank[$q['id']]) && $this->matches($bank[$q['id']], ['usagePolicy' => 'random'] + $row, [])) {
                    $questions[$i]['row'] = $row['id'];
                    break;
                }
            }
        }

        return $questions;
    }

    /** Thông tin hiện ở danh sách "Duyệt đề dự kiến". */
    public function preview(array $questions, array $usage): array
    {
        if ($questions === []) {
            return [];
        }
        $bank = $this->bank();
        $ids = array_column($questions, 'id');
        $full = Question::query()->whereIn('id', $ids)->get(['id', 'code', 'title', 'type', 'body', 'grading_config', 'points', 'metadata'])->keyBy('id');

        return array_map(function ($q) use ($bank, $full, $usage) {
            $b = $bank[$q['id']] ?? null;
            $m = $full->get($q['id']);
            $options = array_values(array_map(fn ($o) => is_array($o) ? (string) ($o['text'] ?? $o['label'] ?? $o['value'] ?? '') : (string) $o, (array) ($m?->grading_config['options'] ?? [])));

            return $q + [
                'title' => $b['title'] ?? ($m?->title ?: 'Câu #'.$q['id']),
                'code' => $b['code'] ?? (string) ($m?->code ?? ''),
                'typeLabel' => $b['typeLabel'] ?? 'Câu hỏi',
                'difficultyLabel' => $b !== null ? QuestionDifficulty::label($b['difficulty']) : '—',
                'topics' => $b['tags'] ?? [],
                'bankName' => $b['bankName'] ?? '—',
                'bankPoints' => $b['points'] ?? null,
                'uses' => $usage[$q['id']] ?? 0,
                'statement' => Str::limit(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) ($m?->body ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'))), 700),
                'options' => array_slice(array_filter($options, fn ($o) => $o !== ''), 0, 8),
                'missing' => $b === null,
            ];
        }, $questions);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['paper' => $message]);
    }
}
