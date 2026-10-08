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

    {{--
      SỬA 8/10 (khách: "cập nhật lại UI màn tài liệu theo source mới, logic giữ nguyên") — trang chi tiết
      theo phong cách AdminContentWorkspace.jsx: đầu trang + huy hiệu, chỉ số tự tính từ dữ liệu đã có,
      các khối trắng bo tròn, thông tin nằm ở thẻ xanh nhạt bên phải. Mọi biến, route, form (thêm/sửa/xoá
      chương, thêm bài từ ZIP, học liệu, quyền đã cấp) và hộp xác nhận GIỮ NGUYÊN như bản cũ.
    --}}
    @include('partials.admin-products-ui')
    @php
        $badgeOf = fn ($tone) => match ($tone) {
            'success' => 'acx-badge--ok',
            'warning' => 'acx-badge--warn',
            'danger', 'error' => 'acx-badge--off',
            'info' => 'acx-badge--info',
            default => '',
        };
        $coverUrl = $product->cover_image_path ? asset('storage/'.$product->cover_image_path) : null;
        $typeIconMap = ['book' => 'book-open', 'topic' => 'layers', 'exam' => 'file-text', 'course' => 'graduation-cap'];
    @endphp

    <div class="acx-wrap">
        <a href="{{ route('admin.products.index') }}" class="acx-back">‹ Quay lại Tài liệu</a>

        <div class="acx-head">
            <div class="acx-head__id">
                @if ($coverUrl)
                    <img class="acx-head__thumb" src="{{ $coverUrl }}" alt="">
                @else
                    <span class="acx-avatar"><x-lucide :name="$typeIconMap[$product->type->value] ?? 'file-text'" /></span>
                @endif
                <div style="min-width:0">
                    <h1>{{ $product->title }}</h1>
                    <div class="acx-head__meta">
                        <span class="acx-badge {{ $badgeOf($meta['tone']) }}">{{ $meta['label'] }}</span>
                        <span>
                            {{ $typeLabels[$product->type->value] ?? $product->type->value }}
                            · Giá học: {{ number_format($product->price) }}đ
                            · Giá dạy: {{ number_format($product->price_teaching) }}đ
                            · Hiển thị: {{ $product->visibility->value === 'public' ? 'Công khai' : 'Riêng tư' }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="acx-head__actions">
                <a href="{{ route('admin.products.edit', $product->id) }}" class="acx-btn">
                    <x-lucide name="pen-line" /> Sửa
                </a>
            </div>
        </div>

        <div class="acx-stats" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr))">
            @if ($chapterLabel)
                <div class="acx-stat"><p>{{ $chapterLabel }}</p><strong>{{ count($chapters) }}</strong></div>
            @endif
            <div class="acx-stat"><p>Bài tập đính kèm</p><strong>{{ count($exercises) }}</strong></div>
            @if ($chapterLabel)
                <div class="acx-stat"><p>Học liệu</p><strong>{{ count($materialsList) }}</strong></div>
            @endif
            <div class="acx-stat"><p>Quyền đã cấp</p><strong>{{ $accessRightCount }}</strong></div>
        </div>

        <div class="acx-grid acx-grid--main">
            <div class="acx-stack">
                <section class="acx-card acx-card--white">
                    <h2><x-lucide name="pen-line" /> Mô tả</h2>
                    @if ($product->description)
                        <div class="rich-content acx-rich">{!! $product->description !!}</div>
                    @else
                        <p class="acx-note">Chưa có mô tả.</p>
                    @endif
                </section>

                <section class="acx-card acx-card--white">
                    <h2><x-lucide name="file-text" /> Tài nguyên đính kèm</h2>
                    @php
                        // SỬA 29/9 (khách chốt: "bỏ file pdf sách đi, chỗ chương mỗi chương là thêm
                        // từng file pdf") — bỏ dòng "File PDF" (tệp tổng của cả sản phẩm) khỏi đây.
                        // Nội dung đọc giờ nằm ở PDF của TỪNG chương/phần/đề, xem khối bên dưới.
                        // Cột content_pdf_path trong DB CỐ Ý giữ lại, không xoá: sản phẩm cũ đã tải
                        // tệp tổng vẫn đọc được (ProductReadService::partsFor() dùng làm tệp dự phòng
                        // khi chưa chương nào có PDF), khỏi phải chuyển dữ liệu trước khi lên bản mới.
                        // SỬA 29/9 (2) — khách: "bỏ File PDF tổng (kiểu cũ) đi, không cần hiển thị".
                        // Chỉ ẩn khỏi màn hình; dữ liệu vẫn nguyên trong DB và trang đọc vẫn dùng tệp
                        // này làm nội dung dự phòng khi sản phẩm chưa chương nào có PDF.
                        $extraResources = [
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
                    <div>
                        @foreach ($extraResources as $res)
                            <div class="apx-res">
                                <span>{{ $res['label'] }}</span>
                                @if ($res['path'])
                                    <span class="apx-ok">✓ {{ $res['name'] }}</span>
                                @else
                                    <span class="apx-muted">Chưa có</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <a href="{{ route('admin.products.edit', $product->id) }}" class="apx-more">Thêm/thay file ›</a>
                </section>

                @if ($chapterLabel)
                    <section class="acx-card acx-card--white" x-data="{ editing: null }">
                        <div class="apx-cardtop">
                            <h2><x-lucide name="book-open" /> {{ $chapterLabel }}</h2>
                            <span class="apx-count">{{ count($chapters) }} mục</span>
                        </div>
                        {{-- SỬA 29/9 (khách chốt: "chỗ chương mỗi chương là thêm từng file pdf") — mỗi
                             mục giờ mang LUÔN tệp PDF nội dung của nó. Học sinh/giáo viên mở trang đọc
                             sẽ thấy các tệp này nối lại thành một dải cuộn liền mạch theo đúng thứ tự
                             ở đây (xem App\Services\ProductReadService). --}}
                        <p class="apx-hint">
                            Đặt tên + tải tệp PDF nội dung của {{ mb_strtolower($chapterLabel) }} này. Người học đọc liền
                            mạch tất cả {{ mb_strtolower($chapterLabel) }} theo thứ tự bên dưới — cần đổi thứ tự thì bấm Sửa.
                        </p>

                        <div class="apx-add">
                            <form action="{{ route('admin.products.chapters.store', $product->id) }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <input type="text" name="title" required maxlength="255" placeholder="Tên {{ mb_strtolower($chapterLabel) }} mới..."
                                       class="apx-input apx-input--grow">
                                <input type="file" name="pdf" accept="application/pdf" class="apx-file">
                                <button type="submit" class="acx-btn acx-btn--primary acx-btn--sm">+ Thêm {{ mb_strtolower($chapterLabel) }}</button>
                            </form>
                        </div>

                        @if (empty($chapters))
                            <x-ws.empty-state :title="'Chưa có '.mb_strtolower($chapterLabel).' nào'" description="Thêm mục đầu tiên ở ô trên để bắt đầu gắn bài tập/học liệu." />
                        @else
                            <div class="apx-list">
                                @foreach ($chapters as $c)
                                    <div class="apx-item" x-show="editing !== {{ $c['id'] }}">
                                        <div class="apx-item__main">
                                            <p class="apx-item__title">{{ $c['title'] }}</p>
                                            <p class="apx-item__sub">
                                                @if ($c['hasPdf'])
                                                    <span class="apx-ok">✓ {{ $c['pdfName'] ?: 'Đã có PDF' }}</span>
                                                @else
                                                    <span class="apx-warn">⚠ Chưa có PDF nội dung</span>
                                                @endif
                                                · {{ $c['questionsCount'] }} bài tập
                                                @if (($c['materialsCount'] ?? 0) > 0)
                                                    · {{ $c['materialsCount'] }} học liệu
                                                @endif
                                            </p>
                                        </div>
                                        <div class="apx-item__side">
                                            <button type="button" @click="editing = {{ $c['id'] }}" class="acx-link">Sửa</button>
                                            <form action="{{ route('admin.products.chapters.destroy', [$product->id, $c['id']]) }}" method="POST" onsubmit="return confirm('Xoá mục này? Không thể hoàn tác.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="acx-link acx-link--del">Xoá</button>
                                            </form>
                                        </div>
                                    </div>
                                    <div x-show="editing === {{ $c['id'] }}" x-cloak>
                                        <form action="{{ route('admin.products.chapters.update', [$product->id, $c['id']]) }}" method="POST" enctype="multipart/form-data" class="apx-edit">
                                            @csrf
                                            @method('PUT')
                                            <input type="text" name="title" value="{{ $c['title'] }}" required maxlength="255" class="apx-input apx-input--grow">
                                            <input type="number" name="order" value="{{ $c['order'] }}" min="0" class="apx-input apx-input--num" title="Thứ tự">
                                            {{-- SỬA 29/9 — thay/thêm tệp PDF của chính mục này. Bỏ trống = giữ tệp đang có. --}}
                                            <input type="file" name="pdf" accept="application/pdf" class="apx-file">
                                            @if ($c['hasPdf'])
                                                <label>
                                                    <input type="checkbox" name="remove_pdf" value="1"> Xoá PDF
                                                </label>
                                            @endif
                                            <button type="submit" class="acx-btn acx-btn--primary acx-btn--sm">Lưu</button>
                                            <button type="button" @click="editing = null" class="acx-btn acx-btn--sm">Huỷ</button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </section>
                @endif

                <section class="acx-card acx-card--white">
                    <div class="apx-cardtop">
                        <h2><x-lucide name="layers" /> Bài tập đính kèm</h2>
                        <span class="apx-count">{{ count($exercises) }} bài</span>
                    </div>
                    <p class="apx-hint">
                        Chọn 1 gói ZIP (định dạng OT360-QPACK) — hệ thống tự đọc đề bài + test case, bạn
                        chỉ cần kiểm tra lại rồi bấm "Lưu bài tập". Không giới hạn số lượng bài — thêm
                        xong 1 bài mới được thêm bài tiếp theo. Hoặc bấm "Thêm thủ công" để tự soạn.
                    </p>

                    <div class="apx-add">
                        <form action="{{ route('admin.products.exercises.store', $product->id) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <input type="file" name="zip_package" accept=".zip" required class="apx-file">
                            <button type="submit" class="acx-btn acx-btn--primary acx-btn--sm"><x-lucide name="upload" /> Thêm từ ZIP</button>
                        </form>
                        <a href="{{ route('admin.products.exercises.createManual', $product->id) }}" class="acx-btn acx-btn--sm">
                            <x-lucide name="pen-line" /> Thêm thủ công
                        </a>
                    </div>

                    @if (empty($exercises))
                        <x-ws.empty-state title="Chưa có bài tập nào" description="Thêm gói ZIP hoặc soạn thủ công ở ô trên để bắt đầu." />
                    @else
                        <div class="apx-list">
                            @foreach ($exercises as $ex)
                                <div class="apx-item">
                                    <div class="apx-item__main">
                                        <p class="apx-item__title">
                                            {{ $ex['title'] }} <span class="apx-muted" style="font-weight:400">· {{ $ex['typeLabel'] }}</span>
                                            @if ($ex['chapterTitle'])
                                                <span class="apx-tag">{{ $ex['chapterTitle'] }}</span>
                                            @endif
                                        </p>
                                        <p class="apx-item__sub">
                                            {{ $ex['points'] }} điểm · {{ $ex['summary'] }}
                                            @if (!empty($ex['tags']))
                                                · {{ implode(', ', $ex['tags']) }}
                                            @endif
                                            · {{ $ex['createdAt'] }}
                                        </p>
                                    </div>
                                    <div class="apx-item__side">
                                        <a href="{{ route('admin.products.exercises.edit', [$product->id, $ex['id']]) }}" class="acx-link">Sửa</a>
                                        <form action="{{ route('admin.products.exercises.destroy', [$product->id, $ex['id']]) }}" method="POST" onsubmit="return confirm('Xoá bài tập này? Không thể hoàn tác.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="acx-link acx-link--del">Xoá</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>

                @if ($chapterLabel)
                    <section class="acx-card acx-card--white">
                        <div class="apx-cardtop">
                            <h2><x-lucide name="library" /> Học liệu theo {{ mb_strtolower($chapterLabel) }}</h2>
                            <span class="apx-count">{{ count($materialsList) }} học liệu</span>
                        </div>
                        <p class="apx-hint">
                            File PDF/audio/ảnh (kể cả ảnh động) đính kèm — 1 học liệu có thể có cả 3 loại
                            cùng lúc, gắn vào đúng {{ mb_strtolower($chapterLabel) }} để học sinh dễ tìm.
                        </p>

                        <div class="apx-add">
                            <a href="{{ route('admin.content.materials.create', ['product_id' => $product->id]) }}" class="acx-btn acx-btn--primary acx-btn--sm">
                                <x-lucide name="upload" /> Thêm học liệu
                            </a>
                        </div>

                        @if (empty($materialsList))
                            <x-ws.empty-state title="Chưa có học liệu nào" description="Thêm học liệu đầu tiên ở nút trên." />
                        @else
                            <div class="apx-list">
                                @foreach ($materialsList as $m)
                                    <div class="apx-item">
                                        <div class="apx-item__main">
                                            <p class="apx-item__title">{{ $m['title'] }}</p>
                                            <p class="apx-item__sub">
                                                {{ $m['chapterTitle'] ?? 'Chưa gắn '.mb_strtolower($chapterLabel) }}
                                                @if ($m['hasPdf']) · 📄 PDF @endif
                                                @if ($m['hasAudio']) · 🔊 Audio @endif
                                                @if ($m['hasImage']) · 🖼️ Ảnh @endif
                                            </p>
                                        </div>
                                        <div class="apx-item__side">
                                            <span class="acx-badge {{ $badgeOf($m['statusTone'] ?? null) }}">{{ $m['statusLabel'] }}</span>
                                            <a href="{{ route('admin.content.materials.edit', $m['id']) }}" class="acx-link">Sửa</a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </section>
                @endif

                <section class="acx-card acx-card--white">
                    <div class="apx-cardtop">
                        <h2><x-lucide name="ticket" /> Quyền đã cấp cho tài liệu này</h2>
                        <span class="apx-count">{{ $accessRightCount }} quyền</span>
                    </div>

                    @if (empty($accessRightRows))
                        <x-ws.empty-state title="Chưa cấp quyền nào cho tài liệu này" description="Quyền được cấp khi người dùng mua và kích hoạt mã (7.4), hoặc khi Admin cấp trực tiếp." />
                    @else
                        <div class="acx-scroll">
                            <table class="acx-table apx-all">
                                <thead>
                                    <tr>
                                        @foreach (['Người dùng', 'Loại quyền', 'Trạng thái', 'Nguồn cấp', 'Đơn hàng / thanh toán', ''] as $col)
                                            <th scope="col">{{ $col }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($accessRightRows as $row)
                                        <tr>
                                            <td><strong>{{ $row['userName'] }}</strong></td>
                                            <td>{{ $row['scopeLabel'] }}</td>
                                            <td>
                                                <span class="acx-badge {{ $badgeOf($row['tone'] ?? null) }}">{{ $row['statusLabel'] }}</span>
                                                <small>
                                                    {{ $row['startsAt']?->format('d/m/Y') }} — {{ $row['expiresAt']?->format('d/m/Y') ?? 'Không giới hạn' }}
                                                </small>
                                            </td>
                                            <td>{{ $row['sourceLabel'] }}</td>
                                            <td>
                                                @if ($row['orderNo'])
                                                    #{{ $row['orderNo'] }}
                                                    @if ($row['paidAt'])
                                                        <small>Duyệt/thanh toán: {{ $row['paidAt']->format('d/m/Y H:i') }}</small>
                                                    @endif
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td>
                                                <div class="acx-actions"><a href="{{ route('admin.access-rights.show', $row['id']) }}" class="acx-link">Xem</a></div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            </div>

            <aside class="acx-card acx-card--mint">
                @if ($coverUrl)
                    <img class="acx-cover" src="{{ $coverUrl }}" alt="Ảnh bìa tài liệu">
                @endif
                <h3><x-lucide name="info" /> Thông tin tài liệu</h3>
                <dl class="acx-dl">
                    <div><dt>Môn học / Khối / Chuyên đề</dt><dd>{{ collect([$product->subject, $product->grade, $product->topic])->filter()->implode(' · ') ?: '— Không chỉ định —' }}</dd></div>
                    <div><dt>Thời hạn quyền mặc định</dt><dd>{{ $product->duration_months ? $product->duration_months.' tháng' : 'Không giới hạn' }}</dd></div>
                    <div><dt>Bản in</dt><dd>{{ $product->has_print_option ? 'Có' : 'Không' }}</dd></div>
                    <div><dt>Đường dẫn công khai</dt><dd>/san-pham/{{ $product->slug }}</dd></div>
                    <div><dt>Ngày tạo</dt><dd>{{ $product->created_at?->format('d/m/Y H:i') }}</dd></div>
                </dl>
            </aside>
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
