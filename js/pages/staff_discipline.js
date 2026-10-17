/* Staff discipline page controller. PHP owns authorization and case decisions. */
(function () {
    'use strict';

    const api = window.API;
    const controller = {
        rows: [],
        context: { staff: [], departments: [] },
        searchTimer: null,
        busy: false,
        dom: {},

        async init() {
            if (this.initialized || !document.getElementById('staffDisciplinePage')) return;
            this.initialized = true;
            await window.AuthContext?.ready?.();
            this.cacheDom();
            this.bindEvents();
            this.applyPermissions();
            await Promise.all([this.loadContext(), this.loadCases()]);
        },

        cacheDom() {
            const ids = [
                'staffDisciplinePage', 'staffDisciplineBody', 'staffDisciplineEmpty',
                'sdTotal', 'sdOpen', 'sdInReview', 'sdEscalated', 'sdResolved',
                'sdSearch', 'sdStatusFilter', 'sdSeverityFilter', 'sdDepartmentFilter',
                'sdFromFilter', 'sdToFilter', 'staffDisciplineExportBtn',
                'staffDisciplinePrintBtn', 'addStaffDisciplineBtn', 'staffDisciplineForm',
                'staffDisciplineModal', 'sdStaffSelect', 'sdCategory', 'sdSeverity',
                'sdIncidentDate', 'sdDepartmentSelect', 'sdDescription', 'sdActionTaken',
            ];
            ids.forEach(id => { this.dom[id] = document.getElementById(id); });
        },

        bindEvents() {
            ['sdStatusFilter', 'sdSeverityFilter', 'sdDepartmentFilter', 'sdFromFilter', 'sdToFilter']
                .forEach(id => this.dom[id]?.addEventListener('change', () => this.loadCases()));
            this.dom.sdSearch?.addEventListener('input', () => {
                clearTimeout(this.searchTimer);
                this.searchTimer = setTimeout(() => this.loadCases(), 250);
            });
            this.dom.staffDisciplineBody?.addEventListener('click', event => this.handleRowAction(event));
            this.dom.staffDisciplineExportBtn?.addEventListener('click', () => this.exportCsv());
            this.dom.staffDisciplinePrintBtn?.addEventListener('click', () => this.print());
            this.dom.addStaffDisciplineBtn?.addEventListener('click', () => this.openCreateModal());
            this.dom.staffDisciplineForm?.addEventListener('submit', event => this.createCase(event));
        },

        applyPermissions() {
            const auth = window.AuthContext;
            const canCreate = auth?.hasAnyPermission?.(['staff_discipline_create', 'staff_discipline_manage']);
            if (this.dom.addStaffDisciplineBtn) this.dom.addStaffDisciplineBtn.hidden = !canCreate;
            if (this.dom.staffDisciplineExportBtn && auth?.canExport) {
                this.dom.staffDisciplineExportBtn.hidden = !auth.canExport('staff_discipline');
            }
            if (this.dom.staffDisciplinePrintBtn && auth?.canPrint) {
                this.dom.staffDisciplinePrintBtn.hidden = !auth.canPrint('staff_discipline');
            }
        },

        async request(path, method = 'GET', data = null, params = {}) {
            if (!api?.callAPI) throw new Error('The application API is unavailable.');
            return api.callAPI(path, method, data, params, { checkPermission: false });
        },

        async loadContext() {
            try {
                const result = await this.request('/staff-discipline/context');
                this.context = result || this.context;
                this.populateDepartments();
                this.populateStaff();
            } catch (error) {
                this.notify(error.message || 'Could not load staff and department options.', 'error');
            }
        },

        async loadCases() {
            if (this.busy) return;
            this.busy = true;
            this.renderMessage('Loading staff disciplinary cases…');
            try {
                const params = {
                    search: this.dom.sdSearch?.value.trim() || '',
                    status: this.dom.sdStatusFilter?.value || '',
                    severity: this.dom.sdSeverityFilter?.value || '',
                    department_id: this.dom.sdDepartmentFilter?.value || '',
                    from: this.dom.sdFromFilter?.value || '',
                    to: this.dom.sdToFilter?.value || '',
                };
                const result = await this.request('/staff-discipline/cases', 'GET', null, params);
                this.rows = Array.isArray(result?.cases) ? result.cases : [];
                this.updateStats(result?.stats || {});
                this.renderRows();
            } catch (error) {
                this.rows = [];
                this.renderMessage('Could not load staff disciplinary cases.');
                this.notify(error.message || 'Could not load staff disciplinary cases.', 'error');
            } finally {
                this.busy = false;
            }
        },

        populateDepartments() {
            const departments = Array.isArray(this.context.departments) ? this.context.departments : [];
            const currentFilter = this.dom.sdDepartmentFilter?.value || '';
            const currentForm = this.dom.sdDepartmentSelect?.value || '';
            this.setOptions(this.dom.sdDepartmentFilter, departments, 'All departments', currentFilter);
            this.setOptions(this.dom.sdDepartmentSelect, departments, '—', currentForm);
        },

        populateStaff() {
            const staff = Array.isArray(this.context.staff) ? this.context.staff : [];
            const select = this.dom.sdStaffSelect;
            if (!select) return;
            select.replaceChildren(new Option('Select staff member', ''));
            staff.forEach(person => {
                const label = [person.staff_no, person.name].filter(Boolean).join(' — ');
                select.add(new Option(label, String(person.id)));
            });
        },

        setOptions(select, items, placeholder, selected) {
            if (!select) return;
            select.replaceChildren(new Option(placeholder, ''));
            items.forEach(item => select.add(new Option(String(item.name || item.code || ''), String(item.id))));
            if ([...select.options].some(option => option.value === selected)) select.value = selected;
        },

        updateStats(stats) {
            const values = {
                sdTotal: stats.total,
                sdOpen: stats.open,
                sdInReview: stats.in_review,
                sdEscalated: stats.escalated,
                sdResolved: Number(stats.resolved || 0) + Number(stats.dismissed || 0),
            };
            Object.entries(values).forEach(([id, value]) => {
                if (this.dom[id]) this.dom[id].textContent = String(Number(value) || 0);
            });
        },

        renderRows() {
            const body = this.dom.staffDisciplineBody;
            if (!body) return;
            if (!this.rows.length) {
                body.replaceChildren();
                this.dom.staffDisciplineEmpty?.classList.remove('d-none');
                return;
            }
            this.dom.staffDisciplineEmpty?.classList.add('d-none');
            body.innerHTML = this.rows.map(row => this.rowHtml(row)).join('');
        },

        rowHtml(row) {
            const statusClass = ({ open: 'warning', in_review: 'primary', escalated: 'danger', resolved: 'success', dismissed: 'secondary' })[row.status] || 'secondary';
            const actions = [];
            if (row.status === 'open' && this.hasAny(['staff_discipline_edit', 'staff_discipline_manage'])) {
                actions.push(this.actionButton('review', row.id, 'Start review'));
            }
            if (row.status !== 'escalated' && !['resolved', 'dismissed'].includes(row.status) && this.hasAny(['staff_discipline_escalate', 'staff_discipline_manage'])) {
                actions.push(this.actionButton('escalate', row.id, 'Escalate'));
            }
            if (!['resolved', 'dismissed'].includes(row.status) && this.hasAny(['staff_discipline_resolve', 'staff_discipline_manage'])) {
                actions.push(this.actionButton('resolve', row.id, 'Resolve'));
                actions.push(this.actionButton('dismiss', row.id, 'Dismiss'));
            }
            return `<tr>
                <td><strong>${this.escape(row.case_no)}</strong></td>
                <td>${this.escape(row.staff_name)}<br><small class="text-muted">${this.escape(row.staff_no)}</small></td>
                <td>${this.escape(row.department_name || '—')}</td>
                <td>${this.escape(this.label(row.category))}</td>
                <td><span class="badge text-bg-${this.severityClass(row.severity)}">${this.escape(this.label(row.severity))}</span></td>
                <td>${this.escape(row.incident_date)}</td>
                <td><span class="badge text-bg-${statusClass}">${this.escape(this.label(row.status))}</span></td>
                <td>${this.escape(row.assigned_to_name || '—')}</td>
                <td class="text-end"><div class="btn-group btn-group-sm">${actions.join('')}</div></td>
            </tr>`;
        },

        async handleRowAction(event) {
            const button = event.target.closest('[data-action][data-case-id]');
            if (!button) return;
            const id = Number(button.dataset.caseId);
            const action = button.dataset.action;
            try {
                if (action === 'review') {
                    await this.request(`/staff-discipline/case/${id}`, 'PUT', { status: 'in_review' });
                } else if (action === 'escalate') {
                    await this.request(`/staff-discipline/case/${id}/escalate`, 'POST', {});
                } else if (action === 'resolve' || action === 'dismiss') {
                    const status = action === 'dismiss' ? 'dismissed' : 'resolved';
                    const confirmed = await window.confirmAction?.(
                        `${this.label(status)} case`,
                        `Confirm ${status} for case ${button.dataset.caseNo || id}?`,
                        { confirmText: this.label(status), danger: status === 'dismissed' },
                    );
                    if (confirmed === false) return;
                    await this.request(`/staff-discipline/case/${id}/resolve`, 'POST', { status });
                }
                this.notify('Staff disciplinary case updated.', 'success');
                await this.loadCases();
            } catch (error) {
                this.notify(error.message || 'Could not update the case.', 'error');
            }
        },

        async createCase(event) {
            event.preventDefault();
            const form = this.dom.staffDisciplineForm;
            if (!form?.reportValidity()) return;
            const data = {
                staff_id: this.dom.sdStaffSelect?.value,
                category: this.dom.sdCategory?.value,
                severity: this.dom.sdSeverity?.value,
                incident_date: this.dom.sdIncidentDate?.value,
                department_id: this.dom.sdDepartmentSelect?.value,
                description: this.dom.sdDescription?.value.trim(),
                action_taken: this.dom.sdActionTaken?.value.trim(),
            };
            const submit = form.querySelector('[type="submit"]');
            if (submit) submit.disabled = true;
            try {
                await this.request('/staff-discipline/case', 'POST', data);
                form.reset();
                const modal = bootstrap.Modal.getInstance(this.dom.staffDisciplineModal);
                modal?.hide();
                this.notify('Staff disciplinary case logged.', 'success');
                await this.loadCases();
            } catch (error) {
                this.notify(error.message || 'Could not log the case.', 'error');
            } finally {
                if (submit) submit.disabled = false;
            }
        },

        openCreateModal() {
            if (!this.hasAny(['staff_discipline_create', 'staff_discipline_manage'])) return;
            bootstrap.Modal.getOrCreateInstance(this.dom.staffDisciplineModal).show();
        },

        exportCsv() {
            if (window.AuthContext?.canExport && !window.AuthContext.canExport('staff_discipline')) return;
            const fields = [
                ['case_no', 'Case No'], ['staff_name', 'Staff'], ['staff_no', 'Staff No'],
                ['department_name', 'Department'], ['category', 'Category'], ['severity', 'Severity'],
                ['incident_date', 'Incident Date'], ['status', 'Status'], ['assigned_to_name', 'Assigned To'],
            ];
            const csv = [fields.map(([, label]) => this.csvCell(label)).join(','), ...this.rows.map(row =>
                fields.map(([key]) => this.csvCell(row[key] ?? '')).join(','),
            )].join('\r\n');
            window.KingswayFileLifecycle?.exportText(csv, 'staff_disciplinary_cases.csv', 'text/csv;charset=utf-8;');
        },

        print() {
            if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('staff_discipline')) return;
            window.print();
        },

        renderMessage(message) {
            if (!this.dom.staffDisciplineBody) return;
            this.dom.staffDisciplineBody.innerHTML = `<tr><td colspan="9" class="text-center py-4">${this.escape(message)}</td></tr>`;
            this.dom.staffDisciplineEmpty?.classList.add('d-none');
        },

        actionButton(action, id, label) {
            return `<button type="button" class="btn btn-outline-secondary" data-action="${action}" data-case-id="${Number(id)}">${this.escape(label)}</button>`;
        },

        hasAny(codes) {
            return window.AuthContext?.hasAnyPermission?.(codes) || false;
        },

        severityClass(value) {
            return ({ low: 'secondary', medium: 'info', high: 'warning', critical: 'danger' })[value] || 'secondary';
        },

        label(value) {
            return String(value || '').replaceAll('_', ' ').replace(/\b\w/g, char => char.toUpperCase());
        },

        escape(value) {
            return String(value ?? '').replace(/[&<>"']/g, char => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
            })[char]);
        },

        csvCell(value) {
            return `"${String(value ?? '').replaceAll('"', '""')}"`;
        },

        notify(message, type = 'info') {
            window.showNotification?.(message, type);
        },
    };

    window.StaffDisciplineController = controller;
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => controller.init(), { once: true });
    } else {
        controller.init();
    }
})();
