<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\ClassRoomService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClassRoomController extends Controller
{
    public function __construct(
        private ClassRoomService $classRoomService,
    ) {}

    public function show(Request $request, int $class): View
    {
        $user = $request->user();
        $tab = $request->query('tab', 'overview');
        $weekOffset = (int) $request->query('week', 0);
        // SỬA 18/9 — ?buoi=<id>: buổi học đang xem ở màn lớp học. Có trên ĐƯỜNG DẪN chứ không chỉ
        // đổi tại chỗ, để tải lại trang hoặc gửi link cho nhau vẫn mở đúng buổi.
        $sessionId = $request->query('buoi') !== null ? (int) $request->query('buoi') : null;

        return view('student.classes.show', $this->classRoomService->buildShowData($user, $class, $tab, $weekOffset, $sessionId));
    }


    /**
     * SỬA 16/9 (khách yêu cầu) — học sinh bấm "Đăng ký học" ở trang lớp học công khai.
     *
     * Không cho vào lớp ngay: tạo yêu cầu CHỜ DUYỆT rồi quay lại đúng trang vừa bấm, giáo viên
     * duyệt xong học sinh mới vào học được (xem Student\ClassRoomService::requestJoin()).
     */
    public function requestJoin(Request $request, int $class): RedirectResponse
    {
        $this->classRoomService->requestJoin($request->user(), $class);

        return back()->with('status', 'class-join-requested');
    }

    /** SỬA 16/9 — lối vào bằng mã lớp đã tắt, xem ClassRoomService::JOIN_BY_CODE_ENABLED. */
    public function join(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);

        $classRoom = $this->classRoomService->joinByCode($request->user(), trim($data['code']));

        return redirect()->route('student.classes.show', $classRoom->id)->with('status', 'joined-class');
    }
}
