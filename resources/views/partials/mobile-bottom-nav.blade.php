{{-- Mobile bottom nav (4.1): Trang chủ, Khóa học, Luyện tập, Thêm (bottom sheet chứa các mục còn lại). --}}
<nav class="lg:hidden fixed bottom-0 inset-x-0 bg-white border-t border-slate-200 flex justify-around py-2 text-xs text-slate-500">
    <a href="{{ route('home') }}" class="flex flex-col items-center gap-1">
        <span>🏠</span> Trang chủ
    </a>
    <a href="{{ route('courses.index') }}" class="flex flex-col items-center gap-1">
        <span>📚</span> Khóa học
    </a>
    <a href="{{ route('practice.index') }}" class="flex flex-col items-center gap-1">
        <span>📝</span> Luyện tập
    </a>
    {{-- TODO: nút "Thêm" mở bottom sheet chứa Tài liệu/Cuộc thi/Bảng xếp hạng/Giáo viên tiêu biểu/Thông tin --}}
    <button type="button" class="flex flex-col items-center gap-1">
        <span>⋯</span> Thêm
    </button>
</nav>
