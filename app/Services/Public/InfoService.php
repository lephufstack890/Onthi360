<?php

namespace App\Services\Public;

/**
 * info.index (PUB-11, 4.1: Giới thiệu, hướng dẫn, tin tức, chính sách, liên hệ, FAQ) — trước
 * đây route này là closure (Route::get('/thong-tin', fn () => view('public.info.index')))
 * và view TỰ khai báo mọi mảng dữ liệu (kể cả 4 số liệu "12k+/340+/98%/24/7" — cố định, không
 * liên quan gì tới dữ liệu thật) ngay trong blade — không có Controller/Service nào đứng sau.
 *
 * "Giới thiệu" giờ dùng lại ĐÚNG 4 số liệu thật đã tính cho trang chủ (HomeService::
 * buildStats()) thay vì 2 số không thể đo được thật ("98% hài lòng" — hệ thống chưa có khảo
 * sát mức hài lòng nào; "24/7" — chỉ là mô tả dịch vụ, không phải số liệu) để không có 2
 * trang cùng hiện 2 bộ số liệu khác nhau cho cùng 1 nền tảng.
 *
 * 5 thẻ "Vì sao chọn" giờ bấm được — trỏ vào ĐÚNG trang tính năng thật tương ứng, giống hệt
 * cách đã làm ở trang chủ (welcome.blade.php) để 2 nơi không lệch hành vi cho cùng nội dung.
 *
 * Chính sách giờ có trang chi tiết thật (info.policies.show) thay vì tạm trỏ '#' — nội dung là
 * bản nháp do đội kỹ thuật soạn theo đúng những gì hệ thống thực sự làm (7.4 quy trình đơn
 * hàng/hoàn tiền, 9.4 kiểm duyệt đánh giá, quyền học/dạy có thời hạn...), CHƯA qua rà soát
 * pháp lý chính thức — cần đội ngũ pháp lý/quản trị xem lại nội dung trước khi công bố chính
 * thức cho người dùng thật.
 *
 * Hướng dẫn sử dụng/Liên hệ vẫn là nội dung tĩnh (văn bản mô tả vai trò, thông tin liên hệ công
 * ty) — không phải dữ liệu nghiệp vụ cần truy vấn, giống cách FAQ trang chủ (HomeService::
 * faqs()) vẫn giữ tĩnh.
 */
class InfoService
{
    public function __construct(private readonly HomeService $homeService) {}

    public function indexData(): array
    {
        return [
            'stats' => $this->homeService->buildStats(),
            'sections' => $this->sections(),
            'guides' => $this->guides(),
            'policies' => $this->policies(),
            'highlights' => $this->highlights(),
            'reasons' => $this->reasons(),
            'contact' => $this->contact(),
        ];
    }

    /**
     * info.policies.show — null nếu $slug không khớp policy nào (Controller trả 404).
     *
     * @return array{title: string, desc: string, sections: array}|null
     */
    public function policyDetail(string $slug): ?array
    {
        $policy = collect($this->policies())->firstWhere('slug', $slug);
        $sections = $this->policyContent()[$slug] ?? null;

        if ($policy === null || $sections === null) {
            return null;
        }

        return [
            'title' => $policy['title'],
            'desc' => $policy['desc'],
            'sections' => $sections,
        ];
    }

    private function sections(): array
    {
        return [
            ['id' => 'gioi-thieu', 'label' => 'Giới thiệu', 'icon' => '📖'],
            ['id' => 'huong-dan', 'label' => 'Hướng dẫn sử dụng', 'icon' => '🧭'],
            ['id' => 'chinh-sach', 'label' => 'Chính sách', 'icon' => '📜'],
            ['id' => 'lien-he', 'label' => 'Liên hệ', 'icon' => '✉️'],
        ];
    }

    private function guides(): array
    {
        return [
            ['role' => 'Học sinh', 'icon' => '🧑‍🎓', 'tone' => 'rose', 'steps' => [
                'Vào lớp bằng mã giáo viên cung cấp, hoặc luyện tập công khai ngay không cần chờ ai duyệt',
                'Làm bài trong lộ trình lớp — bài lập trình được chấm tự động',
                'Theo dõi tiến độ, kết quả và thông báo ngay trên Tổng quan',
            ]],
            ['role' => 'Giáo viên', 'icon' => '🍎', 'tone' => 'sky', 'steps' => [
                'Đăng ký và chờ Admin duyệt hồ sơ giáo viên trước khi mở lớp',
                'Tạo lớp, gắn học liệu còn quyền dạy, giao bài kiểm tra có hạn nộp',
                'Nhập đề nhanh bằng Word/PDF/OCR, rà soát trước khi phát hành',
            ]],
            ['role' => 'Phụ huynh', 'icon' => '👨‍👩‍👧', 'tone' => 'emerald', 'steps' => [
                'Nhận mã liên kết từ con để theo dõi tài khoản học sinh',
                'Xem lịch học, điểm danh, tiến độ và kết quả gần đây',
                'Nhận thông báo khi quyền học/dạy sắp hết hạn',
            ]],
        ];
    }

