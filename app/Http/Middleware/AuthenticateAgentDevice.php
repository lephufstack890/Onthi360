<?php

namespace App\Http\Middleware;

use App\Models\AgentDevice;
use App\Models\ParentLink;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SỬA 4/11 — Xác thực máy tính chạy Chocon Desktop bằng token thiết bị (Authorization: Bearer ...).
 * Token chỉ lưu dạng băm SHA-256. Máy bị gỡ liên kết, hoặc phụ huynh không còn liên kết với con → 401
 * (phần mềm tự xóa ghép sau 3 lần 401 liên tiếp).
 */
class AuthenticateAgentDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (! $token || strlen($token) > 200) {
            return response()->json(['message' => 'Thiếu token thiết bị.'], 401);
        }

        $device = AgentDevice::query()
            ->where('token_hash', AgentDevice::hashToken($token))
            ->whereNull('revoked_at')
            ->first();

        $linked = $device && ParentLink::query()
            ->where('parent_user_id', $device->parent_user_id)
            ->where('student_user_id', $device->student_user_id)
            ->where('status', 'verified')
            ->exists();

        if (! $linked) {
            return response()->json(['message' => 'Thiết bị chưa ghép hoặc đã bị gỡ liên kết.'], 401);
        }

        $request->attributes->set('agentDevice', $device);

        return $next($request);
    }
}
