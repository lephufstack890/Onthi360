@extends('layouts.student')

@section('title', 'Tài liệu của tôi')
@section('page-title', 'Tài liệu của tôi')

@section('content')
    {{-- SỬA 12/10 — DỰNG LẠI THEO education-main (source mới): StudentMaterialsPanel.jsx — tiêu đề "Tài liệu của tôi" + số tài liệu có quyền đọc,
         lưới 2 cột thẻ (bìa 64×96, nhãn quyền, tên, "N bài · hạn dùng", nút "Đọc tài liệu"). GIỮ NGUYÊN logic cũ: 3 tab Sách/Chuyên đề/Bộ đề,
         tìm kiếm, ngăn "Tài nguyên" (tải tài nguyên + làm bài tập), quyền đọc qua $p['readHref']. Thêm phân trang 10 thẻ/trang. --}}
    @php
        $tabs = $tabs ?? [];
        $products = $products ?? [];
        $titles = array_map(fn ($p) => mb_strtolower($p['title']), $products);
    @endphp

    <div class="sw" data-section="STUDENT-MATERIALS">
        @include('student._hero')

        <x-ws.tabs :tabs="$tabs" />

        @if (empty($products))
            <section class="student-overview-card">
                <p class="sw-empty">Chưa có tài liệu nào trong mục này. Mua hoặc nhập mã kích hoạt ở trang Tài liệu để bắt đầu.</p>
                <div style="text-align:center"><a class="student-class-enter" style="display:inline-flex;padding-inline:16px" href="{{ route('materials.index') }}">Khám phá tài liệu <x-lucide name="arrow-right" class="h-3.5 w-3.5" /></a></div>
            </section>
        @else
            <div x-data="stuMats(@js($titles))">
                <div class="sw-split" style="align-items:center;margin-bottom:12px">
                    <h2 class="sw-h2" style="font-size:14px">Tài liệu của tôi</h2>
                    <p class="sw-note"><span x-text="total"></span> / {{ count($products) }} tài liệu có quyền đọc</p>
                </div>
                <div style="position:relative;max-width:320px;margin-bottom:12px">
                    <span style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#7fa5b8;display:flex"><x-lucide name="search" class="h-4 w-4" /></span>
                    <input type="search" x-model.debounce.150ms="q" @input="page = 1" placeholder="Tìm theo tên tài liệu…" aria-label="Tìm tài liệu"
                           style="width:100%;min-height:38px;padding:8px 10px 8px 34px;border:1px solid #dce7ec;border-radius:10px;background:#f8fbfd;font-size:12px;color:#36566d;outline:none">
                </div>

                <div class="sw-mats">
                    @foreach ($products as $p)
                        <article class="sw-mat" style="flex-wrap:wrap" aria-label="{{ $p['title'] }}" x-show="show({{ $loop->index }})" x-data="{ open: false }">
                            @if ($p['coverPath'])
                                <img src="{{ \App\Support\ProductCover::url($p['coverPath']) }}" alt="Bìa {{ $p['title'] }}" loading="lazy">
                            @else
                                <span style="display:grid;place-items:center;width:64px;height:96px;flex-shrink:0;border-radius:8px;background:#eef6fa;color:#23869b"><x-lucide name="book-open" class="h-6 w-6" /></span>
                            @endif
                            <div class="sw-mat-b">
                                <span class="st ok">Có quyền đọc</span>
                                <h3>{{ $p['title'] }}</h3>
                                <p>
                                    @if ($p['lessonCount'] > 0){{ $p['lessonCount'] }} {{ $p['lessonWord'] }} · @endif{{ $p['access']['remainingLabel'] }}
                                </p>
                                @if (count($p['resources']) > 0 || count($p['exercises']) > 0)
                                    <p>{{ count($p['resources']) > 0 ? count($p['resources']).' tài nguyên' : '' }}{{ count($p['resources']) > 0 && count($p['exercises']) > 0 ? ' · ' : '' }}{{ count($p['exercises']) > 0 ? count($p['exercises']).' bài tập' : '' }}</p>
                                @endif
                                <div class="row">
                                    @if ($p['readHref'])
                                        <a href="{{ $p['readHref'] }}" class="student-class-enter"><x-lucide name="book-open" class="h-3.5 w-3.5" />Đọc tài liệu</a>
                                    @else
                                        <span class="sw-note" title="Sản phẩm chưa có bài đọc nào đính kèm file PDF">Chưa có nội dung đọc</span>
                                    @endif
                                    @if (count($p['resources']) > 0 || count($p['exercises']) > 0)
                                        <button type="button" class="sw-act" @click="open = ! open" :aria-expanded="open ? 'true' : 'false'">
                                            <span x-text="open ? 'Thu gọn' : 'Tài nguyên'"></span>
                                            <svg class="sw-chev" :class="{ up: open }" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                        </button>
                                    @endif
                                </div>
                            </div>

                            {{-- ══════ NGĂN KÉO: TÀI NGUYÊN + BÀI TẬP (logic giữ nguyên như cũ) ══════ --}}
                            @if (count($p['resources']) > 0 || count($p['exercises']) > 0)
                                <div x-show="open" x-cloak class="space-y-4 border-t border-[#E9F1F5] bg-[#F8FBFC] p-4" style="width:100%;border-radius:12px">
                                    @if (count($p['resources']) > 0)
                                <div>
                                    <p class="mb-2 text-[10px] font-bold uppercase tracking-wide text-[#7FA5B8]">Tài nguyên đính kèm</p>
                                    <div class="space-y-2">
                                        @foreach ($p['resources'] as $res)
                                            <a href="{{ route('access.resource', ['product' => $p['id'], 'kind' => $res['kind']]) }}" target="_blank" rel="noopener"
                                               class="flex items-center gap-2 rounded-xl border border-[#DDEAF0] bg-white px-3 py-2 text-[12.5px] font-medium text-[#466278] transition hover:border-[#9DC8D7] hover:bg-[#F0F8FB] hover:text-[#126F91]">
                                                <span>{{ $res['icon'] }}</span>{{ $res['label'] }}
                                                <x-lucide name="download" class="ml-auto h-3.5 w-3.5 text-[#9DB6C4]" />
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if (count($p['exercises']) > 0)
                                <div>
                                    <p class="mb-2 text-[10px] font-bold uppercase tracking-wide text-[#7FA5B8]">Bài tập</p>
                                    <div class="space-y-2">
                                        @foreach ($p['exercises'] as $ex)
                                            <div class="flex items-center justify-between gap-3 rounded-xl border border-[#DDEAF0] bg-white px-3 py-2.5">
                                                <div class="min-w-0">
                                                    <p class="truncate text-[12.5px] font-semibold text-[#123B68]">{{ $ex['title'] }}</p>
                                                    <p class="text-[11px] text-[#7A92A3]">{{ $ex['points'] }} điểm · {{ $ex['summary'] }}</p>
                                                </div>
                                                <form action="{{ route('student.practiceByQuestion.startExercise', $ex['id']) }}" method="POST" class="shrink-0">
                                                    @csrf
                                                    {{-- SỬA (fix "quay lại" sai trang): url()->full() trả về URL TUYỆT ĐỐI
                                                         (có scheme+host) — PracticeByQuestionController::startExercise() chỉ
                                                         nhận đường dẫn bắt đầu bằng đúng 1 dấu '/' (chặn open-redirect) nên
                                                         luôn bị coi là không hợp lệ và rơi về mặc định route('student.library.index')
                                                         (xem exercise-play.blade.php: $backUrl = $returnUrl ?? route(...)),
                                                         DÙ đang bấm "Làm bài" từ trang nào khác (vd tab Học liệu ở chi tiết
                                                         lớp) — request()->getRequestUri() trả về đúng path+query TƯƠNG ĐỐI
                                                         (bắt đầu bằng '/'), qua được kiểm tra và quay lại ĐÚNG trang đã bấm. --}}
                                                    <input type="hidden" name="return_url" value="{{ request()->getRequestUri() }}">
                                                    <button type="submit" class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-xl bg-[#2F9E72] px-3 py-1.5 text-[11.5px] font-bold text-white shadow-sm transition hover:bg-[#278761]">Làm bài<x-lucide name="chevron-right" class="h-3.5 w-3.5" /></button>
                                                </form>
                                            </div>
                                        @endforeach
                                    </div>
                                    <p class="mt-2 text-[11px] text-[#7A92A3]">Bài tập chưa có chấm tự động — bài làm sẽ được ghi nhận, chưa báo đúng/sai ngay.</p>
                                </div>
                            @endif
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>

                <p class="sw-empty" x-show="total === 0" x-cloak>Không tìm thấy tài liệu nào khớp từ khoá tìm kiếm.</p>
                <div class="student-overview-card" style="margin-top:12px;padding-block:2px" x-show="total > size" x-cloak>
                    @include('student._pager')
                </div>
            </div>
        @endif
    </div>
    <script>
        function stuMats(titles) {
            return window.swPager(titles.length, 10, {
                q: '', titles: titles,
                get idx() { var q = this.q.trim().toLowerCase(), out = []; this.titles.forEach(function (t, i) { if (!q || t.indexOf(q) !== -1) { out.push(i); } }); return out; },
                get total() { return this.idx.length; },
                show: function (i) { var p = this.idx.indexOf(i); return p >= (this.page - 1) * this.size && p !== -1 && p < this.page * this.size; },
            });
        }
    </script>
@endsection
