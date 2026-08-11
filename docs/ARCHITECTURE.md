# Kiến trúc base — Ôn Thi 360

Tài liệu này giải thích các quyết định kỹ thuật của phần "base" được dựng thêm
vào skeleton Laravel 13 gốc, để bất kỳ dev nào tham gia sau cũng hiểu **vì sao**
code được tổ chức như vậy, không chỉ **cái gì** đã được viết. Mọi tham chiếu số
chương (ví dụ "7.3") là tới file `onthi360figmaspec_1.md` (bản đặc tả BA).

## 1. Nguyên tắc chọn công nghệ cho base này

1. **Không thêm dependency nào chưa được kiểm chứng chạy được trong môi trường
   thật của team.** Toàn bộ code trong base này chỉ dùng `laravel/framework`
   (đã có sẵn trong `composer.json`) — không có `composer require` nào cần
   chạy thêm. Điều này vì lúc dựng base, môi trường build không truy cập được
   packagist.org để tự verify, nên thay vì đưa code "chắc là đúng" cho các gói
   ngoài, base ưu tiên đúng 100% với đúng những gì đã cài.
2. **Business rule tách khỏi controller/UI.** Toàn bộ luật nghiệp vụ khó/dễ
   sai (chương 6, 7, 9) nằm trong `app/Services` và `app/Actions`, có test đi
   kèm. Controller/Blade sau này chỉ gọi vào các class này — khi luật đổi, sửa
   một chỗ, không phải dò từng controller.
3. **String column + PHP enum, không dùng MySQL ENUM.** Xem `app/Enums/*`.
   Thêm một trạng thái mới chỉ cần thêm 1 `case` trong enum PHP, không cần
   `ALTER TABLE ... MODIFY ENUM(...)` — quan trọng khi hệ thống "phát triển lâu
   dài, thay đổi liên tục" như anh Phú yêu cầu.
4. **AccessDecision, không trả bool trần.** Nguyên tắc thiết kế 2.2 mục 1 nói
   UI phải "nêu đúng lý do trước khi kêu gọi hành động". Nếu service trả về
   `bool`, lý do sẽ bị tự chế lại ở tầng UI (dễ lệch nhau giữa các màn). Thay
   vào đó mọi hàm kiểm tra quyền trả về `App\Support\AccessDecision` — có
   `reasonCode`, `message`, `ctaLabel` sẵn.

## 2. Sơ đồ thư mục

```
app/
  Enums/        ~28 enum PHP (trạng thái dùng chung toàn hệ thống)
  Models/       Eloquent model, 1 file/bảng, quan hệ + cast enum
  Services/     Luật nghiệp vụ đa bước, đọc nhiều bảng (AccessGateService, ...)
  Actions/      Luật nghiệp vụ gắn với 1 hành động cụ thể (TeacherAttachMaterialAction)
  Policies/     Ủy quyền theo model (dùng cùng App\Models\Role::hasRole)
  Concerns/     Trait chia sẻ (Auditable)
  Support/      Value object dùng chung (AccessDecision)
  Http/Middleware/EnsureHasRole.php
database/
  migrations/   ~36 file, đặt theo thứ tự FK (xem chú thích trong từng file)
  factories/    Factory cho model cần test
  seeders/      RoleSeeder (luôn chạy) + DemoDataSeeder (chỉ chạy ở local)
tests/Feature/  Test cho từng Service/Action ở trên — xem mục 4
docs/           Tài liệu này + SETUP.md
```

## 3. RBAC & Audit log — tự viết, không dùng package ngoài

**RBAC:** bảng `roles` + `role_user` (many-to-many) + `App\Models\Role` với
hằng số tên role + `User::hasRole()/hasAnyRole()/assignRole()`. Một user có
nhiều role đồng thời (đúng yêu cầu role switcher — 4.3, ví dụ vừa dạy vừa là
phụ huynh).

**Audit log:** bảng `audit_logs` (polymorphic `auditable_type/auditable_id`) +
trait `App\Concerns\Auditable` gắn vào model nhạy cảm (`TeacherProfile`,
`AccessRight`, `Order`, `ActivationCode`, `ClassMaterial`, `ProgressUnlock`,
`Question`, `Assessment`, `Review`, `AttemptAnswer`) — tự động ghi log ở
`created/updated/deleted` qua Eloquent model events. Đáp ứng đúng danh sách ở
16 mục 4.

**Khi nào nên thay bằng package ngoài (không bắt buộc):**

