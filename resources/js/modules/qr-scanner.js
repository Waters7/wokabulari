// Pemindai QR memakai kamera belakang (html5-qrcode).
import { Html5Qrcode } from 'html5-qrcode';

export async function startScanner(elementId, onDecoded) {
    const scanner = new Html5Qrcode(elementId, { verbose: false });

    await scanner.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: (w, h) => ({ width: Math.min(w, h) * 0.7, height: Math.min(w, h) * 0.7 }) },
        (text) => onDecoded(text),
        () => {},
    );

    return {
        async stop() {
            try {
                await scanner.stop();
                scanner.clear();
            } catch (e) {
                // sudah berhenti
            }
        },
    };
}
