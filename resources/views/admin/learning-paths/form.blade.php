{{-- A8 · Biểu mẫu dùng chung cho Thêm mới và Sửa lộ trình.
     Bên gọi truyền $path (null khi thêm mới), $statuses và $languages. --}}
@php
    $editing = $path !== null;
    $action = $editing ? route('admin.learning-paths.update', $path->id) : route('admin.learning-paths.store');
    $outcomesText = old('outcomes', $editing ? implode("\n", $path->outcomeList()) : '');
@endphp

<x-ws.page-header :title="$editing ? 'Sửa lộ trình' : 'Thêm lộ trình'"
                  :icon="$editing ? 'pencil' : 'plus'"
                  :back="route('admin.learning-paths.index')" back-label="Danh sách lộ trình"
                  :subtitle="$editing ? $path->title : 'Điền thông tin chung trước; xếp các bậc ở bước sau.'">
    @if ($editing)
        <x-slot:actions>
            <x-ws.btn :href="route('admin.learning-paths.steps', $path->id)" variant="onhero" icon="list">Xếp bậc</x-ws.btn>
        </x-slot:actions>
    @endif
</x-ws.page-header>

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="grid grid-cols-1 gap-4 lg:grid-cols-[1.4fr_1fr]">
    @csrf
    @if ($editing)
        @method('PUT')
    @endif

    <div class="space-y-4">
        <x-ws.card title="Thông tin lộ trình" icon="route">
            <div class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-[1fr_auto]">
                    <x-ws.field label="Tên lộ trình" name="title" required>
                        <input id="title" name="title" type="text" class="admin-input" required maxlength="160"
                               value="{{ old('title', $path->title ?? '') }}" placeholder="Python Thi đấu THCS">
                    </x-ws.field>

                    <x-ws.field label="Thương hiệu" name="brand" hint="Tên dòng sản phẩm in trên ảnh, ví dụ SASH.">
                        <input id="brand" name="brand" type="text" class="admin-input sm:w-40" maxlength="60"
                               value="{{ old('brand', $path->brand ?? '') }}" placeholder="SASH">
                    </x-ws.field>
                </div>

                <x-ws.field label="Nhãn nhỏ phía trên tiêu đề" name="eyebrow"
                            hint="Dòng chữ in hoa nhỏ, ví dụ LỘ TRÌNH TIẾP CẬN LẬP TRÌNH.">
                    <input id="eyebrow" name="eyebrow" type="text" class="admin-input" maxlength="120"
                           value="{{ old('eyebrow', $path->eyebrow ?? '') }}">
                </x-ws.field>

                <x-ws.field label="Phụ đề" name="subtitle">
                    <input id="subtitle" name="subtitle" type="text" class="admin-input" maxlength="255"
                           value="{{ old('subtitle', $path->subtitle ?? '') }}"
                           placeholder="Từ tư duy thuật toán đến tự tin tạo sản phẩm nhỏ bằng Python">
                </x-ws.field>

                <x-ws.field label="Mục tiêu đích" name="goal_label" required
                            hint="Câu in trong viên mục tiêu, ví dụ: HSG lớp 9 · Thi tuyển sinh 10 Chuyên Tin.">
                    <input id="goal_label" name="goal_label" type="text" class="admin-input" required maxlength="255"
                           value="{{ old('goal_label', $path->goal_label ?? '') }}">
                </x-ws.field>

                <x-ws.field label="Mô tả" name="description">
                    <textarea id="description" name="description" rows="3" class="admin-input" maxlength="5000">{{ old('description', $path->description ?? '') }}</textarea>
                </x-ws.field>
            </div>
        </x-ws.card>

        <x-ws.card title="Kết quả đầu ra" icon="check-circle-2">
            <x-ws.field label="Mỗi dòng một ý" name="outcomes"
                        hint="Ba dòng in ở cuối ảnh lộ trình. Xuống dòng để thêm ý mới.">
                <textarea id="outcomes" name="outcomes" rows="4" class="admin-input" maxlength="1000"
                          placeholder="Hiểu cách máy tính giải quyết vấn đề&#10;Viết được chương trình Python nhỏ&#10;Hình thành thói quen tự học">{{ $outcomesText }}</textarea>
            </x-ws.field>
        </x-ws.card>

        {{-- THÊM 15/9 (khách yêu cầu) — chọn thumbnail khi thêm/sửa lộ trình.
             Hai ảnh có vai trò khác nhau, cố ý tách riêng:
             · Ảnh lộ trình (cover): hiện ở danh sách quản trị và thẻ lộ trình ngoài trang công khai.
             · Ảnh chia sẻ (share): chỉ dùng khi dán link lên Zalo/Facebook.
             Trang lộ trình công khai vẫn dựng thang bậc bằng HTML theo dữ liệu, KHÔNG dùng ảnh,
             để sửa số buổi là thang tự đổi mà không phải nhờ thiết kế vẽ lại. --}}
        <x-ws.card title="Ảnh lộ trình" icon="image">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ws.field label="Ảnh đại diện (thumbnail)" name="cover"
                            hint="Ngang, nên 16:9. JPG/PNG/WEBP, tối đa 4MB. Bỏ trống thì dùng ảnh mặc định của giao diện.">
                    @if ($editing && $path->coverUrl())
                        <img src="{{ $path->coverUrl() }}" alt=""
                             class="mb-2 h-20 w-full max-w-[220px] rounded-xl border border-sky-100 object-cover">
                    @else
                        <div class="mb-2 flex h-20 w-full max-w-[220px] items-center justify-center rounded-xl border border-dashed border-sky-200 bg-sky-50/60 text-sky-300">
                            <x-lucide name="image" class="h-6 w-6" />
                        </div>
                    @endif

                    <input id="cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp"
                           class="admin-input file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-blue-700">

                    @if ($editing && $path->coverUrl())
                        <label class="mt-2 flex items-center gap-2 text-[11px] font-semibold text-rose-600">
                            <input type="checkbox" name="remove_cover" value="1"
                                   class="h-3.5 w-3.5 rounded border-slate-300 text-rose-600">
                            Gỡ ảnh hiện tại
                        </label>
                    @endif
                </x-ws.field>

                <x-ws.field label="Ảnh chia sẻ (tuỳ chọn)" name="share_image"
                            hint="Ảnh hiện khi dán link lên Zalo/Facebook. Tối đa 8MB.">
                    @if ($editing && $path->shareImageUrl())
                        <img src="{{ $path->shareImageUrl() }}" alt=""
                             class="mb-2 h-20 w-full max-w-[220px] rounded-xl border border-sky-100 object-cover">
                    @else
                        <div class="mb-2 flex h-20 w-full max-w-[220px] items-center justify-center rounded-xl border border-dashed border-sky-200 bg-sky-50/60 text-sky-300">
                            <x-lucide name="share-2" class="h-6 w-6" />
                        </div>
                    @endif

                    <input id="share_image" name="share_image" type="file" accept="image/jpeg,image/png,image/webp"
                           class="admin-input file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-blue-700">

                    @if ($editing && $path->shareImageUrl())
                        <label class="mt-2 flex items-center gap-2 text-[11px] font-semibold text-rose-600">
                            <input type="checkbox" name="remove_share_image" value="1"
                                   class="h-3.5 w-3.5 rounded border-slate-300 text-rose-600">
                            Gỡ ảnh hiện tại
                        </label>
                    @endif
                </x-ws.field>
            </div>
        </x-ws.card>
    </div>

    <div class="space-y-4">
        <x-ws.card title="Đối tượng học" icon="graduation-cap">
            <div class="space-y-4">
                {{-- Khối lớp là một KHOẢNG, không phải một lớp: "Khối 6–8" phải khớp được cả
                     lớp 6, 7 và 8. Xem ghi chú ở migration create_learning_paths_table. --}}
                <div class="grid grid-cols-2 gap-3">
                    <x-ws.field label="Từ lớp" name="grade_from" required>
                        <input id="grade_from" name="grade_from" type="number" min="1" max="12" class="admin-input" required
                               value="{{ old('grade_from', $path->grade_from ?? 6) }}">
                    </x-ws.field>
                    <x-ws.field label="Đến lớp" name="grade_to" required>
                        <input id="grade_to" name="grade_to" type="number" min="1" max="12" class="admin-input" required
                               value="{{ old('grade_to', $path->grade_to ?? 8) }}">
                    </x-ws.field>
                </div>
                <p class="-mt-2 text-[11px] text-slate-400">Cùng một số ở hai ô nghĩa là lộ trình chỉ dành cho một lớp.</p>

                {{-- SỬA 15/9 (khách yêu cầu) — ẨN ô "Ngôn ngữ lập trình": lộ trình sau này còn
                     dùng cho Toán, Văn và các môn khác. ẨN CHỨ KHÔNG XOÁ — bật lại bằng cách
                     đổi App\Services\Admin\LearningPathService::SHOW_LANGUAGE thành true. --}}
                @if (\App\Services\Admin\LearningPathService::SHOW_LANGUAGE)
                    <x-ws.field label="Ngôn ngữ" name="language">
                        <x-ws.select id="language" name="language">
                            @foreach ($languages as $value => $label)
                                <option value="{{ $value }}" @selected(old('language', $path->language->value ?? 'python') === $value)>{{ $label }}</option>
                            @endforeach
                        </x-ws.select>
                    </x-ws.field>
                @endif
            </div>
        </x-ws.card>

        <x-ws.card title="Nhịp học" icon="calendar-days">
            <div class="grid grid-cols-2 gap-3">
                <x-ws.field label="Buổi mỗi tuần" name="sessions_per_week" required>
                    <input id="sessions_per_week" name="sessions_per_week" type="number" min="1" max="14" class="admin-input" required
                           value="{{ old('sessions_per_week', $path->sessions_per_week ?? 2) }}">
                </x-ws.field>
                <x-ws.field label="Giờ mỗi buổi" name="hours_per_session" required>
                    <input id="hours_per_session" name="hours_per_session" type="number" step="0.5" min="0.5" max="8" class="admin-input" required
                           value="{{ old('hours_per_session', $path->hours_per_session ?? 2) }}">
                </x-ws.field>
            </div>
            <p class="mt-2 text-[11px] leading-relaxed text-slate-400">
                Dùng để quy tổng số buổi ra số tuần học. Tổng buổi tính từ các bậc, không nhập tay ở đây.
            </p>
        </x-ws.card>

        <x-ws.card title="Sắp xếp" icon="list">
            <x-ws.field label="Thứ tự hiển thị" name="sort_order" hint="Số nhỏ đứng trước ở trang danh sách.">
                <input id="sort_order" name="sort_order" type="number" min="0" max="9999" class="admin-input"
                       value="{{ old('sort_order', $path->sort_order ?? '') }}" placeholder="Tự động xếp cuối">
            </x-ws.field>
        </x-ws.card>

        <div class="flex flex-wrap items-center gap-2">
            <x-ws.btn type="submit" variant="primary" icon="save">{{ $editing ? 'Lưu thay đổi' : 'Tạo lộ trình' }}</x-ws.btn>
            <x-ws.btn :href="route('admin.learning-paths.index')" variant="ghost">Huỷ</x-ws.btn>
        </div>

        @unless ($editing)
            <p class="text-[11px] leading-relaxed text-slate-400">
                Tạo xong sẽ chuyển thẳng sang màn xếp bậc. Lộ trình mới luôn ở dạng bản nháp cho tới khi đủ điều kiện đăng.
            </p>
        @endunless
    </div>
