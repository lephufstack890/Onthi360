{{-- ═══════════ CỘT PHẢI MÀN ĐỌC: BÀI TẬP + HỌC LIỆU THEO CHƯƠNG ═══════════
     SỬA 14/10 (khách: "update lại UI màn đọc tài liệu cho giống style source mới, bài tập và học liệu
     hiển thị theo chương, chỗ chọn chương để nguyên") — dựng theo education-main/src/components/
     MaterialChapterGrid.jsx: lưới Ô MÃ BÀI 3 cột (màu theo kết quả), ô tìm "Tìm mã bài…", 3 nút lọc,
     dòng "x/y đã làm" và chú thích màu ở chân.

     Chia theo chương (khác bản mẫu — bản mẫu chỉ một danh sách phẳng):
       · mặc định "Chương đang đọc": chỉ bài tập + học liệu của chương đang mở ở bên trái (chương
         chọn ở ô "Chương/phần" trên thanh công cụ — ô đó GIỮ NGUYÊN);
       · bấm "Tất cả chương": gom theo từng chương có tiêu đề + số bài đã làm, bài chưa gắn chương ở cuối.
     Bấm 1 ô thì hiện thẻ chi tiết (tên bài, độ khó, điểm) + nút theo vai trò: học sinh "Làm bài",
     giáo viên "Xem đề", admin "Xem / sửa bài" — tránh bấm nhầm là nhảy trang ngay.

     Bên gọi cần: $exercises, $attachments, $chapters, $currentChapterId, $readerRole, $chapterWord.
     CSS viết thường (không dùng lớp Tailwind tuỳ biến) vì máy chủ không build lại CSS. --}}
@php
    $chapters = $chapters ?? [];
    $currentChapterId = $currentChapterId ?? null;
    $readerRole = $readerRole ?? 'student';
    $chapterWord = $chapterWord ?? 'Chương';
    $exercises = $exercises ?? [];
    $attachments = $attachments ?? [];

    $chapterIds = collect($chapters)->pluck('id')->all();
    $chapterOf = fn ($id) => in_array($id, $chapterIds, true) ? $id : 0;   // 0 = chưa gắn chương

    $statusText = ['done' => 'Đã làm', 'progress' => 'Đang làm', 'open' => 'Chưa làm'];
    $rcpRows = [];
    foreach ($exercises as $ex) {
        $rcpRows[] = [
            'id' => $ex['id'],
            'code' => $ex['code'] ?? ('#'.$ex['id']),
            'title' => $ex['title'],
            'chapter' => $chapterOf($ex['chapterId'] ?? null),
            'status' => $ex['status'],
            'statusLabel' => $statusText[$ex['status']] ?? 'Chưa làm',
            'stars' => (int) ($ex['difficultyStars'] ?? 0),
            'difficulty' => $ex['difficultyLabel'] ?? '',
            'points' => (int) ($ex['points'] ?? 0),
            'tags' => array_values($ex['tags'] ?? []),
            'startUrl' => $readerRole === 'student' ? route('student.practiceByQuestion.startExercise', $ex['id']) : null,
            'viewUrl' => match ($readerRole) {
                'admin' => $ex['editHref'] ?? null,
                'teacher' => route('access.resource.exerciseAttachment', [$ex['productId'] ?? 0, $ex['id'], 'statement']),
                default => null,
            },
            'search' => \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii(($ex['code'] ?? '').' '.$ex['title'].' '.implode(' ', $ex['tags'] ?? []))),
        ];
    }

    // Nhóm hiển thị: các chương theo thứ tự + "Chưa gắn chương" (id 0) nếu có bài/học liệu lẻ.
    $groups = collect($chapters)->map(fn ($c) => ['id' => $c['id'], 'label' => $chapterWord.' '.$c['no'], 'title' => $c['title']])->all();
    $hasLooseEx = collect($rcpRows)->contains(fn ($r) => $r['chapter'] === 0);
    $hasLooseAt = collect($attachments)->contains(fn ($a) => $a['kind'] !== 'file' && $chapterOf($a['chapterId'] ?? null) === 0);
    if ($hasLooseEx || $hasLooseAt) {
        $groups[] = ['id' => 0, 'label' => 'Khác', 'title' => 'Chưa gắn '.mb_strtolower($chapterWord)];
    }
    $sharedFiles = collect($attachments)->where('kind', 'file')->values()->all();
    $chapterAttachments = collect($attachments)->where('kind', '!=', 'file')->values()->all();

    $currentChapter = collect($chapters)->firstWhere('id', $currentChapterId);
    $rcpConfig = [
        'rows' => $rcpRows,
        'groups' => collect($groups)->pluck('id')->all(),
        'current' => $currentChapter ? $currentChapterId : null,
        'attachments' => collect($chapterAttachments)->map(fn ($a, $i) => ['i' => $i, 'chapter' => $chapterOf($a['chapterId'] ?? null)])->all(),
    ];
