<?php

namespace App\Services\Admin;

use App\Models\AccessRight;
use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\Question;
use App\Models\ReviewReport;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\ContactMessageRepositoryInterface;
use App\Repositories\Contracts\DraftQuestionRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Repositories\Contracts\TeacherProfileRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;

/**
 * Gom số liệu vận hành cho admin.dashboard (ADM-01, 2.1, 16 mục 9).
 */
class DashboardService
{
    public function __construct(
        private TeacherProfileRepositoryInterface $teacherProfiles,
        private OrderRepositoryInterface $orders,
        private ReviewRepositoryInterface $reviews,
        private UserRepositoryInterface $users,
        private DraftQuestionRepositoryInterface $draftQuestions,
        private AuditLogRepositoryInterface $auditLogs,
        // SỬA 15/9 — yêu cầu hỗ trợ cũng là một hàng chờ vận hành, trước đây bảng điều khiển
        // không đếm nên quản trị chỉ biết có phiếu mới khi tình cờ mở mục đó ra.
        private ContactMessageRepositoryInterface $contactMessages,
    ) {}

    /**
     * Các hàng chờ đang tồn đọng — MỘT NGUỒN DUY NHẤT.
     *
     * SỬA 15/9 — tách ra khỏi dashboardData() vì khối "Không gian học tập" ở trang chủ cũng
     * cần đúng bộ số này cho vai trò quản trị. Viết lại truy vấn ở đó thì sớm muộn hai màn
     * hiện hai con số khác nhau cho cùng một hàng chờ, và người ta sẽ tin màn nào?
     *
     * @return array{teachers:int, orders:int, reviews:int, support:int}
     */
    public function pendingCounts(): array
    {
        return [
            'teachers' => $this->teacherProfiles->countPending(),
            // SỬA 15/9 — dùng CHUNG hằng với viên số cạnh mục menu "Đơn hàng" thay vì tự
            // liệt kê trạng thái ở đây, để 2 chỗ không bao giờ hiện 2 con số khác nhau.
            'orders' => $this->orders->countByStatuses(OrderService::AWAITING_APPROVAL_STATUSES),
            'reviews' => $this->reviews->countPendingModeration(),
            'support' => $this->contactMessages->countNew(),
        ];
    }

    /** Ngưỡng "quyền sắp hết hạn" — trước đây ghi chú TODO chưa chốt, tạm lấy 7 ngày như nhãn cũ của thẻ. */
    public const ACCESS_EXPIRING_DAYS = 7;

    /** Cửa sổ tính "người dùng hoạt động" (ngày) — khớp chữ "trong 30 ngày" trên thẻ. */
    public const ACTIVE_USER_DAYS = 30;

