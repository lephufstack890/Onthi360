{{--
  Route: info.index | Frame: PUB-11
  Spec: 4.1 (Giới thiệu, hướng dẫn, tin tức, chính sách, liên hệ, FAQ).
  TODO: tách route riêng cho từng mục nếu nội dung dài (info.about/info.faq/info.contact...);
  hiện gộp 1 trang, điều hướng bằng anchor link nội trang (#gioi-thieu, #huong-dan, ...).
--}}
@extends('layouts.guest')

@section('title', 'Thông tin')

@section('content')
    @php
        $sections = [
            ['id' => 'gioi-thieu', 'label' => 'Giới thiệu', 'icon' => '📖'],
            ['id' => 'huong-dan', 'label' => 'Hướng dẫn sử dụng', 'icon' => '🧭'],
            ['id' => 'chinh-sach', 'label' => 'Chính sách', 'icon' => '📜'],
            ['id' => 'lien-he', 'label' => 'Liên hệ', 'icon' => '✉️'],
        ];
        $guides = [
            ['role' => 'Học sinh', 'icon' => '🧑‍🎓', 'tone' => 'rose', 'steps' => ['Vào lớp bằng mã giáo viên cung cấp, hoặc luyện tập công khai ngay không cần chờ ai duyệt', 'Làm bài trong lộ trình lớp — bài lập trình được chấm tự động', 'Theo dõi tiến độ, kết quả và thông báo ngay trên Tổng quan']],
            ['role' => 'Giáo viên', 'icon' => '🍎', 'tone' => 'sky', 'steps' => ['Đăng ký và chờ Admin duyệt hồ sơ giáo viên trước khi mở lớp', 'Tạo lớp, gắn học liệu còn quyền dạy, giao bài kiểm tra có hạn nộp', 'Nhập đề nhanh bằng Word/PDF/OCR, rà soát trước khi phát hành']],
            ['role' => 'Phụ huynh', 'icon' => '👨‍👩‍👧', 'tone' => 'emerald', 'steps' => ['Nhận mã liên kết từ con để theo dõi tài khoản học sinh', 'Xem lịch học, điểm danh, tiến độ và kết quả gần đây', 'Nhận thông báo khi quyền học/dạy sắp hết hạn']],
        ];
        $policies = [
            ['title' => 'Chính sách bảo mật', 'desc' => 'Cách thu thập, sử dụng và bảo vệ dữ liệu cá nhân, đặc biệt với học sinh dưới 18 tuổi.'],
            ['title' => 'Điều khoản sử dụng', 'desc' => 'Quy định quyền và trách nhiệm khi sử dụng Ôn Thi 360 cho từng vai trò.'],
            ['title' => 'Chính sách hoàn tiền', 'desc' => 'Điều kiện và quy trình hoàn tiền cho đơn hàng sản phẩm/khóa học (7.4).'],
        ];
    @endphp

    {{-- Hero --}}
    <div class="bg-gradient-to-br from-sky-50 via-white to-rose-50">
        <div class="max-w-5xl mx-auto px-4 py-12 lg:py-16 text-center">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white text-sky-600 text-xs font-medium mb-4 shadow-sm">ℹ️ Thông tin</span>
            <h1 class="text-2xl lg:text-3xl font-semibold text-slate-800">Mọi điều cần biết về Ôn Thi 360</h1>
            <p class="text-slate-500 mt-3 max-w-lg mx-auto">Giới thiệu, hướng dẫn sử dụng theo từng vai trò, chính sách và cách liên hệ với chúng tôi.</p>
        </div>
    </div>

    <div class="max-w-5xl mx-auto px-4 py-10 lg:py-14">
        {{-- Điều hướng nhanh giữa các mục --}}
        <div class="flex flex-wrap justify-center gap-2 mb-12 text-sm sticky top-0 bg-slate-50/90 backdrop-blur py-3 z-10 -mx-4 px-4">
            @foreach ($sections as $s)
                <a href="#{{ $s['id'] }}" class="px-3 py-1.5 rounded-full border border-slate-200 bg-white text-slate-600 font-medium hover:border-rose-300 hover:text-rose-600">
                    {{ $s['icon'] }} {{ $s['label'] }}
                </a>
            @endforeach
        </div>

        {{-- Giới thiệu --}}
        <section id="gioi-thieu" class="mb-14 scroll-mt-20">
            <h2 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2"><span>📖</span> Giới thiệu</h2>
            <div class="bg-white rounded-2xl border border-slate-200 p-6">
                <p class="text-sm text-slate-600 leading-relaxed">
                    Ôn Thi 360 là nền tảng học có lộ trình, luyện tập và chấm bài tự động — nơi học sinh, giáo viên và
                    phụ huynh cùng đồng hành trên một hệ thống minh bạch, luôn nêu rõ lý do trước khi khóa nội dung.
                    Câu lập trình, trắc nghiệm và điền đáp án đều có thể trộn trong cùng một đề, chấm điểm tự động và
                    lưu lại lịch sử để ôn tập lâu dài.
                </p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6 pt-6 border-t border-slate-100 text-center">
                    <div><p class="text-xl font-semibold text-rose-600">12k+</p><p class="text-xs text-slate-400 mt-1">học sinh</p></div>
                    <div><p class="text-xl font-semibold text-rose-600">340+</p><p class="text-xs text-slate-400 mt-1">giáo viên</p></div>
                    <div><p class="text-xl font-semibold text-rose-600">98%</p><p class="text-xs text-slate-400 mt-1">hài lòng</p></div>
                    <div><p class="text-xl font-semibold text-rose-600">24/7</p><p class="text-xs text-slate-400 mt-1">luyện tập</p></div>
                </div>
            </div>
        </section>

        {{-- Hướng dẫn sử dụng --}}
        <section id="huong-dan" class="mb-14 scroll-mt-20">
            <h2 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2"><span>🧭</span> Hướng dẫn sử dụng</h2>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                @foreach ($guides as $g)
                    <div class="bg-white rounded-2xl border border-slate-200 p-5">
                        <div class="flex items-center gap-3 mb-3">
                            <x-icon-tile :emoji="$g['icon']" :tone="$g['tone']" />
                            <h3 class="font-medium text-slate-700">{{ $g['role'] }}</h3>
                        </div>
                        <ol class="space-y-2.5">
                            @foreach ($g['steps'] as $i => $step)
                                <li class="flex items-start gap-2.5 text-sm text-slate-600">
                                    <span class="w-5 h-5 rounded-full bg-slate-100 text-slate-500 text-xs font-semibold flex items-center justify-center shrink-0 mt-0.5">{{ $i + 1 }}</span>
                                    {{ $step }}
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Chính sách --}}
        <section id="chinh-sach" class="mb-14 scroll-mt-20">
            <h2 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2"><span>📜</span> Chính sách</h2>
            <div class="bg-white rounded-2xl border border-slate-200 divide-y divide-slate-100">
                @foreach ($policies as $p)
                    <div class="flex items-center justify-between gap-4 p-5">
                        <div>
                            <p class="font-medium text-slate-700">{{ $p['title'] }}</p>
                            <p class="text-sm text-slate-400 mt-0.5">{{ $p['desc'] }}</p>
                        </div>
                        {{-- TODO: link tới trang chính sách đầy đủ khi có nội dung pháp lý thật --}}
                        <a href="#" class="text-sm text-rose-600 font-medium shrink-0">Xem chi tiết ›</a>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Liên hệ --}}
        <section id="lien-he" class="scroll-mt-20">
            <h2 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2"><span>✉️</span> Liên hệ</h2>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <div class="lg:col-span-1 space-y-3">
                    <div class="bg-white rounded-2xl border border-slate-200 p-5 flex items-start gap-3">
                        <x-icon-tile emoji="📧" tone="sky" />
                        <div>
                            <p class="text-sm font-medium text-slate-700">Email hỗ trợ</p>
                            <p class="text-sm text-slate-500">hotro@onthi360.vn</p>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-slate-200 p-5 flex items-start gap-3">
                        <x-icon-tile emoji="📞" tone="emerald" />
                        <div>
                            <p class="text-sm font-medium text-slate-700">Hotline</p>
                            <p class="text-sm text-slate-500">1900 000 000 (8h–21h)</p>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-slate-200 p-5 flex items-start gap-3">
                        <x-icon-tile emoji="📍" tone="amber" />
                        <div>
                            <p class="text-sm font-medium text-slate-700">Địa chỉ</p>
                            <p class="text-sm text-slate-500">TODO: địa chỉ văn phòng chính thức.</p>
                        </div>
                    </div>
                </div>

                {{-- TODO: nối form liên hệ thật (gửi email/ticket) --}}
                <form class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-6 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1">Họ tên</label>
                            <input type="text" class="w-full rounded-lg border border-slate-200 text-sm p-2.5" placeholder="Tên của bạn">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1">Email</label>
                            <input type="email" class="w-full rounded-lg border border-slate-200 text-sm p-2.5" placeholder="you@email.com">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Nội dung</label>
                        <textarea rows="4" class="w-full rounded-lg border border-slate-200 text-sm p-3" placeholder="Bạn cần hỗ trợ điều gì?"></textarea>
                    </div>
                    <button type="button" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium">Gửi liên hệ</button>
                </form>
            </div>
        </section>
    </div>
@endsection
