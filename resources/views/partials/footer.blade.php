{{-- ═══════════════ [GLOBAL-02] FOOTER TOÀN TRANG ═══════════════
     Dựng lại đúng theo source giao diện khách gửi: education-main/src/components/Footer.jsx.
     Các nút điều hướng của bản mẫu được thay bằng link thật tới đúng trang. --}}
<footer class="footer-typography w-full relative overflow-hidden border-t border-sky-200/80 bg-white mt-3 sm:mt-4">
    {{-- [GLOBAL-02A] Ảnh nền footer --}}
    <img src="{{ asset('assets/footer-bg.jpg') }}" alt=""
         class="absolute inset-0 w-full h-full object-cover object-bottom pointer-events-none select-none z-0">

    <div class="relative z-10 max-w-[1780px] mx-auto px-4 sm:px-8 lg:px-10 py-6 sm:py-7">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-5 sm:gap-7 mb-4 sm:mb-5">

            {{-- [GLOBAL-02B] Logo và giới thiệu nền tảng --}}
            <div class="md:col-span-2">
                <a href="{{ route('home') }}">
                    <img src="{{ asset('assets/header-logo.png') }}" alt="Ôn Thi 360"
                         class="h-9 sm:h-10 object-contain mb-2.5 cursor-pointer">
                </a>
                <p class="text-[13px] sm:text-sm font-semibold leading-relaxed text-blue-700 mb-1.5">
                    Học cùng mục tiêu – Vươn xa ước mơ
                </p>
                <p class="text-xs sm:text-[13px] font-normal leading-[1.65] text-slate-600 max-w-md">
                    Nền tảng học tập và đánh giá Tin học hàng đầu cho học sinh 6–12, giáo viên và phụ huynh.
                    Tích hợp hệ thống chấm bài trực tuyến Online Judge, ngân hàng học liệu bản quyền và đồng hành minh bạch.
                </p>
            </div>

            {{-- [GLOBAL-02C] Nhóm liên kết Học tập --}}
            <div>
                <h5 class="type-footer-heading mb-2.5">Học tập</h5>
                <ul class="type-footer-link flex flex-col gap-1.5 text-slate-600">
                    <li><a href="{{ route('courses.index') }}" class="hover:text-blue-600 transition-colors text-left cursor-pointer py-0.5 block">Lớp học</a></li>
                    <li><a href="{{ route('practice.index') }}" class="hover:text-blue-600 transition-colors text-left cursor-pointer py-0.5 block">Luyện tập</a></li>
                    <li><a href="{{ route('materials.index') }}" class="hover:text-blue-600 transition-colors text-left cursor-pointer py-0.5 block">Tài liệu</a></li>
                    <li><a href="{{ route('competitions.index') }}" class="hover:text-blue-600 transition-colors text-left cursor-pointer py-0.5 block">Cuộc thi</a></li>
                </ul>
            </div>

            {{-- [GLOBAL-02D] Nhóm liên kết Đồng hành --}}
            <div>
                <h5 class="type-footer-heading mb-2.5">Đồng hành</h5>
                <ul class="type-footer-link flex flex-col gap-1.5 text-slate-600">
                    <li><a href="{{ route('practice.index') }}" class="hover:text-blue-600 transition-colors text-left cursor-pointer py-0.5 block">Dành cho học sinh</a></li>
                    <li><a href="{{ route('info.index') }}" class="hover:text-blue-600 transition-colors text-left cursor-pointer py-0.5 block">Dành cho phụ huynh</a></li>
                    <li><a href="{{ route('teachers.index') }}" class="hover:text-blue-600 transition-colors text-left cursor-pointer py-0.5 block">Giáo viên & chuyên gia</a></li>
                    <li><a href="{{ route('leaderboard.index') }}" class="hover:text-blue-600 transition-colors text-left cursor-pointer py-0.5 block">Bảng xếp hạng</a></li>
                </ul>
            </div>

            {{-- [GLOBAL-02E] Nhóm liên kết Thông tin --}}
            <div>
                <h5 class="type-footer-heading mb-2.5">Thông tin</h5>
                <ul class="type-footer-link flex flex-col gap-1.5 text-slate-600">
                    <li><a href="{{ route('info.index') }}" class="hover:text-blue-600 transition-colors text-left cursor-pointer py-0.5 block">Giới thiệu Ôn Thi 360</a></li>
                    <li><a href="{{ route('access.activate') }}" class="hover:text-blue-600 transition-colors text-left cursor-pointer py-0.5 block">Hướng dẫn kích hoạt mã</a></li>
                    <li><a href="{{ route('info.policies.show', 'dieu-khoan') }}" class="hover:text-blue-600 transition-colors text-left cursor-pointer py-0.5 block">Chính sách bản quyền</a></li>
                    <li><a href="{{ route('info.index') }}#lien-he" class="hover:text-blue-600 transition-colors text-left cursor-pointer py-0.5 block">Liên hệ & Hỗ trợ</a></li>
                </ul>
            </div>
        </div>

        {{-- [GLOBAL-02F] THANH CUỐI FOOTER — slogan, mạng xã hội và pháp lý trên cùng một hàng --}}
        <div class="flex flex-col items-center gap-3 border-t border-sky-100/70 pt-3.5 text-center lg:flex-row lg:justify-between lg:text-left">
            <p class="text-xs sm:text-[13px] font-bold leading-relaxed text-blue-900 italic tracking-[0.01em]">
                — Học tốt Tin học – Vững vàng hành trang 6–12 —
            </p>

            <div class="flex items-center gap-2.5">
                <span class="type-footer-meta font-bold text-[#123B68]">Kết nối</span>
                <div class="flex items-center gap-1.5">
                    <a href="https://www.facebook.com/" target="_blank" rel="noopener" aria-label="Facebook"
                       class="w-7 h-7 rounded-full bg-[#1877F2] text-white flex items-center justify-center text-xs font-bold hover:opacity-90 shadow-2xs">f</a>
                    <a href="https://www.youtube.com/" target="_blank" rel="noopener" aria-label="YouTube"
                       class="w-7 h-7 rounded-full bg-[#FF0000] text-white flex items-center justify-center text-xs font-bold hover:opacity-90 shadow-2xs">▶</a>
                    <a href="https://zalo.me/" target="_blank" rel="noopener" aria-label="Zalo"
                       class="w-7 h-7 rounded-full bg-[#0068FF] text-white flex items-center justify-center text-[10px] font-bold hover:opacity-90 shadow-2xs">Zalo</a>
                </div>
            </div>

            <div class="type-footer-meta flex flex-col items-center gap-0.5 text-slate-500 sm:flex-row sm:gap-2 lg:justify-end">
                <p class="whitespace-nowrap">
                    <a href="{{ route('info.policies.show', 'dieu-khoan') }}" class="hover:text-blue-600">Điều khoản sử dụng</a>
                    ·
                    <a href="{{ route('info.policies.show', 'bao-mat') }}" class="hover:text-blue-600">Chính sách bảo mật</a>
                </p>
                <span class="hidden text-slate-300 sm:inline">|</span>
                <p class="whitespace-nowrap">© {{ date('Y') }} Ôn Thi 360 · Bảo lưu mọi quyền</p>
            </div>
        </div>
    </div>
</footer>
