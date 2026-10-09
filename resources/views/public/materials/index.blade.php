@extends('layouts.guest')

@section('title', 'Tài liệu, Giáo trình & Bộ đề thi')
@section('meta-description', 'Kho học liệu Ôn Thi 360 — sách giáo trình, chuyên đề thuật toán và tuyển tập đề thi có bản quyền, kèm lời giải và chấm bài tự động trên web.')

@section('content')
{{-- ═══════════════ [MATERIALS] MÀN TÀI LIỆU ═══════════════
     SỬA 9/10 — DỰNG LẠI TOÀN BỘ theo bản mẫu MỚI của khách: education-main/src/components/MaterialsPage.jsx
     (+ MaterialFilters, MaterialQuality, MaterialPurchaseModal, QuickAssignButton). React state đổi sang Alpine,
     mọi nút gắn link/POST thật; không còn localStorage.

     Quyền hiển thị theo vai trò (xem MaterialAssignmentService::scopeFor):
       · Khách vãng lai  — chỉ "Kho tài liệu"; bấm Mua/Kích hoạt thì hộp chi tiết mời đăng nhập;
       · Học sinh        — Kho tài liệu · Tài liệu của tôi · Tài liệu được giao (lượt giáo viên giao cho mình);
       · Giáo viên       — Kho tài liệu · Tài liệu của tôi · Tài liệu đã giao (lượt mình giao) + nút "Giao tài liệu";
       · Admin           — Kho tài liệu · Tài liệu đã giao (tất cả) + nút "Giao tài liệu" + "Quản lý tài liệu";
       · Vai trò khác (phụ huynh...) — như khách, nhưng không có nút mua/kích hoạt riêng.

     Dữ liệu lấy từ MaterialService::indexData(). Thẻ ở Kho tài liệu / Tài liệu của tôi dựng sẵn ở máy chủ (SEO)
     rồi ẩn/hiện bằng x-show; bảng lượt giao dựng bằng Alpine từ JSON. --}}
@include('partials.practice-assign-style')
@include('partials.materials-page-style')
@php
    $scope = $scope ?? ['role' => 'guest', 'hasLibrary' => false, 'canViewAssigned' => false, 'canManage' => false];
    $role = $scope['role'];
    $isAdmin = $role === 'admin';
    $loggedIn = $role !== 'guest';
    $canBuy = $loggedIn && ! $isAdmin;           // mua / nhập mã kích hoạt
    $cards = $cards ?? [];
    $rows = $rows ?? [];
    $assignedRows = $assignedRows ?? [];
    $managedRows = $managedRows ?? [];

    // Hàng dữ liệu đưa sang Alpine để lọc / phân trang / mở hộp chi tiết ngay tại chỗ (bỏ mô tả HTML cho nhẹ).
    $jsRows = [];
    foreach ($rows as $m) {
        $jsRows[] = [
            'id' => $m['id'],
            'category' => $m['category'],
            'title' => $m['title'],
            'tag' => $m['tag'] ?? '',
            'author' => $m['author'] ?? '',
            'image' => $m['image'],
            'average' => $m['average'],
            'count' => $m['count'],
            'difficultyLevel' => $m['difficultyLevel'],
            'owned' => (bool) $m['owned'],
            'expired' => (bool) $m['expired'],
            'remainingDays' => $m['remainingDays'],
            'expiresAt' => $m['expiresAt'],
            'accessSource' => $m['accessSource'],
            'priceSoft' => $m['priceSoft'],
            'priceValue' => $m['priceValue'],
            'pricePrint' => $m['pricePrint'],
            'hasPrintOption' => (bool) $m['hasPrintOption'],
            'durationMonths' => $m['durationMonths'],
            'unitLabel' => $m['unitLabel'],
            'search' => $m['search'],
            'href' => $m['href'],
            'checkoutHref' => $m['checkoutHref'],
            'readHref' => $m['readHref'],
            'adminHref' => $isAdmin ? route('admin.products.show', $m['id']) : null,
            'inCatalog' => (bool) $m['inCatalog'],
        ];
    }

    $totalMaterials = count($cards);
    $heroSecondValue = match (true) {
        $scope['canManage'] => count($managedRows),
        $scope['canViewAssigned'] => count($assignedRows),
        default => 3,
    };
    $heroSecondLabel = match (true) {
        $scope['canManage'] => 'Lượt đã giao',
        $scope['canViewAssigned'] => 'Lượt được giao',
        default => 'Nhóm tài liệu',
    };

    $scopeTabFromUrl = in_array(request()->query('scope'), ['catalog', 'mine', 'assigned', 'managed'], true) ? request()->query('scope') : 'catalog';
    $categoryFromUrl = in_array(request()->query('category'), ['all', 'books', 'topics', 'exams'], true) ? request()->query('category') : ($activeCategory ?? 'all');

