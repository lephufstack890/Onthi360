<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\Teacher\ScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function __construct(private readonly ScheduleService $scheduleService) {}

    public function index(Request $request): View
    {
        return view('teacher.schedule.index', $this->scheduleService->indexData(Auth::user()));
    }

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

    private function combineDateTime(array $data, string $prefix): string
    {
        return sprintf('%s %s:%s:00', $data[$prefix.'_date'], $data[$prefix.'_hour'], $data[$prefix.'_minute']);
    }

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

    public function attendance(Request $request, int $session): View
    {
        return view('teacher.schedule.attendance', $this->scheduleService->attendanceForSession(Auth::user(), $session));
    }

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

    public function saveSummary(Request $request, int $session): RedirectResponse
    {
        $data = $request->validate(['summary' => ['nullable', 'string', 'max:5000']]);

        $this->scheduleService->saveSummary(Auth::user(), $session, $data['summary'] ?? null);

        return redirect()->route('teacher.schedule.attendance', $session)->with('status', 'summary-saved');
    }

    /**
     * SỬA 9/9 (4) (khách: "thêm mục tạo hoạt động, trong hoạt động thì có nhiều tài nguyên") —
     * tạo hoạt động mới cho buổi học. Hoạt động luôn sinh ra ở trạng thái CHƯA PHÁT.
     */
    public function storeActivity(Request $request, int $session): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [], ['title' => 'Tên hoạt động']);

        $this->scheduleService->createActivity(Auth::user(), $session, $data);

        return redirect()->route('teacher.schedule.attendance', $session)->with('status', 'activity-created');
    }

    /**
     * SỬA 9/9 (4) — nút ▶ / ⏸ (khách: "giáo viên phải click icon play thì học sinh mới thấy
     * được"). Cùng 1 route dùng cho cả phát và thu hồi, xem ScheduleService::toggleActivityPublish().
     */
    public function toggleActivityPublish(Request $request, int $session, int $activity): RedirectResponse
    {
        $updated = $this->scheduleService->toggleActivityPublish(Auth::user(), $session, $activity);

        return redirect()->route('teacher.schedule.attendance', $session)
            ->with('status', $updated->isPublished() ? 'activity-published' : 'activity-unpublished');
    }

    public function destroyActivity(Request $request, int $session, int $activity): RedirectResponse
    {
        $this->scheduleService->deleteActivity(Auth::user(), $session, $activity);

        return redirect()->route('teacher.schedule.attendance', $session)->with('status', 'activity-deleted');
    }

    public function addResource(Request $request, int $session): RedirectResponse
    {
        $data = $request->validate([
            // SỬA 8/9 (6) (khách: "loại tài nguyên chỉ cần để bài giao, còn lại xoá hết") — form
            // chỉ còn gửi lên type=assessment; siết validation đúng bằng đó để không ai gắn thêm
            // được loại khác qua request tự chế. Enum SessionResourceType + các nhánh xử lý loại
            // cũ ở ScheduleService::addResource() giữ nguyên cho dữ liệu đã gắn trước đây.
            'type' => ['required', 'string', 'in:assessment'],
            // SỬA 9/9 (4) — bắt buộc chọn hoạt động; service kiểm tra lại hoạt động có đúng
            // thuộc buổi học này không.
            'activity_id' => ['required', 'integer'],
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

    public function removeResource(Request $request, int $session, int $resource): RedirectResponse
    {
        $this->scheduleService->removeResource(Auth::user(), $session, $resource);

        return redirect()->route('teacher.schedule.attendance', $session)->with('status', 'resource-removed');
    }
}
