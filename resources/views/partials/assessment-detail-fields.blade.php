{{--
    SỬA 2/10 (khách: "khi update UI ông thấy field nào thiếu trong admin và giáo viên thì ông
    bổ sung giúp tôi nha") — 6 ô mô tả đề mà bản mẫu mới cần nhưng form cũ không có chỗ nhập.
    Tách partial để form bên ADMIN và bên GIÁO VIÊN không lệch nhau.

    Hiện ở đâu ngoài trang công khai:
      · Mô tả ngắn   -> dòng dưới tên đề trên thẻ đề + khối "Thông tin đề thi" màn chi tiết;
      · Tác giả / Tỉnh thành / Năm học -> 3 hàng trong khối "Thông tin đề thi";
      · Loại đề      -> dải chip lọc ở tab "Đề thi luyện tập";
      · Ảnh bìa      -> ảnh trên thẻ đề và ở thanh đầu màn chi tiết.

    LƯU Ý cho ai sửa sau: form chứa partial này PHẢI có enctype="multipart/form-data", thiếu nó
    thì trình duyệt chỉ gửi tên tệp chứ không gửi nội dung — bấm Lưu không báo lỗi gì mà ảnh
    vẫn không có (đúng cái bẫy đã ghi ở partials/course-cover-field).

      · Bản xem trước -> cột trái màn chi tiết đề (thay cho câu "Đề này chưa mở bản xem trước").

    SỬA 2/10 lần 2 (khách: "chọn loại luyện tập nó mới hiển thị thôi nha") — cả khối này CHỈ
    hiện khi ô "Loại" đang chọn Luyện tập. Mọi thứ trong đây đều chỉ đổ ra trang Luyện tập công
    khai, bày ở đề Bài giao / Đề thi / Đề thi đấu là bắt người nhập điền thứ không ai thấy.

    Bên gọi truyền $assessment (null khi đang tạo mới).
--}}
@php
    use App\Enums\AssessmentType;

    $assessment = $assessment ?? null;
    $adCoverUrl = $assessment?->coverUrl();

    // Loại đang chọn: ưu tiên giá trị vừa nhập hỏng (old), rồi tới đề đang sửa, cuối cùng là
    // Luyện tập — đúng mặc định của ô "Loại" ở form tạo mới.
    $adType = old('type', $assessment?->type?->value ?? AssessmentType::Practice->value);

    // Tên tệp PDF xem trước đang có (nếu đã tải lên) — chỉ để hiện cho người nhập biết đang có
    // tệp gì, chứ không phải đường dẫn thật.
    $adPreviewName = $assessment?->preview_pdf_path !== null
        ? ($assessment->preview_pdf_original_name ?: basename($assessment->preview_pdf_path))
        : null;
@endphp

{{-- x-init bám vào ô select#type của form bao ngoài: ở form sửa, khi loại bị ẩn khỏi danh sách
     thì ô đó là input ẩn KHÔNG có id, getElementById trả null và khối giữ nguyên giá trị PHP
     tính sẵn ở trên — đúng, vì lúc ấy loại cũng không đổi được. --}}
<div x-data="{ adType: @js($adType) }"
     x-init="const sel = document.getElementById('type');
             if (sel) { adType = sel.value; sel.addEventListener('change', () => adType = sel.value); }"
     x-show="adType === @js(AssessmentType::Practice->value)"
     @if ($adType !== AssessmentType::Practice->value) x-cloak @endif
     class="space-y-4 rounded-2xl border border-sky-100 bg-sky-50/40 p-4">
    <h3 class="flex items-center gap-2 text-[13px] font-bold text-slate-700">
        <x-lucide name="info" class="h-4 w-4 text-blue-600" />Thông tin hiển thị ngoài trang Luyện tập
    </h3>

    <div>
        <label class="mb-1 block text-[13px] font-medium text-slate-600" for="subtitle">Mô tả ngắn</label>
        <textarea id="subtitle" name="subtitle" rows="2" maxlength="255"
                  placeholder="1-2 câu nói rõ đề này luyện gì, thi theo format nào..."
                  class="admin-input">{{ old('subtitle', $assessment?->subtitle) }}</textarea>
        <p class="mt-1 text-xs text-slate-400">Hiện dưới tên đề trên thẻ đề và ở khối "Thông tin đề thi". Để trống thì chỗ đó hiện số câu và thời lượng như trước.</p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label class="mb-1 block text-[13px] font-medium text-slate-600" for="author">Tác giả</label>
            <input id="author" name="author" type="text" maxlength="120"
                   value="{{ old('author', $assessment?->author) }}" placeholder="Ví dụ: Sở GD&ĐT Hà Nội"
                   class="admin-input">
        </div>
        <div>
            <label class="mb-1 block text-[13px] font-medium text-slate-600" for="exam_category">Loại đề</label>
            <x-ws.select id="exam_category" name="exam_category">
                <option value="">— Chưa phân loại —</option>
                @foreach (\App\Support\ExamCategory::CATEGORIES as $code => $label)
                    <option value="{{ $code }}" @selected(old('exam_category', $assessment?->exam_category) === $code)>{{ $label }}</option>
                @endforeach
            </x-ws.select>
            <p class="mt-1 text-xs text-slate-400">Dùng cho dải chip lọc ngoài trang Luyện tập.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label class="mb-1 block text-[13px] font-medium text-slate-600" for="province">Tỉnh/thành</label>
            <x-ws.select id="province" name="province">
                <option value="">— Chưa gán —</option>
                @foreach (\App\Support\ProvinceCatalog::groups() as $groupLabel => $options)
                    <optgroup label="{{ $groupLabel }}">
                        @foreach ($options as $code => $label)
                            <option value="{{ $code }}" @selected(old('province', $assessment?->province) === $code)>{{ $label }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </x-ws.select>
        </div>
        <div>
            <label class="mb-1 block text-[13px] font-medium text-slate-600" for="academic_year">Năm học</label>
            <input id="academic_year" name="academic_year" type="text" maxlength="20"
                   value="{{ old('academic_year', $assessment?->academic_year) }}" placeholder="Ví dụ: 2024-2025"
                   class="admin-input">
            <p class="mt-1 text-xs text-slate-400">Ghi dạng năm học (vắt qua 2 năm), không phải 1 năm dương lịch.</p>
        </div>
    </div>

    {{-- ── BẢN XEM TRƯỚC ──────────────────────────────────────────────────────────────
         Khách: "chỗ mở bản xem trước á chỉ cần hiển thị file pdf để xem thôi… cho chọn file pdf
         xem trước là ok". MỘT cách duy nhất: tải lên một tệp PDF. Mặc định không có tệp = không
         mở xem trước — đề là tài sản, khoe phần nào là do người ra đề quyết.

         Ở ĐÂY KHÔNG đụng tới preview_page_from/to (khoảng trang xem thử của đề PDF) — 2 ô đó
         vẫn ở nguyên màn "Quản lý đề PDF". Bày lại chúng ở đây thành ra hai cách làm một việc,
         và mỗi lần lưu form này lại có nguy cơ ghi đè con số màn kia vừa khai. --}}
    <div class="rounded-xl border border-sky-100 bg-white/70 p-3">
        <h4 class="flex items-center gap-2 text-[13px] font-bold text-slate-700">
            <x-lucide name="eye" class="h-4 w-4 text-blue-600" />Bản xem trước
        </h4>

        <div class="mt-2">
            <label class="mb-1 block text-[13px] font-medium text-slate-600" for="preview_pdf">Tệp PDF xem trước</label>

            @if ($adPreviewName)
                {{-- Đang có tệp: hiện tên và cho mở thử bằng ĐÚNG đường dẫn công khai mà người
                     học sẽ thấy — bấm một cái là biết người ngoài xem được những gì. --}}
                <div class="mb-2 flex flex-wrap items-center gap-2 rounded-xl border border-sky-100 bg-white px-3 py-2">
                    <x-lucide name="file-text" class="h-4 w-4 shrink-0 text-blue-600" />
                    <span class="min-w-0 flex-1 truncate text-[12px] font-semibold text-slate-600">{{ $adPreviewName }}</span>
                    @if ($assessment?->exists)
                        <a href="{{ route('practice.exam.preview', $assessment->id) }}" target="_blank" rel="noopener"
                           class="shrink-0 text-[11px] font-bold text-blue-700 hover:underline">Xem thử</a>
                    @endif
                </div>
                <label class="mb-2 flex w-fit items-center gap-2 text-[11px] font-semibold text-rose-600">
                    <input type="checkbox" name="remove_preview_pdf" value="1" class="h-3.5 w-3.5 rounded border-slate-300 text-rose-600">
                    Gỡ tệp xem trước hiện tại
                </label>
            @endif

            <input id="preview_pdf" name="preview_pdf" type="file" accept="application/pdf,.pdf"
                   class="admin-input file:mr-3 file:rounded-xl file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-blue-700">
            @error('preview_pdf')<p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>@enderror

            <p class="mt-1 text-xs leading-relaxed text-slate-400">
                Chỉ nhận PDF, tối đa 20MB. Tải tệp nào thì ngoài trang chi tiết đề hiện đúng tệp đó.
                @if ($adPreviewName)
                    Chọn tệp mới là <b class="text-slate-500">thay</b> tệp đang có.
                @else
                    Bỏ trống thì màn chi tiết đề chỉ hiện cấu trúc đề, không mở xem trước.
                @endif
            </p>
        </div>
    </div>

    <div>
        <label class="mb-1 block text-[13px] font-medium text-slate-600" for="cover">Ảnh bìa đề</label>
        <div class="flex flex-wrap items-start gap-3">
            @if ($adCoverUrl)
                <img src="{{ $adCoverUrl }}" alt="Ảnh bìa hiện tại của đề" class="h-24 w-40 shrink-0 rounded-xl border border-sky-100 object-cover">
            @else
                {{-- Chưa có ảnh riêng thì vẽ ô trống, KHÔNG hiện ảnh mặc định — để người nhập
                     phân biệt "đề này chưa có ảnh" với "đề này đã có ảnh rồi". --}}
                <div class="flex h-24 w-40 shrink-0 flex-col items-center justify-center gap-1 rounded-xl border border-dashed border-sky-200 bg-sky-50/60 text-sky-300">
                    <x-lucide name="image" class="h-6 w-6" />
                    <span class="text-[10px] font-bold">Chưa có ảnh</span>
                </div>
            @endif

            <div class="min-w-[220px] flex-1">
                <input id="cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp"
                       class="admin-input file:mr-3 file:rounded-xl file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-blue-700">
                <p class="mt-1 text-xs leading-relaxed text-slate-400">Ảnh ngang, nên theo tỉ lệ 16:9. JPG/PNG/WebP, tối đa 4MB. Bỏ trống thì thẻ đề hiện khối ảnh trống chứ không mượn ảnh của thứ khác.</p>
                @if ($adCoverUrl)
                    <label class="mt-2 flex w-fit items-center gap-2 text-[11px] font-semibold text-rose-600">
                        <input type="checkbox" name="remove_cover" value="1" class="h-3.5 w-3.5 rounded border-slate-300 text-rose-600">
                        Gỡ ảnh hiện tại
                    </label>
                @endif
                @error('cover')<p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>
</div>
