<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * SỬA 11/9 — QUÊN / ĐẶT LẠI MẬT KHẨU.
 *
 * Đây là phần nghiệp vụ CÒN THIẾU hẳn: giao diện khách gửi
 * (education-main/src/components/AccessCenterModal.jsx) có đủ 4 màn quên mật khẩu → đã gửi
 * thư → đặt mật khẩu mới → thành công, nhưng hệ thống chưa có route/controller nào cho
 * luồng này, người quên mật khẩu không có cách nào tự lấy lại tài khoản.
 *
 * Dùng thẳng bộ Password broker sẵn có của Laravel (bảng password_reset_tokens đã tồn tại từ
 * migration gốc create_users_table) — không thêm thư viện, không tự chế cơ chế token.
 *
 * LƯU Ý VẬN HÀNH: thư đặt lại mật khẩu chỉ tới được người dùng khi MAIL_MAILER trỏ vào máy
 * chủ gửi thật. Dự án đang để MAIL_MAILER=log nên thư nằm ở storage/logs — mã ở đây đã đúng
 * và đủ, chỉ cần đổi cấu hình thư là chạy ngay.
 */
class PasswordResetController extends Controller
{
    /** Màn "Quên mật khẩu" — nhập email để nhận hướng dẫn. */
    public function showRequest(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Gửi liên kết đặt lại mật khẩu.
     *
     * Luôn báo "đã gửi" dù email có tồn tại hay không (Password::RESET_LINK_SENT hoặc
     * INVALID_USER đều dẫn về cùng một màn) — nếu phân biệt 2 trường hợp thì trang này thành
     * công cụ dò xem email nào đã đăng ký trên hệ thống.
     */
    public function sendLink(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ], [], ['email' => 'email']);

        Password::sendResetLink(['email' => $data['email']]);

        return redirect()
            ->route('password.request')
            ->with('status', 'reset-link-sent')
            ->with('reset-email', $data['email']);
    }

    /** Màn "Đặt lại mật khẩu" — mở từ liên kết trong thư. */
    public function showReset(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    /** Lưu mật khẩu mới. */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ], [], [
            'email' => 'email',
            'password' => 'mật khẩu',
        ]);

        $status = Password::reset($data, function ($user, string $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                // Đổi remember_token để mọi phiên "ghi nhớ đăng nhập" cũ trên máy khác mất
                // hiệu lực — người vừa lấy lại mật khẩu thường là người bị chiếm tài khoản.
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => match ($status) {
                    Password::INVALID_TOKEN => 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.',
                    Password::INVALID_USER => 'Không tìm thấy tài khoản với email này.',
                    default => 'Không đặt lại được mật khẩu. Vui lòng thử lại.',
                },
            ]);
        }

        return redirect()->route('login')->with('status', 'password-reset-success');
    }
}
