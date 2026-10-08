@extends('layouts.admin')

@section('title', $course->title)
@section('page-title', 'Chi tiết khóa học')

@section('content')
    @php
        $statusMeta = [
            'draft' => ['label' => 'Bản nháp', 'tone' => 'neutral'],
            'pending_review' => ['label' => 'Chờ duyệt', 'tone' => 'warning'],
            'published' => ['label' => 'Đang mở', 'tone' => 'success'],
            'archived' => ['label' => 'Lưu trữ', 'tone' => 'neutral'],
        ];
        $statusValue = $course->status->value ?? (string) $course->status;
        $meta = $statusMeta[$statusValue] ?? ['label' => $statusValue, 'tone' => 'neutral'];
        $badgeClass = match ($meta['tone']) {
            'success' => 'acx-badge--ok',
            'warning' => 'acx-badge--warn',
            default => '',
        };
        $coverUrl = $course->coverUrl();
    @endphp

    {{--
      SỬA 8/10 (khách: "cập nhật UI màn khóa và lớp theo source mới, logic giữ nguyên") — trang chi tiết
      khóa theo phong cách AdminCourses.jsx (tiêu đề + huy hiệu, chỉ số tự tính, thẻ nền xanh nhạt, danh
      sách lớp dạng nút có mũi tên). Mọi biến, route, đối chiếu số buổi (A10) GIỮ NGUYÊN như bản cũ.
    --}}
    @include('partials.admin-courses-ui')

    @php
        $courseStatusMessage = match (session('status')) {
            'course-updated' => 'Đã lưu thay đổi khóa học.',
            'class-created' => 'Đã tạo lớp mới.',
            'class-updated' => 'Đã lưu thay đổi lớp học.',
            'class-deleted' => session('statusMessage', 'Đã xóa lớp học cùng toàn bộ dữ liệu liên quan.'),
            default => null,
        };
    @endphp
    @if ($courseStatusMessage)
        @include('partials.toast-flash', ['type' => 'success', 'message' => $courseStatusMessage])
    @endif

    <div class="acx-wrap">
        <a href="{{ route('admin.courses.index') }}" class="acx-back">‹ Quay lại Khóa & Lớp</a>

        <div class="acx-head">
            <div class="acx-head__id">
                @if ($coverUrl)
                    <img class="acx-head__thumb" src="{{ $coverUrl }}" alt="">
                @else
                    <span class="acx-avatar"><x-lucide name="book-open" /></span>
                @endif
                <div style="min-width:0">
                    <h1>{{ $course->title }}</h1>
                    <div class="acx-head__meta">
                        <span class="acx-badge {{ $badgeClass }}">{{ $meta['label'] }}</span>
                        <span>
                            @if ($course->subject) {{ $course->subject }} @endif
                            @if ($course->grade) · {{ $course->grade }} @endif
                            · Tạo bởi {{ $course->creator->name ?? 'Không rõ' }} · {{ $course->created_at?->format('d/m/Y') }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="acx-head__actions">
                <a href="{{ route('admin.courses.edit', $course->id) }}" class="acx-btn">
                    <x-lucide name="pen-line" /> Sửa
                </a>
                <a href="{{ route('courses.show', $course->slug) }}" target="_blank" rel="noopener" class="acx-btn">
                    <x-lucide name="external-link" /> Xem trang công khai
                </a>
            </div>
        </div>

        <div class="acx-stats">
            <div class="acx-stat"><p>Lớp đang triển khai</p><strong>{{ $classRooms->count() }}</strong></div>
            <div class="acx-stat"><p>Tổng học sinh</p><strong>{{ $totalStudents }}</strong></div>
        </div>

        <div class="acx-grid acx-grid--main">
            <div class="acx-stack">
                <section class="acx-card acx-card--white">
                    <h2><x-lucide name="pen-line" /> Mô tả khóa học</h2>
                    @if ($course->description)
                        <div class="rich-content acx-rich">{!! $course->description !!}</div>
                    @else
                        <p class="acx-note">Chưa có mô tả.</p>
                    @endif
                </section>

                {{-- SỬA 1/10 — trang chi tiết phải xem được CẢ hai trường, nếu không sửa xong không
                     có chỗ nào kiểm lại bài giới thiệu vừa soạn. --}}
                <section class="acx-card acx-card--white">
                    <h2><x-lucide name="pen-line" /> Giới thiệu khóa học</h2>
                    @if (\App\Models\Course::hasContent($course->intro))
                        <div class="rich-content acx-rich">{!! $course->intro !!}</div>
                    @else
                        <p class="acx-note">Chưa có bài giới thiệu — trang công khai đang hiện lại phần Mô tả ở trên.</p>
                    @endif
                </section>

                <section class="acx-card acx-card--white">
                    <div class="acx-cardtop">
                        <h2><x-lucide name="graduation-cap" /> Lớp thuộc khóa này</h2>
                        <a href="{{ route('admin.courses.classes.create', $course->id) }}" class="acx-btn acx-btn--sm"><x-lucide name="plus" /> Tạo lớp</a>
                    </div>
                    <div class="acx-child">
                        @forelse ($classRooms as $c)
                            <a href="{{ route('admin.classes.edit', $c['id']) }}">
                                <span>
                                    <strong>{{ $c['name'] }} <span style="font-weight:400;color:#8aa0b6">({{ $c['code'] }})</span></strong>
                                    <small>{{ $c['teacher'] ? 'GV '.$c['teacher'] : 'Chưa phân công giáo viên' }} · {{ $c['students'] }} học sinh</small>

                                    {{-- SỬA 15/9 (A10) — đối chiếu số buổi ĐÃ XẾP LỊCH của lớp với số buổi
                                         THEO CHƯƠNG TRÌNH của khoá. Lệch thì báo ngay tại đây, thay vì đợi
                                         học sinh kêu thiếu buổi. --}}
                                    @php
                                        $designed = (int) ($designedSessions ?? 0);
                                        // SỬA 30/9 — khoá ghi số buổi theo khoảng thì in "33-50"; đối chiếu vẫn theo cận dưới.
                                        $designedLabel = ($designedSessionsLabel ?? '') !== '' ? $designedSessionsLabel : (string) $designed;
                                        $scheduled = (int) ($c['scheduledSessions'] ?? 0);
                                    @endphp
                                    @if ($designed > 0)
                                        <small class="{{ $scheduled >= $designed ? 'acx-ok' : 'acx-warn' }}">
                                            Đã xếp {{ $scheduled }}/{{ $designedLabel }} buổi
                                            @if ($scheduled < $designed)
                                                · còn thiếu {{ $designed - $scheduled }}
                                            @endif
                                        </small>
                                    @elseif ($scheduled > 0)
                                        <small>Đã xếp {{ $scheduled }} buổi</small>
                                    @endif
                                </span>
                                <span class="acx-badge {{ $c['status'] === 'active' ? 'acx-badge--ok' : '' }}">{{ $c['status'] === 'active' ? 'Đang học' : 'Lưu trữ' }}</span>
                                <x-lucide name="chevron-right" class="acx-go" />
                            </a>
                        @empty
                            <x-ws.empty-state title="Chưa có lớp nào thuộc khóa này" description="Bấm '+ Tạo lớp' để mở lớp đầu tiên, hoặc giáo viên đã được duyệt có thể tự tạo lớp và chọn khóa học này (3.3, 8.1)." />
                        @endforelse
                    </div>
                </section>
            </div>

            <aside class="acx-card acx-card--mint">
                @if ($coverUrl)
                    <img class="acx-cover" src="{{ $coverUrl }}" alt="Ảnh đại diện khóa học">
                @endif
                <h3><x-lucide name="info" /> Thông tin khóa học</h3>
                <dl class="acx-dl">
                    <div><dt>Môn học</dt><dd>{{ $course->subject ?: '— Không chỉ định —' }}</dd></div>
                    <div><dt>Khối lớp</dt><dd>{{ $course->grade ?: '— Không chỉ định —' }}</dd></div>
                    <div><dt>Đường dẫn công khai</dt><dd>/khoa-hoc/{{ $course->slug }}</dd></div>
                    <div><dt>Người tạo</dt><dd>{{ $course->creator->name ?? 'Không rõ' }}</dd></div>
                    <div><dt>Ngày tạo</dt><dd>{{ $course->created_at?->format('d/m/Y H:i') }}</dd></div>
                </dl>
            </aside>
        </div>
    </div>
@endsection

@push('scripts')
    <style>
        .rich-content ul { list-style: disc; padding-left: 1.25rem; margin-bottom: 0.5rem; }
        .rich-content ol { list-style: decimal; padding-left: 1.25rem; margin-bottom: 0.5rem; }
        .rich-content p { margin-bottom: 0.5rem; }
        .rich-content a { color: #e11d48; text-decoration: underline; }
    </style>
@endpush
