{{--
  Route: reviews.form | Frame: REV-02
  Spec: 9.3 (tiêu chí phụ theo loại đối tượng/vai trò) + disclosure
  checkbox bắt buộc + validation (13.3).
  TODO controller: truyền $type/$id thật + $criteria theo bảng 9.3; xử lý
  submit qua service kiểm tra ReviewEligibilityService trước khi lưu.
--}}
@extends('layouts.guest')

@section('title', 'Viết đánh giá')

@section('content')
    @php
        $type = request('type', 'material');
        $criteriaByType = [
            'material' => ['Rõ ràng/dễ học', 'Chất lượng bài tập', 'Mức phù hợp', 'Trình bày'],
            'class' => ['Tổ chức/lịch học', 'Nhịp độ phù hợp', 'Hỗ trợ học tập', 'Môi trường lớp'],
        ];
        $criteria = $criteriaByType[$type] ?? $criteriaByType['material'];
    @endphp

    <div class="max-w-2xl mx-auto px-4 py-10">
        <a href="{{ url()->previous() }}" class="text-sm text-slate-500 mb-4 inline-block">‹ Quay lại</a>

        <div class="bg-white rounded-2xl border border-slate-200 p-6">
            <h1 class="text-lg font-semibold text-slate-800 mb-1">Viết đánh giá</h1>
            <p class="text-sm text-slate-500 mb-6">Chia sẻ trải nghiệm thực để giúp người học khác — mọi đánh giá đều qua kiểm duyệt trước khi công bố (9.4).</p>

            <form class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-2">Mức hài lòng tổng thể</label>
                    <div class="flex gap-1 text-2xl text-amber-400">
                        <button type="button">★</button><button type="button">★</button><button type="button">★</button><button type="button">★</button><button type="button" class="text-slate-200">★</button>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-2">Tiêu chí phụ (tùy chọn)</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach ($criteria as $c)
                            <div class="flex items-center justify-between px-3 py-2 rounded-lg bg-slate-50 text-sm">
                                <span class="text-slate-600">{{ $c }}</span>
                                <div class="flex gap-0.5 text-amber-400">
                                    <button type="button">★</button><button type="button">★</button><button type="button" class="text-slate-200">★</button><button type="button" class="text-slate-200">★</button><button type="button" class="text-slate-200">★</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Nhận xét</label>
                    <textarea rows="4" maxlength="1000" class="w-full rounded-lg border border-slate-200 text-sm p-3" placeholder="Nêu trải nghiệm cụ thể của bạn..."></textarea>
                    <p class="text-xs text-slate-400 mt-1 text-right">0/1.000 ký tự</p>
                </div>

                <label class="flex items-start gap-3 text-sm text-slate-600 bg-slate-50 rounded-lg p-3">
                    <input type="checkbox" class="mt-0.5" required>
                    Tôi chia sẻ từ trải nghiệm thực; không đăng thông tin cá nhân của học sinh khác.
                </label>

                <div class="flex gap-3">
                    <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium">Gửi đánh giá</button>
                    <button type="button" class="px-5 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium">Lưu nháp</button>
                </div>
                <p class="text-xs text-slate-400">Bạn có thể sửa đánh giá trong 7 ngày sau khi gửi (9.2).</p>
            </form>
        </div>
    </div>
@endsection
