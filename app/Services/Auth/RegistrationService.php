<?php

namespace App\Services\Auth;

use App\Models\RegistrationVerification;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * SỬA 11/9 — bước 2 "Xác minh tài khoản" của luồng đăng ký 3 bước
 * (education-main/src/components/AccessCenterModal.jsx). Bản mẫu chỉ mô phỏng ở trình
 * duyệt; ở đây mã 6 số là thật: sinh ngẫu nhiên, băm, có hạn dùng, giới hạn số lần nhập sai
 * và chỉ gửi lại được sau một khoảng chờ.
 *
 * TẮT MỀM KHI CHƯA CẤU HÌNH GỬI THƯ (quan trọng): dự án đang để MAIL_MAILER=log, nghĩa là
 * thư chỉ ghi vào log chứ không tới được hộp thư người dùng. Nếu bắt buộc phải nhập mã trong
 * tình trạng đó thì KHÔNG AI đăng ký được nữa — hỏng một luồng đang chạy tốt. Vì vậy
 * enabled() trả về false với mailer log/array: bước 2 vẫn hiện nhưng cho đi tiếp và nói rõ
 * lý do. Chỉ cần đặt MAIL_MAILER thành smtp (hoặc driver gửi thật khác) là bước xác minh tự
 * bật lên, không phải sửa thêm dòng mã nào.
 */
class RegistrationService
{
    /** Mã sống 10 phút — đủ để mở hộp thư, không đủ lâu để người khác dò lại. */
    private const CODE_TTL_MINUTES = 10;

    /** Nhập sai quá 5 lần thì phải xin mã mới (chống dò 000000→999999). */
    private const MAX_ATTEMPTS = 5;

    /** Khoảng chờ giữa 2 lần bấm "Gửi lại mã" — khớp đồng hồ đếm ngược 60 giây ở giao diện. */
    public const RESEND_COOLDOWN_SECONDS = 60;

    /**
     * Hệ thống có thực sự gửi được thư tới người dùng không.
     *
     * 'log' ghi ra storage/logs, 'array' giữ trong bộ nhớ khi chạy test — cả hai đều KHÔNG
     * tới được hộp thư thật, nên coi như chưa bật xác minh.
     */
    public function enabled(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array', null], true);
    }

