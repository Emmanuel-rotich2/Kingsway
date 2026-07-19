/**
 * My CATs Controller - Class Teacher Specific CAT Management
 * Role: Class Teacher (7)
 * Shows only CATs for the teacher's assigned classes
 * Integrates with AcademicContext for academic year awareness
 */

const myCatsCtrl = (() => {
    const state = {
        cats: [],
        years: [],
        terms: [],
        classes: [],
        subjects: [],
        currentAcademicYear: null,
        currentTerm: null
    };

    function toast(msg, type = 'info') {
        const el = document.getElementById('myCatsToast');
        if (!el) {
            // Create toast element if it doesn't exist
            const toast = document.createElement('div');
            toast.id = 'myCatsToast';
            toast.className = `toast align-items-center text-bg-${type} border-0 position-fixed top-0 end-0 m-3`;
            toast.style.zIndex = '9999';
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">${msg}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            document.body.appendChild(toast);
            new bootstrap.Toast(toast, { delay: 3000 }).show();
        } else {
            el.className = `toast align-items-center text-bg-${type} border-0 position-fixed top-0 end-0 m-3`;
            el.querySelector('.toast-body').textContent = msg;
            new bootstrap.Toast(el, { delay: 3000 }).show();
        }
    }

    async function apiCall(endpoint, method = 'GET', data = null) {
        try {
            return await window.API.apiCall(endpoint, method, data, null, { checkPermission: false });
        } catch (error) {
            console.error('API call failed:', error);
            throw error;
        }
    }

    async function loadYears() {
        try {
            const response = await apiCall('academic/years-list');
            state.years = response.data || [];
            const select = document.getElementById('yearFilter');
            if (select) {
                select.innerHTML = '<option value="">All Years</option>';
                state.years.forEach(year => {
                    const option = document.createElement('option');
                    option.value = year.id;
                    option.textContent = year.year_name;
                    if (year.is_current) option.selected = true;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Failed to load years:', error);
        }
    }

    async function loadTerms() {
        try {
            const response = await apiCall('academic/terms-list');
            state.terms = response.data || [];
            const select = document.getElementById('termFilter');
            if (select) {
                select.innerHTML = '<option value="">All Terms</option>';
                state.terms.forEach(term => {
                    const option = document.createElement('option');
                    option.value = term.id;
                    option.textContent = `${term.name} (${term.year_code || ''})`;
                    if (term.status === 'current') option.selected = true;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Failed to load terms:', error);
        }
    }

    async function loadClasses() {
        try {
            const response = await apiCall('academic/classes-list');
            state.classes = response.data || [];
            const select = document.getElementById('classFilter');
            const catClassSelect = document.getElementById('catClass');
            
            if (select) {
                select.innerHTML = '<option value="">All Classes</option>';
                state.classes.forEach(cls => {
                    const option = document.createElement('option');
                    option.value = cls.id;
                    option.textContent = cls.class_name;
                    select.appendChild(option);
                });
            }
            
            if (catClassSelect) {
                catClassSelect.innerHTML = '<option value="">Select Class</option>';
                state.classes.forEach(cls => {
                    const option = document.createElement('option');
                    option.value = cls.id;
                    option.textContent = cls.class_name;
                    catClassSelect.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Failed to load classes:', error);
        }
    }

    async function loadSubjects() {
        try {
            const response = await apiCall('academic/subjects-list');
            state.subjects = response.data || [];
            const select = document.getElementById('catSubject');
            if (select) {
                select.innerHTML = '<option value="">Select Subject</option>';
                state.subjects.forEach(subject => {
                    const option = document.createElement('option');
                    option.value = subject.id;
                    option.textContent = subject.subject_name;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Failed to load subjects:', error);
        }
    }

    async function loadCats() {
        try {
            const yearId = document.getElementById('yearFilter')?.value || '';
            const termId = document.getElementById('termFilter')?.value || '';
            const classId = document.getElementById('classFilter')?.value || '';

            const response = await apiCall('academic/formative-assessments', 'GET', {
                year_id: yearId,
                term_id: termId,
                class_id: classId,
                teacher_only: true // Only show CATs for this teacher's classes
            });

            state.cats = response.data || [];
            renderCats();
            updateStats();
        } catch (error) {
            console.error('Failed to load CATs:', error);
            toast('Failed to load CATs', 'error');
        }
    }

    function renderCats() {
        const tbody = document.getElementById('catsTableBody');
        if (!tbody) return;

        if (!state.cats.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        No CATs found for your classes
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = state.cats.map(cat => {
            const statusClass = cat.status === 'active' ? 'success' : 
                              cat.status === 'completed' ? 'primary' : 'warning';
            return `
                <tr>
                    <td><strong>${cat.name || 'Unnamed CAT'}</strong></td>
                    <td>${cat.class_name || '—'}</td>
                    <td>${cat.subject_name || '—'}</td>
                    <td><span class="badge bg-secondary">${cat.type || '—'}</span></td>
                    <td>${cat.cat_date || '—'}</td>
                    <td><span class="badge bg-${statusClass}">${cat.status || 'draft'}</span></td>
                    <td>${cat.student_count || 0}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="myCatsCtrl.editCat(${cat.id})">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-success" onclick="myCatsCtrl.enterMarks(${cat.id})">
                                <i class="bi bi-check-circle"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="myCatsCtrl.deleteCat(${cat.id})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function updateStats() {
        const total = state.cats.length;
        const active = state.cats.filter(c => c.status === 'active').length;
        const draft = state.cats.filter(c => c.status === 'draft').length;

        document.getElementById('totalCats').textContent = total;
        document.getElementById('activeCats').textContent = active;
        document.getElementById('draftCats').textContent = draft;
    }

    function showCatModal(catId = null) {
        const modal = new bootstrap.Modal(document.getElementById('catModal'));
        const title = document.getElementById('catModalTitle');
        const form = document.getElementById('catForm');
        
        form.reset();
        document.getElementById('catId').value = '';

        if (catId) {
            const cat = state.cats.find(c => c.id === catId);
            if (cat) {
                title.textContent = 'Edit CAT';
                document.getElementById('catId').value = cat.id;
                document.getElementById('catName').value = cat.name || '';
                document.getElementById('catType').value = cat.type || '';
                document.getElementById('catClass').value = cat.class_id || '';
                document.getElementById('catSubject').value = cat.subject_id || '';
                document.getElementById('catDate').value = cat.cat_date || '';
                document.getElementById('catMaxMarks').value = cat.max_marks || 20;
                document.getElementById('catDescription').value = cat.description || '';
                document.getElementById('catStatus').value = cat.status || 'draft';
            }
        } else {
            title.textContent = 'Create CAT';
        }

        modal.show();
    }

    async function saveCat() {
        try {
            const catId = document.getElementById('catId').value;
            const data = {
                name: document.getElementById('catName').value,
                type: document.getElementById('catType').value,
                class_id: document.getElementById('catClass').value,
                subject_id: document.getElementById('catSubject').value,
                cat_date: document.getElementById('catDate').value,
                max_marks: document.getElementById('catMaxMarks').value,
                description: document.getElementById('catDescription').value,
                status: document.getElementById('catStatus').value
            };

            const method = catId ? 'PUT' : 'POST';
            const endpoint = catId ? `academic/formative-assessments/${catId}` : 'academic/formative-assessments';

            await apiCall(endpoint, method, data);
            
            bootstrap.Modal.getInstance(document.getElementById('catModal')).hide();
            toast(catId ? 'CAT updated successfully' : 'CAT created successfully', 'success');
            loadCats();
        } catch (error) {
            console.error('Failed to save CAT:', error);
            toast('Failed to save CAT', 'error');
        }
    }

    async function deleteCat(catId) {
        if (!confirm('Are you sure you want to delete this CAT?')) return;

        try {
            await apiCall(`academic/formative-assessments/${catId}`, 'DELETE');
            toast('CAT deleted successfully', 'success');
            loadCats();
        } catch (error) {
            console.error('Failed to delete CAT:', error);
            toast('Failed to delete CAT', 'error');
        }
    }

    function enterMarks(catId) {
        // Navigate to marks entry page for this CAT
        window.location.href = `?route=enter_marks&cat_id=${catId}`;
    }

    function bindEvents() {
        document.getElementById('refreshBtn')?.addEventListener('click', loadCats);
        document.getElementById('createCatBtn')?.addEventListener('click', () => showCatModal());
        document.getElementById('saveCatBtn')?.addEventListener('click', saveCat);
        
        document.getElementById('yearFilter')?.addEventListener('change', loadCats);
        document.getElementById('termFilter')?.addEventListener('change', loadCats);
        document.getElementById('classFilter')?.addEventListener('change', loadCats);
    }

    async function init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }

        // Initialize Academic Context if available
        if (window.AcademicContext) {
            window.AcademicContext.subscribe((context, event, data) => {
                console.log('AcademicContext changed in my_cats:', event, data);
                if (event === 'yearChanged' || event === 'termChanged' || event === 'initialized' || event === 'refreshed') {
                    loadYears();
                    loadTerms();
                    loadCats();
                }
            });

            if (!window.AcademicContext.isLoaded()) {
                await window.AcademicContext.init();
            }

            state.currentAcademicYear = window.AcademicContext.getAcademicYearId();
            state.currentTerm = window.AcademicContext.getTermId();
        }

        document.getElementById('myCatsLoading').style.display = 'none';
        document.getElementById('myCatsContent').style.display = 'block';

        await Promise.all([loadYears(), loadTerms(), loadClasses(), loadSubjects()]);
        await loadCats();
        bindEvents();
    }

    return {
        init,
        editCat: showCatModal,
        deleteCat,
        enterMarks
    };
})();

document.addEventListener('DOMContentLoaded', myCatsCtrl.init);
