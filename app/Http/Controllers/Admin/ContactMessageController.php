<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContactMessageStatus;
use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Services\Admin\ContactMessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Yêu cầu hỗ trợ gửi từ trang Thông tin công khai.
 *
 * CHỈ QUẢN TRỊ VIÊN: toàn bộ đường dẫn của lớp này khai báo bên trong nhóm
 * Route::middleware(['role:admin,super_admin']) ở routes/web.php.
 */
class ContactMessageController extends Controller
{
    public function __construct(private readonly ContactMessageService $contactMessageService) {}

    public function index(Request $request): View
    {
        return view('admin.contact-messages.index', $this->contactMessageService->indexData([
            'status' => $request->query('status'),
            'topic' => $request->query('topic'),
            'q' => $request->query('q'),
        ]));
    }

    /** Đổi trạng thái: nhận xử lý / đã xử lý / đánh dấu rác / mở lại. */
    public function updateStatus(Request $request, ContactMessage $contactMessage): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_column(ContactMessageStatus::cases(), 'value'))],
        ]);

        $status = ContactMessageStatus::from($data['status']);

        $this->contactMessageService->changeStatus(Auth::user(), $contactMessage, $status);

        return back()->with('status', 'contact-status-changed')
            ->with('contact-status-label', $status->label());
    }

    /** Ghi chú nội bộ — không bao giờ gửi ra ngoài cho người gửi phiếu. */
    public function note(Request $request, ContactMessage $contactMessage): RedirectResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $this->contactMessageService->schemaReady()) {
            // Cột admin_note chưa có — nói thẳng thay vì để câu lệnh SQL báo lỗi 500.
            return back()->with('status', 'contact-note-unavailable');
        }

        $this->contactMessageService->saveNote(Auth::user(), $contactMessage, $data['admin_note'] ?? null);

        return back()->with('status', 'contact-note-saved');
    }

    /**
     * Giữ lại đường dẫn cũ admin.contact-messages.resolve để các liên kết/dấu trang cũ không
     * gãy — chuyển thẳng sang luồng đổi trạng thái mới.
     */
    public function resolve(Request $request, ContactMessage $contactMessage): RedirectResponse
    {
        $this->contactMessageService->changeStatus(Auth::user(), $contactMessage, ContactMessageStatus::Resolved);

        return back()->with('status', 'contact-status-changed')
            ->with('contact-status-label', ContactMessageStatus::Resolved->label());
    }
}
