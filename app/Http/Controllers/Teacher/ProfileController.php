<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\Teacher\ProfileService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $profileService) {}

    public function show(Request $request): View
    {
        return view('teacher.profile.show', $this->profileService->showData($request->user()));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'province' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'in:mien_bac,mien_trung,mien_nam'],
        ]);

        $this->profileService->updateProfile($request->user(), $data);

        return back()->with('status', 'profile-updated');
    }

    public function updateTeacherProfile(Request $request)
    {
        $data = $request->validate([
            'bio' => ['nullable', 'string', 'max:2000'],
            'subjects' => ['nullable', 'string', 'max:500'],
        ]);

        $this->profileService->updateTeacherProfile($request->user(), $data);

        return back()->with('status', 'teacher-profile-updated');
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
