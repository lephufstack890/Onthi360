<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Services\ExamVariantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * SỬA 3/11 — NHÓM MÃ ĐỀ (ô "Mã đề" ở trang chi tiết đề bên admin, partial admin/content/partials/assessment-variant-box).
 * Logic: App\Services\ExamVariantService. Chỉ gắn / gỡ / đặt tên mã — không đụng nội dung, lượt làm hay điểm của đề nào.
 */
class ExamVariantController extends Controller
{
    /** admin.content.assessments.variants.attach — gắn một đề có sẵn làm mã đề của nhóm. */
    public function attach(Request $request, Assessment $assessment): RedirectResponse
    {
        $data = $request->validate([
            'child_id' => ['required', 'integer'],
            'child_code' => ['nullable', 'string', 'max:30'],
            'own_code' => ['nullable', 'string', 'max:30'],
        ], [], ['child_id' => 'Đề cần gắn', 'child_code' => 'Tên mã', 'own_code' => 'Tên mã của đề này']);

        $child = Assessment::query()->findOrFail((int) $data['child_id']);
        if (filled($data['own_code'] ?? null) && filled($data['child_code'] ?? null)
            && mb_strtolower(trim($data['own_code'])) === mb_strtolower(trim($data['child_code']))) {
            return $this->back(false, 'Mã của đề này và mã của đề được gắn đang trùng nhau ("'.trim($data['child_code']).'") — đặt 2 tên khác nhau.');
        }
        [$ok, $msg] = ExamVariantService::attach($assessment, $child, $data['child_code'] ?? null);

        // Đề đang mở chưa đặt tên mã thì đặt luôn (ô "Mã của đề này" chỉ hiện khi đề chưa có mã).
        if ($ok && $request->filled('own_code') && trim((string) $assessment->variant_code) === '') {
            $err = ExamVariantService::setCode($assessment, $data['own_code']);
            if ($err !== null) {
                return $this->back(false, $msg.' Nhưng chưa đặt được tên mã của đề này: '.$err);
            }
        }

        return $this->back($ok, $msg);
    }

    /** admin.content.assessments.variants.code — đổi tên mã của một đề trong nhóm. */
    public function code(Request $request, Assessment $assessment): RedirectResponse
    {
        $data = $request->validate(['code' => ['nullable', 'string', 'max:30']], [], ['code' => 'Tên mã']);
        $err = ExamVariantService::setCode($assessment, $data['code'] ?? null);

        return $err === null ? $this->back(true, 'Đã lưu tên mã đề.') : $this->back(false, $err);
    }

    /** admin.content.assessments.variants.detach — gỡ một đề khỏi nhóm (đề thành đề lẻ, không mất lượt làm). */
    public function detach(Assessment $assessment): RedirectResponse
    {
        [$ok, $msg] = ExamVariantService::detach($assessment);

        return $this->back($ok, $msg);
    }

    private function back(bool $ok, string $msg): RedirectResponse
    {
        return redirect()->to(url()->previous().'#exam-variant-box')
            ->with('examVariantFlash', [$ok ? 'success' : 'error', $msg]);
    }
}
