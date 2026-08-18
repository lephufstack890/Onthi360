<?php

namespace App\Http\Controllers;

use App\Services\Review\ReviewService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function __construct(private ReviewService $reviewService) {}

    public function index(Request $request): View
    {
        $type = $request->query('type', 'material');
        $id = (int) $request->query('id', 0);

        return view('reviews.index', $this->reviewService->buildIndex($type, $id));
    }

    public function form(Request $request): View|RedirectResponse
    {
        $user = Auth::user();
        $type = $request->query('type', 'material');
        $id = (int) $request->query('id', 0);

        // Giữ ĐÚNG thứ tự kiểm tra của reviews.store (ReviewService::store() luôn gọi
        // checkEligibility() trước, kể cả khi đang SỬA review cũ) — nếu form() bỏ qua bước này
        // cho trường hợp sửa, học sinh có thể điền lại cả form rồi mới bị chặn ở bước gửi.
        $decision = $this->reviewService->checkEligibility($user, $type, $id);

        if (! $decision->allowed) {
            return redirect()->route('reviews.ineligible', ['reason' => $decision->message]);
        }

        // Đã từng đánh giá đối tượng này rồi — nếu quá 7 ngày sửa (9.2) thì chặn NGAY từ lúc mở
        // form thay vì để học sinh điền lại từ đầu rồi mới nhận lỗi ở bước gửi (reviews.store).
        $existing = $this->reviewService->findExistingReview($user, $type, $id);

        if ($existing !== null) {
            if (! $existing->isEditable()) {
                return redirect()->route('reviews.ineligible', [
                    'reason' => 'Bạn đã đánh giá đối tượng này và đã quá 7 ngày để sửa lại.',
                ]);
            }

            // Dùng lại đúng luật ReviewPolicy::update() (chủ review + còn hạn 7 ngày) thay vì
            // lặp lại điều kiện đó một lần nữa ở đây — trước đây Policy này chưa nơi nào gọi tới.
            $this->authorize('update', $existing);
        }

        return view('reviews.form', ['type' => $type, 'targetId' => $id, 'existing' => $existing]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:material,class,teacher,competition'],
            'target_id' => ['required', 'integer', 'min:1'],
            'overall_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'criteria_scores' => ['nullable', 'array'],
            'criteria_scores.*' => ['nullable', 'integer', 'min:0', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'disclosure_ack' => ['accepted'],
        ]);

        try {
            $this->reviewService->store(Auth::user(), $data['type'], (int) $data['target_id'], $data);
        } catch (ValidationException $e) {
            return redirect()
                ->route('reviews.form', ['type' => $data['type'], 'id' => $data['target_id']])
                ->withErrors($e->errors())
                ->withInput();
        }

        return redirect()->route('reviews.myReviews')->with('status', 'review-submitted');
    }

    public function ineligible(Request $request): View
    {
        $reason = $request->query('reason', 'Bạn cần tham gia ít nhất 2 buổi học hoặc hoàn thành một hoạt động trong lớp trước khi đánh giá.');

        return view('reviews.ineligible', ['reason' => $reason]);
    }

    public function myReviews(Request $request): View
    {
        $user = Auth::user();

        return view('reviews.my-reviews', $this->reviewService->buildMyReviews($user));
    }
}
