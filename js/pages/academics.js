/**
 * Academic Management Controller
 * Handles Classes, Streams, and Academic configuration
 * Uses window.API.academic methods from api.js
 */

const academicsController = {
    initialized: false,
    initializationPromise: null,
    eventsBound: false,
    classLoadPromise: null,
    classDataLoadPromise: null,
    teachersLoadPromise: null,

    state: {
        // Academic year being viewed (AcademicYearFilter); null until it loads.
        yearId: null,
        yearInfo: null,
        classes: [],
        allClasses: [],
        streams: [],
        teachers: [],
        currentPage: 1,
        pageSize: 10,
        searchTerm: '',
        filters: {
            gradeLevel: '',
            status: ''
        }
    },

    // ==================== INITIALIZATION ====================
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


        try {
            if (window.AuthContext?.ready) {
                await window.AuthContext.ready();
            }

            if (!window.AuthContext?.isAuthenticated?.()) {
                this.showToast(
                    'Please log in to access this page',
                    'error',
                    'Authentication Required'
                );

                window.setTimeout(() => {
                    window.location.replace(
                        `${window.APP_BASE || ''}/index.php`
                    );
                }, 800);

                return this;
            }

            if (!window.API?.academic) {
                throw new Error('Academic API is unavailable.');
            }

            this.setupEventListeners();

            // Teachers are shared by class/stream/assignment dropdowns.
            await this.loadTeachers();

            await this.mountYearFilter();

            if (document.getElementById('classesTableBody')) {
                // Exactly one paginated table request.
                await this.loadClasses(1);

                // Supporting metadata is loaded separately and once.
                await this.loadClassData();
            }

            if (document.getElementById('subjectsTableBody')) {
                await this._loadSubjectsPage();
            }

            this.initialized = true;

            return this;
        } catch (error) {
            console.error(
                '[AcademicsController] Initialization failed:',
                error
            );
            throw error;
        }
    },

    /** Mount the shared academic-year selector; reload the page data on change. */
    async mountYearFilter() {
        const select = document.getElementById('academicYearFilter');
        if (!select || !window.AcademicYearFilter) return;
        await window.AcademicYearFilter.mount(select, {
            onChange: (yearId, year) => {
                const changed = this.state.yearId !== null && this.state.yearId !== yearId;
                this.state.yearId = yearId;
                this.state.yearInfo = year;
                if (changed) void this.reloadForYear();
            },
        });
    },

    /** Query parameters that scope every list call to the selected year. */
    yearParams() {
        return this.state.yearId ? { academic_year_id: this.state.yearId } : {};
    },

    yearLabel() {
        return this.state.yearInfo?.year_name || this.state.yearInfo?.year_code || '';
    },

    async reloadForYear() {
        await this.loadClasses(1, { force: true });
        await this.loadClassData({ force: true });
        const activeTab = document.querySelector('#classesTabs .nav-link.active')?.getAttribute('data-bs-target');
        if (activeTab === '#streams') await this.loadStreams();
        else if (activeTab === '#class-teachers') await this.loadClassTeachers();
    },

    setupEventListeners() {
        if (this.eventsBound) {
            return;
        }

        this.eventsBound = true;

        document
            .querySelectorAll(
                '#classesTabs button[data-bs-toggle="tab"]'
            )
            .forEach((tab) => {
                tab.addEventListener('shown.bs.tab', (event) => {
                    const target =
                        event.target.getAttribute('data-bs-target');

                    if (target === '#streams') {
                        void this.loadStreams();
                    } else if (target === '#class-teachers') {
                        void this.loadClassTeachers();
                    } else if (target === '#all-classes') {
                        void this.loadClasses(
                            this.state.currentPage || 1
                        );
                    }
                });
            });

        const assignClassSelect =
            document.getElementById('assignClass');

        if (
            assignClassSelect &&
            assignClassSelect.dataset.listenerBound !== 'true'
        ) {
            assignClassSelect.dataset.listenerBound = 'true';

            assignClassSelect.addEventListener('change', () => {
                void this.populateAssignStreams(
                    assignClassSelect.value
                );
            });
        }
    },

    // ==================== TOAST NOTIFICATIONS ====================
    showToast(message, type = 'info', title = 'Notification') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type === 'success' ? 'success' : type === 'error' ? 'danger' : type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            <strong>${this._escH(title)}</strong> ${this._escH(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.insertBefore(alertDiv, document.body.firstChild);
        setTimeout(() => alertDiv.remove(), 4000);
    },

    // ==================== CLASSES MANAGEMENT ====================
    async loadClasses(page = 1, options = {}) {
        const force = options.force === true;

        if (this.classLoadPromise && !force) {
            return this.classLoadPromise;
        }

        const request = this._loadClasses(page);
        this.classLoadPromise = request;

        try {
            return await request;
        } finally {
            if (this.classLoadPromise === request) {
                this.classLoadPromise = null;
            }
        }
    },

    async _loadClasses(page = 1) {
        this.state.currentPage = page;

        const params = {
            limit: 500,
            search: this.state.searchTerm,
            ...this.state.filters,
            ...this.yearParams()
        };

        try {
            const response =
                await window.API.academic.listClasses(params);

            const data = Array.isArray(response)
                ? response
                : Array.isArray(response?.data)
                    ? response.data
                    : [];

            this.state.classes = data;
            this.renderClassesTable();
            this.updateClassStatistics();

            return data;
        } catch (error) {
            if (
                error?.name === 'AbortError' ||
                error?.cancelled === true
            ) {
                return [];
            }

            console.error(
                '[AcademicsController] Failed to load classes:',
                error
            );

            this.showToast(
                error?.message || 'Failed to load classes',
                'error',
                'Error'
            );

            return [];
        }
    },

    async loadClassData(options = {}) {
        const force = options.force === true;

        if (this.classDataLoadPromise && !force) {
            return this.classDataLoadPromise;
        }

        const request = this._loadClassData();
        this.classDataLoadPromise = request;

        try {
            return await request;
        } finally {
            if (this.classDataLoadPromise === request) {
                this.classDataLoadPromise = null;
            }
        }
    },

    async _loadClassData() {
        try {
            // Load static data for dropdowns and full summary-card totals.
            const [classesRes, levelsRes, streamsRes] = await Promise.all([
                window.API.academic.listClasses({ limit: 500, ...this.yearParams() }),
                window.API.academic.listLevels().catch(() => []),
                window.API.academic.listStreams(this.yearParams()).catch(() => [])
            ]);
            const classes = Array.isArray(classesRes) ? classesRes : classesRes?.data || [];
            const levels = Array.isArray(levelsRes) ? levelsRes : levelsRes?.data || [];
            const streams = Array.isArray(streamsRes) ? streamsRes : streamsRes?.data || [];

            this.state.allClasses = classes;
            this.state.streams = streams;

            const classLevelSelect = document.getElementById('classGradeLevel');
            if (classLevelSelect && levels.length > 0) {
                classLevelSelect.innerHTML = '<option value="">Select Grade Level</option>' +
                    levels.map(level => `<option value="${level.id}">${kwEscapeHtml(level.name)}</option>`).join('');
            }
            
            // Populate class teacher dropdown in modals
            const classTeacherSelect = document.getElementById('classTeacher');
            if (classTeacherSelect && this.state.teachers.length > 0) {
                classTeacherSelect.innerHTML = '<option value="">Select Class Teacher</option>' +
                    this.state.teachers.map(t => 
                        `<option value="${t.id}">${t.full_name || `${t.first_name || ''} ${t.last_name || ''}`.trim()}</option>`
                    ).join('');
            }

            const streamTeacherSelect = document.getElementById('streamTeacher');
            const assignTeacherSelect = document.getElementById('assignTeacher');
            const teacherOptions = this.state.teachers.map(t =>
                `<option value="${t.id}">${t.full_name || `${t.first_name || ''} ${t.last_name || ''}`.trim()}</option>`
            ).join('');
            if (streamTeacherSelect) {
                streamTeacherSelect.innerHTML = '<option value="">Select Teacher</option>' + teacherOptions;
            }
            if (assignTeacherSelect) {
                assignTeacherSelect.innerHTML = '<option value="">Select Teacher</option>' + teacherOptions;
            }

            // Populate stream class dropdown
            const streamClassSelect = document.getElementById('streamClass');
            const assignClassSelect = document.getElementById('assignClass');
            const classOptions = classes.map(c => `<option value="${c.id}">${kwEscapeHtml(c.name)}</option>`).join('');
            if (streamClassSelect && classes.length > 0) {
                streamClassSelect.innerHTML = '<option value="">Select Class</option>' + classOptions;
            }
            if (assignClassSelect && classes.length > 0) {
                assignClassSelect.innerHTML =
                    '<option value="">Select Class</option>' +
                    classOptions;
            }
            this.updateClassStatistics();
        } catch (error) {
            console.error('Error loading class data:', error);
        }
    },

    async loadTeachers(options = {}) {
        const force = options.force === true;

        if (this.teachersLoadPromise && !force) {
            return this.teachersLoadPromise;
        }

        const request = this._loadTeachers();
        this.teachersLoadPromise = request;

        try {
            return await request;
        } finally {
            if (this.teachersLoadPromise === request) {
                this.teachersLoadPromise = null;
            }
        }
    },

    async _loadTeachers() {
        try {
            
            // Get teaching staff from the academic API. /users/index is only the
            // Users API health endpoint in this app and does not return user rows.
            const response = await window.API.academic.listTeachers({ limit: 200 });
            
            const data = Array.isArray(response) ? response : (Array.isArray(response?.data) ? response.data : []);
            
            this.state.teachers = data;
            
        } catch (error) {
            console.error('Error loading teachers:', error);
            console.error('Error message:', error.message);
            console.error('Full error:', error);
            
            // Check if it's an auth issue
            if (error.message && error.message.includes('JSON')) {
                console.error('⚠️ Non-JSON response from users endpoint - likely authentication issue');
                if (!AuthContext.isAuthenticated()) {
                    console.warn('User is not authenticated - redirecting to login');
                    setTimeout(() => {
                        window.location.href = (window.APP_BASE || '') + '/index.php';
                    }, 1000);
                }
            }
            
            this.state.teachers = [];
        }
    },

    renderClassesTable() {
        const tbody = document.getElementById('classesTableBody');
        if (!tbody) return;

        if (this.state.classes.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-4">
                        <p class="text-muted">No classes found</p>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = this.state.classes.map((cls, index) => `
            <tr>
                <td>${(this.state.currentPage - 1) * this.state.pageSize + index + 1}</td>
                <td><strong>${this._escH(cls.name || cls.class_name || '-')}</strong></td>
                <td>${this._escH(cls.level_name || cls.grade_level || '-')}</td>
                <td><span class="badge bg-info">${cls.stream_count || 0}</span></td>
                <td><span class="badge bg-primary">${cls.student_count || cls.students_count || 0}</span></td>
                <td>${this._escH(cls.class_teacher_name || cls.teacher_name || 'Not assigned')}</td>
                <td>${this._escH(cls.capacity || '-')}</td>
                <td>
                    <span class="badge ${cls.status === 'active' ? 'bg-success' : 'bg-secondary'}">
                        ${this._escH(cls.status || 'active')}
                    </span>
                </td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-info" 
                                data-kw-onclick="academicsController.editClass(${cls.id})" 
                                title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger" 
                                data-kw-onclick="academicsController.deleteClass(${cls.id})" 
                                title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    },

    updateClassStatistics() {
        const totalCount = document.getElementById('totalClassesCount');
        const activeCount = document.getElementById('activeStreamsCount');
        const studentsCount = document.getElementById('studentsEnrolledCount');
        const teachersCount = document.getElementById('teachersAssignedCount');

        const classes = this.state.allClasses.length ? this.state.allClasses : this.state.classes;
        const streams = this.state.streams || [];
        const activeStreams = streams.filter(stream => (stream.status || 'active') === 'active');
        const teacherIds = new Set();

        streams.forEach((stream) => {
            if ((stream.status || 'active') !== 'active') return;
            if (stream.teacher_id) {
                teacherIds.add(`id:${stream.teacher_id}`);
            } else if (stream.teacher_name) {
                teacherIds.add(`name:${stream.teacher_name}`);
            }
        });

        classes.forEach((cls) => {
            if (cls.teacher_id) {
                teacherIds.add(`class:${cls.teacher_id}`);
            } else if (cls.class_teacher_name) {
                teacherIds.add(`class-name:${cls.class_teacher_name}`);
            }
        });

        const streamStudentTotal = activeStreams.reduce((sum, stream) => {
            return sum + (Number(stream.student_count ?? stream.current_students ?? 0) || 0);
        }, 0);
        const classStudentTotal = classes.reduce((sum, cls) => {
            return sum + (Number(cls.student_count ?? cls.students_count ?? 0) || 0);
        }, 0);
        const studentTotal = streamStudentTotal || classStudentTotal;

        if (totalCount) totalCount.textContent = this.formatNumber(classes.length);
        if (activeCount) {
            const streamCount = activeStreams.length || classes.reduce((sum, cls) => sum + (Number(cls.stream_count) || 0), 0);
            activeCount.textContent = this.formatNumber(streamCount);
        }
        if (studentsCount) {
            studentsCount.textContent = this.formatNumber(studentTotal);
        }
        if (teachersCount) {
            teachersCount.textContent = this.formatNumber(teacherIds.size);
        }
    },

    formatNumber(value) {
        return new Intl.NumberFormat().format(Number(value) || 0);
    },

    showClassModal(classId = null) {
        const modal = document.getElementById('classModal');
        const form = document.getElementById('classForm');
        const action = document.getElementById('classModalAction');
        const classIdInput = document.getElementById('classId');

        if (!modal) return;

        if (classId) {
            action.textContent = 'Edit';
            classIdInput.value = classId;
            // Load class data and populate form
            const classData = this.state.classes.find(c => c.id === classId);
            if (classData) {
                document.getElementById('className').value = classData.name || '';
                document.getElementById('classGradeLevel').value = classData.level_id || '';
                document.getElementById('classCapacity').value = classData.capacity || '';
                document.getElementById('classRoom').value = classData.room_number || '';
                document.getElementById('classTeacher').value = classData.teacher_id || '';
                document.getElementById('classAcademicYear').value = classData.academic_year || this.yearLabel();
                document.getElementById('classStatus').value = classData.status || 'active';
            }
        } else {
            action.textContent = 'Add';
            classIdInput.value = '';
            form.reset();
            document.getElementById('classAcademicYear').value = this.yearLabel();
        }

        const bsModal = bootstrap.Modal.getOrCreateInstance(modal);
        bsModal.show();
    },

    async saveClass(event) {
        event.preventDefault();

        const classId = document.getElementById('classId').value;
        const data = {
            name: document.getElementById('className').value.trim(),
            level_id: document.getElementById('classGradeLevel').value,
            capacity: parseInt(document.getElementById('classCapacity').value) || 0,
            room_number: document.getElementById('classRoom').value.trim(),
            teacher_id: document.getElementById('classTeacher').value || null,
            // New classes go into the year being viewed. (The old free-text
            // "2024" never matched a year code such as 2026/2027, so every
            // class silently landed in the current year.)
            ...(classId ? {} : this.yearParams()),
            status: document.getElementById('classStatus').value
        };

        // Validation
        if (!data.name || !data.level_id) {
            this.showToast('Please fill in all required fields', 'warning', 'Validation');
            return;
        }

        try {
            if (classId) {
                await window.API.academic.updateClass(classId, data);
                this.showToast('Class updated successfully', 'success', 'Success');
            } else {
                await window.API.academic.createClass(data);
                this.showToast('Class created successfully', 'success', 'Success');
            }

            const modal = bootstrap.Modal.getInstance(document.getElementById('classModal'));
            modal.hide();
            await this.loadClasses();
        } catch (error) {
            console.error('Error saving class:', error);
            this.showToast(error.message || 'Failed to save class', 'error', 'Error');
        }
    },

    editClass(classId) {
        this.showClassModal(classId);
    },

    async deleteClass(classId) {
        if (!(await window.confirmAction('Confirm Deletion', 'Are you sure you want to delete this class?', { confirmText: 'Delete', danger: true }))) return;

        try {
            await window.API.academic.deleteClass(classId);
            this.showToast('Class deleted successfully', 'success', 'Success');
            await this.loadClasses();
        } catch (error) {
            console.error('Error deleting class:', error);
            this.showToast(error.message || 'Failed to delete class', 'error', 'Error');
        }
    },

    searchClasses(term) {
        this.state.searchTerm = term;
        this.loadClasses(1);
    },

    filterByGradeLevel(level) {
        this.state.filters.gradeLevel = level;
        this.loadClasses(1);
    },

    filterByClassStatus(status) {
        this.state.filters.status = status;
        this.loadClasses(1);
    },

    exportClasses() {
        if (!window.PrintManager) {
            this.showToast('PrintManager not available', 'error', 'Error');
            return;
        }

        const columns = [
            { key: 'index', label: '#' },
            { key: 'name', label: 'Name' },
            { key: 'grade_level', label: 'Grade Level' },
            { key: 'capacity', label: 'Capacity' },
            { key: 'student_count', label: 'Students' },
            { key: 'status', label: 'Status' }
        ];

        const rows = this.state.classes.map((cls, idx) => ({
            index: idx + 1,
            name: cls.name,
            grade_level: cls.level_name || cls.grade_level,
            capacity: cls.capacity,
            student_count: cls.student_count || 0,
            status: cls.status
        }));

        window.PrintManager.exportToCSV({
            filename: `classes_${new Date().toISOString().slice(0,10)}.csv`,
            columns: columns,
            rows: rows
        });

        this.showToast('Classes exported successfully', 'success', 'Success');
    },

    // ==================== STREAMS MANAGEMENT ====================
    async loadStreams() {
        try {
            const response = await window.API.academic.listStreams(this.yearParams());
            const data = Array.isArray(response) ? response : (Array.isArray(response?.data) ? response.data : []);
            
            this.state.streams = data;
            this.renderStreamsTable();
            this.updateClassStatistics();
        } catch (error) {
            console.error('Error loading streams:', error);
            const tbody = document.getElementById('streamsTableBody');
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center text-danger py-4">
                            Failed to load streams: ${this._escH(error.message || 'Request failed')}
                        </td>
                    </tr>
                `;
            }
            this.showToast('Failed to load streams', 'error', 'Error');
        }
    },

    renderStreamsTable() {
        const tbody = document.getElementById('streamsTableBody');
        if (!tbody) return;

        if (this.state.streams.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4">
                        <p class="text-muted">No streams found</p>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = this.state.streams.map((stream, index) => `
            <tr>
                <td>${index + 1}</td>
                <td><strong>${this._escH(stream.name || stream.stream_name || '-')}</strong></td>
                <td>${this._escH(stream.class_name || '-')}</td>
                <td><span class="badge bg-primary">${stream.student_count || stream.current_students || 0}</span></td>
                <td>${this._escH(stream.teacher_name || 'Not assigned')}</td>
                <td>${this._escH(stream.capacity || '-')}</td>
                <td>
                    <span class="badge ${stream.status === 'active' ? 'bg-success' : 'bg-secondary'}">
                        ${this._escH(stream.status || 'active')}
                    </span>
                </td>
            </tr>
        `).join('');
    },

    showStreamModal(streamId = null) {
        const modal = document.getElementById('streamModal');
        const form = document.getElementById('streamForm');
        const action = document.getElementById('streamModalAction');

        if (!modal) return;

        if (streamId) {
            action.textContent = 'Edit';
            const streamData = this.state.streams.find(s => s.id === streamId);
            if (streamData) {
                document.getElementById('streamId').value = streamId;
                document.getElementById('streamClass').value = streamData.class_id || '';
                document.getElementById('streamName').value = streamData.stream_name || streamData.name || '';
                document.getElementById('streamTeacher').value = streamData.teacher_id || '';
                document.getElementById('streamCapacity').value = streamData.capacity || '';
                document.getElementById('streamStatus').value = streamData.status || 'active';
            }
        } else {
            action.textContent = 'Add';
            document.getElementById('streamId').value = '';
            form.reset();
            document.getElementById('streamStatus').value = 'active';
        }

        const bsModal = bootstrap.Modal.getOrCreateInstance(modal);
        bsModal.show();
    },

    async saveStream(event) {
        event.preventDefault();

        const streamId = document.getElementById('streamId').value;
        const data = {
            class_id: document.getElementById('streamClass').value,
            name: document.getElementById('streamName').value.trim(),
            teacher_id: document.getElementById('streamTeacher').value || null,
            capacity: parseInt(document.getElementById('streamCapacity').value) || 0,
            status: document.getElementById('streamStatus').value
        };

        if (!data.class_id || !data.name) {
            this.showToast('Please fill in all required fields', 'warning', 'Validation');
            return;
        }

        try {
            if (streamId) {
                await window.API.academic.updateStream(streamId, data);
                this.showToast('Stream updated successfully', 'success', 'Success');
            } else {
                // Create the stream in the year being viewed. Without a year
                // the server picks the newest year, not the one on screen.
                await window.API.academic.createStream({ ...data, ...this.yearParams() });
                this.showToast('Stream created successfully', 'success', 'Success');
            }

            const modal = bootstrap.Modal.getInstance(document.getElementById('streamModal'));
            modal.hide();
            await this.loadStreams();
        } catch (error) {
            console.error('Error saving stream:', error);
            this.showToast(error.message || 'Failed to save stream', 'error', 'Error');
        }
    },

    editStream(streamId) {
        this.showStreamModal(streamId);
    },

    async deleteStream(streamId) {
        if (!(await window.confirmAction('Confirm Deletion', 'Are you sure you want to delete this stream?', { confirmText: 'Delete', danger: true }))) return;

        try {
            await window.API.academic.deleteStream(streamId);
            this.showToast('Stream deleted successfully', 'success', 'Success');
            await this.loadStreams();
        } catch (error) {
            console.error('Error deleting stream:', error);
            this.showToast(error.message || 'Failed to delete stream', 'error', 'Error');
        }
    },

    // ==================== CLASS TEACHERS MANAGEMENT ====================
    async loadClassTeachers() {
        try {
            const response = await window.API.academic.listStreams(this.yearParams());
            const data = Array.isArray(response) ? response : (Array.isArray(response?.data) ? response.data : []);
            
            const streamsWithTeachers = data.filter(stream => stream.teacher_id || stream.teacher_name);
            this.renderClassTeachersTable(streamsWithTeachers);
        } catch (error) {
            console.error('Error loading class teachers:', error);
            const tbody = document.getElementById('classTeachersTableBody');
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center text-danger py-4">
                            Failed to load class teachers: ${this._escH(error.message || 'Request failed')}
                        </td>
                    </tr>
                `;
            }
            this.showToast('Failed to load class teachers', 'error', 'Error');
        }
    },

    renderClassTeachersTable(teachers) {
        const tbody = document.getElementById('classTeachersTableBody');
        if (!tbody) return;

        if (teachers.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4">
                        <p class="text-muted">No class teachers assigned</p>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = teachers.map((item, index) => `
            <tr>
                <td>${index + 1}</td>
                <td><strong>${this._escH(item.teacher_name || item.class_teacher_name || '-')}</strong></td>
                <td>${this._escH(item.class_name || item.name || '-')}</td>
                <td>${this._escH(item.stream_name || '-')}</td>
                <td><span class="badge bg-primary">${item.student_count || item.current_students || 0}</span></td>
                <td>${this._escH(item.subject_name || '-')}</td>
                <td>${this._escH(item.teacher_contact || '-')}</td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <a class="btn btn-outline-warning"
                           href="${window.APP_BASE || ''}/home.php?route=assign_class_teachers${this.state.yearId ? `&academic_year_id=${this.state.yearId}` : ''}"
                           title="Change class teacher (opens Class Teachers)">
                            <i class="bi bi-person-check"></i>
                        </a>
                    </div>
                </td>
            </tr>
        `).join('');
    },

    async populateAssignStreams(classId, selectedStreamId = null) {
        const select = document.getElementById('assignStream');
        if (!select) return;

        if (!classId) {
            select.innerHTML = '<option value="">No specific stream</option>';
            return;
        }

        try {
            if (!this.state.streams.length) {
                const response = await window.API.academic.listStreams();
                this.state.streams = Array.isArray(response) ? response : (Array.isArray(response?.data) ? response.data : []);
            }

            const streams = this.state.streams.filter((stream) => String(stream.class_id) === String(classId));
            select.innerHTML = '<option value="">Assign to class record</option>' +
                streams.map((stream) => `<option value="${stream.id}">${stream.stream_name || stream.name}</option>`).join('');
            if (selectedStreamId) {
                select.value = String(selectedStreamId);
            }
        } catch (error) {
            console.error('Error loading assign streams:', error);
            select.innerHTML = '<option value="">Assign to class record</option>';
        }
    },

    // ==================== SUBJECT / LEARNING AREA MANAGEMENT ====================
    // Used by manage_subjects.php (learning_areas = subjects in CBC terminology)

    _subjects:    [],
    _subjFiltered: [],
    _subjPage:    1,
    _subjPerPage: 15,

    async _loadSubjectsPage() {
        const tbody = document.getElementById('subjectsTableBody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4"><div class="spinner-border text-primary"></div></td></tr>';
        try {
            const r = await callAPI('/academic/learning-areas/list', 'GET');
            this._subjects = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
            this._subjFiltered = [...this._subjects];
            this._renderSubjectsTable();
            this._updateSubjectStats();
            this._loadSubjectTeachersDropdown();
            this._loadSubjectClassesCheckboxes();
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-danger text-center py-4">Failed to load subjects. ' + (e.message || '') + '</td></tr>';
        }
    },

    _updateSubjectStats() {
        const core     = this._subjects.filter(s => s.category === 'core').length;
        const optional = this._subjects.filter(s => s.category === 'optional').length;
        const withTeach = this._subjects.filter(s => (s.teacher_count ?? 0) > 0).length;
        this._setEl('totalSubjectsCount',  this._subjects.length);
        this._setEl('coreSubjectsCount',   core);
        this._setEl('optionalSubjectsCount', optional);
        this._setEl('teachersAssignedCount', withTeach);
    },

    _renderSubjectsTable() {
        const tbody = document.getElementById('subjectsTableBody');
        if (!tbody) return;
        const start = (this._subjPage - 1) * this._subjPerPage;
        const page  = this._subjFiltered.slice(start, start + this._subjPerPage);

        this._setEl('subjShowingFrom',  this._subjFiltered.length ? start + 1 : 0);
        this._setEl('subjShowingTo',    Math.min(start + this._subjPerPage, this._subjFiltered.length));
        this._setEl('subjTotalRecords', this._subjFiltered.length);

        if (!page.length) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No subjects match the current filters.</td></tr>';
            this._renderSubjectPagination();
            return;
        }
        tbody.innerHTML = page.map((s, i) => `
            <tr>
                <td>${start + i + 1}</td>
                <td><code>${this._escH(s.code || s.subject_code || '—')}</code></td>
                <td><strong>${this._escH(s.name || s.subject_name)}</strong>${s.description ? '<br><small class="text-muted">' + this._escH(s.description.substring(0, 60)) + (s.description.length > 60 ? '…' : '') + '</small>' : ''}</td>
                <td><span class="badge bg-${s.category === 'core' ? 'primary' : s.category === 'optional' ? 'info' : 'secondary'}">${this._escH(s.category || '—')}</span></td>
                <td>${this._escH(s.grade_level || s.level || '—')}</td>
                <td>${s.teacher_count ?? 0}</td>
                <td>${s.class_count ?? 0}</td>
                <td><span class="badge bg-${(s.status || 'active') === 'active' ? 'success' : 'secondary'}">${s.status || 'active'}</span></td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-1" data-kw-onclick="academicsController.showSubjectModal(${s.id})" title="Edit"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-danger" data-kw-onclick="academicsController._deleteSubject(${s.id},'${this._escH(s.name || s.subject_name).replace(/'/g,"\\'")}')"><i class="bi bi-trash"></i></button>
                </td>
            </tr>`).join('');
        this._renderSubjectPagination();
    },

    _renderSubjectPagination() {
        const el = document.getElementById('subjectsPagination');
        if (!el) return;
        const pages = Math.ceil(this._subjFiltered.length / this._subjPerPage);
        if (pages <= 1) { el.innerHTML = ''; return; }
        el.innerHTML = Array.from({ length: pages }, (_, i) => `
            <li class="page-item ${i + 1 === this._subjPage ? 'active' : ''}">
                <button class="page-link" data-kw-onclick="academicsController._goSubjectPage(${i + 1})">${i + 1}</button>
            </li>`).join('');
    },

    _goSubjectPage(page) { this._subjPage = page; this._renderSubjectsTable(); },

    searchSubjects(q) {
        q = (q || '').toLowerCase();
        this._subjFiltered = q
            ? this._subjects.filter(s => (s.name || s.subject_name || '').toLowerCase().includes(q) || (s.code || s.subject_code || '').toLowerCase().includes(q))
            : [...this._subjects];
        this._subjPage = 1;
        this._renderSubjectsTable();
    },

    filterByCategory(val) {
        this._subjFiltered = val ? this._subjects.filter(s => s.category === val) : [...this._subjects];
        this._subjPage = 1;
        this._renderSubjectsTable();
    },

    filterByLevel(val) {
        this._subjFiltered = val ? this._subjects.filter(s => (s.grade_level || s.level || '') === val) : [...this._subjects];
        this._subjPage = 1;
        this._renderSubjectsTable();
    },

    filterByStatus(val) {
        this._subjFiltered = val ? this._subjects.filter(s => (s.status || 'active') === val) : [...this._subjects];
        this._subjPage = 1;
        this._renderSubjectsTable();
    },

    showSubjectModal(id) {
        const form = document.getElementById('subjectForm');
        if (!form) return;
        form.reset();
        document.getElementById('subjectId').value = '';
        document.getElementById('subjectModalAction').textContent = 'Add';

        if (id) {
            const s = this._subjects.find(x => x.id == id);
            if (!s) return;
            document.getElementById('subjectId').value        = s.id;
            document.getElementById('subjectCode').value      = s.code || s.subject_code || '';
            document.getElementById('subjectName').value      = s.name || s.subject_name || '';
            document.getElementById('subjectCategory').value  = s.category || '';
            document.getElementById('subjectGradeLevel').value = s.grade_level || s.level || '';
            document.getElementById('subjectDepartment').value = s.department || '';
            document.getElementById('subjectStatus').value    = s.status || 'active';
            document.getElementById('subjectDescription').value = s.description || '';
            document.getElementById('subjectModalAction').textContent = 'Edit';
        }
        bootstrap.Modal.getOrCreateInstance(document.getElementById('subjectModal')).show();
    },

    async saveSubject(e) {
        e.preventDefault();
        const id = document.getElementById('subjectId').value;
        const payload = {
            code:        document.getElementById('subjectCode').value.trim(),
            name:        document.getElementById('subjectName').value.trim(),
            category:    document.getElementById('subjectCategory').value,
            grade_level: document.getElementById('subjectGradeLevel').value,
            department:  document.getElementById('subjectDepartment').value,
            status:      document.getElementById('subjectStatus').value,
            description: document.getElementById('subjectDescription').value.trim(),
        };
        try {
            if (id) {
                await callAPI('/academic/learning-areas/update/' + id, 'PUT', payload);
                this.showToast('Subject updated', 'success', 'Saved');
            } else {
                await callAPI('/academic/learning-areas/create', 'POST', payload);
                this.showToast('Subject added', 'success', 'Saved');
            }
            bootstrap.Modal.getInstance(document.getElementById('subjectModal'))?.hide();
            await this._loadSubjectsPage();
        } catch (err) {
            this.showToast('Failed to save: ' + (err.message || err), 'error', 'Error');
        }
    },

    async _deleteSubject(id, name) {
        if (!(await window.confirmAction('Confirm Deletion', 'Delete subject "' + name + '"? This cannot be undone.', { confirmText: 'Delete', danger: true }))) return;
        try {
            await callAPI('/academic/learning-areas/delete/' + id, 'DELETE');
            this.showToast('Subject deleted', 'success', 'Deleted');
            await this._loadSubjectsPage();
        } catch (err) {
            this.showToast(err?.message || 'Cannot delete subject', 'error', 'Error');
        }
    },

    exportSubjects() {
        if (!window.PrintManager) {
            this.showToast('PrintManager not available', 'error', 'Error');
            return;
        }

        const columns = [
            { key: 'code', label: 'Code' },
            { key: 'name', label: 'Name' },
            { key: 'category', label: 'Category' },
            { key: 'grade_level', label: 'Level' },
            { key: 'department', label: 'Department' },
            { key: 'status', label: 'Status' }
        ];

        const rows = this._subjects.map(s => ({
            code: s.code || s.subject_code || '',
            name: s.name || s.subject_name || '',
            category: s.category || '',
            grade_level: s.grade_level || '',
            department: s.department || '',
            status: s.status || 'active'
        }));

        window.PrintManager.exportToCSV({
            filename: `subjects_${new Date().toISOString().slice(0,10)}.csv`,
            columns: columns,
            rows: rows
        });
    },

    // ── Curriculum Unit Modal ───────────────────────────────────────
    async showCurriculumUnitModal(id) {
        const form = document.getElementById('curriculumUnitForm');
        if (!form) return;
        form.reset();
        document.getElementById('unitId').value = '';
        document.getElementById('unitModalAction').textContent = 'Add';

        // Populate subjects dropdown
        const sel = document.getElementById('unitSubject');
        if (sel) {
            sel.innerHTML = '<option value="">Select Subject</option>' +
                this._subjects.map(s => `<option value="${s.id}">${this._escH(s.name || s.subject_name)}</option>`).join('');
        }
        if (id) {
            document.getElementById('unitModalAction').textContent = 'Edit';
            try {
                const r = await callAPI(`/academic/curriculum-units/${id}`, 'GET');
                const d = r?.data || r;
                if (d && d.id) {
                    document.getElementById('unitId').value = d.id;
                    if (document.getElementById('unitName')) document.getElementById('unitName').value = d.name || '';
                    if (document.getElementById('unitCode')) document.getElementById('unitCode').value = d.code || '';
                    if (document.getElementById('unitSequence')) document.getElementById('unitSequence').value = d.sort_order || d.order_sequence || '';
                    if (document.getElementById('unitSubject')) document.getElementById('unitSubject').value = d.learning_area_id || '';
                    if (document.getElementById('unitTerm')) document.getElementById('unitTerm').value = d.term_number || '';
                    if (document.getElementById('unitDuration')) document.getElementById('unitDuration').value = d.duration || '';
                    if (document.getElementById('unitObjectives')) document.getElementById('unitObjectives').value = d.description || d.objectives || '';
                    if (document.getElementById('unitTopics')) document.getElementById('unitTopics').value = (d.topics || []).map(t => t.name || t.topic_name).filter(Boolean).join(', ');
                    if (document.getElementById('unitResources')) document.getElementById('unitResources').value = d.suggested_resources || d.resources_needed || '';
                    if (document.getElementById('unitStatus')) document.getElementById('unitStatus').value = d.status || 'active';
                }
            } catch (e) {
                console.warn('Failed to load curriculum unit:', e);
            }
        }
        bootstrap.Modal.getOrCreateInstance(document.getElementById('curriculumUnitModal')).show();
    },

    async saveCurriculumUnit(e) {
        e.preventDefault();
        const id = document.getElementById('unitId').value;
        const payload = {
            name:              document.getElementById('unitName').value.trim(),
            code:              document.getElementById('unitCode').value.trim(),
            sort_order:        parseInt(document.getElementById('unitSequence').value) || 1,
            learning_area_id:  document.getElementById('unitSubject').value,
            description:       document.getElementById('unitObjectives').value.trim(),
            topics:            document.getElementById('unitTopics').value.split(',').map(s => ({ name: s.trim() })).filter(t => t.name),
            status:            document.getElementById('unitStatus').value,
        };
        try {
            if (id) {
                await callAPI('/academic/curriculum-units/' + id, 'PUT', payload);
                this.showToast('Unit updated', 'success', 'Saved');
            } else {
                await callAPI('/academic/curriculum-units', 'POST', payload);
                this.showToast('Unit added', 'success', 'Saved');
            }
            bootstrap.Modal.getInstance(document.getElementById('curriculumUnitModal'))?.hide();
        } catch (err) {
            this.showToast('Failed to save: ' + (err.message || err), 'error', 'Error');
        }
    },

    async _loadSubjectTeachersDropdown() {
        const sel = document.getElementById('subjectTeachers');
        if (!sel || this.state.teachers.length) return;
        try {
            const r = await callAPI('/staff?type=teaching&status=active&limit=200', 'GET');
            const teachers = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
            sel.innerHTML = teachers.map(t => `<option value="${t.id}">${this._escH(t.first_name + ' ' + t.last_name)}</option>`).join('');
        } catch (e) { /* optional */ }
    },

    async _loadSubjectClassesCheckboxes() {
        const container = document.getElementById('subjectClassesCheckboxes');
        if (!container) return;
        try {
            const r = await window.API.academic.listClasses();
            const classes = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
            container.innerHTML = classes.length
                ? classes.map(c => `
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="class_ids[]" value="${c.id}" id="sc_${c.id}">
                        <label class="form-check-label" for="sc_${c.id}">${this._escH(c.name)}</label>
                    </div>`).join('')
                : '<span class="text-muted small">No classes found.</span>';
        } catch (e) { container.innerHTML = '<span class="text-muted small">Could not load classes.</span>'; }
    },

    _setEl(id, val) { const el = document.getElementById(id); if (el) el.textContent = val; },
    _escH(str) { const d = document.createElement('div'); d.textContent = String(str ?? ''); return d.innerHTML; },
};

window.academicsController = academicsController;

function initializeAcademicsController() {
    void academicsController.init().catch((error) => {
        console.error(
            '[AcademicsController] Page initialization failed:',
            error
        );
    });
}

if (window.__APP_BOOTED__) {
    initializeAcademicsController();
} else {
    window.addEventListener(
        'kingsway:ready',
        initializeAcademicsController,
        { once: true }
    );
}
