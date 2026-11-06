{{-- SỬA 6/11 — CSS chung của không gian lớp (tiền tố csp-). Tailwind không build lại trên máy chủ nên viết CSS thường. --}}
<style>
    .csp { --ink: #0F2F57; --mut: #64748B; --line: #E5EDF5; --g: #176B50; --b: #2563EB; }
    .csp .tcx-panel { padding: 0; border: 0; border-radius: 0; background: none; box-shadow: none; }
    .csp-back { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 10px; font-size: 13px; font-weight: 700; color: #475569; text-decoration: none; }
    .csp-back:hover { color: var(--g); }
    .csp-back svg { width: 16px; height: 16px; }
    .csp-hero { position: relative; display: flex; flex-wrap: wrap; align-items: center; gap: 16px 20px; padding: 20px 22px; border-radius: 22px; color: #fff; overflow: hidden; background: linear-gradient(120deg, #0F5C45 0%, #176B50 45%, #1E7FA6 100%); box-shadow: 0 18px 40px rgba(15, 60, 70, .16); }
    .csp-hero::after { content: ""; position: absolute; right: -70px; top: -90px; width: 340px; height: 340px; border-radius: 50%; background: radial-gradient(circle, rgba(255, 255, 255, .16), transparent 65%); pointer-events: none; }
    .csp-cover { display: grid; place-items: center; width: 84px; height: 84px; flex: none; overflow: hidden; border-radius: 20px; background: rgba(255, 255, 255, .14); border: 1px solid rgba(255, 255, 255, .25); font-size: 30px; font-weight: 900; }
    .csp-cover img { width: 100%; height: 100%; object-fit: cover; }
    .csp-hmain { flex: 1 1 380px; min-width: 0; z-index: 1; }
    .csp-hero h1 { margin: 6px 0 8px; font-size: 21px; line-height: 1.25; font-weight: 900; letter-spacing: -.01em; color: #fff; }
    .csp-chips { display: flex; flex-wrap: wrap; gap: 6px 8px; }
    .csp-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; background: rgba(255, 255, 255, .16); font-size: 12px; font-weight: 700; color: #fff; }
    .csp-chip svg { width: 14px; height: 14px; }
    .csp-chip.gold { background: #FDE68A; color: #7C4A03; }
    .csp-hside { display: flex; flex-direction: column; align-items: flex-end; gap: 10px; z-index: 1; margin-left: auto; }
    .csp-code { padding: 7px 12px; border-radius: 12px; background: rgba(255, 255, 255, .14); font-size: 12px; font-weight: 800; }
    .csp-code b { margin-left: 6px; font-size: 14px; letter-spacing: .1em; }
    .csp-prog { width: 220px; font-size: 12px; font-weight: 700; }
    .csp-prog div { display: flex; justify-content: space-between; opacity: .92; }
    .csp-bar { height: 8px; margin-top: 6px; overflow: hidden; border-radius: 9px; background: rgba(255, 255, 255, .22); }
    .csp-bar i { display: block; height: 100%; border-radius: 9px; background: #FDE68A; }
    .csp-tabs { display: flex; gap: 4px; margin: 14px 0; padding: 6px; overflow-x: auto; border: 1px solid var(--line); border-radius: 18px; background: #fff; box-shadow: 0 6px 18px rgba(15, 42, 74, .05); scrollbar-width: thin; }
    .csp-tab { flex: 1 0 auto; display: inline-flex; align-items: center; justify-content: center; gap: 7px; height: 42px; padding: 0 14px; border-radius: 13px; font-size: 13px; font-weight: 700; color: #4B6070; white-space: nowrap; text-decoration: none; transition: background .15s, color .15s; }
    .csp-tab:hover { background: #F2F7F5; color: var(--ink); }
    .csp-tab svg { width: 16px; height: 16px; }
    .csp-tab em { padding: 1px 7px; border-radius: 999px; background: #FEE2E2; color: #DC2626; font-style: normal; font-size: 10.5px; font-weight: 800; }
    .csp-tab.on { background: linear-gradient(135deg, #176B50, #1F8A63); color: #fff; box-shadow: 0 8px 18px rgba(23, 107, 80, .25); }
    .csp-tab.on em { background: rgba(255, 255, 255, .25); color: #fff; }
    .csp-card { padding: 18px; border: 1px solid var(--line); border-radius: 18px; background: #fff; box-shadow: 0 6px 18px rgba(15, 42, 74, .04); }
    .csp-card + .csp-card { margin-top: 14px; }
    .csp-ch { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 12px; margin-bottom: 14px; }
    .csp-ch h3 { margin: 0; font-size: 15px; font-weight: 800; color: var(--ink); }
    .csp-ch p { margin: 2px 0 0; font-size: 12px; color: var(--mut); }
    .csp-ch .grow { flex: 1 1 260px; min-width: 0; }
    .csp-ic { display: grid; place-items: center; width: 38px; height: 38px; flex: none; border-radius: 12px; background: #EEF4FF; color: var(--b); }
    .csp-ic svg { width: 18px; height: 18px; }
    .csp-ic.g { background: #E8F3EE; color: var(--g); } .csp-ic.v { background: #F3EEFF; color: #7C3AED; } .csp-ic.o { background: #FFF1E6; color: #EA580C; } .csp-ic.a { background: #FFF7E6; color: #B45309; }
    .csp-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 38px; padding: 0 14px; border: 1px solid #DCE6F0; border-radius: 11px; background: #fff; font: inherit; font-size: 12.5px; font-weight: 800; color: #334155; white-space: nowrap; cursor: pointer; text-decoration: none; transition: all .15s; }
    .csp-btn svg { width: 16px; height: 16px; }
    .csp-btn:hover { border-color: #B8CCE0; background: #F8FAFC; }
    .csp-btn.sm { height: 32px; padding: 0 11px; font-size: 12px; }
    .csp-btn.icon { width: 34px; padding: 0; }
    .csp-btn.pri { border: 0; background: linear-gradient(135deg, #2563EB, #1D4ED8); color: #fff; box-shadow: 0 8px 18px rgba(37, 99, 235, .22); }
    .csp-btn.grn { border: 0; background: linear-gradient(135deg, #22C55E, #16A34A); color: #fff; box-shadow: 0 8px 18px rgba(22, 163, 74, .22); }
    .csp-btn.amb { border-color: #FCD9A5; background: #FFF7E8; color: #B45309; }
    .csp-btn.red { border-color: #FECACA; background: #FFF5F5; color: #DC2626; }
    .csp-btn:disabled { opacity: .5; cursor: not-allowed; }
    .csp-pill { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 999px; font-size: 11.5px; font-weight: 800; white-space: nowrap; }
    .csp-pill svg { width: 12px; height: 12px; }
    .p-ok, .p-present { background: #DCFCE7; color: #15803D; } .p-no, .p-absent { background: #FEE2E2; color: #DC2626; }
    .p-ex, .p-excused { background: #FEF3C7; color: #B45309; } .p-late { background: #DBEAFE; color: #1D4ED8; } .p-gray { background: #F1F5F9; color: #64748B; }
    .csp-av { display: grid; place-items: center; width: 32px; height: 32px; flex: none; overflow: hidden; border-radius: 50%; font-size: 11.5px; font-weight: 800; color: #fff; }
    .csp-av img { width: 100%; height: 100%; object-fit: cover; }
    .csp-av.h0 { background: linear-gradient(135deg, #2563EB, #7C3AED); } .csp-av.h1 { background: linear-gradient(135deg, #16A34A, #0EA5E9); }
    .csp-av.h2 { background: linear-gradient(135deg, #F97316, #EF4444); } .csp-av.h3 { background: linear-gradient(135deg, #0EA5E9, #6366F1); }
    .csp-av.h4 { background: linear-gradient(135deg, #DB2777, #F97316); } .csp-av.h5 { background: linear-gradient(135deg, #0D9488, #22C55E); }
    .csp-av.h6 { background: linear-gradient(135deg, #7C3AED, #EC4899); } .csp-av.h7 { background: linear-gradient(135deg, #EA580C, #CA8A04); }
    .csp-who { display: flex; align-items: center; gap: 9px; min-width: 0; font-weight: 700; color: var(--ink); }
    .csp-who b { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .csp-empty { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 30px 14px; border: 1px dashed #D5E1EA; border-radius: 14px; background: #FAFCFD; text-align: center; font-size: 12.5px; color: #7B8EA0; }
    .csp-empty svg { width: 24px; height: 24px; color: #A3B6CC; }
    .csp-empty b { font-size: 13.5px; color: var(--ink); }
    .csp-row { display: flex; align-items: center; gap: 12px; padding: 11px 12px; border: 1px solid var(--line); border-radius: 14px; background: #fff; }
    .csp-row + .csp-row { margin-top: 8px; }
    .csp-seg { display: inline-flex; flex-wrap: wrap; gap: 3px; padding: 3px; border-radius: 11px; background: #EEF3F7; }
    .csp-seg a, .csp-seg button { padding: 7px 12px; border: 0; border-radius: 9px; background: none; font: inherit; font-size: 12px; font-weight: 700; color: #4B6070; text-decoration: none; cursor: pointer; white-space: nowrap; }
    .csp-seg .on { background: #fff; color: var(--ink); box-shadow: 0 2px 6px rgba(15, 42, 74, .1); }
    .csp-inp { box-sizing: border-box; height: 38px; padding: 0 12px; border: 1px solid #D9E3EE; border-radius: 11px; background: #fff; font: inherit; font-size: 13px; color: #0F172A; }
    .csp-inp:focus { outline: 0; border-color: #3B82F6; box-shadow: 0 0 0 4px rgba(59, 130, 246, .12); }
    textarea.csp-inp { height: auto; padding: 10px 12px; line-height: 1.55; resize: vertical; }
    .csp-lb { display: block; margin: 0 0 6px 2px; font-size: 12.5px; font-weight: 800; color: #1E293B; }
    .csp-sw { position: relative; width: 42px; height: 24px; flex: none; padding: 0; border: 0; border-radius: 12px; background: #CBD5E1; cursor: pointer; transition: background .15s; }
    .csp-sw::after { content: ""; position: absolute; top: 3px; left: 3px; width: 18px; height: 18px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(0, 0, 0, .2); transition: left .15s; }
    .csp-sw.on { background: #16A34A; } .csp-sw.on::after { left: 21px; }
    .csp-sw:disabled { opacity: .5; cursor: not-allowed; }
    .csp-grid { display: grid; gap: 14px; }
    .csp-stats { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    @media (max-width: 1100px) { .csp-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 560px) { .csp-stats { grid-template-columns: minmax(0, 1fr); } }
    .csp-2 { grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); }
    .csp-2e { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    @media (max-width: 1000px) { .csp-2, .csp-2e { grid-template-columns: minmax(0, 1fr); } }
    .csp-note { display: flex; align-items: flex-start; gap: 8px; padding: 10px 12px; border-radius: 12px; background: #F5F9FF; border: 1px solid #E0EBFA; font-size: 12.5px; line-height: 1.5; color: #46607A; }
    .csp-note svg { width: 15px; height: 15px; flex: none; margin-top: 2px; }
    @media (max-width: 640px) {
        .csp-hero { padding: 16px; } .csp-hero h1 { font-size: 18px; } .csp-cover { width: 60px; height: 60px; font-size: 22px; border-radius: 16px; }
        .csp-hside { width: 100%; align-items: stretch; margin-left: 0; } .csp-prog { width: 100%; }
        .csp-tab { padding: 0 11px; font-size: 12.5px; }
        .csp-card { padding: 14px; }
    }
    @media print {
        header, .csp-back, .csp-hero, .csp-tabs, .csp-noprint, [data-toast-root] { display: none !important; }
        body { background: #fff !important; }
        .csp-card { border: 0; box-shadow: none; padding: 0; }
    }
</style>
