// Perkecil foto sebelum diunggah agar tetap terkirim di sinyal lemah (foto HP bisa 3–10 MB → ±150–300 KB).
// Hanya JPEG/PNG/WEBP; file lain (PDF, HEIC yang tak bisa dibaca browser) dikirim apa adanya.
export const DOC_MAX_SIDE = 1600;
export const DOC_QUALITY = 0.72;

/** Gambar sumber (video/bitmap) ke kanvas berukuran maks. maxSide, latar putih, lalu jadikan File JPEG. */
export function drawToJpeg(source, width, height, name, { maxSide = DOC_MAX_SIDE, quality = DOC_QUALITY } = {}) {
    const scale = Math.min(1, maxSide / Math.max(width, height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(width * scale));
    canvas.height = Math.max(1, Math.round(height * scale));
    const context = canvas.getContext('2d');
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.drawImage(source, 0, 0, canvas.width, canvas.height);

    return new Promise((resolve) => {
        canvas.toBlob((blob) => resolve(blob ? new File([blob], name, { type: 'image/jpeg' }) : null), 'image/jpeg', quality);
    });
}

export async function compressImage(file, options = {}) {
    if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
        return file;
    }

    let bitmap;
    try {
        bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch {
        try {
            bitmap = await createImageBitmap(file);
        } catch {
            return file;
        }
    }

    const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
    const result = await drawToJpeg(bitmap, bitmap.width, bitmap.height, name, options);
    bitmap.close?.();

    // Pakai yang lebih kecil: foto yang sudah kecil tidak perlu diproses ulang.
    return result && result.size < file.size ? result : file;
}

export function sizeLabel(bytes) {
    return bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1).replace('.', ',')} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}
