<?php

namespace App\Services\Teacher;

use App\Enums\ContentStatus;
use App\Enums\OwnerType;
use App\Enums\Visibility;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\User;
use App\Repositories\Contracts\QuestionBankRepositoryInterface;
use App\Repositories\Contracts\QuestionRepositoryInterface;
use App\Repositories\Contracts\TagRepositoryInterface;
use App\Services\QuestionPublishGuard;
use Illuminate\Database\Eloquent\Collection;

/**
 * Tổng hợp dữ liệu cho teacher.questions.index/create/edit — kho riêng của
 * giáo viên (6.5): chỉ giáo viên tạo/chỉnh/sử dụng trong lớp của mình; không
 * mặc định thấy/sửa kho chung hoặc kho giáo viên khác.
 */
class QuestionService
{
    /** Giới hạn hiển thị/trang — đủ lớn để "Tất cả" thực sự hiện hết trong đa số trường hợp thực tế. */
    private const LIST_LIMIT = 200;

    public function __construct(
        private readonly QuestionRepositoryInterface $questions,
        private readonly QuestionBankRepositoryInterface $questionBanks,
        private readonly QuestionPublishGuard $publishGuard,
        private readonly TagRepositoryInterface $tags,
    ) {}

    /** teacher.questions.create/.edit — danh sách tag để tick chọn (xem App\Models\Tag). */
    public function allTags(): Collection
    {
        return $this->tags->allOrderedByName();
    }

    /**
     * teacher.questions.index — kho riêng của giáo viên + tab "Kho chung" chỉ để XEM
     * (6.5: giáo viên vẫn không tự sửa/phát hành/lưu trữ được câu thuộc Kho chung —
     * đó là việc của Admin/Editor; teacher.assessments.store vẫn chỉ nhận câu thuộc
     * đúng kho riêng của giáo viên đó khi soạn đề, không đổi ở đây).
     */
    public function listForTeacher(User $user, string $tab): array
    {
        $counts = [
            'all' => $this->questions->countByOwner($user->id),
            'published' => $this->questions->countByOwner($user->id, ContentStatus::Published->value),
            'draft' => $this->questions->countByOwner($user->id, ContentStatus::Draft->value),
            'shared' => $this->questions->countShared(),
        ];

        $tabs = [
            ['label' => 'Tất cả', 'href' => route('teacher.questions.index'), 'active' => $tab === 'all', 'count' => $counts['all']],
            ['label' => 'Đã phát hành', 'href' => route('teacher.questions.index', ['tab' => 'published']), 'active' => $tab === 'published', 'count' => $counts['published']],
            ['label' => 'Nháp', 'href' => route('teacher.questions.index', ['tab' => 'draft']), 'active' => $tab === 'draft', 'count' => $counts['draft']],
            ['label' => 'Kho chung (chỉ xem)', 'href' => route('teacher.questions.index', ['tab' => 'shared']), 'active' => $tab === 'shared', 'count' => $counts['shared']],
        ];

        if ($tab === 'shared') {
            $questions = $this->questions->sharedLatestWithOwner(self::LIST_LIMIT)
                ->map(fn (Question $q) => $this->mapQuestionRow($q, readOnly: true))
                ->all();

            return ['tab' => $tab, 'tabs' => $tabs, 'questions' => $questions, 'total' => $counts['shared']];
        }

        $statusFilter = match ($tab) {
            'published' => ContentStatus::Published->value,
            'draft' => ContentStatus::Draft->value,
            default => null,
        };

        $total = match ($tab) {
            'published' => $counts['published'],
            'draft' => $counts['draft'],
            default => $counts['all'],
        };

        $questions = $this->questions->byOwner($user->id, $statusFilter, self::LIST_LIMIT)
            ->map(fn (Question $q) => $this->mapQuestionRow($q, readOnly: false))
            ->all();

        return ['tab' => $tab, 'tabs' => $tabs, 'questions' => $questions, 'total' => $total];
    }

