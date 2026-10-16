{{-- SỬA 14/10 — kiểu dáng chung cho 2 khối "Thêm bài tập" và "Thêm học liệu" ở trang chi tiết tài liệu.
     CSS thường (không dùng lớp Tailwind tuỳ ý) vì máy chủ không build lại Tailwind. --}}
@once
<style>
    .oi-add { display: grid; gap: 14px; margin-top: 4px; padding: 16px; border: 1px dashed #BFD6EA; border-radius: 18px; background: #F7FAFD; }
    .oi-add__row { display: grid; gap: 12px; }
    @media (min-width: 768px) { .oi-add__row--2 { grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr); } .oi-add__row--pts { grid-template-columns: minmax(0, 1fr) 140px; } }
    .oi-add__lbl { display: block; margin-bottom: 6px; font-size: 12.5px; font-weight: 700; color: #334E68; }
    .oi-add__lbl small { font-weight: 500; color: #8193A3; }
    .oi-add__input { width: 100%; min-height: 42px; padding: 9px 12px; border: 1px solid #D5E3E9; border-radius: 12px; background: #fff; font-size: 13.5px; color: #183D5E; outline: none; transition: border-color .15s, box-shadow .15s; }
    .oi-add__input:focus { border-color: #2D7FA3; box-shadow: 0 0 0 4px #DDF1F6; }
    textarea.oi-add__input { min-height: 78px; line-height: 1.55; resize: vertical; }
    .oi-add__note { margin: 6px 0 0; font-size: 11.5px; color: #7A92A3; line-height: 1.5; }
    .oi-add__err { margin: 0; padding: 9px 12px; border-radius: 12px; background: #FFF1F2; color: #BE123C; font-size: 12.5px; font-weight: 600; }
    .oi-add__actions { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }

    .oi-kinds { display: grid; gap: 10px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    @media (min-width: 1024px) { .oi-kinds { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .oi-kind { display: flex; align-items: flex-start; gap: 10px; padding: 12px; border: 1.5px solid #DDEAF0; border-radius: 14px; background: #fff; text-align: left; cursor: pointer; transition: border-color .15s, box-shadow .15s, background-color .15s; }
    .oi-kind:hover { border-color: #9DC8D7; }
    .oi-kind.is-on { border-color: #2563EB; background: #EFF5FF; box-shadow: 0 0 0 3px #DBE7FF; }
    .oi-kind__ic { display: grid; flex-shrink: 0; place-items: center; width: 34px; height: 34px; border-radius: 10px; background: #EAF5F8; color: #126F91; }
    .oi-kind.is-on .oi-kind__ic { background: #2563EB; color: #fff; }
    .oi-kind__ic svg { width: 18px; height: 18px; }
    .oi-kind b { display: block; font-size: 13.5px; color: #123B68; }
    .oi-kind small { display: block; margin-top: 2px; font-size: 11.5px; line-height: 1.4; color: #7A92A3; }

    .oi-panel { display: grid; gap: 12px; padding: 14px; border: 1px solid #E2ECF3; border-radius: 14px; background: #fff; }
    .oi-opts { display: grid; gap: 8px; }
    .oi-opt { display: flex; align-items: center; gap: 10px; padding: 6px 8px 6px 6px; border: 1px solid #DDEAF0; border-radius: 12px; transition: border-color .15s, background-color .15s; }
    .oi-opt.is-correct { border-color: #2F8A6B; background: #EFFAF5; }
    .oi-opt__pick { display: inline-flex; flex-shrink: 0; align-items: center; gap: 6px; padding: 6px 9px; border-radius: 9px; background: #F1F6F9; font-size: 11.5px; font-weight: 700; color: #45657D; cursor: pointer; }
    .oi-opt.is-correct .oi-opt__pick { background: #2F8A6B; color: #fff; }
    .oi-opt__pick input { accent-color: #2F8A6B; }
    .oi-opt .oi-add__input { min-height: 38px; border-color: transparent; background: transparent; }
    .oi-opt .oi-add__input:focus { border-color: #2D7FA3; background: #fff; }
    .oi-tf { display: flex; flex-wrap: wrap; gap: 10px; }
    .oi-tf label { display: inline-flex; align-items: center; gap: 8px; min-width: 130px; padding: 11px 16px; border: 1.5px solid #DDEAF0; border-radius: 12px; font-size: 13.5px; font-weight: 700; color: #45657D; cursor: pointer; }
    .oi-tf label.is-on-true { border-color: #2F8A6B; background: #EFFAF5; color: #207153; }
    .oi-tf label.is-on-false { border-color: #E11D48; background: #FFF1F2; color: #BE123C; }
    .oi-check { display: inline-flex; align-items: center; gap: 8px; font-size: 12.5px; color: #45657D; }

    .oi-drop { display: flex; align-items: center; gap: 12px; padding: 16px; border: 1.5px dashed #9DC0DA; border-radius: 14px; background: #fff; cursor: pointer; transition: border-color .15s, background-color .15s; }
    .oi-drop:hover { border-color: #2563EB; background: #F5F9FF; }
    .oi-drop svg { width: 22px; height: 22px; flex-shrink: 0; color: #2563EB; }
    .oi-drop b { display: block; font-size: 13.5px; color: #123B68; }
    .oi-drop small { display: block; margin-top: 2px; font-size: 11.5px; color: #7A92A3; word-break: break-all; }
    .oi-drop.has-file { border-style: solid; border-color: #2F8A6B; background: #EFFAF5; }
    .oi-drop.has-file small { color: #207153; font-weight: 600; }
    .oi-files { display: grid; gap: 10px; }
    @media (min-width: 768px) { .oi-files { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    /* SỬA 16/10 — khối nhập bài tập chỉ còn gói ZIP (tự nhận dạng) */
    .oi-drop--big { position: relative; padding: 22px 16px; }
    .oi-drop--big input[type=file] { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
    .oi-drop--big.is-drag { border-color: #2563EB; background: #EEF4FF; }
    .oi-zip__drop { min-height: 118px; justify-content: center; gap: 14px; border-width: 2px; }
    .oi-zip__ic { display: grid; flex-shrink: 0; place-items: center; width: 48px; height: 48px; border-radius: 14px; background: #EAF2FF; color: #2563EB; }
    .oi-zip__ic svg { width: 24px; height: 24px; }
    .oi-drop.has-file .oi-zip__ic { background: #D1FAE5; color: #047857; }
    .oi-zip__txt { min-width: 0; text-align: left; }
    .oi-zip__txt b { font-size: 14.5px; word-break: break-all; }
    .oi-zip__txt code { padding: 1px 5px; border-radius: 5px; background: #EEF2FF; font-size: 11px; color: #3730A3; }
    .oi-types { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 10px; }
    .oi-types__lbl { display: inline-flex; align-items: center; gap: 5px; margin-right: 2px; font-size: 12px; font-weight: 700; color: #64748B; }
    .oi-types__lbl svg { width: 14px; height: 14px; color: #D97706; }
    .oi-type { display: inline-flex; align-items: center; gap: 5px; padding: 5px 10px; border: 1px solid #DDEAF0; border-radius: 999px; background: #fff; font-size: 12px; font-weight: 600; color: #35617A; }
    .oi-type svg { width: 13px; height: 13px; color: #126F91; }
    .oi-zip__tpl { display: inline-flex; align-items: center; gap: 6px; min-height: 32px; padding: 0 12px; border: 1px dashed #93C5FD; border-radius: 10px; font-size: 12.5px; font-weight: 700; color: #1D4ED8; text-decoration: none; }
    .oi-zip__tpl:hover { background: #EFF6FF; }
    .oi-zip__tpl svg { width: 14px; height: 14px; }
    .oi-sr { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); border: 0; }
</style>
@endonce
