{{--
  Toast container dùng chung cho toàn bộ hệ thống — thay cho các banner màu inline
  (session('status')/session('warning')/lỗi validate) từng trang trước đây.
  Include ở layouts/app.blade.php và layouts/guest.blade.php, ngay trước </body>.

  Cách 1 view con phát 1 toast: @include('partials.toast-flash', ['type' => 'success', 'message' => '...'])
  Cách JS phát toast (VD sau 1 hành động AJAX nếu sau này cần): window.toast('success', 'Đã lưu.')

  window.__flashToasts được khởi tạo TRƯỚC (xem layouts/app.blade.php và layouts/guest.blade.php,
  ngay đầu <head>) để các script toast-flash chạy trước khi Alpine (defer) load vẫn push được
  vào mảng, không bị lỗi "undefined" hay mất toast do thứ tự script.
--}}
<div
    x-data="{
        toasts: [],
        icons: { success: '✅', error: '⚠️', warning: '⚠️', info: 'ℹ️' },
        init() {
            window.toast = (type, message) => this.push(type, message);
            (window.__flashToasts || []).forEach((f) => this.push(f.type, f.message));
        },
        push(type, message) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, type, message, icon: this.icons[type] || this.icons.info });
            setTimeout(() => this.remove(id), 2000);
        },
        remove(id) {
            this.toasts = this.toasts.filter((t) => t.id !== id);
        },
    }"
    class="fixed top-4 right-4 z-[100] w-full max-w-sm space-y-2"
>
    <template x-for="t in toasts" :key="t.id">
        <div
            x-show="true"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="pointer-events-auto rounded-xl shadow-lg border p-3.5 flex items-start gap-2 text-sm bg-white"
            :class="{
                'border-emerald-200 text-emerald-700': t.type === 'success',
                'border-rose-200 text-rose-700': t.type === 'error',
                'border-amber-200 text-amber-800': t.type === 'warning',
                'border-sky-200 text-sky-700': t.type === 'info',
            }"
        >
            <span x-text="t.icon" class="shrink-0"></span>
            <span x-text="t.message" class="flex-1"></span>
            <button type="button" @click="remove(t.id)" class="text-slate-400 hover:text-slate-600 shrink-0">✕</button>
        </div>
    </template>
</div>
