{{-- ═══════════ CSS BÙ CHO TRÌNH ĐỌC TÀI LIỆU (dùng chung 2 màn) ═══════════
     SỬA 3/10 (khách: "2 cái phải đồng bộ") — tách khỏi student/materials/read.blade.php để MÀN
     ĐỌC SẢN PHẨM (materials/product-read) dùng CHUNG đúng một bản. Chép đôi là có ngày sửa một
     bên, bên kia lệch — đúng cái vừa làm khách bực.

     Đây là các quy tắc bù cho class Tailwind chưa có trong bản CSS đã build. Lý do từng nhóm
     ghi ngay tại chỗ. Bên gọi đặt partial này BÊN TRONG khối style của mình.

     LƯU Ý: tuyệt đối không viết tên thẻ đóng của style trong đây, kể cả trong ghi chú — trình
     duyệt kết thúc khối style ở lần gặp đầu tiên. --}}
        /* SỬA 3/10 (2) — MÀU NỀN/VIỀN CỦA THẺ BÀI TẬP.
           Đây là lỗi tôi để sót ở lượt trước: 10 class dưới đây không có trong bản CSS đã
           build, nên trên máy chủ mọi thẻ bài tập sẽ KHÔNG có nền màu nào — đúng cái việc mà
           "nền đổi theo trạng thái" sinh ra để làm.

           BÀI HỌC: TUYỆT ĐỐI không viết tên thẻ đóng của style ngay trong khối CSS này, kể cả
           bên trong dấu ghi chú — trình duyệt kết thúc khối style ở lần gặp ĐẦU TIÊN, không
           quan tâm nó nằm trong ghi chú hay không. Viết vào là nửa dưới của khối CSS bị đổ ra
           màn hình thành chữ. */
        .bg-\[\#F0F8F2\] { background-color: #F0F8F2; }
        .bg-\[\#FFF7E7\] { background-color: #FFF7E7; }
        .bg-\[\#EFF8FA\] { background-color: #EFF8FA; }
        .border-\[\#CBE7D5\] { border-color: #CBE7D5; }
        .border-\[\#F0D9A9\] { border-color: #F0D9A9; }
        .border-\[\#C8E2EA\] { border-color: #C8E2EA; }
        .text-\[\#946A28\] { color: #946A28; }
        .hover\:bg-\[\#E8F4EC\]:hover { background-color: #E8F4EC; }
        .hover\:bg-\[\#FFF1D5\]:hover { background-color: #FFF1D5; }
        .hover\:bg-\[\#E6F3F6\]:hover { background-color: #E6F3F6; }

        /* Viền thẻ ĐANG ĐƯỢC CHỌN. Phải viết bằng 2 lớp (.oi-ex-card.oi-ex-selected) chứ không
           dùng class màu của Tailwind: mấy quy tắc màu viền theo trạng thái ngay trên nằm TRONG
           tệp này, tức là đứng SAU bản CSS đã build, nên chúng sẽ đè mất màu viền xanh dù thẻ
           có class đó. Hai lớp thì trọng số cao hơn, thắng chắc. */
        .oi-ex-card.oi-ex-selected { border-color: #2D7FA3; }
        .max-h-\[420px\] { max-height: 420px; }

        /* SỬA 3/10 — thêm cho thanh đầu trang + thẻ bài tập dựng lại theo bản mẫu. */
        .isolate { isolation: isolate; }
        .min-h-\[74px\] { min-height: 74px; }
        .border-\[\#CFE5E5\] { border-color: #CFE5E5; }
        .ring-\[\#B9DDE8\] { --tw-ring-color: #B9DDE8; }

        /* BẪY: đổ bóng phải ghi qua biến --tw-shadow, KHÔNG ghi thẳng box-shadow. Tailwind v4
           gộp viền-quầng (ring) và bóng vào CÙNG một thuộc tính box-shadow bằng các biến CSS;
           ghi thẳng box-shadow là xoá luôn quầng ring của chính thẻ đó — thẻ bài tập đang chọn
           sẽ mất viền sáng mà không ai hiểu vì sao. */
        .grid-cols-\[minmax\(0\,1fr\)_auto\] { grid-template-columns: minmax(0, 1fr) auto; }
        .shadow-\[0_3px_10px_rgba\(45\,96\,145\,0\.08\)\] { --tw-shadow: 0 3px 10px rgba(45, 96, 145, .08); box-shadow: var(--tw-inset-shadow), var(--tw-inset-ring-shadow), var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow); }

        /* ── Class Tailwind của bản mẫu mới CHƯA có trong CSS đã build ──────────────────
           SỬA 2/10 — máy chủ KHÔNG chạy được vite nên mọi class phải có sẵn trong
           public/build/assets/app-*.css (bản build 19/9). Mấy class dưới đây là màu/kích
           thước mới của bản mẫu MaterialReaderPage.jsx, không nằm trong bản build đó nên
           trên máy chủ sẽ không ra style nào — sao độ khó mất màu, nút Làm bài mất nền,
           hàng 3 cột của thẻ bài tập vỡ. Viết đúng quy tắc mà Tailwind sẽ sinh ra (tên chọn
           lọc y hệt, dấu [ ] # ( ) , . phải thoát bằng \), nên mã HTML không phải sửa và
           khi nào build lại được CSS thì đây chỉ còn là bản trùng vô hại.
           ĐÃ ĐỐI CHIẾU từng tên với app-BHdXR6Bc.css — chỉ liệt kê class THẬT SỰ thiếu. */
        .text-\[\#436F6B\] { color: #436F6B; }
        .text-\[\#526B7D\] { color: #526B7D; }
        .text-\[\#C7D2D9\] { color: #C7D2D9; }
        .text-\[\#D29A18\] { color: #D29A18; }
        .bg-\[\#368F72\] { background-color: #368F72; }
        .bg-\[\#E2EEEC\] { background-color: #E2EEEC; }
        .border-\[\#DDE7EA\] { border-color: #DDE7EA; }
        .pr-1 { padding-right: .25rem; }
        .z-40 { z-index: 40; }
        .w-\[260px\] { width: 260px; }
        .grid-cols-\[minmax\(0\,1\.1fr\)_minmax\(0\,1fr\)_minmax\(0\,\.8fr\)\] {
            grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr) minmax(0, .8fr);
        }
        .shadow-\[0_2px_7px_rgba\(67\,111\,107\,0\.12\)\] { --tw-shadow: 0 2px 7px rgba(67, 111, 107, .12); box-shadow: var(--tw-inset-shadow), var(--tw-inset-ring-shadow), var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow); }
        .shadow-\[0_6px_18px_rgba\(45\,96\,145\,0\.12\)\] { --tw-shadow: 0 6px 18px rgba(45, 96, 145, .12); box-shadow: var(--tw-inset-shadow), var(--tw-inset-ring-shadow), var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow); }
        .hover\:bg-\[\#2F8066\]:hover { background-color: #2F8066; }
        .hover\:bg-\[\#DFF2E9\]:hover { background-color: #DFF2E9; }
        .hover\:bg-\[\#EAF3F2\]:hover { background-color: #EAF3F2; }
        .hover\:border-\[\#B8D7E1\]:hover { border-color: #B8D7E1; }
        .hover\:text-\[\#436F6B\]:hover { color: #436F6B; }
