@extends('layouts.admin')

@section('title', 'Xếp bậc · '.$path->title)
@section('page-title', 'Xếp bậc lộ trình')

@section('content')
{{-- ═══════ A9 · XẾP BẬC ═══════
     Mỗi bậc là một khoá học. Thứ tự bậc là thứ duy nhất lưu trên bảng nối; mã bậc, câu kết
     quả và số buổi nằm trên chính khoá học (sửa ở màn Khoá học).

     Màu các bậc sinh từ App\Support\LearningPathPalette theo VỊ TRÍ, không phải thuộc tính
     của khoá — xem ghi chú trong lớp đó. --}}
@php
    $steps = $steps ?? [];
    $issues = $issues ?? [];
    $availableCourses = $availableCourses ?? collect();

    $blockers = array_values(array_filter($issues, fn ($i) => $i['level'] === \App\Support\LearningPathReadiness::BLOCK));
    $warnings = array_values(array_filter($issues, fn ($i) => $i['level'] === \App\Support\LearningPathReadiness::WARN));
    $canPublish = count($blockers) === 0;
@endphp

@if (session('status') === 'path-created')
    @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tạo lộ trình. Giờ xếp các bậc cho nó.'])
@elseif (session('status') === 'step-attached')
    @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã thêm bậc vào lộ trình.'])
@elseif (session('status') === 'step-detached')
    @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã gỡ bậc khỏi lộ trình. Khoá học vẫn còn nguyên.'])
@elseif (session('status') === 'steps-reordered')
    @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu thứ tự bậc.'])
@elseif (session('status') === 'path-status-changed')
    @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã đổi trạng thái hiển thị.'])
@endif

@if ($errors->any())
    @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
@endif

<x-ws.page-header :title="$path->title" icon="route"
                  :back="route('admin.learning-paths.index')" back-label="Danh sách lộ trình"
                  :subtitle="$path->gradeLabel().' · '.$path->languageLabel().' · '.$path->goal_label">
    <x-slot:actions>
        <x-ws.btn :href="route('admin.learning-paths.edit', $path->id)" variant="onhero-ghost" icon="pencil">Sửa thông tin</x-ws.btn>
    </x-slot:actions>
</x-ws.page-header>

