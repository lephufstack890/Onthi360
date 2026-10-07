<?php

namespace App\Support;

use Carbon\Carbon;
use Throwable;

/**
 * SỬA 7/10 (khách: "cột Hoạt động ở trang Nhật ký nộp bài, chỉ admin xem; dữ liệu lấy ở tab Nhật ký
 * lúc người ta làm bài") — làm sạch, gộp và phân loại NHẬT KÝ LÀM BÀI.
 *
 * Nhật ký do trình duyệt ghi (partials/work-activity-log) rồi gửi kèm khi nộp bài. Dữ liệu đó đến từ
 * phía người dùng nên KHÔNG tin: mọi dòng đều được ép kiểu, cắt độ dài, loại loại-sự-kiện lạ và
 * giới hạn số dòng trước khi lưu. Phân loại ("nhóm dấu hiệu") theo education-main
 * (utils/submissionHistory.js → signalCategory).
 */
final class PracticeActivityLog
{
    /** Số dòng tối đa giữ lại cho một lượt nộp. */
    public const MAX_EVENTS = 200;

    /** Loại sự kiện hợp lệ → nhóm dấu hiệu. */
    private const TYPE_CATEGORY = [
        'screenshot_key' => 'screenshot',
        'screen_capture_requested' => 'screenshot',
        'tab_visibility_lost' => 'tab',
        'window_blur' => 'tab',
        'page_left' => 'tab',
        'solution_guide_opened' => 'guide',
        'sample_opened' => 'guide',
    ];

    /** Nhãn + biểu tượng cho từng nhóm (trang Nhật ký nộp bài dùng để vẽ cột Hoạt động). */
    public const CATEGORIES = [
        'screenshot' => ['label' => 'Chụp / quay màn hình', 'icon' => 'camera'],
        'tab' => ['label' => 'Rời tab / cửa sổ', 'icon' => 'external-link'],
        'guide' => ['label' => 'Hướng dẫn / bài mẫu', 'icon' => 'book-open'],
    ];

    /**
     * Nhật ký cũ (trước khi có trường eventType) chỉ có tiêu đề — đoán loại từ tiêu đề.
     */
    private const TITLE_TYPE = [
        'Mở tab Hướng dẫn' => 'solution_guide_opened',
        'Mở tab Bài mẫu' => 'sample_opened',
        'Bấm phím chụp màn hình' => 'screenshot_key',
        'Tổ hợp phím chụp/quay màn hình' => 'screenshot_key',
        'Yêu cầu quay/chụp màn hình' => 'screen_capture_requested',
        'Mở tab hoặc cửa sổ khác' => 'tab_visibility_lost',
    ];

    /**
     * Chuỗi JSON từ trình duyệt → danh sách dòng đã làm sạch (cũ → mới).
     * Trả null khi KHÔNG gửi gì / JSON hỏng — để chỗ gọi giữ nguyên nhật ký đã lưu thay vì ghi đè.
     *
     * @return list<array{id:string,kind:string,eventType:?string,title:string,detail:string,occurredAt:?string}>|null
     */
    public static function clean(?string $json): ?array
    {
        if ($json === null || trim($json) === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return null;
        }

        $out = [];

        foreach (array_slice(array_values($decoded), 0, self::MAX_EVENTS) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $title = self::text($row['title'] ?? '', 120);

            if ($title === '') {
                continue;
            }

            $type = is_string($row['eventType'] ?? null) ? $row['eventType'] : null;
            if ($type === null || ! isset(self::TYPE_CATEGORY[$type])) {
                $type = self::TITLE_TYPE[$title] ?? null;
            }

            $out[] = [
                'id' => self::text($row['id'] ?? '', 40) ?: substr(md5($title.($row['occurredAt'] ?? '')), 0, 16),
                'kind' => ($row['kind'] ?? '') === 'signal' ? 'signal' : 'event',
                'eventType' => $type,
                'title' => $title,
                'detail' => self::text($row['detail'] ?? '', 240),
                'occurredAt' => self::time($row['occurredAt'] ?? null),
            ];
        }

        return self::sorted($out);
    }

    /**
     * Gộp nhật ký đã lưu với nhật ký mới gửi lên (khoá = id dòng; dòng mới thắng vì dòng "rời tab"
     * được cập nhật thêm thời gian vắng mặt). Học sinh nộp lại sau khi đóng trình duyệt thì
     * sessionStorage đã mất — gộp để không đánh rơi những gì đã ghi ở lần nộp trước.
     *
     * @param  list<array<string,mixed>>|null  $existing
     * @param  list<array<string,mixed>>  $incoming
     * @return list<array<string,mixed>>
     */
    public static function merge(?array $existing, array $incoming): array
    {
        $byId = [];

        foreach (array_merge($existing ?? [], $incoming) as $event) {
            if (isset($event['id'])) {
                $byId[$event['id']] = $event;
            }
        }

        return array_slice(self::sorted(array_values($byId)), -self::MAX_EVENTS);
    }

    /** Nhóm dấu hiệu của một dòng: screenshot | tab | guide | other | null (không phải dấu hiệu). */
    public static function category(array $event): ?string
    {
        $type = $event['eventType'] ?? null;

        if (is_string($type) && isset(self::TYPE_CATEGORY[$type])) {
            return self::TYPE_CATEGORY[$type];
        }

        return ($event['kind'] ?? null) === 'signal' ? 'other' : null;
    }

    /**
     * Số dòng theo từng nhóm dấu hiệu.
     *
     * @param  list<array<string,mixed>>|null  $events
     * @return array<string,int>
     */
    public static function counts(?array $events): array
    {
        $counts = [];

        foreach ($events ?? [] as $event) {
            $category = self::category((array) $event);
            if ($category !== null) {
                $counts[$category] = ($counts[$category] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /** @param list<array<string,mixed>> $events @return list<array<string,mixed>> */
    private static function sorted(array $events): array
    {
        usort($events, fn ($a, $b) => strcmp((string) ($a['occurredAt'] ?? ''), (string) ($b['occurredAt'] ?? '')));

        return $events;
    }

    private static function text(mixed $value, int $max): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        // Bỏ ký tự điều khiển; nội dung vẫn được escape khi vẽ ra trang.
        $clean = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $value) ?? '';

        return mb_substr(trim($clean), 0, $max);
    }

    private static function time(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->utc()->format('Y-m-d\TH:i:s\Z');
        } catch (Throwable) {
            return null;
        }
    }
}
