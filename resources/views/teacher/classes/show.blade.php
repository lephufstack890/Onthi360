@extends('layouts.teacher')

@section('title', 'Chi tiết lớp')
@section('page-title', 'Lớp học: '.($classRoom->name ?? ''))
@section('ws-full', '1')

{{--
    SỬA 3/11 (khách: "làm lại toàn bộ trang chi tiết lớp của giáo viên theo phong cách hiện đại này, logic giữ nguyên") — CHỈ ĐỔI
    GIAO DIỆN: thẻ đầu trang (ảnh khoá học, tên lớp, số học sinh, buổi tới, % hoàn thành), dải tab có biểu tượng, tab Tài liệu
    (tìm kiếm + lọc loại ngay trên trang, thẻ tài liệu có màu theo loại, nút Xem trước / Gỡ khỏi lớp, khối Thêm tài liệu), tab Tổng
    quan. Mọi biến, form, route, luật vẫn như cũ (Teacher\ClassRoomService::showForTeacher()). CSS riêng tiền tố tcx- (Tailwind
    không build lại trên máy chủ). Bản cũ: _to_delete_claude/before-teacher-class-ui/show.blade.php.
--}}
@section('content')
    @php
        $tab = $tab ?? 'overview';
        $materials = $materials ?? [];
        $members = $members ?? collect();
        $studentsCount = $studentsCount ?? 0;
        $courseTitle = $classRoom->course->title ?? '';
        $className = $classRoom->name ?? '';
        $nextSessionLabel = isset($nextSession) && $nextSession ? 'Buổi tới: '.$nextSession->starts_at->format('d/m H:i') : 'Chưa có buổi học sắp tới';
        $ratingAverage = $ratingSummary->avg_rating ?? 0;
        $ratingCount = $ratingSummary->review_count ?? 0;
        $tcxCover = method_exists($classRoom->course ?? null, 'coverUrl') ? $classRoom->course->coverUrl() : null;
        $tcxCompletion = (int) ($completion ?? 0);
        $tcxTabIcons = ['Tổng quan' => 'home', 'Tài liệu' => 'file-text', 'Học liệu' => 'file-text', 'Buổi học' => 'play-circle', 'Giao đề' => 'clipboard-list', 'Kết quả' => 'bar-chart-3', 'Thành viên' => 'users', 'Học sinh' => 'users', 'Thông báo' => 'megaphone'];
        // Loại tài liệu suy từ nhãn có sẵn ("📘 Sách" / "🗂️ Chuyên đề" / "📝 Bộ đề") — không đổi dữ liệu của service.
        $tcxType = function (?string $label): string {
            $l = mb_strtolower((string) $label);
            return str_contains($l, 'chuyên đề') ? 'topic' : (str_contains($l, 'bộ đề') ? 'exam' : 'book');
        };
        $tcxTypeIcon = ['book' => 'book-open', 'topic' => 'layers', 'exam' => 'clipboard-list'];
        $tcxTypeName = ['book' => 'Sách', 'topic' => 'Chuyên đề', 'exam' => 'Bộ đề'];
        $tcxClean = fn (?string $label) => trim(preg_replace('/^[^\p{L}]+/u', '', (string) $label));
    @endphp

    {{-- SỬA 4/11 (khách: "đồng bộ chữ với trang chủ public, chữ to quá") — thu các cỡ chữ lớn (tiêu đề 22→18, số liệu 17–22→15–18, tab 13→12,5). --}}
    <style>
        .tcx [hidden] { display: none !important; }
        .tcx { --tcx-ink: #0F2A4A; --tcx-muted: #5B7189; --tcx-line: #E3ECF5; }
        .tcx a { text-decoration: none; }
        .tcx-back { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 12px; font-size: 13px; font-weight: 600; color: #475569; }
        .tcx-back:hover { color: #1D4ED8; }
        .tcx-back svg { width: 15px; height: 15px; }
        .tcx-hero { position: relative; display: flex; flex-wrap: wrap; align-items: center; gap: 14px 20px; padding: 16px 20px; border: 1px solid #E0ECF9; border-radius: 22px; overflow: hidden;
            background: radial-gradient(120% 140% at 85% 0%, #E6EEFF 0%, rgba(230, 238, 255, 0) 55%), linear-gradient(120deg, #FFFFFF 0%, #F6F9FF 55%, #EEF4FF 100%); box-shadow: 0 10px 30px rgba(30, 64, 175, .07); margin-bottom: 16px; }
        .tcx-media { position: relative; width: 84px; height: 84px; flex: none; border-radius: 18px; overflow: hidden; background: linear-gradient(135deg, #DBEAFE, #E0F2FE); display: grid; place-items: center; font-size: 40px; box-shadow: 0 6px 16px rgba(30, 64, 175, .12); }
        .tcx-media img { width: 100%; height: 100%; object-fit: cover; }
        .tcx-main { flex: 1 1 320px; min-width: 0; }
        .tcx-pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; background: #E8F0FF; color: #1D4ED8; font-size: 12px; font-weight: 700; }
        .tcx-pill svg { width: 14px; height: 14px; }
        .tcx-title { margin: 6px 0 0; font-size: 18px; line-height: 1.2; font-weight: 700; color: var(--tcx-ink); letter-spacing: -.01em; }
        .tcx-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 14px; margin-top: 8px; font-size: 13px; color: #475569; }
        .tcx-meta span { display: inline-flex; align-items: center; gap: 7px; }
        .tcx-meta svg { width: 15px; height: 15px; color: #64748B; }
        .tcx-meta i { width: 1px; height: 14px; background: #CBD5E1; }
        .tcx-prog { display: flex; align-items: center; gap: 14px; padding-left: 20px; border-left: 1px solid #DCE6F2; }
        .tcx-prog-txt small { display: block; max-width: 180px; font-size: 12px; line-height: 1.45; font-weight: 600; color: #475569; }
        .tcx-prog-txt b { display: block; margin-top: 3px; font-size: 18px; line-height: 1; font-weight: 700; color: var(--tcx-ink); }
        .tcx-bar { width: 170px; height: 6px; margin-top: 8px; border-radius: 999px; background: #E2E8F0; overflow: hidden; }
        .tcx-bar span { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg, #3B82F6, #2563EB); }
        .tcx-prog-txt em { display: block; margin-top: 5px; font-style: normal; font-size: 12px; color: #94A3B8; }
        .tcx-target { display: grid; place-items: center; width: 54px; height: 54px; border-radius: 50%; background: radial-gradient(circle at 35% 30%, #93C5FD, #2563EB 70%); color: #fff; box-shadow: 0 8px 18px rgba(37, 99, 235, .28); flex: none; }
        .tcx-target svg { width: 26px; height: 26px; }
        @media (max-width: 820px) { .tcx-prog { padding-left: 0; border-left: 0; width: 100%; } .tcx-target { display: none; } .tcx-title { font-size: 16px; } .tcx-media { width: 68px; height: 68px; font-size: 32px; } }
        .tcx-tabs { display: flex; gap: 4px; overflow-x: auto; padding: 6px 8px 0; border: 1px solid var(--tcx-line); border-bottom: 0; border-radius: 18px 18px 0 0; background: #fff; width: fit-content; max-width: 100%; scrollbar-width: none; }
        .tcx-tab { position: relative; display: inline-flex; align-items: center; gap: 7px; padding: 10px 14px 12px; font-size: 13px; font-weight: 700; color: #334155; white-space: nowrap; }
        .tcx-tab svg { width: 16px; height: 16px; color: #64748B; }
        .tcx-tab:hover { color: #1D4ED8; }
        .tcx-tab.is-on { color: #1D4ED8; }
        .tcx-tab.is-on svg { color: #2563EB; }
        .tcx-tab.is-on::after { content: ''; position: absolute; left: 12px; right: 12px; bottom: 0; height: 3px; border-radius: 3px 3px 0 0; background: #2563EB; }
        .tcx-tab sup { top: 0; padding: 1px 7px; border-radius: 999px; background: #FEE2E2; color: #B91C1C; font-size: 11px; font-weight: 700; }
        .tcx-panel { padding: 16px; border: 1px solid var(--tcx-line); border-radius: 0 18px 18px 18px; background: linear-gradient(180deg, #FBFDFF, #F6F9FD); box-shadow: 0 10px 30px rgba(15, 42, 74, .05); }
        @media (max-width: 640px) { .tcx-panel { padding: 14px; border-radius: 0 0 22px 22px; } .tcx-tabs { border-radius: 18px 18px 0 0; } }
        /* Tab Tài liệu */
        .tcx-toolbar { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; margin-bottom: 12px; }
        .tcx-search { position: relative; flex: 0 1 240px; min-width: 180px; }
        .tcx-search svg { position: absolute; left: 12px; top: 50%; width: 15px; height: 15px; transform: translateY(-50%); color: #64748B; }
        .tcx-search input { box-sizing: border-box; width: 100%; height: 36px; padding: 0 12px 0 34px; border: 1px solid #DCE6F2; border-radius: 999px; background: #fff; font-size: 13px; color: var(--tcx-ink); }
        .tcx-search input:focus, .tcx-filter select:focus { outline: 0; border-color: #3B82F6; box-shadow: 0 0 0 3px rgba(59, 130, 246, .15); }
        .tcx-filter { position: relative; }
        .tcx-filter svg { position: absolute; left: 12px; top: 50%; width: 14px; height: 14px; transform: translateY(-50%); color: #334155; pointer-events: none; }
        .tcx-filter select { box-sizing: border-box; height: 36px; padding: 0 12px 0 32px; border: 1px solid #DCE6F2; border-radius: 999px; background: #fff; font-size: 13px; font-weight: 700; color: #334155; appearance: none; cursor: pointer; }
        .tcx-docs { display: grid; gap: 10px; }
        .tcx-doc { position: relative; display: flex; flex-wrap: wrap; align-items: center; gap: 10px 14px; padding: 11px 14px 11px 18px; border: 1px solid #E6EEF6; border-radius: 16px; background: #fff; box-shadow: 0 4px 14px rgba(15, 42, 74, .05); overflow: hidden; transition: box-shadow .15s, transform .15s; }
        .tcx-doc:hover { box-shadow: 0 10px 24px rgba(15, 42, 74, .08); transform: translateY(-1px); }
        .tcx-doc::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: #60A5FA; }
        .tcx-doc.t-topic::before { background: #A78BFA; } .tcx-doc.t-exam::before { background: #C084FC; }
        .tcx-ic { display: grid; place-items: center; width: 42px; height: 42px; border-radius: 12px; flex: none; background: #E0EDFF; color: #2563EB; }
        .tcx-ic svg { width: 20px; height: 20px; }
        .tcx-doc.t-topic .tcx-ic { background: #EDE7FE; color: #7C3AED; } .tcx-doc.t-exam .tcx-ic { background: #F3E8FF; color: #9333EA; }
        .tcx-doc-txt { flex: 1 1 200px; min-width: 0; }
        .tcx-doc-txt b { display: block; font-size: 13px; font-weight: 700; color: var(--tcx-ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .tcx-doc-txt small { display: block; margin-top: 2px; font-size: 12px; color: #64748B; }
        .tcx-use { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 999px; background: #E3F6EC; color: #15803D; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .tcx-use svg { width: 13px; height: 13px; }
        .tcx-acts { display: flex; align-items: center; gap: 8px; margin-left: auto; }
        .tcx-btn { display: inline-flex; align-items: center; gap: 5px; height: 32px; padding: 0 12px; border: 0; border-radius: 10px; font-size: 12px; font-weight: 700; cursor: pointer; white-space: nowrap; }
        .tcx-btn svg { width: 14px; height: 14px; }
        .tcx-btn.is-view { background: #EEF4FF; color: #1D4ED8; } .tcx-btn.is-view:hover { background: #E0EAFF; }
        .tcx-btn.is-del { background: #FEECEC; color: #DC2626; } .tcx-btn.is-del:hover { background: #FDDCDC; }
        .tcx-btn.is-primary { background: linear-gradient(135deg, #2563EB, #1D4ED8); color: #fff; box-shadow: 0 6px 16px rgba(37, 99, 235, .25); }
        .tcx-sep { width: 1px; height: 22px; background: #E2E8F0; }
        .tcx-empty, .tcx-nomatch { padding: 20px 14px; border: 1.5px dashed #D6E3EF; border-radius: 18px; background: #fff; text-align: center; font-size: 13px; color: #64748B; }
        .tcx-add { display: grid; grid-template-columns: minmax(0, 1fr); gap: 14px; margin-top: 12px; padding: 14px; border: 1.5px dashed #BFD4F1; border-radius: 18px; background: #fff; }
        @media (min-width: 900px) { .tcx-add { grid-template-columns: minmax(0, 1fr) minmax(0, 1.1fr); align-items: start; } }
        .tcx-add-intro { display: flex; align-items: center; gap: 14px; }
        .tcx-folder { position: relative; display: grid; place-items: center; width: 72px; height: 62px; flex: none; border-radius: 16px; background: linear-gradient(135deg, #BFDBFE, #60A5FA); color: #fff; box-shadow: 0 12px 24px rgba(37, 99, 235, .2); }
        .tcx-folder svg { width: 30px; height: 30px; }
        .tcx-folder span { position: absolute; right: -6px; bottom: -6px; display: grid; place-items: center; width: 26px; height: 26px; border-radius: 50%; background: #fff; color: #2563EB; box-shadow: 0 6px 14px rgba(15, 42, 74, .15); }
        .tcx-folder span svg { width: 14px; height: 14px; }
        .tcx-add h3 { margin: 0; font-size: 14px; font-weight: 700; color: var(--tcx-ink); }
        .tcx-add p { margin: 3px 0 0; font-size: 13px; line-height: 1.5; color: #64748B; }
        .tcx-types { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
        .tcx-type { display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 12px; border: 1px solid #DCE6F2; border-radius: 11px; background: #fff; font-size: 13px; font-weight: 700; color: #1E3A5F; cursor: pointer; box-shadow: 0 2px 6px rgba(15, 42, 74, .05); }
        .tcx-type svg { width: 15px; height: 15px; }
        .tcx-type .c-book { color: #2563EB; } .tcx-type .c-topic { color: #7C3AED; } .tcx-type .c-exam { color: #EA580C; }
        .tcx-type em { font-style: normal; padding: 0 6px; border-radius: 999px; background: #EEF2F7; font-size: 11px; color: #475569; }
        .tcx-type.is-on { border-color: #2563EB; background: #EEF4FF; color: #1D4ED8; box-shadow: 0 0 0 3px rgba(37, 99, 235, .12); }
        .tcx-pick { min-height: 120px; padding: 10px; border: 1px solid #E3ECF5; border-radius: 14px; background: #F8FBFE; }
        .tcx-pick-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 10px; padding: 8px 10px; border-radius: 12px; background: #fff; border: 1px solid #E6EEF6; }
        .tcx-pick-row + .tcx-pick-row { margin-top: 8px; }
        .tcx-pick-row b { display: block; font-size: 13px; color: var(--tcx-ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .tcx-pick-row small { display: block; margin-top: 2px; font-size: 12px; color: #94A3B8; }
        .tcx-pick-empty { display: grid; place-items: center; min-height: 100px; text-align: center; font-size: 13px; color: #94A3B8; }
        /* Tab Tổng quan */
        .tcx-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
        .tcx-stat { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid #E6EEF6; border-radius: 16px; background: #fff; box-shadow: 0 4px 14px rgba(15, 42, 74, .05); }
        .tcx-stat .tcx-ic { width: 40px; height: 40px; border-radius: 12px; }
        .tcx-stat .tcx-ic svg { width: 19px; height: 19px; }
        .tcx-stat small { display: block; font-size: 12px; font-weight: 600; color: #64748B; }
        .tcx-stat b { display: block; margin-top: 1px; font-size: 15px; font-weight: 700; color: var(--tcx-ink); }
        .tcx-ic.g { background: #E3F6EC; color: #15803D; } .tcx-ic.a { background: #FFF1D6; color: #B45309; } .tcx-ic.v { background: #EDE7FE; color: #7C3AED; }
        .tcx-cards { display: grid; grid-template-columns: minmax(0, 1fr); gap: 12px; margin-top: 12px; }
        @media (min-width: 900px) { .tcx-cards { grid-template-columns: 1fr 1fr; } }
        .tcx-card { padding: 14px 16px; border: 1px solid #E6EEF6; border-radius: 16px; background: #fff; box-shadow: 0 4px 14px rgba(15, 42, 74, .05); }
        .tcx-card h3 { display: flex; align-items: center; gap: 8px; margin: 0 0 10px; font-size: 14px; font-weight: 700; color: var(--tcx-ink); }
        .tcx-card h3 .tcx-ic { width: 32px; height: 32px; border-radius: 10px; } .tcx-card h3 .tcx-ic svg { width: 16px; height: 16px; }
        .tcx-card p { margin: 0; font-size: 13px; color: #475569; }
        /* SỬA 3/11 (khách: "tab Buổi học, Kết quả, Thành viên cũng thiết kế lại hiện đại") */
        .tcx-sec { padding: 14px 16px; border: 1px solid #E6EEF6; border-radius: 16px; background: #fff; box-shadow: 0 4px 14px rgba(15, 42, 74, .05); }
        .tcx-sec + .tcx-sec { margin-top: 12px; }
        .tcx-sec-head { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 12px; margin-bottom: 12px; }
        .tcx-sec-head h3 { margin: 0; font-size: 14px; font-weight: 700; color: var(--tcx-ink); }
        .tcx-sec-head p { margin: 2px 0 0; font-size: 12px; color: #64748B; }
        .tcx-sec-head .tcx-ic { width: 36px; height: 36px; border-radius: 11px; } .tcx-sec-head .tcx-ic svg { width: 18px; height: 18px; }
        .tcx-count { margin-left: auto; padding: 3px 10px; border-radius: 999px; background: #EEF4FF; color: #1D4ED8; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .tcx-count.is-amber { background: #FFF1D6; color: #B45309; }
        .tcx-form-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 10px; }
        @media (min-width: 640px) { .tcx-form-grid { grid-template-columns: 1fr 1fr; } }
        .tcx-label { display: block; margin-bottom: 4px; font-size: 12px; font-weight: 700; color: #475569; }
        .tcx-sess { display: grid; gap: 8px; }
        .tcx-ses { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 14px; padding: 10px 12px; border: 1px solid #E6EEF6; border-radius: 14px; background: #fff; transition: box-shadow .15s; }
        .tcx-ses:hover { box-shadow: 0 8px 18px rgba(15, 42, 74, .06); }
        .tcx-date { display: grid; place-items: center; width: 52px; flex: none; padding: 6px 0; border-radius: 12px; background: #EEF4FF; color: #1D4ED8; line-height: 1.1; text-align: center; }
        .tcx-date b { font-size: 16px; font-weight: 700; } .tcx-date small { font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .tcx-ses-txt { flex: 1 1 220px; min-width: 0; }
        .tcx-ses-txt b { display: block; font-size: 14px; font-weight: 700; color: var(--tcx-ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .tcx-ses-txt span { display: inline-flex; align-items: center; gap: 4px; margin: 3px 12px 0 0; font-size: 12px; color: #64748B; }
        .tcx-ses-txt span svg { width: 13px; height: 13px; }
        .tcx-chip { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 700; white-space: nowrap; background: #F1F5F9; color: #475569; }
        .tcx-chip.t-success { background: #E3F6EC; color: #15803D; } .tcx-chip.t-warning { background: #FFF1D6; color: #B45309; }
        .tcx-chip.t-info, .tcx-chip.t-brand { background: #E0EDFF; color: #1D4ED8; } .tcx-chip.t-danger { background: #FEE2E2; color: #B91C1C; }
        .tcx-btn.is-green { background: #16A34A; color: #fff; } .tcx-btn.is-green:hover { background: #15803D; }
        .tcx-btn.is-ghost { background: #fff; color: #475569; border: 1px solid #DCE6F2; }
        .tcx-btn.is-ghost:hover { border-color: #FCA5A5; color: #DC2626; background: #FFF5F5; }
        .tcx-btn.is-red { background: #DC2626; color: #fff; }
        .tcx-input { box-sizing: border-box; height: 32px; padding: 0 10px; border: 1px solid #DCE6F2; border-radius: 10px; background: #fff; font-size: 12px; color: var(--tcx-ink); }
        .tcx-input:focus { outline: 0; border-color: #3B82F6; box-shadow: 0 0 0 3px rgba(59, 130, 246, .15); }
        .tcx-people { display: grid; grid-template-columns: minmax(0, 1fr); gap: 8px; }
        @media (min-width: 900px) { .tcx-people { grid-template-columns: 1fr 1fr; } }
        .tcx-person { padding: 10px 12px; border: 1px solid #E6EEF6; border-radius: 14px; background: #fff; }
        .tcx-person.is-confirm { border-color: #FECACA; background: #FFF7F7; }
        .tcx-person-row { display: flex; align-items: center; gap: 10px; }
        .tcx-person-txt { flex: 1; min-width: 0; }
        .tcx-person-txt b { display: block; font-size: 13px; font-weight: 700; color: var(--tcx-ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .tcx-person-txt small { display: block; margin-top: 1px; font-size: 12px; color: #64748B; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .tcx-person form { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 8px; }
        .tcx-person form .tcx-input { flex: 1 1 160px; }
        .tcx-req { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; padding: 10px 12px; border: 1px solid #FDE7C2; border-radius: 14px; background: #FFFCF5; }
        .tcx-req + .tcx-req { margin-top: 8px; }
        .tcx-req-acts { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-left: auto; }
        .tcx-req-acts form { display: flex; align-items: center; gap: 6px; }
        /* SỬA 6/11 — popup "Thêm học sinh" (tiền tố tam-; popup gắn ra <body> nên không dùng biến --tcx-*) */
        .tam-ov { position: fixed; inset: 0; z-index: 90; display: grid; place-items: center; padding: 16px; background: rgba(15, 35, 60, .45); backdrop-filter: blur(4px); }
        .tam-dlg { width: min(600px, 100%); max-height: calc(100vh - 32px); display: flex; flex-direction: column; border-radius: 22px; background: #fff; box-shadow: 0 30px 70px rgba(15, 35, 60, .3); overflow: hidden; font-family: inherit; color: #1E293B; }
        .tam-head { display: flex; align-items: center; gap: 12px; padding: 18px 20px 14px; background: linear-gradient(120deg, #ECFDF5, #F0F9FF); border-bottom: 1px solid #E2EEF5; }
        .tam-hic { display: grid; place-items: center; width: 42px; height: 42px; flex: none; border-radius: 14px; background: linear-gradient(135deg, #22C55E, #16A34A); color: #fff; box-shadow: 0 8px 18px rgba(22, 163, 74, .3); }
        .tam-hic svg { width: 20px; height: 20px; }
        .tam-htx { flex: 1; min-width: 0; }
        .tam-htx h3 { margin: 0; font-size: 16px; font-weight: 700; color: #0F2F57; }
        .tam-htx p { margin: 2px 0 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 13px; color: #5B7488; }
        .tam-x { display: grid; place-items: center; width: 34px; height: 34px; border: 0; border-radius: 10px; background: rgba(255, 255, 255, .8); color: #5B7488; cursor: pointer; }
        .tam-x:hover { background: #fff; color: #0F2F57; }
        .tam-x svg { width: 17px; height: 17px; }
        .tam-seg { display: flex; gap: 4px; margin: 14px 20px 0; padding: 4px; border-radius: 12px; background: #F1F5F9; }
        .tam-seg button { flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 34px; border: 0; border-radius: 9px; background: none; font-size: 13px; font-weight: 700; color: #5B7488; cursor: pointer; }
        .tam-seg button svg { width: 15px; height: 15px; }
        .tam-seg button.on { background: #fff; color: #0F2F57; box-shadow: 0 2px 6px rgba(15, 35, 60, .1); }
        .tam-body { padding: 14px 20px 6px; overflow-y: auto; }
        .tam-combo { position: relative; }
        .tam-input { display: flex; align-items: center; gap: 8px; height: 44px; padding: 0 6px 0 12px; border: 1.5px solid #DCE6F0; border-radius: 13px; background: #fff; color: #7B8EA0; transition: border-color .15s, box-shadow .15s; }
        .tam-input.on, .tam-input:focus-within { border-color: #22C55E; box-shadow: 0 0 0 4px rgba(34, 197, 94, .12); }
        .tam-input > svg { width: 17px; height: 17px; flex: none; }
        .tam-input input { flex: 1; min-width: 0; height: 100%; border: 0; outline: 0; background: transparent; font-size: 14px; color: #0F2F57; }
        .tam-caret { display: grid; place-items: center; width: 32px; height: 32px; border: 0; border-radius: 9px; background: #F1F5F9; color: #5B7488; cursor: pointer; }
        .tam-caret svg { width: 16px; height: 16px; transition: transform .15s; }
        .tam-caret[aria-expanded="true"] svg { transform: rotate(180deg); }
        .tam-spin { width: 16px; height: 16px; border: 2px solid #BBF7D0; border-top-color: #16A34A; border-radius: 50%; animation: tam-spin .7s linear infinite; }
        @keyframes tam-spin { to { transform: rotate(360deg); } }
        .tam-drop { margin-top: 8px; border: 1px solid #E2EBF2; border-radius: 14px; background: #fff; box-shadow: 0 12px 28px rgba(15, 35, 60, .1); overflow: hidden; } /* nằm trong luồng (không absolute) để không bị khung popup cắt */
        .tam-drop-h { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 12px; border-bottom: 1px solid #EEF3F7; background: #F8FAFC; font-size: 12px; font-weight: 700; color: #7B8EA0; }
        .tam-drop-h button { border: 0; background: none; font-size: 12px; font-weight: 700; color: #16A34A; cursor: pointer; }
        .tam-list { max-height: min(270px, 38vh); margin: 0; padding: 6px; list-style: none; overflow-y: auto; }
        .tam-list li { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 11px; cursor: pointer; transition: background .12s; }
        .tam-list li:hover { background: #F0FDF4; }
        .tam-list li.sel { background: #DCFCE7; }
        .tam-list li.dis { cursor: not-allowed; opacity: .55; }
        .tam-list li.dis:hover { background: none; }
        .tam-ck { display: grid; place-items: center; width: 20px; height: 20px; flex: none; border: 1.5px solid #CBD5E1; border-radius: 6px; background: #fff; color: transparent; transition: all .12s; }
        .tam-ck svg { width: 13px; height: 13px; }
        .tam-list li.sel .tam-ck { border-color: #16A34A; background: #16A34A; color: #fff; }
        .tam-av { display: grid; place-items: center; width: 34px; height: 34px; flex: none; overflow: hidden; border-radius: 50%; font-size: 12px; }
        .tam-av.sm { width: 22px; height: 22px; font-size: 10px; }
        .tam-av img { width: 100%; height: 100%; object-fit: cover; }
        .tam-av b { font-weight: 700; }
        .tam-av.t0 { background: #DCFCE7; color: #15803D; } .tam-av.t1 { background: #DBEAFE; color: #1D4ED8; }
        .tam-av.t2 { background: #FFEDD5; color: #C2410C; } .tam-av.t3 { background: #EDE9FE; color: #6D28D9; }
        .tam-who { flex: 1; min-width: 0; }
        .tam-who b { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 13px; color: #0F2F57; }
        .tam-who small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; color: #7B8EA0; }
        .tam-tag { flex: none; padding: 2px 8px; border-radius: 999px; background: #F1F5F9; font-size: 11px; font-weight: 700; color: #64748B; }
        .tam-empty { margin: 0; padding: 16px; text-align: center; font-size: 13px; color: #94A3B8; }
        .tam-picked { margin-top: 14px; padding: 12px; border-radius: 14px; border: 1px dashed #BBF7D0; background: #F7FEF9; }
        .tam-picked-h { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 13px; color: #166534; }
        .tam-picked-h button { border: 0; background: none; font-size: 12px; font-weight: 700; color: #DC2626; cursor: pointer; }
        .tam-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; max-height: 120px; overflow-y: auto; }
        .tam-chip { display: inline-flex; align-items: center; gap: 6px; height: 30px; padding: 0 4px 0 4px; border: 1px solid #BBF7D0; border-radius: 999px; background: #fff; font-size: 12px; font-weight: 600; color: #14532D; }
        .tam-chip button { display: grid; place-items: center; width: 20px; height: 20px; border: 0; border-radius: 50%; background: #F0FDF4; color: #16A34A; cursor: pointer; }
        .tam-chip button:hover { background: #DC2626; color: #fff; }
        .tam-chip button svg { width: 12px; height: 12px; }
        .tam-hint { margin: 6px 0 0; font-size: 12px; color: #6B8196; }
        .tam-lb { display: block; margin-bottom: 8px; font-size: 13px; line-height: 1.6; color: #3D566B; }
        .tam-ta { width: 100%; min-height: 140px; padding: 10px 12px; border: 1.5px solid #DCE6F0; border-radius: 13px; font: inherit; font-size: 13px; line-height: 1.7; color: #0F2F57; resize: vertical; outline: 0; box-sizing: border-box; }
        .tam-ta:focus { border-color: #22C55E; box-shadow: 0 0 0 4px rgba(34, 197, 94, .12); }
        .tam-err { display: flex; align-items: center; gap: 6px; margin: 6px 20px 0; padding: 8px 12px; border-radius: 10px; background: #FFF5F5; color: #B91C1C; font-size: 13px; font-weight: 600; }
        .tam-err svg { width: 15px; height: 15px; }
        .tam-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-top: 10px; padding: 14px 20px; border-top: 1px solid #EEF3F7; background: #FAFCFD; }
        .tam-foot small { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: #6B8196; }
        .tam-foot small svg { width: 14px; height: 14px; flex: none; }
        .tam-acts { display: flex; gap: 8px; margin-left: auto; }
        .tam-btn { display: inline-flex; align-items: center; gap: 6px; height: 38px; padding: 0 16px; border: 0; border-radius: 11px; font-size: 13px; font-weight: 700; cursor: pointer; }
        .tam-btn svg { width: 16px; height: 16px; }
        .tam-btn.ghost { background: #F1F5F9; color: #475569; } .tam-btn.ghost:hover { background: #E2E8F0; }
        .tam-btn.pri { background: linear-gradient(135deg, #22C55E, #16A34A); color: #fff; box-shadow: 0 8px 18px rgba(22, 163, 74, .3); }
        .tam-btn.pri:hover { filter: brightness(1.05); }
        .tam-btn.pri:disabled { opacity: .5; cursor: not-allowed; box-shadow: none; }
        @media (max-width: 560px) { .tam-ov { align-items: end; padding: 0; } .tam-dlg { border-radius: 22px 22px 0 0; max-height: 92vh; } .tam-foot small { display: none; } .tam-acts { width: 100%; } .tam-btn { flex: 1; justify-content: center; } }
        /* SỬA 6/11 — form + kết quả "Thêm học sinh vào lớp" */
        .tcx-addform { display: grid; gap: 8px; margin: 0 0 12px; padding: 12px 14px; border: 1px dashed #BBF7D0; border-radius: 14px; background: #F0FDF4; }
        .tcx-addform label { font-size: 13px; color: #3D566B; }
        .tcx-addform textarea.tcx-input { height: auto; min-height: 76px; padding: 8px 10px; line-height: 1.6; resize: vertical; font-family: inherit; }
        .tcx-addform-acts { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .tcx-addform-acts small { font-size: 12px; color: #64748B; }
        .tcx-addres { display: grid; gap: 6px; margin-bottom: 12px; }
        .tcx-addres p { display: flex; align-items: flex-start; gap: 7px; margin: 0; padding: 9px 12px; border-radius: 12px; font-size: 13px; line-height: 1.5; }
        .tcx-addres p svg { width: 15px; height: 15px; flex: none; margin-top: 2px; }
        .tcx-addres .ok { background: #F0FDF4; color: #166534; border: 1px solid #BBF7D0; }
        .tcx-addres .info { background: #EFF6FF; color: #1E40AF; border: 1px solid #BFDBFE; }
        .tcx-addres .warn { background: #FFFBEB; color: #92400E; border: 1px solid #FDE68A; }
        .tcx-alert { display: flex; gap: 8px; align-items: flex-start; margin-bottom: 12px; padding: 10px 12px; border: 1px solid #FECACA; border-radius: 12px; background: #FFF5F5; font-size: 13px; font-weight: 600; color: #991B1B; }
        .tcx-alert svg { width: 16px; height: 16px; flex: none; margin-top: 1px; }
        .tcx-results { display: grid; grid-template-columns: minmax(0, 1fr); gap: 12px; }
        @media (min-width: 900px) { .tcx-results { grid-template-columns: 1.4fr 1fr; } }
        .tcx-res-hero { display: flex; flex-wrap: wrap; align-items: center; gap: 14px; padding: 18px; border-radius: 16px; background: linear-gradient(120deg, #EEF4FF, #F5F9FF 60%, #fff); border: 1px solid #DCE8FA; }
        .tcx-res-hero .tcx-ic { width: 52px; height: 52px; border-radius: 15px; background: linear-gradient(135deg, #3B82F6, #1D4ED8); color: #fff; }
        .tcx-res-hero .tcx-ic svg { width: 24px; height: 24px; }
        .tcx-res-hero h3 { margin: 0; font-size: 14px; font-weight: 700; color: var(--tcx-ink); }
        .tcx-res-hero p { margin: 3px 0 0; font-size: 13px; color: #475569; }
        .tcx-res-list { margin: 0; padding: 0; list-style: none; display: grid; gap: 8px; }
        .tcx-res-list li { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #475569; }
        .tcx-res-list li svg { width: 15px; height: 15px; color: #16A34A; flex: none; }
    </style>

    <div class="tcx csp">
    {{-- SỬA 6/11 (khách: "lớp phải là một giao diện riêng, 8 tab") — đầu không gian lớp + 8 tab (thay thẻ đầu trang + dải tab cũ). --}}
    @if (session('status') === 'class-created')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tạo lớp thành công — bạn là giáo viên chính của lớp này.'])
    @endif
    @include('teacher.classes.space._header')

    <div class="tcx-panel" data-csp-panel>
    @include('teacher.classes._panel')
    </div>{{-- /.tcx-panel --}}
    </div>{{-- /.tcx --}}

    @push('scripts')
        {{-- x-cloak cho khối chọn loại tài liệu (Alpine) — tránh chớp nháy hiện cả 3 loại cùng lúc. --}}
        <style>
            [x-cloak] { display: none !important; }
        </style>
    @endpush
    {{-- SỬA 6/11 (khách: "mỗi lần chuyển tab là load lại trang, khách muốn không load lại mà dữ liệu vẫn tối ưu") — bấm tab (và các
         link / form lọc GET trong cùng trang lớp, vd. phân trang điểm danh) chỉ tải PHẦN NỘI DUNG tab qua fetch (header X-Csp-Tab,
         xem ClassRoomController::show), thay vào khung rồi đổi địa chỉ (pushState) — Quay lại / Tiến tới của trình duyệt vẫn đúng.
         Máy chủ vẫn chỉ tính dữ liệu của đúng tab đang mở. Rê chuột lên tab là tải trước (dùng 1 lần, tối đa 15 giây) để bấm là hiện
         ngay; không giữ bộ nhớ đệm lâu hơn nên dữ liệu luôn mới. Lỗi mạng / phiên hết hạn → mở trang theo cách thường. --}}
    <style>
        .tcx-panel[aria-busy="true"] { opacity: .55; pointer-events: none; transition: opacity .15s; }
        .csp-load { position: fixed; left: 0; top: 0; z-index: 80; height: 3px; width: 0; background: linear-gradient(90deg, #1F8A63, #34D399); box-shadow: 0 0 8px rgba(31, 138, 99, .5); opacity: 0; transition: width .4s ease, opacity .2s; pointer-events: none; }
        .csp-load.on { opacity: 1; width: 70%; }
        .csp-load.done { opacity: 0; width: 100%; }
    </style>
    <div class="csp-load" aria-hidden="true"></div>
    <script>
        (function () {
            var panel = document.querySelector('[data-csp-panel]');
            if (!panel || !window.fetch || !window.history || !history.pushState || !window.FormData) return;
            var bar = document.querySelector('.csp-load');
            var base = location.pathname.replace(/\/+$/, '');
            var pre = {}; // url -> { t, p } tải trước khi rê chuột, dùng 1 lần
            var seq = 0;
            var HDR = { 'X-Csp-Tab': '1', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' };

            function same(u) { return u.origin === location.origin && u.pathname.replace(/\/+$/, '') === base; }
            function under(u) { var p = u.pathname.replace(/\/+$/, ''); return u.origin === location.origin && (p === base || p.indexOf(base + '/') === 0); }
            function get(url) {
                return fetch(url, { headers: HDR, credentials: 'same-origin', cache: 'no-store' })
                    .then(function (r) { if (!r.ok || r.redirected) throw new Error('http'); return r.text(); });
            }
            function take(url) {
                var c = pre[url]; delete pre[url];
                return (c && Date.now() - c.t < 15000) ? c.p : get(url);
            }
            function tabOf(u) { return new URL(u, location.href).searchParams.get('tab'); }
            function markTab(url) {
                var t = tabOf(url);
                document.querySelectorAll('.csp-tabs .csp-tab').forEach(function (a) {
                    var on = t !== null && tabOf(a.href) === t;
                    a.classList.toggle('on', on);
                    if (on) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current');
                });
            }
            function swap(html) {
                var A = window.Alpine, y = window.scrollY;
                var toasts = window.__flashToasts || (window.__flashToasts = []), n0 = toasts.length;
                panel.style.minHeight = panel.offsetHeight + 'px'; // giữ chiều cao lúc thay → trang không giật
                if (A && A.destroyTree) Array.prototype.forEach.call(panel.children, function (el) { A.destroyTree(el); });
                var put = function () { panel.innerHTML = html; };
                if (A && A.mutateDom) A.mutateDom(put); else put();
                // Chạy <script> của tab theo đúng thứ tự (định nghĩa cspAtt, cspAssign… trước khi Alpine dựng khối).
                Array.prototype.forEach.call(panel.querySelectorAll('script'), function (old) {
                    var s = document.createElement('script');
                    Array.prototype.forEach.call(old.attributes, function (at) { s.setAttribute(at.name, at.value); });
                    s.textContent = old.textContent;
                    old.parentNode.replaceChild(s, old);
                });
                if (A && A.initTree) A.initTree(panel);
                document.documentElement.style.overflow = '';
                // Thông báo (đã lưu / lỗi) của phần vừa tải.
                if (window.toast) toasts.slice(n0).forEach(function (f) { window.toast(f.type, f.message); });
                requestAnimationFrame(function () { panel.style.minHeight = ''; });
                return y;
            }
            function busy(on) {
                if (on) { panel.setAttribute('aria-busy', 'true'); if (bar) { bar.classList.remove('done'); void bar.offsetWidth; bar.classList.add('on'); } }
                else { panel.removeAttribute('aria-busy'); if (bar) { bar.classList.remove('on'); bar.classList.add('done'); } }
            }
            // keep = true: giữ nguyên vị trí cuộn (tải lại chính tab đang xem).
            function go(url, push, keep) {
                var my = ++seq;
                markTab(url);
                busy(true);
                return take(url).then(function (html) {
                    if (my !== seq) return;
                    var y = swap(html);
                    if (push) history.pushState({ csp: 1 }, '', url);
                    if (keep) { window.scrollTo(0, y); return; }
                    var tabs = document.querySelector('.csp-tabs');
                    if (tabs && tabs.getBoundingClientRect().top < 0) tabs.scrollIntoView({ block: 'start' });
                }).catch(function () {
                    if (my === seq) location.href = url; // lỗi / hết phiên đăng nhập → mở trang theo cách thường
                }).finally(function () { if (my === seq) busy(false); });
            }
            window.cspReload = function () { return go(location.href, false, true); };

            // SỬA 7/11 (khách: "bấm nút bị nhảy trang") — form gửi dữ liệu (POST) của trang lớp: gửi bằng fetch, máy chủ xử lý như
            // cũ rồi chuyển về trang lớp → nhận lại đúng phần nội dung tab, thay tại chỗ, GIỮ vị trí cuộn. Máy chủ chuyển sang
            // trang khác (vd. xoá lớp) thì mở trang đó như thường.
            function post(f, submitter) {
                var fd = new FormData(f);
                if (submitter && submitter.name) fd.append(submitter.name, submitter.value);
                var my = ++seq;
                busy(true);
                return fetch(f.action, { method: 'POST', body: fd, headers: HDR, credentials: 'same-origin' }).then(function (r) {
                    if (r.status === 419) { location.reload(); return; }
                    if (!r.ok) throw new Error('http');
                    var u = new URL(r.url);
                    if (!same(u)) { location.href = r.url; return; }
                    return r.text().then(function (html) {
                        if (my !== seq) return;
                        var y = swap(html);
                        if (u.href !== location.href) { history.replaceState({ csp: 1 }, '', u.href); markTab(u.href); }
                        window.scrollTo(0, y);
                    });
                }).catch(function () {
                    if (window.toast) window.toast('error', 'Không lưu được — kiểm tra kết nối mạng rồi thử lại.');
                }).finally(function () { if (my === seq) busy(false); });
            }
            function plainClick(e, a) {
                return !(e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey
                    || a.target || a.hasAttribute('download') || a.hasAttribute('data-csp-full'));
            }

            document.addEventListener('click', function (e) {
                var a = e.target.closest ? e.target.closest('a[href]') : null;
                if (!a || !a.closest('.csp') || !plainClick(e, a)) return;
                var u = new URL(a.href, location.href);
                if (!same(u) || u.hash) return;
                e.preventDefault();
                if (u.href === location.href && a.classList.contains('csp-tab')) return; // đang ở tab này
                go(u.href, true);
            });
            document.addEventListener('submit', function (e) {
                var f = e.target;
                if (!f || f.tagName !== 'FORM' || e.defaultPrevented || f.target || f.hasAttribute('data-csp-full')) return;
                var method = (f.getAttribute('method') || 'get').toLowerCase();
                var u = new URL(f.getAttribute('action') || location.href, location.href);
                if (method === 'get') {
                    // Form lọc dạng GET trong trang lớp (khoảng ngày điểm danh…): chỉ tải phần nội dung.
                    if (!f.closest('[data-csp-panel]') || !same(u)) return;
                    e.preventDefault();
                    u.search = new URLSearchParams(new FormData(f)).toString();
                    go(u.href, true);
                    return;
                }
                // POST: form trong trang lớp (cả hộp thoại đưa ra ngoài bằng x-teleport) gửi tới địa chỉ của lớp này, hoặc có data-csp-ajax.
                if (!(under(u) || f.hasAttribute('data-csp-ajax')) || !(f.closest('.csp') || f.closest('.tam-ov') || f.hasAttribute('data-csp-ajax'))) return;
                if (f.querySelector('input[type="file"]') && Array.prototype.some.call(f.querySelectorAll('input[type="file"]'), function (i) { return i.files && i.files.length; })) return;
                e.preventDefault();
                post(f, e.submitter);
            });
            // Rê chuột / chạm vào tab → tải trước.
            var hoverT = null;
            document.addEventListener('pointerover', function (e) {
                var a = e.target.closest ? e.target.closest('.csp-tabs .csp-tab') : null;
                if (!a || a.classList.contains('on')) return;
                clearTimeout(hoverT);
                hoverT = setTimeout(function () {
                    var c = pre[a.href];
                    if (c && Date.now() - c.t < 15000) return;
                    var p = get(a.href); p.catch(function () { delete pre[a.href]; });
                    pre[a.href] = { t: Date.now(), p: p };
                }, 90);
            });
            document.addEventListener('pointerout', function (e) { if (e.target.closest && e.target.closest('.csp-tabs .csp-tab')) clearTimeout(hoverT); });
            window.addEventListener('popstate', function () {
                if (same(new URL(location.href))) go(location.href, false);
            });
            history.replaceState({ csp: 1 }, '', location.href);
        })();
    </script>

    {{-- SỬA 6/11 — popup "Thêm học sinh": tìm (gõ là tìm, 250ms), chọn nhiều, gửi danh sách id + email / SĐT nhập tay. --}}
    <script>
        window.tamAdd = function (cfg) {
            return {
                open: !!cfg.open, mode: cfg.contacts ? 'contact' : 'pick', q: '', results: [], selected: [], contacts: cfg.contacts || '',
                drop: false, loading: false, submitting: false, timer: null, seq: 0, loaded: false,
                init() { if (this.open) { this.lock(true); if (this.mode === 'pick') this.search(true); } },
                lock(on) { document.documentElement.style.overflow = on ? 'hidden' : ''; },
                openModal() { this.open = true; this.submitting = false; this.lock(true); if (!this.loaded) this.search(true); this.$nextTick(() => { if (this.mode === 'pick' && this.$refs.q) { this.$refs.q.focus(); this.drop = true; } }); },
                close() { this.open = false; this.drop = false; this.lock(false); },
                search(now) {
                    clearTimeout(this.timer);
                    const run = () => {
                        const my = ++this.seq; this.loading = true;
                        fetch(cfg.url + '?q=' + encodeURIComponent(this.q.trim()), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                            .then((r) => r.ok ? r.json() : { items: [] })
                            .then((j) => { if (my === this.seq) { this.results = j.items || []; this.loading = false; this.loaded = true; } })
                            .catch(() => { if (my === this.seq) this.loading = false; });
                    };
                    this.drop = true;
                    now ? run() : (this.timer = setTimeout(run, 250));
                },
                isSel(u) { return this.selected.some((s) => s.id === u.id); },
                toggle(u) { if (u.inClass) return; this.isSel(u) ? this.remove(u.id) : this.selected.push(u); },
                remove(id) { this.selected = this.selected.filter((s) => s.id !== id); },
                get selectable() { return this.results.filter((u) => !u.inClass); },
                get allPicked() { return this.selectable.length > 0 && this.selectable.every((u) => this.isSel(u)); },
                selectAll() { if (this.allPicked) { const ids = this.selectable.map((u) => u.id); this.selected = this.selected.filter((s) => !ids.includes(s.id)); } else { this.selectable.forEach((u) => { if (!this.isSel(u)) this.selected.push(u); }); } },
                pickFirst() { const u = this.selectable.find((x) => !this.isSel(x)); if (u) this.selected.push(u); },
                get typedCount() { return this.contacts.split(/[\r\n,;]+/).map((t) => t.trim()).filter(Boolean).length; },
                get count() { return this.selected.length + this.typedCount; },
                get canSubmit() { return this.selected.length > 0 || this.contacts.trim() !== ''; },
            };
        };
    </script>
@endsection
