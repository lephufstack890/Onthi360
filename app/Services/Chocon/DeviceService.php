<?php

namespace App\Services\Chocon;

use App\Models\AgentActivitySession;
use App\Models\AgentDevice;
use App\Models\AgentEvent;
use App\Models\AgentExtraTimeRequest;
use App\Models\AgentPairingCode;
use App\Models\AgentPolicy;
use App\Models\ParentLink;
use App\Models\User;
use App\Notifications\ChoconAlert;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * SỬA 4/11 — Nghiệp vụ Chocon Desktop: ghép máy, nhận dữ liệu từ máy con, thao tác của phụ huynh.
 * Quyền: phụ huynh chỉ thao tác được trên con có liên kết ĐÃ XÁC MINH (như các mục khác của khu phụ huynh).
 */
class DeviceService
{
    private const CATEGORIES = ['study', 'game', 'browser', 'other', 'system', 'unknown'];

    // ===================== Phụ huynh =====================

    /** @return list<int> id các con đã xác minh của phụ huynh */
    public function childIds(User $parent): array
    {
        return ParentLink::query()
            ->where('parent_user_id', $parent->id)
            ->where('status', 'verified')
            ->pluck('student_user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function assertParentOf(User $parent, int $studentId): void
    {
        abort_unless(in_array($studentId, $this->childIds($parent), true), 403);
    }

    public function createPairingCode(User $parent, int $studentId): AgentPairingCode
    {
        $this->assertParentOf($parent, $studentId);

        // Mã cũ chưa dùng của cặp phụ huynh–con này hết hiệu lực luôn.
        AgentPairingCode::query()
            ->where('parent_user_id', $parent->id)->where('student_user_id', $studentId)
            ->whereNull('used_at')->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        // Dọn mã cũ để bảng không phình và mã được dùng lại.
        AgentPairingCode::query()->where('expires_at', '<', now()->subDay())->delete();

        do {
            $code = (string) random_int(100000, 999999);
        } while (AgentPairingCode::query()->where('code', $code)->exists());

        return AgentPairingCode::query()->create([
            'code' => $code,
            'parent_user_id' => $parent->id,
            'student_user_id' => $studentId,
            'expires_at' => now()->addMinutes((int) config('chocon.pairing_code_minutes', 15)),
        ]);
    }

    public function setLock(User $parent, int $studentId, bool $locked, ?string $message): void
    {
        $this->assertParentOf($parent, $studentId);
        $policy = AgentPolicy::forStudent($studentId);
        $policy->fill(['lock_now' => $locked, 'lock_message' => $locked ? $message : null, 'updated_by' => $parent->id])->save();
    }

    /** @param  array<string, mixed>  $data  đã validate ở controller */
    public function savePolicy(User $parent, int $studentId, array $data): void
    {
        $this->assertParentOf($parent, $studentId);
        $policy = AgentPolicy::forStudent($studentId);
        $policy->fill($data + ['updated_by' => $parent->id])->save();
    }

    /** PIN đặt một lần dùng cho mọi con của phụ huynh. */
    public function setPin(User $parent, string $pin): void
    {
        $hash = PolicyBuilder::hashPin($pin);
        foreach ($this->childIds($parent) as $studentId) {
            $policy = AgentPolicy::forStudent($studentId);
            $policy->fill(['parent_pin_hash' => $hash, 'updated_by' => $parent->id])->save();
        }
    }

    public function decideExtraTime(User $parent, AgentExtraTimeRequest $request, bool $approve): void
    {
        $this->assertParentOf($parent, (int) $request->student_user_id);
        if ($request->status !== 'pending') {
            return;
        }
        $request->update([
            'status' => $approve ? 'approved' : 'rejected',
            'for_date' => today(),
            'decided_by' => $parent->id,
            'decided_at' => now(),
        ]);
    }

    public function revokeDevice(User $parent, AgentDevice $device): void
    {
        $this->assertParentOf($parent, (int) $device->student_user_id);
        $device->update(['revoked_at' => now()]);
    }

    // ===================== Máy con (API) =====================

    /**
     * Ghép máy bằng mã 6 số. Cùng máy (machine_id) ghép lại thì dùng lại bản ghi cũ, cấp token mới.
     *
     * @return array{device: AgentDevice, token: string, child_name: string}|null  null = mã sai / hết hạn
     */
    public function pair(string $code, string $deviceName, ?string $osVersion, ?string $agentVersion, ?string $machineId): ?array
    {
        return DB::transaction(function () use ($code, $deviceName, $osVersion, $agentVersion, $machineId) {
            $pairing = AgentPairingCode::query()
                ->where('code', $code)->whereNull('used_at')->where('expires_at', '>', now())
                ->lockForUpdate()->first();
            if (! $pairing) {
                return null;
            }
            $stillLinked = ParentLink::query()
                ->where('parent_user_id', $pairing->parent_user_id)
                ->where('student_user_id', $pairing->student_user_id)
                ->where('status', 'verified')
                ->exists();
            if (! $stillLinked) {
                return null;
            }

            $token = Str::random(48);
            $device = $machineId
                ? AgentDevice::query()->where('student_user_id', $pairing->student_user_id)->where('machine_id', $machineId)->first()
                : null;

            $attributes = [
                'parent_user_id' => $pairing->parent_user_id,
                'student_user_id' => $pairing->student_user_id,
                'name' => Str::limit($deviceName, 100, ''),
                'machine_id' => $machineId,
                'token_hash' => AgentDevice::hashToken($token),
                'os_version' => $osVersion ? Str::limit($osVersion, 150, '') : null,
                'agent_version' => $agentVersion ? Str::limit($agentVersion, 20, '') : null,
                'last_seen_at' => now(),
                'revoked_at' => null,
            ];
            if ($device) {
                $device->update($attributes);
            } else {
                $device = AgentDevice::query()->create($attributes);
            }

            // Cùng máy trước đây ghép cho con khác → thu hồi bản ghi cũ.
            if ($machineId) {
                AgentDevice::query()->where('machine_id', $machineId)->where('id', '!=', $device->id)
                    ->whereNull('revoked_at')->update(['revoked_at' => now()]);
            }

            $pairing->update(['used_at' => now(), 'device_id' => $device->id]);

            return [
                'device' => $device,
                'token' => $token,
                'child_name' => (string) (User::query()->find($pairing->student_user_id)?->name ?? ''),
            ];
        });
    }

    /** @param  list<array<string, mixed>>  $sessions */
    public function recordActivity(AgentDevice $device, array $sessions): int
    {
        $rows = [];
        foreach ($sessions as $s) {
            try {
                $start = Carbon::parse($s['started_at'])->setTimezone(config('app.timezone'));
                $end = Carbon::parse($s['ended_at'])->setTimezone(config('app.timezone'));
            } catch (Throwable) {
                continue;
            }
            if ($end->lte($start) || ! Str::isUuid((string) ($s['id'] ?? ''))) {
                continue;
            }
            $category = in_array($s['category'] ?? '', self::CATEGORIES, true) ? $s['category'] : 'unknown';

            $rows[] = [
                'id' => (string) $s['id'],
                'device_id' => $device->id,
                'student_user_id' => $device->student_user_id,
                'started_at' => $start,
                'ended_at' => $end,
                'duration_seconds' => (int) min(86400, $start->diffInSeconds($end, true)),
                'process_name' => Str::limit((string) ($s['process_name'] ?? ''), 100, ''),
                'window_title' => isset($s['window_title']) ? Str::limit((string) $s['window_title'], 200, '') : null,
                'domain' => isset($s['domain']) ? Str::limit(mb_strtolower((string) $s['domain']), 190, '') : null,
                'category' => $category,
                'created_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            AgentActivitySession::query()->insertOrIgnore($chunk);
        }

        return count($rows);
    }

    /** @param  list<array<string, mixed>>  $events */
    public function recordEvents(AgentDevice $device, array $events): int
    {
        $ids = array_values(array_filter(array_map(fn ($e) => (string) ($e['id'] ?? ''), $events), fn ($id) => Str::isUuid($id)));
        $existing = AgentEvent::query()->whereIn('id', $ids)->pluck('id')->all();
        $new = 0;

        foreach ($events as $e) {
            $id = (string) ($e['id'] ?? '');
            if (! Str::isUuid($id) || in_array($id, $existing, true)) {
                continue;
            }
            try {
                $at = Carbon::parse($e['occurred_at'])->setTimezone(config('app.timezone'));
            } catch (Throwable) {
                $at = now();
            }
            $type = Str::limit((string) ($e['type'] ?? 'unknown'), 40, '');
            $data = is_array($e['data'] ?? null) ? array_slice($e['data'], 0, 20) : null;

            AgentEvent::query()->insertOrIgnore([[
                'id' => $id,
                'device_id' => $device->id,
                'student_user_id' => $device->student_user_id,
                'type' => $type,
                'occurred_at' => $at,
                'data' => $data === null ? null : json_encode($data, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
            ]]);
            $existing[] = $id;
            $new++;

            $this->reactToEvent($device, $type, $data ?? [], $at);
        }

        return $new;
    }

    /** @param  array<string, mixed>  $data */
    public function heartbeat(AgentDevice $device, array $data): void
    {
        $device->forceFill([
            'agent_version' => isset($data['agent_version']) ? Str::limit((string) $data['agent_version'], 20, '') : $device->agent_version,
            'policy_version' => (int) ($data['policy_version'] ?? $device->policy_version),
            'tray_connected' => (bool) ($data['tray_connected'] ?? false),
            'enforcement_enabled' => (bool) ($data['enforcement_enabled'] ?? false),
            'locked' => (bool) ($data['locked'] ?? false),
            'last_seen_at' => now(),
            // Máy hoạt động lại → lần mất kết nối sau được báo lại.
            'last_stop_reason' => null,
            'offline_notified_at' => null,
        ])->save();
    }

    public function requestExtraTime(AgentDevice $device, int $minutes, ?string $reason): AgentExtraTimeRequest
    {
        $pending = AgentExtraTimeRequest::query()
            ->where('student_user_id', $device->student_user_id)
            ->where('status', 'pending')
            ->whereDate('for_date', today())
            ->first();
        if ($pending) {
            return $pending;
        }

        $request = AgentExtraTimeRequest::query()->create([
            'device_id' => $device->id,
            'student_user_id' => $device->student_user_id,
            'minutes' => $minutes,
            'reason' => $reason ? Str::limit($reason, 200, '') : null,
            'status' => 'pending',
            'for_date' => today(),
        ]);

        $child = $device->student?->name ?? 'Con';
        $this->notifyParents($device, '⏰', 'info', "{$child} xin thêm {$minutes} phút chơi",
            ($reason ? "Lý do: {$reason}. " : '').'Bấm để duyệt hoặc từ chối.');

        return $request;
    }

    // ===================== Nội bộ =====================

    /** @param  array<string, mixed>  $data */
    private function reactToEvent(AgentDevice $device, string $type, array $data, Carbon $at): void
    {
        $child = $device->student?->name ?? 'Con';
        $machine = $device->name;

        match ($type) {
            'uninstall_confirmed' => $this->notifyParents($device, '⚠️', 'danger',
                "Phần mềm Chocon trên máy {$machine} đang bị gỡ",
                'Đã có người nhập đúng mã PIN phụ huynh để gỡ. Nếu không phải bạn, hãy đổi PIN ngay.'),
            // Chỉ báo khi lặp lại (lần thứ 3 trong ngày) để phụ huynh không bị làm phiền.
            'pin_failed' => $this->countToday($device, $type) === 3 ? $this->notifyParents($device, '🔐', 'warning',
                "Có người nhập sai PIN nhiều lần trên máy của {$child}", "Máy {$machine}. Nếu không phải bạn, hãy nhắc con.") : null,
            'clock_tampered' => $this->notifyParents($device, '🕒', 'warning',
                "Đồng hồ máy của {$child} bị chỉnh", 'Lệch '.($data['drift_minutes'] ?? '?').' phút. Giờ chơi vẫn được tính theo giờ chuẩn.'),
            'tray_restarted' => $this->countToday($device, $type) === 3 ? $this->notifyParents($device, '🔁', 'warning',
                "Phần mềm trên máy của {$child} bị tắt nhiều lần hôm nay", 'Phần mềm đã tự bật lại mỗi lần.') : null,
            'agent_stopping' => $this->onAgentStopping($device, (string) ($data['reason'] ?? 'stopped'), $at),
            // Phụ huynh mở khóa tại máy bằng PIN → bỏ lệnh khóa trên web để không khóa lại.
            'device_unlocked' => ($data['by'] ?? null) === 'parent_pin'
                // Sự kiện có thể đến muộn (máy mất mạng) → chỉ bỏ khóa nếu phụ huynh chưa khóa lại sau thời điểm đó.
                ? AgentPolicy::query()->where('student_user_id', $device->student_user_id)
                    ->where('updated_at', '<=', $at)->update(['lock_now' => false, 'lock_message' => null])
                : null,
            default => null,
        };
    }

    /**
     * Phần mềm báo sắp dừng: tắt máy / ngủ / đang cập nhật là bình thường (không báo phụ huynh);
     * "stopped" = phần mềm bị dừng khi máy vẫn chạy → báo ngay (trừ khi vừa gỡ bằng PIN / vừa cập nhật).
     */
    private function onAgentStopping(AgentDevice $device, string $reason, Carbon $at): void
    {
        $reason = in_array($reason, ['shutdown', 'sleep', 'updating', 'stopped'], true) ? $reason : 'stopped';
        // Sự kiện đến muộn (máy đã hoạt động lại sau đó) thì bỏ qua.
        if ($device->last_seen_at && $device->last_seen_at->gt($at->copy()->addMinute())) {
            return;
        }
        $device->forceFill(['last_stop_reason' => $reason])->save();

        if ($reason !== 'stopped') {
            return;
        }
        $expected = AgentEvent::query()
            ->where('device_id', $device->id)
            ->whereIn('type', ['uninstall_confirmed', 'update_installing'])
            ->where('occurred_at', '>=', $at->copy()->subMinutes(15))
            ->exists();
        if (! $expected) {
            $child = $device->student?->name ?? 'Con';
            $this->notifyParents($device, '⚠️', 'danger', "Phần mềm trên máy của {$child} vừa bị dừng",
                "Máy {$device->name}: phần mềm quản lý bị dừng khi máy vẫn đang bật. Có thể ai đó dùng quyền quản trị Windows để tắt.");
        }
    }

    /**
     * Gọi định kỳ (lịch chocon:check-offline): máy im lặng quá N phút mà KHÔNG báo tắt máy / ngủ trước đó
     * → có thể bị ngắt mạng, tắt phần mềm, rút điện → báo phụ huynh 1 lần cho mỗi lần mất kết nối.
     */
    public function notifyOfflineDevices(): int
    {
        $minutes = (int) config('chocon.offline_alert_minutes', 15);
        $devices = AgentDevice::query()
            ->whereNull('revoked_at')
            ->whereNull('offline_notified_at')
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '<', now()->subMinutes($minutes))
            ->where('last_seen_at', '>', now()->subDay())
            ->where(fn ($q) => $q->whereNull('last_stop_reason')->orWhere('last_stop_reason', 'stopped'))
            ->with('student')
            ->get();

        foreach ($devices as $device) {
            $device->forceFill(['offline_notified_at' => now()])->save();
            if ($device->last_stop_reason === 'stopped') {
                continue; // đã báo "bị dừng" lúc phần mềm dừng rồi
            }
            $child = $device->student?->name ?? 'Con';
            $this->notifyParents($device, '📡', 'warning', "Máy của {$child} mất kết nối hơn {$minutes} phút",
                "Máy {$device->name} không tắt hay ngủ theo cách bình thường. Có thể máy bị ngắt mạng, mất điện hoặc phần mềm bị tắt.");
        }

        return $devices->count();
    }

    private function countToday(AgentDevice $device, string $type): int
    {
        return AgentEvent::query()->where('student_user_id', $device->student_user_id)
            ->where('type', $type)->where('occurred_at', '>=', now()->startOfDay())->count();
    }

    private function notifyParents(AgentDevice $device, string $icon, string $tone, string $title, string $text): void
    {
        $parentIds = ParentLink::query()
            ->where('student_user_id', $device->student_user_id)
            ->where('status', 'verified')
            ->pluck('parent_user_id');

        User::query()->whereIn('id', $parentIds)->get()
            ->each(fn (User $p) => $p->notify(new ChoconAlert($icon, $tone, $title, $text, (int) $device->student_user_id)));
    }
}
