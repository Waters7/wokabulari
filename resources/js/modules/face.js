// Kamera + face-api: deteksi wajah, liveness sederhana (kedip / menoleh), descriptor 128 dimensi.
// Descriptor dihitung di browser lalu dicocokkan di server; referensi tidak pernah dikirim ke browser.
import * as faceapi from '@vladmandic/face-api';

let modelsPromise = null;

export function loadModels() {
    if (!modelsPromise) {
        modelsPromise = Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri('/models'),
            faceapi.nets.faceLandmark68Net.loadFromUri('/models'),
            faceapi.nets.faceRecognitionNet.loadFromUri('/models'),
        ]);
    }

    return modelsPromise;
}

export async function startCamera(video) {
    if (!navigator.mediaDevices?.getUserMedia) {
        throw new Error('Kamera tidak tersedia. Buka lewat HTTPS dan izinkan akses kamera.');
    }

    const stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
        audio: false,
    });

    video.srcObject = stream;
    video.setAttribute('playsinline', '');
    video.muted = true;
    await video.play();

    return stream;
}

export function stopCamera(stream) {
    stream?.getTracks().forEach((track) => track.stop());
}

const detectorOptions = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 });

/** Deteksi satu wajah + landmark (+ descriptor bila diminta). */
export async function detect(video, withDescriptor = false) {
    const task = faceapi.detectSingleFace(video, detectorOptions).withFaceLandmarks();

    return withDescriptor ? task.withFaceDescriptor() : task;
}

/** Jumlah wajah di frame (untuk menolak lebih dari satu orang). */
export async function countFaces(video) {
    const faces = await faceapi.detectAllFaces(video, detectorOptions);

    return faces.length;
}

const dist = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);

function eyeAspectRatio(eye) {
    return (dist(eye[1], eye[5]) + dist(eye[2], eye[4])) / (2 * dist(eye[0], eye[3]));
}

/** Rata-rata EAR kedua mata; turun tajam saat berkedip. */
export function eyeOpenness(landmarks) {
    return (eyeAspectRatio(landmarks.getLeftEye()) + eyeAspectRatio(landmarks.getRightEye())) / 2;
}

/**
 * Perkiraan arah kepala: negatif = menoleh ke kiri (dari sudut pandang peserta), positif = ke kanan.
 * Dihitung dari posisi ujung hidung terhadap titik tengah kedua mata.
 */
export function headYaw(landmarks) {
    const left = landmarks.getLeftEye();
    const right = landmarks.getRightEye();
    const nose = landmarks.getNose()[3];
    const leftCenter = { x: (left[0].x + left[3].x) / 2, y: (left[0].y + left[3].y) / 2 };
    const rightCenter = { x: (right[0].x + right[3].x) / 2, y: (right[0].y + right[3].y) / 2 };
    const mid = (leftCenter.x + rightCenter.x) / 2;
    const interocular = dist(leftCenter, rightCenter) || 1;

    // Video kamera depan tidak dicerminkan di data, jadi tanda dibalik agar sesuai pandangan peserta.
    return -((nose.x - mid) / interocular);
}

/** Ambil frame video sebagai JPEG terkompresi (data URI). */
export function captureFrame(video, maxWidth = 480, quality = 0.75) {
    const scale = Math.min(1, maxWidth / video.videoWidth);
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(video.videoWidth * scale);
    canvas.height = Math.round(video.videoHeight * scale);
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

    return canvas.toDataURL('image/jpeg', quality);
}

export function toArray(descriptor) {
    return Array.from(descriptor, (v) => Number(v.toFixed(6)));
}
