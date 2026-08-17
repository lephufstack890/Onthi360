<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\InfoService;
use Illuminate\View\View;

class InfoController extends Controller
{
    public function __construct(private readonly InfoService $infoService) {}

    /** info.index (PUB-11, 4.1) — trang Thông tin công khai, dữ liệu thật. */
    public function index(): View
    {
        return view('public.info.index', $this->infoService->indexData());
    }

    /** info.policies.show — trang chi tiết 1 chính sách (bao-mat/dieu-khoan/hoan-tien). */
    public function policy(string $slug): View
    {
        $data = $this->infoService->policyDetail($slug);

        abort_if($data === null, 404);

        return view('public.info.policy-show', $data);
    }
}
