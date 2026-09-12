@extends('layouts.guest')

@section('title', 'Tài liệu, Giáo trình & Bộ đề thi')
@section('meta-description', 'Kho học liệu Ôn Thi 360 — sách giáo trình, chuyên đề thuật toán và tuyển tập đề thi có bản quyền, kèm lời giải và chấm bài tự động trên web.')

@section('content')
{{-- ═══════════════ [MATERIALS] MÀN TÀI LIỆU ═══════════════
     SỬA 11/9 — dựng lại theo ĐÚNG source giao diện khách gửi:
     education-main/src/components/MaterialsPage.jsx.
     Bố cục/class chép nguyên; React state đổi sang Alpine; mọi nút gắn link thật.

     Dữ liệu lấy từ cơ sở dữ liệu (App\Services\Public\MaterialService::indexData):
       · 3 tab + số đếm <- $tabs / $materialGroups  (Sách / Chuyên đề / Bộ đề — đã phát hành + công khai)
       · thẻ tài liệu   <- ảnh bìa, nhãn chuyên đề, số chương/phần/đề, mô tả, tác giả,
                           giá bản mềm, tuỳ chọn bản in, đánh giá thật
     Cả 3 nhóm nạp sẵn nên đổi tab KHÔNG tải lại trang, đúng như bản mẫu. --}}
@php
    $materialGroups = $materialGroups ?? ['sach' => ($materials ?? []), 'chuyen-de' => [], 'de-thi' => []];
    $activeTab = $activeTab ?? 'sach';

    $tabMeta = [
        'sach' => ['label' => 'Sách giáo trình', 'icon' => 'book-open', 'tone' => 'text-[#2D7FA3]'],
        'chuyen-de' => ['label' => 'Chuyên đề', 'icon' => 'sparkles', 'tone' => 'text-[#786BB1]'],
        'de-thi' => ['label' => 'Bộ đề', 'icon' => 'award', 'tone' => 'text-[#AF7C32]'],
    ];

    // Hàng dữ liệu đưa sang Alpine để lọc/phân trang/mở hộp chi tiết ngay tại chỗ.
    $materialRows = [];
    foreach ($materialGroups as $key => $group) {
        foreach ($group as $m) {
            $materialRows[] = [
                'id' => $m['id'],
                'tab' => $key,
                'search' => mb_strtolower(trim($m['title'].' '.($m['tag'] ?? '').' '.($m['highlight'] ?? '').' '.($m['author'] ?? ''))),
                'title' => $m['title'],
                'tag' => $m['tag'] ?? '',
                'author' => $m['author'] ?? '',
                'image' => $m['image'],
                'average' => $m['average'],
                'priceSoft' => $m['priceSoft'] ?? $m['meta'],
                'hasPrintOption' => (bool) ($m['hasPrintOption'] ?? false),
                'durationMonths' => $m['durationMonths'] ?? null,
                'owned' => (bool) ($m['owned'] ?? false),
                'href' => $m['href'],
                'checkoutHref' => $m['checkoutHref'],
            ];
        }
    }

    $totalMaterials = count($materialRows);
    $ownedCount = 0;
    foreach ($materialRows as $r) { if ($r['owned']) { $ownedCount++; } }
@endphp

