<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\ProfileService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private ProfileService $profileService,
    ) {}

    /** student.profile (STU-11 — hồ sơ + liên kết phụ huynh). */
    public function show(Request $request): View
    {
        $user = $request->user();
        $parentLinks = $this->profileService->parentLinksForUser($user);

        return view('student.profile', ['user' => $user, 'parentLinks' => $parentLinks]);
    }

    /** Lưu thông tin hồ sơ cơ bản (tên, số điện thoại). Email không cho tự đổi ở đây. */
    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);
        $this->profileService->updateProfile($user, $data);

        return back()->with('status', 'profile-updated');
    }
}
