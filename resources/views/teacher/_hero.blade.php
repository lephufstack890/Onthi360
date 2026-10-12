{{-- Hero() của RoleWorkspace.jsx (source mới) cho khu Giáo viên: ảnh nền + lớp phủ xanh + lời chào, tiêu đề gọn
     (compactHeading). Mọi mục của khu dùng CHUNG một hero, nút "Mở lớp học". --}}
@include('partials.teacher-workspace-style')
<section class="sw-hero is-compact">
    <img src="{{ asset('assets/workspace-teacher-hero-v1.jpg') }}" alt="" decoding="async">
    <div class="sw-hero-in">
        <div style="min-width:0">
            <small>Không gian giảng dạy</small>
            <h1>Chào {{ auth()->user()->name ?? 'thầy/cô' }}, lớp học đang tiến triển tốt</h1>
            <p>Theo dõi lớp học, lịch dạy, bài được giao và kết quả của học sinh.</p>
        </div>
        <div><a class="sw-hero-btn" href="{{ route('teacher.classes.index') }}">Mở lớp học</a></div>
    </div>
</section>
