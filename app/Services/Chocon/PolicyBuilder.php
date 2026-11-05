<?php

namespace App\Services\Chocon;

use App\Enums\AttemptSource;
use App\Enums\AttemptStatus;
use App\Models\AgentExtraTimeRequest;
use App\Models\AgentPolicy;
use App\Models\Attempt;
use Illuminate\Support\Facades\Cache;

/**
 * SỬA 4/11 — Dựng "bộ luật" gửi xuống Chocon Desktop (API GET /api/agent/v1/policy).
 * Định dạng khớp PolicyDto bên phần mềm C# (docs/API-HOP-DONG.md).
 *
 * Phần server tự tính (phụ huynh không phải bấm gì):
 *  · bonus_game_minutes_today = thưởng theo số bài/đề con nộp hôm nay + yêu cầu xin thêm giờ đã duyệt
 *  · study_goal_met_today     = hôm nay con đã nộp đủ số bài/đề phụ huynh đặt chưa ("học xong mới được chơi")
 *  · focus_mode_active        = con đang mở một đề chưa nộp → chế độ tập trung
 * "version" là mã băm của chính nội dung luật → bất kỳ giá trị nào đổi (kể cả sang ngày mới) thì version đổi,
 * máy con tự tải lại. Không cần cột đếm version.
 */
class PolicyBuilder
{
    private const WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

    /**
     * SỬA 5/11 — máy con hỏi luật mỗi ~10 giây: ghi nhớ kết quả 30 giây để server nhẹ.
     * Phụ huynh đổi gì (khóa, luật, PIN, duyệt thêm giờ, gỡ máy) thì forget() ngay → máy con nhận trong vài giây.
     *
     * @return array<string, mixed>
     */
    public function cached(int $studentId): array
    {
        return Cache::remember(self::cacheKey($studentId), 30, fn () => $this->build($studentId));
    }

    public static function forget(int $studentId): void
    {
        Cache::forget(self::cacheKey($studentId));
    }

    private static function cacheKey(int $studentId): string
    {
        return "chocon:policy:{$studentId}";
    }

    /** @return array<string, mixed> */
    public function build(int $studentId): array
    {
        $p = AgentPolicy::forStudent($studentId);
        $today = now()->startOfDay();

        $doneToday = $this->itemsSubmittedToday($studentId);
        $goal = max(1, (int) $p->study_goal_items);
        $earned = min((int) $p->bonus_cap_minutes, $doneToday * (int) $p->bonus_minutes_per_item);
        $approved = (int) AgentExtraTimeRequest::query()
            ->where('student_user_id', $studentId)
            ->where('status', 'approved')
            ->whereDate('for_date', $today)
            ->sum('minutes');

        $studyWindow = $this->window(self::WEEKDAYS, $p->study_hours_start, $p->study_hours_end);
        $sleepWindow = $this->window([], $p->sleep_start, $p->sleep_end);

        $payload = [
            // SỬA 5/11 (khách: chỉ cần theo dõi con vào web nào, có chơi game không) — bỏ giới hạn phút chơi game.
            // Giá trị cũ vẫn giữ trong DB; muốn bật lại thì đổi null thành $p->daily_game_minutes.
            'daily_game_minutes' => null,
            'bonus_game_minutes_today' => $earned + $approved,
            // Giờ học + giờ ngủ: không được chơi game.
            'blocked_game_schedules' => array_values(array_filter([$studyWindow, $sleepWindow])),
            'blocked_processes' => array_values($p->blocked_processes ?? []),
            'blocked_domains' => array_values($p->blocked_domains ?? []),
            'disable_private_browsing' => (bool) $p->disable_private_browsing,
            'lock_now' => (bool) $p->lock_now,
            'lock_message' => $p->lock_message,
            'game_requires_study' => (bool) $p->game_requires_study,
            'study_goal_met_today' => $doneToday >= $goal,
            'study_goal_progress' => "Đã làm {$doneToday}/{$goal} bài hôm nay",
            'focus_mode_active' => $this->hasExamInProgress($studentId),
            'focus_url_patterns' => array_values(config('chocon.focus_url_patterns', [])),
            'focus_allowed_processes' => null,
            'parent_pin_hash' => $p->parent_pin_hash,
            'extra_games' => [],
            'extra_study_apps' => [],
            'extra_study_domains' => [],
            'study_only_mode' => (bool) $p->study_only_mode,
            'study_only_schedules' => $p->study_only_in_study_hours && $studyWindow ? [$studyWindow] : [],
            'allowed_domains' => array_values($p->allowed_domains ?? []),
        ];

        // Số dương 31 bit (bên C# là int).
        $version = max(1, crc32(json_encode($payload, JSON_UNESCAPED_UNICODE)) & 0x7FFFFFFF);

        return ['version' => $version] + $payload;
    }

