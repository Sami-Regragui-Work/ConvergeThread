<div x-data="confirmDialog()"
    @confirm-action.window="ask($event.detail.message, $event.detail.form)"
    x-cloak>
    <div x-show="open" class="fixed inset-0 z-[300] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60" @click="cancel()"></div>
        <div class="relative w-full max-w-sm rounded-2xl border border-white/10 bg-surface-200 p-6 shadow-2xl space-y-4"
            @click.stop>
            <div class="w-12 h-12 rounded-xl bg-red-500/10 text-red-400 flex items-center justify-center mx-auto">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                </svg>
            </div>
            <div class="text-center space-y-1">
                <p class="text-white font-semibold">Please confirm</p>
                <p class="text-sm text-slate-400" x-text="message"></p>
            </div>
            <div class="flex gap-3 pt-1">
                <button type="button" @click="cancel()"
                    class="flex-1 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 text-sm font-semibold transition">
                    Cancel
                </button>
                <button type="button" @click="confirm()"
                    class="flex-1 py-2.5 rounded-xl bg-red-500/90 hover:bg-red-500 text-white text-sm font-semibold transition">
                    Confirm
                </button>
            </div>
        </div>
    </div>
</div>

@once
    @push('scripts')
        @verbatim
        <script>
            function confirmDialog() {
                return {
                    open: false,
                    message: '',
                    form: null,
                    ask(message, form) {
                        this.message = message || 'Are you sure?';
                        this.form = form || null;
                        this.open = true;
                    },
                    cancel() {
                        this.open = false;
                        this.form = null;
                    },
                    confirm() {
                        const form = this.form;
                        this.open = false;
                        this.form = null;
                        if (form) form.submit();
                    },
                };
            }
        </script>
        @endverbatim
    @endpush
@endonce
