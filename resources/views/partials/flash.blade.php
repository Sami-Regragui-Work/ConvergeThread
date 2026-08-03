@php
    $flashLinks = collect(session('flash_links', []));

    if (session('accept_url')) {
        $flashLinks->push([
            'label' => 'Invitation link',
            'url' => session('accept_url'),
        ]);
    }

    $flashLinks = $flashLinks->filter(fn ($link) => !empty($link['url']))->values();
    $hasLinks = $flashLinks->isNotEmpty();
    $flashMessage = session('success') ?? session('info') ?? session('error');
    $flashType = session('success') ? 'success' : (session('info') ? 'info' : (session('error') ? 'error' : null));
@endphp

@if ($flashMessage || $hasLinks)
    @if ($hasLinks)
        <div x-data="{ show: true }" x-show="show" x-cloak
            class="border-b border-white/5 bg-surface-300/95 backdrop-blur shrink-0">
            <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-4 sm:px-6 lg:px-8">
                <div @class([
                    'rounded-2xl border px-4 py-4 shadow-xl',
                    'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' => $flashType === 'success',
                    'border-brand-500/30 bg-brand-500/10 text-brand-300' => $flashType === 'info',
                    'border-red-500/30 bg-red-500/10 text-red-300' => $flashType === 'error',
                    'border-white/10 bg-surface-200 text-slate-300' => !$flashType,
                ])>
                    <div class="flex items-start gap-3">
                        <div class="min-w-0 flex-1 space-y-1">
                            @if ($flashMessage)
                                <p class="text-sm font-medium">{{ $flashMessage }}</p>
                            @endif
                            <p class="text-xs opacity-80">
                                Email is not configured — copy and share this link manually.
                            </p>
                        </div>
                        <button type="button" @click="show = false"
                            class="shrink-0 rounded-lg p-1 opacity-60 transition hover:bg-white/5 hover:opacity-100">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="mt-4 space-y-3">
                        @foreach ($flashLinks as $link)
                            @include('partials.copyable-link', [
                                'url' => $link['url'],
                                'label' => $link['label'] ?? 'Link',
                                'autoCopy' => $loop->first,
                            ])
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @elseif ($flashMessage)
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show"
            x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-[-1rem]"
            x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="fixed top-4 right-4 z-50 max-w-sm w-full" x-cloak>
            @if (session('success'))
                <div
                    class="flex items-start gap-3 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 px-4 py-3 rounded-xl shadow-xl backdrop-blur">
                    <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <p class="text-sm">{{ session('success') }}</p>
                    <button @click="show = false" class="ml-auto text-emerald-400/60 hover:text-emerald-400 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            @endif
            @if (session('error'))
                <div
                    class="flex items-start gap-3 bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded-xl shadow-xl backdrop-blur">
                    <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-sm">{{ session('error') }}</p>
                    <button @click="show = false" class="ml-auto text-red-400/60 hover:text-red-400 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            @endif
            @if (session('info'))
                <div
                    class="flex items-start gap-3 bg-brand-500/10 border border-brand-500/30 text-brand-400 px-4 py-3 rounded-xl shadow-xl backdrop-blur">
                    <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-sm">{{ session('info') }}</p>
                    <button @click="show = false" class="ml-auto text-brand-400/60 hover:text-brand-400 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            @endif
        </div>
    @endif
@endif

@once
    @push('scripts')
        <script>
            function copyableLink(url, autoCopy = false) {
                return {
                    url,
                    copied: false,
                    autoCopied: false,

                    init() {
                        if (autoCopy) {
                            this.copy(true);
                        }
                    },

                    async copy(silent = false) {
                        const copied = await this.writeToClipboard(this.url);

                        if (copied) {
                            this.copied = true;

                            if (silent) {
                                this.autoCopied = true;
                            } else {
                                setTimeout(() => {
                                    this.copied = false;
                                }, 2000);
                            }
                        }
                    },

                    async writeToClipboard(text) {
                        if (navigator.clipboard?.writeText) {
                            try {
                                await navigator.clipboard.writeText(text);
                                return true;
                            } catch (error) {
                                //
                            }
                        }

                        const textarea = document.createElement('textarea');
                        textarea.value = text;
                        textarea.setAttribute('readonly', '');
                        textarea.style.position = 'absolute';
                        textarea.style.left = '-9999px';
                        document.body.appendChild(textarea);
                        textarea.select();

                        try {
                            return document.execCommand('copy');
                        } catch (error) {
                            return false;
                        } finally {
                            document.body.removeChild(textarea);
                        }
                    },
                };
            }
        </script>
    @endpush
@endonce
