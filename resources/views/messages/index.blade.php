@extends('layouts.app')
@section('title', 'Messages')
@section('fill-height', true)

@section('content')
    <div class="flex flex-1 min-h-0 flex-col px-4 py-3 sm:px-6"
        x-data="chatPanel({
            messages: @js($initialMessages),
            participants: @js($participants),
            mentionSuggestions: @js($mentionSuggestions),
            pollUrl: @js(route('messages.poll', [$chatType, $chatId])),
            mentionsUrl: @js(route('messages.mentions', [$chatType, $chatId])),
            markMentionUrlTemplate: @js(preg_replace('/\/\d+\//', '/__ID__/', route('messages.mentions.read', 0))),
            storeUrl: @js(route('messages.store', [$chatType, $chatId])),
            updateUrlTemplate: @js(preg_replace('/\/\d+$/', '/__ID__', route('messages.update', 0))),
            threadUrlTemplate: @js(preg_replace('/\/0(\/thread)$/', '/__ID__$1', route('messages.thread', 0))),
            currentUserId: @js(auth()->id()),
            canSend: @js(auth()->user()->can('create', [App\Models\Message::class, $chatable])),
            mentionIds: @js($mentionIds),
            showThreadLink: true,
            chatType: @js($chatType),
            chatId: @js((int) $chatId),
            cryptoShowUrl: @js(route('messages.crypto.show', [$chatType, $chatId])),
            cryptoSharesUrl: @js(route('messages.crypto.shares', [$chatType, $chatId])),
            cryptoPublicKeyUrl: @js(route('messages.crypto.public-key')),
            callSignalUrl: @js(route('messages.call.signal', [$chatType, $chatId])),
            currentUserName: @js(auth()->user()->displayLabel()),
        })"
        x-init="init()">

        <div class="flex items-center justify-between gap-3 pb-4 border-b border-white/5 mb-4 shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <div
                    class="w-8 h-8 rounded-lg bg-brand-500/10 text-brand-400 flex items-center justify-center text-xs font-bold shrink-0">
                    {{ strtoupper(substr($chatable->name ?? 'M', 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-white truncate">{{ $chatable->name ?? 'Messages' }}</p>
                    <p class="text-xs text-slate-500 capitalize">{{ $chatType }} · type &#64; to mention</p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <span x-show="e2eeReady" x-cloak
                    class="hidden sm:inline-flex items-center px-2 py-1 rounded-lg border border-emerald-500/30 bg-emerald-500/10 text-[10px] uppercase tracking-wide text-emerald-300"
                    title="Messages and attachments are end-to-end encrypted in this chat">E2EE</span>
                <span x-show="e2eeError" x-cloak class="hidden sm:inline text-[10px] text-amber-400" x-text="e2eeError"></span>
                <form method="POST" action="{{ route('messages.mute', [$chatType, $chatId]) }}">
                    @csrf
                    <button type="submit"
                        class="px-2.5 py-1.5 rounded-lg border text-xs transition {{ $chatMuted ? 'border-amber-500/40 text-amber-400 bg-amber-500/10' : 'border-white/10 text-slate-400 hover:bg-white/5 hover:text-white' }}"
                        title="{{ $chatMuted ? 'Unmute notifications' : 'Mute notifications' }}">
                        {{ $chatMuted ? 'Unmute' : 'Mute' }}
                    </button>
                </form>
                <button type="button" @click="openCall('voice')" title="Voice call"
                    class="p-2 rounded-lg border border-white/10 text-slate-400 hover:bg-white/5 hover:text-white transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                    </svg>
                </button>
                <button type="button" @click="openCall('video')" title="Video call"
                    class="p-2 rounded-lg border border-white/10 text-slate-400 hover:bg-white/5 hover:text-white transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                </button>
            </div>
        </div>

        <div class="flex-1 min-h-0 overflow-y-auto overflow-x-hidden pr-1" x-ref="messagesContainer">
                <div class="min-h-full flex flex-col">
                <template x-if="messages.length === 0">
                    <div class="flex flex-1 items-center justify-center py-16">
                        <p class="text-slate-500 text-sm">No messages yet. Say hello!</p>
                    </div>
                </template>

                <div class="space-y-3" x-show="messages.length > 0">
                <template x-for="message in messages" :key="message.id">
                    <div class="flex gap-3 rounded-xl transition"
                        :class="[
                            message.user_id === currentUserId ? 'flex-row-reverse' : '',
                            focusMessageId === message.id ? 'msg-focus-pulse bg-brand-500/10' : '',
                        ]"
                        :data-message-id="message.id">
                        <div
                            class="w-7 h-7 rounded-full bg-brand-500/10 text-brand-400 flex items-center justify-center text-xs font-semibold shrink-0 mt-1"
                            x-text="message.user_initial"></div>
                        <div class="max-w-[85%] sm:max-w-[70%] flex flex-col gap-1"
                            :class="message.user_id === currentUserId ? 'items-end' : 'items-start'">
                            <p class="text-xs font-medium"
                                :style="message.user_role_color ? 'color:' + message.user_role_color : ''"
                                :class="!message.user_role_color ? 'text-slate-500' : ''"
                                x-text="message.user_name"></p>
                            <div class="relative group/msg">
                                <template x-if="editingId === message.id">
                                    <div class="space-y-2">
                                        <input type="text" x-model="editDraft"
                                            class="w-full bg-surface-200 border border-white/10 text-white rounded-xl px-3 py-2 text-sm">
                                        <div class="flex gap-2 justify-end">
                                            <button type="button" @click="cancelEdit()"
                                                class="text-xs text-slate-400 hover:text-white px-2 py-1">Cancel</button>
                                            <button type="button" @click="saveEdit(message.id)"
                                                class="text-xs bg-brand-500 hover:bg-brand-600 text-white px-3 py-1 rounded-lg">Save</button>
                                        </div>
                                    </div>
                                </template>
                                <div x-show="editingId !== message.id" class="px-4 py-2.5 rounded-2xl text-sm wrap-break-word transition-shadow"
                                    :class="message.user_id === currentUserId ? 'bg-brand-500 text-white rounded-tr-sm' : 'bg-surface-100 text-slate-200 rounded-tl-sm'">
                                    @include('partials.chat-attachments')
                                    <template x-if="message.content_html">
                                        <div class="whitespace-pre-wrap" x-html="message.content_html"></div>
                                    </template>
                                    <template x-if="message.content && !message.content_html">
                                        <span x-text="message.content"></span>
                                    </template>
                                </div>
                                <button type="button" x-show="message.can_edit && editingId !== message.id" x-cloak
                                    @click="startEdit(message)"
                                    class="absolute -top-2 opacity-0 group-hover/msg:opacity-100 transition text-[10px] px-1.5 py-0.5 rounded bg-surface-300 border border-white/10 text-slate-400 hover:text-white"
                                    :class="message.user_id === currentUserId ? '-left-2' : '-right-2'">Edit</button>
                                <template x-if="showThreadLink">
                                    <a :href="threadUrl(message.id)"
                                        class="text-xs text-brand-400 hover:text-brand-300 mt-0.5 block">
                                        <span
                                            x-text="message.reply_count > 0 ? message.reply_count + ' ' + (message.reply_count === 1 ? 'reply' : 'replies') + ' →' : 'Open thread →'"></span>
                                    </a>
                                </template>
                            </div>
                            <span class="text-xs text-slate-600">
                                <span x-text="message.created_at"></span>
                                <span x-show="message.updated_at" x-cloak class="text-slate-700"> · edited <span x-text="message.updated_at"></span></span>
                            </span>
                        </div>
                    </div>
                </template>
                </div>
                </div>
            </div>

            <button type="button" x-show="mentionCount() > 0" x-cloak @click="jumpNextMention()"
                class="fixed bottom-24 right-6 z-50 flex items-center justify-center w-11 h-11 rounded-full bg-brand-500 text-white shadow-lg shadow-brand-500/30 hover:bg-brand-600 transition lg:right-10"
                title="Jump to mentions">
                <span class="text-lg font-bold leading-none">&#64;</span>
                <span
                    class="absolute -top-1 -right-1 min-w-5 h-5 px-1 rounded-full bg-red-500 text-[10px] font-bold flex items-center justify-center"
                    x-text="mentionCount()"></span>
            </button>

        <div class="pt-3 border-t border-white/5 shrink-0 relative">
            <template x-if="canSend">
                <form @submit.prevent="sendMessage" enctype="multipart/form-data" class="space-y-3">
                    <div x-show="showSelectedPicker" x-cloak
                        class="absolute bottom-full left-0 right-0 mb-2 rounded-xl border border-white/10 bg-surface-300 shadow-xl p-4 space-y-3 z-50 max-h-64 flex flex-col">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-medium text-white">Pick people for &#64;selected</p>
                            <button type="button" @click="showSelectedPicker = false"
                                class="text-xs text-slate-400 hover:text-white">Close</button>
                        </div>
                        <input type="text" x-model="selectedSearch" placeholder="Search…"
                            class="w-full bg-surface-200 border border-white/10 text-white rounded-lg px-3 py-2 text-sm">
                        <div class="flex gap-2 text-xs">
                            <button type="button" @click="selectAllFiltered()"
                                class="text-brand-400 hover:text-brand-300">Select filtered</button>
                            <button type="button" @click="unselectAllFiltered()"
                                class="text-slate-400 hover:text-white">Unselect filtered</button>
                        </div>
                        <div class="overflow-y-auto flex-1 space-y-1 min-h-0">
                            <template x-for="person in filteredForSelected()" :key="person.id">
                                <label class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-white/5 cursor-pointer">
                                    <input type="checkbox" :checked="selectedUserIds.includes(person.id)"
                                        @change="toggleSelected(person.id)"
                                        class="rounded border-white/20 bg-surface-200 text-brand-500 focus:ring-brand-500/50">
                                    <span class="text-sm text-slate-200" x-text="person.display_name"></span>
                                </label>
                            </template>
                        </div>
                        <button type="button" @click="confirmSelected()"
                            class="w-full bg-brand-500 hover:bg-brand-600 text-white py-2 rounded-lg text-sm font-semibold">
                            Confirm &#64;selected
                        </button>
                    </div>

                    <div x-show="selectedUserIds.length" x-cloak class="flex flex-wrap gap-1.5">
                        <template x-for="id in selectedUserIds" :key="'sel-' + id">
                            <span
                                class="inline-flex items-center text-[10px] uppercase tracking-wide text-brand-300/90 bg-brand-500/10 px-1.5 py-0.5 rounded"
                                x-text="participantLabel(id)"></span>
                        </template>
                    </div>

                    <div class="relative" @click.outside="closeMentionMenu()">
                        <div x-show="showMentionMenu" x-cloak
                            class="absolute bottom-full left-0 right-0 mb-2 max-h-48 overflow-y-auto rounded-xl border border-white/10 bg-surface-300 shadow-xl z-40">
                            <template x-for="(item, index) in filteredSuggestions()" :key="item.token">
                                <button type="button"
                                    @mousedown.prevent="pickSuggestion(item)"
                                    class="w-full text-left px-4 py-2.5 text-sm flex items-center justify-between gap-4"
                                    :class="activeMentionIndex === index ? 'bg-white/10' : 'hover:bg-white/5'">
                                    <span class="font-medium shrink-0"
                                        :style="item.color ? 'color:' + item.color : ''"
                                        :class="!item.color ? 'text-brand-300' : ''"
                                        x-text="item.token"></span>
                                    <span class="text-slate-500 truncate text-right" x-text="item.label"></span>
                                </button>
                            </template>
                        </div>

                        <div class="flex items-end gap-2">
                            <button type="button" @click="toggleMentionMenu()"
                                class="shrink-0 w-10 h-10 rounded-xl border border-white/10 bg-surface-200 text-brand-400 hover:bg-white/5 transition font-bold"
                                title="Mention someone">&#64;</button>
                            <input type="text" x-ref="draftInput" x-model="draft"
                                @input="onDraftInput()"
                                @keydown="onDraftKeydown($event)"
                                autocomplete="off"
                                placeholder="Message… @all, @role:Admin, @username"
                                :disabled="sending"
                                class="flex-1 bg-surface-200 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 transition placeholder-slate-500 disabled:opacity-50">
                            <label class="shrink-0 w-10 h-10 flex items-center justify-center rounded-xl border border-white/10 bg-surface-200 text-slate-400 hover:bg-white/5 hover:text-white cursor-pointer transition"
                                title="Attach files">
                                <input type="file" x-ref="fileInput" accept="*/*" multiple @change="onFilesChange($event)" class="sr-only">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                </svg>
                            </label>
                            <button type="submit" :disabled="sending || (!draft.trim() && !files.length)"
                                class="shrink-0 bg-brand-500 hover:bg-brand-600 disabled:opacity-50 text-white px-5 py-2.5 rounded-xl text-sm font-semibold transition">
                                <span x-text="sending ? '…' : 'Send'"></span>
                            </button>
                        </div>
                    </div>
                    <div x-show="filePreviews.length" x-cloak class="flex flex-wrap gap-2">
                        <template x-for="(preview, index) in filePreviews" :key="preview.key || index">
                            <div class="relative group/file rounded-xl border border-white/10 bg-surface-200 overflow-hidden w-20 h-20 shrink-0">
                                <img x-show="preview.isImage" :src="preview.url" :alt="preview.name" class="h-full w-full object-cover min-w-16 min-h-16 max-w-20 max-h-20">
                                <div x-show="preview.isVideo" class="relative h-full w-full bg-black">
                                    <video :src="preview.url" class="h-full w-full object-cover" muted playsinline></video>
                                    <span class="absolute inset-0 flex items-center justify-center text-white text-xs">▶</span>
                                </div>
                                <div x-show="!preview.isImage && !preview.isVideo" class="h-full w-full flex flex-col items-center justify-center px-1.5 text-center gap-0.5">
                                    <span class="text-[9px] font-bold uppercase text-slate-400" x-text="preview.ext"></span>
                                    <span class="text-[9px] text-slate-500 truncate w-full" x-text="preview.name"></span>
                                    <span class="text-[9px] text-slate-600" x-text="preview.sizeLabel"></span>
                                </div>
                                <button type="button" @click="removeFile(index)"
                                    class="absolute top-1 right-1 w-5 h-5 rounded-full bg-black/60 text-white text-xs opacity-0 group-hover/file:opacity-100 transition">×</button>
                            </div>
                        </template>
                    </div>
                    <p x-show="sendError" x-cloak class="text-xs text-red-400" x-text="sendError"></p>
                </form>
            </template>
            <template x-if="!canSend">
                <p class="text-sm text-slate-500 text-center">You don't have permission to send messages here.</p>
            </template>
        </div>

        {{-- Incoming call --}}
        <div x-show="incomingCall" x-cloak class="fixed inset-0 z-200 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60"></div>
            <div class="relative bg-surface-200 border border-white/10 rounded-2xl p-6 max-w-sm w-full shadow-2xl text-center space-y-4">
                <div class="w-16 h-16 mx-auto rounded-full bg-emerald-500/10 text-emerald-300 flex items-center justify-center text-xl font-bold"
                    x-text="(incomingCall?.from_user_name || '?').slice(0, 1).toUpperCase()"></div>
                <div>
                    <p class="text-white font-semibold" x-text="(incomingCall?.from_user_name || 'Someone') + ' is calling'"></p>
                    <p class="text-sm text-slate-500 mt-1"
                        x-text="(incomingCall?.call_type === 'video' ? 'Video' : 'Voice') + ' call · {{ $chatable->name ?? 'chat' }}'"></p>
                </div>
                <div class="flex gap-3">
                    <button type="button" @click="rejectIncoming()"
                        class="flex-1 py-2.5 rounded-xl bg-red-500/20 text-red-400 hover:bg-red-500/30 text-sm font-semibold transition">Decline</button>
                    <button type="button" @click="acceptIncoming()"
                        class="flex-1 py-2.5 rounded-xl bg-emerald-500/20 text-emerald-300 hover:bg-emerald-500/30 text-sm font-semibold transition">Accept</button>
                </div>
            </div>
        </div>

        {{-- Active / outgoing call --}}
        <div x-show="showCallModal && callState !== 'idle'" x-cloak class="fixed inset-0 z-200 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/70"></div>
            <div class="relative bg-surface-200 border border-white/10 rounded-2xl p-5 sm:p-6 max-w-3xl w-full shadow-2xl space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-white font-semibold"
                            x-text="callState === 'outgoing' ? ('Calling…') : (callType === 'video' ? 'Video call' : 'Voice call')"></p>
                        <p class="text-xs text-slate-500">{{ $chatable->name ?? 'chat' }}</p>
                    </div>
                    <span class="text-[10px] uppercase tracking-wide px-2 py-1 rounded-lg border border-white/10 text-slate-400"
                        x-text="callState"></span>
                </div>

                <p x-show="callError" x-cloak class="text-sm text-amber-300" x-text="callError"></p>

                <div class="grid gap-3"
                    :class="callType === 'video' ? 'sm:grid-cols-2' : ''">
                    <div class="relative rounded-xl overflow-hidden border border-white/10 bg-black min-h-40 flex items-center justify-center">
                        <video x-ref="localVideo" x-show="callType === 'video'" autoplay muted playsinline
                            class="absolute inset-0 h-full w-full object-cover"></video>
                        <div x-show="callType !== 'video' || localVideoOff" class="relative z-10 text-center p-4">
                            <div class="w-14 h-14 mx-auto rounded-full bg-brand-500/20 text-brand-300 flex items-center justify-center text-lg font-bold"
                                x-text="(currentUserName || 'Y').slice(0, 1).toUpperCase()"></div>
                            <p class="text-xs text-slate-400 mt-2">You <span x-show="localMuted">(muted)</span></p>
                        </div>
                        <span class="absolute bottom-2 left-2 text-[10px] px-1.5 py-0.5 rounded bg-black/60 text-white">You</span>
                    </div>

                    <template x-for="peer in peers" :key="peer.userId">
                        <div class="relative rounded-xl overflow-hidden border border-white/10 bg-black min-h-40 flex items-center justify-center">
                            <video x-show="callType === 'video'" :id="'remote-video-' + peer.userId" autoplay playsinline
                                class="absolute inset-0 h-full w-full object-cover"
                                x-effect="if ($el && peer.stream) $el.srcObject = peer.stream"></video>
                            <audio x-show="callType === 'voice'" :id="'remote-audio-' + peer.userId" autoplay
                                x-effect="if ($el && peer.stream) $el.srcObject = peer.stream"></audio>
                            <div x-show="callType !== 'video' || !peer.stream" class="relative z-10 text-center p-4">
                                <div class="w-14 h-14 mx-auto rounded-full bg-brand-500/20 text-brand-300 flex items-center justify-center text-lg font-bold"
                                    x-text="(peer.name || '?').slice(0, 1).toUpperCase()"></div>
                                <p class="text-xs text-slate-400 mt-2" x-text="peer.name"></p>
                            </div>
                            <span class="absolute bottom-2 left-2 text-[10px] px-1.5 py-0.5 rounded bg-black/60 text-white" x-text="peer.name"></span>
                        </div>
                    </template>

                    <div x-show="callState === 'outgoing' && peers.length === 0" class="rounded-xl border border-dashed border-white/10 min-h-40 flex items-center justify-center text-sm text-slate-500">
                        Waiting for someone to join…
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-center gap-2 pt-1">
                    <button type="button" @click="toggleMute()"
                        class="px-3 py-2 rounded-xl border text-sm transition"
                        :class="localMuted ? 'border-amber-500/40 bg-amber-500/10 text-amber-300' : 'border-white/10 text-slate-300 hover:bg-white/5'">
                        <span x-text="localMuted ? 'Unmute' : 'Mute'"></span>
                    </button>
                    <button type="button" x-show="callType === 'video'" @click="toggleVideo()"
                        class="px-3 py-2 rounded-xl border text-sm transition"
                        :class="localVideoOff ? 'border-amber-500/40 bg-amber-500/10 text-amber-300' : 'border-white/10 text-slate-300 hover:bg-white/5'">
                        <span x-text="localVideoOff ? 'Camera on' : 'Camera off'"></span>
                    </button>
                    <button type="button" @click="endCall()"
                        class="px-4 py-2 rounded-xl bg-red-500/20 text-red-400 hover:bg-red-500/30 text-sm font-semibold transition">
                        End call
                    </button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        @include('partials.chat-crypto')
        @include('partials.chat-panel-script')
    @endpush
@endsection
