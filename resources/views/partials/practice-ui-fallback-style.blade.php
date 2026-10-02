{{-- ═══════════ CSS BÙ CHO MÀN LUYỆN TẬP (kho đề + chi tiết đề) ═══════════
     SỬA 2/10 — VÌ SAO có tệp này: máy chủ KHÔNG chạy được vite, nên mọi class Tailwind phải
     CÓ SẴN trong public/build/assets/app-*.css đã build từ 19/9. Class tuỳ ý với bộ số MỚI
     (bg-[#E9F0F4], min-h-[430px], lg:grid-cols-[minmax(0,1fr)_296px]…) không nằm trong tệp đó
     nên trên máy chủ sẽ KHÔNG ra style nào — màn vỡ lưới, chữ mất màu.

     Cách bù: viết đúng những quy tắc mà Tailwind sẽ sinh ra, với tên chọn lọc y hệt (dấu [ ] #
     phải thoát bằng \). Nhờ vậy mã HTML không phải sửa, và khi nào máy chủ build lại được CSS
     thì các quy tắc này chỉ còn là bản trùng vô hại.

     Mốc màn hình lấy đúng theo Tailwind v4.3.3 đang dùng: sm = 40rem, lg = 64rem.

     ĐÃ ĐỐI CHIẾU từng tên với app-BHdXR6Bc.css — chỉ liệt kê class THẬT SỰ thiếu; class nào
     đã có trong bản build (order-1, w-48, gap-y-1, các shadow cũ…) thì không nhắc lại ở đây. --}}
<style>
    /* ── màu chữ ── */
    .text-\[\#427EA1\] { color: #427EA1; }
    .text-\[\#456B7D\] { color: #456B7D; }
    .text-\[\#4A7D9B\] { color: #4A7D9B; }
    .text-\[\#52687B\] { color: #52687B; }
    .text-\[\#6F4F18\] { color: #6F4F18; }
    .text-\[\#785C2A\] { color: #785C2A; }
    .text-\[\#7B6D50\] { color: #7B6D50; }
    .text-\[\#887C6C\] { color: #887C6C; }
    .text-\[\#A87530\] { color: #A87530; }

    /* ── màu nền / viền ── */
    .bg-\[\#E9F0F4\] { background-color: #E9F0F4; }
    .border-\[\#CFE5D9\] { border-color: #CFE5D9; }
    .border-\[\#DCE7EC\] { border-color: #DCE7EC; }
    .border-\[\#EADDB9\] { border-color: #EADDB9; }

    /* ── cỡ chữ ── */
    .text-\[18px\] { font-size: 18px; }

    /* ── chiều cao / chiều cao tối thiểu ── */
    .h-48 { height: 12rem; }
    .min-h-4 { min-height: 1rem; }
    .min-h-5 { min-height: 1.25rem; }
    .min-h-\[34px\] { min-height: 34px; }
    .min-h-\[72px\] { min-height: 72px; }
    .min-h-\[360px\] { min-height: 360px; }
    .min-h-\[430px\] { min-height: 430px; }

    /* ── bề rộng / khoảng cách / vị trí ── */
    .max-w-\[900px\] { max-width: 900px; }
    .inset-\[19\%\] { inset: 19%; }
    .gap-y-0\.5 { row-gap: .125rem; }

    /* ── lưới ── */
    .grid-cols-\[76px_minmax\(0\,1fr\)\] { grid-template-columns: 76px minmax(0, 1fr); }

    /* ── đổ bóng ── */
    .shadow-\[0_2px_10px_rgba\(28\,91\,121\,0\.04\)\] { box-shadow: 0 2px 10px rgba(28, 91, 121, .04); }
    .shadow-\[0_3px_16px_rgba\(28\,91\,121\,0\.04\)\] { box-shadow: 0 3px 16px rgba(28, 91, 121, .04); }

    @media (min-width: 40rem) {
        .sm\:h-52 { height: 13rem; }
        .sm\:w-52 { width: 13rem; }
        .sm\:w-\[170px\] { width: 170px; }
    }

    @media (min-width: 64rem) {
        .lg\:order-1 { order: 1; }
        .lg\:order-2 { order: 2; }
        .lg\:grid-cols-\[minmax\(0\,1fr\)_320px\] { grid-template-columns: minmax(0, 1fr) 320px; }
    }

    /* ── Nội dung đề bài soạn bằng trình soạn thảo (question.body) ──────────────────────
       SỬA 2/10 lần 2 — .rich-content không nằm trong CSS đã build mà được từng màn tự khai
       trong thẻ style riêng (exercise-play, courses/show…). Màn chi tiết đề giờ cũng đổ
       question.body ra nên cần đúng bộ quy tắc đó, chép y nguyên cho khỏi lệch kiểu chữ. */
    .rich-content ul { list-style: disc; padding-left: 1.25rem; margin-bottom: .5rem; }
    .rich-content ol { list-style: decimal; padding-left: 1.25rem; margin-bottom: .5rem; }
    .rich-content p { margin-bottom: .5rem; }
    .rich-content a { color: #126F91; text-decoration: underline; }
    .rich-content img { max-width: 100%; height: auto; }
    .rich-content pre { overflow-x: auto; }
    .rich-content table { width: 100%; border-collapse: collapse; }
    .rich-content td, .rich-content th { border: 1px solid #DDEAF0; padding: 6px 8px; }
</style>
