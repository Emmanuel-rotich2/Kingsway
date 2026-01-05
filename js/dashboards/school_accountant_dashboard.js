/**
 * School Accountant Dashboard Controller
 * Manages School Accountant dashboard using api.js for real-time data
 */

const accountantDashboardController = {
    dashboardData: {},
    refreshInterval: 30000,
    isLoading: false,
    
    init: function() {
        if (!AuthContext.isAuthenticated()) {
            window.location.href = '/Kingsway/index.php';
            return;
        }
        
        this.loadDashboardData();
        this.attachEventListeners();
        this.setupAutoRefresh();
        console.log('School Accountant Dashboard initialized');
    },
    
    loadDashboardData: async function() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        try {
            const stats = await window.API.apiCall('/dashboard/accountant/financial', 'GET');
            if (stats) {
                this.dashboardData.stats = stats;
                this.updateStatistics();
            }
            
            const additionalData = await window.API.apiCall('/dashboard/accountant/payments', 'GET');
            if (additionalData) {
                this.dashboardData.additional = additionalData;
                this.updateAdditionalData();
            }
            
            const refreshTime = document.getElementById('lastRefreshTime');
            if (refreshTime) {
                refreshTime.textContent = new Date().toLocaleTimeString();
            }
        } catch (error) {
            console.error('Error loading dashboard data:', error);
            showNotification('Error loading dashboard data: ' + error.message, 'error');
        } finally {
            this.isLoading = false;
        }
    },
    
    updateStatistics: function() {
        const stats = this.dashboardData.stats;
        if (!stats) return;
        
        Object.keys(stats).forEach(key => {
            const element = document.getElementById(key) || document.querySelector(`[data-stat="${key}"]`);
            if (element) {
                if (typeof stats[key] === 'number') {
                    element.textContent = this.formatNumber(stats[key]);
                } else {
                    element.textContent = stats[key];
                }
            }
        });
    },
    
    updateAdditionalData: function() {
        const data = this.dashboardData.additional;
        if (!data) return;
        
        if (data.chartData) {
            this.updateCharts(data.chartData);
        }
        
        if (data.tableData) {
            this.updateTables(data.tableData);
        }
    },
    
    updateCharts: function(chartData) {
        console.log('Updating charts with:', chartData);
    },
    
    updateTables: function(tableData) {
        Object.keys(tableData).forEach(tableId => {
            const table = document.getElementById(tableId);
            if (table && table.querySelector('tbody')) {
                const tbody = table.querySelector('tbody');
                const rows = tableData[tableId].map((item, index) => {
                    const dateStr = new Date(item.date || item.created_at).toLocaleDateString();
                    return `<tr><td>${index + 1}</td><td>${this.getItemDisplay(item)}</td><td>${this.formatStatus(item.status)}</td><td>${dateStr}</td></tr>`;
                }).join('');
                tbody.innerHTML = rows;
            }
        });
    },
    
    getItemDisplay: function(item) {
        return item.name || item.title || item.description || item.id || 'N/A';
    },
    
    formatStatus: function(status) {
        if (!status) return '<span class="badge bg-secondary">N/A</span>';
        const statusClass = {'active': 'bg-success', 'pending': 'bg-warning', 'completed': 'bg-success', 'failed': 'bg-danger'}[status.toLowerCase()] || 'bg-secondary';
        return `<span class="badge ${statusClass}">${status}</span>`;
    },
    
    formatNumber: function(num) {
        return new Intl.NumberFormat().format(num);
    },
    
    attachEventListeners: function() {
        document.getElementById('refreshDashboard')?.addEventListener('click', () => {
            this.loadDashboardData();
        });
        
        document.querySelectorAll('[data-filter]').forEach(filter => {
            filter.addEventListener('change', () => {
                this.loadDashboardData();
            });
        });
        
        document.getElementById('exportDashboard')?.addEventListener('click', () => {
            this.exportDashboardData();
        });
        
        document.getElementById('printDashboard')?.addEventListener('click', () => {
            window.print();
        });
    },
    
    setupAutoRefresh: function() {
        setInterval(() => {
            this.loadDashboardData();
        }, this.refreshInterval);
    },
    
    exportDashboardData: function() {
        try {
            const data = {
                dashboard: 'School Accountant Dashboard',
                timestamp: new Date().toISOString(),
                ...this.dashboardData
            };
            
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            downloadFile(blob, 'dashboard-export-' + Date.now() + '.json');
            showNotification('Dashboard exported successfully', 'success');
        } catch (error) {
            console.error('Error exporting dashboard:', error);
            showNotification('Error exporting dashboard', 'error');
        }
    },
    
    navigateTo: function(route) {
        window.location.href = `/Kingsway/home.php?route=${route}`;
    }
};

document.addEventListener('DOMContentLoaded', () => accountantDashboardController.init());
