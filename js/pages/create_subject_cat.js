/**
 * Create Subject CAT Controller - Subject Teacher CAT Creation
 * Role: Subject Teacher (8)
 * Shows only subjects the teacher is assigned to
 * Integrates with AcademicContext for academic year awareness
 */

const createSubjectCatCtrl = (() => {
    const state = {
        subjects: [],
        classes: [],
        years: [],
        terms: [],
        currentAcademicYear: null,
        currentTerm: null
    };

    function toast(msg, type = 'info') {
        const el = document.getElementById('catToast');
        if (!el) {
            const toast = document.createElement('div');
            toast.id = 'catToast';
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
            const select = document.getElementById('catYear');
            if (select) {
                select.innerHTML = '<option value="">Select Year</option>';
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
            const select = document.getElementById('catTerm');
            if (select) {
                select.innerHTML = '<option value="">Select Term</option>';
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

    async function loadSubjects() {
        try {
            // Load only subjects assigned to this teacher
            const response = await apiCall('academic/subjects-list', 'GET', {
                subject_teacher_only: true
            });
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

    async function loadClasses() {
        try {
            const response = await apiCall('academic/classes-list');
            state.classes = response.data || [];
            const select = document.getElementById('catClass');
            if (select) {
                select.innerHTML = '<option value="">Select Class</option>';
                state.classes.forEach(cls => {
                    const option = document.createElement('option');
                    option.value = cls.id;
                    option.textContent = cls.class_name;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Failed to load classes:', error);
        }
    }

    async function createCat(event) {
        event.preventDefault();

        const data = {
            name: document.getElementById('catName').value,
            type: document.getElementById('catType').value,
            subject_id: document.getElementById('catSubject').value,
            class_id: document.getElementById('catClass').value,
            year_id: document.getElementById('catYear').value,
            term_id: document.getElementById('catTerm').value,
            cat_date: document.getElementById('catDate').value,
            max_marks: document.getElementById('catMaxMarks').value,
            status: document.getElementById('catStatus').value,
            description: document.getElementById('catDescription').value,
            instructions: document.getElementById('catInstructions').value
        };

        try {
            const submitBtn = document.querySelector('#createCatForm button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating...';

            await apiCall('academic/formative-assessments', 'POST', data);

            toast('CAT created successfully', 'success');
            document.getElementById('createCatForm').reset();
            
            // Reload data
            await Promise.all([loadYears(), loadTerms(), loadSubjects(), loadClasses()]);
        } catch (error) {
            console.error('Failed to create CAT:', error);
            toast('Failed to create CAT', 'error');
        } finally {
            const submitBtn = document.querySelector('#createCatForm button[type="submit"]');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Create CAT';
        }
    }

    function resetForm() {
        document.getElementById('createCatForm').reset();
        toast('Form reset', 'info');
    }

    function viewMyCats() {
        // Navigate to my_subject_cats page
        window.location.href = '?route=my_subject_cats';
    }

    function bindEvents() {
        document.getElementById('createCatForm')?.addEventListener('submit', createCat);
        document.getElementById('resetBtn')?.addEventListener('click', resetForm);
        document.getElementById('viewMyCatsBtn')?.addEventListener('click', viewMyCats);
        
        // Update classes when subject changes (optional)
        document.getElementById('catSubject')?.addEventListener('change', async (e) => {
            // Could filter classes based on subject if needed
        });
    }

    async function init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }

        // Initialize Academic Context if available
        if (window.AcademicContext) {
            window.AcademicContext.subscribe((context, event, data) => {
                console.log('AcademicContext changed in create_subject_cat:', event, data);
                if (event === 'yearChanged' || event === 'termChanged' || event === 'initialized' || event === 'refreshed') {
                    loadYears();
                    loadTerms();
                }
            });

            if (!window.AcademicContext.isLoaded()) {
                await window.AcademicContext.init();
            }

            state.currentAcademicYear = window.AcademicContext.getAcademicYearId();
            state.currentTerm = window.AcademicContext.getTermId();
            
            // Set default year and term from context
            if (state.currentAcademicYear) {
                document.getElementById('catYear').value = state.currentAcademicYear;
            }
            if (state.currentTerm) {
                document.getElementById('catTerm').value = state.currentTerm;
            }
        }

        document.getElementById('catLoading').style.display = 'none';
        document.getElementById('catContent').style.display = 'block';

        await Promise.all([loadYears(), loadTerms(), loadSubjects(), loadClasses()]);
        bindEvents();
    }

    return { init };
})();

document.addEventListener('DOMContentLoaded', createSubjectCatCtrl.init);
