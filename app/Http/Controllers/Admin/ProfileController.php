<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ProfileService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private ProfileService $profileService,
    ) {}

    /** admin.profile.show — hồ sơ + đổi mật khẩu cho tài khoản Admin. */
    public function show(Request $request): View
    {
        return view('admin.profile.show', ['user' => $request->user()]);
    }

    /** Lưu thông tin hồ sơ cơ bản (tên, số điện thoại). */
    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        $this->profileService->updateProfile($request->user(), $data);

        return back()->with('status', 'profile-updated');
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
