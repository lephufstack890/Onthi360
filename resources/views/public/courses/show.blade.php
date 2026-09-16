@extends('layouts.guest')

@section('title', $course->title)
@section('meta-description', \Illuminate\Support\Str::limit(strip_tags((string) $course->description), 155) ?: 'Khoá học '.$course->title.' trên Ôn Thi 360 — nội dung, lớp đang mở, học phí và đánh giá của học viên.')

@section('content')
{{-- ═══════════════ [COURSE-SHOW] CHI TIẾT KHOÁ HỌC ═══════════════
     SỬA 14/9 — CHỈ ĐỔI GIAO DIỆN: dựng lại theo ngôn ngữ thị giác của trang Khoá học và các
     trang công khai đã làm. Ba thứ được sửa kèm vì cùng là lỗi hiển thị:
       · ẢNH: bỏ picsum.photos (ảnh ngẫu nhiên từ máy chủ ngoài) — dùng ảnh bìa thật của khoá
         học, thiếu thì lấy bộ ảnh sẵn có như ở trang danh sách.
       · AVATAR GIÁO VIÊN: bỏ ui-avatars.com — dịch vụ này nhận HỌ TÊN THẬT của giáo viên qua
         URL ở mỗi lần tải trang. Thay bằng x-ws.avatar vẽ chữ cái đầu ngay tại chỗ.
     Logic, route và mọi điều kiện hiển thị giữ nguyên. --}}
    @php
        $classes = $classes ?? [];
        $ratingAverage = $ratingAverage ?? null;
        $ratingCount = $ratingCount ?? 0;
        $isStudent = $isStudent ?? false;
        $myClassRoomIdsInThisCourse = $myClassRoomIdsInThisCourse ?? [];
        $isEnrolledInThisCourse = count($myClassRoomIdsInThisCourse) > 0;

        // B6/C2 (15/9) — dải bậc trong lộ trình + nút mua thật.
        //
        // SỬA 15/9 (khách đổi ý, tạm dừng lộ trình công khai) — Public\CourseService::showData()
        // KHÔNG còn trả 'pathStrips' nữa, nên $pathStrips luôn rỗng và KHỐI B6 bên dưới không
        // bao giờ hiện. Cố ý GIỮ NGUYÊN mã khối B6: bật lại chỉ cần trả khoá đó về trong
        // showData() (xem App\Services\Public\LearningPathService::PUBLIC_ENABLED).
        //
        // Ngược lại, 'buyHref'/'priceLabel'/'chooseClassHref' (KHỐI C — mua khoá rồi chọn lớp)
        // VẪN được trả về và vẫn chạy: phần đó không dính gì tới lộ trình.
        $pathStrips = $pathStrips ?? [];
        $mainStrip = $pathStrips[0] ?? null;
        $buyHref = $buyHref ?? null;
        $priceLabel = $priceLabel ?? null;
        $chooseClassHref = $chooseClassHref ?? null;

        // Cùng bộ ảnh dự phòng với trang danh sách khoá học để hai màn nhìn liền mạch.
        $fallbackCovers = ['course-img-1.png', 'course-img-2.png', 'course-img-3.png', 'course-img-4.png', 'course-img-5.png'];
        $coverUrl = $course->cover_image_path
            ? asset('storage/'.$course->cover_image_path)
            : asset('assets/'.$fallbackCovers[$course->id % count($fallbackCovers)]);

        /*
         * SỬA 15/9 — 5 số liệu dưới đây do Public\CourseService::headlineFigures() tính từ
         * dữ liệu THẬT (class_enrollments + class_sessions), view chỉ in ra. Vẫn để ?? mặc
         * định phòng khi view được gọi từ chỗ khác chưa truyền đủ.
         */
        $totalStudents = $totalStudents ?? 0;
        $openClassCount = $openClassCount ?? count($classes);
        $sessionTotal = $sessionTotal ?? 0;
        $weekSpan = $weekSpan ?? 0;
        $sessionsPerWeek = $sessionsPerWeek ?? null;

        // Viên nhãn cam ở góc bảng thông tin (chỗ chữ "SASH" trong mẫu khách gửi): ưu tiên mã
        // bậc do quản trị đặt, không có thì lấy môn học. Không có cả hai thì giấu hẳn viên nhãn
        // chứ không in chữ chống chế.
        $brandChip = $course->level_code ?: $course->subject;

        // Câu tóm tắt dưới tiêu đề — lấy chữ thật từ mô tả, cắt gọn. Mô tả đầy đủ (có định
        // dạng) vẫn in nguyên ở khối "Giới thiệu khoá học" bên dưới.
        $shortIntro = \Illuminate\Support\Str::limit(trim(strip_tags((string) $course->description)), 160);
    @endphp

