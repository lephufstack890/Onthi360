<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * admin.settings.index — 3.1: Super Admin cấu hình role, chính sách, tích hợp.
     * TODO: giới hạn quyền truy cập trang này chỉ cho Super Admin (Policy/middleware riêng,
     * hiện route group chỉ yêu cầu role:admin,super_admin chung).
     */
    public function index(Request $request): View
    {
        return view('admin.settings.index');
    }
}
