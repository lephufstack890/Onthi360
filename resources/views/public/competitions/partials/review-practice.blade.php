{{-- SỬA 3/11 (khách: "kỳ thi / kỳ khảo sát đã kết thúc thì cho học sinh làm lại bài nhưng không lưu điểm, không lưu bảng xếp
     hạng, làm đi làm lại được — thêm 1 nút thật đẹp") — khối LUYỆN LẠI ĐỀ dưới hộp "Kỳ thi đã kết thúc".
     $contestReview do App\Services\Contest\ContestReviewService::panel() dựng (null = không hiện). CSS riêng tiền tố crv-. --}}
@php $crv = $contestReview; $crvMany = count($crv['options']) > 1; @endphp
<style>
    .crv { position: relative; overflow: hidden; display: flex; flex-wrap: wrap; align-items: center; gap: 14px 18px; margin: 0 0 12px; padding: 14px 16px;
        border-radius: 14px; border: 1px solid #DDD6FE; background: linear-gradient(120deg, #F5F3FF 0%, #EEF2FF 55%, #E0F2FE 100%); }
    .crv::after { content: ""; position: absolute; right: -40px; top: -40px; width: 140px; height: 140px; border-radius: 50%; background: radial-gradient(circle, rgba(139, 92, 246, .16), transparent 70%); pointer-events: none; }
    .crv-ic { display: grid; place-items: center; width: 42px; height: 42px; flex: none; border-radius: 13px; color: #fff;
        background: linear-gradient(135deg, #8B5CF6, #6366F1); box-shadow: 0 8px 18px rgba(99, 102, 241, .28); }
    .crv-ic svg { width: 21px; height: 21px; }
    .crv-copy { flex: 1 1 260px; min-width: 0; }
    .crv-copy h3 { margin: 0; font-size: 15px; font-weight: 800; color: #3B2A7A; }
    .crv-copy p { margin: 3px 0 0; font-size: 12.5px; line-height: 1.5; color: #5B5A86; }
    .crv-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
    .crv-tag { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 999px; background: rgba(255, 255, 255, .8); border: 1px solid #E4DEFC; font-size: 11px; font-weight: 700; color: #5B4BB0; }
    .crv-tag svg { width: 12px; height: 12px; }
    .crv-act { position: relative; z-index: 1; display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .crv-act select { height: 40px; max-width: 220px; padding: 0 10px; border: 1px solid #D9D2FB; border-radius: 11px; background: #fff; font: inherit; font-size: 12.5px; font-weight: 600; color: #3B2A7A; }
    .crv-btn { display: inline-flex; align-items: center; gap: 8px; height: 42px; padding: 0 18px; border: 0; border-radius: 12px; cursor: pointer; white-space: nowrap; text-decoration: none;
        font: inherit; font-size: 13.5px; font-weight: 800; color: #fff; background: linear-gradient(135deg, #8B5CF6 0%, #6D28D9 100%);
        box-shadow: 0 10px 22px rgba(109, 40, 217, .28); transition: transform .15s, box-shadow .15s, filter .15s; }
    .crv-btn:hover { transform: translateY(-1px); filter: brightness(1.05); box-shadow: 0 14px 26px rgba(109, 40, 217, .34); }
    .crv-btn svg { width: 17px; height: 17px; } .crv-btn .go { transition: transform .15s; } .crv-btn:hover .go { transform: translateX(3px); }
    .crv-btn.is-ghost { background: #fff; color: #6D28D9; border: 1px solid #D9D2FB; box-shadow: none; }
    .crv-btn:disabled { opacity: .65; cursor: wait; transform: none; }
    @media (max-width: 640px) { .crv-act, .crv-act form { width: 100%; } .crv-act select { max-width: none; flex: 1 1 100%; } .crv-btn { flex: 1; justify-content: center; } }
</style>
<section class="crv" aria-label="Luyện lại đề">
    <span class="crv-ic"><x-lucide name="rotate-ccw" /></span>
    <div class="crv-copy">
        <h3>Luyện lại đề {{ $survey ? 'kỳ khảo sát' : 'kỳ thi' }} này</h3>
        <p>Kỳ đã kết thúc nhưng bạn vẫn có thể làm lại đề để ôn tập — xem đúng sai và điểm ngay sau khi nộp.</p>
        <div class="crv-tags">
            <span class="crv-tag"><x-lucide name="shield-check" />Không tính điểm</span>
            <span class="crv-tag"><x-lucide name="trophy" />Không vào bảng xếp hạng</span>
            <span class="crv-tag"><x-lucide name="refresh-cw" />Làm không giới hạn</span>
            @if (($crv['doneCount'] ?? 0) > 0)<span class="crv-tag"><x-lucide name="history" />Đã luyện {{ $crv['doneCount'] }} lần</span>@endif
        </div>
    </div>
    <div class="crv-act">
        @if (! empty($crv['resumeUrl']))
            <a href="{{ $crv['resumeUrl'] }}" class="crv-btn is-ghost"><x-lucide name="play" />Tiếp tục lượt đang luyện</a>
        @endif
        <form method="POST" action="{{ $crv['url'] }}" x-data="{ busy: false }" @submit="busy = true" style="display:flex;flex-wrap:wrap;gap:8px">
            @csrf
            @if ($crvMany)
                <select name="assessment_id" aria-label="Chọn đề luyện lại">
                    @foreach ($crv['options'] as $oid => $olabel)
                        <option value="{{ $oid }}" @selected($oid === $crv['preferred'])>{{ $olabel }}</option>
                    @endforeach
                </select>
            @else
                <input type="hidden" name="assessment_id" value="{{ $crv['preferred'] }}">
            @endif
            <button type="submit" class="crv-btn" :disabled="busy">
                <x-lucide name="rotate-ccw" /><span x-text="busy ? 'Đang mở đề…' : '{{ ! empty($crv['resumeUrl']) ? 'Làm lượt mới' : 'Luyện lại ngay' }}'">{{ ! empty($crv['resumeUrl']) ? 'Làm lượt mới' : 'Luyện lại ngay' }}</span><x-lucide name="arrow-right" class="go" />
            </button>
        </form>
    </div>
</section>
