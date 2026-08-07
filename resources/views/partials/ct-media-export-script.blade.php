{{-- Client-side A/V bake: trim + speed into a new File (preview rate alone does not change uploads). --}}
<script>
    window.CtMediaExport = (function () {
        function clampRate(value) {
            const n = Number(value);
            if (!Number.isFinite(n)) return 1;
            return Math.min(3, Math.max(0.01, n));
        }

        function baseName(name, fallback) {
            const raw = (name || fallback || 'media').replace(/\.[^.]+$/, '');
            return raw || fallback || 'media';
        }

        function writeString(view, offset, string) {
            for (let i = 0; i < string.length; i++) {
                view.setUint8(offset + i, string.charCodeAt(i));
            }
        }

        function encodeWav(audioBuffer) {
            const numChannels = audioBuffer.numberOfChannels;
            const sampleRate = audioBuffer.sampleRate;
            const format = 1;
            const bitDepth = 16;
            const samples = audioBuffer.length;
            const blockAlign = numChannels * (bitDepth / 8);
            const byteRate = sampleRate * blockAlign;
            const dataSize = samples * blockAlign;
            const buffer = new ArrayBuffer(44 + dataSize);
            const view = new DataView(buffer);

            writeString(view, 0, 'RIFF');
            view.setUint32(4, 36 + dataSize, true);
            writeString(view, 8, 'WAVE');
            writeString(view, 12, 'fmt ');
            view.setUint32(16, 16, true);
            view.setUint16(20, format, true);
            view.setUint16(22, numChannels, true);
            view.setUint32(24, sampleRate, true);
            view.setUint32(28, byteRate, true);
            view.setUint16(32, blockAlign, true);
            view.setUint16(34, bitDepth, true);
            writeString(view, 36, 'data');
            view.setUint32(40, dataSize, true);

            let offset = 44;
            const channels = [];
            for (let c = 0; c < numChannels; c++) {
                channels.push(audioBuffer.getChannelData(c));
            }
            for (let i = 0; i < samples; i++) {
                for (let c = 0; c < numChannels; c++) {
                    let sample = channels[c][i];
                    sample = Math.max(-1, Math.min(1, sample));
                    view.setInt16(offset, sample < 0 ? sample * 0x8000 : sample * 0x7fff, true);
                    offset += 2;
                }
            }

            return new Blob([buffer], { type: 'audio/wav' });
        }

        async function processAudio(file, options = {}) {
            const rate = clampRate(options.rate ?? 1);
            const arrayBuffer = await file.arrayBuffer();
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            let decoded;
            try {
                decoded = await audioCtx.decodeAudioData(arrayBuffer.slice(0));
            } finally {
                try { await audioCtx.close(); } catch (e) {}
            }

            const trimStart = Math.max(0, Math.min(decoded.duration, Number(options.trimStart) || 0));
            let trimEnd = options.trimEnd == null ? decoded.duration : Number(options.trimEnd);
            if (!Number.isFinite(trimEnd)) trimEnd = decoded.duration;
            trimEnd = Math.max(trimStart + 0.05, Math.min(decoded.duration, trimEnd));

            const sourceDuration = trimEnd - trimStart;
            const outDuration = sourceDuration / rate;
            const frames = Math.max(1, Math.ceil(outDuration * decoded.sampleRate));
            const offline = new OfflineAudioContext(decoded.numberOfChannels, frames, decoded.sampleRate);
            const source = offline.createBufferSource();
            source.buffer = decoded;
            source.playbackRate.value = rate;
            source.connect(offline.destination);
            source.start(0, trimStart, sourceDuration);
            const rendered = await offline.startRendering();
            const blob = encodeWav(rendered);
            const name = baseName(file.name, 'audio') + '.wav';
            return new File([blob], name, { type: 'audio/wav', lastModified: Date.now() });
        }

        function pickRecorderMime() {
            const candidates = [
                'video/webm;codecs=vp9,opus',
                'video/webm;codecs=vp8,opus',
                'video/webm;codecs=vp9',
                'video/webm;codecs=vp8',
                'video/webm',
            ];
            for (const type of candidates) {
                if (window.MediaRecorder && MediaRecorder.isTypeSupported(type)) return type;
            }
            return '';
        }

        async function processVideo(file, options = {}) {
            if (typeof MediaRecorder === 'undefined') {
                throw new Error('Video editing is not supported in this browser.');
            }
            const rate = clampRate(options.rate ?? 1);
            const url = URL.createObjectURL(file);
            const video = document.createElement('video');
            video.src = url;
            video.playsInline = true;
            video.muted = false;
            video.preload = 'auto';
            video.crossOrigin = 'anonymous';
            let audioCtx = null;

            try {
                await new Promise((resolve, reject) => {
                    const onReady = () => resolve();
                    video.addEventListener('loadedmetadata', onReady, { once: true });
                    video.addEventListener('error', () => reject(new Error('Could not load video for editing.')), { once: true });
                });

                const duration = Number.isFinite(video.duration) ? video.duration : 0;
                const trimStart = Math.max(0, Math.min(duration, Number(options.trimStart) || 0));
                let trimEnd = options.trimEnd == null ? duration : Number(options.trimEnd);
                if (!Number.isFinite(trimEnd)) trimEnd = duration;
                trimEnd = Math.max(trimStart + 0.05, Math.min(duration, trimEnd));

                const canvas = document.createElement('canvas');
                canvas.width = Math.max(2, video.videoWidth || 640);
                canvas.height = Math.max(2, video.videoHeight || 360);
                const ctx = canvas.getContext('2d');
                const canvasStream = canvas.captureStream(30);

                let mixedStream = canvasStream;
                try {
                    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                    const source = audioCtx.createMediaElementSource(video);
                    const dest = audioCtx.createMediaStreamDestination();
                    source.connect(dest);
                    mixedStream = new MediaStream([
                        ...canvasStream.getVideoTracks(),
                        ...dest.stream.getAudioTracks(),
                    ]);
                } catch (e) {
                    mixedStream = canvasStream;
                }

                const mime = pickRecorderMime();
                const recorder = mime
                    ? new MediaRecorder(mixedStream, { mimeType: mime, videoBitsPerSecond: 2_500_000 })
                    : new MediaRecorder(mixedStream);
                const chunks = [];
                recorder.ondataavailable = (e) => {
                    if (e.data && e.data.size) chunks.push(e.data);
                };

                const stopped = new Promise((resolve, reject) => {
                    recorder.onstop = () => resolve();
                    recorder.onerror = () => reject(new Error('Video recording failed.'));
                });

                video.playbackRate = rate;
                video.currentTime = trimStart;
                await new Promise((resolve) => {
                    if (Math.abs(video.currentTime - trimStart) < 0.05) return resolve();
                    video.addEventListener('seeked', () => resolve(), { once: true });
                });

                let drawing = true;
                const draw = () => {
                    if (!drawing) return;
                    try { ctx.drawImage(video, 0, 0, canvas.width, canvas.height); } catch (e) {}
                    requestAnimationFrame(draw);
                };

                recorder.start(100);
                draw();
                try {
                    await video.play();
                } catch (e) {
                    drawing = false;
                    try { recorder.stop(); } catch (err) {}
                    throw new Error('Could not play video for export. Try again.');
                }

                await new Promise((resolve) => {
                    const onTick = () => {
                        if (video.ended || video.currentTime >= trimEnd - 0.04) {
                            video.pause();
                            video.removeEventListener('timeupdate', onTick);
                            resolve();
                        }
                    };
                    video.addEventListener('timeupdate', onTick);
                    const wallMs = Math.ceil(((trimEnd - trimStart) / rate) * 1000) + 400;
                    setTimeout(() => {
                        video.removeEventListener('timeupdate', onTick);
                        try { video.pause(); } catch (e) {}
                        resolve();
                    }, wallMs);
                });

                drawing = false;
                if (recorder.state !== 'inactive') recorder.stop();
                await stopped;

                const type = recorder.mimeType || 'video/webm';
                const blob = new Blob(chunks, { type });
                if (!blob.size) throw new Error('Exported video was empty.');
                const name = baseName(file.name, 'video') + '.webm';
                return new File([blob], name, { type: 'video/webm', lastModified: Date.now() });
            } finally {
                try { video.pause(); } catch (e) {}
                URL.revokeObjectURL(url);
                if (audioCtx) {
                    try { await audioCtx.close(); } catch (e) {}
                }
            }
        }

        async function processFile(file, options = {}) {
            const type = (file.type || '').toLowerCase();
            if (type.startsWith('audio/')) return processAudio(file, options);
            if (type.startsWith('video/')) return processVideo(file, options);
            throw new Error('Only audio and video can be trimmed or re-timed.');
        }

        function needsProcess(options = {}, duration = null) {
            const rate = clampRate(options.rate ?? 1);
            if (Math.abs(rate - 1) > 0.001) return true;
            const start = Number(options.trimStart) || 0;
            if (start > 0.02) return true;
            if (options.trimEnd != null && duration != null && Number(options.trimEnd) < duration - 0.05) return true;
            if (options.trimEnd != null && options.trimStart != null
                && Number(options.trimEnd) - Number(options.trimStart) > 0
                && duration == null) {
                return true;
            }
            return false;
        }

        return { processAudio, processVideo, processFile, needsProcess, clampRate };
    })();
</script>
