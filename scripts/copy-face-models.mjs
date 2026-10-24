// Salin model face-api yang dipakai aplikasi ke public/models (dijalankan oleh npm postinstall).
import { cpSync, existsSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const source = join(root, 'node_modules', '@vladmandic', 'face-api', 'model');
const target = join(root, 'public', 'models');

// Hanya model yang dibutuhkan: deteksi wajah ringan, landmark (liveness), dan descriptor.
const models = ['tiny_face_detector_model', 'face_landmark_68_model', 'face_recognition_model'];

if (!existsSync(source)) {
    console.warn('[face-models] @vladmandic/face-api belum terpasang, lewati.');
    process.exit(0);
}

mkdirSync(target, { recursive: true });

for (const model of models) {
    for (const ext of ['-weights_manifest.json', '.bin']) {
        cpSync(join(source, model + ext), join(target, model + ext));
    }
}

console.log(`[face-models] ${models.length} model disalin ke public/models`);
