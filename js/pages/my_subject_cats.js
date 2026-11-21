/**
 * My Subject CATs Controller - Subject Teacher Specific CAT Management
 * Role: Subject Teacher (8)
 * Shows only CATs for the teacher's assigned subjects
 * Integrates with AcademicContext for academic year awareness
 */

const mySubjectCatsCtrl = (() => {
    const state = {
        cats: [],
        years: [],
        terms: [],
        subjects: [],
        classes: [],
        currentAcademicYear: null,
        currentTerm: null
    };

    const _esc = (s) => {
        const d = document.createElement('div');
        d.textContent = String(s ?? '');
        return d.innerHTML;
    };

    function toast(msg, type = 'info') {
        const el = document.getElementById('myCatsToast');
        if (!el) {
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

    /** Rows from an unwrapped API payload (list or {data|items|...}). */
    function rowsOf(payload, key = '') {
        if (Array.isArray(payload)) return payload;
        if (key && Array.isArray(payload?.[key])) return payload[key];
        return payload?.data || payload?.items || payload?.assessments || payload?.students || payload?.subjects || payload?.exams || [];
    }

    /** The shared year/term selector; the current year (and running term) by default. */
    async function mountYearFilter() {
        await window.AcademicYearFilter?.mount(document.getElementById('yearFilter'), {
            termSelect: document.getElementById('termFilter'),
            allTermsLabel: 'All terms',
            allTermsDefault: false,
            onChange: (yearId, year, termId) => {
                state.currentAcademicYear = yearId;
                state.currentTerm = termId;
                loadCats();
            },
        });
    }

    async function loadSubjects() {
        try {
            // Load only subjects assigned to this teacher
            const response = await apiCall('academic/subjects-list', 'GET', null, {
                subject_teacher_only: true
            });
            state.subjects = response || [];
            const select = document.getElementById('subjectFilter');
            const catSubjectSelect = document.getElementById('catSubject');
            
            if (select) {
                select.innerHTML = '<option value="">All Subjects</option>';
                state.subjects.forEach(subject => {
                    const option = document.createElement('option');
                    option.value = subject.id;
                    option.textContent = subject.name || subject.subject_name;
                    select.appendChild(option);
                });
            }
            
            if (catSubjectSelect) {
                catSubjectSelect.innerHTML = '<option value="">Select Subject</option>';
                state.subjects.forEach(subject => {
                    const option = document.createElement('option');
                    option.value = subject.id;
                    option.textContent = subject.name || subject.subject_name;
                    catSubjectSelect.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Failed to load subjects:', error);
        }
    }

    async function loadClasses() {
        try {
            const response = await apiCall('academic/classes-list');
            state.classes = response || [];
            const select = document.getElementById('catClass');
            if (select) {
                select.innerHTML = '<option value="">Select Class</option>';
                state.classes.forEach(cls => {
                    const option = document.createElement('option');
                    option.value = cls.id;
                    option.textContent = cls.name || cls.class_name;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Failed to load classes:', error);
        }
    }

    async function loadCats() {
        try {
            const yearId = state.currentAcademicYear;
            if (!yearId) return;
            const termId = state.currentTerm || '';
            const subjectId = document.getElementById('subjectFilter')?.value || '';

            const response = await apiCall('academic/formative-assessments', 'GET', null, {
                academic_year_id: yearId,
                year_id: yearId,
                academic_year_term_id: termId,
                term_id: termId,
                subject_id: subjectId,
                subject_teacher_only: true // Only show CATs for this teacher's subjects
            });

            state.cats = response || [];
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
                        No CATs found for your subjects
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
                    <td><strong>${_esc(cat.name) || 'Unnamed CAT'}</strong></td>
                    <td>${_esc(cat.subject_name) || '—'}</td>
                    <td>${_esc(cat.class_name) || '—'}</td>
                    <td><span class="badge bg-secondary">${_esc(cat.type) || '—'}</span></td>
                    <td>${_esc(cat.cat_date) || '—'}</td>
                    <td><span class="badge bg-${statusClass}">${_esc(cat.status) || 'draft'}</span></td>
                    <td>${Number(cat.student_count) || 0}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" data-year-write data-kw-onclick="mySubjectCatsCtrl.editCat(${cat.id})">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-success" data-kw-onclick="mySubjectCatsCtrl.enterMarks(${cat.id})">
                                <i class="bi bi-check-circle"></i>
                            </button>
                            <button class="btn btn-outline-danger" data-year-write data-kw-onclick="mySubjectCatsCtrl.deleteCat(${cat.id})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
        // A completed year stays read-only for users who may not edit it.
        window.YearMode?.refresh(tbody);
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
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('catModal'));
        const title = document.getElementById('catModalTitle');
        const form = document.getElementById('catForm');
        
        form.reset();
        document.getElementById('catId').value = '';

        if (catId) {
            const cat = state.cats.find(c => c.id === catId);
            if (cat) {
                const typeName = (cat.type_name || cat.type || '').toString().toLowerCase();
                const typeSelect = document.getElementById('catType');
                const typeMatch = typeSelect
                    ? [...typeSelect.options].find(o => o.text.toLowerCase() === typeName || typeName.includes(o.value))
                    : null;
                title.textContent = 'Edit CAT';
                document.getElementById('catId').value = cat.id;
                document.getElementById('catName').value = cat.name || '';
                document.getElementById('catType').value = typeMatch ? typeMatch.value : (cat.type || '');
                document.getElementById('catSubject').value = cat.subject_id || '';
                document.getElementById('catClass').value = cat.class_id || '';
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
            // A new CAT belongs to the term the page is showing, never "now".
            if (!catId && !state.currentTerm) {
                toast('Choose the term this CAT belongs to in the Term filter first.', 'warning');
                return;
            }
            const data = {
                academic_year_id: state.currentAcademicYear,
                ...(catId ? {} : { academic_year_term_id: state.currentTerm, term_id: state.currentTerm }),
                name: document.getElementById('catName').value,
                type: document.getElementById('catType').value,
                subject_id: document.getElementById('catSubject').value,
                class_id: document.getElementById('catClass').value,
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
            toast(error?.message || 'Failed to save CAT', 'error');
        }
    }

    async function deleteCat(catId) {
        if (!(await window.confirmAction('Confirm Deletion', 'Are you sure you want to delete this CAT?', { confirmText: 'Delete', danger: true }))) return;

        try {
            await apiCall(`academic/formative-assessments/${catId}`, 'DELETE');
            toast('CAT deleted successfully', 'success');
            loadCats();
        } catch (error) {
            console.error('Failed to delete CAT:', error);
            toast(error?.message || 'Failed to delete CAT', 'error');
        }
    }

    function enterMarks(catId) {
        // Navigate to marks entry page for this CAT
        window.location.href = `?route=subject_grade_entry&cat_id=${catId}`;
    }

    function bindEvents() {
        document.getElementById('refreshBtn')?.addEventListener('click', loadCats);
        document.getElementById('createCatBtn')?.addEventListener('click', () => showCatModal());
        document.getElementById('saveCatBtn')?.addEventListener('click', saveCat);
        document.getElementById('subjectFilter')?.addEventListener('change', loadCats);
    }

    async function init() {
        await window.AuthContext?.ready();
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }

        // Initialize Academic Context if available
        if (window.AcademicContext) {
            window.AcademicContext.subscribe((context, event, data) => {
                if (event === 'refreshed') loadCats();
            });

            if (!window.AcademicContext.isLoaded()) {
                await window.AcademicContext.init();
            }
        }

        document.getElementById('myCatsLoading').style.display = 'none';
        document.getElementById('myCatsContent').style.display = 'block';

        await Promise.all([loadSubjects(), loadClasses()]);

        bindEvents();

        await mountYearFilter(); // loads data for the default year/term

    }

    return {
        init,
        editCat: showCatModal,
        deleteCat,
        enterMarks
    };
})();

document.addEventListener('DOMContentLoaded', mySubjectCatsCtrl.init);

window.mySubjectCatsCtrl = mySubjectCatsCtrl;
window.APIRealtime?.register?.(mySubjectCatsCtrl, mySubjectCatsCtrl);
