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
use App\Services\QuestionPublishGuard;

/**
 * Tổng hợp dữ liệu cho teacher.questions.index/create/edit — kho riêng của
 * giáo viên (6.5): chỉ giáo viên tạo/chỉnh/sử dụng trong lớp của mình; không
 * mặc định thấy/sửa kho chung hoặc kho giáo viên khác.
 */
class QuestionService
{
    public function __construct(
        private readonly QuestionRepositoryInterface $questions,
        private readonly QuestionBankRepositoryInterface $questionBanks,
        private readonly QuestionPublishGuard $publishGuard,
    ) {}

    public function listForTeacher(User $user, string $tab): array
    {
        $counts = [
            'all' => $this->questions->countByOwner($user->id),
            'published' => $this->questions->countByOwner($user->id, ContentStatus::Published->value),
            'draft' => $this->questions->countByOwner($user->id, ContentStatus::Draft->value),
        ];

        $tabs = [
            ['label' => 'Tất cả', 'href' => route('teacher.questions.index'), 'active' => $tab === 'all', 'count' => $counts['all']],
            ['label' => 'Đã phát hành', 'href' => route('teacher.questions.index', ['tab' => 'published']), 'active' => $tab === 'published', 'count' => $counts['published']],
            ['label' => 'Nháp', 'href' => route('teacher.questions.index', ['tab' => 'draft']), 'active' => $tab === 'draft', 'count' => $counts['draft']],
        ];

        $statusFilter = match ($tab) {
            'published' => ContentStatus::Published->value,
            'draft' => ContentStatus::Draft->value,
            default => null,
        };

        $questions = $this->questions->byOwner($user->id, $statusFilter, 50)
            ->map(fn (Question $q) => [
                'id' => $q->id,
                'title' => $q->title,
                'type' => $q->type->value,
                'status' => $q->status === ContentStatus::Published ? 'Phát hành' : ($q->status === ContentStatus::Archived ? 'Lưu trữ' : $this->draftLabel($q)),
                'tone' => $q->status === ContentStatus::Published ? 'success' : ($q->status === ContentStatus::Archived ? 'neutral' : 'warning'),
                'canPublish' => $q->status === ContentStatus::Draft,
                'canArchive' => $q->status !== ContentStatus::Archived,
            ])->all();

        return ['tab' => $tab, 'tabs' => $tabs, 'questions' => $questions];
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

    /** Kho riêng của giáo viên (6.5) — tự tạo nếu chưa có, mỗi giáo viên đúng 1 kho riêng. */
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

        return $this->questions->create([
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
            return $this->publishGuard->createNewVersion($question, $attributes);
        }

        $question->update($attributes);

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
     */
    private function buildGradingConfig(string $type, array $data): array
    {
        return match ($type) {
            'mcq' => [
                'options' => array_values(array_filter($data['options'] ?? [], fn ($o) => filled($o))),
                'correct_options' => array_values(array_filter([$data['correct_option'] ?? null], fn ($v) => filled($v))),
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
}
