{{-- Shared notification body. Expects $data (the notification's decoded JSON payload). --}}
@php
    $data ??= [];
@endphp
@if(($data['type'] ?? '') === 'chat_message')
    <p class="text-sm text-white">
        Messages from <span class="font-semibold">{{ $data['chat_label'] ?? 'chat' }}</span>
        @if(($data['stack_count'] ?? 1) > 1)
            <span class="ml-2 inline-flex min-w-5 h-5 px-1.5 rounded-full bg-brand-500 text-[11px] font-bold items-center justify-center">{{ $data['stack_count'] }}</span>
        @endif
    </p>
    <p class="text-xs text-slate-500 mt-1">{{ $data['preview'] ?? '' }}</p>
@elseif(($data['type'] ?? '') === 'added_to_group')
    <p class="text-sm text-white">Added to group <span class="font-semibold">{{ $data['group_name'] ?? '' }}</span></p>
    <p class="text-xs text-slate-500 mt-1">By {{ $data['added_by'] ?? 'someone' }}</p>
@elseif(($data['type'] ?? '') === 'role_changed')
    <p class="text-sm text-white">Your role is now <span class="font-semibold">{{ $data['role_name'] ?? '' }}</span></p>
@elseif(($data['type'] ?? '') === 'group_permissions')
    <p class="text-sm text-white">{{ $data['summary'] ?? 'New permissions' }}</p>
@elseif(($data['type'] ?? '') === 'merge_session')
    <p class="text-sm text-white">Merge session started for <span class="font-semibold">{{ $data['group_name'] ?? '' }}</span></p>
@elseif(($data['type'] ?? '') === 'incoming_call')
    <p class="text-sm text-white">
        <span class="font-semibold">{{ $data['author_name'] ?? 'Someone' }}</span>
        started a {{ ($data['call_type'] ?? '') === 'video' ? 'video' : 'voice' }} call
        in <span class="font-semibold">{{ $data['chat_label'] ?? 'chat' }}</span>
        @if(($data['stack_count'] ?? 1) > 1)
            <span class="ml-2 inline-flex min-w-5 h-5 px-1.5 rounded-full bg-brand-500 text-[11px] font-bold items-center justify-center">{{ $data['stack_count'] }}</span>
        @endif
    </p>
    <p class="text-xs text-slate-500 mt-1">{{ $data['preview'] ?? 'Tap to join' }}</p>
@elseif(($data['type'] ?? '') === 'registration_pending')
    <p class="text-sm text-white">
        Registration request from <span class="font-semibold">{{ $data['email'] ?? '' }}</span>
    </p>
    <p class="text-xs text-slate-500 mt-1">
        @if($data['tenant_name'] ?? null)
            wants to join {{ $data['tenant_name'] }}
        @else
            wants to join unknown workspace <span class="text-slate-400">{{ $data['tenant_slug'] ?? '—' }}</span>
        @endif
    </p>
@else
    <p class="text-sm text-white">
        <span class="font-semibold">{{ $data['author_name'] ?? 'Someone' }}</span> mentioned you
    </p>
    <p class="text-xs text-slate-500 mt-1 line-clamp-2">{{ $data['preview'] ?? '' }}</p>
@endif
