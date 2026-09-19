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

            if (
                profile?.profile_completed &&
                profile?.communication_completed
            ) {
                state.className = 'alert alert-success';
                state.textContent =
                    'Profile already complete. Redirecting to dashboard\u2026';
                setTimeout(() => {
                    location.href = 'home.php';
                }, 800);
                return;
            }

            this.renderHeader(profile);
            this.renderReadOnlySections(profile);
            this.populateForm(profile);
            this.renderQualificationClaims(profile.qualification_claims || []);

            state.className = 'alert alert-warning';
            state.textContent =
                'Review your details below. Fields marked with * are required to complete your profile.';
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
                <p class="mb-1 text-muted">${this._escH(p.staff_no)} &middot; ${this._escH(p.position)}${p.department_name ? ' &middot; ' + this._escH(p.department_name) : ''}</p>
                <div><span class="badge ${badgeClass}">${this._escH(p.status)}</span></div>
            </div>`;
    },

    renderReadOnlySections(p) {
        const empInfo = document.getElementById('spEmploymentInfo');
        const payInfo = document.getElementById('spPayrollInfo');
        const schedInfo = document.getElementById('spScheduleInfo');

        const card = (label, value) => {
            const v =
                value != null && value !== ''
                    ? this._escH(String(value))
                    : '\u2014';
            return `<div class="col-md-4 mb-2"><div class="p-2 bg-light rounded"><small class="text-muted d-block">${label}</small><strong>${v}</strong></div></div>`;
        };

        if (empInfo) {
            empInfo.innerHTML =
                card('Staff Type', p.staff_type_name) +
                card('Staff Category', p.staff_category_name) +
                card('Department', p.department_name) +
                card('Position', p.position) +
                card('Supervisor', p.supervisor_name) +
                card('Employment Date', p.employment_date) +
                card('Contract Type', p.contract_type) +
                card('TSC No', p.tsc_no);
        }

        if (payInfo) {
            payInfo.innerHTML =
                card('Salary (KES)',
                    p.salary != null
                        ? Number(p.salary).toLocaleString()
                        : null) +
                card('Bank Name', p.bank_name) +
                card('Bank Account', p.bank_account) +
                card('KRA PIN', p.kra_pin) +
                card('NSSF No', p.nssf_no) +
                card('NHIF No', p.nhif_no);
        }

        if (schedInfo) {
            schedInfo.innerHTML =
                card('Work Start', p.work_start_time) +
                card('Work End', p.work_end_time) +
                card('Late Threshold (min)', p.late_threshold_minutes);
        }
    },

    populateForm(p) {
        const form = document.getElementById('spForm');
        if (!form) return;

        const fields = [
            'phone',
            'communication_email',
            'communication_phone',
            'date_of_birth',
            'gender',
            'marital_status',
            'address',
            'emergency_contact_name',
            'emergency_contact_phone',
        ];
        fields.forEach((k) => {
            const el = form.elements.namedItem(k);
            if (el && p[k] != null) {
                el.value = p[k];
            }
        });
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
            data.qualifications = Array.from(document.querySelectorAll('#spQualifications > .row[data-read-only="0"]')).map((row) => Object.fromEntries(Array.from(row.querySelectorAll('[data-q]')).map((el) => [el.dataset.q, el.value.trim()]))).filter((q) => q.title || q.institution);
            await window.API.staffMigration.completeProfile(data);
            state.className = 'alert alert-success';
            state.textContent = 'Profile completed. Redirecting\u2026';
            setTimeout(() => {
                location.href = 'home.php';
            }, 800);
        } catch (error) {
            state.className = 'alert alert-danger';
            state.textContent = error.message || 'Unable to save profile.';
        }
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
