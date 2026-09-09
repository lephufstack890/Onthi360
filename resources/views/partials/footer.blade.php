{{-- ═══════════════ FOOTER ═══════════════
     SỬA 9/9 (10) — theo đúng source React khách gửi (App.jsx, phần "7. FULL-WIDTH PANORAMIC
     FOOTER"). Bản gốc dùng neo #courses/#testimonials; ở đây thay bằng LINK THẬT sang từng
     trang theo yêu cầu "gắn link đầy đủ". --}}
@php
    $footerLearn = [
        ['Lớp học', route('courses.index')],
        ['Luyện tập', route('practice.index')],
        ['Tài liệu', route('materials.index')],
        ['Cuộc thi', route('competitions.index')],
    ];
    $footerCompanion = [
        ['Dành cho học sinh', route('courses.index')],
        ['Dành cho phụ huynh', route('info.index')],
        ['Giáo viên & chuyên gia', route('teachers.index')],
        ['Bảng xếp hạng', route('leaderboard.index')],
    ];
    $footerInfo = [
        ['Giới thiệu', route('info.index')],
        ['Tin tức', route('competitions.index')],
        ['Hướng dẫn sử dụng', route('info.index')],
        ['Liên hệ', route('info.index').'#lien-he'],
    ];
@endphp

<footer class="w-full relative overflow-hidden border-t border-sky-200/80 bg-white mt-10">
    <img src="{{ asset('assets/footer-bg.jpg') }}" alt="" class="absolute inset-0 w-full h-full object-cover object-bottom pointer-events-none select-none z-0">

    <div class="relative z-10 max-w-[1780px] mx-auto px-4 sm:px-8 lg:px-10 py-9 sm:py-11">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-7 sm:gap-8 mb-8">
            <div class="md:col-span-2">
                <a href="{{ route('home') }}"><img src="{{ asset('assets/header-logo.png') }}" alt="Ôn Thi 360" class="h-10 sm:h-11 object-contain mb-3"></a>
                <p class="text-sm sm:text-base font-semibold text-blue-700 mb-1.5">Học cùng mục tiêu – Vươn xa ước mơ</p>
                <p class="text-xs sm:text-sm text-slate-500 leading-relaxed max-w-sm">Nền tảng học tập Tin học uy tín, đồng hành cùng học sinh trên hành trình chinh phục tri thức và ước mơ.</p>
            </div>

            <div>
                <h5 class="text-sm sm:text-base font-bold text-slate-900 mb-3">Học tập</h5>
                <ul class="flex flex-col gap-2 text-xs sm:text-sm text-slate-600">
                    @foreach ($footerLearn as [$label, $url])
                        <li><a href="{{ $url }}" class="hover:text-blue-600 transition-colors">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </div>

            <div>
                <h5 class="text-sm sm:text-base font-bold text-slate-900 mb-3">Đồng hành</h5>
                <ul class="flex flex-col gap-2 text-xs sm:text-sm text-slate-600">
                    @foreach ($footerCompanion as [$label, $url])
                        <li><a href="{{ $url }}" class="hover:text-blue-600 transition-colors">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </div>

            <div>
                <h5 class="text-sm sm:text-base font-bold text-slate-900 mb-3">Thông tin</h5>
                <ul class="flex flex-col gap-2 text-xs sm:text-sm text-slate-600 mb-3.5">
                    @foreach ($footerInfo as [$label, $url])
                        <li><a href="{{ $url }}" class="hover:text-blue-600 transition-colors">{{ $label }}</a></li>
                    @endforeach
                </ul>

                <h5 class="text-xs sm:text-sm font-bold text-slate-900 mb-2">Kết nối với chúng tôi</h5>
                <div class="flex items-center gap-2.5 mb-3.5">
                    <a href="https://facebook.com/onthi360" target="_blank" rel="noopener" aria-label="Facebook" class="w-8 h-8 rounded-full bg-[#1877F2] text-white flex items-center justify-center text-sm font-bold hover:opacity-90 shadow-2xs">f</a>
                    <a href="https://www.youtube.com/@onthi360" target="_blank" rel="noopener" aria-label="YouTube" class="w-8 h-8 rounded-full bg-[#FF0000] text-white flex items-center justify-center text-sm font-bold hover:opacity-90 shadow-2xs">▶</a>
                    <a href="https://zalo.me" target="_blank" rel="noopener" aria-label="Zalo" class="w-8 h-8 rounded-full bg-[#0068FF] text-white flex items-center justify-center text-xs font-bold hover:opacity-90 shadow-2xs">Zalo</a>
                </div>

                <div class="text-xs text-slate-500 leading-normal">
                    <p><a href="{{ route('info.policies.show', 'dieu-khoan') }}" class="hover:text-blue-600 transition-colors">Điều khoản sử dụng</a> | <a href="{{ route('info.policies.show', 'bao-mat') }}" class="hover:text-blue-600 transition-colors">Chính sách bảo mật</a></p>
                    <p class="mt-0.5">© {{ now()->year }} Ôn Thi 360. Tất cả quyền được bảo lưu.</p>
                </div>
            </div>
        </div>

        <div class="pt-6 border-t border-sky-100/70 text-center flex items-center justify-center gap-3">
            <div class="h-px bg-gradient-to-r from-transparent via-sky-300 to-transparent w-24 hidden sm:block"></div>
            <p class="text-sm sm:text-base font-bold text-blue-900 italic tracking-wide">— Học tốt Tin học – Vững vàng hành trang 6–12 —</p>
            <div class="h-px bg-gradient-to-r from-transparent via-sky-300 to-transparent w-24 hidden sm:block"></div>
        </div>
    </div>
</footer>
