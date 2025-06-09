// Admin Dashboard JavaScript

async function fetchAdminDashboardData() {
    try {
        const response = await window.API.reports.getDashboardStats();
        if (response && response.data) {
            updateDashboardStats(response.data.stats);
            updateCharts(response.data);
            updateTables(response.data);
        }
    } catch (error) {
        console.error('Error fetching dashboard data:', error);
        window.API.showNotification('Failed to load dashboard data', 'error');
    }
}

function updateDashboardStats(stats) {
    const cards = [
        {
            title: 'Total Students',
            count: stats.students.total,
            percent: stats.students.growth,
            icon: 'bi-people-fill',
            bgColor: '#6f42c1'
        },
        {
            title: 'Total Staff',
            count: stats.staff.total,
            percent: stats.staff.growth,
            icon: 'bi-person-badge-fill',
            bgColor: '#198754'
        },
        {
            title: 'Revenue (KES)',
            count: stats.revenue.total,
            percent: stats.revenue.growth,
            icon: 'bi-currency-dollar',
            bgColor: '#0d6efd'
        },
        {
            title: 'System Users',
            count: stats.users.total,
            percent: stats.users.growth,
            icon: 'bi-person-circle',
            bgColor: '#fd7e14'
        }
    ];

    let html = '';
    cards.forEach(card => {
        html += `
            <div class="col-md-3">
                <div class="card text-white mb-3" style="background-color:${card.bgColor}">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <i class="bi ${card.icon} fs-2 me-3"></i>
                            <div>
                                <h5 class="card-title mb-0">${card.title}</h5>
                                <h3>${card.count.toLocaleString()}</h3>
                                <small class="text-${card.percent >= 0 ? 'success' : 'danger'}">
                                    ${card.percent >= 0 ? '+' : ''}${card.percent}% vs last month
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    document.getElementById('admin-summary-cards').innerHTML = html;
}

function updateCharts(data) {
    const { enrollment, revenue } = data.charts || {};
    
    // Update enrollment chart
    if (enrollment && window.enrollmentChart) {
        window.enrollmentChart.data = {
            labels: enrollment.labels,
            datasets: [{
                label: 'New Enrollments',
                data: enrollment.data,
                borderColor: '#4e73df',
                fill: false
            }]
        };
        window.enrollmentChart.update();
    }
    
    // Update revenue chart
    if (revenue && window.revenueChart) {
        window.revenueChart.data = {
            labels: revenue.labels,
            datasets: [{
                data: revenue.data,
                backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc']
            }]
        };
        window.revenueChart.update();
    }
}

function updateTables(data) {
    const { activities } = data.tables || {};
    
    // Update activities table
    if (activities) {
        const tbody = document.querySelector('#activities-table tbody');
        if (tbody) {
            tbody.innerHTML = activities.map(activity => `
                <tr>
                    <td>${activity.user}</td>
                    <td>${activity.action}</td>
                    <td>${activity.module}</td>
                    <td>${activity.timestamp}</td>
                </tr>
            `).join('');
        }
    }
}

// Initialize dashboard
document.addEventListener('DOMContentLoaded', function() {
    // Initialize charts
    const enrollmentCtx = document.getElementById('enrollmentChart');
    if (enrollmentCtx) {
        window.enrollmentChart = new Chart(enrollmentCtx, {
            type: 'line',
            data: { labels: [], datasets: [] },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }
    
    const revenueCtx = document.getElementById('revenueChart');
    if (revenueCtx) {
        window.revenueChart = new Chart(revenueCtx, {
            type: 'doughnut',
            data: { labels: [], datasets: [] },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }
    
    // Fetch initial data
    fetchAdminDashboardData();
    
    // Set up refresh interval
    setInterval(fetchAdminDashboardData, 30000); // Refresh every 30 seconds
}); 