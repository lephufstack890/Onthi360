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
 * (2) $topic — "chuyên đề": SỬA 19/8 (Giai đoạn 6) — trước đây hệ thống chưa có bảng Tag/
 * Chuyên đề riêng nên tạm dùng App\Models\QuestionBank::name làm chiều lọc gần đúng; giờ đã
 * có App\Models\Tag thật (xem withQuestionMeta() bên dưới) nên đổi hẳn sang dùng Tag, đúng như
 * lời mời sửa ghi ở đây trước đó. Lưu ý: câu hỏi cũ tạo trước Giai đoạn 6 chưa được gắn tag nào
 * thì sẽ KHÔNG xuất hiện trong danh sách "Chuyên đề" cho tới khi Admin/Giáo viên vào sửa câu
 * đó và tick/thêm tag — đây là đánh đổi chấp nhận được của việc chuyển nguồn dữ liệu, không
 * phải lỗi.
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

        // SỬA 18/9 (khách: "copy lại UI bản mẫu rồi đổ dữ liệu database vào trước") — 3 ô số
        // liệu của bản mẫu (Bài được giao / Đã lưu / Lịch sử nộp) cần thêm 2 MỐC THỜI GIAN
        // THẬT cho dòng ghi chú, thay vì in con số minh hoạ như trong RoleWorkspace.jsx.
        // Mỗi mốc 1 câu truy vấn nhẹ (lấy đúng 1 bản ghi), không đụng gì tới $items/$counts.
        $nextDueAt = $classRoomIds === []
            ? null
            : $this->assignments->query()
                ->whereIn('class_room_id', $classRoomIds)
                ->where('status', 'open')
                ->whereNotNull('due_at')
                ->orderBy('due_at')
                ->first(['due_at'])?->due_at;

        $lastSubmittedAt = $this->attempts->recentSubmittedForUser($user->id, 1)->first()?->submitted_at;

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
            // SỬA 18/9 (khách: "làm bài rồi mà không hiển % tỉ lệ / không đổi thành Luyện lại")
            // — từ nay lượt LUYỆN THEO CÂU cũng được ghi lại (Student\PracticeByQuestionService
            // ::recordSubmission()). Lượt đó KHÔNG thuộc đề nào (assessment_id = null) nên tên
            // phải lấy từ chính các câu đã làm, và nút phải trỏ về trang Luyện tập chứ không
            // phải trang kết quả đề (trang đó dựng quanh một đề, không có đề thì vô nghĩa).
            'history' => $this->attempts->recentSubmittedForUser($user->id, 30)
                ->map(function ($attempt) {
                    $isSelfPractice = $attempt->assessment_id === null;

                    $questionCount = $isSelfPractice ? $attempt->answers()->count() : 0;

                    return [
                        'title' => $attempt->assessment->title
                            ?? ($isSelfPractice ? 'Tự luyện '.$questionCount.' câu' : 'Bài đã nộp'),
                        'type' => $attempt->assessment?->type?->value ?? '',
                        'typeLabel' => $attempt->assessment?->type?->label() ?? ($isSelfPractice ? 'Luyện theo câu' : ''),
                        'typeIcon' => $attempt->assessment?->type?->icon() ?? '📝',
                        'source' => $isSelfPractice ? 'Tự luyện' : ucfirst($attempt->source?->value ?? ''),
                        'difficulty' => '',
                        'status' => $attempt->total_score !== null ? 'Đã nộp — '.$attempt->total_score : 'Đang chấm',
                        'tone' => $attempt->is_provisional ? 'info' : 'success',
                        'takeRoute' => $isSelfPractice
                            ? route('practice.index')
                            : route('student.assessment.result', $attempt->id),
                    ];
                })->all(),
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
            // Khoá THÊM cho giao diện mới — mọi khoá cũ giữ nguyên nên không phá chỗ nào đang dùng.
            'counts' => $counts,
            'nextDueAt' => $nextDueAt,
            'lastSubmittedAt' => $lastSubmittedAt,
            // Nút "Làm đề hỗn hợp" của bản mẫu: trỏ vào ĐỀ TỰ LUYỆN đầu danh sách đang phát
            // hành — lấy từ chính $items đã nạp, KHÔNG thêm truy vấn. Tab khác (hoặc lọc xong
            // không còn đề nào) thì trả null và Blade ẩn nút, không đưa ra link chết.
            'quickStartHref' => ($tab === 'self' && $items !== []) ? ($items[0]['takeRoute'] ?? null) : null,
            'type' => $type,
            'topic' => $topic,
            'filtersApply' => $filtersApply,
            'availableTopics' => $availableTopics,
        ];
    }

    /**
     * Gắn 'questionTypes' (App\Enums\QuestionType[]) và 'topics' (tên Tag thật — Question::
     * tags(), xem class docblock) vào 1 item danh sách — dùng để lọc theo dạng câu hỏi/chuyên
     * đề mà KHÔNG cần truy vấn DB thêm (đã eager-load items.question.tags ở repository).
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

            foreach ($question->tags ?? [] as $tag) {
                $topics[$tag->name] = true;
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
            ->with('assessment.items.question.bank', 'assessment.items.question.tags', 'classRoom')
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
