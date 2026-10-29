// Kartu "lencana baru" untuk dibagikan ke media sosial. Digambar di HP (canvas), tidak diunggah ke server.

const SIZES = {
    story: { width: 1080, height: 1920 }, // 9:16 — Instagram/WhatsApp story
    square: { width: 1080, height: 1080 }, // 1:1 — feed
};

const COLORS = {
    top: '#0b3b38',
    bottom: '#007d71',
    accent: '#d5e022',
    gold: '#f2a81d',
    text: '#ffffff',
    muted: 'rgba(255,255,255,0.72)',
};

function loadImage(src) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = reject;
        image.src = src;
    });
}

/** Ikon lencana (SVG heroicons) dijadikan gambar berwarna. */
async function iconImage(svg, color) {
    const markup = svg
        .replace(/class="[^"]*"/, '')
        .replace('<svg', `<svg width="512" height="512" color="${color}" style="color:${color}"`)
        .replaceAll('currentColor', color);

    return loadImage('data:image/svg+xml;charset=utf-8,' + encodeURIComponent(markup));
}

/** Pecah teks menjadi beberapa baris sesuai lebar maksimum. */
function wrap(ctx, text, maxWidth) {
    const lines = [];
    let line = '';

    for (const word of String(text).split(/\s+/)) {
        const test = line ? `${line} ${word}` : word;
        if (ctx.measureText(test).width > maxWidth && line) {
            lines.push(line);
            line = word;
        } else {
            line = test;
        }
    }

    if (line) {
        lines.push(line);
    }

    return lines;
}

function lines(ctx, text, x, y, maxWidth, lineHeight, maxLines = 3) {
    const rows = wrap(ctx, text, maxWidth).slice(0, maxLines);
    rows.forEach((row, i) => ctx.fillText(row, x, y + i * lineHeight));

    return y + rows.length * lineHeight;
}

/**
 * @param {HTMLCanvasElement} canvas
 * @param {object} card  { badge, description, earned, name, classroom, level, program, tagline, hashtags, logo }
 * @param {string} iconSvg
 * @param {'story'|'square'} format
 * @param {boolean} showName
 */
export async function drawBadgeCard(canvas, card, iconSvg, format = 'story', showName = true) {
    const { width, height } = SIZES[format] ?? SIZES.story;
    const story = format === 'story';
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    const display = getComputedStyle(document.documentElement).getPropertyValue('--font-display') || '"Baloo 2", sans-serif';
    const sans = getComputedStyle(document.documentElement).getPropertyValue('--font-sans') || '"Plus Jakarta Sans", sans-serif';

    await Promise.all([document.fonts.load(`800 96px ${display}`), document.fonts.load(`600 40px ${sans}`)]).catch(() => null);

    // Latar: gradasi hijau Kemenkes + lingkaran dekoratif.
    const gradient = ctx.createLinearGradient(0, 0, width, height);
    gradient.addColorStop(0, COLORS.top);
    gradient.addColorStop(1, COLORS.bottom);
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, width, height);

    ctx.globalAlpha = 0.08;
    ctx.fillStyle = COLORS.accent;
    ctx.beginPath();
    ctx.arc(width * 0.9, height * 0.08, width * 0.35, 0, Math.PI * 2);
    ctx.arc(width * 0.05, height * 0.95, width * 0.3, 0, Math.PI * 2);
    ctx.fill();
    ctx.globalAlpha = 1;

    ctx.fillStyle = COLORS.accent;
    ctx.fillRect(0, 0, width, 14);

    // Kepala: logo + nama aplikasi.
    const top = story ? 150 : 90;
    try {
        const logo = await loadImage(card.logo);
        ctx.drawImage(logo, 80, top - 70, 110, 110);
    } catch {
        // Logo gagal dimuat: lanjut tanpa logo.
    }
    ctx.textAlign = 'left';
    ctx.textBaseline = 'alphabetic';
    ctx.fillStyle = COLORS.text;
    ctx.font = `800 64px ${display}`;
    ctx.fillText('Wokabulari', 210, top + 2);
    ctx.font = `500 30px ${sans}`;
    ctx.fillStyle = COLORS.muted;
    ctx.fillText(card.program, 212, top + 42);

    // Medali lencana.
    const cx = width / 2;
    const cy = story ? 720 : 390;
    const radius = story ? 230 : 150;
    ctx.fillStyle = 'rgba(255,255,255,0.08)';
    ctx.beginPath();
    ctx.arc(cx, cy, radius + 50, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = COLORS.gold;
    ctx.beginPath();
    ctx.arc(cx, cy, radius, 0, Math.PI * 2);
    ctx.fill();
    ctx.lineWidth = 14;
    ctx.strokeStyle = COLORS.accent;
    ctx.stroke();
    try {
        const icon = await iconImage(iconSvg, COLORS.top);
        const size = radius * 1.05;
        ctx.drawImage(icon, cx - size / 2, cy - size / 2, size, size);
    } catch {
        // Ikon gagal digambar: medali polos.
    }

    // Teks utama.
    ctx.textAlign = 'center';
    let y = cy + radius + (story ? 150 : 85);
    ctx.fillStyle = COLORS.accent;
    ctx.font = `700 ${story ? 40 : 32}px ${sans}`;
    ctx.fillText('LENCANA BARU', cx, y);
    y += story ? 100 : 72;
    ctx.fillStyle = COLORS.text;
    ctx.font = `800 ${story ? 92 : 66}px ${display}`;
    y = lines(ctx, card.badge, cx, y, width - 160, story ? 100 : 72, 2);

    if (card.description && story) {
        ctx.fillStyle = COLORS.muted;
        ctx.font = `500 38px ${sans}`;
        y = lines(ctx, card.description, cx, y + 20, width - 220, 52, 3);
    }

    y += story ? 70 : 24;
    ctx.fillStyle = COLORS.text;
    ctx.font = `700 ${story ? 46 : 36}px ${sans}`;
    if (showName && card.name) {
        y = lines(ctx, card.name, cx, y, width - 200, story ? 56 : 44, 2);
    }
    ctx.fillStyle = COLORS.muted;
    ctx.font = `500 ${story ? 34 : 28}px ${sans}`;
    const meta = [card.classroom, card.earned].filter(Boolean).join(' · ');
    ctx.fillText(meta, cx, y + (story ? 10 : 6));
    if (card.level && story) {
        ctx.fillText(card.level, cx, y + 60);
    }

    // Kaki: tagline & tagar.
    ctx.fillStyle = COLORS.accent;
    ctx.font = `800 ${story ? 52 : 40}px ${display}`;
    ctx.fillText(card.tagline, cx, height - (story ? 200 : 95));
    ctx.fillStyle = COLORS.muted;
    ctx.font = `600 ${story ? 32 : 26}px ${sans}`;
    ctx.fillText(card.hashtags, cx, height - (story ? 140 : 50));

    return canvas;
}

export function toBlob(canvas) {
    return new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
}
