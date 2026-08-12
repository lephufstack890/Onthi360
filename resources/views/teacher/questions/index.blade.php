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
    @endphp

    <x-page-header title="Kho câu hỏi của tôi" subtitle="Chỉ bạn tạo/sửa/sử dụng — ranh giới rõ với kho chung của hệ thống (6.5).">
        <x-slot:actions>
            <a href="{{ route('teacher.questions.create') }}" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">+ Tạo câu hỏi</a>
        </x-slot:actions>
    </x-page-header>

    <x-tabs :tabs="$tabs" />

    <x-data-table :columns="['Tên câu hỏi', 'Loại', 'Trạng thái', '']">
        @forelse ($questions as $q)
            <tr>
                <td class="px-4 py-3 font-medium text-slate-700">{{ $q['title'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $q['type'] }}</td>
                <td class="px-4 py-3"><x-status-badge :tone="$q['tone']">{{ $q['status'] }}</x-status-badge></td>
                <td class="px-4 py-3 text-right"><a href="{{ route('teacher.questions.create') }}" class="text-rose-600 font-medium">Sửa</a></td>
            </tr>
        @empty
            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">Chưa có câu hỏi nào trong kho của bạn.</td></tr>
        @endforelse
    </x-data-table>
@endsection