@endphp

<div class="max-w-[1780px] w-full mx-auto px-3 sm:px-5 lg:px-6 2xl:px-10 py-3 sm:py-5">
<div x-data="onthiMaterialsPage({{ Js::from([
        'rows' => $jsRows,
        'scope' => $scope,
        'loggedIn' => $loggedIn,
        'assignedRows' => $assignedRows,
        'managedRows' => $managedRows,
        'scope_tab' => $scopeTabFromUrl,
        'category' => $categoryFromUrl,
        'activateHref' => route('access.activate'),
        'loginHref' => route('login'),
        'assignUrl' => route('materials.assign'),
        'assignSearchUrl' => route('materials.assign.students'),
        'csrf' => csrf_token(),
        'accessDays' => $accessDaysOptions ?? [7, 30, 90, 365],
    ]) }})" class="mp-page">

    {{-- ══════ 1. HERO ══════ --}}
    <section class="mp-hero">
        <img src="{{ asset('assets/hero-materials.jpg') }}" alt="" class="mp-hero-img">
        <div class="mp-hero-inner">
            <div style="max-width: 42rem;">
                <span class="mp-hero-badge"><x-lucide name="shield-check" />Kho học liệu Ôn Thi 360</span>
                <h1>Tài liệu, Giáo trình & Bộ đề thi</h1>
                <p class="mp-lead">Chọn tài liệu phù hợp, theo dõi quyền sử dụng và học theo nội dung được giao.</p>
                <div class="mp-hero-chips">
                    <span>Bản mềm · Quyền đọc theo từng tài liệu</span>
                    <span>Sách in kèm bản mềm</span>
                    @if ($canBuy)
                        <a href="{{ route('access.activate') }}"><x-lucide name="key-round" />Kích hoạt mã sách / tài liệu</a>
                    @endif
                </div>
            </div>
            <div class="mp-hero-stats">
                <div><b>{{ number_format($totalMaterials) }}</b><small>Tài liệu trong kho</small></div>
                <div><b class="is-gold">{{ $heroSecondValue }}</b><small>{{ $heroSecondLabel }}</small></div>
            </div>
        </div>
    </section>

    {{-- ══════ 2. BỘ LỌC + CÁC KHÔNG GIAN THEO VAI TRÒ ══════ --}}
    <section aria-label="Bộ lọc tài liệu" class="mp-filters">
        <div class="mp-scopes" role="tablist" aria-label="Không gian tài liệu">
            <template x-for="(tab, index) in tabs" :key="tab.id">
                <button type="button" role="tab" :id="'mp-tab-' + tab.id" aria-controls="mp-panel"
                        :aria-selected="activeScope === tab.id ? 'true' : 'false'" :tabindex="activeScope === tab.id ? 0 : -1"
                        @keydown="tabKey($event, index)" @click="setScope(tab.id)"
                        class="mp-tab" :class="activeScope === tab.id ? 'is-active' : ''">
                    <span class="mp-ico" x-show="tab.icon === 'book'"><x-lucide name="book-open" /></span>
                    <span class="mp-ico" x-show="tab.icon === 'key'"><x-lucide name="key-round" /></span>
                    <span class="mp-ico" x-show="tab.icon === 'file'"><x-lucide name="file-text" /></span>
                    <span class="mp-ico" x-show="tab.icon === 'send'"><x-lucide name="send" /></span>
                    <span x-text="tab.label"></span>
                    <span class="mp-count" x-text="tab.count"></span>
                </button>
            </template>
        </div>

        <div class="mp-filters-head">
            <h2><x-lucide name="filter" />Tìm tài liệu phù hợp</h2>
            <button type="button" class="mp-text-btn" x-show="canReset" x-cloak @click="resetFilters()"><x-lucide name="rotate-ccw" />Đặt lại bộ lọc</button>
        </div>

        <div style="margin-bottom: 12px;">
            <p class="mp-label" style="margin-bottom: 8px;">Thể loại</p>
            <div class="mp-cats" style="margin-bottom: 0;" aria-label="Thể loại tài liệu">
                <template x-for="c in categories" :key="c.id">
                    <button type="button" class="mp-cat" :aria-pressed="category === c.id ? 'true' : 'false'" @click="category = c.id" x-text="c.label"></button>
                </template>
            </div>
        </div>

        <div class="mp-grid-filters">
            <label class="mp-span-2" :class="isAssignmentView ? 'mp-span-3' : 'mp-span-all'" style="min-width:0">
                <span class="mp-label" x-text="isAssignmentView ? (activeScope === 'managed' ? 'Tìm tài liệu, học sinh hoặc người giao' : 'Tìm tài liệu, người giao hoặc lời nhắn') : 'Tìm tài liệu, tác giả'"></span>
                <span class="mp-search">
                    <x-lucide name="search" />
                    <input type="search" class="mp-input" aria-label="Tìm kiếm tài liệu" x-model="query"
                           :placeholder="isAssignmentView ? (activeScope === 'managed' ? 'Tên tài liệu, học sinh, người giao, lời nhắn...' : 'Tên tài liệu, người giao, lời nhắn...') : 'Tên tài liệu, tác giả, nội dung... (có thể gõ không dấu)'">
                </span>
            </label>

            <label x-show="isAssignmentView" x-cloak style="min-width:0">
                <span class="mp-label">Trạng thái đọc</span>
                <select class="mp-input" aria-label="Trạng thái đọc" x-model="status">
                    <option value="all">Tất cả trạng thái</option>
                    <option value="todo">Chưa mở</option>
                    <option value="opened">Đã mở tài liệu</option>
                    <option value="overdue">Quá hạn đọc</option>
                </select>
            </label>

            <label style="min-width:0">
                <span class="mp-label">Độ khó</span>
                <select class="mp-input" aria-label="Độ khó" x-model="difficulty">
                    <option value="all">Tất cả độ khó</option>
                    @foreach (['Cơ bản', 'Dễ', 'Trung bình', 'Khó', 'Nâng cao'] as $dlIndex => $dlLabel)
                        <option value="{{ $dlIndex + 1 }}">{{ $dlIndex + 1 }} sao · {{ $dlLabel }}</option>
                    @endforeach
                </select>
            </label>

            <label style="min-width:0">
                <span class="mp-label">Giá bản mềm</span>
                <select class="mp-input" aria-label="Giá bản mềm" x-model="price">
                    <option value="all">Tất cả mức giá</option>
                    <option value="under100">Dưới 100.000đ</option>
                    <option value="100to200">100.000đ – 200.000đ</option>
                    <option value="over200">Trên 200.000đ</option>
                </select>
            </label>

            @if ($loggedIn)
                <label x-show="canFilterAccess" x-cloak style="min-width:0">
                    <span class="mp-label">Quyền sử dụng</span>
                    <select class="mp-input" aria-label="Quyền sử dụng" x-model="access">
                        <option value="all">Tất cả quyền sử dụng</option>
                        <option value="active">Đang có quyền đọc</option>
                        <option value="expired">Đã hết hạn sử dụng</option>
                        <option value="locked">Chưa kích hoạt</option>
                    </select>
                </label>
            @endif

            <label style="min-width:0">
                <span class="mp-label">Sắp xếp theo</span>
                <select class="mp-input" aria-label="Sắp xếp theo" x-model="sort">
                    <option value="default" x-text="isAssignmentView ? 'Mới giao nhất' : 'Theo danh mục'">Theo danh mục</option>
                    <option value="difficulty-asc">Độ khó: dễ → khó</option>
                    <option value="difficulty-desc">Độ khó: khó → dễ</option>
                    <option value="category">Theo thể loại</option>
                    <option value="rating">Đánh giá cao nhất</option>
                    <option value="price-asc">Giá: thấp → cao</option>
                    <option value="price-desc">Giá: cao → thấp</option>
                    <option value="title">Tên tài liệu: A → Z</option>
                    <option value="deadline" :hidden="!isAssignmentView" :disabled="!isAssignmentView">Hạn đọc gần nhất</option>
                </select>
            </label>
        </div>
    </section>

    {{-- ══════ 3. NỘI DUNG THEO KHÔNG GIAN ══════ --}}
    <section id="mp-panel" role="tabpanel" class="mp-panel">
        <div class="mp-panel-head">
            <div>
                <h2 x-text="panelTitle"></h2>
                <p role="status" x-text="total + ' ' + (isAssignmentView ? 'lượt giao' : 'tài liệu') + ' phù hợp' + (isAssignmentView ? ' · Hạn đọc và hạn sử dụng được theo dõi riêng.' : ' · Giá bản mềm đã gồm thời hạn sử dụng của từng tài liệu.')"></p>
            </div>
            <button type="button" class="mp-text-btn" x-show="hasFilters" x-cloak @click="resetFilters()">Xóa bộ lọc</button>
        </div>

        {{-- ── Lưới thẻ: Kho tài liệu / Tài liệu của tôi (dựng sẵn ở máy chủ) ── --}}
        <div class="mp-cards" x-show="!isAssignmentView && total > 0">
            @foreach ($cards as $item)
                {{-- DÙNG DẠNG ĐỐI TƯỢNG cho :style, KHÔNG dùng chuỗi — Alpine 3 với chuỗi sẽ ghi đè cả thuộc tính style,
                     xoá luôn display:none mà x-show vừa đặt khiến thẻ lẽ ra phải ẩn lại hiện ra (lỗi khách báo 18/9). --}}
                <article x-show="visibleIds.includes({{ $item['id'] }})" x-cloak
                         :style="{ order: visibleIds.indexOf({{ $item['id'] }}) }"
                         class="mp-card">
                    @if ($scope['canManage'])
                        <div class="mp-card-tools">
                            <button type="button" class="oi-assign-chip" aria-haspopup="dialog" aria-label="Giao tài liệu: {{ $item['title'] }}"
                                    @click.stop="openAssign({{ $item['id'] }})">
                                <x-lucide name="send" />Giao tài liệu
                            </button>
                        </div>
                    @endif

                    <button type="button" class="mp-cover" aria-label="Thông tin tài liệu: {{ $item['title'] }}" @click="openDetail({{ $item['id'] }})">
                        <img src="{{ $item['image'] }}" alt="" loading="lazy" decoding="async">
                    </button>

                    <div class="mp-card-body">
                        <div class="mp-unit"><x-lucide name="file-text" />{{ $item['unitLabel'] }}</div>
                        <h3><a href="{{ $item['href'] }}" style="color:inherit;text-decoration:none">{{ $item['title'] }}</a></h3>

                        <div class="mp-quality">
                            <div class="mp-quality-row"><b>Độ khó</b>@include('partials.practice-difficulty-stars', ['level' => $item['difficultyLevel']])</div>
                            <div class="mp-quality-row"><b>Đánh giá</b>@include('partials.practice-exam-rating', ['rating' => $item['average'], 'count' => $item['count']])</div>
                        </div>

                        <div class="mp-badges">
                            @if ($item['owned'])
                                <span class="mp-status is-opened"><x-lucide name="check-circle" />{{ match ($item['accessSource']) { 'assignment' => 'Được cấp quyền', 'class' => 'Được lớp cấp quyền', default => 'Có quyền đọc' } }}</span>
                                <span class="mp-status is-todo" @if ($item['expiresAt']) title="Hết hạn: {{ $item['expiresAt'] }}" @endif><x-lucide name="clock" />{{ $item['remainingDays'] !== null ? 'Còn '.$item['remainingDays'].' ngày' : 'Không giới hạn' }}</span>
                            @elseif ($item['expired'])
                                <span class="mp-status is-overdue"><x-lucide name="lock" />Hết hạn sử dụng</span>
                            @elseif ($isAdmin)
                                <span class="mp-status is-neutral"><x-lucide name="check-circle" />Đang công khai</span>
                            @else
                                <span class="mp-status is-neutral"><x-lucide name="lock" />Chưa kích hoạt</span>
                            @endif
                        </div>

                        <p class="mp-desc">{{ \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $item['highlight']))) ?: 'Học liệu bản quyền của Ôn Thi 360.', 160) }}</p>
                        <p class="mp-author">{{ $item['author'] }}</p>

                        <div class="mp-buy">
                            <div class="mp-price-row">
                                <div>
                                    <small>Bản mềm · {{ $item['durationMonths'] ? $item['durationMonths'].' tháng' : 'Không giới hạn' }}</small>
                                    <strong>{{ $item['priceSoft'] }}</strong>
                                </div>
                                <x-lucide name="clock" />
                            </div>
                            <p class="mp-print-note">{{ $item['pricePrint'] ? 'Kèm sách in: '.$item['pricePrint'] : 'Đọc và luyện tập trực tuyến' }}</p>

                            @if ($isAdmin)
                                <a href="{{ route('admin.products.show', $item['id']) }}" class="mp-primary">Quản lý tài liệu<x-lucide name="chevron-right" /></a>
                            @elseif ($item['owned'] && $item['readHref'])
                                <a href="{{ $item['readHref'] }}" class="mp-primary is-green"><x-lucide name="book-open" />Đọc tài liệu<x-lucide name="chevron-right" /></a>
                            @elseif ($item['owned'])
                                <button type="button" class="mp-primary" @click="openDetail({{ $item['id'] }})"><x-lucide name="book-open" />Xem thông tin<x-lucide name="chevron-right" /></button>
                            @else
                                <button type="button" class="mp-primary" @click="openDetail({{ $item['id'] }})"><x-lucide name="shopping-cart" />{{ $item['expired'] ? 'Gia hạn / Mua tài liệu' : 'Mua / Kích hoạt' }}<x-lucide name="chevron-right" /></button>
                            @endif
                            <button type="button" class="mp-text-btn" @click="openDetail({{ $item['id'] }})">Chi tiết giá & quyền sử dụng</button>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        {{-- ── Bảng lượt giao: Tài liệu được giao (học sinh) / Tài liệu đã giao (giáo viên, admin) ── --}}
        <section class="mp-asg" x-show="isAssignmentView && total > 0" x-cloak
                 :aria-label="activeScope === 'managed' ? 'Danh sách tài liệu đã giao' : 'Danh sách tài liệu được giao'">
            <div class="mp-asg-head">
                <span>Tài liệu / Trạng thái</span>
                <span x-text="activeScope === 'managed' ? 'Học sinh / Người giao' : 'Người giao'"></span>
                <span>Hạn đọc / Quyền sử dụng</span>
                <span>Thao tác</span>
            </div>
            <template x-for="row in (isAssignmentView ? pageEntries : [])" :key="row.id">
                <article class="mp-asg-row">
                    <div class="mp-asg-left">
                        <img :src="row.item.image" alt="">
                        <div style="min-width:0">
                            <span class="mp-status" :class="statusClass(row.status)" x-text="row.statusLabel"></span>
                            <h3 class="mp-asg-title" x-text="row.item.title"></h3>
                            <div style="margin-top: 8px;">@include('partials.materials-quality', ['expr' => 'row.item'])</div>
                            <p class="mp-asg-note" x-show="row.note" x-text="'Lời nhắn: ' + row.note"></p>
                        </div>
                    </div>

                    <div class="mp-asg-mid">
                        <p class="mp-asg-who" x-show="activeScope === 'managed'"><x-lucide name="users" /><span style="word-break:break-all" x-text="row.studentName + ' · ' + row.account"></span></p>
                        <p>Giao bởi <strong x-text="row.teacher"></strong></p>
                        <p style="color:#94a3b8" x-text="'Ngày giao: ' + row.assignedAt"></p>
                    </div>

                    <div class="mp-asg-time">
                        <p class="is-deadline"><x-lucide name="calendar-days" /><span x-text="'Hạn đọc: ' + row.deadline"></span></p>
                        <p class="is-muted" x-text="'Quyền đọc: ' + row.accessDays + ' ngày'"></p>
                        <p :class="row.accessExpired ? 'is-bad' : 'is-muted-plain'" :style="row.accessExpired ? '' : 'color:#607a90'" x-text="(row.accessExpired ? 'Đã hết hạn: ' : 'Sử dụng đến: ') + row.accessExpiresAt"></p>
                    </div>

                    <div class="mp-asg-actions">
                        <template x-if="activeScope === 'managed'">
                            <button type="button" class="oi-assign-chip" x-show="row.item.inCatalog" @click.stop="openAssign(row.productId)"><x-lucide name="send" />Giao tài liệu</button>
                        </template>
                        <template x-if="activeScope === 'assigned' && row.item.owned && row.item.readHref">
                            <a :href="row.item.readHref" class="mp-primary is-green"><x-lucide name="book-open" />Đọc tài liệu</a>
                        </template>
                        <template x-if="activeScope === 'assigned' && !(row.item.owned && row.item.readHref)">
                            <button type="button" class="mp-primary" @click="openDetail(row.productId)"><x-lucide name="book-open" /><span x-text="row.item.owned ? 'Xem thông tin' : 'Gia hạn / Mua'"></span></button>
                        </template>
                        <button type="button" class="mp-text-btn" @click="openDetail(row.productId)">Giá & thời hạn</button>
                    </div>
                </article>
            </template>
        </section>

        {{-- ── Trống ── --}}
        <div class="mp-empty" x-show="total === 0" x-cloak>
            <x-lucide name="book-open" />
            <h3 x-text="emptyTitle"></h3>
            <p x-text="emptyHint"></p>
            <button type="button" class="mp-text-btn" style="margin-top: 16px;" @click="setScope('catalog'); resetFilters()">Khám phá kho tài liệu</button>
        </div>

        {{-- ── Phân trang ── --}}
        <nav aria-label="Phân trang tài liệu" class="mp-pager" x-show="totalPages > 1" x-cloak>
            <span>Trang <span x-text="page"></span> / <span x-text="totalPages"></span></span>
            <div>
                <button type="button" class="mp-icon-btn" aria-label="Trang trước" :disabled="page === 1" @click="pageIndex = Math.max(1, page - 1)"><x-lucide name="chevron-left" /></button>
                <button type="button" class="mp-icon-btn" aria-label="Trang sau" :disabled="page === totalPages" @click="pageIndex = Math.min(totalPages, page + 1)"><x-lucide name="chevron-right" /></button>
            </div>
        </nav>

        <p class="mp-note">
            Giá bản mềm đã gồm thời hạn sử dụng ghi trên từng tài liệu; quyền đọc tính từ lúc kích hoạt mã hoặc lúc giáo viên giao tài liệu.
            <span x-show="isAssignmentView" x-cloak>Trạng thái “Đã mở” chỉ ghi nhận việc mở tài liệu, không đồng nghĩa đã đọc xong.</span>
        </p>
    </section>

    {{-- ══════ HỘP CHI TIẾT GIÁ & QUYỀN SỬ DỤNG ══════ --}}
    <div class="mp-modal-back" x-show="selected" x-cloak @keydown.escape.window="selected = null" @click.self="closeDetail()">
        <div class="mp-modal" role="dialog" aria-modal="true" aria-labelledby="mp-modal-title">
            <header class="mp-modal-head">
                <div>
                    <p class="mp-eyebrow">Thông tin & quyền sử dụng</p>
                    <h2 id="mp-modal-title" x-text="selected && selected.owned ? 'Thông tin tài liệu' : 'Mua / kích hoạt tài liệu'"></h2>
                </div>
                <button type="button" class="mp-icon-btn" aria-label="Đóng thông tin tài liệu" @click="closeDetail()"><x-lucide name="x" /></button>
            </header>

            <div class="mp-modal-body">
                <template x-if="selected">
                    <div>
                        <div class="mp-modal-item">
                            <img :src="selected.image" alt="">
                            <div style="min-width:0">
                                <span class="mp-status is-neutral" x-text="selected.tag"></span>
                                <h3 style="margin-top:6px" x-text="selected.title"></h3>
                                <p x-text="'Tác giả: ' + selected.author"></p>
                                <p class="is-accent" x-text="selected.unitLabel + ' · Đọc trực tuyến và luyện tập'"></p>
                            </div>
                        </div>

                        <div style="margin-top: 16px;">@include('partials.materials-quality', ['expr' => 'selected'])</div>

                        <p class="mp-legend">Hình thức & giá</p>
                        <div class="mp-opt is-on">
                            <span>Bản mềm (Online)<small x-text="selected.durationMonths ? 'Quyền đọc trực tuyến ' + selected.durationMonths + ' tháng' : 'Quyền đọc trực tuyến không giới hạn thời gian'"></small></span>
                            <strong x-text="selected.priceSoft"></strong>
                        </div>
                        <div class="mp-opt" x-show="selected.pricePrint">
                            <span>Bản mềm + sách in<small>Giao tận nhà · chọn ở bước thanh toán</small></span>
                            <strong x-text="selected.pricePrint"></strong>
                        </div>
                        <p class="mp-meta-line"><x-lucide name="clock" />Thời hạn tính từ khi kích hoạt quyền đọc.</p>

                        {{-- Trạng thái quyền của người xem --}}
                        <div class="mp-box is-green" x-show="selected.owned" role="status">
                            <p>
                                <strong x-text="selected.accessSource === 'assignment' ? 'Tài liệu được giáo viên giao cho bạn.' : (selected.accessSource === 'class' ? 'Tài liệu được lớp của bạn cấp quyền.' : 'Bạn đang có quyền đọc.')"></strong>
                                <span x-text="selected.remainingDays !== null ? ' Còn ' + selected.remainingDays + ' ngày (đến ' + selected.expiresAt + ').' : ' Không giới hạn thời gian.'"></span>
                            </p>
                        </div>
                        <div class="mp-box is-amber" x-show="!selected.owned && selected.expired" role="status">
                            <p x-text="'Quyền đọc đã hết hạn từ ' + selected.expiresAt + '. Mua hoặc nhập mã kích hoạt để gia hạn.'"></p>
                        </div>
                        @if ($role === 'guest')
                            <div class="mp-box is-amber" x-show="!selected.owned"><p>Đăng nhập hoặc đăng ký để mua và kích hoạt tài liệu này.</p></div>
                        @elseif (! $isAdmin)
                            <div class="mp-box is-sky" x-show="!selected.owned && !selected.expired"><p>Bạn chưa có quyền đọc tài liệu này. Mua quyền học hoặc nhập mã kích hoạt đã có.</p></div>
                        @endif

                        <div class="mp-modal-actions">
                            <a :href="selected.href" class="mp-primary is-light">Xem mục lục</a>
                            @if ($role === 'guest')
                                <a :href="loginHref" class="mp-primary">Đăng nhập để mua</a>
                            @endif
                            @if ($canBuy)
                                <a :href="activateHref" class="mp-primary is-amber">Nhập mã kích hoạt</a>
                                <a :href="selected.checkoutHref" x-show="!selected.owned && selected.inCatalog" class="mp-primary"><x-lucide name="shopping-cart" />Mua quyền học ngay →</a>
                                <a :href="selected.readHref" x-show="selected.owned && selected.readHref" class="mp-primary is-green"><x-lucide name="book-open" />Vào đọc ngay →</a>
                            @endif
                            @if ($scope['canManage'])
                                <button type="button" class="oi-assign-chip" style="min-height:40px;padding:0 14px" x-show="selected.inCatalog" @click="openAssign(selected.id)"><x-lucide name="send" />Giao tài liệu</button>
                            @endif
                            @if ($isAdmin)
                                <a :href="selected.adminHref" class="mp-primary">Quản lý tài liệu</a>
                            @endif
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- ══════ POPUP GIAO TÀI LIỆU (chỉ giáo viên / admin) ══════ --}}
    @if ($scope['canManage'])
        @include('partials.materials-assign-modal')
    @endif
</div>
</div>
@endsection

@push('scripts')
    @include('partials.materials-page-script')
@endpush
