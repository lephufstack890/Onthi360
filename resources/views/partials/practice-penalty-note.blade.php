{{-- SỬA 10/10 — dòng nói rõ ĐIỂM BỊ TRỪ do nhật ký làm bài (chuyển tab / chụp màn hình / xem Hướng dẫn-Bài mẫu).
     Cần $feedback['penalty'] (xem Student\PracticeByQuestionService::recordSubmission); không có thì không in gì. --}}
@php $pen = $feedback['penalty'] ?? null; @endphp
@if ($pen && ($pen['percent'] ?? 0) > 0)
    @php
        $fmtN = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', ''), '0'), ',');
    @endphp
    <div class="mt-2 rounded-lg px-2.5 py-2 leading-5" style="background:#FFF7ED;color:#9A3412;border:1px solid #FED7AA" data-penalty-note>
        <p class="font-bold">
            Điểm bị trừ {{ $pen['percent'] }}% theo nhật ký làm bài{{ ($pen['baseScore'] ?? 0) > 0 ? ': '.$fmtN($pen['baseScore']).' → '.$fmtN($pen['score']).'/'.$fmtN($pen['maxPoints']).' điểm' : '' }}
        </p>
        <ul class="mt-0.5 list-disc pl-4">
            @foreach ($pen['parts'] as $part)
                <li>{{ ucfirst($part['label']) }} {{ $part['count'] }} lần (−{{ $part['percent'] }}%)</li>
            @endforeach
        </ul>
    </div>
@endif
