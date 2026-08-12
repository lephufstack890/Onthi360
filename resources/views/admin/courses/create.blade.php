{{--
  Route: admin.courses.create | "Khóa & Lớp" trong sidebar (4.2)
  Spec: 8.1 (Khóa học khác Lớp học — lớp được tạo riêng sau, gắn về khóa này).
  Dữ liệu thật ($grades, $statuses) do App\Http\Controllers\Admin\CourseController
  truyền vào qua App\Services\Admin\CourseService::createFormData().
--}}
@extends('layouts.admin')

@section('title', 'Tạo khóa học')
@section('page-title', 'Tạo khóa học')

@section('content')
    @php
        $grades = $grades ?? [];
        $statuses = $statuses ?? [];
    @endphp

    <a href="{{ route('admin.courses.index') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Khóa & Lớp</a>

    <div class="rounded-3xl bg-gradient-to-br from-sky-100 via-white to-rose-50 p-6 lg:p-8 mb-6 flex items-center gap-4 flex-wrap">
        <x-icon-tile emoji="🏫" tone="rose" />
        <div>
            <h1 class="text-xl lg:text-2xl font-semibold text-slate-800">Tạo khóa học mới</h1>
            <p class="text-sm text-slate-500 mt-1">Khóa học là "khung" nội dung — lớp học (lịch, giáo viên, học sinh) sẽ được tạo riêng và gắn vào khóa này sau (8.1).</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 mb-6 text-sm text-rose-700 flex items-start gap-2">
            <span class="shrink-0">⚠️</span>
            <div>
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-6">
            <form method="POST" action="{{ route('admin.courses.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="title">Tên khóa học</label>
                    <input id="title" name="title" type="text" value="{{ old('title') }}" required maxlength="255"
                           placeholder="Ví dụ: Luyện thi vào 10 Chuyên Tin"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="subject">Môn học</label>
                        <input id="subject" name="subject" type="text" value="{{ old('subject') }}" maxlength="60"
                               placeholder="Ví dụ: Tin học, Toán"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="grade">Khối lớp</label>
                        <x-select id="grade" name="grade" icon="🎓">
                            <option value="">— Không chỉ định —</option>
                            @foreach ($grades as $g)
                                <option value="{{ $g }}" @selected(old('grade') === $g)>{{ $g }}</option>
                            @endforeach
                        </x-select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="description">Mô tả khóa học</label>
                    {{-- CKEditor gắn vào đúng textarea này (script dùng chung ở partials.rich-editor-assets,
                         include ở @push('scripts') cuối file) — name="description" giữ nguyên nên vẫn submit
                         đúng field cũ, không đổi backend. --}}
                    <textarea id="description" name="description" rows="5" maxlength="5000" data-rich-editor
                              placeholder="Giới thiệu ngắn về mục tiêu, đối tượng phù hợp của khóa học..."
                              class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">{{ old('description') }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="status">Trạng thái</label>
                    <x-select id="status" name="status" required>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', 'draft') === $value)>{{ $label }}</option>
                        @endforeach
                    </x-select>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">Tạo khóa học</button>
                    <a href="{{ route('admin.courses.index') }}" class="px-5 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">Huỷ</a>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-6 space-y-4">
            <h3 class="font-medium text-slate-700 flex items-center gap-2"><span>💡</span> Cần biết</h3>
            <div class="flex items-start gap-3">
                <x-icon-tile emoji="🧭" tone="sky" />
                <p class="text-sm text-slate-500">Khóa học chỉ là khung nội dung — sau khi tạo, giáo viên (đã được duyệt) sẽ tạo lớp thuộc khóa này để dạy thật (3.3, 8.1).</p>
            </div>
            <div class="flex items-start gap-3">
                <x-icon-tile emoji="📝" tone="violet" />
                <p class="text-sm text-slate-500">Đường dẫn (slug) hiển thị công khai được tự sinh từ tên khóa học, không cần tự nhập.</p>
            </div>
            <div class="flex items-start gap-3">
                <x-icon-tile emoji="👁️" tone="amber" />
                <p class="text-sm text-slate-500">Chọn "Bản nháp" nếu chưa muốn hiển thị công khai — có thể xuất bản sau khi kiểm tra lại nội dung.</p>
            </div>
        </div>
    </div>

    @push('scripts')
        @include('partials.rich-editor-assets')
    @endpush
@endsection