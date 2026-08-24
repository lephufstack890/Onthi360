<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\Teacher\ClassRoomService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClassRoomController extends Controller
{
    public function __construct(private readonly ClassRoomService $classRoomService) {}

    public function index(Request $request): View
    {
        $user = Auth::user();

        return view('teacher.classes.index', $this->classRoomService->listForTeacher($user));
    }

    public function show(Request $request, int $class): View
    {
        $user = Auth::user();
        $tab = $request->query('tab', 'overview');

        return view('teacher.classes.show', $this->classRoomService->showForTeacher($user, $class, $tab));
    }

    public function create(Request $request): View
    {
        return view('teacher.classes.create', $this->classRoomService->createFormData());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:255'],
            'schedule_note' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'string', 'in:active,archived'],
        ]);

        $classRoom = $this->classRoomService->store(Auth::user(), $data);

        return redirect()->route('teacher.classes.show', $classRoom->id)->with('status', 'class-created');
    }

    public function attachMaterial(Request $request, int $class)
    {
        $data = $request->validate(['material_id' => ['required', 'integer', 'exists:materials,id']]);

        $this->classRoomService->attachMaterial(Auth::user(), $class, (int) $data['material_id']);

        return redirect()->route('teacher.classes.show', ['class' => $class, 'tab' => 'materials'])->with('status', 'material-attached');
    }

    public function detachMaterial(Request $request, int $class, int $classMaterial)
    {
        $this->classRoomService->detachMaterial(Auth::user(), $class, $classMaterial);

        return redirect()->route('teacher.classes.show', ['class' => $class, 'tab' => 'materials'])->with('status', 'material-detached');
    }

    /**
     * teacher.classes.assign — SỬA 24/8 (khách yêu cầu): "Giao đề" giờ làm từ TAB "Giao đề"
     * ở đây (lớp đã biết sẵn qua $class, giáo viên chỉ cần chọn đề có sẵn) — thay cho luồng
     * cũ ở Bài tập & Đề (đã bỏ, xem Teacher\AssessmentController::store()). Logic giao đề
     * thật (auto-publish nếu chưa, kiểm tra lớp, chia ca...) vẫn NGUYÊN 1 nơi duy nhất ở
     * AssessmentService::assignToClass() — chỉ khác chỗ lấy assessment/class nào để gọi.
     */
    public function assignAssessment(Request $request, int $class): RedirectResponse
    {
        $data = $request->validate($this->assignRules());

        try {
            $data['opens_at'] = $this->combineOptionalDateTime($data, 'opens');
            $data['closes_at'] = $this->combineOptionalDateTime($data, 'closes');

            $this->classRoomService->assignAssessmentToClass(Auth::user(), $class, (int) $data['assessment_id'], $data);
        } catch (ValidationException $e) {
            return redirect()->route('teacher.classes.show', ['class' => $class, 'tab' => 'assign'])->withErrors($e->errors());
        }

        return redirect()->route('teacher.classes.show', ['class' => $class, 'tab' => 'assign'])->with('status', 'class-exam-assigned');
    }

    /** Giống hệt Teacher\AssessmentController's cũ (đã bỏ) — chỉ thêm assessment_id, bỏ class_room_id (đã biết qua route {class}). */
    private function assignRules(): array
    {
        return [
            'assessment_id' => ['required', 'integer'],
            'opens_day' => ['nullable', 'numeric', 'digits_between:1,2', 'between:1,31'],
            'opens_month' => ['nullable', 'numeric', 'digits_between:1,2', 'between:1,12'],
            'opens_year' => ['nullable', 'numeric', 'digits_between:4,4', 'between:2000,2100'],
            'opens_hour' => ['nullable', 'numeric', 'digits_between:1,2', 'between:0,23'],
            'opens_minute' => ['nullable', 'numeric', 'digits_between:1,2', 'between:0,59'],
            'closes_day' => ['nullable', 'numeric', 'digits_between:1,2', 'between:1,31'],
            'closes_month' => ['nullable', 'numeric', 'digits_between:1,2', 'between:1,12'],
            'closes_year' => ['nullable', 'numeric', 'digits_between:4,4', 'between:2000,2100'],
            'closes_hour' => ['nullable', 'numeric', 'digits_between:1,2', 'between:0,23'],
            'closes_minute' => ['nullable', 'numeric', 'digits_between:1,2', 'between:0,59'],
            'due_at' => ['nullable', 'date'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            // Chia ca thi chống nghẽn khi đông thí sinh (note họp 13/8, mục 7).
            'shift_count' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }

    /**
     * Ghép Ngày/Tháng/Năm (Ngày để trống = coi là "chưa đặt mốc", trả null) + Giờ/Phút
     * (mặc định 00:00 nếu chưa đổi) thành 1 chuỗi datetime cho AssessmentService::
     * assignToClass() (vẫn Carbon::parse() như cũ). Ném ValidationException nếu Ngày có
     * nhưng Ngày/Tháng/Năm ghép lại không phải ngày thật (vd 30/02). Giống hệt bản cũ ở
     * Teacher\AssessmentController (đã bỏ cùng luồng "giao ngay khi tạo đề").
     *
     * @throws ValidationException
     */
    private function combineOptionalDateTime(array $data, string $prefix): ?string
    {
        $day = $data[$prefix.'_day'] ?? null;

        if (blank($day)) {
            return null;
        }

        $month = (int) ($data[$prefix.'_month'] ?? now()->format('m'));
        $year = (int) ($data[$prefix.'_year'] ?? now()->format('Y'));
        $day = (int) $day;
        $hour = (int) ($data[$prefix.'_hour'] ?? '00');
        $minute = (int) ($data[$prefix.'_minute'] ?? '00');

        if (! checkdate($month, $day, $year)) {
            throw ValidationException::withMessages([
                $prefix.'_day' => 'Ngày/Tháng/Năm không hợp lệ.',
            ]);
        }

        return sprintf('%04d-%02d-%02d %02d:%02d:00', $year, $month, $day, $hour, $minute);
    }
}
