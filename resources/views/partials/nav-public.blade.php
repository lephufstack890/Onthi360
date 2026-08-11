{{-- Menu công khai (4.1). Desktop: hàng ngang đủ 8 mục + đăng nhập. --}}
<header class="bg-white border-b border-slate-200">
    <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
        <a href="{{ route('home') }}" class="font-semibold text-rose-600">Ôn Thi 360</a>

        <nav class="hidden lg:flex items-center gap-6 text-sm font-medium text-slate-600">
            <a href="{{ route('home') }}" class="hover:text-rose-600">Trang chủ</a>
            <a href="{{ route('courses.index') }}" class="hover:text-rose-600">Khóa học</a>
            <a href="{{ route('practice.index') }}" class="hover:text-rose-600">Luyện tập</a>
            <a href="{{ route('materials.index') }}" class="hover:text-rose-600">Tài liệu</a>
            <a href="{{ route('competitions.index') }}" class="hover:text-rose-600">Cuộc thi</a>
            <a href="{{ route('leaderboard.index') }}" class="hover:text-rose-600">Bảng xếp hạng</a>
            <a href="{{ route('teachers.index') }}" class="hover:text-rose-600">Giáo viên tiêu biểu</a>
            <a href="{{ route('info.index') }}" class="hover:text-rose-600">Thông tin</a>
        </nav>

        <div class="flex items-center gap-2">
            @auth
                <a href="{{ route('dashboard') }}" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">Vào học</a>
            @else
                <a href="{{ route('login') }}" class="px-4 py-2 rounded-lg text-sm font-medium text-slate-600">Đăng nhập</a>
                <a href="{{ route('register') }}" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">Đăng ký</a>
            @endauth
        </div>
    </div>
</header>
