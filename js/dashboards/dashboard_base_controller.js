/**
 * Kingsway Dashboard Base Controller
 *
 * Shared lifecycle and rendering primitives for role dashboard controllers.
 * Every concrete dashboard remains a named controller and supplies one
 * canonical API method from js/api.js.
 */
const DashboardBaseController = {
    create(definition) {
        const controller = {
            initialized: false,
            initializationPromise: null,
            eventsBound: false,
            charts: {},
            period: definition.defaultPeriod || 'month',
            // Realtime invalidations refresh immediately. Five minutes is only
            // a repair/safety poll for missed cron or third-party changes.
            liveRefreshMs: Number(definition.liveRefreshMs || 300000),
            liveRefreshTimer: null,
            liveSyncBound: false,
            state: {
                data: null,
                loading: false,
                error: null,
                lastLoadedAt: null
            },

            ...definition,

            async init() {
                if (this.initializationPromise) {
                    return this.initializationPromise;
                }

                this.initializationPromise = this._initialize();

                try {
                    await this.initializationPromise;
                    return this;
                } catch (error) {
                    this.initializationPromise = null;
                    throw error;
                }
            },

            async _initialize() {
                if (this.initialized) {
                    return this;
                }

                console.log(`[${this.controllerName}] Initializing...`);

                if (window.AuthContext?.ready) {
                    await window.AuthContext.ready();
                }

                if (!window.AuthContext?.isAuthenticated?.()) {
                    window.location.replace(
                        `${window.APP_BASE || ''}/index.php`
                    );
                    return this;
                }

                if (!document.getElementById(this.rootId)) {
                    return this;
                }

                if (typeof this.apiMethod !== 'function') {
                    throw new Error(
                        `${this.controllerName} API method is unavailable.`
                    );
                }

                this.setupEventListeners();
                await this.loadDashboard({ throwOnError: true });
                this.startLiveSync();
                this.initialized = true;

                console.log(`[${this.controllerName}] Initialized successfully`);
                return this;
            },

            setupEventListeners() {
                if (this.eventsBound) {
                    return;
                }

                this.eventsBound = true;

                document
                    .getElementById(this.refreshButtonId)
                    ?.addEventListener('click', () => {
                        void this.loadDashboard({ force: true });
                    });

                document
                    .getElementById(this.rootId)
                    ?.addEventListener('click', (event) => {
                        const routeElement = event.target.closest('[data-route]');
                        if (!routeElement) {
                            return;
                        }

                        event.preventDefault();
                        this.navigate(routeElement.dataset.route);
                    });

                this.setupPeriodListeners();
            },

            setupPeriodListeners() {
                const periodBar = document.getElementById(this.rootId + 'PeriodBar');
                if (!periodBar) return;

                periodBar.addEventListener('click', (event) => {
                    const btn = event.target.closest('.dash-period-btn');
                    if (!btn) return;
                    const newPeriod = btn.dataset.period;
                    if (newPeriod === this.period) return;

                    this.period = newPeriod;
                    periodBar.querySelectorAll('.dash-period-btn').forEach(b => {
                        b.classList.remove('btn-success');
                        b.classList.add('btn-outline-success');
                    });
                    btn.classList.remove('btn-outline-success');
                    btn.classList.add('btn-success');

                    const label = document.getElementById(this.rootId + 'PeriodBarLabel');
                    if (label) label.textContent = btn.textContent;

                    this.destroyCharts();
                    void this.loadDashboard({ force: true });
                });

                const defaultBtn = periodBar.querySelector('.dash-period-btn.btn-success');
                if (defaultBtn) {
                    const label = document.getElementById(this.rootId + 'PeriodBarLabel');
                    if (label) label.textContent = defaultBtn.textContent;
                }
            },

            startLiveSync() {
                if (this.liveSyncBound || this.liveRefreshMs < 5000) {
                    return;
                }

                this.liveSyncBound = true;
                const refreshVisibleDashboard = () => {
                    if (!document.getElementById(this.rootId)) {
                        this.stopLiveSync();
                        return;
                    }
                    if (document.visibilityState !== 'visible' || this.state.loading) {
                        return;
                    }
                    void this.loadDashboard({ force: true, silent: true });
                };

                this._liveVisibilityHandler = () => {
                    if (document.visibilityState === 'visible') {
                        refreshVisibleDashboard();
                    }
                };
                this._liveOnlineHandler = () => refreshVisibleDashboard();

                document.addEventListener('visibilitychange', this._liveVisibilityHandler);
                window.addEventListener('online', this._liveOnlineHandler);
                this.liveRefreshTimer = window.setInterval(
                    refreshVisibleDashboard,
                    this.liveRefreshMs
                );
            },

            stopLiveSync() {
                if (this.liveRefreshTimer) {
                    window.clearInterval(this.liveRefreshTimer);
                    this.liveRefreshTimer = null;
                }
                if (this._liveVisibilityHandler) {
                    document.removeEventListener('visibilitychange', this._liveVisibilityHandler);
                }
                if (this._liveOnlineHandler) {
                    window.removeEventListener('online', this._liveOnlineHandler);
                }
                this.liveSyncBound = false;
            },

            async loadDashboard(options = {}) {
                if (this.state.loading && options.force !== true) {
                    return this.state.data;
                }

                const throwOnError = options.throwOnError === true;
                const silent = options.silent === true;

                this.state.loading = true;
                this.state.error = null;
                if (!silent) {
                    this.renderLoadingState();
                    this.setRefreshBusy(true);
                }

                try {
                    const filters = this.resolvePeriodFilters(this.period);
                    const response = await this.apiMethod(filters);
                    const data = this.filterDateBearingData(
                        this.normalizeResponse(response),
                        filters
                    );

                    if (!data || typeof data !== 'object' || Array.isArray(data)) {
                        throw new Error(
                            `${this.controllerName} received an invalid dashboard payload.`
                        );
                    }

                    this.state.data = data;
                    this.state.lastLoadedAt = new Date();
                    this.renderDashboard(data);
                    if (!silent) {
                        this.renderSuccessState();
                    }
                    return data;
                } catch (error) {
                    console.error(`[${this.controllerName}] Load failed:`, error);
                    this.state.error = error;
                    if (!silent) {
                        this.renderErrorState(
                            error?.message || 'Unable to load dashboard data.'
                        );
                    }

                    if (throwOnError) {
                        throw error;
                    }

                    return null;
                } finally {
                    this.state.loading = false;
                    if (!silent) {
                        this.setRefreshBusy(false);
                    }
                }
            },

            normalizeResponse(response) {
                return response?.data?.data
                    || response?.data
                    || response
                    || null;
            },

            resolvePeriodFilters(period) {
                const pad = value => String(value).padStart(2, '0');
                const format = date => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
                const today = new Date();
                const from = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                const to = new Date(from);

                if (period === 'week') {
                    const weekday = from.getDay() || 7;
                    from.setDate(from.getDate() - weekday + 1);
                } else if (period === 'month') {
                    from.setDate(1);
                } else if (period === 'year') {
                    from.setMonth(0, 1);
                } else if (period === 'term') {
                    const context = window.AcademicContext?.state || {};
                    const startValue = context.termStartDate;
                    const endValue = context.termEndDate;
                    const start = startValue ? new Date(`${startValue}T00:00:00`) : null;
                    const end = endValue ? new Date(`${endValue}T00:00:00`) : null;
                    if (start && !Number.isNaN(start.getTime())) {
                        from.setTime(start.getTime());
                    } else {
                        from.setMonth(0, 1);
                    }
                    if (end && !Number.isNaN(end.getTime()) && end < to) {
                        to.setTime(end.getTime());
                    }
                }

                return {
                    period: period || 'today',
                    date_from: format(from),
                    date_to: format(to),
                    start_date: format(from),
                    end_date: format(to),
                    term_id: window.AcademicContext?.state?.termId || undefined,
                    academic_year_id: window.AcademicContext?.state?.academicYearId || undefined
                };
            },

            filterDateBearingData(data, filters) {
                if (!data || typeof data !== 'object') return data;
                const dateKeys = [
                    'date', 'created_at', 'updated_at', 'submitted_at',
                    'incident_date', 'assessment_date', 'observation_date',
                    'session_date', 'start_date', 'event_date', 'required_date',
                    'payment_date', 'transaction_date', 'due_date'
                ];
                const from = new Date(`${filters.date_from}T00:00:00`).getTime();
                const to = new Date(`${filters.date_to}T23:59:59`).getTime();
                const filterRows = rows => {
                    if (!Array.isArray(rows)) return rows;
                    return rows.filter(row => {
                        if (!row || typeof row !== 'object') return true;
                        const key = dateKeys.find(candidate => row[candidate]);
                        if (!key) return true;
                        const timestamp = new Date(String(row[key]).replace(' ', 'T')).getTime();
                        return Number.isNaN(timestamp) || (timestamp >= from && timestamp <= to);
                    });
                };

                if (data.tables && typeof data.tables === 'object') {
                    Object.keys(data.tables).forEach(key => {
                        const table = data.tables[key];
                        if (Array.isArray(table)) {
                            data.tables[key] = filterRows(table);
                        } else if (table && Array.isArray(table.data)) {
                            table.data = filterRows(table.data);
                            if (Object.prototype.hasOwnProperty.call(table, 'total')) {
                                table.total = table.data.length;
                            }
                        }
                    });
                }
                data.meta = { ...(data.meta || {}), filters };
                return data;
            },

            renderDashboard(data) {
                this.renderCards(data);
                this.renderCharts(data);
                this.renderTables(data);
                this.renderMeta(data);

                if (typeof this.afterRender === 'function') {
                    this.afterRender(data);
                }
            },

            renderCards(data) {
                (this.cards || []).forEach((card) => {
                    const value = typeof card.value === 'function'
                        ? card.value(data, this)
                        : this.getPath(data, card.path);

                    this.setText(
                        card.id,
                        this.formatValue(value, card.format || 'number')
                    );

                    if (card.subtitleId) {
                        const subtitle = typeof card.subtitle === 'function'
                            ? card.subtitle(data, this)
                            : card.subtitle;
                        this.setText(card.subtitleId, subtitle || '');
                    }
                });
            },

            renderCharts(data) {
                (this.chartDefinitions || []).forEach((definition) => {
                    const payload = typeof definition.data === 'function'
                        ? definition.data(data, this)
                        : this.getPath(data, definition.path);

                    this.renderChart(definition, payload || {});
                });
            },

            renderChart(definition, payload) {
                const canvas = document.getElementById(definition.id);
                if (!canvas || typeof window.Chart !== 'function') {
                    return;
                }

                if (this.charts[definition.id]) {
                    this.charts[definition.id].destroy();
                    delete this.charts[definition.id];
                }

                const labels = Array.isArray(payload.labels)
                    ? payload.labels
                    : [];

                let datasets;
                if (Array.isArray(payload.datasets)) {
                    datasets = payload.datasets;
                } else {
                    datasets = [{
                        label: definition.label || 'Value',
                        data: Array.isArray(payload.data) ? payload.data : [],
                        borderWidth: 2,
                        tension: 0.3,
                        fill: definition.fill === true
                    }];
                }

                const hasChartData = labels.length > 0
                    && datasets.some((dataset) => Array.isArray(dataset.data)
                        && dataset.data.length > 0);
                let emptyState = canvas.parentElement?.querySelector(
                    `[data-chart-empty-for="${definition.id}"]`
                );
                if (!hasChartData) {
                    canvas.hidden = true;
                    if (!emptyState && canvas.parentElement) {
                        emptyState = document.createElement('div');
                        emptyState.dataset.chartEmptyFor = definition.id;
                        emptyState.className = 'h-100 d-flex align-items-center justify-content-center text-muted text-center px-3';
                        emptyState.textContent = definition.emptyText
                            || 'No chart data is recorded for this period.';
                        canvas.parentElement.appendChild(emptyState);
                    }
                    return;
                }
                canvas.hidden = false;
                emptyState?.remove();

                this.charts[definition.id] = new window.Chart(canvas, {
                    type: definition.type || 'line',
                    data: { labels, datasets },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: definition.showLegend === true
                            }
                        },
                        scales: definition.type === 'doughnut'
                            || definition.type === 'pie'
                            ? undefined
                            : definition.type === 'radar'
                                ? {
                                    r: {
                                        beginAtZero: true,
                                        ticks: { precision: 0 }
                                    }
                                }
                                : {
                                    y: {
                                        beginAtZero: true,
                                        ticks: { precision: 0 }
                                    }
                                }
                    }
                });
            },

            renderTables(data) {
                (this.tableDefinitions || []).forEach((definition) => {
                    const rows = typeof definition.rows === 'function'
                        ? definition.rows(data, this)
                        : this.getPath(data, definition.path);

                    this.renderTable(
                        definition,
                        Array.isArray(rows) ? rows : []
                    );
                });
            },

            renderTable(definition, rows) {
                const body = document.getElementById(definition.bodyId);
                if (!body) {
                    return;
                }

                if (!rows.length) {
                    body.innerHTML = `
                        <tr>
                            <td colspan="${definition.columns.length}"
                                class="text-center text-muted py-4">
                                <i class="bi bi-inbox me-2"></i>
                                ${this.escapeHtml(
                                    definition.emptyText || 'No records found.'
                                )}
                            </td>
                        </tr>`;
                    return;
                }

                body.innerHTML = rows.map((row) => `
                    <tr>
                        ${definition.columns.map((column) => {
                            const rawValue = typeof column.value === 'function'
                                ? column.value(row, this)
                                : this.getPath(row, column.key);

                            if (typeof column.render === 'function') {
                                return `<td>${column.render(rawValue, row, this)}</td>`;
                            }

                            return `<td>${this.escapeHtml(
                                this.formatValue(rawValue, column.format || 'text')
                            )}</td>`;
                        }).join('')}
                    </tr>`).join('');
            },

            renderMeta(data) {
                const meta = data.meta || {};
                const scope = meta.scope_label
                    || meta.department_name
                    || meta.class_name
                    || meta.role_name
                    || '';

                this.setText(this.scopeId, scope);
                this.setText(
                    this.lastUpdatedId,
                    this.state.lastLoadedAt
                        ? this.state.lastLoadedAt.toLocaleTimeString('en-GB', {
                            hour: '2-digit',
                            minute: '2-digit'
                        })
                        : ''
                );
            },

            renderLoadingState() {
                const state = document.getElementById(this.stateId);
                if (!state) {
                    return;
                }

                state.hidden = false;
                state.className = 'alert alert-light border d-flex align-items-center';
                state.innerHTML = `
                    <span class="spinner-border spinner-border-sm me-2"
                        aria-hidden="true"></span>
                    Loading dashboard data...`;
            },

            renderSuccessState() {
                const state = document.getElementById(this.stateId);
                if (state) {
                    state.hidden = true;
                    state.textContent = '';
                }
            },

            renderErrorState(message) {
                const state = document.getElementById(this.stateId);
                if (!state) {
                    return;
                }

                state.hidden = false;
                state.className = 'alert alert-danger d-flex align-items-center';
                state.innerHTML = `
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <span>${this.escapeHtml(message)}</span>`;
            },

            setRefreshBusy(busy) {
                const button = document.getElementById(this.refreshButtonId);
                if (!button) {
                    return;
                }

                button.disabled = busy;
                button.querySelector('i')?.classList.toggle('spin', busy);
            },

            navigate(route) {
                if (!route) {
                    return;
                }

                if (window.AppRouter?.go) {
                    window.AppRouter.go(route);
                    return;
                }

                window.location.href = `${window.APP_BASE || ''}/home.php?route=${encodeURIComponent(route)}`;
            },

            getPath(object, path) {
                if (!path) {
                    return object;
                }

                return String(path)
                    .split('.')
                    .reduce((current, segment) => current?.[segment], object);
            },

            formatValue(value, format) {
                if (value === null || value === undefined || value === '') {
                    return format === 'number' || format === 'currency'
                        || format === 'percent' ? '0' : '—';
                }

                const numericValue = Number(value);

                switch (format) {
                    case 'number':
                        return new Intl.NumberFormat('en-GB').format(
                            Number.isFinite(numericValue) ? numericValue : 0
                        );
                    case 'currency':
                        return new Intl.NumberFormat('en-KE', {
                            style: 'currency',
                            currency: 'KES',
                            maximumFractionDigits: 0
                        }).format(Number.isFinite(numericValue) ? numericValue : 0);
                    case 'percent':
                        return `${Number.isFinite(numericValue)
                            ? numericValue.toFixed(1)
                            : '0.0'}%`;
                    case 'date': {
                        const date = new Date(value);
                        return Number.isNaN(date.getTime())
                            ? String(value)
                            : date.toLocaleDateString('en-GB');
                    }
                    case 'datetime': {
                        const date = new Date(value);
                        return Number.isNaN(date.getTime())
                            ? String(value)
                            : date.toLocaleString('en-GB');
                    }
                    case 'time':
                        return String(value).slice(0, 5);
                    default:
                        return String(value);
                }
            },

            badge(value, map = {}) {
                const normalized = String(value || 'unknown').toLowerCase();
                const variant = map[normalized] || 'secondary';
                return `<span class="badge bg-${variant}">${this.escapeHtml(
                    String(value || 'Unknown')
                )}</span>`;
            },

            setText(id, value) {
                const element = document.getElementById(id);
                if (element) {
                    element.textContent = String(value ?? '');
                }
            },

            escapeHtml(value) {
                const node = document.createElement('div');
                node.textContent = String(value ?? '');
                return node.innerHTML;
            },

            destroyCharts() {
                Object.values(this.charts).forEach((chart) => chart?.destroy?.());
                this.charts = {};
            }
        };

        return controller;
    },

    boot(controller, globalName) {
        window[globalName] = controller;

        const initialize = () => {
            void controller.init().catch((error) => {
                console.error(`[${globalName}] Initialization failed:`, error);
            });
        };

        if (window.__APP_BOOTED__) {
            initialize();
        } else {
            window.addEventListener('kingsway:ready', initialize, { once: true });
        }
    }
};

window.DashboardBaseController = DashboardBaseController;
