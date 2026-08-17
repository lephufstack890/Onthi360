<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function __construct(private readonly ScheduleService $scheduleService) {}

    public function index(Request $request): View
    {
        $weekOffset = (int) $request->query('week', 0);

        return view('student.schedule.index', $this->scheduleService->buildWeekData(Auth::user(), $weekOffset));
    }
}
