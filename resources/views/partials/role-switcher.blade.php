@auth
    @if (auth()->user()->roles()->count() > 1)
        <div class="relative">
            <button type="button" class="text-sm px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600">
                Vai trò ▾
            </button>
        </div>
    @endif
@endauth
