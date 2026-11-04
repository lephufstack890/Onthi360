<?php

namespace App\Services\Chocon;

use App\Models\AgentActivitySession;
use App\Models\AgentEvent;

/** SỬA 4/11 — Số liệu "hôm nay con dùng máy thế nào" cho trang Máy tính của con. */
class UsageReport
{
    private const EVENT_LABELS = [
        'app_blocked' => 'Đã đóng ứng dụng',
        'pin_failed' => 'Nhập sai PIN phụ huynh',
        'clock_tampered' => 'Đồng hồ máy bị chỉnh',
        'tray_restarted' => 'Phần mềm bị tắt và đã tự bật lại',
        'uninstall_confirmed' => 'Gỡ phần mềm (đã nhập đúng PIN)',
        'device_locked' => 'Máy đã khóa',
        'device_unlocked' => 'Máy đã mở khóa',
        'focus_mode_started' => 'Bật chế độ tập trung làm bài',
        'agent_started' => 'Máy bật / phần mềm khởi động',
        'update_installing' => 'Đang cập nhật phần mềm',
        'agent_stopping' => 'Phần mềm dừng',
    ];

    private const REASONS = [
        'GameQuotaExceeded' => 'hết giờ chơi',
        'BlockedSchedule' => 'giờ không được chơi',
        'StudyRequired' => 'chưa học xong',
        'StudyOnlyMode' => 'giờ chỉ học',
        'FocusMode' => 'đang làm bài',
        'BlockedApp' => 'phần mềm bị chặn',
    ];

    /** @return array<string, mixed> */
    public function forDay(int $studentId, ?\DateTimeInterface $day = null): array
    {
        $from = $day ? \Illuminate\Support\Carbon::instance($day)->startOfDay() : now()->startOfDay();
        $to = (clone $from)->endOfDay();

        $base = AgentActivitySession::query()
            ->where('student_user_id', $studentId)
            ->whereBetween('started_at', [$from, $to]);

        $byCategory = (clone $base)->selectRaw('category, SUM(duration_seconds) as s')->groupBy('category')->pluck('s', 'category');
        $minutes = fn (string $c) => intdiv((int) ($byCategory[$c] ?? 0), 60);

        $topApps = (clone $base)->whereIn('category', ['game', 'other'])
            ->selectRaw('process_name as name, category, SUM(duration_seconds) as s')
            ->groupBy('process_name', 'category')->orderByDesc('s')->limit(6)->get()
            ->map(fn ($r) => ['name' => $r->name, 'category' => $r->category, 'minutes' => intdiv((int) $r->s, 60)])->all();

        $topSites = (clone $base)->whereNotNull('domain')
            ->selectRaw('domain as name, SUM(duration_seconds) as s')
            ->groupBy('domain')->orderByDesc('s')->limit(6)->get()
            ->map(fn ($r) => ['name' => $r->name, 'minutes' => intdiv((int) $r->s, 60)])->all();

        $events = AgentEvent::query()
            ->where('student_user_id', $studentId)
            ->where('type', '!=', 'agent_started')
            ->orderByDesc('occurred_at')->limit(15)->get()
            ->map(function (AgentEvent $e) {
                $label = self::EVENT_LABELS[$e->type] ?? $e->type;
                if ($e->type === 'agent_stopping') {
                    $label .= ' ('.match ($e->data['reason'] ?? '') {
                        'shutdown' => 'tắt máy', 'sleep' => 'máy ngủ', 'updating' => 'cập nhật', default => 'bị dừng khi máy đang bật',
                    }.')';
                }
                if ($e->type === 'app_blocked') {
                    $label .= ' '.($e->data['process'] ?? '').' ('.(self::REASONS[$e->data['reason'] ?? ''] ?? 'bị giới hạn').')';
                }

                return ['label' => $label, 'time' => $e->occurred_at?->format('H:i d/m'), 'type' => $e->type];
            })->all();

        return [
            'study' => $minutes('study'),
            'game' => $minutes('game'),
            'browser' => $minutes('browser'),
            'other' => $minutes('other'),
            'topApps' => $topApps,
            'topSites' => $topSites,
            'events' => $events,
        ];
    }
}
