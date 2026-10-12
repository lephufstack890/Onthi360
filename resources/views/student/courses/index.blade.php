@extends('layouts.student')

@section('title', 'Lớp học của tôi')
@section('page-title', 'Lớp học của tôi')

@section('content')
    {{--
      SỬA 12/10 — DỰNG LẠI THEO education-main (source mới): StudentClassesPanel.jsx — thẻ lớp có ảnh bìa,
      mã lớp, giáo viên, lịch học hằng tuần, tóm tắt bài/đề của lớp và nút "Vào lớp".
      Dữ liệu thật: Student\StudentWorkspaceService::classes(). Giữ nguyên các thông báo tham gia/đăng ký lớp
      và khối "Có mã lớp?" đang ẩn (ClassRoomService::JOIN_BY_CODE_ENABLED = false).
    --}}
    @php($classes = $classes ?? [])

    <div class="sw">
        @include('student._hero')

        @if (session('status') === 'joined-class')
            @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tham gia lớp thành công!'])
        @endif
        @if (session('status') === 'class-join-requested')
            @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã gửi yêu cầu đăng ký — chờ giáo viên duyệt.'])
        @endif

        {{-- ẨN 16/9 (khách: "bỏ chỗ nhập mã lớp") — khối "Có mã lớp?" ẨN CHỨ KHÔNG XOÁ: đổi
             ClassRoomService::JOIN_BY_CODE_ENABLED thành true rồi thêm lại form POST student.classes.join. --}}

        <section class="student-classes" aria-label="Lớp học của tôi">
            <div class="student-classes-heading">
                <h2>Lớp đang tham gia <span>{{ count($classes) }}</span></h2>
                <a class="student-overview-action" href="{{ route('courses.index') }}">+ Khám phá lớp học mới</a>
            </div>

            @if (empty($classes))
                <div class="student-overview-card">
                    <p class="student-overview-empty" style="text-align:center">Bạn chưa tham gia lớp nào. Mở trang Lớp học, chọn lớp phù hợp rồi bấm "Đăng ký học" — giáo viên duyệt là bạn vào học được ngay.</p>
                    <div style="text-align:center"><a class="student-class-enter" style="display:inline-flex;padding-inline:16px" href="{{ route('courses.index') }}">Xem các lớp đang mở <x-lucide name="arrow-right" class="h-3.5 w-3.5" /></a></div>
                </div>
            @else
                <div class="student-classes-grid">
                    @foreach ($classes as $c)
                        <article class="student-class-card student-class-{{ $c['tone'] }}" aria-label="{{ $c['title'] }}">
                            <div class="student-class-header">
                                <img class="student-class-cover" src="{{ $c['cover'] }}" alt="" decoding="async" loading="lazy">
                                <div class="student-class-title">
                                    <span class="student-class-code">{{ $c['code'] }}</span>
                                    <h3>{{ $c['title'] }}</h3>
                                    <span class="student-class-format"><x-lucide name="video" class="h-3 w-3" /> {{ $c['format'] }}</span>
                                </div>
                            </div>
                            <div class="student-class-teacher">
                                @if ($c['teacherAvatar'])
                                    <img src="{{ $c['teacherAvatar'] }}" alt="" loading="lazy">
                                @else
                                    <span class="ini">{{ $c['teacherInitial'] }}</span>
                                @endif
                                <div><span>Giáo viên</span><p>{{ $c['teacher'] }}</p></div>
                            </div>
                            <div class="student-class-schedule" aria-label="Lịch học">
                                <x-lucide name="calendar-days" class="h-4 w-4" />
                                <div><strong>{{ $c['schedule'] }}</strong></div>
                                <span class="student-class-weekly">Lịch lớp</span>
                            </div>
                            <div class="student-class-progress">
                                <div>
                                    <span>{{ $c['assignTotal'] ? $c['pendingExercises'].' bài · '.$c['pendingExams'].' đề cần làm' : 'Chưa có bài hoặc đề được giao' }}</span>
                                    <strong>{{ $c['assignTotal'] > 0 ? $c['completed'].' hoàn thành' : '' }}</strong>
                                </div>
                                @if ($c['submitted'] > 0)
                                    <p class="sw-note" style="margin-top:4px">{{ $c['submitted'] }} đang chờ chấm</p>
                                @endif
                            </div>
                            <a class="student-class-enter" href="{{ $c['url'] }}">Vào lớp <x-lucide name="arrow-right" class="h-3.5 w-3.5" /></a>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
