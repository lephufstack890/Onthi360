<?php

namespace App\Services\Teacher;

use App\Enums\ContentStatus;
use App\Models\Question;
use App\Models\User;
use App\Repositories\Contracts\QuestionRepositoryInterface;
use App\Services\QuestionPublishGuard;

/** Tổng hợp dữ liệu cho teacher.questions.index — kho riêng của giáo viên (6.5). */
class QuestionService
{
    public function __construct(
        private readonly QuestionRepositoryInterface $questions,
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
                'status' => $q->status === ContentStatus::Published ? 'Phát hành' : $this->draftLabel($q),
                'tone' => $q->status === ContentStatus::Published ? 'success' : 'warning',
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
}
