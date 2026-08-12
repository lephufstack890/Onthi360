{{--
  Route: parent.children.index
  Spec: 10.3 (chỉ thấy con đã liên kết/xác minh) + 3.3 (không browse tìm trẻ em khác).
  TODO controller: xử lý submit tạo yêu cầu liên kết (ParentLink) + xác minh.
--}}
@extends('layouts.parent')

@section('title', 'Con của tôi')
@section('page-title', 'Con của tôi')

@section('content')
    @php
        $children = [
            ['id' => 1, 'name' => 'Nguyễn Minh An', 'class' => '10CT-2026', 'status' => 'Đã xác minh', 'tone' => 'success'],
        ];
    @endphp

    <x-page-header title="Con của tôi" subtitle="Chỉ hiển thị học sinh đã liên kết và xác minh — không thể tìm kiếm học sinh khác (10.3).">
        <x-slot:actions>
            <button type="button" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">+ Liên kết con</button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @forelse ($children as $c)
            <a href="{{ route('parent.children.show', $c['id']) }}" class="rounded-2xl bg-white border border-slate-200 p-5 hover:shadow-md transition block">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-violet-200 to-rose-100 flex items-center justify-center font-medium text-slate-700">
                        {{ mb_substr($c['name'], 0, 1) }}
                    </div>
                    <div>
                        <p class="font-medium text-slate-700">{{ $c['name'] }}</p>
                        <p class="text-xs text-slate-400">Lớp {{ $c['class'] }}</p>
                    </div>
                </div>
                <x-status-badge :tone="$c['tone']">{{ $c['status'] }}</x-status-badge>
            </a>
        @empty
            <div class="col-span-full">
                <x-empty-state title="Chưa liên kết con nào" description="Nhập mã liên kết do con cung cấp trong Hồ sơ, hoặc yêu cầu giáo viên/admin hỗ trợ xác minh." />
            </div>
        @endforelse
    </div>

    {{-- Form nhập mã liên kết --}}
    <div class="mt-6 bg-white rounded-2xl border border-slate-200 p-5 max-w-md">
        <h3 class="font-medium text-slate-700 mb-3">Nhập mã liên kết</h3>
        <div class="flex gap-2">
            <input type="text" class="flex-1 rounded-lg border border-slate-200 text-sm p-2.5" placeholder="VD: LINK-8F3K">
            <button type="button" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">Gửi yêu cầu</button>
        </div>
        <p class="text-xs text-slate-400 mt-2">Yêu cầu cần được xác minh trước khi bạn xem được dữ liệu của con.</p>
    </div>
@endsection
