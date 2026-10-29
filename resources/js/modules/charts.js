// Grafik Chart.js dengan palet Wokabulari.
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

export const palette = ['#007D71', '#C2CC0F', '#1ABBCB', '#43BA7D', '#C8312A', '#0B3B38', '#2563EB', '#9333EA'];

// Palet seri yang lolos uji buta warna & kontras (dipakai dashboard eksekutif: palette: 'validated').
// Urutan tetap: seri ke-n selalu warna ke-n; maksimal 4 seri per grafik.
const validated = {
    light: ['#009b8a', '#eb6834', '#2a78d6', '#eda100'],
    dark: ['#14a594', '#d95926', '#3987e5', '#c98500'],
};

// Garis acuan (target, batas lulus): abu-abu putus-putus, bukan warna seri.
const referenceColor = '#898781';

function isDark() {
    return document.documentElement.classList.contains('dark');
}

export function createChart(canvas, config) {
    const text = isDark() ? '#F5FBFA' : '#0B3B38';
    const grid = isDark() ? 'rgba(255,251,242,0.12)' : 'rgba(18,59,45,0.08)';
    const strict = config.palette === 'validated';
    const colors = strict ? validated[isDark() ? 'dark' : 'light'] : palette;

    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.color = text;

    let slot = 0;
    const datasets = (config.data?.datasets ?? []).map((dataset) => {
        if (dataset.reference) {
            const { reference, ...rest } = dataset;

            return { type: 'line', borderColor: referenceColor, backgroundColor: referenceColor, borderDash: [6, 4], borderWidth: 1.5, pointRadius: 0, pointHoverRadius: 0, fill: false, ...rest };
        }

        const color = colors[slot++ % colors.length];
        const marks = strict && config.type === 'bar'
            ? { borderRadius: 4, borderSkipped: 'start', maxBarThickness: 28 }
            : strict && config.type === 'line' ? { borderWidth: 2, pointRadius: 4, pointHoverRadius: 6 } : {};

        return {
            borderColor: color,
            backgroundColor: config.type === 'radar' || config.type === 'line' ? color + '33' : color,
            pointBackgroundColor: color,
            tension: 0.3,
            spanGaps: true,
            ...marks,
            ...dataset,
        };
    });

    const horizontal = config.options?.indexAxis === 'y';
    const valueAxis = { beginAtZero: true, grid: { color: grid }, ...(config.max ? { max: config.max } : {}) };
    const scales = config.type === 'radar'
        ? { r: { min: 0, max: 100, ticks: { stepSize: 20, backdropColor: 'transparent' }, grid: { color: grid }, angleLines: { color: grid } } }
        : config.type === 'doughnut' || config.type === 'pie'
            ? {}
            : horizontal
                ? { x: valueAxis, y: { grid: { display: false } } }
                : { x: { grid: { color: grid, display: !strict } }, y: valueAxis };

    // Satu seri tidak perlu kotak legenda (judul grafik sudah menamainya).
    const series = datasets.filter((d) => d.type !== 'line' || config.type === 'line').length;
    const unit = config.unit ?? '';

    return new Chart(canvas, {
        type: config.type,
        data: { ...config.data, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            ...(strict && config.type === 'line' ? { interaction: { mode: 'index', intersect: false } } : {}),
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12 }, display: !(strict && series <= 1 && datasets.length === 1) },
                ...(unit ? { tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${ctx.formattedValue}${unit}` } } } : {}),
            },
            scales,
            ...(config.options ?? {}),
        },
    });
}
