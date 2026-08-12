<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class QuestionController extends Controller
{
    /** teacher.questions.index (TEA-03) — kho riêng của giáo viên (6.5). */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $tab = $request->query('tab', 'all');

        $base = Question::where('owner_id', $user->id);
        $counts = [
            'all' => (clone $base)->count(),
            'published' => (clone $base)->where('status', ContentStatus::Published)->count(),
            'draft' => (clone $base)->where('status', ContentStatus::Draft)->count(),
        ];

        $tabs = [
            ['label' => 'Tất cả', 'href' => route('teacher.questions.index'), 'active' => $tab === 'all', 'count' => $counts['all']],
            ['label' => 'Đã phát hành', 'href' => route('teacher.questions.index', ['tab' => 'published']), 'active' => $tab === 'published', 'count' => $counts['published']],
            ['label' => 'Nháp', 'href' => route('teacher.questions.index', ['tab' => 'draft']), 'active' => $tab === 'draft', 'count' => $counts['draft']],
        ];

        $query = Question::where('owner_id', $user->id);
        if ($tab === 'published') {
            $query->where('status', ContentStatus::Published);
        } elseif ($tab === 'draft') {
            $query->where('status', ContentStatus::Draft);
        }

        $questions = $query->latest()->limit(50)->get()->map(fn ($q) => [
            'id' => $q->id,
            'title' => $q->title,
            'type' => $q->type->value,
            'status' => $q->status === ContentStatus::Published ? 'Phát hành' : ($q->hasMinimumGradingConfig() ? 'Nháp' : 'Nháp — thiếu cấu hình chấm'),
            'tone' => $q->status === ContentStatus::Published ? 'success' : 'warning',
        ])->all();

        return view('teacher.questions.index', ['tab' => $tab, 'tabs' => $tabs, 'questions' => $questions]);
    }

    /** teacher.questions.create (TEA-04) — tạo câu hỏi mới theo loại (MCQ/điền đáp án/lập trình). */
    public function create(Request $request): View
    {
        $type = $request->query('type', 'mcq');

        return view('teacher.questions.create', ['type' => $type]);
    }
}