    private function mapQuestionRow(Question $q, bool $readOnly): array
    {
        return [
            'id' => $q->id,
            'title' => $q->title,
            'type' => $q->type->value,
            'status' => $q->status === ContentStatus::Published ? 'Phát hành' : ($q->status === ContentStatus::Archived ? 'Lưu trữ' : $this->draftLabel($q)),
            'tone' => $q->status === ContentStatus::Published ? 'success' : ($q->status === ContentStatus::Archived ? 'neutral' : 'warning'),
            'canPublish' => ! $readOnly && $q->status === ContentStatus::Draft,
            'canArchive' => ! $readOnly && $q->status !== ContentStatus::Archived,
            'readOnly' => $readOnly,
        ];
    }

    /**
     * Nhãn nháp chi tiết dựa trên App\Services\QuestionPublishGuard::canPublish() —
     * cùng một cổng kiểm tra dùng khi thật sự phát hành (6.2/6.4), tránh 2 nơi lệch luật.
     */
    private function draftLabel(Question $q): string
    {
        $decision = $this->publishGuard->canPublish($q);

        if ($decision->allowed) {
            return 'Nháp';
        }

        return match ($decision->primaryReasonCode) {
            'missing_content' => 'Nháp — thiếu nội dung',
            'requires_new_version' => 'Nháp — cần tạo phiên bản mới',
            default => 'Nháp — thiếu cấu hình chấm',
        };
    }

    /** Chỉ giáo viên sở hữu mới được xem/sửa (6.5: không mặc định thấy/sửa kho giáo viên khác). */
    public function findOwned(User $teacher, int $id): Question
    {
        $question = $this->questions->findOrFail($id);

        abort_unless($question->owner_type === OwnerType::Teacher && (int) $question->owner_id === $teacher->id, 403);

        return $question;
    }

    public function findOrCreatePersonalBank(User $teacher): QuestionBank
    {
        $bank = $this->questionBanks->findPersonalBank($teacher->id);

        if ($bank !== null) {
            return $bank;
        }

        return $this->questionBanks->create([
            'name' => 'Kho câu hỏi của '.$teacher->name,
            'owner_type' => OwnerType::Teacher,
            'owner_id' => $teacher->id,
        ]);
    }

    /** teacher.questions.store — tạo câu hỏi mới trong kho riêng (6.5), luôn bắt đầu "Nháp". */
    public function store(User $teacher, array $data): Question
    {
        $bank = $this->findOrCreatePersonalBank($teacher);

        $question = $this->questions->create([
            'bank_id' => $bank->id,
            'code' => 'Q-'.$teacher->id.'-'.now()->format('ymd').'-'.random_int(1000, 9999),
            'owner_type' => OwnerType::Teacher,
            'owner_id' => $teacher->id,
            'visibility' => Visibility::Private,
            'status' => ContentStatus::Draft,
            'version' => 1,
            'created_by' => $teacher->id,
            ...$this->buildAttributes($data),
        ]);

        $question->tags()->sync($this->resolveTagIds($data));

        return $question;
    }

    /**
     * teacher.questions.update — nếu câu đã có người làm (AttemptAnswer tồn tại), KHÔNG sửa
     * âm thầm mà tạo phiên bản mới (6.2: "không sửa âm thầm làm thay đổi ý nghĩa kết quả cũ"),
     * dùng lại đúng App\Services\QuestionPublishGuard::createNewVersion() — cùng luật với
     * lúc publish, tránh 2 nơi lệch nhau.
     */
    public function update(Question $question, array $data): Question
    {
        $attributes = $this->buildAttributes($data);

        if ($this->publishGuard->hasBeenAttempted($question)) {
            $newVersion = $this->publishGuard->createNewVersion($question, $attributes);
            $newVersion->tags()->sync($this->resolveTagIds($data));

            return $newVersion;
        }

        $question->update($attributes);
        $question->tags()->sync($this->resolveTagIds($data));

        return $question;
    }

    /**
     * teacher.questions.publish — cùng cổng App\Services\QuestionPublishGuard dùng cho mọi
     * nguồn nhập liệu (6.2, 6.4), không tách riêng luật cho câu tạo tay và câu từ OCR.
     *
     * @throws \Illuminate\Validation\ValidationException nếu chưa đủ điều kiện phát hành.
     */
    public function publish(Question $question): Question
    {
        $decision = $this->publishGuard->canPublish($question);

        if (! $decision->allowed) {
            throw \Illuminate\Validation\ValidationException::withMessages(['publish' => $decision->message]);
        }

        $question->update(['status' => ContentStatus::Published]);

        return $question;
    }

