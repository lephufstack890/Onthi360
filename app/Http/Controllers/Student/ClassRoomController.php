<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\ClassRoomService;
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

        return view('student.classes.show', $this->classRoomService->buildShowData($user, $class, $tab));
    }
}
