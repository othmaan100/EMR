/**
 * Vital-sign trend charts. One chart per measure, one y-axis each.
 *
 * <canvas data-trend-chart='{"title":..,"unit":..,"labels":[..],"series":[{label,data}]}'>
 * Chart.js is loaded on demand so pages without charts don't pay for it.
 */
const SERIES_COLORS = ['#2a78d6', '#eb6834']; // reference categorical slots 1 & 2
const TEXT_SECONDARY = '#52514e';
const GRID = '#e8e8e6';

/**
 * Horizontal bars for ranked categories (top diagnoses, visits per clinic…).
 * Thin bars with a 4px rounded data end; single hue unless multi-series.
 */
function renderBar(Chart, canvas, cfg, multi) {
    const fmt = (v) => (v ?? 0).toLocaleString(undefined, { maximumFractionDigits: 1 });

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: cfg.labels,
            datasets: cfg.series.map((s, i) => ({
                label: s.label,
                data: s.data,
                backgroundColor: SERIES_COLORS[i],
                borderRadius: 4,
                borderSkipped: 'start',
                barPercentage: 0.75,
                categoryPercentage: 0.9,
                maxBarThickness: 18,
            })),
        },
        options: {
            indexAxis: cfg.horizontal ? 'y' : 'x',
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            interaction: { mode: 'nearest', axis: cfg.horizontal ? 'y' : 'x', intersect: false },
            plugins: {
                legend: { display: multi, labels: { color: TEXT_SECONDARY } },
                tooltip: { callbacks: { label: (ctx) => ` ${fmt(cfg.horizontal ? ctx.parsed.x : ctx.parsed.y)} ${cfg.unit}` } },
            },
            scales: {
                x: {
                    ticks: { color: TEXT_SECONDARY },
                    grid: { color: cfg.horizontal ? GRID : 'transparent' },
                    border: { display: false },
                    title: { display: cfg.horizontal, text: cfg.unit, color: TEXT_SECONDARY },
                },
                y: {
                    ticks: { color: TEXT_SECONDARY, autoSkip: false },
                    grid: { color: cfg.horizontal ? 'transparent' : GRID },
                    border: { display: false },
                },
            },
        },
    });
}

/**
 * Partograph: measurements against hours in labour on a linear time axis.
 * Reference lines (alert/action, normal limits) are neutral grey so they
 * never compete with the measured series; the legend names them.
 */
function renderPartograph(Chart, canvas, cfg) {
    const REF = ['#8a8986', '#52514e'];

    new Chart(canvas, {
        type: 'line',
        data: {
            datasets: [
                ...cfg.series.map((s, i) => ({
                    label: s.label,
                    data: s.data,
                    borderColor: SERIES_COLORS[i],
                    backgroundColor: SERIES_COLORS[i],
                    borderWidth: 2,
                    pointRadius: 4,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    tension: 0,
                })),
                ...(cfg.reference || []).map((r, i) => ({
                    label: r.label,
                    data: r.data,
                    borderColor: REF[i % REF.length],
                    backgroundColor: REF[i % REF.length],
                    borderWidth: 1.5,
                    borderDash: r.dash,
                    pointRadius: 0,
                    tension: 0,
                })),
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            interaction: { mode: 'nearest', intersect: false },
            plugins: {
                legend: { display: (cfg.reference || []).length > 0, position: 'top', align: 'end', labels: { color: TEXT_SECONDARY, boxWidth: 14 } },
                tooltip: {
                    filter: (item) => item.datasetIndex < cfg.series.length,
                    callbacks: {
                        title: (items) => `${Number(items[0].parsed.x).toFixed(1)} h`,
                        label: (ctx) => ` ${ctx.dataset.label}: ${ctx.parsed.y} ${cfg.unit}`,
                    },
                },
            },
            scales: {
                x: { type: 'linear', min: 0, title: { display: true, text: 'Hours', color: TEXT_SECONDARY }, ticks: { color: TEXT_SECONDARY, stepSize: 1 }, grid: { color: GRID } },
                y: { min: cfg.min, max: cfg.max, title: { display: true, text: cfg.unit, color: TEXT_SECONDARY }, ticks: { color: TEXT_SECONDARY }, grid: { color: GRID }, border: { display: false } },
            },
        },
    });
}

export async function initTrendCharts() {
    const canvases = document.querySelectorAll('canvas[data-trend-chart]');
    if (!canvases.length) return;

    const { Chart, LineController, LineElement, PointElement, BarController, BarElement, LinearScale, CategoryScale, Tooltip, Legend } =
        await import('chart.js');
    Chart.register(LineController, LineElement, PointElement, BarController, BarElement, LinearScale, CategoryScale, Tooltip, Legend);

    canvases.forEach((canvas) => {
        const cfg = JSON.parse(canvas.dataset.trendChart);
        const multi = cfg.series.length > 1;

        if (cfg.type === 'bar') {
            renderBar(Chart, canvas, cfg, multi);
            return;
        }
        if (cfg.type === 'partograph') {
            renderPartograph(Chart, canvas, cfg);
            return;
        }

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: cfg.labels,
                datasets: cfg.series.map((s, i) => ({
                    label: s.label,
                    data: s.data,
                    borderColor: SERIES_COLORS[i],
                    backgroundColor: SERIES_COLORS[i],
                    borderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    tension: 0,
                    spanGaps: true,
                })),
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                // Crosshair-style hover: every series at the hovered time.
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    // A legend only when there is more than one series; the title names a single one.
                    legend: {
                        display: multi,
                        position: 'top',
                        align: 'end',
                        labels: { color: TEXT_SECONDARY, boxWidth: 10, boxHeight: 10, usePointStyle: true },
                    },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => ` ${ctx.dataset.label}: ${ctx.parsed.y ?? '—'} ${cfg.unit}`,
                        },
                    },
                },
                scales: {
                    x: {
                        ticks: { color: TEXT_SECONDARY, maxRotation: 0, autoSkip: true, maxTicksLimit: 6 },
                        grid: { display: false },
                    },
                    y: {
                        min: cfg.min,
                        max: cfg.max,
                        title: { display: true, text: cfg.unit, color: TEXT_SECONDARY },
                        ticks: { color: TEXT_SECONDARY },
                        grid: { color: GRID },
                        border: { display: false },
                    },
                },
            },
        });
    });
}
