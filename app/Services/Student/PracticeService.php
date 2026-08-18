<?php

namespace App\Services\Student;

use App\Models\Assessment;
use App\Models\User;
use App\Repositories\Contracts\AssessmentRepositoryInterface;
use App\Repositories\Contracts\AssignmentRepositoryInterface;
use App\Repositories\Contracts\AttemptRepositoryInterface;
use App\Repositories\Contracts\ClassEnrollmentRepositoryInterface;

/**
 * STU-04 — tabs Tự luyện · Theo lớp · Bài được giao · Đã lưu · Lịch sử.
 *
 * SỬA 18/8 (luồng Luyện tập): trước đây trang chỉ có 4 nút lọc UI ("Tất cả/Lập trình/Trắc
 * nghiệm/Điền đáp án/Độ khó") KHÔNG lọc được gì thật (xem TODO cũ ở view) — giờ lọc thật theo
 * 2 chiều lấy từ dữ liệu có sẵn, không thêm bảng mới:
 * (1) $type — App\Enums\QuestionType ('mcq'/'fill_blank'/'coding'), 1 đề có thể có NHIỀU dạng
 * câu hỏi trộn lẫn (6.3) nên lọc theo "đề có ít nhất 1 câu dạng này", không phải "đề CHỈ có
 * dạng này".
 * (2) $topic — "chuyên đề": hệ thống CHƯA có bảng Tag/Chuyên đề riêng, nên tạm dùng
 * App\Models\QuestionBank::name (ngân hàng câu hỏi thường đã được đặt tên theo chuyên đề/
 * chương, vd "Đại số 10 — Chương 1") làm chiều lọc gần đúng nhất hiện có — không tự bịa thêm
 * dữ liệu giả. Nếu sau này có bảng Chuyên đề/Tag thật, chỉ cần đổi nguồn lấy 'topics' bên dưới.
 * "Độ khó" CHƯA lọc được vì Question không có cột difficulty — giữ nguyên là nút vô hiệu hoá
 * (xem view) thay vì giả vờ lọc được.
 */
class PracticeService
{
    public function __construct(
        private ClassEnrollmentRepositoryInterface $classEnrollments,
        private AssessmentRepositoryInterface $assessments,
        private AssignmentRepositoryInterface $assignments,
        private AttemptRepositoryInterface $attempts,
    ) {}

