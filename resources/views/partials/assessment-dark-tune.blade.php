{{-- ═══════ CHỈNH MÀU CHO NỀN TỐI CỦA MODAL LÀM BÀI (dùng chung) ═══════

     SỬA 1/10 (khách: "đề ở chế độ tối bị lớp phủ đen, mờ" + khoanh đỏ khối kết quả chấm
     "chữ bị mờ, cho sáng lên").

     VÌ SAO VIẾT Ở ĐÂY chứ không sửa resources/css/app.css: máy chủ KHÔNG chạy được vite,
     sửa trong app.css thì phải build lại mới có tác dụng — mà bản build hiện tại trên máy
     chủ không đổi. Đặt thẳng vào thẻ style của trang là ăn ngay sau khi git pull.

     Nạp ở: exercise-play (làm bài tập), assessment/take (phòng thi),
            competitions/exam + exam-pdf (cuộc thi) — mọi màn có lớp .assessment-modal. --}}
<style>
    /* ── 1. Trang đề KHÔNG bị dìm ────────────────────────────────────────────────────
       app.css (bản đã build trên máy chủ) hạ chói trang đề xuống brightness .72 — ý đồ
       chống chói, nhưng tờ giấy trắng hoá xám chì.

       SỬA 1/10 lần 1 nâng lên .94, khách xem lại vẫn thấy xám: "giao diện phải trắng sáng,
       đừng bị lớp phủ đen lên khi ở chế độ tối". Nên BỎ HẲN bộ lọc — tờ đề giữ đúng màu
       thật, trắng y như ở nền sáng. Phần khung ngoài vẫn tối nên không chói cả màn hình.

       Giữ 'filter: none' chứ không xoá luật: luật trong app.css vẫn còn và máy chủ không
       build lại vite được, phải có luật này đè lên mới ăn. */
    html.theme-dark .assessment-modal .assessment-pdf-surface canvas,
    html.theme-dark .assessment-modal .assessment-pdf-iframe {
        filter: none !important;
    }

    /* Mặt khung chứa tờ đề cũng đừng ngả đen quá: giữ nền xanh nhạt như nền sáng, chỉ đậm
       hơn chút cho đỡ chói viền. Nếu không, tờ giấy trắng nằm trên nền gần như đen trông
       như bị "lớp phủ" đúng từ khách dùng. */
    html.theme-dark .assessment-modal .assessment-pdf-surface {
        background-color: #22404f !important;
        border-color: #3d5d6d !important;
    }

    /* ── 2. Chữ đỏ (test sai, lỗi biên dịch) ─────────────────────────────────────────
       text-rose-700 là đỏ sẫm, hợp nền sáng. Nền tối KHÔNG có luật nào lật nó (app.css
       chỉ lật emerald và amber-600/700), nên "Kết quả chấm: Có test sai." và chữ "Sai"
       ở từng dòng thành đỏ sẫm trên nền xanh đen — gần như chìm hẳn. */
    html.theme-dark .assessment-modal .text-rose-700,
    html.theme-dark .assessment-modal .text-rose-600,
    html.theme-dark .assessment-modal .text-rose-500 {
        color: #ff9ea4 !important;
    }

    /* ── 3. Khối mách nước freopen ───────────────────────────────────────────────────
       Nền bg-amber-50 ĐÃ bị nền tối đổi thành xanh đen, nhưng chữ text-amber-900 (nâu
       sẫm) thì không được lật — nâu sẫm trên xanh đen là không đọc được. */
    html.theme-dark .assessment-modal .text-amber-900,
    html.theme-dark .assessment-modal .text-amber-800 {
        color: #f2c778 !important;
    }

    /* ── 4. Viền sáng quanh mấy ô mã nguồn trong phần chi tiết test sai ──────────────
       Mấy lớp ring-[...] là màu nhạt dành cho nền sáng; để nguyên trên nền tối thì thành
       đường kẻ trắng chói quanh ô. Hạ về đúng tông viền của nền tối. */
    html.theme-dark .assessment-modal [class*="ring-[#F"],
    html.theme-dark .assessment-modal [class*="ring-[#E"],
    html.theme-dark .assessment-modal [class*="ring-[#D"] {
        --tw-ring-color: #34505e;
    }
</style>
