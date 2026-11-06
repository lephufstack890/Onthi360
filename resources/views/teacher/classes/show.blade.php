@extends('layouts.teacher')

@section('title', 'Chi tiết lớp')
@section('page-title', 'Chi tiết lớp')

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
        $tcxTabIcons = ['Tổng quan' => 'home', 'Tài liệu' => 'file-text', 'Học liệu' => 'file-text', 'Buổi học' => 'play-circle', 'Giao đề' => 'clipboard-list', 'Kết quả' => 'bar-chart-3', 'Thành viên' => 'users', 'Học sinh' => 'users'];
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
        .tcx-back { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 12px; font-size: 12.5px; font-weight: 600; color: #475569; }
        .tcx-back:hover { color: #1D4ED8; }
        .tcx-back svg { width: 15px; height: 15px; }
        .tcx-hero { position: relative; display: flex; flex-wrap: wrap; align-items: center; gap: 14px 20px; padding: 16px 20px; border: 1px solid #E0ECF9; border-radius: 22px; overflow: hidden;
            background: radial-gradient(120% 140% at 85% 0%, #E6EEFF 0%, rgba(230, 238, 255, 0) 55%), linear-gradient(120deg, #FFFFFF 0%, #F6F9FF 55%, #EEF4FF 100%); box-shadow: 0 10px 30px rgba(30, 64, 175, .07); margin-bottom: 16px; }
        .tcx-media { position: relative; width: 84px; height: 84px; flex: none; border-radius: 18px; overflow: hidden; background: linear-gradient(135deg, #DBEAFE, #E0F2FE); display: grid; place-items: center; font-size: 40px; box-shadow: 0 6px 16px rgba(30, 64, 175, .12); }
        .tcx-media img { width: 100%; height: 100%; object-fit: cover; }
        .tcx-main { flex: 1 1 320px; min-width: 0; }
        .tcx-pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; background: #E8F0FF; color: #1D4ED8; font-size: 12px; font-weight: 700; }
        .tcx-pill svg { width: 14px; height: 14px; }
        .tcx-title { margin: 6px 0 0; font-size: 18px; line-height: 1.2; font-weight: 800; color: var(--tcx-ink); letter-spacing: -.01em; }
        .tcx-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 14px; margin-top: 8px; font-size: 12.5px; color: #475569; }
        .tcx-meta span { display: inline-flex; align-items: center; gap: 7px; }
        .tcx-meta svg { width: 15px; height: 15px; color: #64748B; }
        .tcx-meta i { width: 1px; height: 14px; background: #CBD5E1; }
        .tcx-prog { display: flex; align-items: center; gap: 14px; padding-left: 20px; border-left: 1px solid #DCE6F2; }
        .tcx-prog-txt small { display: block; max-width: 180px; font-size: 12px; line-height: 1.45; font-weight: 600; color: #475569; }
        .tcx-prog-txt b { display: block; margin-top: 3px; font-size: 18px; line-height: 1; font-weight: 800; color: var(--tcx-ink); }
        .tcx-bar { width: 170px; height: 6px; margin-top: 8px; border-radius: 999px; background: #E2E8F0; overflow: hidden; }
        .tcx-bar span { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg, #3B82F6, #2563EB); }
        .tcx-prog-txt em { display: block; margin-top: 5px; font-style: normal; font-size: 11.5px; color: #94A3B8; }
        .tcx-target { display: grid; place-items: center; width: 54px; height: 54px; border-radius: 50%; background: radial-gradient(circle at 35% 30%, #93C5FD, #2563EB 70%); color: #fff; box-shadow: 0 8px 18px rgba(37, 99, 235, .28); flex: none; }
        .tcx-target svg { width: 26px; height: 26px; }
        @media (max-width: 820px) { .tcx-prog { padding-left: 0; border-left: 0; width: 100%; } .tcx-target { display: none; } .tcx-title { font-size: 16px; } .tcx-media { width: 68px; height: 68px; font-size: 32px; } }
        .tcx-tabs { display: flex; gap: 4px; overflow-x: auto; padding: 6px 8px 0; border: 1px solid var(--tcx-line); border-bottom: 0; border-radius: 18px 18px 0 0; background: #fff; width: fit-content; max-width: 100%; scrollbar-width: none; }
        .tcx-tab { position: relative; display: inline-flex; align-items: center; gap: 7px; padding: 10px 14px 12px; font-size: 12.5px; font-weight: 700; color: #334155; white-space: nowrap; }
        .tcx-tab svg { width: 16px; height: 16px; color: #64748B; }
        .tcx-tab:hover { color: #1D4ED8; }
        .tcx-tab.is-on { color: #1D4ED8; }
        .tcx-tab.is-on svg { color: #2563EB; }
        .tcx-tab.is-on::after { content: ''; position: absolute; left: 12px; right: 12px; bottom: 0; height: 3px; border-radius: 3px 3px 0 0; background: #2563EB; }
        .tcx-tab sup { top: 0; padding: 1px 7px; border-radius: 999px; background: #FEE2E2; color: #B91C1C; font-size: 11px; font-weight: 800; }
        .tcx-panel { padding: 16px; border: 1px solid var(--tcx-line); border-radius: 0 18px 18px 18px; background: linear-gradient(180deg, #FBFDFF, #F6F9FD); box-shadow: 0 10px 30px rgba(15, 42, 74, .05); }
        @media (max-width: 640px) { .tcx-panel { padding: 14px; border-radius: 0 0 22px 22px; } .tcx-tabs { border-radius: 18px 18px 0 0; } }
        /* Tab Tài liệu */
        .tcx-toolbar { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; margin-bottom: 12px; }
        .tcx-search { position: relative; flex: 0 1 240px; min-width: 180px; }
        .tcx-search svg { position: absolute; left: 12px; top: 50%; width: 15px; height: 15px; transform: translateY(-50%); color: #64748B; }
        .tcx-search input { box-sizing: border-box; width: 100%; height: 36px; padding: 0 12px 0 34px; border: 1px solid #DCE6F2; border-radius: 999px; background: #fff; font-size: 12.5px; color: var(--tcx-ink); }
        .tcx-search input:focus, .tcx-filter select:focus { outline: 0; border-color: #3B82F6; box-shadow: 0 0 0 3px rgba(59, 130, 246, .15); }
        .tcx-filter { position: relative; }
        .tcx-filter svg { position: absolute; left: 12px; top: 50%; width: 14px; height: 14px; transform: translateY(-50%); color: #334155; pointer-events: none; }
        .tcx-filter select { box-sizing: border-box; height: 36px; padding: 0 12px 0 32px; border: 1px solid #DCE6F2; border-radius: 999px; background: #fff; font-size: 12.5px; font-weight: 700; color: #334155; appearance: none; cursor: pointer; }
        .tcx-docs { display: grid; gap: 10px; }
        .tcx-doc { position: relative; display: flex; flex-wrap: wrap; align-items: center; gap: 10px 14px; padding: 11px 14px 11px 18px; border: 1px solid #E6EEF6; border-radius: 16px; background: #fff; box-shadow: 0 4px 14px rgba(15, 42, 74, .05); overflow: hidden; transition: box-shadow .15s, transform .15s; }
        .tcx-doc:hover { box-shadow: 0 10px 24px rgba(15, 42, 74, .08); transform: translateY(-1px); }
        .tcx-doc::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: #60A5FA; }
        .tcx-doc.t-topic::before { background: #A78BFA; } .tcx-doc.t-exam::before { background: #C084FC; }
        .tcx-ic { display: grid; place-items: center; width: 42px; height: 42px; border-radius: 12px; flex: none; background: #E0EDFF; color: #2563EB; }
        .tcx-ic svg { width: 20px; height: 20px; }
        .tcx-doc.t-topic .tcx-ic { background: #EDE7FE; color: #7C3AED; } .tcx-doc.t-exam .tcx-ic { background: #F3E8FF; color: #9333EA; }
        .tcx-doc-txt { flex: 1 1 200px; min-width: 0; }
        .tcx-doc-txt b { display: block; font-size: 13px; font-weight: 800; color: var(--tcx-ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .tcx-doc-txt small { display: block; margin-top: 2px; font-size: 12px; color: #64748B; }
        .tcx-use { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 999px; background: #E3F6EC; color: #15803D; font-size: 11.5px; font-weight: 700; white-space: nowrap; }
        .tcx-use svg { width: 13px; height: 13px; }
        .tcx-acts { display: flex; align-items: center; gap: 8px; margin-left: auto; }
        .tcx-btn { display: inline-flex; align-items: center; gap: 5px; height: 32px; padding: 0 12px; border: 0; border-radius: 10px; font-size: 12px; font-weight: 700; cursor: pointer; white-space: nowrap; }
        .tcx-btn svg { width: 14px; height: 14px; }
        .tcx-btn.is-view { background: #EEF4FF; color: #1D4ED8; } .tcx-btn.is-view:hover { background: #E0EAFF; }
        .tcx-btn.is-del { background: #FEECEC; color: #DC2626; } .tcx-btn.is-del:hover { background: #FDDCDC; }
        .tcx-btn.is-primary { background: linear-gradient(135deg, #2563EB, #1D4ED8); color: #fff; box-shadow: 0 6px 16px rgba(37, 99, 235, .25); }
        .tcx-sep { width: 1px; height: 22px; background: #E2E8F0; }
        .tcx-empty, .tcx-nomatch { padding: 20px 14px; border: 1.5px dashed #D6E3EF; border-radius: 18px; background: #fff; text-align: center; font-size: 12.5px; color: #64748B; }
        .tcx-add { display: grid; grid-template-columns: minmax(0, 1fr); gap: 14px; margin-top: 12px; padding: 14px; border: 1.5px dashed #BFD4F1; border-radius: 18px; background: #fff; }
        @media (min-width: 900px) { .tcx-add { grid-template-columns: minmax(0, 1fr) minmax(0, 1.1fr); align-items: start; } }
        .tcx-add-intro { display: flex; align-items: center; gap: 14px; }
        .tcx-folder { position: relative; display: grid; place-items: center; width: 72px; height: 62px; flex: none; border-radius: 16px; background: linear-gradient(135deg, #BFDBFE, #60A5FA); color: #fff; box-shadow: 0 12px 24px rgba(37, 99, 235, .2); }
        .tcx-folder svg { width: 30px; height: 30px; }
        .tcx-folder span { position: absolute; right: -6px; bottom: -6px; display: grid; place-items: center; width: 26px; height: 26px; border-radius: 50%; background: #fff; color: #2563EB; box-shadow: 0 6px 14px rgba(15, 42, 74, .15); }
        .tcx-folder span svg { width: 14px; height: 14px; }
        .tcx-add h3 { margin: 0; font-size: 14px; font-weight: 800; color: var(--tcx-ink); }
        .tcx-add p { margin: 3px 0 0; font-size: 12.5px; line-height: 1.5; color: #64748B; }
        .tcx-types { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
        .tcx-type { display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 12px; border: 1px solid #DCE6F2; border-radius: 11px; background: #fff; font-size: 12.5px; font-weight: 700; color: #1E3A5F; cursor: pointer; box-shadow: 0 2px 6px rgba(15, 42, 74, .05); }
        .tcx-type svg { width: 15px; height: 15px; }
        .tcx-type .c-book { color: #2563EB; } .tcx-type .c-topic { color: #7C3AED; } .tcx-type .c-exam { color: #EA580C; }
        .tcx-type em { font-style: normal; padding: 0 6px; border-radius: 999px; background: #EEF2F7; font-size: 11px; color: #475569; }
        .tcx-type.is-on { border-color: #2563EB; background: #EEF4FF; color: #1D4ED8; box-shadow: 0 0 0 3px rgba(37, 99, 235, .12); }
        .tcx-pick { min-height: 120px; padding: 10px; border: 1px solid #E3ECF5; border-radius: 14px; background: #F8FBFE; }
        .tcx-pick-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 10px; padding: 8px 10px; border-radius: 12px; background: #fff; border: 1px solid #E6EEF6; }
        .tcx-pick-row + .tcx-pick-row { margin-top: 8px; }
        .tcx-pick-row b { display: block; font-size: 13px; color: var(--tcx-ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .tcx-pick-row small { display: block; margin-top: 2px; font-size: 12px; color: #94A3B8; }
        .tcx-pick-empty { display: grid; place-items: center; min-height: 100px; text-align: center; font-size: 12.5px; color: #94A3B8; }
        /* Tab Tổng quan */
        .tcx-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
        .tcx-stat { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid #E6EEF6; border-radius: 16px; background: #fff; box-shadow: 0 4px 14px rgba(15, 42, 74, .05); }
        .tcx-stat .tcx-ic { width: 40px; height: 40px; border-radius: 12px; }
        .tcx-stat .tcx-ic svg { width: 19px; height: 19px; }
        .tcx-stat small { display: block; font-size: 12px; font-weight: 600; color: #64748B; }
        .tcx-stat b { display: block; margin-top: 1px; font-size: 15px; font-weight: 800; color: var(--tcx-ink); }
        .tcx-ic.g { background: #E3F6EC; color: #15803D; } .tcx-ic.a { background: #FFF1D6; color: #B45309; } .tcx-ic.v { background: #EDE7FE; color: #7C3AED; }
        .tcx-cards { display: grid; grid-template-columns: minmax(0, 1fr); gap: 12px; margin-top: 12px; }
        @media (min-width: 900px) { .tcx-cards { grid-template-columns: 1fr 1fr; } }
        .tcx-card { padding: 14px 16px; border: 1px solid #E6EEF6; border-radius: 16px; background: #fff; box-shadow: 0 4px 14px rgba(15, 42, 74, .05); }
        .tcx-card h3 { display: flex; align-items: center; gap: 8px; margin: 0 0 10px; font-size: 13.5px; font-weight: 800; color: var(--tcx-ink); }
        .tcx-card h3 .tcx-ic { width: 32px; height: 32px; border-radius: 10px; } .tcx-card h3 .tcx-ic svg { width: 16px; height: 16px; }
        .tcx-card p { margin: 0; font-size: 12.5px; color: #475569; }
        /* SỬA 3/11 (khách: "tab Buổi học, Kết quả, Thành viên cũng thiết kế lại hiện đại") */
        .tcx-sec { padding: 14px 16px; border: 1px solid #E6EEF6; border-radius: 16px; background: #fff; box-shadow: 0 4px 14px rgba(15, 42, 74, .05); }
        .tcx-sec + .tcx-sec { margin-top: 12px; }
        .tcx-sec-head { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 12px; margin-bottom: 12px; }
        .tcx-sec-head h3 { margin: 0; font-size: 14px; font-weight: 800; color: var(--tcx-ink); }
        .tcx-sec-head p { margin: 2px 0 0; font-size: 12px; color: #64748B; }
        .tcx-sec-head .tcx-ic { width: 36px; height: 36px; border-radius: 11px; } .tcx-sec-head .tcx-ic svg { width: 18px; height: 18px; }
        .tcx-count { margin-left: auto; padding: 3px 10px; border-radius: 999px; background: #EEF4FF; color: #1D4ED8; font-size: 11.5px; font-weight: 800; white-space: nowrap; }
        .tcx-count.is-amber { background: #FFF1D6; color: #B45309; }
        .tcx-form-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 10px; }
        @media (min-width: 640px) { .tcx-form-grid { grid-template-columns: 1fr 1fr; } }
        .tcx-label { display: block; margin-bottom: 4px; font-size: 12px; font-weight: 700; color: #475569; }
        .tcx-sess { display: grid; gap: 8px; }
        .tcx-ses { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 14px; padding: 10px 12px; border: 1px solid #E6EEF6; border-radius: 14px; background: #fff; transition: box-shadow .15s; }
        .tcx-ses:hover { box-shadow: 0 8px 18px rgba(15, 42, 74, .06); }
        .tcx-date { display: grid; place-items: center; width: 52px; flex: none; padding: 6px 0; border-radius: 12px; background: #EEF4FF; color: #1D4ED8; line-height: 1.1; text-align: center; }
        .tcx-date b { font-size: 16px; font-weight: 800; } .tcx-date small { font-size: 10.5px; font-weight: 700; text-transform: uppercase; }
        .tcx-ses-txt { flex: 1 1 220px; min-width: 0; }
        .tcx-ses-txt b { display: block; font-size: 13.5px; font-weight: 800; color: var(--tcx-ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .tcx-ses-txt span { display: inline-flex; align-items: center; gap: 4px; margin: 3px 12px 0 0; font-size: 12px; color: #64748B; }
        .tcx-ses-txt span svg { width: 13px; height: 13px; }
        .tcx-chip { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 800; white-space: nowrap; background: #F1F5F9; color: #475569; }
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
        .tcx-person-txt b { display: block; font-size: 13px; font-weight: 800; color: var(--tcx-ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .tcx-person-txt small { display: block; margin-top: 1px; font-size: 11.5px; color: #64748B; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .tcx-person form { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 8px; }
        .tcx-person form .tcx-input { flex: 1 1 160px; }
        .tcx-req { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; padding: 10px 12px; border: 1px solid #FDE7C2; border-radius: 14px; background: #FFFCF5; }
        .tcx-req + .tcx-req { margin-top: 8px; }
        .tcx-req-acts { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-left: auto; }
        .tcx-req-acts form { display: flex; align-items: center; gap: 6px; }
        /* SỬA 6/11 — form + kết quả "Thêm học sinh vào lớp" */
        .tcx-addform { display: grid; gap: 8px; margin: 0 0 12px; padding: 12px 14px; border: 1px dashed #BBF7D0; border-radius: 14px; background: #F0FDF4; }
        .tcx-addform label { font-size: 12.5px; color: #3D566B; }
        .tcx-addform textarea.tcx-input { height: auto; min-height: 76px; padding: 8px 10px; line-height: 1.6; resize: vertical; font-family: inherit; }
        .tcx-addform-acts { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .tcx-addform-acts small { font-size: 11.5px; color: #64748B; }
        .tcx-addres { display: grid; gap: 6px; margin-bottom: 12px; }
        .tcx-addres p { display: flex; align-items: flex-start; gap: 7px; margin: 0; padding: 9px 12px; border-radius: 12px; font-size: 12.5px; line-height: 1.5; }
        .tcx-addres p svg { width: 15px; height: 15px; flex: none; margin-top: 2px; }
        .tcx-addres .ok { background: #F0FDF4; color: #166534; border: 1px solid #BBF7D0; }
        .tcx-addres .info { background: #EFF6FF; color: #1E40AF; border: 1px solid #BFDBFE; }
        .tcx-addres .warn { background: #FFFBEB; color: #92400E; border: 1px solid #FDE68A; }
        .tcx-alert { display: flex; gap: 8px; align-items: flex-start; margin-bottom: 12px; padding: 10px 12px; border: 1px solid #FECACA; border-radius: 12px; background: #FFF5F5; font-size: 12.5px; font-weight: 600; color: #991B1B; }
        .tcx-alert svg { width: 16px; height: 16px; flex: none; margin-top: 1px; }
        .tcx-results { display: grid; grid-template-columns: minmax(0, 1fr); gap: 12px; }
        @media (min-width: 900px) { .tcx-results { grid-template-columns: 1.4fr 1fr; } }
        .tcx-res-hero { display: flex; flex-wrap: wrap; align-items: center; gap: 14px; padding: 18px; border-radius: 16px; background: linear-gradient(120deg, #EEF4FF, #F5F9FF 60%, #fff); border: 1px solid #DCE8FA; }
        .tcx-res-hero .tcx-ic { width: 52px; height: 52px; border-radius: 15px; background: linear-gradient(135deg, #3B82F6, #1D4ED8); color: #fff; }
        .tcx-res-hero .tcx-ic svg { width: 24px; height: 24px; }
        .tcx-res-hero h3 { margin: 0; font-size: 14px; font-weight: 800; color: var(--tcx-ink); }
        .tcx-res-hero p { margin: 3px 0 0; font-size: 12.5px; color: #475569; }
        .tcx-res-list { margin: 0; padding: 0; list-style: none; display: grid; gap: 8px; }
        .tcx-res-list li { display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: #475569; }
        .tcx-res-list li svg { width: 15px; height: 15px; color: #16A34A; flex: none; }
    </style>

    <div class="tcx">
    <a href="{{ route('teacher.classes.index') }}" class="tcx-back"><x-lucide name="arrow-left" />Quay lại lớp học</a>

    @if (session('status') === 'class-created')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tạo lớp thành công — bạn là giáo viên chính của lớp này.'])
    @endif

    <section class="tcx-hero">
        <div class="tcx-media">
            @if ($tcxCover)
                <img src="{{ $tcxCover }}" alt="{{ $courseTitle }}">
            @else
                <span aria-hidden="true">🏫</span>
            @endif
        </div>
        <div class="tcx-main">
            @if ($courseTitle !== '')
                <span class="tcx-pill"><x-lucide name="graduation-cap" />{{ $courseTitle }}</span>
            @endif
            <h1 class="tcx-title">{{ $className }}</h1>
            <div class="tcx-meta">
                <span><x-lucide name="users" />{{ $studentsCount }} học sinh</span>
                <i aria-hidden="true"></i>
                <span><x-lucide name="calendar-days" />{{ $nextSessionLabel }}</span>
            </div>
        </div>
        <div class="tcx-prog">
            <div class="tcx-prog-txt">
                <small>Hoàn thành chung (theo buổi học)</small>
                <b>{{ $tcxCompletion }}%</b>
                <div class="tcx-bar"><span style="width: {{ max(0, min(100, $tcxCompletion)) }}%"></span></div>
                @if (($completionTotalSessions ?? 0) > 0)
                    <em>{{ $completionEndedSessions }}/{{ $completionTotalSessions }} buổi đã học</em>
                @endif
            </div>
            <span class="tcx-target" aria-hidden="true"><x-lucide name="target" /></span>
        </div>
    </section>

    <nav class="tcx-tabs" aria-label="Các mục của lớp">
        @foreach ($tabsData as $t)
            {{-- SỬA 3/11 (khách: "tạm thời ẩn tab Kết quả") — chỉ ẩn nút tab; muốn hiện lại thì bỏ dòng continue ngay dưới. --}}
            @continue($t['label'] === 'Kết quả')
            <a href="{{ $t['href'] }}" class="tcx-tab {{ ($t['active'] ?? false) ? 'is-on' : '' }}" @if ($t['active'] ?? false) aria-current="page" @endif>
                <x-lucide :name="$tcxTabIcons[$t['label']] ?? 'file-text'" />{{ $t['label'] }}
                @isset($t['count'])<sup>{{ $t['count'] }}</sup>@endisset
            </a>
        @endforeach
    </nav>

    <div class="tcx-panel">
    @if ($tab === 'materials')
        {{-- Tab Tài liệu — logic như cũ (gắn NGUYÊN 1 sản phẩm, chỉ hiện sách/chuyên đề/bộ đề còn quyền dạy, Gỡ = "Đã gỡ").
             Tìm kiếm + lọc loại chạy ngay trên trang (JS ở cuối), không gọi máy chủ. --}}
        @php
            $attachableProducts = $attachableProducts ?? [];
            $hasAnyAttachable = collect($attachableProducts)->sum(fn ($g) => count($g['products'])) > 0;
            $defaultType = null;
            foreach ($attachableProducts as $typeKey => $group) {
                if (count($group['products']) > 0) { $defaultType = $typeKey; break; }
            }
            $defaultType = $defaultType ?? array_key_first($attachableProducts);
        @endphp

        @if (session('status') === 'material-attached')
            @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã thêm tài liệu vào lớp — học sinh trong lớp xem được ngay, không cần tự mua riêng.'])
        @elseif (session('status') === 'material-detached')
            @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã gỡ tài liệu — lịch sử bài làm cũ vẫn giữ nguyên.'])
        @endif
        @if ($errors->any())
            @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
        @endif

        <div data-tcx-docs>
            @if (count($materials) > 0)
                <div class="tcx-toolbar">
                    <label class="tcx-search"><x-lucide name="search" /><input type="search" placeholder="Tìm kiếm tài liệu…" aria-label="Tìm kiếm tài liệu" data-tcx-q></label>
                    <label class="tcx-filter"><x-lucide name="filter" />
                        <select aria-label="Lọc theo loại" data-tcx-type>
                            <option value="">Bộ lọc: Tất cả</option>
                            <option value="book">Sách</option>
                            <option value="topic">Chuyên đề</option>
                            <option value="exam">Bộ đề</option>
                        </select>
                    </label>
                </div>
            @endif

            <div class="tcx-docs">
                @forelse ($materials as $m)
                    @php $mt = $tcxType($m['typeLabel'] ?? ''); @endphp
                    <div class="tcx-doc t-{{ $mt }}" data-tcx-row data-type="{{ $mt }}" data-k="{{ mb_strtolower($m['title'].' '.$tcxClean($m['typeLabel'] ?? '')) }}">
                        <span class="tcx-ic"><x-lucide :name="$tcxTypeIcon[$mt]" /></span>
                        <div class="tcx-doc-txt">
                            <b title="{{ $m['title'] }}">{{ $m['title'] }}</b>
                            <small>{{ $tcxClean($m['typeLabel'] ?? '') ?: $tcxTypeName[$mt] }}</small>
                        </div>
                        <span class="tcx-use"><x-lucide name="check-circle-2" />{{ $m['scope'] }}</span>
                        <div class="tcx-acts">
                            @if ($m['readHref'] ?? null)
                                <a href="{{ $m['readHref'] }}" target="_blank" rel="noopener" class="tcx-btn is-view"><x-lucide name="eye" />Xem trước</a>
                                <span class="tcx-sep" aria-hidden="true"></span>
                            @endif
                            <form method="POST" action="{{ route('teacher.classes.materials.detach', ['class' => $classRoom->id, 'classMaterial' => $m['id']]) }}"
                                  onsubmit="return confirm('Gỡ tài liệu này khỏi lớp? Lịch sử bài làm cũ vẫn giữ nguyên.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="tcx-btn is-del"><x-lucide name="trash-2" />Gỡ khỏi lớp</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="tcx-empty">Lớp chưa gắn tài liệu nào — chọn tài liệu ở khung bên dưới để thêm.</div>
                @endforelse
                <div class="tcx-nomatch" data-tcx-nomatch hidden>Không có tài liệu nào khớp tìm kiếm / bộ lọc.</div>
            </div>
        </div>

        <div class="tcx-add" x-data="{ type: '{{ $defaultType }}' }">
            <div>
                <div class="tcx-add-intro">
                    <span class="tcx-folder" aria-hidden="true"><x-lucide name="book-open" /><span><x-lucide name="plus" /></span></span>
                    <div>
                        <h3>Thêm tài liệu vào lớp</h3>
                        <p>Chọn loại tài liệu rồi chọn đúng cuốn. Chỉ hiện sách, chuyên đề, bộ đề bạn còn quyền dạy (còn hạn).</p>
                    </div>
                </div>
                @if (! empty($attachableProducts))
                    <div class="tcx-types">
                        @foreach ($attachableProducts as $typeKey => $group)
                            <button type="button" class="tcx-type" :class="type === '{{ $typeKey }}' && 'is-on'" @click="type = '{{ $typeKey }}'">
                                <x-lucide :name="$tcxTypeIcon[$typeKey] ?? 'book-open'" class="c-{{ $typeKey }}" />{{ $tcxClean($group['label']) }}<em>{{ count($group['products']) }}</em>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="tcx-pick">
                @if (empty($attachableProducts))
                    <div class="tcx-pick-empty">Không có sách / chuyên đề / bộ đề nào bạn còn quyền dạy để thêm — quyền dạy đã hết hạn hoặc chưa được cấp.</div>
                @elseif (! $hasAnyAttachable)
                    <div class="tcx-pick-empty">Mọi sách / chuyên đề / bộ đề bạn còn quyền dạy đã gắn hết vào lớp này rồi.</div>
                @else
                    @foreach ($attachableProducts as $typeKey => $group)
                        <div x-show="type === '{{ $typeKey }}'" x-cloak>
                            @forelse ($group['products'] as $p)
                                <form method="POST" action="{{ route('teacher.classes.materials.attach', $classRoom->id) }}" class="tcx-pick-row">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $p['id'] }}">
                                    <span class="tcx-ic" style="width:34px;height:34px;border-radius:10px"><x-lucide :name="$tcxTypeIcon[$typeKey] ?? 'book-open'" style="width:17px;height:17px" /></span>
                                    <div style="flex:1 1 180px;min-width:0">
                                        <b title="{{ $p['title'] }}">{{ $p['title'] }}</b>
                                        <small>Dùng được ở mọi lớp phụ trách đến {{ $p['expiresAtLabel'] ?? '—' }}</small>
                                    </div>
                                    <button type="submit" class="tcx-btn is-primary"><x-lucide name="plus" />Thêm vào lớp</button>
                                </form>
                            @empty
                                <div class="tcx-pick-empty">Không còn {{ mb_strtolower($tcxClean($group['label'])) }} nào để thêm.</div>
                            @endforelse
                        </div>
                    @endforeach
                @endif
            </div>
        </div>

        <script>
        (() => {
            const box = document.querySelector('[data-tcx-docs]');
            if (!box) return;
            const q = box.querySelector('[data-tcx-q]'), type = box.querySelector('[data-tcx-type]'), none = box.querySelector('[data-tcx-nomatch]');
            const rows = [...box.querySelectorAll('[data-tcx-row]')];
            const fold = (s) => String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd');
            rows.forEach((r) => { r.dataset.f = fold(r.dataset.k); });
            const apply = () => {
                const k = fold(q ? q.value.trim() : ''), t = type ? type.value : '';
                let shown = 0;
                rows.forEach((r) => { const ok = (!t || r.dataset.type === t) && (!k || r.dataset.f.includes(k)); r.hidden = !ok; if (ok) shown++; });
                if (none) none.hidden = rows.length === 0 || shown > 0;
            };
            if (q) q.addEventListener('input', apply);
            if (type) type.addEventListener('change', apply);
        })();
        </script>
    @elseif ($tab === 'schedule')
        @php $sessions = $sessions ?? []; @endphp

        @if (session('status') === 'session-created')
            @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tạo buổi học mới.'])
        @endif
        @if ($errors->any())
            @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
        @endif

        <section class="tcx-sec" x-data="{ openForm: {{ $errors->any() || count($sessions) === 0 ? 'true' : 'false' }} }">
            <div class="tcx-sec-head" style="margin-bottom:0">
                <span class="tcx-ic"><x-lucide name="plus" /></span>
                <div style="flex:1 1 220px;min-width:0"><h3>Tạo buổi học mới</h3><p>Chủ đề, địa điểm / link và thời gian bắt đầu – kết thúc.</p></div>
                <button type="button" class="tcx-btn is-view" @click="openForm = ! openForm"><x-lucide name="plus" /><span x-text="openForm ? 'Thu gọn' : 'Tạo buổi học'">Tạo buổi học</span></button>
            </div>
            <form method="POST" action="{{ route('teacher.schedule.store') }}" x-show="openForm" x-cloak x-data="{ startsDate: '{{ old('starts_date', '') }}', endsDate: '{{ old('ends_date', '') }}' }" style="display:grid;gap:12px;margin-top:12px">
                @csrf
                <input type="hidden" name="class_room_id" value="{{ $classRoom->id }}">
                <input type="hidden" name="back_to_class" value="1">

                <div class="tcx-form-grid">
                    <div>
                        <label class="tcx-label" for="topic">Chủ đề buổi học</label>
                        <input id="topic" type="text" name="topic" maxlength="255" value="{{ old('topic') }}" placeholder="Ví dụ: Ôn tập chương 3" class="admin-input">
                    </div>
                    <div>
                        <label class="tcx-label" for="location">Địa điểm / link</label>
                        <input id="location" type="text" name="location" maxlength="255" value="{{ old('location') }}" placeholder="Phòng học hoặc link online" class="admin-input">
                    </div>
                </div>

                @include('partials.session-datetime-fields')

                <div><button type="submit" class="tcx-btn is-primary"><x-lucide name="calendar-days" />Tạo buổi học</button></div>
            </form>
        </section>

        <section class="tcx-sec">
            <div class="tcx-sec-head">
                <span class="tcx-ic v"><x-lucide name="calendar-days" /></span>
                <div><h3>Lịch buổi học</h3><p>Mới nhất ở trên — bấm Điểm danh để ghi nhận học sinh có mặt.</p></div>
                <span class="tcx-count">{{ count($sessions) }} buổi</span>
            </div>
            @if (count($sessions) === 0)
                <div class="tcx-empty">Lớp chưa có buổi học nào — bấm "Tạo buổi học" ở trên để thêm buổi đầu tiên.</div>
            @else
                <div class="tcx-sess">
                    @foreach ($sessions as $s)
                        @php $sd = $s['startsAt'] ?? null; @endphp
                        <div class="tcx-ses">
                            <span class="tcx-date">
                                @if ($sd)
                                    <small>Th {{ $sd->format('m') }}</small><b>{{ $sd->format('d') }}</b><small>{{ $sd->format('Y') }}</small>
                                @else
                                    <x-lucide name="calendar-days" />
                                @endif
                            </span>
                            <div class="tcx-ses-txt">
                                <b>{{ $s['topic'] ?: 'Buổi học' }}</b>
                                <span><x-lucide name="clock-3" />{{ $s['timeRangeLabel'] }}</span>
                                @if ($s['location'])<span><x-lucide name="home" />{{ $s['location'] }}</span>@endif
                            </div>
                            <span class="tcx-chip t-{{ $s['timeStatusTone'] }}">{{ $s['timeStatusLabel'] }}</span>
                            <span class="tcx-chip t-{{ $s['attendanceTaken'] ? 'success' : 'warning' }}">{{ $s['attendanceSummary'] }}</span>
                            <a href="{{ route('teacher.schedule.attendance', $s['id']) }}" class="tcx-btn is-view" style="margin-left:auto"><x-lucide name="check" />Điểm danh</a>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @elseif ($tab === 'assign')
        @php
            $assignments = $assignments ?? [];
            $assignableAssessments = $assignableAssessments ?? [];
        @endphp

        {{-- SỬA 24/8 — khách yêu cầu: giao đề ngay tại đây, chỉ cần chọn đề có sẵn (lớp đã
             biết trước qua trang này) — không còn giao được từ trang "Bài tập & Đề" nữa. --}}
        @if (session('status') === 'class-exam-assigned')
            @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã giao đề cho lớp này.'])
        @endif
        @if ($errors->any())
            @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
        @endif

        <div class="bg-white rounded-3xl border border-sky-100 p-5 mb-4 space-y-3">
            {{-- SỬA 8/9 (5) — màn "Luyện tập" có thể đang ẩn (config/features.php); khi ẩn thì
                 không trỏ người dùng sang đó nữa mà chỉ sang "Đề PDF của tôi". Giao đề vẫn chạy
                 bình thường với các đề đã có. --}}
            @if (config('features.teacher_practice_screen', false))
                <p class="text-[13px] text-slate-500">Giao đề dùng cho kiểm tra có thời điểm mở-đóng, hạn nộp riêng (8.4). Chưa có đề? Tạo ở trang <a href="{{ route('teacher.assessments.create') }}" class="text-blue-600 font-medium">Luyện tập</a> trước, rồi quay lại đây để chọn và giao.</p>
            @else
                <p class="text-[13px] text-slate-500">Giao đề dùng cho kiểm tra có thời điểm mở-đóng, hạn nộp riêng (8.4). Chưa có đề? Tạo ở trang <a href="{{ route('teacher.questions.index', ['tab' => 'assessments']) }}" class="text-blue-600 font-medium">Kho bài tập / câu hỏi và đề</a> trước, rồi quay lại đây để chọn và giao.</p>
            @endif

            @if (empty($assignableAssessments))
                <p class="text-[13px] text-slate-400">Bạn chưa có đề nào — tạo đề ở "{{ config('features.teacher_practice_screen', false) ? 'Luyện tập' : 'Kho bài tập / câu hỏi và đề' }}" trước khi giao cho lớp này.</p>
            @else
                <form method="POST" action="{{ route('teacher.classes.assign', $classRoom->id) }}" class="rounded-xl bg-slate-50 border border-sky-100 p-4 space-y-2">
                    @csrf
                    <p class="text-xs font-medium text-slate-500 mb-1">+ Giao đề có sẵn cho lớp này</p>
                    <x-ws.select name="assessment_id" required>
                        <option value="">— Chọn đề —</option>
                        @foreach ($assignableAssessments as $ass)
                            <option value="{{ $ass['id'] }}" @selected((string) old('assessment_id') === (string) $ass['id'])>{{ $ass['title'] }}{{ $ass['status'] === 'Nháp' ? ' (Nháp — tự phát hành khi giao)' : '' }}</option>
                        @endforeach
                    </x-ws.select>
                    <div class="space-y-2">
                        @include('partials.optional-date-hour-minute-fields', ['prefix' => 'opens', 'label' => 'Mở lúc (tùy chọn)'])
                        @include('partials.optional-date-hour-minute-fields', ['prefix' => 'closes', 'label' => 'Đóng lúc (tùy chọn)'])
                        <p class="text-[11px] text-slate-400">Để trống Ngày nếu không giới hạn mốc thời gian đó.</p>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-600 mb-1" for="shift_count">Chia ca thi (tùy chọn — chống nghẽn khi đông thí sinh)</label>
                        <input id="shift_count" name="shift_count" type="number" min="1" max="20" value="{{ old('shift_count') }}"
                               class="admin-input" placeholder="VD: 3 (cần đủ cả Mở lúc + Đóng lúc)">
                    </div>
                    <textarea name="instructions" rows="2" class="admin-input" placeholder="Hướng dẫn làm bài (tùy chọn)...">{{ old('instructions') }}</textarea>
                    <p class="text-xs text-slate-400">Đề sẽ tự động phát hành nếu mọi câu đã đủ điều kiện (6.2), không hỗ trợ ngoại lệ từng học sinh (8.4).</p>
                    <button type="submit" class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700">Giao đề cho lớp</button>
                </form>
            @endif
        </div>

        <x-ws.table :columns="['Tên đề', 'Mở lúc', 'Đóng lúc', 'Trạng thái', '']">
            @forelse ($assignments as $ag)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-medium text-slate-700">
                        {{ $ag['title'] }}
                        @if ($ag['instructions'])
                            <p class="text-xs text-slate-400 font-normal mt-0.5">{{ $ag['instructions'] }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-500">{{ $ag['opensAtLabel'] }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $ag['closesAtLabel'] }}</td>
                    <td class="px-4 py-3"><x-ws.badge :tone="$ag['statusTone']">{{ $ag['statusLabel'] }}</x-ws.badge></td>
                    <td class="px-4 py-3 text-right"><a href="{{ route('teacher.results.index', ['class' => $classRoom->id, 'assessment' => $ag['id']]) }}" class="text-blue-600 font-medium">Xem kết quả</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Lớp chưa có đề nào được giao — chọn đề ở khung trên để giao.</td></tr>
            @endforelse
        </x-ws.table>
    @elseif ($tab === 'results')
        <div class="tcx-results">
            <div class="tcx-res-hero">
                <span class="tcx-ic"><x-lucide name="bar-chart-3" /></span>
                <div style="flex:1 1 240px;min-width:0">
                    <h3>Kết quả học tập của lớp</h3>
                    <p>Xem điểm, bài nộp và tiến độ chi tiết của từng học sinh trong lớp.</p>
                </div>
                <a href="{{ route('teacher.results.index') }}" class="tcx-btn is-primary"><x-lucide name="trending-up" />Xem kết quả chi tiết</a>
            </div>
            <div class="tcx-sec">
                <div class="tcx-sec-head" style="margin-bottom:10px"><span class="tcx-ic g"><x-lucide name="check-circle-2" /></span><div><h3>Trong trang kết quả</h3></div></div>
                <ul class="tcx-res-list">
                    <li><x-lucide name="check" />Điểm từng bài giao, từng học sinh</li>
                    <li><x-lucide name="check" />Bài đã nộp / chưa nộp</li>
                    <li><x-lucide name="check" />Tiến độ học tập của lớp</li>
                </ul>
            </div>
        </div>
    @elseif ($tab === 'members')
        {{-- Giữ nguyên luồng: duyệt / từ chối yêu cầu vào lớp (approveJoinRequest / rejectJoinRequest), gỡ học sinh khỏi lớp
             (removeStudent — chuyển 'left', không xoá dữ liệu). Chỉ đổi giao diện; ô tìm học sinh lọc ngay trên trang. --}}
        @php $pendingRequests = $pendingRequests ?? collect(); @endphp

        @if (session('status') === 'join-request-approved')
            @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã duyệt — học sinh vào học được ngay.'])
        @elseif (session('status') === 'join-request-rejected')
            @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã từ chối yêu cầu và báo cho học sinh.'])
        @elseif (session('status') === 'member-removed')
            @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã gỡ học sinh khỏi lớp và gửi thông báo cho em.'])
        @endif

        {{-- SỬA 6/11 — kết quả "Thêm học sinh vào lớp" --}}
        @php $tcxAdded = session('members_added'); @endphp
        @if (is_array($tcxAdded))
            @if (! empty($tcxAdded['added']))
                @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã thêm '.count($tcxAdded['added']).' học sinh vào lớp.'])
            @endif
            <div class="tcx-addres">
                @if (! empty($tcxAdded['added']))<p class="ok"><x-lucide name="check-circle-2" />Đã thêm vào lớp: <b>{{ implode(', ', $tcxAdded['added']) }}</b></p>@endif
                @if (! empty($tcxAdded['already']))<p class="info"><x-lucide name="info" />Đã ở trong lớp từ trước: {{ implode(', ', $tcxAdded['already']) }}</p>@endif
                @if (! empty($tcxAdded['notFound']))<p class="warn"><x-lucide name="alert-triangle" />Không tìm thấy tài khoản: {{ implode(', ', $tcxAdded['notFound']) }} — kiểm tra lại email / số điện thoại học sinh đã đăng ký.</p>@endif
                @if (! empty($tcxAdded['notStudent']))<p class="warn"><x-lucide name="alert-triangle" />Không phải tài khoản học sinh (hoặc đang bị khoá): {{ implode(', ', $tcxAdded['notStudent']) }}</p>@endif
            </div>
        @endif

        @if ($errors->any())
            <div class="tcx-alert"><x-lucide name="alert-triangle" /><div>@foreach ($errors->all() as $message)<p style="margin:0">{{ $message }}</p>@endforeach</div></div>
        @endif

        <section class="tcx-sec">
            <div class="tcx-sec-head">
                <span class="tcx-ic a"><x-lucide name="user-plus" /></span>
                <div style="flex:1 1 220px;min-width:0"><h3>Yêu cầu vào lớp chờ duyệt</h3><p>Học sinh bấm "Đăng ký học" ở trang Lớp học — duyệt là các em vào học được ngay.</p></div>
                <span class="tcx-count is-amber">{{ $pendingRequests->count() }} yêu cầu</span>
            </div>
            @if ($pendingRequests->isEmpty())
                <div class="tcx-empty" style="padding:14px">Hiện chưa có yêu cầu nào chờ duyệt.</div>
            @else
                @foreach ($pendingRequests as $req)
                    <div class="tcx-req">
                        <x-ws.avatar :name="$req->student->name ?? 'Học sinh'" size="md" />
                        <div class="tcx-person-txt" style="flex:1 1 200px">
                            <b>{{ $req->student->name ?? 'Học sinh đã bị xoá' }}</b>
                            <small>{{ $req->student->email ?? '' }}@if ($req->requested_at) · xin vào lúc {{ $req->requested_at->format('H:i d/m/Y') }}@endif</small>
                        </div>
                        <div class="tcx-req-acts">
                            <form method="POST" action="{{ route('teacher.classes.requests.approve', ['class' => $classRoom->id, 'enrollment' => $req->id]) }}">
                                @csrf
                                <button type="submit" class="tcx-btn is-green"><x-lucide name="check" />Duyệt</button>
                            </form>
                            <form method="POST" action="{{ route('teacher.classes.requests.reject', ['class' => $classRoom->id, 'enrollment' => $req->id]) }}">
                                @csrf
                                <input type="text" name="reason" maxlength="255" placeholder="Lý do (không bắt buộc)" class="tcx-input" style="width:170px">
                                <button type="submit" class="tcx-btn is-del"><x-lucide name="x" />Từ chối</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            @endif
        </section>

        <section class="tcx-sec" x-data="{ q: '', adding: {{ $errors->has('contacts') ? 'true' : 'false' }} }">
            <div class="tcx-sec-head">
                <span class="tcx-ic"><x-lucide name="users" /></span>
                <div style="flex:1 1 220px;min-width:0"><h3>Học sinh đang học</h3><p>Gỡ khỏi lớp: em mất quyền vào lớp ngay; bài làm, điểm danh cũ vẫn giữ nguyên.</p></div>
                <span class="tcx-count">{{ $members->count() }} học sinh</span>
                {{-- SỬA 6/11 (khách: "cho giáo viên thêm học sinh vào lớp thủ công") --}}
                <button type="button" class="tcx-btn is-green" @click="adding = !adding"><x-lucide name="user-plus" />Thêm học sinh</button>
            </div>
            <form x-show="adding" x-cloak method="POST" action="{{ route('teacher.classes.members.add', $classRoom->id) }}" class="tcx-addform">
                @csrf
                <label for="tcx-contacts"><b>Thêm học sinh vào lớp</b> — nhập <b>email</b> hoặc <b>số điện thoại</b> học sinh đã dùng để đăng ký tài khoản, mỗi dòng một người (tối đa 50).</label>
                <textarea id="tcx-contacts" name="contacts" rows="3" class="tcx-input" placeholder="vd: nguyenvana@gmail.com&#10;0912345678">{{ old('contacts') }}</textarea>
                <div class="tcx-addform-acts">
                    <button type="submit" class="tcx-btn is-green"><x-lucide name="user-plus" />Thêm vào lớp</button>
                    <button type="button" class="tcx-btn is-ghost" @click="adding = false">Huỷ</button>
                    <small>Học sinh vào lớp ngay, không cần chờ duyệt, và nhận được thông báo.</small>
                </div>
            </form>
            @if ($members->count() > 6)
                <label class="tcx-search" style="display:block;max-width:320px;margin-bottom:10px"><x-lucide name="search" /><input type="search" placeholder="Tìm tên hoặc email học sinh…" x-model="q" aria-label="Tìm học sinh"></label>
            @endif
            @if ($members->isEmpty())
                <div class="tcx-empty">Lớp chưa có học sinh nào. Bấm <b>Thêm học sinh</b> để thêm bằng email / số điện thoại, hoặc để các em đăng ký ở trang Lớp học rồi duyệt ở khối phía trên.</div>
            @else
                <div class="tcx-people">
                    @foreach ($members as $m)
                        <div x-data="{ confirming: false }" class="tcx-person" :class="confirming && 'is-confirm'"
                             data-k="{{ mb_strtolower($m->name.' '.($m->email ?? '')) }}" x-show="q === '' || $el.dataset.k.includes(q.toLowerCase())">
                            <div class="tcx-person-row">
                                <x-ws.avatar :name="$m->name" size="md" />
                                <div class="tcx-person-txt">
                                    <b>{{ $m->name }}</b>
                                    @if ($m->email)<small>{{ $m->email }}</small>@endif
                                </div>
                                <button type="button" x-show="! confirming" x-on:click="confirming = true" class="tcx-btn is-ghost"><x-lucide name="log-out" />Gỡ khỏi lớp</button>
                            </div>
                            {{-- Không nhúng TÊN học sinh vào chuỗi confirm (dấu nháy trong tên làm vỡ JS). --}}
                            <form x-show="confirming" x-cloak method="POST"
                                  action="{{ route('teacher.classes.members.remove', ['class' => $classRoom->id, 'student' => $m->id]) }}"
                                  onsubmit="return confirm('Gỡ học sinh này khỏi lớp? Em sẽ không vào lớp được nữa cho tới khi đăng ký lại và được duyệt.');">
                                @csrf
                                <input type="text" name="reason" maxlength="255" placeholder="Lý do (không bắt buộc)" class="tcx-input">
                                <button type="submit" class="tcx-btn is-red"><x-lucide name="log-out" />Xác nhận gỡ</button>
                                <button type="button" x-on:click="confirming = false" class="tcx-btn is-ghost">Huỷ</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @else
        <div class="tcx-stats">
            <div class="tcx-stat"><span class="tcx-ic"><x-lucide name="users" /></span><div><small>Học sinh đang học</small><b>{{ $studentsCount }}</b></div></div>
            <div class="tcx-stat"><span class="tcx-ic v"><x-lucide name="calendar-days" /></span><div><small>Buổi đã học</small><b>{{ ($completionTotalSessions ?? 0) > 0 ? $completionEndedSessions.'/'.$completionTotalSessions : '—' }}</b></div></div>
            <div class="tcx-stat"><span class="tcx-ic g"><x-lucide name="target" /></span><div><small>Hoàn thành chung</small><b>{{ $tcxCompletion }}%</b></div></div>
        </div>
        <div class="tcx-cards">
            <div class="tcx-card">
                <h3><span class="tcx-ic a"><x-lucide name="star" /></span>Đánh giá nội bộ</h3>
                <x-rating-summary :average="$ratingAverage" :count="$ratingCount" />
            </div>
            <div class="tcx-card">
                <h3><span class="tcx-ic"><x-lucide name="calendar-days" /></span>Buổi học gần nhất</h3>
                <p>{{ $nextSessionLabel }}</p>
            </div>
        </div>
    @endif
    </div>{{-- /.tcx-panel --}}
    </div>{{-- /.tcx --}}

    @push('scripts')
        {{-- x-cloak cho khối chọn loại tài liệu (Alpine) — tránh chớp nháy hiện cả 3 loại cùng lúc. --}}
        <style>
            [x-cloak] { display: none !important; }
        </style>
    @endpush
@endsection
