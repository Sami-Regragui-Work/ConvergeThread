{{-- Global incoming-call ring (works on any page, not only inside a chat). --}}
@auth
@unless(auth()->user()->isOwner())
<div x-data="globalCallRing({ userId: {{ (int) auth()->id() }} })" x-cloak x-init="init()">
    <div x-show="incoming" class="fixed inset-0 z-300 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60"></div>
        <div class="relative bg-surface-200 border border-white/10 rounded-2xl p-6 max-w-sm w-full shadow-2xl text-center space-y-4">
            <div class="w-16 h-16 mx-auto rounded-full bg-emerald-500/10 text-emerald-300 flex items-center justify-center text-xl font-bold animate-pulse"
                x-text="(incoming?.from_user_name || '?').slice(0, 1).toUpperCase()"></div>
            <div>
                <p class="text-white font-semibold" x-text="(incoming?.from_user_name || 'Someone') + ' is calling'"></p>
                <p class="text-sm text-slate-500 mt-1"
                    x-text="(incoming?.call_type === 'video' ? 'Video' : 'Voice') + ' · ' + (incoming?.chat_label || 'chat')"></p>
                <p x-show="error" class="text-xs text-amber-300 mt-2" x-text="error"></p>
            </div>
            <div class="flex gap-3">
                <button type="button" @click="decline()"
                    class="flex-1 py-2.5 rounded-xl bg-red-500/20 text-red-400 hover:bg-red-500/30 text-sm font-semibold transition">Decline</button>
                <button type="button" @click="accept()"
                    class="flex-1 py-2.5 rounded-xl bg-emerald-500/20 text-emerald-300 hover:bg-emerald-500/30 text-sm font-semibold transition">Accept</button>
            </div>
        </div>
    </div>
</div>

@once
@push('scripts')
@verbatim
<script>
    function globalCallRing(config) {
        return {
            userId: config.userId,
            incoming: null,
            error: '',
            ringtoneCtx: null,
            ringtoneNodes: null,

            init() {
                window.__ctInCall = window.__ctInCall || false;
                window.__ctSetInCall = (v) => { window.__ctInCall = !!v; };
                if (!window.Echo || !this.userId) return;
                window.Echo.private('user.' + this.userId)
                    .listen('.call.incoming', (payload) => this.onIncoming(payload))
                    .listen('.notifications.unread', (e) => {
                        if (typeof e?.count === 'number') {
                            window.dispatchEvent(new CustomEvent('ct-unread', { detail: { count: e.count } }));
                        }
                    });
            },

            soundsMuted() {
                try { return localStorage.getItem('ct_sounds_muted') === '1'; } catch (e) { return false; }
            },

            onIncoming(payload) {
                if (!payload?.call_id) return;
                if (window.__ctInCall) return;
                if (window.__ctSuppressGlobalCall && window.__ctSuppressGlobalCall(payload)) return;
                this.incoming = payload;
                this.error = '';
                this.startRingtone();
            },

            startRingtone() {
                this.stopRingtone();
                if (this.soundsMuted()) return;
                try {
                    const Ctx = window.AudioContext || window.webkitAudioContext;
                    if (!Ctx) return;
                    const ctx = new Ctx();
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.value = 880;
                    gain.gain.value = 0.0001;
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start();
                    const pulse = () => {
                        if (!this.ringtoneNodes) return;
                        const now = ctx.currentTime;
                        gain.gain.cancelScheduledValues(now);
                        gain.gain.setValueAtTime(0.0001, now);
                        gain.gain.linearRampToValueAtTime(0.08, now + 0.05);
                        gain.gain.linearRampToValueAtTime(0.0001, now + 0.35);
                    };
                    pulse();
                    this.ringtoneNodes = { osc, gain, timer: setInterval(pulse, 900) };
                    this.ringtoneCtx = ctx;
                } catch (e) {}
            },

            stopRingtone() {
                if (this.ringtoneNodes?.timer) clearInterval(this.ringtoneNodes.timer);
                try { this.ringtoneNodes?.osc?.stop(); } catch (e) {}
                try { this.ringtoneCtx?.close(); } catch (e) {}
                this.ringtoneNodes = null;
                this.ringtoneCtx = null;
            },

            accept() {
                if (!this.incoming?.url) return;
                if (window.__ctInCall) {
                    this.error = 'Leave your current call before joining another.';
                    return;
                }
                this.stopRingtone();
                window.location.href = this.incoming.url;
            },

            async decline() {
                const call = this.incoming;
                this.stopRingtone();
                this.incoming = null;
                if (!call?.chat_type || !call?.chat_id) return;
                try {
                    await fetch('/messages/' + call.chat_type + '/' + call.chat_id + '/call/signal', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            action: 'reject',
                            call_id: call.call_id,
                            call_type: call.call_type || 'voice',
                            to_user_id: call.from_user_id,
                        }),
                    });
                } catch (e) {}
            },
        };
    }
</script>
@endverbatim
@endpush
@endonce
@endunless
@endauth