    /**
     * Bước 1 → 2: ghi nhận thông tin đăng ký vào bảng tạm và gửi mã xác minh.
     *
     * Mỗi email chỉ giữ đúng 1 bản ghi tạm: đăng ký lại cùng email sẽ ghi đè bản cũ, tránh
     * để lại nhiều mã còn hiệu lực cùng lúc cho một người.
     *
     * @param  array{name: string, email: string, phone: ?string, password: string}  $data
     */
    public function start(array $data): RegistrationVerification
    {
        $code = $this->generateCode();

        $pending = RegistrationVerification::updateOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
                'attempts' => 0,
                'verified_at' => null,
                'claim_token' => null,
            ],
        );

        $this->sendCode($pending, $code);

        return $pending;
    }

    /**
     * Bấm "Gửi lại mã": sinh mã mới, đặt lại bộ đếm nhập sai và hẹn giờ chờ lần gửi kế tiếp.
     * Trả về false nếu bấm lại quá sớm (chưa hết thời gian chờ).
     */
    public function resend(RegistrationVerification $pending): bool
    {
        $sentAt = $pending->updated_at;

        if ($sentAt !== null && $sentAt->diffInSeconds(now()) < self::RESEND_COOLDOWN_SECONDS) {
            return false;
        }

        $code = $this->generateCode();

        $pending->forceFill([
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
            'attempts' => 0,
            'verified_at' => null,
            'claim_token' => null,
        ])->save();

        $this->sendCode($pending, $code);

        return true;
    }

    /**
     * Bước 2 → 3: đối chiếu mã người dùng nhập.
     *
     * @return array{ok: bool, reason: ?string, token: ?string}
     */
    public function verify(RegistrationVerification $pending, string $code): array
    {
        if ($pending->isExpired()) {
            return ['ok' => false, 'reason' => 'Mã xác minh đã hết hạn. Bấm "Gửi lại mã" để nhận mã mới.', 'token' => null];
        }

        if ($pending->attempts >= self::MAX_ATTEMPTS) {
            return ['ok' => false, 'reason' => 'Bạn đã nhập sai quá nhiều lần. Bấm "Gửi lại mã" để nhận mã mới.', 'token' => null];
        }

        if (! Hash::check($code, $pending->code_hash)) {
            $pending->increment('attempts');

            $left = max(self::MAX_ATTEMPTS - $pending->attempts, 0);

            return [
                'ok' => false,
                'reason' => $left > 0
                    ? 'Mã xác minh không đúng. Bạn còn '.$left.' lần thử.'
                    : 'Bạn đã nhập sai quá nhiều lần. Bấm "Gửi lại mã" để nhận mã mới.',
                'token' => null,
            ];
        }

        $token = Str::random(64);

        $pending->forceFill([
            'verified_at' => now(),
            'claim_token' => $token,
        ])->save();

        return ['ok' => true, 'reason' => null, 'token' => $token];
    }

    /**
     * Bước 3: tìm lại bản ghi tạm ĐÃ XÁC MINH tương ứng với token mà form gửi lên.
     *
     * Đây là chốt chặn để không ai bỏ qua bước 2 bằng cách POST thẳng vào /register: token
     * chỉ tồn tại sau khi verify() thành công, và phải khớp đúng email đang đăng ký.
     */
    public function claim(string $email, ?string $token): ?RegistrationVerification
    {
        if (blank($token)) {
            return null;
        }

        return RegistrationVerification::query()
            ->where('email', $email)
            ->where('claim_token', $token)
            ->whereNotNull('verified_at')
            ->first();
    }

    /** Dọn bản ghi tạm sau khi đã tạo tài khoản thật xong. */
    public function consume(RegistrationVerification $pending): void
    {
        $pending->delete();
    }

    /** Xoá các bản ghi tạm quá hạn từ lâu — gọi kèm mỗi lần bắt đầu đăng ký, không cần job riêng. */
    public function pruneExpired(): void
    {
        RegistrationVerification::query()
            ->where('expires_at', '<', now()->subDay())
            ->delete();
    }

    public function findPendingByEmail(string $email): ?RegistrationVerification
    {
        return RegistrationVerification::query()->where('email', $email)->first();
    }

    /** Mã 6 chữ số, dùng bộ sinh ngẫu nhiên an toàn (random_int) chứ không phải rand(). */
    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Gửi mã tới email người đăng ký. Dùng Mail::raw để không phải thêm view thư riêng cho
     * một nội dung 3 dòng; khi nào cần thư có thương hiệu thì đổi sang Mailable là đủ.
     */
    private function sendCode(RegistrationVerification $pending, string $code): void
    {
        if (! $this->enabled()) {
            // Chưa cấu hình gửi thư thật: ghi lại để đội kỹ thuật đối chiếu khi thử nghiệm,
            // KHÔNG ném lỗi ra màn hình người dùng.
            Log::info('[Đăng ký] Mã xác minh cho '.$pending->email.': '.$code);

            return;
        }

        $body = "Xin chào {$pending->name},\n\n"
            ."Mã xác minh tài khoản Ôn Thi 360 của bạn là: {$code}\n"
            .'Mã có hiệu lực trong '.self::CODE_TTL_MINUTES." phút.\n\n"
            ."Nếu bạn không yêu cầu đăng ký, hãy bỏ qua thư này.";

        try {
            Mail::raw($body, function ($message) use ($pending) {
                $message->to($pending->email)->subject('Mã xác minh tài khoản Ôn Thi 360');
            });
        } catch (\Throwable $e) {
            // Máy chủ thư trục trặc thì không được làm hỏng luôn lượt đăng ký — ghi log để
            // xử lý, người dùng vẫn bấm "Gửi lại mã" được.
            Log::error('[Đăng ký] Không gửi được mã xác minh tới '.$pending->email.': '.$e->getMessage());
        }
    }

    /**
     * Tạo tài khoản thật từ bản ghi tạm. Mật khẩu đã băm từ bước 1 nên gán thẳng, KHÔNG băm
     * lại lần nữa (băm 2 lần sẽ khiến người dùng không đăng nhập được).
     */
    public function buildUserPayload(RegistrationVerification $pending): array
    {
        return [
            'name' => $pending->name,
            'email' => $pending->email,
            'phone' => $pending->phone,
            'password' => $pending->password,
            'email_verified_at' => $pending->verified_at,
        ];
    }

    /** Có phải tài khoản này vừa qua xác minh email thật hay không (dùng cho thông báo sau đăng ký). */
    public function wasVerified(?RegistrationVerification $pending): bool
    {
        return $pending !== null && $pending->isVerified();
    }

    /** Ẩn bớt email khi hiện lại trên màn xác minh: minhanh@example.com -> mi••••@example.com */
    public static function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($domain === '') {
            return $email;
        }

        $keep = mb_substr($name, 0, min(2, mb_strlen($name)));

        return $keep.str_repeat('•', max(mb_strlen($name) - mb_strlen($keep), 1)).'@'.$domain;
    }
}
