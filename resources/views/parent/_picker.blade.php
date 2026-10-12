{{-- Ô "Đối tượng theo dõi" của source. GET ?child=… trên chính trang đang mở; chỉ hiện khi có từ 2 con trở lên. --}}
@if (! empty($multi))
    <form method="GET" action="{{ url()->current() }}" class="pw-pick">
        <label for="pw-child-select" style="display:contents"><span>Đối tượng theo dõi</span></label>
        <select id="pw-child-select" name="child" aria-label="Chọn học sinh theo dõi" onchange="this.form.submit()">
            <option value="all" @selected($selected === 'all')>Tất cả các con</option>
            @foreach ($children as $c)
                <option value="{{ $c['id'] }}" @selected($selected === $c['id'])>{{ $c['name'] }} · {{ $c['class'] }}</option>
            @endforeach
        </select>
        <noscript><button type="submit" class="pw-btn pw-btn--ghost">Xem</button></noscript>
    </form>
@endif
