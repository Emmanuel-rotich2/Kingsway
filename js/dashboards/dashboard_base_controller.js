/**
 * Dashboard Base Controller
 * 
 * Provides common patterns and utilities for ALL dashboard controllers
 * Ensures consistency across all 19 role-based dashboards
 * 
 * CRITICAL PRINCIPLE: Every dashboard follows ONE question:
 * "What does this role need to do its job — and nothing more?"
 * 
 * Usage:
 * const myDashboardController = Object.assign({}, dashboardBaseController, {
 *     dashboardName: 'Class Teacher',
 *     apiEndpoints: ['/api/endpoint1', '/api/endpoint2'],
 *     cardsCount: 6,
 *     chartsCount: 2,
 *     tablesCount: 3,
 *     refreshInterval: 900000, // 15 minutes
 *     
 *     // Override specific methods as needed
 *     processCustomData: function(data) { ... }
 * });
 */

const dashboardBaseController = {
    
    // ============= STATE MANAGEMENT =============
    
    state: {
        summaryCards: {},
        chartData: {},
        tableData: {},
        lastRefresh: null,
        isLoading: false,
        errorMessage: null
    },
    
    charts: {}, // Stores Chart.js instances for proper cleanup
    
    // ============= CONFIGURATION =============
    
    config: {
        refreshInterval: 900000, // 15 minutes (15 * 60 * 1000) - override per dashboard
        maxRetries: 3,
        retryDelay: 1000,
        fallbackTimeout: 5000
    },
    
    // ============= LIFECYCLE METHODS =============
    
    /**
     * Initialize dashboard - ENTRY POINT
     */
    init: function() {
        const dashboardName = this.dashboardName || 'Dashboard';
        console.log(`🚀 ${dashboardName} initializing...`);
        
        // Security: Check authentication
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
            console.error('❌ User not authenticated');
            window.location.href = '/Kingsway/index.php';
            return;
        }
        
        // Load data and render
        this.loadDashboardData();
        this.setupEventListeners();
        this.setupAutoRefresh();
        
        console.log(`✓ ${dashboardName} initialized successfully`);
    },
    
    /**
     * Fetch all dashboard data in parallel
     * Uses Promise.allSettled for resilience - one failing API doesn't crash dashboard
     */
    loadDashboardData: async function() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        this.state.errorMessage = null;
        const startTime = performance.now();
        
        try {
            const dashboardName = this.dashboardName || 'Dashboard';
            console.log(`📡 ${dashboardName}: Fetching data...`);
            
            // Get all API endpoints for this dashboard
            const apiEndpoints = this.apiEndpoints || [];
            
            if (apiEndpoints.length === 0) {
                console.warn(`⚠️  No API endpoints configured for ${dashboardName}`);
                this.renderDashboard();
                return;
            }
            
            // Make parallel API calls
            const apiPromises = apiEndpoints.map(endpoint => {
                // Determine which API method to call
                if (endpoint.includes('students')) return window.API.dashboard?.getStudentStats?.() || Promise.resolve(null);
                if (endpoint.includes('staff')) return window.API.dashboard?.getTeachingStats?.() || Promise.resolve(null);
                if (endpoint.includes('payments')) return window.API.dashboard?.getFeesCollected?.() || Promise.resolve(null);
                if (endpoint.includes('attendance')) return window.API.dashboard?.getTodayAttendance?.() || Promise.resolve(null);
                if (endpoint.includes('schedule')) return window.API.dashboard?.getScheduleStats?.() || Promise.resolve(null);
                
                // Generic fetch for other endpoints
                return fetch(`/Kingsway${endpoint}`)
                    .then(r => r.ok ? r.json() : null)
                    .catch(e => {
                        console.warn(`⚠️  API call failed: ${endpoint}`, e);
                        return null;
                    });
            });
            
            const results = await Promise.allSettled(apiPromises);
            
            // Process each result
            results.forEach((result, index) => {
                if (result.status === 'fulfilled' && result.value) {
                    console.log(`✓ API result ${index}:`, result.value);
                    // Process will be handled by role-specific methods
                } else {
                    console.warn(`⚠️  API call ${index} failed or returned null`);
                }
            });
            
            // Render dashboard with whatever data we have (or fallback)
            this.renderDashboard();
            
            this.state.lastRefresh = new Date();
            const duration = (performance.now() - startTime).toFixed(2);
            console.log(`✓ ${this.dashboardName}: Loaded in ${duration}ms`);
            
        } catch (error) {
            console.error(`❌ ${this.dashboardName}: Loading failed`, error);
            this.state.errorMessage = error.message;
            this.showErrorState();
        } finally {
            this.isLoading = false;
        }
    },
    
    /**
     * Render complete dashboard UI
     * Override this in role-specific dashboards for custom layout
     */
    renderDashboard: function() {
        console.log('🎨 Rendering dashboard...');
        
        const mainContent = document.getElementById('mainContent');
        if (!mainContent) {
            console.error('❌ mainContent div not found');
            return;
        }
        
        // Clear previous content
        mainContent.innerHTML = '';
        
        // Render sections
        this.renderSummaryCards();
        this.renderCharts();
        this.renderTables();
        
        console.log('✓ Dashboard rendered');
    },
    
    /**
     * Render summary cards section
     */
    renderSummaryCards: function() {
        const cardsContainer = document.createElement('div');
        cardsContainer.className = 'row g-3 mb-4';
        cardsContainer.id = 'summaryCardsContainer';
        
        // Render each card
        Object.values(this.state.summaryCards).forEach(card => {
            if (!card) return;
            const cardHTML = this.createCardHTML(card);
            cardsContainer.innerHTML += cardHTML;
        });
        
        const mainContent = document.getElementById('mainContent');
        if (mainContent) mainContent.appendChild(cardsContainer);
    },
    
    /**
     * Create individual card HTML
     */
    createCardHTML: function(card) {
        const colWidth = 12 / (Object.keys(this.state.summaryCards).length || 4); // Auto-width
        const iconClass = card.icon || 'bi-graph-up';
        const colorClass = `bg-${card.color || 'primary'}`;
        
        return `
            <div class="col-md-6 col-lg-${colWidth}">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-title text-muted text-uppercase fs-7 fw-600">${card.title}</h6>
                                <h2 class="card-text fw-bold mb-2">${card.value}</h2>
                                <p class="card-text text-muted small mb-1">${card.subtitle}</p>
                                <p class="card-text text-secondary fs-8">${card.secondary || ''}</p>
                            </div>
                            <div class="text-${card.color || 'primary'} fs-2">
                                <i class="bi ${iconClass}"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },
    
    /**
     * Render charts section
     */
    renderCharts: function() {
        if (Object.keys(this.state.chartData).length === 0) return;
        
        const chartsContainer = document.createElement('div');
        chartsContainer.className = 'row g-3 mb-4';
        chartsContainer.id = 'chartsContainer';
        
        Object.entries(this.state.chartData).forEach(([chartName, chartData]) => {
            const chartDiv = document.createElement('div');
            chartDiv.className = 'col-md-6';
            chartDiv.innerHTML = `
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">${chartName}</h5>
                        <canvas id="chart_${chartName.replace(/\s+/g, '_')}"></canvas>
                    </div>
                </div>
            `;
            chartsContainer.appendChild(chartDiv);
        });
        
        const mainContent = document.getElementById('mainContent');
        if (mainContent) mainContent.appendChild(chartsContainer);
        
        // Draw charts after DOM is ready
        setTimeout(() => this.drawCharts(), 100);
    },
    
    /**
     * Override in role-specific dashboard
     */
    drawCharts: function() {
        // To be implemented by role-specific controller
        console.log('⚠️  drawCharts() not implemented in ' + (this.dashboardName || 'this dashboard'));
    },
    
    /**
     * Render data tables section (tabbed interface)
     */
    renderTables: function() {
        if (Object.keys(this.state.tableData).length === 0) return;
        
        const tablesContainer = document.createElement('div');
        tablesContainer.className = 'card border-0 shadow-sm';
        tablesContainer.id = 'tablesContainer';
        
        const tableNames = Object.keys(this.state.tableData);
        
        // Tab navigation
        let tabsHTML = '<ul class="nav nav-tabs" role="tablist">';
        tableNames.forEach((tableName, index) => {
            const isActive = index === 0 ? 'active' : '';
            tabsHTML += `
                <li class="nav-item" role="presentation">
                    <button class="nav-link ${isActive}" id="tab_${tableName}" 
                            data-bs-toggle="tab" data-bs-target="#content_${tableName}" 
                            type="button" role="tab" aria-selected="${index === 0}">
                        ${tableName}
                    </button>
                </li>
            `;
        });
        tabsHTML += '</ul>';
        
        // Tab content
        let contentHTML = '<div class="tab-content">';
        tableNames.forEach((tableName, index) => {
            const isActive = index === 0 ? 'active' : '';
            const tableData = this.state.tableData[tableName] || [];
            
            contentHTML += `
                <div class="tab-pane fade ${isActive}" id="content_${tableName}" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <tbody id="tbody_${tableName}">
                                <!-- Rows populated by renderTableRows -->
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        });
        contentHTML += '</div>';
        
        tablesContainer.innerHTML = `<div class="card-body">${tabsHTML}${contentHTML}</div>`;
        
        const mainContent = document.getElementById('mainContent');
        if (mainContent) mainContent.appendChild(tablesContainer);
    },
    
    /**
     * Helper: render table rows (override for custom table rendering)
     */
    renderTableRows: function(tableBodyId, rows) {
        const tbody = document.getElementById(tableBodyId);
        if (!tbody) return;
        
        tbody.innerHTML = '';
        rows.forEach(row => {
            const tr = document.createElement('tr');
            tr.innerHTML = Object.values(row).map(v => `<td>${v}</td>`).join('');
            tbody.appendChild(tr);
        });
    },
    
    // ============= ERROR HANDLING =============
    
    /**
     * Show error state with user-friendly message
     */
    showErrorState: function() {
        const mainContent = document.getElementById('mainContent');
        if (!mainContent) return;
        
        mainContent.innerHTML = `
            <div class="alert alert-danger" role="alert">
                <h4 class="alert-heading">Unable to Load Dashboard</h4>
                <p>${this.state.errorMessage || 'An unexpected error occurred. Please refresh the page.'}</p>
                <hr>
                <p class="mb-0">
                    <button class="btn btn-sm btn-danger" onclick="location.reload()">Reload Page</button>
                    <button class="btn btn-sm btn-secondary" onclick="window.history.back()">Go Back</button>
                </p>
            </div>
        `;
    },
    
    // ============= AUTO-REFRESH =============
    
    /**
     * Setup automatic dashboard refresh
     */
    setupAutoRefresh: function() {
        const interval = this.config.refreshInterval || 900000;
        console.log(`⏱️  Setting up auto-refresh: every ${interval / 1000}s`);
        
        setInterval(() => {
            if (!this.isLoading) {
                console.log('🔄 Auto-refreshing dashboard...');
                this.loadDashboardData();
            }
        }, interval);
    },
    
    // ============= EVENT HANDLING =============
    
    /**
     * Setup event listeners (override for role-specific handlers)
     */
    setupEventListeners: function() {
        // Example: Listen for dynamic table actions
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('btn-action')) {
                this.handleTableAction(e);
            }
        });
    },
    
    /**
     * Handle table action (override in role-specific dashboard)
     */
    handleTableAction: function(event) {
        console.log('Table action triggered:', event.target);
    },
    
    // ============= UTILITY FUNCTIONS =============
    
    /**
     * Format large numbers with commas
     */
    formatNumber: function(num) {
        if (typeof num !== 'number') return num;
        return num.toLocaleString();
    },
    
    /**
     * Format currency (KES)
     */
    formatCurrency: function(amount) {
        if (typeof amount !== 'number') return amount;
        return new Intl.NumberFormat('en-KE', {
            style: 'currency',
            currency: 'KES',
            minimumFractionDigits: 0
        }).format(amount);
    },
    
    /**
     * Format percentage
     */
    formatPercent: function(value, decimals = 0) {
        if (typeof value !== 'number') return value;
        return value.toFixed(decimals) + '%';
    },
    
    /**
     * Format date
     */
    formatDate: function(date) {
        if (!date) return '';
        return new Date(date).toLocaleDateString();
    },
    
    /**
     * Format time
     */
    formatTime: function(time) {
        if (!time) return '';
        return new Date(time).toLocaleTimeString();
    },
    
    /**
     * Destroy all Chart.js instances before redraw
     */
    destroyCharts: function() {
        Object.values(this.charts).forEach(chart => {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
        this.charts = {};
    },
    
    /**
     * Get color based on value/status
     */
    getColorClass: function(status) {
        const colorMap = {
            'success': 'success',
            'high': 'danger',
            'warning': 'warning',
            'info': 'info',
            'primary': 'primary',
            'secondary': 'secondary',
            'danger': 'danger'
        };
        return colorMap[status] || 'secondary';
    }
};

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = dashboardBaseController;
}
