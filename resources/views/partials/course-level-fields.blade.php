{{-- Các trường phụ của khoá học, dùng chung cho màn Thêm và Sửa khoá học.
     Bên gọi truyền $course (null khi thêm mới).

     Ba KHỐI trong file này, mỗi khối một công tắc riêng ở Admin\CourseService:

       · "Hiển thị trên trang khoá học" -> SHOW_SHOWCASE_FIELDS  (ĐANG BẬT)
       · "Thông tin bậc trong lộ trình" -> SHOW_LEVEL_FIELDS     (đang ẩn)
       · "Bán khoá học này"             -> SHOW_SELLING_FIELDS   (đang ẩn)

     QUAN TRỌNG: các hằng đó KHÔNG chỉ điều khiển giao diện. Ẩn ô nhập thì trình duyệt không
     gửi trường lên nữa, mà store()/update() trước đây ghi thẳng `?? null` — tức mỗi lần lưu
     một khoá học sẽ xoá trắng các cột mà khách đã nhập. Vì vậy chính các hằng đó cũng chặn
     luôn phần ghi dữ liệu, xem CourseService::levelAttributes() và productAttribute().
     Đừng bọc @if ở đây mà quên chỗ đó. --}}
@php
    $course = $course ?? null;
    $courseProducts = $courseProducts ?? [];
    $selectedProductId = (string) old('product_id', $course->product_id ?? '');
@endphp

@if (\App\Services\Admin\CourseService::SHOW_SHOWCASE_FIELDS)
    {{-- ══════ HIỂN THỊ TRÊN TRANG KHOÁ HỌC ══════
         SỬA 15/9 — ba trường này nuôi thẳng bảng thông tin ở đầu trang /khoa-hoc/{id} (bản
         dựng theo mẫu khách gửi). Ghi rõ từng ô hiện ở ĐÂU trên trang công khai để người nhập
         biết mình đang gõ vào chỗ nào, thay vì đoán. --}}
    <div class="rounded-2xl border border-sky-100 bg-[#F8FBFE] p-3.5">
        <div class="mb-3 flex items-start gap-2.5">
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl border border-sky-100 bg-white text-blue-600">
                <x-lucide name="eye" class="h-4 w-4" />
            </span>
            <div class="min-w-0">
                <p class="text-[13px] font-bold text-slate-800">Hiển thị trên trang khoá học</p>
                <p class="mt-0.5 text-[11px] leading-relaxed text-slate-500">
                    Ba ô này in thẳng lên bảng thông tin ở đầu trang giới thiệu khoá học.
                    Bỏ trống ô nào thì phần tương ứng tự ẩn đi, không hiện ô rỗng.
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-[11px] font-bold text-slate-600" for="level_code">Nhãn khoá học</label>
                <input id="level_code" name="level_code" type="text" maxlength="60" class="admin-input"
                       value="{{ old('level_code', $course->level_code ?? '') }}" placeholder="SASH">
                <p class="mt-1 text-[10px] leading-relaxed text-slate-400">Viên nhãn cam ở góc trên bảng thông tin. Ngắn thôi, vài chữ.</p>
                @error('level_code')<p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-1 block text-[11px] font-bold text-slate-600" for="outcome">Mục tiêu đích</label>
                <input id="outcome" name="outcome" type="text" maxlength="160" class="admin-input"
                       value="{{ old('outcome', $course->outcome ?? '') }}" placeholder="Giỏi Python · Sẵn sàng thi đấu">
                <p class="mt-1 text-[10px] leading-relaxed text-slate-400">Ô nền vàng "MỤC TIÊU ĐÍCH". Một câu ngắn nói học xong đạt được gì.</p>
                @error('outcome')<p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-1 block text-[11px] font-bold text-slate-600" for="session_count">Tổng số buổi</label>
                <input id="session_count" name="session_count" type="number" min="1" max="999" class="admin-input"
                       value="{{ old('session_count', $course->session_count ?? '') }}" placeholder="120">
                <p class="mt-1 text-[10px] leading-relaxed text-slate-400">
                    Ô "Tổng buổi". Là số buổi <strong>theo chương trình</strong> — bỏ trống thì trang tự đếm số buổi đã xếp lịch thật của lớp dài nhất.
                </p>
                @error('session_count')<p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>
@endif

@if (\App\Services\Admin\CourseService::SHOW_LEVEL_FIELDS)
    {{-- ══════ THÔNG TIN BẬC TRONG LỘ TRÌNH ══════
         Chỉ còn đúng nhãn phụ của bậc — ba trường còn lại đã chuyển lên khối "Hiển thị trên
         trang khoá học" vì trang công khai dùng tới chúng dù có lộ trình hay không.
         ẨN CHỨ KHÔNG XOÁ: bật SHOW_LEVEL_FIELDS là hiện lại. --}}
    <div class="mt-3 rounded-2xl border border-sky-100 bg-[#F8FBFE] p-3.5">
        <div class="mb-3 flex items-start gap-2.5">
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl border border-sky-100 bg-white text-blue-600">
                <x-lucide name="route" class="h-4 w-4" />
            </span>
            <div class="min-w-0">
                <p class="text-[13px] font-bold text-slate-800">Thông tin bậc trong lộ trình</p>
                <p class="mt-0.5 text-[11px] leading-relaxed text-slate-500">
                    Điền khi khoá học này là một bậc của lộ trình nào đó. Bỏ trống nếu là khoá lẻ.
                </p>
            </div>
        </div>

        <div>
            <label class="mb-1 block text-[11px] font-bold text-slate-600" for="level_subtitle">Nhãn phụ</label>
            <input id="level_subtitle" name="level_subtitle" type="text" maxlength="60" class="admin-input"
                   value="{{ old('level_subtitle', $course->level_subtitle ?? '') }}" placeholder="CORE">
            @error('level_subtitle')<p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>@enderror
        </div>
    </div>
