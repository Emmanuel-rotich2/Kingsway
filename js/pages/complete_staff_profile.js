/**
 * Staff Profile Completion Controller
 * One-time onboarding: review & complete profile after password setup.
 * Uses window.API.staffMigration from api.js
 */

const staffProfileController = {
    initialized: false,
    initializationPromise: null,
    eventBound: false,

    state: {
        profile: null,
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

            if (!window.API?.staffMigration) {
                throw new Error('Staff Migration API is unavailable.');
            }

        this.setupEventListeners();
            this.setupQualificationRows();
            await this.loadProfile();

            this.initialized = true;
            return this;
        } catch (error) {
            const state = document.getElementById('spState');
            if (state) {
                state.className = 'alert alert-danger';
                state.textContent = error.message || 'Unable to load profile.';
            }
            throw error;
        }
    },

    setupEventListeners() {
        if (this.eventBound) {
            return;
        }

        this.eventBound = true;

        const form = document.getElementById('spForm');
        if (form) {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                void this.saveProfile();
            });
        }
        document.getElementById('spAddQualification')?.addEventListener('click', () => this.addQualificationRow());
    },

    setupQualificationRows() {
        const container = document.getElementById('spQualifications');
        if (container && !container.children.length) this.addQualificationRow();
    },

    renderQualificationClaims(claims) {
        const container = document.getElementById('spQualifications');
        if (!container) return;
        container.innerHTML = '';
        claims.forEach((claim) => this.addQualificationRow(claim, true));
        this.addQualificationRow();
    },

    addQualificationRow(claim = {}, readOnly = false) {
        const container = document.getElementById('spQualifications');
        if (!container) return;
        const row = document.createElement('div');
        row.className = 'row g-2 mb-2 align-items-end';
        const status = claim.verification_status ? ` <span class="badge ${claim.verification_status === 'verified' ? 'bg-success' : 'bg-warning text-dark'}">${this._escH(claim.verification_status)}</span>` : '';
        row.innerHTML = `<div class="col-md-2"><label class="form-label small">Level${status}</label><select data-q="qualification_level" class="form-select" ${readOnly ? 'disabled' : ''}><option value="certificate">Certificate</option><option value="diploma">Diploma</option><option value="degree">Degree</option><option value="postgraduate_diploma">PG Diploma</option><option value="masters">Masters</option><option value="phd">PhD</option><option value="professional">Professional</option><option value="other">Other</option></select></div><div class="col-md-3"><label class="form-label small">Title</label><input data-q="title" class="form-control" maxlength="255" ${readOnly ? 'disabled' : ''}></div><div class="col-md-3"><label class="form-label small">Institution</label><input data-q="institution" class="form-control" maxlength="255" ${readOnly ? 'disabled' : ''}></div><div class="col-md-2"><label class="form-label small">Year</label><input data-q="year_obtained" type="number" min="1950" max="2100" class="form-control" ${readOnly ? 'disabled' : ''}></div><div class="col-md-2"><button type="button" class="btn btn-outline-${readOnly ? 'secondary' : 'danger'} w-100">${readOnly ? 'Recorded' : 'Remove'}</button></div>`;
        row.dataset.readOnly = readOnly ? '1' : '0';
        row.querySelector('[data-q="qualification_level"]').value = claim.qualification_level || 'degree';
        row.querySelector('[data-q="title"]').value = claim.title || '';
        row.querySelector('[data-q="institution"]').value = claim.institution || '';
        row.querySelector('[data-q="year_obtained"]').value = claim.year_obtained || '';
        if (!readOnly) row.querySelector('button').addEventListener('click', () => row.remove());
        container.appendChild(row);
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

    // ==================== PROFILE LOADING ====================
    async loadProfile() {
        const state = document.getElementById('spState');
        const content = document.getElementById('spProfileContent');

        try {
            const unwrap = (r) => r?.data?.data ?? r?.data ?? r;
            const profile = unwrap(
                await window.API.staffMigration.onboarding()
            );

            this.state.profile = profile;
            const source = String(profile?.employment_source || 'existing_staff_manual');
            const sourceLabel = source.startsWith('new_staff_')
                ? 'new staff member'
                : 'existing staff member';

            const schoolAssignmentComplete = Boolean(
                profile?.department_id &&
                String(profile?.position || '').trim() &&
                String(profile?.employment_date || '').trim() &&
                String(profile?.contract_type || '').trim() &&
                Number(profile?.staff_type_id) > 0 &&
                Number(profile?.staff_category_id) > 0
            );
            if (!schoolAssignmentComplete) {
                state.className = 'alert alert-warning';
                state.textContent = `This ${sourceLabel} account is waiting for the school to finish its employment assignment. Department, position, employment date, contract and staff classification are assigned by the school. You do not need to enter them; contact the School Administrator to complete the staff record.`;
                content.classList.add('d-none');
                return;
            }

            if (profile?.email_valid === false) {
                state.className = 'alert alert-warning';
                state.textContent = `The school account email for this ${sourceLabel} is missing or invalid. It must be corrected by the School Administrator before profile completion and dashboard access.`;
                content.classList.add('d-none');
                return;
            }

            if (
                profile?.profile_completed &&
                profile?.communication_completed
            ) {
                state.className = 'alert alert-success';
                state.textContent =
                    'Profile already complete. Redirecting to dashboard\u2026';
                window.setTimeout(() => void this.redirectAfterCompletion(), 300);
                return;
            }

            this.renderHeader(profile);
            this.populateForm(profile);
            this.renderQualificationClaims(profile.qualification_claims || []);

            state.className = 'alert alert-warning';
            state.textContent = source.startsWith('new_staff_')
                ? 'Your new staff account is ready for personal profile completion. The school owns your role, employment assignment and payroll information; this form collects only your missing personal and contact details.'
                : 'Your existing staff record is ready for profile completion. The school owns your role, employment assignment and payroll information; this form collects only your missing personal and contact details.';
            content.classList.remove('d-none');
        } catch (error) {
            state.className = 'alert alert-danger';
            state.textContent = error.message || 'Unable to load profile.';
        }
    },

    renderHeader(p) {
        const header = document.getElementById('spProfileHeader');
        if (!header) return;

        const initials =
            ((p.first_name || '')[0] || '') +
            ((p.last_name || '')[0] || '');
        const badgeClass =
            p.status === 'active'
                ? 'bg-success'
                : p.status === 'on_leave'
                  ? 'bg-warning text-dark'
                  : 'bg-secondary';

        header.innerHTML = `
            <div class="col-auto">
                <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white fw-bold" style="width:80px;height:80px;font-size:2rem;">
                    ${
                        p.profile_pic_url
                            ? `<img src="${p.profile_pic_url}" class="rounded-circle w-100 h-100" style="object-fit:cover">`
                            : initials
                    }
                </div>
            </div>
            <div class="col">
                <h4 class="mb-1">${this._escH(p.first_name)} ${this._escH(p.last_name)}</h4>
                <p class="mb-1 text-muted">${this._escH(p.staff_no)} &middot; ${this._escH(p.position || 'Position not set')}${p.department_name ? ' &middot; ' + this._escH(p.department_name) : ''}</p>
                <div><span class="badge ${badgeClass}">${this._escH(p.status)}</span></div>
            </div>`;
    },

    populateForm(p) {
        const form = document.getElementById('spForm');
        if (!form) return;

        const fields = [
            'phone',
            'middle_name',
            'national_id_no',
            'bank_name',
            'bank_account',
            'mpesa_phone',
            'tsc_no',
            'date_of_birth',
            'gender',
            'address',
            'emergency_contact_name',
            'emergency_contact_phone',
            'emergency_contact_relationship',
        ];
        const requiredWhenMissing = new Set([
            'phone', 'date_of_birth', 'gender', 'address',
        ]);
        fields.forEach((k) => {
            const el = form.elements.namedItem(k);
            if (el && p[k] != null) {
                const value = k === 'gender' ? String(p[k]).toLowerCase() : p[k];
                el.value = value;
            }
            if (el && requiredWhenMissing.has(k)) {
                const invalid = (k === 'phone' && p.phone_valid === false)
                    || (k === 'gender' && p.gender_valid === false)
                    || (k === 'date_of_birth' && p.date_of_birth_valid === false);
                const missing = p[k] == null || String(p[k]).trim() === '' || invalid;
                el.required = missing;
                const marker = el.closest('.col-md-6, .col-md-4, .col-12')?.querySelector('[data-required-mark]');
                if (marker) marker.hidden = !missing;
            }
        });
        const accountEmail = document.getElementById('spAccountEmail');
        if (accountEmail) accountEmail.value = p.communication_email || '';
        const teachingSection = document.getElementById('spTeachingSection');
        const learningAreas = document.getElementById('spLearningAreas');
        const primaryArea = document.getElementById('spPrimaryLearningArea');
        const isTeacher = String(p.staff_type_name || '').toLowerCase().includes('teach');
        if (teachingSection) teachingSection.hidden = !isTeacher;
        if (learningAreas && isTeacher) {
            learningAreas.replaceChildren(...(p.learning_areas || []).map((area) => {
                const option = document.createElement('option');
                option.value = String(area.id);
                option.textContent = area.name;
                return option;
            }));
            const selected = new Set((p.requested_learning_area_ids || []).map(String));
            Array.from(learningAreas.options).forEach((option) => { option.selected = selected.has(option.value); });
            if (primaryArea) {
                const options = Array.from(learningAreas.options).filter((option) => option.selected).map((option) => {
                    const primaryOption = document.createElement('option');
                    primaryOption.value = option.value;
                    primaryOption.textContent = option.textContent;
                    return primaryOption;
                });
                primaryArea.replaceChildren(new Option('No primary area selected', ''), ...options);
                primaryArea.value = String(p.primary_learning_area_id || '');
                learningAreas.addEventListener('change', () => {
                    const current = primaryArea.value;
                    const selectedOptions = Array.from(learningAreas.selectedOptions).map((option) => {
                        const primaryOption = document.createElement('option');
                        primaryOption.value = option.value;
                        primaryOption.textContent = option.textContent;
                        return primaryOption;
                    });
                    primaryArea.replaceChildren(new Option('No primary area selected', ''), ...selectedOptions);
                    primaryArea.value = selectedOptions.some((option) => option.value === current) ? current : '';
                });
            }
        }
    },

    // ==================== SAVE ====================
    async saveProfile() {
        const form = document.getElementById('spForm');
        const state = document.getElementById('spState');
        if (!form || !state) return;

        state.className = 'alert alert-info';
        state.textContent = 'Saving\u2026';

        try {
            const data = Object.fromEntries(new FormData(form).entries());
            data.learning_area_ids = Array.from(document.getElementById('spLearningAreas')?.selectedOptions || []).map((option) => Number(option.value));
            const primaryAreaId = document.getElementById('spPrimaryLearningArea')?.value;
            data.primary_learning_area_id = primaryAreaId ? Number(primaryAreaId) : null;
            data.qualifications = Array.from(document.querySelectorAll('#spQualifications > .row[data-read-only="0"]')).map((row) => Object.fromEntries(Array.from(row.querySelectorAll('[data-q]')).map((el) => [el.dataset.q, el.value.trim()]))).filter((q) => q.title || q.institution);
            await window.API.staffMigration.completeProfile(data);
            state.className = 'alert alert-success';
            state.textContent = 'Profile completed. Redirecting\u2026';
            await this.redirectAfterCompletion();
        } catch (error) {
            state.className = 'alert alert-danger';
            state.textContent = error.message || 'Unable to save profile.';
        }
    },

    async redirectAfterCompletion() {
        // Login deliberately points incomplete staff at this page. Refresh the
        // full auth envelope after saving so the cached dashboard route is
        // recalculated from the account's real role before leaving onboarding.
        const refreshed = await window.AuthContext?.refreshToken?.();
        if (!refreshed) {
            const state = document.getElementById('spState');
            if (state) {
                state.className = 'alert alert-warning';
                state.textContent = 'Your profile is saved. Sign in again to open your staff dashboard.';
            }
            return;
        }

        const dashboard = window.AuthContext?.getDashboardInfo?.();
        const route = String(dashboard?.key || '').trim();
        const destination = route
            ? `${window.APP_BASE || ''}/home.php?route=${encodeURIComponent(route)}`
            : `${window.APP_BASE || ''}/home.php`;
        window.location.replace(destination);
    },

    // ==================== HELPERS ====================
    _escH(str) {
        const d = document.createElement('div');
        d.textContent = String(str ?? '');
        return d.innerHTML;
    },
};

window.staffProfileController = staffProfileController;

function initializeStaffProfileController() {
    void staffProfileController.init().catch((error) => {
        console.error(
            '[StaffProfileController] Page initialization failed:',
            error
        );
    });
}

if (window.__APP_BOOTED__) {
    initializeStaffProfileController();
} else {
    window.addEventListener(
        'kingsway:ready',
        initializeStaffProfileController,
        { once: true }
    );
}
