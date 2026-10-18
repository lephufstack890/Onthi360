{{-- SỬA 18/10 — MODULE BỘ ĐỀ: khối "Đề trong bộ đề" ở trang chi tiết tài liệu loại Bộ đề (admin/products/show).
     Bộ đề TRỎ tới đề luyện tập có sẵn (không nhập lại): tự gom theo bộ lọc + ghim / bỏ / sắp xếp tay.
     Dữ liệu: ExamBundleService::adminData(); thao tác: Admin\ExamBundleController (routes admin.products.bundle.*).
     CSS tự chứa (tiền tố ebd-) vì máy chủ không build lại Tailwind. Tham số: $product. --}}
@php
    $bundleSvc = app(\App\Services\ExamBundle\ExamBundleService::class);
    $bd = $bundleSvc->adminData($product);
    $bf = $bd['filters'];
    $bdCats = \App\Support\ExamCategory::CATEGORIES;
    $bdLevels = \App\Support\ExamLevel::LEVELS;
    $bdProvinceGroups = \App\Support\ProvinceCatalog::groups();
@endphp

@if (session('status') === 'bundle-saved' && session('statusMessage'))
    @include('partials.toast-flash', ['type' => 'success', 'message' => session('statusMessage')])
@endif

<style>
    .ebd-sub { margin: -4px 0 14px; font-size: 12.5px; line-height: 1.65; color: #64748b; }
    .ebd-box { margin-bottom: 14px; padding: 14px; border: 1px solid #dbe7f3; border-radius: 14px; background: #f8fbff; }
    .ebd-box h3 { display: flex; align-items: center; gap: 6px; margin: 0 0 10px; font-size: 13.5px; font-weight: 800; color: #1e3a5f; }
    .ebd-box h3 svg { width: 16px; height: 16px; color: #2563eb; }
    .ebd-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
    .ebd-lbl { display: block; margin-bottom: 6px; font-size: 12px; font-weight: 700; color: #475569; }
    .ebd-chips { display: flex; flex-wrap: wrap; gap: 6px; }
    .ebd-chip { display: inline-flex; align-items: center; gap: 6px; padding: 5px 10px; border: 1px solid #d5e2ef; border-radius: 999px; background: #fff; font-size: 12.5px; color: #334155; cursor: pointer; }
    .ebd-chip input { margin: 0; }
    .ebd-select { width: 100%; min-height: 120px; border: 1px solid #bdcbdc; border-radius: 10px; background: #fff; padding: 6px; font-size: 13px; color: #273f58; }
    .ebd-years { display: flex; align-items: center; gap: 8px; }
    .ebd-years input { box-sizing: border-box; width: 104px; height: 38px; min-height: 0; border: 1px solid #bdcbdc; border-radius: 10px; padding: 6px 10px; font-size: 13px; }
    .ebd-prov-search { box-sizing: border-box; width: 100%; height: 36px; margin-bottom: 6px; border: 1px solid #bdcbdc; border-radius: 10px; padding: 6px 10px; font-size: 13px; }
    .ebd-prov { max-height: 190px; overflow-y: auto; padding: 4px 8px; border: 1px solid #dbe7f3; border-radius: 10px; background: #fff; }
    .ebd-prov p { margin: 6px 0 2px; font-size: 10.5px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: #94a3b8; }
    .ebd-prov label { display: flex; align-items: center; gap: 6px; padding: 3px 0; font-size: 12.5px; color: #334155; cursor: pointer; }
    .ebd-summary { margin: 10px 0 0; font-size: 12.5px; color: #1d4ed8; font-weight: 600; }
    .ebd-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 12px; }
    .ebd-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-height: 36px; padding: 0 14px; border: 1px solid #d6e4f0; border-radius: 10px; background: #fff; font-size: 12.5px; font-weight: 700; color: #35506a; cursor: pointer; text-decoration: none; }
    .ebd-btn svg { width: 14px; height: 14px; }
    .ebd-btn:hover { background: #f5f9ff; }
    .ebd-btn--primary { border-color: #2563eb; background: #2563eb; color: #fff; }
    .ebd-btn--primary:hover { background: #1d4ed8; }
    .ebd-btn--warn { border-color: #f5c27a; background: #fff8eb; color: #92400e; }
    .ebd-btn--sm { min-height: 30px; padding: 0 10px; font-size: 12px; }
    .ebd-btn:disabled { opacity: .45; cursor: not-allowed; }
    .ebd-lock { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 12px; border-radius: 12px; font-size: 12.5px; }
    .ebd-lock.is-on { border: 1px solid #f5c27a; background: #fff8eb; color: #92400e; }
    .ebd-lock.is-off { border: 1px solid #c9e7d8; background: #effaf5; color: #1f6b4f; }
    .ebd-pick-search { width: 100%; min-height: 38px; margin-bottom: 8px; border: 1px solid #bdcbdc; border-radius: 10px; padding: 6px 12px; font-size: 13px; }
    .ebd-pick { max-height: 260px; overflow-y: auto; border: 1px solid #dbe7f3; border-radius: 10px; background: #fff; }
    .ebd-pick label { display: flex; align-items: flex-start; gap: 8px; padding: 8px 10px; border-bottom: 1px solid #eef3f8; font-size: 13px; color: #334155; cursor: pointer; }
    .ebd-pick label:last-child { border-bottom: 0; }
    .ebd-pick small { display: block; color: #7a8ea3; font-size: 11.5px; }
    .ebd-list { margin: 0; padding: 0; list-style: none; border: 1px solid #dbe7f3; border-radius: 12px; overflow: hidden; }
    .ebd-row { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-bottom: 1px solid #eef3f8; background: #fff; }
    .ebd-row:nth-child(even) { background: #fbfdff; }
    .ebd-row:last-child { border-bottom: 0; }
    .ebd-no { flex-shrink: 0; width: 26px; height: 26px; display: grid; place-items: center; border-radius: 8px; background: #eef4fb; font-size: 12px; font-weight: 800; color: #3b5b7d; }
    .ebd-main { flex: 1; min-width: 0; }
    .ebd-title { margin: 0; font-size: 13.5px; font-weight: 700; color: #1e3a5f; overflow-wrap: anywhere; }
    .ebd-meta { margin: 2px 0 0; font-size: 11.5px; color: #7a8ea3; }
    .ebd-tags { display: inline-flex; flex-wrap: wrap; gap: 4px; margin-left: 6px; vertical-align: middle; }
    .ebd-tag { display: inline-block; padding: 1px 7px; border-radius: 6px; font-size: 10.5px; font-weight: 800; }
    .ebd-tag--pin { background: #ede9fe; color: #5b21b6; }
    .ebd-tag--auto { background: #e0f2fe; color: #0369a1; }
    .ebd-tag--only { background: #fef3c7; color: #92400e; }
    .ebd-ops { display: flex; flex-shrink: 0; align-items: center; gap: 6px; }
    .ebd-ops form { margin: 0; }
    .ebd-empty { padding: 18px; border: 1px dashed #c9dbec; border-radius: 12px; text-align: center; font-size: 13px; color: #64748b; }
    @media (max-width: 720px) { .ebd-row { flex-wrap: wrap; } .ebd-ops { width: 100%; justify-content: flex-end; } }
</style>

<section class="acx-card acx-card--white" id="exam-bundle" style="scroll-margin-top:80px">
    <div class="apx-cardtop">
        <h2><x-lucide name="clipboard-list" /> Đề trong bộ đề</h2>
        <span class="apx-count">{{ count($bd['items']) }} đề · {{ $bd['totalQuestions'] }} câu</span>
    </div>

    @if (! $bd['ready'])
        <p class="acx-note">Chưa chạy migration bộ đề — chạy <code>php artisan migrate</code> trên máy chủ để dùng tính năng này.</p>
    @else
        <p class="ebd-sub">
            Bộ đề <b>không nhập lại đề</b> — nó lấy thẳng các <b>đề luyện tập đã phát hành</b>. Đặt bộ lọc để hệ thống tự gom (đề mới
            khớp bộ lọc sẽ tự vào), hoặc thêm / bỏ từng đề bằng tay. Đề công khai vẫn làm miễn phí ở Luyện tập; đề
            <b>"Chỉ trong bộ đề"</b> thì chỉ người mua bộ đề làm được (bật ở trang chi tiết đề).
            <a href="{{ route('bundles.show', $product->id) }}" target="_blank" rel="noopener" style="font-weight:700;color:#1d4ed8">Xem trang bộ đề ↗</a>
        </p>

        {{-- ── 1. Bộ lọc tự gom ── --}}
        <form method="POST" action="{{ route('admin.products.bundle.filters', $product->id) }}" class="ebd-box">
            @csrf
            <h3><x-lucide name="filter" /> Bộ lọc tự gom đề</h3>
            <div class="ebd-grid">
                <div>
                    <span class="ebd-lbl">Cuộc thi</span>
                    <div class="ebd-chips">
                        @foreach ($bdCats as $code => $label)
                            <label class="ebd-chip"><input type="checkbox" name="categories[]" value="{{ $code }}" @checked(in_array($code, $bf->categories, true))> {{ $label }}</label>
                        @endforeach
                    </div>
                    <span class="ebd-lbl" style="margin-top:10px">Khối</span>
                    <div class="ebd-chips">
                        @foreach ($bdLevels as $code => $label)
                            <label class="ebd-chip"><input type="checkbox" name="levels[]" value="{{ $code }}" @checked(in_array($code, $bf->levels, true))> {{ $label }}</label>
                        @endforeach
                    </div>
                    <span class="ebd-lbl" style="margin-top:10px">Năm học</span>
                    <div class="ebd-years">
                        <input type="number" name="year_from" min="1990" max="2100" placeholder="Từ năm" value="{{ $bf->yearFrom }}">
                        <span>–</span>
                        <input type="number" name="year_to" min="1990" max="2100" placeholder="Đến năm" value="{{ $bf->yearTo }}">
                    </div>
                </div>
                <div>
                    <span class="ebd-lbl">Tỉnh/thành <span style="font-weight:500;color:#94a3b8">(tick nhiều — bỏ trống = mọi tỉnh)</span></span>
                    <input type="search" class="ebd-prov-search" placeholder="Tìm tỉnh/thành…" oninput="ebdFilterProv(this)" aria-label="Tìm tỉnh/thành">
                    <div class="ebd-prov" data-ebd-prov>
                        @foreach ($bdProvinceGroups as $groupLabel => $options)
                            <p>{{ $groupLabel }}</p>
                            @foreach ($options as $code => $label)
                                <label data-text="{{ mb_strtolower($label) }}"><input type="checkbox" name="provinces[]" value="{{ $code }}" @checked(in_array($code, $bf->provinces, true))> {{ $label }}</label>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            </div>
            <p class="ebd-summary">Đang lọc: {{ $bf->summary() }}</p>
            <div class="ebd-actions">
                <button type="submit" class="ebd-btn ebd-btn--primary"><x-lucide name="save" /> Lưu bộ lọc</button>
                <span style="font-size:12px;color:#64748b">Để trống tất cả = không tự gom (chỉ dùng đề thêm tay).</span>
            </div>
        </form>

        {{-- ── 2. Khoá danh sách ── --}}
        <div class="ebd-lock {{ $bd['locked'] ? 'is-on' : 'is-off' }}" style="margin-bottom:14px">
            <span>
                @if ($bd['locked'])
                    <b>Đang khoá danh sách</b> — bộ đề giữ đúng các đề hiện tại, đề mới không tự vào nữa.
                @else
                    <b>Đang tự cập nhật</b> — đề luyện tập mới phát hành khớp bộ lọc sẽ tự vào bộ đề.
                @endif
            </span>
            <form method="POST" action="{{ route('admin.products.bundle.lock', $product->id) }}" style="margin:0">
                @csrf
                <input type="hidden" name="locked" value="{{ $bd['locked'] ? 0 : 1 }}">
                <button type="submit" class="ebd-btn ebd-btn--sm {{ $bd['locked'] ? '' : 'ebd-btn--warn' }}">
                    <x-lucide name="{{ $bd['locked'] ? 'refresh-cw' : 'lock' }}" /> {{ $bd['locked'] ? 'Mở khoá (tự cập nhật lại)' : 'Khoá danh sách' }}
                </button>
            </form>
        </div>

        {{-- ── 3. Thêm đề có sẵn ── --}}
        <details class="ebd-box" @if (count($bd['items']) === 0) open @endif>
            <summary style="cursor:pointer;font-size:13.5px;font-weight:800;color:#1e3a5f">+ Thêm đề có sẵn ({{ count($bd['candidates']) }} đề luyện tập chưa có trong bộ)</summary>
            @if (count($bd['candidates']) === 0)
                <p class="acx-note" style="margin-top:10px">Mọi đề luyện tập đã phát hành đều đã có trong bộ đề.</p>
            @else
                <form method="POST" action="{{ route('admin.products.bundle.add', $product->id) }}" style="margin-top:10px">
                    @csrf
                    <input type="search" class="ebd-pick-search" placeholder="Tìm tên đề, mã đề, tỉnh, năm…" oninput="ebdFilterPick(this)" aria-label="Tìm đề">
                    <div class="ebd-pick" data-ebd-pick>
                        @foreach ($bd['candidates'] as $c)
                            <label data-text="{{ mb_strtolower($c['title'].' '.$c['code'].' '.$c['province'].' '.$c['year'].' '.$c['category']) }}">
                                <input type="checkbox" name="assessment_ids[]" value="{{ $c['id'] }}">
                                <span>{{ $c['title'] }}
                                    <small>{{ collect([$c['code'], $c['category'], $c['level'], $c['province'], $c['year']])->filter()->implode(' · ') }} · {{ $c['questions'] }} câu{{ $c['bundleOnly'] ? ' · Chỉ trong bộ đề' : '' }}</small>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <div class="ebd-actions"><button type="submit" class="ebd-btn ebd-btn--primary"><x-lucide name="plus" /> Thêm đề đã chọn</button></div>
                </form>
            @endif
        </details>

        {{-- ── 4. Danh sách đề trong bộ ── --}}
        @if (count($bd['items']) === 0)
            <div class="ebd-empty">Bộ đề chưa có đề nào — đặt bộ lọc hoặc thêm đề có sẵn ở trên.</div>
        @else
            <ol class="ebd-list">
                @foreach ($bd['items'] as $i => $it)
                    <li class="ebd-row">
                        <span class="ebd-no">{{ $i + 1 }}</span>
                        <div class="ebd-main">
                            <p class="ebd-title">{{ $it['title'] }}
                                <span class="ebd-tags">
                                    @if ($it['pinned'])<span class="ebd-tag ebd-tag--pin" title="Thêm tay">Thêm tay</span>@endif
                                    @if ($it['byFilter'])<span class="ebd-tag ebd-tag--auto" title="Khớp bộ lọc">Theo bộ lọc</span>@endif
                                    @if ($it['bundleOnly'])<span class="ebd-tag ebd-tag--only" title="Không hiện ở Luyện tập">Chỉ trong bộ đề</span>@endif
                                </span>
                            </p>
                            <p class="ebd-meta">{{ collect([$it['code'], $it['category'], $it['level'], $it['province'], $it['year']])->filter()->implode(' · ') }} · {{ $it['questions'] }} câu{{ $it['duration'] ? ' · '.$it['duration'].' phút' : '' }}</p>
                        </div>
                        <div class="ebd-ops">
                            <form method="POST" action="{{ route('admin.products.bundle.move', [$product->id, $it['id']]) }}">
                                @csrf <input type="hidden" name="direction" value="up">
                                <button type="submit" class="ebd-btn ebd-btn--sm" title="Đẩy lên" aria-label="Đẩy lên" @disabled($i === 0)>↑</button>
                            </form>
                            <form method="POST" action="{{ route('admin.products.bundle.move', [$product->id, $it['id']]) }}">
                                @csrf <input type="hidden" name="direction" value="down">
                                <button type="submit" class="ebd-btn ebd-btn--sm" title="Đẩy xuống" aria-label="Đẩy xuống" @disabled($i === count($bd['items']) - 1)>↓</button>
                            </form>
                            <a href="{{ route('admin.content.show', ['content' => $it['id'], 'kind' => 'assessment']) }}" class="ebd-btn ebd-btn--sm">Xem</a>
                            <form method="POST" action="{{ route('admin.products.bundle.remove', [$product->id, $it['id']]) }}" onsubmit="return confirm('Bỏ đề này khỏi bộ đề? (Đề vẫn còn nguyên ở Luyện tập / Kho đề.)')">
                                @csrf @method('DELETE')
                                <button type="submit" class="ebd-btn ebd-btn--sm" style="color:#b91c1c">Bỏ</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ol>
            <form method="POST" action="{{ route('admin.products.bundle.resetOrder', $product->id) }}" class="ebd-actions">
                @csrf
                <button type="submit" class="ebd-btn ebd-btn--sm"><x-lucide name="rotate-ccw" /> Về thứ tự mặc định</button>
            </form>
        @endif

        {{-- ── 5. Đề đã bỏ ── --}}
        @if (count($bd['excluded']) > 0)
            <details class="ebd-box" style="margin-top:14px;background:#fff">
                <summary style="cursor:pointer;font-size:13px;font-weight:700;color:#64748b">Đề đã bỏ khỏi bộ ({{ count($bd['excluded']) }}) — bộ lọc sẽ không tự thêm lại</summary>
                <ul class="ebd-list" style="margin-top:10px">
                    @foreach ($bd['excluded'] as $ex)
                        <li class="ebd-row">
                            <div class="ebd-main"><p class="ebd-title">{{ $ex['title'] }}</p>
                                <p class="ebd-meta">{{ collect([$ex['code'], $ex['category'], $ex['province'], $ex['year']])->filter()->implode(' · ') }}</p></div>
                            <form method="POST" action="{{ route('admin.products.bundle.restore', [$product->id, $ex['id']]) }}" style="margin:0">
                                @csrf
                                <button type="submit" class="ebd-btn ebd-btn--sm">Khôi phục</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    @endif
</section>

<script>
    function ebdFilterProv(input) {
        var q = (input.value || '').trim().toLowerCase();
        var box = input.parentNode.querySelector('[data-ebd-prov]');
        if (!box) return;
        box.querySelectorAll('label').forEach(function (row) {
            row.style.display = !q || (row.getAttribute('data-text') || '').indexOf(q) !== -1 ? '' : 'none';
        });
    }
    function ebdFilterPick(input) {
        var q = (input.value || '').trim().toLowerCase();
        var box = input.parentNode.querySelector('[data-ebd-pick]');
        if (!box) return;
        box.querySelectorAll('label').forEach(function (row) {
            row.style.display = !q || (row.getAttribute('data-text') || '').indexOf(q) !== -1 ? '' : 'none';
        });
    }
</script>