@endif

@if (\App\Services\Admin\CourseService::SHOW_SELLING_FIELDS)
{{-- ══════ C1 · Sản phẩm bán khoá học ══════
     Khoá học không có cột giá riêng. Nó trỏ sang một sản phẩm loại "Khóa học", để việc mua
     đi đúng con đường Sản phẩm → Đơn hàng → Mã kích hoạt → Quyền đã chạy sẵn cho sách và
     chuyên đề. Xem migration add_product_id_to_courses_table. --}}
<div class="mt-3 rounded-2xl border border-emerald-100 bg-[#F6FDF9] p-3.5">
    <div class="mb-3 flex items-start gap-2.5">
        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl border border-emerald-100 bg-white text-emerald-600">
            <x-lucide name="banknote" class="h-4 w-4" />
        </span>
        <div class="min-w-0">
            <p class="text-[13px] font-bold text-slate-800">Bán khoá học này</p>
            <p class="mt-0.5 text-[11px] leading-relaxed text-slate-500">
                Chọn sản phẩm loại <strong>Khóa học</strong> để mở nút mua ngoài trang công khai.
                Bỏ trống thì khoá vẫn chạy bình thường, chỉ là học sinh phải vào bằng mã lớp.
            </p>
        </div>
    </div>

    @unless (\App\Models\Course::supportsProduct())
        {{-- Chưa chạy `php artisan migrate`: ô này chưa lưu được. Nói thẳng lý do và cách sửa,
             thay vì để quản trị chọn xong bấm lưu rồi thấy không có gì thay đổi. --}}
        <div class="mb-3 flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5">
            <x-lucide name="alert-triangle" class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
            <p class="text-[11.5px] leading-relaxed text-amber-800">
                Máy chủ chưa cập nhật cơ sở dữ liệu nên chưa lưu được mục này.
                Chạy <code class="rounded bg-white px-1 py-0.5 font-mono text-[11px]">php artisan migrate</code> rồi tải lại trang.
                Các mục khác vẫn lưu bình thường.
            </p>
        </div>
    @endunless

    <label class="mb-1 block text-[11px] font-bold text-slate-600" for="product_id">Sản phẩm bán khoá này</label>
    <select id="product_id" name="product_id" class="admin-input" @disabled(! \App\Models\Course::supportsProduct())>
        <option value="">— Chưa mở bán trực tuyến —</option>
        @foreach ($courseProducts as $id => $label)
            <option value="{{ $id }}" @selected($selectedProductId === (string) $id)>{{ $label }}</option>
        @endforeach
    </select>
    @error('product_id')<p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>@enderror

    @if (count($courseProducts) === 0)
        <p class="mt-2 text-[11px] leading-relaxed text-amber-700">
            Chưa có sản phẩm nào loại "Khóa học".
            <a href="{{ route('admin.products.create') }}" class="font-bold text-blue-600 hover:underline">Tạo sản phẩm</a>
            (chọn loại Khóa học, điền giá) rồi quay lại chọn ở đây.
        </p>
    @else
        <p class="mt-2 text-[11px] leading-relaxed text-slate-400">
            Giá bán, mô tả và thời hạn quyền sửa ở màn Sản phẩm. Ở đây chỉ chọn xem khoá này bán bằng sản phẩm nào.
        </p>
    @endif

    {{-- Khoá đang nằm trong lộ trình nào, bậc mấy — chỉ hiện ở màn Sửa. --}}
    @if ($course && $course->exists)
        @php $coursePaths = $course->learningPaths()->orderBy('title')->get(); @endphp
        <div class="mt-3 border-t border-sky-100 pt-3">
            @forelse ($coursePaths as $lp)
                <a href="{{ route('admin.learning-paths.steps', $lp->id) }}"
                   class="mb-1.5 flex items-center justify-between gap-2 rounded-xl border border-sky-100 bg-white px-3 py-2 transition-colors hover:border-sky-200 hover:bg-sky-50">
                    <span class="min-w-0">
                        <span class="block truncate text-[12px] font-bold text-slate-700">{{ $lp->title }}</span>
                        {{-- Ô ngôn ngữ đang tắt nên chỉ ghi khối lớp, tránh lòi dấu "—". --}}
                        <span class="block text-[10px] text-slate-400">{{ $lp->gradeLabel() }}@if (\App\Services\Admin\LearningPathService::SHOW_LANGUAGE) · {{ $lp->languageLabel() }}@endif</span>
                    </span>
                    <span class="shrink-0 rounded-lg bg-blue-50 px-2 py-1 text-[11px] font-black text-blue-700">
                        Bậc {{ $lp->pivot->sort_order }}
                    </span>
                </a>
            @empty
                <p class="text-[11px] text-slate-400">Khoá học này chưa nằm trong lộ trình nào.</p>
            @endforelse
        </div>
    @endif
</div>
@endif
