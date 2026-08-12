<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CompetitionStatus;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\TeacherProfile;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompetitionController extends Controller
{
    /** admin.competitions.index (ADM-05) — 11.1: vòng đời cuộc thi. */
    public function index(Request $request): View
    {
        $tabs = [
            ['label' => 'Cuộc thi', 'href' => route('admin.competitions.index'), 'active' => true, 'count' => Competition::count()],
            ['label' => 'Giáo viên tiêu biểu', 'href' => route('admin.featured-teachers.index'), 'active' => false, 'count' => TeacherProfile::where('approval_status', 'approved')->count()],
        ];

        $competitions = Competition::latest('starts_at')->limit(50)->get()->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->title,
            'type' => $c->type->value === 'contest' ? 'Cuộc thi' : 'Khảo sát',
            'time' => ($c->starts_at?->format('d/m') ?? '').' - '.($c->ends_at?->format('d/m/Y') ?? ''),
            'status' => match ($c->status) {
                CompetitionStatus::Upcoming => 'Sắp diễn ra',
                CompetitionStatus::Ongoing => 'Đang diễn ra',
                CompetitionStatus::PendingPublish => 'Chờ công bố',
                CompetitionStatus::Published => 'Đã công bố',
                CompetitionStatus::Archived => 'Lưu trữ',
            },
            'tone' => match ($c->status) {
                CompetitionStatus::Upcoming => 'info',
                CompetitionStatus::Ongoing => 'warning',
                CompetitionStatus::Published => 'success',
                default => 'neutral',
            },
        ])->all();

        return view('admin.competitions.index', ['tabs' => $tabs, 'competitions' => $competitions]);
    }
}
