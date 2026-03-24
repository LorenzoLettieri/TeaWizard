import {
    ArcElement,
    BarElement,
    BarController,
    CategoryScale,
    Chart,
    DoughnutController,
    Legend,
    LinearScale,
    Tooltip,
} from 'chart.js';

Chart.register(
    ArcElement,
    BarElement,
    BarController,
    CategoryScale,
    DoughnutController,
    Legend,
    LinearScale,
    Tooltip,
);

window.TeaWizardCharts = {
    render(canvas, config) {
        if (!canvas) {
            return null;
        }

        const existingChart = Chart.getChart(canvas);

        if (existingChart) {
            existingChart.destroy();
        }

        return new Chart(canvas, config);
    },
};
