# Làm nhẹ ảnh và tăng tốc tải trang

Ghi ngày 30/9 sau khi khách phàn nàn: *"bấm Xem lộ trình, qua trang lộ trình ảnh load chậm"*.

## Vì sao chậm

| Nguyên nhân | Chi tiết |
|---|---|
| Ảnh bìa tải lên không bị thu nhỏ | `$file->store()` cất nguyên xi. Có tấm 1672×941 nặng **1,5MB**, trong khi chỗ hiển thị rộng chưa tới 700px. |
| Bộ ảnh tĩnh quá nặng | `public/assets` nặng **6,7MB**. Riêng logo trên thanh điều hướng — xuất hiện ở **mọi trang** — nặng 193KB cho một tấm cao 32px. |
| Máy chủ không bật gzip | Tệp `app-*.css` nặng 219KB gửi nguyên xi; gzip xong còn khoảng 30KB. |
| Không đặt hạn dùng cho tệp tĩnh | Mỗi lần chuyển trang, trình duyệt hỏi lại từng tấm ảnh. Chục tấm là chục lượt đi về. |
| Tải phông chữ thừa | 3 bộ phông với đủ mọi độ đậm, trong đó **Plus Jakarta Sans không bao giờ được dùng** (nó chỉ nằm dự phòng sau Be Vietnam Pro). Thẻ phông chặn hiển thị cho tới khi tải xong. |

## Đã sửa trong mã nguồn

1. **`app/Support/ImageOptimizer.php`** — ảnh tải lên từ nay được thu về tối đa 1600px, ảnh
   không có nền trong suốt chuyển sang JPEG. Áp dụng cho: bìa lộ trình, ảnh chia sẻ, bìa khoá
   học, bìa sản phẩm, ảnh và biểu tượng của lời chứng thực.
2. **`public/assets`** nén lại: **6,7MB → 4,0MB**. 22 tệp PNG không có nền trong suốt đã đổi
   sang `.jpg` (mọi chỗ gọi trong mã đã đổi theo). Logo `header-logo.png`: 193KB → 28KB.
3. **Phông chữ**: bỏ Plus Jakarta Sans và các độ đậm không dùng.
4. **Thẻ ảnh**: khai `width`/`height` cho logo (trang khỏi giật), `loading="lazy"` cho chân
   trang, `fetchpriority="high"` + `<link rel="preload">` cho ảnh bìa trang lộ trình.
5. **`docker/nginx/default.conf`**: bật gzip và đặt hạn dùng cho tệp tĩnh.

## Việc phải làm trên máy chủ

### 1. Nén lại ảnh đã tải lên từ trước

```bash
cd /var/www/onthi360

# Xem trước sẽ tiết kiệm được bao nhiêu — KHÔNG sửa tệp nào
php artisan images:optimize

# Sao lưu rồi nén thật
cp -r storage/app/public storage/app/public.bak
php artisan images:optimize --ghi
```

Lệnh giữ nguyên tên tệp nên không hỏng liên kết nào trong cơ sở dữ liệu.
Cần phần mở rộng GD của PHP — kiểm tra bằng `php -m | grep -i gd`; chưa có thì
`sudo apt install php8.4-gd && sudo systemctl restart php8.4-fpm`.

Sau khi ưng ý thì xoá bản sao lưu: `rm -rf storage/app/public.bak`

### 2. Bật gzip và hạn dùng cho tệp tĩnh trên nginx

Nếu máy chủ chạy nginx trực tiếp (không qua Docker), mở tệp cấu hình của trang
(thường là `/etc/nginx/sites-available/onthi360`) và dán khối dưới đây vào **trong**
`server { ... }`, đặt **trước** `location / { ... }`:

```nginx
    gzip on;
    gzip_vary on;
    gzip_comp_level 5;
    gzip_min_length 1024;
    gzip_proxied any;
    gzip_types text/plain text/css text/xml text/javascript
               application/javascript application/json application/xml
               image/svg+xml font/woff font/woff2;

    location ^~ /build/ {
        expires 1y;
        add_header Cache-Control "public, max-age=31536000, immutable";
        access_log off;
    }

    location ~* \.(?:jpg|jpeg|png|gif|webp|svg|ico|woff2?|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, max-age=2592000";
        access_log off;
        try_files $uri =404;
    }
```

Rồi kiểm tra và nạp lại:

```bash
sudo nginx -t && sudo systemctl reload nginx
```

`nginx -t` báo lỗi thì **đừng reload** — sửa cho hết lỗi đã, trang vẫn chạy bằng cấu hình cũ.

### 3. Sau khi `git pull`

```bash
php artisan optimize:clear
```

## Kiểm lại có nhanh hơn không

Mở trang lộ trình, nhấn F12 → thẻ **Network** → tick **Disable cache** → tải lại.
Nhìn dòng tổng ở cuối: **transferred** (số byte thật sự tải về) và **Load**.
Bỏ tick *Disable cache* rồi bấm qua lại giữa các trang — từ lần thứ hai ảnh phải hiện
ra ngay, cột Size ghi `(disk cache)`.
