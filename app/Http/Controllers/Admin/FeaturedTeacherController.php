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
     */
    public function index(Request $request): View
    {
        return view('admin.featured-teachers.index', $this->featuredTeacherService->indexData());
    }

    public function feature(Request $request, TeacherProfile $featuredTeacher)
    {
        $data = $request->validate(['achievement' => ['nullable', 'string', 'max:1000']]);

        $this->featuredTeacherService->feature($featuredTeacher, $data['achievement'] ?? null);

        return back()->with('status', 'featured');
    }

    public function unfeature(Request $request, TeacherProfile $featuredTeacher)
    {
        $this->featuredTeacherService->unfeature($featuredTeacher);

        return back()->with('status', 'unfeatured');
    }
}
