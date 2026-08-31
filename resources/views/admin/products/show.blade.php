@extends('layouts.admin')

@section('title', $product->title)
@section('page-title', 'Chi tiết sản phẩm')

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
        // SỬA 26/8 ("gộp Học liệu vào Sản phẩm & quyền"): $materialsTree — xem
        // App\Services\Admin\ProductService::buildMaterialsTree().
        $materialsTree = $materialsTree ?? [];
        // SỬA 31/8 ("ZIP bài tập" gắn vào sản phẩm) — danh sách bài tập, xem
        // App\Services\Admin\ContentService::productExercisesFor().
        $exercises = $exercises ?? [];
    @endphp

    <a href="{{ route('admin.products.index') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Sản phẩm</a>

    @if (in_array(session('status'), ['product-created', 'product-updated'], true))
        @include('partials.toast-flash', ['type' => 'success', 'message' => session('status') === 'product-created' ? 'Đã tạo sản phẩm mới.' : 'Đã lưu thay đổi.'])
    @endif
    @if (session('status') === 'material-deleted')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã xoá học liệu cùng bài con và file PDF liên quan.'])
    @elseif (session('status') === 'materials-bulk-imported')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tải lên '.session('bulkCreatedCount').' bài — vào từng bài nếu cần sửa tên/mã/PDF.'])
    @elseif (session('status') === 'material-updated')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu thay đổi học liệu.'])
    @elseif (session('status') === 'exercise-parsed')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã đọc xong gói ZIP — kiểm tra lại thông tin rồi bấm "Lưu bài tập" để hoàn tất.'])
    @elseif (session('status') === 'exercise-saved')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu bài tập.'])
    @elseif (session('status') === 'exercise-deleted')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã xoá bài tập.'])
    @endif

    <div class="rounded-3xl bg-gradient-to-br from-sky-100 via-white to-rose-50 p-6 lg:p-8 mb-6 flex items-start justify-between gap-4 flex-wrap">
        <div class="flex items-start gap-4">
            <x-icon-tile emoji="🎫" tone="rose" />
            <div>
                <div class="flex items-center gap-2 flex-wrap mb-1">
                    <h1 class="text-xl lg:text-2xl font-semibold text-slate-800">{{ $product->title }}</h1>
                    <x-status-badge :tone="$meta['tone']">{{ $meta['label'] }}</x-status-badge>
                </div>
                <p class="text-sm text-slate-500">
                    {{ $typeLabels[$product->type->value] ?? $product->type->value }}
                    · Giá học: {{ number_format($product->price) }}đ
                    · Giá dạy: {{ number_format($product->price_teaching) }}đ
                    · Hiển thị: {{ $product->visibility->value === 'public' ? 'Công khai' : 'Riêng tư' }}
                </p>
            </div>
        </div>
        <a href="{{ route('admin.products.edit', $product->id) }}"
           class="px-4 py-2 rounded-lg border border-slate-200 bg-white text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition shrink-0">
            ✏️ Sửa
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-5">
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h2 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span>📝</span> Mô tả</h2>
                @if ($product->description)
                    <div class="rich-content text-sm text-slate-600 leading-relaxed">{!! $product->description !!}</div>
                @else
                    <p class="text-sm text-slate-400">Chưa có mô tả.</p>
                @endif
            </div>

            {{-- SỬA 27/8 (2 — "bỏ mục học liệu thuộc sản phẩm đi"): đã bỏ khối "Học liệu thuộc
                 sản phẩm" (cây chương/mục PDF cũ) ở đây theo yêu cầu — quản lý học liệu (nếu
                 còn cần) vẫn còn nguyên ở admin.content.materials.* (route/controller/service
                 KHÔNG bị xoá, chỉ bỏ lối vào từ trang này). SỬA 27/8 ("4 file đính kèm sản
                 phẩm") — 3 tài nguyên dưới đây upload/thay qua nút "Sửa sản phẩm" (cùng form,
                 xem admin/products/edit.blade.php). --}}
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h2 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span>📎</span> Tài nguyên đính kèm</h2>
                @php
                    // SỬA 31/8 — "ZIP bài tập" (1 file duy nhất) không còn upload MỚI được qua
                    // form Sửa nữa (xem mục "🧪 Bài tập đính kèm" bên dưới) — chỉ còn hiện ở đây
                    // NẾU sản phẩm này có file cũ từ trước, để không mất khả năng xem/tải dữ
                    // liệu đã có (route access.resource, kind=exercise vẫn hoạt động bình
                    // thường). Sản phẩm mới sẽ không bao giờ có dòng này.
                    $extraResources = [
                        ['label' => 'File PDF', 'path' => $product->content_pdf_path, 'name' => $product->content_pdf_original_name],
                        ['label' => 'PDF hướng dẫn', 'path' => $product->guide_pdf_path, 'name' => $product->guide_pdf_original_name],
                        ['label' => 'Học liệu (ảnh động/audio)', 'path' => $product->media_path, 'name' => $product->media_original_name],
                    ];
                    if ($product->exercise_zip_path) {
                        array_splice($extraResources, 2, 0, [[
                            'label' => 'ZIP bài tập (cũ)', 'path' => $product->exercise_zip_path, 'name' => $product->exercise_zip_original_name,
                        ]]);
                    }
                @endphp
                <div class="divide-y divide-slate-100">
                    @foreach ($extraResources as $res)
                        <div class="flex items-center justify-between gap-3 py-2.5">
                            <span class="text-sm text-slate-600 shrink-0">{{ $res['label'] }}</span>
                            @if ($res['path'])
                                <span class="text-xs text-emerald-600 font-medium truncate min-w-0">✓ {{ $res['name'] }}</span>
                            @else
                                <span class="text-xs text-slate-400">Chưa có</span>
                            @endif
                        </div>
                    @endforeach
                </div>
                <a href="{{ route('admin.products.edit', $product->id) }}" class="text-sm text-rose-600 font-medium mt-3 inline-block">Thêm/thay file ›</a>
            </div>

            {{-- SỬA 31/8 ("ZIP bài tập" — nhập bằng ZIP, không giới hạn số lượng, chấm kiểu thi
                 online khi học sinh làm): xem App\Services\Admin\ContentService::productExercise*()
                 + Admin\ProductExerciseController. CHỈ Admin quản lý mục này (route cùng nhóm
                 middleware role:admin,super_admin với admin.products.* — routes/web.php) —
                 giáo viên KHÔNG có nút thêm/sửa/xoá; học sinh/giáo viên "Làm bài" ở trang "Tài
                 liệu của tôi" (student/teacher materials/mine.blade.php). --}}
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <div class="flex items-center justify-between mb-1 flex-wrap gap-2">
                    <h2 class="font-medium text-slate-700 flex items-center gap-2"><span>🧪</span> Bài tập đính kèm</h2>
                    <span class="text-xs text-slate-400">{{ count($exercises) }} bài</span>
                </div>
                <p class="text-xs text-slate-400 mb-3">
                    Chọn 1 gói ZIP (định dạng OT360-QPACK) — hệ thống tự đọc đề bài + test case, bạn
                    chỉ cần kiểm tra lại rồi bấm "Lưu bài tập". Không giới hạn số lượng bài — thêm
                    xong 1 bài mới được thêm bài tiếp theo.
                </p>

                <form action="{{ route('admin.products.exercises.store', $product->id) }}" method="POST" enctype="multipart/form-data" class="flex items-center gap-3 flex-wrap mb-4 p-3 rounded-xl bg-slate-50 border border-dashed border-slate-200">
                    @csrf
                    <input type="file" name="zip_package" accept=".zip" required
                           class="text-sm text-slate-600 flex-1 min-w-[200px] file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-rose-50 file:text-rose-600 file:text-sm">
                    <button type="submit" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium hover:bg-rose-700 transition shrink-0">
                        ⬆️ Thêm bài tập từ ZIP
                    </button>
                </form>

                @if (empty($exercises))
                    <x-empty-state title="Chưa có bài tập nào" description="Thêm gói ZIP đầu tiên ở ô trên để bắt đầu." />
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach ($exercises as $ex)
                            <div class="flex items-center justify-between gap-3 py-3 flex-wrap">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-slate-700 truncate">{{ $ex['title'] }} <span class="text-xs font-normal text-slate-400">· {{ $ex['typeLabel'] }}</span></p>
                                    <p class="text-xs text-slate-400">
                                        {{ $ex['points'] }} điểm · {{ $ex['summary'] }}
                                        @if (!empty($ex['tags']))
                                            · {{ implode(', ', $ex['tags']) }}
                                        @endif
                                        · {{ $ex['createdAt'] }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-3 shrink-0">
                                    <a href="{{ route('admin.products.exercises.edit', [$product->id, $ex['id']]) }}" class="text-sm text-rose-600 font-medium">Sửa</a>
                                    <form action="{{ route('admin.products.exercises.destroy', [$product->id, $ex['id']]) }}" method="POST" onsubmit="return confirm('Xoá bài tập này? Không thể hoàn tác.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm text-slate-400 hover:text-rose-600">Xoá</button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Note họp 13/8 mục 2: "Có danh sách các quyền được cấp, cần danh sách phê
                 duyệt hoặc là xem đã thanh toán lúc nào khi người ta mua sản phẩm". --}}
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-medium text-slate-700 flex items-center gap-2"><span>🔑</span> Quyền đã cấp cho sản phẩm này</h2>
                    <span class="text-xs text-slate-400">{{ $accessRightCount }} quyền</span>
                </div>

                @if (empty($accessRightRows))
                    <x-empty-state title="Chưa cấp quyền nào cho sản phẩm này" description="Quyền được cấp khi người dùng mua và kích hoạt mã (7.4), hoặc khi Admin cấp trực tiếp." />
                @else
                    <div class="overflow-x-auto">
                        <x-data-table :columns="['Người dùng', 'Loại quyền', 'Trạng thái', 'Hiệu lực', 'Nguồn cấp', 'Đơn hàng / thanh toán', '']">
                            @foreach ($accessRightRows as $row)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 text-sm text-slate-700">{{ $row['userName'] }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-600">{{ $row['scopeLabel'] }}</td>
                                    <td class="px-4 py-3"><x-status-badge :tone="$row['tone']">{{ $row['statusLabel'] }}</x-status-badge></td>
                                    <td class="px-4 py-3 text-xs text-slate-500">
                                        {{ $row['startsAt']?->format('d/m/Y') }} — {{ $row['expiresAt']?->format('d/m/Y') ?? 'Không giới hạn' }}
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
                                    <td class="px-4 py-3 text-sm">
                                        <a href="{{ route('admin.access-rights.show', $row['id']) }}" class="text-rose-600 font-medium">Xem</a>
                                    </td>
                                </tr>
                            @endforeach
                        </x-data-table>
                    </div>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-5 space-y-4">
            <h3 class="font-medium text-slate-700 flex items-center gap-2"><span>ℹ️</span> Thông tin sản phẩm</h3>
            <div class="text-sm space-y-3">
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
