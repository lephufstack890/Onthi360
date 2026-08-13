<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(private ReportService $reportService) {}

    /**
     * admin.reports.index — 2.3: P0 chỉ cần báo cáo vận hành cơ bản, báo cáo
     * thương mại sâu/chiến dịch thuộc P1, ngoài phạm vi trang này.
     */
    public function index(Request $request): View
    {
        return view('admin.reports.index', $this->reportService->indexData());
    }
}
