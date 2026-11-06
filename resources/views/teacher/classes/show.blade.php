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
        /* SỬA 6/11 — popup "Thêm học sinh" (tiền tố tam-; popup gắn ra <body> nên không dùng biến --tcx-*) */
        .tam-ov { position: fixed; inset: 0; z-index: 90; display: grid; place-items: center; padding: 16px; background: rgba(15, 35, 60, .45); backdrop-filter: blur(4px); }
        .tam-dlg { width: min(600px, 100%); max-height: calc(100vh - 32px); display: flex; flex-direction: column; border-radius: 22px; background: #fff; box-shadow: 0 30px 70px rgba(15, 35, 60, .3); overflow: hidden; font-family: inherit; color: #1E293B; }
        .tam-head { display: flex; align-items: center; gap: 12px; padding: 18px 20px 14px; background: linear-gradient(120deg, #ECFDF5, #F0F9FF); border-bottom: 1px solid #E2EEF5; }
        .tam-hic { display: grid; place-items: center; width: 42px; height: 42px; flex: none; border-radius: 14px; background: linear-gradient(135deg, #22C55E, #16A34A); color: #fff; box-shadow: 0 8px 18px rgba(22, 163, 74, .3); }
        .tam-hic svg { width: 20px; height: 20px; }
        .tam-htx { flex: 1; min-width: 0; }
        .tam-htx h3 { margin: 0; font-size: 16px; font-weight: 800; color: #0F2F57; }
        .tam-htx p { margin: 2px 0 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12.5px; color: #5B7488; }
        .tam-x { display: grid; place-items: center; width: 34px; height: 34px; border: 0; border-radius: 10px; background: rgba(255, 255, 255, .8); color: #5B7488; cursor: pointer; }
        .tam-x:hover { background: #fff; color: #0F2F57; }
        .tam-x svg { width: 17px; height: 17px; }
        .tam-seg { display: flex; gap: 4px; margin: 14px 20px 0; padding: 4px; border-radius: 12px; background: #F1F5F9; }
        .tam-seg button { flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 34px; border: 0; border-radius: 9px; background: none; font-size: 12.5px; font-weight: 700; color: #5B7488; cursor: pointer; }
        .tam-seg button svg { width: 15px; height: 15px; }
        .tam-seg button.on { background: #fff; color: #0F2F57; box-shadow: 0 2px 6px rgba(15, 35, 60, .1); }
        .tam-body { padding: 14px 20px 6px; overflow-y: auto; }
        .tam-combo { position: relative; }
        .tam-input { display: flex; align-items: center; gap: 8px; height: 44px; padding: 0 6px 0 12px; border: 1.5px solid #DCE6F0; border-radius: 13px; background: #fff; color: #7B8EA0; transition: border-color .15s, box-shadow .15s; }
        .tam-input.on, .tam-input:focus-within { border-color: #22C55E; box-shadow: 0 0 0 4px rgba(34, 197, 94, .12); }
        .tam-input > svg { width: 17px; height: 17px; flex: none; }
        .tam-input input { flex: 1; min-width: 0; height: 100%; border: 0; outline: 0; background: transparent; font-size: 13.5px; color: #0F2F57; }
        .tam-caret { display: grid; place-items: center; width: 32px; height: 32px; border: 0; border-radius: 9px; background: #F1F5F9; color: #5B7488; cursor: pointer; }
        .tam-caret svg { width: 16px; height: 16px; transition: transform .15s; }
        .tam-caret[aria-expanded="true"] svg { transform: rotate(180deg); }
        .tam-spin { width: 16px; height: 16px; border: 2px solid #BBF7D0; border-top-color: #16A34A; border-radius: 50%; animation: tam-spin .7s linear infinite; }
        @keyframes tam-spin { to { transform: rotate(360deg); } }
        .tam-drop { margin-top: 8px; border: 1px solid #E2EBF2; border-radius: 14px; background: #fff; box-shadow: 0 12px 28px rgba(15, 35, 60, .1); overflow: hidden; } /* nằm trong luồng (không absolute) để không bị khung popup cắt */
        .tam-drop-h { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 12px; border-bottom: 1px solid #EEF3F7; background: #F8FAFC; font-size: 11.5px; font-weight: 700; color: #7B8EA0; }
        .tam-drop-h button { border: 0; background: none; font-size: 11.5px; font-weight: 800; color: #16A34A; cursor: pointer; }
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
        .tam-av.sm { width: 22px; height: 22px; font-size: 9.5px; }
        .tam-av img { width: 100%; height: 100%; object-fit: cover; }
        .tam-av b { font-weight: 800; }
        .tam-av.t0 { background: #DCFCE7; color: #15803D; } .tam-av.t1 { background: #DBEAFE; color: #1D4ED8; }
        .tam-av.t2 { background: #FFEDD5; color: #C2410C; } .tam-av.t3 { background: #EDE9FE; color: #6D28D9; }
        .tam-who { flex: 1; min-width: 0; }
        .tam-who b { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 13px; color: #0F2F57; }
        .tam-who small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 11.5px; color: #7B8EA0; }
        .tam-tag { flex: none; padding: 2px 8px; border-radius: 999px; background: #F1F5F9; font-size: 10.5px; font-weight: 700; color: #64748B; }
        .tam-empty { margin: 0; padding: 16px; text-align: center; font-size: 12.5px; color: #94A3B8; }
        .tam-picked { margin-top: 14px; padding: 12px; border-radius: 14px; border: 1px dashed #BBF7D0; background: #F7FEF9; }
        .tam-picked-h { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 12.5px; color: #166534; }
        .tam-picked-h button { border: 0; background: none; font-size: 12px; font-weight: 700; color: #DC2626; cursor: pointer; }
        .tam-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; max-height: 120px; overflow-y: auto; }
        .tam-chip { display: inline-flex; align-items: center; gap: 6px; height: 30px; padding: 0 4px 0 4px; border: 1px solid #BBF7D0; border-radius: 999px; background: #fff; font-size: 12px; font-weight: 600; color: #14532D; }
        .tam-chip button { display: grid; place-items: center; width: 20px; height: 20px; border: 0; border-radius: 50%; background: #F0FDF4; color: #16A34A; cursor: pointer; }
        .tam-chip button:hover { background: #DC2626; color: #fff; }
        .tam-chip button svg { width: 12px; height: 12px; }
        .tam-hint { margin: 6px 0 0; font-size: 12px; color: #6B8196; }
        .tam-lb { display: block; margin-bottom: 8px; font-size: 12.5px; line-height: 1.6; color: #3D566B; }
        .tam-ta { width: 100%; min-height: 140px; padding: 10px 12px; border: 1.5px solid #DCE6F0; border-radius: 13px; font: inherit; font-size: 13px; line-height: 1.7; color: #0F2F57; resize: vertical; outline: 0; box-sizing: border-box; }
        .tam-ta:focus { border-color: #22C55E; box-shadow: 0 0 0 4px rgba(34, 197, 94, .12); }
        .tam-err { display: flex; align-items: center; gap: 6px; margin: 6px 20px 0; padding: 8px 12px; border-radius: 10px; background: #FFF5F5; color: #B91C1C; font-size: 12.5px; font-weight: 600; }
        .tam-err svg { width: 15px; height: 15px; }
        .tam-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-top: 10px; padding: 14px 20px; border-top: 1px solid #EEF3F7; background: #FAFCFD; }
        .tam-foot small { display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; color: #6B8196; }
        .tam-foot small svg { width: 14px; height: 14px; flex: none; }
        .tam-acts { display: flex; gap: 8px; margin-left: auto; }
        .tam-btn { display: inline-flex; align-items: center; gap: 6px; height: 38px; padding: 0 16px; border: 0; border-radius: 11px; font-size: 13px; font-weight: 800; cursor: pointer; }
        .tam-btn svg { width: 16px; height: 16px; }
        .tam-btn.ghost { background: #F1F5F9; color: #475569; } .tam-btn.ghost:hover { background: #E2E8F0; }
        .tam-btn.pri { background: linear-gradient(135deg, #22C55E, #16A34A); color: #fff; box-shadow: 0 8px 18px rgba(22, 163, 74, .3); }
        .tam-btn.pri:hover { filter: brightness(1.05); }
        .tam-btn.pri:disabled { opacity: .5; cursor: not-allowed; box-shadow: none; }
        @media (max-width: 560px) { .tam-ov { align-items: end; padding: 0; } .tam-dlg { border-radius: 22px 22px 0 0; max-height: 92vh; } .tam-foot small { display: none; } .tam-acts { width: 100%; } .tam-btn { flex: 1; justify-content: center; } }
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

        {{-- SỬA 6/11 (khách: "nhiều sách / bộ đề kéo dài quá — thiết kế đẹp, có phân trang, bố cục hài hoà") — khung "Thêm tài liệu
             vào lớp" dựng lại: đầu khung (giới thiệu + ô tìm) / hàng chọn loại / lưới thẻ 3 cột / phân trang 9 thẻ mỗi trang (điện thoại
             5). Vẫn gửi đúng form cũ (teacher.classes.materials.attach, product_id) — logic gắn không đổi. CSS tiền tố tca-. --}}
        @php
            $tcaItems = [];
            foreach ($attachableProducts as $typeKey => $group) {
                foreach ($group['products'] as $p) {
                    $tcaItems[] = [
                        'id' => $p['id'], 'type' => $typeKey, 'title' => $p['title'],
                        'sub' => (! empty($p['adminFree']) && empty($p['expiresAtLabel'])) ? 'Quyền quản trị · không cần mua' : 'Quyền dạy đến '.($p['expiresAtLabel'] ?? '—'),
                    ];
                }
            }
            $tcaTypes = collect($attachableProducts)->map(fn ($g, $k) => ['key' => $k, 'label' => $tcxClean($g['label']), 'count' => count($g['products'])])->values()->all();
        @endphp
        <section class="tca" x-data="tcaPick(@js(['type' => $defaultType, 'items' => $tcaItems]))">
            <div class="tca-head">
                <span class="tcx-folder" aria-hidden="true"><x-lucide name="book-open" /><span><x-lucide name="plus" /></span></span>
                <div class="tca-intro">
                    <h3>Thêm tài liệu vào lớp</h3>
                    <p>@if (auth()->user()?->actingRole() === \App\Models\Role::TEACHER)Bạn đang ở vai Giáo viên từ tài khoản quản trị — thêm được mọi tài liệu đã phát hành, không cần mua.@else Chỉ hiện sách, chuyên đề, bộ đề bạn còn quyền dạy (còn hạn).@endif</p>
                </div>
                @if ($hasAnyAttachable)
                    <label class="tca-search">
                        <x-lucide name="search" />
                        <input type="search" x-model="q" @input="page = 1" placeholder="Tìm tên sách, bộ đề…" aria-label="Tìm tài liệu để thêm">
                        <button type="button" x-show="q" x-cloak @click="q = ''; page = 1" aria-label="Xoá tìm kiếm"><x-lucide name="x" /></button>
                    </label>
                @endif
            </div>

            @if (empty($attachableProducts))
                <div class="tca-empty"><x-lucide name="book-open" /><p>Không có sách / chuyên đề / bộ đề nào bạn còn quyền dạy để thêm — quyền dạy đã hết hạn hoặc chưa được cấp.</p></div>
            @elseif (! $hasAnyAttachable)
                <div class="tca-empty"><x-lucide name="check-circle-2" /><p>Mọi sách / chuyên đề / bộ đề bạn còn quyền dạy đã gắn hết vào lớp này rồi.</p></div>
            @else
                <div class="tca-bar">
                    <div class="tca-types" role="tablist">
                        @foreach ($tcaTypes as $t)
                            <button type="button" role="tab" class="tca-type t-{{ $t['key'] }}" :class="{ on: type === '{{ $t['key'] }}' }" @click="setType('{{ $t['key'] }}')" @disabled($t['count'] === 0)>
                                <x-lucide :name="$tcxTypeIcon[$t['key']] ?? 'book-open'" />{{ $t['label'] }}<em x-text="countFor('{{ $t['key'] }}')">{{ $t['count'] }}</em>
                            </button>
                        @endforeach
                    </div>
                    <span class="tca-range" x-show="filtered.length" x-text="rangeText"></span>
                </div>

                <div class="tca-grid">
                    <template x-for="it in pageItems" :key="it.id">
                        <form method="POST" action="{{ route('teacher.classes.materials.attach', $classRoom->id) }}" class="tca-card" :class="'t-' + it.type" @submit="busy = it.id">
                            <input type="hidden" name="_token" value="{{ csrf_token() }}">
                            <input type="hidden" name="product_id" :value="it.id">
                            <span class="tca-ic">
                                <span x-show="it.type === 'book'"><x-lucide name="book-open" /></span>
                                <span x-show="it.type === 'topic'"><x-lucide name="layers" /></span>
                                <span x-show="it.type === 'exam'"><x-lucide name="clipboard-list" /></span>
                            </span>
                            <div class="tca-tx">
                                <b :title="it.title" x-text="it.title"></b>
                                <small x-text="it.sub"></small>
                            </div>
                            <button type="submit" class="tca-add" :disabled="busy !== null" :aria-label="'Thêm ' + it.title + ' vào lớp'">
                                <span x-show="busy !== it.id"><x-lucide name="plus" /></span><span x-text="busy === it.id ? 'Đang thêm…' : 'Thêm'"></span>
                            </button>
                        </form>
                    </template>
                </div>
                <div class="tca-none" x-show="filtered.length === 0" x-cloak>
                    <x-lucide name="search" /><p x-text="q ? 'Không tìm thấy tài liệu khớp “' + q + '” trong mục này.' : 'Mục này không còn tài liệu nào để thêm.'"></p>
                </div>

                <nav class="tca-pager" x-show="pages > 1" x-cloak aria-label="Phân trang tài liệu">
                    <button type="button" class="tca-pg" @click="go(page - 1)" :disabled="page === 1" aria-label="Trang trước"><x-lucide name="chevron-left" /></button>
                    <template x-for="(n, k) in pageList" :key="k">
                        <button type="button" class="tca-pg" :class="{ on: n === page, dots: n === '…' }" :disabled="n === '…'" @click="go(n)" x-text="n"></button>
                    </template>
                    <button type="button" class="tca-pg" @click="go(page + 1)" :disabled="page === pages" aria-label="Trang sau"><x-lucide name="chevron-right" /></button>
                </nav>
            @endif
        </section>
        <style>
            .tca { margin-top: 12px; padding: 18px; border: 1.5px dashed #BFD4F1; border-radius: 18px; background: linear-gradient(180deg, #FBFDFF, #F6FAFF); }
            .tca-head { display: flex; flex-wrap: wrap; align-items: center; gap: 12px 14px; }
            .tca-intro { flex: 1 1 320px; min-width: 0; }
            .tca-intro h3 { margin: 0; font-size: 14.5px; font-weight: 800; color: var(--tcx-ink, #0F2F57); }
            .tca-intro p { margin: 3px 0 0; font-size: 12.5px; line-height: 1.5; color: #64748B; }
            .tca-search { display: flex; align-items: center; gap: 8px; flex: 0 1 320px; min-width: 220px; height: 40px; padding: 0 12px; border: 1px solid #D9E3EE; border-radius: 12px; background: #fff; transition: border-color .15s, box-shadow .15s; }
            .tca-search:focus-within { border-color: #3B82F6; box-shadow: 0 0 0 4px rgba(59, 130, 246, .12); }
            .tca-search svg { width: 16px; height: 16px; flex: none; color: #94A3B8; }
            .tca-search input { flex: 1; min-width: 0; height: 100%; padding: 0; border: 0; outline: 0; background: none; box-shadow: none; font: inherit; font-size: 13px; color: #0F172A; }
            .tca-search input:focus { box-shadow: none; outline: 0; }
            .tca-search input::-webkit-search-cancel-button { display: none; }
            .tca-search button { display: grid; place-items: center; width: 24px; height: 24px; flex: none; padding: 0; border: 0; border-radius: 7px; background: #F1F5F9; color: #64748B; cursor: pointer; }
            .tca-search button svg { width: 13px; height: 13px; color: inherit; }
            .tca-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin: 16px 0 12px; }
            .tca-types { display: flex; flex-wrap: wrap; gap: 4px; padding: 4px; border-radius: 13px; background: #EDF2F8; }
            .tca-type { display: inline-flex; align-items: center; gap: 7px; height: 34px; padding: 0 14px; border: 0; border-radius: 10px; background: none; font: inherit; font-size: 13px; font-weight: 700; color: #475569; cursor: pointer; transition: all .15s; }
            .tca-type svg { width: 15px; height: 15px; }
            .tca-type.t-book svg { color: #2563EB; } .tca-type.t-topic svg { color: #7C3AED; } .tca-type.t-exam svg { color: #EA580C; }
            .tca-type em { font-style: normal; min-width: 20px; padding: 1px 7px; border-radius: 999px; background: rgba(255, 255, 255, .8); font-size: 11px; font-weight: 800; color: #64748B; text-align: center; }
            .tca-type:hover:not(:disabled) { color: #0F172A; }
            .tca-type.on { background: #fff; color: #0F2F57; box-shadow: 0 2px 8px rgba(15, 42, 74, .1); }
            .tca-type.on em { background: #EEF4FF; color: #1D4ED8; }
            .tca-type:disabled { opacity: .45; cursor: not-allowed; }
            .tca-range { font-size: 12px; font-weight: 600; color: #94A3B8; }
            .tca-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 10px; }
            @media (min-width: 720px) { .tca-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
            @media (min-width: 1200px) { .tca-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
            .tca-card { display: flex; align-items: center; gap: 12px; min-width: 0; margin: 0; padding: 12px; border: 1px solid #E3EBF4; border-radius: 14px; background: #fff; transition: border-color .15s, box-shadow .15s, transform .15s; }
            .tca-card:hover { border-color: #BFD3EA; box-shadow: 0 10px 22px rgba(15, 42, 74, .07); transform: translateY(-1px); }
            .tca-ic { display: grid; place-items: center; width: 40px; height: 40px; flex: none; border-radius: 12px; background: #EEF4FF; color: #2563EB; }
            .tca-card.t-topic .tca-ic { background: #F3EEFF; color: #7C3AED; } .tca-card.t-exam .tca-ic { background: #FFF1E6; color: #EA580C; }
            .tca-ic svg { width: 19px; height: 19px; display: block; }
            .tca-tx { flex: 1; min-width: 0; }
            .tca-tx b { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-size: 13px; font-weight: 800; line-height: 1.4; color: #0F172A; }
            .tca-tx small { display: block; margin-top: 3px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 11.5px; color: #94A3B8; }
            .tca-add { display: inline-flex; align-items: center; gap: 4px; flex: none; height: 32px; padding: 0 12px; border: 1px solid #C7DAF7; border-radius: 10px; background: #F2F7FF; font: inherit; font-size: 12.5px; font-weight: 800; color: #1D4ED8; white-space: nowrap; cursor: pointer; transition: all .15s; }
            .tca-add svg { width: 14px; height: 14px; display: block; }
            .tca-card:hover .tca-add, .tca-add:focus-visible { border-color: #2563EB; background: linear-gradient(135deg, #2563EB, #1D4ED8); color: #fff; box-shadow: 0 6px 14px rgba(37, 99, 235, .25); }
            .tca-add:disabled { opacity: .55; cursor: wait; }
            .tca-none, .tca-empty { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 26px 12px; border-radius: 14px; border: 1px dashed #D5E1EE; background: #fff; text-align: center; }
            .tca-empty { margin-top: 14px; }
            .tca-none svg, .tca-empty svg { width: 22px; height: 22px; color: #A3B6CC; }
            .tca-none p, .tca-empty p { margin: 0; font-size: 12.5px; color: #8093A8; }
            .tca-pager { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px; margin-top: 16px; }
            .tca-pg { display: grid; place-items: center; min-width: 36px; height: 36px; padding: 0 8px; border: 1px solid #DCE6F2; border-radius: 10px; background: #fff; font: inherit; font-size: 13px; font-weight: 700; color: #475569; cursor: pointer; transition: all .15s; }
            .tca-pg svg { width: 16px; height: 16px; }
            .tca-pg:hover:not(:disabled) { border-color: #93C5FD; color: #1D4ED8; }
            .tca-pg.on { border-color: #2563EB; background: #2563EB; color: #fff; box-shadow: 0 4px 12px rgba(37, 99, 235, .25); }
            .tca-pg:disabled { opacity: .4; cursor: default; } .tca-pg.dots { border-color: transparent; background: none; opacity: 1; }
            @media (max-width: 560px) { .tca { padding: 14px; } .tca-head .tcx-folder { display: none; } .tca-search { flex: 1 1 100%; min-width: 0; } .tca-types { width: 100%; display: grid; grid-auto-flow: column; grid-auto-columns: minmax(0, 1fr); } .tca-type { justify-content: center; gap: 4px; padding: 0 4px; font-size: 12px; white-space: nowrap; } .tca-type svg { display: none; } .tca-add { padding: 0 10px; } }
        </style>
        <script>
            window.tcaPick = function (c) {
                const fold = (s) => String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd');
                const items = (c.items || []).map((it) => Object.assign({}, it, { f: fold(it.title) }));
                return {
                    type: c.type, q: '', page: 1, busy: null, items, per: (window.matchMedia && window.matchMedia('(max-width: 719px)').matches) ? 5 : 9,
                    setType(t) { this.type = t; this.page = 1; },
                    countFor(t) { const k = fold(this.q.trim()); return this.items.filter((it) => it.type === t && (!k || it.f.includes(k))).length; },
                    get filtered() { const k = fold(this.q.trim()); return this.items.filter((it) => it.type === this.type && (!k || it.f.includes(k))); },
                    get pages() { return Math.max(1, Math.ceil(this.filtered.length / this.per)); },
                    get pageItems() { const p = Math.min(this.page, this.pages); return this.filtered.slice((p - 1) * this.per, p * this.per); },
                    get rangeText() { const n = this.filtered.length, p = Math.min(this.page, this.pages); return 'Hiển thị ' + ((p - 1) * this.per + 1) + '–' + Math.min(p * this.per, n) + ' / ' + n + ' tài liệu'; },
                    get pageList() {
                        const n = this.pages, p = this.page;
                        if (n <= 7) return Array.from({ length: n }, (_, i) => i + 1);
                        const set = [1, p - 1, p, p + 1, n].filter((x) => x >= 1 && x <= n);
                        const out = []; [...new Set(set)].sort((a, b) => a - b).forEach((x, i, arr) => { if (i && x - arr[i - 1] > 1) out.push('…'); out.push(x); });
                        return out;
                    },
                    go(n) { if (typeof n === 'number' && n >= 1 && n <= this.pages) this.page = n; },
                };
            };
        </script>

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
        @php
            $sessions = $sessions ?? [];
            $tseFail = filled(old('edit_session_id')); // SỬA 6/11 — lỗi khi SỬA buổi học: mở lại popup sửa, không đổ dữ liệu cũ vào form Tạo
            $tsfOld = fn ($k) => $tseFail ? '' : old($k, '');
        @endphp

        @if (session('status') === 'session-created')
            @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tạo buổi học mới.'])
        @elseif (session('status') === 'session-updated')
            @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu thay đổi buổi học.'])
        @endif
        @if ($errors->any())
            @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
        @endif

        <section class="tcx-sec" x-data="{ openForm: {{ ($errors->any() && ! $tseFail) || count($sessions) === 0 ? 'true' : 'false' }} }">
            <div class="tcx-sec-head" style="margin-bottom:0">
                <span class="tcx-ic"><x-lucide name="plus" /></span>
                <div style="flex:1 1 220px;min-width:0"><h3>Tạo buổi học mới</h3><p>Chủ đề, địa điểm / link và thời gian bắt đầu – kết thúc.</p></div>
                <button type="button" class="tcx-btn is-view" @click="openForm = ! openForm"><span x-show="! openForm"><x-lucide name="plus" /></span><span x-show="openForm" x-cloak><x-lucide name="chevron-up" /></span><span x-text="openForm ? 'Thu gọn' : 'Tạo buổi học'">Tạo buổi học</span></button>
            </div>
            {{-- SỬA 6/11 (khách chê form dính, xấu) — dựng lại form "Tạo buổi học mới": ô có biểu tượng, 2 thẻ Bắt đầu → Kết thúc,
                 nút chọn nhanh thời lượng (tự tính giờ kết thúc), báo lỗi khi kết thúc trước bắt đầu. Tên trường gửi đi GIỮ NGUYÊN
                 (topic, location, starts_/ends_ date/hour/minute) nên ScheduleController không đổi. CSS tiền tố tsf-. --}}
            <form method="POST" action="{{ route('teacher.schedule.store') }}" class="tsf" x-show="openForm" x-cloak
                  x-data="tsfForm(@js(['sd' => $tsfOld('starts_date'), 'sh' => $tsfOld('starts_hour'), 'sm' => $tsfOld('starts_minute'), 'ed' => $tsfOld('ends_date'), 'eh' => $tsfOld('ends_hour'), 'em' => $tsfOld('ends_minute')]))"
                  @submit="if (dur <= 0) { $event.preventDefault(); tried = true; }">
                @csrf
                <input type="hidden" name="class_room_id" value="{{ $classRoom->id }}">
                <input type="hidden" name="back_to_class" value="1">

                <div class="tsf-row">
                    <div class="tsf-f">
                        <label class="tsf-lb" for="tsf-topic">Chủ đề buổi học</label>
                        <div class="tsf-in"><x-lucide name="book-open" /><input id="tsf-topic" type="text" name="topic" maxlength="255" value="{{ $tsfOld('topic') }}" placeholder="Ví dụ: Ôn tập chương 3"></div>
                    </div>
                    <div class="tsf-f">
                        <label class="tsf-lb" for="tsf-loc">Địa điểm / link học online</label>
                        <div class="tsf-in"><x-lucide name="map-pin" /><input id="tsf-loc" type="text" name="location" maxlength="255" value="{{ $tsfOld('location') }}" placeholder="Phòng 201, hoặc dán link Google Meet / Zoom"></div>
                    </div>
                </div>

                <div class="tsf-time">
                    <div class="tsf-slot is-a">
                        <div class="tsf-slot-h"><span class="tsf-dot"></span>Bắt đầu<small x-text="dayLabel(sd)"></small></div>
                        <div class="tsf-dt">
                            <label class="sr-only" for="starts_date">Ngày bắt đầu</label>
                            <div class="tsf-in tsf-date"><input id="starts_date" type="date" name="starts_date" required x-model="sd" @change="onStart()"></div>
                            <div class="tsf-in tsf-tm"><x-lucide name="clock-3" />
                                <label class="sr-only" for="starts_hour">Giờ bắt đầu</label>
                                <select id="starts_hour" name="starts_hour" required x-model="sh" @change="onStart()">@for ($h = 0; $h < 24; $h++)<option value="{{ sprintf('%02d', $h) }}">{{ sprintf('%02d', $h) }}</option>@endfor</select>
                                <b>:</b>
                                <label class="sr-only" for="starts_minute">Phút bắt đầu</label>
                                <select id="starts_minute" name="starts_minute" required x-model="sm" @change="onStart()">@foreach (range(0, 55, 5) as $m)<option value="{{ sprintf('%02d', $m) }}">{{ sprintf('%02d', $m) }}</option>@endforeach</select>
                            </div>
                        </div>
                    </div>
                    <span class="tsf-arrow" aria-hidden="true"><x-lucide name="arrow-right" /></span>
                    <div class="tsf-slot is-b" :class="{ bad: tried && dur <= 0 }">
                        <div class="tsf-slot-h"><span class="tsf-dot"></span>Kết thúc<small x-text="dayLabel(ed)"></small></div>
                        <div class="tsf-dt">
                            <label class="sr-only" for="ends_date">Ngày kết thúc</label>
                            <div class="tsf-in tsf-date"><input id="ends_date" type="date" name="ends_date" required x-model="ed" @change="lastDur = dur"></div>
                            <div class="tsf-in tsf-tm"><x-lucide name="clock-3" />
                                <label class="sr-only" for="ends_hour">Giờ kết thúc</label>
                                <select id="ends_hour" name="ends_hour" required x-model="eh" @change="lastDur = dur">@for ($h = 0; $h < 24; $h++)<option value="{{ sprintf('%02d', $h) }}">{{ sprintf('%02d', $h) }}</option>@endfor</select>
                                <b>:</b>
                                <label class="sr-only" for="ends_minute">Phút kết thúc</label>
                                <select id="ends_minute" name="ends_minute" required x-model="em" @change="lastDur = dur">@foreach (range(0, 55, 5) as $m)<option value="{{ sprintf('%02d', $m) }}">{{ sprintf('%02d', $m) }}</option>@endforeach</select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tsf-quick">
                    <span class="tsf-q-lb"><x-lucide name="timer" />Thời lượng nhanh</span>
                    <div class="tsf-chips">
                        <template x-for="d in [45, 60, 90, 120, 150]" :key="d">
                            <button type="button" :class="{ on: dur === d }" @click="setDur(d)" x-text="durLabel(d)"></button>
                        </template>
                    </div>
                    <span class="tsf-sum" :class="{ bad: dur <= 0 }" x-text="dur > 0 ? 'Buổi học dài ' + durLabel(dur) : 'Giờ kết thúc phải sau giờ bắt đầu'"></span>
                </div>

                <div class="tsf-foot">
                    <small><x-lucide name="info" /><span>Tạo xong, buổi học hiện ở danh sách bên dưới — bấm <b>Điểm danh</b> khi lên lớp.</span></small>
                    <div class="tsf-acts">
                        <button type="button" class="tsf-btn ghost" @click="openForm = false">Huỷ</button>
                        <button type="submit" class="tsf-btn pri" :disabled="dur <= 0"><x-lucide name="calendar-days" />Tạo buổi học</button>
                    </div>
                </div>
            </form>
            <style>
                .tsf { display: grid; gap: 16px; margin-top: 16px; padding-top: 16px; border-top: 1px dashed #DCE6F2; }
                .tsf-row { display: grid; grid-template-columns: minmax(0, 1fr); gap: 14px; }
                @media (min-width: 720px) { .tsf-row { grid-template-columns: 1fr 1fr; gap: 16px; } }
                .tsf-f { min-width: 0; }
                .tsf-lb { display: block; margin: 0 0 6px 2px; font-size: 12.5px; font-weight: 700; color: #334155; }
                .tsf-in { display: flex; align-items: center; gap: 8px; height: 44px; padding: 0 12px; border: 1px solid #D9E3EE; border-radius: 12px; background: #fff; transition: border-color .15s, box-shadow .15s; }
                .tsf-in:hover { border-color: #B8C9DC; }
                .tsf-in:focus-within { border-color: #3B82F6; box-shadow: 0 0 0 4px rgba(59, 130, 246, .12); }
                .tsf-in > svg { width: 16px; height: 16px; flex: none; color: #94A3B8; }
                .tsf-in:focus-within > svg { color: #2563EB; }
                .tsf-in input, .tsf-in select { flex: 1; min-width: 0; height: 100%; padding: 0; border: 0; outline: 0; background: transparent; box-shadow: none; font: inherit; font-size: 13.5px; color: #0F172A; }
                .tsf-in input::placeholder { color: #A3B1C2; }
                .tsf-in input:focus, .tsf-in select:focus { box-shadow: none; outline: 0; }
                .tsf-time { display: grid; grid-template-columns: minmax(0, 1fr); gap: 10px; align-items: stretch; }
                @media (min-width: 900px) { .tsf-time { grid-template-columns: minmax(0, 1fr) 36px minmax(0, 1fr); gap: 12px; } }
                .tsf-slot { display: grid; gap: 10px; padding: 14px; border: 1px solid #D1FAE5; border-radius: 16px; background: linear-gradient(160deg, #F0FDF6, #FAFFFC); }
                .tsf-slot.is-b { border-color: #FFE0E3; background: linear-gradient(160deg, #FFF4F5, #FFFCFC); }
                .tsf-slot.bad { border-color: #F87171; box-shadow: 0 0 0 3px rgba(248, 113, 113, .15); }
                .tsf-slot-h { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 800; color: #047857; }
                .tsf-slot.is-b .tsf-slot-h { color: #BE123C; }
                .tsf-slot-h small { margin-left: auto; font-size: 12px; font-weight: 600; color: #64748B; }
                .tsf-dot { width: 10px; height: 10px; border-radius: 50%; background: #10B981; box-shadow: 0 0 0 4px rgba(16, 185, 129, .18); }
                .tsf-slot.is-b .tsf-dot { background: #F43F5E; box-shadow: 0 0 0 4px rgba(244, 63, 94, .16); }
                .tsf-dt { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 10px; }
                .tsf-date input { cursor: pointer; }
                .tsf-tm { gap: 4px; padding-right: 10px; }
                .tsf-tm select { flex: none; width: auto; padding: 0 2px; appearance: none; -webkit-appearance: none; background-image: none; cursor: pointer; font-weight: 700; font-variant-numeric: tabular-nums; text-align: center; }
                .tsf-tm b { color: #94A3B8; font-weight: 800; }
                .tsf-arrow { display: none; }
                @media (min-width: 900px) { .tsf-arrow { display: grid; place-items: center; align-self: center; width: 36px; height: 36px; border-radius: 50%; border: 1px solid #E2E8F0; background: #fff; color: #64748B; box-shadow: 0 4px 10px rgba(15, 42, 74, .06); } .tsf-arrow svg { width: 16px; height: 16px; } }
                .tsf-quick { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 12px; }
                .tsf-q-lb { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 700; color: #475569; }
                .tsf-q-lb svg { width: 15px; height: 15px; color: #64748B; }
                .tsf-chips { display: flex; flex-wrap: wrap; gap: 8px; }
                .tsf-chips button { height: 32px; padding: 0 14px; border: 1px solid #DCE6F2; border-radius: 999px; background: #fff; font: inherit; font-size: 12.5px; font-weight: 700; color: #475569; cursor: pointer; transition: all .15s; }
                .tsf-chips button:hover { border-color: #93C5FD; color: #1D4ED8; background: #F5F9FF; }
                .tsf-chips button.on { border-color: #2563EB; background: #2563EB; color: #fff; box-shadow: 0 4px 12px rgba(37, 99, 235, .25); }
                .tsf-sum { margin-left: auto; padding: 6px 12px; border-radius: 999px; background: #EEF4FF; font-size: 12.5px; font-weight: 700; color: #1D4ED8; }
                .tsf-sum.bad { background: #FEECEC; color: #DC2626; }
                .tsf-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding-top: 14px; border-top: 1px solid #EEF2F7; }
                .tsf-foot small { display: inline-flex; align-items: flex-start; line-height: 1.5; gap: 6px; font-size: 12px; color: #64748B; }
                .tsf-foot small svg { width: 14px; height: 14px; flex: none; margin-top: 2px; }
                .tsf-acts { display: flex; gap: 10px; }
                .tsf-btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; height: 42px; padding: 0 20px; border-radius: 12px; border: 1px solid transparent; font: inherit; font-size: 13.5px; font-weight: 800; white-space: nowrap; cursor: pointer; transition: all .15s; }
                .tsf-btn svg { width: 16px; height: 16px; }
                .tsf-btn.ghost { border-color: #DCE6F2; background: #fff; color: #475569; }
                .tsf-btn.ghost:hover { background: #F8FAFC; }
                .tsf-btn.pri { background: linear-gradient(135deg, #2563EB, #1D4ED8); color: #fff; box-shadow: 0 8px 20px rgba(37, 99, 235, .28); }
                .tsf-btn.pri:hover { transform: translateY(-1px); box-shadow: 0 12px 24px rgba(37, 99, 235, .32); }
                .tsf-btn.pri:disabled { opacity: .5; cursor: not-allowed; transform: none; box-shadow: none; }
                @media (max-width: 340px) { .tsf-dt { grid-template-columns: minmax(0, 1fr); } }
                @media (max-width: 560px) { .tsf-sum { margin-left: 0; } .tsf-acts { width: 100%; } .tsf-btn { flex: 1; } }
            </style>
            <script>
                window.tsfForm = function (o) {
                    const pad = (n) => String(n).padStart(2, '0');
                    const ymd = (d) => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
                    const days = ['Chủ nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];
                    const fresh = !o.sd;
                    const base = new Date(); base.setMinutes(0, 0, 0); base.setHours(base.getHours() + 1);
                    return {
                        sd: o.sd || ymd(base), sh: o.sh || pad(base.getHours()), sm: o.sm || '00',
                        ed: o.ed || '', eh: o.eh || '', em: o.em || '', lastDur: 90, tried: false,
                        init() { if (fresh || !this.ed) this.setDur(90); this.lastDur = this.dur > 0 ? this.dur : 90; },
                        at(d, h, m) { const t = new Date(d + 'T' + h + ':' + m + ':00'); return isNaN(t) ? null : t; },
                        get dur() { const a = this.at(this.sd, this.sh, this.sm), b = this.at(this.ed, this.eh, this.em); return a && b ? Math.round((b - a) / 60000) : 0; },
                        setDur(min) { const a = this.at(this.sd, this.sh, this.sm); if (!a) return; const b = new Date(a.getTime() + min * 60000); this.ed = ymd(b); this.eh = pad(b.getHours()); this.em = pad(b.getMinutes() - b.getMinutes() % 5); this.lastDur = min; this.tried = false; },
                        onStart() { this.setDur(this.lastDur > 0 ? this.lastDur : 90); },
                        durLabel(m) { const h = Math.floor(m / 60), r = m % 60; return h ? h + ' giờ' + (r ? ' ' + r + ' phút' : '') : r + ' phút'; },
                        dayLabel(d) { const t = this.at(d, '12', '00'); return t ? days[t.getDay()] + ', ' + pad(t.getDate()) + '/' + pad(t.getMonth() + 1) : ''; },
                    };
                };
            </script>
        </section>

        @php
            $tseRound = fn ($d) => $d ? $d->copy()->setTime((int) $d->format('H'), intdiv((int) $d->format('i'), 5) * 5) : null;
            $tsePayload = function (array $s) use ($tseRound) {
                $a = $tseRound($s['startsAt'] ?? null);
                $b = $tseRound($s['endsAt'] ?? null) ?? $a;

                return [
                    'id' => $s['id'], 'topic' => (string) ($s['topic'] ?? ''), 'location' => (string) ($s['location'] ?? ''), 'taken' => (bool) $s['attendanceTaken'],
                    'sd' => $a?->format('Y-m-d') ?? '', 'sh' => $a?->format('H') ?? '08', 'sm' => $a?->format('i') ?? '00',
                    'ed' => $b?->format('Y-m-d') ?? '', 'eh' => $b?->format('H') ?? '09', 'em' => $b?->format('i') ?? '30',
                ];
            };
            $tseReopen = $tseFail ? [
                'id' => (int) old('edit_session_id'), 'topic' => (string) old('topic', ''), 'location' => (string) old('location', ''), 'taken' => false,
                'sd' => (string) old('starts_date', ''), 'sh' => (string) old('starts_hour', ''), 'sm' => (string) old('starts_minute', ''),
                'ed' => (string) old('ends_date', ''), 'eh' => (string) old('ends_hour', ''), 'em' => (string) old('ends_minute', ''),
            ] : null;
        @endphp
        <section class="tcx-sec" x-data="tseEdit(@js(['url' => route('teacher.schedule.update', ['session' => 0]), 'reopen' => $tseReopen]))" @keydown.escape.window="open && close()">
            <div class="tcx-sec-head">
                <span class="tcx-ic v"><x-lucide name="calendar-days" /></span>
                <div><h3>Lịch buổi học</h3><p>Mới nhất ở trên — bấm Điểm danh để ghi nhận học sinh có mặt, bấm Sửa nếu lỡ nhập sai.</p></div>
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
                            <div class="tse-acts">
                                <button type="button" class="tcx-btn tse-edit" @click="edit(@js($tsePayload($s)))"><x-lucide name="pencil" />Sửa</button>
                                <a href="{{ route('teacher.schedule.attendance', $s['id']) }}" class="tcx-btn is-view"><x-lucide name="check" />Điểm danh</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- SỬA 6/11 (khách: "lỡ thêm sai thì giáo viên sửa buổi học, bấm Sửa hiện popup thật đẹp") — popup Sửa buổi học.
                 Dùng lại khung popup tam- (Thêm học sinh) + ô / thẻ thời gian tsf- (form Tạo buổi học) cho đồng bộ. --}}
            <template x-teleport="body">
                <div class="tam-ov" x-show="open" x-transition.opacity x-cloak @click.self="close()">
                    <form class="tam-dlg tse-dlg" method="POST" :action="action" x-show="open" x-transition.scale.95 role="dialog" aria-modal="true" aria-labelledby="tse-title"
                          @submit="if (dur <= 0) { $event.preventDefault(); tried = true; } else { submitting = true; }">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="edit_session_id" :value="id">

                        <header class="tam-head tse-head">
                            <span class="tam-hic tse-hic"><x-lucide name="pencil" /></span>
                            <div class="tam-htx">
                                <h3 id="tse-title">Sửa buổi học</h3>
                                <p x-text="(topic || 'Buổi học') + ' · {{ addslashes($classRoom->name) }}'"></p>
                            </div>
                            <button type="button" class="tam-x" @click="close()" aria-label="Đóng"><x-lucide name="x" /></button>
                        </header>

                        <div class="tam-body tse-body">
                            <div class="tsf-row">
                                <div class="tsf-f">
                                    <label class="tsf-lb" for="tse-topic">Chủ đề buổi học</label>
                                    <div class="tsf-in"><x-lucide name="book-open" /><input id="tse-topic" x-ref="topic" type="text" name="topic" maxlength="255" x-model="topic" placeholder="Ví dụ: Ôn tập chương 3"></div>
                                </div>
                                <div class="tsf-f">
                                    <label class="tsf-lb" for="tse-loc">Địa điểm / link học online</label>
                                    <div class="tsf-in"><x-lucide name="map-pin" /><input id="tse-loc" type="text" name="location" maxlength="255" x-model="location" placeholder="Phòng 201, hoặc dán link Google Meet / Zoom"></div>
                                </div>
                            </div>

                            <div class="tsf-time tse-time">
                                <div class="tsf-slot is-a">
                                    <div class="tsf-slot-h"><span class="tsf-dot"></span>Bắt đầu<small x-text="dayLabel(sd)"></small></div>
                                    <div class="tsf-dt">
                                        <label class="sr-only" for="tse-sd">Ngày bắt đầu</label>
                                        <div class="tsf-in tsf-date"><input id="tse-sd" type="date" name="starts_date" required x-model="sd" @change="onStart()"></div>
                                        <div class="tsf-in tsf-tm"><x-lucide name="clock-3" />
                                            <label class="sr-only" for="tse-sh">Giờ bắt đầu</label>
                                            <select id="tse-sh" name="starts_hour" required x-model="sh" @change="onStart()">@for ($h = 0; $h < 24; $h++)<option value="{{ sprintf('%02d', $h) }}">{{ sprintf('%02d', $h) }}</option>@endfor</select>
                                            <b>:</b>
                                            <label class="sr-only" for="tse-sm">Phút bắt đầu</label>
                                            <select id="tse-sm" name="starts_minute" required x-model="sm" @change="onStart()">@foreach (range(0, 55, 5) as $m)<option value="{{ sprintf('%02d', $m) }}">{{ sprintf('%02d', $m) }}</option>@endforeach</select>
                                        </div>
                                    </div>
                                </div>
                                <span class="tsf-arrow" aria-hidden="true"><x-lucide name="arrow-right" /></span>
                                <div class="tsf-slot is-b" :class="{ bad: tried && dur <= 0 }">
                                    <div class="tsf-slot-h"><span class="tsf-dot"></span>Kết thúc<small x-text="dayLabel(ed)"></small></div>
                                    <div class="tsf-dt">
                                        <label class="sr-only" for="tse-ed">Ngày kết thúc</label>
                                        <div class="tsf-in tsf-date"><input id="tse-ed" type="date" name="ends_date" required x-model="ed" @change="lastDur = dur"></div>
                                        <div class="tsf-in tsf-tm"><x-lucide name="clock-3" />
                                            <label class="sr-only" for="tse-eh">Giờ kết thúc</label>
                                            <select id="tse-eh" name="ends_hour" required x-model="eh" @change="lastDur = dur">@for ($h = 0; $h < 24; $h++)<option value="{{ sprintf('%02d', $h) }}">{{ sprintf('%02d', $h) }}</option>@endfor</select>
                                            <b>:</b>
                                            <label class="sr-only" for="tse-em">Phút kết thúc</label>
                                            <select id="tse-em" name="ends_minute" required x-model="em" @change="lastDur = dur">@foreach (range(0, 55, 5) as $m)<option value="{{ sprintf('%02d', $m) }}">{{ sprintf('%02d', $m) }}</option>@endforeach</select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tsf-quick">
                                <span class="tsf-q-lb"><x-lucide name="timer" />Thời lượng nhanh</span>
                                <div class="tsf-chips">
                                    <template x-for="d in [45, 60, 90, 120, 150]" :key="d">
                                        <button type="button" :class="{ on: dur === d }" @click="setDur(d)" x-text="durLabel(d)"></button>
                                    </template>
                                </div>
                                <span class="tsf-sum" :class="{ bad: dur <= 0 }" x-text="dur > 0 ? 'Buổi học dài ' + durLabel(dur) : 'Giờ kết thúc phải sau giờ bắt đầu'"></span>
                            </div>

                            <p class="tse-note" x-show="taken"><x-lucide name="info" /><span>Buổi này đã điểm danh — sửa thông tin buổi học <b>không</b> làm mất điểm danh, nhận xét hay tài liệu đã gắn.</span></p>
                        </div>

                        <footer class="tam-foot">
                            <small><x-lucide name="shield-check" />Chỉ đổi thông tin buổi học, dữ liệu học sinh giữ nguyên.</small>
                            <div class="tam-acts">
                                <button type="button" class="tam-btn ghost" @click="close()">Huỷ</button>
                                <button type="submit" class="tam-btn pri tse-save" :disabled="dur <= 0 || submitting"><x-lucide name="check" /><span x-text="submitting ? 'Đang lưu…' : 'Lưu thay đổi'"></span></button>
                            </div>
                        </footer>
                    </form>
                </div>
            </template>
            <style>
                .tse-acts { display: flex; gap: 8px; margin-left: auto; }
                .tcx-btn.tse-edit { background: #FFF7E6; color: #B45309; } .tcx-btn.tse-edit:hover { background: #FFEFCC; }
                .tse-dlg { width: min(820px, 100%); }
                .tse-head { background: linear-gradient(120deg, #FFF7E6, #EEF4FF); }
                .tse-hic { background: linear-gradient(135deg, #F59E0B, #EA7A0B); box-shadow: 0 8px 18px rgba(234, 122, 11, .3); }
                .tse-body { display: grid; gap: 16px; padding: 18px 20px 6px; }
                @media (min-width: 700px) { .tse-time { grid-template-columns: minmax(0, 1fr) 36px minmax(0, 1fr); gap: 12px; } .tse-time .tsf-arrow { display: grid; place-items: center; align-self: center; width: 36px; height: 36px; border-radius: 50%; border: 1px solid #E2E8F0; background: #fff; color: #64748B; } .tse-time .tsf-arrow svg { width: 16px; height: 16px; } }
                .tse-note { display: flex; align-items: flex-start; gap: 8px; margin: 0; padding: 10px 12px; border-radius: 12px; background: #F0F9FF; border: 1px solid #D6ECFA; font-size: 12.5px; line-height: 1.5; color: #1E5A85; }
                .tse-note svg { width: 15px; height: 15px; flex: none; margin-top: 2px; }
                .tam-btn.pri.tse-save { background: linear-gradient(135deg, #2563EB, #1D4ED8); box-shadow: 0 8px 18px rgba(37, 99, 235, .3); }
                @media (max-width: 560px) { .tse-acts { width: 100%; } .tse-acts .tcx-btn { flex: 1; justify-content: center; } }
            </style>
            <script>
                window.tseEdit = function (cfg) {
                    const o = window.tsfForm({ sd: '', sh: '', sm: '', ed: '', eh: '', em: '' });
                    o.init = function () { if (cfg.reopen) this.edit(cfg.reopen); };
                    o.open = false; o.id = null; o.topic = ''; o.location = ''; o.taken = false; o.submitting = false; o.action = '';
                    o.edit = function (s) {
                        Object.assign(this, { id: s.id, topic: s.topic, location: s.location, taken: !!s.taken, sd: s.sd, sh: s.sh, sm: s.sm, ed: s.ed, eh: s.eh, em: s.em, tried: false, submitting: false });
                        this.action = cfg.url.replace(/\/0$/, '/' + s.id);
                        this.lastDur = this.dur > 0 ? this.dur : 90;
                        this.open = true; document.documentElement.style.overflow = 'hidden';
                        this.$nextTick(() => this.$refs.topic && this.$refs.topic.focus());
                    };
                    o.close = function () { this.open = false; document.documentElement.style.overflow = ''; };
                    return o;
                };
            </script>
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

        <section class="tcx-sec" x-data="{ q: '' }">
            <div class="tcx-sec-head">
                <span class="tcx-ic"><x-lucide name="users" /></span>
                <div style="flex:1 1 220px;min-width:0"><h3>Học sinh đang học</h3><p>Gỡ khỏi lớp: em mất quyền vào lớp ngay; bài làm, điểm danh cũ vẫn giữ nguyên.</p></div>
                <span class="tcx-count">{{ $members->count() }} học sinh</span>
                {{-- SỬA 6/11 (khách: "bấm Thêm học sinh ra popup thật đẹp: ô chọn sổ xuống có tìm kiếm, chọn nhiều") --}}
                <div x-data="tamAdd(@js(['url' => route('teacher.classes.students.search', $classRoom->id), 'open' => $errors->has('contacts'), 'contacts' => old('contacts', '')]))" @keydown.escape.window="open && close()">
                    <button type="button" class="tcx-btn is-green" @click="openModal()"><x-lucide name="user-plus" />Thêm học sinh</button>

                    <template x-teleport="body">
                        <div class="tam-ov" x-show="open" x-transition.opacity x-cloak @click.self="close()">
                            <form class="tam-dlg" method="POST" action="{{ route('teacher.classes.members.add', $classRoom->id) }}" x-show="open" x-transition.scale.95 role="dialog" aria-modal="true" aria-labelledby="tam-title" @submit="submitting = true">
                                @csrf
                                <template x-for="s in selected" :key="'h' + s.id"><input type="hidden" name="student_ids[]" :value="s.id"></template>

                                <header class="tam-head">
                                    <span class="tam-hic"><x-lucide name="user-plus" /></span>
                                    <div class="tam-htx">
                                        <h3 id="tam-title">Thêm học sinh vào lớp</h3>
                                        <p>{{ $classRoom->name }}</p>
                                    </div>
                                    <button type="button" class="tam-x" @click="close()" aria-label="Đóng"><x-lucide name="x" /></button>
                                </header>

                                <div class="tam-seg" role="tablist">
                                    <button type="button" :class="{ on: mode === 'pick' }" @click="mode = 'pick'"><x-lucide name="search" />Chọn từ danh sách</button>
                                    <button type="button" :class="{ on: mode === 'contact' }" @click="mode = 'contact'"><x-lucide name="mail" />Nhập email / SĐT</button>
                                </div>

                                <div class="tam-body" x-show="mode === 'pick'">
                                    <div class="tam-combo" @click.outside="drop = false">
                                        <label class="tam-input" :class="{ on: drop }">
                                            <x-lucide name="search" />
                                            <input type="text" x-ref="q" x-model="q" @input="search()" @focus="drop = true" @keydown.enter.prevent="pickFirst()"
                                                   placeholder="Tìm theo tên, email hoặc số điện thoại…" autocomplete="off" aria-label="Tìm học sinh">
                                            <span class="tam-spin" x-show="loading"></span>
                                            <button type="button" class="tam-caret" @click="drop = !drop" :aria-expanded="drop" aria-label="Mở danh sách"><x-lucide name="chevron-down" /></button>
                                        </label>
                                        <div class="tam-drop" x-show="drop" x-transition.origin.top>
                                            <div class="tam-drop-h">
                                                <span x-text="q.trim() ? (results.length + ' kết quả') : 'Học sinh mới đăng ký gần đây'"></span>
                                                <button type="button" x-show="selectable.length > 1" @click="selectAll()" x-text="allPicked ? 'Bỏ chọn tất cả' : 'Chọn tất cả (' + selectable.length + ')'"></button>
                                            </div>
                                            <ul class="tam-list" role="listbox" aria-multiselectable="true">
                                                <template x-for="u in results" :key="u.id">
                                                    <li role="option" :aria-selected="isSel(u)" :class="{ sel: isSel(u), dis: u.inClass }" @click="toggle(u)">
                                                        <span class="tam-ck"><x-lucide name="check" /></span>
                                                        <span class="tam-av" :class="'t' + (u.id % 4)"><img x-show="u.avatar" :src="u.avatar" alt=""><b x-show="!u.avatar" x-text="u.initials"></b></span>
                                                        <span class="tam-who"><b x-text="u.name"></b><small x-text="[u.contact, u.province].filter(Boolean).join(' • ') || 'Chưa có email / SĐT'"></small></span>
                                                        <span class="tam-tag" x-show="u.inClass">Đã trong lớp</span>
                                                    </li>
                                                </template>
                                            </ul>
                                            <p class="tam-empty" x-show="!loading && results.length === 0" x-text="q.trim() ? 'Không tìm thấy học sinh phù hợp. Thử tên khác, hoặc chuyển sang “Nhập email / SĐT”.' : 'Chưa có tài khoản học sinh nào.'"></p>
                                        </div>
                                    </div>

                                    <div class="tam-picked">
                                        <div class="tam-picked-h">
                                            <b>Đã chọn <span x-text="selected.length"></span> học sinh</b>
                                            <button type="button" x-show="selected.length" @click="selected = []">Bỏ chọn hết</button>
                                        </div>
                                        <div class="tam-chips" x-show="selected.length">
                                            <template x-for="s in selected" :key="'c' + s.id">
                                                <span class="tam-chip">
                                                    <span class="tam-av sm" :class="'t' + (s.id % 4)"><img x-show="s.avatar" :src="s.avatar" alt=""><b x-show="!s.avatar" x-text="s.initials"></b></span>
                                                    <span x-text="s.name"></span>
                                                    <button type="button" @click="remove(s.id)" :aria-label="'Bỏ chọn ' + s.name"><x-lucide name="x" /></button>
                                                </span>
                                            </template>
                                        </div>
                                        <p class="tam-hint" x-show="!selected.length">Gõ tên vào ô tìm kiếm rồi bấm để chọn — chọn được nhiều em cùng lúc.</p>
                                    </div>
                                </div>

                                <div class="tam-body" x-show="mode === 'contact'" x-cloak>
                                    <label class="tam-lb" for="tam-contacts">Nhập <b>email</b> hoặc <b>số điện thoại</b> học sinh đã dùng để đăng ký tài khoản — mỗi dòng một người (tối đa 50).</label>
                                    <textarea id="tam-contacts" name="contacts" rows="6" x-model="contacts" class="tam-ta" placeholder="vd: nguyenvana@gmail.com&#10;0912 345 678"></textarea>
                                </div>

                                @error('contacts')<p class="tam-err"><x-lucide name="alert-triangle" />{{ $message }}</p>@enderror

                                <footer class="tam-foot">
                                    <small><x-lucide name="info" />Học sinh vào lớp ngay, không cần chờ duyệt, và nhận được thông báo.</small>
                                    <div class="tam-acts">
                                        <button type="button" class="tam-btn ghost" @click="close()">Huỷ</button>
                                        <button type="submit" class="tam-btn pri" :disabled="!canSubmit || submitting">
                                            <x-lucide name="user-plus" /><span x-text="submitting ? 'Đang thêm…' : (count > 0 ? 'Thêm ' + count + ' học sinh' : 'Thêm vào lớp')"></span>
                                        </button>
                                    </div>
                                </footer>
                            </form>
                        </div>
                    </template>
                </div>
            </div>
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
    @elseif ($tab === 'notices')
        {{-- SỬA 6/11 (khách: "thêm tab Thông báo để giáo viên thông báo cho toàn bộ học sinh của lớp, thiết kế đẹp dễ dùng") —
             soạn + gửi thông báo cho cả lớp (học sinh / kèm phụ huynh / chỉ phụ huynh), mẫu soạn nhanh, xem trước như học sinh
             thấy, bước xác nhận trước khi gửi, lịch sử đã gửi. Gửi qua teacher.notices.store (back_to_class=1) — dùng lại nguyên
             TeacherWorkspaceService::saveAnnouncement() (thông báo vào chuông + dải "Thông báo mới" ở trang Tổng quan học sinh). --}}
        @php
            $tncCounts = $noticeCounts ?? ['students' => 0, 'parents' => 0];
            $tncList = $notices ?? [];
            $tncAudLabel = ['students' => 'Học sinh', 'families' => 'Học sinh + phụ huynh', 'parents' => 'Chỉ phụ huynh'];
        @endphp
        @if (session('notice_status') === 'published')
            @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã gửi thông báo — học sinh nhận được ngay.'])
        @elseif (session('notice_status') === 'deleted')
            @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã xoá thông báo — học sinh, phụ huynh không còn thấy nữa.'])
        @endif
        @if ($errors->any())
            <div class="tcx-alert"><x-lucide name="alert-triangle" /><div>@foreach ($errors->all() as $message)<p style="margin:0">{{ $message }}</p>@endforeach</div></div>
        @endif

        <section class="tcx-sec tnc-compose" x-data="tncForm(@js([
            'students' => (int) $tncCounts['students'], 'parents' => (int) $tncCounts['parents'],
            'aud' => old('audience', 'students'), 'title' => old('title', ''), 'body' => old('body', ''),
            'className' => $classRoom->name, 'teacher' => auth()->user()->name,
        ]))">
            <div class="tcx-sec-head">
                <span class="tcx-ic tnc-ic"><x-lucide name="megaphone" /></span>
                <div style="flex:1 1 260px;min-width:0"><h3>Gửi thông báo cho cả lớp</h3><p>Các em nhận ngay trong chuông thông báo và ở mục "Thông báo mới" trang Tổng quan.</p></div>
                <span class="tcx-count">{{ $tncCounts['students'] }} học sinh · {{ $tncCounts['parents'] }} phụ huynh</span>
            </div>

            @if ((int) $tncCounts['students'] === 0)
                <div class="tnc-warn"><x-lucide name="alert-triangle" /><span>Lớp chưa có học sinh đang học nên chưa gửi được. Hãy thêm học sinh ở tab <a href="{{ route('teacher.classes.show', ['class' => $classRoom->id, 'tab' => 'members']) }}">Học sinh</a> trước.</span></div>
            @endif

            <div class="tnc-grid">
                <form method="POST" action="{{ route('teacher.notices.store') }}" class="tnc-form" @submit="sending = true">
                    @csrf
                    <input type="hidden" name="class_room_id" value="{{ $classRoom->id }}">
                    <input type="hidden" name="back_to_class" value="1">
                    <input type="hidden" name="action" value="publish">

                    <div>
                        <span class="tnc-lb">1. Gửi tới</span>
                        <div class="tnc-aud" role="radiogroup">
                            <label class="tnc-opt" :class="{ on: aud === 'students' }">
                                <input type="radio" name="audience" value="students" x-model="aud">
                                <span class="tnc-opt-ic"><x-lucide name="users" /></span>
                                <span class="tnc-opt-tx"><b>Học sinh trong lớp</b><small x-text="students + ' học sinh'"></small></span>
                                <span class="tnc-tick"><x-lucide name="check" /></span>
                            </label>
                            <label class="tnc-opt" :class="{ on: aud === 'families' }">
                                <input type="radio" name="audience" value="families" x-model="aud">
                                <span class="tnc-opt-ic f"><x-lucide name="user-round" /></span>
                                <span class="tnc-opt-tx"><b>Học sinh + phụ huynh</b><small x-text="students + ' HS · ' + parents + ' phụ huynh'"></small></span>
                                <span class="tnc-tick"><x-lucide name="check" /></span>
                            </label>
                            <label class="tnc-opt" :class="{ on: aud === 'parents', dis: parents === 0 }">
                                <input type="radio" name="audience" value="parents" x-model="aud" :disabled="parents === 0">
                                <span class="tnc-opt-ic p"><x-lucide name="user-round" /></span>
                                <span class="tnc-opt-tx"><b>Chỉ phụ huynh</b><small x-text="parents ? parents + ' phụ huynh đã liên kết' : 'Chưa có phụ huynh liên kết'"></small></span>
                                <span class="tnc-tick"><x-lucide name="check" /></span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <span class="tnc-lb">2. Soạn nội dung <em>— hoặc bấm một mẫu có sẵn rồi sửa lại</em></span>
                        <div class="tnc-tpl">
                            <template x-for="(t, i) in templates" :key="i">
                                <button type="button" @click="useTpl(t)" :class="{ on: picked === i }"><span x-text="t.icon"></span><span x-text="t.name"></span></button>
                            </template>
                        </div>
                    </div>

                    <div class="tnc-field">
                        <div class="tnc-fh"><label for="tnc-title">Tiêu đề</label><small :class="{ over: title.length > 110 }" x-text="title.length + '/120'"></small></div>
                        <input id="tnc-title" name="title" type="text" maxlength="120" x-model="title" x-ref="title" placeholder="Ví dụ: Lịch học tuần sau thay đổi" class="tnc-input">
                    </div>
                    <div class="tnc-field">
                        <div class="tnc-fh"><label for="tnc-body">Nội dung</label><small :class="{ over: body.length > 2900 }" x-text="body.length + '/3000'"></small></div>
                        <textarea id="tnc-body" name="body" rows="6" maxlength="3000" x-model="body" placeholder="Viết rõ ràng, ngắn gọn: việc gì, khi nào, các em cần làm gì…" class="tnc-input tnc-ta"></textarea>
                    </div>

                    <div class="tnc-foot" x-show="!confirming">
                        <small x-show="!canSend" x-text="hint"></small>
                        <button type="button" class="tnc-btn pri" :disabled="!canSend" @click="confirming = true"><x-lucide name="send" /><span x-text="'Gửi tới ' + recipients + ' người'"></span></button>
                    </div>
                    <div class="tnc-confirm" x-show="confirming" x-cloak x-transition>
                        <span class="tnc-confirm-ic"><x-lucide name="bell" /></span>
                        <p>Gửi <b x-text="'“' + title.trim() + '”'"></b> tới <b x-text="whoLabel"></b>? Đã gửi thì không sửa hay thu hồi được.</p>
                        <div class="tnc-acts">
                            <button type="button" class="tnc-btn ghost" @click="confirming = false" :disabled="sending">Xem lại</button>
                            <button type="submit" class="tnc-btn pri" :disabled="sending"><x-lucide name="send" /><span x-text="sending ? 'Đang gửi…' : 'Gửi ngay'"></span></button>
                        </div>
                    </div>
                </form>

                <aside class="tnc-prev">
                    <span class="tnc-lb"><x-lucide name="eye" />Các em sẽ thấy như sau</span>
                    <div class="tnc-phone">
                        <div class="tnc-phone-h"><x-lucide name="bell" />Thông báo <span>1 mới</span></div>
                        <div class="tnc-noti">
                            <span class="tnc-noti-ic">📣</span>
                            <div class="tnc-noti-tx">
                                <b x-text="title.trim() || 'Tiêu đề thông báo'" :class="{ ph: !title.trim() }"></b>
                                <p x-text="previewText" :class="{ ph: !body.trim() }"></p>
                                <small>Vừa xong · Giáo viên <span x-text="teacher"></span></small>
                            </div>
                        </div>
                        <div class="tnc-noti ghost"><span class="tnc-noti-ic">📘</span><div class="tnc-noti-tx"><i></i><i class="s"></i></div></div>
                    </div>
                    <ul class="tnc-tips">
                        <li><x-lucide name="sparkles" />Tiêu đề nói ngay việc chính, vd "Nghỉ học thứ Bảy 08/11".</li>
                        <li><x-lucide name="clock-3" />Ghi rõ ngày, giờ, việc các em cần chuẩn bị.</li>
                        <li><x-lucide name="user-round" />Việc cần gia đình biết thì chọn "Học sinh + phụ huynh".</li>
                    </ul>
                </aside>
            </div>
        </section>

        <section class="tcx-sec" x-data="tncPager({{ count($tncList) }})" x-ref="tncSec">
            <div class="tcx-sec-head">
                <span class="tcx-ic v"><x-lucide name="history" /></span>
                <div style="flex:1 1 220px;min-width:0"><h3>Thông báo đã gửi</h3><p>Mới nhất ở trên — gồm thông báo của mọi giáo viên dạy lớp này.</p></div>
                <span class="tcx-count">{{ count($tncList) }} thông báo</span>
            </div>
            @if (empty($tncList))
                <div class="tcx-empty">Lớp chưa có thông báo nào. Soạn ở khung phía trên rồi bấm <b>Gửi</b> — các em nhận được ngay.</div>
            @else
                <div class="tnc-list">
                    @foreach ($tncList as $n)
                        <article class="tnc-item" x-data="{ del: false }" :class="{ 'is-del': del }" x-show="onPage({{ $loop->index }})" @if ($loop->index >= 5) style="display:none" @endif>
                            <span class="tnc-item-ic">📣</span>
                            <div class="tnc-item-main">
                                <div class="tnc-item-top">
                                    <b>{{ $n['title'] ?: 'Thông báo lớp' }}</b>
                                    <span class="tnc-aud-chip a-{{ $n['audience'] }}">{{ $tncAudLabel[$n['audience']] ?? 'Học sinh' }}</span>
                                </div>
                                <p class="tnc-item-body" :class="{ open: openId === {{ $n['id'] }} }">{{ $n['body'] }}</p>
                                @if (mb_strlen($n['body']) > 180)
                                    <button type="button" class="tnc-more" @click="openId = openId === {{ $n['id'] }} ? null : {{ $n['id'] }}" x-text="openId === {{ $n['id'] }} ? 'Thu gọn' : 'Xem đầy đủ'">Xem đầy đủ</button>
                                @endif
                                <div class="tnc-meta">
                                    <span title="{{ $n['at'] }}"><x-lucide name="clock-3" />{{ $n['ago'] }} · {{ $n['at'] }}</span>
                                    <span><x-lucide name="user-round" />{{ $n['mine'] ? 'Bạn' : $n['by'] }}</span>
                                    @if ($n['students'] > 0)<span><x-lucide name="users" />{{ $n['students'] }} học sinh</span>@endif
                                    @if ($n['parents'] > 0)<span><x-lucide name="user-round" />{{ $n['parents'] }} phụ huynh</span>@endif
                                </div>
                                {{-- SỬA 6/11 — xoá thông báo đã gửi (hỏi lại ngay tại chỗ, không bật hộp thoại). --}}
                                @if (! empty($n['canDelete']))
                                    <form method="POST" action="{{ route('teacher.classes.notices.destroy', ['class' => $classRoom->id, 'notice' => $n['id']]) }}" class="tnc-del-bar" x-show="del" x-cloak x-transition @submit="$el.querySelector('[type=submit]').disabled = true">
                                        @csrf
                                        @method('DELETE')
                                        <x-lucide name="alert-triangle" />
                                        <p>Xoá thông báo này? Học sinh{{ $n['parents'] > 0 ? ', phụ huynh' : '' }} sẽ không còn thấy trong chuông thông báo nữa. Không khôi phục được.</p>
                                        <div class="tnc-del-acts">
                                            <button type="button" class="tnc-mini ghost" @click="del = false">Giữ lại</button>
                                            <button type="submit" class="tnc-mini red"><x-lucide name="trash-2" />Xoá luôn</button>
                                        </div>
                                    </form>
                                @endif
                            </div>
                            @if (! empty($n['canDelete']))
                                <button type="button" class="tnc-del" x-show="! del" @click="del = true" title="Xoá thông báo" aria-label="Xoá thông báo"><x-lucide name="trash-2" /><span>Xoá</span></button>
                            @endif
                        </article>
                    @endforeach
                </div>
                {{-- SỬA 6/11 (khách: "nhiều thông báo thì có phân trang, đừng dài xuống quá") — 5 thông báo mỗi trang. --}}
                <div class="tnc-pager" x-show="pages > 1" x-cloak>
                    <span class="tnc-range" x-text="rangeText"></span>
                    <nav class="tnc-pgs" aria-label="Phân trang thông báo">
                        <button type="button" class="tnc-pg" @click="go(page - 1)" :disabled="page === 1" aria-label="Trang trước"><x-lucide name="chevron-left" /></button>
                        <template x-for="(n, k) in pageList" :key="k">
                            <button type="button" class="tnc-pg" :class="{ on: n === page, dots: n === '…' }" :disabled="n === '…'" @click="go(n)" x-text="n"></button>
                        </template>
                        <button type="button" class="tnc-pg" @click="go(page + 1)" :disabled="page === pages" aria-label="Trang sau"><x-lucide name="chevron-right" /></button>
                    </nav>
                </div>
            @endif
        </section>

        <style>
            .tnc-ic { background: linear-gradient(135deg, #FFEDD5, #FFE4E6) !important; color: #EA580C !important; }
            .tnc-warn { display: flex; align-items: flex-start; gap: 8px; margin-bottom: 14px; padding: 10px 12px; border-radius: 12px; background: #FFF7E6; border: 1px solid #FDE2B3; font-size: 12.5px; line-height: 1.5; color: #92400E; }
            .tnc-warn svg { width: 15px; height: 15px; flex: none; margin-top: 2px; } .tnc-warn a { color: #B45309; font-weight: 800; }
            .tnc-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 18px; }
            @media (min-width: 1100px) { .tnc-grid { grid-template-columns: minmax(0, 1.55fr) minmax(300px, 1fr); gap: 22px; } }
            .tnc-form { display: grid; gap: 16px; min-width: 0; }
            .tnc-lb { display: block; margin: 0 0 8px 2px; line-height: 1.5; font-size: 12.5px; font-weight: 800; color: #1E293B; }
            .tnc-lb em { font-style: normal; font-weight: 600; color: #94A3B8; }
            .tnc-lb svg { width: 15px; height: 15px; margin-right: 5px; vertical-align: -3px; color: #64748B; }
            .tnc-aud { display: grid; grid-template-columns: minmax(0, 1fr); gap: 10px; }
            @media (min-width: 700px) { .tnc-aud { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
            .tnc-opt { position: relative; display: flex; align-items: center; gap: 10px; padding: 12px; border: 1.5px solid #E2E8F0; border-radius: 14px; background: #fff; cursor: pointer; transition: all .15s; }
            .tnc-opt:hover { border-color: #BFD3EA; background: #FAFCFF; }
            .tnc-opt input { position: absolute; opacity: 0; pointer-events: none; }
            .tnc-opt.on { border-color: #2563EB; background: #F5F9FF; box-shadow: 0 0 0 4px rgba(37, 99, 235, .1); }
            .tnc-opt.dis { opacity: .55; cursor: not-allowed; }
            .tnc-opt-ic { display: grid; place-items: center; width: 36px; height: 36px; flex: none; border-radius: 11px; background: #EEF4FF; color: #2563EB; }
            .tnc-opt-ic.f { background: #ECFDF5; color: #059669; } .tnc-opt-ic.p { background: #FFF1F2; color: #E11D48; }
            .tnc-opt-ic svg { width: 18px; height: 18px; }
            .tnc-opt-tx { flex: 1; min-width: 0; } .tnc-opt-tx b { display: block; font-size: 13px; font-weight: 800; color: #0F172A; } .tnc-opt-tx small { display: block; margin-top: 1px; font-size: 11.5px; color: #64748B; }
            .tnc-tick { display: grid; place-items: center; width: 20px; height: 20px; flex: none; border-radius: 50%; border: 1.5px solid #CBD5E1; color: transparent; }
            .tnc-tick svg { width: 12px; height: 12px; }
            .tnc-opt.on .tnc-tick { border-color: #2563EB; background: #2563EB; color: #fff; }
            .tnc-tpl { display: flex; flex-wrap: wrap; gap: 8px; }
            .tnc-tpl button { display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 13px; border: 1px solid #E2E8F0; border-radius: 999px; background: #fff; font: inherit; font-size: 12.5px; font-weight: 700; color: #334155; cursor: pointer; transition: all .15s; }
            .tnc-tpl button:hover { border-color: #FDBA74; background: #FFF7ED; color: #C2410C; }
            .tnc-tpl button.on { border-color: #F97316; background: #FFF7ED; color: #C2410C; }
            .tnc-field { min-width: 0; }
            .tnc-fh { display: flex; align-items: baseline; justify-content: space-between; margin: 0 2px 6px; }
            .tnc-fh label { font-size: 12.5px; font-weight: 800; color: #1E293B; }
            .tnc-fh small { font-size: 11px; font-weight: 600; color: #94A3B8; font-variant-numeric: tabular-nums; } .tnc-fh small.over { color: #DC2626; }
            .tnc-input { display: block; box-sizing: border-box; width: 100%; height: 44px; padding: 0 14px; border: 1px solid #D9E3EE; border-radius: 12px; background: #fff; font: inherit; font-size: 13.5px; color: #0F172A; transition: border-color .15s, box-shadow .15s; }
            .tnc-input::placeholder { color: #A3B1C2; }
            .tnc-input:focus { outline: 0; border-color: #3B82F6; box-shadow: 0 0 0 4px rgba(59, 130, 246, .12); }
            .tnc-ta { height: auto; min-height: 140px; padding: 12px 14px; line-height: 1.6; resize: vertical; }
            .tnc-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 12px; }
            .tnc-foot small { margin-right: auto; font-size: 12px; color: #94A3B8; }
            .tnc-btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; height: 42px; padding: 0 20px; border: 1px solid transparent; border-radius: 12px; font: inherit; font-size: 13.5px; font-weight: 800; white-space: nowrap; cursor: pointer; transition: all .15s; }
            .tnc-btn svg { width: 16px; height: 16px; }
            .tnc-btn.pri { background: linear-gradient(135deg, #F97316, #EA580C); color: #fff; box-shadow: 0 8px 20px rgba(234, 88, 12, .28); }
            .tnc-btn.pri:hover { transform: translateY(-1px); }
            .tnc-btn.pri:disabled { opacity: .45; cursor: not-allowed; transform: none; box-shadow: none; }
            .tnc-btn.ghost { border-color: #E2E8F0; background: #fff; color: #475569; }
            .tnc-confirm { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding: 14px; border-radius: 14px; background: linear-gradient(120deg, #FFF7ED, #FFF1F2); border: 1px solid #FED7AA; }
            .tnc-confirm-ic { display: grid; place-items: center; width: 38px; height: 38px; flex: none; border-radius: 12px; background: #fff; color: #EA580C; box-shadow: 0 4px 10px rgba(234, 88, 12, .15); }
            .tnc-confirm-ic svg { width: 18px; height: 18px; }
            .tnc-confirm p { flex: 1 1 240px; margin: 0; font-size: 13px; line-height: 1.5; color: #7C2D12; }
            .tnc-acts { display: flex; gap: 10px; margin-left: auto; }
            .tnc-prev { min-width: 0; }
            .tnc-phone { padding: 14px; border-radius: 20px; background: linear-gradient(160deg, #F1F5FB, #EEF2FF); border: 1px solid #E2E8F0; }
            .tnc-phone-h { display: flex; align-items: center; gap: 6px; margin-bottom: 10px; font-size: 13px; font-weight: 800; color: #0F2F57; }
            .tnc-phone-h svg { width: 15px; height: 15px; } .tnc-phone-h span { margin-left: auto; padding: 2px 8px; border-radius: 999px; background: #EF4444; color: #fff; font-size: 10.5px; }
            .tnc-noti { display: flex; gap: 10px; padding: 12px; border-radius: 14px; background: #fff; box-shadow: 0 6px 16px rgba(15, 42, 74, .07); }
            .tnc-noti + .tnc-noti { margin-top: 8px; }
            .tnc-noti.ghost { opacity: .5; box-shadow: none; }
            .tnc-noti-ic { display: grid; place-items: center; width: 34px; height: 34px; flex: none; border-radius: 11px; background: #FFF1E6; font-size: 16px; }
            .tnc-noti-tx { flex: 1; min-width: 0; }
            .tnc-noti-tx b { display: block; font-size: 13px; font-weight: 800; color: #0F172A; word-break: break-word; }
            .tnc-noti-tx p { margin: 3px 0 0; font-size: 12px; line-height: 1.5; color: #475569; word-break: break-word; display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden; }
            .tnc-noti-tx .ph { color: #A3B1C2; font-weight: 600; }
            .tnc-noti-tx small { display: block; margin-top: 6px; font-size: 11px; color: #94A3B8; }
            .tnc-noti-tx i { display: block; height: 9px; width: 70%; border-radius: 6px; background: #E2E8F0; margin-top: 4px; } .tnc-noti-tx i.s { width: 45%; }
            .tnc-tips { display: grid; gap: 8px; margin: 14px 0 0; padding: 0; list-style: none; }
            .tnc-tips li { display: flex; align-items: flex-start; gap: 8px; font-size: 12px; line-height: 1.5; color: #64748B; }
            .tnc-tips svg { width: 14px; height: 14px; flex: none; margin-top: 2px; color: #F59E0B; }
            .tnc-list { display: grid; gap: 10px; }
            .tnc-item { display: flex; gap: 12px; padding: 14px; border: 1px solid #E6EEF6; border-radius: 14px; background: #fff; transition: box-shadow .15s; }
            .tnc-item:hover { box-shadow: 0 8px 18px rgba(15, 42, 74, .06); }
            .tnc-item-ic { display: grid; place-items: center; width: 40px; height: 40px; flex: none; border-radius: 12px; background: #FFF1E6; font-size: 18px; }
            .tnc-item-main { flex: 1; min-width: 0; }
            .tnc-item-top { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 10px; }
            .tnc-item-top b { font-size: 14px; font-weight: 800; color: #0F172A; }
            .tnc-aud-chip { padding: 2px 9px; border-radius: 999px; font-size: 11px; font-weight: 800; background: #EEF4FF; color: #1D4ED8; }
            .tnc-aud-chip.a-families { background: #ECFDF5; color: #047857; } .tnc-aud-chip.a-parents { background: #FFF1F2; color: #BE123C; }
            .tnc-item-body { margin: 6px 0 0; font-size: 13px; line-height: 1.6; color: #334155; white-space: pre-line; word-break: break-word; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
            .tnc-item-body.open { display: block; -webkit-line-clamp: unset; }
            .tnc-more { margin-top: 4px; padding: 0; border: 0; background: none; font: inherit; font-size: 12px; font-weight: 800; color: #2563EB; cursor: pointer; }
            .tnc-meta { display: flex; flex-wrap: wrap; gap: 6px 14px; margin-top: 8px; }
            .tnc-meta span { display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px; color: #64748B; }
            .tnc-meta svg { width: 13px; height: 13px; }
            .tnc-item { align-items: flex-start; }
            .tnc-pager { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-top: 14px; padding-top: 14px; border-top: 1px solid #EEF2F7; }
            .tnc-range { font-size: 12px; font-weight: 600; color: #94A3B8; }
            .tnc-pgs { display: flex; flex-wrap: wrap; gap: 6px; }
            .tnc-pg { display: grid; place-items: center; min-width: 34px; height: 34px; padding: 0 8px; border: 1px solid #DCE6F2; border-radius: 10px; background: #fff; font: inherit; font-size: 13px; font-weight: 700; color: #475569; cursor: pointer; transition: all .15s; }
            .tnc-pg svg { width: 16px; height: 16px; }
            .tnc-pg:hover:not(:disabled) { border-color: #93C5FD; color: #1D4ED8; }
            .tnc-pg.on { border-color: #2563EB; background: #2563EB; color: #fff; box-shadow: 0 4px 12px rgba(37, 99, 235, .25); }
            .tnc-pg:disabled { opacity: .4; cursor: default; } .tnc-pg.dots { border-color: transparent; background: none; opacity: 1; }
            @media (max-width: 560px) { .tnc-pager { justify-content: center; } .tnc-range { width: 100%; text-align: center; } }
            .tnc-item.is-del { border-color: #FECACA; background: #FFFBFB; }
            .tnc-del { display: inline-flex; align-items: center; gap: 5px; flex: none; height: 32px; padding: 0 11px; border: 1px solid #E2E8F0; border-radius: 10px; background: #fff; font: inherit; font-size: 12px; font-weight: 700; color: #64748B; cursor: pointer; transition: all .15s; }
            .tnc-del svg { width: 14px; height: 14px; }
            .tnc-del:hover { border-color: #FCA5A5; background: #FFF5F5; color: #DC2626; }
            .tnc-del-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 10px; margin-top: 12px; padding: 10px 12px; border-radius: 12px; background: #FEF2F2; border: 1px solid #FECACA; }
            .tnc-del-bar > svg { width: 16px; height: 16px; flex: none; color: #DC2626; }
            .tnc-del-bar p { flex: 1 1 220px; margin: 0; font-size: 12.5px; line-height: 1.5; color: #991B1B; }
            .tnc-del-acts { display: flex; gap: 8px; margin-left: auto; }
            .tnc-mini { display: inline-flex; align-items: center; gap: 5px; height: 32px; padding: 0 12px; border: 1px solid transparent; border-radius: 9px; font: inherit; font-size: 12px; font-weight: 800; cursor: pointer; }
            .tnc-mini svg { width: 14px; height: 14px; }
            .tnc-mini.ghost { border-color: #E2E8F0; background: #fff; color: #475569; }
            .tnc-mini.red { background: #DC2626; color: #fff; box-shadow: 0 4px 12px rgba(220, 38, 38, .25); } .tnc-mini.red:hover { background: #B91C1C; } .tnc-mini.red:disabled { opacity: .6; }
            @media (max-width: 560px) { .tnc-foot .tnc-btn, .tnc-acts { width: 100%; } .tnc-acts .tnc-btn { flex: 1; } .tnc-del span { display: none; } .tnc-del { padding: 0 9px; } }
        </style>
        <script>
            window.tncPager = function (total) {
                return {
                    openId: null, page: 1, per: 5, total,
                    get pages() { return Math.max(1, Math.ceil(this.total / this.per)); },
                    onPage(i) { return Math.floor(i / this.per) + 1 === this.page; },
                    get rangeText() { return 'Hiển thị ' + ((this.page - 1) * this.per + 1) + '–' + Math.min(this.page * this.per, this.total) + ' / ' + this.total + ' thông báo'; },
                    get pageList() {
                        const n = this.pages, p = this.page;
                        if (n <= 7) return Array.from({ length: n }, (_, i) => i + 1);
                        const out = []; [...new Set([1, p - 1, p, p + 1, n].filter((x) => x >= 1 && x <= n))].sort((a, b) => a - b)
                            .forEach((x, i, arr) => { if (i && x - arr[i - 1] > 1) out.push('…'); out.push(x); });
                        return out;
                    },
                    go(n) {
                        if (typeof n !== 'number' || n < 1 || n > this.pages || n === this.page) return;
                        this.page = n; this.openId = null;
                        const r = this.$refs.tncSec; if (r && r.getBoundingClientRect().top < 0) r.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    },
                };
            };
            window.tncForm = function (c) {
                const cls = c.className || 'lớp';
                return {
                    students: c.students, parents: c.parents, aud: c.aud || 'students', title: c.title || '', body: c.body || '',
                    teacher: c.teacher || '', confirming: false, sending: false, picked: null,
                    templates: [
                        { icon: '🏖️', name: 'Nghỉ học', title: 'Thông báo nghỉ học ngày [dd/mm]', body: 'Các em lưu ý: lớp ' + cls + ' nghỉ học buổi ngày [dd/mm]. Lịch học bù thầy/cô sẽ thông báo sau. Các em tranh thủ ôn lại bài cũ nhé!' },
                        { icon: '🗓️', name: 'Đổi lịch học', title: 'Thay đổi lịch học tuần này', body: 'Buổi học ngày [dd/mm] của lớp ' + cls + ' chuyển sang [giờ], ngày [dd/mm]. Các em sắp xếp thời gian tham gia đầy đủ nhé.' },
                        { icon: '📝', name: 'Nhắc làm bài', title: 'Nhắc hoàn thành bài tập', body: 'Các em nhớ hoàn thành bài tập đã giao trước [giờ] ngày [dd/mm]. Em nào gặp khó khăn cứ nhắn thầy/cô để được hỗ trợ.' },
                        { icon: '🎯', name: 'Kiểm tra sắp tới', title: 'Lịch kiểm tra ngày [dd/mm]', body: 'Lớp ' + cls + ' sẽ làm bài kiểm tra vào [giờ], ngày [dd/mm]. Nội dung: [phạm vi kiến thức]. Các em ôn tập kỹ và vào đúng giờ nhé.' },
                        { icon: '🌟', name: 'Khen ngợi', title: 'Cả lớp làm rất tốt!', body: 'Thầy/cô rất vui vì tuần qua cả lớp học tập chăm chỉ và tiến bộ rõ rệt. Các em tiếp tục cố gắng nhé!' },
                    ],
                    get recipients() { return this.aud === 'parents' ? this.parents : (this.aud === 'families' ? this.students + this.parents : this.students); },
                    get whoLabel() { return this.aud === 'parents' ? this.parents + ' phụ huynh' : (this.aud === 'families' ? this.students + ' học sinh và ' + this.parents + ' phụ huynh' : this.students + ' học sinh'); },
                    get canSend() { return this.students > 0 && this.recipients > 0 && this.title.trim() !== '' && this.body.trim() !== ''; },
                    get hint() { return this.students === 0 ? 'Lớp chưa có học sinh.' : (!this.title.trim() || !this.body.trim() ? 'Nhập tiêu đề và nội dung để gửi.' : ''); },
                    get previewText() { return this.body.trim() ? 'Lớp ' + cls + ' · ' + this.teacher + ': ' + this.body.trim() : 'Nội dung thông báo sẽ hiện ở đây…'; },
                    useTpl(t) { this.picked = this.templates.indexOf(t); this.title = t.title; this.body = t.body; this.confirming = false; this.$nextTick(() => this.$refs.title && this.$refs.title.focus()); },
                };
            };
        </script>
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
