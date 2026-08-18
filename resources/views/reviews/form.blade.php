{{--
  Route: reviews.form (GET) / reviews.store (POST)
  Spec: 9.3 (tiêu chí phụ theo loại đối tượng/vai trò) + disclosure
  checkbox bắt buộc + validation (13.3). 4 loại đối tượng: material (tài liệu), class (lớp
  học), teacher (giáo viên), competition (cuộc thi) — note họp 13/8, mục 2: "Giáo viên, tài
  liệu, cuộc thi cần có đánh giá sao của người dùng".
  $type/$targetId/$existing do App\Http\Controllers\ReviewController::form() truyền vào (đã
  qua kiểm tra đủ điều kiện — ReviewEligibilityService, hoặc ReviewPolicy::update() nếu đang
  SỬA review cũ còn trong hạn 7 ngày). $existing (App\Models\Review|null): có giá trị khi học
  sinh mở lại form để sửa đánh giá đã gửi trước đó — trước đây form luôn trắng dù review cũ đã
  tồn tại, khiến học sinh mất hết nội dung cũ khi "sửa". Submit thật qua reviews.store, xử lý ở
  App\Services\Review\ReviewService::store() — kiểm tra lại điều kiện lần nữa tại server
  trước khi lưu (16 mục 3).
--}}
@extends('layouts.guest')

@section('title', ($existing ?? null) ? 'Sửa đánh giá' : 'Viết đánh giá')

@section('content')
    @php
        $type = $type ?? request('type', 'material');
        $targetId = $targetId ?? (int) request('id', 0);
        $existing = $existing ?? null;
        $criteriaByType = [
            'material' => ['ro_rang' => 'Rõ ràng/dễ học', 'chat_luong_bai_tap' => 'Chất lượng bài tập', 'muc_phu_hop' => 'Mức phù hợp', 'trinh_bay' => 'Trình bày'],
            'class' => ['to_chuc' => 'Tổ chức/lịch học', 'nhip_do' => 'Nhịp độ phù hợp', 'ho_tro' => 'Hỗ trợ học tập', 'moi_truong' => 'Môi trường lớp'],
            'teacher' => ['giang_day' => 'Chất lượng giảng dạy', 'tan_tam' => 'Tận tâm/hỗ trợ', 'de_hieu' => 'Dễ hiểu', 'to_chuc_lop' => 'Đúng giờ/tổ chức'],
            'competition' => ['de_thi' => 'Chất lượng đề thi', 'to_chuc_thi' => 'Tổ chức cuộc thi', 'cong_bang' => 'Công bằng/minh bạch', 'cong_bo_kq' => 'Công bố kết quả'],
        ];
        $criteria = $criteriaByType[$type] ?? $criteriaByType['material'];
        // old() (redo sau lỗi validate) ưu tiên hơn dữ liệu review cũ — review cũ chỉ dùng để
        // điền sẵn lần ĐẦU mở form sửa, không được ghi đè lên input học sinh vừa gõ rồi bị lỗi.
        $existingCriteria = $existing?->criteria_scores ?? [];
    @endphp

    <div class="max-w-2xl mx-auto px-4 py-10">
        <a href="{{ url()->previous() }}" class="text-sm text-slate-500 mb-4 inline-block">‹ Quay lại</a>

        <div class="bg-white rounded-2xl border border-slate-200 p-6">
            <h1 class="text-lg font-semibold text-slate-800 mb-1">{{ $existing ? 'Sửa đánh giá' : 'Viết đánh giá' }}</h1>
            <p class="text-sm text-slate-500 mb-6">Chia sẻ trải nghiệm thực để giúp người học khác — mọi đánh giá đều qua kiểm duyệt trước khi công bố (9.4).</p>

            @if ($existing)
                <div class="mb-5 text-sm text-amber-700 bg-amber-50 border border-amber-100 rounded-lg p-3">
                    Đánh giá này {{ $existing->isPublished() ? 'đang hiển thị công khai' : 'đang chờ duyệt' }}. Nếu bạn lưu thay đổi, đánh giá sẽ <strong>tạm ẩn khỏi công khai</strong> và quay lại hàng chờ kiểm duyệt của Admin cho tới khi được công bố lại.
                </div>
            @endif

            @if ($errors->any())
                @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
            @endif

            <form method="POST" action="{{ route('reviews.store') }}" class="space-y-5" x-data="{
                    overall: {{ (int) old('overall_rating', $existing?->overall_rating ?? 0) }},
                    criteria: { @foreach ($criteria as $key => $label) {{ $key }}: {{ (int) old('criteria_scores.'.$key, $existingCriteria[$key] ?? 0) }}, @endforeach },
                }">
                @csrf
                <input type="hidden" name="type" value="{{ $type }}">
                <input type="hidden" name="target_id" value="{{ $targetId }}">

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-2">Mức hài lòng tổng thể</label>
                    <input type="hidden" name="overall_rating" :value="overall">
                    <div class="flex gap-1 text-2xl">
                        <template x-for="i in [1,2,3,4,5]" :key="i">
                            <button type="button" @click="overall = i" :class="i <= overall ? 'text-amber-400' : 'text-slate-200'">★</button>
                        </template>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-2">Tiêu chí phụ (tùy chọn)</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach ($criteria as $key => $label)
                            <div class="flex items-center justify-between px-3 py-2 rounded-lg bg-slate-50 text-sm">
                                <span class="text-slate-600">{{ $label }}</span>
                                <input type="hidden" name="criteria_scores[{{ $key }}]" :value="criteria.{{ $key }}">
                                <div class="flex gap-0.5 text-lg">
                                    <template x-for="i in [1,2,3,4,5]" :key="i">
                                        <button type="button" @click="criteria.{{ $key }} = i" :class="i <= criteria.{{ $key }} ? 'text-amber-400' : 'text-slate-200'">★</button>
                                    </template>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="comment">Nhận xét</label>
                    <textarea id="comment" name="comment" rows="4" maxlength="1000" class="w-full rounded-lg border border-slate-200 text-sm p-3" placeholder="Nêu trải nghiệm cụ thể của bạn...">{{ old('comment', $existing?->comment ?? '') }}</textarea>
                </div>

                <label class="flex items-start gap-3 text-sm text-slate-600 bg-slate-50 rounded-lg p-3">
                    <input type="checkbox" name="disclosure_ack" value="1" class="mt-0.5" required @checked(old('disclosure_ack', $existing !== null))>
                    Tôi chia sẻ từ trải nghiệm thực; không đăng thông tin cá nhân của học sinh khác.
                </label>

                <div class="flex gap-3">
                    <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium">{{ $existing ? 'Lưu thay đổi' : 'Gửi đánh giá' }}</button>
                </div>
                @unless ($existing)
                    <p class="text-xs text-slate-400">Bạn có thể sửa đánh giá trong 7 ngày sau khi gửi.</p>
                @endunless
            </form>
        </div>
    </div>
@endsection
