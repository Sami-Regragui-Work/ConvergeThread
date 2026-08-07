@extends('layouts.app')
@section('title', 'Thread')
@section('fill-height', true)

@section('content')
    <div class="flex flex-1 min-h-0 flex-col px-4 py-3 sm:px-6"
        x-data="chatPanel({
            messages: @js($initialReplies),
            participants: @js($participants),
            mentionSuggestions: @js($mentionSuggestions),
            pollUrl: @js(route('messages.poll', [$chatType, $message->chatable_id]) . '?parent_id=' . $message->id),
            mentionsUrl: @js(route('messages.mentions', [$chatType, $message->chatable_id])),
            markMentionUrlTemplate: @js(preg_replace('/\/\d+\//', '/__ID__/', route('messages.mentions.read', 0))),
            storeUrl: @js(route('messages.store', [$chatType, $message->chatable_id])),
            updateUrlTemplate: @js(preg_replace('/\/\d+$/', '/__ID__', route('messages.update', 0))),
            destroyUrlTemplate: @js(preg_replace('/\/\d+$/', '/__ID__', route('messages.destroy', 0))),
            parentId: @js($message->id),
            currentUserId: @js(auth()->id()),
            canSend: @js(auth()->user()->can('create', [App\Models\Message::class, $message->chatable])),
            mentionIds: @js($mentionIds),
            showThreadLink: false,
            chatType: @js($chatType),
            chatId: @js((int) $message->chatable_id),
            cryptoShowUrl: @js(route('messages.crypto.show', [$chatType, $message->chatable_id])),
            cryptoSharesUrl: @js(route('messages.crypto.shares', [$chatType, $message->chatable_id])),
            cryptoPublicKeyUrl: @js(route('messages.crypto.public-key')),
            callSignalUrl: @js(route('messages.call.signal', [$chatType, $message->chatable_id])),
            callActiveUrl: @js(route('messages.call.active', [$chatType, $message->chatable_id])),
            sfuTokenUrl: @js(route('messages.call.sfu-token', [$chatType, $message->chatable_id])),
            sfuEnabled: @js((bool) config('webrtc.sfu.enabled')),
            sfuUrl: @js(config('webrtc.sfu.url')),
            preferredMediaMode: @js(
                config('webrtc.sfu.enabled') && (
                    config('webrtc.sfu.force_all') || in_array($chatType, ['group', 'merge'], true)
                ) ? 'sfu' : 'mesh'
            ),
            parentMessage: @js($parentPayload),
            currentUserName: @js(auth()->user()->displayLabel()),
            activeCall: @js($activeCall),
            iceServers: @js(config('webrtc.ice_servers')),
        })"
        x-init="init()">

        <div class="flex items-center justify-between gap-3 pb-4 border-b border-white/5 mb-4 shrink-0">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-white">Thread</p>
                <p class="text-xs text-slate-500">Replies · type &#64; to mention</p>
            </div>
            <div class="flex items-center gap-2 shrink-0 flex-wrap justify-end">
                @include('partials.chat-call-ui', ['mode' => 'buttons'])
                <form method="POST" action="{{ route('messages.thread.mute', $message) }}">
                    @csrf
                    <button type="submit"
                        class="px-2.5 py-1.5 rounded-lg border text-xs transition {{ $threadMuted ? 'border-amber-500/40 text-amber-400 bg-amber-500/10' : 'border-white/10 text-slate-400 hover:bg-white/5 hover:text-white' }}"
                        title="{{ $threadMuted ? 'Unmute thread notifications' : 'Mute thread notifications' }}">
                        {{ $threadMuted ? 'Unmute' : 'Mute' }}
                    </button>
                </form>
            </div>
        </div>

        <div x-show="activeCall && callState === 'idle'" x-cloak
            class="mb-4 shrink-0 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 flex flex-col sm:flex-row sm:items-center gap-3">
            <div class="min-w-0 flex-1">
                <p class="text-sm text-emerald-200 font-medium"
                    x-text="(activeCall?.from_user_name || 'Someone') + ' started a ' + (activeCall?.call_type === 'video' ? 'video' : 'voice') + ' call'"></p>
                <p class="text-xs text-emerald-200/70 mt-0.5"
                    x-text="(activeCall?.participant_count ? (activeCall.participant_count + ' in call · ') : '') + 'Join to connect with people already in the call.'"></p>
            </div>
            <button type="button" @click="joinActiveCall()"
                class="shrink-0 px-3 py-1.5 rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-200 text-xs font-semibold transition">
                Join call
            </button>
        </div>

        <div class="bg-surface-200 border border-white/5 rounded-2xl p-5 mb-4 shrink-0"
            x-show="parentMessage && !hasVideoStage()" x-cloak>
            <div class="flex items-center gap-2 mb-3">
                <div class="w-7 h-7 rounded-full bg-brand-500/10 text-brand-400 flex items-center justify-center text-xs font-semibold"
                    x-text="parentMessage?.user_initial"></div>
                <span class="text-sm text-slate-300" x-text="parentMessage?.user_name"></span>
                <span class="text-xs text-slate-600">
                    <span x-text="formatRelativeTime(parentMessage?.created_at_iso, parentMessage?.created_at)"></span>
                    <span x-show="parentMessage?.is_edited || parentMessage?.updated_at" x-cloak> · edited</span>
                </span>
                <button type="button" x-show="parentMessage?.can_delete && !parentMessage?.is_deleted" x-cloak
                    @click="askDelete(parentMessage)"
                    class="ml-auto text-[10px] px-1.5 py-0.5 rounded bg-surface-300 border border-white/10 text-red-400 hover:text-red-300">
                    Delete
                </button>
            </div>

            <template x-if="parentMessage?.is_deleted">
                <p class="text-sm italic text-slate-400" x-text="'Deleted by ' + (parentMessage.deleted_by_name || 'someone')"></p>
            </template>
            <template x-if="parentMessage && !parentMessage.is_deleted">
                <div>
                    <template x-for="message in [parentMessage]" :key="'parent-' + parentMessage.id">
                        <div>
                            @include('partials.chat-attachments')
                        </div>
                    </template>
                    <template x-if="parentMessage.content_html">
                        <div class="text-sm text-slate-200" :class="parentMessage.is_markdown ? 'ct-md-body' : 'whitespace-pre-wrap'" x-html="parentMessage.content_html"></div>
                    </template>
                    <template x-if="parentMessage.content && !parentMessage.content_html">
                        <p class="text-sm text-slate-200 whitespace-pre-wrap" x-text="parentMessage.content"></p>
                    </template>
                </div>
            </template>
        </div>

        <div class="flex-1 min-h-0 overflow-y-auto overflow-x-hidden pr-1" x-ref="messagesContainer"
            x-show="!hasVideoStage()">
                <div class="min-h-full flex flex-col">
                <template x-if="messages.length === 0">
                    <div class="flex flex-1 items-center justify-center py-16">
                        <p class="text-sm text-slate-500">No replies yet. Start the discussion.</p>
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
                        <div class="w-7 h-7 rounded-full bg-brand-500/10 text-brand-400 flex items-center justify-center text-xs font-semibold shrink-0"
                            x-text="message.user_initial"></div>
                        <div class="max-w-[85%] sm:max-w-[70%] flex flex-col gap-1"
                            :class="message.user_id === currentUserId ? 'items-end' : 'items-start'">
                            <p class="text-xs font-medium"
                                :style="message.user_role_color ? 'color:' + message.user_role_color : ''"
                                :class="!message.user_role_color ? 'text-slate-500' : ''"
                                x-text="message.user_name"></p>
                            <div class="relative group/msg">
                                <template x-if="editingId === message.id && !message.is_deleted">
                                    @include('partials.chat-message-edit')
                                </template>
                            <div x-show="editingId !== message.id && message.is_deleted" x-cloak
                                class="px-4 py-2.5 rounded-2xl text-sm italic border border-white/10 bg-surface-200/80 text-slate-400">
                                <span x-text="'Deleted by ' + (message.deleted_by_name || 'someone')"></span>
                            </div>
                            <div x-show="editingId !== message.id && !message.is_deleted" class="px-4 py-2.5 rounded-2xl text-sm wrap-break-word transition-shadow"
                                :class="message.user_id === currentUserId ? 'bg-brand-500 text-white rounded-tr-sm' : 'bg-surface-100 text-slate-200 rounded-tl-sm'">
                                @include('partials.chat-attachments')
                                <template x-if="message.content_html">
                                    <div :class="message.is_markdown ? 'ct-md-body' : 'whitespace-pre-wrap'" x-html="message.content_html"></div>
                                </template>
                                <template x-if="message.content && !message.content_html">
                                    <span class="whitespace-pre-wrap" x-text="message.content"></span>
                                </template>
                            </div>
                                <div class="absolute -top-2 flex gap-1 opacity-0 group-hover/msg:opacity-100 transition"
                                    :class="message.user_id === currentUserId ? '-left-2' : '-right-2'"
                                    x-show="editingId !== message.id && !message.is_deleted && (message.can_edit || message.can_delete)" x-cloak>
                                    <button type="button" x-show="message.can_edit" @click="startEdit(message)"
                                        class="text-[10px] px-1.5 py-0.5 rounded bg-surface-300 border border-white/10 text-slate-400 hover:text-white">Edit</button>
                                    <button type="button" x-show="message.can_delete" @click="askDelete(message)"
                                        class="text-[10px] px-1.5 py-0.5 rounded bg-surface-300 border border-white/10 text-red-400 hover:text-red-300">Delete</button>
                                </div>
                            </div>
                            <span class="text-xs text-slate-600"><span x-text="formatRelativeTime(message.created_at_iso, message.created_at)"></span><span x-show="message.is_edited || message.updated_at" x-cloak> · edited</span></span>
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

        <div class="pt-3 border-t border-white/5 relative min-h-0 flex flex-col"
            :class="hasVideoStage() ? 'flex-1 min-h-0' : 'shrink-0'"
            :style="hasVideoStage() ? 'flex: 1 1 0%' : null">
            <form x-show="canSend" x-cloak @submit.prevent="sendMessage" enctype="multipart/form-data"
                class="flex flex-col gap-3 min-h-0 h-full"
                @paste="onComposerPaste($event)"
                @dragover.prevent="dragOverComposer = true"
                @dragleave.prevent="dragOverComposer = false"
                @drop.prevent="onComposerDrop($event)"
                :class="[
                    hasVideoStage() ? 'flex-1' : '',
                    dragOverComposer ? 'rounded-xl ring-2 ring-brand-500/40 p-1' : '',
                ]"
                :style="hasVideoStage() ? 'flex: 1 1 0%; min-height: 0' : null">
                    <div x-show="showSelectedPicker" x-cloak
                        class="absolute bottom-full left-0 right-0 mb-2 rounded-xl border border-white/10 bg-surface-300 shadow-xl p-4 space-y-3 z-50 max-h-64 flex flex-col">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-medium text-white">Pick people for &#64;selected</p>
                            <button type="button" @click="showSelectedPicker = false"
                                class="text-xs text-slate-400 hover:text-white">Close</button>
                        </div>
                        <input type="text" x-model="selectedSearch" placeholder="Search…"
                            class="w-full bg-surface-200 border border-white/10 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition">
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

                    <div x-show="selectedUserIds.length" x-cloak class="flex flex-wrap gap-1.5 shrink-0">
                        <template x-for="id in selectedUserIds" :key="'sel-' + id">
                            <span
                                class="inline-flex items-center text-[10px] uppercase tracking-wide text-brand-300/90 bg-brand-500/10 px-1.5 py-0.5 rounded"
                                x-text="participantLabel(id)"></span>
                        </template>
                    </div>

                    @include('partials.chat-composer-controls', [
                        'composerPlaceholder' => 'Reply in thread… paste or drop files',
                        'composerSubmitLabel' => 'Reply',
                    ])
            </form>
            <p x-show="!canSend" x-cloak class="text-sm text-slate-500 text-center">You don't have permission to reply in this thread.</p>
        </div>

        @include('partials.chat-call-ui', ['chatLabel' => 'Thread', 'mode' => 'modals'])
        @include('partials.chat-media-viewer')
    </div>

    @push('scripts')
        @include('partials.chat-panel-script')
    @endpush
@endsection
