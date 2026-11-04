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
            // SỬA 11/9 — giao diện mới (education-main/src/components/InfoPage.jsx) có khối FAQ
            // riêng cạnh form hỗ trợ. Nội dung là mô tả CÁCH HỆ THỐNG NÀY thực sự hoạt động
            // (kích hoạt mã, chấm bài OJ, mở lớp, phụ huynh theo dõi) — văn bản tĩnh giống
            // HomeService::faqs(), không phải dữ liệu nghiệp vụ cần truy vấn.
            'supportFaqs' => $this->supportFaqs(),
        ];
    }

    /** @return array<int, array{q: string, a: string}> */
    private function supportFaqs(): array
    {
        return [
            ['q' => 'Làm thế nào để kích hoạt mã khóa học hoặc sách?', 'a' => 'Vào khu học tập (hoặc khu dạy học) rồi chọn mục "Mã kích hoạt" ở thanh bên trái, nhập mã được cấp và bấm Kích hoạt. Quyền học được mở ngay sau khi mã hợp lệ; nếu mã đã dùng hoặc hết hạn, hệ thống nêu rõ lý do.'],
            ['q' => 'Hệ thống chấm bài Online Judge hoạt động như thế nào?', 'a' => 'Bài lập trình được chấm bằng bộ test và luật chấm đã định sẵn cho từng câu: mã nguồn được biên dịch, chạy qua các bộ test ẩn rồi trả kết quả. Trắc nghiệm và điền đáp án được chấm ngay theo đáp án đã cấu hình, nên điểm hiện gần như tức thì.'],
            ['q' => 'Giáo viên mở lớp và giao đề cho học sinh như thế nào?', 'a' => 'Giáo viên đăng ký hồ sơ và chờ quản trị viên duyệt trước khi mở lớp. Sau khi được duyệt, giáo viên tạo lớp, gắn học liệu còn quyền dạy, rồi giao bài kiểm tra kèm thời điểm mở và hạn nộp cho từng lớp.'],
            ['q' => 'Phụ huynh theo dõi việc học của con ra sao?', 'a' => 'Phụ huynh dùng mã liên kết do chính học sinh cấp để kết nối tài khoản. Sau khi liên kết được xác thực, phụ huynh xem được lịch học, điểm danh, số bài đã nộp và tiến độ của con — không tài khoản phụ huynh nào khác thấy được dữ liệu đó.'],
            // SỬA 4/11 (khách: "thêm cho đủ nội dung như trang chủ") — nối thêm toàn bộ câu hỏi của trang chủ (HomeService::faqs),
            // bỏ câu trùng ý "Chấm bài code (OJ)…" vì đã có câu 2 ở trên giải thích kỹ hơn. Sửa câu hỏi trang chủ là trang này đổi theo.
            ...array_values(array_filter(
                rescue(fn () => app(HomeService::class)->faqs(), [], false),
                fn ($f) => ! str_starts_with((string) ($f['q'] ?? ''), 'Chấm bài code (OJ)')
            )),
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
            // SỬA 3/11 — ngày cập nhật hiện dưới tiêu đề (giao diện mới của trang chính sách).
            'updated' => $policy['updated'] ?? null,
            'slug' => $slug,
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
    /**
     * SỬA 12/9 — danh sách slug chính sách, để sitemap (Public\SitemapController) và các nơi
     * khác đọc CHUNG một nguồn thay vì chép cứng 3 slug ở nhiều chỗ rồi lệch nhau.
     *
     * @return array<int, string>
     */
    public function policySlugs(): array
    {
        return array_column($this->policies(), 'slug');
    }

    private function policies(): array
    {
        return [
            ['slug' => 'bao-mat', 'title' => 'Chính sách bảo mật', 'desc' => 'Cách thu thập, sử dụng và bảo vệ dữ liệu cá nhân, đặc biệt với học sinh dưới 18 tuổi.'],
            ['slug' => 'dieu-khoan', 'title' => 'Điều khoản sử dụng', 'desc' => 'Quy định về tài khoản, quyền và nghĩa vụ của học sinh, giáo viên, phụ huynh và Ôn Thi 360; quy định khi luyện tập, chấm code online, tham gia cuộc thi, thanh toán token và xử lý vi phạm.', 'updated' => '04/11/2026'],
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
            // SỬA 4/11 (khách: "trang điều khoản còn ít chữ, nghèo nàn — thêm thông tin cho phong phú") — viết đầy đủ theo đúng các
            // tính năng ĐANG CÓ (tài khoản & vai trò, chấm code online, cuộc thi/khảo sát, token & mã kích hoạt, liên kết phụ huynh,
            // kiểm duyệt đánh giá…). Không bịa tên pháp nhân / địa chỉ. Nên nhờ bộ phận pháp lý của khách rà lại trước khi coi là
            // văn bản chính thức. Khoá mỗi mục: body (đoạn văn) · intro + items (danh sách tick) · split (2 thẻ trái/phải).
            'dieu-khoan' => [
                ['heading' => 'Giới thiệu', 'body' => 'Tin học Onthi360 (tinhoc.onthi360.vn) thuộc hệ thống Ôn Thi 360 (onthi360.vn) — nền tảng học tập và luyện thi môn Tin học trực tuyến. Nền tảng cung cấp lớp học và khóa học, kho tài liệu, kho bài luyện tập có chấm code online tự động (C++, Python), đề thi luyện tập mô phỏng, cuộc thi và khảo sát năng lực, bảng xếp hạng, cùng các công cụ để giáo viên tổ chức lớp và phụ huynh đồng hành cùng con. Bằng việc truy cập, đăng ký tài khoản hoặc sử dụng bất kỳ dịch vụ nào trên nền tảng, bạn xác nhận đã đọc, hiểu và đồng ý với toàn bộ Điều khoản sử dụng này cùng Chính sách bảo mật và Chính sách hoàn tiền đi kèm.'],
                ['heading' => 'Giải thích từ ngữ', 'intro' => 'Trong Điều khoản này, các từ ngữ dưới đây được hiểu như sau:', 'items' => [
                    'Nền tảng / Ôn Thi 360: website tinhoc.onthi360.vn và các dịch vụ, tính năng đi kèm do Ôn Thi 360 vận hành.',
                    'Người dùng: mọi cá nhân truy cập nền tảng, gồm khách chưa đăng nhập và người đã có tài khoản (học sinh, giáo viên, phụ huynh).',
                    'Quyền học / quyền dạy: quyền truy cập một khóa học, tài liệu hoặc bộ đề trong một thời hạn nhất định, được cấp sau khi đơn hàng được duyệt hoặc khi kích hoạt mã.',
                    'Token: đơn vị thanh toán nội bộ trong ví của tài khoản, dùng để mua quyền học, học liệu và các dịch vụ trên nền tảng.',
                    'Mã kích hoạt: mã dùng một lần để mở quyền học / quyền dạy cho một tài khoản.',
                    'Nội dung người dùng: bài làm, bài code, đánh giá, nhận xét, tin nhắn liên hệ, đề và học liệu do người dùng gửi hoặc đăng lên nền tảng.',
                ]],
                ['heading' => 'Phạm vi áp dụng', 'body' => 'Điều khoản này áp dụng cho tất cả người truy cập và sử dụng dịch vụ trên Ôn Thi 360: học sinh, giáo viên, phụ huynh đã đăng ký tài khoản, khách vãng lai truy cập nội dung luyện tập / tài liệu công khai và các đối tác liên quan. Với một số dịch vụ riêng (cuộc thi, khảo sát, khóa học có điều kiện riêng), quy định của dịch vụ đó được áp dụng bổ sung; nếu có điểm khác nhau thì quy định riêng của dịch vụ được ưu tiên.'],
                ['heading' => 'Đăng ký và quản lý tài khoản', 'intro' => 'Khi tạo và sử dụng tài khoản, người dùng cần:', 'items' => [
                    'Cung cấp thông tin chính xác, đầy đủ (họ tên, email, số điện thoại…) và cập nhật khi có thay đổi.',
                    'Tự bảo mật mật khẩu, không cho người khác dùng chung tài khoản; báo ngay cho Ôn Thi 360 khi nghi ngờ tài khoản bị truy cập trái phép.',
                    'Mỗi người chỉ dùng một tài khoản cho một vai trò; không tạo nhiều tài khoản để gian lận điểm, xếp hạng hoặc ưu đãi.',
                    'Học sinh dưới 18 tuổi nên sử dụng nền tảng với sự đồng ý và đồng hành của cha mẹ hoặc người giám hộ.',
                    'Giáo viên cần được Ban quản trị duyệt hồ sơ trước khi mở lớp, giao bài và đăng đề.',
                    'Người dùng chịu trách nhiệm về mọi hoạt động phát sinh từ tài khoản của mình, trừ trường hợp đã báo mất quyền kiểm soát tài khoản cho Ôn Thi 360.',
                ]],
                ['heading' => 'Quyền và nghĩa vụ của người dùng', 'split' => [
                    ['title' => 'Quyền của người dùng', 'tone' => 'ok', 'items' => [
                        'Được truy cập, sử dụng các dịch vụ của Ôn Thi 360 theo quyền học / dạy đang có.',
                        'Được luyện tập công khai, làm bài và nhận kết quả chấm tự động ngay trên web.',
                        'Được biết rõ lý do khi một nội dung bị khoá (thiếu quyền, giáo viên chưa mở theo tiến độ lớp, hoặc quyền đã hết hạn).',
                        'Được xem lại lịch sử làm bài, kết quả và tiến độ học tập của mình.',
                        'Được hỗ trợ, giải đáp thắc mắc qua các kênh chính thức.',
                        'Được bảo vệ thông tin cá nhân theo Chính sách bảo mật; được yêu cầu chỉnh sửa hoặc xoá dữ liệu của mình.',
                    ]],
                    ['title' => 'Nghĩa vụ của người dùng', 'tone' => 'warn', 'items' => [
                        'Tuân thủ các điều khoản, quy định của website và pháp luật Việt Nam.',
                        'Trung thực khi làm bài, thi và khảo sát; không gian lận dưới bất kỳ hình thức nào.',
                        'Không chia sẻ tài khoản, mã kích hoạt, đề thi đang diễn ra cho người khác.',
                        'Không đăng nội dung vi phạm pháp luật, thuần phong mỹ tục hoặc spam quảng cáo trong đánh giá, tin nhắn liên hệ.',
                        'Tôn trọng giáo viên, ban tổ chức và những người dùng khác.',
                        'Thanh toán đầy đủ, đúng hướng dẫn cho các dịch vụ có phí.',
                    ]],
                ]],
                ['heading' => 'Luyện tập, làm bài và chấm code online', 'intro' => 'Hệ thống chấm code online (Online Judge) và khu luyện tập vận hành theo các nguyên tắc sau:', 'items' => [
                    'Bài lập trình được chấm tự động bằng bộ test của đề; kết quả hiển thị theo từng test (đúng, sai, quá thời gian, lỗi biên dịch…).',
                    'Bài trắc nghiệm và bài điền đáp án được chấm ngay sau khi nộp; lịch sử nộp bài được lưu để người dùng ôn lại.',
                    'Không gửi mã độc, không cố tình khai thác lỗi, dò tìm bộ test hay làm quá tải hệ thống chấm.',
                    'Không sao chép bài làm của người khác, không dùng nhiều tài khoản để nộp thử nhằm nâng thứ hạng.',
                    'Thời gian chạy và giới hạn bộ nhớ của mỗi bài do đề quy định; kết quả chấm tự động là kết quả chính thức của lượt nộp.',
                    'Khi phát hiện bộ test hoặc đáp án của đề có sai sót, Ôn Thi 360 có thể sửa đề và chấm lại; điểm và thứ hạng liên quan được cập nhật theo.',
                ]],
                ['heading' => 'Cuộc thi và khảo sát năng lực', 'intro' => 'Khi tham gia cuộc thi hoặc khảo sát trên nền tảng:', 'items' => [
                    'Thí sinh đăng ký theo hướng dẫn của từng cuộc thi; một số cuộc thi cần ban tổ chức duyệt đơn hoặc nhập mật khẩu kỳ thi.',
                    'Thí sinh làm bài đúng thời gian quy định; bài nộp sau khi hết giờ có thể không được tính.',
                    'Hệ thống ghi nhận nhật ký thao tác trong lúc làm bài để phục vụ việc kiểm tra tính trung thực.',
                    'Ban tổ chức có quyền huỷ kết quả, loại thí sinh khi phát hiện gian lận, làm bài hộ hoặc trao đổi đề trong giờ thi.',
                    'Ban tổ chức có thể điều chỉnh điểm thủ công (cộng / trừ) khi có căn cứ và công bố bảng điểm, xếp giải theo quy chế của từng cuộc thi.',
                    'Thắc mắc về kết quả gửi cho ban tổ chức trong thời hạn được thông báo; quyết định cuối cùng thuộc về ban tổ chức cuộc thi.',
                ]],
                ['heading' => 'Dành cho giáo viên', 'intro' => 'Giáo viên sử dụng nền tảng để dạy học cần:', 'items' => [
                    'Chỉ tổ chức lớp, giao bài và đăng đề sau khi hồ sơ giáo viên được duyệt.',
                    'Chịu trách nhiệm về tính chính xác và bản quyền của đề, tài liệu, bài tập do mình tải lên; không đăng nội dung sao chép trái phép.',
                    'Chỉ gắn vào lớp các học liệu mà mình còn quyền dạy; khi quyền dạy hết hạn, học liệu tương ứng sẽ bị khoá theo quy định.',
                    'Rà soát đề nhập từ Word / PDF / ảnh trước khi phát hành để tránh sai sót cho học sinh.',
                    'Bảo mật thông tin học sinh trong lớp, chỉ dùng thông tin đó cho mục đích dạy học.',
                    'Dùng tính năng thông báo lớp, nhận xét đúng mục đích học tập, lời lẽ chuẩn mực.',
                ]],
                ['heading' => 'Dành cho phụ huynh', 'intro' => 'Phụ huynh đồng hành cùng con trên nền tảng:', 'items' => [
                    'Liên kết với tài khoản của con bằng mã do chính học sinh cung cấp; một phụ huynh có thể theo dõi nhiều con.',
                    'Được xem lịch học, điểm danh, tiến độ, kết quả bài làm và nhận thông báo từ giáo viên, nhà trường và Ban quản trị.',
                    'Chỉ sử dụng thông tin của con cho mục đích theo dõi, hỗ trợ việc học; không chia sẻ cho người khác.',
                    'Có thể gỡ liên kết khi không còn nhu cầu theo dõi.',
                ]],
                ['heading' => 'Thanh toán, token và mã kích hoạt', 'items' => [
                    'Người dùng nạp token bằng chuyển khoản theo đúng nội dung / mã chuyển khoản hệ thống cung cấp; token được cộng sau khi Ban quản trị đối soát sao kê và duyệt.',
                    'Token dùng để mua quyền học, học liệu và các dịch vụ trong hệ thống Ôn Thi 360.',
                    'Đơn mua hàng được duyệt thủ công; sau khi duyệt, quyền học / quyền dạy hoặc mã kích hoạt được cấp cho tài khoản.',
                    'Mã kích hoạt chỉ dùng được một lần; người dùng tự bảo quản mã, Ôn Thi 360 không chịu trách nhiệm với mã bị lộ do người dùng chia sẻ.',
                    'Quyền học / quyền dạy có thời hạn; hết hạn thì nội dung tương ứng bị khoá nhưng kết quả và lịch sử làm bài cũ vẫn được giữ lại.',
                    'Điều kiện và quy trình hoàn tiền áp dụng theo Chính sách hoàn tiền.',
                ]],
                ['heading' => 'Quyền sở hữu trí tuệ', 'body' => 'Toàn bộ nội dung trên nền tảng — đề thi, bài tập, bộ test, tài liệu, video, giao diện, logo, tên gọi Ôn Thi 360 / Tin học Onthi360 — thuộc quyền sở hữu của Ôn Thi 360 hoặc của giáo viên, đối tác đã cho phép sử dụng.', 'intro' => 'Người dùng được sử dụng nội dung cho mục đích học tập cá nhân, và cam kết:', 'items' => [
                    'Không sao chép, tải về hàng loạt, chụp lại để phát tán, đăng lại hoặc bán lại đề, tài liệu khi chưa được Ôn Thi 360 đồng ý bằng văn bản.',
                    'Không dùng công cụ tự động (bot, phần mềm thu thập dữ liệu) để lấy nội dung của nền tảng.',
                    'Bài code, bài làm do người dùng tạo ra vẫn thuộc về người dùng; người dùng đồng ý để Ôn Thi 360 lưu trữ, chấm và hiển thị trong phạm vi vận hành (lịch sử nộp bài, bảng xếp hạng, bài mẫu khi giáo viên chọn).',
                ]],
                ['heading' => 'Đánh giá, nhận xét và nội dung người dùng', 'items' => [
                    'Đánh giá về khóa học, tài liệu, giáo viên phải phản ánh trải nghiệm thật của người dùng.',
                    'Ôn Thi 360 kiểm duyệt đánh giá / nhận xét trước khi công khai — chỉ xử lý nội dung vi phạm (spam, xúc phạm, quảng cáo, lộ thông tin cá nhân), không ẩn đánh giá chỉ vì tiêu cực nhưng hợp lệ.',
                    'Người dùng chịu trách nhiệm về nội dung mình đăng; nội dung vi phạm có thể bị ẩn hoặc gỡ mà không cần báo trước.',
                ]],
                ['heading' => 'Quyền và nghĩa vụ của Ôn Thi 360', 'intro' => 'Ôn Thi 360 có quyền và trách nhiệm:', 'items' => [
                    'Duy trì, nâng cấp nền tảng; cung cấp, thay đổi hoặc ngừng một phần hay toàn bộ dịch vụ khi cần bảo trì hoặc điều chỉnh tính năng, có thông báo trước khi có thể.',
                    'Đảm bảo hệ thống chấm bài, lưu trữ kết quả hoạt động ổn định và xử lý sự cố trong thời gian sớm nhất.',
                    'Từ chối, tạm khoá hoặc khoá tài khoản vi phạm điều khoản.',
                    'Bảo mật thông tin người dùng theo Chính sách bảo mật; không bán dữ liệu cá nhân cho bên thứ ba.',
                    'Tiếp nhận, phản hồi yêu cầu hỗ trợ và khiếu nại qua các kênh chính thức.',
                    'Thông báo cho người dùng khi Điều khoản có thay đổi quan trọng.',
                ]],
                ['heading' => 'Hành vi bị nghiêm cấm', 'items' => [
                    'Gian lận khi làm bài, thi, khảo sát: làm bài hộ, thi hộ, trao đổi đáp án trong giờ thi, dùng nhiều tài khoản.',
                    'Tấn công, dò quét lỗ hổng, gửi mã độc, can thiệp trái phép vào hệ thống chấm bài hoặc dữ liệu của người khác.',
                    'Mua bán, cho thuê, chia sẻ tài khoản hoặc mã kích hoạt.',
                    'Đăng tải nội dung vi phạm pháp luật, xúc phạm, bạo lực, phản cảm, lừa đảo hoặc quảng cáo trái phép.',
                    'Thu thập, sử dụng thông tin cá nhân của người dùng khác khi chưa được đồng ý.',
                    'Mạo danh Ôn Thi 360, giáo viên, ban tổ chức hoặc người dùng khác.',
                ]],
                ['heading' => 'Xử lý vi phạm', 'intro' => 'Tuỳ mức độ vi phạm, Ôn Thi 360 có thể áp dụng một hoặc nhiều biện pháp:', 'items' => [
                    'Nhắc nhở, yêu cầu gỡ bỏ hoặc tự ẩn nội dung vi phạm.',
                    'Huỷ kết quả bài làm, bài thi; loại khỏi bảng xếp hạng hoặc cuộc thi.',
                    'Tạm khoá hoặc khoá vĩnh viễn tài khoản; thu hồi quyền học / quyền dạy, mã kích hoạt liên quan đến hành vi vi phạm.',
                    'Cung cấp thông tin cho cơ quan nhà nước có thẩm quyền khi hành vi có dấu hiệu vi phạm pháp luật.',
                ]],
                ['heading' => 'Giới hạn trách nhiệm', 'items' => [
                    'Nền tảng có thể tạm gián đoạn do bảo trì, nâng cấp, sự cố đường truyền hoặc nguyên nhân bất khả kháng; Ôn Thi 360 sẽ khắc phục sớm nhất và cố gắng thông báo trước khi bảo trì.',
                    'Ôn Thi 360 không chịu trách nhiệm với thiệt hại phát sinh do người dùng để lộ mật khẩu, mã kích hoạt hoặc chia sẻ tài khoản.',
                    'Kết quả học tập và thi cử còn phụ thuộc vào sự nỗ lực của mỗi người; nền tảng hỗ trợ ôn luyện nhưng không cam kết một kết quả thi cụ thể.',
                    'Các liên kết tới website hoặc dịch vụ bên ngoài (Zalo, Facebook…) do bên thứ ba quản lý, chịu sự điều chỉnh bởi điều khoản của bên đó.',
                ]],
                ['heading' => 'Thay đổi điều khoản', 'body' => 'Điều khoản có thể được cập nhật khi nền tảng có tính năng mới hoặc khi quy định pháp luật thay đổi. Ngày cập nhật gần nhất luôn được ghi ở đầu trang; với thay đổi quan trọng, Ôn Thi 360 sẽ thông báo trên website hoặc qua thông báo trong tài khoản. Việc tiếp tục sử dụng dịch vụ sau khi điều khoản được cập nhật đồng nghĩa với việc người dùng đồng ý với nội dung mới.'],
                ['heading' => 'Giải quyết tranh chấp', 'body' => 'Mọi thắc mắc, khiếu nại liên quan đến dịch vụ trước hết được giải quyết bằng thương lượng, hoà giải trên tinh thần thiện chí qua các kênh hỗ trợ chính thức. Trường hợp không thể thương lượng, tranh chấp được giải quyết theo quy định của pháp luật nước Cộng hoà xã hội chủ nghĩa Việt Nam.'],
                ['heading' => 'Liên hệ', 'body' => 'Mọi câu hỏi về Điều khoản sử dụng, vui lòng liên hệ Ôn Thi 360 qua hotline / Zalo 0344 082 787, email hotro@onthi360.vn, hoặc gửi yêu cầu ở mục Liên hệ của trang Thông tin. Chúng tôi sẽ phản hồi trong thời gian sớm nhất.'],
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

    /**
     * SỬA 3/11 (khách: "chọn người hỗ trợ xong bấm Yêu cầu hỗ trợ thì nhảy sang Zalo tương ứng") — đội hỗ trợ. Form
     * "Gửi yêu cầu hỗ trợ" cho chọn 1 người; gửi xong lưu phiếu (admin vẫn xem ở Liên hệ) rồi mở Zalo đúng số người đó.
     * topic = loại yêu cầu ghi vào phiếu (App\Enums\SupportTopic) để bộ lọc ở trang admin vẫn dùng được.
     *
     * @return array<string, array{role: string, name: string, phone: string, hint: string, icon: string, topic: string}>
     */
    public static function supportTeam(): array
    {
        return [
            'admin' => ['role' => 'Admin tối cao', 'name' => 'Thầy Nguyễn Tiến Thành', 'phone' => '0978729962',
                'hint' => 'Khoá học, hợp tác, các việc chung', 'icon' => 'shield-check', 'topic' => 'other'],
            'quan_sinh' => ['role' => 'Quản sinh', 'name' => 'Cô Lê Thị Tươi', 'phone' => '0988670222',
                'hint' => 'Lớp học, học sinh, phụ huynh', 'icon' => 'users', 'topic' => 'learning'],
            'tro_giang' => ['role' => 'Trợ giảng', 'name' => 'Thầy Lê Trung Anh', 'phone' => '0373199558',
                'hint' => 'Bài tập, đề thi, kiến thức', 'icon' => 'book-open', 'topic' => 'learning'],
            'ky_thuat' => ['role' => 'Kỹ thuật', 'name' => 'Anh Lê Phú', 'phone' => '0916743545',
                'hint' => 'Lỗi web, đăng nhập, nạp token', 'icon' => 'settings', 'topic' => 'technical'],
        ];
    }

    private function contact(): array
    {
        return [
            // SỬA 3/11 (khách) — hotline / Zalo chung 0344 082 787, nhóm Facebook của trung tâm.
            'hotline' => '0344 082 787',
            // SỬA 23/9 — tên miền chính thức là onthi360.vn (web chạy ở tinhoc.onthi360.vn), hộp thư
            // hỗ trợ là hotro@onthi360.vn đã tạo trên cPanel. Địa chỉ .edu.vn cũ không tồn tại.
            'email' => 'hotro@onthi360.vn',
            'facebook' => 'facebook.com/groups/1506090357815287',
            'facebookUrl' => 'https://www.facebook.com/groups/1506090357815287?locale=vi_VN',
            'zalo' => 'zalo.me/0344082787',
            // Chưa có địa chỉ văn phòng chính thức để công bố — không bịa 1 địa chỉ giả.
            'address' => null,
        ];
    }
}
