<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\Teacher\ScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * teacher.schedule.* (TEA-01/02, 8.2 "Lớp học: ... lịch, điểm danh, thông báo") — lịch buổi
 * học xuyên tất cả lớp giáo viên phụ trách, điểm danh, tổng kết buổi học, và tài nguyên
 * riêng cho từng buổi (note họp 13/8, mục 3). Toàn bộ nghiệp vụ/kiểm tra quyền (giáo viên
 * có thực sự phụ trách lớp không, roster thực tế, sở hữu tài liệu/câu hỏi/đề...) nằm ở
 * App\Services\Teacher\ScheduleService — controller chỉ validate input thô rồi gọi service.
 */
class ScheduleController extends Controller
{
    public function __construct(private readonly ScheduleService $scheduleService) {}

    /** teacher.schedule.index — lịch buổi học của mọi lớp giáo viên phụ trách. */
    public function index(Request $request): View
    {
        return view('teacher.schedule.index', $this->scheduleService->indexData(Auth::user()));
    }

    /** Ngày (input date) + giờ/phút (2 dropdown) rời rạc, xem partials.session-datetime-fields. */
    private function storeRules(): array
    {
        return [
            'class_room_id' => ['required', 'integer'],
            'topic' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_date' => ['required', 'date'],
            'starts_hour' => ['required', 'string'],
            'starts_minute' => ['required', 'string'],
            'ends_date' => ['required', 'date'],
            'ends_hour' => ['required', 'string'],
            'ends_minute' => ['required', 'string'],
        ];
    }

    /** Gộp cặp ngày + giờ + phút rời rạc thành 1 chuỗi datetime "Y-m-d H:i:00" để Carbon parse. */
    private function combineDateTime(array $data, string $prefix): string
    {
        return sprintf('%s %s:%s:00', $data[$prefix.'_date'], $data[$prefix.'_hour'], $data[$prefix.'_minute']);
    }

    /** teacher.schedule.store — tạo buổi học mới cho 1 lớp đang dạy. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->storeRules());

        $this->scheduleService->store(Auth::user(), [
            'class_room_id' => $data['class_room_id'],
            'topic' => $data['topic'] ?? null,
            'location' => $data['location'] ?? null,
            'starts_at' => $this->combineDateTime($data, 'starts'),
            'ends_at' => $this->combineDateTime($data, 'ends'),
        ]);

        return redirect()->route('teacher.schedule.index')->with('status', 'session-created');
    }

    /** teacher.schedule.attendance — trang điểm danh + tổng kết + tài nguyên của 1 buổi học. */
    public function attendance(Request $request, int $session): View
    {
        return view('teacher.schedule.attendance', $this->scheduleService->attendanceForSession(Auth::user(), $session));
    }

    /** teacher.schedule.attendance.save — status[]/note[]/needs_more_practice[] keyed theo student_id. */
    public function saveAttendance(Request $request, int $session): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'array'],
            'status.*' => ['string', 'in:present,absent,excused,late'],
            'note' => ['nullable', 'array'],
            'note.*' => ['nullable', 'string', 'max:1000'],
            'needs_more_practice' => ['nullable', 'array'],
        ]);

        $this->scheduleService->saveAttendance(
            Auth::user(),
            $session,
            $data['status'] ?? [],
            $data['note'] ?? [],
            $data['needs_more_practice'] ?? []
        );

        return redirect()->route('teacher.schedule.attendance', $session)->with('status', 'attendance-saved');
    }

    /** teacher.schedule.summary.save — "tổng kết buổi học" (note họp 13/8). */
    public function saveSummary(Request $request, int $session): RedirectResponse
    {
        $data = $request->validate(['summary' => ['nullable', 'string', 'max:5000']]);

        $this->scheduleService->saveSummary(Auth::user(), $session, $data['summary'] ?? null);

        return redirect()->route('teacher.schedule.attendance', $session)->with('status', 'summary-saved');
    }

    /** teacher.schedule.resources.save — gắn tài liệu/câu hỏi/đề thi/video/link/ghi chú (note họp 13/8, mục 3). */
    public function addResource(Request $request, int $session): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:material,question,assessment,video,link,note'],
            'material_id' => ['nullable', 'integer'],
            'question_id' => ['nullable', 'integer'],
            'assessment_id' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:2048'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->scheduleService->addResource(Auth::user(), $session, $data);

        return redirect()->route('teacher.schedule.attendance', $session)->with('status', 'resource-added');
    }

    /** teacher.schedule.resources.delete — gỡ 1 tài nguyên khỏi buổi học. */
    public function removeResource(Request $request, int $session, int $resource): RedirectResponse
    {
        $this->scheduleService->removeResource(Auth::user(), $session, $resource);

        return redirect()->route('teacher.schedule.attendance', $session)->with('status', 'resource-removed');
    }
}
