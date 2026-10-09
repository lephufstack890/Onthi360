{{-- SỬA 9/10 (khách: "check UI khi tạo câu hỏi và cập nhật admin… giống source mới") — style dùng
     CHUNG cho 2 màn Tạo / Sửa câu hỏi ở khu Admin, dựng theo CompactContentEditor của source mới
     (education-main): banner, lưới 2 cột (nội dung chính + cột phân loại/hiển thị), ô nhập gói ZIP
     viền đứt, nút xanh lá, khối "Bộ test".

     CHỈ là CSS có tiền tố .qe- — không đụng tới class của chỗ khác. Không dùng class Tailwind
     chưa được biên dịch (server không chạy Vite). --}}
<style>
    .qe { --qe-border:#dfeaf5; --qe-soft:#f6faff; --qe-text:#1a3865; --qe-muted:#64748b; --qe-blue:#215cf4; --qe-green:#16804a; }
    .qe-back { display:inline-flex; align-items:center; gap:4px; margin:0 0 14px; color:#64748b; font-size:13px; text-decoration:none; }
    .qe-back:hover { color:#2563eb; }
    .qe-hero-gap { margin-bottom:18px; }

    /* Khung 2 cột: nội dung chính + cột phân loại */
    .qe-layout { display:grid; grid-template-columns:minmax(0,1fr) 300px; gap:20px; align-items:start; }
    .qe-main { display:flex; flex-direction:column; gap:18px; min-width:0; }
    .qe-aside { position:sticky; top:84px; display:flex; flex-direction:column; gap:0; min-width:0; background:#fff; border:1px solid var(--qe-border); border-radius:18px; box-shadow:0 4px 16px rgba(33,60,89,.05); overflow:hidden; }
    .qe-aside-body { display:flex; flex-direction:column; gap:16px; padding:20px 20px 18px; }
    .qe-aside-actions { display:flex; flex-direction:column; gap:8px; padding:14px 20px 18px; border-top:1px solid #e7edf5; background:#fff; }
    .qe-aside-actions small { display:block; text-align:center; font-size:11px; color:#61778d; }
    .qe-panel { background:#fff; border:1px solid var(--qe-border); border-radius:18px; padding:22px; box-shadow:0 4px 16px rgba(33,60,89,.04); min-width:0; }
    .qe-panel > .qe-h { display:flex; align-items:center; gap:8px; margin:0 0 16px; padding-bottom:12px; border-bottom:1px solid #e7edf5; font-size:16px; font-weight:700; color:var(--qe-text); }
    .qe-panel > .qe-h svg, .qe-aside-body > .qe-h svg { color:#3b82f6; flex-shrink:0; }
    .qe-aside-body > .qe-h { display:flex; align-items:center; gap:8px; margin:0; padding-bottom:12px; border-bottom:1px solid #e7edf5; font-size:16px; font-weight:700; color:var(--qe-text); }
    .qe-stack { display:flex; flex-direction:column; gap:16px; }
    .qe-note { margin:0; font-size:12px; color:#7b8da3; line-height:1.55; }
    .qe-sub { margin:0 0 10px; font-size:13px; font-weight:650; color:#405873; }

    /* Nút */
    .qe-btn { display:inline-flex; align-items:center; justify-content:center; gap:7px; min-height:40px; padding:8px 16px; border:1px solid #cfdcec; border-radius:12px; background:#fff; color:#2153b4; font-size:13px; font-weight:700; line-height:1; cursor:pointer; text-decoration:none; transition:background .14s,border-color .14s,color .14s; }
    .qe-btn:hover:not(:disabled) { background:#eef4ff; border-color:#bdd3fa; }
    .qe-btn:disabled { opacity:.6; cursor:default; }
    .qe-btn:focus-visible { outline:3px solid #bfdbfe; outline-offset:2px; }
    .qe-btn--primary { background:#2563eb; border-color:#2563eb; color:#fff; box-shadow:0 4px 12px rgba(37,99,235,.22); }
    .qe-btn--primary:hover:not(:disabled) { background:#1d4ed8; border-color:#1d4ed8; }
    .qe-btn--green { background:var(--qe-green); border-color:var(--qe-green); color:#fff; }
    .qe-btn--green:hover:not(:disabled) { background:#11653a; border-color:#11653a; }
    .qe-btn--green:focus-visible { outline-color:#a7e4bd; }
    .qe-btn--ghost { min-height:34px; padding:6px 12px; font-size:12px; }
    .qe-btn--danger { color:#be123c; }
    .qe-btn--danger:hover:not(:disabled) { background:#fff1f2; border-color:#fecdd3; }
    .qe-btn svg { flex-shrink:0; }

    /* Ô nhập gói ZIP */
    .qe-zip { display:flex; align-items:center; gap:14px; padding:18px 20px; border:1.5px dashed #a9c9f1; border-radius:16px; background:var(--qe-soft); color:#5e8cc5; }
    .qe-zip-ico { display:grid; place-items:center; width:42px; height:42px; flex-shrink:0; border-radius:12px; background:#e3efff; color:#2970dd; }
    .qe-zip-body { flex:1; min-width:0; }
    .qe-zip-body strong { display:block; color:#325d92; font-size:14px; }
    .qe-zip-body p { margin:4px 0 0; color:#6f87a3; font-size:12px; line-height:1.55; }
    .qe-zip details { margin-top:6px; }
    .qe-zip summary { cursor:pointer; color:#526b87; font-size:12px; }
    .qe-zip summary:hover { color:#215cf4; }
    .qe-zip details p { margin-top:6px; }
    .qe-zip details code, .qe-note code { padding:1px 5px; border-radius:5px; background:#eaf1fb; color:#2b4f86; font-size:11px; }
    .qe-zip.is-drag { background:#e9f3ff; border-color:#2970dd; }

    /* Lưới ô nhập */
    .qe-grid2 { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
    .qe-field > label, .qe-label { display:block; margin-bottom:6px; font-size:13px; font-weight:600; color:#405873; }
    .qe-field small { display:block; margin-top:5px; font-size:12px; color:#7b8da3; }
    .qe-readonly { padding:10px 2px; font-size:13px; color:#526b87; }
    .qe-readonly small { color:#94a3b8; }

    /* Phương án trắc nghiệm */
    .qe-opts { display:flex; flex-direction:column; gap:8px; }
    .qe-opt { display:flex; align-items:center; gap:10px; padding:6px 10px 6px 12px; border:1px solid #e1eaf6; border-radius:12px; background:#fafcff; }
    .qe-opt:has(input[type=radio]:checked) { border-color:#86d1a4; background:#effaf3; }
    .qe-opt b { width:22px; flex-shrink:0; color:#526b87; font-size:13px; }
    .qe-opt input[type=text] { flex:1; min-width:0; min-height:38px; border:0; background:transparent; font-size:13px; color:var(--qe-text); outline:none; }

    /* Khối Bộ test */
    .qe-tests-head { display:flex; align-items:center; flex-wrap:wrap; gap:10px 14px; margin-bottom:12px; }
    .qe-tests-head .qe-count { display:inline-flex; align-items:center; min-height:26px; padding:0 10px; border-radius:999px; background:#eaf7ef; color:#16804a; font-size:12px; font-weight:700; }
    .qe-tests-head .qe-count.is-empty { background:#f1f5f9; color:#64748b; }
    .qe-tests-head .qe-spacer { flex:1; }
    .qe-drop { display:flex; align-items:center; gap:14px; padding:16px 18px; border:1.5px dashed #a9c9f1; border-radius:14px; background:var(--qe-soft); cursor:pointer; transition:background .14s,border-color .14s; }
    .qe-drop:hover, .qe-drop.is-drag { background:#e9f3ff; border-color:#2970dd; }
    .qe-drop > div { flex:1; min-width:0; }
    .qe-drop strong { display:block; font-size:13px; color:#325d92; }
    .qe-drop span { display:block; margin-top:3px; font-size:12px; color:#6f87a3; }
    .qe-drop code { padding:1px 5px; border-radius:5px; background:#eaf1fb; color:#2b4f86; font-size:11px; }
    .qe-mode { display:flex; flex-wrap:wrap; align-items:center; gap:6px 16px; margin-bottom:10px; font-size:12px; color:#526b87; }
    .qe-mode label { display:inline-flex; align-items:center; gap:6px; cursor:pointer; }
    .qe-report { margin-top:10px; padding:10px 12px; border-radius:12px; font-size:12px; line-height:1.6; }
    .qe-report.is-ok { background:#effaf3; border:1px solid #b9e6cb; color:#14653c; }
    .qe-report.is-warn { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .qe-report ul { margin:4px 0 0; padding-left:18px; list-style:disc; }
    .qe-manifest { margin-top:12px; max-height:360px; overflow:auto; border:1px solid #e1eaf6; border-radius:12px; background:#fafcff; }
    .qe-test { border-bottom:1px solid #edf2f8; }
    .qe-test:last-child { border-bottom:0; }
    .qe-test-row { display:flex; align-items:center; gap:10px; padding:9px 12px; font-size:12px; }
    .qe-test-no { width:26px; flex-shrink:0; text-align:center; color:#8a9fb8; font-weight:700; font-variant-numeric:tabular-nums; }
    .qe-test-files { flex:1; min-width:0; display:flex; align-items:center; flex-wrap:wrap; gap:4px 8px; }
    .qe-test-files code { padding:2px 7px; border-radius:6px; font-size:12px; font-weight:600; overflow-wrap:anywhere; }
    .qe-test-files code.in { background:#e8f1ff; color:#1f4fa8; }
    .qe-test-files code.out { background:#e6f7ee; color:#14653c; }
    .qe-test-files i { font-style:normal; color:#94a3b8; }
    .qe-test-meta { flex-shrink:0; color:#8a9fb8; font-size:11px; font-variant-numeric:tabular-nums; }
    .qe-test-act { flex-shrink:0; display:flex; gap:2px; }
    .qe-icon-btn { display:grid; place-items:center; width:30px; height:30px; border:0; border-radius:8px; background:transparent; color:#6f87a3; cursor:pointer; }
    .qe-icon-btn:hover { background:#e8f1ff; color:#215cf4; }
    .qe-icon-btn.is-danger:hover { background:#fff1f2; color:#be123c; }
    .qe-test-edit { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; padding:2px 12px 12px 48px; }
    .qe-test-edit label { display:block; margin-bottom:4px; font-size:11px; font-weight:700; color:#526b87; }
    .qe-test-edit textarea { width:100%; min-height:96px; padding:8px 10px; border:1px solid #dbe6f3; border-radius:10px; background:#fff; font:12px/1.5 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; color:#1a3865; resize:vertical; outline:none; }
    .qe-test-edit textarea:focus { border-color:#93c5fd; box-shadow:0 0 0 2px #bfdbfe; }
    .qe-test-edit textarea[readonly] { background:#f8fafc; color:#64748b; }
    .qe-test-edit p { grid-column:1/-1; margin:0; font-size:11px; color:#8a9fb8; }
    .qe-tests-empty { margin-top:12px; padding:22px 16px; text-align:center; border:1px solid #e1eaf6; border-radius:12px; background:#fafcff; color:#8a9fb8; font-size:12px; }
    .qe-tests-foot { margin-top:10px; display:flex; flex-wrap:wrap; gap:8px 12px; align-items:center; font-size:12px; color:#7b8da3; }
    .qe-dirty { color:#b45309; font-weight:600; }

    /* Tệp đính kèm */
    .qe-file-row { display:flex; flex-direction:column; gap:6px; }
    .qe-cur { display:flex; flex-wrap:wrap; align-items:center; gap:10px; }
    .qe-cur a { display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border:1px solid #dbe6f3; border-radius:10px; color:#526b87; font-size:12px; text-decoration:none; }
    .qe-cur a:hover { border-color:#bdd3fa; color:#215cf4; }
    .qe-chip { display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border:1px solid #dbe6f3; border-radius:10px; font-size:12px; color:#526b87; background:#fff; }
    .qe-warn { display:flex; gap:8px; padding:12px 14px; border:1px solid #fde68a; border-radius:12px; background:#fffbeb; color:#92400e; font-size:12px; line-height:1.55; }

    @media (max-width:1100px) { .qe-layout { grid-template-columns:minmax(0,1fr) 272px; gap:16px; } }
    @media (max-width:900px) {
        .qe-layout { grid-template-columns:minmax(0,1fr); }
        .qe-aside { position:static; }
    }
    @media (max-width:640px) {
        .qe-panel { padding:16px; border-radius:16px; }
        .qe-grid2 { grid-template-columns:minmax(0,1fr); }
        .qe-zip { flex-wrap:wrap; padding:14px; }
        .qe-zip .qe-btn { width:100%; }
        .qe-test-row { flex-wrap:wrap; }
        .qe-test-meta { display:none; }
        .qe-test-edit { grid-template-columns:minmax(0,1fr); padding-left:12px; }
        .qe-drop { flex-wrap:wrap; }
    }
</style>
