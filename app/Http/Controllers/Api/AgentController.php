<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentDevice;
use App\Services\Chocon\DeviceService;
use App\Services\Chocon\PolicyBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * SỬA 4/11 — API cho phần mềm Chocon Desktop trên máy của con (/api/agent/v1/...).
 * Hợp đồng chi tiết: docs/API-HOP-DONG.md trong source OnThi360.Agent. JSON snake_case.
 */
class AgentController extends Controller
{
    public function __construct(
        private readonly DeviceService $devices,
        private readonly PolicyBuilder $policies,
    ) {}

    /** POST pair — ghép máy bằng mã 6 số phụ huynh lấy trên web. */
    public function pair(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pairing_code' => ['required', 'string', 'max:16'],
            'device_name' => ['required', 'string', 'max:100', 'regex:/^[\pL\pN ._()-]+$/u'],
            'os_version' => ['nullable', 'string', 'max:255'],
            'agent_version' => ['nullable', 'string', 'max:50'],
            'machine_id' => ['nullable', 'string', 'max:64'],
        ]);

        $result = $this->devices->pair(
            preg_replace('/\D/', '', $data['pairing_code']),
            $data['device_name'],
            $data['os_version'] ?? null,
            $data['agent_version'] ?? null,
            $data['machine_id'] ?? null,
        );

        if ($result === null) {
            return response()->json(['message' => 'Mã ghép sai hoặc đã hết hạn.'], 401);
        }

        return response()->json([
            'device_id' => (string) $result['device']->id,
            'device_token' => $result['token'],
            'child_name' => $result['child_name'],
        ]);
    }

    /** GET policy?known_version=N — 304 nếu luật không đổi. */
    public function policy(Request $request): JsonResponse|Response
    {
        $payload = $this->policies->build((int) $this->device($request)->student_user_id);

        if ((int) $request->query('known_version', 0) === $payload['version']) {
            return response('', 304);
        }

        return response()->json($payload, 200, [], JSON_UNESCAPED_UNICODE);
    }

    /** POST activity — phiên sử dụng máy. */
    public function activity(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sessions' => ['required', 'array', 'max:500'],
            'sessions.*' => ['array'],
        ]);

        return response()->json(['accepted' => $this->devices->recordActivity($this->device($request), $data['sessions'])]);
    }

    /** POST events — sự kiện (đóng game, thử gỡ phần mềm...). */
    public function events(Request $request): JsonResponse
    {
        $data = $request->validate([
            'events' => ['required', 'array', 'max:500'],
            'events.*' => ['array'],
        ]);

        return response()->json(['accepted' => $this->devices->recordEvents($this->device($request), $data['events'])]);
    }

    /** POST heartbeat — máy còn sống (mỗi phút). */
    public function heartbeat(Request $request): Response
    {
        $this->devices->heartbeat($this->device($request), $request->only([
            'agent_version', 'policy_version', 'tray_connected', 'enforcement_enabled', 'locked',
        ]));

        return response()->noContent();
    }

    /** POST extra-time-requests — con xin thêm giờ. */
    public function extraTime(Request $request): JsonResponse
    {
        $data = $request->validate([
            'minutes' => ['required', 'integer', 'min:5', 'max:180'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $r = $this->devices->requestExtraTime($this->device($request), (int) $data['minutes'], $data['reason'] ?? null);

        return response()->json(['id' => (string) $r->id, 'status' => $r->status]);
    }

    /** GET update?current_version=&arch= — 204 nếu đang dùng bản mới nhất. */
    public function update(Request $request): JsonResponse|Response
    {
        $latest = (string) config('chocon.version');
        $current = (string) $request->query('current_version', '0.0.0');
        $file = storage_path('app/chocon/'.basename((string) config('chocon.file')));

        if (! version_compare($latest, $current, '>') || ! config('chocon.sha256') || ! is_file($file)) {
            return response()->noContent();
        }

        return response()->json([
            'version' => $latest,
            'url' => route('chocon.download.file'),
            'sha256' => (string) config('chocon.sha256'),
            'mandatory' => (bool) config('chocon.mandatory'),
            'notes' => config('chocon.notes'),
        ]);
    }

    private function device(Request $request): AgentDevice
    {
        return $request->attributes->get('agentDevice');
    }
}
