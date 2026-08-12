<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * admin.reports.index — 2.3: P0 chỉ cần báo cáo vận hành cơ bản, báo cáo
     * thương mại sâu thuộc P1. Chưa có báo cáo nào được cấu hình.
     */
    public function index(Request $request): View
    {
        return view('admin.reports.index');
    }
}
