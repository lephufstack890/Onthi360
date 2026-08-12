<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentStatus;
use App\Enums\OwnerType;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\DraftQuestion;
use App\Models\Material;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContentController extends Controller
{
    private function statusLabel(ContentStatus $status): array
    {
        return match ($status) {
            ContentStatus::Draft => ['Nháp', 'neutral'],
            ContentStatus::PendingReview => ['Chờ duyệt', 'warning'],
            ContentStatus::Published => ['Phát hành', 'success'],
            ContentStatus::Archived => ['Lưu trữ', 'neutral'],
        };
    }

    /** admin.content.index (ADM-03) — 6.2/6.4/6.5. */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'materials');

        $counts = [
            'materials' => Material::count(),
            'questions' => Question::where('owner_type', OwnerType::Shared)->count(),
            'assessments' => Assessment::count(),
            'drafts' => DraftQuestion::where('review_status', 'pending')->count(),
        ];

        $tabs = [
            ['label' => 'Học liệu (Sách/Chuyên đề/Đề thi)', 'href' => route('admin.content.index'), 'active' => $tab === 'materials', 'count' => $counts['materials']],
            ['label' => 'Kho câu hỏi chung', 'href' => route('admin.content.index', ['tab' => 'questions']), 'active' => $tab === 'questions', 'count' => $counts['questions']],
            ['label' => 'Đề/bộ bài', 'href' => route('admin.content.index', ['tab' => 'assessments']), 'active' => $tab === 'assessments', 'count' => $counts['assessments']],
            ['label' => 'Câu hỏi chờ rà soát (OCR)', 'href' => route('admin.content.index', ['tab' => 'drafts']), 'active' => $tab === 'drafts', 'count' => $counts['drafts']],
        ];

        $rows = [];
        if ($tab === 'questions') {
            $rows = Question::with('owner')->where('owner_type', OwnerType::Shared)->latest()->limit(50)->get()
                ->map(function ($q) {
                    [$label, $tone] = $this->statusLabel($q->status);

                    return ['id' => $q->id, 'title' => $q->title, 'type' => $q->type->value, 'status' => $label, 'tone' => $tone, 'owner' => 'Kho chung'];
                })->all();
        } elseif ($tab === 'assessments') {
            $rows = Assessment::with('creator')->latest()->limit(50)->get()
                ->map(function ($a) {
                    [$label, $tone] = $this->statusLabel($a->status);

                    return ['id' => $a->id, 'title' => $a->title, 'type' => $a->type->value, 'status' => $label, 'tone' => $tone, 'owner' => $a->owner_type === OwnerType::Shared ? 'Kho chung' : ('GV '.($a->creator->name ?? ''))];
                })->all();
        } elseif ($tab !== 'drafts') {
            $rows = Material::with('product')->latest()->limit(50)->get()
                ->map(function ($m) {
                    [$label, $tone] = $this->statusLabel($m->status);

                    return ['id' => $m->id, 'title' => $m->title, 'type' => $m->type, 'status' => $label, 'tone' => $tone, 'owner' => $m->product?->owner_type === OwnerType::Teacher ? 'Giáo viên' : 'Kho chung'];
                })->all();
        }

        return view('admin.content.index', ['tab' => $tab, 'tabs' => $tabs, 'rows' => $rows, 'total' => $counts[$tab] ?? count($rows)]);
    }

    /** admin.content.show — 6.2 (chặn phát hành khi thiếu cấu hình). */
    public function show(Request $request, int $content): View
    {
        $material = Material::with('product')->find($content);

        $publishErrors = [];
        if ($material === null) {
            $item = ['id' => $content, 'title' => 'Không tìm thấy nội dung', 'status' => ''];
        } else {
            [$label] = $this->statusLabel($material->status);
            $item = ['id' => $material->id, 'title' => $material->title, 'status' => $label];
            // TODO: nối App\Services\QuestionPublishGuard thật để tính $publishErrors theo từng câu.
        }

        return view('admin.content.show', ['item' => $item, 'publishErrors' => $publishErrors]);
    }
}
