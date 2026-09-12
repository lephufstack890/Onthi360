<?php

namespace App\Http\Controllers\Public;

use App\Enums\CompetitionStatus;
use App\Enums\ProductType;
use App\Enums\TeacherApprovalStatus;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Competition;
use App\Models\Course;
use App\Models\LeaderboardEntry;
use App\Models\Product;
use App\Models\Question;
use App\Models\TeacherProfile;
use App\Services\Public\InfoService;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * sitemap.xml — bản đồ trang gửi cho Google/Bing.
 *
 * SỬA 12/9 — dựng lại theo ĐÚNG bảng đường dẫn của source khách gửi
 * (education-main/src/seo.js — hằng PUBLIC_ROUTES + SITE_URL). Source liệt kê 8 trang công
 * khai; ở đây giữ nguyên đúng 8 trang đó, chỉ đổi sang địa chỉ THẬT mà máy chủ đang phục vụ
 * (xem ghi chú "KHÁC BIỆT ĐƯỜNG DẪN" ở self::SECTION_PAGES).
 *
 * Những điểm sửa so với bản cũ, đều là thiếu sót thật:
 *   1. Thiếu <lastmod> ở 8 trang mục lục. Google dựa vào lastmod để quyết định có quay lại
 *      quét hay không; không có thì trang mục lục gần như không được quét lại. Giờ mỗi trang
 *      mục lục lấy mốc sửa mới nhất của CHÍNH nội dung nó liệt kê.
 *   2. Danh sách chính sách bị chép cứng 3 slug. Giờ đọc từ InfoService::policySlugs() nên
 *      thêm/bớt chính sách là sitemap tự cập nhật, không bị sót.
 *   3. Không có bộ nhớ đệm: mỗi lượt bot gọi là chạy lại toàn bộ truy vấn. Giờ đệm 1 giờ.
 *   4. Không chặn trần 50.000 URL của chuẩn sitemap.org — site lớn lên là file hỏng âm thầm.
 *   5. Nâng trần số trang chi tiết (2.000 -> 5.000 mỗi loại) và ghi rõ khi nào cần tách
 *      thành sitemap index.
 *
 * Địa chỉ trong sitemap BẮT BUỘC là địa chỉ tuyệt đối đúng tên miền thật. Laravel sinh ra từ
 * APP_URL, nên trên máy chủ thật phải đặt APP_URL=https://<tên miền> — không thì Google nhận
 * được toàn "http://localhost". Xem thêm public/robots.txt.
 */
class SitemapController extends Controller
{
    /** Thời gian đệm kết quả (phút). Nội dung mới chậm nhất 1 tiếng là có mặt trong sitemap. */
    private const CACHE_MINUTES = 60;

    /** Trần của chuẩn sitemap.org là 50.000 URL/file. Vượt thì phải tách sitemap index. */
    private const MAX_URLS = 50000;

    /** Số bản ghi chi tiết tối đa lấy cho mỗi loại nội dung. */
    private const DETAIL_LIMIT = 5000;

    /**
     * 8 trang công khai, đúng thứ tự và ý nghĩa của PUBLIC_ROUTES trong
     * education-main/src/seo.js.
     *
     * KHÁC BIỆT ĐƯỜNG DẪN (cố ý, không phải nhầm): source đặt "/lop-hoc/" và
     * "/giao-vien-chuyen-gia/", còn máy chủ đang chạy "/khoa-hoc" và "/giao-vien-tieu-bieu"
     * (routes/web.php). Sitemap PHẢI ghi địa chỉ có thật — ghi theo source thì Google quét vào
     * là 404. Ở đây dùng TÊN ROUTE nên luôn bám địa chỉ thật; ngày nào đổi slug cho khớp
     * source thì sitemap tự đổi theo, không phải sửa lại file này.
     *
     * 'lastmod' là KHÓA của loại nội dung mà trang đó liệt kê (xem lastModifiedByType()).
     *
     * @var array<int, array{route: string, priority: float, changefreq: string, lastmod: ?string}>
     */
    private const SECTION_PAGES = [
        ['route' => 'home', 'priority' => 1.0, 'changefreq' => 'daily', 'lastmod' => 'any'],
        ['route' => 'courses.index', 'priority' => 0.9, 'changefreq' => 'daily', 'lastmod' => 'course'],
        ['route' => 'practice.index', 'priority' => 0.9, 'changefreq' => 'daily', 'lastmod' => 'practice'],
        ['route' => 'materials.index', 'priority' => 0.8, 'changefreq' => 'daily', 'lastmod' => 'product'],
        ['route' => 'competitions.index', 'priority' => 0.7, 'changefreq' => 'daily', 'lastmod' => 'competition'],
        ['route' => 'leaderboard.index', 'priority' => 0.6, 'changefreq' => 'daily', 'lastmod' => 'leaderboard'],
        ['route' => 'teachers.index', 'priority' => 0.6, 'changefreq' => 'weekly', 'lastmod' => 'teacher'],
        ['route' => 'info.index', 'priority' => 0.4, 'changefreq' => 'monthly', 'lastmod' => 'info'],
    ];

