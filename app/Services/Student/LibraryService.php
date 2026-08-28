<?php

namespace App\Services\Student;

use App\Enums\AccessScope;
use App\Enums\ProductType;
use App\Models\AccessRight;
use App\Models\Material;
use App\Models\Product;
use App\Models\User;
use App\Repositories\Contracts\AccessRightRepositoryInterface;
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

    public function __construct(private AccessRightRepositoryInterface $accessRights) {}

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

        $owned = $this->ownedProducts($user);

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
            ->get(['id', 'product_id', 'parent_id', 'title', 'pdf_path'])
            ->groupBy('product_id');

        $products = $productsForTab->map(fn (Product $p) => [
            'id' => $p->id,
            'title' => $p->title,
            'coverPath' => $p->cover_image_path,
            'toc' => $this->buildTocTree($materialsByProduct->get($p->id, collect()), null),
            'resources' => $this->resources($p, $includeGuide),
        ])->all();

        return ['tabs' => $tabs, 'products' => $products];
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
     * @return Collection<int, Product>
     */
    private function ownedProducts(User $user): Collection
    {
        return $this->accessRights->forUserWithProduct($user->id)
            ->filter(fn (AccessRight $ar) => in_array($ar->scope, [AccessScope::PersonalLearning, AccessScope::TeacherTeaching], true)
                && $ar->isCurrentlyActive()
                && $ar->product !== null)
            ->pluck('product')
            ->unique('id')
            ->values();
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
