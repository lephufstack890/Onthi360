{{--
    SỬA 27/10 (khách: "thêm tab Bài của tài liệu: đổ bài tập của sách, chuyên đề, bộ đề; thiết kế cả phần lọc để sau này dễ tìm
    kiếm và dễ sửa nhất") — tab admin.content.index?tab=material_exercises. Dữ liệu: App\Services\Admin\MaterialExerciseIndexService
    (biến me*). Dùng lại đúng lớp giao diện của tab Câu hỏi (partials/admin-content-ui) cho đồng bộ.
--}}
@php
    $f = $meFilters;
    $meLink = function (array $override = []) use ($f) {
        $query = array_merge([
            'tab' => 'material_exercises',
            'loai' => $f['loai'], 'tai_lieu' => $f['tai_lieu'], 'phan' => $f['phan'],
            'dang' => $f['dang'], 'trang_thai' => $f['trang_thai'], 'q' => $f['q'],
        ], $override);

        return route('admin.content.index', array_filter($query, fn ($v) => $v !== null && $v !== ''));
    };
    $meActive = collect($f)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
    $pg = $mePagination;
    $pgFrom = $pg['total'] > 0 ? ($pg['page'] - 1) * $pg['perPage'] + 1 : 0;
    $pgTo = $pg['total'] > 0 ? $pgFrom + count($meRows) - 1 : 0;
    $pgItems = [];
    $pgPrev = 0;
    for ($i = 1; $i <= $pg['lastPage']; $i++) {
        if ($i === 1 || $i === $pg['lastPage'] || abs($i - $pg['page']) <= 1) {
            if ($pgPrev && $i - $pgPrev > 1) {
                $pgItems[] = '…';
            }
            $pgItems[] = $i;
            $pgPrev = $i;
        }
    }
    $typeIcon = ['book' => 'book-open', 'topic' => 'layers', 'exam' => 'file-text', 'course' => 'graduation-cap'];
@endphp

<style>
    .mex-doc{display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:700;color:#123b68;text-decoration:none}
    .mex-doc:hover{color:#2563eb;text-decoration:underline}
    .mex-doc svg{width:14px;height:14px;flex:none;color:#3b82f6}
    .mex-kind{display:inline-block;margin-bottom:4px;padding:1px 8px;border-radius:999px;background:#eef5ff;color:#2563eb;font-size:11px;font-weight:700}
    .mex-kind--topic{background:#effaf3;color:#15803d}
    .mex-kind--exam{background:#fff6e8;color:#b45309}
    .mex-part{margin-top:3px;font-size:12px;color:#587089}
    .mex-part a{color:#587089}
    .mex-part a:hover{color:#2563eb}
    .mex-note{margin:0;padding:0 20px 12px;font-size:12px;color:#6b7f93}
</style>

@if (! empty($meError))
    <p role="alert" style="margin:16px 0 0;padding:12px 16px;border:1px solid #f3cccc;border-radius:12px;background:#fff3f2;color:#a33b3b;font-size:13px">{{ $meError }}</p>
@endif
<section class="acx-panel" style="margin-top:16px" aria-label="Bộ lọc bài của tài liệu">
    {{-- Loại tài liệu: chip có số đếm, bấm là lọc (đổi loại thì bỏ chọn tài liệu / chương). --}}
    <div class="akx-pad">
        <div class="akx-chips">
            <a href="{{ $meLink(['loai' => null, 'tai_lieu' => null, 'phan' => null]) }}" class="akx-chip akx-chip--type {{ $f['loai'] === null ? 'is-on' : '' }}">Tất cả tài liệu <small>({{ $meTotalAll }} bài)</small></a>
            {{-- SỬA 27/10 — luôn đủ 3 loại Sách · Chuyên đề · Bộ đề (số trong ngoặc là số bài tập). --}}
            @foreach ($meTypeLabels as $value => $label)
                <a href="{{ $meLink(['loai' => $value, 'tai_lieu' => null, 'phan' => null]) }}" class="akx-chip akx-chip--type {{ $f['loai'] === $value ? 'is-on' : '' }}">{{ $label }} <small>({{ $meTypeCounts[$value] ?? 0 }} bài)</small></a>
            @endforeach
        </div>
    </div>

    <div class="akx-pad">
        <form method="GET" action="{{ route('admin.content.index') }}" class="akx-form" id="mex-filter">
            <input type="hidden" name="tab" value="material_exercises">
            <input type="hidden" name="loai" value="{{ $f['loai'] }}">
            <div class="min-w-[240px]" style="flex:1 1 260px">
                <label class="akx-lbl" for="mex-doc">Tài liệu</label>
                {{-- Chọn tài liệu là lọc ngay; đổi tài liệu thì bỏ chọn chương/phần/đề cũ. --}}
                <x-ws.select id="mex-doc" name="tai_lieu" onchange="if (this.form.phan) this.form.phan.value = ''; this.form.submit()">
                    <option value="">{{ $f['loai'] !== null ? 'Tất cả '.\Illuminate\Support\Str::lower($meTypeLabels[$f['loai']]) : 'Tất cả tài liệu' }} ({{ count($meProductOptions) }})</option>
                    @foreach ($meProductOptions as $o)
                        <option value="{{ $o['id'] }}" @selected($f['tai_lieu'] === $o['id'])>{{ $f['loai'] === null ? $o['typeLabel'].' · ' : '' }}{{ $o['title'] }} ({{ $o['count'] }} bài)</option>
                    @endforeach
                </x-ws.select>
            </div>
            @if ($f['tai_lieu'] !== null)
                <div class="min-w-[200px]" style="flex:1 1 220px">
                    <label class="akx-lbl" for="mex-part">{{ $mePartWord }}</label>
                    <x-ws.select id="mex-part" name="phan" onchange="this.form.submit()">
                        <option value="">Tất cả {{ \Illuminate\Support\Str::lower($mePartWord) }}</option>
                        @foreach ($mePartOptions as $o)
                            <option value="{{ $o['key'] }}" @selected($f['phan'] === $o['key'])>{{ $o['title'] }} ({{ $o['count'] }} bài)</option>
                        @endforeach
                    </x-ws.select>
                </div>
            @endif
            <div class="min-w-[150px]">
                <label class="akx-lbl" for="mex-type">Dạng bài</label>
                <x-ws.select id="mex-type" name="dang">
                    <option value="">Tất cả dạng</option>
                    @foreach ($meQuestionTypes as $value => $label)
                        <option value="{{ $value }}" @selected($f['dang'] === $value)>{{ $label }}</option>
                    @endforeach
                </x-ws.select>
            </div>
            <div class="min-w-[140px]">
                <label class="akx-lbl" for="mex-status">Trạng thái</label>
                <x-ws.select id="mex-status" name="trang_thai">
                    <option value="">Tất cả</option>
                    @foreach ($meStatuses as $value => $label)
                        <option value="{{ $value }}" @selected($f['trang_thai'] === $value)>{{ $label }}</option>
                    @endforeach
                </x-ws.select>
            </div>
            <div class="akx-grow">
                <label class="akx-lbl" for="mex-q">Tìm theo tên bài, mã bài hoặc tên tài liệu</label>
                <div class="acx-search">
                    <x-lucide name="search" />
                    <input id="mex-q" name="q" type="search" value="{{ $f['q'] }}" maxlength="100" placeholder="Ví dụ: tổng hai số, APLUSB, Python tập 1…" class="admin-input">
                </div>
            </div>
            <button type="submit" class="acx-btn acx-btn--primary">Lọc</button>
            @if ($meActive)
                <a href="{{ route('admin.content.index', ['tab' => 'material_exercises']) }}" class="acx-btn">Xoá lọc</a>
            @endif
        </form>
    </div>
</section>

<section class="acx-panel" style="margin-top:16px" aria-label="Danh sách bài của tài liệu">
    <div class="akx-sub">
        <span><b>{{ $pg['total'] }}</b> kết quả · Bài của tài liệu</span>
        <span>Sắp xếp: loại tài liệu → tài liệu → {{ $f['tai_lieu'] !== null ? \Illuminate\Support\Str::lower($mePartWord) : 'chương / phần / đề' }}</span>
    </div>
    <p class="mex-note">Bài tập lấy từ chính tài liệu (bài nhập ZIP / tạo ở trang quản lý tài liệu) — cùng danh sách với cột "Bài tập" khi đọc tài liệu. Bấm Sửa để sửa bài; thêm bài mới ở trang quản lý từng tài liệu.</p>

    <div class="acx-scroll">
        <table class="acx-table apx-up akx-table" style="min-width:900px">
            <caption class="acx-sr">Bài tập của sách, chuyên đề, bộ đề</caption>
            <thead>
                <tr>
                    <th scope="col">Bài tập</th>
                    <th scope="col">Tài liệu / vị trí</th>
                    <th scope="col">Dạng · Độ khó</th>
                    <th scope="col">Trạng thái</th>
                    <th scope="col">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($meRows as $r)
                    <tr>
                        <td>
                            <a href="{{ $r['editHref'] }}" class="akx-title">{{ $r['title'] !== '' ? $r['title'] : 'Bài #'.$r['id'] }}</a>
                            @if ($r['code'] !== '')
                                <div class="akx-meta"><code>{{ $r['code'] }}</code></div>
                            @endif
                        </td>
                        <td>
                            <span class="mex-kind mex-kind--{{ $r['productType'] }}">{{ $r['productTypeLabel'] }}</span><br>
                            <a href="{{ $meLink(['loai' => $r['productType'], 'tai_lieu' => $r['productId'], 'phan' => null, 'page' => null]) }}" class="mex-doc" title="Lọc bài của tài liệu này">
                                <x-lucide :name="$typeIcon[$r['productType']] ?? 'book-open'" />{{ $r['productTitle'] }}
                            </a>
                            <div class="mex-part">
                                {{ $r['partWord'] }}:
                                @if ($r['partTitle'] !== null)
                                    <a href="{{ $meLink(['loai' => $r['productType'], 'tai_lieu' => $r['productId'], 'phan' => $r['partKey'], 'page' => null]) }}" title="Lọc bài của {{ \Illuminate\Support\Str::lower($r['partWord']) }} này">{{ $r['partTitle'] }}</a>
                                @else
                                    <span class="akx-mute">chưa gắn</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            <div class="akx-k">{{ $r['typeLabel'] }}</div>
                            <div class="akx-k2">{{ $r['difficulty'] }}</div>
                        </td>
                        <td><span class="acx-badge {{ $r['statusClass'] }}">{{ $r['statusLabel'] }}</span></td>
                        <td>
                            <div class="akx-act">
                                <div class="akx-act__links">
                                    <a href="{{ $r['editHref'] }}" class="akx-lnk">Sửa</a>
                                    <a href="{{ $r['viewHref'] }}" class="akx-lnk akx-lnk--mute">Xem</a>
                                    @if ($r['partHref'])
                                        <a href="{{ $r['partHref'] }}" class="akx-lnk akx-lnk--mute" title="Mở đề chứa câu này">Mở đề</a>
                                    @endif
                                    <a href="{{ $r['productHref'] }}" class="akx-lnk akx-lnk--mute" title="Mở trang quản lý tài liệu">Mở tài liệu</a>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if (count($meRows) === 0)
            <div class="akx-empty">
                <strong>{{ $meActive ? 'Không có bài nào khớp bộ lọc' : 'Chưa có bài tập nào trong sách / chuyên đề / bộ đề.' }}</strong>
                {{ $meActive ? 'Thử bỏ bớt điều kiện hoặc bấm "Xoá lọc".' : 'Thêm bài ở trang quản lý từng tài liệu.' }}
            </div>
        @endif
    </div>

    <nav aria-label="Phân trang bài của tài liệu" class="akx-pg">
        <span>{{ $pgFrom }}–{{ $pgTo }} / {{ $pg['total'] }} bài{{ $pg['lastPage'] > 1 ? ' · Trang '.$pg['page'].'/'.$pg['lastPage'] : '' }}</span>
        <div class="akx-pg__btns">
            @if ($pg['page'] > 1)
                <a href="{{ $meLink(['page' => $pg['page'] > 2 ? $pg['page'] - 1 : null]) }}" aria-label="Trang trước" class="akx-pgb"><x-lucide name="chevron-left" /></a>
            @else
                <span aria-hidden="true" class="akx-pgb is-off"><x-lucide name="chevron-left" /></span>
            @endif
            @foreach ($pgItems as $item)
                @if ($item === '…')
                    <span class="akx-dots">…</span>
                @elseif ($item === $pg['page'])
                    <span aria-current="page" class="akx-pgb is-on">{{ $item }}</span>
                @else
                    <a href="{{ $meLink(['page' => $item > 1 ? $item : null]) }}" aria-label="Trang {{ $item }}" class="akx-pgb">{{ $item }}</a>
                @endif
            @endforeach
            @if ($pg['page'] < $pg['lastPage'])
                <a href="{{ $meLink(['page' => $pg['page'] + 1]) }}" aria-label="Trang sau" class="akx-pgb"><x-lucide name="chevron-right" /></a>
            @else
                <span aria-hidden="true" class="akx-pgb is-off"><x-lucide name="chevron-right" /></span>
            @endif
        </div>
    </nav>
</section>
