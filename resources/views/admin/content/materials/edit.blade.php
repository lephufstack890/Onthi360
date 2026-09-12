@extends('layouts.admin')

@section('title', 'Sửa học liệu')
@section('page-title', 'Sửa học liệu')

@section('content')
    @php
        $products = $products ?? []; $assessments = $assessments ?? [];
        $types = $types ?? []; $statuses = $statuses ?? [];
        // SỬA 4/9 (khách yêu cầu: "chỗ thêm file học liệu thì cho chọn chương/phần/đề") — xem
        // ghi chú tương ứng ở materials/create.blade.php.
        $chaptersByProduct = $chaptersByProduct ?? [];
        $chapterLabelByProduct = $chapterLabelByProduct ?? [];
    @endphp

    <a href="{{ route('admin.content.show', $material->id) }}" class="text-[13px] text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-blue-600">‹ Quay lại chi tiết</a>

    <x-admin.page-header title="Sửa học liệu" icon="pencil" :subtitle="$material->title" />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-5 sm:p-6"
         x-data="{
            type: '{{ old('type', $material->type) }}',
            productId: '{{ old('product_id', $material->product_id) }}',
            selectedParentId: '{{ old('parent_id', $material->parent_id) }}',
            chaptersByProduct: {{ json_encode($chaptersByProduct) }},
            chapterLabelByProduct: {{ json_encode($chapterLabelByProduct) }},
            get chapterLabel() { return this.chapterLabelByProduct[this.productId] || null },
            get chapters() { return this.chaptersByProduct[this.productId] || [] },
            updateTitleFromFiles() {
                const pick = this.$refs.pdf.files[0] || this.$refs.audio.files[0] || this.$refs.image.files[0];
                if (pick) {
                    this.$refs.titleInput.value = pick.name.replace(/\.[^.]+$/, '');
                }
            },
         }">
        <form method="POST" action="{{ route('admin.content.materials.update', $material->id) }}" class="space-y-4" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            {{-- SỬA 4/9 (khách yêu cầu "ẩn hết field đi chỉ để cho chọn chương với chọn các
                 file") — bỏ khỏi giao diện Sửa: "Thuộc sản phẩm", "Thứ tự hiển thị", "Tiêu đề",
                 "Trạng thái", "Mã bài" — chỉ còn chọn chương/phần/đề + 3 ô tệp. TẤT CẢ vẫn gửi
                 ngầm qua input ẩn với giá trị HIỆN CÓ của học liệu (không đổi khi lưu), trừ
                 "Tiêu đề" tự cập nhật theo tên tệp mới nếu admin thay tệp (xem
                 updateTitleFromFiles() ở x-data trên). Không cho đổi "Thuộc sản phẩm" qua màn
                 Sửa nữa (hiếm khi cần, admin có thể sửa trực tiếp ở DB nếu thật sự cần chuyển
                 học liệu sang sản phẩm khác). --}}
            <input type="hidden" name="product_id" value="{{ old('product_id', $material->product_id) }}">

            <div x-show="chapterLabel" x-cloak>
                <label class="block text-[13px] font-medium text-slate-600 mb-1" for="parent_id_picker" x-text="'Thuộc ' + (chapterLabel || '').toLowerCase()"></label>
                <template x-if="chapters.length > 0">
                    <x-admin.select id="parent_id_picker" x-model="selectedParentId">
                        <option value="">— Chưa gắn —</option>
                        <template x-for="c in chapters" :key="c.id">
                            <option :value="String(c.id)" x-text="c.title"></option>
                        </template>
                    </x-admin.select>
                </template>
                <template x-if="chapters.length === 0">
                    <p class="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-xl px-3 py-2"
                       x-text="'Tài liệu này chưa có ' + (chapterLabel || '').toLowerCase() + ' nào — vào trang tài liệu để tạo trước.'"></p>
                </template>
            </div>
            {{-- Hidden input thật (name="parent_id") để không mất giá trị khi field bị ẩn ở
                 trên (Khóa học hoặc chưa có chương/phần/đề nào) — dropdown hiển thị chỉ là
                 "proxy" (không có name), chỉ cập nhật selectedParentId qua x-model. --}}
            <input type="hidden" name="parent_id" :value="selectedParentId">

            <input type="hidden" name="type" x-model="type">
            <input type="hidden" name="order" value="{{ old('order', $material->order) }}">
            <input type="hidden" name="title" value="{{ old('title', $material->title) }}" x-ref="titleInput">
            <input type="hidden" name="status" value="{{ old('status', $material->status->value) }}">
            <input type="hidden" name="code" value="{{ old('code', $material->code) }}">

            <div x-show="type === 'assessment_ref'" x-cloak>
                <label class="block text-[13px] font-medium text-slate-600 mb-1" for="assessment_id">Đề/bộ bài tham chiếu</label>
                <x-admin.select id="assessment_id" name="assessment_id">
                    <option value="">— Chọn đề/bộ bài —</option>
                    @foreach ($assessments as $a)
                        <option value="{{ $a->id }}" @selected((string) old('assessment_id', $material->assessment_id) === (string) $a->id)>{{ $a->title }}</option>
                    @endforeach
                </x-admin.select>
            </div>

            {{--
                SỬA 25/8 (khách chốt: "các bài cần có cơ chế sửa sau khi nhập" — mã bài và PDF
                ĐỀU sửa lại được, không phải tải lên xong là khóa cứng, xem ContentService::materialUpdate()).
                Để trống ô PDF/audio/ảnh thì GIỮ NGUYÊN tệp hiện tại, không xóa/thay gì cả.
            --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="pdf">📄 Tệp PDF (tùy chọn)</label>
                    @if ($material->pdf_path)
                        <p class="text-xs text-slate-500 mb-1.5">
                            📄 Đã có tệp: <span class="font-medium text-slate-600">{{ $material->pdf_original_name ?: basename($material->pdf_path) }}</span>
                            — chọn tệp mới bên dưới để thay thế.
                        </p>
                    @endif
                    <input id="pdf" name="pdf" type="file" accept="application/pdf" x-ref="pdf" @change="updateTitleFromFiles()"
                           class="admin-input file:mr-3 file:rounded-xl file:border-0 file:bg-blue-50 file:px-3 file:py-1 file:text-xs file:font-bold file:text-blue-700">
                </div>
                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="audio">🔊 Tệp audio (tùy chọn)</label>
                    @if ($material->audio_path)
                        <p class="text-xs text-slate-500 mb-1.5">
                            🔊 Đã có tệp: <span class="font-medium text-slate-600">{{ $material->audio_original_name ?: basename($material->audio_path) }}</span>
                            — chọn tệp mới bên dưới để thay thế.
                        </p>
                    @endif
                    <input id="audio" name="audio" type="file" accept="audio/*" x-ref="audio" @change="updateTitleFromFiles()"
                           class="admin-input file:mr-3 file:rounded-xl file:border-0 file:bg-blue-50 file:px-3 file:py-1 file:text-xs file:font-bold file:text-blue-700">
                    <p class="text-xs text-slate-400 mt-1">mp3/wav/ogg/m4a/aac — tối đa {{ number_format(\App\Services\Admin\ContentService::maxMaterialAudioKb() / 1024) }}MB.</p>
                </div>
                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="image">🖼️ Tệp ảnh (tùy chọn, hỗ trợ GIF động)</label>
                    @if ($material->image_path)
                        <p class="text-xs text-slate-500 mb-1.5">
                            🖼️ Đã có tệp: <span class="font-medium text-slate-600">{{ $material->image_original_name ?: basename($material->image_path) }}</span>
                            — chọn tệp mới bên dưới để thay thế.
                        </p>
                    @endif
                    <input id="image" name="image" type="file" accept="image/*" x-ref="image" @change="updateTitleFromFiles()"
                           class="admin-input file:mr-3 file:rounded-xl file:border-0 file:bg-blue-50 file:px-3 file:py-1 file:text-xs file:font-bold file:text-blue-700">
                    <p class="text-xs text-slate-400 mt-1">jpg/png/gif/webp — tối đa {{ number_format(\App\Services\Admin\ContentService::maxMaterialImageKb() / 1024) }}MB.</p>
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-[13px] font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 shadow-sm hover:bg-blue-700 transition">Lưu thay đổi</button>
                <a href="{{ route('admin.content.show', $material->id) }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-sky-100 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition-colors hover:border-sky-200 hover:bg-sky-50">Huỷ</a>
            </div>
        </form>
    </div>
@endsection
