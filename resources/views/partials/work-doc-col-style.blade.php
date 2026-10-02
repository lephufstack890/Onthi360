{{--
    SỬA 1/10 — CỘT NỘI DUNG của các tab Đề bài / Hướng dẫn / Bài mẫu, tách ra partial để màn
    Luyện tập (exercise-play) và màn Phòng thi (assessment/take) dùng CHUNG một bản.

    Khách: "đề nó phải hiển thị như này đừng to full nha" — tờ đề nằm gọn giữa khung như một tờ
    A4 đặt trên mặt bàn, không kéo căng hết bề ngang màn hình. Trước đây 2 lớp này chỉ có trong
    thẻ style riêng của exercise-play nên phòng thi không có, đề kéo full.

    VIẾT CSS THƯỜNG, không dùng class Tailwind: max-w-[820px] và sm:py-6 CHƯA CÓ trong
    public/build/assets/app-*.css mà máy chủ thì không chạy được vite — dùng class Tailwind mới
    là mất tác dụng.
--}}
<style>
    .oi-doc-col { margin-left: auto; margin-right: auto; width: 100%; max-width: 820px; }
    .oi-doc-page { padding: 18px 16px; }
    @media (min-width: 640px) { .oi-doc-page { padding: 26px 32px; } }

    /* ── Dải 5 tab trên màn hẹp ──────────────────────────────────────────────────────
       SỬA 1/10 — class Tailwind `grid-cols-5` KHÔNG CÓ trong public/build/assets/app-*.css
       (bản build hiện tại chỉ có grid-cols-2/3/4), mà máy chủ không chạy được vite. Màn
       Luyện tập đã dùng class đó từ 30/9 nên trên điện thoại 5 tab bị xếp DỌC thành 5 hàng
       thay vì 5 cột — lỗi có sẵn, sửa luôn ở đây cho cả hai màn.
       Từ 768px trở lên thì md:flex-col của Tailwind tiếp quản (rail dọc bên trái). */
    @media (max-width: 767.98px) {
        .oi-tab-rail { grid-template-columns: repeat(5, minmax(0, 1fr)); }
    }

    /* ── Chừa mép khi nhảy tới một câu ──────────────────────────────────────────────
       SỬA 2/10 — `scroll-mt-2` cũng không có trong bản CSS đã build, nên khi bấm số câu
       để nhảy tới, khung câu dán sát đỉnh vùng cuộn. Viết tay 1 dòng cho đúng ý ban đầu. */
    .scroll-mt-2 { scroll-margin-top: .5rem; }
</style>
