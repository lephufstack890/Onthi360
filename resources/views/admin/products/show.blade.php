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
    @endphp

    <a href="{{ route('admin.products.index') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Sản phẩm</a>

    @if (in_array(session('status'), ['product-created', 'product-updated'], true))
        @include('partials.toast-flash', ['type' => 'success', 'message' => session('status') === 'product-created' ? 'Đã tạo sản phẩm mới.' : 'Đã lưu thay đổi.'])
    @endif
    @if (session('status') === 'material-deleted')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã xoá học liệu cùng bài con và file PDF liên quan.'])
    @elseif (session('status') === 'materials-bulk-imported')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tải lên '.session('bulkCreatedCount').' bài — vào từng bài nếu cần sửa tên/mã/PDF.'])
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

            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <div class="flex items-center justify-between gap-3 flex-wrap mb-3">
                    <h2 class="font-medium text-slate-700 flex items-center gap-2"><span>📚</span> Học liệu thuộc sản phẩm</h2>
                    {{-- SỬA 26/8 ("gộp Học liệu vào Sản phẩm & quyền"): trước đây phải qua tab
                         "Học liệu" riêng ở Nội dung rồi tự chọn lại sản phẩm — giờ 2 nút này
                         luôn hiện ngay tại đây, đi thẳng tới đúng form đã điền sẵn sản phẩm
                         hiện tại (?product_id=), xem ContentController::materialsCreate()/
                         materialsBulkImportCreate(). --}}
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.content.materials.create', ['product_id' => $product->id]) }}" class="px-3 py-1.5 rounded-lg bg-rose-600 text-white text-xs font-medium">+ Thêm học liệu</a>
                        <a href="{{ route('admin.content.materials.bulk.create', ['product_id' => $product->id]) }}" class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 text-xs font-medium hover:border-rose-200 hover:text-rose-600 transition">+ Tải hàng loạt (ZIP)</a>
                    </div>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($materialsTree as $i => $node)
                        @include('partials.admin-materials-tree-item', ['item' => $node, 'depth' => 0])
                    @empty
                        <x-empty-state title="Chưa có học liệu nào" description="Bấm &quot;+ Thêm học liệu&quot; ở trên để tạo chương/bài/mục đầu tiên cho sản phẩm này (6.5)." :actionLabel="'+ Thêm học liệu'" :actionHref="route('admin.content.materials.create', ['product_id' => $product->id])" />
                    @endforelse
                </div>
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
