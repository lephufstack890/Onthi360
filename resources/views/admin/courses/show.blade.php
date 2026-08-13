{{--
  Route: admin.courses.show | "Khóa & Lớp" trong sidebar (4.2)
  Spec: 8.1 (Khóa học khác Lớp học — lớp thuộc khóa này được tạo bởi giáo
  viên đã duyệt, xem App\Http\Controllers\Teacher\ClassRoomController).
  Dữ liệu thật do App\Http\Controllers\Admin\CourseController truyền vào
  qua App\Services\Admin\CourseService::showData().
  Lưu ý: $course->description là HTML do CKEditor sinh ra (xem
  admin.courses.create) — hiển thị bằng {!! !!}, không escape.
--}}
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
    @endphp

    <a href="{{ route('admin.courses.index') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Khóa & Lớp</a>

    @php
        $courseStatusMessage = match (session('status')) {
            'course-updated' => 'Đã lưu thay đổi khóa học.',
            'class-created' => 'Đã tạo lớp mới.',
            'class-updated' => 'Đã lưu thay đổi lớp học.',
            'class-deleted' => 'Đã xóa lớp học (xóa mềm, đã ghi lý do).',
            default => null,
        };
    @endphp
    @if ($courseStatusMessage)
        @include('partials.toast-flash', ['type' => 'success', 'message' => $courseStatusMessage])
    @endif

    <div class="rounded-3xl bg-gradient-to-br from-sky-100 via-white to-rose-50 p-6 lg:p-8 mb-6 flex items-start justify-between gap-4 flex-wrap">
        <div class="flex items-start gap-4">
            <x-icon-tile emoji="🏫" tone="rose" />
            <div>
                <div class="flex items-center gap-2 flex-wrap mb-1">
                    <h1 class="text-xl lg:text-2xl font-semibold text-slate-800">{{ $course->title }}</h1>
                    <x-status-badge :tone="$meta['tone']">{{ $meta['label'] }}</x-status-badge>
                </div>
                <p class="text-sm text-slate-500">
                    @if ($course->subject) {{ $course->subject }} @endif
                    @if ($course->grade) · {{ $course->grade }} @endif
                    · Tạo bởi {{ $course->creator->name ?? 'Không rõ' }} · {{ $course->created_at?->format('d/m/Y') }}
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('admin.courses.edit', $course->id) }}"
               class="px-4 py-2 rounded-lg border border-slate-200 bg-white text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">
                ✏️ Sửa
            </a>
            <a href="{{ route('courses.show', $course->slug) }}" target="_blank" rel="noopener"
               class="px-4 py-2 rounded-lg border border-slate-200 bg-white text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">
                🔗 Xem trang công khai
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        <x-stat-tile label="Lớp đang triển khai" :value="$classRooms->count()" tone="info" />
        <x-stat-tile label="Tổng học sinh" :value="$totalStudents" tone="success" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-5">
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h2 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span>📝</span> Mô tả khóa học</h2>
                @if ($course->description)
                    <div class="rich-content text-sm text-slate-600 leading-relaxed">{!! $course->description !!}</div>
                @else
                    <p class="text-sm text-slate-400">Chưa có mô tả.</p>
                @endif
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-medium text-slate-700 flex items-center gap-2"><span>🏫</span> Lớp thuộc khóa này</h2>
                    <a href="{{ route('admin.courses.classes.create', $course->id) }}" class="text-sm font-medium text-rose-600 hover:underline">+ Tạo lớp</a>
                </div>
                <div class="space-y-2">
                    @forelse ($classRooms as $c)
                        <a href="{{ route('admin.classes.edit', $c['id']) }}" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-slate-50 hover:bg-slate-100 transition">
                            <x-icon-tile emoji="🏫" tone="sky" />
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-slate-700">{{ $c['name'] }} <span class="text-slate-400 font-normal">({{ $c['code'] }})</span></p>
                                <p class="text-xs text-slate-400">{{ $c['teacher'] ? 'GV '.$c['teacher'] : 'Chưa phân công giáo viên' }} · {{ $c['students'] }} học sinh</p>
                            </div>
                            <x-status-badge :tone="$c['status'] === 'active' ? 'success' : 'neutral'">{{ $c['status'] === 'active' ? 'Đang học' : 'Lưu trữ' }}</x-status-badge>
                        </a>
                    @empty
                        <x-empty-state title="Chưa có lớp nào thuộc khóa này" description="Bấm '+ Tạo lớp' để mở lớp đầu tiên, hoặc giáo viên đã được duyệt có thể tự tạo lớp và chọn khóa học này (3.3, 8.1)." />
                    @endforelse
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-5 space-y-4">
            <h3 class="font-medium text-slate-700 flex items-center gap-2"><span>ℹ️</span> Thông tin khóa học</h3>
            <div class="text-sm space-y-3">
                <div>
                    <p class="text-slate-400 text-xs">Môn học</p>
                    <p class="text-slate-700">{{ $course->subject ?: '— Không chỉ định —' }}</p>
                </div>
                <div>
                    <p class="text-slate-400 text-xs">Khối lớp</p>
                    <p class="text-slate-700">{{ $course->grade ?: '— Không chỉ định —' }}</p>
                </div>
                <div>
                    <p class="text-slate-400 text-xs">Đường dẫn công khai</p>
                    <p class="text-slate-700 break-all">/khoa-hoc/{{ $course->slug }}</p>
                </div>
                <div>
                    <p class="text-slate-400 text-xs">Người tạo</p>
                    <p class="text-slate-700">{{ $course->creator->name ?? 'Không rõ' }}</p>
                </div>
                <div>
                    <p class="text-slate-400 text-xs">Ngày tạo</p>
                    <p class="text-slate-700">{{ $course->created_at?->format('d/m/Y H:i') }}</p>
                </div>
            </div>
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
