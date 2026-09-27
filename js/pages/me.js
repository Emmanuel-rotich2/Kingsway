const ProfileController = (() => {
    let userData = null;
    let profile = null;          // merged profile payload (staff or system)
    let domain = 'unknown';      // 'school' | 'system'
    let editing = false;

    // Schema-driven rendering. Each entry becomes a rendered field/card row only
    // when the source profile actually has a non-empty value for it — the page
    // never assumes a fixed shape, so a user without e.g. a TSC number (that
    // column no longer exists) or without bank details simply never sees them.
    const PERSONAL_FIELDS = [
        { key: 'first_name', label: 'First Name', type: 'text', editable: true },
        { key: 'middle_name', label: 'Middle Name', type: 'text', editable: true },
        { key: 'last_name', label: 'Last Name', type: 'text', editable: true },
        { key: 'email', label: 'Email', type: 'email', editable: true },
        { key: 'phone', label: 'Phone', type: 'tel', editable: true },
        { key: 'gender', label: 'Gender', type: 'select', options: ['male', 'female', 'other'], editable: true },
        { key: 'date_of_birth', label: 'Date of Birth', type: 'date', editable: true },
    ];

    const EMPLOYMENT_FIELDS = [
        { key: 'staff_no', label: 'Staff Number', type: 'text' },
        { key: 'position', label: 'Position / Job Title', type: 'text' },
        { key: 'department_name', label: 'Department', type: 'text' },
        { key: 'contract_type', label: 'Contract Type', type: 'text' },
        { key: 'employment_date', label: 'Employment Date', type: 'text' },
        { key: 'supervisor_name', label: 'Supervisor', type: 'text' },
    ];

    const FINANCIAL_FIELDS = [
        { key: 'bank_name', label: 'Bank Name', type: 'text' },
        { key: 'bank_account', label: 'Bank Account', type: 'text', mask: true },
        { key: 'kra_pin', label: 'KRA PIN', type: 'text' },
        { key: 'nssf_no', label: 'NSSF No.', type: 'text' },
        { key: 'nhif_no', label: 'NHIF No.', type: 'text' },
    ];

    async function init() {
        await window.AuthContext?.ready();
        if (typeof AuthContext === 'undefined' || !AuthContext.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }
        userData = AuthContext.getUser() || {};
        await bindEvents();
        await loadProfile();
        renderProfile();
        renderRoles();
        loadLoginHistory();
        loadCurrentAcademicYear();
    }

    async function loadProfile() {
        try {
            const resp = await window.API.staff.getProfile();
            profile = resp && typeof resp === 'object' ? resp : {};
        } catch (e) {
            profile = null;
            console.warn('Profile load failed:', e);
        }
        if (!profile) {
            // No staff profile and no account profile → fall back to auth payload
            profile = {
                _domain: 'system',
                first_name: userData.first_name || '',
                middle_name: userData.middle_name || '',
                last_name: userData.last_name || '',
                email: userData.email || '',
                phone: userData.phone || '',
                username: userData.username || '',
                last_login: userData.last_login || null,
                roles: userData.roles || [],
            };
        }
        domain = profile._domain === 'system' ? 'system' : 'school';
    }

    function hasValue(v) {
        return v !== null && v !== undefined && String(v).trim() !== '';
    }

    function displayValue(v, opts = {}) {
        if (!hasValue(v)) return opts.empty ?? '—';
        if (opts.mask) return '••••' + String(v).slice(-4);
        if (opts.bool) return v ? 'Yes' : 'No';
        return v;
    }

    // ── Rendering ──────────────────────────────────────────────────

    function renderProfile() {
        const s = profile || {};
        const u = userData || {};

        const name = [s.first_name, s.middle_name, s.last_name]
            .filter(n => hasValue(n)).join(' ')
            .trim() || u.first_name || 'User';

        const initials = name.split(' ').filter(Boolean).map(n => n.charAt(0))
            .join('').toUpperCase().substring(0, 2);

        const completionKeys = ['first_name', 'last_name', 'email', 'phone', 'gender', 'date_of_birth'];
        const completedFields = completionKeys.filter(key => hasValue(s[key] || u[key])).length;
        const completion = Math.round((completedFields / completionKeys.length) * 100);
        const completionBar = document.getElementById('profileCompletionBar');
        const completionLabel = document.getElementById('profileCompletionLabel');
        if (completionBar) completionBar.style.width = `${completion}%`;
        if (completionBar) completionBar.setAttribute('aria-valuenow', String(completion));
        if (completionLabel) completionLabel.textContent = `${completion}%`;

        const avatar = document.getElementById('profileAvatar');
        const photo = window.KingswayFileLifecycle?.resolveUrl?.(s.photo_url) || window.KingswayFileLifecycle?.avatarUrl?.();
        avatar.innerHTML = `<img src="${escapeHtml(photo)}" alt="profile" style="width:100%;height:100%;object-fit:cover" onerror="this.onerror=null;this.src=KingswayFileLifecycle.avatarUrl()">`;
        const accountAvatar = document.getElementById('settings-avatar');
        if (accountAvatar) {
            accountAvatar.innerHTML = `<img src="${escapeHtml(photo)}" alt="${escapeHtml(name)}" onerror="this.onerror=null;this.src=KingswayFileLifecycle.avatarUrl()">`;
        }

        document.getElementById('profileName').textContent = name;
        document.getElementById('profileEmail').textContent = s.email || u.email || '—';
        document.getElementById('profilePhone').textContent = s.phone || u.phone || '—';
        document.getElementById('profileLastLogin').textContent =
            (s.last_login || u.last_login) ? new Date(s.last_login || u.last_login).toLocaleDateString() : '—';

        const roles = u.roles || [];
        const mainRole = roles.length > 0 ? (typeof roles[0] === 'string' ? roles[0] : (roles[0].name || 'User')) : 'User';
        document.getElementById('profileRole').textContent = mainRole;

        // Domain badge
        const badge = document.getElementById('profileDomainBadge');
        if (domain === 'system') {
            badge.innerHTML = '<span class="badge bg-secondary mb-2">System / Platform User</span>';
        } else if (domain === 'school') {
            badge.innerHTML = '<span class="badge bg-info mb-2">School Staff</span>';
        } else {
            badge.innerHTML = '';
        }

        // School-only summary rows
        document.getElementById('profileEmployeeRow').classList.toggle('d-none', domain !== 'school');
        document.getElementById('profileDepartmentRow').classList.toggle('d-none', domain !== 'school');
        document.getElementById('profileTenureRow').classList.toggle('d-none', domain !== 'school');
        document.getElementById('profileEmployeeId').textContent = s.staff_no || '—';
        document.getElementById('profileDepartment').textContent = s.department_name || '—';
        document.getElementById('profileTenure').textContent = formatTenure(s.employment_date);

        // Greeting
        const greetEl = document.getElementById('profileGreeting');
        if (greetEl) {
            const hour = new Date().getHours();
            let greeting = 'Good evening';
            if (hour < 12) greeting = 'Good morning';
            else if (hour < 17) greeting = 'Good afternoon';
            greetEl.textContent = `${greeting}, ${name.split(' ')[0]}`;
        }

        renderPersonal();
        renderEmploymentAndFinancial();
        renderKpis();
    }

    function renderPersonal() {
        const container = document.getElementById('personalInfoFields');
        if (!container) return;
        const rows = PERSONAL_FIELDS.map(f => fieldControl(f, editing));
        container.innerHTML = rows.length
            ? '<div class="row g-3">' + rows.join('') + '</div>'
            : '<p class="text-muted small mb-0">No personal details on file.</p>';
    }

    function fieldControl(f, edit) {
        const value = profile[f.key];
        const label = `<label class="form-label small fw-semibold">${escapeHtml(f.label)}</label>`;
        let control;

        if (edit && f.editable) {
            if (f.type === 'select') {
                const opts = (f.options || []).map(o =>
                    `<option value="${escapeHtml(o)}" ${String(value) === o ? 'selected' : ''}>${escapeHtml(o)}</option>`
                ).join('');
                control = `<select class="form-select" data-field="${f.key}">${opts}</select>`;
            } else if (f.type === 'date') {
                control = `<input type="date" class="form-control" data-field="${f.key}" value="${escapeHtml(value || '')}">`;
            } else {
                control = `<input type="${f.type}" class="form-control" data-field="${f.key}" value="${escapeHtml(value || '')}">`;
            }
        } else {
            control = `<input type="text" class="form-control" value="${escapeHtml(displayValue(value))}" readonly>`;
        }
        return `<div class="col-md-6">${label}${control}</div>`;
    }

    function renderEmploymentAndFinancial() {
        const employmentCard = document.getElementById('employmentCard');
        const financialCard = document.getElementById('financialCard');
        const systemCard = document.getElementById('systemProfessionalCard');

        const isSchool = domain === 'school';

        if (isSchool) {
            systemCard?.classList.add('d-none');
            const empFields = EMPLOYMENT_FIELDS;
            document.getElementById('employmentFields').innerHTML =
                '<div class="row g-3">' + empFields.map(f =>
                    `<div class="col-md-6"><label class="form-label small fw-semibold">${escapeHtml(f.label)}</label>` +
                    `<input type="text" class="form-control" value="${escapeHtml(displayValue(profile[f.key]))}" readonly></div>`
                ).join('') + '</div>';
            employmentCard.classList.remove('d-none');

            const finFields = FINANCIAL_FIELDS;
            document.getElementById('financialFields').innerHTML =
                '<div class="row g-3">' + finFields.map(f =>
                    `<div class="col-md-6"><label class="form-label small fw-semibold">${escapeHtml(f.label)}</label>` +
                    `<input type="text" class="form-control" value="${escapeHtml(displayValue(profile[f.key], f))}" readonly></div>`
                ).join('') + '</div>';
            financialCard.classList.remove('d-none');
        } else {
            employmentCard.classList.add('d-none');
            financialCard.classList.add('d-none');
            if (systemCard) {
                const primaryRole = Array.isArray(profile.roles) && profile.roles.length
                    ? (profile.roles[0].name || profile.roles[0].role_name || profile.roles[0])
                    : (userData.role_name || 'System user');
                systemCard.classList.remove('d-none');
                document.getElementById('systemProfessionalUsername').value = displayValue(profile.username || userData.username);
                document.getElementById('systemProfessionalDomain').value = 'System / Platform';
                document.getElementById('systemProfessionalRole').value = displayValue(primaryRole);
            }
        }
    }

    async function renderKpis() {
        const section = document.getElementById('kpiSection');
        const container = document.getElementById('kpiSummary');
        if (domain !== 'school') { if (section) section.classList.add('d-none'); return; }
        if (section) section.classList.remove('d-none');
        const staffId = profile?.id;
        if (!staffId) { if (container) container.innerHTML = ''; return; }
        try {
            const resp = await window.API.staff.getAcademicKPISummary(staffId);
            const kpis = resp && typeof resp === 'object' ? resp : {};
            const cards = Object.entries(kpis).filter(([, v]) => v !== null && v !== undefined).slice(0, 6);
            if (!cards.length) { container.innerHTML = '<p class="text-muted small mb-0">No KPI data available</p>'; return; }
            container.innerHTML = '<div class="row g-2">' + cards.map(([key, value]) => `
                <div class="col-md-4 col-6">
                    <div class="border rounded p-2 text-center">
                        <strong class="d-block fs-5">${escapeHtml(value)}</strong>
                        <small class="text-muted">${escapeHtml(key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()))}</small>
                    </div>
                </div>
            `).join('') + '</div>';
        } catch (e) {
            container.innerHTML = '';
        }
    }

    function renderRoles() {
        const roles = userData?.roles || [];
        const rolesHtml = roles.map(r => {
            const n = typeof r === 'string' ? r : r.name;
            return `<span class="badge bg-primary me-2 mb-2 fs-6">${escapeHtml(n)}</span>`;
        }).join('') || '<span class="text-muted">No roles assigned</span>';
        document.getElementById('rolesList').innerHTML = rolesHtml;
    }

    // ── Editing ───────────────────────────────────────────────────

    function setEditing(on) {
        editing = on;
        const editableCount = PERSONAL_FIELDS.filter(f => f.editable).length;
        document.getElementById('profileEditBtn').classList.toggle('d-none', on);
        document.getElementById('profileCancelBtn').classList.toggle('d-none', !on);
        document.getElementById('profileSaveBtn').classList.toggle('d-none', !on);
        if (on && editableCount === 0) {
            window.showNotification?.('There are no editable personal fields for this account.', 'warning');
            setEditing(false);
            return;
        }
        renderPersonal();
        if (!on) renderProfile(); // reseat read-only values if cancelled
    }

    async function saveEdits() {
        const payload = {};
        PERSONAL_FIELDS.filter(f => f.editable).forEach(f => {
            const input = document.querySelector(`[data-field="${f.key}"]`);
            if (input) payload[f.key] = input.value;
        });
        try {
            const resp = await window.API.users.updateSelfProfile(payload);
            if (resp?.success === false) {
                window.showNotification?.(resp.error || 'Update failed', 'error');
                return;
            }
            window.showNotification?.('Profile updated successfully.', 'success');
            setEditing(false);
            await refreshAuth();
            await loadProfile();
            renderProfile();
        } catch (e) {
            window.showNotification?.(e.message || 'Update failed', 'error');
        }
    }

    async function refreshAuth() {
        try { await window.AuthContext?.refresh?.(); } catch (e) { /* non-fatal */ }
    }

    async function bindEvents() {
        const editBtn = document.getElementById('profileEditBtn');
        const cancelBtn = document.getElementById('profileCancelBtn');
        const saveBtn = document.getElementById('profileSaveBtn');
        if (editBtn) editBtn.addEventListener('click', () => setEditing(true));
        if (cancelBtn) cancelBtn.addEventListener('click', () => setEditing(false));
        if (saveBtn) saveBtn.addEventListener('click', () => saveEdits());
    }

    // ── Aux loaders ────────────────────────────────────────────────

    function formatTenure(employmentDate) {
        if (!employmentDate) return '—';
        const start = new Date(employmentDate);
        const now = new Date();
        let years = now.getFullYear() - start.getFullYear();
        let months = now.getMonth() - start.getMonth();
        let days = now.getDate() - start.getDate();
        if (days < 0) { months--; days += new Date(now.getFullYear(), now.getMonth(), 0).getDate(); }
        if (months < 0) { years--; months += 12; }
        if (years < 0) return '—';
        const parts = [];
        if (years > 0) parts.push(`${years} year${years > 1 ? 's' : ''}`);
        if (months > 0) parts.push(`${months} month${months > 1 ? 's' : ''}`);
        parts.push(`${days} day${days > 1 ? 's' : ''}`);
        return parts.join(', ') || '—';
    }

    async function loadCurrentAcademicYear() {
        const el = document.getElementById('currentAcademicYear');
        if (!el) return;
        try {
            const resp = await window.API.academic.getCurrentAcademicYear?.();
            const year = resp?.year_code || resp?.name || '';
            el.textContent = year || '—';
        } catch (e) {
            el.textContent = '—';
        }
    }

    async function loadLoginHistory() {
        try {
            const uid = userData?.id;
            if (!uid) throw new Error('no user id');
            const response = await window.API.apiCall(`/users/${uid}/login-history`, 'GET');
            const history = response?.data || response || [];
            renderLoginHistory(Array.isArray(history) ? history : []);
        } catch (e) {
            document.querySelector('#loginHistoryTable tbody')?.replaceChildren();
            const tbody = document.querySelector('#loginHistoryTable tbody');
            if (tbody) tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Unable to load login history</td></tr>';
        }
    }

    function renderLoginHistory(history) {
        const tbody = document.querySelector('#loginHistoryTable tbody');
        if (!tbody) return;
        if (!history.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No login history available</td></tr>';
            return;
        }
        tbody.innerHTML = history.slice(0, 20).map(h => `
            <tr>
                <td>${h.created_at ? new Date(h.created_at).toLocaleString() : '--'}</td>
                <td>${escapeHtml(h.ip_address || '--')}</td>
                <td>${escapeHtml(h.device || '--')}</td>
                <td>${escapeHtml(h.browser || '--')}</td>
                <td><span class="badge bg-${h.status === 'success' ? 'success' : 'danger'}">${escapeHtml(h.status || 'unknown')}</span></td>
            </tr>
        `).join('');
    }

    function escapeHtml(v) {
        if (v === null || v === undefined) return '';
        return String(v)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    return { init, refresh: init };
})();

document.addEventListener('DOMContentLoaded', () => ProfileController.init());

window.ProfileController = ProfileController;
