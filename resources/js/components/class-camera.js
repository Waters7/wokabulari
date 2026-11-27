// Dokumentasi kelas: foto kamera (depan/belakang) atau file pilihan, diperkecil dulu lalu diunggah ke properti Livewire `model`.
// Unggahan menampilkan progres; bila gagal (mis. sinyal lemah) file yang sama bisa dikirim ulang tanpa memotret lagi.
import { compressImage, drawToJpeg, sizeLabel } from '../modules/compress-image.js';

const FAILED = 'Unggahan gagal, kemungkinan sinyal lemah. Tekan "Coba unggah lagi" (foto tidak perlu diambil ulang) atau pindah ke tempat dengan sinyal lebih baik.';

// Bagian bersama kamera & unggah file.
const uploader = (model) => ({
    progress: 0,
    pending: null,
    sizeText: '',

    send(file) {
        this.pending = file;
        this.sizeText = sizeLabel(file.size);
        this.progress = 0;
        this.state = 'uploading';
        this.$wire.upload(
            model,
            file,
            () => {
                this.pending = null;
                this.state = 'done';
                this.stop();
            },
            () => this.fail(FAILED),
            (event) => {
                this.progress = event.detail?.progress ?? 0;
            },
        );
    },

    resend() {
        if (this.pending) {
            this.send(this.pending);
        }
    },
});

export default (model) => ({
    state: 'idle', // idle | starting | live | uploading | done | error
    message: '',
    facing: 'user',
    stream: null,
    ...uploader(model),

    async open() {
        if (!navigator.mediaDevices?.getUserMedia) {
            this.fail('Kamera tidak tersedia di perangkat/browser ini. Pakai "Unggah file".');
            return;
        }

        this.stop();
        this.pending = null;
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

    async snap() {
        const video = this.$refs.video;
        if (!video.videoWidth) {
            return;
        }

        const file = await drawToJpeg(video, video.videoWidth, video.videoHeight, `dokumentasi-${Date.now()}.jpg`);
        if (!file) {
            this.fail('Foto tidak bisa diproses. Ambil ulang.');
            return;
        }

        this.send(file);
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

/** Pilih foto/file dari galeri: foto diperkecil dulu, PDF/HEIC dikirim apa adanya. */
export const docUpload = (model) => ({
    state: 'idle', // idle | processing | uploading | done | error
    message: '',
    ...uploader(model),

    async pick(event) {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file) {
            return;
        }

        this.state = 'processing';
        this.send(await compressImage(file));
    },

    fail(message) {
        this.state = 'error';
        this.message = message;
    },

    stop() {},
});
