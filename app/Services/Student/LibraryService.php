<?php

namespace App\Services\Student;

use App\Enums\AccessScope;
use App\Enums\ContentStatus;
use App\Enums\ProductType;
use App\Models\AccessRight;
use App\Models\Material;
use App\Models\Product;
use App\Models\Question;
use App\Models\User;
use App\Repositories\Contracts\AccessRightRepositoryInterface;
use App\Services\AccessGateService;
use Illuminate\Support\Collection;

/**
 * "Tài liệu của tôi" (28/8, dùng chung cho cả học sinh VÀ giáo viên — SỬA 28/8 (2) "bên giáo
 * viên cũng xem tài liệu giống như học sinh, chỉ khác được xem thêm file hướng dẫn"). Trước
 * đây Mục lục + Tài nguyên đính kèm hiện NGAY trên trang tài liệu công khai
 * (public/materials/show) — theo yêu cầu khách, 2 khối đó đã bị ẩn khỏi trang public (không
 * xoá dữ liệu, xem MaterialService::showData()) và giờ CHỈ xem được ở khu vực riêng của mỗi
 * vai trò (student.library.index / teacher.library.index), SAU KHI đã đăng nhập + đã
 * mua/kích hoạt.
 *
 * 3 tab Sách/Chuyên đề/Bộ đề y hệt trang công khai (App\Services\Public\MaterialService) —
 * type=course thuộc Khóa học, không thuộc "Tài liệu" (4.3).
 *
 * Nằm ở namespace Student vì được viết cho student.library.index trước — Teacher\LibraryController
 * TÁI DÙNG nguyên class này (cùng cách MaterialReadService đã dùng chung cho cả
 * Student\MaterialController lẫn Teacher\MaterialController), không tạo bản sao thứ hai.
 *
 * "Trừ file hướng dẫn" cho học sinh (yêu cầu 28/8): $includeGuide=false (mặc định, dùng cho
 * học sinh) khiến resources() CỐ Ý không đưa 'guide' vào danh sách trả về — học sinh không
 * thấy link, không biết file đó tồn tại. Giáo viên gọi với $includeGuide=true thì có thêm
 * mục "PDF hướng dẫn". Luật THẬT (chặn kể cả gọi thẳng route, không chỉ ẩn UI) nằm ở
 * App\Services\Access\AccessService::downloadResource() — vẫn kiểm tra lại theo vai trò
 * (student) ở đó, không tin riêng cờ $includeGuide này.
 */
class LibraryService
{
    /** tab (?tab=) -> ProductType — khớp 3 tab như trang công khai. */
    private const TABS = [
        'sach' => ProductType::Book,
        'chuyen-de' => ProductType::Topic,
        'de-thi' => ProductType::Exam,
    ];

    public function __construct(
        private AccessRightRepositoryInterface $accessRights,
        // SỬA 31/8 (khách yêu cầu — "gắn cả sản phẩm vào lớp, học sinh thuộc lớp xem được"):
        // dùng classGrantedProducts() để gộp thêm sản phẩm được cấp quyền MIỄN PHÍ qua lớp
        // vào danh sách "đang sở hữu", xem ownedProducts() bên dưới.
        private AccessGateService $accessGate,
    ) {}