    /** @return array<int, array{slug: string, title: string, desc: string}> */
    private function policies(): array
    {
        return [
            ['slug' => 'bao-mat', 'title' => 'Chính sách bảo mật', 'desc' => 'Cách thu thập, sử dụng và bảo vệ dữ liệu cá nhân, đặc biệt với học sinh dưới 18 tuổi.'],
            ['slug' => 'dieu-khoan', 'title' => 'Điều khoản sử dụng', 'desc' => 'Quy định quyền và trách nhiệm khi sử dụng Ôn Thi 360 cho từng vai trò.'],
            ['slug' => 'hoan-tien', 'title' => 'Chính sách hoàn tiền', 'desc' => 'Điều kiện và quy trình hoàn tiền cho đơn hàng sản phẩm/khóa học (7.4).'],
        ];
    }

    /**
     * Nội dung chi tiết từng chính sách (info.policies.show) — bản nháp bám theo đúng luồng
     * nghiệp vụ đã triển khai thật (đơn hàng/kích hoạt 7.4, kiểm duyệt đánh giá 9.4, quyền
     * học/dạy có thời hạn...), KHÔNG chép mẫu điều khoản pháp lý có sẵn từ nơi khác. Đội pháp
     * lý/quản trị cần rà soát trước khi xem đây là nội dung chính thức.
     *
     * @return array<string, array<int, array{heading: string, body: string}>>
     */
    private function policyContent(): array
    {
        return [
            'bao-mat' => [
                ['heading' => 'Dữ liệu chúng tôi thu thập', 'body' => 'Họ tên, email, vai trò tài khoản (học sinh/giáo viên/phụ huynh), kết quả luyện tập và làm bài, lịch sử đơn hàng và giao dịch ví/token. Chúng tôi không thu thập dữ liệu nào ngoài phạm vi cần thiết để vận hành các tính năng đang có.'],
                ['heading' => 'Mục đích sử dụng', 'body' => 'Vận hành tài khoản và lớp học, chấm bài và lưu lại lịch sử để ôn tập, đối soát thanh toán và cấp quyền học/dạy, phản hồi khi có yêu cầu hỗ trợ qua mục Liên hệ.'],
                ['heading' => 'Bảo vệ dữ liệu học sinh dưới 18 tuổi', 'body' => 'Phụ huynh chỉ theo dõi được tài khoản con qua mã liên kết do chính học sinh cấp — không tự động lộ thông tin học sinh cho bất kỳ tài khoản phụ huynh nào khác.'],
                ['heading' => 'Chia sẻ với bên thứ ba', 'body' => 'Chỉ chia sẻ trong phạm vi cần thiết để vận hành (ví dụ dịch vụ hạ tầng lưu trữ), không bán hoặc cho thuê dữ liệu cá nhân cho mục đích quảng cáo.'],
                ['heading' => 'Quyền của người dùng', 'body' => 'Người dùng có thể yêu cầu xem, chỉnh sửa hoặc xoá dữ liệu cá nhân của mình bằng cách liên hệ qua mục Liên hệ ở trang này.'],
            ],
            'dieu-khoan' => [
                ['heading' => 'Phạm vi áp dụng', 'body' => 'Áp dụng cho học sinh, giáo viên, phụ huynh đã đăng ký tài khoản, và khách vãng lai truy cập nội dung luyện tập/tài liệu công khai.'],
                ['heading' => 'Tài khoản và vai trò', 'body' => 'Giáo viên cần được Admin duyệt hồ sơ trước khi mở lớp. Học sinh cần có quyền học còn hiệu lực để truy cập nội dung giới hạn — hệ thống luôn nêu rõ lý do khi một nội dung bị khoá (thiếu quyền, giáo viên chưa mở theo tiến độ lớp, hoặc quyền đã hết hạn), không khoá mà không giải thích.'],
                ['heading' => 'Nội dung do người dùng đăng', 'body' => 'Đánh giá/nhận xét được kiểm duyệt trước khi công khai. Chúng tôi chỉ xử lý nội dung vi phạm (spam, xúc phạm, quảng cáo, lộ thông tin cá nhân) — không ẩn hoặc xoá đánh giá chỉ vì nội dung tiêu cực nhưng hợp lệ.'],
                ['heading' => 'Hành vi không được phép', 'body' => 'Gian lận khi làm bài/thi, chia sẻ tài khoản cho người khác sử dụng, đăng nội dung vi phạm pháp luật hoặc thuần phong mỹ tục, spam quảng cáo trong đánh giá hoặc tin nhắn liên hệ.'],
                ['heading' => 'Thay đổi điều khoản', 'body' => 'Điều khoản có thể được cập nhật khi hệ thống có tính năng mới. Việc tiếp tục sử dụng sau khi cập nhật được xem là đã đồng ý với điều khoản mới.'],
            ],
            'hoan-tien' => [
                ['heading' => 'Phạm vi áp dụng', 'body' => 'Áp dụng cho đơn hàng mua quyền học/dạy, khóa học hoặc gói token đã đặt qua hệ thống (7.4).'],
                ['heading' => 'Quy trình đơn hàng', 'body' => 'Đặt đơn → thanh toán → Admin đối soát và duyệt thủ công → hệ thống cấp mã kích hoạt → người dùng kích hoạt để nhận quyền. Yêu cầu hoàn tiền chỉ được xem xét trước khi quyền được kích hoạt và sử dụng.'],
                ['heading' => 'Trường hợp được hoàn tiền', 'body' => 'Đơn bị từ chối duyệt do lỗi từ phía hệ thống, đơn bị đặt trùng lặp, hoặc mã kích hoạt chưa được sử dụng trong thời gian hợp lý sau khi thanh toán.'],
                ['heading' => 'Trường hợp không hoàn tiền', 'body' => 'Quyền học/dạy đã được kích hoạt và sử dụng, hoặc mã kích hoạt đã được dùng để mở khóa nội dung.'],
                ['heading' => 'Cách yêu cầu hoàn tiền', 'body' => 'Liên hệ qua mục Liên hệ ở trang này, kèm mã đơn hàng và lý do. Mọi quyết định duyệt/từ chối hoàn tiền đều được Admin ghi lại lý do rõ ràng (giống quy trình duyệt/từ chối đơn hàng, 7.4/10.4).'],
            ],
        ];
    }

