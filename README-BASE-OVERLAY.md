# Base bổ sung cho project onthi360 (Laravel)

File này (và toàn bộ nội dung trong archive) là phần "base vững chắc" được
dựng thêm vào project Laravel trống anh Phú đã tạo, dựa trên bản đặc tả BA
`onthi360figmaspec_1.md`.

- Đọc `docs/SETUP.md` TRƯỚC — checklist cài đặt + verify từng bước.
- Đọc `docs/ARCHITECTURE.md` để hiểu vì sao code tổ chức như vậy trước khi
  sửa/mở rộng.
- Không có `composer.json`/`package.json` nào bị đổi — base này không thêm
  dependency mới, chạy được ngay với những gì project đã có.
- 3 file bị ghi đè khi giải nén đè lên project cũ: `app/Models/User.php`,
  `database/seeders/DatabaseSeeder.php`, `routes/web.php` (cả 3 đều là file
  mặc định chưa có logic thật, an toàn để đè).
