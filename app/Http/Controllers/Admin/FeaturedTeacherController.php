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

    public function index(Request $request): View
    {
        return view('admin.featured-teachers.index', $this->featuredTeacherService->indexData());
    }

    /** THÊM vào danh sách vinh danh. */
    public function feature(Request $request, TeacherProfile $featuredTeacher)
    {
        $this->featuredTeacherService->feature($featuredTeacher, $this->validatePayload($request));

        return back()->with('status', 'featured');
    }

    /** SỬA người đang vinh danh. */
    public function update(Request $request, TeacherProfile $featuredTeacher)
    {
        $this->featuredTeacherService->update($featuredTeacher, $this->validatePayload($request));

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
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        $data = $request->validate([
            'display_name' => ['nullable', 'string', 'max:120'],
            'workplace' => ['nullable', 'string', 'max:160'],
            'role_title' => ['nullable', 'string', 'max:120'],
            'achievement_note' => ['nullable', 'string', 'max:2000'],
            // Chặn ngay ở đây chứ không chỉ ở ô nhập: ô number của trình duyệt chỉ gợi ý, người
            // ta gửi thẳng request vẫn lọt. 5 sao là trần, âm thì vô nghĩa.
            'display_rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'is_expert' => ['nullable', 'boolean'],
        ], [
            'display_rating.max' => 'Số sao xếp hạng không được quá 5.',
            'display_rating.min' => 'Số sao xếp hạng không được là số âm.',
        ]);

        // Ô tick không được tick thì TRÌNH DUYỆT KHÔNG GỬI trường đó lên. Phải đọc bằng
        // $request->boolean() chứ không dựa vào validate() — nếu không, bỏ tick rồi lưu sẽ
        // không gỡ được cờ chuyên gia.
        $data['is_expert'] = $request->boolean('is_expert');

        return $data;
    }
}
