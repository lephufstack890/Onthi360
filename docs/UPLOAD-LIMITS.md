# Giới hạn tải tệp — cách sửa lỗi `413 Request Entity Too Large`

## Vì sao gặp lỗi

nginx mặc định chỉ nhận yêu cầu tối đa **1MB** (`client_max_body_size`). Tải một tệp nặng
hơn thì nginx cắt ngay tại cửa và trả `413 Request Entity Too Large` — yêu cầu chưa từng
tới được PHP, nên Laravel không có cơ hội hiện câu báo lỗi tiếng Việt nào cả.

## Ba tầng chặn phải xếp đúng thứ tự

```
nginx client_max_body_size  (230M)   ← rộng nhất
  > PHP post_max_size       (220M)
    > PHP upload_max_filesize (210M)
      > luật kiểm tra của Laravel (200MB)   ← hẹp nhất
```

Đặt đúng thứ tự này thì tệp quá khổ bị **chính Laravel** từ chối và người dùng đọc được
"Tệp tối đa ...MB". Nếu tầng ngoài hẹp hơn tầng trong, PHP vứt cả thân yêu cầu trước khi
Laravel chạy — mất luôn mã CSRF — và người dùng nhận trang `419 Page expired` hoặc `413`
trần trụi, không hiểu vì sao.

## Giới hạn thật của ứng dụng

| Nơi tải | Hằng số trong mã | Giới hạn |
|---|---|---|
| PDF nguồn nhập hàng loạt | `PdfBulkImportService::MAX_SOURCE_PDF_KB` | **200MB** |
| PDF đề / tài liệu | `PdfAssessmentEditingService::MAX_PDF_KB` | 50MB |
| Media sản phẩm | `ProductController::MAX_MEDIA_KB` | 50MB |
| ZIP gói học liệu | `ContentService::MAX_BULK_MATERIAL_ZIP_KB` | 50MB |
| Gói ZIP câu hỏi | `ContentService::MAX_ZIP_PACKAGE_KB` | 20MB |
| Audio học liệu | `ContentService::MAX_MATERIAL_AUDIO_KB` | 20MB |
| Ảnh học liệu | `ContentService::MAX_MATERIAL_IMAGE_KB` | 15MB |
| Ảnh bìa sản phẩm / lộ trình | `ProductController` / `LearningPathController` | 4–8MB |

Đổi con số nào trong bảng thì nhớ đổi kèm hai tệp cấu hình bên dưới.

## Sửa trên VPS (nginx cài trực tiếp, không qua Docker)

### 1. nginx

```bash
sudo tee /etc/nginx/conf.d/upload-limits.conf > /dev/null <<'EOF'
# Giới hạn tải tệp của onthi360 — xem docs/UPLOAD-LIMITS.md
client_max_body_size 230M;
client_body_timeout  600s;
send_timeout         600s;
fastcgi_read_timeout 600s;
fastcgi_send_timeout 600s;
EOF

sudo nginx -t && sudo systemctl reload nginx
```

Đặt ở `conf.d/` thay vì sửa thẳng `sites-available/default`: cấu hình site có thể bị ghi
đè khi triển khai lại, còn tệp này thì không, và nhìn tên là biết ngay nó lo việc gì.

Nếu khối `server` của site có đặt `client_max_body_size` riêng thì giá trị trong khối
`server` thắng — phải sửa hoặc xoá dòng đó đi. Kiểm bằng:

```bash
sudo grep -rn "client_max_body_size" /etc/nginx/
```

### 2. PHP-FPM

```bash
# Xác định đúng phiên bản PHP đang chạy
php -v

sudo tee /etc/php/8.4/fpm/conf.d/99-onthi360-uploads.ini > /dev/null <<'EOF'
upload_max_filesize = 210M
post_max_size = 220M
max_input_time = 600
max_execution_time = 600
memory_limit = 512M
max_file_uploads = 50
EOF

sudo systemctl restart php8.4-fpm
```

Đặt ở `conf.d/` chứ không sửa `php.ini`: nâng cấp PHP sẽ thay `php.ini` nhưng giữ nguyên
thư mục `conf.d`.

### 3. Kiểm tra lại

```bash
php -i | grep -E "upload_max_filesize|post_max_size|max_file_uploads"
curl -sI -X POST -H "Content-Length: 100000000" https://<tên-miền>/ | head -1
```

Dòng đầu trả `419` hoặc `405` là nginx đã cho qua (tốt). Còn trả `413` là chưa ăn cấu hình.

## Nếu vẫn 413 sau khi làm hết

- Có Cloudflare hoặc proxy đứng trước không? Gói miễn phí của Cloudflare chặn ở **100MB**
  và không nâng được — lúc đó phải tải tệp lớn qua đường khác, hoặc cắt nhỏ tệp.
- Còn tầng nginx thứ hai (load balancer) đứng trước không?
- `sudo nginx -t` có báo đang đọc đúng tệp cấu hình vừa thêm không?

## Ghi chú cho lần sau

Mức 200MB cho một lần tải qua HTTP là khá mạo hiểm: mạng chập chờn giữa chừng là hỏng cả
lần tải, người dùng phải làm lại từ đầu. Nếu thực tế khách hay tải tệp cỡ đó, nên tính
đường tải theo từng phần (chunked upload) thay vì nâng mãi giới hạn.
