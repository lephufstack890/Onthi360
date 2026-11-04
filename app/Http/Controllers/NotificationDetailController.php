<?php

namespace App\Http\Controllers;

use App\Models\ClassAnnouncement;
use App\Models\Role;
use App\Models\SystemAnnouncement;
use App\Services\DashboardRoutingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * SỬA 4/11 (khách: "hiển thị ra thông báo") — trang ĐỌC ĐỦ một thông báo. Trước đây bấm vào thông báo không có đường dẫn
 * (thông báo lớp của giáo viên…) thì bị đưa về trang tổng quan, người nhận chỉ đọc được 160 ký tự đầu ở chuông.
 *  · show(): thông báo trong hộp thư của chính người đang đăng nhập (đánh dấu đã đọc). Thông báo lớp / thông báo của
 *    Quản trị thì lấy nội dung đầy đủ từ bản gốc (khoá ref "cls:<id>" / "sys:<id>"); loại khác hiện tiêu đề + nội dung có sẵn.
 *  · publicShow(): thông báo Quản trị đang GHIM ở trang chủ — ai cũng xem được (bấm từ thanh "Thông báo hệ thống").
 */
class NotificationDetailController extends Controller
{
    public function show(Request $request, string $notification): View
    {
        $user = Auth::user();
        $record = $user->notifications()->where('id', $notification)->firstOrFail();
        $record->markAsRead();

        $data = (array) $record->data;
        $detail = [
            'icon' => (string) ($data['icon'] ?? '🔔'),
            'tone' => (string) ($data['tone'] ?? 'info'),
            'title' => (string) ($data['title'] ?? 'Thông báo'),
            'body' => (string) ($data['text'] ?? ''),
            'from' => (string) ($data['from'] ?? ''),
            'meta' => null,
            'important' => ($data['level'] ?? null) === 'important',
            'url' => $data['url'] ?? null,
            'time' => $record->created_at,
        ];

        $ref = (string) ($data['ref'] ?? '');
        if (str_starts_with($ref, 'sys:') && SystemAnnouncement::isReady()) {
            $a = SystemAnnouncement::query()->find((int) substr($ref, 4));
            if ($a !== null && $a->state !== SystemAnnouncement::STATE_DRAFT) {
                $detail['title'] = trim((string) $a->title) !== '' ? (string) $a->title : $detail['title'];
                $detail['body'] = (string) $a->body;
                $detail['from'] = 'Ban quản trị Ôn Thi 360';
                $detail['important'] = $a->level === 'important';
            }
        } elseif (str_starts_with($ref, 'cls:') && ClassAnnouncement::isReady()) {
            $c = ClassAnnouncement::query()->with(['classRoom:id,name', 'teacher:id,name'])->find((int) substr($ref, 4));
            if ($c !== null) {
                $detail['body'] = (string) $c->body;
                $detail['from'] = 'Giáo viên '.($c->teacher->name ?? '');
                $detail['meta'] = 'Lớp '.($c->classRoom->name ?? '');
            }
        }

        $wsRole = DashboardRoutingService::workspaceRoleFor($user);
        $back = match (true) {
            $wsRole === 'teacher' => route('teacher.notifications.index', ['tab' => 'inbox']),
            $wsRole === 'parent' => route('parent.notifications.index'),
            $wsRole === 'student' && $user->hasRole(Role::STUDENT) => route('student.notifications'),
            default => route('dashboard'),
        };

        return view('notifications.show', ['n' => $detail, 'wsRole' => $wsRole, 'backUrl' => $back]);
    }

    public function publicShow(int $announcement): View
    {
        abort_unless(SystemAnnouncement::isReady(), 404);
        $a = SystemAnnouncement::query()->findOrFail($announcement);
        abort_unless($a->isPinnedNow(), 404);

        return view('public.announcements.show', ['a' => $a]);
    }
}
