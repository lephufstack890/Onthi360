<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * SỬA 9/10 (khách: "màn đổi mật khẩu tách riêng ra màn mới") — một màn Đổi mật khẩu riêng cho cả
 * 4 khu. Trước đây chỉ giáo viên/admin có form đổi mật khẩu và nằm lẫn trong trang hồ sơ; học sinh
 * và phụ huynh không có chỗ nào để đổi.
 *
 * Khu (admin/teacher/parent/student) suy ra từ tên route (admin.password.edit...) nên khung trang
 * và menu trái luôn đúng khu đang đứng, dù một tài khoản có nhiều vai trò.
 */
class PasswordController extends Controller
{
    /** khu => [layout, route hồ sơ] */
    private const AREAS = [
        'admin' => ['layouts.admin', 'admin.profile.show'],
        'teacher' => ['layouts.teacher', 'teacher.profile.show'],
        'parent' => ['layouts.parent', 'parent.profile'],
        'student' => ['layouts.student', 'student.profile'],
    ];

    public function edit(Request $request): View
    {
        [$area, $layout, $profileRoute] = $this->area($request);

        return view('account.password', [
            'layout' => $layout,
            'profileUrl' => route($profileRoute),
            'passwordUrl' => route($area.'.password.edit'),
            'formAction' => route($area.'.password.update'),
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        [$area] = $this->area($request);

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ], [
            'password.different' => 'Mật khẩu mới phải khác mật khẩu hiện tại.',
        ]);

        $user = $request->user();

        // Luôn đòi đúng mật khẩu hiện tại — không tin chỉ vì đã đăng nhập (giống updatePassword() của hồ sơ).
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Mật khẩu hiện tại không đúng.']);
        }

        // Cast 'hashed' của User tự băm.
        $user->update(['password' => $data['password']]);

        return redirect()->route($area.'.password.edit')->with('status', 'password-updated');
    }

    /** @return array{0: string, 1: string, 2: string} [khu, layout, route hồ sơ] */
    private function area(Request $request): array
    {
        $area = explode('.', (string) $request->route()?->getName())[0];
        abort_unless(isset(self::AREAS[$area]), 404);

        return [$area, ...self::AREAS[$area]];
    }
}
