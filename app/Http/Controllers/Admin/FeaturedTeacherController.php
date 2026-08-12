<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\FeaturedTeacherService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeaturedTeacherController extends Controller
{
    public function __construct(private FeaturedTeacherService $featuredTeacherService) {}

    /**
     * admin.featured-teachers.index — PUB-10 (trang vinh danh, không phải danh bạ cá nhân, 12.2).
     */
    public function index(Request $request): View
    {
        return view('admin.featured-teachers.index', $this->featuredTeacherService->indexData());
    }
}
