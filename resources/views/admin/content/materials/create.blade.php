@extends('layouts.admin')

@section('title', 'Tạo học liệu')
@section('page-title', 'Tạo học liệu')

@section('content')
    @php
        $products = $products ?? []; $assessments = $assessments ?? [];
        $types = $types ?? []; $statuses = $statuses ?? [];
        // SỬA 4/9 (khách yêu cầu: "chỗ thêm file học liệu thì cho chọn chương/phần/đề") — 2
        // mảng JS dùng để lọc đúng danh sách chương/phần/đề THEO sản phẩm đang chọn, xem
        // ContentService::materialCreateFormData().
        $chaptersByProduct = $chaptersByProduct ?? [];
        $chapterLabelByProduct = $chapterLabelByProduct ?? [];
        // SỬA 26/8 (ẩn field Loại) — mặc định "Bài/Mục" (section) thay vì "Chương" (chapter):
        // admin không cần chọn tay nữa, trừ khi vào từ link "tạo học liệu tham chiếu đề" (có
        // ?assessment_id=) thì vẫn tự nhận đúng loại 'assessment_ref' như trước (xem
        // resources/views/admin/content/assessments/pdf.blade.php).
        $defaultType = old('type', request()->filled('assessment_id') ? 'assessment_ref' : 'section');
        $defaultAssessmentId = old('assessment_id', request('assessment_id'));
        // SỬA 26/8 ("gộp Học liệu vào Sản phẩm & quyền"): $selectedProductId có khi vào từ nút
        // "+ Thêm học liệu" ở trang chi tiết 1 sản phẩm — dùng để tự điền sẵn dropdown + đưa
        // link "Quay lại"/"Huỷ" về đúng trang sản phẩm đó thay vì Nội dung.
        $selectedProductId = $selectedProductId ?? null;
        $backHref = $selectedProductId ? route('admin.products.show', $selectedProductId) : route('admin.products.index');
        $backLabel = $selectedProductId ? '‹ Quay lại sản phẩm' : '‹ Quay lại Sản phẩm & quyền';
    @endphp

    <a href="{{ $backHref }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">{{ $backLabel }}</a>

    <x-page-header title="📦 Tạo học liệu" subtitle="Học liệu là chương/bài/mục thuộc một sản phẩm (sách, chuyên đề, đề thi, khóa học) — 6.5." />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="bg-white rounded-2xl border border-slate-200 p-6"
         x-data="{
            type: '{{ $defaultType }}',
            productId: '{{ old('product_id', $selectedProductId) }}',
            selectedParentId: '{{ old('parent_id') }}',
            chaptersByProduct: {{ json_encode($chaptersByProduct) }},
            chapterLabelByProduct: {{ json_encode($chapterLabelByProduct) }},
            get chapterLabel() { return this.chapterLabelByProduct[this.productId] || null },
            get chapters() { return this.chaptersByProduct[this.productId] || [] },
         }">
        <form method="POST" action="{{ route('admin.content.materials.store') }}" class="space-y-4" enctype="multipart/form-data">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="product_id">Thuộc sản phẩm</label>
                <x-select id="product_id" name="product_id" required x-model="productId" @change="selectedParentId = ''">
                    <option value="">— Chọn sản phẩm —</option>
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}" @selected((string) old('product_id', $selectedProductId) === (string) $p->id)>{{ $p->title }}</option>
                    @endforeach
                </x-select>
            </div>

            {{-- SỬA 4/9 (khách yêu cầu "chỗ thêm file học liệu thì cho chọn chương/phần/đề") —
                 chỉ hiện khi sản phẩm đang chọn thuộc loại có khái niệm này (sách/chuyên đề/bộ
                 đề — không hiện với Khóa học, xem ProductType::chapterLabel()). Nhãn field tự
                 đổi theo loại sản phẩm: "Thuộc chương"/"Thuộc phần"/"Thuộc đề". --}}
            <div x-show="chapterLabel" x-cloak>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="parent_id" x-text="'Thuộc ' + (chapterLabel || '').toLowerCase()"></label>
                <template x-if="chapters.length > 0">
                    <x-select id="parent_id" name="parent_id" x-model="selectedParentId">
                        <option value="">— Chưa gắn —</option>
                        <template x-for="c in chapters" :key="c.id">
                            <option :value="String(c.id)" x-text="c.title"></option>
                        </template>
                    </x-select>
                </template>
                <template x-if="chapters.length === 0">
                    <p class="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2"
                       x-text="'Tài liệu này chưa có ' + (chapterLabel || '').toLowerCase() + ' nào — vào trang tài liệu để tạo trước.'"></p>
                </template>
            </div>

            {{-- SỬA 26/8: ẩn field "Loại" — mặc định luôn "Bài/Mục", khỏi cần admin chọn tay
                 (trường hợp assessment_ref vẫn tự nhận qua $defaultType ở trên). Vẫn phải gửi
                 lên vì 'type' là required ở materialsStore(), nên giữ input ẩn thay vì bỏ hẳn. --}}
            <input type="hidden" name="type" x-model="type">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="order">Thứ tự hiển thị</label>
                    <input id="order" name="order" type="number" min="0" value="{{ old('order', 0) }}"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="title">Tiêu đề</label>
                <input id="title" name="title" type="text" value="{{ old('title') }}" required maxlength="255"
                       placeholder="Ví dụ: Chương 1 - Nhập môn"
                       class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
            </div>

            <div x-show="type === 'assessment_ref'" x-cloak>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="assessment_id">Đề/bộ bài tham chiếu</label>
                <x-select id="assessment_id" name="assessment_id">
                    <option value="">— Chọn đề/bộ bài —</option>
                    @foreach ($assessments as $a)
                        <option value="{{ $a->id }}" @selected((string) $defaultAssessmentId === (string) $a->id)>{{ $a->title }}</option>
                    @endforeach
                </x-select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="status">Trạng thái</label>
                <x-select id="status" name="status" required>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', 'draft') === $value)>{{ $label }}</option>
                    @endforeach
                </x-select>
            </div>

            {{--
                SỬA 25/8 (tải bài — 2 trường MỚI, đều TÙY CHỌN, xem ContentService::materialStore()):
                bỏ trống "Mã bài" mà có tải PDF thì hệ thống tự đặt mã theo tên tệp; bỏ trống cả 2
                thì Material vẫn tạo được như trước (dùng làm mục lục/chương cha không cần nội dung).
            --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="code">Mã bài (tùy chọn)</label>
                    <input id="code" name="code" type="text" value="{{ old('code') }}" maxlength="60"
                           placeholder="Để trống sẽ tự đặt theo tên tệp"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="pdf">Tệp PDF bài học (tùy chọn)</label>
                    <input id="pdf" name="pdf" type="file" accept="application/pdf"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2 file:mr-3 file:rounded-lg file:border-0 file:bg-rose-50 file:text-rose-600 file:px-3 file:py-1 file:text-sm hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                </div>
            </div>

            {{--
                SỬA 4/9 (khách yêu cầu: "file học liệu có thể là audio, pdf, ảnh động...") — 2
                tệp MỚI, ĐỘC LẬP với PDF ở trên, cả 2 đều tùy chọn, xem ContentService::
                materialStore()/storeMaterialAudio()/storeMaterialImage().
            --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="audio">🔊 Tệp audio (tùy chọn)</label>
                    <input id="audio" name="audio" type="file" accept="audio/*"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2 file:mr-3 file:rounded-lg file:border-0 file:bg-rose-50 file:text-rose-600 file:px-3 file:py-1 file:text-sm hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    <p class="text-xs text-slate-400 mt-1">mp3/wav/ogg/m4a/aac — tối đa {{ number_format(\App\Services\Admin\ContentService::maxMaterialAudioKb() / 1024) }}MB.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="image">🖼️ Tệp ảnh (tùy chọn, hỗ trợ GIF động)</label>
                    <input id="image" name="image" type="file" accept="image/*"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2 file:mr-3 file:rounded-lg file:border-0 file:bg-rose-50 file:text-rose-600 file:px-3 file:py-1 file:text-sm hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    <p class="text-xs text-slate-400 mt-1">jpg/png/gif/webp — tối đa {{ number_format(\App\Services\Admin\ContentService::maxMaterialImageKb() / 1024) }}MB.</p>
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">Tạo học liệu</button>
                <a href="{{ $backHref }}" class="px-5 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">Huỷ</a>
            </div>
        </form>
    </div>
@endsection
