// Komponen Alpine kamera wajah untuk pendaftaran wajah (enroll) dan absen (verify).
const CHALLENGES = [
    { type: 'blink', label: 'Kedipkan mata ngana' },
    { type: 'turn', dir: -1, label: 'Toleh pelan ke kiri, lalu kembali lurus' },
    { type: 'turn', dir: 1, label: 'Toleh pelan ke kanan, lalu kembali lurus' },
];

export default function faceCapture({ mode = 'verify', samples = 3, action = 'submit', needsLocation = false, allowSkip = false }) {
    return {
        mode,
        state: 'idle',
        message: 'Ketuk "Buka kamera" untuk memulai.',
        challenge: null,
        progress: 0,
        samples,
        allowSkip,
        stream: null,
        running: false,
        face: null,
        selfie: null,
        _blink: { open: false, closed: false },
        _turn: { turned: false },
        _startedAt: 0,

        async start() {
            this.state = 'loading';
            this.message = 'Menyiapkan kamera dan model wajah…';

            try {
                this.face = await window.Wokabulari.load('face');
                await Promise.all([this.face.loadModels(), this.openCamera()]);
            } catch (error) {
                this.fail(error?.name === 'NotAllowedError' ? 'Izin kamera ditolak. Aktifkan kamera untuk situs ini di pengaturan browser.' : error?.message || 'Kamera tidak dapat dibuka.');
                return;
            }

            this.newChallenge();
            this.running = true;
            this.loop();
        },

        async openCamera() {
            this.stream = await this.face.startCamera(this.$refs.video);
        },

        newChallenge() {
            this.challenge = CHALLENGES[Math.floor(Math.random() * CHALLENGES.length)];
            this._blink = { open: false, closed: false };
            this._turn = { turned: false };
            this._startedAt = Date.now();
            this.state = 'challenge';
            this.message = this.challenge.label;
        },

        async loop() {
            if (!this.running) {
                return;
            }

            try {
                const result = await this.face.detect(this.$refs.video, false);
                await this.process(result);
            } catch (error) {
                // Abaikan frame gagal, lanjut ke frame berikutnya.
            }

            if (this.running) {
                setTimeout(() => this.loop(), 120);
            }
        },

        async process(result) {
            if (!result) {
                if (this.state === 'challenge') {
                    this.message = 'Wajah belum terlihat. ' + this.challenge.label;
                }
                return;
            }

            const ear = this.face.eyeOpenness(result.landmarks);
            const yaw = this.face.headYaw(result.landmarks);

            if (this.state === 'challenge') {
                if (Date.now() - this._startedAt > 25000) {
                    this.newChallenge();
                    this.message = 'Coba lagi: ' + this.challenge.label;
                    return;
                }

                if (this.passedChallenge(ear, yaw)) {
                    this.state = 'frontal';
                    this.message = 'Mantap! Sekarang lihat lurus ke kamera…';
                }
                return;
            }

            if ((this.state === 'frontal' || this.state === 'sampling') && Math.abs(yaw) < 0.15 && ear > 0.2) {
                await this.collect();
            }
        },

        passedChallenge(ear, yaw) {
            if (this.challenge.type === 'blink') {
                if (ear > 0.25) {
                    this._blink.open = true;
                }
                if (this._blink.open && ear < 0.2) {
                    this._blink.closed = true;
                }
                return this._blink.closed && ear > 0.24;
            }

            if (yaw * this.challenge.dir > 0.28) {
                this._turn.turned = true;
            }
            return this._turn.turned && Math.abs(yaw) < 0.12;
        },

        descriptors: [],

        async collect() {
            this.running = false;
            const video = this.$refs.video;

            if ((await this.face.countFaces(video)) > 1) {
                this.message = 'Hanya satu wajah yang boleh terlihat di kamera.';
                this.running = true;
                this.loop();
                return;
            }

            const result = await this.face.detect(video, true);
            if (!result) {
                this.running = true;
                this.loop();
                return;
            }

            if (!this.selfie) {
                this.selfie = this.face.captureFrame(video);
            }

            this.descriptors.push(this.face.toArray(result.descriptor));
            this.progress = this.descriptors.length;

            const needed = this.mode === 'enroll' ? this.samples : 1;
            if (this.descriptors.length < needed) {
                this.state = 'sampling';
                this.message = `Mengambil sampel ${this.descriptors.length + 1} dari ${needed}… tetap lihat kamera.`;
                setTimeout(() => {
                    this.running = true;
                    this.loop();
                }, 600);
                return;
            }

            await this.finish();
        },

        async finish(skipped = false) {
            this.face?.stopCamera(this.stream);
            this.running = false;

            const payload = this.mode === 'enroll'
                ? { descriptors: this.descriptors, selfie: this.selfie }
                : { descriptor: skipped ? null : this.descriptors[0] ?? null, selfie: this.selfie, device: navigator.userAgent.slice(0, 250) };

            if (needsLocation) {
                this.state = 'locating';
                this.message = 'Mengambil lokasi GPS…';
                try {
                    Object.assign(payload, await this.locate());
                } catch (error) {
                    this.fail(error);
                    return;
                }
            }

            this.state = 'sending';
            this.message = 'Mengirim…';

            try {
                await this.$wire.call(action, payload);
                this.state = 'done';
                this.message = 'Selesai.';
            } catch (error) {
                this.fail('Gagal mengirim. Periksa koneksi lalu coba lagi.');
            }
        },

        async skip() {
            // Mode uji: verifikasi wajah dimatikan di server.
            if (this.stream && this.$refs.video.videoWidth) {
                this.selfie = this.face.captureFrame(this.$refs.video);
            }
            await this.finish(true);
        },

        locate() {
            return new Promise((resolve, reject) => {
                if (!('geolocation' in navigator)) {
                    reject('Perangkat tidak mendukung GPS.');
                    return;
                }
                navigator.geolocation.getCurrentPosition(
                    (pos) => resolve({ latitude: pos.coords.latitude, longitude: pos.coords.longitude, accuracy: pos.coords.accuracy }),
                    (err) => reject(err.code === 1 ? 'Izin lokasi ditolak. Aktifkan lokasi untuk situs ini.' : 'Lokasi tidak dapat diambil. Coba di tempat terbuka.'),
                    { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 },
                );
            });
        },

        fail(message) {
            this.running = false;
            this.face?.stopCamera(this.stream);
            this.state = 'error';
            this.message = message;
        },

        retry() {
            this.descriptors = [];
            this.selfie = null;
            this.progress = 0;
            this.start();
        },

        destroy() {
            this.running = false;
            this.face?.stopCamera(this.stream);
        },
    };
}
