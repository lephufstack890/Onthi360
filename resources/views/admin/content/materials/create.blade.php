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
        // SỬA 4/9 (khách yêu cầu: "ẩn hết field đi chỉ để cho chọn chương với chọn các file") —
        // bỏ khỏi giao diện: Thứ tự hiển thị, Tiêu đề, Trạng thái, Mã bài — chỉ còn Thuộc sản
        // phẩm (ẩn luôn nếu đã biết sẵn từ ?product_id=), chọn chương/phần/đề, và 3 ô tệp.
        // - order: gửi ngầm = 0 (giữ nguyên mặc định cũ, admin không cần chỉnh tay nữa).
        // - status: gửi ngầm = 'published' — học liệu thêm thủ công lên thẳng luôn, không qua
        //   bước nháp/chờ duyệt (khớp cách bài tập thủ công cũng lưu Published ngay, xem
        //   ContentService::productExerciseStoreManual()).
        // - title: KHÔNG còn ô nhập tay — tự lấy theo TÊN TỆP admin vừa chọn (ưu tiên PDF, rồi
        //   audio, rồi ảnh — xem script cuối file), có placeholder mặc định phòng khi chưa chọn
        //   tệp nào (vẫn tạo được Material rỗng làm mục lục, 'title' đang required).
        // - code: bỏ hẳn (không gửi field) — ContentService::resolveMaterialCode() đã tự đặt mã
        //   theo tên tệp khi để trống, y hệt trước giờ.
        $lockProduct = (bool) $selectedProductId;
        $defaultTitle = old('title', 'Học liệu '.now()->format('d/m/Y H:i'));
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
            lockProduct: {{ $lockProduct ? 'true' : 'false' }},
            updateTitleFromFiles() {
                const pick = this.$refs.pdf.files[0] || this.$refs.audio.files[0] || this.$refs.image.files[0];
                if (pick) {
                    this.$refs.titleInput.value = pick.name.replace(/\.[^.]+$/, '');
                }
            },
         }">
        <form method="POST" action="{{ route('admin.content.materials.store') }}" class="space-y-4" enctype="multipart/form-data">
            @csrf

            {{-- SỬA 4/9 (khách yêu cầu "ẩn hết field... chỉ để chọn chương với chọn file") — chỉ
                 1 trong 2 phần tử dưới đây thực sự TỒN TẠI trong DOM tại 1 thời điểm (dùng
                 <template x-if>, KHÔNG dùng x-show — x-show chỉ ẩn bằng CSS nên nếu dùng ở đây
                 sẽ gửi trùng 2 giá trị "product_id" cùng lúc). lockProduct cố định ngay từ lúc
                 tải trang (true khi đã có sẵn ?product_id=, tức vào từ nút "+ Thêm học liệu" ở
                 trang 1 sản phẩm cụ thể — trường hợp thường gặp), nên không cần phản ứng động. --}}
            <template x-if="!lockProduct">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="product_id">Thuộc sản phẩm</label>
                    <x-select id="product_id" name="product_id" required x-model="productId" @change="selectedParentId = ''">
                        <option value="">— Chọn sản phẩm —</option>
                        @foreach ($products as $p)
                            <option value="{{ $p['id'] }}" @selected((string) old('product_id', $selectedProductId) === (string) $p['id'])>{{ $p['title'] }}</option>
                        @endforeach
                    </x-select>
                </div>
            </template>
            <template x-if="lockProduct">
                <input type="hidden" name="product_id" :value="productId">
            </template>

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

            {{-- SỬA 4/9 — ẩn "Thứ tự hiển thị"/"Tiêu đề"/"Trạng thái", xem ghi chú ở khối php phía trên. --}}
            <input type="hidden" name="order" value="{{ old('order', 0) }}">
            <input type="hidden" name="status" value="{{ old('status', 'published') }}">
            <input type="hidden" name="title" value="{{ $defaultTitle }}" x-ref="titleInput">

            <div x-show="type === 'assessment_ref'" x-cloak>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="assessment_id">Đề/bộ bài tham chiếu</label>
                <x-select id="assessment_id" name="assessment_id">
                    <option value="">— Chọn đề/bộ bài —</option>
                    @foreach ($assessments as $a)
                        <option value="{{ $a->id }}" @selected((string) $defaultAssessmentId === (string) $a->id)>{{ $a->title }}</option>
                    @endforeach
                </x-select>
            </div>

            {{--
                SỬA 25/8 (tải bài): bỏ trống PDF thì Material vẫn tạo được như trước (dùng làm
                mục lục/chương cha không cần nội dung). SỬA 4/9 (khách yêu cầu: "file học liệu
                có thể là audio, pdf, ảnh động..."): thêm 2 tệp audio/ảnh, ĐỘC LẬP với PDF, cả 3
                đều tùy chọn — xem ContentService::materialStore()/storeMaterialAudio()/
                storeMaterialImage(). "Mã bài" đã bỏ khỏi giao diện — để trống sẽ tự đặt theo
                tên tệp (resolveMaterialCode()).
            --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="pdf">📄 Tệp PDF (tùy chọn)</label>
                    <input id="pdf" name="pdf" type="file" accept="application/pdf" x-ref="pdf" @change="updateTitleFromFiles()"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2 file:mr-3 file:rounded-lg file:border-0 file:bg-rose-50 file:text-rose-600 file:px-3 file:py-1 file:text-sm hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="audio">🔊 Tệp audio (tùy chọn)</label>
                    <input id="audio" name="audio" type="file" accept="audio/*" x-ref="audio" @change="updateTitleFromFiles()"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2 file:mr-3 file:rounded-lg file:border-0 file:bg-rose-50 file:text-rose-600 file:px-3 file:py-1 file:text-sm hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    <p class="text-xs text-slate-400 mt-1">mp3/wav/ogg/m4a/aac — tối đa {{ number_format(\App\Services\Admin\ContentService::maxMaterialAudioKb() / 1024) }}MB.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="image">🖼️ Tệp ảnh (tùy chọn, hỗ trợ GIF động)</label>
                    <input id="image" name="image" type="file" accept="image/*" x-ref="image" @change="updateTitleFromFiles()"
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
