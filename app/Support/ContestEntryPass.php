<?php

namespace App\Support;

/**
 * SỬA 24/10 (khách: "cuộc thi loại khảo sát khi tham gia sẽ nhập mật khẩu") — dấu "đã nhập đúng mật khẩu" của
 * một kỳ khảo sát, giữ trong PHIÊN đăng nhập (không lưu DB).
 *
 * Cấp ở ContestEntryService::enter() khi học sinh nhập đúng mật khẩu; kiểm ở 2 chỗ mở lượt làm bài:
 * Student\CompetitionController::exam() và AttemptService::competitionEntryDecision() (đường mở đề chung).
 * Hết hạn sau TTL giây hoặc khi đăng xuất → lần sau phải nhập lại, đúng mô tả của bản mẫu
 * ("Nhập lại mật khẩu mỗi khi bắt đầu hoặc tiếp tục làm bài").
 */
final class ContestEntryPass
{
    private const KEY = 'contest_entry_pass';

    private const TTL = 6 * 3600;

    public static function grant(int $examId): void
    {
        $passes = (array) session(self::KEY, []);
        $passes[$examId] = time();
        session()->put(self::KEY, $passes);
    }

    public static function has(int $examId): bool
    {
        $at = ((array) session(self::KEY, []))[$examId] ?? null;

        return is_int($at) && time() - $at < self::TTL;
    }
}