<div class="max-w-[1780px] w-full mx-auto px-3 sm:px-5 lg:px-6 2xl:px-10 py-3 sm:py-5">
<div x-data="onthiMaterialsPage({{ Js::from(['rows' => $materialRows, 'pageSize' => 4, 'activeTab' => $activeTab, 'activateHref' => route('access.activate')]) }})" class="flex flex-col gap-5">

    {{-- ══════ 1. HERO TÀI LIỆU ══════ --}}
    <div class="relative rounded-3xl overflow-hidden border border-sky-200/90 shadow-[0_10px_35px_rgba(0,100,220,0.08)] bg-gradient-to-r from-[#0B3C78] via-[#0284C7] to-[#38BDF8] p-6 sm:p-8 text-white flex flex-col md:flex-row items-center justify-between gap-6">
        <img src="{{ asset('assets/hero-materials.jpg') }}" alt="Kho học liệu Ôn Thi 360"
             class="absolute inset-0 w-full h-full object-cover object-right pointer-events-none opacity-45 mix-blend-overlay">

        <div class="relative z-10 max-w-2xl">
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-400 text-amber-950 mb-3 shadow-sm">
                <x-lucide name="shield-check" class="w-3.5 h-3.5" />
                <span>Kho học liệu & Sách giáo trình có bản quyền</span>
            </div>

            <h1 class="text-2xl font-black tracking-tight text-white leading-tight">Tài liệu, Giáo trình & Bộ đề thi</h1>

            <p class="text-xs sm:text-sm text-sky-100 mt-2 leading-relaxed">
                Hệ thống sách giáo trình, chuyên đề giải thuật và bộ đề thi chuẩn hóa được biên soạn công phu
                bởi các chuyên gia và giáo viên trường Chuyên hàng đầu.
            </p>

            <div class="flex flex-wrap items-center gap-2.5 mt-4">
                <a href="{{ route('access.activate') }}"
                   class="px-5 py-2.5 rounded-full bg-gradient-to-r from-amber-400 to-amber-500 hover:from-amber-500 hover:to-amber-600 text-amber-950 font-black text-xs shadow-md flex items-center gap-1.5 cursor-pointer transition-all active:scale-98">
                    <x-lucide name="key-round" class="w-4 h-4" />
                    <span>Kích hoạt mã sách / tài liệu</span>
                </a>
                <span class="text-xs text-sky-100 font-medium">Hỗ trợ bản mềm PDF tương tác & Bản in giao tận nhà</span>
            </div>
        </div>

        <div class="relative z-10 bg-white/10 backdrop-blur-md border border-white/20 rounded-3xl p-4 sm:p-5 w-full md:w-80 shadow-xl text-center">
            <p class="text-xs font-bold text-sky-200 uppercase tracking-wider">Học liệu đã phát hành</p>
            <div class="grid grid-cols-2 gap-3 mt-3">
                <div class="bg-white/10 rounded-2xl p-2.5">
                    <p class="text-2xl font-black text-white">{{ number_format($totalMaterials) }}</p>
                    <p class="text-[10px] text-sky-200 mt-0.5">Đầu sách & Chuyên đề</p>
                </div>
                <div class="bg-white/10 rounded-2xl p-2.5">
                    <p class="text-2xl font-black text-amber-300">{{ $ownedCount }}</p>
                    <p class="text-[10px] text-sky-200 mt-0.5">Bạn đã có quyền học</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════ 2. 3 TAB: SÁCH / CHUYÊN ĐỀ / BỘ ĐỀ ══════ --}}
    <div class="bg-white rounded-3xl p-4 border border-sky-100 shadow-[0_2px_10px_rgba(0,100,220,0.04)] flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <div class="flex items-center gap-2 bg-slate-100 p-1 rounded-2xl w-full sm:w-auto">
            @foreach ($tabMeta as $key => $meta)
                <button type="button" @click="setTab(@js($key))" :aria-pressed="activeTab === @js($key)"
                        class="flex-1 sm:flex-none min-h-10 px-4 py-2 rounded-xl text-[11px] font-bold transition-all cursor-pointer flex items-center justify-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#9DC8D7]"
                        :class="activeTab === @js($key) ? 'bg-[#0066CC] text-white shadow-2xs' : 'text-[#536D86] hover:bg-white hover:text-[#126F91]'">
                    <span class="grid h-5 w-5 place-items-center rounded-lg" :class="activeTab === @js($key) ? 'bg-white/15' : 'bg-[#EAF5F8]'">
                        <x-lucide :name="$meta['icon']" class="h-3.5 w-3.5" ::class="activeTab === @js($key) ? 'text-white' : '{{ $meta['tone'] }}'" />
                    </span>
                    <span>{{ $meta['label'] }}</span>
                    <span class="text-[10px] px-1.5 rounded-full" :class="activeTab === @js($key) ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-600'">{{ count($materialGroups[$key] ?? []) }}</span>
                </button>
            @endforeach
        </div>

        <div class="relative flex-1 sm:max-w-xs">
            <x-lucide name="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input type="text" placeholder="Tìm tài liệu, tác giả..." x-model="searchQuery"
                   class="w-full pl-10 pr-4 py-2 text-xs bg-[#F0F6FC] border border-sky-200 rounded-2xl text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
    </div>

    {{-- ══════ 3. LƯỚI TÀI LIỆU ══════ --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        @foreach ($materialGroups as $tabKey => $group)
            @foreach ($group as $item)
                <div x-show="visibleIds.includes({{ $item['id'] }})" x-cloak
                     :style="'order:' + visibleIds.indexOf({{ $item['id'] }})"
                     class="bg-white rounded-3xl border border-sky-100 shadow-[0_4px_16px_rgba(0,100,220,0.05)] overflow-hidden flex flex-col justify-between hover:shadow-lg hover:border-sky-200 transition-all duration-300 group">
                    <div>
                        {{-- Ảnh bìa --}}
                        <div class="relative h-56 overflow-hidden bg-slate-50 flex items-center justify-center p-3">
                            <img src="{{ $item['image'] }}" alt="{{ $item['title'] }}"
                                 class="h-full object-contain drop-shadow-md group-hover:scale-105 transition-transform duration-300">
                            <span class="absolute top-3 left-3 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-white/95 text-[#0050A0] border border-sky-200 shadow-2xs backdrop-blur-xs">{{ $item['tag'] }}</span>
                            @if ($item['owned'])
                                <span class="absolute top-3 right-3 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 shadow-2xs">
                                    <x-lucide name="check-circle" class="h-3 w-3" />Đã có quyền
                                </span>
                            @endif
                        </div>

                        {{-- Thông tin --}}
                        <div class="p-4">
                            <div class="flex items-center justify-between gap-1 text-xs mb-1.5">
                                <span class="inline-flex items-center gap-1 text-[10.5px] text-slate-500 font-medium">
                                    <x-lucide name="file-text" class="h-3.5 w-3.5 text-[#2D7FA3]" />
                                    {{ $item['unitLabel'] }}
                                </span>
                                <div class="flex items-center gap-1 text-amber-500 font-bold text-xs">
                                    <x-lucide name="star" class="w-3.5 h-3.5 fill-amber-400" />
                                    <span>{{ $item['average'] !== null ? number_format($item['average'], 1) : '—' }}</span>
                                    <span class="text-[10px] text-slate-400">({{ $item['count'] }})</span>
                                </div>
                            </div>

                            <h3 class="text-xs sm:text-sm font-extrabold text-[#0B3C78] leading-snug line-clamp-2 mb-2 group-hover:text-blue-600 transition-colors">{{ $item['title'] }}</h3>

                            <p class="flex items-start gap-1 text-[11px] text-slate-500 line-clamp-2 mb-2">
                                <x-lucide name="sparkles" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-[#AF7C32]" />
                                <span>{{ \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $item['highlight']))) ?: 'Học liệu bản quyền của Ôn Thi 360.', 160) }}</span>
                            </p>

                            <p class="text-[10px] text-slate-400">Tác giả: <strong class="text-slate-600">{{ $item['author'] }}</strong></p>
                        </div>
                    </div>

                    {{-- Giá & hành động --}}
                    <div class="p-4 pt-0">
                        <div class="pt-3 border-t border-sky-100 flex items-center justify-between">
                            <div>
                                <p class="text-[10px] text-slate-400">Bản mềm (Online)</p>
                                <p class="text-sm font-black text-[#0B3C78]">{{ $item['priceSoft'] }}</p>
                            </div>

                            <button type="button" @click="openDetail({{ $item['id'] }})"
                                    class="px-3.5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-2xs flex items-center gap-1 transition-all cursor-pointer">
                                <span>Xem tài liệu</span>
                                <x-lucide name="chevron-right" class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        @endforeach
    </div>

    {{-- Phân trang --}}
    <nav aria-label="Phân trang tài liệu" x-show="totalPages > 1" x-cloak
         class="mt-3 flex flex-col items-center justify-between gap-2 rounded-xl border border-sky-100 bg-white p-2 sm:flex-row">
        <span class="text-[11px] text-slate-500">Trang <b class="text-slate-700" x-text="page"></b> / <span x-text="totalPages"></span></span>
        <div class="flex items-center gap-1.5">
            <button type="button" aria-label="Trang trước" :disabled="page === 1" @click="pageIndex = Math.max(1, page - 1)"
                    class="grid h-9 w-9 place-items-center rounded-lg border border-sky-100 bg-white text-slate-600 transition hover:border-sky-300 hover:bg-sky-50 disabled:cursor-not-allowed disabled:opacity-40">
                <x-lucide name="chevron-left" class="h-4 w-4" />
            </button>
            <template x-for="n in totalPages" :key="'mp' + n">
                <button type="button" :aria-label="'Trang ' + n" :aria-current="page === n ? 'page' : null" @click="pageIndex = n"
                        class="grid h-9 min-w-9 place-items-center rounded-lg px-2 text-[11px] font-extrabold transition"
                        :class="page === n ? 'bg-[#0066CC] text-white shadow-2xs' : 'text-slate-600 hover:bg-sky-50'"
                        x-text="n"></button>
            </template>
            <button type="button" aria-label="Trang sau" :disabled="page === totalPages" @click="pageIndex = Math.min(totalPages, page + 1)"
                    class="grid h-9 w-9 place-items-center rounded-lg border border-sky-100 bg-white text-slate-600 transition hover:border-sky-300 hover:bg-sky-50 disabled:cursor-not-allowed disabled:opacity-40">
                <x-lucide name="chevron-right" class="h-4 w-4" />
            </button>
        </div>
    </nav>

    <div x-show="filtered.length === 0" x-cloak class="rounded-3xl border border-dashed border-sky-200 bg-white p-10 text-center">
        <x-lucide name="search" class="mx-auto h-9 w-9 text-sky-300" />
        <h2 class="mt-3 text-sm font-black text-slate-800">Không tìm thấy tài liệu</h2>
        <p class="mt-1 text-xs text-slate-500">Thử chọn danh mục khác hoặc xóa từ khóa tìm kiếm.</p>
        <button type="button" @click="searchQuery = ''" class="mt-4 text-xs font-bold text-blue-600">Xóa tìm kiếm</button>
    </div>

    {{-- ══════ HỘP CHI TIẾT & MUA QUYỀN ══════ --}}
    <div x-show="selected" x-cloak @keydown.escape.window="selected = null"
         class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4">
        <div @click.outside="selected = null" class="bg-white rounded-3xl max-w-xl w-full p-5 sm:p-6 shadow-2xl border border-sky-100 relative">
            <button type="button" aria-label="Đóng thông tin tài liệu" @click="selected = null"
                    class="absolute right-4 top-4 grid h-9 w-9 place-items-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#9DC8D7]">
                <x-lucide name="x" class="h-4 w-4" />
            </button>

            <template x-if="selected">
                <div>
                    <div class="flex gap-4 mb-4">
                        <img :src="selected.image" alt="" class="w-24 h-32 object-contain rounded-xl border border-slate-200">
                        <div>
                            <span class="text-[10px] font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md" x-text="selected.tag"></span>
                            <h3 class="text-sm font-bold text-[#0B3C78] mt-1" x-text="selected.title"></h3>
                            <p class="text-xs text-slate-500 mt-1">Tác giả: <span x-text="selected.author"></span></p>
                            <div class="mt-2 flex items-center gap-1 text-xs font-bold text-amber-500">
                                <span class="inline-flex items-center gap-0.5">
                                    <x-lucide name="star" class="h-3.5 w-3.5 fill-amber-400" />
                                    <x-lucide name="star" class="h-3.5 w-3.5 fill-amber-400" />
                                    <x-lucide name="star" class="h-3.5 w-3.5 fill-amber-400" />
                                    <x-lucide name="star" class="h-3.5 w-3.5 fill-amber-400" />
                                    <x-lucide name="star" class="h-3.5 w-3.5 fill-amber-400" />
                                </span>
                                <span x-text="selected.average !== null ? selected.average + ' (Đã xác thực)' : 'Chưa có đánh giá'"></span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-[#F8FBFE] p-3.5 rounded-2xl border border-sky-100 text-xs space-y-2 mb-4">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Bản mềm (Đọc & Chấm bài trên web):</span>
                            <strong class="text-blue-600" x-text="selected.priceSoft"></strong>
                        </div>
                        <div class="flex justify-between" x-show="selected.hasPrintOption">
                            <span class="text-slate-500">Tùy chọn kèm sách in giao tận nhà:</span>
                            <strong class="text-amber-600">Liên hệ để đặt bản in</strong>
                        </div>
                        <div class="flex justify-between" x-show="selected.durationMonths">
                            <span class="text-slate-500">Thời hạn quyền học mặc định:</span>
                            <strong class="text-slate-700"><span x-text="selected.durationMonths"></span> tháng</strong>
                        </div>
                        <div class="flex items-start gap-1.5 border-t border-sky-100 pt-1 text-[11px] text-slate-400">
                            <x-lucide name="check-circle" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-[#3B9374]" />
                            <span>Được cấp quyền truy cập ngay sau khi nhập mã kích hoạt hợp lệ.</span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <a :href="selected.href" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs cursor-pointer">Xem mục lục</a>
                        <a :href="activateHref" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs shadow-xs cursor-pointer">Nhập mã kích hoạt có sẵn</a>
                        <a :href="selected.checkoutHref" x-show="!selected.owned"
                           class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-md cursor-pointer">Mua quyền học ngay →</a>
                        <a :href="selected.href" x-show="selected.owned"
                           class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md cursor-pointer">Vào đọc ngay →</a>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
</div>
@endsection

@push('scripts')
    @include('partials.materials-page-script')
@endpush
