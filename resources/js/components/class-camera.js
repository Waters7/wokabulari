// Kamera dokumentasi kelas: buka kamera (depan/belakang), ambil foto, unggah ke properti Livewire `model`.
// Bila kamera tidak bisa dibuka, pengajar diarahkan ke "Unggah file".
export default (model) => ({
    state: 'idle', // idle | starting | live | uploading | done | error
    message: '',
    facing: 'user',
    stream: null,

    async open() {
        if (!navigator.mediaDevices?.getUserMedia) {
            this.fail('Kamera tidak tersedia di perangkat/browser ini. Pakai "Unggah file".');
            return;
        }

        this.stop();
        this.state = 'starting';

        try {
            this.stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: this.facing, width: { ideal: 1600 }, height: { ideal: 1200 } },
                audio: false,
            });
            this.$refs.video.srcObject = this.stream;
            await this.$refs.video.play();
            this.state = 'live';
        } catch (e) {
            this.fail('Kamera tidak bisa dibuka (izin ditolak atau dipakai aplikasi lain). Pakai "Unggah file".');
        }
    },

    flip() {
        this.facing = this.facing === 'user' ? 'environment' : 'user';
        this.open();
    },

    snap() {
        const video = this.$refs.video;
        if (!video.videoWidth) {
            return;
        }

        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        this.state = 'uploading';

        canvas.toBlob((blob) => {
            const file = new File([blob], `dokumentasi-${Date.now()}.jpg`, { type: 'image/jpeg' });
            this.$wire.upload(model, file,
                () => { this.state = 'done'; this.stop(); },
                () => this.fail('Foto gagal diunggah. Periksa koneksi lalu coba lagi.'),
            );
        }, 'image/jpeg', 0.85);
    },

    retake() {
        this.open();
    },

    fail(message) {
        this.stop();
        this.state = 'error';
        this.message = message;
    },

    stop() {
        this.stream?.getTracks().forEach((track) => track.stop());
        this.stream = null;
    },

    destroy() {
        this.stop();
    },
});
