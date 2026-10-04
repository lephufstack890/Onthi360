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

    /** THÊM vào danh sách vinh danh. */
    public function feature(Request $request, TeacherProfile $featuredTeacher)
    {
        $data = $this->validatePayload($request);

        $this->featuredTeacherService->feature($featuredTeacher, $data['achievement'], $data['is_expert']);

        return back()->with('status', 'featured');
    }

    /** SỬA người đang vinh danh: đổi thành tích công bố và cờ chuyên gia. */
    public function update(Request $request, TeacherProfile $featuredTeacher)
    {
        $data = $this->validatePayload($request);

        $this->featuredTeacherService->update($featuredTeacher, $data['achievement'], $data['is_expert']);

        return back()->with('status', 'updated');
    }

    /**
     * XOÁ KHỎI DANH SÁCH VINH DANH — không đụng tới hồ sơ hay tài khoản giáo viên,
     * xem FeaturedTeacherService::unfeature().
     */
    public function unfeature(Request $request, TeacherProfile $featuredTeacher)
    {
        $this->featuredTeacherService->unfeature($featuredTeacher);

        return back()->with('status', 'unfeatured');
    }

    /**
     * Ô "Là chuyên gia" là checkbox: không tick thì TRÌNH DUYỆT KHÔNG GỬI trường đó lên. Phải
     * đọc bằng $request->boolean() chứ không dựa vào validate() — nếu không, bỏ tick rồi lưu
     * sẽ không gỡ được cờ chuyên gia.
     *
     * @return array{achievement: ?string, is_expert: bool}
     */
    private function validatePayload(Request $request): array
    {
        $data = $request->validate([
            'achievement' => ['nullable', 'string', 'max:1000'],
            'is_expert' => ['nullable', 'boolean'],
        ]);

        return [
            'achievement' => $data['achievement'] ?? null,
            'is_expert' => $request->boolean('is_expert'),
        ];
    }
}
