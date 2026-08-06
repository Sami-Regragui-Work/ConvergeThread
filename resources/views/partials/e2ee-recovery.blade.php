{{-- Export / import E2EE identity private key (multi-device recovery). --}}
@auth
<div x-data="e2eeRecovery({
    userId: @js(auth()->id()),
    publicKeyUrl: @js(route('messages.crypto.public-key')),
})" x-cloak class="contents">
    <template x-teleport="body">
        <div x-show="open" class="fixed inset-0 z-200 flex items-center justify-center p-4" @keydown.escape.window="open = false">
            <div class="absolute inset-0 bg-black/70" @click="open = false"></div>
            <div class="relative w-full max-w-lg rounded-2xl border border-white/10 bg-surface-300 shadow-2xl p-5 space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-white">E2EE key recovery</h2>
                        <p class="text-[11px] text-slate-500 mt-0.5">Export this browser’s private key to unlock chats on another device.</p>
                    </div>
                    <button type="button" @click="open = false" class="text-slate-400 hover:text-white text-sm">Close</button>
                </div>
                <div class="space-y-2">
                    <button type="button" @click="exportKey()"
                        class="w-full px-3 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold">
                        Download recovery key
                    </button>
                    <label class="block w-full px-3 py-2 rounded-xl border border-white/10 text-slate-300 text-sm text-center cursor-pointer hover:bg-white/5">
                        Import recovery key…
                        <input type="file" accept=".json,application/json" class="hidden" @change="importKey($event)">
                    </label>
                    <p x-show="status" class="text-xs text-slate-400" x-text="status"></p>
                    <p x-show="error" class="text-xs text-red-400" x-text="error"></p>
                </div>
            </div>
        </div>
    </template>
</div>
<script>
    function e2eeRecovery(config) {
        return {
            open: false,
            userId: config.userId,
            publicKeyUrl: config.publicKeyUrl,
            status: '',
            error: '',
            init() {
                window.addEventListener('ct-e2ee-recovery', () => {
                    this.open = true;
                    this.status = '';
                    this.error = '';
                });
            },
            storageKey() {
                return 'ct_e2ee_private_' + this.userId;
            },
            exportKey() {
                this.error = '';
                const raw = localStorage.getItem(this.storageKey());
                if (!raw) {
                    this.error = 'No private key in this browser yet. Open a chat once first.';
                    return;
                }
                const blob = new Blob([JSON.stringify({
                    version: 1,
                    user_id: this.userId,
                    private_jwk: JSON.parse(raw),
                    exported_at: new Date().toISOString(),
                }, null, 2)], { type: 'application/json' });
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = 'convergethread-e2ee-recovery-' + this.userId + '.json';
                a.click();
                URL.revokeObjectURL(a.href);
                this.status = 'Recovery file downloaded. Keep it private.';
            },
            async importKey(event) {
                this.error = '';
                this.status = '';
                const file = event.target.files?.[0];
                event.target.value = '';
                if (!file) return;
                try {
                    const text = await file.text();
                    const data = JSON.parse(text);
                    const jwk = data.private_jwk || data;
                    if (!jwk || jwk.kty !== 'EC' || !jwk.d) {
                        this.error = 'Invalid recovery file.';
                        return;
                    }
                    localStorage.setItem(this.storageKey(), JSON.stringify(jwk));
                    if (window.ChatCrypto) {
                        await window.ChatCrypto.ensureIdentity(this.userId, this.publicKeyUrl);
                    }
                    this.status = 'Key imported. Reload open chats to decrypt history.';
                } catch (e) {
                    this.error = 'Could not read recovery file.';
                }
            },
        };
    }
    window.__openE2eeRecovery = () => window.dispatchEvent(new CustomEvent('ct-e2ee-recovery'));
</script>
@endauth
