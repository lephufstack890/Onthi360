{{-- SỬA 18/10 — MODULE BỘ ĐỀ: ô "Bộ đề" ở trang chi tiết đề LUYỆN TẬP (admin/content/show, $type === 'assessment').
     Bật/tắt "Chỉ trong bộ đề" + xem đề đang thuộc bộ đề nào + "Thêm vào bộ đề…". Logic: ExamBundleService.
     Tham số: $assessment (App\Models\Assessment). Chỉ hiện với đề loại Luyện tập & khi đã chạy migration. --}}
@php
    $bxSvc = app(\App\Services\ExamBundle\ExamBundleService::class);
    $bxReady = \App\Services\ExamBundle\ExamBundleService::ready();
    $bxIsPractice = ($assessment->type?->value ?? $assessment->type) === 'practice';
@endphp

@if ($bxReady && $bxIsPractice)
    @php
        $bxIn = $bxSvc->bundlesContaining($assessment);
        $bxAll = \App\Models\Product::query()->where('type', \App\Enums\ProductType::Exam->value)->orderBy('title')->get(['id', 'title']);
        $bxOthers = $bxAll->reject(fn ($p) => $bxIn->contains('id', $p->id));
        $bxPublished = ($assessment->status?->value ?? $assessment->status) === 'published';
    @endphp
    @if (session('status') === 'bundle-saved' && session('statusMessage'))
        @include('partials.toast-flash', ['type' => 'success', 'message' => session('statusMessage')])
    @endif
    <style>
        .ebx { display: grid; gap: 12px; }
        .ebx-row { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 12px; border: 1px solid #dbe7f3; border-radius: 12px; background: #f8fbff; font-size: 13px; color: #334155; }
        .ebx-row form { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: 0; }
        .ebx-row b { color: #1e3a5f; }
        .ebx-note { margin: 2px 0 0; font-size: 11.5px; color: #7a8ea3; }
        .ebx-btn { display: inline-flex; align-items: center; min-height: 32px; padding: 0 12px; border: 1px solid #d6e4f0; border-radius: 10px; background: #fff; font-size: 12.5px; font-weight: 700; color: #35506a; cursor: pointer; text-decoration: none; }
        .ebx-btn--on { border-color: #f5c27a; background: #fff8eb; color: #92400e; }
        .ebx-select { min-height: 32px; border: 1px solid #bdcbdc; border-radius: 10px; padding: 0 8px; font-size: 12.5px; max-width: 260px; }
        .ebx-chip { display: inline-flex; align-items: center; gap: 4px; margin: 2px 4px 2px 0; padding: 2px 9px; border-radius: 999px; background: #e0f2fe; color: #0369a1; font-size: 12px; font-weight: 700; text-decoration: none; }
    </style>
    <section class="acx-card acx-card--white" id="exam-bundle-box" style="scroll-margin-top:80px">
        <h2><x-lucide name="package" /> Bộ đề</h2>
        <div class="ebx">
            <div class="ebx-row">
                <div>
                    @if ($assessment->bundle_only)
                        <b>Chỉ trong bộ đề</b> — đề KHÔNG hiện ở trang Luyện tập; chỉ người mua bộ đề chứa đề này làm được.
                    @else
                        <b>Công khai</b> — đề hiện ở trang Luyện tập, ai cũng làm miễn phí (kể cả khi nằm trong bộ đề).
                    @endif
                </div>
                <form method="POST" action="{{ route('admin.content.assessments.bundle.visibility', $assessment->id) }}">
                    @csrf
                    <input type="hidden" name="bundle_only" value="{{ $assessment->bundle_only ? 0 : 1 }}">
                    <button type="submit" class="ebx-btn {{ $assessment->bundle_only ? '' : 'ebx-btn--on' }}">
                        {{ $assessment->bundle_only ? 'Công khai lại ở Luyện tập' : 'Chuyển thành "Chỉ trong bộ đề"' }}
                    </button>
                </form>
            </div>

            <div class="ebx-row">
                <div>
                    <b>Đang thuộc bộ đề:</b>
                    @forelse ($bxIn as $bp)
                        <a class="ebx-chip" href="{{ route('admin.products.show', $bp->id) }}#exam-bundle">{{ $bp->title }}</a>
                    @empty
                        <span style="color:#7a8ea3">chưa thuộc bộ đề nào</span>
                    @endforelse
                    @if ($assessment->bundle_only && $bxIn->isEmpty())
                        <p class="ebx-note" style="color:#b45309">Đề "Chỉ trong bộ đề" nhưng chưa nằm trong bộ đề nào — chưa ai làm được đề này.</p>
                    @endif
                    @unless ($bxPublished)
                        <p class="ebx-note">Đề chưa phát hành — phát hành đề thì mới vào được bộ đề.</p>
                    @endunless
                </div>
                @if ($bxPublished && $bxOthers->isNotEmpty())
                    <form method="POST" action="{{ route('admin.content.assessments.bundle.attach', $assessment->id) }}">
                        @csrf
                        <select name="product_id" class="ebx-select" required aria-label="Chọn bộ đề">
                            <option value="">Thêm vào bộ đề…</option>
                            @foreach ($bxOthers as $bp)
                                <option value="{{ $bp->id }}">{{ $bp->title }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="ebx-btn">Thêm</button>
                    </form>
                @endif
            </div>
        </div>
    </section>
@endif
