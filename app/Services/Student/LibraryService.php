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
 * "Tài liệu của tôi" (28/8) — student.library.index. Trước đây Mục lục + Tài nguyên đính
 * kèm hiện NGAY trên trang tài liệu công khai (public/materials/show) — theo yêu cầu khách,
 * 2 khối đó đã bị ẩn khỏi trang public (không xoá dữ liệu, xem MaterialService::showData())
 * và giờ CHỈ xem được ở đây, SAU KHI học sinh đã đăng nhập + đã mua/kích hoạt.
 *
 * 3 tab Sách/Chuyên đề/Bộ đề y hệt trang công khai (App\Services\Public\MaterialService) —
 * type=course thuộc Khóa học, không thuộc "Tài liệu" (4.3).
 *
 * "Trừ file hướng dẫn" (yêu cầu 28/8): resources() ở đây CỐ Ý không đưa 'guide' vào danh
 * sách trả về — học sinh không thấy link, không biết file đó tồn tại. Luật THẬT (chặn kể cả
 * gọi thẳng route) nằm ở App\Services\Access\AccessService::downloadResource(), đây chỉ là
 * không hiển thị, tránh học sinh bấm vào rồi dính 403 khó hiểu.
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

    /** @return array{tabs: array, products: array} */
    public function indexData(User $user, string $tab): array
    {
        $type = self::TABS[$tab] ?? self::TABS['sach'];

        $owned = $this->ownedProducts($user);

        $tabs = [
            ['label' => '📘 Sách', 'href' => route('student.library.index', ['tab' => 'sach']), 'active' => $tab === 'sach', 'count' => $owned->where('type', ProductType::Book)->count()],
            ['label' => '🗂️ Chuyên đề', 'href' => route('student.library.index', ['tab' => 'chuyen-de']), 'active' => $tab === 'chuyen-de', 'count' => $owned->where('type', ProductType::Topic)->count()],
            ['label' => '📝 Bộ đề', 'href' => route('student.library.index', ['tab' => 'de-thi']), 'active' => $tab === 'de-thi', 'count' => $owned->where('type', ProductType::Exam)->count()],
        ];

        $productsForTab = $owned->where('type', $type)->values();

        // Mục lục (đọc chương/bài — student.materials.read/file) cho TỪNG sản phẩm đã mua ở
        // tab này — 1 câu truy vấn duy nhất, tránh N+1 (mỗi sản phẩm 1 câu nếu lặp trong map()
        // bên dưới).
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
            'resources' => $this->resources($p),
        ])->all();

        return ['tabs' => $tabs, 'products' => $products];
    }

    /**
     * Tài nguyên đính kèm học sinh ĐƯỢC XEM — content (PDF nội dung chính), exercise (ZIP bài
     * tập), media (ảnh động/audio). 'guide' (PDF hướng dẫn) CỐ Ý không có trong danh sách này
     * — xem ghi chú ở đầu class.
     *
     * @return array<int, array{kind:string,icon:string,label:string}>
     */
    private function resources(Product $product): array
    {
        return collect([
            ['kind' => 'content', 'icon' => '📄', 'label' => 'File PDF', 'present' => filled($product->content_pdf_path)],
            ['kind' => 'exercise', 'icon' => '🗂️', 'label' => 'ZIP bài tập', 'present' => filled($product->exercise_zip_path)],
            ['kind' => 'media', 'icon' => '🎬', 'label' => 'Học liệu (ảnh động/audio)', 'present' => filled($product->media_path)],
        ])->filter(fn ($r) => $r['present'])->map(fn ($r) => [
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
