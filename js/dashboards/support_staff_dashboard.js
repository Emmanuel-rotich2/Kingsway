/**
 * Support Staff Dashboard Controller
 * Shared by Kitchen Staff, Security Staff, Janitor and Generic Staff.
 *
 * The dashboard composes canonical Staff, Communications, Catering, Boarding
 * and Maintenance APIs. It does not depend on a dashboard-specific backend
 * service.
 */
(() => {
    const unwrap = (response) => {
        let value = response;
        for (let depth = 0; depth < 5; depth += 1) {
            if (
                value
                && typeof value === 'object'
                && !Array.isArray(value)
                && Object.prototype.hasOwnProperty.call(value, 'data')
            ) {
                value = value.data;
                continue;
            }
            break;
        }
        return value;
    };

    const localDate = (date = new Date()) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    const dateRange = (days) => {
        const end = new Date();
        const start = new Date();
        start.setDate(end.getDate() - Math.max(0, days - 1));
        return {
            start_date: localDate(start),
            end_date: localDate(end)
        };
    };

    const normaliseRoleName = (roles) => {
        const values = Array.isArray(roles)
            ? roles.map((role) => String(role || '').trim().toLowerCase())
            : [];
        const preferred = [
            'kitchen staff',
            'security staff',
            'janitor',
            'staff'
        ];
        return preferred.find((role) => values.includes(role))
            || values[0]
            || 'staff';
    };

    const titleCase = (value) => String(value || '')
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());

    const attendanceScore = (status) => {
        const value = String(status || '').toLowerCase();
        if (['present', 'on_time', 'checked_in'].includes(value)) {
            return 1;
        }
        if (['late', 'half_day'].includes(value)) {
            return 0.5;
        }
        return 0;
    };

    const controller = DashboardBaseController.create({
        controllerName: 'SupportStaffDashboardController',
        rootId: 'supportStaffDashboard',
        refreshButtonId: 'supportStaffRefresh',
        stateId: 'supportStaffState',
        scopeId: 'supportStaffDepartment',
        lastUpdatedId: 'supportStaffLastUpdated',

        async apiMethod() {
            const access = unwrap(await window.API.staff.getAccessContext()) || {};
            const staffId = Number(access.staff_id || 0);
            if (!staffId) {
                throw new Error('No staff profile is linked to this account.');
            }

            const currentYear = new Date().getFullYear();
            const attendanceRange = dateRange(14);

            const [
                profileResponse,
                attendanceResponse,
                payrollResponse,
                leaveTypesResponse,
                leaveBalanceResponse,
                leaveRequestsResponse,
                announcementResponse,
                opportunitiesResponse,
                incidentsResponse
            ] = await Promise.all([
                window.API.staff.getProfile(staffId),
                window.API.staff.getAttendance(staffId, attendanceRange),
                window.API.staff.getPayrollHistory(staffId, { limit: 6 }),
                window.API.staff.getLeaveTypes(),
                window.API.staff.getLeaveBalance(),
                window.API.staff.getLeaveRequests({ year: currentYear }),
                window.API.communications.getAnnouncement(),
                window.API.staff.getInternalOpportunities(),
                window.API.staff.getIncidentReports()
            ]);

            const profile = unwrap(profileResponse) || {};
            const attendanceRows = unwrap(attendanceResponse);
            const payrollValue = unwrap(payrollResponse) || {};
            const leaveTypes = unwrap(leaveTypesResponse);
            const leaveBalances = unwrap(leaveBalanceResponse);
            const leaveRequests = unwrap(leaveRequestsResponse);
            const announcements = unwrap(announcementResponse);
            const opportunities = unwrap(opportunitiesResponse);
            const incidents = unwrap(incidentsResponse);
            const roleName = normaliseRoleName(access.roles);
            const roleSummary = await this.loadRoleSummary(roleName);

            const attendance = this.buildAttendance(
                Array.isArray(attendanceRows) ? attendanceRows : [],
                attendanceRange
            );
            const payrollHistory = Array.isArray(payrollValue)
                ? payrollValue
                : Array.isArray(payrollValue.payroll_history)
                    ? payrollValue.payroll_history
                    : [];
            const balanceRows = Array.isArray(leaveBalances) ? leaveBalances : [];
            const totalAvailableDays = balanceRows.reduce((total, row) => {
                const value = Number(row.available_days);
                return Number.isFinite(value) ? total + value : total;
            }, 0);
            const messageRows = (Array.isArray(announcements) ? announcements : [])
                .map((row) => ({
                    ...row,
                    subject: row.subject || row.title || 'Staff notice',
                    source: 'Staff announcement',
                    priority: row.priority || 'notice'
                }));

            return {
                meta: {
                    role_name: titleCase(roleName),
                    department_name: profile.department_name || 'Department',
                    scope_label: profile.department_name || titleCase(roleName)
                },
                profile: {
                    ...profile,
                    profile_pic_url: profile.profile_pic_url
                        || profile.profile_photo
                        || profile.photo
                        || profile.photo_path
                        || null
                },
                attendance,
                payroll: { payslips: payrollHistory },
                leave: {
                    types: Array.isArray(leaveTypes) ? leaveTypes : [],
                    balances: balanceRows,
                    total_available_days: totalAvailableDays,
                    requests: Array.isArray(leaveRequests) ? leaveRequests : []
                },
                messages: messageRows,
                opportunities: Array.isArray(opportunities) ? opportunities : [],
                incidents: Array.isArray(incidents) ? incidents : [],
                cards: {
                    current_notices: messageRows.length,
                    open_opportunities: Array.isArray(opportunities)
                        ? opportunities.filter((row) => !row.application_status).length
                        : 0
                },
                role_summary: roleSummary
            };
        }
    });

    controller.buildAttendance = function (rows, range) {
        const byDate = new Map(rows.map((row) => [String(row.date || ''), row]));
        const labels = [];
        const data = [];
        const cursor = new Date(`${range.start_date}T00:00:00`);
        const end = new Date(`${range.end_date}T00:00:00`);

        while (cursor <= end) {
            const key = localDate(cursor);
            const row = byDate.get(key);
            labels.push(cursor.toLocaleDateString('en-GB', {
                day: '2-digit',
                month: 'short'
            }));
            data.push(row ? attendanceScore(row.status || row.attendance_status) : 0);
            cursor.setDate(cursor.getDate() + 1);
        }

        const today = byDate.get(localDate()) || null;
        return { today, rows, trend: { labels, data } };
    };

    controller.loadRoleSummary = async function (roleName) {
        try {
            if (roleName === 'kitchen staff') {
                const date = localDate();
                const [statsResponse, stockResponse] = await Promise.all([
                    window.API.catering.getStats({ date }),
                    window.API.catering.getFoodStock({ low_stock: 1, limit: 10 })
                ]);
                const stats = unwrap(statsResponse) || {};
                const stock = unwrap(stockResponse);
                return {
                    title: 'Kitchen Summary',
                    metrics: [
                        { label: 'Meals planned', value: Number(stats.meals_planned || 0) },
                        { label: 'Planned servings', value: Number(stats.planned_servings || 0) },
                        { label: 'Prepared meals', value: Number(stats.prepared_meals || 0) },
                        { label: 'Food stock alerts', value: Array.isArray(stock) ? stock.length : Number(stats.low_stock || 0) }
                    ]
                };
            }

            if (roleName === 'security staff') {
                const exeatsValue = unwrap(await window.API.boarding.getExeats({}));
                const exeats = Array.isArray(exeatsValue) ? exeatsValue : [];
                return {
                    title: 'Gate Permissions Summary',
                    note: 'Read-only visibility. Approval and check-in actions remain in the authorised boarding workflow.',
                    metrics: [
                        { label: 'Pending', value: exeats.filter((row) => row.status === 'pending').length },
                        { label: 'Approved', value: exeats.filter((row) => row.status === 'approved').length },
                        { label: 'Currently out', value: exeats.filter((row) => row.checked_out_at && !row.checked_in_at).length },
                        { label: 'Returned', value: exeats.filter((row) => row.checked_in_at).length }
                    ]
                };
            }

            if (roleName === 'janitor') {
                const maintenance = unwrap(
                    await window.API.maintenance.getDashboardSummary()
                ) || {};
                return {
                    title: 'Maintenance Summary',
                    metrics: [
                        { label: 'Overdue equipment', value: Number(maintenance.overdue_count || 0) },
                        { label: 'Upcoming vehicle work', value: Number(maintenance.upcoming_count || 0) }
                    ]
                };
            }

            return {
                title: 'Staff Self-Service',
                metrics: [
                    { label: 'Role', value: titleCase(roleName) },
                    { label: 'Scope', value: 'Own records' }
                ]
            };
        } catch (error) {
            console.error('[SupportStaffDashboardController] Role summary failed:', error);
            return {
                title: `${titleCase(roleName)} Summary`,
                error: error?.message || 'Role-specific summary could not be loaded.',
                metrics: []
            };
        }
    };

    controller.setupEventListeners = function () {
        if (this.eventsBound) {
            return;
        }

        this.eventsBound = true;

        document
            .getElementById(this.refreshButtonId)
            ?.addEventListener('click', () => void this.loadDashboard({ force: true }));

        document
            .getElementById(this.rootId)
            ?.addEventListener('click', (event) => {
                const routeElement = event.target.closest('[data-route]');
                if (routeElement) {
                    event.preventDefault();
                    this.navigate(routeElement.dataset.route);
                    return;
                }

                const payslipButton = event.target.closest('[data-download-payslip]');
                if (payslipButton) {
                    void this.downloadPayslip(payslipButton.dataset.downloadPayslip);
                    return;
                }

                const p9Button = event.target.closest('[data-download-p9]');
                if (p9Button) {
                    void this.downloadP9(p9Button.dataset.downloadP9);
                    return;
                }

                const opportunityButton = event.target.closest('[data-apply-opportunity]');
                if (opportunityButton) {
                    void this.applyForOpportunity(
                        Number(opportunityButton.dataset.applyOpportunity)
                    );
                }
            });

        document
            .getElementById('supportLeaveForm')
            ?.addEventListener('submit', (event) => {
                event.preventDefault();
                void this.submitLeaveRequest();
            });

        document
            .getElementById('supportIncidentForm')
            ?.addEventListener('submit', (event) => {
                event.preventDefault();
                void this.submitIncidentReport();
            });
    };

    controller.renderDashboard = function (data) {
        this.renderProfile(data.profile || {});
        this.renderSummaryCards(data);
        this.renderAttendance(data.attendance || {});
        this.renderMessages(data.messages || []);
        this.renderPayslips(data.payroll || {});
        this.renderLeave(data.leave || {});
        this.renderOpportunities(data.opportunities || []);
        this.renderIncidents(data.incidents || []);
        this.renderRoleSummary(data.role_summary || {});

        this.setText('supportStaffRole', data.meta?.role_name || 'Staff');
        this.setText('supportStaffDepartment', data.meta?.department_name || 'Department');
        this.setText(
            'supportStaffLastUpdated',
            this.state.lastLoadedAt
                ? this.state.lastLoadedAt.toLocaleTimeString('en-GB', {
                    hour: '2-digit',
                    minute: '2-digit'
                })
                : '—'
        );
    };

    controller.renderProfile = function (profile) {
        const fullName = [profile.first_name, profile.last_name]
            .filter(Boolean)
            .join(' ') || 'Staff Member';

        this.setText('supportProfileName', fullName);
        this.setText('supportProfilePosition', profile.position || profile.job_title || 'Staff');
        this.setText('supportProfileStaffNo', profile.staff_no || '—');
        this.setText('supportProfileDepartment', profile.department_name || '—');
        this.setText('supportProfileSupervisor', profile.supervisor_name || 'Not assigned');
        this.setText(
            'supportProfileEmployment',
            profile.contract_type || profile.employment_type || profile.status || '—'
        );
        this.setText('supportProfileContact', profile.email || profile.phone || '—');

        const avatar = document.getElementById('supportProfileAvatar');
        if (!avatar) {
            return;
        }

        if (profile.profile_pic_url) {
            avatar.innerHTML = `<img src="${this.escapeHtml(
                this.resolveAssetUrl(profile.profile_pic_url)
            )}" alt="">`;
            return;
        }

        avatar.textContent = fullName
            .split(/\s+/)
            .slice(0, 2)
            .map((part) => part.charAt(0))
            .join('')
            .toUpperCase() || 'ST';
    };

    controller.renderSummaryCards = function (data) {
        const attendance = data.attendance?.today || {};
        this.setText(
            'supportAttendanceToday',
            attendance.status || attendance.attendance_status || 'Not marked'
        );
        this.setText(
            'supportAttendanceTodaySub',
            attendance.check_in_time
                ? `Checked in ${String(attendance.check_in_time).slice(0, 5)}`
                : 'No check-in recorded'
        );
        this.setText('supportLeaveBalance', data.leave?.total_available_days || 0);
        this.setText('supportUnreadMessages', data.cards?.current_notices || 0);
        this.setText('supportOpenOpportunities', data.cards?.open_opportunities || 0);
    };

    controller.renderAttendance = function (attendance) {
        this.renderChart({
            id: 'supportAttendanceChart',
            label: 'Attendance score',
            type: 'bar',
            showLegend: false
        }, attendance.trend || { labels: [], data: [] });
    };

    controller.renderMessages = function (messages) {
        this.renderTable({
            bodyId: 'supportMessagesBody',
            emptyText: 'No current staff announcements or notices.',
            columns: [
                { key: 'subject' },
                { key: 'source' },
                {
                    key: 'priority',
                    render: (value, row, instance) => instance.badge(value, {
                        low: 'secondary', normal: 'info', notice: 'info',
                        high: 'warning', urgent: 'danger', critical: 'danger'
                    })
                },
                { key: 'created_at', format: 'date' }
            ]
        }, messages);
    };

    controller.renderPayslips = function (payroll) {
        const rows = Array.isArray(payroll.payslips) ? payroll.payslips : [];
        this.renderTable({
            bodyId: 'supportPayslipsBody',
            emptyText: 'No payslips are available yet.',
            columns: [
                {
                    value: (row) => `${this.monthName(row.payroll_month)} ${row.payroll_year || ''}`.trim()
                },
                { key: 'net_salary', format: 'currency' },
                {
                    value: (row) => row.payslip_status || row.status || '—',
                    render: (value, row, instance) => instance.badge(value, {
                        draft: 'secondary', approved: 'primary', paid: 'success', cancelled: 'danger'
                    })
                },
                {
                    value: (row) => row.id,
                    render: (value, row) => `
                        <div class="d-flex justify-content-end gap-1">
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                data-download-payslip="${this.escapeHtml(String(value))}">
                                <i class="bi bi-download"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-success"
                                data-download-p9="${this.escapeHtml(String(row.payroll_year || new Date().getFullYear()))}">
                                P9
                            </button>
                        </div>`
                }
            ]
        }, rows);
    };

    controller.renderLeave = function (leave) {
        const types = Array.isArray(leave.types) ? leave.types : [];
        const select = document.getElementById('supportLeaveType');
        if (select) {
            select.innerHTML = '<option value="">Select leave type</option>'
                + types.map((type) => `
                    <option value="${Number(type.id)}">
                        ${this.escapeHtml(type.name)}${type.days_allowed ? ` (${Number(type.days_allowed)} days)` : ''}
                    </option>`).join('');
        }

        this.renderTable({
            bodyId: 'supportLeaveBody',
            emptyText: 'No leave requests submitted.',
            columns: [
                { value: (row) => row.leave_type_name || row.leave_type || 'Leave' },
                { value: (row) => `${this.formatValue(row.start_date, 'date')} – ${this.formatValue(row.end_date, 'date')}` },
                { key: 'days_requested', format: 'number' },
                {
                    key: 'status',
                    render: (value, row, instance) => instance.badge(value, {
                        pending: 'warning', approved: 'success', rejected: 'danger', cancelled: 'secondary'
                    })
                }
            ]
        }, Array.isArray(leave.requests) ? leave.requests : []);
    };

    controller.renderOpportunities = function (opportunities) {
        this.renderTable({
            bodyId: 'supportOpportunitiesBody',
            emptyText: 'No internal opportunities are currently open.',
            columns: [
                { key: 'title' },
                { key: 'department' },
                { key: 'deadline', format: 'date' },
                {
                    key: 'application_status',
                    render: (value, row, instance) => instance.badge(
                        value || 'Open',
                        {
                            open: 'success', received: 'primary', shortlisted: 'info',
                            interviewed: 'warning', hired: 'success', rejected: 'danger'
                        }
                    )
                },
                {
                    value: (row) => row.id,
                    render: (value, row) => row.application_status
                        ? '<span class="text-muted small">Applied</span>'
                        : `<button type="button" class="btn btn-sm btn-success" data-apply-opportunity="${Number(value)}">Apply</button>`
                }
            ]
        }, opportunities);
    };

    controller.renderIncidents = function (incidents) {
        this.renderTable({
            bodyId: 'supportIncidentsBody',
            emptyText: 'No incident reports submitted.',
            columns: [
                { key: 'reference_no' },
                { key: 'category' },
                {
                    key: 'severity',
                    render: (value, row, instance) => instance.badge(value, {
                        low: 'secondary', medium: 'info', high: 'warning', critical: 'danger'
                    })
                },
                {
                    key: 'status',
                    render: (value, row, instance) => instance.badge(value, {
                        reported: 'primary', under_review: 'info', assigned: 'warning',
                        resolved: 'success', closed: 'secondary', dismissed: 'danger'
                    })
                }
            ]
        }, incidents);
    };

    controller.renderRoleSummary = function (summary) {
        const host = document.getElementById('supportRoleSummary');
        if (!host) {
            return;
        }

        if (summary.error) {
            host.innerHTML = `
                <h6 class="mb-2">${this.escapeHtml(summary.title || 'Department Summary')}</h6>
                <div class="alert alert-warning py-2 mb-0" role="alert">
                    ${this.escapeHtml(summary.error)}
                </div>`;
            return;
        }

        const metrics = Array.isArray(summary.metrics) ? summary.metrics : [];
        host.innerHTML = `
            <h6 class="mb-2">${this.escapeHtml(summary.title || 'Department Summary')}</h6>
            ${summary.note ? `<p class="small text-muted mb-2">${this.escapeHtml(summary.note)}</p>` : ''}
            ${metrics.length
                ? `<div class="row g-2">${metrics.map((metric) => `
                    <div class="col-6">
                        <div class="border rounded p-2 h-100">
                            <div class="fw-bold">${this.escapeHtml(String(metric.value ?? 0))}</div>
                            <small class="text-muted">${this.escapeHtml(metric.label || '')}</small>
                        </div>
                    </div>`).join('')}</div>`
                : '<small class="text-muted">No role-specific summary is available.</small>'}`;
    };

    controller.submitLeaveRequest = async function () {
        const startDate = document.getElementById('supportLeaveStart')?.value;
        const endDate = document.getElementById('supportLeaveEnd')?.value;
        const payload = {
            leave_type_id: Number(document.getElementById('supportLeaveType')?.value),
            start_date: startDate,
            end_date: endDate,
            reason: document.getElementById('supportLeaveReason')?.value.trim()
        };

        if (!payload.leave_type_id || !startDate || !endDate || !payload.reason) {
            this.notify('Complete all leave request fields.', 'warning');
            return;
        }

        if (new Date(endDate) < new Date(startDate)) {
            this.notify('Leave end date cannot be before the start date.', 'warning');
            return;
        }

        const button = document.getElementById('supportLeaveSubmit');
        this.setButtonBusy(button, true, 'Submitting...');
        try {
            await window.API.staff.createLeaveRequest(payload);
            window.bootstrap?.Modal.getInstance(
                document.getElementById('supportLeaveModal')
            )?.hide();
            document.getElementById('supportLeaveForm')?.reset();
            this.notify('Leave request submitted successfully.', 'success');
            await this.loadDashboard({ force: true });
        } catch (error) {
            this.notify(error?.message || 'Failed to submit leave request.', 'error');
        } finally {
            this.setButtonBusy(button, false, 'Submit request');
        }
    };

    controller.submitIncidentReport = async function () {
        const payload = {
            category: document.getElementById('supportIncidentCategory')?.value,
            severity: document.getElementById('supportIncidentSeverity')?.value,
            occurred_at: document.getElementById('supportIncidentOccurredAt')?.value,
            location: document.getElementById('supportIncidentLocation')?.value.trim(),
            description: document.getElementById('supportIncidentDescription')?.value.trim(),
            immediate_action: document.getElementById('supportIncidentAction')?.value.trim()
        };

        if (!payload.category || !payload.occurred_at || !payload.location || !payload.description) {
            this.notify('Complete all required incident fields.', 'warning');
            return;
        }

        const button = document.getElementById('supportIncidentSubmit');
        this.setButtonBusy(button, true, 'Submitting...');
        try {
            await window.API.staff.createIncidentReport(payload);
            window.bootstrap?.Modal.getInstance(
                document.getElementById('supportIncidentModal')
            )?.hide();
            document.getElementById('supportIncidentForm')?.reset();
            this.notify('Incident report submitted successfully.', 'success');
            await this.loadDashboard({ force: true });
        } catch (error) {
            this.notify(error?.message || 'Failed to submit incident report.', 'error');
        } finally {
            this.setButtonBusy(button, false, 'Submit report');
        }
    };

    controller.applyForOpportunity = async function (jobId) {
        if (!jobId || !window.confirm('Submit an internal application for this opportunity?')) {
            return;
        }

        try {
            await window.API.staff.applyForInternalOpportunity({ job_id: jobId });
            this.notify('Internal application submitted.', 'success');
            await this.loadDashboard({ force: true });
        } catch (error) {
            this.notify(error?.message || 'Failed to submit application.', 'error');
        }
    };

    controller.downloadPayslip = async function (payslipId) {
        const payroll = this.state.data?.payroll?.payslips || [];
        const payslip = payroll.find((row) => String(row.id) === String(payslipId));
        if (!payslip) {
            this.notify('Payslip record was not found.', 'warning');
            return;
        }

        try {
            await window.API.staff.downloadPayslip(
                Number(this.state.data?.profile?.id),
                {
                    month: Number(payslip.payroll_month),
                    year: Number(payslip.payroll_year)
                }
            );
        } catch (error) {
            this.notify(error?.message || 'Payslip download failed.', 'error');
        }
    };

    controller.downloadP9 = async function (year) {
        try {
            await window.API.staff.downloadP9(
                Number(this.state.data?.profile?.id),
                Number(year)
            );
        } catch (error) {
            this.notify(error?.message || 'P9 download failed.', 'error');
        }
    };

    controller.resolveAssetUrl = function (value) {
        const path = String(value || '').trim();
        if (!path || /^(?:https?:|data:|blob:)/i.test(path)) {
            return path;
        }
        return `${window.APP_BASE || ''}${path.startsWith('/') ? path : `/${path}`}`;
    };

    controller.monthName = function (month) {
        const number = Number(month);
        if (number < 1 || number > 12) {
            return 'Unknown';
        }
        return new Date(2000, number - 1, 1).toLocaleString('en-GB', { month: 'short' });
    };

    controller.notify = function (message, type = 'info') {
        if (typeof window.API?.showNotification === 'function') {
            window.API.showNotification(message, type);
            return;
        }
        window.alert(message);
    };

    controller.setButtonBusy = function (button, busy, text) {
        if (!button) {
            return;
        }
        button.disabled = busy;
        button.textContent = text;
    };

    window.SupportStaffDashboardController = controller;
    window.supportStaffDashboardController = controller;
    DashboardBaseController.boot(controller, 'SupportStaffDashboardController');
})();
