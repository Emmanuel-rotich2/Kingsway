/**
 * Teacher Performance Reviews Controller
 * Handles teacher_performance_reviews.php
 * Uses existing api.js JWT authentication
 */
const TeacherPerformanceReviewsController = {
    reviews: [],
    teachers: [],
    subjects: [],
    currentFilters: {
        search: '',
        filter: '',
        date: ''
    },

    async init() {
        if (typeof AuthContext !== 'undefined') {
            await AuthContext.ready();
        }

        if (!AuthContext?.isAuthenticated()) {
            window.location.href = (window.APP_BASE || "") + "/index.php";
            return;
        }

        if (!AuthContext.canView('staff')) {
            showNotification('You do not have permission to view performance reviews', 'error');
            return;
        }

        this.bindEvents();
        await this.loadInitialData();
    },

    async loadInitialData() {
        await Promise.all([
            this.loadReviews(),
            this.loadTeachers(),
            this.loadSubjects()
        ]);
    },

    async loadReviews() {
        try {
            const response = await window.API.staff.getPerformanceReviews({});
            const normalized = AppState.normalizeResponse(response);
            
            if (normalized.success) {
                this.reviews = Array.isArray(normalized.data) ? normalized.data : [];
                this.render();
            } else {
                showNotification('Failed to load performance reviews', 'error');
            }
        } catch (error) {
            console.error('Error loading performance reviews:', error);
            showNotification('Failed to load performance reviews', 'error');
        }
    },

    async loadTeachers() {
        try {
            const response = await window.API.staff.getTeachers({});
            const normalized = AppState.normalizeResponse(response);
            
            if (normalized.success) {
                this.teachers = Array.isArray(normalized.data) ? normalized.data : [];
            }
        } catch (error) {
            console.error('Error loading teachers:', error);
        }
    },

    async loadSubjects() {
        try {
            const response = await window.API.academic.listSubjects();
            const normalized = AppState.normalizeResponse(response);
            
            if (normalized.success) {
                this.subjects = Array.isArray(normalized.data) ? normalized.data : [];
                this.populateFilterDropdown();
            }
        } catch (error) {
            console.error('Error loading subjects:', error);
        }
    },

    populateFilterDropdown() {
        const select = document.getElementById('filterSelect');
        if (!select) return;
        select.innerHTML = '<option value="">All</option>' + 
            this.subjects.map(subject => `<option value="${subject.id}">${subject.name}</option>`).join('');
    },

    bindEvents() {
        document.getElementById('searchInput')?.addEventListener('input', (e) => {
            this.currentFilters.search = e.target.value;
            this.applyFilters();
        });

        document.getElementById('filterSelect')?.addEventListener('change', (e) => {
            this.currentFilters.filter = e.target.value;
            this.applyFilters();
        });

        document.getElementById('dateFilter')?.addEventListener('change', (e) => {
            this.currentFilters.date = e.target.value;
            this.applyFilters();
        });
    },

    applyFilters() {
        let filtered = [...this.reviews];

        if (this.currentFilters.search) {
            const search = this.currentFilters.search.toLowerCase();
            filtered = filtered.filter(review => {
                const teacher = this.getTeacherName(review.teacher_id);
                const reviewer = review.reviewer_name || '';
                return teacher.toLowerCase().includes(search) || reviewer.toLowerCase().includes(search);
            });
        }

        if (this.currentFilters.filter) {
            filtered = filtered.filter(review => review.subject_id == this.currentFilters.filter);
        }

        if (this.currentFilters.date) {
            filtered = filtered.filter(review => review.review_date === this.currentFilters.date);
        }

        this.renderTable(filtered);
    },

    render() {
        this.renderStats();
        this.renderTable(this.reviews);
    },

    renderStats() {
        const total = this.reviews.length;
        const ratings = this.reviews.map(r => r.rating || 0);
        const avg = ratings.length ? (ratings.reduce((a, b) => a + b, 0) / ratings.length).toFixed(1) : 0;
        const excellent = ratings.filter(r => r >= 4).length;
        const low = ratings.filter(r => r < 2).length;

        document.getElementById('statTotal').textContent = total;
        document.getElementById('statAvg').textContent = avg;
        document.getElementById('statExcellent').textContent = excellent;
        document.getElementById('statLow').textContent = low;
    },

    renderTable(reviews) {
        const tbody = document.querySelector('#dataTable tbody');
        if (!tbody) return;

        if (reviews.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No performance reviews found</td></tr>';
            return;
        }

        tbody.innerHTML = reviews.map((review, index) => {
            const teacher = this.getTeacherName(review.teacher_id);
            const subject = this.getSubjectName(review.subject_id);
            const ratingBadge = this.getRatingBadge(review.rating);
            
            return `
                <tr>
                    <td>${index + 1}</td>
                    <td><strong>${this.escapeHtml(teacher)}</strong></td>
                    <td>${this.escapeHtml(subject)}</td>
                    <td>${review.review_date || '-'}</td>
                    <td>${this.escapeHtml(review.reviewer_name || '-')}</td>
                    <td>${ratingBadge}</td>
                    <td>${this.escapeHtml(review.category || '-')}</td>
                    <td><small>${this.escapeHtml(review.remarks || '-')}</small></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="TeacherPerformanceReviewsController.viewDetails(${review.id})" title="View Details">
                            <i class="bi bi-eye"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    },

    getTeacherName(teacherId) {
        const teacher = this.teachers.find(t => t.id === teacherId);
        return teacher ? `${teacher.first_name} ${teacher.last_name}` : 'Unknown';
    },

    getSubjectName(subjectId) {
        const subject = this.subjects.find(s => s.id === subjectId);
        return subject ? subject.name : 'Unknown';
    },

    getRatingBadge(rating) {
        if (rating >= 4) return '<span class="badge bg-success">Excellent</span>';
        if (rating >= 3) return '<span class="badge bg-primary">Good</span>';
        if (rating >= 2) return '<span class="badge bg-warning">Fair</span>';
        return '<span class="badge bg-danger">Poor</span>';
    },

    viewDetails(reviewId) {
        const review = this.reviews.find(r => r.id === reviewId);
        if (!review) return;

        const teacher = this.getTeacherName(review.teacher_id);
        const subject = this.getSubjectName(review.subject_id);

        alert(`Performance Review Details:\n\nTeacher: ${teacher}\nSubject: ${subject}\nDate: ${review.review_date}\nReviewer: ${review.reviewer_name}\nRating: ${review.rating}\nCategory: ${review.category}\nRemarks: ${review.remarks}`);
    },

    refresh() {
        this.currentFilters = { search: '', filter: '', date: '' };
        document.getElementById('searchInput').value = '';
        document.getElementById('filterSelect').value = '';
        document.getElementById('dateFilter').value = '';
        this.loadReviews();
    },

    exportCSV() {
        if (!AuthContext.canExport('staff')) {
            showNotification('You do not have permission to export performance reviews', 'error');
            return;
        }

        if (!this.reviews.length) {
            showNotification('No data to export', 'warning');
            return;
        }

        const headers = ['#', 'Teacher', 'Subject', 'Review Date', 'Reviewer', 'Rating', 'Category', 'Remarks'];
        const rows = this.reviews.map((review, i) => [
            i + 1,
            this.getTeacherName(review.teacher_id),
            this.getSubjectName(review.subject_id),
            review.review_date || '-',
            review.reviewer_name || '-',
            review.rating || '-',
            review.category || '-',
            review.remarks || '-'
        ]);

        let csv = headers.join(',') + '\n' + 
            rows.map(r => r.map(v => '"' + (v || '') + '"').join(',')).join('\n');

        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
        a.download = 'teacher_performance_reviews.csv';
        a.click();
    },

    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
};

document.addEventListener('DOMContentLoaded', () => TeacherPerformanceReviewsController.init());