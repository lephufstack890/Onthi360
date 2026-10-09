{{-- SỬA 9/10 — CSS của màn "Thông tin cá nhân" + "Đổi mật khẩu", chép từ source mới
     (education-main/src/components/personalProfile.css), đổi tiền tố .personal-profile/.pp-*.
     Chỉ dùng CSS riêng (không phụ thuộc lớp Tailwind đã build vì server không chạy Vite). --}}
<style>
    .personal-profile { color: #24435b; min-width: 0; font-size: 12px; line-height: 1.5; }
    .personal-profile *, .personal-profile *::before, .personal-profile *::after { box-sizing: border-box; }
    .pp-tabs { display: inline-flex; gap: 4px; padding: 4px; margin-bottom: 16px; border: 1px solid #d6e5ed; background: #fff; border-radius: 14px; max-width: 100%; }
    .pp-tabs a { display: inline-flex; align-items: center; gap: 7px; min-height: 36px; padding: 7px 14px; border-radius: 10px; font-size: 12px; font-weight: 700; color: #607a90; text-decoration: none; white-space: nowrap; }
    .pp-tabs a:hover { background: #edf6fa; color: #126f91; }
    .pp-tabs a[aria-current="page"] { background: #2563eb; color: #fff; box-shadow: 0 4px 10px #2563eb33; }
    .pp-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
    .pp-heading p { color: #126f91; text-transform: uppercase; font-size: 11px; letter-spacing: .08em; font-weight: 700; margin: 0; }
    .pp-heading h1 { font-size: 20px; line-height: 1.4; font-weight: 700; margin: 4px 0 8px; }
    .pp-heading span { font-size: 12px; color: #607a90; }
    .pp-heading .pp-role { display: flex; align-items: center; gap: 6px; padding: 8px 12px; border: 1px solid #d6e5ed; background: #fff; border-radius: 20px; white-space: nowrap; color: #24435b; }
    .pp-layout { display: grid; grid-template-columns: 280px minmax(0, 1fr); gap: 16px; padding: 0; border: 0; min-width: 0; margin: 0; align-items: stretch; }
    .pp-single { width: 100%; }
    .pp-card { border: 1px solid #ddeaf0; border-radius: 24px; background: #fff; padding: 20px; min-width: 0; box-shadow: 0 3px 16px #123b6806; }
    .pp-card h2 { display: flex; align-items: center; gap: 8px; font-size: 14px; line-height: 20px; font-weight: 700; margin: 0 0 12px; }
    .pp-avatar-card { display: flex; flex-direction: column; align-items: center; gap: 10px; }
    .pp-avatar-card strong { max-width: 100%; text-align: center; overflow-wrap: anywhere; font-size: 14px; font-weight: 700; }
    .pp-avatar-preview { position: relative; width: 128px; height: 128px; flex-shrink: 0; padding: 5px; border: 1px solid #cce1ec; border-radius: 50%; background: #f0f7fa; }
    .pp-avatar-preview img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; display: block; }
    .pp-avatar-preview .pp-initials { width: 100%; height: 100%; border-radius: 50%; display: grid; place-items: center; font-size: 40px; font-weight: 800; color: #126f91; background: linear-gradient(135deg, #dff0f8, #eaf3ff); user-select: none; }
    .pp-account { font-size: 12px; color: #607a90; overflow-wrap: anywhere; text-align: center; }
    .personal-profile button, .personal-profile .pp-btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; border: 1px solid #ccdfe9; border-radius: 10px; padding: 10px 14px; min-height: 40px; background: #fff; color: #24435b; font-size: 12px; font-weight: 650; cursor: pointer; text-decoration: none; font-family: inherit; }
    .personal-profile button:hover, .personal-profile .pp-btn:hover { background: #edf6fa; }
    .personal-profile button:disabled { opacity: .5; cursor: not-allowed; }
    .personal-profile .pp-primary { background: #2563eb; color: #fff; border-color: #2563eb; }
    .personal-profile .pp-primary:hover { background: #1d4ed8; }
    .personal-profile .pp-text-button { border: 0; background: transparent; color: #607a90; padding: 4px 8px; min-height: 0; }
    .personal-profile .pp-text-button:hover { background: transparent; color: #b33d45; }
    .pp-file { position: absolute; width: 1px; height: 1px; opacity: 0; overflow: hidden; pointer-events: none; }
    .pp-hint { display: block; color: #6a8091; font-size: 11px; line-height: 1.7; font-weight: 400; margin: 0; }
    .pp-avatar-card > .pp-hint { text-align: center; }
    .pp-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin-top: 16px; }
    .pp-fields label { font-size: 12px; font-weight: 600; min-width: 0; display: block; }
    .pp-fields label > span.pp-req { color: #b84646; }
    .pp-fields input, .pp-fields textarea { display: block; width: 100%; min-width: 0; min-height: 42px; margin-top: 6px; border: 1px solid #cfdee7; background: #fbfdfe; border-radius: 10px; padding: 10px 12px; color: #24435b; font: inherit; font-size: 14px; font-weight: 400; }
    .pp-fields input:disabled { background: #f1f6f9; color: #7d96a6; }
    .pp-fields [aria-invalid="true"] { border-color: #cb5e5e; }
    .pp-fields .pp-select { display: block; margin-top: 6px; }
    .personal-profile :is(input, textarea, select, button, a):focus-visible { outline: 3px solid #a6d6e6; outline-offset: 2px; }
    .pp-wide { grid-column: 1 / -1; }
    .pp-field-error { display: block; color: #b33d45; margin-top: 5px; font-weight: 400; font-size: 11px; }
    .pp-pass-wrap { position: relative; display: block; }
    .pp-pass-wrap input { padding-right: 46px; }
    .pp-pass-wrap button { position: absolute; right: 4px; top: 10px; min-height: 34px; padding: 0 10px; border: 0; background: transparent; color: #6a8091; }
    .pp-account-info { margin-top: 20px; border-top: 1px solid #e2edf2; padding-top: 16px; }
    .pp-account-info h3 { display: flex; align-items: center; gap: 7px; font-size: 12px; font-weight: 650; margin: 0; }
    .pp-account-info dl { display: flex; flex-wrap: wrap; gap: 16px 32px; margin: 14px 0 0; font-size: 12px; }
    .pp-account-info dt { color: #6a8091; font-size: 11px; }
    .pp-account-info dd { margin: 4px 0 0; overflow-wrap: anywhere; }
    .pp-crop { width: 100%; margin-top: 10px; border-top: 1px solid #e2edf2; padding-top: 15px; }
    .pp-crop[hidden] { display: none; }
    .pp-crop h3 { font-size: 13px; font-weight: 650; margin: 0 0 10px; }
    .pp-crop canvas { width: 100%; height: auto; border-radius: 12px; background: #e9f1f6; display: block; }
    .pp-crop label { display: block; font-size: 11px; margin-top: 12px; }
    .pp-crop input[type=range] { display: block; width: 100%; margin-top: 5px; accent-color: #126f91; }
    .pp-crop-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
    .pp-save-bar { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 18px 0; margin-top: 4px; }
    .pp-save-bar strong { font-size: 12px; font-weight: 600; }
    .pp-save-bar p { font-size: 11px; color: #6a8091; margin: 4px 0 0; }
    .pp-save-bar > div:last-child { display: flex; gap: 8px; flex-shrink: 0; }
    .pp-error, .pp-success { margin-top: 14px; padding: 12px 14px; font-size: 12px; line-height: 1.7; border-radius: 10px; overflow-wrap: anywhere; }
    .pp-error { background: #fff0f1; color: #a33747; }
    .pp-success { display: flex; gap: 8px; align-items: center; background: #eaf8f0; color: #26724f; }
    .pp-tips { margin: 14px 0 0; padding: 12px 14px; background: #f4f9fc; border-radius: 12px; color: #4a6479; font-size: 11px; line-height: 1.8; }
    .pp-tips ul { margin: 6px 0 0; padding-left: 18px; list-style: disc; }
    @media (max-width: 1100px) { .pp-layout { grid-template-columns: 230px minmax(0, 1fr); } }
    @media (max-width: 767px) {
        .pp-layout { grid-template-columns: minmax(0, 1fr); }
        .pp-card { padding: 16px; }
        .pp-fields { grid-template-columns: minmax(0, 1fr); }
        .pp-fields input, .pp-fields textarea { font-size: 16px; }
        .pp-heading { flex-wrap: wrap; }
        .pp-crop { max-width: 280px; }
        .pp-save-bar { flex-direction: column; align-items: stretch; }
        .pp-save-bar > div:last-child { justify-content: flex-end; }
        .pp-tabs { display: flex; }
        .pp-tabs a { flex: 1; justify-content: center; }
    }
    html.theme-dark .personal-profile { color: #d7e7f1; }
    html.theme-dark .personal-profile :is(.pp-card, .pp-role, .pp-tabs, .pp-fields input, .pp-fields textarea) { background: #162b3b; color: #d7e7f1; border-color: #355267; }
    html.theme-dark .personal-profile :is(.pp-hint, .pp-account, .pp-heading span, .pp-account-info dt, .pp-save-bar p) { color: #a3bdcd; }
</style>
