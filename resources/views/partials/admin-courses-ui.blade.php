{{-- KIỂU CHUNG CHO MÀN "KHÓA & LỚP" CỦA ADMIN (danh sách, chi tiết khóa, form tạo/sửa khóa & lớp).

     SỬA 8/10 (khách: "cập nhật lại UI màn khóa và lớp theo source mới, logic giữ nguyên, chỉ lấy phong
     cách UI") — chuyển phong cách của education-main/src/components/AdminCourses.jsx +
     adminCourses.css sang Blade: bảng .ac-table với ô định danh (ảnh nhỏ + tên + dòng phụ), huy hiệu
     trạng thái dạng chữ nhật bo nhẹ, thanh lọc dạng "viên thuốc" có số đếm, thẻ .ac-card nền xanh nhạt,
     thanh hành động đáy form, danh sách lớp dạng nút có mũi tên.

     CHỈ LÀ KIỂU: không route, không tên field, không truy vấn nào đổi. Server không build lại
     Tailwind nên mọi thứ nằm ở khối <style> này, tiền tố .acx- (không đụng class cũ của app.css). --}}
<style>
    .acx-wrap{display:flex;flex-direction:column;gap:16px;min-width:0}
    .acx-back{display:inline-flex;align-items:center;gap:4px;font-size:13px;font-weight:500;color:#64748b;text-decoration:none;margin-bottom:2px}
    .acx-back:hover{color:#2563eb}

    /* ===== Nút ===== */
    .acx-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:40px;padding:8px 14px;border-radius:10px;border:1px solid #cbdcec;background:#fff;color:#345b80;font-size:13px;font-weight:600;line-height:1.2;text-decoration:none;cursor:pointer;transition:background .15s,border-color .15s,color .15s}
    .acx-btn:hover{background:#f0f7ff;border-color:#93c5fd;color:#1d4ed8}
    .acx-btn--primary{background:#2563eb;border-color:#2563eb;color:#fff;box-shadow:0 2px 6px rgba(37,99,235,.22)}
    .acx-btn--primary:hover{background:#1d4ed8;border-color:#1d4ed8;color:#fff}
    .acx-btn--danger{background:#e11d48;border-color:#e11d48;color:#fff}
    .acx-btn--danger:hover{background:#be123c;border-color:#be123c;color:#fff}
    .acx-btn--sm{min-height:34px;padding:6px 11px;font-size:12px;border-radius:9px}
    .acx-btn svg{width:15px;height:15px}

    /* ===== Thẻ / khung ===== */
    .acx-panel{min-width:0;border-radius:24px;border:1px solid #e0f2fe;background:#fff;box-shadow:0 3px 12px rgba(25,90,150,.04);overflow:hidden}
    .acx-panel__pad{padding:18px 20px}
    .acx-card{min-width:0;border:1px solid #d7e3f0;border-radius:16px;background:#eaf2fb;padding:18px}
    .acx-card--white{background:#fff;border-color:#e0f2fe;box-shadow:0 3px 12px rgba(25,90,150,.04);border-radius:24px;padding:20px}
    .acx-card--mint{border-color:#d4e5e1;background:#edf5f2}
    .acx-card h2,.acx-card h3{display:flex;align-items:center;gap:8px;margin:0 0 12px;font-size:15px;font-weight:700;color:#294c70}
    .acx-card--mint h2,.acx-card--mint h3{color:#315d56}
    .acx-card--white h2,.acx-card--white h3{color:#0f172a}
    .acx-card h2 svg,.acx-card h3 svg{width:17px;height:17px;flex-shrink:0}
    .acx-grid{display:grid;gap:16px;grid-template-columns:minmax(0,1fr)}
    @media (min-width:1024px){.acx-grid--main{grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);align-items:start}}
    .acx-stack{display:flex;flex-direction:column;gap:16px;min-width:0}

    /* ===== Đầu trang chi tiết / form ===== */
    .acx-head{display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px;border-radius:24px;border:1px solid #e0f2fe;background:linear-gradient(135deg,#f0f9ff,#fff 55%,#eff6ff);box-shadow:0 3px 12px rgba(25,90,150,.04);padding:20px}
    .acx-head__id{display:flex;align-items:center;gap:14px;min-width:0}
    .acx-avatar{display:grid;place-items:center;flex-shrink:0;width:48px;height:48px;border-radius:50%;border:1px solid #bfdbfe;background:#eff6ff;color:#1d4ed8}
    .acx-avatar svg{width:22px;height:22px}
    .acx-head__thumb{flex-shrink:0;width:96px;height:60px;border-radius:12px;object-fit:cover;border:1px solid #d7e3f0;background:#fff}
    .acx-head h1{margin:0;font-size:20px;line-height:1.35;font-weight:700;color:#0f172a;overflow-wrap:anywhere}
    .acx-head__meta{display:flex;flex-wrap:wrap;align-items:center;gap:6px 10px;margin-top:6px;font-size:12px;color:#60778d}
    .acx-head__actions{display:flex;flex-wrap:wrap;gap:8px}
    @media (min-width:1024px){.acx-head h1{font-size:22px}}

    /* ===== Huy hiệu trạng thái ===== */
    .acx-badge{display:inline-flex;align-items:center;padding:3px 8px;border-radius:6px;font-size:11px;font-weight:600;white-space:nowrap;background:#edf1f6;color:#586e85}
    .acx-badge--ok{color:#236654;background:#e4f3eb}
    .acx-badge--warn{color:#9a6a12;background:#fdf1d8}
    .acx-badge--off{color:#945463;background:#f9ebef}

    /* ===== Danh sách ===== */
    .acx-seg{display:flex;flex-wrap:wrap;gap:8px;padding:16px 20px 0}
    .acx-seg a{display:inline-flex;align-items:center;gap:8px;min-height:38px;padding:0 14px;border-radius:999px;border:1px solid #d7e3f0;background:#fff;color:#4b627a;font-size:13px;font-weight:600;text-decoration:none;transition:background .15s,border-color .15s}
    .acx-seg a:hover{background:#f0f7ff;border-color:#93c5fd}
    .acx-seg a span{display:inline-grid;place-items:center;min-width:22px;height:22px;padding:0 6px;border-radius:999px;background:#eef3f9;color:#587089;font-size:11px;font-weight:700}
    .acx-seg a[aria-current="page"]{background:#2563eb;border-color:#2563eb;color:#fff}
    .acx-seg a[aria-current="page"] span{background:rgba(255,255,255,.22);color:#fff}
    .acx-toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:12px;padding:16px 20px}
    .acx-search{position:relative;flex:1 1 260px;min-width:0}
    .acx-search svg{position:absolute;left:12px;top:50%;width:17px;height:17px;transform:translateY(-50%);color:#8aa0b6;pointer-events:none}
    .acx-field,.acx-search input{min-height:40px;border:1px solid #bdcbdc;border-radius:10px;background:#fff;color:#273f58;font-size:14px;padding:8px 12px}
    .acx-search input{width:100%;padding-left:38px;padding-right:34px}
    .acx-field:focus,.acx-search input:focus{outline:0;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.09)}
    .acx-filter{display:flex;align-items:center;gap:8px;font-size:12px;font-weight:600;color:#587089}
    .acx-filter select{max-width:210px;min-width:0}
    .acx-clear{position:absolute;right:6px;top:50%;transform:translateY(-50%);display:none;place-items:center;width:26px;height:26px;border:0;border-radius:6px;background:transparent;color:#8aa0b6;cursor:pointer}
    .acx-clear:hover{background:#eef3f9;color:#475569}
    .acx-clear.is-on{display:grid}
    .acx-scroll{overflow-x:auto}
    .acx-table{width:100%;border-collapse:collapse;text-align:left;font-size:13px}
    .acx-table thead th{padding:12px 18px;background:#f1f6fc;color:#526b85;font-size:12px;font-weight:600;white-space:nowrap;border-block:1px solid #dfe8f2}
    .acx-table thead th:first-child{width:42%}
    .acx-table thead th:last-child{text-align:right}
    .acx-table tbody th,.acx-table tbody td{padding:14px 18px;border-bottom:1px solid #e8eef5;vertical-align:middle;font-weight:400;text-align:left}
    .acx-table tbody td:last-child{text-align:right}
    .acx-table tbody tr:nth-child(even){background:#f8fbfe}
    .acx-table tbody tr:hover{background:#eff5fd}
    .acx-table small{display:block;margin-top:4px;font-size:12px;line-height:1.6;color:#637b92}
    .acx-table strong{font-weight:600;color:#365673}
    .acx-ident{display:flex;align-items:center;gap:12px}
    .acx-ident>div{min-width:0}
    .acx-thumb{flex-shrink:0;width:64px;height:40px;border-radius:8px;object-fit:cover;background:#e8f1fb;border:1px solid #d7e3f0}
    .acx-thumb--blank{display:grid;place-items:center;color:#7aa2cf}
    .acx-thumb--blank svg{width:18px;height:18px}
    .acx-name{display:block;font-size:14px;font-weight:600;line-height:1.5;color:#1e3a5f;text-decoration:none;overflow-wrap:anywhere}
    .acx-name:hover{color:#2563eb;text-decoration:underline}
    .acx-missing{color:#976817!important}
    .acx-actions{display:inline-flex;align-items:center;justify-content:flex-end;gap:6px;flex-wrap:wrap}
    .acx-actions form{display:inline;margin:0}
    .acx-link{display:inline-flex;align-items:center;min-height:32px;padding:0 10px;border:1px solid #cbdcec;border-radius:8px;background:#fff;color:#2563eb;font-size:12px;font-weight:600;text-decoration:none;cursor:pointer;transition:background .15s}
    .acx-link:hover{background:#eff6ff}
    .acx-link--del{color:#be123c;border-color:#f3cdd5}
    .acx-link--del:hover{background:#fff1f3}
    .acx-empty{display:none;text-align:center;padding:36px 16px;color:#64748b}
    .acx-empty.is-on{display:block}
    .acx-empty strong{display:block;margin-bottom:4px;font-size:15px;color:#334155}
    .acx-pager{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;padding:14px 20px;border-top:1px solid #e8eef5;font-size:12px;color:#587089}
    .acx-pager[hidden]{display:none}
    .acx-sr{position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
    .acx-pager>div{display:flex;align-items:center;gap:10px}
    .acx-pager select{min-height:32px;padding:3px 8px;font-size:12px}
    .acx-pager button{display:grid;place-items:center;width:34px;height:34px;border:1px solid #cbdcec;border-radius:9px;background:#fff;color:#345b80;cursor:pointer}
    .acx-pager button:hover:not(:disabled){background:#eff6ff;border-color:#93c5fd}
    .acx-pager button:disabled{opacity:.4;cursor:not-allowed}
    .acx-pager button svg{width:16px;height:16px}
    .acx-foot{padding:0 20px 16px;font-size:12px;color:#7a8ea3}
    @media (max-width:1100px){.acx-table tbody th,.acx-table tbody td{padding:12px}}
    @media (max-width:767px){
        .acx-seg,.acx-toolbar,.acx-pager{padding-left:14px;padding-right:14px}
        .acx-table tr>:nth-child(2){display:none}
        .acx-table thead th:first-child{width:auto}
        .acx-table thead th,.acx-table tbody th,.acx-table tbody td{padding:12px 8px;overflow-wrap:anywhere}
        .acx-thumb{display:none}
        .acx-actions{flex-direction:column;align-items:stretch}
        .acx-filter select{max-width:100%}
        .acx-filter{flex:1 1 100%}
        .acx-filter select{flex:1}
    }

    /* ===== Chỉ số tự tính + danh sách lớp ===== */
    .acx-stats{display:grid;gap:12px;grid-template-columns:repeat(2,minmax(0,1fr))}
    .acx-stat{border:1px solid #e0f2fe;border-radius:18px;background:#fff;padding:14px 16px;box-shadow:0 3px 12px rgba(25,90,150,.04)}
    .acx-stat p{margin:0;font-size:12px;font-weight:600;color:#587089}
    .acx-stat strong{display:block;margin-top:6px;font-size:26px;line-height:1;font-weight:800;color:#0f172a}
    .acx-child{display:grid;gap:10px}
    .acx-child a{display:flex;align-items:center;gap:12px;padding:14px;border:1px solid #d8e5f2;border-radius:12px;background:#edf4fc;color:#345674;text-decoration:none;transition:border-color .15s,background .15s}
    .acx-child a:hover{border-color:#93c5fd;background:#e6f0fc}
    .acx-child a>span:first-child{flex:1;min-width:0}
    .acx-child strong{display:block;font-size:13px;font-weight:600;overflow-wrap:anywhere}
    .acx-child small{display:block;margin-top:4px;font-size:12px;color:#647c92}
    .acx-child .acx-ok{color:#059669;font-weight:700}
    .acx-child .acx-warn{color:#d97706;font-weight:700}
    .acx-child svg.acx-go{flex-shrink:0;width:17px;height:17px;color:#7f93a8}
    .acx-cardtop{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}
    .acx-cardtop h2,.acx-cardtop h3{margin:0}
    .acx-dl{display:grid;gap:0;margin:0}
    .acx-dl>div{padding:10px 0;border-bottom:1px solid #d4e5e1;font-size:13px}
    .acx-dl>div:last-child{border-bottom:0}
    .acx-dl dt{font-size:12px;color:#5d786f}
    .acx-dl dd{margin:3px 0 0;color:#273f58;overflow-wrap:anywhere}
    .acx-note{margin:0;font-size:13px;line-height:1.7;color:#64748b}
    .acx-rich{font-size:13px;line-height:1.7;color:#475569}
    .acx-cover{display:block;width:100%;aspect-ratio:16/9;object-fit:cover;border-radius:12px;border:1px solid #d7e3f0;background:#fff;margin-bottom:12px}

    /* ===== Form tạo / sửa ===== */
    .acx-form .admin-input,
    .acx-form input[type="text"],.acx-form input[type="number"],.acx-form input[type="date"],.acx-form input[type="url"],.acx-form input[type="email"],.acx-form textarea{border:1px solid #bdcbdc;border-radius:10px;background:#fff;color:#273f58}
    .acx-form .admin-input:focus,.acx-form textarea:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.09);outline:0}
    .acx-form label{color:#4b627a}
    .acx-form>*+*{margin-top:16px}
    .acx-footer{position:sticky;bottom:0;z-index:5;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;margin-top:20px!important;padding:12px 16px;border:1px solid #d7e2ec;border-radius:16px;background:rgba(237,243,250,.96);backdrop-filter:blur(4px)}
    .acx-footer small{font-size:12px;color:#657d92}
    .acx-footer__btns{display:flex;flex-wrap:wrap;gap:8px}
    .acx-danger{border:1px solid #fbd0d9;border-radius:24px;background:#fff7f8;padding:20px}
    .acx-danger h3{display:flex;align-items:center;gap:8px;margin:0 0 8px;font-size:14px;font-weight:700;color:#be123c}
    .acx-danger h3 svg{width:16px;height:16px}
    .acx-danger p{margin:0 0 10px;font-size:13px;line-height:1.65;color:#64748b}
    .acx-danger__toggle{border:0;background:transparent;padding:0;font-size:12px;font-weight:700;color:#be123c;cursor:pointer}
    .acx-danger__toggle:hover{text-decoration:underline}
    .acx-danger form{margin-top:12px}
    .acx-danger textarea{width:100%;margin-top:4px}
    .acx-tips{display:grid;gap:12px}
    .acx-tips>div{display:flex;align-items:flex-start;gap:10px;font-size:13px;line-height:1.65;color:#527067}
</style>
