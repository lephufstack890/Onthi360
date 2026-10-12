{{-- Thanh phân trang — nằm TRONG phạm vi x-data có swPager() (page, pages, from, to, total, size, nums, go). --}}
<div class="sw-pager" x-show="total > size" x-cloak>
    <p>Hiển thị <span x-text="from"></span>–<span x-text="to"></span> / <span x-text="total"></span></p>
    <nav aria-label="Phân trang">
        <button type="button" :disabled="page <= 1" @click="go(page - 1)" aria-label="Trang trước"><x-lucide name="chevron-left" /></button>
        <template x-for="(n, idx) in nums" :key="idx">
            <button type="button" :class="{ on: n === page }" :disabled="n === 0" :aria-current="n === page ? 'page' : null" @click="go(n)" x-text="n === 0 ? '…' : n"></button>
        </template>
        <button type="button" :disabled="page >= pages" @click="go(page + 1)" aria-label="Trang sau"><x-lucide name="chevron-right" /></button>
    </nav>
</div>
