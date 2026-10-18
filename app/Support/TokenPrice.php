<?php

namespace App\Support;

/**
 * SỬA 18/10 (khách: "giá 200.000đ hiển thị là 200 token; nạp 100.000đ được 100 token — tương tự mọi trường hợp") —
 * ĐƠN VỊ HIỂN THỊ token. NƠI DUY NHẤT quy đổi tiền ↔ token để mọi màn nói cùng một con số.
 *
 * Cơ sở dữ liệu GIỮ NGUYÊN đơn vị đồng (products.price, orders.total_amount, users.token_balance, token_topups.amount…):
 * nạp 100.000đ vẫn cộng 100.000 vào token_balance, mua tài liệu 200.000đ vẫn trừ 200.000 — chỉ khi HIỂN THỊ thì chia
 * cho tỉ giá (mặc định 1.000đ = 1 token) nên người dùng thấy "nạp 100 token", "giá 200 token", "số dư 100 token". Nhờ vậy
 * không phải chuyển dữ liệu, số dư cũ vẫn đúng, và đổi tỉ giá sau này chỉ cần TOKEN_VND_RATE trong .env (config/tokens.php).
 */
final class TokenPrice
{
    /** Số đồng cho 1 token. */
    public static function rate(): int
    {
        return max(1, (int) config('tokens.vnd_per_token', 1000));
    }

    /** Số token (có thể lẻ) của một số tiền tính bằng đồng. */
    public static function tokens(int|float|null $vnd): float
    {
        return (float) ($vnd ?? 0) / self::rate();
    }

    /** Chỉ con số token kiểu Việt Nam: 1.250 / 15,5 (tối đa 2 chữ số lẻ, bỏ số 0 thừa). */
    public static function number(int|float|null $vnd): string
    {
        $t = self::tokens($vnd);
        if (abs($t - round($t)) < 0.000001) {
            return number_format($t, 0, ',', '.');
        }

        return rtrim(rtrim(number_format($t, 2, ',', '.'), '0'), ',');
    }

    /** "200 token". */
    public static function label(int|float|null $vnd): string
    {
        return self::number($vnd).' token';
    }

    /** "200 token" hoặc "Miễn phí" khi giá 0. */
    public static function priceLabel(int|float|null $vnd, string $free = 'Miễn phí'): string
    {
        return (float) ($vnd ?? 0) > 0 ? self::label($vnd) : $free;
    }

    /** Tiền đồng kiểu Việt Nam: 100.000đ (cho chỗ thật sự là TIỀN: số tiền chuyển khoản khi nạp). */
    public static function vnd(int|float|null $vnd): string
    {
        return number_format((float) ($vnd ?? 0), 0, ',', '.').'đ';
    }
}
