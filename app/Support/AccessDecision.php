<?php

namespace App\Support;

/**
 * Kết quả kiểm tra quyền truy cập nội dung — luôn trả về CÓ LÝ DO (nguyên tắc
 * thiết kế 2.2 mục 1: "Nêu đúng lý do trước khi kêu gọi hành động"). Không bao
 * giờ trả về true/false trần — UI luôn có đủ dữ liệu để hiển thị đúng câu giải
 * thích và đúng CTA theo bảng 7.3.
 */
final class AccessDecision
{
    /**
     * @param  string[]  $missingGates  Toàn bộ điều kiện còn thiếu (có thể nhiều hơn 1, ví dụ
     *                                   thiếu cả quyền cá nhân và tiến độ — 7.3 đoạn cuối).
     */
    private function __construct(
        public readonly bool $allowed,
        public readonly ?string $primaryReasonCode = null,
        public readonly ?string $message = null,
        public readonly ?string $ctaLabel = null,
        public readonly ?string $ctaAction = null,
        public readonly array $missingGates = [],
    ) {}

    public static function allow(): self
    {
        return new self(allowed: true);
    }

    public static function deny(
        string $reasonCode,
        string $message,
        ?string $ctaLabel = null,
        ?string $ctaAction = null,
        array $missingGates = [],
    ): self {
        return new self(
            allowed: false,
            primaryReasonCode: $reasonCode,
            message: $message,
            ctaLabel: $ctaLabel,
            ctaAction: $ctaAction,
            missingGates: $missingGates ?: [$reasonCode],
        );
    }
}
