<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthService;
use App\Services\Auth\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Luồng đăng nhập/đăng ký (ACC-01).
 *
 * SỬA 11/9 — dựng lại theo source giao diện khách gửi
 * (education-main/src/components/AccessCenterModal.jsx) và bổ sung phần nghiệp vụ mà bản mẫu
 * chỉ mô phỏng ở trình duyệt:
 *   · đăng nhập bằng Email HOẶC Số điện thoại   -> AuthService::attemptByIdentifier()
 *   · chặn tài khoản đang bị khoá                -> AuthService::isSuspended()
 *   · đăng ký 3 bước có mã xác minh 6 số thật    -> RegistrationService
 *   · quên/đặt lại mật khẩu                      -> Auth\PasswordResetController
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly RegistrationService $registrationService,
    ) {
    }

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // Một ô duy nhất nhận cả email lẫn số điện thoại — giao diện có 2 tab nhưng gửi
            // lên cùng tên trường, người dùng gõ nhầm tab vẫn đăng nhập được.
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ], [], [
            'identifier' => 'email hoặc số điện thoại',
        ]);

        if (! $this->authService->attemptByIdentifier($data['identifier'], $data['password'], $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'identifier' => 'Email/số điện thoại hoặc mật khẩu không đúng.',
            ]);
        }

        $user = $request->user();

        // Tài khoản bị Admin khoá thì đăng xuất ngay — trước đây cột status không hề được
        // kiểm tra ở bước đăng nhập nên người bị khoá vẫn vào được.
        if ($this->authService->isSuspended($user)) {
            $this->authService->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'identifier' => 'Tài khoản của bạn đang tạm khoá. Vui lòng liên hệ bộ phận hỗ trợ.',
            ]);
        }

        // SỬA 23/9 — tài khoản tạo TỪ NGÀY bật xác minh mà chưa xác minh email thì không cho
        // vào (chốt an toàn). Tài khoản cũ hơn mốc này không bị ảnh hưởng, xem
        // config/registration.php -> require_verified_login_from.
        if ($this->authService->needsEmailVerification($user)) {
            $this->authService->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'identifier' => 'Tài khoản chưa xác minh email. Vui lòng kiểm tra hộp thư để lấy mã, hoặc liên hệ hỗ trợ.',
            ]);
        }

        $request->session()->regenerate();

        // "Mỗi học sinh chỉ được đăng nhập trên 1 máy" (note họp 13/8, mục 7).
        $this->authService->enforceSingleDeviceForStudents($user, $request->session()->getId());

        // SỬA 11/9 (khách chốt) — đăng nhập xong về TRANG CHỦ CÔNG KHAI, không vào thẳng khu
        // học tập nữa. Vẫn giữ intended() để trường hợp người dùng bấm vào 1 trang cần đăng
        // nhập rồi bị đẩy sang /login thì đăng nhập xong quay lại đúng trang đó — chỉ đổi
        // đích MẶC ĐỊNH (khi không có trang nào đang chờ) từ dashboard sang trang chủ.
        return redirect()->intended(route('home'));
    }

    public function showRegister(Request $request): View
    {
        // SỬA 23/9 — mốc thời gian MỞ form giữ ở session, không đặt trong form: dữ liệu trong
        // form thì bot sửa được, session thì không.
        $request->session()->put('register_form_opened_at', now()->timestamp);

        return view('auth.register', [
            'verificationEnabled' => $this->registrationService->enabled(),
            'resendCooldown' => RegistrationService::RESEND_COOLDOWN_SECONDS,
        ]);
    }

    /**
     * Bước 1 → 2 (gọi bằng fetch từ giao diện): ghi thông tin vào bảng tạm và gửi mã xác minh.
     * Chưa tạo User ở bước này — xem migration create_registration_verifications_table.
     */
    public function sendRegistrationCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8'],
        ], [], [
            'name' => 'họ và tên',
            'email' => 'email',
            'phone' => 'số điện thoại',
            'password' => 'mật khẩu',
        ]);

        if (filled($data['phone'] ?? null)) {
            $data['phone'] = AuthService::normalizePhone($data['phone']);
        }

        // SỬA 23/9 (khách sợ spam) — 3 chốt chặn trước khi gửi mã, xem config/registration.php.
        if ($this->registrationService->isBlockedEmailDomain($data['email'])) {
            return response()->json([
                'ok' => false,
                'message' => 'Email dùng một lần không đăng ký được. Hãy dùng email thật (Gmail, email trường, email công ty).',
            ], 422);
        }

        if (($wait = $this->registrationService->ipCooldownSeconds((string) $request->ip())) > 0) {
            return response()->json([
                'ok' => false,
                'message' => 'Thiết bị này đã đăng ký quá nhiều lần. Vui lòng thử lại sau '.ceil($wait / 60).' phút.',
            ], 429);
        }

        if (($wait = $this->registrationService->codeCooldownSeconds($data['email'])) > 0) {
            return response()->json([
                'ok' => false,
                'message' => 'Email này đã nhận quá nhiều mã. Vui lòng thử lại sau '.ceil($wait / 60).' phút.',
            ], 429);
        }

        $this->registrationService->pruneExpired();
        $pending = $this->registrationService->start($data);
        $this->registrationService->recordAttempt($data['email'], (string) $request->ip());

        return response()->json([
            'ok' => true,
            'verificationEnabled' => $this->registrationService->enabled(),
            'maskedEmail' => RegistrationService::maskEmail($pending->email),
        ]);
    }

    /** Bấm "Gửi lại mã" ở bước 2. */
    public function resendRegistrationCode(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $pending = $this->registrationService->findPendingByEmail($data['email']);

        if ($pending === null) {
            return response()->json(['ok' => false, 'message' => 'Không tìm thấy yêu cầu đăng ký. Hãy quay lại bước 1.'], 422);
        }

        // SỬA 23/9 — nút "Gửi lại mã" cũng tính vào trần mã/giờ của email đó.
        if (($wait = $this->registrationService->codeCooldownSeconds($data['email'])) > 0) {
            return response()->json([
                'ok' => false,
                'message' => 'Email này đã nhận quá nhiều mã. Vui lòng thử lại sau '.ceil($wait / 60).' phút.',
            ], 429);
        }

        if (! $this->registrationService->resend($pending)) {
            return response()->json(['ok' => false, 'message' => 'Vui lòng đợi hết thời gian chờ rồi gửi lại mã.'], 429);
        }

        $this->registrationService->recordAttempt($data['email'], (string) $request->ip());

        return response()->json(['ok' => true]);
    }

    /** Bước 2 → 3: đối chiếu mã 6 số, trả token để bước cuối chứng minh đã xác minh. */
    public function verifyRegistrationCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $pending = $this->registrationService->findPendingByEmail($data['email']);

        if ($pending === null) {
            return response()->json(['ok' => false, 'message' => 'Không tìm thấy yêu cầu đăng ký. Hãy quay lại bước 1.'], 422);
        }

        $result = $this->registrationService->verify($pending, $data['code']);

        if (! $result['ok']) {
            return response()->json(['ok' => false, 'message' => $result['reason']], 422);
        }

        return response()->json(['ok' => true, 'token' => $result['token']]);
    }

    /**
     * Bước 3: chọn vai trò rồi tạo tài khoản thật.
     *
     * Hai đường vào, cùng một chốt chặn vai trò:
     *   · đã bật xác minh  -> bắt buộc có claim_token hợp lệ (không thể bỏ qua bước 2)
     *   · chưa bật xác minh -> tạo thẳng từ dữ liệu đã gửi, y như luồng đăng ký cũ
     */
    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30', 'unique:users,phone'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
            // Chỉ 3 vai trò công khai được tự chọn (3.1) — Admin/Editor/Super Admin không có
            // trong danh sách này, và AuthService chặn lại lần nữa ở tầng nghiệp vụ.
            'role' => ['required', Rule::in(AuthService::SELF_REGISTERABLE_ROLES)],
            'subjects' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'verification_token' => ['nullable', 'string'],
        ], [], [
            'name' => 'họ và tên',
            'email' => 'email',
            'phone' => 'số điện thoại',
            'password' => 'mật khẩu',
            'role' => 'vai trò',
        ]);

        if (filled($data['phone'] ?? null)) {
            $data['phone'] = AuthService::normalizePhone($data['phone']);
        }

        // SỬA 23/9 (khách: "chưa cần OTP, check IP trước") — khi bước nhập mã đang TẮT thì đây
        // là chốt chặn DUY NHẤT: giao diện đi thẳng bước 1 -> bước 3, KHÔNG gọi
        // sendRegistrationCode() nữa (xem partials/register-wizard-script.blade.php), nên mọi
        // kiểm tra phải đặt ở đây chứ không chỉ ở hàm gửi mã.
        $this->guardAgainstRegistrationSpam($request);

        // Chặn lại lần nữa ở bước cuối, phòng trường hợp gọi thẳng POST /register.
        if ($this->registrationService->isBlockedEmailDomain($data['email'])) {
            throw ValidationException::withMessages([
                'email' => 'Email dùng một lần không đăng ký được. Hãy dùng email thật.',
            ]);
        }

        $pending = $this->registrationService->claim($data['email'], $data['verification_token'] ?? null);

        if ($this->registrationService->enabled() && $pending === null) {
            throw ValidationException::withMessages([
                'email' => 'Bạn cần xác minh email trước khi hoàn tất đăng ký.',
            ]);
        }

        if ($pending !== null) {
            $user = $this->authService->registerFromPending($pending, $data['role'], $data);
            $this->registrationService->consume($pending);
        } else {
            $user = $this->authService->register($data, $data['role']);
        }

        $this->registrationService->recordRegistration((string) $request->ip());
        $request->session()->forget('register_form_opened_at');

        $this->authService->login($user);
        $request->session()->regenerate();
        $this->authService->enforceSingleDeviceForStudents($user, $request->session()->getId());

        return redirect()->intended(route('dashboard'))->with('status', 'register-success');
    }

    /**
     * SỬA 23/9 — 4 lớp chặn spam đăng ký, KHÔNG cần gửi email:
     *   1. ô mồi ẩn 'website' — người dùng không nhìn thấy nên luôn để trống, bot điền mọi ô;
     *   2. thời gian điền form — dưới vài giây là máy, không phải người;
     *   3. trần số lần đăng ký của 1 IP trong 1 GIỜ;
     *   4. trần số tài khoản của 1 IP trong 1 NGÀY.
     * Báo lỗi cố tình chung chung để người viết bot không biết mình vướng lớp nào.
     */
    private function guardAgainstRegistrationSpam(Request $request): void
    {
        if (filled($request->input('website'))) {
            throw ValidationException::withMessages([
                'email' => 'Không gửi được biểu mẫu. Vui lòng tải lại trang và thử lại.',
            ]);
        }

        $minSeconds = (int) config('registration.min_form_seconds', 4);
        $openedAt = (int) $request->session()->get('register_form_opened_at', 0);

        if ($minSeconds > 0 && $openedAt > 0 && (now()->timestamp - $openedAt) < $minSeconds) {
            throw ValidationException::withMessages([
                'email' => 'Bạn gửi biểu mẫu quá nhanh. Vui lòng kiểm tra lại thông tin rồi gửi lại.',
            ]);
        }

        $ip = (string) $request->ip();

        foreach ([
            $this->registrationService->ipCooldownSeconds($ip),
            $this->registrationService->ipDailyCooldownSeconds($ip),
        ] as $wait) {
            if ($wait > 0) {
                $minutes = (int) ceil($wait / 60);

                throw ValidationException::withMessages([
                    'email' => $minutes >= 60
                        ? 'Thiết bị này đã đăng ký quá nhiều tài khoản. Vui lòng thử lại sau '.(int) ceil($minutes / 60).' giờ.'
                        : 'Thiết bị này đã đăng ký quá nhiều lần. Vui lòng thử lại sau '.$minutes.' phút.',
                ]);
            }
        }

        // Lượt này tính vào trần theo GIỜ ngay cả khi bước sau lỗi — bot dò email/số điện thoại
        // hợp lệ bằng cách thử liên tục cũng bị chặn.
        $this->registrationService->recordAttempt((string) $request->input('email', 'unknown'), $ip);
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->authService->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
