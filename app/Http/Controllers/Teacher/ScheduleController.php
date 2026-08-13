<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\Teacher\ScheduleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function __construct(private readonly ScheduleService $scheduleService) {}

    /** teacher.schedule.index (TEA-01/02) — lịch buổi học xuyên mọi lớp giáo viên phụ trách (8.2). */
    public function index(): View
    {
        return view('teacher.schedule.index', $this->scheduleService->indexData(Auth::user()));
    }

    /**
     * teacher.schedule.store — tạo buổi học mới cho một lớp đang dạy. Ngày dùng input
     * date thường; Giờ dùng 2 dropdown Giờ/Phút (starts_hour/starts_minute/ends_hour/
     * ends_minute) thay vì <input type="time"> — widget giờ gốc của trình duyệt (đặc
     * biệt Safari) khó bấm/chọn, dropdown thì bấm chọn ngay không phụ thuộc trình duyệt.
     * Ghép lại thành Carbon ở đây trước khi giao cho Service (Service vẫn chỉ nhận
     * starts_at/ends_at như cũ).
     */
    public function store(Request $request)
    {
        // Giờ/Phút do 2 dropdown <select> gửi lên dạng CHUỖI CÓ SỐ 0 ĐỨNG ĐẦU (vd "08",
        // "05" — xem resources/views/partials/session-datetime-fields.blade.php,
        // sprintf('%02d', ...)). Rule 'integer' của Laravel dùng
        // filter_var($value, FILTER_VALIDATE_INT), và PHP trả về false cho chuỗi có số 0
        // đứng đầu như "08" (nhầm là số bát phân không hợp lệ) — khiến form KHÔNG BAO GIỜ
        // submit được mỗi khi giờ từ 0-9 hoặc phút là 00/05 (lỗi thực tế đã gặp: nhập giờ
        // vẫn báo "validation.integer" dù đã chọn hợp lệ). Dùng 'digits_between:1,2' thay
        // 'integer' — chỉ kiểm tra toàn số + độ dài 1-2 ký tự, không quan tâm số 0 đứng đầu;
        // 'between' vẫn chạy đúng vì Laravel tính kích thước theo floatval() cho chuỗi số.
        $data = $request->validate([
            'class_room_id' => ['required', 'integer', 'exists:class_rooms,id'],
            'starts_date' => ['required', 'date_format:Y-m-d'],
            'starts_hour' => ['required', 'digits_between:1,2', 'between:0,23'],
            'starts_minute' => ['required', 'digits_between:1,2', 'between:0,59'],
            'ends_date' => ['required', 'date_format:Y-m-d'],
            'ends_hour' => ['required', 'digits_between:1,2', 'between:0,23'],
            'ends_minute' => ['required', 'digits_between:1,2', 'between:0,59'],
            'topic' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $startsTime = sprintf('%02d:%02d', $data['starts_hour'], $data['starts_minute']);
        $endsTime = sprintf('%02d:%02d', $data['ends_hour'], $data['ends_minute']);

        $startsAt = Carbon::createFromFormat('Y-m-d H:i', "{$data['starts_date']} {$startsTime}");
        $endsAt = Carbon::createFromFormat('Y-m-d H:i', "{$data['ends_date']} {$endsTime}");

        if ($endsAt->lte($startsAt)) {
            throw ValidationException::withMessages(['ends_hour' => 'Giờ kết thúc phải sau giờ bắt đầu.']);
        }

        $session = $this->scheduleService->store(Auth::user(), [
            'class_room_id' => $data['class_room_id'],
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'topic' => $data['topic'] ?? null,
            'location' => $data['location'] ?? null,
        ]);

        if ($request->filled('back_to_class')) {
            return redirect()->route('teacher.classes.show', ['class' => $session->class_room_id, 'tab' => 'schedule'])->with('status', 'session-created');
        }

        return redirect()->route('teacher.schedule.index')->with('status', 'session-created');
    }

    /** teacher.schedule.attendance — form điểm danh cho một buổi học cụ thể (8.2). */
    public function attendance(int $session): View
    {
        return view('teacher.schedule.attendance', $this->scheduleService->attendanceForSession(Auth::user(), $session));
    }

    /** teacher.schedule.attendance.save — lưu điểm danh (present/absent/excused/late). */
    public function saveAttendance(Request $request, int $session)
    {
        $data = $request->validate([
            'status' => ['required', 'array'],
            'status.*' => ['required', 'string', 'in:present,absent,excused,late'],
        ]);

        $this->scheduleService->saveAttendance(Auth::user(), $session, $data['status']);

        return redirect()->route('teacher.schedule.attendance', $session)->with('status', 'attendance-saved');
    }
}
