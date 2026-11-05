<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Models\AgentDevice;
use App\Models\AgentExtraTimeRequest;
use App\Models\AgentPolicy;
use App\Models\User;
use App\Services\Chocon\DeviceService;
use App\Services\Chocon\PolicyBuilder;
use App\Services\Chocon\UsageReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * SỬA 4/11 — mục "Máy tính của con" (Chocon Desktop): thêm máy bằng mã ghép, khóa/mở khóa từ xa,
 * đặt luật (giờ chơi, giờ học, chế độ chỉ học, chặn web/app, học xong mới được chơi), duyệt xin thêm giờ,
 * xem hôm nay con dùng máy thế nào. Chỉ thao tác trên con có liên kết ĐÃ XÁC MINH.
 */
class DeviceController extends Controller
{
    public function __construct(
        private readonly DeviceService $devices,
        private readonly PolicyBuilder $policies,
        private readonly UsageReport $usage,
    ) {}

    public function index(Request $request): View
    {
        $children = User::query()
            ->whereIn('id', $this->devices->childIds($request->user()))
            ->orderBy('name')->get(['id', 'name']);

        $selectedId = (int) $request->query('child', 0);
        if (! $children->contains('id', $selectedId)) {
            $selectedId = (int) ($children->first()->id ?? 0);
        }

        $data = [
            'children' => $children,
            'selectedId' => $selectedId,
            'hasChildren' => $children->isNotEmpty(),
            // SỬA 5/11 — phụ huynh nhiều con: thẻ tổng quan tất cả các con ở đầu trang.
            'overview' => $this->devices->overview($children->pluck('id')->map(fn ($id) => (int) $id)->all()),
        ];

        if ($selectedId > 0) {
            $policy = AgentPolicy::forStudent($selectedId);
            $pairing = session('chocon_pairing');

            $data += [
                'childName' => (string) $children->firstWhere('id', $selectedId)?->name,
                'policy' => $policy,
                'hasPin' => filled($policy->parent_pin_hash),
                'devices' => AgentDevice::query()->where('student_user_id', $selectedId)->whereNull('revoked_at')
                    ->orderByDesc('last_seen_at')->get(),
                'usage' => $this->usage->forDay($selectedId),
                'doneToday' => $this->policies->itemsSubmittedToday($selectedId),
                'requests' => AgentExtraTimeRequest::query()->where('student_user_id', $selectedId)
                    ->whereDate('for_date', today())->orderByDesc('id')->get(),
                'pairing' => is_array($pairing) && (int) ($pairing['child'] ?? 0) === $selectedId ? $pairing : null,
            ];
        }

        return view('parent.devices.index', $data);
    }

    /**
     * SỬA 5/11 — "dấu vân tay" trạng thái máy của con (số máy, online, khóa, lần hoạt động cuối, yêu cầu thêm giờ).
     * Trang Máy tính của con hỏi mỗi 5 giây; đổi thì tự tải lại → vừa ghép máy / máy vừa khóa là thấy ngay.
     */
    public function status(Request $request): JsonResponse
    {
        // Gộp TẤT CẢ các con: con nào vừa ghép máy / bật máy / bị khóa / xin thêm giờ là trang cập nhật ngay.
        $ids = $this->devices->childIds($request->user());
        $sig = collect($this->devices->overview($ids))
            ->map(fn (array $o, int $id) => $id.':'.$o['devices'].':'.$o['online'].':'.(int) $o['locked'].':'.(int) $o['tray_off'].':'.$o['pending'])
            ->implode('|');

        return response()->json(['sig' => md5($sig)]);
    }

    public function pairingCode(Request $request): RedirectResponse
    {
        $childId = (int) $request->validate(['child' => ['required', 'integer']])['child'];
        $code = $this->devices->createPairingCode($request->user(), $childId);

        return $this->back($childId)->with('chocon_pairing', [
            'child' => $childId,
            'code' => $code->code,
            'expires' => $code->expires_at->format('H:i'),
        ]);
    }

