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

    /** student.classes.show (STU-03) — chi tiết lớp, 7 tab. */
    public function show(Request $request, int $class): View
    {
        $user = $request->user();
        $tab = $request->query('tab', 'overview');
        $weekOffset = (int) $request->query('week', 0);

        return view('student.classes.show', $this->classRoomService->buildShowData($user, $class, $tab, $weekOffset));
    }

    /**
     * student.classes.join — "Nhập mã lớp để tham gia" (đã hứa sẵn ở empty-state của
     * student.courses.index nhưng trước đây chưa có luồng thật nào thực hiện việc này).
     */
    public function join(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);

        $classRoom = $this->classRoomService->joinByCode($request->user(), trim($data['code']));

        return redirect()->route('student.classes.show', $classRoom->id)->with('status', 'joined-class');
    }
}