| Nhu cầu phát sinh | Gợi ý | Vì sao chưa dùng ngay |
|---|---|---|
| Permission chi tiết theo action (không chỉ theo role) | `spatie/laravel-permission` | Ma trận quyền 3.2 hiện là theo role cố định, chưa cần permission builder động |
| Cần xem lịch sử audit log đẹp, filter, export | `spatie/laravel-activitylog` | Bảng `audit_logs` tự viết đã đủ dữ liệu; UI xem log có thể build sau bằng Blade/Filament |
| Cần dựng nhanh 6 module admin (ADM-01→06) | `filament/filament` (chạy trên Livewire — hợp với hướng "Blade" đã chọn) | Cần `composer require` + `php artisan filament:install` mà chưa verify được trong lúc dựng base |
| Cần UI tương tác (filter realtime, wizard nhiều bước) không muốn viết JS tay | `livewire/livewire` | Base hiện dùng Blade + controller thuần, đủ cho các luồng CRUD chính; thêm Livewire theo từng màn khi cần, không phải quyết định "tất cả hoặc không gì" |
| Hàng đợi lớn, cần dashboard theo dõi queue (OCR, chấm bài) | `laravel/horizon` (cần Redis) | `QUEUE_CONNECTION=redis` đã chuẩn bị sẵn trong `.env.example.additions`; thêm Horizon khi queue thật sự cần giám sát |

## 4. Luật nghiệp vụ đã cài + test tương ứng

| Class | Luật (tham chiếu spec) | Test |
|---|---|---|
| `AccessGateService::canAccessMaterial()` | 7.1 (phạm vi nội dung) + 7.3 (3 cửa độc lập) | `AccessGateServiceTest` |
| `TeacherAttachMaterialAction` | 7.2 (bảng điều kiện gắn học liệu) | `TeacherAttachMaterialActionTest` |
| `OrderActivationService` | 7.4 (đơn → duyệt → mã → kích hoạt → quyền) | `OrderActivationServiceTest` |
| `QuestionPublishGuard` | 6.2 (điều kiện phát hành câu hỏi) | `QuestionPublishGuardTest` |
| `ReviewEligibilityService` | 9.2 (ai được đánh giá và khi nào) | `ReviewEligibilityServiceTest` |

**Bất biến quan trọng nhất cần giữ khi sửa code sau này** (nếu phá vỡ 1 trong
các điều dưới, rất nhiều màn hình sẽ sai mà không báo lỗi rõ):

- `AccessRight` chỉ được tạo ở `OrderActivationService::activate()` — không tạo
  ở bất kỳ đâu khác trong luồng đơn hàng.
- `class_limit` của `AccessRight` với `scope = teacher_teaching` LUÔN là
  `null` (unlimited) — không có UI nào cho phép giới hạn số lớp của quyền dạy.
- `ClassMaterial` không bao giờ sao chép `Question`/`Assessment` — chỉ tham
  chiếu qua `material_id`/`product_id`.
- Gỡ học liệu (`TeacherAttachMaterialAction::detach()`) đổi status thành
  `removed`, không bao giờ xóa bản ghi — lịch sử OJ/kết quả cũ phải còn truy
  vết được.

## 5. Vì sao chọn MySQL + cấu trúc bảng này cho quy mô vài nghìn → vài trăm nghìn user

- **Không dùng UUID làm khóa chính** — dùng `id` auto-increment (BIGINT) cho
  hiệu năng insert/index tốt hơn ở MySQL khi bảng lớn (đặc biệt `attempts`,
  `attempt_answers` sẽ tăng nhanh nhất). Nếu cần ID không đoán được để lộ ra
  URL công khai (ví dụ mã đơn hàng), dùng thêm cột `code`/`order_no` riêng
  (đã làm ở `orders`, `activation_codes`) thay vì đổi khóa chính.
- **JSON column cho phần hay đổi cấu trúc** (`grading_config`, `metadata`,
  `criteria_scores`, `ranking_rule`) — tránh phải `ALTER TABLE` liên tục khi
  thêm loại câu hỏi/luật xếp hạng mới. MySQL 8+ index được vào JSON qua
  generated column nếu sau này cần query nhanh theo 1 field JSON cụ thể.
- **`rating_summaries` là bảng cache riêng**, không tính `AVG()` trực tiếp từ
  `reviews` mỗi lần hiển thị card — quan trọng khi danh mục Tài liệu/Lớp có
  hàng chục nghìn item và hiển thị đồng thời cho nhiều user.
- **Redis cho cache/session/queue** khi vượt vài chục nghìn user đồng thời —
  driver `database` (mặc định skeleton) sẽ làm bảng `sessions`/`cache`/`jobs`
  trở thành điểm nghẽn ghi. Đã chuẩn bị sẵn trong `.env.example.additions`,
  chỉ cần đổi `.env` khi cần, không phải sửa code.
- **Đọc nhiều / viết ít ở các bảng public** (`products`, `courses`,
  `class_rooms`) — khi cần scale, thêm MySQL read replica và trỏ các query
  chỉ-đọc (trang danh mục, trang chủ) qua connection riêng trong
  `config/database.php` (`'read' => [...]`) mà không cần đổi model/query code
  nếu dùng Eloquent mặc định.
