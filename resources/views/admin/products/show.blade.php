@extends('layouts.admin')

@section('title', $product->title)
@section('page-title', 'Chi tiết tài liệu')

@section('content')
    @php
        $typeLabels = ['book' => 'Sách', 'topic' => 'Chuyên đề', 'exam' => 'Bộ đề', 'course' => 'Khóa học'];
        // SỬA 18/10 (khách: "bộ đề lấy đề luyện tập có sẵn, trong đề có câu hỏi rồi — ẩn khối Đề và Bài tập đính kèm") —
        // tài liệu loại Bộ đề chỉ còn khối "Đề trong bộ đề" (admin/products/partials/exam-bundle); khối Đề (chương PDF),
        // Bài tập đính kèm, Học liệu ẩn đi. Dữ liệu cũ (nếu có) không bị xoá.
        $isExamBundle = $product->type === \App\Enums\ProductType::Exam && \App\Services\ExamBundle\ExamBundleService::ready();
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
        $coverUrl = \App\Support\ProductCover::url($product->cover_image_path);
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
                            {{-- SỬA 14/10 — gộp còn MỘT giá; có bản in thì ghi thêm giá bản in. --}}
                            · Giá: {{ number_format($product->price) }}đ
                            @if ($product->has_print_option)
                                · Bản in: +{{ number_format($product->printPrice()) }}đ
                            @endif
                            · Hiển thị: {{ $product->visibility->value === 'public' ? 'Công khai' : 'Riêng tư' }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="acx-head__actions">
                @if ($readHref ?? null)
                    {{-- SỬA 14/10 — admin đọc được mọi tài liệu không cần mua (khách yêu cầu). --}}
                    <a href="{{ $readHref }}" class="acx-btn">
                        <x-lucide name="book-open" /> Đọc tài liệu
                    </a>
                @endif
                <a href="{{ route('admin.products.edit', $product->id) }}" class="acx-btn">
                    <x-lucide name="pen-line" /> Sửa
                </a>
            </div>
        </div>

        <div class="acx-stats" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr))">
            @if ($isExamBundle)
                <div class="acx-stat"><p>Đề trong bộ đề</p><strong>{{ app(\App\Services\ExamBundle\ExamBundleService::class)->items($product)->count() }}</strong></div>
            @else
            @if ($chapterLabel)
                <div class="acx-stat"><p>{{ $chapterLabel }}</p><strong>{{ count($chapters) }}</strong></div>
            @endif
            <div class="acx-stat"><p>Bài tập đính kèm</p><strong>{{ count($exercises) }}</strong></div>
            @if ($chapterLabel)
                <div class="acx-stat"><p>Học liệu</p><strong>{{ count($materialsList) }}</strong></div>
            @endif
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

                {{-- SỬA 18/10 — MODULE BỘ ĐỀ: tài liệu loại Bộ đề có khối "Đề trong bộ đề" (lấy đề luyện tập có sẵn, không nhập lại). --}}
                @if ($product->type === \App\Enums\ProductType::Exam)
                    @include('admin.products.partials.exam-bundle', ['product' => $product])
                @endif

                {{-- SỬA 14/10 (khách: "bỏ khối Tài nguyên đính kèm đi, không cần") — đã bỏ cả khối. Tệp cũ (ZIP bài tập/học liệu
                     kiểu cũ) vẫn nằm nguyên trong CSDL và người học vẫn mở được như trước, chỉ không hiện ở đây nữa. --}}

                @if ($chapterLabel && ! $isExamBundle)
                    <section class="acx-card acx-card--white" id="chapters" x-data="{ editing: null }" style="scroll-margin-top:80px">
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
                                            {{-- SỬA 15/10 (khách: "nút mũi tên lên xuống để đảo thứ tự chương", "để gần nút Sửa/Xoá cho đẹp") —
                                                 đổi chỗ với mục liền trên/dưới; đây là thứ tự đọc của học sinh. --}}
                                            <div class="apx-move" role="group" aria-label="Đổi thứ tự">
                                                <form action="{{ route('admin.products.chapters.move', [$product->id, $c['id']]) }}" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="direction" value="up">
                                                    <button type="submit" class="apx-move__btn" title="Đưa lên trên" aria-label="Đưa {{ $c['title'] }} lên trên" {{ $loop->first ? 'disabled' : '' }}>
                                                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>
                                                    </button>
                                                </form>
                                                <form action="{{ route('admin.products.chapters.move', [$product->id, $c['id']]) }}" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="direction" value="down">
                                                    <button type="submit" class="apx-move__btn" title="Đưa xuống dưới" aria-label="Đưa {{ $c['title'] }} xuống dưới" {{ $loop->last ? 'disabled' : '' }}>
                                                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                                    </button>
                                                </form>
                                            </div>
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

                @unless ($isExamBundle)
                <section class="acx-card acx-card--white">
                    <div class="apx-cardtop">
                        <h2><x-lucide name="layers" /> Bài tập đính kèm</h2>
                        <span class="apx-count">{{ count($exercises) }} bài</span>
                    </div>
                    <p class="apx-hint">
                        {{ $chapterLabel ? 'Chọn '.mb_strtolower($chapterLabel).' rồi tải' : 'Tải' }} gói ZIP bài tập (mẫu OT360-QPACK) — hệ thống <b>tự nhận dạng</b>
                        Trắc nghiệm, Đúng / Sai, Điền từ hay Lập trình theo file trong gói. Đọc xong vẫn sửa lại được từng bài.
                    </p>

                    @include('admin.products.partials.exercise-adder')

                    @if (empty($exercises))
                        <x-ws.empty-state title="Chưa có bài tập nào" description="Tải gói ZIP ở khung trên để thêm bài đầu tiên." />
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
                @endunless

                @if ($chapterLabel && ! $isExamBundle)
                    <section class="acx-card acx-card--white">
                        <div class="apx-cardtop">
                            <h2><x-lucide name="library" /> Học liệu theo {{ mb_strtolower($chapterLabel) }}</h2>
                            <span class="apx-count">{{ count($materialsList) }} học liệu</span>
                        </div>
                        <p class="apx-hint">
                            File PDF/audio/ảnh (kể cả ảnh động) đính kèm — 1 học liệu có thể có cả 3 loại
                            cùng lúc, gắn vào đúng {{ mb_strtolower($chapterLabel) }} để học sinh dễ tìm.
                        </p>

                        @include('admin.products.partials.material-adder')

                        @if (empty($materialsList))
                            <x-ws.empty-state title="Chưa có học liệu nào" description="Chọn chương, đặt tên và tệp ở khung trên để thêm học liệu đầu tiên." />
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
                    {{-- SỬA 14/10 — bỏ "Chuyên đề"; bản in kèm giá bản in và giá chính. --}}
                    <div><dt>Môn học / Khối</dt><dd>{{ collect([$product->subject, $product->grade])->filter()->implode(' · ') ?: '— Không chỉ định —' }}</dd></div>
                    <div><dt>Thời hạn quyền mặc định</dt><dd>{{ $product->duration_months ? $product->duration_months.' tháng' : 'Không giới hạn' }}</dd></div>
                    <div><dt>Bản in</dt><dd>{{ $product->has_print_option ? 'Có · +'.number_format($product->printPrice()).'đ (giá chính '.number_format($product->price + $product->printPrice()).'đ)' : 'Không' }}</dd></div>
                    <div><dt>Độ khó</dt><dd>{{ $product->difficulty_level ? $product->difficulty_level.' sao · '.(['Cơ bản', 'Dễ', 'Trung bình', 'Khó', 'Nâng cao'][$product->difficulty_level - 1] ?? '') : '— Chưa xếp —' }}</dd></div>
                    <div><dt>Tác giả</dt><dd>{{ $product->author_name ?: '— Chưa nhập —' }}</dd></div>
                    <div><dt>Đánh giá nhập tay</dt><dd>{{ $product->rating_score !== null && $product->rating_count > 0 ? number_format((float) $product->rating_score, 1, ',', '').'/5 · '.$product->rating_count.' lượt' : '— Chưa nhập —' }}</dd></div>
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