    public function buildIndexData(User $user, string $tab, ?string $type = null, ?string $topic = null): array
    {
        $classRoomIds = $this->classEnrollments->activeClassRoomIdsForUser($user->id);

        $counts = [
            'self' => $this->assessments->countPublishedPractice(),
            'class' => $this->assignments->countForClassRoomIds($classRoomIds),
            'assigned' => $this->assignments->countForClassRoomIds($classRoomIds, 'open'),
            'saved' => 0, // TODO: chưa có bảng "đã lưu/bookmark".
            'history' => $this->attempts->countSubmittedForUser($user->id),
        ];

        $tabs = [
            ['label' => 'Tự luyện', 'href' => route('student.practice.index'), 'active' => $tab === 'self', 'count' => $counts['self']],
            ['label' => 'Theo lớp', 'href' => route('student.practice.index', ['tab' => 'class']), 'active' => $tab === 'class', 'count' => $counts['class']],
            ['label' => 'Bài được giao', 'href' => route('student.practice.index', ['tab' => 'assigned']), 'active' => $tab === 'assigned', 'count' => $counts['assigned']],
            ['label' => 'Đã lưu', 'href' => route('student.practice.index', ['tab' => 'saved']), 'active' => $tab === 'saved', 'count' => $counts['saved']],
            ['label' => 'Lịch sử', 'href' => route('student.practice.index', ['tab' => 'history']), 'active' => $tab === 'history', 'count' => $counts['history']],
        ];

        // 3 tab liệt kê đề (self/class/assigned) lọc được theo dạng câu hỏi + chuyên đề; tab
        // "Lịch sử" (đã nộp rồi) và "Đã lưu" (placeholder, chưa có dữ liệu) không áp dụng.
        $filterableTabs = ['self', 'class', 'assigned'];
        $filtersApply = in_array($tab, $filterableTabs, true);

        $items = match ($tab) {
            'class' => $this->assignments->forClassRoomIds($classRoomIds, null, 30)
                ->map(fn ($a) => $this->withQuestionMeta([
                    'title' => $a->assessment->title ?? 'Bài tập',
                    'type' => $a->assessment?->type?->value ?? '',
                    'typeLabel' => $a->assessment?->type?->label() ?? '',
                    'typeIcon' => $a->assessment?->type?->icon() ?? '📝',
                    'source' => 'Lớp '.($a->classRoom->name ?? ''),
                    'difficulty' => '',
                    'status' => $a->isOpenNow() ? 'Đã mở' : 'Đã đóng',
                    'tone' => $a->isOpenNow() ? 'success' : 'neutral',
                    'takeRoute' => route('student.assessment.take', ['assessment' => $a->assessment_id, 'assignment' => $a->id]),
                ], $a->assessment))->all(),
            'assigned' => $this->assignedTabItems($classRoomIds),
            'saved' => [], // TODO: chưa có bảng "đã lưu/bookmark".
            'history' => $this->attempts->recentSubmittedForUser($user->id, 30)
                ->map(fn ($attempt) => [
                    'title' => $attempt->assessment->title ?? 'Bài đã nộp',
                    'type' => $attempt->assessment?->type?->value ?? '',
                    'typeLabel' => $attempt->assessment?->type?->label() ?? '',
                    'typeIcon' => $attempt->assessment?->type?->icon() ?? '📝',
                    'source' => ucfirst($attempt->source?->value ?? ''),
                    'difficulty' => '',
                    'status' => $attempt->total_score !== null ? 'Đã nộp — '.$attempt->total_score : 'Đang chấm',
                    'tone' => $attempt->is_provisional ? 'info' : 'success',
                    'takeRoute' => route('student.assessment.result', $attempt->id),
                ])->all(),
            default => $this->assessments->publishedPractice(30)
                ->map(fn ($a) => $this->withQuestionMeta([
                    'title' => $a->title,
                    'type' => $a->type->value,
                    'typeLabel' => $a->type->label(),
                    'typeIcon' => $a->type->icon(),
                    'source' => 'Tự luyện',
                    'difficulty' => '',
                    'status' => 'Chưa làm',
                    'tone' => 'info',
                    'takeRoute' => route('student.assessment.take', $a->id),
                ], $a))->all(),
        };

        $availableTopics = [];

        if ($filtersApply) {
            if ($type !== null && $type !== '') {
                $items = array_values(array_filter($items, fn ($it) => in_array($type, $it['questionTypes'] ?? [], true)));
            }

            $availableTopics = collect($items)->pluck('topics')->flatten()->filter()->unique()->sort()->values()->all();

            if ($topic !== null && $topic !== '') {
                $items = array_values(array_filter($items, fn ($it) => in_array($topic, $it['topics'] ?? [], true)));
            }
        }

        return [
            'tab' => $tab,
            'tabs' => $tabs,
            'items' => $items,
            'type' => $type,
            'topic' => $topic,
            'filtersApply' => $filtersApply,
            'availableTopics' => $availableTopics,
        ];
    }

    /**
     * Gắn 'questionTypes' (App\Enums\QuestionType[]) và 'topics' (tên QuestionBank, xem class
     * docblock) vào 1 item danh sách — dùng để lọc theo dạng câu hỏi/chuyên đề mà KHÔNG cần
     * truy vấn DB thêm (đã eager-load items.question.bank ở repository).
     */
    private function withQuestionMeta(array $item, ?Assessment $assessment): array
    {
        $types = [];
        $topics = [];

        foreach ($assessment?->items ?? [] as $assessmentItem) {
            $question = $assessmentItem->question;

            if ($question === null) {
                continue;
            }

            $types[$question->type->value] = true;

            if ($question->bank?->name) {
                $topics[$question->bank->name] = true;
            }
        }

        $item['questionTypes'] = array_keys($types);
        $item['topics'] = array_keys($topics);

        return $item;
    }

    /**
     * Tab "Bài được giao": sắp xếp theo due_at tăng dần + eager-load ['assessment','classRoom']
     * — khác forClassRoomIds() (sắp xếp theo opens_at giảm dần, eager-load thêm classRoom.course)
     * nên dùng query() (van an toàn của repo) để giữ đúng hành vi cũ.
     */
    private function assignedTabItems(array $classRoomIds): array
    {
        return $this->assignments->query()
            ->whereIn('class_room_id', $classRoomIds)
            ->where('status', 'open')
            ->with('assessment.items.question.bank', 'classRoom')
            ->orderBy('due_at')
            ->limit(30)
            ->get()
            ->map(fn ($a) => $this->withQuestionMeta([
                'title' => $a->assessment->title ?? 'Bài tập',
                'type' => $a->assessment?->type?->value ?? '',
                'typeLabel' => $a->assessment?->type?->label() ?? '',
                'typeIcon' => $a->assessment?->type?->icon() ?? '📝',
                'source' => 'Lớp '.($a->classRoom->name ?? ''),
                'difficulty' => '',
                'status' => $a->due_at ? 'Hạn: '.$a->due_at->format('d/m H:i') : 'Đang mở',
                'tone' => 'warning',
                'takeRoute' => route('student.assessment.take', ['assessment' => $a->assessment_id, 'assignment' => $a->id]),
            ], $a->assessment))->all();
    }
}
