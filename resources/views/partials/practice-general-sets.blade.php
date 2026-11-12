{{--
    SỬA 3/11 (khách: "dạng PHỔ THÔNG — mỗi bài nhiều câu…"; rồi: "UI in đúc dạng lập trình, chỉ khác dạng; bấm Làm bài là vô làm
    luôn") — danh sách "Bài phổ thông" khi ô dạng bài chọn "Phổ thông" (partials/practice-catalog). Dùng ĐÚNG bộ khung / class của
    hàng bài lập trình (oi-prob-grid, oi-code-line, oi-topics…) nên nhìn y hệt; chỉ khác: nhãn dạng "Phổ thông · N câu", cột kết quả
    là điểm trung bình, nút "Làm bài" mở thẳng màn làm bài (student.assessment.take).
    Dữ liệu: App\Services\Public\GeneralSetCatalogService. Lọc theo ô tìm kiếm / chuyên đề / độ khó / "Chưa làm" của trang.
--}}
@php
    // SỬA 12/11 — practice-catalog có thể đã nạp sẵn (để dựng băng chuyên đề Phổ thông) thì dùng lại, khỏi truy vấn 2 lần.
    $gstRows = $gstRows ?? app(\App\Services\Public\GeneralSetCatalogService::class)->rows(auth()->user());
    $gstMatchData = array_map(fn ($r) => ['id' => $r['id'], 'search' => $r['search'].' '.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($r['search'])) /* SỬA 12/11 — tìm không dấu */, 'tagIds' => $r['tagIds'], 'difficulty' => $r['difficulty'], 'done' => $r['myAttempts'] > 0], $gstRows);
@endphp
<script>
    // Số bài đang hiện — để dòng "Hiển thị N bài tập" ở đầu trang đếm đúng khi chọn Phổ thông.
    (function () {
        const reg = () => { try { if (! Alpine.store('oiGeneralSets')) Alpine.store('oiGeneralSets', { count: 0 }); } catch (e) {} };
        if (window.Alpine && typeof Alpine.store === 'function') reg(); else document.addEventListener('alpine:init', reg);
    })();
    window.oiGeneralSetList = function (rows) {
        return {
            gstRows: rows,
            gstShow: 20,
            get gstVisibleIds() {
                const q = (this.searchQuery || '').trim().toLowerCase();
                const qPlain = q.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd');
                const topic = this.selectedTopic, diff = this.selectedDifficulty;
                return this.gstRows.filter((r) =>
                    (!q || r.search.includes(q) || r.search.includes(qPlain))
                    && (topic === 'all' || topic === undefined || r.tagIds.includes(Number(topic)))
                    && (diff === 'all' || diff === undefined || r.difficulty === diff)
                    && (!this.onlyUndone || !r.done)).map((r) => r.id);
            },
            gstPos(id) { const i = this.gstVisibleIds.indexOf(id); return i >= 0 && i < this.gstShow ? i : -1; },
        };
    };
