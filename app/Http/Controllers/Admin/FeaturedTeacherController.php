<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TeacherProfile;
use App\Services\Admin\FeaturedTeacherService;
use App\Support\UploadLimit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FeaturedTeacherController extends Controller
{
    public function __construct(private FeaturedTeacherService $featuredTeacherService) {}

    public function index(Request $request): View
    {
        return view('admin.featured-teachers.index', $this->featuredTeacherService->indexData());
    }

    public function store(Request $request)
    {
        $account = $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30', 'unique:users,phone'],
            'password' => ['required', 'confirmed', 'min:8'],
            'province' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'in:mien_bac,mien_trung,mien_nam'],
        ], [
            'display_name.required' => 'Phải nhập họ tên.',
            'email.unique' => 'Email này đã có tài khoản.',
            'phone.unique' => 'Số điện thoại này đã có tài khoản.',
            'password.confirmed' => 'Hai ô mật khẩu chưa khớp nhau.',
            'password.min' => 'Mật khẩu phải từ 8 ký tự.',
        ]);

        $this->featuredTeacherService->createWithAccount(
            Auth::user(),
            $account + $this->validatePayload($request, nameRequired: true),
            $request->file('avatar'),
        );

        return back()->with('status', 'created');
    }

    public function feature(Request $request, TeacherProfile $featuredTeacher)
    {
        $this->featuredTeacherService->feature($featuredTeacher, $this->validatePayload($request), $request->file('avatar'));

        return back()->with('status', 'featured');
    }

    public function update(Request $request, TeacherProfile $featuredTeacher)
    {
        $this->featuredTeacherService->update($featuredTeacher, $this->validatePayload($request), $request->file('avatar'));

        return back()->with('status', 'updated');
    }

    public function unfeature(Request $request, TeacherProfile $featuredTeacher)
    {
        $this->featuredTeacherService->unfeature($featuredTeacher);

        return back()->with('status', 'unfeatured');
    }

    public function destroy(Request $request, TeacherProfile $featuredTeacher)
    {
        if (! $this->featuredTeacherService->deleteStandalone($featuredTeacher)) {
            return back()->withErrors([
                'delete' => 'Hồ sơ này gắn với một tài khoản giáo viên nên không xoá được từ màn vinh danh. Dùng "Rút khỏi danh sách" nếu chỉ muốn ẩn khỏi trang công khai.',
            ]);
        }

        return back()->with('status', 'deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $nameRequired = false): array
    {
        $data = $request->validate([
            'display_name' => [$nameRequired ? 'required' : 'nullable', 'string', 'max:120'],
            'workplace' => ['nullable', 'string', 'max:160'],
            'role_title' => ['nullable', 'string', 'max:120'],
            'achievement_note' => ['nullable', 'string', 'max:2000'],
            'display_rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_expert' => ['nullable', 'boolean'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.UploadLimit::maxKilobytes(4096)],
            'remove_avatar' => ['nullable', 'boolean'],
            'province' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'in:mien_bac,mien_trung,mien_nam'],
        ], [
            'display_rating.max' => 'Số sao xếp hạng không được quá 5.',
            'display_rating.min' => 'Số sao xếp hạng không được là số âm.',
            'display_name.required' => 'Phải nhập họ tên cho giáo viên / chuyên gia thêm mới.',
            'avatar.image' => 'Ảnh đại diện phải là tệp ảnh (JPG, PNG hoặc WebP).',
            'avatar.max' => 'Ảnh đại diện vượt quá '.UploadLimit::label(4096).' mà máy chủ nhận được.',
        ]);

        $data['remove_avatar'] = $request->boolean('remove_avatar');

        $data['is_expert'] = $request->boolean('is_expert');

        return $data;
    }
}
