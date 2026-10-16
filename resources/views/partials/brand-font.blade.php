{{-- SỬA 16/10 (khách: "chữ lí nhí, bé, không tương đồng với giao diện source mới") — NẠP font thương hiệu Be Vietnam Pro.
     resources/css/app.css đã khai báo font-family 'Be Vietnam Pro' (giống source mới education-main) nhưng trước đây CHỈ layout
     guest/auth nạp font từ Google Fonts; các layout trong (admin, giáo viên, học sinh, phụ huynh, trình đọc, phòng thi) không
     nạp nên trình duyệt rơi về font hệ thống (Segoe UI/Arial) — chữ mảnh, nhỏ và khác hẳn bản thiết kế. --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
