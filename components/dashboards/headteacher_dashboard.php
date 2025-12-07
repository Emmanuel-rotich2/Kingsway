<?php
// Head Teacher Dashboard
require_once __DIR__ . '/../../components/global/dashboard_base.php';

// Initialize empty arrays for data
$summaryCards = [];
$performanceData = [];
$attendanceData = [];
$activities = [];
?>

<div class="container-fluid py-4">
    <!-- Summary Cards -->
    <div class="row g-3" id="head-teacher-summary-cards"></div>

    <!-- Charts -->
    <div class="row mt-4">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Academic Performance Trend</h5>
                    <canvas id="performanceChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Attendance Overview</h5>
                    <canvas id="attendanceChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Activities and Staff -->
    <div class="row mt-4">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Upcoming Activities</h5>
                    <div id="activities-list" class="list-group list-group-flush"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Staff Overview</h5>
                    <div id="staff-overview" class="list-group list-group-flush"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    fetchHeadTeacherDashboardData();
    // Refresh every 30 seconds
    setInterval(fetchHeadTeacherDashboardData, 30000);
});

function fetchHeadTeacherDashboardData() {
    window.API.reports.getDashboardStats()
        .then(response => {
            if (response.status === 'success') {
                const data = response.data;
                
                // Update summary cards
                const cards = [
                    {
                        title: 'Total Students',
                        count: data.students.total || 0,
                        percent: data.students.growth || 0,
                        icon: 'bi-people-fill',
                        bgColor: '#6f42c1'
                    },
                    {
                        title: 'Teaching Staff',
                        count: data.staff.teaching || 0,
                        percent: data.staff.growth || 0,
                        icon: 'bi-person-badge-fill',
                        bgColor: '#0d6efd'
                    },
                    {
                        title: 'Attendance Rate',
                        count: data.attendance.rate || 0,
                        percent: data.attendance.rate - 95 || 0, // Compare to target of 95%
                        icon: 'bi-person-check-fill',
                        bgColor: '#198754'
                    },
                    {
                        title: 'Average Score',
                        count: data.academic?.average_score || 0,
                        percent: data.academic?.score_growth || 0,
                        icon: 'bi-graph-up',
                        bgColor: '#20c997'
                    }
                ];

                let cardsHtml = '';
                cards.forEach(card => {
                    cardsHtml += `
                        <div class="col-md-3">
                            <div class="card text-white" style="background-color: ${card.bgColor}">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <i class="bi ${card.icon} fs-2 me-3"></i>
                                        <div>
                                            <h6 class="card-title mb-0">${card.title}</h6>
                                            <h3 class="mb-0">${card.count.toLocaleString()}</h3>
                                            <small>${card.percent >= 0 ? '+' : ''}${card.percent}%</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
                document.getElementById('head-teacher-summary-cards').innerHTML = cardsHtml;

                // Update activities list
                let activitiesHtml = '';
                (data.activities || []).forEach(activity => {
                    activitiesHtml += `
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">${activity.title}</h6>
                                <small>${new Date(activity.start_date).toLocaleDateString()}</small>
                            </div>
                            <p class="mb-1">${activity.description}</p>
                            <small>Venue: ${activity.venue}</small>
                        </div>
                    `;
                });
                document.getElementById('activities-list').innerHTML = activitiesHtml || '<p class="text-muted m-3">No upcoming activities</p>';

                // Update staff overview
                const staffOverview = [
                    {
                        title: 'Teaching Staff',
                        count: data.staff.teaching || 0,
                        icon: 'bi-person-workspace'
                    },
                    {
                        title: 'Present Today',
                        count: data.staff.present || 0,
                        icon: 'bi-person-check'
                    },
                    {
                        title: 'On Leave',
                        count: data.staff.on_leave || 0,
                        icon: 'bi-calendar2-minus'
                    },
                    {
                        title: 'Classes Today',
                        count: data.schedules?.length || 0,
                        icon: 'bi-journal-bookmark'
                    }
                ];

                let staffHtml = '';
                staffOverview.forEach(item => {
                    staffHtml += `
                        <div class="list-group-item">
                            <div class="d-flex align-items-center">
                                <i class="bi ${item.icon} fs-4 me-3"></i>
                                <div>
                                    <h6 class="mb-0">${item.title}</h6>
                                    <strong>${item.count}</strong>
                                </div>
                            </div>
                        </div>
                    `;
                });
                document.getElementById('staff-overview').innerHTML = staffHtml;
            }
        })
        .catch(error => {
            console.error('Error fetching dashboard data:', error);
            window.API.showNotification('Failed to load dashboard data', 'error');
        });
}
</script>