    /**
     * Mỗi mục trỏ vào ĐÚNG trang tính năng tương ứng đã có thật trong hệ thống — giống hệt
     * mapping ở welcome.blade.php (trang chủ) để hành vi 2 nơi nhất quán. "Khảo sát an toàn"
     * trỏ competitions.index (gộp chung Cuộc thi + Khảo sát). "Du học & Trải nghiệm" chưa có
     * tính năng riêng nên trỏ về #gioi-thieu (đầu trang Thông tin) — vì trang này CHÍNH LÀ
     * info.index nên trỏ tới chính route hiện tại không hợp lý, dùng anchor nội trang thay thế.
     *
     * @return array<int, array{emoji: string, tone: string, title: string, body: string, route: string}>
     */
    private function highlights(): array
    {
        return [
            ['emoji' => '🤖', 'tone' => 'rose', 'title' => 'AI luyện tập thông minh', 'body' => 'Gợi ý bài phù hợp năng lực, cá nhân hóa lộ trình học.', 'route' => 'practice.index'],
            ['emoji' => '🧑‍🤝‍🧑', 'tone' => 'sky', 'title' => 'Lớp học gọn gàng', 'body' => 'Tổ chức lớp, giao bài, theo dõi tiến độ dễ dàng.', 'route' => 'courses.index'],
            ['emoji' => '📋', 'tone' => 'violet', 'title' => 'Khảo sát an toàn', 'body' => 'Tham gia đúng thời điểm, kiểm soát chặt với late-link.', 'route' => 'competitions.index'],
            ['emoji' => '👛', 'tone' => 'amber', 'title' => 'Ví & Token minh bạch', 'body' => 'Xem số dư, lịch sử sử dụng và quyền lợi rõ ràng.', 'route' => 'wallet.index'],
            ['emoji' => '🌍', 'tone' => 'emerald', 'title' => 'Du học & Trải nghiệm', 'body' => 'Không gian mở rộng cho định hướng và cơ hội phát triển.', 'route' => null],
        ];
    }

    private function reasons(): array
    {
        return [
            '⏰ Luyện tập mọi lúc, mọi nơi',
            '📈 Nâng cao hiệu quả học tập',
            '📝 Đề thi bám sát chương trình',
            '🛡️ Học tập an toàn, lành mạnh',
            '📊 Phân tích điểm chi tiết',
            '👥 Cộng đồng học tập tích cực',
        ];
    }

    private function contact(): array
    {
        return [
            'hotline' => '0978 729 962',
            'email' => 'support@onthi360.edu.vn',
            'facebook' => 'facebook.com/onthi360',
            'zalo' => 'zalo.me/onthi360',
            // Chưa có địa chỉ văn phòng chính thức để công bố — không bịa 1 địa chỉ giả.
            'address' => null,
        ];
    }
}
