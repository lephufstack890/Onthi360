# Module Bộ đề (18/10)

Bộ đề là tài liệu loại **Bộ đề** (`products.type = exam`) mà người dùng **phải mua**. Bộ đề **không chứa bản sao đề**: nó trỏ tới các **đề luyện tập đã phát hành** (`assessments.type = practice`). Đề công khai vẫn làm miễn phí ở trang Luyện tập.

## Cách tính danh sách đề (`ExamBundleService::items()`)

```
(đề khớp bộ lọc products.bundle_filters)      ← bỏ qua khi bộ đề đang "Khoá danh sách"
+ (đề ghim tay: product_assessments.source = manual)
− (đề đã bấm "Bỏ khỏi bộ đề": excluded = 1)
→ xếp: sort_order tay → năm học mới trước → tỉnh A–Z → đề mới trước
```

Danh sách được tính lại mỗi lần xem, nên không cần đồng bộ hay cron:
- đề mới phát hành mà khớp bộ lọc thì tự vào bộ;
- đề bị gỡ phát hành hoặc xoá thì tự ra khỏi bộ.

**Khoá danh sách** chụp danh sách hiện tại vào bảng `product_assessments`, rồi ngừng áp bộ lọc.

## Dữ liệu (migration `2025_01_01_001240_create_exam_bundle_tables`)

| Cột / bảng | Ý nghĩa |
|---|---|
| `products.bundle_filters` (json) | `{categories[], levels[], provinces[], year_from, year_to}`, xem `App\Support\ExamBundleFilters` |
| `products.bundle_locked` (bool) | Khoá danh sách |
| `assessments.bundle_only` (bool) | "Chỉ trong bộ đề": ẩn khỏi Luyện tập, chỉ người mua bộ đề làm được |
| `product_assessments` | Đề ghim tay, đề bị loại, thứ tự tay |

Mọi cột có mặc định "như cũ", nên migrate xong không có gì thay đổi cho tới khi admin thiết lập bộ đề.

## Tệp của module

| Tệp | Vai trò |
|---|---|
| `app/Services/ExamBundle/ExamBundleService.php` | **Toàn bộ logic**: tính danh sách, thao tác admin, quyền, gói ZIP |
| `app/Support/ExamBundleFilters.php` | Bộ lọc 4 tiêu chí (muốn thêm tiêu chí thì sửa ở đây) |
| `app/Models/ProductAssessment.php` | Bảng `product_assessments` |
| `app/Http/Controllers/Admin/ExamBundleController.php` | Thao tác admin (routes `admin.products.bundle.*`, `admin.content.assessments.bundle.*`) |
| `app/Http/Controllers/Public/ExamBundleController.php` | Trang `/bo-de/{id}` (route `bundles.show`) |
| `resources/views/admin/products/partials/exam-bundle.blade.php` | Khối "Đề trong bộ đề" ở trang chi tiết tài liệu |
| `resources/views/admin/content/partials/assessment-bundle-box.blade.php` | Ô "Bộ đề" ở trang chi tiết đề luyện tập |
| `resources/views/public/bundles/show.blade.php` | Trang bộ đề cho người xem |
| `resources/views/partials/bundle-cta.blade.php` | Nút "Xem danh sách đề" ở trang chi tiết tài liệu |

## Chỗ nối vào code cũ (mỗi chỗ 1–3 dòng, có ghi chú `SỬA 18/10 — MODULE BỘ ĐỀ`)

- `routes/web.php`: các route của module.
- `Product` / `Assessment` model: thêm casts.
- `admin/products/show`, `admin/content/show`, `public/materials/show`: thêm 1 dòng `@include`.
- `Public\PracticeService::examBaseRows()`: ẩn đề `bundle_only` khỏi Luyện tập.
- `Student\AssessmentService::buildTakeData()`: `assertCanTake()` chặn làm đề `bundle_only` khi chưa mua.
- `ExamZipImportService::importPracticeForAdmin()`: đọc `exam.json` → `"visibility": "bundle_only"`, `"bundles": ["slug hoặc id bộ đề"]`.

## Quyền (`ExamBundleService::canUseBundle()`)

Có quyền dùng bộ đề khi người dùng có quyền cá nhân còn hạn, hoặc thuộc lớp được gắn cả tài liệu, hoặc là admin. Hiển thị "Công khai" của tài liệu **không** tính là đã mua.

Đề `bundle_only` làm được khi người dùng có quyền ở một bộ đề chứa đề đó, hoặc được giao đề, hoặc là admin.

## Gỡ module

1. Xoá các dòng `@include` / route / hook ở trên.
2. Chạy `php artisan migrate:rollback --step=1` (nếu đây là migration cuối).
