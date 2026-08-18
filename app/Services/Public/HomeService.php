<?php

namespace App\Services\Public;

use App\Repositories\Contracts\ClassEnrollmentRepositoryInterface;
use App\Repositories\Contracts\ClassRoomRepositoryInterface;
use App\Repositories\Contracts\CourseRepositoryInterface;
use App\Repositories\Contracts\RatingSummaryRepositoryInterface;
use App\Repositories\Contracts\TeacherProfileRepositoryInterface;

/**
 * home (PUB-01/02, 12.1: hero → lộ trình → năng lực chấm → khóa/tài liệu nổi bật → cuộc thi
 * → giáo viên tiêu biểu → cam kết/FAQ) — trước đây route 'home' là closure
 * (Route::get('/', fn () => view('welcome'))) và welcome.blade.php TỰ khai báo mảng dữ liệu
 * minh họa cứng ngay trong view — không có Controller/Service thật nào đứng sau.
 *
 * "Nổi bật" trên trang chủ = TOP N của chính danh mục công khai thật (tái dùng lại đúng
 * CourseService/MaterialService/CompetitionService/TeacherService đã có cho từng trang con),
 * KHÔNG phải một tập dữ liệu ảo riêng cho trang chủ — để tránh trang chủ hứa hẹn nội dung mà
 * trang con thật không có, và tránh phải bảo trì luật lọc (published/public/is_featured...)
 * ở 2 nơi cùng lúc.
 */
class HomeService
{
    /** Số thẻ hiển thị cho mỗi khối "nổi bật" trên trang chủ (khóa học/tài liệu/giáo viên). */
    private const FEATURED_LIMIT = 4;

    /** "Cuộc thi sắp tới" chỉ cần 2 thẻ vì khối này đã có nền tối chiếm nhiều diện tích. */
    private const UPCOMING_COMPETITIONS_LIMIT = 2;

    public function __construct(
        private readonly CourseService $courseService,
        private readonly MaterialService $materialService,
        private readonly CompetitionService $competitionService,
        private readonly TeacherService $teacherService,
        private readonly CourseRepositoryInterface $courses,
        private readonly ClassRoomRepositoryInterface $classRooms,
        private readonly ClassEnrollmentRepositoryInterface $classEnrollments,
        private readonly TeacherProfileRepositoryInterface $teacherProfiles,
        private readonly RatingSummaryRepositoryInterface $ratingSummaries,
    ) {}

    public function indexData(): array
    {
        return [
            'stats' => $this->buildStats(),
            // CourseService::indexData(null) đã trả đúng hình dạng thẻ (id/title/meta/
            // average/count) mà <x-card-item> cần, sắp mới nhất trước — lấy $limit đầu làm
            // "nổi bật" (khóa học vừa phát hành gần đây nhất).
            'featuredCourses' => array_slice($this->courseService->indexData(null)['courses'], 0, self::FEATURED_LIMIT),
            'featuredMaterials' => $this->materialService->featuredData(self::FEATURED_LIMIT),
            'upcomingCompetitions' => $this->competitionService->upcomingData(self::UPCOMING_COMPETITIONS_LIMIT),
            'featuredTeachers' => $this->teacherService->featuredData(self::FEATURED_LIMIT),
            'faqs' => $this->faqs(),
        ];
    }

