<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use App\Services\Teacher\TeacherWorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly TeacherWorkspaceService $workspace,
    ) {}

    /**
     * SỬA 6/11 (khách: "bỏ tab Thông báo lớp, chỉ để Hộp thư, thiết kế lại đẹp, có phân trang") — trang Thông báo của giáo viên
     * giờ CHỈ còn Hộp thư: lọc Tất cả / Chưa đọc, phân trang 10 thư mỗi trang (phân trang ở máy chủ), gom theo ngày.
     * Gửi thông báo cho lớp chuyển hẳn vào tab "Thông báo" trong trang từng lớp (teacher.classes.show?tab=notices).
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $filter = $request->query('loc') === 'unread' ? 'unread' : 'all';
        $query = $filter === 'unread' ? $user->unreadNotifications() : $user->notifications();
        $page = $query->paginate(10)->withQueryString();

        $today = now()->startOfDay();
        $items = $page->getCollection()->map(function ($n) use ($today) {
            $data = (array) $n->data;
            $at = $n->created_at;
            $day = $at?->copy()->startOfDay();

            return [
                'id' => $n->id,
                'icon' => $data['icon'] ?? '🔔',
                'tone' => $data['tone'] ?? 'neutral',
                'title' => (string) ($data['title'] ?? 'Thông báo'),
                'text' => (string) ($data['text'] ?? ''),
                'from' => (string) ($data['from'] ?? ''),
                'read' => $n->read_at !== null,
                'ago' => $at?->copy()->locale('vi')->diffForHumans(),
                'at' => $at?->format('H:i d/m/Y'),
                'group' => match (true) {
                    $day === null => 'Khác',
                    $day->equalTo($today) => 'Hôm nay',
                    $day->equalTo($today->copy()->subDay()) => 'Hôm qua',
                    default => $at->format('d/m/Y'),
                },
            ];
        })->all();

        return view('teacher.notifications.index', [
            'items' => $items,
            'page' => $page,
            'filter' => $filter,
            'unreadCount' => $user->unreadNotifications()->count(),
            'totalCount' => $user->notifications()->count(),
        ]);
    }
}