    /**
     * @param  string  $routeName  Tên route trang này của ĐÚNG vai trò đang gọi — 'student.library.index'
     *                             hoặc 'teacher.library.index' — dùng để dựng href cho 3 tab.
     * @param  bool  $includeGuide  true (giáo viên) = có thêm mục "PDF hướng dẫn"; false (học
     *                               sinh, mặc định) = KHÔNG có mục này — xem ghi chú ở đầu class.
     * @return array{tabs: array, products: array}
     */
    public function indexData(User $user, string $tab, string $routeName, bool $includeGuide = false): array
    {
        $type = self::TABS[$tab] ?? self::TABS['sach'];

        // SỬA 18/9 — gọi kho quyền ĐÚNG MỘT LẦN rồi dùng chung cho cả danh sách sản phẩm lẫn
        // nhãn hạn dùng in trên thẻ. Trước đây ownedProducts() tự gọi repo rồi vứt AccessRight
        // đi, muốn biết "còn bao nhiêu ngày" sẽ phải truy vấn lần thứ hai cho cùng một dữ liệu.
        $rights = $this->accessRights->forUserWithProduct($user->id);

        $owned = $this->ownedProducts($user, $rights);
        $remainingByProduct = $this->remainingLabels($rights);

        $tabs = [
            ['label' => '📘 Sách', 'href' => route($routeName, ['tab' => 'sach']), 'active' => $tab === 'sach', 'count' => $owned->where('type', ProductType::Book)->count()],
            ['label' => '🗂️ Chuyên đề', 'href' => route($routeName, ['tab' => 'chuyen-de']), 'active' => $tab === 'chuyen-de', 'count' => $owned->where('type', ProductType::Topic)->count()],
            ['label' => '📝 Bộ đề', 'href' => route($routeName, ['tab' => 'de-thi']), 'active' => $tab === 'de-thi', 'count' => $owned->where('type', ProductType::Exam)->count()],
        ];

        $productsForTab = $owned->where('type', $type)->values();

        // Mục lục (đọc chương/bài — student|teacher.materials.read/file) cho TỪNG sản phẩm đã
        // mua ở tab này — 1 câu truy vấn duy nhất, tránh N+1 (mỗi sản phẩm 1 câu nếu lặp trong
        // map() bên dưới). Hiện KHÔNG hiển thị ở Blade (ẩn theo yêu cầu 28/8 (2) "ẩn chỗ mục
        // lục đi"), vẫn tính sẵn ở đây để bật lại nhanh khi cần, không phải viết lại.
        $materialsByProduct = Material::query()
            ->whereIn('product_id', $productsForTab->pluck('id')->all())
            ->orderBy('order')
            // SỬA 18/9 — thêm 'status' vào select: nút "Đọc tài liệu" chỉ được trỏ vào bài ĐÃ
            // PHÁT HÀNH, đúng bộ lọc mà trang đọc dùng để dựng điều hướng bài trước/sau
            // (MaterialReadService::buildReadData: status = Published + pdf_path khác null).
            ->get(['id', 'product_id', 'parent_id', 'title', 'pdf_path', 'status'])
            ->groupBy('product_id');

        // SỬA 31/8 ("ZIP bài tập" gắn vào sản phẩm) — bài tập lập trình đính kèm TỪNG sản phẩm
        // đã mua ở tab này, CHỈ lấy bài đã Published (admin đã bấm "Lưu bài tập" ở
        // ContentService::productExerciseSave() — bản Nháp mới đọc từ ZIP chưa xong không bao
        // giờ lọt ra đây, dù có lỡ chưa kịp bị discardAbandonedDraftsFor() quét dọn). 1 câu
        // truy vấn cho cả trang, cùng cách tránh N+1 với $materialsByProduct ở trên.
        $exercisesByProduct = Question::query()
            ->whereIn('product_id', $productsForTab->pluck('id')->all())
            ->where('status', ContentStatus::Published->value)
            ->orderByDesc('id')
            // SỬA 31/8 (2) — thêm cột 'type' vào select: exercisesFor() bên dưới giờ gọi
            // Question::exerciseSummaryLabel() (match theo $this->type) thay vì luôn đọc
            // 'test_cases' cứng — thiếu cột này match() sẽ luôn nhận giá trị cast mặc định sai.
            ->get(['id', 'product_id', 'title', 'points', 'type', 'grading_config'])
            ->groupBy('product_id');

        // SỬA 18/9 (khách: "có nút đọc tài liệu thì nó sẽ hiển thị ra trang đó") — dựng sẵn
        // link vào trang đọc. Tiền tố route suy từ $routeName nên giáo viên ra
        // teacher.materials.read, học sinh ra student.materials.read — không hard-code như
        // lỗi đã mắc ở read.blade.php trước đây.
        $readPrefix = str_starts_with($routeName, 'teacher.') ? 'teacher' : 'student';

        $products = $productsForTab->map(function (Product $p) use ($materialsByProduct, $exercisesByProduct, $includeGuide, $readPrefix, $remainingByProduct) {
            // Bài ĐỌC ĐƯỢC = đã phát hành + có pdf_path — CÙNG bộ lọc với
            // Public\MaterialService::firstReadableMaterialIds() và với danh sách bài mà trang
            // đọc dùng để điều hướng, nên nút không bao giờ dẫn tới một bài trang đọc không nhận.
            // $materialsByProduct đã orderBy('order') nên first() đúng là bài mở đầu.
            $readable = $materialsByProduct->get($p->id, collect())
                ->filter(fn (Material $m) => $m->status === ContentStatus::Published && filled($m->pdf_path));
            $firstReadable = $readable->first();

            return [
                'id' => $p->id,
                'title' => $p->title,
                'coverPath' => $p->cover_image_path,
                'toc' => $this->buildTocTree($materialsByProduct->get($p->id, collect()), null),
                'resources' => $this->resources($p, $includeGuide),
                'exercises' => $this->exercisesFor($exercisesByProduct->get($p->id, collect())),
                // Số bài đọc được + ĐÚNG danh từ của từng loại sản phẩm (Chương/Phần/Đề) —
                // dùng lại ProductType::chapterLabel() đang có, không đặt thêm nhãn mới.
                'lessonCount' => $readable->count(),
                'lessonWord' => mb_strtolower($p->type?->chapterLabel() ?? 'bài'),
                'readHref' => $firstReadable !== null
                    ? route($readPrefix.'.materials.read', $firstReadable->id)
                    : null,
                // Huy hiệu như bản mẫu (MaterialsPage.jsx): "Đã sở hữu" + hạn dùng. Sản phẩm
                // được cấp MIỄN PHÍ qua lớp không có AccessRight cá nhân nên không có trong
                // $remainingByProduct — in đúng nguồn quyền thay vì bịa một con số ngày.
                'access' => [
                    'owned' => true,
                    'remainingLabel' => $remainingByProduct[$p->id] ?? 'Cấp qua lớp học',
                ],
            ];
        })->all();

        // SỬA 31/8 — $includeGuide (true = đang gọi cho giáo viên) TÁI DÙNG làm cờ cho
        // mine.blade.php biết có nên hiện nút "Làm bài" (chỉ học sinh) hay chỉ xem/tải đề bài
        // (giáo viên) ở mục "🧪 Bài tập" — đúng ý nghĩa hiện có của tham số này, không cần thêm
        // cờ riêng.
        return ['tabs' => $tabs, 'products' => $products, 'isTeacherView' => $includeGuide];
    }