    /** teacher.questions.archive — gỡ khỏi lưu hành, không xóa (Table 27: chỉ 4 trạng thái nội dung). */
    public function archive(Question $question): Question
    {
        $question->update(['status' => ContentStatus::Archived]);

        return $question;
    }

    private function buildAttributes(array $data): array
    {
        return [
            'type' => $data['type'],
            'title' => $data['title'],
            'body' => $data['body'],
            'points' => (int) $data['points'],
            'grading_config' => $this->buildGradingConfig($data['type'], $data),
        ];
    }

    /**
     * Cấu trúc grading_config theo loại câu (6.1/6.2) — đọc lại bởi
     * Question::hasMinimumGradingConfig() để xác định đủ điều kiện phát hành.
     *
     * SỬA 19/8 — LỖI CHẤM ĐIỂM THẬT (phát hiện khi làm Giai đoạn 6): 'correct_options' PHẢI
     * lưu CHỈ SỐ (int 0-3, khớp thứ tự mảng 'options') vì App\Services\AttemptService::
     * gradeMcq() so khớp bằng array_map('intval', ...) — trước đây hàm này lưu THẲNG giá trị
     * $data['correct_option'] gửi từ form (khi đó là chữ cái "A"/"B"/"C"/"D", xem sửa cùng lúc
     * ở resources/views/teacher/questions/create.blade.php), intval("B")/("C")/("D") đều ra 0
     * → mọi câu Trắc nghiệm giáo viên tự tạo tay có đáp án đúng KHÁC phương án A đều bị chấm
     * SAI cho học sinh chọn đúng. Ép (int) ở đây, đúng y hệt Admin\ContentService::
     * buildGradingConfig() đã làm từ đầu (nơi \DUY NHẤT trước đó làm đúng logic này).
     */
    private function buildGradingConfig(string $type, array $data): array
    {
        return match ($type) {
            'mcq' => [
                'options' => array_values(array_filter($data['options'] ?? [], fn ($o) => filled($o))),
                'correct_options' => isset($data['correct_option']) && $data['correct_option'] !== ''
                    ? [(int) $data['correct_option']]
                    : [],
            ],
            'fill_blank' => [
                'accepted_answers' => array_values(array_filter(array_map('trim', explode(',', (string) ($data['accepted_answers'] ?? ''))), fn ($v) => $v !== '')),
                'case_sensitive' => (bool) ($data['case_sensitive'] ?? false),
            ],
            'coding' => [
                'test_cases' => $data['test_cases_parsed'] ?? [],
                'time_limit_ms' => filled($data['time_limit_ms'] ?? null) ? (int) $data['time_limit_ms'] : null,
                'memory_limit_mb' => filled($data['memory_limit_mb'] ?? null) ? (int) $data['memory_limit_mb'] : null,
            ],
            default => [],
        };
    }

    /**
     * SỬA 19/8 (Giai đoạn 6 — "Gắn tag/chủ đề cho câu hỏi"): gộp tag có sẵn (tick,
     * "tag_ids[]") + tag gõ mới (cách nhau bằng dấu phẩy, "new_tags") thành 1 danh sách ID
     * để sync vào Question::tags(). Cố ý TRÙNG LOGIC với App\Services\Admin\ContentService::
     * resolveTagIds() thay vì gọi chéo sang service của Admin — 2 tầng Teacher/Admin trong
     * codebase này vốn độc lập nhau (không service nào gọi service của tầng còn lại), tách
     * riêng 1 helper dùng chung cho cả 2 tầng sẽ phải tạo 1 lớp mới chỉ để tránh 10 dòng
     * trùng — chưa đáng, nếu sau này logic phức tạp hơn thì tách ra App\Services\TagResolver
     * dùng chung.
     */
    private function resolveTagIds(array $data): array
    {
        $tagIds = array_map('intval', $data['tag_ids'] ?? []);
        $newNames = array_filter(array_map('trim', explode(',', (string) ($data['new_tags'] ?? ''))), fn ($n) => $n !== '');

        foreach ($newNames as $name) {
            $tagIds[] = $this->tags->findOrCreateByName($name)->id;
        }

        return array_values(array_unique($tagIds));
    }
}
