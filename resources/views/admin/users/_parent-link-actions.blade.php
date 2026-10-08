<div x-data="{ rejecting: false }">
    <div class="aux-act">
        <form method="POST" action="{{ route('admin.parent-links.approve', $link->id) }}">
            @csrf
            <button type="submit" class="acx-btn acx-btn--primary acx-btn--sm">Xác minh</button>
        </form>
        <button type="button" @click="rejecting = !rejecting" class="acx-btn acx-btn--sm">Từ chối</button>
    </div>
    <form method="POST" action="{{ route('admin.parent-links.reject', $link->id) }}" x-show="rejecting" x-cloak class="aux-reject">
        @csrf
        <input type="text" name="reason" required maxlength="1000" placeholder="Lý do từ chối..." class="apx-input apx-input--grow">
        <button type="submit" class="acx-btn acx-btn--primary acx-btn--sm">Gửi</button>
    </form>
</div>
