{{-- Hero() của RoleWorkspace.jsx (source mới) cho khu Học sinh: ảnh nền + lớp phủ xanh + lời chào.
     Mọi mục của khu dùng CHUNG một hero (giống source: tiêu đề "Chào …", nút "Vào luyện tập"). --}}
@include('partials.student-workspace-style')
<section class="sw-hero">
    <img src="{{ asset('assets/workspace-student-hero-v1.jpg') }}" alt="" decoding="async">
    <div class="sw-hero-in">
        <div style="min-width:0">
            <small>Không gian học tập của bạn</small>
            <h1>Chào {{ auth()->user()->name ?? 'bạn' }}</h1>
            <p>Theo dõi lịch học, bài và đề được giao. Kết nối với phụ huynh và giáo viên.</p>
        </div>
        <div><a class="sw-hero-btn" href="{{ route('student.practice.index') }}">Vào luyện tập</a></div>
    </div>
</section>
