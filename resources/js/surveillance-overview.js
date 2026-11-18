const palette = {
    blue: '#74bfdc',
    pink: '#ed91b5',
    amber: '#d99631',
    gray: '#a0a9b8',
    ink: '#293952',
    muted: '#727d91',
    line: '#edf0f5',
};

const signalColor = (outcome) => outcome === 'Alert'
    ? palette.pink
    : ['Signal', 'Watch'].includes(outcome) ? palette.amber : palette.blue;

export default (payload) => {
    let instances = [];
    let disposed = false;

    return {
        loading: true,
        failed: false,

        init() {
            this.$nextTick(async () => {
                try {
                    if (!payload.comparison.labels.length && !payload.trend.labels.length) return;
                    const { default: Chart } = await import('chart.js/auto');
                    if (disposed || !this.$el.isConnected) return;
                    this.draw(Chart);
                } catch (error) {
                    if (!disposed) this.failed = true;
                    console.error('Unable to display surveillance charts.', error);
                } finally {
                    if (!disposed) this.loading = false;
                }
            });
        },

        draw(Chart) {
            instances.forEach((chart) => chart.destroy());
            instances = [];

            const options = (horizontal = false) => ({
                responsive: true,
                maintainAspectRatio: false,
                animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 250 },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: palette.muted, usePointStyle: true, boxWidth: 8, padding: 20, font: { family: 'Figtree, sans-serif', size: 11 } },
                    },
                    tooltip: { backgroundColor: palette.ink, padding: 12, cornerRadius: 8 },
                },
                scales: {
                    x: {
                        beginAtZero: horizontal,
                        grid: { display: horizontal, color: palette.line },
                        border: { display: false },
                        ticks: { color: palette.muted, maxRotation: 0, maxTicksLimit: horizontal ? 6 : 4, ...(horizontal ? { precision: 0 } : {}) },
                        ...(horizontal ? { title: { display: true, text: 'Qualifying cases', color: palette.muted } } : {}),
                    },
                    y: {
                        beginAtZero: !horizontal,
                        grid: { display: !horizontal, color: palette.line },
                        border: { display: false },
                        ticks: { color: palette.muted, ...(horizontal ? { font: { size: 11 }, autoSkip: false } : { precision: 0 }) },
                        ...(!horizontal ? { title: { display: true, text: 'Cases per 48-hour window', color: palette.muted } } : {}),
                    },
                },
            });

            if (this.$refs.trend && payload.trend.labels.length) {
                Chart.getChart(this.$refs.trend)?.destroy();
                instances.push(new Chart(this.$refs.trend, {
                    type: 'line',
                    data: {
                        labels: payload.trend.labels,
                        datasets: [
                            { label: 'Observed cases', data: payload.trend.observed, borderColor: palette.blue, backgroundColor: '#a8dcf026', fill: true, tension: 0.2, borderWidth: 2.5, pointRadius: 4, pointHoverRadius: 6, pointBackgroundColor: payload.trend.outcomes.map(signalColor) },
                            { label: 'Historical expected', data: payload.trend.expected, borderColor: palette.gray, backgroundColor: palette.gray, borderDash: [5, 5], borderWidth: 2, pointRadius: 2, spanGaps: false },
                            { label: 'Saved minimum', data: payload.trend.minimum, borderColor: palette.pink, backgroundColor: palette.pink, borderDash: [3, 4], borderWidth: 1.5, pointRadius: 2, spanGaps: false },
                        ],
                    },
                    options: options(),
                }));
            }

            if (this.$refs.comparison && payload.comparison.labels.length) {
                Chart.getChart(this.$refs.comparison)?.destroy();
                instances.push(new Chart(this.$refs.comparison, {
                    type: 'bar',
                    data: {
                        labels: payload.comparison.labels,
                        datasets: [
                            { label: 'Observed cases', data: payload.comparison.observed, backgroundColor: payload.comparison.outcomes.map(signalColor), borderRadius: 4, maxBarThickness: 22 },
                            { label: 'Historical expected', data: payload.comparison.expected, backgroundColor: '#dce2eb', borderRadius: 4, maxBarThickness: 22 },
                        ],
                    },
                    options: { ...options(true), indexAxis: 'y' },
                }));
            }
        },

        destroy() {
            disposed = true;
            instances.forEach((chart) => chart.destroy());
            instances = [];
        },
    };
};
