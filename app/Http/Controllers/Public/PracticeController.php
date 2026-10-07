<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Public\PracticeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PracticeController extends Controller
{
    public function __construct(private PracticeService $practiceService) {}

    public function index(Request $request): View
    {
        return view('public.practice.index', $this->practiceService->indexData(Auth::user()));
    }

    /**
     * SỬA 2/10 (khách: "khi click vào xem chi tiết đề thi nó hiển thị ra màn UI mới") — màn CHI
     * TIẾT ĐỀ LUYỆN TẬP. Trang công khai: ai cũng xem được thông tin đề, nhưng nút "Bắt đầu làm
     * bài" chỉ mở phòng thi cho người đã đăng nhập (xem $canTakeDirectly ở service).
     *
     * CHỈ đề type=practice đã phát hành — service tự chặn, 404 cho mọi loại khác.
     */
    public function exam(Request $request, int $assessment): View
    {
        return view('public.practice.exam', $this->practiceService->examDetailData(Auth::user(), $assessment));
    }

    /**
     * SỬA 7/10 (khách: "thiếu số sao đánh giá") — học sinh chấm sao 1-5 cho đề luyện tập. Chỉ người
     * đã nộp đề mới chấm được; PracticeService::rateExam() kiểm tra và ném lỗi 422 nếu không đủ
     * điều kiện. Trả JSON để trang chi tiết đề cập nhật tại chỗ, khỏi tải lại cả trang.
     */
    public function rate(Request $request, int $assessment): JsonResponse
    {
        $data = $request->validate(['rating' => ['required', 'integer', 'between:1,5']]);

        $result = $this->practiceService->rateExam($request->user(), $assessment, (int) $data['rating']);

        return response()->json(['ok' => true] + $result);
    }

    /**
     * SỬA 2/10 — bản XEM TRƯỚC của đề PDF: trả về ĐÚNG khoảng trang admin đã khai ở ô "Xem thử
     * từ trang … đến trang …", cắt ra bằng FPDI rồi mới gửi đi.
     *
     * CẮT THẬT chứ không gửi cả tệp rồi nhờ trình duyệt chỉ hiện vài trang: gửi cả tệp là cả đề
     * đã nằm trong máy người xem, chỉ cần mở công cụ mạng là lấy được — bản "xem trước" lúc đó
     * chỉ còn là tấm rèm.
     *
     * SỬA 2/10 lần 3 — nếu đề đã có TỆP PDF XEM TRƯỚC riêng (người ra đề tự tải lên) thì gửi
     * thẳng tệp đó, không cắt; lúc ấy nội dung lộ ra đúng bằng những gì họ đã chọn đưa lên.
     */
    public function examPreview(Request $request, int $assessment): Response
    {
        return $this->practiceService->streamExamPreview($assessment);
    }
}
