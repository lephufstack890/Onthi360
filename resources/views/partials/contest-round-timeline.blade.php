{{-- Dải "các vòng thi" — chuyển đúng component RoundTimeline của
     education-main/src/components/ContestsPage.jsx sang Blade.
     $rounds : mảng vòng thi thật (competition_exams), mỗi phần tử có status completed|current|upcoming
     $compact: true = bản gọn dùng trong thẻ và hộp chi tiết (đúng như bản mẫu). --}}
@php
    $compact = $compact ?? false;
    $rounds = $rounds ?? [];
    $lastIndex = count($rounds) - 1;
@endphp

<div class="flex items-start {{ $compact ? 'gap-1' : 'gap-2' }}">
    @foreach ($rounds as $i => $round)
        <div class="min-w-0 flex-1 text-center">
            <div class="mx-auto flex items-center justify-center rounded-full border-2 {{ $compact ? 'h-6 w-6' : 'h-8 w-8' }} {{ $round['status'] === 'completed' ? 'border-emerald-500 bg-emerald-500 text-white' : ($round['status'] === 'current' ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-slate-200 bg-white text-slate-300') }}">
                @if ($round['status'] === 'completed')
                    <x-lucide name="check" class="{{ $compact ? 'h-3.5 w-3.5' : 'h-4 w-4' }}" stroke-width="3" />
                @else
                    <span class="font-black {{ $compact ? 'text-[9px]' : 'text-[10px]' }}">{{ $round['order'] }}</span>
                @endif
            </div>
            <p class="mt-1 truncate font-bold {{ $compact ? 'text-[9px]' : 'text-[10px]' }} {{ $round['status'] === 'upcoming' ? 'text-slate-400' : 'text-slate-700' }}">{{ $compact ? $round['shortLabel'] : $round['label'] }}</p>
            @unless ($compact)
                <p class="mt-0.5 text-[10px] text-slate-400">{{ $round['date'] }}</p>
            @endunless
        </div>
        @if ($i < $lastIndex)
            <div class="mt-3 h-0.5 flex-1 rounded-full {{ $round['status'] === 'completed' ? 'bg-emerald-300' : 'bg-slate-200' }}"></div>
        @endif
    @endforeach

    @if ($rounds === [])
        <p class="w-full py-1 text-center text-[10px] text-slate-400">Cuộc thi chưa gắn vòng thi nào.</p>
    @endif
</div>