    /**
     * 4 số liệu ở hero (12.1) — TÍNH TRỰC TIẾP từ dữ liệu thật, thay cho 4 chuỗi cố định
     * ('12.000+', '350+', '120+', '4.8/5') trong bản cũ.
     *
     * - "Học sinh đang học": đếm SỐ HỌC SINH KHÁC NHAU đang có ít nhất 1 ghi danh active
     *   (distinct student_id) — không đếm theo tổng số tài khoản role=student, vì một tài
     *   khoản có thể chưa từng vào lớp nào.
     * - "Giáo viên đã duyệt": TeacherProfileRepositoryInterface::countApproved() — đúng bằng
     *   con số đã hiển thị ở admin.featured-teachers.index nên không lệch số giữa 2 màn.
     * - "Khóa học & lớp": Course đã phát hành (status=published) + ClassRoom đang hoạt động
     *   (status=active) — gộp lại vì trang chủ chỉ cần 1 con số chung, không tách 2 khối.
     * - "Đánh giá trung bình": trung bình CÓ TRỌNG SỐ theo review_count trên TOÀN BỘ
     *   RatingSummary (mọi loại đối tượng: material/class_room/teacher/competition), cùng
     *   công thức aggregate() đã dùng ở CourseService — chỉ khác là tính cho CẢ nền tảng
     *   thay vì 1 khóa/lớp cụ thể.
     *
     * Để PUBLIC (không private) vì App\Services\Public\InfoService (trang Thông tin) dùng
     * lại ĐÚNG 4 số liệu này ở mục "Giới thiệu" — 1 nguồn tính duy nhất, tránh 2 trang hiện
     * 2 con số lệch nhau cho cùng một nền tảng.
     */
    public function buildStats(): array
    {
        $activeStudents = $this->classEnrollments->query()
            ->where('status', 'active')
            ->distinct()
            ->count('student_id');

        $approvedTeachers = $this->teacherProfiles->countApproved();

        $publishedCourses = $this->courses->query()->where('status', 'published')->count();
        $activeClassRooms = $this->classRooms->query()->where('status', 'active')->count();

        $ratingRow = $this->ratingSummaries->query()
            ->selectRaw('SUM(avg_rating * review_count) as weighted, SUM(review_count) as total')
            ->first();
        $platformAverage = ($ratingRow !== null && (int) $ratingRow->total > 0)
            ? round($ratingRow->weighted / $ratingRow->total, 1)
            : null;

        return [
            ['value' => $this->countLabel($activeStudents), 'label' => 'Học sinh đang học'],
            ['value' => $this->countLabel($approvedTeachers), 'label' => 'Giáo viên đã duyệt'],
            ['value' => $this->countLabel($publishedCourses + $activeClassRooms), 'label' => 'Khóa học & lớp'],
            ['value' => $platformAverage !== null ? number_format($platformAverage, 1).'/5' : '—', 'label' => 'Đánh giá trung bình'],
        ];
    }

    /**
     * "12.000+" khi >= 1.000 (làm tròn xuống hàng trăm gần nhất — không cam kết đúng
     * TỪNG NGƯỜI mỗi lần tải lại trang, chỉ là một mốc tăng trưởng); số nhỏ hơn hiện
     * nguyên kèm dấu "+". Hệ thống mới/số liệu = 0 hiện "—" thay vì "0" — tránh trang chủ
     * trông như lỗi/trống dữ liệu khi thực chất chỉ là chưa có ai (nhất quán với cách
     * "Đánh giá trung bình" đã xử lý ngay bên dưới).
     */
    private function countLabel(int $count): string
    {
        if ($count <= 0) {
            return '—';
        }

        if ($count >= 1000) {
            return number_format((int) (floor($count / 100) * 100)).'+';
        }

        return ((string) $count).'+';
    }

    /** Nội dung FAQ (12.1 mục 9) — thông tin chính sách tĩnh, không phải dữ liệu nghiệp vụ cần truy vấn. */
    private function faqs(): array
    {
        return [
            ['q' => 'Bài công khai có cần đăng nhập không?', 'a' => 'Khách xem được; cần đăng nhập để bắt đầu, nộp bài và lưu kết quả.'],
            ['q' => 'Quyền học và quyền dạy khác nhau thế nào?', 'a' => 'Quyền dạy của giáo viên không tự cấp quyền học cho học sinh, và ngược lại — mỗi quyền có phạm vi và thời hạn riêng, luôn hiển thị rõ trên từng học liệu.'],
            ['q' => 'Vì sao một bài học lại bị khóa?', 'a' => 'Hệ thống luôn nêu đúng lý do: thiếu quyền học liệu, giáo viên chưa mở theo tiến độ lớp, hoặc quyền đã hết hạn — không khóa mà không giải thích.'],
            ['q' => 'Chấm bài code (OJ) hoạt động thế nào?', 'a' => 'Bài code được chấm bằng bộ test/luật rõ ràng, kế thừa năng lực từ Quinhdao OJ — không gọi là "AI chấm" khi hệ thống thực chất dùng luật/test case.'],
        ];
    }
}
