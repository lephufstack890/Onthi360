<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AccessRightService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccessRightController extends Controller
{
    public function __construct(private AccessRightService $accessRightService) {}

    /** admin.access-rights.index — 7.1-7.5: quyền học cá nhân / quyền dạy đa lớp. */
    public function index(Request $request): View
    {
        return view('admin.access-rights.index', $this->accessRightService->indexData());
    }
}
