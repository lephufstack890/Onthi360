{{-- ═══════════════ POPUP CHI TIẾT LỚP (trang Lớp học công khai) ═══════════════
     SỬA 18/9 (khách yêu cầu: "khi bấm đăng ký học thì nó hiển thị popup UI như source mới").
     Dựng theo education-main/src/components/ClassDetailModal.jsx.

     Đây là MẢNH giao diện, nạp riêng theo từng lớp khi người dùng bấm (route
     courses.classDetail) — xem App\Services\Public\CourseService::classDetailData().

     KHÁC bản mẫu ở danh sách tab, và là chủ ý: bản mẫu là popup dành cho người ĐÃ Ở TRONG LỚP
     nên có đủ Bài tập / Tài liệu / Học liệu / Thông báo / Thành viên. Popup này bật ra ở trang
     CÔNG KHAI cho người CHƯA vào lớp — mấy mục đó là dữ liệu nội bộ của lớp, người ngoài không
     có quyền xem (AccessGateService::canAccessClassRoom), in ra là lộ dữ liệu. Nên chỉ giữ 4 tab
     dựng được từ dữ liệu vốn đã công khai: Tổng quan · Lịch học · Giáo viên · Đánh giá.

     Markup CỐ TÌNH không dùng Alpine: mảnh này được nhét vào trang bằng innerHTML, mà Alpine 3
     không tự khởi tạo cây DOM mới. Tab chuyển bằng data-class-tab / data-class-panel, do
     partials/courses-script.blade.php bắt sự kiện ở thẻ bao ngoài. --}}
@php
    $cl = $class;
    $fallbackCovers = ['course-img-1.png', 'course-img-2.png', 'course-img-3.png', 'course-img-4.png', 'course-img-5.png'];
    $cover = $cl['image'] ?: asset('assets/'.$fallbackCovers[$cl['courseId'] % count($fallbackCovers)]);

    [$stateLabel, $stateChip, $stateIcon] = $cl['isMember']
        ? ['Bạn đang học lớp này', 'bg-emerald-50 text-emerald-700', 'check-circle-2']
        : ($cl['isPending']
            ? ['Đang chờ giáo viên duyệt', 'bg-[#FFF7E3] text-[#8A6A2A]', 'clock']
            : ['Đang mở đăng ký', 'bg-sky-50 text-[#126F91]', 'user-plus']);

    $seatsLabel = $cl['capacity']
        ? number_format($cl['studentsCount']).' / '.number_format($cl['capacity']).' học sinh'
        : number_format($cl['studentsCount']).' học sinh';

    $intro = $courseDescription !== '' ? $courseDescription : ($cl['subtitle'] ?: null);
@endphp

{{-- ══════ ĐẦU POPUP ══════ --}}
<div class="relative shrink-0 border-b border-sky-100 bg-white">
    <div class="h-1 bg-[#126F91]"></div>

    <button type="button" data-class-detail-close aria-label="Đóng chi tiết lớp học"
            class="absolute right-3 top-3 grid h-9 w-9 place-items-center rounded-xl border border-slate-200 bg-white text-slate-500 shadow-sm transition-colors hover:border-sky-200 hover:bg-sky-50 hover:text-[#126F91]">
        <x-lucide name="x" class="h-5 w-5" />
    </button>

    <div class="flex flex-col items-start gap-3 border-b border-[#F0D99D] bg-[#FFF9E9] p-4 pr-14 sm:flex-row sm:items-center sm:p-5 sm:pr-16">
        <img src="{{ $cover }}" alt="Lớp {{ $cl['name'] }}" loading="eager" decoding="async"
             class="h-16 w-16 shrink-0 rounded-2xl border border-sky-100 object-cover shadow-sm sm:h-20 sm:w-20">

        <div class="min-w-0 flex-1">
            <div class="mb-2 flex flex-wrap items-center gap-1.5">
                @if ($cl['tag'])
                    <span class="rounded-full border border-amber-100 bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700">{{ $cl['tag'] }}</span>
                @endif
                <span class="rounded-full border border-sky-100 bg-sky-50 px-2 py-0.5 text-[10px] font-bold text-[#126F91]">Mã lớp: {{ $cl['code'] }}</span>
                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $stateChip }}">
                    <x-lucide :name="$stateIcon" class="h-3.5 w-3.5" />{{ $stateLabel }}
                </span>
            </div>

            <h2 class="max-w-2xl text-[16px] font-bold leading-5 text-[#123B68] sm:leading-6">{{ $cl['name'] }}</h2>

            <p class="mt-1 line-clamp-1 text-xs text-slate-500">
                Giảng viên: <strong>{{ $cl['teacherName'] ?: 'Chưa phân công' }}</strong>
            </p>

            <div class="mt-1.5 flex items-center gap-1.5 text-[11px] font-semibold text-amber-600">
                @if ($cl['average'] !== null)
                    <x-lucide name="star" class="h-3 w-3 fill-amber-400 text-amber-400" />
                    <span>{{ number_format($cl['average'], 1) }}</span>
                    <span class="font-medium text-slate-400">· {{ $cl['count'] }} đánh giá đã duyệt</span>
                @else
                    <span class="font-medium text-slate-400">Lớp chưa có đánh giá nào</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Dải tab --}}
    <div class="flex items-center gap-1 overflow-x-auto border-t border-sky-100 px-3 py-2 no-scrollbar sm:px-5">
        @foreach ([['overview', 'Tổng quan', 'book-open'], ['schedule', 'Lịch học', 'calendar'], ['teachers', 'Giáo viên', 'user-round'], ['reviews', 'Đánh giá', 'star']] as [$tabId, $tabLabel, $tabIcon])
            <button type="button" data-class-tab="{{ $tabId }}"
                    class="flex items-center gap-1.5 whitespace-nowrap rounded-xl px-2.5 py-1.5 text-xs font-bold transition-all {{ $loop->first ? 'bg-[#126F91] text-white shadow-sm' : 'text-slate-500 hover:bg-sky-50 hover:text-[#126F91]' }}">
                <x-lucide :name="$tabIcon" class="h-3.5 w-3.5" />
                <span>{{ $tabLabel }}{{ $tabId === 'reviews' && $cl['average'] !== null ? ' (★ '.number_format($cl['average'], 1).')' : '' }}</span>
            </button>
        @endforeach
    </div>
    <p class="px-3 pb-2 text-[11px] font-medium text-slate-400 sm:hidden">Vuốt ngang để xem thêm mục</p>
