{{-- SỬA 16/10 (khách: "giao diện xem sách chữ đang lí nhí, bé") — tăng cỡ chữ phần VỎ trình đọc (thanh đầu trang, thanh công cụ,
     cột Bài tập) cho dễ đọc. Chỉ áp trong khung có lớp .rd-ui (student/materials/read + materials/product-read); KHÔNG đụng vùng
     PDF (#reader-scroll-box) vì trang PDF đã có nút − / + riêng. Dùng chung cho 2 màn đọc. --}}
<style>
    @media (min-width: 1024px) { .rd-ui [class*="lg:grid-cols-[minmax(0,1fr)_340px]"] { grid-template-columns: minmax(0, 1fr) 356px; } }
    .rd-ui .text-\[9px\] { font-size: 11px; }
    .rd-ui .text-\[10px\] { font-size: 12px; }
    .rd-ui .text-\[11px\] { font-size: 12.5px; }
    .rd-ui .text-\[12px\] { font-size: 13.5px; }
    .rd-ui .text-\[13px\] { font-size: 14.5px; }
    .rd-ui header h1 { font-size: 18px; font-weight: 700; }
    .rd-ui #reader-page-input { height: 32px; width: 60px; font-size: 13px; }
    .rd-ui #reader-page-form button { width: 32px; height: 32px; }
    .rd-ui .rz-label { font-size: 13.5px; }
    /* SỬA 16/10 (khách: "co giãn phù hợp để thấy toàn bộ tên chương") — ô chọn chương tự rộng theo tên chương dài nhất. */
    .rd-ui #material-section-select { width: auto; min-width: 220px; max-width: 100%; padding-right: 34px; }
    .rd-ui #reader-scroll-box .text-\[13px\] { font-size: 13px; }

    .rd-ui .rcp-title h2, .rd-ui .rcp-mtoggle h2 { font-size: 16px; }
    .rd-ui .rcp-title span, .rd-ui .rcp-status, .rd-ui .rcp-ghead, .rd-ui .rcp-hint { font-size: 12.5px; }
    .rd-ui .rcp-scope button, .rd-ui .rcp-tabs button { min-height: 36px; font-size: 13px; }
    .rd-ui .rcp-chapter, .rd-ui .rcp-media a.rcp-file { font-size: 13px; }
    .rd-ui .rcp-search input { min-height: 42px; font-size: 13.5px; }
    .rd-ui .rcp-ghead b, .rd-ui .rcp-ghead i, .rd-ui .rcp-pill, .rd-ui .rcp-count { font-size: 11.5px; }
    .rd-ui .rcp-empty, .rd-ui .rcp-empty button, .rd-ui .rcp-act { font-size: 13.5px; }
    .rd-ui .rcp-detail h3 { font-size: 14.5px; }
    .rd-ui .rcp-meta, .rd-ui .rcp-legend { font-size: 12px; }
    .rd-ui .rcp-media-row p { font-size: 14px; }

    /* SỬA 25/10 (khách: "cột Bài tập phải bằng khung đọc" — sách, chuyên đề, bộ đề) — cột phải có lớp .rd-aside-fill thì cột
       Bài tập cao đúng bằng cột đọc bên trái (trước đây cao theo màn hình nên lệch đáy); danh sách dài cuộn bên trong. */
    @media (min-width: 1024px) {
        /* Cột phải không tự đẩy cao hàng: khung Bài tập phủ kín cột (absolute), hàng cao theo cột đọc bên trái. */
        .rd-ui .rd-aside-fill { position: relative; top: auto; height: auto; min-height: 0; }
        .rd-ui .rd-aside-fill > .rcp, .rd-ui .rd-aside-fill > .rcp.is-focus { position: absolute; inset: 0; height: auto; max-height: none; }
    }

    /* Chế độ Tập trung: ẩn thanh đầu trang (giữ cột Bài tập) -> vùng đọc PDF cao thêm cho vừa màn hình. */
    @media (min-width: 1024px) { .rd-ui .rd-focus #reader-scroll-box { height: calc(100dvh - 150px); max-height: none; } }
</style>
