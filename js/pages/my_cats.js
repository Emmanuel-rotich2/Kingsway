/**
 * Formative Assessments Controller - effective scope for class teachers,
 * subject teachers, and staff holding both roles.
 * Integrates with AcademicContext for academic year awareness
 */

const myCatsCtrl = (() => {
    const state = {
        cats: [],
        years: [],
        terms: [],
        classes: [],
        subjects: [],
        assignments: [],
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

    async function apiCall(endpoint, method = 'GET', data = null, params = {}) {
        try {
            return await window.API.apiCall(endpoint, method, data, params, { checkPermission: false });
        } catch (error) {
            console.error('API call failed:', error);
            throw error;
        }
    }

    async function loadTeachingScope() {
        try {
            const response = await apiCall('academic/my-classes');
            state.assignments = Array.isArray(response) ? response : [];
            const classMap = new Map();
            const subjectMap = new Map();
            state.assignments.forEach((assignment) => {
                classMap.set(Number(assignment.class_id), {
                    id: Number(assignment.class_id),
                    name: assignment.class_name
                });
                subjectMap.set(Number(assignment.subject_id), {
                    id: Number(assignment.subject_id),
                    name: assignment.subject_name
                });
            });
            state.classes = [...classMap.values()];
            state.subjects = [...subjectMap.values()];
            const select = document.getElementById('classFilter');
            const catClassSelect = document.getElementById('catClass');
            
            if (select) {
                select.innerHTML = '<option value="">All Classes</option>';
                state.classes.forEach(cls => {
                    const option = document.createElement('option');
                    option.value = cls.id;
                    option.textContent = cls.name || '';
                    select.appendChild(option);
                });
            }
            
            if (catClassSelect) {
                catClassSelect.innerHTML = '<option value="">Select Class</option>';
                state.classes.forEach(cls => {
                    const option = document.createElement('option');
                    option.value = cls.id;
                    option.textContent = cls.name || '';
                    catClassSelect.appendChild(option);
                });
            }
            populateSubjectOptions();
        } catch (error) {
            console.error('Failed to load teaching scope:', error);
            toast('Unable to load your assigned classes and learning areas', 'error');
        }
    }

    function populateSubjectOptions(classId = '') {
        const select = document.getElementById('catSubject');
        if (!select) return;
        const selected = String(select.value || '');
        const subjectMap = new Map();
        state.assignments
            .filter((assignment) => !classId || String(assignment.class_id) === String(classId))
            .forEach((assignment) => subjectMap.set(Number(assignment.subject_id), assignment.subject_name));
        select.innerHTML = '<option value="">Select Learning Area</option>' +
            [...subjectMap.entries()].map(([id, name]) => `<option value="${id}">${_esc(name)}</option>`).join('');
        if ([...subjectMap.keys()].some((id) => String(id) === selected)) select.value = selected;
    }

    async function loadCats() {
        try {
            const yearId = document.getElementById('yearFilter')?.value || '';
            const termId = document.getElementById('termFilter')?.value || '';
            const classId = document.getElementById('classFilter')?.value || '';

            const response = await apiCall('academic/formative-assessments', 'GET', null, {
                year_id: yearId,
                term_id: termId,
                class_id: classId,
                teacher_scope_only: true
            });

            state.cats = response || [];
            renderCats();
            updateStats();
        } catch (error) {
            console.error('Failed to load CATs:', error);
            toast('Failed to load formative assessments', 'error');
        }
    }

    function renderCats() {
        const tbody = document.getElementById('catsTableBody');
        if (!tbody) return;

        if (!state.cats.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        No formative assessments found in your teaching scope
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
                    <td>${_esc(cat.class_name) || '—'}</td>
                    <td>${_esc(cat.subject_name) || '—'}</td>
                    <td><span class="badge bg-secondary">${_esc(cat.type) || '—'}</span></td>
                    <td>${_esc(cat.cat_date) || '—'}</td>
                    <td><span class="badge bg-${statusClass}">${_esc(cat.status) || 'draft'}</span></td>
                    <td>${Number(cat.student_count) || 0}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" data-kw-onclick="myCatsCtrl.editCat(${cat.id})" data-year-write>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-success" data-kw-onclick="myCatsCtrl.enterMarks(${cat.id})">
                                <i class="bi bi-check-circle"></i>
                            </button>
                            <button class="btn btn-outline-danger" data-kw-onclick="myCatsCtrl.deleteCat(${cat.id})" data-year-write>
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
                title.textContent = 'Edit Formative Assessment';
                document.getElementById('catId').value = cat.id;
                document.getElementById('catName').value = cat.name || '';
                document.getElementById('catType').value = typeMatch ? typeMatch.value : (cat.type || '');
                document.getElementById('catClass').value = cat.class_id || '';
                document.getElementById('catSubject').value = cat.subject_id || '';
                document.getElementById('catDate').value = cat.cat_date || '';
                document.getElementById('catMaxMarks').value = cat.max_marks || 20;
                document.getElementById('catDescription').value = cat.description || '';
                document.getElementById('catStatus').value = cat.status || 'draft';
            }
        } else {
            title.textContent = 'New Formative Assessment';
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
            toast(catId ? 'Formative assessment updated' : 'Formative assessment created', 'success');
            loadCats();
        } catch (error) {
            console.error('Failed to save formative assessment:', error);
            toast('Failed to save formative assessment', 'error');
        }
    }

    async function deleteCat(catId) {
        if (!(await window.confirmAction('Confirm Deletion', 'Delete this formative assessment?', { confirmText: 'Delete', danger: true }))) return;

        try {
            await apiCall(`academic/formative-assessments/${catId}`, 'DELETE');
            toast('Formative assessment deleted', 'success');
            loadCats();
        } catch (error) {
            console.error('Failed to delete formative assessment:', error);
            toast('Failed to delete formative assessment', 'error');
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
        
        document.getElementById('classFilter')?.addEventListener('change', loadCats);
        document.getElementById('catClass')?.addEventListener('change', (event) => populateSubjectOptions(event.target.value));
    }

    async function init() {
        await window.AuthContext?.ready();
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }

        document.getElementById('myCatsLoading').style.display = 'none';
        document.getElementById('myCatsContent').style.display = 'block';

        await loadTeachingScope();
        bindEvents();
        // Year and term: the shared filter (current year, running term, kept
        // in the URL), which loads the CATs now and on every change.
        const yearSelect = document.getElementById('yearFilter');
        const mounted = window.AcademicYearFilter && yearSelect
            ? await window.AcademicYearFilter.mount(yearSelect, {
                termSelect: document.getElementById('termFilter'),
                onChange: (yearId, year, termId) => {
                    state.currentAcademicYear = yearId;
                    state.currentTerm = termId;
                    loadCats();
                },
            })
            : null;
        if (!mounted) await loadCats();
    }

    return {
        init,
        editCat: showCatModal,
        deleteCat,
        enterMarks
    };
})();

document.addEventListener('DOMContentLoaded', myCatsCtrl.init);

window.myCatsCtrl = myCatsCtrl;
window.APIRealtime?.register?.(myCatsCtrl, myCatsCtrl);
