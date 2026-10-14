{{-- SỬA 14/10 — kiểu dáng các khối sửa câu Lập trình ở màn Sửa bài tập của tài liệu (CSS thường, server không build Tailwind). --}}
@once
<style>
    .ex-coding { display: grid; gap: 14px; }
    .ex-block { padding: 16px; border: 1px solid #DFEAF5; border-radius: 16px; background: #FAFCFF; }
    .ex-h { display: flex; align-items: center; gap: 8px; margin: 0 0 12px; font-size: 14.5px; font-weight: 700; color: #1A3865; }
    .ex-h svg { width: 17px; height: 17px; color: #3B82F6; }
    .ex-lbl { display: block; margin-bottom: 6px; font-size: 12.5px; font-weight: 600; color: #475569; }
    .ex-grid2 { display: grid; gap: 12px; }
    @media (min-width: 640px) { .ex-grid2 { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .ex-io { margin-top: 12px; }
    .ex-note { margin: 6px 0 0; font-size: 11.5px; line-height: 1.55; color: #7B8DA3; }
    .ex-note code { font-size: 11px; color: #1A3865; }
    .ex-files { display: grid; gap: 12px; }
    @media (min-width: 900px) { .ex-files { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    .ex-file { display: flex; flex-direction: column; gap: 8px; padding: 12px; border: 1px solid #E2ECF3; border-radius: 14px; background: #fff; }
    .ex-file__head { display: flex; align-items: center; gap: 7px; font-size: 13px; color: #1A3865; }
    .ex-file__head svg { width: 16px; height: 16px; color: #126F91; }
    .ex-file__cur { display: flex; flex-direction: column; gap: 4px; padding: 8px 10px; border-radius: 10px; background: #F1F7FB; font-size: 12px; }
    .ex-file__cur a { color: #2563EB; font-weight: 600; word-break: break-all; }
    .ex-file__rm { display: inline-flex; align-items: center; gap: 6px; color: #BE123C; font-size: 11.5px; }
    .ex-file__pick { display: flex; align-items: center; gap: 8px; padding: 9px 11px; border: 1.5px dashed #A9C6DD; border-radius: 11px; font-size: 12px; font-weight: 600; color: #2563EB; cursor: pointer; word-break: break-all; }
    .ex-file__pick:hover { border-color: #2563EB; background: #F5F9FF; }
    .ex-file__pick.has-file { border-style: solid; border-color: #2F8A6B; background: #EFFAF5; color: #207153; }
    .ex-file__pick svg { width: 15px; height: 15px; flex-shrink: 0; }
    .ex-sr { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); border: 0; }
</style>
@endonce
