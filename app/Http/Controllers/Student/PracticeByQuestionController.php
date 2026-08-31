<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Services\AccessGateService;
use App\Services\Student\PracticeByQuestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Route: student.practiceByQuestion.* | Giai đoạn 6 — "Luyện tập theo câu" (xem docblock đầy
 * đủ ở App\Services\Student\PracticeByQuestionService: luyện từng câu ngoài đề, tiến trình
 * lưu tạm ở session, KHÔNG tạo Attempt/AttemptAnswer).
 */
class PracticeByQuestionController extends Controller
{
    public function __construct(
        private readonly PracticeByQuestionService $service,
        // SỬA 31/8 ("Làm bài" 1 bài tập của sản phẩm) — kiểm tra quyền sở hữu sản phẩm trước
        // khi mở phiên luyện, xem startExercise() bên dưới.
        private readonly AccessGateService $accessGate,
    ) {}

    /**
     * SỬA 24/8 — trang công khai (public.practice.index) cho khách CHỌN bộ lọc trước khi đăng
     * nhập, rồi submit GET thẳng vào route này (route này đứng sau middleware auth+role:student).
     * Khách chưa đăng nhập bị Laravel bounce sang /login và tự lưu NGUYÊN url + query string vào
     * session('url.intended') (Illuminate\Routing\Redirector::guest(), AuthController::login() ở
     * cuối dùng redirect()->intended()) — đăng nhập xong quay lại ĐÚNG url này, tức là quay lại
     * đây kèm 'type'/'tag_ids' như cũ. Thấy 2 param đó trên query string nghĩa là request đến từ
     * đúng luồng đó (hoặc học sinh tự sửa URL) — tự động start() luôn thay vì bắt chọn lại lần 2.
     * Không có query nào thì vẫn là màn "chọn tag" bình thường (CTA nội bộ cũ link route này
     * không kèm query, không đổi hành vi).
     */
    public function setup(Request $request): View|RedirectResponse
    {
        if ($request->query->has('type') || $request->query->has('tag_ids')) {
            $data = $request->validate([
                'tag_ids' => ['nullable', 'array'],
                'tag_ids.*' => ['integer'],
                'type' => ['nullable', 'in:mcq,fill_blank,coding'],
            ]);

            $started = $this->service->start($data['tag_ids'] ?? [], $data['type'] ?? null);

            if ($started) {
                return redirect()->route('student.practiceByQuestion.play');
            }

            return redirect()->route('student.practiceByQuestion.setup')
                ->withErrors(['tag_ids' => 'Không tìm thấy câu hỏi phù hợp bộ lọc — thử bỏ bớt điều kiện lọc.']);
        }

        return view('student.practice.by-question-setup', $this->service->setupData());
    }

    public function start(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer'],
            'type' => ['nullable', 'in:mcq,fill_blank,coding'],
        ]);

        $started = $this->service->start($data['tag_ids'] ?? [], $data['type'] ?? null);

        if (! $started) {
            return redirect()->route('student.practiceByQuestion.setup')
                ->withErrors(['tag_ids' => 'Không tìm thấy câu hỏi phù hợp bộ lọc — thử bỏ bớt điều kiện lọc.']);
        }

        return redirect()->route('student.practiceByQuestion.play');
    }

    /**
     * student.practiceByQuestion.startExercise (31/8, "ZIP bài tập" gắn vào sản phẩm) — "Làm
     * bài" 1 bài tập cụ thể từ trang "Tài liệu của tôi". Kiểm tra lại quyền sở hữu sản phẩm Ở
     * ĐÂY (không tin trang "Tài liệu của tôi" đã lọc đúng — cùng nguyên tắc "2 request độc lập"
     * dùng khắp nơi trong hệ thống, ví dụ AccessService::downloadResource()) — chặn cả việc học
     * sinh tự gõ URL vào thẳng 1 bài tập của sản phẩm mình chưa mua.
     */
    public function startExercise(Request $request, Question $exercise): RedirectResponse
    {
        abort_if($exercise->product_id === null, 404);

        $product = $exercise->product;
        abort_unless($this->accessGate->canAccessProduct(Auth::user(), $product)->allowed, 403);

        $data = $request->validate(['return_url' => ['nullable', 'string', 'max:2000']]);

        // Chặn open-redirect: chỉ nhận đường dẫn NỘI BỘ bắt đầu bằng đúng 1 dấu '/' (không phải
        // '//host/...' — dạng protocol-relative có thể trỏ ra domain khác) — 'return_url' tuy
        // do server tự đặt vào form ẩn ở mine.blade.php, nhưng vẫn là input POST nên không tin
        // nguyên văn giá trị người dùng gửi lên (16 mục 3).
        $returnUrl = $data['return_url'] ?? null;
        if ($returnUrl !== null && (! str_starts_with($returnUrl, '/') || str_starts_with($returnUrl, '//'))) {
            $returnUrl = null;
        }

        $this->service->startForQuestion($exercise->id, $returnUrl);

        return redirect()->route('student.practiceByQuestion.play');
    }

    public function play(Request $request): View|RedirectResponse
    {
        $data = $this->service->playData();

        if ($data === null) {
            return redirect()->route('student.practiceByQuestion.setup');
        }

        return view('student.practice.by-question-play', $data);
    }

    /**
     * SỬA 24/8 (v4) — thêm 'code_source'/'language' cho câu Lập trình (xem
     * PracticeByQuestionService::answer() — chỉ GHI NHẬN bài làm, không tự chấm đúng/sai vì
     * chưa có sandbox chấm code thật).
     */
    public function answer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'selected_option' => ['nullable', 'integer'],
            'text' => ['nullable', 'string', 'max:500'],
            'code_source' => ['nullable', 'string', 'max:20000'],
            'language' => ['nullable', 'string', 'max:30'],
        ]);

        if (! $this->service->answer($data)) {
            return redirect()->route('student.practiceByQuestion.setup');
        }

        return redirect()->route('student.practiceByQuestion.play');
    }

    public function next(Request $request): RedirectResponse
    {
        $this->service->advance();

        return redirect()->route('student.practiceByQuestion.play');
    }

    /** SỬA 31/8 — nếu phiên vừa dừng là "Làm bài" 1 bài tập sản phẩm (có returnUrl lưu sẵn,
     *  xem startForQuestion()), quay lại ĐÚNG trang sản phẩm thay vì trang "Luyện tập" chung. */
    public function stop(Request $request): RedirectResponse
    {
        $returnUrl = $this->service->stop();

        return $returnUrl !== null
            ? redirect()->to($returnUrl)
            : redirect()->route('student.practice.index');
    }
}
