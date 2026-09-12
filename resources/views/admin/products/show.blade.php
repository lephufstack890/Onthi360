@extends('layouts.admin')

@section('title', $product->title)
@section('page-title', 'Chi tiết tài liệu')

@section('content')
    @php
        $typeLabels = ['book' => 'Sách', 'topic' => 'Chuyên đề', 'exam' => 'Bộ đề', 'course' => 'Khóa học'];
        $statusMeta = [
            'draft' => ['label' => 'Bản nháp', 'tone' => 'neutral'],
            'pending_review' => ['label' => 'Chờ duyệt', 'tone' => 'warning'],
            'published' => ['label' => 'Xuất bản', 'tone' => 'success'],
            'archived' => ['label' => 'Lưu trữ', 'tone' => 'neutral'],
        ];
        $meta = $statusMeta[$product->status->value] ?? ['label' => $product->status->value, 'tone' => 'neutral'];
        $accessRightRows = $accessRightRows ?? [];
        $accessRightCount = $accessRightCount ?? 0;
        $materialsTree = $materialsTree ?? [];
        $exercises = $exercises ?? [];
        $chapterLabel = $product->chapterLabel();
        $chapters = $chapters ?? [];
        $materialsList = $materialsList ?? [];
    @endphp

    <a href="{{ route('admin.products.index') }}" class="text-[13px] text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-blue-600">‹ Quay lại Tài liệu</a>

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    @if (in_array(session('status'), ['product-created', 'product-updated'], true))
        @include('partials.toast-flash', ['type' => 'success', 'message' => session('status') === 'product-created' ? 'Đã tạo tài liệu mới.' : 'Đã lưu thay đổi.'])
    @endif
    @if (session('status') === 'material-deleted')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã xoá học liệu cùng bài con và file PDF liên quan.'])
    @elseif (session('status') === 'materials-bulk-imported')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tải lên '.session('bulkCreatedCount').' bài — vào từng bài nếu cần sửa tên/mã/PDF.'])
    @elseif (session('status') === 'material-created')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã thêm học liệu.'])
    @elseif (session('status') === 'material-updated')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu thay đổi học liệu.'])
    @elseif (session('status') === 'exercise-parsed')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã đọc xong gói ZIP — kiểm tra lại thông tin rồi bấm "Lưu bài tập" để hoàn tất.'])
    @elseif (session('status') === 'exercise-saved')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu bài tập.'])
    @elseif (session('status') === 'exercise-deleted')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã xoá bài tập.'])
    @elseif (session('status') === 'chapter-created')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã thêm '.mb_strtolower($chapterLabel ?? 'mục').'.'])
    @elseif (session('status') === 'chapter-updated')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu thay đổi.'])
    @elseif (session('status') === 'chapter-deleted')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã xoá.'])
    @endif

    <div class="rounded-3xl border border-sky-100 bg-gradient-to-br from-sky-50 via-white to-blue-50 p-5 lg:p-6 mb-4 shadow-[0_2px_8px_rgba(0,90,180,.04)] flex items-start justify-between gap-4 flex-wrap">
        <div class="flex items-start gap-4">
            <x-admin.icon-tile emoji="🎫" tone="rose" />
            <div>
                <div class="flex items-center gap-2 flex-wrap mb-1">
                    <h1 class="text-xl lg:text-2xl font-semibold text-slate-800">{{ $product->title }}</h1>
                    <x-admin.badge :tone="$meta['tone']">{{ $meta['label'] }}</x-admin.badge>
                </div>
                <p class="text-[13px] text-slate-500">
                    {{ $typeLabels[$product->type->value] ?? $product->type->value }}
                    · Giá học: {{ number_format($product->price) }}đ
                    · Giá dạy: {{ number_format($product->price_teaching) }}đ
                    · Hiển thị: {{ $product->visibility->value === 'public' ? 'Công khai' : 'Riêng tư' }}
                </p>
            </div>
        </div>
        <a href="{{ route('admin.products.edit', $product->id) }}"
           class="px-4 py-2 rounded-xl border border-sky-100 bg-white text-slate-600 text-[13px] font-medium hover:border-blue-200 hover:text-blue-600 transition shrink-0">
            ✏️ Sửa
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-5">
            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                <h2 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span><x-lucide name="pen-line" class="h-4 w-4" /></span> Mô tả</h2>
                @if ($product->description)
                    <div class="rich-content text-[13px] text-slate-600 leading-relaxed">{!! $product->description !!}</div>
                @else
                    <p class="text-[13px] text-slate-400">Chưa có mô tả.</p>
                @endif
            </div>

            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                <h2 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span><x-lucide name="file-text" class="h-4 w-4" /></span> Tài nguyên đính kèm</h2>
                @php
                    $extraResources = [
                        ['label' => 'File PDF', 'path' => $product->content_pdf_path, 'name' => $product->content_pdf_original_name],
                        ['label' => 'PDF hướng dẫn', 'path' => $product->guide_pdf_path, 'name' => $product->guide_pdf_original_name],
                    ];
                    if ($product->exercise_zip_path) {
                        $extraResources[] = [
                            'label' => 'ZIP bài tập (cũ)', 'path' => $product->exercise_zip_path, 'name' => $product->exercise_zip_original_name,
                        ];
                    }
                    if ($product->media_path) {
                        $extraResources[] = [
                            'label' => 'Học liệu (ảnh động/audio, cũ)', 'path' => $product->media_path, 'name' => $product->media_original_name,
                        ];
                    }
                @endphp
                <div class="divide-y divide-slate-100">
                    @foreach ($extraResources as $res)
                        <div class="flex items-center justify-between gap-3 py-2.5">
                            <span class="text-[13px] text-slate-600 shrink-0">{{ $res['label'] }}</span>
                            @if ($res['path'])
                                <span class="text-xs text-emerald-600 font-medium truncate min-w-0">✓ {{ $res['name'] }}</span>
                            @else
                                <span class="text-xs text-slate-400">Chưa có</span>
                            @endif
                        </div>
                    @endforeach
                </div>
                <a href="{{ route('admin.products.edit', $product->id) }}" class="text-[13px] text-blue-600 font-medium mt-3 inline-block">Thêm/thay file ›</a>
            </div>

            @if ($chapterLabel)
                <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5" x-data="{ editing: null }">
                    <div class="flex items-center justify-between mb-1 flex-wrap gap-2">
                        <h2 class="font-medium text-slate-700 flex items-center gap-2"><span><x-lucide name="book-open" class="h-4 w-4" /></span> {{ $chapterLabel }}</h2>
                        <span class="text-xs text-slate-400">{{ count($chapters) }} mục</span>
                    </div>
                    <p class="text-xs text-slate-400 mb-3">
                        Chỉ cần đặt tên — dùng để gắn bài tập/học liệu vào đúng {{ mb_strtolower($chapterLabel) }} này.
                    </p>

                    <form action="{{ route('admin.products.chapters.store', $product->id) }}" method="POST"
                          class="flex items-center gap-3 flex-wrap mb-4 p-3 rounded-xl bg-slate-50 border border-dashed border-sky-100">
                        @csrf
                        <input type="text" name="title" required maxlength="255" placeholder="Tên {{ mb_strtolower($chapterLabel) }} mới..."
                               class="flex-1 min-w-[200px] rounded-xl border border-sky-100 text-[13px] p-2.5 hover:border-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-300 transition">
                        <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 transition-colors text-white text-[13px] font-medium shrink-0">
                            + Thêm {{ mb_strtolower($chapterLabel) }}
                        </button>
                    </form>

                    @if (empty($chapters))
                        <x-admin.empty-state :title="'Chưa có '.mb_strtolower($chapterLabel).' nào'" description="Thêm mục đầu tiên ở ô trên để bắt đầu gắn bài tập/học liệu." />
                    @else
                        <div class="divide-y divide-slate-100">
                            @foreach ($chapters as $c)
                                <div class="py-2.5" x-show="editing !== {{ $c['id'] }}">
                                    <div class="flex items-center justify-between gap-3 flex-wrap">
                                        <div class="min-w-0">
                                            <p class="text-[13px] font-medium text-slate-700 truncate">{{ $c['title'] }}</p>
                                            <p class="text-xs text-slate-400">{{ $c['questionsCount'] }} bài tập</p>
                                        </div>
                                        <div class="flex items-center gap-3 shrink-0">
                                            <button type="button" @click="editing = {{ $c['id'] }}" class="text-[13px] text-blue-600 font-medium">Sửa</button>
                                            <form action="{{ route('admin.products.chapters.destroy', [$product->id, $c['id']]) }}" method="POST" onsubmit="return confirm('Xoá mục này? Không thể hoàn tác.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-xs font-bold text-slate-400 transition-colors hover:text-blue-600">Xoá</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <div class="py-2.5" x-show="editing === {{ $c['id'] }}" x-cloak>
                                    <form action="{{ route('admin.products.chapters.update', [$product->id, $c['id']]) }}" method="POST" class="flex items-center gap-2 flex-wrap">
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="title" value="{{ $c['title'] }}" required maxlength="255"
                                               class="flex-1 min-w-[160px] rounded-xl border border-sky-100 text-[13px] p-2">
                                        <input type="number" name="order" value="{{ $c['order'] }}" min="0"
                                               class="w-20 rounded-xl border border-sky-100 text-[13px] p-2" title="Thứ tự">
                                        <button type="submit" class="px-3 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 transition-colors text-white text-xs font-semibold">Lưu</button>
                                        <button type="button" @click="editing = null" class="px-3 py-2 rounded-xl border border-sky-100 text-slate-500 text-xs font-medium hover:border-blue-200 hover:text-blue-600 transition-colors">Huỷ</button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                <div class="flex items-center justify-between mb-1 flex-wrap gap-2">
                    <h2 class="font-medium text-slate-700 flex items-center gap-2"><span><x-lucide name="layers" class="h-4 w-4" /></span> Bài tập đính kèm</h2>
                    <span class="text-xs text-slate-400">{{ count($exercises) }} bài</span>
                </div>
                <p class="text-xs text-slate-400 mb-3">
                    Chọn 1 gói ZIP (định dạng OT360-QPACK) — hệ thống tự đọc đề bài + test case, bạn
                    chỉ cần kiểm tra lại rồi bấm "Lưu bài tập". Không giới hạn số lượng bài — thêm
                    xong 1 bài mới được thêm bài tiếp theo. Hoặc bấm "Thêm thủ công" để tự soạn.
                </p>

                <div class="flex items-center gap-3 flex-wrap mb-4 p-3 rounded-xl bg-slate-50 border border-dashed border-sky-100">
                    <form action="{{ route('admin.products.exercises.store', $product->id) }}" method="POST" enctype="multipart/form-data" class="flex items-center gap-3 flex-wrap flex-1 min-w-[240px]">
                        @csrf
                        <input type="file" name="zip_package" accept=".zip" required
                               class="text-[13px] text-slate-600 flex-1 min-w-[200px] file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-blue-50 file:text-blue-600 file:text-[13px]">
                        <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 transition-colors text-white text-[13px] font-medium shrink-0">
                            ⬆️ Thêm từ ZIP
                        </button>
                    </form>
                    <a href="{{ route('admin.products.exercises.createManual', $product->id) }}"
                       class="px-4 py-2 rounded-xl border border-sky-100 bg-white text-slate-600 text-[13px] font-medium hover:border-blue-200 hover:text-blue-600 transition-colors shrink-0">
                        ✏️ Thêm thủ công
                    </a>
                </div>

                @if (empty($exercises))
                    <x-admin.empty-state title="Chưa có bài tập nào" description="Thêm gói ZIP hoặc soạn thủ công ở ô trên để bắt đầu." />
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach ($exercises as $ex)
                            <div class="flex items-center justify-between gap-3 py-3 flex-wrap">
                                <div class="min-w-0">
                                    <p class="text-[13px] font-medium text-slate-700 truncate">
                                        {{ $ex['title'] }} <span class="text-xs font-normal text-slate-400">· {{ $ex['typeLabel'] }}</span>
                                        @if ($ex['chapterTitle'])
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-slate-100 text-xs font-medium text-slate-500 ml-1">{{ $ex['chapterTitle'] }}</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-slate-400">
                                        {{ $ex['points'] }} điểm · {{ $ex['summary'] }}
                                        @if (!empty($ex['tags']))
                                            · {{ implode(', ', $ex['tags']) }}
                                        @endif
                                        · {{ $ex['createdAt'] }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-3 shrink-0">
                                    <a href="{{ route('admin.products.exercises.edit', [$product->id, $ex['id']]) }}" class="text-[13px] text-blue-600 font-medium">Sửa</a>
                                    <form action="{{ route('admin.products.exercises.destroy', [$product->id, $ex['id']]) }}" method="POST" onsubmit="return confirm('Xoá bài tập này? Không thể hoàn tác.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-bold text-slate-400 transition-colors hover:text-blue-600">Xoá</button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($chapterLabel)
                <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                    <div class="flex items-center justify-between mb-1 flex-wrap gap-2">
                        <h2 class="font-medium text-slate-700 flex items-center gap-2"><span><x-lucide name="library" class="h-4 w-4" /></span> Học liệu theo {{ mb_strtolower($chapterLabel) }}</h2>
                        <span class="text-xs text-slate-400">{{ count($materialsList) }} học liệu</span>
                    </div>
                    <p class="text-xs text-slate-400 mb-3">
                        File PDF/audio/ảnh (kể cả ảnh động) đính kèm — 1 học liệu có thể có cả 3 loại
                        cùng lúc, gắn vào đúng {{ mb_strtolower($chapterLabel) }} để học sinh dễ tìm.
                    </p>

                    <a href="{{ route('admin.content.materials.create', ['product_id' => $product->id]) }}"
                       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 transition-colors text-white text-[13px] font-medium mb-4">
                        ⬆️ Thêm học liệu
                    </a>

                    @if (empty($materialsList))
                        <x-admin.empty-state title="Chưa có học liệu nào" description="Thêm học liệu đầu tiên ở nút trên." />
                    @else
                        <div class="divide-y divide-slate-100">
                            @foreach ($materialsList as $m)
                                <div class="flex items-center justify-between gap-3 py-3 flex-wrap">
                                    <div class="min-w-0">
                                        <p class="text-[13px] font-medium text-slate-700 truncate">{{ $m['title'] }}</p>
                                        <p class="text-xs text-slate-400">
                                            {{ $m['chapterTitle'] ?? 'Chưa gắn '.mb_strtolower($chapterLabel) }}
                                            @if ($m['hasPdf']) · 📄 PDF @endif
                                            @if ($m['hasAudio']) · 🔊 Audio @endif
                                            @if ($m['hasImage']) · 🖼️ Ảnh @endif
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-3 shrink-0">
                                        <x-admin.badge :tone="$m['statusTone']">{{ $m['statusLabel'] }}</x-admin.badge>
                                        <a href="{{ route('admin.content.materials.edit', $m['id']) }}" class="text-[13px] text-blue-600 font-medium">Sửa</a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-medium text-slate-700 flex items-center gap-2"><span><x-lucide name="ticket" class="h-4 w-4" /></span> Quyền đã cấp cho tài liệu này</h2>
                    <span class="text-xs text-slate-400">{{ $accessRightCount }} quyền</span>
                </div>

                @if (empty($accessRightRows))
                    <x-admin.empty-state title="Chưa cấp quyền nào cho tài liệu này" description="Quyền được cấp khi người dùng mua và kích hoạt mã (7.4), hoặc khi Admin cấp trực tiếp." />
                @else
                    <div class="overflow-x-auto">
                        <x-admin.table :columns="['Người dùng', 'Loại quyền', 'Trạng thái', 'Nguồn cấp', 'Đơn hàng / thanh toán', '']">
                            @foreach ($accessRightRows as $row)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 text-[13px] text-slate-700">{{ $row['userName'] }}</td>
                                    <td class="px-4 py-3 text-[13px] text-slate-600">{{ $row['scopeLabel'] }}</td>
                                    <td class="px-4 py-3">
                                        <x-admin.badge :tone="$row['tone']">{{ $row['statusLabel'] }}</x-admin.badge>
                                        <p class="text-xs text-slate-400 mt-1">
                                            {{ $row['startsAt']?->format('d/m/Y') }} — {{ $row['expiresAt']?->format('d/m/Y') ?? 'Không giới hạn' }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-slate-500">{{ $row['sourceLabel'] }}</td>
                                    <td class="px-4 py-3 text-xs text-slate-500">
                                        @if ($row['orderNo'])
                                            #{{ $row['orderNo'] }}
                                            @if ($row['paidAt'])
                                                <span class="block text-slate-400">Duyệt/thanh toán: {{ $row['paidAt']->format('d/m/Y H:i') }}</span>
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-[13px]">
                                        <a href="{{ route('admin.access-rights.show', $row['id']) }}" class="text-blue-600 font-medium">Xem</a>
                                    </td>
                                </tr>
                            @endforeach
                        </x-admin.table>
                    </div>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-3xl border border-sky-100 p-5 space-y-4">
            <h3 class="font-medium text-slate-700 flex items-center gap-2"><span><x-lucide name="info" class="h-4 w-4" /></span> Thông tin tài liệu</h3>
            <div class="text-[13px] space-y-3">
                <div><p class="text-slate-400 text-xs">Môn học / Khối / Chuyên đề</p><p class="text-slate-700">{{ collect([$product->subject, $product->grade, $product->topic])->filter()->implode(' · ') ?: '— Không chỉ định —' }}</p></div>
                <div><p class="text-slate-400 text-xs">Thời hạn quyền mặc định</p><p class="text-slate-700">{{ $product->duration_months ? $product->duration_months.' tháng' : 'Không giới hạn' }}</p></div>
                <div><p class="text-slate-400 text-xs">Bản in</p><p class="text-slate-700">{{ $product->has_print_option ? 'Có' : 'Không' }}</p></div>
                <div><p class="text-slate-400 text-xs">Đường dẫn công khai</p><p class="text-slate-700 break-all">/san-pham/{{ $product->slug }}</p></div>
                <div><p class="text-slate-400 text-xs">Ngày tạo</p><p class="text-slate-700">{{ $product->created_at?->format('d/m/Y H:i') }}</p></div>
            </div>
        </div>
    </div>

    @push('scripts')
        <style>
            .rich-content ul { list-style: disc; padding-left: 1.25rem; margin-bottom: 0.5rem; }
            .rich-content ol { list-style: decimal; padding-left: 1.25rem; margin-bottom: 0.5rem; }
            .rich-content p { margin-bottom: 0.5rem; }
        </style>
    @endpush
@endsection