</script>
<div x-show="activeTab === 'general' && problemScope !== 'assigned'" x-cloak x-data="oiGeneralSetList(@js($gstMatchData))"
     x-effect="if ($store.oiGeneralSets) $store.oiGeneralSets.count = gstVisibleIds.length">
    <div x-show="gstVisibleIds.length > 0" class="flex flex-col divide-y divide-[#E7EFF3] overflow-hidden rounded-2xl border border-[#DDEAF0] bg-white shadow-[0_2px_10px_rgba(28,91,121,0.05)]">
        <div class="hidden bg-[#F4F8FB] px-4 py-2.5 text-[11px] font-bold uppercase tracking-[.06em] text-[#365B7A] lg:grid oi-prob-grid {{ $showProblemProvinceAuthor ? '' : 'oi-prob-grid--lite' }}">
            <span>Tên bài tập &amp; Mã</span>
            <span>Chuyên đề</span>
            @if ($showProblemProvinceAuthor)<span>Tỉnh thành</span>@endif
            <span>Năm</span>
            @if ($showProblemProvinceAuthor)<span class="oi-prob-author">Tác giả</span>@endif
            <span>Độ khó</span>
            <span>Điểm TB</span>
            <span class="text-right">Hành động</span>
        </div>

        @foreach ($gstRows as $set)
            @php
                $gstHref = $canTakeDirectly ? route('student.assessment.take', $set['id']) : route('login');
                $gstTopics = array_values(array_filter($set['topicLabels'] ?? []));
                $gstTopicSubs = array_slice($gstTopics, 1, 2);
                $gstTopicMore = array_slice($gstTopics, 3);
                $gstAvg = (int) ($set['avgPercent'] ?? 0);
            @endphp
            <div x-show="gstPos({{ $set['id'] }}) >= 0" :style="{ order: gstPos({{ $set['id'] }}) }"
                 :class="gstPos({{ $set['id'] }}) % 2 === 0 ? 'bg-[#FCFEFF]' : 'bg-[#F7FBFC]'"
                 class="grid grid-cols-1 gap-x-2.5 gap-y-2 border-l-2 border-transparent p-2.5 transition-all hover:border-l-[#2D7FA3] hover:bg-[#F8FBFC] sm:px-4 oi-prob-grid {{ $showProblemProvinceAuthor ? '' : 'oi-prob-grid--lite' }} lg:items-center lg:gap-2.5">

                {{-- Tên bài --}}
                <div class="min-w-0">
                    <div class="flex min-w-0 items-center gap-2">
                        @if ($set['myAttempts'] > 0)
                            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-[#237052] text-white shadow-[0_3px_8px_rgba(35,112,82,0.2)]" title="Đã làm" aria-label="Đã làm">
                                {{ $oiIcon('check-circle-2', 'h-4 w-4') }}
                            </span>
                        @endif
                        <a href="{{ $gstHref }}" title="{{ $set['title'] }}" @click="withReturn($event)"
                           class="min-w-0 flex-1 line-clamp-2 text-left text-[14px] font-semibold leading-5 text-[#123B68] transition-colors hover:text-[#126F91] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#CBEAF1] sm:text-[15px]">{{ $set['title'] }}</a>
                        <span class="mt-0.5 shrink-0 whitespace-nowrap rounded-full px-2 py-1 text-[10px] font-bold {{ $set['myAttempts'] > 0 ? 'bg-[#EFF9F5] text-[#2F8A6B]' : 'bg-[#F2F6F8] text-[#607A90]' }}">
                            {{ $set['myAttempts'] > 0 ? 'Đã làm '.$set['myAttempts'].' lần' : 'Chưa làm' }}
                        </span>
                    </div>

                    <div class="mt-1.5">
                        @include('partials.practice-content-location', ['province' => $set['provinceLabel'], 'region' => $set['regionLabel']])
                    </div>

                    <p class="mt-1 flex flex-wrap items-center gap-2 text-[11px] font-medium text-[#2F7F67]">
                        @if ($assignScope['canAssignExam'] ?? $assignScope['canAssign'])
                            <button type="button" class="oi-assign-chip" aria-haspopup="dialog" aria-label="Giao bài: {{ $set['title'] }}"
                                    @click.stop="openAssign('exam', {{ $set['id'] }}, @js($set['title']), @js($set['code'] ?: '#'.$set['id']))">
                                {{ $oiIcon('send') }}Giao bài
                            </button>
                        @endif
                        @auth
                            <a href="{{ route('practice.history.exam', $set['id']) }}" title="Nhật ký nộp bài"
                               class="oi-log-link inline-flex min-h-7 shrink-0 items-center gap-1 rounded-lg border border-[#D6E3EF] bg-[#EEF4FA] px-2 py-1 text-[10px] font-bold text-[#365B7A] shadow-[0_2px_6px_rgba(18,59,104,0.08)]">
                                {{ $oiIcon('clipboard-list', 'h-3 w-3') }}Nhật ký nộp bài
                            </a>
                        @endauth
                        <span class="flex min-w-0 items-center gap-1 truncate">
                            {{ $oiIcon('sparkles', 'h-3 w-3 shrink-0 text-[#3B9374]') }}
                            <span class="truncate">Nguồn: Kho luyện tập Ôn Thi 360</span>
                        </span>
                    </p>

                    <div class="oi-code-line">
                        <span class="oi-mono">Mã: {{ $set['code'] ?: '#'.$set['id'] }}</span>
                        <span class="oi-type-chip" aria-label="Dạng bài: Phổ thông">Phổ thông</span>
                        <span class="oi-mono" style="display:inline-flex;align-items:center;gap:.5rem"><span aria-hidden="true">•</span><span>{{ $set['questionCount'] }} câu / {{ $set['totalPoints'] }} điểm</span></span>
                    </div>
                </div>

                <div class="col-span-1 grid grid-cols-2 gap-2 lg:contents">
                    <div class="min-w-0 rounded-lg border border-[#E7EFF3] bg-[#F8FBFC] px-2.5 py-2 lg:rounded-none lg:border-0 lg:bg-transparent lg:p-0">
                        <span class="mb-0.5 block text-[10px] font-bold uppercase tracking-wide text-[#6B8295] lg:hidden">Chuyên đề</span>
                        @php $gstTopicIds = array_values($set['tagIds'] ?? []); /* SỬA 12/11 — bấm chuyên đề để lọc */ @endphp
                        <div class="oi-topics" title="{{ $gstTopics ? implode(', ', $gstTopics) : $set['topicLabel'] }}">
                            @if (isset($gstTopicIds[0]))
                                <button type="button" class="oi-topic-chip oi-topic-pick rounded-lg border border-[#D6E3EF] bg-[#EEF4FA] px-2 py-0.5 text-[12px] font-semibold text-[#365B7A]"
                                        @click.stop="pickTopic({{ (int) $gstTopicIds[0] }})" title="Lọc theo chuyên đề “{{ $set['topicLabel'] }}”">{{ $set['topicLabel'] }}</button>
                            @else
                                <span class="oi-topic-chip rounded-lg border border-[#D6E3EF] bg-[#EEF4FA] px-2 py-0.5 text-[12px] font-semibold text-[#365B7A]">{{ $set['topicLabel'] }}</span>
                            @endif
                            @if ($gstTopicSubs)
                                <span class="oi-topic-subs">
                                    @foreach ($gstTopicSubs as $si => $gstTopic)
                                        @if (isset($gstTopicIds[$si + 1]))
                                            <button type="button" class="oi-topic-sub oi-topic-pick" @click.stop="pickTopic({{ (int) $gstTopicIds[$si + 1] }})" title="Lọc theo chuyên đề “{{ $gstTopic }}”">{{ $gstTopic }}</button>
                                        @else
                                            <span class="oi-topic-sub">{{ $gstTopic }}</span>
                                        @endif
                                    @endforeach
                                    @if ($gstTopicMore)
                                        <span class="oi-topic-more">+{{ count($gstTopicMore) }}</span>
                                    @endif
                                </span>
                            @endif
                        </div>
                    </div>

                    @if ($showProblemProvinceAuthor)
                    <div class="min-w-0 rounded-lg border border-[#E7EFF3] bg-[#F8FBFC] px-2.5 py-2 lg:rounded-none lg:border-0 lg:bg-transparent lg:p-0">
                        <span class="mb-0.5 block text-[10px] font-bold uppercase tracking-wide text-[#6B8295] lg:hidden">Tỉnh thành</span>
                        <span class="block truncate text-[12px] font-semibold text-[#365B7A]">{{ $set['provinceLabel'] }}</span>
                    </div>
                    @endif

                    <div class="min-w-0 rounded-lg border border-[#E7EFF3] bg-[#F8FBFC] px-2.5 py-2 lg:rounded-none lg:border-0 lg:bg-transparent lg:p-0">
                        <span class="mb-0.5 block text-[10px] font-bold uppercase tracking-wide text-[#6B8295] lg:hidden">Năm</span>
                        <span class="block text-[12px] font-semibold text-[#365B7A]">{{ $set['yearLabel'] }}</span>
                    </div>

                    @if ($showProblemProvinceAuthor)
                    <div class="oi-prob-author min-w-0 rounded-lg border border-[#E7EFF3] bg-[#F8FBFC] px-2.5 py-2 lg:rounded-none lg:border-0 lg:bg-transparent lg:p-0">
                        <span class="mb-0.5 block text-[10px] font-bold uppercase tracking-wide text-[#6B8295] lg:hidden">Tác giả</span>
                        <span class="block truncate text-[12px] font-semibold text-[#365B7A]" title="{{ $set['authorName'] ?: '—' }}">{{ $set['authorName'] ?: '—' }}</span>
                    </div>
                    @endif

                    <div class="min-w-0 rounded-lg border border-[#E7EFF3] bg-[#F8FBFC] px-2.5 py-2 lg:rounded-none lg:border-0 lg:bg-transparent lg:p-0">
                        <span class="mb-0.5 block text-[10px] font-bold uppercase tracking-wide text-[#6B8295] lg:hidden">Độ khó</span>
                        <div class="flex flex-col items-start gap-0.5">
                            <div class="inline-flex items-center gap-0.5" role="img" aria-label="Độ khó {{ $set['difficultyStars'] }} trên 5 sao">
                                @for ($star = 0; $star < 5; $star++)
                                    {{ $oiIcon('star', 'h-3.5 w-3.5 '.($star < $set['difficultyStars'] ? 'fill-amber-400 text-amber-500' : 'text-slate-200')) }}
                                @endfor
                            </div>
                            <span class="text-[11px] font-semibold text-[#607A90]">{{ $set['difficultyLabel'] }}</span>
                        </div>
                    </div>

                    <div class="col-span-2 min-w-0 rounded-lg border border-[#E7EFF3] bg-[#F8FBFC] px-2.5 py-2 lg:col-span-1 lg:rounded-none lg:border-0 lg:bg-transparent lg:p-0">
                        <span class="mb-0.5 block text-[10px] font-bold uppercase tracking-wide text-[#6B8295] lg:hidden">Điểm TB</span>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-black text-[#123B68]">{{ $set['avgPercent'] === null ? '—' : $set['avgPercent'].'%' }}</span>
                            <span class="text-[9px] font-bold uppercase tracking-wide text-[#6B8295]">ĐIỂM</span>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-[#EAF0F3]" aria-label="Điểm trung bình {{ $gstAvg }}%">
                            <div class="h-full rounded-full bg-[#2F8A6B] transition-all" style="width: {{ $gstAvg }}%"></div>
                        </div>
                        <p class="mt-1 text-[9px] text-[#6B8295]">{{ number_format($set['attemptCount']) }} lượt làm toàn hệ thống</p>
                        @if ($set['myBest'])
                            <p class="mt-1 flex items-center gap-1 text-[9px] font-bold text-[#2F8A6B]">
                                {{ $oiIcon('user-check', 'h-3 w-3 shrink-0') }}
                                Bạn: cao nhất {{ $set['myBest'] }} điểm
                            </p>
                        @endif
                    </div>
                </div>

                {{-- Hành động — bấm là vào làm bài ngay (chưa đăng nhập thì sang đăng nhập, như bài lập trình). --}}
                <div class="col-span-1 flex items-center justify-end gap-2 border-t border-[#E7EFF3] pt-2 lg:col-auto lg:flex-col lg:items-stretch lg:gap-1 lg:border-t-0 lg:pt-0">
                    <a href="{{ $gstHref }}" @click="withReturn($event)"
                       class="flex min-h-9 min-w-0 flex-1 items-center justify-center gap-1 rounded-lg px-2 py-1.5 text-[12px] font-bold text-white shadow-none transition-colors active:scale-[.98] lg:w-full bg-[#2F8A6B] hover:bg-[#28795E]">
                        {{ $oiIcon('play', 'h-3.5 w-3.5') }}
                        <span>Làm bài</span>
                    </a>
                </div>
            </div>
        @endforeach

        <button type="button" x-show="gstVisibleIds.length > gstShow" @click="gstShow += 20"
                class="w-full border-t border-[#E7EFF3] bg-[#F4F8FB] py-2.5 text-[12px] font-bold text-[#126F91]" style="order: 99999"
                x-text="'Xem thêm (' + (gstVisibleIds.length - gstShow) + ' bài)'"></button>
    </div>

    <div x-show="gstVisibleIds.length === 0" class="rounded-3xl border border-dashed border-[#C9DFE8] bg-white p-10 text-center">
        <x-lucide name="search" class="mx-auto h-9 w-9 text-[#9DC8D7]" />
        <h2 class="mt-3 text-sm font-black text-[#123B68]">{{ $gstRows === [] ? 'Chưa có bài phổ thông nào' : 'Không có bài phổ thông phù hợp' }}</h2>
        <p class="mt-1 text-xs text-[#607A90]">{{ $gstRows === [] ? 'Các bài trắc nghiệm, điền từ… nhiều câu sẽ hiện ở đây khi được phát hành.' : 'Thử đổi chuyên đề, độ khó hoặc từ khoá tìm kiếm.' }}</p>
        <button type="button" @click="resetProblemFilters(); activeTab = 'general'" class="mt-4 text-[11px] font-bold text-[#126F91] hover:underline">Xóa bộ lọc</button>
    </div>
</div>