<div x-data="learningPathSteps({{ Js::from([
        'steps' => $steps,
        'palette' => \App\Support\LearningPathPalette::ramp(),
        'sessionsPerWeek' => $path->sessions_per_week,
        'hoursPerSession' => $path->hours_per_session,
    ]) }})" class="space-y-4">

    {{-- ══════ Số liệu tổng, tự tính lại ngay khi kéo thả ══════ --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-ws.stat label="Số bậc" :value="count($steps)" tone="blue" icon="list" hint="Mỗi bậc là một khoá học" />
        <div class="h-full rounded-2xl border border-sky-100 bg-white p-3.5 shadow-[0_2px_8px_rgba(0,90,180,.04)]">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Tổng buổi</p>
                    <p class="mt-1 text-xl font-black text-slate-800" x-text="totalSessions"></p>
                </div>
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border border-emerald-100 bg-emerald-50 text-emerald-600">
                    <x-lucide name="calendar-days" class="h-4 w-4" />
                </span>
            </div>
            <p class="mt-2 text-[11px] font-medium text-slate-500">Cộng từ số buổi của các bậc</p>
        </div>
        <div class="h-full rounded-2xl border border-sky-100 bg-white p-3.5 shadow-[0_2px_8px_rgba(0,90,180,.04)]">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Số tuần ước tính</p>
                    <p class="mt-1 text-xl font-black text-slate-800" x-text="totalWeeks"></p>
                </div>
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border border-violet-100 bg-violet-50 text-violet-600">
                    <x-lucide name="clock-3" class="h-4 w-4" />
                </span>
            </div>
            <p class="mt-2 text-[11px] font-medium text-slate-500">{{ $path->paceLabel() }}</p>
        </div>
        <div class="h-full rounded-2xl border border-sky-100 bg-white p-3.5 shadow-[0_2px_8px_rgba(0,90,180,.04)]">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Tổng giờ học</p>
                    <p class="mt-1 text-xl font-black text-slate-800" x-text="totalHours"></p>
                </div>
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border border-amber-100 bg-amber-50 text-amber-600">
                    <x-lucide name="bar-chart-3" class="h-4 w-4" />
                </span>
            </div>
            <p class="mt-2 text-[11px] font-medium text-slate-500">Con số phụ huynh hay hỏi nhất</p>
        </div>
    </div>

    {{-- ══════ A12 · Điều kiện đăng ══════ --}}
    @if (count($blockers) > 0)
        <div class="flex items-start gap-3 rounded-3xl border border-rose-200 bg-rose-50 p-4">
            <x-ws.icon-tile icon="alert-triangle" tone="rose" />
            <div class="min-w-0 flex-1">
                <p class="text-[13px] font-bold text-rose-700">Chưa đăng được lộ trình này</p>
                <ul class="mt-1.5 space-y-1">
                    @foreach ($blockers as $issue)
                        <li class="text-[12px] leading-relaxed text-rose-700">• {{ $issue['message'] }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if (count($warnings) > 0)
        <div class="flex items-start gap-3 rounded-3xl border border-amber-200 bg-amber-50 p-4">
            <x-ws.icon-tile icon="alert-triangle" tone="amber" />
            <div class="min-w-0 flex-1">
                <p class="text-[13px] font-bold text-amber-800">Vẫn đăng được, nhưng nên xem lại</p>
                <ul class="mt-1.5 space-y-1">
                    @foreach ($warnings as $issue)
                        <li class="text-[12px] leading-relaxed text-amber-800">• {{ $issue['message'] }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    {{-- ══════ Danh sách bậc ══════ --}}
    <x-ws.card title="Các bậc của lộ trình" icon="list">
        <x-slot:default>
            @if (count($steps) === 0)
                <div class="rounded-2xl border border-dashed border-sky-200 bg-[#F8FBFE] p-8 text-center">
                    <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-white text-sky-600 shadow-2xs">
                        <x-lucide name="list" class="h-6 w-6" />
                    </span>
                    <p class="mt-3 text-sm font-bold text-slate-800">Lộ trình chưa có bậc nào</p>
                    <p class="mt-1 text-xs text-slate-500">Chọn một khoá học ở khung bên dưới để thêm bậc đầu tiên.</p>
                </div>
            @else
                <p class="mb-3 flex items-center gap-1.5 text-[11px] text-slate-400">
                    <x-lucide name="grip-vertical" class="h-3.5 w-3.5" />
                    Kéo thả để đổi thứ tự, hoặc dùng nút lên/xuống. Đổi xong nhớ bấm “Lưu thứ tự”.
                </p>

                <div class="space-y-2">
                    <template x-for="(step, index) in steps" :key="step.id">
                        <div draggable="true"
                             @dragstart="onDragStart(index)"
                             @dragover.prevent
                             @drop.prevent="onDrop(index)"
                             class="flex items-stretch gap-3 rounded-2xl border bg-white p-3 transition-shadow hover:shadow-md"
                             :style="'border-color:' + colorAt(index).ring">

                            {{-- Số bậc + màu theo vị trí --}}
                            <div class="flex shrink-0 flex-col items-center justify-center gap-1 rounded-xl px-2.5 py-2 text-white"
                                 :style="'background:' + colorAt(index).solid">
                                <span class="text-[15px] font-black leading-none" x-text="index + 1"></span>
                                <x-lucide name="grip-vertical" class="h-3.5 w-3.5 opacity-70" />
                            </div>

                            {{-- Thông tin bậc --}}
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="rounded-lg px-2 py-0.5 text-[10px] font-black uppercase tracking-wide"
                                          :style="'background:' + colorAt(index).soft + ';color:' + colorAt(index).ink"
                                          x-text="step.levelCode || 'CHƯA ĐẶT MÃ BẬC'"></span>
                                    <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400" x-text="step.levelSubtitle"></span>
                                </div>
                                <p class="mt-1 truncate text-[13px] font-bold text-slate-800" x-text="step.title"></p>
                                <p class="mt-0.5 truncate text-[11px] italic text-slate-500" x-text="step.outcome || 'Chưa có câu kết quả'"></p>

                                <div class="mt-1.5 flex flex-wrap items-center gap-2 text-[11px]">
                                    <span class="inline-flex items-center gap-1 font-bold"
                                          :class="step.sessionCount > 0 ? 'text-slate-600' : 'text-rose-600'">
                                        <x-lucide name="calendar-days" class="h-3 w-3" />
                                        <span x-text="step.sessionCount > 0 ? step.sessionCount + ' buổi' : 'Chưa có số buổi'"></span>
                                    </span>
                                    <span class="inline-flex items-center gap-1"
                                          :class="step.openClassCount > 0 ? 'text-slate-400' : 'text-amber-600 font-bold'">
                                        <x-lucide name="school" class="h-3 w-3" />
                                        <span x-text="step.openClassCount > 0 ? step.openClassCount + ' lớp đang mở' : 'Chưa có lớp nào mở'"></span>
                                    </span>
                                </div>
                            </div>

                            {{-- Thao tác --}}
                            <div class="flex shrink-0 flex-col items-end justify-between gap-1.5">
                                <div class="flex items-center gap-1">
                                    <button type="button" @click="moveUp(index)" :disabled="index === 0" aria-label="Đưa bậc lên trên"
                                            class="grid h-7 w-7 place-items-center rounded-lg border border-sky-100 bg-white text-slate-500 transition-colors hover:bg-sky-50 hover:text-blue-700 disabled:cursor-not-allowed disabled:opacity-30">
                                        <x-lucide name="arrow-up" class="h-3.5 w-3.5" />
                                    </button>
                                    <button type="button" @click="moveDown(index)" :disabled="index === steps.length - 1" aria-label="Đưa bậc xuống dưới"
                                            class="grid h-7 w-7 place-items-center rounded-lg border border-sky-100 bg-white text-slate-500 transition-colors hover:bg-sky-50 hover:text-blue-700 disabled:cursor-not-allowed disabled:opacity-30">
                                        <x-lucide name="arrow-down" class="h-3.5 w-3.5" />
                                    </button>
                                </div>

                                <div class="flex items-center gap-1.5">
                                    <a :href="step.editHref"
                                       class="inline-flex min-h-8 items-center gap-1 rounded-lg border border-blue-100 bg-blue-50 px-2 text-[11px] font-bold text-blue-700 transition-colors hover:bg-blue-100">
                                        Sửa khoá
                                    </a>
                                    <form method="POST" action="{{ route('admin.learning-paths.steps.detach', $path->id) }}">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="course_id" :value="step.id">
                                        <button type="submit" aria-label="Gỡ bậc khỏi lộ trình"
                                                class="grid h-8 w-8 place-items-center rounded-lg border border-rose-200 bg-rose-50 text-rose-600 transition-colors hover:bg-rose-100">
                                            <x-lucide name="x" class="h-3.5 w-3.5" />
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Lưu thứ tự — gửi bằng form thường, không gọi API ngầm. --}}
                <form method="POST" action="{{ route('admin.learning-paths.steps.reorder', $path->id) }}"
                      class="mt-3 flex flex-wrap items-center gap-2">
                    @csrf
                    <template x-for="step in steps" :key="'order-' + step.id">
                        <input type="hidden" name="order[]" :value="step.id">
                    </template>
                    <x-ws.btn type="submit" variant="primary" icon="save">Lưu thứ tự</x-ws.btn>
                    <span x-show="dirty" x-cloak class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-600">
                        <x-lucide name="alert-triangle" class="h-3.5 w-3.5" />Thứ tự đã đổi nhưng chưa lưu
                    </span>
                </form>
            @endif
        </x-slot:default>
    </x-ws.card>

    {{-- ══════ Thêm bậc ══════ --}}
    <x-ws.card title="Thêm bậc" icon="plus">
        @if ($availableCourses->isEmpty())
            <p class="text-[12px] leading-relaxed text-slate-500">
                Mọi khoá học hiện có đều đã nằm trong lộ trình này.
                <a href="{{ route('admin.courses.create') }}" class="font-bold text-blue-600 hover:underline">Tạo khoá học mới</a>
                rồi quay lại thêm bậc.
            </p>
        @else
            <form method="POST" action="{{ route('admin.learning-paths.steps.attach', $path->id) }}"
                  class="flex flex-col gap-2.5 sm:flex-row sm:items-end">
                @csrf
                <x-ws.field label="Chọn khoá học làm bậc tiếp theo" name="course_id" class="flex-1">
                    <x-ws.select id="course_id" name="course_id">
                        @foreach ($availableCourses as $course)
                            <option value="{{ $course->id }}">
                                {{ $course->level_code ? $course->level_code.' — ' : '' }}{{ $course->title }}{{ $course->session_count ? ' ('.$course->session_count.' buổi)' : '' }}
                            </option>
                        @endforeach
                    </x-ws.select>
                </x-ws.field>
                <x-ws.btn type="submit" variant="primary" icon="plus">Thêm vào cuối</x-ws.btn>
            </form>
            <p class="mt-2 text-[11px] leading-relaxed text-slate-400">
                Bậc mới luôn thêm vào cuối; kéo thả để đưa lên vị trí mong muốn.
                Mã bậc, câu kết quả và số buổi sửa ở màn Khoá học.
            </p>
        @endif
    </x-ws.card>

    {{-- ══════ Đăng / gỡ ══════ --}}
    <x-ws.card title="Hiển thị ra ngoài" icon="eye">
        <div class="flex flex-wrap items-center gap-2">
            @if ($path->isPublished())
                <form method="POST" action="{{ route('admin.learning-paths.status', $path->id) }}">
                    @csrf
                    <input type="hidden" name="status" value="draft">
                    <x-ws.btn type="submit" variant="ghost" icon="eye-off">Gỡ xuống bản nháp</x-ws.btn>
                </form>
                <span class="inline-flex items-center gap-1 text-[12px] font-bold text-emerald-700">
                    <x-lucide name="check-circle-2" class="h-4 w-4" />Đang hiển thị công khai
                </span>
            @else
                <form method="POST" action="{{ route('admin.learning-paths.status', $path->id) }}">
                    @csrf
                    <input type="hidden" name="status" value="published">
                    <x-ws.btn type="submit" :variant="$canPublish ? 'success' : 'ghost'" icon="eye">Đăng lộ trình</x-ws.btn>
                </form>
                @unless ($canPublish)
                    <span class="text-[11px] font-medium text-rose-600">Còn {{ count($blockers) }} điều kiện chưa đạt ở trên.</span>
                @endunless
            @endif
        </div>
    </x-ws.card>
</div>
@endsection

@push('scripts')
    @include('partials.learning-path-steps-script')
@endpush
