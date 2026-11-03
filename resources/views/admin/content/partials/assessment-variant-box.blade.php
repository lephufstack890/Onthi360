{{-- SỬA 3/11 (khách: "đề có nhiều mã đề") — ô "Mã đề" ở trang chi tiết đề LUYỆN TẬP bên admin (admin/content/show).
     Gắn đề có sẵn làm mã đề khác của đề này, đặt tên mã, gỡ khỏi nhóm. Logic: App\Services\ExamVariantService.
     Ngoài trang Luyện tập: mỗi nhóm hiện 1 thẻ (đề gốc) kèm huy hiệu "N mã đề"; bấm Bắt đầu làm bài → popup chọn mã.
     Chưa chạy migration / không phải đề Luyện tập / là Bài phổ thông → không hiện gì. --}}
@php
    $vxReady = \App\Services\ExamVariantService::ready();
    $vxIsPractice = ($assessment->type?->value ?? $assessment->type) === 'practice';
@endphp

@if ($vxReady && $vxIsPractice && ! $assessment->isGeneralSet())
    @php
        $vxMembers = \App\Services\ExamVariantService::members($assessment);
        $vxInGroup = $vxMembers->count() > 1;
        $vxRootId = \App\Services\ExamVariantService::rootId($assessment);
        $vxCandidates = \App\Services\ExamVariantService::candidates($assessment);
        $vxStatus = fn ($a) => ($a->status?->value ?? $a->status) === 'published';
    @endphp
    @if (session('examVariantFlash'))
        @include('partials.toast-flash', ['type' => session('examVariantFlash')[0], 'message' => session('examVariantFlash')[1]])
    @endif
    <style>
        .vxb { display: grid; gap: 10px; }
        .vxb-note { margin: 0; font-size: 12px; line-height: 1.6; color: #64748b; }
        .vxb-list { display: grid; gap: 6px; margin: 0; padding: 0; list-style: none; }
        .vxb-item { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 8px 10px; border: 1px solid #dbe7f3; border-radius: 12px; background: #f8fbff; font-size: 13px; color: #334155; }
        .vxb-item.is-me { border-color: #93c5fd; background: #eff6ff; }
        .vxb-item form { display: inline-flex; align-items: center; gap: 6px; margin: 0; }
        .vxb-title { flex: 1 1 220px; min-width: 0; }
        .vxb-title a { font-weight: 700; color: #1e3a5f; text-decoration: none; }
        .vxb-title a:hover { text-decoration: underline; }
        .vxb-title small { display: block; font-size: 11.5px; color: #7a8ea3; }
        .vxb-tag { display: inline-flex; align-items: center; padding: 1px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .vxb-tag--root { background: #e0f2fe; color: #0369a1; }
        .vxb-tag--draft { background: #fef3c7; color: #92400e; }
        .vxb-input { width: 96px; min-height: 30px; border: 1px solid #bdcbdc; border-radius: 9px; padding: 0 8px; font-size: 12.5px; }
        .vxb-select { min-height: 32px; max-width: 360px; flex: 1 1 240px; border: 1px solid #bdcbdc; border-radius: 10px; padding: 0 8px; font-size: 12.5px; }
        .vxb-btn { display: inline-flex; align-items: center; min-height: 30px; padding: 0 11px; border: 1px solid #d6e4f0; border-radius: 9px; background: #fff; font-size: 12px; font-weight: 700; color: #35506a; cursor: pointer; }
        .vxb-btn:hover { background: #f1f7fd; }
        .vxb-btn--go { border-color: #2563eb; background: #2563eb; color: #fff; }
        .vxb-btn--go:hover { background: #1d4ed8; }
        .vxb-btn--red { border-color: #fecaca; color: #b91c1c; }
        .vxb-btn--red:hover { background: #fef2f2; }
        .vxb-add { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 10px; border: 1px dashed #bcd3ea; border-radius: 12px; background: #fff; }
        .vxb-add label { font-size: 12px; font-weight: 700; color: #35506a; }
    </style>
    <section class="acx-card acx-card--white" id="exam-variant-box" style="scroll-margin-top:80px">
        <h2><x-lucide name="copy" /> Mã đề {{ $vxInGroup ? '('.$vxMembers->count().' mã)' : '' }}</h2>
        <div class="vxb">
            @if ($vxInGroup)
                <p class="vxb-note">
                    Đề này thuộc một <b>nhóm mã đề</b>. Ngoài trang Luyện tập chỉ hiện <b>1 thẻ</b> (đề gốc) kèm nhãn "{{ $vxMembers->count() }} mã đề";
                    học sinh bấm <b>Bắt đầu làm bài</b> sẽ chọn mã rồi mới vào làm. Mỗi mã vẫn là một đề riêng — sửa nội dung, đáp án, phát hành ở chính đề đó.
                </p>
                <ul class="vxb-list">
                    @foreach ($vxMembers as $i => $m)
                        <li class="vxb-item {{ (int) $m->id === (int) $assessment->id ? 'is-me' : '' }}">
                            <span class="vxb-title">
                                <a href="{{ route('admin.content.show', ['content' => $m->id, 'kind' => 'assessment']) }}#exam-variant-box">{{ $m->title }}</a>
                                @if ((int) $m->id === $vxRootId)<span class="vxb-tag vxb-tag--root">Đề gốc</span>@endif
                                @unless ($vxStatus($m))<span class="vxb-tag vxb-tag--draft">Chưa phát hành — học sinh chưa thấy mã này</span>@endunless
                                <small>{{ $m->exam_code ?: '#'.$m->id }} · {{ $m->items_count > 0 ? $m->items_count : (int) ($m->answer_keys_count ?? 0) }} câu</small>
                            </span>
                            <form method="POST" action="{{ route('admin.content.assessments.variants.code', $m->id) }}">
                                @csrf
                                <input class="vxb-input" name="code" value="{{ $m->variant_code }}" maxlength="30" placeholder="{{ \App\Services\ExamVariantService::label($m, $i) }}" aria-label="Tên mã đề">
                                <button type="submit" class="vxb-btn">Lưu tên mã</button>
                            </form>
                            <form method="POST" action="{{ route('admin.content.assessments.variants.detach', $m->id) }}"
                                  onsubmit="return confirm({{ json_encode('Gỡ "'.$m->title.'" khỏi nhóm mã đề? Đề sẽ hiện lại như một đề lẻ, không mất lượt làm nào.', JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) }});">
                                @csrf
                                <button type="submit" class="vxb-btn vxb-btn--red">Gỡ khỏi nhóm</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="vxb-note">
                    Đề này chưa có mã đề nào khác — học sinh bấm Bắt đầu làm bài là vào làm luôn (như hiện tại).
                    Nếu đề có nhiều mã (mỗi mã một file đề / đáp án riêng): tạo từng mã như một đề Luyện tập bình thường, rồi gắn vào đây.
                </p>
            @endif

            @if ($vxCandidates->isNotEmpty())
                <form method="POST" action="{{ route('admin.content.assessments.variants.attach', $assessment->id) }}" class="vxb-add">
                    @csrf
                    @if (trim((string) $assessment->variant_code) === '')
                        <label for="vxb-own">Mã của đề này</label>
                        <input id="vxb-own" class="vxb-input" name="own_code" maxlength="30" placeholder="vd 101">
                    @endif
                    <label for="vxb-child">Gắn thêm mã đề</label>
                    <select id="vxb-child" name="child_id" class="vxb-select" required>
                        <option value="">Chọn đề luyện tập có sẵn…</option>
                        @foreach ($vxCandidates as $c)
                            <option value="{{ $c->id }}">{{ $c->title }} ({{ $c->exam_code ?: '#'.$c->id }}){{ $vxStatus($c) ? '' : ' · chưa phát hành' }}</option>
                        @endforeach
                    </select>
                    <input class="vxb-input" name="child_code" maxlength="30" placeholder="Tên mã, vd 102" aria-label="Tên mã của đề được gắn">
                    <button type="submit" class="vxb-btn vxb-btn--go">Gắn làm mã đề</button>
                </form>
            @else
                <p class="vxb-note">Không còn đề Luyện tập nào rảnh để gắn (đề đã thuộc nhóm khác thì phải gỡ ra trước).</p>
            @endif
        </div>
    </section>
@endif