    /**
     * SỬA 31/8 (khách yêu cầu — "học sinh xem học liệu NGAY TRONG LỚP (tab Học liệu ở chi
     * tiết lớp), tách hẳn khỏi Tài liệu của tôi — tài liệu tự mua xem ở trang Tài liệu,
     * không liên quan"): dựng đúng 1 "thẻ" tài nguyên+bài tập cho MỘT Product — dùng ở
     * Student\ClassRoomService cho tab "Học liệu" của MỘT lớp cụ thể. Khác indexData() ở
     * trên (liệt kê MỌI sản phẩm đang sở hữu, gộp cả cá nhân lẫn qua lớp, không phân biệt
     * lớp nào) — hàm này chỉ dựng 1 thẻ độc lập cho sản phẩm ĐÃ BIẾT TRƯỚC (đang gắn lớp
     * đó), nên không cần bước "lọc theo lớp" ở đây (ClassRoomService tự lọc trước khi gọi).
     * Tái dùng đúng resources()/exercisesFor() để hiển thị giống hệt "Tài liệu của tôi" —
     * học sinh không bị lẫn 2 cách trình bày khác nhau cho cùng 1 loại dữ liệu.
     * $includeGuide luôn false — trang lớp là của học sinh, không có khái niệm "giáo viên
     * xem PDF hướng dẫn" ở đây (khác teacher.library.index).
     *
     * @return array{id:int,title:string,coverPath:?string,resources:array,exercises:array}
     */
    public function productCard(Product $product): array
    {
        $exercises = Question::query()
            ->where('product_id', $product->id)
            ->where('status', ContentStatus::Published->value)
            ->orderByDesc('id')
            ->get(['id', 'product_id', 'title', 'points', 'type', 'grading_config']);

        return [
            'id' => $product->id,
            'title' => $product->title,
            'coverPath' => $product->cover_image_path,
            'resources' => $this->resources($product, false),
            'exercises' => $this->exercisesFor($exercises),
        ];
    }

