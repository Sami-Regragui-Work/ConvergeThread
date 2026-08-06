{{-- Server-rendered attachment grid for thread parent message --}}
@php
    $attachments = $attachments ?? [];
@endphp
@if(count($attachments) > 0)
    <div class="mb-3 flex flex-wrap gap-2">
        @foreach($attachments as $attachment)
            @php
                $isImage = !empty($attachment['is_image']);
                $isVideo = !empty($attachment['is_video']);
                $kind = $attachment['kind'] ?? 'file';
            @endphp
            <div class="overflow-hidden rounded-xl border border-white/10 bg-black/20 shrink-0 {{ ($isImage || $isVideo) ? 'w-36 h-36 sm:w-40 sm:h-40' : 'w-36 sm:w-40 min-h-28' }}">
                @if($isImage)
                    <a href="{{ $attachment['url'] }}" target="_blank" rel="noopener" class="block h-full w-full">
                        <img src="{{ $attachment['preview_url'] ?? $attachment['url'] }}" alt="{{ $attachment['name'] }}"
                            class="h-full w-full min-h-24 min-w-24 max-h-40 max-w-40 object-cover">
                    </a>
                @elseif($isVideo)
                    <div class="relative h-full w-full bg-black">
                        <video src="{{ $attachment['preview_url'] ?? $attachment['url'] }}" class="h-full w-full object-cover" muted playsinline preload="metadata"></video>
                        <a href="{{ $attachment['url'] }}" target="_blank" rel="noopener"
                            class="absolute inset-0 flex items-center justify-center bg-black/35 hover:bg-black/45 transition">
                            <span class="w-10 h-10 rounded-full bg-white/90 text-surface-400 flex items-center justify-center text-sm font-bold">▶</span>
                        </a>
                        @if(!empty($attachment['size_label']))
                            <span class="absolute bottom-1 right-1 text-[10px] px-1.5 py-0.5 rounded bg-black/70 text-white">{{ $attachment['size_label'] }}</span>
                        @endif
                    </div>
                @else
                    <a href="{{ $attachment['url'] }}" target="_blank" rel="noopener"
                        class="flex flex-col items-center justify-center gap-1.5 px-3 py-4 text-center min-h-28 h-full hover:bg-black/30 transition">
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
                        @if(!empty($attachment['size_label']))
                            <span class="text-[10px] text-slate-500">{{ $attachment['size_label'] }}</span>
                        @endif
                    </a>
                @endif
            </div>
        @endforeach
    </div>
@endif
