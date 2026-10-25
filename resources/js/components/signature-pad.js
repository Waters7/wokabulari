// Tanda tangan di layar sentuh (signature_pad, dimuat lazy), hasil PNG dikirim ke aksi Livewire.
export default function signaturePad({ action, args = [] }) {
    return {
        pad: null,
        empty: true,
        sending: false,
        width: 0,

        async init() {
            const { default: SignaturePad } = await import('signature_pad');
            const canvas = this.$refs.canvas;
            this.pad = new SignaturePad(canvas, { penColor: '#0B3B38', minWidth: 0.8, maxWidth: 2.6, backgroundColor: 'rgba(0,0,0,0)' });
            this.pad.addEventListener('endStroke', () => (this.empty = this.pad.isEmpty()));
            this.resize();
            // Kanvas di dalam modal baru punya ukuran setelah modal tampil: ukur ulang saat lebarnya berubah.
            this.observer = new ResizeObserver(() => this.resize());
            this.observer.observe(canvas);
        },

        destroy() {
            this.observer?.disconnect();
            this.pad?.off();
        },

        resize() {
            const canvas = this.$refs.canvas;
            if (!this.pad || canvas.offsetWidth === 0 || canvas.offsetWidth === this.width) {
                return;
            }
            this.width = canvas.offsetWidth;
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext('2d').scale(ratio, ratio);
            this.pad.clear();
            this.empty = true;
        },

        clear() {
            this.pad?.clear();
            this.empty = true;
        },

        async submit() {
            if (!this.pad || this.pad.isEmpty()) {
                return;
            }
            this.sending = true;
            try {
                await this.$wire.call(action, ...args, this.pad.toDataURL('image/png'));
                this.clear();
            } finally {
                this.sending = false;
            }
        },
    };
}