    /**
     * Số bài / đề con đã nộp hôm nay CÓ ĐIỂM (luyện tập, bài được giao, đề, cuộc thi).
     * Bỏ lượt nộp 0 điểm (nộp trắng / nộp bừa để mở khóa giờ chơi) và lượt làm thử của ban tổ chức.
     */
    public function itemsSubmittedToday(int $studentId): int
    {
        return Attempt::query()
            ->where('user_id', $studentId)
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '>=', now()->startOfDay())
            ->where('source', '!=', AttemptSource::StaffTest->value)
            ->where('total_score', '>', 0)
            ->count();
    }

    private function hasExamInProgress(int $studentId): bool
    {
        return Attempt::query()
            ->where('user_id', $studentId)
            ->where('status', AttemptStatus::InProgress->value)
            ->whereNull('submitted_at')
            ->where('started_at', '>=', now()->subMinutes((int) config('chocon.focus_window_minutes', 120)))
            ->exists();
    }

    /** @return array{days: list<string>, start: string, end: string}|null */
    private function window(array $days, ?string $start, ?string $end): ?array
    {
        if (! self::isTime($start) || ! self::isTime($end) || $start === $end) {
            return null;
        }

        return ['days' => $days, 'start' => $start, 'end' => $end];
    }

    public static function isTime(?string $value): bool
    {
        return is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
    }

    /**
     * Chuẩn hóa danh sách tên miền phụ huynh nhập (mỗi dòng / dấu phẩy 1 tên): bỏ http://, www., đường dẫn;
     * bỏ dòng sai định dạng; tối đa 200 tên.
     *
     * @return list<string>
     */
    public static function normalizeDomains(?string $text): array
    {
        $out = [];
        foreach (preg_split('/[\s,;]+/u', mb_strtolower(trim((string) $text))) ?: [] as $item) {
            $item = preg_replace('#^[a-z]+://#', '', $item);
            $item = preg_replace('#[/?\#].*$#', '', (string) $item);
            $item = preg_replace('/^www\./', '', (string) $item);
            $item = preg_replace('/:\d+$/', '', (string) $item);
            if ($item !== '' && preg_match('/^(?:[\p{L}\p{N}](?:[\p{L}\p{N}-]{0,61}[\p{L}\p{N}])?\.)+[\p{L}][\p{L}\p{N}-]{0,61}$/u', $item)) {
                $out[$item] = true;
            }
        }

        return array_slice(array_keys($out), 0, 200);
    }

    /**
     * Tên phần mềm bị chặn: "discord", "Zalo.exe" → "discord", "zalo".
     *
     * @return list<string>
     */
    public static function normalizeProcesses(?string $text): array
    {
        $out = [];
        foreach (preg_split('/[\r\n,;]+/u', mb_strtolower(trim((string) $text))) ?: [] as $item) {
            $item = preg_replace('/\.exe$/', '', trim($item));
            if ($item !== '' && preg_match('/^[a-z0-9._ -]{1,80}$/', (string) $item)) {
                $out[$item] = true;
            }
        }

        return array_slice(array_keys($out), 0, 100);
    }

    /** PIN phụ huynh → chuỗi băm PBKDF2-SHA256 cùng định dạng phần mềm C# kiểm tra (ParentPin.Verify). */
    public static function hashPin(string $pin): string
    {
        $salt = random_bytes(16);
        $hash = hash_pbkdf2('sha256', $pin, $salt, 100000, 32, true);

        return 'pbkdf2_sha256$100000$'.base64_encode($salt).'$'.base64_encode($hash);
    }
}
