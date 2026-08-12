<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /** student.profile (STU-11 — hồ sơ + liên kết phụ huynh). */
    public function show(Request $request): View
    {
        $user = Auth::user();
        $parentLinks = $user->parentLinks()->with('parent')->get();

        return view('student.profile', ['user' => $user, 'parentLinks' => $parentLinks]);
    }

    /** Lưu thông tin hồ sơ cơ bản (tên, số điện thoại). Email không cho tự đổi ở đây. */
    public function update(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);
        $user->update($data);

        return back()->with('status', 'profile-updated');
    }
}