    /**
     * Danh sách bài tập (Question, product_id = sản phẩm này) để hiện ở mục "🧪 Bài tập" của
     * mine.blade.php — học sinh có nút "Làm bài" (tái dùng
     * Student\PracticeByQuestionService::startForQuestion()), giáo viên chỉ xem/tải đề bài
     * (KHÔNG có nút "Làm bài" — xem ghi chú ở mine.blade.php).
     *
     * @param  Collection<int, Question>  $exercises
     * @return array<int, array{id:int,title:string,points:int,summary:string}>
     */
    private function exercisesFor(Collection $exercises): array
    {
        // SỬA 31/8 (2, "mở rộng ZIP bài tập" nhiều dạng câu/nhiều môn) — 'testCasesCount' (chỉ
        // đúng cho Lập trình) đổi thành 'summary' (Question::exerciseSummaryLabel(), mô tả
        // đúng theo TỪNG dạng: trắc nghiệm/điền đáp án/nhiều phần) vì bài tập giờ có thể là bất
        // kỳ dạng nào trong 4 dạng ZIP hỗ trợ.
        return $exercises->map(fn (Question $q) => [
            'id' => $q->id,
            'title' => $q->title,
            'points' => $q->points,
            'summary' => $q->exerciseSummaryLabel(),
        ])->values()->all();
    }

    /**
     * Tài nguyên đính kèm — content (PDF nội dung chính), exercise (ZIP bài tập), media (ảnh
     * động/audio) luôn có; 'guide' (PDF hướng dẫn) CHỈ thêm khi $includeGuide=true (giáo viên)
     * — xem ghi chú ở đầu class.
     *
     * @return array<int, array{kind:string,icon:string,label:string}>
     */
    private function resources(Product $product, bool $includeGuide): array
    {
        $items = collect([
            ['kind' => 'content', 'icon' => '📄', 'label' => 'File PDF', 'present' => filled($product->content_pdf_path)],
            ['kind' => 'exercise', 'icon' => '🗂️', 'label' => 'ZIP bài tập', 'present' => filled($product->exercise_zip_path)],
            ['kind' => 'media', 'icon' => '🎬', 'label' => 'Học liệu (ảnh động/audio)', 'present' => filled($product->media_path)],
        ]);

        if ($includeGuide) {
            $items->push(['kind' => 'guide', 'icon' => '📘', 'label' => 'PDF hướng dẫn', 'present' => filled($product->guide_pdf_path)]);
        }

        return $items->filter(fn ($r) => $r['present'])->map(fn ($r) => [
            'kind' => $r['kind'], 'icon' => $r['icon'], 'label' => $r['label'],
        ])->values()->all();
    }

