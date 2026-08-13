{{-- Participants menu: mute a specific person's voice for you during calls, or mute their
     notifications / calls / shrink their messages for you in this chat --}}
<div class="relative" x-data="{ open: false }">
    <button type="button" @click="open = !open" title="Participants — mute a specific person for you"
        class="p-2 rounded-lg border border-white/10 text-slate-400 hover:bg-white/5 hover:text-white transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87M16 3.13a4 4 0 010 7.75M12 7a4 4 0 11-8 0 4 4 0 018 0zm3 3a3 3 0 100-6"/>
        </svg>
    </button>

    <div x-show="open" @click.outside="open = false" x-cloak data-chat-overlay
        class="absolute right-0 mt-2 w-72 rounded-xl border border-white/10 bg-surface-200 shadow-xl z-50 py-2">
        <p class="px-3 pb-1 text-[10px] uppercase tracking-wide text-slate-500">People</p>
        <p class="px-3 pb-2 text-[11px] text-slate-500">Mute someone's voice in calls, or mute their notifications, calls and shrink their messages for you.</p>

        <input type="text" x-model="muteSearch" placeholder="Search…" @keydown.escape.window="open = false"
            class="mx-3 w-[calc(100%-1.5rem)] bg-surface-100 border border-white/10 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition">

        <div class="flex gap-3 px-3 pt-1.5 pb-1 text-xs">
            <button type="button" @click="selectAllMuteFiltered()" class="text-brand-400 hover:text-brand-300">Select filtered</button>
            <button type="button" @click="unselectAllMuteFiltered()" class="text-slate-400 hover:text-white">Unselect filtered</button>
        </div>

        <div class="max-h-64 overflow-y-auto">
            <template x-for="person in filteredForMute()" :key="'part-' + person.id">
                <div class="flex items-center gap-2.5 px-3 py-1.5 hover:bg-white/5">
                    <input type="checkbox" :checked="muteSelectedIds.includes(Number(person.id))"
                        @change="toggleMuteSelected(person.id)"
                        class="rounded border-white/20 bg-surface-100 text-brand-500 focus:ring-brand-500/50 shrink-0">
                    <div class="w-6 h-6 rounded-full bg-brand-500/15 text-brand-300 flex items-center justify-center text-[10px] font-bold shrink-0"
                        x-text="(participantLabel(person.id) || '?').slice(0, 1).toUpperCase()"></div>
                    <div class="min-w-0 flex-1 flex items-center gap-1.5">
                        <span class="text-sm text-slate-200 truncate" x-text="participantLabel(person.id)"></span>
                        <span x-show="hasUserMuteFlags(person.id)" x-cloak
                            class="shrink-0 inline-flex items-center px-1 py-0.5 rounded bg-amber-500/15 border border-amber-500/30 text-[9px] uppercase tracking-wide text-amber-300"
                            title="Muted for you in this chat">muted</span>
                    </div>
                    <button type="button" @click="toggleDeafenPeer(person.id)"
                        class="inline-flex items-center justify-center w-7 h-7 rounded-md border transition shrink-0"
                        :class="deafenPeerIds[person.id] ? 'border-amber-500/40 bg-amber-500/10 text-amber-300' : 'border-white/10 text-slate-400 hover:text-white'"
                        :title="deafenPeerIds[person.id] ? 'Unmute ' + participantLabel(person.id) + ' for me' : 'Mute ' + participantLabel(person.id) + ' for me'">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 8.5a6.5 6.5 0 1 1 13 0c0 6-6 6-6 10a3.5 3.5 0 1 1-7 0"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 8.5a2.5 2.5 0 0 0-5 0v1a2 2 0 1 1 0 4"/>
                            <path x-show="deafenPeerIds[person.id]" stroke-linecap="round" stroke-width="2" d="M3 3l18 18"/>
                        </svg>
                    </button>
                    <button type="button" @click="openMuteOptions([person.id])"
                        class="inline-flex items-center justify-center w-7 h-7 rounded-md border border-white/10 text-slate-400 hover:text-white hover:bg-white/5 transition shrink-0"
                        :title="'Mute options for ' + participantLabel(person.id) + '…'">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6V4m0 16v-2m6-6h2M4 12h2m10.95-4.95l1.4-1.4M5.65 19.35l1.4-1.4m0-11.9l-1.4-1.4M18.35 19.35l-1.4-1.4"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </template>
            <p x-show="filteredForMute().length === 0" x-cloak
                class="px-3 py-2 text-xs text-slate-500">No other participants.</p>
        </div>

        <div class="px-3 pt-2 mt-1 border-t border-white/5">
            <button type="button" @click="openMuteOptions(muteSelectedIds)"
                :disabled="!muteSelectedIds.length"
                :class="muteSelectedIds.length
                    ? 'bg-brand-500 hover:bg-brand-600 text-white'
                    : 'bg-white/5 text-slate-500 cursor-not-allowed'"
                class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 8.5a6.5 6.5 0 1 1 13 0c0 6-6 6-6 10a3.5 3.5 0 1 1-7 0"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 8.5a2.5 2.5 0 0 0-5 0v1a2 2 0 1 1 0 4"/>
                </svg>
                <span>Mute options for <span x-text="muteSelectedIds.length"></span> selected…</span>
            </button>
        </div>
    </div>

    {{-- Mute options popup (notifications / calls / shrink) --}}
    <div x-show="showMuteOptions" x-cloak @keydown.escape.window="closeMuteOptions()"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60" @click="closeMuteOptions()"></div>
        <div class="relative w-full max-w-sm rounded-2xl border border-white/10 bg-surface-300 shadow-2xl p-5">
            <div class="flex items-center justify-between gap-3 mb-4">
                <p class="text-sm font-semibold text-white" x-text="muteOptionsTitle()"></p>
                <button type="button" @click="closeMuteOptions()"
                    class="text-xs text-slate-400 hover:text-white">Close</button>
            </div>

            <div class="space-y-4">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" x-model="muteDraft.notifications"
                        class="mt-0.5 rounded border-white/20 bg-surface-200 text-brand-500 focus:ring-brand-500/50">
                    <div class="min-w-0">
                        <p class="text-sm text-white">Mute notifications</p>
                        <p class="text-xs text-slate-500">No alert when they send a message in this chat.</p>
                    </div>
                </label>
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" x-model="muteDraft.calls"
                        class="mt-0.5 rounded border-white/20 bg-surface-200 text-brand-500 focus:ring-brand-500/50">
                    <div class="min-w-0">
                        <p class="text-sm text-white">Mute calls</p>
                        <p class="text-xs text-slate-500">Don't ring me when they start a call in this chat.</p>
                    </div>
                </label>
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" x-model="muteDraft.shrink"
                        class="mt-0.5 rounded border-white/20 bg-surface-200 text-brand-500 focus:ring-brand-500/50">
                    <div class="min-w-0">
                        <p class="text-sm text-white">Shrink messages</p>
                        <p class="text-xs text-slate-500">Show "Sent a message" and hide the content until you tap it.</p>
                    </div>
                </label>
            </div>

            <p x-show="muteSaveError" x-cloak class="text-xs text-red-400 mt-3" x-text="muteSaveError"></p>

            <div class="flex gap-2 mt-5">
                <button type="button" @click="closeMuteOptions()"
                    class="flex-1 px-3 py-2 rounded-lg border border-white/10 text-slate-300 hover:bg-white/5 text-sm transition">Cancel</button>
                <button type="button" @click="saveUserMutes()" :disabled="muteSaving"
                    class="flex-1 px-3 py-2 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold transition">
                    <span x-text="muteSaving ? 'Saving…' : 'Save'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
