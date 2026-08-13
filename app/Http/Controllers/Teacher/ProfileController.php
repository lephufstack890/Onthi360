<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\Teacher\ProfileService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $profileService) {}

    /** teacher.profile.show — hồ sơ tài khoản + hồ sơ chuyên môn giáo viên (3.3). */
    public function show(Request $request): View
    {
        return view('teacher.profile.show', $this->profileService->showData($request->user()));
    }

    /** Lưu thông tin tài khoản cơ bản (tên, số điện thoại). */
    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        $this->profileService->updateProfile($request->user(), $data);

        return back()->with('status', 'profile-updated');
    }

    /** Lưu hồ sơ chuyên môn (bio, môn dạy) — không đụng tới approval_status/is_featured. */
    public function updateTeacherProfile(Request $request)
    {
        $data = $request->validate([
            'bio' => ['nullable', 'string', 'max:2000'],
            'subjects' => ['nullable', 'string', 'max:500'],
        ]);

        $this->profileService->updateTeacherProfile($request->user(), $data);

        return back()->with('status', 'teacher-profile-updated');
    }

    /** Đổi mật khẩu — yêu cầu xác nhận mật khẩu hiện tại. */
    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->profileService->updatePassword($request->user(), $data['current_password'], $data['password']);

        return back()->with('status', 'password-updated');
    }
}