<div class="max-w-[1780px] w-full mx-auto px-3 sm:px-5 lg:px-6 2xl:px-10 py-3 sm:py-5">
{{-- gap-5 = 20px, đúng bằng mt-5 mà source dùng giữa các <section>. --}}
<div class="flex flex-col gap-5">

    {{-- ══════ 1. ĐƯỜNG DẪN ══════
         SỬA 15/9 — đổi nút "Quay lại" thành breadcrumb đủ 3 cấp đúng mẫu khách gửi: người vào
         thẳng từ Google cần biết mình đang đứng ở đâu, không chỉ cần một lối lùi. --}}
    {{-- -mb-2 bù lại: khung ngoài của trang giãn các khối 20px, còn source để breadcrumb
         cách khối dưới đúng 12px (mb-3). Trừ 8px là ra đúng khoảng của source. --}}
    <nav aria-label="Breadcrumb" class="-mb-2 flex items-center gap-2 px-1 text-[11px] font-medium text-[#7890A3] sm:text-xs">
        <a href="{{ route('home') }}" class="transition-colors hover:text-[#126F91]">Trang chủ</a>
        <x-lucide name="chevron-right" class="h-3.5 w-3.5 shrink-0" />
        <a href="{{ route('courses.index') }}" class="transition-colors hover:text-[#126F91] text-[#123B68]">Lớp học</a>
        <x-lucide name="chevron-right" class="h-3.5 w-3.5 shrink-0" />
        <span class="min-w-0 truncate text-[#123B68]">{{ $course->title }}</span>
    </nav>

    {{-- ══════ B6 · DẢI BẬC TRONG LỘ TRÌNH ══════
         Đặt NGAY TRÊN hero: người vào từ tìm kiếm Google thường rơi thẳng vào một khoá lẻ mà
         không biết nó nằm ở đâu trong cả chặng đường. Dòng "Bậc 2/6" trả lời câu đó trước khi
         họ đọc bất cứ thứ gì khác.
         Một khoá dùng lại được ở nhiều lộ trình — in dải đầu tiên, các lộ trình còn lại gom
         thành một dòng liên kết nhỏ phía dưới. --}}
    @if ($mainStrip)
        <section class="flex flex-col gap-2.5 rounded-3xl border bg-white p-3.5 shadow-[0_2px_10px_rgba(0,100,220,0.04)] sm:flex-row sm:items-center sm:gap-3"
                 style="border-color: {{ $mainStrip['color']['ring'] }}">

            <a href="{{ $mainStrip['pathHref'] }}" class="flex min-w-0 flex-1 items-center gap-3">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl text-white"
                      style="background: {{ $mainStrip['color']['solid'] }}">
                    <span class="text-[15px] font-black leading-none">{{ $mainStrip['position'] }}</span>
                </span>
                <span class="min-w-0">
                    <span class="block text-[10px] font-black uppercase tracking-[.09em]" style="color: {{ $mainStrip['color']['ink'] }}">
                        Bậc {{ $mainStrip['position'] }}/{{ $mainStrip['total'] }}
                    </span>
                    <span class="mt-0.5 block truncate text-[13px] font-bold text-[#0B3C78]">
                        Lộ trình {{ $mainStrip['pathTitle'] }}
                    </span>
                </span>
            </a>

            {{-- Sang bậc trước / bậc sau ngay tại chỗ, khỏi phải quay về trang lộ trình. --}}
            <div class="flex shrink-0 flex-wrap items-center gap-1.5">
                @if ($mainStrip['prev'])
                    <a href="{{ $mainStrip['prev']['href'] }}" title="{{ $mainStrip['prev']['title'] }}"
                       class="inline-flex min-h-9 max-w-[190px] items-center gap-1 rounded-xl border border-sky-100 bg-white px-2.5 text-[11px] font-bold text-slate-600 transition-colors hover:border-sky-200 hover:text-blue-700">
                        <x-lucide name="chevron-left" class="h-3.5 w-3.5 shrink-0" />
                        <span class="truncate">{{ $mainStrip['prev']['levelCode'] ?: 'Bậc trước' }}</span>
                    </a>
                @endif

                <a href="{{ $mainStrip['pathHref'] }}"
                   class="inline-flex min-h-9 items-center gap-1 rounded-xl border border-sky-100 bg-sky-50 px-2.5 text-[11px] font-bold text-blue-700 transition-colors hover:bg-sky-100">
                    <x-lucide name="route" class="h-3.5 w-3.5" />Cả lộ trình
                </a>

                @if ($mainStrip['next'])
                    <a href="{{ $mainStrip['next']['href'] }}" title="{{ $mainStrip['next']['title'] }}"
                       class="inline-flex min-h-9 max-w-[190px] items-center gap-1 rounded-xl border border-sky-100 bg-white px-2.5 text-[11px] font-bold text-slate-600 transition-colors hover:border-sky-200 hover:text-blue-700">
                        <span class="truncate">{{ $mainStrip['next']['levelCode'] ?: 'Bậc sau' }}</span>
                        <x-lucide name="chevron-right" class="h-3.5 w-3.5 shrink-0" />
                    </a>
                @endif
            </div>
        </section>

        @if (count($pathStrips) > 1)
            <p class="-mt-2 flex flex-wrap items-center gap-1.5 text-[11px] text-slate-500">
                <span>Khoá này còn nằm trong:</span>
                @foreach (array_slice($pathStrips, 1) as $other)
                    <a href="{{ $other['pathHref'] }}" class="font-bold text-blue-600 hover:underline">{{ $other['pathTitle'] }} (bậc {{ $other['position'] }}/{{ $other['total'] }})</a>
                @endforeach
            </p>
        @endif
    @endif

    {{-- ══════ 2. BẢNG ĐẦU TRANG: ẢNH + THÔNG TIN ══════
         SỬA 15/9 (khách: "copy design màn này từ source, làm design in đúc vậy") — chép ĐÚNG
         TỪNG CHUỖI CLASS của education-main/src/components/RoadmapPage.jsx: cùng lưới
         1.42fr/0.88fr, cùng gap-5, cùng bo góc, cùng đổ bóng, cùng cỡ chữ và mã màu.
         Chỉ dữ liệu là của khoá học thay vì lộ trình. --}}
    <section class="grid items-stretch gap-5 lg:grid-cols-[minmax(0,1.42fr)_minmax(300px,0.88fr)]">

        <div class="h-full overflow-hidden rounded-3xl border border-[#DDEAF0] bg-white shadow-[0_4px_18px_rgba(34,105,132,0.06)]">
            <div class="aspect-[16/9] overflow-hidden bg-[#fffaf0]">
                <img src="{{ $coverUrl }}" alt="Ảnh giới thiệu khoá học {{ $course->title }}"
                     loading="eager" decoding="async" class="block h-full w-full object-cover object-center">
            </div>
        </div>

        <div class="flex h-full min-w-0 flex-col justify-center rounded-3xl border border-[#DDEAF0] bg-white p-4 shadow-[0_4px_18px_rgba(34,105,132,0.06)] sm:p-5 lg:p-5">

            @if ($brandChip)
                <span class="w-fit rounded-full bg-[#ef744d] px-3 py-0.5 text-[10px] font-bold tracking-[0.14em] text-white">{{ mb_strtoupper($brandChip) }}</span>
            @endif

            <h1 class="mt-2 break-words text-2xl font-bold leading-tight tracking-tight text-[#123B68]">{{ $course->title }}</h1>

            @if ($shortIntro !== '')
                <p class="mt-1.5 type-body leading-5 text-[#2D7FA3]">{{ $shortIntro }}</p>
            @endif

            {{-- Ô "Mục tiêu đích" — chỉ hiện khi quản trị đã nhập câu kết quả cho khoá
                 (courses.outcome). Chưa nhập thì giấu hẳn, không dựng ô rỗng cho đủ mẫu. --}}
            @if ($course->outcome)
                <div class="mt-2.5 flex min-w-0 items-center gap-3 rounded-2xl border border-[#f1dfad] bg-[#fff9e6] p-2.5">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-[#f4cd67] text-[#8b6512]">
                        <x-lucide name="target" class="h-4 w-4" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-[#2D7FA3]">Mục tiêu đích</p>
                        <p class="mt-0.5 truncate text-sm font-semibold text-[#126F91]">{{ $course->outcome }}</p>
                    </div>
                </div>
            @endif

            {{-- 4 ô số liệu — đúng thành phần <Stat> của source (viền, nền, cỡ chữ, khoảng đệm).
                 Ô nào chưa có dữ liệu thật thì in "—" chứ không in số 0: "0 buổi" khiến phụ
                 huynh tưởng khoá rỗng, "—" thì hiểu là chưa công bố. --}}
            @php
                $figureTiles = [
                    ['label' => 'Khối lớp', 'value' => $course->grade ?: '—', 'icon' => 'graduation-cap'],
                    ['label' => 'Lớp đang mở', 'value' => $openClassCount > 0 ? $openClassCount : '—', 'icon' => 'school'],
                    ['label' => 'Tổng buổi', 'value' => $sessionTotal > 0 ? $sessionTotal : '—', 'icon' => 'calendar-days'],
                    ['label' => 'Học viên', 'value' => $totalStudents > 0 ? $totalStudents : '—', 'icon' => 'users'],
                ];
            @endphp
            <div class="mt-2.5 grid grid-cols-2 gap-2">
                @foreach ($figureTiles as $tile)
                    <div class="rounded-2xl border border-[#DDEAF0] bg-[#F8FBFC] px-2.5 py-2 text-[#2D7FA3]">
                        <div class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-[0.06em]">
                            <x-lucide :name="$tile['icon']" class="h-3.5 w-3.5 shrink-0" />{{ $tile['label'] }}
                        </div>
                        <p class="mt-1 text-[15px] font-semibold text-[#123B68]">{{ $tile['value'] }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Dòng nhịp học — đúng dải "Học theo thứ tự từ bậc 1 đến bậc 6" của source. Chỉ
                 hiện khi các lớp đã xếp lịch thật, vì số tuần suy ra từ khoảng cách buổi đầu tới
                 buổi cuối (xem CourseService::headlineFigures). --}}
            @if ($weekSpan > 0)
                <div class="mt-2.5 flex min-w-0 items-center gap-3 rounded-2xl border border-[#e7edf2] bg-[#fbfcfe] px-3 py-2 text-[11px] font-semibold text-[#2D7FA3]">
                    <x-lucide name="clock-3" class="h-3.5 w-3.5 shrink-0 text-[#e27a57]" />
                    <span>Học khoảng {{ $weekSpan }} tuần{{ $sessionsPerWeek ? ' · '.rtrim(rtrim(number_format($sessionsPerWeek, 1), '0'), '.').' buổi/tuần' : '' }}</span>
                </div>
            @endif

            {{-- Cụm 2 nút — đúng hình dáng của source: nút chính chiếm hết phần còn lại
                 (flex-1), nút "Tư vấn" viền trắng đứng cạnh.

                 SỬA 15/9 (khách: "chỗ button này chỉ cần hiển thị xem các lớp đang mở là
                 xong, in đúc design") — BỎ HẲN việc đổi chữ theo vai trò. Nút luôn là
                 "Xem các lớp đang mở" đúng một chữ như bản mẫu, ai xem cũng thấy giống nhau. --}}
            @php
                $heroBtn = 'inline-flex min-h-10 min-w-0 flex-1 items-center justify-center gap-2 rounded-xl bg-[#2f7695] px-4 text-[11px] font-semibold text-white shadow-[0_8px_18px_rgba(47,118,149,0.2)] transition hover:-translate-y-0.5 hover:bg-[#24627e]';

                $showClassList = \App\Services\Public\CourseService::SHOW_CLASS_LIST;
                $showJoinByCode = \App\Services\Public\CourseService::SHOW_JOIN_BY_CODE;

                /*
                 * SỬA 16/9 (khách: "bấm Xem các lớp đang mở thì qua trang lớp học hiển thị đúng
                 * lớp của khoá đó") — nút dẫn sang TRANG LỚP HỌC đã LỌC SẴN theo đúng khoá này,
                 * qua tham số ?khoa=<id> mà Public\CourseController::index đã biết đọc.
                 *
                 * Cố ý KHÔNG neo xuống khối "Các lớp đang triển khai" ngay trong trang nữa, kể
                 * cả khi khối đó được bật lại: trang Lớp học mới là nơi liệt kê đầy đủ từng lớp
                 * kèm lịch học, sĩ số, giáo viên và nút đăng ký.
                 */
                $primaryHref = route('courses.index', ['khoa' => $course->id]);
            @endphp
            <div class="mt-2.5 flex flex-col gap-2 sm:flex-row">
                <a href="{{ $primaryHref }}" class="{{ $heroBtn }}">
                    Xem các lớp đang mở <x-lucide name="arrow-right" class="h-4 w-4" />
                </a>

                <a href="{{ route('info.index') }}"
                   class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-[#dbe7eb] bg-white px-5 text-[11px] font-semibold text-[#2D7FA3] transition hover:border-[#a9d5dd] hover:bg-[#f2fafb]">
                    <x-lucide name="phone" class="h-3.5 w-3.5" /> Tư vấn
                </a>
            </div>

            {{-- Dòng xanh cuối bảng — đếm lớp thật, không có lớp nào thì nói thẳng. --}}
            @if ($openClassCount > 0)
                <p class="mt-1 text-center text-[10px] font-semibold text-[#56a17f]">Đang có {{ $openClassCount }} lớp mở · Vào học được ngay</p>
            @else
                <p class="mt-1 text-center text-[10px] font-semibold text-[#A2874A]">Chưa có lớp nào đang mở · Để lại liên hệ để được báo khi mở lớp</p>
            @endif
        </div>
    </section>

    {{-- ══════ 3. GIỚI THIỆU KHOÁ HỌC ══════
         Đúng thành phần <InfoCard> + khung bài viết nền kem của source. Mô tả là HTML do quản
         trị soạn bằng trình soạn thảo nên in bằng {!! !!}; class .course-intro ở cuối trang
         chép lại cách source tô đậm/đánh dấu danh sách bên trong bài viết. --}}
    @if (trim(strip_tags((string) $course->description)) !== '')
        <article class="min-w-0 rounded-2xl border border-[#DDEAF0] bg-white p-4 shadow-[0_4px_18px_rgba(34,105,132,0.05)] sm:p-5">
            <div class="flex items-center gap-2.5">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#eaf6fb] text-[#2c789a]">
                    <x-lucide name="target" class="h-4 w-4" />
                </span>
                <h2 class="type-section-title text-lg font-bold sm:text-xl">Giới thiệu khoá học</h2>
            </div>
            <div class="mt-3 type-body">
                <div class="course-intro max-w-none space-y-4 rounded-2xl border border-[#efe3c8] bg-gradient-to-br from-[#fffaf0] via-[#fffdf8] to-[#fff8e8] px-4 py-4 text-[13px] font-normal leading-6 text-[#3E79A4] sm:px-5 sm:py-5">
                    {!! $course->description !!}
                </div>
            </div>
        </article>
    @endif

    {{-- ══════ 4. DẢI TƯ VẤN ══════ --}}
    <section class="flex flex-col items-start justify-between gap-3 rounded-2xl border border-[#cfe4e8] bg-gradient-to-r from-[#e9f7f7] via-[#f4fbfb] to-[#fff8e6] p-4 sm:flex-row sm:items-center">
        <div class="flex items-start gap-2.5">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-[#2c789a] shadow-sm">
                <x-lucide name="users" class="h-4 w-4" />
            </span>
            <div>
                <h2 class="type-section-title">Chưa biết con nên bắt đầu từ đâu?</h2>
                <p class="type-body mt-1 text-[#617A8E]">Đội ngũ tư vấn sẽ giúp gia đình chọn lớp phù hợp với sức học của con.</p>
            </div>
        </div>
        <a href="{{ route('info.index') }}"
           class="inline-flex min-h-10 shrink-0 items-center gap-2 rounded-xl bg-[#2f7695] px-4 text-xs font-bold text-white transition hover:bg-[#24627e]">
            Nhận tư vấn miễn phí <x-lucide name="arrow-right" class="h-4 w-4" />
        </a>
    </section>

    {{-- ══════ 5. CÁC LỚP ĐANG TRIỂN KHAI ══════
         SỬA 15/9 (khách yêu cầu) — ĐANG ẨN, xem Public\CourseService::SHOW_CLASS_LIST.
         ẨN CHỨ KHÔNG XOÁ: đổi hằng đó thành true là hiện lại y nguyên, và nút chính ở đầu
         trang cũng tự neo xuống đây trở lại. --}}
    @if ($showClassList)
    <section id="lop-dang-mo" class="scroll-mt-20 rounded-3xl border border-sky-100 bg-white p-4 shadow-[0_2px_10px_rgba(0,100,220,0.04)] sm:p-5">
        <div class="mb-3 flex items-center gap-2.5">
            <span class="grid h-8.5 w-8.5 place-items-center rounded-xl bg-blue-600 text-white shadow-2xs">
                <x-lucide name="school" class="h-4.5 w-4.5" />
            </span>
            <div>
                <h2 class="type-section-title">Các lớp đang triển khai</h2>
                <p class="type-body mt-0.5 text-slate-500">Chọn lớp phù hợp với lịch học của bạn</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @forelse ($classes as $cl)
                <div class="flex flex-col justify-between rounded-2xl border border-sky-100 bg-[#F8FBFE] p-3.5 transition-all hover:border-sky-200 hover:shadow-md">
                    <div class="flex items-start justify-between gap-2.5">
                        <p class="type-card-title min-w-0 flex-1">{{ $cl['name'] }}</p>
                        @if ($cl['isMember'] ?? false)
                            <span class="inline-flex shrink-0 items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">
                                <x-lucide name="check-circle-2" class="h-3 w-3" />Đã tham gia
                            </span>
                        @else
                            <span class="inline-flex shrink-0 items-center rounded-full border border-sky-200 bg-white px-2 py-0.5 text-[10px] font-bold text-sky-700">Đang mở</span>
                        @endif
                    </div>

                    <div class="mt-3 flex items-center gap-2 border-t border-sky-100 pt-2.5">
                        <x-ws.avatar :name="$cl['teacher']" size="sm" />
                        <span class="min-w-0 flex-1">
                            <span class="type-meta block text-slate-400">Giáo viên phụ trách</span>
                            <span class="block truncate text-[12px] font-bold text-slate-700">{{ $cl['teacher'] }}</span>
                        </span>
                    </div>

                    <p class="type-meta mt-2 flex items-center gap-1 text-slate-500">
                        <x-lucide name="users" class="h-3 w-3 shrink-0" />{{ $cl['studentsCount'] }} học sinh đã tham gia
                    </p>
                </div>
            @empty
                <div class="sm:col-span-2 xl:col-span-3">
                    <div class="rounded-2xl border border-dashed border-sky-200 bg-[#F8FBFE] p-8 text-center">
                        <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-white text-sky-600 shadow-2xs">
                            <x-lucide name="school" class="h-6 w-6" />
                        </span>
                        <p class="type-card-title mt-3">Chưa có lớp nào đang triển khai</p>
                        <p class="type-body mt-1 text-slate-500">Quay lại sau hoặc chọn lớp học khác.</p>
                        <a href="{{ route('courses.index') }}" class="type-action mt-3 inline-block text-blue-600 hover:underline">Xem các lớp khác →</a>
                    </div>
                </div>
            @endforelse
        </div>
    </section>
    @endif

    {{-- ══════ 6. THAM GIA BẰNG MÃ LỚP ══════
         SỬA 15/9 (khách yêu cầu) — ĐANG ẨN, xem Public\CourseService::SHOW_JOIN_BY_CODE.
         Ẩn khối này cũng ẩn theo ô nhập mã lớp và dải "Chọn lớp" của luồng mua khoá; học sinh
         vẫn nhập mã được ở khu Khoá học của học sinh nên không mất đường nào. --}}
    @if ($showJoinByCode && $isStudent)
        <section id="tham-gia-lop" class="scroll-mt-20 rounded-3xl border border-sky-100 bg-white p-4 shadow-[0_2px_10px_rgba(0,100,220,0.04)] sm:p-5">
            <div class="mb-3 flex items-center gap-2.5">
                <span class="grid h-8.5 w-8.5 place-items-center rounded-xl bg-amber-50 text-amber-700">
                    <x-lucide name="key-round" class="h-4.5 w-4.5" />
                </span>
                <div>
                    <h2 class="type-section-title">Có mã lớp?</h2>
                    <p class="type-body mt-0.5 text-slate-500">Giáo viên cấp mã riêng cho từng lớp — nhập đúng mã để tham gia ngay</p>
                </div>
            </div>

            {{-- C3 — đã mua khoá rồi thì không cần mã: tự chọn lớp trong danh sách lớp đang mở. --}}
            @if ($buyHref && $chooseClassHref)
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-sky-100 bg-[#F8FBFE] px-3 py-2.5">
                    <p class="text-[11.5px] leading-relaxed text-slate-600">
                        Đã đăng ký khoá này rồi? Bạn không cần mã — chọn thẳng một lớp đang mở.
                    </p>
                    <a href="{{ $chooseClassHref }}"
                       class="inline-flex min-h-9 shrink-0 items-center gap-1 rounded-xl border border-sky-200 bg-white px-3 text-[11px] font-bold text-blue-700 transition-colors hover:bg-sky-50">
                        <x-lucide name="school" class="h-3.5 w-3.5" />Chọn lớp <x-lucide name="chevron-right" class="h-3 w-3" />
                    </a>
                </div>
            @endif

            <form method="POST" action="{{ route('student.classes.join') }}" class="flex max-w-md flex-col gap-2.5 sm:flex-row">
                @csrf
                <input type="text" name="code" placeholder="Ví dụ: 10CT-2026" aria-label="Mã lớp"
                       class="h-11 flex-1 rounded-xl border bg-[#F0F6FC] px-3.5 text-xs font-medium text-slate-700 outline-none placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-100 {{ $errors->has('code') ? 'border-rose-300' : 'border-sky-200' }}">
                <button type="submit"
                        class="inline-flex min-h-11 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-[#126F91] px-5 text-[12px] font-bold text-white shadow-[0_5px_12px_rgba(18,111,145,0.18)] transition-colors hover:bg-[#0F5F7A]">
                    <x-lucide name="plus" class="h-4 w-4" />Tham gia lớp
                </button>
            </form>
            @error('code')
                <p class="mt-2 flex items-center gap-1 text-[11px] font-medium text-rose-600">
                    <x-lucide name="info" class="h-3 w-3 shrink-0" />{{ $message }}
                </p>
            @enderror
        </section>
    @endif
</div>
</div>
@endsection

@push('scripts')
    <style>
        .rich-content ul { list-style: disc; padding-left: 1.25rem; margin-bottom: 0.5rem; }
        .rich-content ol { list-style: decimal; padding-left: 1.25rem; margin-bottom: 0.5rem; }
        .rich-content p { margin-bottom: 0.5rem; }
        .rich-content a { color: #126F91; text-decoration: underline; }
    </style>
@endpush
