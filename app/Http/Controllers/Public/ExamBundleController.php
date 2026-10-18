<?php

namespace App\Http\Controllers\Public;

use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ExamBundle\ExamBundleService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * SỬA 18/10 — trang BỘ ĐỀ cho người xem (route bundles.show, /bo-de/{product}): danh sách đề trong bộ + tiến độ của người
 * đang xem; đề "Chỉ trong bộ đề" bị khoá khi chưa mua. Ai cũng xem được danh sách (để biết bộ đề có gì trước khi mua).
 * Logic ở App\Services\ExamBundle\ExamBundleService::viewerData().
 */
class ExamBundleController extends Controller
{
    public function __construct(private readonly ExamBundleService $bundles) {}

    public function show(Request $request, int $product): View
    {
        abort_unless(ExamBundleService::ready(), 404);

        $model = Product::query()->findOrFail($product);
        $isAdmin = (bool) $request->user()?->hasAnyRole(\App\Models\Role::ADMIN, \App\Models\Role::SUPER_ADMIN);
        abort_unless($this->bundles->isBundle($model) && ($model->status === ContentStatus::Published || $isAdmin), 404);

        return view('public.bundles.show', $this->bundles->viewerData($model, $request->user()));
    }
}