</div>

{{-- ══════ THÂN POPUP ══════ --}}
<div class="flex-1 overflow-y-auto bg-[#F8FAFB] p-4 sm:p-5">

    {{-- ── TAB 1: TỔNG QUAN ── --}}
    <div data-class-panel="overview" class="flex flex-col gap-4">
        @if ($intro)
            <div class="rounded-2xl border border-sky-100 bg-white p-4">
                <h4 class="mb-2 text-[13px] font-bold text-[#0B3C78]">Tổng quan lớp học</h4>
                <p class="text-xs leading-relaxed text-slate-600">{{ $intro }}</p>
                {{-- Giới thiệu đã cắt gọn cho vừa popup — bản đầy đủ ở trang chi tiết khoá. --}}
                <a href="{{ $cl['href'] }}" class="mt-2 inline-flex items-center gap-1 text-[11px] font-bold text-[#126F91] hover:underline">
                    Xem chi tiết khoá học<x-lucide name="chevron-right" class="h-3 w-3" />
                </a>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="flex items-center gap-3 rounded-2xl border border-[#C5DEE7] bg-[#E5F1F4] p-3.5 shadow-[0_4px_12px_rgba(45,127,163,0.08)]">
                <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#F4FAFB] text-[#126F91]">
                    <x-lucide name="clock" class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold text-[#6B8195]">Số buổi đã xếp lịch</p>
                    <p class="text-xs font-bold text-slate-800">{{ $cl['sessionsCount'] > 0 ? $cl['sessionsCount'].' buổi' : 'Chưa xếp lịch' }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3 rounded-2xl border border-[#E4CF9B] bg-[#F4E8C7] p-3.5 shadow-[0_4px_12px_rgba(217,168,61,0.1)]">
                <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#FFF5D9] text-[#8A641F]">
                    <x-lucide name="users" class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold text-[#6B8195]">Sĩ số</p>
                    <p class="text-xs font-bold text-slate-800">{{ $seatsLabel }}</p>
                </div>
            </div>
        </div>

        {{-- Bảng thông tin lớp — dòng nào chưa có dữ liệu thì giấu hẳn, không in ô rỗng. --}}
        <div class="rounded-2xl border border-sky-100 bg-white p-4">
            <h4 class="mb-3 text-[13px] font-bold text-[#0B3C78]">Thông tin lớp</h4>
            <dl class="grid grid-cols-1 gap-x-4 gap-y-2.5 sm:grid-cols-2">
                @foreach ([
                    ['book-open', 'Khoá học', $cl['courseTitle']],
                    ['graduation-cap', 'Khối lớp', $cl['grade']],
                    ['calendar-days', 'Lịch học', $cl['scheduleNote']],
                    ['map-pin', 'Nơi học', $cl['location']],
                    ['video', 'Hình thức', $cl['format']],
                    ['map-pin', 'Địa chỉ', $cl['address']],
                    ['wallet', 'Học phí trọn khoá', $cl['priceLabel']],
                ] as [$rowIcon, $rowLabel, $rowValue])
                    @if (filled($rowValue))
                        <div class="flex min-w-0 items-start gap-2">
                            <x-lucide :name="$rowIcon" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-[#2D7FA3]" />
                            <div class="min-w-0">
                                <dt class="text-[10px] font-semibold text-[#6B8195]">{{ $rowLabel }}</dt>
                                <dd class="text-xs font-bold text-slate-800">{{ $rowValue }}</dd>
                            </div>
                        </div>
                    @endif
                @endforeach
            </dl>
        </div>

        {{-- Bản mẫu có khối "Tiến độ của bạn". Người CHƯA vào lớp thì chưa có tiến độ nào để in —
             thay bằng đúng trạng thái đăng ký của chính họ, là thứ họ đang cần biết ở màn này. --}}
        <div class="rounded-2xl border border-[#C5DEE7] bg-[#E5F1F4] p-4 shadow-[0_4px_12px_rgba(45,127,163,0.08)]">
            <h5 class="text-[13px] font-bold text-[#0B3C78]">Trạng thái của bạn</h5>
            <p class="mt-0.5 text-xs text-[#536D86]">
                @if ($cl['isMember'])
                    Bạn đã là thành viên lớp này — vào học được ngay.
                @elseif ($cl['isPending'])
                    Yêu cầu đăng ký đã gửi, đang chờ giáo viên của lớp duyệt. Được duyệt là bạn vào học ngay và có thông báo.
                @elseif ($isGuest)
                    Đăng nhập bằng tài khoản học sinh để gửi yêu cầu đăng ký lớp này.
                @elseif ($canRequestJoin)
                    Bấm "Đăng ký học" bên dưới để gửi yêu cầu — giáo viên của lớp duyệt là bạn vào học được.
                @else
                    Chỉ tài khoản học sinh mới gửi được yêu cầu đăng ký lớp.
                @endif
            </p>
        </div>
    </div>

    {{-- ── TAB 2: LỊCH HỌC ── --}}
    <div data-class-panel="schedule" hidden class="flex flex-col gap-3">
        @if ($cl['scheduleNote'])
            <div class="flex items-start gap-2.5 rounded-2xl border border-sky-100 bg-white p-4">
                <x-lucide name="calendar-days" class="mt-0.5 h-4 w-4 shrink-0 text-[#2D7FA3]" />
                <div>
                    <p class="text-[10px] font-semibold text-[#6B8195]">Lịch định kỳ</p>
                    <p class="text-xs font-bold text-slate-800">{{ $cl['scheduleNote'] }}</p>
                </div>
            </div>
        @endif

        @forelse ($sessions as $session)
            <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-sky-100 bg-white p-3.5">
                <div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#EAF5F8] text-[#126F91]">
                    <x-lucide name="calendar" class="h-5 w-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-bold text-slate-800">{{ $session['topic'] }}</p>
                    <p class="mt-0.5 text-[11px] text-slate-500">
                        {{ $session['weekdayLabel'] }}, {{ $session['dateLabel'] }} · {{ $session['timeLabel'] }}
                        @if ($session['location'])
                            · {{ $session['location'] }}
                        @endif
                    </p>
                </div>
                <span class="shrink-0 rounded-lg border border-[#D4EDE2] bg-[#EFF9F5] px-2 py-1 text-[10px] font-bold text-[#397C68]">Sắp diễn ra</span>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-sky-200 bg-white p-8 text-center">
                <x-lucide name="calendar" class="mx-auto h-8 w-8 text-[#9DC8D7]" />
                <p class="mt-2 text-xs font-bold text-slate-700">Chưa có buổi học nào sắp diễn ra</p>
                <p class="mt-1 text-[11px] text-slate-500">Giáo viên xếp lịch buổi mới là hiện ngay ở đây.</p>
            </div>
        @endforelse
    </div>

    {{-- ── TAB 3: GIÁO VIÊN ── --}}
    <div data-class-panel="teachers" hidden class="flex flex-col gap-3">
        <div class="flex items-center gap-3 rounded-2xl border border-sky-100 bg-white p-4">
            <x-ws.avatar :name="$cl['teacherName'] ?: 'Chưa phân công'" size="lg" />
            <div class="min-w-0">
                <p class="text-[13px] font-bold text-[#0B3C78]">{{ $cl['teacherName'] ?: 'Chưa phân công giáo viên' }}</p>
                <p class="mt-0.5 text-[11px] text-slate-500">Giảng viên phụ trách</p>
            </div>
        </div>

        @if (count($cl['assistantNames']) > 0)
            <div class="rounded-2xl border border-sky-100 bg-white p-4">
                <p class="mb-2.5 text-[10px] font-semibold uppercase tracking-wide text-[#6B8195]">Trợ giảng đồng hành</p>
                <div class="flex flex-col gap-2.5">
                    @foreach ($cl['assistantNames'] as $assistantName)
                        <div class="flex items-center gap-2.5">
                            <x-ws.avatar :name="$assistantName" size="sm" />
                            <p class="truncate text-xs font-bold text-slate-700">{{ $assistantName }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- ── TAB 4: ĐÁNH GIÁ ── --}}
    <div data-class-panel="reviews" hidden class="flex flex-col gap-3">
        @forelse ($reviews as $review)
            <div class="rounded-2xl border border-sky-100 bg-white p-4">
                <div class="flex items-start gap-3">
                    <x-ws.avatar :name="$review['name']" size="sm" />
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-xs font-bold text-slate-700">{{ $review['name'] }}</p>
                            <p class="text-[11px] text-slate-400">{{ $review['timeLabel'] }}</p>
                        </div>
                        <p class="mt-0.5 text-[13px] text-amber-500" aria-label="{{ $review['stars'] }} trên 5 sao">
                            {{ str_repeat('★', $review['stars']) }}{{ str_repeat('☆', 5 - $review['stars']) }}
                        </p>
                        @if ($review['comment'] !== '')
                            <p class="mt-1.5 text-xs leading-relaxed text-slate-600">{{ $review['comment'] }}</p>
                        @endif
                        @if ($review['adminReply'])
                            <div class="mt-2.5 rounded-xl border border-slate-100 bg-slate-50 p-2.5">
                                <p class="mb-1 text-[10px] font-semibold text-slate-500">Phản hồi từ Ban quản trị</p>
                                <p class="text-xs text-slate-600">{{ $review['adminReply'] }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-sky-200 bg-white p-8 text-center">
                <x-lucide name="star" class="mx-auto h-8 w-8 text-[#9DC8D7]" />
                <p class="mt-2 text-xs font-bold text-slate-700">Lớp chưa có đánh giá nào</p>
                <p class="mt-1 text-[11px] text-slate-500">Đánh giá hiện ở đây sau khi học viên của lớp gửi và Ban quản trị duyệt.</p>
            </div>
        @endforelse
    </div>
</div>

{{-- ══════ CHÂN POPUP ══════ --}}
<div class="flex shrink-0 items-center justify-between gap-3 border-t border-sky-100 bg-white p-3.5 sm:p-4">
    <span class="hidden text-[13px] font-medium text-slate-500 sm:inline">Khoá học có bản quyền của <strong>Ôn Thi 360</strong></span>

    <div class="flex w-full items-center justify-end gap-2 sm:w-auto">
        <button type="button" data-class-detail-close
                class="rounded-xl px-4 py-2 text-[13px] font-bold text-slate-600 transition-colors hover:bg-slate-100">Đóng</button>

        @if ($cl['isMember'])
            <a href="{{ route('student.classes.show', $cl['id']) }}"
               class="rounded-xl bg-[#126F91] px-4 py-2.5 text-[13px] font-bold text-white shadow-sm transition-colors hover:bg-[#0F607E]">Vào học →</a>
        @elseif ($cl['isPending'])
            <span class="cursor-not-allowed rounded-xl bg-amber-400 px-4 py-2.5 text-[13px] font-bold text-white shadow-sm">Đang chờ duyệt</span>
        @elseif ($canRequestJoin)
            {{-- Gửi yêu cầu THẬT — cùng đường đi với nút ngoài thẻ lớp, xem
                 App\Services\Student\ClassRoomService::requestJoin(). --}}
            <form method="POST" action="{{ route('student.classes.requestJoin', $cl['id']) }}">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-[#126F91] px-4 py-2.5 text-[13px] font-bold text-white shadow-sm transition-colors hover:bg-[#0F607E]">
                    <x-lucide name="user-plus" class="h-4 w-4" />Đăng ký học
                </button>
            </form>
        @elseif ($isGuest)
            <a href="{{ route('login') }}"
               class="inline-flex items-center gap-1.5 rounded-xl bg-[#126F91] px-4 py-2.5 text-[13px] font-bold text-white shadow-sm transition-colors hover:bg-[#0F607E]">
                <x-lucide name="log-in" class="h-4 w-4" />Đăng nhập để đăng ký
            </a>
        @else
            <a href="{{ $cl['href'] }}"
               class="rounded-xl border border-[#B8DCE6] bg-[#EAF5F8] px-4 py-2.5 text-[13px] font-bold text-[#126F91] transition-colors hover:bg-[#DDF1F6]">Xem khoá học</a>
        @endif
    </div>
</div>
