<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TeacherProfile;
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

    /** admin.featured-teachers.feature — thêm vào trang vinh danh, có thể kèm ghi chú thành tích. */
    public function feature(Request $request, TeacherProfile $featuredTeacher)
    {
        $data = $request->validate(['achievement' => ['nullable', 'string', 'max:1000']]);

        $this->featuredTeacherService->feature($featuredTeacher, $data['achievement'] ?? null);

        return back()->with('status', 'featured');
    }

    /** admin.featured-teachers.unfeature — bỏ khỏi trang vinh danh (không xoá ghi chú thành tích). */
    public function unfeature(Request $request, TeacherProfile $featuredTeacher)
    {
        $this->featuredTeacherService->unfeature($featuredTeacher);

        return back()->with('status', 'unfeatured');
    }
}
