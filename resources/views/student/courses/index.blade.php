@extends('layouts.student')

@section('title', 'Khóa học của tôi')
@section('page-title', 'Khóa học của tôi')

@section('content')
    {{--
      SỬA 24/9 (khách: "xem source mới nhất trong học sinh, xây lại trang khoá học cho giống UI
      source mới, logic vẫn giữ nguyên").

      DỰNG LẠI THEO education-main/src/components/RoleWorkspace.jsx — nhánh
      StudentContent(active === "Khóa học của tôi"): lưới 2 cột, mỗi lớp là một thẻ NGANG (ảnh
      bên trái, nội dung bên phải), nhãn nhỏ IN HOA màu xanh ở trên, thanh tiến độ mảnh và dòng
      "% tiến độ" bên dưới.

      LOGIC KHÔNG ĐỔI: vẫn đúng các khoá của Student\ClassRoomService (id, course, class,
      teacher, percent, nextSession), vẫn cùng route, vẫn giữ nguyên 2 thông báo session và
      khối "Có mã lớp?" đang tạm ẩn.
    --}}
    @php
        $classes = $classes ?? [];
        // Ảnh bìa xoay vòng theo VỊ TRÍ để mỗi lớp luôn nhận đúng một ảnh, không nhảy lung tung
        // mỗi lần tải trang.
        $covers = ['course-img-1.png', 'course-img-2.png', 'course-img-3.png', 'course-img-4.png', 'course-img-5.png'];
    @endphp

    <x-ws.page-header title="Khóa học của tôi" icon="book-open" subtitle="Lớp là nơi tổ chức lịch, học viên và tiến độ của bạn (8.1).">
        <x-slot:actions>
            <a href="{{ route('courses.index') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-sky-100 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition-colors hover:border-sky-200 hover:bg-sky-50">+ Khám phá khóa học mới</a>
        </x-slot:actions>
    </x-ws.page-header>

    @if (session('status') === 'joined-class')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tham gia lớp thành công!'])
    @endif

    {{-- ẨN 16/9 (khách yêu cầu: "bỏ chỗ nhập mã lớp tham gia lớp đi") — khối "Có mã lớp?".
         ẨN CHỨ KHÔNG XOÁ: đổi App\Services\Student\ClassRoomService::JOIN_BY_CODE_ENABLED
         thành true rồi bỏ dấu chú thích quanh khối này là hiện lại nguyên vẹn.
         Lối vào lớp bây giờ: trang Lớp học công khai -> bấm "Đăng ký học" -> giáo viên duyệt.
    <div class="rounded-3xl bg-white border border-sky-100 p-5 mb-6">
        <h2 class="font-medium text-slate-700 mb-1 flex items-center gap-2"><span><x-lucide name="ticket" class="h-4 w-4" /></span> Có mã lớp?</h2>
        <p class="text-[13px] text-slate-500 mb-3">Giáo viên cung cấp mã lớp riêng cho từng lớp — nhập đúng mã để tham gia ngay.</p>
        <form method="POST" action="{{ route('student.classes.join') }}" class="flex flex-col sm:flex-row gap-3 max-w-md">
            @csrf
            <input type="text" name="code" placeholder="Ví dụ: 10CT-2026"
                   class="flex-1 rounded-xl border border-sky-100 text-[13px] p-2.5">
            <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 shrink-0">Tham gia lớp</button>
        </form>
    </div>
    --}}

    {{-- SỬA 16/9 — kết quả gửi yêu cầu đăng ký (khi học sinh bấm từ trang lớp công khai rồi
         quay lại đây). --}}
    @if (session('status') === 'class-join-requested')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã gửi yêu cầu đăng ký — chờ giáo viên duyệt.'])
    @endif

    @if (empty($classes))
        <x-ws.empty-state title="Bạn chưa tham gia lớp nào" description="Mở trang Lớp học, chọn lớp phù hợp rồi bấm &quot;Đăng ký học&quot; — giáo viên duyệt là bạn vào học được ngay." actionLabel="Xem các lớp đang mở" :actionHref="route('courses.index')" />
    @else
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            @foreach ($classes as $i => $c)
                @php($percent = max(0, min(100, (int) ($c['percent'] ?? 0))))
                <a href="{{ route('student.classes.show', $c['id']) }}"
                   class="student-course-card flex flex-col overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] sm:flex-row">
                    <img src="{{ asset('assets/'.$covers[$i % count($covers)]) }}" alt="" decoding="async"
                         class="student-course-cover h-28 w-full object-cover">

                    <div class="min-w-0 flex-1 p-4">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-blue-600">
                            {{ $c['course'] !== '' ? $c['course'] : 'Khóa đang học' }}
                        </span>
                        <h2 class="mt-1 text-sm font-black text-slate-800">{{ $c['class'] }}</h2>
                        <p class="mt-1 text-[11px] text-slate-500">
                            {{ $c['teacher'] }}{{ $c['nextSession'] ? ' · Buổi tới: '.$c['nextSession'] : '' }}
                        </p>

                        <div class="mt-3">
                            <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-blue-500" style="width: {{ $percent }}%"></div>
                            </div>
                            <p class="mt-1 text-[10px] text-slate-400">{{ $percent }}% tiến độ</p>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
@endsection

@push('scripts')
    {{-- Bề ngang cố định của ảnh bìa ở khổ màn hình lớn (sm:w-28 của bản mẫu) + hiệu ứng rê
         chuột. Viết CSS thường vì bản CSS trên máy chủ là bản build sẵn. --}}
    <style>
        .student-course-card { transition: border-color 160ms ease, box-shadow 160ms ease; }
        .student-course-card:hover { border-color: #BFDBFE; box-shadow: 0 6px 18px rgba(0, 90, 180, .10); }

        @media (min-width: 640px) {
            /* sm:w-28 + sm:h-auto của bản mẫu — cả hai đều chưa có trong bản CSS build sẵn. */
            .student-course-cover { width: 7rem; height: auto; flex-shrink: 0; }
        }
    </style>
@endpush
