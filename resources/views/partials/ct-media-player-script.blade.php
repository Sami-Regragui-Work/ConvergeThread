{{-- ctMediaPlayer Alpine factory (include once near chat scripts) --}}
<script>
    window.ctMediaPlayer = function (config = {}) {
        return {
            src: config.src || '',
            kind: config.kind === 'video' ? 'video' : 'audio',
            compact: !!config.compact,
            editable: !!config.editable,
            previewIndex: Number.isFinite(config.previewIndex) ? config.previewIndex : null,
            playing: false,
            current: 0,
            duration: 0,
            rate: window.CtMediaExport ? window.CtMediaExport.clampRate(config.rate ?? 1) : (config.rate || 1),
            trimStart: Number(config.trimStart) || 0,
            trimEnd: config.trimEnd == null ? null : Number(config.trimEnd),
            showSpeed: false,
            showTrim: false,
            applying: false,
            applyError: '',
            _seeking: false,

            init() {
                this.$watch('src', () => this.$nextTick(() => this.bindMedia()));
                this.$watch('kind', () => this.$nextTick(() => this.bindMedia()));
                this.$nextTick(() => this.bindMedia());
            },

            mediaEl() {
                return this.kind === 'video' ? this.$refs.videoEl : this.$refs.audioEl;
            },

            effectiveTrimEnd() {
                if (this.trimEnd != null && Number.isFinite(this.trimEnd)) {
                    return Math.min(this.duration || this.trimEnd, this.trimEnd);
                }
                return this.duration || 0;
            },

            isDirty() {
                if (!this.editable) return false;
                if (Math.abs(this.rate - 1) > 0.001) return true;
                if (this.trimStart > 0.02) return true;
                const end = this.effectiveTrimEnd();
                if (this.duration > 0 && end < this.duration - 0.05) return true;
                return false;
            },

            emitChange() {
                if (!this.editable || this.previewIndex == null) return;
                this.$dispatch('media-edit-change', {
                    index: this.previewIndex,
                    rate: this.rate,
                    trimStart: this.trimStart,
                    trimEnd: this.effectiveTrimEnd(),
                    duration: this.duration,
                });
            },

            bindMedia() {
                const video = this.$refs.videoEl;
                const audio = this.$refs.audioEl;
                if (video) {
                    video.pause?.();
                    if (this.kind === 'video' && this.src) {
                        if (video.getAttribute('src') !== this.src) video.src = this.src;
                    } else {
                        video.removeAttribute('src');
                        video.load?.();
                    }
                }
                if (audio) {
                    audio.pause?.();
                    if (this.kind === 'audio' && this.src) {
                        if (audio.getAttribute('src') !== this.src) audio.src = this.src;
                    } else {
                        audio.removeAttribute('src');
                        audio.load?.();
                    }
                }
                const el = this.mediaEl();
                if (el) {
                    el.playbackRate = this.clampRate(this.rate);
                    el.muted = false;
                }
                this.playing = false;
                this.applyError = '';
                const start = this.editable ? (this.trimStart || 0) : 0;
                this.current = start;
                this.$nextTick(() => this.seekTo(start));
            },

            clampRate(value) {
                if (window.CtMediaExport) return window.CtMediaExport.clampRate(value);
                const n = Number(value);
                if (!Number.isFinite(n)) return 1;
                return Math.min(3, Math.max(0.01, n));
            },

            rateLabel() {
                const r = this.clampRate(this.rate);
                const text = (Math.round(r * 100) / 100).toString();
                return text + '×';
            },

            formatTime(sec) {
                const s = Math.max(0, Number(sec) || 0);
                const m = Math.floor(s / 60);
                const r = Math.floor(s % 60);
                return m + ':' + String(r).padStart(2, '0');
            },

            trimLabel() {
                return this.formatTime(this.trimStart) + '–' + this.formatTime(this.effectiveTrimEnd());
            },

            clampPlayhead(time) {
                let t = Math.max(0, Number(time) || 0);
                if (this.duration > 0) t = Math.min(this.duration, t);
                if (this.editable) {
                    t = Math.min(this.effectiveTrimEnd(), Math.max(this.trimStart || 0, t));
                }
                return t;
            },

            seekTo(time, { resume = false } = {}) {
                const el = this.mediaEl();
                if (!el) return Promise.resolve();
                const t = this.clampPlayhead(time);
                this.current = t;
                this._seeking = true;

                const alreadyThere = Math.abs((el.currentTime || 0) - t) < 0.04;
                const seekPromise = alreadyThere
                    ? Promise.resolve()
                    : new Promise((resolve) => {
                        let settled = false;
                        const finish = () => {
                            if (settled) return;
                            settled = true;
                            el.removeEventListener('seeked', finish);
                            resolve();
                        };
                        el.addEventListener('seeked', finish, { once: true });
                        try {
                            el.currentTime = t;
                        } catch (e) {
                            finish();
                            return;
                        }
                        setTimeout(finish, 500);
                    });

                return seekPromise.then(async () => {
                    // Hard-sync in case seeked fired early or was ignored.
                    if (Math.abs((el.currentTime || 0) - t) > 0.08) {
                        try { el.currentTime = t; } catch (e) {}
                    }
                    this.current = el.currentTime || t;
                    this._seeking = false;
                    if (this.editable && (this.trimStart || 0) > 0.02) {
                        el.muted = (el.currentTime || 0) < (this.trimStart - 0.05);
                    } else {
                        el.muted = false;
                    }
                    if (resume) {
                        el.playbackRate = this.clampRate(this.rate);
                        try {
                            await el.play();
                            this.playing = true;
                        } catch (e) {
                            this.playing = false;
                        }
                    }
                });
            },

            onMeta() {
                const el = this.mediaEl();
                if (!el) return;
                if (Number.isFinite(el.duration)) this.duration = el.duration;
                if (this.trimEnd == null || this.trimEnd > this.duration) {
                    this.trimEnd = this.duration || 0;
                }
                if (this.trimStart > this.effectiveTrimEnd() - 0.05) {
                    this.trimStart = 0;
                }
                el.playbackRate = this.clampRate(this.rate);
                if (this.editable) {
                    this.seekTo(Math.max(this.trimStart || 0, this.current || 0));
                }
                this.emitChange();
            },

            onTime() {
                if (this._seeking) return;
                const el = this.mediaEl();
                if (!el) return;
                let t = el.currentTime || 0;
                if (this.editable) {
                    const start = this.trimStart || 0;
                    const end = this.effectiveTrimEnd();

                    // Mute any pre-trim audio bleed until the playhead is inside the window.
                    if (start > 0.02 && t < start - 0.03) {
                        el.muted = true;
                        try { el.currentTime = start; } catch (e) {}
                        this.current = start;
                        return;
                    }
                    if (el.muted && t >= start - 0.01) {
                        el.muted = false;
                    }

                    if (end > 0 && t >= end - 0.02) {
                        el.pause();
                        el.muted = false;
                        try { el.currentTime = end; } catch (e) {}
                        this.playing = false;
                        this.current = end;
                        return;
                    }
                }
                this.current = t;
            },

            onEnded() {
                this.playing = false;
                const el = this.mediaEl();
                if (el) el.muted = false;
                this.current = this.editable ? this.effectiveTrimEnd() : (this.duration || this.current);
            },

            async togglePlay() {
                const el = this.mediaEl();
                if (!el || !this.src) return;
                el.playbackRate = this.clampRate(this.rate);
                if (el.paused) {
                    let startAt = el.currentTime || 0;
                    if (this.editable) {
                        if (startAt < this.trimStart || startAt >= this.effectiveTrimEnd() - 0.05) {
                            startAt = this.trimStart || 0;
                        }
                    }
                    await this.seekTo(startAt, { resume: true });
                } else {
                    el.pause();
                    this.playing = false;
                }
            },

            async replay() {
                const el = this.mediaEl();
                if (!el || !this.src) return;
                const start = this.editable ? (this.trimStart || 0) : 0;
                await this.seekTo(start, { resume: true });
            },

            seek(value) {
                this.seekTo(value);
            },

            ensureControlsVisible() {
                this.$nextTick(() => {
                    const root = this.$el;
                    if (!root) return;
                    const scroller = root.closest('.overflow-y-auto');
                    const target = root.querySelector('[data-ct-media-footer]') || root;
                    if (!scroller || !target) return;
                    const sRect = scroller.getBoundingClientRect();
                    const tRect = target.getBoundingClientRect();
                    if (tRect.bottom > sRect.bottom - 8) {
                        scroller.scrollTop += (tRect.bottom - sRect.bottom) + 16;
                    }
                    if (tRect.top < sRect.top + 8) {
                        scroller.scrollTop -= (sRect.top - tRect.top) + 16;
                    }
                });
            },

            setRate(value) {
                this.rate = this.clampRate(value);
                const el = this.mediaEl();
                if (el) el.playbackRate = this.rate;
                this.emitChange();
            },

            setTrimStart(value) {
                let start = Math.max(0, Number(value) || 0);
                const end = this.effectiveTrimEnd();
                if (start > end - 0.05) start = Math.max(0, end - 0.05);
                this.trimStart = start;
                // Always snap preview to the new cut so you hear from the trimmed start.
                this.seekTo(start, { resume: this.playing });
                this.emitChange();
                this.ensureControlsVisible();
            },

            setTrimEnd(value) {
                let end = Math.max(0, Number(value) || 0);
                if (end < this.trimStart + 0.05) end = this.trimStart + 0.05;
                if (this.duration > 0) end = Math.min(this.duration, end);
                this.trimEnd = end;
                if (this.current > end) {
                    this.seekTo(end, { resume: false });
                    const el = this.mediaEl();
                    if (el && this.playing) {
                        el.pause();
                        this.playing = false;
                    }
                }
                this.emitChange();
            },

            async applyEdit() {
                if (!this.editable || this.previewIndex == null || this.applying) return;
                if (typeof this.$root.applyStagedMediaEdit !== 'function') return;
                this.applying = true;
                this.applyError = '';
                try {
                    await this.$root.applyStagedMediaEdit({
                        index: this.previewIndex,
                        rate: this.rate,
                        trimStart: this.trimStart,
                        trimEnd: this.effectiveTrimEnd(),
                    });
                    this.rate = 1;
                    this.trimStart = 0;
                    this.trimEnd = null;
                    this.showSpeed = false;
                    this.showTrim = false;
                    const el = this.mediaEl();
                    if (el) el.muted = false;
                } catch (e) {
                    this.applyError = e?.message || 'Could not apply edits.';
                } finally {
                    this.applying = false;
                }
            },
        };
    };
</script>
