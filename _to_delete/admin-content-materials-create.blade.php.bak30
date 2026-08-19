{{--
  Route: admin.content.materials.create / admin.content.materials.store
  Spec: 6.5 (học liệu thuộc sản phẩm — sách/chuyên đề/đề thi) + Table 27 (trạng thái nội dung).
  Dữ liệu thật ($products, $parents, $assessments, $types, $statuses) do
  ContentController::materialsCreate() truyền vào qua ContentService::materialCreateFormData().
--}}
@extends('layouts.admin')

@section('title', 'Tạo học liệu')
@section('page-title', 'Tạo học liệu')

@section('content')
    @php
        $products = $products ?? []; $parents = $parents ?? []; $assessments = $assessments ?? [];
        $types = $types ?? []; $statuses = $statuses ?? [];
    @endphp

    <a href="{{ route('admin.content.index') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Nội dung</a>

    <x-page-header title="📦 Tạo học liệu" subtitle="Học liệu là chương/bài/mục thuộc một sản phẩm (sách, chuyên đề, đề thi, khóa học) — 6.5." />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="bg-white rounded-2xl border border-slate-200 p-6" x-data="{ type: '{{ old('type', 'chapter') }}' }">
        <form method="POST" action="{{ route('admin.content.materials.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="product_id">Thuộc sản phẩm</label>
                <x-select id="product_id" name="product_id" required>
                    <option value="">— Chọn sản phẩm —</option>
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}" @selected((string) old('product_id') === (string) $p->id)>{{ $p->title }}</option>
                    @endforeach
                </x-select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="type">Loại</label>
                    <x-select id="type" name="type" x-model="type" required>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected(old('type', 'chapter') === $value)>{{ $label }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="order">Thứ tự hiển thị</label>
                    <input id="order" name="order" type="number" min="0" value="{{ old('order', 0) }}"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="title">Tiêu đề</label>
                <input id="title" name="title" type="text" value="{{ old('title') }}" required maxlength="255"
                       placeholder="Ví dụ: Chương 1 - Nhập môn"
                       class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="parent_id">Thuộc mục cha (tùy chọn)</label>
                <x-select id="parent_id" name="parent_id">
                    <option value="">— Không có, đây là mục gốc —</option>
                    @foreach ($parents as $par)
                        <option value="{{ $par['id'] }}" @selected((string) old('parent_id') === (string) $par['id'])>{{ $par['label'] }}</option>
                    @endforeach
                </x-select>
            </div>

            <div x-show="type === 'assessment_ref'" x-cloak>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="assessment_id">Đề/bộ bài tham chiếu</label>
                <x-select id="assessment_id" name="assessment_id">
                    <option value="">— Chọn đề/bộ bài —</option>
                    @foreach ($assessments as $a)
                        <option value="{{ $a->id }}" @selected((string) old('assessment_id') === (string) $a->id)>{{ $a->title }}</option>
                    @endforeach
                </x-select>
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
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">Tạo học liệu</button>
                <a href="{{ route('admin.content.index') }}" class="px-5 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">Huỷ</a>
            </div>
        </form>
    </div>
@endsection
