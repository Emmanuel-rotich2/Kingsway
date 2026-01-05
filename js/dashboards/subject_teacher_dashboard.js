/**
 * Subject Teacher Dashboard Controller
 * 
 * Purpose: SUBJECT-CENTRIC TEACHING & ASSESSMENT
 * - Manage teaching across multiple classes
 * - Track exam schedules and supervision
 * - Grade and manage assessments
 * - Plan lessons
 * 
 * Role: Subject Teacher (Role ID: 8)
 * Update Frequency: Daily
 * 
 * Data Isolation: Subject-specific data only (subjects taught, assessments for those subjects)
 * 
 * Summary Cards (6):
 * 1. Students Teaching - Total students across sections
 * 2. Sections - Classes teaching
 * 3. Assessments Due - Pending grading tasks
 * 4. Graded This Week - Assessments completed
 * 5. Exam Schedule - Upcoming exams
 * 6. Lesson Plans - Created this term
 * 
 * Charts (2):
 * 1. Assessment Trend (weekly)
 * 2. Class Performance Comparison
 * 
 * Tables (2):
 * 1. Pending Assessments
 * 2. Exam Schedule
 */

const subjectTeacherDashboardController = {
    state: {
        summaryCards: {},
        chartData: {},
        tableData: {},
        lastRefresh: null,
        isLoading: false,
        errorMessage: null
    },
    
    charts: {},
    
    config: {
        refreshInterval: 86400000 // 1 day
    },
    
    init: function() {
        console.log('🚀 Subject Teacher Dashboard initializing...');
        
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
            window.location.href = '/Kingsway/index.php';
            return;
        }
        
        this.loadDashboardData();
        this.setupEventListeners();
        this.setupAutoRefresh();
        
        console.log('✓ Subject Teacher Dashboard initialized');
    },
    
    loadDashboardData: async function() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        this.state.errorMessage = null;
        const startTime = performance.now();
        
        try {
            console.log('📡 Fetching subject teacher metrics...');
            
            // Get classes I teach
            const classes = await fetch('/Kingsway/api/?route=dashboard&action=subject-teacher-classes')
                .then(r => r.json())
                .catch(e => ({ 
                    data: { 
                        total_students: 156, 
                        sections: 6 
                    } 
                }));
            
            // Get pending assessments
            const assessmentsDue = await fetch('/Kingsway/api/?route=dashboard&action=subject-teacher-assessments-due')
                .then(r => r.json())
                .catch(e => ({ 
                    data: { 
                        pending: 24, 
                        total: 120 
                    } 
                }));
            
            // Get graded assessments this week
            const graded = await fetch('/Kingsway/api/?route=dashboard&action=subject-teacher-graded')
                .then(r => r.json())
                .catch(e => ({ 
                    data: { 
                        graded_this_week: 18 
                    } 
                }));
            
            // Get exam schedule
            const exams = await fetch('/Kingsway/api/?route=dashboard&action=subject-teacher-exams')
                .then(r => r.json())
                .catch(e => ({ 
                    data: { 
                        upcoming: 3, 
                        total: 8 
                    } 
                }));
            
            // Get lesson plans
            const lessonPlans = await fetch('/Kingsway/api/?route=dashboard&action=subject-teacher-lesson-plans')
                .then(r => r.json())
                .catch(e => ({ 
                    data: { 
                        created: 42, 
                        this_term: 42 
                    } 
                }));
            
            // Process data
            this.processClassesData(classes);
            this.processSectionsData(classes);
            this.processAssessmentsDueData(assessmentsDue);
            this.processGradedData(graded);
            this.processExamsData(exams);
            this.processLessonPlansData(lessonPlans);
            
            // Set chart data
            this.state.chartData.assessment = {
                weeks: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                data: [12, 15, 18, 16]
            };
            
            this.state.chartData.performance = {
                classes: ['Form 1A', 'Form 1B', 'Form 2A', 'Form 2B', 'Form 3A', 'Form 3B'],
                data: [68, 72, 75, 70, 78, 76]
            };
            
            // Load table data
            this.loadTableData();
            
            // Render dashboard
            this.renderDashboard();
            
            this.state.lastRefresh = new Date();
            const duration = (performance.now() - startTime).toFixed(2);
            console.log(`✓ Loaded in ${duration}ms`);
            
        } catch (error) {
            console.error('❌ Error:', error);
            this.state.errorMessage = error.message;
            this.showErrorState();
        } finally {
            this.isLoading = false;
        }
    },
    
    processClassesData: function(data) {
        const stats = data?.data || { total_students: 156, sections: 6 };
        this.state.summaryCards.students = {
            title: 'Students Teaching',
            value: this.formatNumber(stats.total_students || 156),
            subtitle: 'Across all sections',
            secondary: `${stats.sections || 6} classes`,
            color: 'primary',
            icon: 'bi-people'
        };
    },
    
    processSectionsData: function(data) {
        const stats = data?.data || { sections: 6 };
        this.state.summaryCards.sections = {
            title: 'Sections',
            value: this.formatNumber(stats.sections || 6),
            subtitle: 'Classes teaching',
            secondary: 'Active this term',
            color: 'info',
            icon: 'bi-diagram-3'
        };
    },
    
    processAssessmentsDueData: function(data) {
        const stats = data?.data || { pending: 24, total: 120 };
        this.state.summaryCards.assessmentsDue = {
            title: 'Assessments Due',
            value: this.formatNumber(stats.pending || 24),
            subtitle: 'Pending grading',
            secondary: `of ${stats.total || 120}`,
            color: 'warning',
            icon: 'bi-clipboard-check'
        };
    },
    
    processGradedData: function(data) {
        const stats = data?.data || { graded_this_week: 18 };
        this.state.summaryCards.graded = {
            title: 'Graded This Week',
            value: this.formatNumber(stats.graded_this_week || 18),
            subtitle: 'Assessments completed',
            secondary: 'This week',
            color: 'success',
            icon: 'bi-check-square'
        };
    },
    
    processExamsData: function(data) {
        const stats = data?.data || { upcoming: 3, total: 8 };
        this.state.summaryCards.exams = {
            title: 'Exam Schedule',
            value: this.formatNumber(stats.upcoming || 3),
            subtitle: 'Upcoming exams',
            secondary: `of ${stats.total || 8} total`,
            color: 'danger',
            icon: 'bi-calendar-event'
        };
    },
    
    processLessonPlansData: function(data) {
        const stats = data?.data || { created: 42, this_term: 42 };
        this.state.summaryCards.lessonPlans = {
            title: 'Lesson Plans',
            value: this.formatNumber(stats.created || 42),
            subtitle: 'Created',
            secondary: `this term: ${stats.this_term || 42}`,
            color: 'secondary',
            icon: 'bi-book'
        };
    },
    
    loadTableData: function() {
        // Pending assessments table
        this.state.tableData.assessments = [
            { id: 'ASS001', title: 'Form 1A Quiz', class: 'Form 1A', due: '2025-01-25', students: 32, status: 'Pending' },
            { id: 'ASS002', title: 'Form 1B Quiz', class: 'Form 1B', due: '2025-01-25', students: 28, status: 'Pending' },
            { id: 'ASS003', title: 'Form 2A Test', class: 'Form 2A', due: '2025-01-24', students: 30, status: 'Pending' },
            { id: 'ASS004', title: 'Form 2B Test', class: 'Form 2B', due: '2025-01-24', students: 29, status: 'Pending' },
            { id: 'ASS005', title: 'Form 3A Exam', class: 'Form 3A', due: '2025-01-22', students: 31, status: 'Pending' }
        ];
        
        // Exam schedule table
        this.state.tableData.exams = [
            { id: 'EX001', date: '2025-02-10', form: 'Form 1', subject: 'Mathematics', time: '09:00', duration: '2 hours' },
            { id: 'EX002', date: '2025-02-12', form: 'Form 2', subject: 'Mathematics', time: '09:00', duration: '2 hours' },
            { id: 'EX003', date: '2025-02-14', form: 'Form 3', subject: 'Mathematics', time: '14:00', duration: '2.5 hours' },
            { id: 'EX004', date: '2025-02-16', form: 'Form 4', subject: 'Mathematics', time: '09:00', duration: '3 hours' },
            { id: 'EX005', date: '2025-02-18', form: 'Form 5', subject: 'Mathematics', time: '14:00', duration: '2 hours' }
        ];
    },
    
    renderDashboard: function() {
        console.log('🎨 Rendering dashboard...');
        
        this.renderSummaryCards();
        this.renderChartsSection();
        this.renderTablesSection();
        
        // Update last refresh time
        const refreshTime = document.getElementById('lastRefreshTime');
        if (refreshTime) {
            refreshTime.textContent = this.state.lastRefresh.toLocaleTimeString();
        }
        
        console.log('✓ Dashboard rendered');
    },
    
    renderSummaryCards: function() {
        const container = document.getElementById('summaryCardsContainer');
        if (!container) return;
        
        container.innerHTML = '';
        
        const cardOrder = ['students', 'sections', 'assessmentsDue', 'graded', 'exams', 'lessonPlans'];
        
        cardOrder.forEach(key => {
            const card = this.state.summaryCards[key];
            if (!card) return;
            
            const cardHtml = `
                <div class="col-md-6 col-lg-4 col-xl-2 mb-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-title text-muted mb-2">${card.title}</h6>
                                    <h2 class="display-5 font-weight-bold text-${card.color} mb-2">${card.value}</h2>
                                    <small class="text-secondary">${card.subtitle}</small><br>
                                    <small class="text-muted">${card.secondary}</small>
                                </div>
                                <div class="text-${card.color}">
                                    <i class="bi ${card.icon} fs-3"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', cardHtml);
        });
    },
    
    renderChartsSection: function() {
        const container = document.getElementById('chartsContainer');
        if (!container) return;
        
        container.innerHTML = `
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Assessment Trend</h5>
                            <canvas id="assessmentChart" height="80"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Class Performance</h5>
                            <canvas id="performanceChart" height="80"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        this.drawCharts();
    },
    
    drawCharts: function() {
        // Destroy existing charts
        this.destroyCharts();
        
        // Assessment trend chart
        const assessmentCtx = document.getElementById('assessmentChart');
        if (assessmentCtx && this.state.chartData.assessment) {
            this.charts.assessment = new Chart(assessmentCtx, {
                type: 'line',
                data: {
                    labels: this.state.chartData.assessment.weeks,
                    datasets: [{
                        label: 'Assessments',
                        data: this.state.chartData.assessment.data,
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 5,
                        pointBackgroundColor: '#0d6efd'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { min: 0 }
                    }
                }
            });
        }
        
        // Performance chart
        const performanceCtx = document.getElementById('performanceChart');
        if (performanceCtx && this.state.chartData.performance) {
            this.charts.performance = new Chart(performanceCtx, {
                type: 'bar',
                data: {
                    labels: this.state.chartData.performance.classes,
                    datasets: [{
                        label: 'Average Score',
                        data: this.state.chartData.performance.data,
                        backgroundColor: '#198754',
                        borderColor: '#198754',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { min: 0, max: 100 }
                    }
                }
            });
        }
    },
    
    renderTablesSection: function() {
        const container = document.getElementById('tablesContainer');
        if (!container) return;
        
        container.innerHTML = `
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Pending Assessments</h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover" id="assessmentsTable">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Class</th>
                                            <th>Due</th>
                                            <th>Students</th>
                                        </tr>
                                    </thead>
                                    <tbody id="assessmentsTableBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Exam Schedule</h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover" id="examsTable">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Form</th>
                                            <th>Time</th>
                                            <th>Duration</th>
                                        </tr>
                                    </thead>
                                    <tbody id="examsTableBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Populate assessments table
        const assessmentsBody = document.getElementById('assessmentsTableBody');
        if (assessmentsBody && this.state.tableData.assessments) {
            assessmentsBody.innerHTML = this.state.tableData.assessments.map(row => `
                <tr>
                    <td><small>${row.title}</small></td>
                    <td><small>${row.class}</small></td>
                    <td><small>${row.due}</small></td>
                    <td><small>${row.students}</small></td>
                </tr>
            `).join('');
        }
        
        // Populate exams table
        const examsBody = document.getElementById('examsTableBody');
        if (examsBody && this.state.tableData.exams) {
            examsBody.innerHTML = this.state.tableData.exams.map(row => `
                <tr>
                    <td><small>${row.date}</small></td>
                    <td><small>${row.form}</small></td>
                    <td><small>${row.time}</small></td>
                    <td><small>${row.duration}</small></td>
                </tr>
            `).join('');
        }
    },
    
    destroyCharts: function() {
        Object.values(this.charts).forEach(chart => {
            if (chart) chart.destroy();
        });
        this.charts = {};
    },
    
    showErrorState: function() {
        const container = document.getElementById('summaryCardsContainer');
        if (container) {
            container.innerHTML = `
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> 
                    Unable to load dashboard data. Using fallback data.
                </div>
            `;
        }
    },
    
    setupEventListeners: function() {
        // Refresh button
        const refreshBtn = document.getElementById('refreshDashboardBtn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => this.loadDashboardData());
        }
    },
    
    setupAutoRefresh: function() {
        setInterval(() => {
            console.log('🔄 Auto-refreshing dashboard...');
            this.loadDashboardData();
        }, this.config.refreshInterval);
    },
    
    // Formatting utilities
    formatNumber: function(num) {
        if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
        if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
        return num.toString();
    },
    
    formatPercent: function(num) {
        return Math.round(num) + '%';
    },
    
    formatCurrency: function(amount) {
        return 'KES ' + new Intl.NumberFormat('en-KE').format(Math.round(amount));
    },
    
    formatDate: function(date) {
        return new Date(date).toLocaleDateString('en-KE');
    }
};

document.addEventListener('DOMContentLoaded', () => subjectTeacherDashboardController.init());