</form>

@if ($editing)
    {{-- Khối xoá — tách ra ngoài biểu mẫu chính để không lồng form vào nhau. --}}
    <x-ws.card class="border-rose-100" x-data="{ open: false, reason: '' }">
        <h3 class="flex items-center gap-2 text-sm font-bold text-rose-700">
            <x-lucide name="alert-triangle" class="h-4 w-4" /> Xoá lộ trình
        </h3>
        <p class="mt-1 text-[11px] text-slate-500">
            Chỉ xoá lộ trình. <strong>Các khoá học và lớp bên trong vẫn còn nguyên</strong>, chỉ là không còn thuộc lộ trình này nữa.
        </p>
        <button type="button" @click="open = !open" class="mt-2 text-xs font-bold text-rose-600 hover:underline"
                x-text="open ? 'Đóng' : 'Tôi muốn xoá lộ trình này'"></button>

        <form method="POST" action="{{ route('admin.learning-paths.destroy', $path->id) }}" x-show="open" x-cloak class="mt-3 space-y-2">
            @csrf
            @method('DELETE')
            <textarea name="reason" x-model="reason" rows="2" class="admin-input" placeholder="Lý do xoá (ghi vào nhật ký thao tác)..."></textarea>
            <button type="submit" :disabled="reason.trim().length === 0"
                    class="inline-flex min-h-10 w-full items-center justify-center gap-1.5 rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-rose-100 transition-colors hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-40">
                Xác nhận xoá
            </button>
        </form>
    </x-ws.card>
@endif
