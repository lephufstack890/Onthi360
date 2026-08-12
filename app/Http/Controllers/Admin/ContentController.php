<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ContentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContentController extends Controller
{
    public function __construct(private ContentService $contentService) {}

    /** admin.content.index (ADM-03) — 6.2/6.4/6.5. */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'materials');

        return view('admin.content.index', $this->contentService->indexData($tab));
    }

    /** admin.content.show — 6.2 (chặn phát hành khi thiếu cấu hình). */
    public function show(Request $request, int $content): View
    {
        return view('admin.content.show', $this->contentService->showData($content));
    }
}
