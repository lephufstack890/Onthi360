{{-- Biểu mẫu dùng chung cho Thêm mới và Sửa câu chuyện đồng hành ([HOME-10]).
     Bên gọi truyền $testimonial (null khi thêm mới) và $statuses. --}}
@php
    $editing = $testimonial !== null;
    $action = $editing ? route('admin.testimonials.update', $testimonial->id) : route('admin.testimonials.store');
@endphp

<x-ws.page-header :title="$editing ? 'Sửa câu chuyện' : 'Thêm câu chuyện'"
                  :icon="$editing ? 'pencil' : 'plus'"
                  :back="route('admin.testimonials.index')" back-label="Quay lại danh sách"
                  subtitle="Nội dung này hiện ở khối “Câu chuyện đồng hành” trên trang chủ công khai." />

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="grid grid-cols-1 gap-4 lg:grid-cols-[1.4fr_1fr]">
    @csrf
    @if ($editing)
        @method('PUT')
    @endif

    <div class="space-y-4">
        <x-ws.card title="Nội dung câu chuyện" icon="pen-line">
            <div class="space-y-4">
                <x-ws.field label="Lời kể" name="quote" required
                            hint="Nên viết 2–3 câu, nói rõ kết quả đạt được. Câu chuyện cụ thể vừa đáng tin với phụ huynh, vừa tốt cho tìm kiếm.">
                    <textarea id="quote" name="quote" rows="4" class="admin-input" required
                              placeholder="Ví dụ: Sau 4 tháng học chuyên đề Cấu trúc dữ liệu, con đạt giải Nhì HSG Tin học cấp tỉnh...">{{ old('quote', $testimonial->quote ?? '') }}</textarea>
                </x-ws.field>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-ws.field label="Tên người kể" name="author_name" required>
                        <input id="author_name" name="author_name" type="text" class="admin-input" required
                               value="{{ old('author_name', $testimonial->author_name ?? '') }}" placeholder="Nguyễn Minh Anh">
                    </x-ws.field>

                    <x-ws.field label="Vai trò" name="author_role" hint="Học sinh lớp 12 / Phụ huynh / Giáo viên...">
                        <input id="author_role" name="author_role" type="text" class="admin-input"
                               value="{{ old('author_role', $testimonial->author_role ?? '') }}" placeholder="Học sinh lớp 12">
                    </x-ws.field>
                </div>

                <x-ws.field label="Trường / đơn vị (tuỳ chọn)" name="author_org"
                            hint="Ghi cụ thể giúp câu chuyện đáng tin hơn và hợp với các tìm kiếm theo địa phương.">
                    <input id="author_org" name="author_org" type="text" class="admin-input"
                           value="{{ old('author_org', $testimonial->author_org ?? '') }}" placeholder="THPT Chuyên Thái Bình">
                </x-ws.field>
            </div>
        </x-ws.card>

        <x-ws.card title="Hình ảnh" icon="file-text">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ws.field label="Ảnh đại diện" name="avatar" hint="Vuông, tối đa 2MB. Bỏ trống thì dùng ảnh mặc định của giao diện.">
                    @if ($editing)
                        <img src="{{ $testimonial->avatarUrl() }}" alt="" class="mb-2 h-14 w-14 rounded-full border border-sky-100 object-cover">
                    @endif
                    <input id="avatar" name="avatar" type="file" accept="image/*"
                           class="admin-input file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-blue-700">
                </x-ws.field>

                <x-ws.field label="Ảnh bìa thẻ" name="banner" hint="Ngang, tối đa 4MB.">
                    @if ($editing)
                        <img src="{{ $testimonial->bannerUrl() }}" alt="" class="mb-2 h-14 w-24 rounded-lg border border-sky-100 object-cover">
                    @endif
                    <input id="banner" name="banner" type="file" accept="image/*"
                           class="admin-input file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-blue-700">
                </x-ws.field>
            </div>
        </x-ws.card>
    </div>

    <div class="space-y-4">
        <x-ws.card title="Hiển thị" icon="eye">
            <div class="space-y-4">
                <x-ws.field label="Trạng thái" name="status" required>
                    <x-ws.select id="status" name="status">
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $testimonial->status->value ?? 'draft') === $value)>{{ $label }}</option>
                        @endforeach
                    </x-ws.select>
                </x-ws.field>

                <x-ws.field label="Thứ tự" name="sort_order" hint="Số nhỏ hiện trước. Trang chủ lấy 3 câu đầu tiên trong số đang hiển thị.">
                    <input id="sort_order" name="sort_order" type="number" min="0" max="9999" class="admin-input"
                           value="{{ old('sort_order', $testimonial->sort_order ?? '') }}" placeholder="Tự động xếp cuối">
                </x-ws.field>

                <x-ws.field label="Số sao (tuỳ chọn)" name="rating" hint="Chỉ dùng khi câu chuyện đã xác minh.">
                    <x-ws.select id="rating" name="rating">
                        <option value="">— Không ghi sao —</option>
                        @for ($i = 5; $i >= 1; $i--)
                            <option value="{{ $i }}" @selected((string) old('rating', $testimonial->rating ?? '') === (string) $i)>{{ $i }} sao</option>
                        @endfor
                    </x-ws.select>
                </x-ws.field>
            </div>
        </x-ws.card>

        {{-- Ô xác minh — quyết định có gửi dữ liệu đánh giá cho Google hay không. --}}
        <x-ws.card title="Xác minh" icon="shield-check">
            <label class="flex cursor-pointer items-start gap-2.5 rounded-2xl border border-sky-100 bg-[#F8FBFE] p-3 transition-colors hover:border-sky-200">
                <input type="hidden" name="verified" value="0">
                <input type="checkbox" name="verified" value="1" class="mt-0.5 h-4 w-4 shrink-0 rounded accent-blue-600"
                       @checked(old('verified', $testimonial?->isVerified() ? 1 : 0))>
                <span class="text-[11px] leading-relaxed text-slate-600">
                    <strong class="block text-[12px] text-slate-800">Đây là câu chuyện có thật và đã được người kể đồng ý đăng.</strong>
                    Chỉ khi tích ô này, câu chuyện mới được gắn dữ liệu đánh giá (schema.org/Review) gửi cho Google.
                    Khai báo đánh giá cho nội dung không có thật là vi phạm chính sách của Google và có thể khiến cả
                    tên miền bị phạt — nên ô này mặc định tắt.
                </span>
            </label>
        </x-ws.card>

        <div class="flex flex-wrap items-center gap-2">
            <x-ws.btn type="submit" variant="primary" icon="save">{{ $editing ? 'Lưu thay đổi' : 'Thêm câu chuyện' }}</x-ws.btn>
            <x-ws.btn :href="route('admin.testimonials.index')" variant="ghost">Huỷ</x-ws.btn>
        </div>
    </div>
</form>

@if ($editing)
    {{-- Khối xoá — tách hẳn ra ngoài form chính để không lồng form vào nhau. --}}
    <x-ws.card class="border-rose-100" x-data="{ open: false, reason: '' }">
        <h3 class="flex items-center gap-2 text-sm font-bold text-rose-700">
            <x-lucide name="alert-triangle" class="h-4 w-4" /> Xoá câu chuyện
        </h3>
        <p class="mt-1 text-[11px] text-slate-500">Xoá hẳn khỏi hệ thống, kèm cả ảnh đã tải lên. Không khôi phục được.</p>
        <button type="button" @click="open = !open" class="mt-2 text-xs font-bold text-rose-600 hover:underline"
                x-text="open ? 'Đóng' : 'Tôi muốn xoá câu chuyện này'"></button>

        <form method="POST" action="{{ route('admin.testimonials.destroy', $testimonial->id) }}" x-show="open" x-cloak class="mt-3 space-y-2">
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
