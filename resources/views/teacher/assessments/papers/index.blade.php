{{--
  Route: teacher.papers.index
  SỬA 18/8 (đề PDF, 16/8 mục 1.2: "Đề thi lẻ được Admin hoặc giáo viên tải lên dưới dạng
  PDF"): danh sách đề PDF RIÊNG của giáo viên — tách hẳn khỏi teacher/assessments/index.blade.php
  ("Bài tập & Đề" cũ, chọn Question rời — content_mode=structured, KHÔNG đổi). Đề ở đây LUÔN
  content_mode=pdf_answer_sheet, mặc định riêng tư (owner_type=teacher) cho tới khi Admin
  duyệt đưa ra kho chung (chưa làm ở Giai đoạn 1).
  Dữ liệu thật do App\Services\Teacher\AssessmentService::papersForTeacher() truyền vào.
--}}
@extends('layouts.teacher')

@section('title', 'Đề PDF của tôi')
@section('page-title', 'Đề PDF của tôi')

@section('content')
    @php $papers = $papers ?? []; @endphp

    <div class="rounded-3xl bg-gradient-to-br from-sky-100 via-white to-violet-50 p-6 lg:p-8 mb-6 flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-start gap-4">
            <div class="w-14 h-14 rounded-2xl bg-white flex items-center justify-center text-3xl shrink-0 shadow-sm">📄</div>
            <div>
                <h1 class="text-xl lg:text-2xl font-semibold text-slate-800">Đề PDF của tôi</h1>
                <p class="text-sm text-slate-500 mt-1">Tải đề dưới dạng PDF + đáp án — riêng tư cho tới khi Admin duyệt đưa ra kho chung</p>
            </div>
        </div>
        <a href="{{ route('teacher.papers.create') }}" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm shrink-0">+ Tạo đề PDF mới</a>
    </div>

    @if (session('status') === 'paper-created')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tạo đề — tiếp tục tải PDF và nhập đáp án.'])
    @elseif (session('status') === 'assessment-published')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã phát hành đề.'])
    @endif
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <x-data-table :columns="['Tên đề', 'Loại', 'Mã đề', 'PDF', 'Câu/đáp án', 'Trạng thái', '']">
        @forelse ($papers as $p)
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3 font-medium text-slate-700">{{ $p['title'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $p['type'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $p['examCode'] ?: '—' }}</td>
                <td class="px-4 py-3">
                    <span class="text-xs font-medium {{ $p['hasPdf'] ? 'text-emerald-600' : 'text-amber-600' }}">{{ $p['hasPdf'] ? 'Đã tải' : 'Chưa tải' }}</span>
                </td>
                <td class="px-4 py-3 text-slate-500">{{ $p['answerKeysCount'] }} câu · {{ $p['codingItemsCount'] }} bài code</td>
                <td class="px-4 py-3"><x-status-badge :tone="$p['tone']">{{ $p['status'] }}</x-status-badge></td>
                <td class="px-4 py-3 text-right space-x-3 whitespace-nowrap">
                    <a href="{{ route('teacher.papers.pdf.edit', $p['id']) }}" class="text-rose-600 font-medium">Quản lý đề PDF</a>
                    @if ($p['canPublish'])
                        <form method="POST" action="{{ route('teacher.assessments.publish', $p['id']) }}" class="inline">
                            @csrf
                            <button type="submit" class="text-emerald-600 font-medium">Phát hành</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-4 py-6 text-center text-slate-400">Chưa có đề PDF nào — bấm "+ Tạo đề PDF mới" để bắt đầu.</td></tr>
        @endforelse
    </x-data-table>
@endsection