    /**
     * Sản phẩm ĐANG SỞ HỮU (quyền còn hiệu lực) của $user — cùng luật với
     * App\Services\Public\MaterialService::ownedProductIds() (2 scope PersonalLearning +
     * TeacherTeaching, AccessRight::isCurrentlyActive()), khác ở chỗ cần CẢ Product (không
     * chỉ id) để nhóm theo type và dựng thẻ hiển thị.
     *
     * SỬA 31/8 (khách yêu cầu — "gắn cả sản phẩm vào lớp, học sinh thuộc lớp xem được miễn
     * phí"): gộp thêm sản phẩm được cấp qua lớp (AccessGateService::classGrantedProducts() —
     * lớp $user đang là thành viên active có gắn active sản phẩm đó), KHÔNG đòi $user phải
     * tự có AccessRight cho sản phẩm này. Sau khi gộp, sản phẩm hiện y hệt sản phẩm tự mua ở
     * "Tài liệu của tôi" — dùng lại nguyên resources()/exercisesFor() bên dưới, không viết
     * nhánh hiển thị riêng.
     *
     * @return Collection<int, Product>
     */
    private function ownedProducts(User $user, Collection $rights): Collection
    {
        $personal = $rights
            ->filter(fn (AccessRight $ar) => in_array($ar->scope, [AccessScope::PersonalLearning, AccessScope::TeacherTeaching], true)
                && $ar->isCurrentlyActive()
                && $ar->product !== null)
            ->pluck('product');

        return $personal->merge($this->accessGate->classGrantedProducts($user))
            ->unique('id')
            ->values();
    }

    /**
     * SỬA 18/9 — nhãn hạn dùng của TỪNG sản phẩm, in ở huy hiệu thứ hai trên thẻ (giống
     * MaterialAccessBadges của bản mẫu: "Đã sở hữu" + "Còn N ngày").
     *
     * Dùng lại ĐÚNG cách tính của Student\MaterialReadService::accessChip() để hai màn không
     * bao giờ nói khác nhau: chỉ quyền còn hiệu lực, vĩnh viễn (expires_at = null) thắng mọi
     * hạn, còn lại giữ hạn XA NHẤT. Không truy vấn thêm — $rights là kết quả đã nạp ở
     * indexData().
     *
     * @param  Collection<int, AccessRight>  $rights
     * @return array<int, string>  product_id => nhãn
     */
    private function remainingLabels(Collection $rights): array
    {
        /** @var array<int, int|null> $best product_id => null (vĩnh viễn) | số ngày còn lại */
        $best = [];

        foreach ($rights as $right) {
            if ($right->product_id === null
                || ! in_array($right->scope, [AccessScope::PersonalLearning, AccessScope::TeacherTeaching], true)
                || ! $right->isCurrentlyActive()) {
                continue;
            }

            if (array_key_exists($right->product_id, $best) && $best[$right->product_id] === null) {
                continue; // đã có quyền vĩnh viễn — không hạn nào thắng được nữa
            }

            if ($right->expires_at === null) {
                $best[$right->product_id] = null;

                continue;
            }

            $days = max(0, (int) now()->diffInDays($right->expires_at, false));

            if (! array_key_exists($right->product_id, $best) || $days > $best[$right->product_id]) {
                $best[$right->product_id] = $days;
            }
        }

        return array_map(
            fn (?int $days) => $days === null ? 'Không giới hạn' : 'Còn '.$days.' ngày',
            $best
        );
    }

    /**
     * Y hệt App\Services\Public\MaterialService::buildTocTree() (đã private ở đó, không tiện
     * dùng chung) — dựng cây Mục lục đa cấp từ danh sách Material PHẲNG của 1 sản phẩm.
     *
     * @param  Collection<int, Material>  $materials
     * @return array<int, array{id:int,title:string,hasContent:bool,children:array}>
     */
    private function buildTocTree(Collection $materials, ?int $parentId): array
    {
        return $materials
            ->where('parent_id', $parentId)
            ->map(fn (Material $m) => [
                'id' => $m->id,
                'title' => $m->title,
                'hasContent' => $m->pdf_path !== null,
                'children' => $this->buildTocTree($materials, $m->id),
            ])
            ->values()
            ->all();
    }
}
