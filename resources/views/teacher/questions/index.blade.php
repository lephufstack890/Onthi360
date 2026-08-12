{{--
  Route: teacher.questions.index | Frame: TEA-03
  Spec: 6.5 (kho riêng giáo viên — chỉ giáo viên tạo/chỉnh/sử dụng trong
  lớp của mình; không mặc định thấy/sửa kho chung hoặc kho giáo viên khác).
  TODO controller: truyền $questions = Question::where('owner_id', auth()->id())->paginate().
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
        $typeIcons = ['Trắc nghiệm' => '🔤', 'Điền đáp án' => '✏️', 'Lập trình' => '💻'];
    @endphp

    <div class="rounded-3xl bg-gradient-to-br from-violet-100 via-white to-sky-50 p-6 lg:p-8 mb-6 flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-start gap-4">
            <div class="w-14 h-14 rounded-2xl bg-white flex items-center justify-center text-3xl shrink-0 shadow-sm">❓</div>
            <div>
                <h1 class="text-xl lg:text-2xl font-semibold text-slate-800">Kho câu hỏi của tôi</h1>
                <p class="text-sm text-slate-500 mt-1">Chỉ bạn tạo/sửa/sử dụng — ranh giới rõ với kho chung của hệ thống (6.5).</p>
            </div>
        </div>
        <a href="{{ route('teacher.questions.create') }}" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm shrink-0">+ Tạo câu hỏi</a>
    </div>

    <x-tabs :tabs="$tabs" />

    <x-data-table :columns="['Tên câu hỏi', 'Loại', 'Trạng thái', '']">
        @forelse ($questions as $q)
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3 font-medium text-slate-700">
                    <span class="mr-2">{{ $typeIcons[$q['type']] ?? '❓' }}</span>{{ $q['title'] }}
                </td>
                <td class="px-4 py-3 text-slate-500">{{ $q['type'] }}</td>
                <td class="px-4 py-3"><x-status-badge :tone="$q['tone']">{{ $q['status'] }}</x-status-badge></td>
                <td class="px-4 py-3 text-right"><a href="{{ route('teacher.questions.create') }}" class="text-rose-600 font-medium">Sửa</a></td>
            </tr>
        @empty
            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">Chưa có câu hỏi nào trong kho của bạn.</td></tr>
        @endforelse
    </x-data-table>
@endsection
