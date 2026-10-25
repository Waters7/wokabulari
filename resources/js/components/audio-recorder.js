// Perekam suara (MediaRecorder) yang mengunggah hasil rekaman ke properti Livewire.
export default function audioRecorder({ model, maxSeconds = 300 }) {
    return {
        state: 'idle', // idle | recording | recorded | uploading | uploaded | error
        seconds: 0,
        url: null,
        error: null,
        recorder: null,
        chunks: [],
        timer: null,
        stream: null,

        get supported() {
            return !!(navigator.mediaDevices?.getUserMedia && window.MediaRecorder);
        },

        get display() {
            const m = Math.floor(this.seconds / 60);
            const s = this.seconds % 60;
            return `${m}:${String(s).padStart(2, '0')}`;
        },

        async start() {
            this.error = null;
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            } catch (e) {
                this.state = 'error';
                this.error = 'Izin mikrofon ditolak. Aktifkan mikrofon untuk situs ini.';
                return;
            }

            const type = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg'].find((t) => MediaRecorder.isTypeSupported?.(t)) || '';
            this.recorder = new MediaRecorder(this.stream, type ? { mimeType: type } : undefined);
            this.chunks = [];
            this.recorder.ondataavailable = (e) => e.data.size && this.chunks.push(e.data);
            this.recorder.onstop = () => this.finish();
            this.recorder.start();
            this.state = 'recording';
            this.seconds = 0;
            this.timer = setInterval(() => {
                this.seconds++;
                if (this.seconds >= maxSeconds) {
                    this.stop();
                }
            }, 1000);
        },

        stop() {
            clearInterval(this.timer);
            if (this.recorder?.state === 'recording') {
                this.recorder.stop();
            }
            this.stream?.getTracks().forEach((t) => t.stop());
        },

        finish() {
            const mime = this.recorder.mimeType || 'audio/webm';
            const blob = new Blob(this.chunks, { type: mime });
            const ext = mime.includes('mp4') ? 'm4a' : mime.includes('ogg') ? 'ogg' : 'webm';
            this.url = URL.createObjectURL(blob);
            this.state = 'uploading';

            const file = new File([blob], `rekaman-${Date.now()}.${ext}`, { type: mime.split(';')[0] });
            this.$wire.upload(
                model,
                file,
                () => (this.state = 'uploaded'),
                () => {
                    this.state = 'error';
                    this.error = 'Rekaman gagal diunggah. Coba lagi.';
                },
            );
        },

        reset() {
            if (this.url) {
                URL.revokeObjectURL(this.url);
            }
            this.url = null;
            this.state = 'idle';
            this.seconds = 0;
            this.$wire.set(model, null);
        },

        destroy() {
            this.stop();
        },
    };
}
