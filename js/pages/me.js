/**
 * Profile Page Controller
 * Manages user profile display, password change, and login history
 */
const ProfileController = (() => {
    let userData = null;

    async function init() {
        if (typeof AuthContext === 'undefined' || !AuthContext.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }
        userData = AuthContext.getUser();
        renderProfile();
        renderRoles();
        loadLoginHistory();
    }

    function renderProfile() {
        if (!userData) return;

        const name = `${userData.first_name || ''} ${userData.last_name || ''}`.trim() || userData.username || 'User';
        const initials = name.split(' ').map(n => n.charAt(0)).join('').toUpperCase().substring(0, 2);

        document.getElementById('profileAvatar').innerHTML = initials;
        document.getElementById('profileName').textContent = name;
        document.getElementById('profileEmail').textContent = userData.email || '--';

        const roles = userData.roles || [];
        const mainRole = roles.length > 0 ? (typeof roles[0] === 'string' ? roles[0] : roles[0].name) : 'User';
        document.getElementById('profileRole').textContent = mainRole;

        document.getElementById('profileEmployeeId').textContent = userData.employee_id || userData.id || '--';
        document.getElementById('profilePhone').textContent = userData.phone || '--';
        document.getElementById('profileDepartment').textContent = userData.department || '--';
        document.getElementById('profileLastLogin').textContent = userData.last_login ? new Date(userData.last_login).toLocaleDateString() : '--';

        // Populate form
        document.getElementById('firstName').value = userData.first_name || '';
        document.getElementById('lastName').value = userData.last_name || '';
        document.getElementById('email').value = userData.email || '';
        document.getElementById('phone').value = userData.phone || '';
        document.getElementById('gender').value = userData.gender || '';
        document.getElementById('dob').value = userData.date_of_birth || '';
        document.getElementById('address').value = userData.address || '';
    }

    function renderRoles() {
        const roles = userData?.roles || [];
        const rolesHtml = roles.map(r => {
            const name = typeof r === 'string' ? r : r.name;
            return `<span class="badge bg-primary me-2 mb-2 fs-6">${name}</span>`;
        }).join('') || '<span class="text-muted">No roles assigned</span>';
        document.getElementById('rolesList').innerHTML = rolesHtml;

        const permissions = AuthContext.getPermissions ? AuthContext.getPermissions() : [];
        const grouped = {};
        permissions.forEach(p => {
            const parts = p.split('_');
            const entity = parts.slice(0, -1).join('_') || p;
            if (!grouped[entity]) grouped[entity] = [];
            grouped[entity].push(parts[parts.length - 1]);
        });

        let permHtml = '<div class="row g-2">';
        const entities = Object.keys(grouped).sort();
        if (entities.length === 0) {
            permHtml += '<div class="col-12"><span class="text-muted">No permissions data available</span></div>';
        } else {
            entities.slice(0, 20).forEach(entity => {
                permHtml += `<div class="col-md-4"><div class="border rounded p-2"><strong class="text-capitalize">${entity.replace(/_/g, ' ')}</strong><br><small class="text-muted">${grouped[entity].join(', ')}</small></div></div>`;
            });
            if (entities.length > 20) {
                permHtml += `<div class="col-12"><span class="text-muted">...and ${entities.length - 20} more entities</span></div>`;
            }
        }
        permHtml += '</div>';
        document.getElementById('permissionsSummary').innerHTML = permHtml;
    }

    async function loadLoginHistory() {
        try {
            const response = await window.API.apiCall(`/users/${userData.id}/login-history`, 'GET');
            const history = response?.data || response || [];
            renderLoginHistory(Array.isArray(history) ? history : []);
        } catch (e) {
            document.querySelector('#loginHistoryTable tbody').innerHTML =
                '<tr><td colspan="5" class="text-center text-muted">Unable to load login history</td></tr>';
        }
    }

    function renderLoginHistory(history) {
        const tbody = document.querySelector('#loginHistoryTable tbody');
        if (!history.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No login history available</td></tr>';
            return;
        }
        tbody.innerHTML = history.slice(0, 20).map(h => `
            <tr>
                <td>${h.created_at ? new Date(h.created_at).toLocaleString() : '--'}</td>
                <td>${h.ip_address || '--'}</td>
                <td>${h.device || '--'}</td>
                <td>${h.browser || '--'}</td>
                <td><span class="badge bg-${h.status === 'success' ? 'success' : 'danger'}">${h.status || 'unknown'}</span></td>
            </tr>
        `).join('');
    }

    async function changePassword() {
        const current = document.getElementById('currentPassword').value;
        const newPass = document.getElementById('newPassword').value;
        const confirm = document.getElementById('confirmPassword').value;

        if (!current || !newPass || !confirm) {
            showNotification('Please fill in all password fields', 'warning');
            return;
        }
        if (newPass.length < 8) {
            showNotification('New password must be at least 8 characters', 'warning');
            return;
        }
        if (newPass !== confirm) {
            showNotification('New passwords do not match', 'warning');
            return;
        }

        try {
            await window.API.apiCall(`/users/${userData.id}/change-password`, 'POST', {
                current_password: current,
                new_password: newPass
            });
            showNotification('Password updated successfully', 'success');
            document.getElementById('passwordForm').reset();
        } catch (e) {
            showNotification(e.message || 'Failed to update password', 'danger');
        }
    }

    function showNotification(message, type) {
        const modal = document.getElementById('notificationModal');
        if (modal) {
            const msgEl = modal.querySelector('.notification-message');
            const content = modal.querySelector('.modal-content');
            if (msgEl) msgEl.textContent = message;
            if (content) content.className = `modal-content notification-${type || 'info'}`;
            const bsModal = bootstrap.Modal.getOrCreateInstance(modal);
            bsModal.show();
            setTimeout(() => bsModal.hide(), 3000);
        }
    }

    return {
        init,
        changePassword,
        refresh: init
    };
})();

document.addEventListener('DOMContentLoaded', () => ProfileController.init());
