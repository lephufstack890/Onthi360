{{-- ═══════════ [HOME-09A] THỐNG KÊ HỆ THỐNG ═══════════
     SỬA 12/10 (khách: "dựa vào source mới update lại UI trang chủ, đổ dữ liệu từ database ra") — dựng theo
     education-main/src/components/HomeSystemStats.jsx. Bản mẫu React dùng số minh hoạ cứng (PREVIEW_STATS, ghi
     chú "thay bằng số liệu tổng hợp từ máy chủ khi có") — ở đây lấy từ cơ sở dữ liệu thật:
     App\Services\Public\HomeService::systemStats() (đếm thành viên theo vai trò, đề luyện tập + bài tập đã phát
     hành, lượt làm bài 7 ngày gần nhất; lưu nhớ tạm 10 phút).

     CSS viết riêng trong khối <style> bên dưới (máy chủ không chạy Vite nên không thể trông chờ class Tailwind
     mới được biên dịch) — chỉ phạm vi .oi-sysstats, không ảnh hưởng khối khác. --}}
@php
    $ss = $systemStats ?? null;
    $ssFmt = fn ($n) => number_format((int) $n, 0, ',', '.');
@endphp

@if (is_array($ss))
    @php
        $ssDaily = $ss['dailyAttempts'];
        $ssMax = max(1, max($ssDaily));
        $ssToday = (int) end($ssDaily);
        $ssRoles = [
            ['Học sinh', $ss['students'], 'graduation-cap', 'oi-ss-blue'],
            ['Phụ huynh', $ss['parents'], 'heart', 'oi-ss-rose'],
            ['Giáo viên', $ss['teachers'], 'users', 'oi-ss-green'],
        ];
    @endphp
    <section data-section="HOME-09A-SYSTEM-STATS" aria-labelledby="home-system-stats-title" class="oi-sysstats">
        <div class="oi-ss-head">
            <span class="oi-ss-icon"><x-lucide name="bar-chart-2" class="h-4 w-4" /></span>
            <h3 id="home-system-stats-title" class="type-card-title oi-ss-title">Thống kê hệ thống</h3>
            <span class="oi-ss-label" title="Số liệu tổng hợp từ hệ thống">Dữ liệu thật</span>
        </div>

        <div class="oi-ss-members">
            <p class="oi-ss-total">{{ $ssFmt($ss['members']) }}</p>
            <p class="oi-ss-label">thành viên</p>
        </div>

        <dl class="oi-ss-roles">
            @foreach ($ssRoles as [$ssLabel, $ssCount, $ssIcon, $ssTone])
                <div>
                    <dt class="oi-ss-label"><x-lucide :name="$ssIcon" class="h-3 w-3 {{ $ssTone }}" /><span>{{ $ssLabel }}</span></dt>
                    <dd class="oi-ss-value">{{ $ssFmt($ssCount) }}</dd>
                </div>
            @endforeach
        </dl>

        <dl class="oi-ss-content">
            <div>
                <dt class="oi-ss-label"><x-lucide name="file-text" class="h-3 w-3 oi-ss-muted" />Đề thi</dt>
                <dd class="oi-ss-value">{{ $ssFmt($ss['exams']) }}</dd>
            </div>
            <div>
                <dt class="oi-ss-label"><x-lucide name="book-open" class="h-3 w-3 oi-ss-muted" />Bài tập</dt>
                <dd class="oi-ss-value">{{ $ssFmt($ss['exercises']) }}</dd>
            </div>
        </dl>

        <div class="oi-ss-today">
            <div>
                <p class="oi-ss-label">Lượt làm bài hôm nay</p>
                <p class="oi-ss-today-n">{{ $ssFmt($ssToday) }}</p>
            </div>
            <figure class="oi-ss-chart" aria-label="Lượt làm bài trong 7 ngày gần nhất">
                <div class="oi-ss-bars" aria-hidden="true">
                    @foreach ($ssDaily as $ssIndex => $ssCount)
                        @php $ssAgo = count($ssDaily) - 1 - $ssIndex; @endphp
                        <span class="{{ $ssAgo === 0 ? 'is-today' : '' }}"
                              title="{{ $ssAgo === 0 ? 'Hôm nay' : $ssAgo.' ngày trước' }}: {{ $ssFmt($ssCount) }} lượt"
                              style="height: {{ $ssCount > 0 ? max(8, round($ssCount / $ssMax * 100)) : 4 }}%"></span>
                    @endforeach
                </div>
                <figcaption class="oi-ss-label" aria-hidden="true">7 ngày qua</figcaption>
                <ul class="sr-only">
                    @foreach ($ssDaily as $ssIndex => $ssCount)
                        @php $ssAgo = count($ssDaily) - 1 - $ssIndex; @endphp
                        <li>{{ $ssAgo === 0 ? 'Hôm nay' : $ssAgo.' ngày trước' }}: {{ $ssFmt($ssCount) }} lượt</li>
                    @endforeach
                </ul>
            </figure>
        </div>
    </section>

    <style>
        .oi-sysstats { overflow: hidden; border: 1px solid #DDEAF0; border-radius: 1.5rem; background: #fff; padding: .875rem; box-shadow: 0 4px 16px rgba(52, 91, 120, .045); }
        @media (min-width: 1280px) { .oi-sysstats { padding-left: 1rem; padding-right: 1rem; } }
        .oi-sysstats .oi-ss-head { display: flex; align-items: center; gap: .5rem; }
        .oi-sysstats .oi-ss-icon { display: flex; height: 2rem; width: 2rem; flex: none; align-items: center; justify-content: center; border: 1px solid #CFE6EC; border-radius: .75rem; background: #EAF5F8; color: #2D7FA3; }
        .oi-sysstats .oi-ss-title { min-width: 0; flex: 1 1 0%; margin: 0; }
        .oi-sysstats .oi-ss-label { display: inline-flex; align-items: center; justify-content: center; gap: .25rem; margin: 0; white-space: nowrap; font-size: 11px; font-weight: 500; line-height: 1rem; color: #71869A; }
        .oi-sysstats .oi-ss-head .oi-ss-label { flex: none; }
        .oi-sysstats .oi-ss-members { display: flex; align-items: baseline; gap: .375rem; margin-top: .5rem; }
        .oi-sysstats .oi-ss-total { margin: 0; font-size: 1rem; font-weight: 600; line-height: 1.25rem; color: #245B7A; font-variant-numeric: tabular-nums; }
        .oi-sysstats .oi-ss-value { margin: .125rem 0 0; font-size: 13px; font-weight: 600; line-height: 1.25rem; color: #3D647A; font-variant-numeric: tabular-nums; }
        .oi-sysstats .oi-ss-roles { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); margin: .5rem 0 0; }
        .oi-sysstats .oi-ss-roles > div { min-width: 0; text-align: center; }
        .oi-sysstats .oi-ss-roles > div + div { border-left: 1px solid #EDF2F4; }
        .oi-sysstats .oi-ss-roles dt, .oi-sysstats .oi-ss-content dt { display: flex; }
        .oi-sysstats .oi-ss-roles dt { justify-content: center; }
        .oi-sysstats .oi-ss-roles dd, .oi-sysstats .oi-ss-content dd { margin-left: 0; }
        .oi-sysstats .oi-ss-content { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; margin: .625rem 0 0; padding-top: .5rem; border-top: 1px solid #EDF2F4; }
        .oi-sysstats .oi-ss-content > div { display: flex; min-width: 0; align-items: center; justify-content: space-between; gap: .25rem; }
        .oi-sysstats .oi-ss-content .oi-ss-value { margin: 0; }
        .oi-sysstats .oi-ss-blue { color: #337CA5; }
        .oi-sysstats .oi-ss-rose { color: #9B708D; }
        .oi-sysstats .oi-ss-green { color: #378A77; }
        .oi-sysstats .oi-ss-muted { color: #7293A6; }
        .oi-sysstats .oi-ss-today { display: flex; align-items: center; justify-content: space-between; gap: .75rem; margin-top: .625rem; padding: .5rem .625rem; border-radius: .75rem; background: #F2F8F6; }
        .oi-sysstats .oi-ss-today-n { margin: .125rem 0 0; font-size: 1rem; font-weight: 600; line-height: 1.25rem; color: #387D68; font-variant-numeric: tabular-nums; }
        .oi-sysstats .oi-ss-chart { width: 68px; flex: none; margin: 0; }
        .oi-sysstats .oi-ss-bars { display: flex; height: 1.5rem; align-items: flex-end; gap: .25rem; }
        .oi-sysstats .oi-ss-bars > span { min-width: 0; flex: 1 1 0%; border-radius: 2px 2px 0 0; background: #C3DDD2; }
        .oi-sysstats .oi-ss-bars > span.is-today { background: #6DA591; }
        .oi-sysstats .oi-ss-chart figcaption { display: block; margin-top: .25rem; text-align: right; }
        html.theme-dark .oi-sysstats { background: #12283A; border-color: #25445C; box-shadow: none; }
        html.theme-dark .oi-sysstats .oi-ss-icon { background: #173A52; border-color: #25445C; color: #7CC4DD; }
        html.theme-dark .oi-sysstats .oi-ss-label { color: #9FB6C6; }
        html.theme-dark .oi-sysstats .oi-ss-total { color: #BFE3F2; }
        html.theme-dark .oi-sysstats .oi-ss-value { color: #D6E6EF; }
        html.theme-dark .oi-sysstats .oi-ss-roles > div + div, html.theme-dark .oi-sysstats .oi-ss-content { border-color: #25445C; }
        html.theme-dark .oi-sysstats .oi-ss-today { background: #16382F; }
        html.theme-dark .oi-sysstats .oi-ss-today-n { color: #8ED3B9; }
        html.theme-dark .oi-sysstats .oi-ss-bars > span { background: #2E5B4C; }
        html.theme-dark .oi-sysstats .oi-ss-bars > span.is-today { background: #6DA591; }
    </style>
@endif
