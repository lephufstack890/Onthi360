<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ClassAnnouncement;
use App\Services\Teacher\TeacherWorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * SỬA 12/10 — soạn / đăng thông báo lớp (TeacherNoticePanel của source mới). Đăng xong gửi thông báo
 * hệ thống tới học sinh trong lớp và phụ huynh đã liên kết (nếu chọn) — xem
 * TeacherWorkspaceService::saveAnnouncement().
 */
class NoticeController extends Controller
{
    public function __construct(private readonly TeacherWorkspaceService $workspace) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'class_room_id' => ['required', 'integer'],
            'audience' => ['required', 'in:students,families'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:3000'],
            'action' => ['required', 'in:draft,publish'],
        ], [
            'class_room_id.required' => 'Hãy chọn lớp nhận thông báo.',
            'title.max' => 'Tiêu đề tối đa 120 ký tự.',
            'body.max' => 'Nội dung tối đa 3000 ký tự.',
        ]);

        $publish = $data['action'] === 'publish';

        try {
            $this->workspace->saveAnnouncement(Auth::user(), $data, $publish);
        } catch (ValidationException $e) {
            return redirect()->route('teacher.notifications.index', ['tab' => 'compose'] + (! empty($data['id']) ? ['edit' => $data['id']] : []))
                ->withErrors($e->errors())->withInput();
        }

        return redirect()->route('teacher.notifications.index', ['tab' => 'compose'])
            ->with('notice_status', $publish ? 'published' : 'draft');
    }

    public function destroy(int $notice): RedirectResponse
    {
        $this->workspace->deleteDraft(Auth::user(), $notice);

        return redirect()->route('teacher.notifications.index', ['tab' => 'compose'])->with('notice_status', 'deleted');
    }
}