    public function __construct(private InfoService $infoService) {}

    /**
     * Đổi số này khi sửa CẤU TRÚC sitemap (thêm/bớt loại trang) để buộc dựng lại ngay, khỏi
     * phải chờ hết hạn đệm hay xoá cache thủ công.
     */
    private const STRUCTURE_VERSION = 3;

    public function index(): Response
    {
        /*
         * Khoá đệm gắn với MỐC SỬA MỚI NHẤT của nội dung, không phải chỉ theo thời gian. Nhờ
         * vậy vừa đăng một lớp/tài liệu/cuộc thi mới là sitemap đổi ngay trong lượt gọi kế
         * tiếp, thay vì bot phải chờ tới 1 tiếng mới thấy. Sáu truy vấn MAX() để dựng khoá rất
         * nhẹ so với việc dựng lại cả sitemap.
         */
        $lastmods = $this->lastModifiedByType();
        $fingerprint = md5(self::STRUCTURE_VERSION.'|'.collect($lastmods)->map(fn ($m) => $m?->getTimestamp() ?? 0)->implode(','));

        $xml = Cache::remember(
            'sitemap.xml:'.$fingerprint,
            now()->addMinutes(self::CACHE_MINUTES),
            fn () => $this->build($lastmods),
        );

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age='.(self::CACHE_MINUTES * 60),
        ]);
    }

    /** @param array<string, ?Carbon> $lastmods */
    private function build(array $lastmods): string
    {
        $urls = [];

        // ── 1. Tám trang công khai (bám đúng source seo.js) ──────────────────
        foreach (self::SECTION_PAGES as $page) {
            $urls[] = [
                'loc' => route($page['route']),
                'lastmod' => $page['lastmod'] !== null ? ($lastmods[$page['lastmod']] ?? null) : null,
                'priority' => $page['priority'],
                'changefreq' => $page['changefreq'],
            ];
        }

        // ── 2. Trang chính sách (đọc từ InfoService, không chép cứng) ────────
        foreach ($this->infoService->policySlugs() as $slug) {
            $urls[] = [
                'loc' => route('info.policies.show', $slug),
                'lastmod' => $lastmods['info'] ?? null,
                'priority' => 0.2,
                'changefreq' => 'yearly',
            ];
        }

        // ── 3. Trang chi tiết ────────────────────────────────────────────────
        Course::query()
            ->where('status', 'published')
            ->select('id', 'updated_at')
            ->orderByDesc('updated_at')
            ->limit(self::DETAIL_LIMIT)
            ->each(function (Course $c) use (&$urls) {
                $urls[] = ['loc' => route('courses.show', $c->id), 'lastmod' => $c->updated_at, 'priority' => 0.8, 'changefreq' => 'weekly'];
            });

        Product::query()
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->where('type', '!=', ProductType::Course->value)
            ->select('id', 'updated_at')
            ->orderByDesc('updated_at')
            ->limit(self::DETAIL_LIMIT)
            ->each(function (Product $p) use (&$urls) {
                $urls[] = ['loc' => route('materials.show', $p->id), 'lastmod' => $p->updated_at, 'priority' => 0.7, 'changefreq' => 'weekly'];
            });

        Competition::query()
            ->where('status', '!=', CompetitionStatus::Archived->value)
            ->select('id', 'updated_at')
            ->orderByDesc('updated_at')
            ->limit(self::DETAIL_LIMIT)
            ->each(function (Competition $c) use (&$urls) {
                $urls[] = ['loc' => route('competitions.show', $c->id), 'lastmod' => $c->updated_at, 'priority' => 0.6, 'changefreq' => 'weekly'];
            });

        /*
         * CHƯA đưa trang hồ sơ giáo viên (teachers.show) vào: route đó hiện trả về view rỗng,
         * chưa có nội dung thật. Khai một trang trống với Google chỉ tổ bị chấm là nội dung
         * mỏng. Khi nào dựng xong màn đó thì thêm vào đây (ưu tiên 0.5, changefreq weekly).
         */

        // Trần của chuẩn: quá 50.000 URL thì cả file bị coi là không hợp lệ.
        if (count($urls) > self::MAX_URLS) {
            $urls = array_slice($urls, 0, self::MAX_URLS);
        }

        /*
         * Dòng khai báo XML ở đầu file do CONTROLLER ghép vào chứ không đặt trong Blade: chuỗi
         * đó chứa dấu đóng thẻ PHP nên để trong Blade là hỏng khối PHP sau khi biên dịch.
         * (Chính vì lý do đó mà đoạn ghi chú này phải là comment dạng khối, không dùng "//".)
         */
        return '<?xml version="1.0" encoding="UTF-8"?>'.view('sitemap', ['urls' => $urls])->render();
    }

    /**
     * Mốc sửa mới nhất của TỪNG loại nội dung, gộp trong vài truy vấn MAX() rất nhẹ — dùng làm
     * <lastmod> cho các trang mục lục. Trang "Lớp học" mà có lớp vừa sửa hôm nay thì lastmod là
     * hôm nay, Google mới có cớ quay lại quét.
     *
     * @return array<string, ?Carbon>
     */
    private function lastModifiedByType(): array
    {
        $max = function (string $model, ?callable $filter = null, string $column = 'updated_at'): ?Carbon {
            $query = $model::query();

            if ($filter !== null) {
                $filter($query);
            }

            $value = $query->max($column);

            return $value !== null ? Carbon::parse($value) : null;
        };

        $lastmods = [
            'course' => $max(Course::class, fn ($q) => $q->where('status', 'published')),
            'product' => $max(Product::class, fn ($q) => $q->where('status', 'published')->where('visibility', 'public')),
            'competition' => $max(Competition::class, fn ($q) => $q->where('status', '!=', CompetitionStatus::Archived->value)),
            /*
             * Trang Luyện tập giờ chạy lối "luyện theo câu" (SỬA 24/8) nên mốc sửa phải lấy từ
             * KHO CÂU HỎI công khai — đúng bộ lọc của QuestionRepository::idsForPractice() —
             * chứ không phải từ bảng đề. Lấy thêm mốc của đề luyện tập rồi so lấy cái mới hơn,
             * vì phần "đề" tuy đang ẩn ở view nhưng vẫn là nội dung thật của trang đó.
             */
            'practice' => collect([
                $max(Question::class, fn ($q) => $q->where('status', 'published')
                    ->whereNull('product_id')
                    ->whereIn('type', ['mcq', 'fill_blank', 'coding', 'composite'])),
                $max(Assessment::class, fn ($q) => $q->where('type', 'practice')->where('status', 'published')),
            ])->filter()->max(),
            'leaderboard' => $max(LeaderboardEntry::class, null, 'computed_at'),
            'teacher' => $max(TeacherProfile::class, fn ($q) => $q->where('approval_status', TeacherApprovalStatus::Approved->value)),
        ];

        /*
         * Trang Thông tin và 3 trang chính sách có nội dung nằm CỨNG TRONG MÃ NGUỒN
         * (InfoService), không có bản ghi nào trong CSDL để lấy mốc. Dùng thời điểm sửa file
         * đó — đây đúng là lúc nội dung thay đổi thật, chứ không phải con số bịa ra. Không đọc
         * được file (đã nén mã, quyền hạn...) thì bỏ trống, sitemap vẫn hợp lệ.
         */
        $infoMtime = @filemtime(app_path('Services/Public/InfoService.php'));
        $lastmods['info'] = $infoMtime !== false ? Carbon::createFromTimestamp($infoMtime) : null;

        // Trang chủ liệt kê mọi thứ nên lấy mốc mới nhất trong tất cả.
        $lastmods['any'] = collect($lastmods)->filter()->max();

        return $lastmods;
    }
}
