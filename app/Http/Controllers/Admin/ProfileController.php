<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Account\AvatarService;
use App\Services\Admin\ProfileService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private ProfileService $profileService,
    ) {}

    public function show(Request $request): View
    {
        return view('admin.profile.show', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            ...AvatarService::rules(),
        ]);

        // SỬA 9/10 — ảnh đại diện: tệp 'avatar' (đã cắt ở trình duyệt) hoặc 'remove_avatar'. Không đi vào updateProfile().
        $avatar = $request->file('avatar');
        $removeAvatar = (bool) ($data['remove_avatar'] ?? false);
        unset($data['avatar'], $data['remove_avatar']);

        $this->profileService->updateProfile($request->user(), $data);
        app(AvatarService::class)->apply($request->user(), $avatar, $removeAvatar);

        return back()->with('status', 'profile-updated');
    }

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
