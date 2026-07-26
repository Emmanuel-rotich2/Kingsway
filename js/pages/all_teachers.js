/**
 * All Teachers Controller
 * Handles all_teachers.php as a teacher-specific staff directory.
 * Uses window.API.staff.getTeachers from api.js.
 */
const AllTeachersController = {
    initialized: false,
    initializationPromise: null,
    eventsBound: false,

    state: {
        teachers: [],
        filteredTeachers: [],
        activePrintPayload: null,
        filters: {
            search: '',
            department_id: '',
            subject_id: '',
            school_level: '',
            teaching_role: ''
        }
    },

    async init() {
        if (this.initializationPromise) {
            return this.initializationPromise;
        }

        this.initializationPromise = this._initialize().catch((error) => {
            this.initializationPromise = null;
            throw error;
        });

        return this.initializationPromise;
    },

    async _initialize() {
        if (this.initialized) {
            return this;
        }

        if (window.AuthContext?.ready) {
            await window.AuthContext.ready();
        }

        if (!window.AuthContext?.isAuthenticated?.()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return this;
        }

        if (window.StaffAccess?.init) {
            await StaffAccess.init();
        }

        const canViewTeachers = window.StaffAccess
            ? StaffAccess.can('staff.teachers.view')
            : AuthContext.canView('staff');

        if (!canViewTeachers) {
            this.showToast('You do not have permission to view teachers', 'error');
            return this;
        }

        this.bindEvents();
        await this.loadTeachers();

        this.initialized = true;
        return this;
    },

    bindEvents() {
        if (this.eventsBound) return;
        this.eventsBound = true;

        document.getElementById('searchTeacher')?.addEventListener('input', (event) => {
            this.state.filters.search = event.target.value || '';
            this.applyFilters();
        });

        [
            ['filterDepartment', 'department_id'],
            ['filterSubject', 'subject_id'],
            ['filterLevel', 'school_level'],
            ['filterTeachingRole', 'teaching_role']
        ].forEach(([elementId, filterKey]) => {
            document.getElementById(elementId)?.addEventListener('change', (event) => {
                this.state.filters[filterKey] = event.target.value || '';
                this.applyFilters();
            });
        });

        document.getElementById('exportTeachers')?.addEventListener('click', () => {
            if (AuthContext.canExport('staff')) {
                this.exportTeachers();
            } else {
                this.showToast('You do not have permission to export teachers', 'error');
            }
        });

        document.getElementById('teacherInsightPrintBtn')?.addEventListener('click', () => {
            void this.printActiveModalSummary();
        });
    },

    async loadTeachers() {
        try {
            const response = await window.API.staff.getTeachers({});
            this.state.teachers = this.extractList(response, 'teachers').map((teacher) => this.normalizeTeacher(teacher));
            this.state.filteredTeachers = [...this.state.teachers];
            this.populateFiltersFromTeachers();
            this.render();
        } catch (error) {
            console.error('[AllTeachersController] Failed to load teachers:', error);
            this.showToast(error.message || 'Failed to load teachers', 'error');
            this.renderError(error);
        }
    },

    extractList(response, key) {
        if (Array.isArray(response)) return response;
        if (Array.isArray(response?.[key])) return response[key];
        if (Array.isArray(response?.data?.[key])) return response.data[key];
        if (Array.isArray(response?.data?.data?.[key])) return response.data.data[key];
        if (Array.isArray(response?.data)) return response.data;
        return [];
    },

    normalizeTeacher(teacher) {
        const learningAreas = this.listFrom(teacher.learning_area_names || teacher.learning_areas || teacher.subject_names || teacher.subjects);
        const classes = this.listFrom(teacher.class_names || teacher.classes);
        const schoolLevels = this.listFrom(teacher.school_level_names || teacher.school_levels);
        const teachingRoles = this.listFrom(teacher.teaching_roles || teacher.role_name || teacher.role_names || teacher.assignment_roles);

        return {
            ...teacher,
            full_name: this.compactText(teacher.full_name || `${teacher.first_name || ''} ${teacher.last_name || ''}`) || 'Unnamed teacher',
            staff_no: teacher.staff_no || teacher.employee_id || '',
            employee_id: teacher.employee_id || teacher.staff_no || '',
            learning_area_names: learningAreas,
            subject_names: learningAreas,
            subject_ids: this.idListFrom(teacher.subject_ids),
            class_names: classes,
            class_ids: this.idListFrom(teacher.class_ids),
            school_level_names: schoolLevels,
            school_level_ids: this.idListFrom(teacher.school_level_ids),
            teaching_roles: teachingRoles,
            department_name: teacher.department_name || '',
            department_id: teacher.department_id || '',
            is_class_teacher: Number(teacher.is_class_teacher || teacher.class_teacher_count || 0) > 0,
            is_hod: Number(teacher.is_hod || teacher.hod_count || 0) > 0,
            subject_teacher_count: Number(teacher.subject_teacher_count || 0),
            assignment_count: Number(teacher.assignment_count || 0),
            periods_per_week: Number(teacher.periods_per_week || 0)
        };
    },

    listFrom(value) {
        if (Array.isArray(value)) {
            return value.map((item) => String(item || '').trim()).filter(Boolean);
        }

        return String(value || '')
            .split(',')
            .map((item) => item.trim())
            .filter(Boolean);
    },

    idListFrom(value) {
        if (Array.isArray(value)) {
            return value.map((item) => Number(item)).filter((item) => Number.isFinite(item) && item > 0);
        }

        return this.listFrom(value)
            .map((item) => Number(item))
            .filter((item) => Number.isFinite(item) && item > 0);
    },

    populateFiltersFromTeachers() {
        this.populateSelect(
            'filterDepartment',
            'Teaching Departments',
            this.uniqueOptions(this.state.teachers, 'department_id', 'department_name')
        );

        this.populateSelect(
            'filterSubject',
            'Learning Areas',
            this.uniqueListOptions(this.state.teachers, 'subject_ids', 'learning_area_names')
        );

        this.populateSelect(
            'filterLevel',
            'School Levels',
            this.uniqueListOptions(this.state.teachers, 'school_level_names', 'school_level_names')
        );

        this.populateSelect(
            'filterTeachingRole',
            'Teaching Roles',
            this.uniqueListOptions(this.state.teachers, 'teaching_roles', 'teaching_roles')
        );
    },

    uniqueOptions(rows, valueKey, labelKey) {
        const map = new Map();
        rows.forEach((row) => {
            const value = row[valueKey];
            const label = row[labelKey];
            if (value && label && !map.has(String(value))) {
                map.set(String(value), String(label));
            }
        });

        return [...map.entries()]
            .map(([value, label]) => ({ value, label }))
            .sort((a, b) => a.label.localeCompare(b.label));
    },

    uniqueListOptions(rows, valueKey, labelKey) {
        const map = new Map();
        rows.forEach((row) => {
            const values = Array.isArray(row[valueKey]) ? row[valueKey] : this.listFrom(row[valueKey]);
            const labels = Array.isArray(row[labelKey]) ? row[labelKey] : this.listFrom(row[labelKey]);

            labels.forEach((label, index) => {
                const value = values[index] ?? label;
                if (value && label && !map.has(String(value))) {
                    map.set(String(value), String(label));
                }
            });
        });

        return [...map.entries()]
            .map(([value, label]) => ({ value, label }))
            .sort((a, b) => a.label.localeCompare(b.label));
    },

    populateSelect(elementId, defaultLabel, options) {
        const select = document.getElementById(elementId);
        if (!select) return;

        const currentValue = select.value;
        select.innerHTML = `<option value="">${this.escapeHtml(defaultLabel)}</option>` +
            options.map((option) => `<option value="${this.escapeHtml(option.value)}">${this.escapeHtml(option.label)}</option>`).join('');
        select.value = currentValue;
    },

    applyFilters() {
        const filters = this.state.filters;
        const search = filters.search.trim().toLowerCase();

        this.state.filteredTeachers = this.state.teachers.filter((teacher) => {
            if (search && !this.teacherMatchesSearch(teacher, search)) return false;
            if (filters.department_id && String(teacher.department_id) !== String(filters.department_id)) return false;
            if (filters.subject_id && !teacher.subject_ids.map(String).includes(String(filters.subject_id))) return false;
            if (filters.school_level && !teacher.school_level_names.includes(filters.school_level)) return false;
            if (filters.teaching_role && !teacher.teaching_roles.includes(filters.teaching_role)) return false;
            return true;
        });

        this.render();
    },

    teacherMatchesSearch(teacher, search) {
        return [
            teacher.full_name,
            teacher.email,
            teacher.staff_no,
            teacher.department_name,
            teacher.teaching_roles.join(' '),
            teacher.learning_area_names.join(' '),
            teacher.class_names.join(' '),
            teacher.school_level_names.join(' ')
        ].some((value) => String(value || '').toLowerCase().includes(search));
    },

    render() {
        this.renderTeachersTable();
        this.updateStats();
    },

    renderTeachersTable() {
        const tbody = document.querySelector('#teachersTable tbody');
        if (!tbody) return;

        const teachers = this.state.filteredTeachers;
        if (teachers.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No teachers match the current filters.</td></tr>';
            return;
        }

        tbody.innerHTML = teachers.map((teacher) => this.renderTeacherRow(teacher)).join('');
    },

    renderTeacherRow(teacher) {
        return `
            <tr>
                <td>${this.renderAvatar(teacher)}</td>
                <td>
                    <strong>${this.escapeHtml(teacher.full_name)}</strong>
                    <br><small class="text-muted">${this.escapeHtml(teacher.email || 'No email on user account')}</small>
                </td>
                <td>${this.escapeHtml(teacher.staff_no || '--')}</td>
                <td>${this.renderBadges(teacher.teaching_roles, 'secondary', 'No teaching role')}</td>
                <td>${this.renderBadges(teacher.learning_area_names, 'info', 'No learning area assigned')}</td>
                <td>
                    <div>${this.renderBadges(teacher.class_names, 'primary', 'No class assigned')}</div>
                    <small class="text-muted">${this.escapeHtml(teacher.school_level_names.join(', ') || 'No school level')}</small>
                </td>
                <td>${this.escapeHtml(teacher.department_name || '--')}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="AllTeachersController.viewTeacher(${Number(teacher.id)})" title="View">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-info" onclick="AllTeachersController.viewAssignments(${Number(teacher.id)})" title="View Assignments">
                            <i class="bi bi-journal-text"></i>
                        </button>
                        <button class="btn btn-outline-warning" onclick="AllTeachersController.viewWorkload(${Number(teacher.id)})" title="View Workload">
                            <i class="bi bi-bar-chart"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    },

    renderAvatar(teacher) {
        const photoUrl = String(teacher.photo_url || '').trim();
        if (photoUrl && !photoUrl.includes('/images/placeholders/')) {
            return `<img src="${this.escapeHtml(this.resolveAssetUrl(photoUrl))}" alt="${this.escapeHtml(teacher.full_name)}" class="rounded-circle" width="40" height="40" onerror="this.replaceWith(AllTeachersController.initialsNode('${this.escapeJs(teacher.full_name)}'))">`;
        }

        return this.initialsHtml(teacher.full_name);
    },

    resolveAssetUrl(url) {
        if (/^https?:\/\//i.test(url) || url.startsWith('/')) return url;
        return `${window.APP_BASE || ''}/${url.replace(/^\/+/, '')}`;
    },

    initialsHtml(name) {
        const initials = this.initials(name);
        return `<div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" style="width:40px;height:40px">${this.escapeHtml(initials)}</div>`;
    },

    initialsNode(name) {
        const node = document.createElement('div');
        node.className = 'rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center';
        node.style.width = '40px';
        node.style.height = '40px';
        node.textContent = this.initials(name);
        return node;
    },

    initials(name) {
        const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        return (parts[0]?.[0] || '?') + (parts[1]?.[0] || '');
    },

    renderBadges(values, tone, emptyLabel) {
        if (!values.length) {
            return `<span class="text-muted">${this.escapeHtml(emptyLabel)}</span>`;
        }

        return values.slice(0, 3)
            .map((value) => `<span class="badge bg-${tone} me-1 mb-1">${this.escapeHtml(value)}</span>`)
            .join('') +
            (values.length > 3 ? `<span class="badge bg-light text-dark">+${values.length - 3}</span>` : '');
    },

    updateStats() {
        this.setText('totalTeachers', this.state.filteredTeachers.length);
        this.setText('classTeachers', this.state.filteredTeachers.filter((teacher) => teacher.is_class_teacher).length);
        this.setText('hods', this.state.filteredTeachers.filter((teacher) => (
            teacher.subject_teacher_count > 0 ||
            teacher.learning_area_names.length > 0 ||
            teacher.teaching_roles.includes('Subject Teacher')
        )).length);
    },

    renderError(error) {
        const tbody = document.querySelector('#teachersTable tbody');
        if (!tbody) return;
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center text-danger py-4">
                    ${this.escapeHtml(error?.message || 'Failed to load teachers')}
                </td>
            </tr>
        `;
    },

    viewTeacher(teacherId) {
        const teacher = this.findTeacher(teacherId);
        if (!teacher) return;

        const insights = teacher.insights || {};
        const workload = insights.workload || {};
        const attendance = insights.attendance || {};
        const performance = insights.performance?.latest || null;
        const lessonPlans = insights.lesson_plans || {};
        const observations = insights.observations || {};

        this.openInsightModal(
            'Teacher Profile',
            `${teacher.full_name} · ${teacher.staff_no || 'No staff number'}`,
            `
                ${this.renderTeacherHero(teacher)}
                <div class="row g-3 mb-3">
                    ${this.metricCard('Teaching Roles', teacher.teaching_roles.length, teacher.teaching_roles.join(', ') || 'No role assigned')}
                    ${this.metricCard('Learning Areas', teacher.learning_area_names.length, teacher.learning_area_names.join(', ') || 'No learning area assigned')}
                    ${this.metricCard('Classes', teacher.class_names.length, teacher.class_names.join(', ') || 'No class assigned')}
                    ${this.metricCard('Load Status', this.titleCase(workload.status || 'not scheduled'), `${workload.periods_per_week || 0} periods/week`)}
                </div>
                <div class="row g-3">
                    <div class="col-lg-6">
                        <h6>Profile Context</h6>
                        ${this.definitionList({
                            Department: teacher.department_name || '--',
                            Position: teacher.position || '--',
                            'Staff Type': teacher.staff_type_name || '--',
                            Category: teacher.staff_category_name || '--',
                            Email: teacher.email || '--',
                            Phone: teacher.phone || '--',
                            'TSC No': teacher.tsc_no || '--',
                            Status: teacher.status || '--'
                        })}
                    </div>
                    <div class="col-lg-6">
                        <h6>Current Signals</h6>
                        ${this.definitionList({
                            'Attendance, 30 days': attendance.attendance_rate === null ? 'No marked days' : `${attendance.attendance_rate}%`,
                            'Marked Days': attendance.marked_days ?? 0,
                            'Late Days': attendance.late_days ?? 0,
                            'Latest Review': performance ? `${performance.review_date || '--'} · ${performance.overall_rating || performance.performance_grade || '--'}` : 'No review recorded',
                            'Lesson Plans': `${lessonPlans.total || 0} total, ${lessonPlans.approved || 0} approved`,
                            'Observations': observations.total ? `${observations.total} · avg ${observations.average_rating || '--'}` : 'No observations recorded'
                        })}
                    </div>
                    <div class="col-lg-6">
                        <h6>Qualifications</h6>
                        ${this.renderQualificationList(insights.qualifications || [])}
                    </div>
                    <div class="col-lg-6">
                        <h6>Experience</h6>
                        ${this.renderExperienceList(insights.experience || [])}
                    </div>
                    <div class="col-12">
                        <h6>Analyst Notes</h6>
                        ${this.renderAnalystNotes(teacher)}
                    </div>
                </div>
            `,
            this.buildTeacherProfilePrintPayload(teacher)
        );
    },

    viewAssignments(teacherId) {
        const teacher = this.findTeacher(teacherId);
        if (!teacher) return;

        const assignments = teacher.insights?.assignments || [];
        this.openInsightModal(
            'Teacher Assignments',
            `${teacher.full_name} · ${assignments.length} active assignment${assignments.length === 1 ? '' : 's'}`,
            `
                ${this.renderTeacherHero(teacher)}
                <div class="row g-3 mb-3">
                    ${this.metricCard('Classes', teacher.class_names.length, teacher.class_names.join(', ') || 'No class assigned')}
                    ${this.metricCard('Learning Areas', teacher.learning_area_names.length, teacher.learning_area_names.join(', ') || 'No learning area assigned')}
                    ${this.metricCard('School Levels', teacher.school_level_names.length, teacher.school_level_names.join(', ') || 'No school level')}
                    ${this.metricCard('Assignments', assignments.length, 'Active academic-year records')}
                </div>
                ${this.renderAssignmentsTable(assignments)}
            `,
            this.buildTeacherAssignmentsPrintPayload(teacher)
        );
    },

    viewWorkload(teacherId) {
        const teacher = this.findTeacher(teacherId);
        if (!teacher) return;

        const workload = teacher.insights?.workload || {};
        const assignments = teacher.insights?.assignments || [];
        const lessonPlans = teacher.insights?.lesson_plans || {};
        const observations = teacher.insights?.observations || {};
        const performance = teacher.insights?.performance?.latest || null;

        this.openInsightModal(
            'Teacher Workload',
            `${teacher.full_name} · ${this.titleCase(workload.status || 'not scheduled')}`,
            `
                ${this.renderTeacherHero(teacher)}
                <div class="row g-3 mb-3">
                    ${this.metricCard('Periods / Week', workload.periods_per_week || 0, workload.scheduled_periods ? 'From timetable' : 'From assignment records')}
                    ${this.metricCard('Active Assignments', workload.active_assignments || 0, `${workload.classes_count || 0} classes`)}
                    ${this.metricCard('Learning Areas', workload.learning_areas_count || 0, 'Assigned with subject_id')}
                    ${this.metricCard('Load Status', this.titleCase(workload.status || 'not scheduled'), this.workloadInterpretation(workload.status))}
                </div>
                <div class="row g-3">
                    <div class="col-lg-6">
                        <h6>Workload Breakdown</h6>
                        ${this.definitionList({
                            'Class-teacher classes': workload.class_teacher_classes || 0,
                            'Subject assignments': assignments.filter((item) => item.role === 'subject_teacher').length,
                            'Scheduled timetable periods': workload.scheduled_periods || 0,
                            'Lesson plans': `${lessonPlans.total || 0} total, ${lessonPlans.approved || 0} approved, ${lessonPlans.drafts || 0} drafts`,
                            'Latest lesson plan': lessonPlans.latest_lesson_date || '--'
                        })}
                    </div>
                    <div class="col-lg-6">
                        <h6>Quality Signals</h6>
                        ${this.definitionList({
                            'Latest performance review': performance ? `${performance.review_date || '--'} · ${performance.overall_rating || performance.performance_grade || '--'}` : 'No review recorded',
                            'Observation count': observations.total || 0,
                            'Average observation rating': observations.average_rating ?? '--',
                            'Latest observation': observations.latest_observation_date || '--'
                        })}
                    </div>
                    <div class="col-12">
                        <h6>Assignments Behind This Workload</h6>
                        ${this.renderAssignmentsTable(assignments)}
                    </div>
                </div>
            `,
            this.buildTeacherWorkloadPrintPayload(teacher)
        );
    },

    findTeacher(teacherId) {
        const teacher = this.state.teachers.find((item) => String(item.id) === String(teacherId));
        if (!teacher) {
            this.showToast('Teacher record was not found on this page', 'error');
        }
        return teacher || null;
    },

    openInsightModal(title, subtitle, bodyHtml, printPayload = null) {
        const modal = document.getElementById('teacherInsightModal');
        const titleNode = document.getElementById('teacherInsightModalLabel');
        const subtitleNode = document.getElementById('teacherInsightModalSubtitle');
        const bodyNode = document.getElementById('teacherInsightModalBody');
        const printButton = document.getElementById('teacherInsightPrintBtn');

        if (!modal || !titleNode || !subtitleNode || !bodyNode) {
            this.showToast('Teacher modal is not available on this page', 'error');
            return;
        }

        this.state.activePrintPayload = printPayload;
        titleNode.textContent = title;
        subtitleNode.textContent = subtitle;
        bodyNode.innerHTML = bodyHtml;
        if (printButton) {
            printButton.hidden = !printPayload;
            printButton.disabled = !printPayload;
        }
        bootstrap.Modal.getOrCreateInstance(modal).show();
    },

    async printActiveModalSummary() {
        const payload = this.state.activePrintPayload;
        if (!payload) {
            this.showToast('No printable teacher summary is open', 'warning');
            return;
        }

        if (!window.PrintManager?.printRecord) {
            this.showToast('Print service is not available on this page', 'error');
            return;
        }

        const button = document.getElementById('teacherInsightPrintBtn');
        const originalHtml = button?.innerHTML || '';

        try {
            if (button) {
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generating';
            }

            await window.PrintManager.printRecord({
                ...payload,
                orientation: 'portrait',
                paperSize: 'A4',
                reportCodePrefix: 'TCHR',
                preferApiHelper: true
            });
        } catch (error) {
            console.error('[AllTeachersController] Teacher summary print failed:', error);
            this.showToast(error.message || 'Unable to generate teacher summary PDF', 'error');
        } finally {
            if (button) {
                button.disabled = false;
                button.innerHTML = originalHtml;
            }
        }
    },

    renderTeacherHero(teacher) {
        return `
            <div class="d-flex align-items-start gap-3 mb-4">
                ${this.renderAvatar(teacher)}
                <div>
                    <h5 class="mb-1">${this.escapeHtml(teacher.full_name)}</h5>
                    <div class="text-muted">${this.escapeHtml(teacher.staff_no || '--')} · ${this.escapeHtml(teacher.department_name || '--')}</div>
                    <div class="mt-2">${this.renderBadges(teacher.teaching_roles, 'secondary', 'No teaching role')}</div>
                </div>
            </div>
        `;
    },

    metricCard(label, value, hint) {
        return `
            <div class="col-md-3">
                <div class="border rounded p-3 h-100">
                    <div class="small text-muted">${this.escapeHtml(label)}</div>
                    <div class="fs-5 fw-semibold">${this.escapeHtml(value)}</div>
                    <div class="small text-muted">${this.escapeHtml(hint || '')}</div>
                </div>
            </div>
        `;
    },

    definitionList(items) {
        return `
            <dl class="row mb-0">
                ${Object.entries(items).map(([label, value]) => `
                    <dt class="col-sm-5 text-muted fw-normal">${this.escapeHtml(label)}</dt>
                    <dd class="col-sm-7">${this.escapeHtml(value ?? '--')}</dd>
                `).join('')}
            </dl>
        `;
    },

    renderQualificationList(rows) {
        if (!rows.length) return '<p class="text-muted mb-0">No qualifications recorded.</p>';
        return `
            <div class="list-group list-group-flush">
                ${rows.map((row) => `
                    <div class="list-group-item px-0">
                        <div class="fw-semibold">${this.escapeHtml(row.title || '--')}</div>
                        <div class="small text-muted">${this.escapeHtml(row.institution || '--')} · ${this.escapeHtml(row.year_obtained || '--')} · ${this.escapeHtml(row.qualification_type || '--')}</div>
                    </div>
                `).join('')}
            </div>
        `;
    },

    renderExperienceList(rows) {
        if (!rows.length) return '<p class="text-muted mb-0">No prior experience recorded.</p>';
        return `
            <div class="list-group list-group-flush">
                ${rows.map((row) => `
                    <div class="list-group-item px-0">
                        <div class="fw-semibold">${this.escapeHtml(row.position || '--')}</div>
                        <div class="small text-muted">${this.escapeHtml(row.organization || '--')} · ${this.escapeHtml(row.start_date || '--')} to ${this.escapeHtml(row.end_date || 'Present')}</div>
                    </div>
                `).join('')}
            </div>
        `;
    },

    renderAssignmentsTable(assignments) {
        if (!assignments.length) {
            return '<div class="alert alert-warning mb-0">No active assignment rows were found for the current academic year.</div>';
        }

        return `
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>Learning Area</th>
                            <th>Class</th>
                            <th>Level</th>
                            <th>Periods</th>
                            <th>Academic Year</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${assignments.map((item) => `
                            <tr>
                                <td>${this.escapeHtml(item.role_label || this.titleCase(item.role || '--'))}</td>
                                <td>${this.escapeHtml(item.learning_area_name || 'No learning area assigned')}</td>
                                <td>${this.escapeHtml(item.class_name || '--')}${item.stream_name ? `<br><small class="text-muted">${this.escapeHtml(item.stream_name)}</small>` : ''}</td>
                                <td>${this.escapeHtml(item.school_level || '--')}</td>
                                <td>${this.escapeHtml(item.periods_per_week || 0)}</td>
                                <td>${this.escapeHtml(item.academic_year || '--')}</td>
                                <td><span class="badge bg-success">${this.escapeHtml(item.status || 'active')}</span></td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    },

    renderAnalystNotes(teacher) {
        const notes = [];
        const workload = teacher.insights?.workload || {};
        const assignments = teacher.insights?.assignments || [];

        if (!teacher.email) notes.push('No email is attached to the user account.');
        if (!teacher.learning_area_names.length && teacher.teaching_roles.includes('Subject Teacher')) {
            notes.push('This teacher has a Subject Teacher role but no active learning-area assignment.');
        }
        if (teacher.is_class_teacher && !teacher.class_names.length) {
            notes.push('This teacher is marked as a class teacher but no active class assignment is visible.');
        }
        if ((workload.periods_per_week || 0) === 0 && assignments.length > 0) {
            notes.push('Assignments exist, but periods per week are not populated, so workload cannot be measured accurately yet.');
        }
        if (!notes.length) notes.push('No immediate data quality issue detected from the available teacher records.');

        return `<ul class="mb-0">${notes.map((note) => `<li>${this.escapeHtml(note)}</li>`).join('')}</ul>`;
    },

    workloadInterpretation(status) {
        const labels = {
            balanced: 'Within normal planning range',
            underloaded: 'Below normal planning range',
            overloaded: 'Above normal planning range',
            'not scheduled': 'No timetable periods or assignment periods recorded'
        };
        return labels[status] || 'No workload classification';
    },

    buildTeacherProfilePrintPayload(teacher) {
        const insights = teacher.insights || {};
        const workload = insights.workload || {};
        const attendance = insights.attendance || {};
        const performance = insights.performance?.latest || {};
        const lessonPlans = insights.lesson_plans || {};
        const observations = insights.observations || {};

        return {
            title: 'Teacher Profile Summary',
            subtitle: `${teacher.full_name} · ${teacher.staff_no || 'No staff number'}`,
            description: 'Teacher profile, assignment, workload and quality signals',
            filename: this.printFilename('teacher_profile', teacher),
            sections: [
                {
                    title: 'Identity',
                    fields: this.fieldsFrom({
                        Name: teacher.full_name,
                        'Staff No': teacher.staff_no || '--',
                        Email: teacher.email || '--',
                        Phone: teacher.phone || '--',
                        Department: teacher.department_name || '--',
                        Position: teacher.position || '--',
                        'Staff Type': teacher.staff_type_name || '--',
                        Category: teacher.staff_category_name || '--',
                        Status: teacher.status || '--'
                    })
                },
                {
                    title: 'Teaching Context',
                    fields: this.fieldsFrom({
                        'Teaching Roles': teacher.teaching_roles.join(', ') || '--',
                        'Learning Areas': teacher.learning_area_names.join(', ') || 'No learning area assigned',
                        Classes: teacher.class_names.join(', ') || 'No class assigned',
                        'School Levels': teacher.school_level_names.join(', ') || '--',
                        'Active Assignments': workload.active_assignments || 0,
                        'Periods / Week': workload.periods_per_week || 0,
                        'Workload Status': this.titleCase(workload.status || 'not scheduled')
                    })
                },
                {
                    title: 'Operational Signals',
                    fields: this.fieldsFrom({
                        '30-Day Attendance': attendance.attendance_rate === null || attendance.attendance_rate === undefined ? 'No marked days' : `${attendance.attendance_rate}%`,
                        'Marked Days': attendance.marked_days ?? 0,
                        'Late Days': attendance.late_days ?? 0,
                        'Latest Review': performance.review_date ? `${performance.review_date} · ${performance.overall_rating || performance.performance_grade || '--'}` : 'No review recorded',
                        'Lesson Plans': `${lessonPlans.total || 0} total, ${lessonPlans.approved || 0} approved`,
                        Observations: observations.total ? `${observations.total}, average ${observations.average_rating || '--'}` : 'No observations recorded'
                    })
                },
                this.rowsToPrintSection('Qualifications', insights.qualifications || [], ['title', 'institution', 'year_obtained', 'qualification_type']),
                this.rowsToPrintSection('Experience', insights.experience || [], ['position', 'organization', 'start_date', 'end_date']),
                {
                    title: 'Analyst Notes',
                    fields: this.analystNoteStrings(teacher).map((note, index) => ({
                        label: `Note ${index + 1}`,
                        value: note
                    }))
                }
            ]
        };
    },

    buildTeacherAssignmentsPrintPayload(teacher) {
        const assignments = teacher.insights?.assignments || [];

        return {
            title: 'Teacher Assignment Summary',
            subtitle: `${teacher.full_name} · ${teacher.staff_no || 'No staff number'}`,
            description: 'Current academic-year teaching assignments',
            filename: this.printFilename('teacher_assignments', teacher),
            sections: [
                {
                    title: 'Teacher',
                    fields: this.fieldsFrom({
                        Name: teacher.full_name,
                        'Staff No': teacher.staff_no || '--',
                        Department: teacher.department_name || '--',
                        'Teaching Roles': teacher.teaching_roles.join(', ') || '--'
                    })
                },
                {
                    title: 'Assignment Summary',
                    fields: this.fieldsFrom({
                        'Active Assignments': assignments.length,
                        Classes: teacher.class_names.join(', ') || 'No class assigned',
                        'School Levels': teacher.school_level_names.join(', ') || '--',
                        'Learning Areas': teacher.learning_area_names.join(', ') || 'No learning area assigned'
                    })
                },
                this.rowsToPrintSection(
                    'Assignment Rows',
                    assignments,
                    ['role_label', 'learning_area_name', 'class_name', 'school_level', 'periods_per_week', 'academic_year', 'status']
                )
            ]
        };
    },

    buildTeacherWorkloadPrintPayload(teacher) {
        const workload = teacher.insights?.workload || {};
        const lessonPlans = teacher.insights?.lesson_plans || {};
        const observations = teacher.insights?.observations || {};
        const performance = teacher.insights?.performance?.latest || {};
        const assignments = teacher.insights?.assignments || [];

        return {
            title: 'Teacher Workload Summary',
            subtitle: `${teacher.full_name} · ${teacher.staff_no || 'No staff number'}`,
            description: 'Teacher workload and related planning signals',
            filename: this.printFilename('teacher_workload', teacher),
            sections: [
                {
                    title: 'Workload Snapshot',
                    fields: this.fieldsFrom({
                        'Periods / Week': workload.periods_per_week || 0,
                        'Load Status': this.titleCase(workload.status || 'not scheduled'),
                        'Active Assignments': workload.active_assignments || 0,
                        'Classes Count': workload.classes_count || 0,
                        'Learning Areas Count': workload.learning_areas_count || 0,
                        'Scheduled Timetable Periods': workload.scheduled_periods || 0,
                        Interpretation: this.workloadInterpretation(workload.status)
                    })
                },
                {
                    title: 'Quality Signals',
                    fields: this.fieldsFrom({
                        'Lesson Plans': `${lessonPlans.total || 0} total, ${lessonPlans.approved || 0} approved, ${lessonPlans.drafts || 0} drafts`,
                        'Latest Lesson Date': lessonPlans.latest_lesson_date || '--',
                        Observations: observations.total || 0,
                        'Average Observation Rating': observations.average_rating ?? '--',
                        'Latest Observation': observations.latest_observation_date || '--',
                        'Latest Performance Review': performance.review_date ? `${performance.review_date} · ${performance.overall_rating || performance.performance_grade || '--'}` : 'No review recorded'
                    })
                },
                this.rowsToPrintSection(
                    'Assignments Behind Workload',
                    assignments,
                    ['role_label', 'learning_area_name', 'class_name', 'school_level', 'periods_per_week', 'academic_year', 'status']
                )
            ]
        };
    },

    fieldsFrom(values) {
        return Object.entries(values).map(([label, value]) => ({
            label,
            value: value ?? '--'
        }));
    },

    rowsToPrintSection(title, rows, keys) {
        if (!rows.length) {
            return {
                title,
                fields: [{ label: 'Status', value: 'No records found' }]
            };
        }

        return {
            title,
            fields: rows.flatMap((row, index) => [
                { label: `Record ${index + 1}`, value: '' },
                ...keys.map((key) => ({
                    label: this.titleCase(key),
                    value: row[key] ?? '--'
                }))
            ])
        };
    },

    analystNoteStrings(teacher) {
        const notes = [];
        const workload = teacher.insights?.workload || {};
        const assignments = teacher.insights?.assignments || [];

        if (!teacher.email) notes.push('No email is attached to the user account.');
        if (!teacher.learning_area_names.length && teacher.teaching_roles.includes('Subject Teacher')) {
            notes.push('This teacher has a Subject Teacher role but no active learning-area assignment.');
        }
        if (teacher.is_class_teacher && !teacher.class_names.length) {
            notes.push('This teacher is marked as a class teacher but no active class assignment is visible.');
        }
        if ((workload.periods_per_week || 0) === 0 && assignments.length > 0) {
            notes.push('Assignments exist, but periods per week are not populated, so workload cannot be measured accurately yet.');
        }

        return notes.length ? notes : ['No immediate data quality issue detected from the available teacher records.'];
    },

    printFilename(prefix, teacher) {
        const name = String(teacher.staff_no || teacher.full_name || 'teacher')
            .trim()
            .replace(/[^A-Za-z0-9._-]+/g, '_')
            .replace(/^[._-]+|[._-]+$/g, '');
        return `${prefix}_${name || 'teacher'}_${new Date().toISOString().slice(0, 10)}`;
    },

    exportTeachers() {
        const teachers = this.state.filteredTeachers;
        if (!teachers.length) return;

        const headers = ['#', 'Name', 'Staff No', 'Email', 'Teaching Roles', 'Learning Areas', 'Classes', 'School Levels', 'Department'];
        const rows = teachers.map((teacher, index) => [
            index + 1,
            teacher.full_name,
            teacher.staff_no || '--',
            teacher.email || '',
            teacher.teaching_roles.join('; '),
            teacher.learning_area_names.join('; '),
            teacher.class_names.join('; '),
            teacher.school_level_names.join('; '),
            teacher.department_name || '--'
        ]);

        const csv = headers.join(',') + '\n' +
            rows.map((row) => row.map((value) => `"${String(value || '').replace(/"/g, '""')}"`).join(',')).join('\n');

        const link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
        link.download = `teachers_${new Date().toISOString().slice(0, 10)}.csv`;
        link.click();
        URL.revokeObjectURL(link.href);
    },

    compactText(value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
    },

    titleCase(value) {
        return String(value || '')
            .replace(/_/g, ' ')
            .replace(/\w\S*/g, (word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase());
    },

    setText(id, value) {
        const element = document.getElementById(id);
        if (element) element.textContent = value;
    },

    showToast(message, type = 'info') {
        if (typeof showNotification === 'function') {
            showNotification(message, type);
            return;
        }

        console[type === 'error' ? 'error' : 'log'](message);
    },

    escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = String(value ?? '');
        return div.innerHTML;
    },

    escapeJs(value) {
        return String(value ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    }
};

window.AllTeachersController = AllTeachersController;

function initializeAllTeachersController() {
    void AllTeachersController.init().catch((error) => {
        console.error('[AllTeachersController] Initialization failed:', error);
        AllTeachersController.showToast(error.message || 'Teachers page failed to initialize', 'error');
    });
}

if (window.__APP_BOOTED__) {
    initializeAllTeachersController();
} else {
    window.addEventListener('kingsway:ready', initializeAllTeachersController, { once: true });
}
