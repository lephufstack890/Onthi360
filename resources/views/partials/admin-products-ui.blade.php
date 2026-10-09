{{-- KIỂU CHUNG CHO MÀN "TÀI LIỆU" CỦA ADMIN (danh sách, chi tiết, form tạo/sửa).

     SỬA 8/10 (khách: "cập nhật lại UI màn tài liệu theo source mới, logic giữ nguyên, chỉ lấy phong cách
     UI") — chuyển phong cách của education-main/src/components/AdminContentWorkspace.jsx +
     adminContent.css (danh sách có bộ chuyển + thanh tìm/lọc + bảng ô định danh; trình soạn 2 cột gồm
     thẻ "Thông tin chung" và thẻ "Thiết lập hiển thị") sang Blade.

     Dùng LẠI khối kiểu .acx- của màn "Khóa & Lớp" (nút, thẻ, huy hiệu, bảng, đầu trang) để hai màn
     đồng bộ, rồi chỉ thêm phần riêng của màn này, tiền tố .apx-. Không route, không tên field,
     không truy vấn nào đổi. Server không build lại Tailwind nên mọi kiểu mới nằm ở đây. --}}
@include('partials.admin-courses-ui')
<style>
    .acx-badge--info{color:#1d4ed8;background:#e0edff}
    .acx-table.apx-all tr>:nth-child(2){display:table-cell}
    .acx-table.apx-all thead th:first-child{width:auto}

    /* ===== Danh sách ===== */
    .apx-tile{display:grid;place-items:center;flex-shrink:0;width:40px;height:40px;border-radius:12px;border:1px solid #bfdbfe;background:#eff6ff;color:#1d4ed8}
    .apx-tile svg{width:18px;height:18px}
    .apx-cell-sub{display:none}
    .apx-money{font-weight:600;color:#365673;white-space:nowrap}
    @media (max-width:767px){
        .apx-cell-sub{display:block}
        .acx-table.apx-list-table tr>:nth-child(3){display:none}
    }

    /* ===== Khối nội dung ===== */
    .apx-hint{margin:-4px 0 14px;font-size:12px;line-height:1.65;color:#64748b}
    .apx-cardtop{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:12px}
    .apx-cardtop h2{margin:0!important}
    .apx-count{display:inline-flex;align-items:center;padding:3px 10px;border-radius:999px;background:#eef3f9;color:#587089;font-size:12px;font-weight:600}
    .apx-add{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-bottom:14px;padding:12px;border:1px dashed #b9d3ee;border-radius:14px;background:#f4f9ff}
    .apx-add form{display:flex;flex-wrap:wrap;align-items:center;gap:10px;flex:1 1 240px;min-width:0;margin:0}
    .apx-input{min-width:0;min-height:40px;border:1px solid #bdcbdc;border-radius:10px;background:#fff;color:#273f58;font-size:13px;padding:8px 12px}
    .apx-input:focus{outline:0;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.09)}
    .apx-input--grow{flex:1 1 200px}
    .apx-input--num{width:80px}
    .apx-file{flex:1 1 200px;min-width:0;font-size:13px;color:#475569}
    .apx-file::file-selector-button{margin-right:10px;padding:7px 12px;border:1px solid #bfdbfe;border-radius:9px;background:#eff6ff;color:#1d4ed8;font-size:12px;font-weight:600;cursor:pointer}
    .apx-file::file-selector-button:hover{background:#dbeafe}
    .apx-list{display:flex;flex-direction:column}
    .apx-item{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px 14px;padding:12px 2px;border-top:1px solid #e8eef5}
    .apx-item:first-child{border-top:0}
    .apx-item__main{flex:1 1 240px;min-width:0}
    .apx-item__title{margin:0;font-size:13px;font-weight:600;line-height:1.5;color:#1e3a5f;overflow-wrap:anywhere}
    .apx-item__sub{margin:3px 0 0;font-size:12px;line-height:1.6;color:#637b92}
    .apx-item__side{display:flex;flex-shrink:0;flex-wrap:wrap;align-items:center;gap:8px}
    .apx-item__side form{margin:0}
    .apx-tag{display:inline-flex;align-items:center;margin-left:6px;padding:2px 8px;border-radius:999px;background:#eef3f9;color:#587089;font-size:11px;font-weight:600;vertical-align:middle}
    .apx-ok{color:#059669;font-weight:600}
    .apx-warn{color:#d97706;font-weight:600}
    .apx-muted{color:#8aa0b6}
    .apx-edit{display:flex;flex-wrap:wrap;align-items:center;gap:8px;padding:10px 2px;border-top:1px solid #e8eef5}
    .apx-edit label{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:#587089}
    .apx-more{display:inline-block;margin-top:12px;font-size:13px;font-weight:600;color:#2563eb;text-decoration:none}
    .apx-more:hover{text-decoration:underline}
    .apx-res{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 0;border-top:1px solid #e8eef5;font-size:13px}
    .apx-res:first-child{border-top:0}
    .apx-res span:first-child{flex-shrink:0;color:#475569}
    .apx-res span:last-child{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px}
    .apx-order{display:block;font-size:11px;color:#8aa0b6}

    /* ===== Form tạo / sửa (2 cột: Thông tin chung + Thiết lập hiển thị) ===== */
    .apx-editor{display:grid;gap:16px;grid-template-columns:minmax(0,1fr);align-items:start}
    @media (min-width:1024px){.apx-editor{grid-template-columns:minmax(0,1.9fr) minmax(280px,1fr);grid-template-rows:auto 1fr}.apx-editor__main{grid-row:1 / span 2}}
    .apx-editor>form{display:contents}
    .apx-fields{display:flex;flex-direction:column;gap:16px}
    .apx-fields label.apx-lbl{display:block;margin-bottom:4px;font-size:13px;font-weight:600;color:#4b627a}
    .apx-fields .admin-input,.apx-fields input[type="text"],.apx-fields input[type="number"],.apx-fields textarea{border:1px solid #bdcbdc;border-radius:10px;background:#fff;color:#273f58}
    .apx-fields .admin-input:focus,.apx-fields textarea:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.09);outline:0}
    .apx-section{padding-top:16px;border-top:1px solid #e3ecf5}
    .apx-aside{position:sticky;top:12px}
    .apx-save{width:100%;margin-top:6px}
    .apx-note{margin:6px 0 0;font-size:12px;line-height:1.6;color:#7a8ea3}
    /* ===== Ảnh bìa từ catalog (bê từ .cm-cover-catalog của source mới) ===== */
    .apx-cc{min-width:0;margin:0;border:1px solid #dce8f6;border-radius:16px;padding:14px;background:#f8fbff}
    .apx-cc legend{padding:0 5px;font-size:13px;font-weight:700;color:#334d70}
    .apx-cc__grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(115px,1fr));gap:10px}
    .apx-cc__item{position:relative;display:flex;min-width:0;flex-direction:column;gap:6px;border:2px solid transparent;border-radius:12px;padding:7px;background:#fff;cursor:pointer;box-sizing:border-box}
    .apx-cc__item:hover{border-color:#bfdbfe}
    .apx-cc__item.is-on{border-color:#2563eb;box-shadow:0 0 0 2px #dbeafe}
    .apx-cc__item input{position:absolute;right:10px;top:10px;width:16px;height:16px;accent-color:#2563eb;margin:0}
    .apx-cc__item img{display:block;width:100%;height:110px;border-radius:8px;object-fit:cover;background:#eaf2fb}
    .apx-cc__item span{display:-webkit-box;overflow:hidden;-webkit-box-orient:vertical;-webkit-line-clamp:2;font-size:11px;line-height:1.35;font-weight:600;color:#475569}
    .apx-cover{display:flex;align-items:center;gap:12px;margin-bottom:8px}
    .apx-cover img{width:80px;height:80px;border-radius:12px;object-fit:cover;border:1px solid #d7e3f0;background:#fff}

    /* ===== Danh sách kiểu AdminContentWorkspace: 3 tab nhóm + thanh tìm + bảng tiêu đề in hoa ===== */
    .apx-tabs{display:flex;flex-wrap:wrap;gap:8px;padding:6px;margin-top:16px;border:1px solid #e0f2fe;border-radius:20px;background:#fff;box-shadow:0 3px 12px rgba(25,90,150,.04)}
    .apx-tab{flex:1 1 0;display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;padding:0 16px;border:0;border-radius:14px;background:transparent;color:#4b627a;font:inherit;font-size:14px;font-weight:700;cursor:pointer;transition:background .15s,color .15s}
    .apx-tab:hover{background:#f0f7ff}
    .apx-tab span{display:inline-grid;place-items:center;min-width:22px;height:22px;padding:0 6px;border-radius:7px;background:#eef3f9;color:#587089;font-size:11px;font-weight:700}
    .apx-tab.is-on{background:#2563eb;color:#fff;box-shadow:0 2px 6px rgba(37,99,235,.22)}
    .apx-tab.is-on span{background:rgba(255,255,255,.22);color:#fff}
    @media (max-width:767px){.apx-tab{flex:1 1 40%}}
    .apx-sub{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px 16px;padding:0 20px 12px;font-size:12px;color:#587089}
    .apx-sub b{color:#1e3a5f}
    .apx-sub__r{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
    .apx-sub .acx-field{min-height:32px;padding:3px 8px;font-size:12px}
    .acx-table.apx-up thead th{text-transform:uppercase;letter-spacing:.04em;font-size:11px;font-weight:700}
    .apx-bigempty{display:none;text-align:center;padding:48px 16px 56px;color:#64748b}
    .apx-bigempty.is-on{display:block}
    .apx-bigempty svg{width:30px;height:30px;color:#6f8aa6;margin-bottom:10px}
    .apx-bigempty strong{display:block;margin-bottom:6px;font-size:16px;font-weight:700;color:#1e3a5f}
    .apx-bigempty p{margin:0 0 16px;font-size:13px}
    @media (max-width:767px){.apx-sub{padding:0 14px 12px}}
</style>