    public function lock(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'child' => ['required', 'integer'],
            'locked' => ['required', 'boolean'],
            'lock_message' => ['nullable', 'string', 'max:200'],
        ]);
        $this->devices->setLock($request->user(), (int) $data['child'], (bool) $data['locked'], $data['lock_message'] ?? null);

        return $this->back((int) $data['child'])->with('status', $data['locked'] ? 'chocon-locked' : 'chocon-unlocked');
    }

    public function lockAll(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locked' => ['required', 'boolean'],
            'lock_message' => ['nullable', 'string', 'max:200'],
            'child' => ['nullable', 'integer'],
        ]);
        $this->devices->setLockAll($request->user(), (bool) $data['locked'], $data['lock_message'] ?? null);

        return $this->back((int) ($data['child'] ?? 0))->with('status', $data['locked'] ? 'chocon-locked-all' : 'chocon-unlocked-all');
    }

    public function updatePolicy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'child' => ['required', 'integer'],
            'daily_game_minutes' => ['nullable', 'integer', 'min:0', 'max:720'],
            'study_hours_start' => ['nullable', 'date_format:H:i'],
            'study_hours_end' => ['nullable', 'date_format:H:i'],
            'sleep_start' => ['nullable', 'date_format:H:i'],
            'sleep_end' => ['nullable', 'date_format:H:i'],
            'allowed_domains' => ['nullable', 'string', 'max:5000'],
            'blocked_domains' => ['nullable', 'string', 'max:5000'],
            'blocked_processes' => ['nullable', 'string', 'max:3000'],
            'study_goal_items' => ['required', 'integer', 'min:1', 'max:20'],
            'bonus_minutes_per_item' => ['required', 'integer', 'min:0', 'max:60'],
            'bonus_cap_minutes' => ['required', 'integer', 'min:0', 'max:240'],
        ]);

        $this->devices->savePolicy($request->user(), (int) $data['child'], [
            'daily_game_minutes' => $data['daily_game_minutes'] ?? null,
            'study_only_mode' => $request->boolean('study_only_mode'),
            'study_only_in_study_hours' => $request->boolean('study_only_in_study_hours'),
            'study_hours_start' => $data['study_hours_start'] ?? null,
            'study_hours_end' => $data['study_hours_end'] ?? null,
            'sleep_start' => $data['sleep_start'] ?? null,
            'sleep_end' => $data['sleep_end'] ?? null,
            'allowed_domains' => PolicyBuilder::normalizeDomains($data['allowed_domains'] ?? null),
            'blocked_domains' => PolicyBuilder::normalizeDomains($data['blocked_domains'] ?? null),
            'blocked_processes' => PolicyBuilder::normalizeProcesses($data['blocked_processes'] ?? null),
            'disable_private_browsing' => $request->boolean('disable_private_browsing'),
            'game_requires_study' => $request->boolean('game_requires_study'),
            'study_goal_items' => (int) $data['study_goal_items'],
            'bonus_minutes_per_item' => (int) $data['bonus_minutes_per_item'],
            'bonus_cap_minutes' => (int) $data['bonus_cap_minutes'],
        ]);

        return $this->back((int) $data['child'])->with('status', 'chocon-policy-saved');
    }

    public function pin(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'child' => ['required', 'integer'],
            'pin' => ['required', 'digits_between:6,8', 'confirmed'],
        ], [
            'pin.digits_between' => 'Mã PIN gồm 6 đến 8 chữ số.',
            'pin.confirmed' => 'Hai lần nhập mã PIN không khớp.',
        ]);
        $this->devices->setPin($request->user(), $data['pin']);

        return $this->back((int) $data['child'])->with('status', 'chocon-pin-saved');
    }

    public function decide(Request $request, AgentExtraTimeRequest $extraTimeRequest): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:approve,reject']]);
        $this->devices->decideExtraTime($request->user(), $extraTimeRequest, $data['decision'] === 'approve');

        return $this->back((int) $extraTimeRequest->student_user_id)
            ->with('status', $data['decision'] === 'approve' ? 'chocon-approved' : 'chocon-rejected');
    }

    public function destroy(Request $request, AgentDevice $device): RedirectResponse
    {
        $this->devices->revokeDevice($request->user(), $device);

        return $this->back((int) $device->student_user_id)->with('status', 'chocon-device-removed');
    }

    private function back(int $childId): RedirectResponse
    {
        return redirect()->route('parent.devices.index', $childId > 0 ? ['child' => $childId] : []);
    }
}
