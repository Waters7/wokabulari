// Grafik Chart.js dengan palet Wokabulari.
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

export const palette = ['#007D71', '#C2CC0F', '#1ABBCB', '#43BA7D', '#C8312A', '#0B3B38', '#2563EB', '#9333EA'];

function isDark() {
    return document.documentElement.classList.contains('dark');
}

export function createChart(canvas, config) {
    const text = isDark() ? '#F5FBFA' : '#0B3B38';
    const grid = isDark() ? 'rgba(255,251,242,0.12)' : 'rgba(18,59,45,0.08)';

    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.color = text;

    const datasets = (config.data?.datasets ?? []).map((dataset, i) => ({
        borderColor: palette[i % palette.length],
        backgroundColor: config.type === 'radar' || config.type === 'line' ? palette[i % palette.length] + '33' : palette[i % palette.length],
        pointBackgroundColor: palette[i % palette.length],
        tension: 0.3,
        spanGaps: true,
        ...dataset,
    }));

    const scales = config.type === 'radar'
        ? { r: { min: 0, max: 100, ticks: { stepSize: 20, backdropColor: 'transparent' }, grid: { color: grid }, angleLines: { color: grid } } }
        : config.type === 'doughnut' || config.type === 'pie'
            ? {}
            : { x: { grid: { color: grid } }, y: { beginAtZero: true, grid: { color: grid }, ...(config.max ? { max: config.max } : {}) } };

    return new Chart(canvas, {
        type: config.type,
        data: { ...config.data, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } },
            scales,
            ...(config.options ?? {}),
        },
    });
}
