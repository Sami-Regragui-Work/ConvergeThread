{{-- Server-rendered attachment grid for thread parent message --}}
@php
    $attachments = $attachments ?? [];
@endphp
@if(count($attachments) > 0)
    <div class="mb-3 flex flex-wrap gap-2">
        @foreach($attachments as $attachment)
            <a href="{{ $attachment['url'] }}" target="_blank" rel="noopener"
                class="block overflow-hidden rounded-xl border border-white/10 bg-black/20 hover:bg-black/30 transition shrink-0 {{ !empty($attachment['is_image']) ? 'w-36 h-36 sm:w-40 sm:h-40' : 'w-36 sm:w-40 min-h-28' }}">
                @if(!empty($attachment['is_image']))
                    <img src="{{ $attachment['preview_url'] ?? $attachment['url'] }}" alt="{{ $attachment['name'] }}"
                        class="h-full w-full min-h-24 min-w-24 max-h-40 max-w-40 object-cover">
                @else
                    <div class="flex flex-col items-center justify-center gap-1.5 px-3 py-4 text-center min-h-28">
                        @php $kind = $attachment['kind'] ?? 'file'; @endphp
                        <span
                            class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-[10px] font-bold uppercase tracking-wide
                            {{ match($kind) {
                                'pdf' => 'bg-red-500/20 text-red-300',
                                'ppt' => 'bg-orange-500/20 text-orange-300',
                                'doc' => 'bg-blue-500/20 text-blue-300',
                                'sheet' => 'bg-emerald-500/20 text-emerald-300',
                                default => 'bg-white/10 text-slate-300',
                            } }}">
                            {{ $attachment['ext'] ?? 'FILE' }}
                        </span>
                        <span class="text-[11px] leading-tight truncate w-full text-slate-300">{{ $attachment['name'] }}</span>
                    </div>
                @endif
            </a>
        @endforeach
    </div>
@endif
