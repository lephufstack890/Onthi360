<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

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

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Mật khẩu hiện tại không đúng.']);
        }

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
