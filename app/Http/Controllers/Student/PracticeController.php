<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Public\PracticeService as PublicPracticeService;
use App\Services\Student\PracticeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PracticeController extends Controller
{
    public function __construct(
        private PracticeService $practiceService,
        // SỬA 18/9 (khách: "trang luyện tập trong học sinh xây tương tự trang public") — tab
        // "Tự luyện" giờ hiển thị ĐÚNG kho bài của trang công khai, nên dùng LUÔN service của
        // trang đó thay vì dựng một nguồn thứ hai: hai màn không bao giờ lệch số liệu/tỷ lệ AC.
        private PublicPracticeService $catalog,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $tab = $request->query('tab', 'self');
        // SỬA 18/8: 2 tham số lọc thật (App\Enums\QuestionType + tên QuestionBank làm "chuyên
        // đề" tạm — xem docblock PracticeService::buildIndexData()), null nếu không lọc.
        $type = $request->query('type');
        $topic = $request->query('topic');

        $data = $this->practiceService->buildIndexData($user, $tab, $type, $topic);

        // Chỉ nạp kho bài công khai khi ĐANG ở tab "Tự luyện" — 4 tab còn lại (Theo lớp / Bài
        // được giao / Đã lưu / Lịch sử) dùng danh sách riêng của học sinh, nạp thêm là tốn
        // truy vấn vô ích.
        // SỬA 18/9 (khách: "để 1 tab lịch sử thôi") — trang chỉ còn 2 mục: kho bài tập và
        // Lịch sử. Mọi tab KHÁC 'history' đều vẽ kho bài tập nên cần dữ liệu công khai; riêng
        // 'history' dùng danh sách lượt nộp của học sinh, không nạp thêm cho khỏi tốn truy vấn.
        if ($tab !== 'history') {
            // Thứ tự merge CỐ Ý: khoá 'items' của kho công khai (thẻ đề thi) ghi đè khoá
            // 'items' của service học sinh — ở mục này Blade chỉ vẽ kho công khai nên đó đúng
            // là dữ liệu cần. Mục Lịch sử không merge nên vẫn dùng 'items' của học sinh
            // (danh sách lượt đã nộp).
            $data = array_merge($data, $this->catalog->indexData($user));
        }

        return view('student.practice.index', $data);
    }
}