@endphp

<style>
    .rcp { display: flex; min-height: 0; flex-direction: column; gap: 12px; color: #466278; }
    @media (min-width: 1024px) {
        .rcp { position: sticky; top: 84px; height: calc(100dvh - 104px); max-height: 1100px; overflow-y: auto; overscroll-behavior: contain; scrollbar-width: thin; }
        .rcp.is-focus { top: 16px; height: calc(100dvh - 32px); }
    }
    .rcp-card { display: flex; min-height: 0; flex-direction: column; overflow: hidden; border: 1px solid #D5E8ED; border-radius: 16px; background: #fff; box-shadow: 0 1px 2px rgba(15,23,42,.04); }
    .rcp-card.is-main { flex: none; }
    @media (min-width: 1024px) { .rcp-card.is-main { flex: 1 0 0; min-height: 560px; } }
    .rcp-head { flex-shrink: 0; border-bottom: 1px solid #E5EEF3; padding: 12px; }
    .rcp-title { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    .rcp-title h2 { margin: 0; font-size: 14px; font-weight: 700; color: #123B68; }
    .rcp-title span { font-size: 11px; color: #61798B; }
    .rcp-scope { display: flex; gap: 4px; margin-top: 10px; padding: 3px; border: 1px solid #DCE9EE; border-radius: 10px; background: #F7FAFC; }
    .rcp-scope button { flex: 1; min-width: 0; min-height: 32px; padding: 0 8px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; border: 0; border-radius: 8px; background: transparent; font-size: 11px; font-weight: 700; color: #61798B; cursor: pointer; }
    .rcp-scope button.is-on { background: #E3F1FA; color: #1D5A7A; }
    .rcp-scope button.is-chapter { flex: 1.6; }
    .rcp-scope b { font-weight: 800; }
    .rcp-scope button:not(.is-on):hover { background: #EDF5FA; }
    .rcp-chapter { display: flex; align-items: center; gap: 6px; margin-top: 8px; padding: 7px 9px; border-radius: 10px; background: #F2F8FA; font-size: 11.5px; color: #35617A; }
    .rcp-chapter b { flex-shrink: 0; font-weight: 800; color: #126F91; }
    .rcp-chapter span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .rcp-search { position: relative; margin-top: 10px; }
    .rcp-search svg { position: absolute; left: 10px; top: 50%; width: 15px; height: 15px; transform: translateY(-50%); color: #61798B; pointer-events: none; }
    .rcp-search input { width: 100%; min-height: 40px; box-sizing: border-box; padding: 0 32px 0 32px; border: 1px solid #DDEAF0; border-radius: 10px; background: #F8FBFE; font-size: 12px; color: #123B68; outline: none; }
    .rcp-search input:focus { border-color: #126F91; box-shadow: 0 0 0 3px #DDF1F6; }
    .rcp-search input::-webkit-search-cancel-button { display: none; }
    .rcp-search button { position: absolute; right: 0; top: 0; display: grid; width: 32px; height: 40px; place-items: center; border: 0; background: none; color: #61798B; cursor: pointer; font-size: 15px; }
    .rcp-tabs { display: flex; gap: 4px; margin-top: 8px; padding: 4px; border-radius: 10px; background: #F2F6F8; }
    .rcp-tabs button { flex: 1; min-height: 32px; border: 0; border-radius: 8px; background: transparent; font-size: 11.5px; font-weight: 600; color: #61798B; cursor: pointer; }
    .rcp-tabs button.is-on { background: #fff; color: #123B68; box-shadow: 0 1px 3px rgba(18,59,104,.12); }
    .rcp-tabs button:not(.is-on):hover { background: #EDF5FA; }
    .rcp-status { margin: 8px 0 0; font-size: 11px; color: #61798B; }
    .rcp-body { min-height: 132px; flex: 1 1 auto; overflow-y: auto; overscroll-behavior: contain; padding: 12px; }
    @media (max-width: 1023px) { .rcp-body { max-height: 55vh; } }
    .rcp-group { margin-bottom: 14px; }
    .rcp-ghead { display: flex; align-items: center; gap: 6px; margin: 0 0 7px; font-size: 11px; color: #61798B; }
    .rcp-ghead b { flex-shrink: 0; padding: 2px 7px; border-radius: 6px; background: #EAF5F8; font-size: 10px; font-weight: 800; color: #126F91; }
    .rcp-ghead span { min-width: 0; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 600; color: #35617A; }
    .rcp-ghead i { flex-shrink: 0; font-style: normal; font-size: 10px; }
    .rcp-ghead.is-current b { background: #126F91; color: #fff; }
    .rcp-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 6px; }
    .rcp-tile { position: relative; display: flex; min-height: 48px; min-width: 0; align-items: center; justify-content: center; gap: 4px; padding: 6px; border: 1px solid #DDEAF0; border-radius: 9px; background: #fff; color: #123B68; cursor: pointer; transition: background .12s, border-color .12s, box-shadow .12s; }
    .rcp-tile:hover { border-color: #126F91; background: #F8FBFE; }
    .rcp-tile code { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font: 700 11px/1.2 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
    .rcp-tile svg { flex-shrink: 0; width: 12px; height: 12px; }
    .rcp-tile.is-done { border-color: #CDE7D6; background: #EFF8F2; color: #166534; }
    .rcp-tile.is-done:hover { background: #E2F2E7; }
    .rcp-tile.is-progress { border-color: #F0E1BA; background: #FFFAEB; color: #92400E; }
    .rcp-tile.is-progress:hover { background: #FFF3CF; }
    .rcp-tile.is-selected { border-color: #135C78; background: #17698A; color: #fff; box-shadow: 0 0 0 2px #B9DDE8; }
    .rcp-tile:focus-visible { outline: 2px solid #7FAFCB; outline-offset: 2px; }
    .rcp-empty { padding: 16px; border: 1px dashed #C9DFE8; border-radius: 10px; text-align: center; font-size: 12px; color: #61798B; }
    .rcp-empty button { margin-top: 6px; border: 0; background: none; font-size: 12px; font-weight: 700; color: #126F91; text-decoration: underline; cursor: pointer; }
    .rcp-detail { flex-shrink: 0; border-top: 1px solid #E5EEF3; background: #F8FBFD; padding: 10px 12px; }
    .rcp-detail-top { display: flex; align-items: center; gap: 6px; }
    .rcp-detail-top code { padding: 2px 7px; border-radius: 6px; background: #E3F1FA; font: 700 10.5px/1.4 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; color: #1D5A7A; }
    .rcp-pill { padding: 2px 7px; border-radius: 999px; font-size: 10px; font-weight: 700; }
    .rcp-pill.is-done { background: #DFF2E9; color: #287B5F; }
    .rcp-pill.is-progress { background: #FFF1D5; color: #946A28; }
    .rcp-pill.is-open { background: #EAF0F5; color: #526B7D; }
    .rcp-detail h3 { margin: 6px 0 0; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; font-size: 13px; font-weight: 700; line-height: 1.35; color: #123B68; }
    .rcp-meta { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-top: 6px; font-size: 10.5px; color: #526B7D; }
    .rcp-stars { display: inline-flex; gap: 1px; }
    .rcp-stars svg { width: 13px; height: 13px; color: #C9D7E0; }
    .rcp-stars svg.is-on { color: #D29A18; fill: currentColor; }
    .rcp-act { display: flex; width: 100%; min-height: 34px; margin-top: 8px; align-items: center; justify-content: center; gap: 6px; border: 0; border-radius: 9px; background: #368F72; font-size: 12px; font-weight: 700; color: #fff; text-decoration: none; cursor: pointer; }
    .rcp-act:hover { background: #2F8066; }
    .rcp-act.is-light { border: 1px solid #DDEAF0; background: #fff; color: #126F91; }
    .rcp-act.is-light:hover { border-color: #9DC8D7; background: #F0F8FB; }
    .rcp-act svg { width: 14px; height: 14px; }
    .rcp-hint { margin: 0; font-size: 11px; color: #7A93A5; text-align: center; }
    .rcp-legend { display: flex; flex-shrink: 0; flex-wrap: wrap; gap: 6px 12px; border-top: 1px solid #E5EEF3; padding: 8px 12px; font-size: 10px; color: #61798B; }
    .rcp-legend span { display: inline-flex; align-items: center; gap: 4px; }
    .rcp-legend i { width: 12px; height: 12px; border: 1px solid; border-radius: 3px; }
    /* Học liệu */
    .rcp-card.is-media { flex: 0 0 auto; max-height: 460px; }
    @media (max-width: 1023px) { .rcp-card.is-media { max-height: none; } }
    .rcp-mtoggle { display: flex; width: 100%; flex-shrink: 0; align-items: center; justify-content: space-between; gap: 8px; padding: 12px; border: 0; background: none; text-align: left; cursor: pointer; }
    .rcp-mtoggle h2 { margin: 0; display: flex; align-items: center; gap: 6px; font-size: 14px; font-weight: 700; color: #123B68; }
    .rcp-mtoggle h2 svg { width: 16px; height: 16px; color: #126F91; }
    .rcp-count { padding: 3px 9px; border-radius: 999px; background: #EAF5F8; font-size: 10.5px; font-weight: 700; color: #126F91; }
    .rcp-mbody { min-height: 0; overflow-y: auto; border-top: 1px solid #E5EEF3; padding: 10px 12px 12px; }
    .rcp-media { margin-bottom: 8px; padding: 9px; border: 1px solid #DDEAF0; border-radius: 12px; background: #F8FBFC; }
    .rcp-media-row { display: flex; align-items: center; gap: 8px; }
    .rcp-media-row > span { display: grid; flex-shrink: 0; width: 30px; height: 30px; place-items: center; border-radius: 8px; background: #E9F7F8; color: #23869B; }
    .rcp-media-row > span svg { width: 14px; height: 14px; }
    .rcp-media-row p { margin: 0; min-width: 0; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12.5px; font-weight: 600; color: #123B68; }
    .rcp-media audio { width: 100%; margin-top: 8px; }
    .rcp-media img { display: block; width: 100%; margin-top: 8px; border: 1px solid #DDEAF0; border-radius: 10px; }
    .rcp-media a.rcp-file { display: inline-flex; align-items: center; gap: 6px; margin-top: 8px; padding: 5px 10px; border: 1px solid #DDEAF0; border-radius: 9px; background: #fff; font-size: 11.5px; font-weight: 600; color: #466278; text-decoration: none; }
    .rcp-media a.rcp-file:hover { border-color: #9DC8D7; color: #126F91; }
    .rcp-media a.rcp-file svg { width: 13px; height: 13px; }
    [x-cloak] { display: none !important; }
</style>

<div class="rcp" :class="focus ? 'is-focus' : ''" x-data="onthiChapterPanel({{ \Illuminate\Support\Js::from($rcpConfig) }})">
    {{-- ══════ BÀI TẬP ══════ --}}
    <section class="rcp-card is-main" aria-label="Danh sách bài tập">
        <div class="rcp-head">
            <div class="rcp-title">
                <h2>Bài tập</h2>
                <span x-text="scopeRows.length + ' bài / đề'">{{ count($rcpRows) }} bài / đề</span>
            </div>

            {{-- SỬA 16/10 (khách: "bỏ cái này đi, không cần") — BỎ cặp nút "Chương đang đọc | Tất cả chương". Cột Bài tập giờ
                 luôn hiện TẤT CẢ bài, gom theo từng chương (dòng tiêu đề chương, chương đang đọc được tô đậm). --}}

            <div class="rcp-search">
                <x-lucide name="search" />
                <input type="search" x-model="q" placeholder="Tìm mã bài, tên bài…" aria-label="Tìm bài tập" autocomplete="off">
                <button type="button" x-show="q" x-cloak @click="q = ''" aria-label="Xoá tìm kiếm">×</button>
            </div>
            <div class="rcp-tabs" role="group" aria-label="Lọc trạng thái bài">
                @foreach ([['all', 'Tất cả'], ['todo', 'Chưa xong'], ['done', 'Đã làm']] as [$fKey, $fLabel])
                    <button type="button" :class="filter === '{{ $fKey }}' ? 'is-on' : ''" :aria-pressed="filter === '{{ $fKey }}'" @click="filter = '{{ $fKey }}'">{{ $fLabel }}</button>
                @endforeach
            </div>
            <p class="rcp-status" role="status" x-text="statusLine"></p>
        </div>

        <div class="rcp-body">
            @foreach ($groups as $g)
                <div class="rcp-group" x-show="groupVisible({{ $g['id'] }})" x-cloak>
                    <p class="rcp-ghead {{ $g['id'] === $currentChapterId ? 'is-current' : '' }}" x-show="scope === 'all'">
                        <b>{{ $g['label'] }}</b><span title="{{ $g['title'] }}">{{ $g['title'] }}</span><i x-text="groupDone({{ $g['id'] }})"></i>
                    </p>
                    <div class="rcp-grid">
                        @foreach ($rcpRows as $r)
                            @continue($r['chapter'] !== $g['id'])
                            <button type="button" class="rcp-tile is-{{ $r['status'] }}"
                                    x-show="matches({{ $r['id'] }})"
                                    :class="selectedId === {{ $r['id'] }} ? 'is-selected' : ''"
                                    :aria-current="selectedId === {{ $r['id'] }} ? 'true' : null"
                                    @click="selectedId = {{ $r['id'] }}"
                                    title="{{ $r['code'] }} · {{ $r['title'] }} · {{ $r['statusLabel'] }}"
                                    aria-label="Bài {{ $r['code'] }} · {{ $r['statusLabel'] }}">
                                <code>{{ $r['code'] }}</code>
                                @if ($r['status'] === 'done')
                                    <x-lucide name="check-circle-2" />
                                @elseif ($r['status'] === 'progress')
                                    <x-lucide name="clock-3" />
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="rcp-empty" x-show="visibleCount === 0" x-cloak>
                <p style="margin:0" x-text="emptyText"></p>
                <button type="button" x-show="q || filter !== 'all' || scope === 'chapter'" @click="q = ''; filter = 'all'; scope = 'all'">Xem tất cả bài</button>
            </div>
        </div>

        {{-- Thẻ chi tiết bài đang chọn + nút theo vai trò. --}}
        <div class="rcp-detail">
            <p class="rcp-hint" x-show="! selected">Chọn một ô mã bài để xem đề và {{ $readerRole === 'student' ? 'bắt đầu làm' : 'mở bài' }}.</p>
            <template x-if="selected">
                <div>
                    <div class="rcp-detail-top">
                        <code x-text="selected.code"></code>
                        <span class="rcp-pill" :class="'is-' + selected.status" x-text="selected.statusLabel"></span>
                    </div>
                    <h3 x-text="selected.title" :title="selected.title"></h3>
                    <div class="rcp-meta">
                        <span class="rcp-stars" role="img" :aria-label="'Độ khó ' + selected.stars + ' trên 5 sao'" :title="selected.difficulty">
                            <template x-for="n in [1, 2, 3, 4, 5]" :key="n">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :class="n <= selected.stars ? 'is-on' : ''"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            </template>
                        </span>
                        <span x-text="selected.points + ' điểm'"></span>
                    </div>
                    @if ($readerRole === 'student')
                        {{-- Giữ NGUYÊN đường đi cũ của nút Làm bài: POST kèm return_url để làm xong quay lại đúng trang này. --}}
                        <form method="POST" :action="selected.startUrl">
                            @csrf
                            <input type="hidden" name="return_url" value="{{ request()->getRequestUri() }}">
                            <button type="submit" class="rcp-act"><x-lucide name="play-circle" />Làm bài</button>
                        </form>
                    @elseif ($readerRole === 'admin')
                        <a class="rcp-act is-light" :href="selected.viewUrl" target="_blank" rel="noopener"><x-lucide name="pen-line" />Xem / sửa bài</a>
                    @else
                        <a class="rcp-act is-light" :href="selected.viewUrl" target="_blank" rel="noopener"><x-lucide name="file-text" />Xem đề</a>
                    @endif
                </div>
            </template>
        </div>

        <div class="rcp-legend" aria-label="Chú thích màu">
            <span><i style="border-color:#CDE7D6;background:#EFF8F2"></i>Đã làm</span>
            <span><i style="border-color:#F0E1BA;background:#FFFAEB"></i>Đang làm</span>
            <span><i style="border-color:#CBD5E1;background:#fff"></i>Chưa làm</span>
            <span><i style="border-color:#135C78;background:#17698A"></i>Đang chọn</span>
        </div>
    </section>

    {{-- ══════ HỌC LIỆU ══════ (audio/ảnh theo chương + tệp chung của tài liệu) --}}
    @if (count($attachments) > 0)
        <section class="rcp-card is-media" x-data="{ open: window.innerHeight >= 1000 || window.innerWidth < 1024 }">
            <button type="button" class="rcp-mtoggle" @click="open = ! open" :aria-expanded="open">
                <h2><x-lucide name="library" />Học liệu</h2>
                <span style="display:inline-flex;align-items:center;gap:6px">
                    <span class="rcp-count" x-text="mediaCount + {{ count($sharedFiles) }}">{{ count($attachments) }}</span>
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#9AAEBC" stroke-width="2" :style="open ? 'transform:rotate(180deg)' : ''" style="transition:transform .15s"><path d="m6 9 6 6 6-6"/></svg>
                </span>
            </button>
            <div class="rcp-mbody" x-show="open">
                @foreach ($groups as $g)
                    @php $items = collect($chapterAttachments)->filter(fn ($a) => $chapterOf($a['chapterId'] ?? null) === $g['id']); @endphp
                    @continue($items->isEmpty())
                    <div x-show="scope === 'all' || current === null || current === {{ $g['id'] }}">
                        <p class="rcp-ghead {{ $g['id'] === $currentChapterId ? 'is-current' : '' }}" x-show="scope === 'all' || current === null"><b>{{ $g['label'] }}</b><span>{{ $g['title'] }}</span></p>
                        @foreach ($items as $item)
                            <div class="rcp-media">
                                <div class="rcp-media-row">
                                    <span><x-lucide :name="$item['kind'] === 'audio' ? 'volume-2' : 'image'" /></span>
                                    <p title="{{ $item['title'] }}">{{ $item['title'] }}</p>
                                </div>
                                @if ($item['kind'] === 'audio')
                                    <audio controls preload="none" src="{{ $item['url'] }}"></audio>
                                @else
                                    <a href="{{ $item['url'] }}" target="_blank" rel="noopener"><img src="{{ $item['url'] }}" alt="{{ $item['title'] }}" loading="lazy"></a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endforeach

                @if (count($sharedFiles) > 0)
                    <p class="rcp-ghead"><b>Chung</b><span>Tệp của cả tài liệu</span></p>
                    @foreach ($sharedFiles as $item)
                        <div class="rcp-media">
                            <div class="rcp-media-row">
                                <span><x-lucide name="paperclip" /></span>
                                <p title="{{ $item['title'] }}">{{ $item['title'] }}</p>
                            </div>
                            <a class="rcp-file" href="{{ $item['url'] }}" target="_blank" rel="noopener"><x-lucide name="download" />Mở tệp</a>
                        </div>
                    @endforeach
                @endif

                <p class="rcp-hint" x-show="mediaCount === 0 && {{ count($sharedFiles) }} === 0" x-cloak>{{ $chapterWord }} này chưa có học liệu.</p>
            </div>
        </section>
    @endif
</div>

<script>
    function onthiChapterPanel(config) {
        const fold = (s) => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'D').toLowerCase().trim();
        return {
            rows: config.rows || [],
            groups: config.groups || [],
            current: config.current,
            attachments: config.attachments || [],
            scope: 'all', // SỬA 16/10 — đã bỏ nút chọn phạm vi, luôn hiện tất cả chương.
            q: '',
            filter: 'all',
            selectedId: null,

            init() {
                // Tài liệu cũ chưa gắn bài/học liệu vào chương nào -> mở sẵn "Tất cả" cho khỏi trống trơn.
                if (this.scope === 'chapter'
                    && ! this.rows.some((r) => r.chapter === this.current)
                    && ! this.attachments.some((a) => a.chapter === this.current)) {
                    this.scope = 'all';
                }
            },

            inScope(row) { return this.scope === 'all' || this.current === null || row.chapter === this.current; },
            get scopeRows() { return this.rows.filter((r) => this.inScope(r)); },
            passes(row) {
                const terms = fold(this.q).split(/\s+/).filter(Boolean);
                const okFilter = this.filter === 'all' || (this.filter === 'done' ? row.status === 'done' : row.status !== 'done');
                return okFilter && terms.every((t) => row.search.includes(t));
            },
            matches(id) {
                const row = this.rows.find((r) => r.id === id);
                return !! row && this.inScope(row) && this.passes(row);
            },
            groupVisible(gid) {
                return this.rows.some((r) => r.chapter === gid && this.inScope(r) && this.passes(r));
            },
            groupDone(gid) {
                const list = this.rows.filter((r) => r.chapter === gid);
                return list.filter((r) => r.status === 'done').length + '/' + list.length + ' đã làm';
            },
            get visibleCount() { return this.rows.filter((r) => this.inScope(r) && this.passes(r)).length; },
            get statusLine() {
                const list = this.scopeRows;
                if (this.q || this.filter !== 'all') return this.visibleCount + ' kết quả';
                return list.filter((r) => r.status === 'done').length + '/' + list.length + ' đã làm';
            },
            get emptyText() {
                if (! this.rows.length) return 'Tài liệu chưa có bài tập.';
                if (! this.scopeRows.length) return 'Chương này chưa có bài tập.';
                return 'Không tìm thấy bài phù hợp.';
            },
            get selected() { return this.rows.find((r) => r.id === this.selectedId) || null; },
            get mediaCount() { return this.attachments.filter((a) => this.scope === 'all' || this.current === null || a.chapter === this.current).length; },
        };
    }
</script>
