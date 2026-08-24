<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionExam;
use App\Services\Teacher\CompetitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * SỬA 24/8 — khách yêu cầu: giáo viên (cố vấn/đồng hành, xem Competition::advisors()) chỉ
 * được THÊM và SỬA kỳ thi (vòng) trong 1 cuộc thi có sẵn — KHÔNG được tạo/sửa/lưu trữ chính
 * Competition (chỉ Admin\CompetitionController làm được). Vì vậy controller này CHỦ Ý không
 * có create()/store()/edit()/update()/archive() cho Competition, chỉ có index()/show() (đọc)
 * + exam*() (ghi kỳ thi). Toàn bộ logic + chặn quyền nằm ở Teacher\CompetitionService (tái
 * dùng Admin\CompetitionService cho phần CRUD kỳ thi, xem docblock ở đó).
 */
class CompetitionController extends Controller
{
    public function __construct(private readonly CompetitionService $competitionService) {}

    public function index(): View
    {
        return view('teacher.competitions.index', $this->competitionService->indexData(Auth::id()));
    }

    public function show(int $competition): View
    {
        return view('teacher.competitions.show', $this->competitionService->showData($competition, Auth::id()));
    }

    /**
     * Giống hệt Admin\CompetitionController::combineDateTimeInputs() — form chọn Ngày/Tháng/
     * Năm/Giờ/Phút riêng (x-date-time-fields) thay vì 1 ô datetime-local, gộp lại đây trước
     * khi validate. Tái sử dụng nguyên bản để form kỳ thi Teacher giống hệt Admin.
     *
     * @throws ValidationException
     */
    private function combineDateTimeInputs(Request $request, array $fields): void
    {
        $errors = [];

        foreach ($fields as $field) {
            $day = trim((string) $request->input($field.'_day'));
            $month = trim((string) $request->input($field.'_month'));
            $year = trim((string) $request->input($field.'_year'));

            $filledCount = ($day !== '' ? 1 : 0) + ($month !== '' ? 1 : 0) + ($year !== '' ? 1 : 0);

            if ($filledCount === 0) {
                $request->merge([$field => null]);

                continue;
            }

            if ($filledCount < 3) {
                $errors[$field] = 'Thiếu Ngày/Tháng/Năm — chọn đủ cả 3 ô, hoặc để trống toàn bộ nếu chưa muốn đặt mốc thời gian này.';

                continue;
            }

            $day = str_pad($day, 2, '0', STR_PAD_LEFT);
            $month = str_pad($month, 2, '0', STR_PAD_LEFT);

            $hour = trim((string) $request->input($field.'_hour'));
            $minute = trim((string) $request->input($field.'_minute'));
            $hour = $hour !== '' ? str_pad($hour, 2, '0', STR_PAD_LEFT) : '00';
            $minute = $minute !== '' ? str_pad($minute, 2, '0', STR_PAD_LEFT) : '00';

            $request->merge([$field => "{$year}-{$month}-{$day}T{$hour}:{$minute}"]);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** teacher.competitions.exams.store — thêm 1 kỳ thi mới vào cuộc thi mình là cố vấn. */
    public function examStore(Request $request, Competition $competition): RedirectResponse
    {
        $this->combineDateTimeInputs($request, ['starts_at', 'ends_at']);
        $data = $request->validate($this->competitionService->examValidationRules());

        try {
            $this->competitionService->storeExam($competition, Auth::id(), $data);
        } catch (ValidationException $e) {
            return redirect()->route('teacher.competitions.show', $competition->id)->withErrors($e->errors())->withInput();
        }

        return redirect()->route('teacher.competitions.show', $competition->id)->with('status', 'exam-added');
    }

    /** teacher.competitions.exams.update. */
    public function examUpdate(Request $request, CompetitionExam $competitionExam): RedirectResponse
    {
        $competitionId = $competitionExam->competition_id;

        $this->combineDateTimeInputs($request, ['starts_at', 'ends_at']);
        $data = $request->validate($this->competitionService->examValidationRules());

        try {
            $this->competitionService->updateExam($competitionExam, Auth::id(), $data);
        } catch (ValidationException $e) {
            return redirect()->route('teacher.competitions.show', $competitionId)->withErrors($e->errors())->withInput();
        }

        return redirect()->route('teacher.competitions.show', $competitionId)->with('status', 'exam-updated');
    }

    /** teacher.competitions.exams.destroy — chặn xoá nếu kỳ thi đã có lượt xếp hạng riêng. */
    public function examDestroy(Request $request, CompetitionExam $competitionExam): RedirectResponse
    {
        $competitionId = $competitionExam->competition_id;

        try {
            $this->competitionService->deleteExam($competitionExam, Auth::id());
        } catch (ValidationException $e) {
            return redirect()->route('teacher.competitions.show', $competitionId)->withErrors($e->errors());
        }

        return redirect()->route('teacher.competitions.show', $competitionId)->with('status', 'exam-deleted');
    }
}
