@extends('layouts.teacher')

@section('title', 'Bài tập & Đề')
@section('page-title', 'Bài tập & Đề')

@section('content')
    @php
        $assessments = $assessments ?? [];
    @endphp

    <div class="rounded-3xl bg-gradient-to-br from-violet-100 via-white to-sky-50 p-6 lg:p-8 mb-6 flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-start gap-4">
            <div class="w-14 h-14 rounded-2xl bg-white flex items-center justify-center text-3xl shrink-0 shadow-sm">🧾</div>
            <div>
                <h1 class="text-xl lg:text-2xl font-semibold text-slate-800">Bài tập & Đề</h1>
                <p class="text-sm text-slate-500 mt-1">Đề do bạn tự tạo từ kho câu hỏi riêng — trộn được nhiều kiểu câu (6.3).</p>
            </div>
        </div>
        <a href="{{ route('teacher.assessments.create') }}" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm shrink-0">+ Tạo đề mới</a>
    </div>

    @if (session('status') === 'assessment-created')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu nháp đề.'])
    @elseif (session('status') === 'assessment-published')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã phát hành đề.'])
    @endif
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <x-data-table :columns="['Tên đề', 'Số câu', 'Tổng điểm', 'Đã giao', 'Trạng thái', '']">
        @forelse ($assessments as $a)
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3 font-medium text-slate-700">{{ $a['title'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $a['itemsCount'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $a['totalPoints'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $a['assignmentsCount'] }} lớp</td>
                <td class="px-4 py-3"><x-status-badge :tone="$a['tone']">{{ $a['status'] }}</x-status-badge></td>
                <td class="px-4 py-3 text-right">
                    {{-- SỬA 24/8 — khách yêu cầu: bỏ "Giao cho lớp" ở đây, việc giao đề (chọn
                         đề có sẵn) chuyển hẳn sang tab "Giao đề" trong trang Chi tiết lớp. --}}
                    @if ($a['canPublish'])
                        <form method="POST" action="{{ route('teacher.assessments.publish', $a['id']) }}" class="inline">
                            @csrf
                            <button type="submit" class="text-emerald-600 font-medium">Phát hành</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Chưa có đề nào — bấm "+ Tạo đề mới" để bắt đầu.</td></tr>
        @endforelse
    </x-data-table>
@endsection
