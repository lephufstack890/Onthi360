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

        return view('student.classes.show', $this->classRoomService->buildShowData($user, $class, $tab, $weekOffset));
    }


    public function join(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);

        $classRoom = $this->classRoomService->joinByCode($request->user(), trim($data['code']));

        return redirect()->route('student.classes.show', $classRoom->id)->with('status', 'joined-class');
    }
}
