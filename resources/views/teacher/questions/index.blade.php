{{--
  Route: teacher.questions.index | Frame: TEA-03
  Spec: 6.5 (kho riêng giáo viên — chỉ giáo viên tạo/chỉnh/sử dụng trong lớp của
  mình). Tab "Kho chung (chỉ xem)" cho giáo viên xem thêm câu hỏi thuộc Kho chung
  của hệ thống — CHỈ XEM, không Sửa/Phát hành/Lưu trữ được (đó vẫn là việc của
  Admin/Editor); teacher.assessments.store khi soạn đề vẫn chỉ nhận câu thuộc
  đúng kho riêng của giáo viên, không đổi ở đây.
  Dữ liệu thật do App\Services\Teacher\QuestionService::listForTeacher() truyền vào.
  "+ Nhập đề (Word/PDF/OCR)" trỏ sang teacher.assessments.import (6.4) — câu tách ra
  từ OCR/trích xuất, khi lưu và đủ điều kiện, sẽ tự vào đúng kho này.
--}}
@extends('layouts.teacher')

@section('title', 'Kho câu hỏi của tôi')
@section('page-title', 'Kho câu hỏi của tôi')

@section('content')
    {{-- Dữ liệu thật do App\Http\Controllers\Teacher\QuestionController truyền vào. --}}
    @php
        $tab = $tab ?? 'all';
        $tabs = $tabs ?? [];
        $questions = $questions ?? [];
        $total = $total ?? count($questions);
        $typeIcons = ['Trắc nghiệm' => '🔤', 'Điền đáp án' => '✏️', 'Lập trình' => '💻'];
    @endphp

    <div class="rounded-3xl bg-gradient-to-br from-violet-100 via-white to-sky-50 p-6 lg:p-8 mb-6 flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-start gap-4">
            <div class="w-14 h-14 rounded-2xl bg-white flex items-center justify-center text-3xl shrink-0 shadow-sm">❓</div>
            <div>
                <h1 class="text-xl lg:text-2xl font-semibold text-slate-800">Kho câu hỏi của tôi</h1>
                <p class="text-sm text-slate-500 mt-1">Kho riêng chỉ bạn tạo/sửa/sử dụng — có thể xem thêm Kho chung của hệ thống ở tab riêng, nhưng không sửa được (6.5).</p>
            </div>
        </div>
        <div class="flex items-center gap-3 shrink-0">
            <a href="{{ route('teacher.assessments.import') }}" class="px-5 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">+ Nhập đề (Word/PDF/OCR)</a>
            <a href="{{ route('teacher.questions.create') }}" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm">+ Tạo câu hỏi</a>
        </div>
    </div>

    <x-tabs :tabs="$tabs" />

    @if (session('status') === 'question-created')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu nháp câu hỏi.'])
    @elseif (session('status') === 'question-updated')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu thay đổi.'])
    @elseif (session('status') === 'question-published')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã phát hành câu hỏi.'])
    @elseif (session('status') === 'question-archived')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu trữ câu hỏi.'])
    @endif

    <x-data-table :columns="['Tên câu hỏi', 'Loại', 'Trạng thái', '']">
        @forelse ($questions as $q)
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3 font-medium text-slate-700">
                    <span class="mr-2">{{ $typeIcons[$q['type']] ?? '❓' }}</span>{{ $q['title'] }}
                </td>
                <td class="px-4 py-3 text-slate-500">{{ $q['type'] }}</td>
                <td class="px-4 py-3"><x-status-badge :tone="$q['tone']">{{ $q['status'] }}</x-status-badge></td>
                <td class="px-4 py-3 text-right space-x-3">
                    @if ($q['readOnly'] ?? false)
                        <span class="text-xs text-slate-400">Thuộc Kho chung — chỉ Admin/Editor sửa được (6.5).</span>
                    @else
                        <a href="{{ route('teacher.questions.edit', $q['id']) }}" class="text-rose-600 font-medium">Sửa</a>
                        @if ($q['canPublish'])
                            <form method="POST" action="{{ route('teacher.questions.publish', $q['id']) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-emerald-600 font-medium">Phát hành</button>
                            </form>
                        @endif
                        @if ($q['canArchive'])
                            <form method="POST" action="{{ route('teacher.questions.archive', $q['id']) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-slate-400 font-medium">Lưu trữ</button>
                            </form>
                        @endif
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">Chưa có câu hỏi nào trong kho của bạn.</td></tr>
        @endforelse
    </x-data-table>
    <x-pagination-note :shown="count($questions)" :total="$total" />
@endsection
