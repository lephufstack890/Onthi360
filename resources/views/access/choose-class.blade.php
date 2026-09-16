{{--
  Route: access.chooseClass | C3 — chọn lớp sau khi mua khoá học.

  Đây là ĐƯỜNG CHÍNH mới: mua khoá → có quyền → tự chọn lớp đang mở.
  SỬA 16/9 — lối phụ "nhập mã lớp" ở cuối trang đã ẩn theo yêu cầu khách (xem
  App\Services\Student\ClassRoomService::JOIN_BY_CODE_ENABLED).

  Dữ liệu do App\Services\Access\CourseEnrollmentService::chooseClassData() trả về:
  $course, $openClasses, $hasRight, $joinedClassId.
--}}
@php
    $ccUser = auth()->user();
    $ccRole = match (true) {
        (bool) $ccUser?->hasAnyRole(\App\Models\Role::ADMIN, \App\Models\Role::SUPER_ADMIN) => 'admin',
        (bool) $ccUser?->hasRole(\App\Models\Role::TEACHER) => 'teacher',
        (bool) $ccUser?->hasRole(\App\Models\Role::PARENT) => 'parent',
        default => 'student',
    };
    $openClasses = $openClasses ?? [];
    $hasRight = $hasRight ?? false;
    $joinedClassId = $joinedClassId ?? null;
@endphp
@extends('layouts.workspace', ['wsRole' => $ccRole])

@section('title', 'Chọn lớp · '.$course->title)
@section('page-title', 'Chọn lớp để vào học')

@section('content')
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <x-ws.page-header title="Chọn lớp để vào học" icon="school"
                      :back="route('access.myAccess')" back-label="Quyền của tôi"
                      :subtitle="$course->title">
        <x-slot:actions>
            <x-ws.btn :href="route('courses.show', $course->id)" variant="onhero-ghost" icon="book-open">Xem khoá học</x-ws.btn>
        </x-slot:actions>
    </x-ws.page-header>

    <div class="mt-4 space-y-4">

        {{-- ══ Chưa có quyền thì không cho chọn lớp ══ --}}
        @unless ($hasRight)
            <div class="flex flex-wrap items-start gap-3 rounded-3xl border border-amber-200 bg-amber-50 p-4">
                <x-ws.icon-tile icon="lock" tone="amber" />
                <div class="min-w-0 flex-1">
                    <p class="text-[13px] font-bold text-amber-800">Bạn chưa có quyền học khoá này</p>
                    <p class="mt-1 text-[12px] leading-relaxed text-amber-800">
                        Đặt mua khoá học để được chọn lớp, hoặc nhập mã kích hoạt nếu đã có mã.
                    </p>
                    <div class="mt-2.5 flex flex-wrap items-center gap-2">
                        @if ($course->product_id)
                            <x-ws.btn :href="route('access.checkout', $course->product_id)" variant="primary" icon="banknote">Đặt mua khoá học</x-ws.btn>
                        @endif
                        <x-ws.btn :href="route('access.activate')" variant="ghost" icon="key-round">Nhập mã kích hoạt</x-ws.btn>
                    </div>
                </div>
            </div>
        @endunless

        {{-- ══ Danh sách lớp đang mở ══ --}}
        <x-ws.card title="Lớp đang mở của khoá này" icon="school">
            @if (count($openClasses) === 0)
                <x-ws.empty-state icon="school" title="Chưa có lớp nào đang mở"
                                  description="Khi trung tâm mở lớp mới, lớp sẽ hiện ngay tại đây." />
            @else
                <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                    @foreach ($openClasses as $class)
                        <div class="flex h-full flex-col rounded-2xl border p-3.5 transition-shadow hover:shadow-md
                                    {{ $class['joined'] ? 'border-emerald-200 bg-emerald-50/50' : 'border-sky-100 bg-white' }}">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-[13.5px] font-bold text-slate-800">{{ $class['name'] }}</p>
                                    <p class="mt-0.5 text-[11px] font-bold uppercase tracking-wide text-slate-400">Mã lớp: {{ $class['code'] }}</p>
                                </div>
                                @if ($class['joined'])
                                    <x-ws.badge tone="success">Đang học</x-ws.badge>
                                @endif
                            </div>

                            <div class="mt-2.5 space-y-1.5 text-[11.5px] text-slate-600">
                                <p class="flex items-start gap-1.5">
                                    <x-lucide name="user-round" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-slate-400" />
                                    <span>{{ count($class['teachers']) > 0 ? implode(', ', $class['teachers']) : 'Chưa phân công giáo viên' }}</span>
                                </p>
                                <p class="flex items-start gap-1.5">
                                    <x-lucide name="calendar-days" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-slate-400" />
                                    <span>{{ $class['scheduleNote'] ?: 'Lịch học sẽ thông báo sau' }}</span>
                                </p>
                                <p class="flex items-start gap-1.5">
                                    <x-lucide name="users" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-slate-400" />
                                    <span>{{ $class['studentCount'] }} học viên đang học</span>
                                </p>
                            </div>

                            <div class="mt-3 pt-0.5">
                                @if ($class['joined'])
                                    <x-ws.btn :href="route('student.classes.show', $class['id'])" variant="ghost" icon="log-in" class="w-full justify-center">Vào lớp</x-ws.btn>
                                @elseif (! $hasRight)
                                    <button type="button" disabled
                                            class="inline-flex min-h-10 w-full cursor-not-allowed items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-4 text-xs font-bold text-slate-400">
                                        Cần có quyền mới chọn được
                                    </button>
                                @else
                                    <form method="POST" action="{{ route('access.chooseClass.store', $course->id) }}">
                                        @csrf
                                        <input type="hidden" name="class_room_id" value="{{ $class['id'] }}">
                                        <x-ws.btn type="submit" variant="primary" icon="log-in" class="w-full justify-center">Chọn lớp này</x-ws.btn>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($hasRight && $joinedClassId === null)
                    <p class="mt-3 text-[11px] leading-relaxed text-slate-400">
                        Chọn một lớp là vào học được ngay. Muốn đổi lớp thì liên hệ giáo viên phụ trách.
                    </p>
                @endif
            @endif
        </x-ws.card>

        {{-- ẨN 16/9 (khách yêu cầu: "bỏ chỗ nhập mã lớp tham gia lớp đi") — lối phụ nhập mã lớp.
             ẨN CHỨ KHÔNG XOÁ: bật App\Services\Student\ClassRoomService::JOIN_BY_CODE_ENABLED
             rồi bỏ dấu chú thích quanh khối này là hiện lại.
        <x-ws.card title="Đã có mã lớp?" icon="key-round">
            <p class="text-[12px] leading-relaxed text-slate-500">
                Nếu giáo viên gửi riêng cho bạn một mã lớp, bạn vẫn vào lớp bằng mã như trước — không cần chọn ở danh sách trên.
            </p>
            @if ($ccRole === 'student')
                <form method="POST" action="{{ route('student.classes.join') }}" class="mt-2.5 flex flex-col gap-2 sm:flex-row sm:items-end">
                    @csrf
                    <x-ws.field label="Mã lớp" name="code" class="flex-1">
                        <input id="code" name="code" type="text" class="admin-input" maxlength="40" placeholder="VD: TIN9-A1" required>
                    </x-ws.field>
                    <x-ws.btn type="submit" variant="ghost" icon="log-in">Vào bằng mã lớp</x-ws.btn>
                </form>
            @endif
        </x-ws.card>
        --}}
    </div>
@endsection
