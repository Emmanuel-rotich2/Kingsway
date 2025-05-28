// Chart.js configuration for the dashboard
import Chart from 'chart.js/auto';
// Ensure Chart.js is available globally
if (typeof window !== 'undefined') {
    window.Chart = Chart;
}
document.addEventListener("DOMContentLoaded", () => {
    // Bar Chart
    new Chart(document.getElementById('barChart'), {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [
                {
                    label: 'Course Visit',
                    data: [30, 40, 45, 50, 60, 55, 70, 68, 90, 75, 80, 100],
                    backgroundColor: '#6f42c1'
                },
                {
                    label: 'Course Sales',
                    data: [15, 20, 25, 30, 35, 33, 45, 43, 50, 47, 49, 60],
                    backgroundColor: '#20c997'
                }
            ]
        }
    });

    // Doughnut Charts
    const doughnutOptions = {
        cutout: '80%',
        borderWidth: 0
    };

    new Chart(document.getElementById('doughnutChart'), {
        type: 'doughnut',
        data: {
            labels: ['Total Sale'],
            datasets: [{
                data: [4500, 500],
                backgroundColor: ['#6f42c1', '#e9ecef']
            }]
        },
        options: doughnutOptions
    });

    new Chart(document.getElementById('incomeGauge'), {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [75, 25],
                backgroundColor: ['#20c997', '#e9ecef']
            }]
        },
        options: doughnutOptions
    });

    new Chart(document.getElementById('withdrawGauge'), {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [56, 44],
                backgroundColor: ['#dc3545', '#e9ecef']
            }]
        },
        options: doughnutOptions
    });

    new Chart(document.getElementById('lineChart'), {
        type: 'line',
        data: {
            labels: ['Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
            datasets: [{
                label: 'Working Activity',
                data: [30, 40, 70, 90, 65, 70, 60],
                borderColor: '#6f42c1',
                backgroundColor: 'rgba(111,66,193,0.2)',
                tension: 0.4,
                fill: true
            }]
        }
    });
});
