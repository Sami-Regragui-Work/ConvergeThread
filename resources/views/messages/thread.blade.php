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
        })"
        x-init="init()">

        <div class="flex items-center justify-between gap-3 pb-4 border-b border-white/5 mb-4 shrink-0">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-white">Thread</p>
                <p class="text-xs text-slate-500">Replies · type &#64; to mention</p>
            </div>
            <form method="POST" action="{{ route('messages.thread.mute', $message) }}">
                @csrf
                <button type="submit"
                    class="px-2.5 py-1.5 rounded-lg border text-xs transition {{ $threadMuted ? 'border-amber-500/40 text-amber-400 bg-amber-500/10' : 'border-white/10 text-slate-400 hover:bg-white/5 hover:text-white' }}"
                    title="{{ $threadMuted ? 'Unmute thread notifications' : 'Mute thread notifications' }}">
                    {{ $threadMuted ? 'Unmute' : 'Mute' }}
                </button>
            </form>
        </div>

        <div class="bg-surface-200 border border-white/5 rounded-2xl p-5 mb-4 shrink-0">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-7 h-7 rounded-full bg-brand-500/10 text-brand-400 flex items-center justify-center text-xs font-semibold">
                    {{ $parentPayload['user_initial'] }}
                </div>
                <span class="text-sm text-slate-300">{{ $parentPayload['user_name'] }}</span>
                <span class="text-xs text-slate-600">{{ $message->created_at->diffForHumans() }}</span>
            </div>

            @include('partials.message-attachments', ['attachments' => $parentPayload['attachments'] ?? []])

            @if($parentPayload['content_html'])
                <div class="text-sm text-slate-200 whitespace-pre-wrap">{!! $parentPayload['content_html'] !!}</div>
            @elseif($parentPayload['content'])
                <p class="text-sm text-slate-200 whitespace-pre-wrap">{{ $parentPayload['content'] }}</p>
            @endif
        </div>

        <div class="flex-1 min-h-0 overflow-y-auto overflow-x-hidden pr-1" x-ref="messagesContainer">
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
                                <template x-if="editingId === message.id">
                                    <div class="space-y-2">
                                        <input type="text" x-model="editDraft" class="w-full bg-surface-200 border border-white/10 text-white rounded-xl px-3 py-2 text-sm">
                                        <div class="flex gap-2 justify-end">
                                            <button type="button" @click="cancelEdit()" class="text-xs text-slate-400 hover:text-white px-2 py-1">Cancel</button>
                                            <button type="button" @click="saveEdit(message.id)" class="text-xs bg-brand-500 text-white px-3 py-1 rounded-lg">Save</button>
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
                                <button type="button" x-show="message.can_edit && editingId !== message.id" x-cloak @click="startEdit(message)"
                                    class="absolute -top-2 opacity-0 group-hover/msg:opacity-100 transition text-[10px] px-1.5 py-0.5 rounded bg-surface-300 border border-white/10 text-slate-400 hover:text-white"
                                    :class="message.user_id === currentUserId ? '-left-2' : '-right-2'">Edit</button>
                            </div>
                            <span class="text-xs text-slate-600"><span x-text="message.created_at"></span><span x-show="message.updated_at" x-cloak> · edited</span></span>
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
                                    <span class="font-medium shrink-0" :style="item.color ? 'color:' + item.color : ''" :class="!item.color ? 'text-brand-300' : ''" x-text="item.token"></span>
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
                                placeholder="Reply in thread… @all, @role:Admin"
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
                                <span x-text="sending ? '…' : 'Reply'"></span>
                            </button>
                        </div>
                    </div>
                    <div x-show="filePreviews.length" x-cloak class="flex flex-wrap gap-2">
                        <template x-for="(preview, index) in filePreviews" :key="preview.key || index">
                            <div class="relative group/file rounded-xl border border-white/10 bg-surface-200 overflow-hidden w-20 h-20 shrink-0">
                                <img x-show="preview.isImage" :src="preview.url" class="h-full w-full object-cover min-w-16 min-h-16 max-w-20 max-h-20">
                                <div x-show="preview.isVideo" class="relative h-full w-full bg-black">
                                    <video :src="preview.url" class="h-full w-full object-cover" muted playsinline></video>
                                    <span class="absolute inset-0 flex items-center justify-center text-white text-xs">▶</span>
                                </div>
                                <div x-show="!preview.isImage && !preview.isVideo" class="h-full w-full flex flex-col items-center justify-center px-1.5 text-center gap-0.5">
                                    <span class="text-[9px] font-bold uppercase text-slate-400" x-text="preview.ext"></span>
                                    <span class="text-[9px] text-slate-500 truncate w-full" x-text="preview.name"></span>
                                    <span class="text-[9px] text-slate-600" x-text="preview.sizeLabel"></span>
                                </div>
                                <button type="button" @click="removeFile(index)" class="absolute top-1 right-1 w-5 h-5 rounded-full bg-black/60 text-white text-xs">×</button>
                            </div>
                        </template>
                    </div>
                    <p x-show="sendError" x-cloak class="text-xs text-red-400" x-text="sendError"></p>
                </form>
            </template>
            <template x-if="!canSend">
                <p class="text-sm text-slate-500 text-center">You don't have permission to reply in this thread.</p>
            </template>
        </div>
    </div>

    @push('scripts')
        @include('partials.chat-crypto')
        @include('partials.chat-panel-script')
    @endpush
@endsection
