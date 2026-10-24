{{-- SỬA 24/10 — nút hành động chính của phòng thi (dùng 2 chỗ: khối "Quyền tham gia" và thanh dưới trên điện thoại).
     $action do ContestHubService::action() dựng; kind: disabled | register | enter | tab | link. --}}
@php $kind = $action['kind'] ?? 'disabled'; @endphp
@if ($kind === 'register')
    <form method="POST" action="{{ $action['url'] }}">
        @csrf
        <button type="submit" class="contest-btn primary hub-primary-action"><x-lucide :name="$action['icon']" />{{ $action['label'] }}<x-lucide name="arrow-right" /></button>
    </form>
@elseif ($kind === 'enter')
    <button type="button" class="contest-btn primary hub-primary-action" @click="openEntry()"><x-lucide :name="$action['icon']" />{{ $action['label'] }}<x-lucide name="arrow-right" /></button>
@elseif ($kind === 'tab')
    <button type="button" class="contest-btn primary hub-primary-action" @click="{{ ! empty($action['openAdd']) ? 'openAdd()' : "showTab('".$action['tab']."')" }}"><x-lucide :name="$action['icon']" />{{ $action['label'] }}<x-lucide name="arrow-right" /></button>
@elseif ($kind === 'link')
    <a href="{{ $action['url'] }}" class="contest-btn primary hub-primary-action"><x-lucide :name="$action['icon']" />{{ $action['label'] }}<x-lucide name="arrow-right" /></a>
@else
    <button type="button" class="contest-btn primary hub-primary-action" disabled><x-lucide :name="$action['icon']" />{{ $action['label'] }}</button>
@endif
