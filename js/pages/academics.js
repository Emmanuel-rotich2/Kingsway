/**
 * Academic Management Controller
 * Handles Classes, Streams, and Academic configuration
 * Uses window.API.academic methods from api.js
 */

const academicsController = {
    state: {
        classes: [],
        streams: [],
        teachers: [],
        currentPage: 1,
        pageSize: 10,
        searchTerm: '',
        filters: {
            gradeLevel: '',
            section: '',
            status: ''
        }
    },

    // ==================== INITIALIZATION ====================
    async init() {
        console.log('Initializing Academics Controller...');
        console.log('Checking prerequisites...');
        console.log('- AuthContext available:', typeof AuthContext !== 'undefined' ? '✓ Yes' : '✗ No');
        console.log('- window.API available:', typeof window.API !== 'undefined' ? '✓ Yes' : '✗ No');
        console.log('- Token in localStorage:', localStorage.getItem('token') ? '✓ Yes' : '✗ No');
        console.log('- User authenticated:', AuthContext ? AuthContext.isAuthenticated() : 'N/A');
        
        try {
            // Check if we have authentication
            if (!AuthContext.isAuthenticated()) {
                console.error('❌ User is not authenticated');
                console.log('Redirecting to login page...');
                this.showToast('Please log in to access this page', 'error', 'Authentication Required');
                setTimeout(() => {
                    window.location.href = (window.APP_BASE || '') + '/index.php';
                }, 2000);
                return;
            }
            
            // Load teachers first (needed for dropdowns)
            await this.loadTeachers();
            console.log('Teachers loaded');
            
            // Only load classes if the element exists
            if (document.getElementById('classesTableBody')) {
                await this.loadClasses();
            }

            // Load subjects if on manage_subjects page
            if (document.getElementById('subjectsTableBody')) {
                await this._loadSubjectsPage();
            }

            this.setupEventListeners();
            console.log('Academics Controller initialized successfully');
        } catch (error) {
            console.error('Error initializing Academics Controller:', error);
        }
    },

    setupEventListeners() {
        // Event listeners for modals and controls
        document.addEventListener('click', (e) => {
            if (e.target.closest('[data-permission]')) {
                // Permission checks handled by middleware
            }
        });
    },

    // ==================== TOAST NOTIFICATIONS ====================
    showToast(message, type = 'info', title = 'Notification') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type === 'success' ? 'success' : type === 'error' ? 'danger' : type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            <strong>${title}</strong> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.insertBefore(alertDiv, document.body.firstChild);
        setTimeout(() => alertDiv.remove(), 4000);
    },

    // ==================== CLASSES MANAGEMENT ====================
    async loadClasses(page = 1) {
        try {
            this.state.currentPage = page;
            const params = {
                page,
                limit: this.state.pageSize,
                search: this.state.searchTerm,
                ...this.state.filters
            };

            console.log('Loading classes with params:', params);
            console.log('Current token:', localStorage.getItem('token') ? '✓ Present' : '✗ Missing');
            console.log('Auth user:', AuthContext.getUser());
            console.log('Is authenticated:', AuthContext.isAuthenticated());
            
            // Make the API call
            let response;
            try {
                response = await window.API.academic.listClasses(params);
            } catch (apiError) {
                console.error('API call failed:', apiError);
                console.error('Error message:', apiError.message);
                console.error('Full error:', apiError);
                
                // Check if it's an auth issue
                if (apiError.message && apiError.message.includes('JSON')) {
                    console.error('⚠️ Non-JSON response received - likely authentication issue or server error');
                    console.error('Checking authentication status...');
                    if (!AuthContext.isAuthenticated()) {
                        this.showToast('Please log in to access this page', 'error', 'Authentication Required');
                        setTimeout(() => {
                            window.location.href = (window.APP_BASE || '') + '/index.php';
                        }, 2000);
                    } else {
                        this.showToast(`Server error: ${apiError.message}`, 'error', 'Error');
                    }
                } else {
                    // Show the actual error message from the API
                    this.showToast(`API Error: ${apiError.message}`, 'error', 'Error');
                }
                return;
            }
            
            console.log('Classes API response:', response);
            
            const data = Array.isArray(response) ? response : (Array.isArray(response?.data) ? response.data : []);
            
            console.log('Processed classes data:', data);
            this.state.classes = data;
            this.renderClassesTable();
            this.updateClassStatistics();
        } catch (error) {
            console.error('Error loading classes:', error);
            console.error('Error details:', error.message, error.response);
            this.showToast(`Failed to load classes: ${error.message}`, 'error', 'Error');
        }
    },

    async loadClassData() {
        try {
            // Load static data for dropdowns
            const classesRes = await window.API.academic.listClasses();
            const classes = Array.isArray(classesRes) ? classesRes : classesRes?.data || [];
            
            // Populate class teacher dropdown in modals
            const classTeacherSelect = document.getElementById('classTeacher');
            if (classTeacherSelect && this.state.teachers.length > 0) {
                classTeacherSelect.innerHTML = '<option value="">Select Class Teacher</option>' +
                    this.state.teachers.map(t => 
                        `<option value="${t.id}">${t.first_name} ${t.last_name}</option>`
                    ).join('');
            }

            // Populate stream class dropdown
            const streamClassSelect = document.getElementById('streamClass');
            if (streamClassSelect && classes.length > 0) {
                streamClassSelect.innerHTML = '<option value="">Select Class</option>' +
                    classes.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
            }
        } catch (error) {
            console.error('Error loading class data:', error);
        }
    },

    async loadTeachers() {
        try {
            console.log('Loading teachers...');
            console.log('Current token:', localStorage.getItem('token') ? '✓ Present' : '✗ Missing');
            console.log('Is authenticated:', AuthContext.isAuthenticated());
            
            // Get teachers from users API
            const response = await window.API.users.index();
            console.log('Teachers API response:', response);
            
            const data = Array.isArray(response) ? response : (Array.isArray(response?.data) ? response.data : []);
            
            // Filter for users with teacher role
            this.state.teachers = data.filter(user => 
                user.role === 'teacher' || 
                (Array.isArray(user.roles) && user.roles.some(r => r.name === 'teacher' || r === 'teacher'))
            );
            
            console.log('Processed teachers data:', this.state.teachers);
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
                    <td colspan="10" class="text-center py-4">
                        <p class="text-muted">No classes found</p>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = this.state.classes.map((cls, index) => `
            <tr>
                <td>${(this.state.currentPage - 1) * this.state.pageSize + index + 1}</td>
                <td><strong>${cls.name || cls.class_name || '-'}</strong></td>
                <td>${cls.level_name || cls.grade_level || '-'}</td>
                <td>${cls.section || '-'}</td>
                <td><span class="badge bg-info">${cls.stream_count || 0}</span></td>
                <td><span class="badge bg-primary">${cls.student_count || cls.students_count || 0}</span></td>
                <td>${cls.class_teacher_name || cls.teacher_name || 'Not assigned'}</td>
                <td>${cls.capacity || '-'}</td>
                <td>
                    <span class="badge ${cls.status === 'active' ? 'bg-success' : 'bg-secondary'}">
                        ${cls.status || 'active'}
                    </span>
                </td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-info" 
                                onclick="academicsController.editClass(${cls.id})" 
                                title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger" 
                                onclick="academicsController.deleteClass(${cls.id})" 
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

        if (totalCount) totalCount.textContent = this.state.classes.length;
        if (activeCount) {
            const streamCount = this.state.classes.reduce((sum, cls) => sum + (cls.stream_count || 0), 0);
            activeCount.textContent = streamCount;
        }
        if (studentsCount) {
            const studentTotal = this.state.classes.reduce((sum, cls) => 
                sum + (cls.student_count || cls.students_count || 0), 0);
            studentsCount.textContent = studentTotal;
        }
        if (teachersCount) {
            const assignedTeachers = this.state.classes.filter(cls => cls.class_teacher_name).length;
            teachersCount.textContent = assignedTeachers;
        }
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
                document.getElementById('classGradeLevel').value = classData.grade_level || '';
                document.getElementById('classSection').value = classData.section || 'primary';
                document.getElementById('classCapacity').value = classData.capacity || '';
                document.getElementById('classRoom').value = classData.room_number || '';
                document.getElementById('classTeacher').value = classData.class_teacher_id || '';
                document.getElementById('classAcademicYear').value = classData.academic_year || new Date().getFullYear();
                document.getElementById('classDescription').value = classData.description || '';
                document.getElementById('classStatus').value = classData.status || 'active';
            }
        } else {
            action.textContent = 'Add';
            classIdInput.value = '';
            form.reset();
            document.getElementById('classAcademicYear').value = new Date().getFullYear();
        }

        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    },

    async saveClass(event) {
        event.preventDefault();

        const classId = document.getElementById('classId').value;
        const data = {
            name: document.getElementById('className').value.trim(),
            grade_level: document.getElementById('classGradeLevel').value,
            section: document.getElementById('classSection').value,
            capacity: parseInt(document.getElementById('classCapacity').value) || 0,
            room_number: document.getElementById('classRoom').value.trim(),
            class_teacher_id: document.getElementById('classTeacher').value || null,
            academic_year: document.getElementById('classAcademicYear').value,
            description: document.getElementById('classDescription').value.trim(),
            status: document.getElementById('classStatus').value
        };

        // Validation
        if (!data.name || !data.grade_level) {
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
        if (!confirm('Are you sure you want to delete this class?')) return;

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

    filterBySection(section) {
        this.state.filters.section = section;
        this.loadClasses(1);
    },

    filterByClassStatus(status) {
        this.state.filters.status = status;
        this.loadClasses(1);
    },

    exportClasses() {
        try {
            const headers = ['#', 'Name', 'Grade Level', 'Section', 'Capacity', 'Students', 'Status'];
            const rows = this.state.classes.map((cls, idx) => [
                idx + 1,
                cls.name,
                cls.grade_level,
                cls.section,
                cls.capacity,
                cls.student_count || 0,
                cls.status
            ]);

            const csv = [
                headers.join(','),
                ...rows.map(row => row.join(','))
            ].join('\n');

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `classes-${new Date().getTime()}.csv`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);

            this.showToast('Classes exported successfully', 'success', 'Success');
        } catch (error) {
            console.error('Error exporting classes:', error);
            this.showToast('Failed to export classes', 'error', 'Error');
        }
    },

    // ==================== STREAMS MANAGEMENT ====================
    async loadStreams() {
        try {
            const response = await window.API.academic.listStreams();
            const data = Array.isArray(response) ? response : (Array.isArray(response?.data) ? response.data : []);
            
            this.state.streams = data;
            this.renderStreamsTable();
        } catch (error) {
            console.error('Error loading streams:', error);
            this.showToast('Failed to load streams', 'error', 'Error');
        }
    },

    renderStreamsTable() {
        const tbody = document.getElementById('streamsTableBody');
        if (!tbody) return;

        if (this.state.streams.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4">
                        <p class="text-muted">No streams found</p>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = this.state.streams.map((stream, index) => `
            <tr>
                <td>${index + 1}</td>
                <td><strong>${stream.name || stream.stream_name || '-'}</strong></td>
                <td>${stream.class_name || '-'}</td>
                <td><span class="badge bg-primary">${stream.student_count || 0}</span></td>
                <td>${stream.teacher_name || 'Not assigned'}</td>
                <td>${stream.capacity || '-'}</td>
                <td>
                    <span class="badge ${stream.status === 'active' ? 'bg-success' : 'bg-secondary'}">
                        ${stream.status || 'active'}
                    </span>
                </td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-info" 
                                onclick="academicsController.editStream(${stream.id})" 
                                title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger" 
                                onclick="academicsController.deleteStream(${stream.id})" 
                                title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
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
                document.getElementById('streamName').value = streamData.name || '';
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

        const bsModal = new bootstrap.Modal(modal);
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
                await window.API.academic.createStream(data);
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
        if (!confirm('Are you sure you want to delete this stream?')) return;

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
            // Load classes with teacher assignments
            const response = await window.API.academic.listClasses();
            const data = Array.isArray(response) ? response : (Array.isArray(response?.data) ? response.data : []);
            
            const classesWithTeachers = data.filter(cls => cls.class_teacher_id);
            this.renderClassTeachersTable(classesWithTeachers);
        } catch (error) {
            console.error('Error loading class teachers:', error);
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
                <td><strong>${item.class_teacher_name || '-'}</strong></td>
                <td>${item.name || item.class_name || '-'}</td>
                <td>${item.stream_name || '-'}</td>
                <td><span class="badge bg-primary">${item.student_count || 0}</span></td>
                <td>${item.subject_name || '-'}</td>
                <td>${item.teacher_contact || '-'}</td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-warning" 
                                onclick="academicsController.showAssignTeacherModal(${item.id})" 
                                title="Reassign">
                            <i class="bi bi-person-check"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    },

    showAssignTeacherModal(classId = null) {
        const modal = document.getElementById('assignTeacherModal');
        if (!modal) return;

        if (classId) {
            document.getElementById('assignClass').value = classId;
        }

        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    },

    async assignTeacher(event) {
        event.preventDefault();

        const data = {
            class_id: document.getElementById('assignClass').value,
            stream_id: document.getElementById('assignStream').value || null,
            teacher_id: document.getElementById('assignTeacher').value,
            subject_id: document.getElementById('assignSubject').value || null,
            assignment_type: document.getElementById('assignmentType').value
        };

        if (!data.class_id || !data.teacher_id) {
            this.showToast('Please select a class and teacher', 'warning', 'Validation');
            return;
        }

        try {
            await window.API.academic.assignTeacher(data);
            this.showToast('Teacher assigned successfully', 'success', 'Success');
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('assignTeacherModal'));
            modal.hide();
            await this.loadClassTeachers();
        } catch (error) {
            console.error('Error assigning teacher:', error);
            this.showToast(error.message || 'Failed to assign teacher', 'error', 'Error');
        }
    },

    // ==================== TIMETABLES MANAGEMENT ====================
    async loadTimetables() {
        try {
            // Load timetables
            const response = await window.API.academic.listSchedules();
            const data = Array.isArray(response) ? response : (Array.isArray(response?.data) ? response.data : []);
            
            this.renderTimetablesSelect(data);
        } catch (error) {
            console.error('Error loading timetables:', error);
            this.showToast('Failed to load timetables', 'error', 'Error');
        }
    },

    renderTimetablesSelect(timetables) {
        const select = document.getElementById('timetableClassFilter');
        if (!select) return;

        select.innerHTML = '<option value="">Select Class</option>' +
            this.state.classes.map(cls => 
                `<option value="${cls.id}">${cls.name}</option>`
            ).join('');
    },

    async loadTimetableForClass(classId) {
        if (!classId) {
            document.getElementById('timetableContainer').innerHTML = 
                '<p class="text-muted text-center">Select a class to view timetable</p>';
            return;
        }

        try {
            const response = await window.API.academic.getSchedule(classId);
            const data = response || {};
            
            if (!data || Object.keys(data).length === 0) {
                document.getElementById('timetableContainer').innerHTML = 
                    '<p class="text-muted text-center">No timetable found for this class</p>';
                return;
            }

            this.renderTimetable(data);
        } catch (error) {
            console.error('Error loading timetable:', error);
            document.getElementById('timetableContainer').innerHTML = 
                '<p class="text-danger text-center">Failed to load timetable</p>';
        }
    },

    renderTimetable(timetableData) {
        const container = document.getElementById('timetableContainer');
        if (!container) return;

        // Simple timetable display (customize based on actual data structure)
        container.innerHTML = `
            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Time</th>
                            <th>Monday</th>
                            <th>Tuesday</th>
                            <th>Wednesday</th>
                            <th>Thursday</th>
                            <th>Friday</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="6" class="text-center py-3">
                                <p class="text-muted">Timetable data structure to be implemented</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        `;
    },

    generateTimetable() {
        this.showToast('Timetable generation feature coming soon', 'info', 'Info');
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
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="academicsController.showSubjectModal(${s.id})" title="Edit"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="academicsController._deleteSubject(${s.id},'${this._escH(s.name || s.subject_name).replace(/'/g,"\\'")}')"><i class="bi bi-trash"></i></button>
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
                <button class="page-link" onclick="academicsController._goSubjectPage(${i + 1})">${i + 1}</button>
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
        if (!confirm('Delete subject "' + name + '"? This cannot be undone.')) return;
        try {
            await callAPI('/academic/learning-areas/delete/' + id, 'DELETE');
            this.showToast('Subject deleted', 'success', 'Deleted');
            await this._loadSubjectsPage();
        } catch (err) {
            this.showToast(err?.message || 'Cannot delete subject', 'error', 'Error');
        }
    },

    exportSubjects() {
        const rows = [['Code','Name','Category','Level','Department','Status']];
        this._subjects.forEach(s => rows.push([
            s.code || s.subject_code || '', s.name || s.subject_name || '',
            s.category || '', s.grade_level || '', s.department || '', s.status || 'active',
        ]));
        const csv  = rows.map(r => r.map(v => '"' + String(v).replace(/"/g,'""') + '"').join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv' });
        const a    = document.createElement('a');
        a.href = URL.createObjectURL(blob); a.download = 'subjects.csv'; a.click();
    },

    // ── Curriculum Unit Modal ───────────────────────────────────────
    showCurriculumUnitModal(id) {
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
            // TODO: load unit data when curriculum_units endpoint is available
            document.getElementById('unitModalAction').textContent = 'Edit';
        }
        bootstrap.Modal.getOrCreateInstance(document.getElementById('curriculumUnitModal')).show();
    },

    async saveCurriculumUnit(e) {
        e.preventDefault();
        const id = document.getElementById('unitId').value;
        const methods = Array.from(document.getElementById('unitAssessmentMethods').selectedOptions).map(o => o.value);
        const payload = {
            name:              document.getElementById('unitName').value.trim(),
            code:              document.getElementById('unitCode').value.trim(),
            sequence_order:    parseInt(document.getElementById('unitSequence').value) || 1,
            subject_id:        document.getElementById('unitSubject').value,
            term_number:       document.getElementById('unitTerm').value,
            duration_hours:    document.getElementById('unitDuration').value,
            objectives:        document.getElementById('unitObjectives').value.trim(),
            topics:            document.getElementById('unitTopics').value.trim(),
            assessment_methods: methods,
            resources_needed:  document.getElementById('unitResources').value.trim(),
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

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    academicsController.init();
});