    /**
     * SỬA 8/10 (khách: "dựa vào source mới cập nhật lại UI màn tổng quan admin, dữ liệu lấy từ
     * database đổ vào") — dựng lại theo education-main/src/components/RoleWorkspace.jsx,
     * hàm AdminOverview(): 4 thẻ số liệu, danh sách "Việc cần xử lý", thẻ "Hoạt động học tập".
     * Bản mẫu chỉ có số bịa (12.840, 28, 46, 12, +18%) — ở đây mọi con số đều đếm từ DB:
     *
     *   Người dùng hoạt động = số người DISTINCT có ít nhất một lượt làm bài (bảng attempts, gồm cả
     *     luyện tập theo câu) bắt đầu trong 30 ngày qua; % so với 30 ngày liền trước đó. Hệ thống
     *     chưa lưu thời điểm đăng nhập nên "làm bài" là dấu hiệu hoạt động duy nhất đo được.
     *   Chờ phê duyệt = GV chờ duyệt + đơn chờ duyệt + câu hỏi + đề đang ở trạng thái pending_review.
     *   Quyền sắp hết hạn = access_rights đang active, expires_at rơi vào ACCESS_EXPIRING_DAYS ngày tới.
     *   Đánh giá cần xử lý = đánh giá đang chờ kiểm duyệt; chú thích = số đánh giá có báo cáo chưa xử lý.
     *   Hoạt động học tập = lượt nộp bài (attempts.submitted_at) 7 ngày qua so với 7 ngày trước đó.
     *
     * @return array{metrics: array, tasks: array, learning: array, activity: array}
     */
    public function dashboardData(): array
    {
        $pending = $this->pendingCounts();
        $now = now();

        // --- Người dùng hoạt động (30 ngày) ---
        $days = self::ACTIVE_USER_DAYS;
        $activeNow = (int) Attempt::query()->where('started_at', '>=', $now->copy()->subDays($days))->distinct()->count('user_id');
        $activePrev = (int) Attempt::query()
            ->where('started_at', '>=', $now->copy()->subDays($days * 2))
            ->where('started_at', '<', $now->copy()->subDays($days))
            ->distinct()->count('user_id');
        $totalUsers = (int) $this->users->count();
        $activeChange = $this->percentChange($activeNow, $activePrev);

        // --- Chờ phê duyệt ---
        $pendingQuestions = (int) Question::query()->where('status', 'pending_review')->count();
        $pendingAssessments = (int) Assessment::query()->where('status', 'pending_review')->count();
        $pendingContent = $pendingQuestions + $pendingAssessments;
        $pendingTotal = $pending['teachers'] + $pending['orders'] + $pendingContent;

        // --- Quyền sắp hết hạn ---
        $expiring = (int) AccessRight::query()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$now, $now->copy()->addDays(self::ACCESS_EXPIRING_DAYS)])
            ->count();

        // --- Đánh giá ---
        $reportedReviews = (int) ReviewReport::query()->where('status', 'pending')->distinct()->count('review_id');

        $metrics = [
            [
                'icon' => 'users', 'tone' => 'blue', 'label' => 'Người dùng hoạt động',
                'value' => number_format($activeNow, 0, ',', '.'),
                'detail' => ($activeChange['text'] !== null ? $activeChange['text'].' trong '.$days.' ngày' : $days.' ngày qua')
                    .' · tổng '.number_format($totalUsers, 0, ',', '.').' tài khoản',
                'href' => route('admin.users.index'),
            ],
            [
                'icon' => 'file-check-2', 'tone' => 'amber', 'label' => 'Chờ phê duyệt',
                'value' => number_format($pendingTotal, 0, ',', '.'),
                'detail' => $pendingTotal > 0
                    ? $pendingContent.' nội dung · '.$pending['orders'].' đơn · '.$pending['teachers'].' giáo viên'
                    : 'Không có gì đang chờ duyệt',
                'href' => '#viec-can-xu-ly',
            ],
            [
                'icon' => 'wallet-cards', 'tone' => 'violet', 'label' => 'Quyền sắp hết hạn',
                'value' => number_format($expiring, 0, ',', '.'),
                'detail' => $expiring > 0 ? 'Hết hạn trong '.self::ACCESS_EXPIRING_DAYS.' ngày tới, cần nhắc gia hạn' : 'Không có quyền nào hết hạn trong '.self::ACCESS_EXPIRING_DAYS.' ngày tới',
                'href' => route('admin.access-rights.index'),
            ],
            [
                'icon' => 'star', 'tone' => 'emerald', 'label' => 'Đánh giá cần xử lý',
                'value' => number_format($pending['reviews'], 0, ',', '.'),
                'detail' => $reportedReviews > 0 ? $reportedReviews.' đánh giá đã bị báo cáo' : 'Chưa có đánh giá nào bị báo cáo',
                'href' => route('admin.reviews.index'),
            ],
        ];

        // "Việc cần xử lý": mỗi hàng là một hàng chờ THẬT, bấm vào là tới đúng màn xử lý.
        $tasks = [
            ['count' => $pending['orders'], 'label' => 'Đơn thanh toán cần xác nhận', 'href' => route('admin.orders.index')],
            ['count' => $pending['teachers'], 'label' => 'Hồ sơ giáo viên cần duyệt', 'href' => route('admin.teacher-approvals.index')],
            ['count' => $pending['reviews'], 'label' => 'Đánh giá chờ kiểm duyệt', 'href' => route('admin.reviews.index')],
            ['count' => $pending['support'], 'label' => 'Yêu cầu hỗ trợ mới', 'href' => route('admin.contact-messages.index')],
            ['count' => $pendingAssessments, 'label' => 'Đề thi cần rà soát', 'href' => route('admin.content.index', ['tab' => 'assessments'])],
            ['count' => $pendingQuestions, 'label' => 'Câu hỏi cần rà soát', 'href' => route('admin.content.index', ['tab' => 'questions'])],
        ];

        // --- Hoạt động học tập: lượt nộp bài 7 ngày qua so với 7 ngày trước đó ---
        $weekNow = (int) Attempt::query()->whereNotNull('submitted_at')->where('submitted_at', '>=', $now->copy()->subDays(7))->count();
        $weekPrev = (int) Attempt::query()->whereNotNull('submitted_at')
            ->where('submitted_at', '>=', $now->copy()->subDays(14))
            ->where('submitted_at', '<', $now->copy()->subDays(7))->count();
        $weekChange = $this->percentChange($weekNow, $weekPrev);
        $learning = [
            // Có số tuần trước → hiện %; chưa có (0) thì % vô nghĩa nên hiện thẳng số lượt tuần này.
            'value' => $weekChange['text'] ?? number_format($weekNow, 0, ',', '.'),
            'unit' => $weekChange['text'] === null ? 'lượt' : null,
            'trend' => $weekChange['trend'],
            'caption' => match (true) {
                $weekNow === 0 && $weekPrev === 0 => 'Chưa có lượt nộp bài nào trong 14 ngày qua.',
                $weekPrev === 0 => 'Có '.number_format($weekNow, 0, ',', '.').' lượt nộp bài trong 7 ngày qua, 7 ngày trước đó chưa có lượt nào.',
                default => number_format($weekNow, 0, ',', '.').' lượt nộp bài trong 7 ngày qua, so với '.number_format($weekPrev, 0, ',', '.').' lượt của 7 ngày trước đó.',
            },
            'headline' => match ($weekChange['trend']) {
                'up' => 'Lượt hoàn thành bài tăng so với 7 ngày trước',
                'down' => 'Lượt hoàn thành bài giảm so với 7 ngày trước',
                default => 'Lượt hoàn thành bài trong 7 ngày qua',
            },
        ];

        $activity = $this->auditLogs->latestWithActor(8)->map(fn ($log) => [
            'time' => $log->created_at?->diffForHumans(),
            'text' => $log->action.($log->reason ? ' — '.$log->reason : ''),
            'actor' => $log->actor->email ?? 'system',
        ])->all();

        return ['metrics' => $metrics, 'tasks' => $tasks, 'learning' => $learning, 'activity' => $activity];
    }

    /**
     * % thay đổi $current so với $previous, định dạng kiểu Việt ("+8,4%", "−12%", "0%").
     * $previous = 0 thì không có mốc so sánh → text = null (để nơi gọi tự chọn cách hiện).
     *
     * @return array{text:?string, trend:string}
     */
    private function percentChange(int $current, int $previous): array
    {
        if ($previous <= 0) {
            return ['text' => null, 'trend' => $current > 0 ? 'up' : 'flat'];
        }

        $pct = ($current - $previous) / $previous * 100;
        $rounded = round($pct, 1);
        if ($rounded == 0.0) {
            return ['text' => '0%', 'trend' => 'flat'];
        }
        // Số nguyên thì bỏ ",0" cho gọn.
        $abs = number_format(abs($rounded), floor($rounded) == $rounded ? 0 : 1, ',', '.');

        return ['text' => ($rounded > 0 ? '+' : '−').$abs.'%', 'trend' => $rounded > 0 ? 'up' : 'down'];
    }
}
