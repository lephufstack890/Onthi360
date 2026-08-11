# Cài đặt & kiểm tra base — làm theo đúng thứ tự

Base này được viết trong môi trường không có PHP kết nối trực tiếp tới đúng
project của anh, nên **chưa được chạy thử thật**. Trước khi viết thêm code
mới lên trên base này, hãy chạy đúng checklist dưới để tự xác nhận mọi thứ ăn
khớp — nếu có lỗi (thường chỉ là lỗi nhỏ do khác version), báo lại để sửa
ngay.

## 0. Sao lưu trước khi copy đè

```bash
cd ~/Desktop/onthi360
git init && git add -A && git commit -m "trước khi thêm base mới" # nếu chưa có git
```

## 1. Giải nén / copy các file mới vào project

Toàn bộ file trong `onthi360-base.zip` map 1:1 vào project hiện tại của anh
(cùng cấu trúc `app/`, `database/`, `routes/`, `docs/`, `docker/`,
`docker-compose.yml`). Giải nén và cho phép **ghi đè** các file cùng tên
(chỉ 2 file default bị ghi đè: `app/Models/User.php`, `database/seeders/
DatabaseSeeder.php`, `routes/web.php` — cả 3 đều chưa có logic thật nên an
toàn để đè).

**Base này không thêm package composer/npm nào** — không cần chạy
`composer update`/`npm install` vì lý do dependency mới. Nếu `composer
install` báo thiếu gì đó thì đó là vấn đề môi trường máy anh, không phải do
base này.

## 2. Cấu hình MySQL

```bash
# copy các dòng cần thiết từ .env.example.additions vào .env thật
# (đổi DB_CONNECTION=sqlite -> mysql, điền DB_DATABASE/DB_USERNAME/DB_PASSWORD)
```

Nếu dùng Docker: `docker compose up -d mysql redis` rồi trỏ `.env` vào
`DB_HOST=127.0.0.1` (cổng đã map ra ngoài ở `docker-compose.yml`).

Nếu dùng MySQL cài trực tiếp trên máy, tạo database + user:

```sql
CREATE DATABASE onthi360 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'onthi360'@'localhost' IDENTIFIED BY 'secret';
GRANT ALL PRIVILEGES ON onthi360.* TO 'onthi360'@'localhost';
FLUSH PRIVILEGES;
```

## 3. Đăng ký middleware role (1 dòng trong bootstrap/app.php)

Mở `bootstrap/app.php`, tìm khối `->withMiddleware(function (Middleware
$middleware) { ... })` (đã có sẵn nhưng rỗng trong skeleton mặc định), thêm:

```php
$middleware->alias([
    'role' => \App\Http\Middleware\EnsureHasRole::class,
]);
```

## 4. Chạy migration + seed + test — đây là bước quan trọng nhất

```bash
php artisan migrate:fresh --seed
php artisan test
```

**Kỳ vọng:** migration chạy hết ~36 file không lỗi, seed tạo xong 6 role +
(ở môi trường `local`) dữ liệu demo, và toàn bộ 5 file test trong
`tests/Feature/*Test.php` PASS (khoảng 20 test case, cover đúng 5 luật nghiệp
vụ quan trọng nhất — xem `docs/ARCHITECTURE.md` mục 4).

Nếu một test fail, đọc kỹ message — phần lớn khả năng là do khác biệt nhỏ
giữa Laravel 13 thật và kiến thức của em tại thời điểm viết (ví dụ tên method
đổi), sửa 1-2 dòng là xong, không phải lỗi thiết kế.

## 5. Đăng nhập thử với dữ liệu demo (chỉ ở local)

Sau `--seed`, có 3 user demo (xem `DemoDataSeeder`):
`admin@onthi360.test`, `teacher@onthi360.test`, `student@onthi360.test` —
password là password ngẫu nhiên do `UserFactory` tạo, dùng
`php artisan tinker` rồi `User::where('email','teacher@onthi360.test')
->first()->update(['password' => 'password'])` để đặt lại nếu cần đăng nhập
tay qua UI (UI đăng nhập thật chưa được viết trong base này).

## 6. Việc CHƯA có trong base này (cần làm tiếp)

- Route/Controller/Blade view thật cho từng màn hình (base chỉ có khung
  `routes/web.php` với TODO comment).
- Judge/runner chấm code (bảng `judge_submissions` đã sẵn, cần viết job dispatch
  + worker sandbox riêng — xem `docs/ARCHITECTURE.md`).
- OCR worker thật (bảng `uploaded_documents`/`ocr_results`/`draft_questions` đã
  sẵn, cần chọn engine OCR — mục 18.7 của spec).
- UI admin (gợi ý Filament — xem bảng "khi nào nên thay bằng package ngoài"
  trong ARCHITECTURE.md).
