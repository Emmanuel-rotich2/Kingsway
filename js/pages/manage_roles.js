/**
 * Manage Roles Page Controller
 * Scope-aware: system admin sees all + can create permissions; school admin manages school roles only.
 */

(function () {
  'use strict';

  const ManageRolesController = {
    roles: [],
    filtered: [],
    currentPage: 1,
    perPage: 15,
    modal: null,
    isSystemAdmin: false,
    isSchoolAdmin: false,

    init: function () {
      if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
        window.location.href = (window.APP_BASE || '') + '/index.php';
        return;
      }

      const user = typeof AuthContext !== 'undefined' ? AuthContext.getUser() : null;
      const roles = (user?.roles || []).map(r => (typeof r === 'string' ? r : r?.name || '').toLowerCase());
      this.isSystemAdmin = roles.includes('system administrator');
      this.isSchoolAdmin = !this.isSystemAdmin && roles.includes('school administrator');

      this.applyUIScope();
      this.bindEvents();
      this.loadRoles();
    },

    /** Show/hide controls based on who is logged in. */
    applyUIScope: function () {
      // System-admin-only elements
      document.querySelectorAll('[data-system-only]').forEach(el => {
        el.style.display = this.isSystemAdmin ? '' : 'none';
      });

      // Scope selector in modal — only system admin can pick scope/is_system
      const scopeRow = document.getElementById('scopeRow');
      if (scopeRow) scopeRow.style.display = this.isSystemAdmin ? '' : 'none';

      // System module permissions — school admin cannot assign these
      const sysModule = document.getElementById('systemPermissionsModule');
      if (sysModule) sysModule.style.display = this.isSystemAdmin ? '' : 'none';
    },

    bindEvents: function () {
      const self = this;

      const search = document.getElementById('searchRoles');
      if (search) search.addEventListener('input', () => self.applyFilters());

      const statusFilter = document.getElementById('statusFilter');
      if (statusFilter) statusFilter.addEventListener('change', () => self.applyFilters());

      const typeFilter = document.getElementById('typeFilter');
      if (typeFilter) typeFilter.addEventListener('change', () => self.applyFilters());

      const addBtn = document.getElementById('addRoleBtn');
      if (addBtn) addBtn.addEventListener('click', () => self.openModal());

      const saveBtn = document.getElementById('saveRoleBtn');
      if (saveBtn) saveBtn.addEventListener('click', () => self.saveRole());

      const exportBtn = document.getElementById('exportBtn');
      if (exportBtn) exportBtn.addEventListener('click', () => self.exportCSV());

      document.querySelectorAll('.module-check').forEach(function (chk) {
        chk.addEventListener('change', function () {
          const mod = this.dataset.module;
          document.querySelectorAll(`.permission-check[data-module="${mod}"]`)
            .forEach(p => { p.checked = this.checked; });
        });
      });
    },

    loadRoles: async function () {
      try {
        const tbody = document.querySelector('#rolesTable tbody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="8" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div> Loading...</td></tr>';

        const res = await API.system.getRoles();
        this.roles = Array.isArray(res) ? res : (res?.roles || res?.data || []);
        this.applyFilters();
        this.updateStats();
      } catch (err) {
        console.error('Failed to load roles:', err);
        const tbody = document.querySelector('#rolesTable tbody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-3">Failed to load roles.</td></tr>';
      }
    },

    applyFilters: function () {
      const q = (document.getElementById('searchRoles')?.value || '').toLowerCase();
      const status = document.getElementById('statusFilter')?.value || '';
      const type = document.getElementById('typeFilter')?.value || '';

      this.filtered = this.roles.filter(function (r) {
        const name = (r.name || r.role_name || '').toLowerCase();
        const desc = (r.description || '').toLowerCase();
        const rStatus = (r.status || (r.is_active ? 'active' : 'inactive'));
        const rType = r.is_system ? 'system' : 'custom';

        if (q && !name.includes(q) && !desc.includes(q)) return false;
        if (status && rStatus !== status) return false;
        if (type && rType !== type) return false;
        return true;
      });

      this.currentPage = 1;
      this.renderTable();
      this.renderPagination();
    },

    updateStats: function () {
      const active = this.roles.filter(r => r.is_active || r.status === 'active').length;
      const custom = this.roles.filter(r => !r.is_system).length;
      const totalUsers = this.roles.reduce((s, r) => s + (parseInt(r.user_count) || 0), 0);

      const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
      set('totalRoles', this.roles.length);
      set('activeRoles', active);
      set('customRoles', custom);
      set('totalUsers', totalUsers);
    },

    renderTable: function () {
      const tbody = document.querySelector('#rolesTable tbody');
      if (!tbody) return;

      const start = (this.currentPage - 1) * this.perPage;
      const page = this.filtered.slice(start, start + this.perPage);

      if (page.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No roles found.</td></tr>';
        return;
      }

      const self = this;
      tbody.innerHTML = page.map(r => {
        const name = self.esc(r.name || r.role_name || '—');
        const desc = self.esc(r.description || '—');
        const isSystem = !!r.is_system;
        const scope = r.scope || 'school';
        const typeBadge = isSystem
          ? '<span class="badge bg-secondary">System</span>'
          : (scope === 'system'
              ? '<span class="badge bg-dark">Sys-Custom</span>'
              : '<span class="badge bg-info">School</span>');
        const scopeBadge = self.isSystemAdmin
          ? `<span class="badge ${scope === 'system' ? 'bg-danger' : 'bg-success'} ms-1">${scope}</span>`
          : '';
        const users = r.user_count ?? '—';
        const perms = r.permission_count ?? '—';
        const isActive = r.is_active || r.status === 'active';
        const statusBadge = isActive
          ? '<span class="badge bg-success">Active</span>'
          : '<span class="badge bg-secondary">Inactive</span>';

        // School admin: cannot edit/delete system or system-scope roles
        const schoolAdminRestricted = self.isSchoolAdmin && (isSystem || scope === 'system');
        const canEdit = !isSystem && !schoolAdminRestricted;
        const canToggle = !isSystem && !schoolAdminRestricted;

        return `<tr>
          <td><strong>${name}</strong>${scopeBadge}</td>
          <td class="text-muted small">${desc}</td>
          <td>${typeBadge}</td>
          <td>${users}</td>
          <td>${perms}</td>
          <td>${statusBadge}</td>
          <td>
            <div class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" ${isActive ? 'checked' : ''}
                onchange="ManageRolesController.toggleStatus(${r.id}, this.checked)"
                ${canToggle ? '' : 'disabled'}>
            </div>
          </td>
          <td>
            ${canEdit ? `
              <button class="btn btn-sm btn-outline-primary me-1" onclick="ManageRolesController.editRole(${r.id})" title="Edit">
                <i class="fas fa-edit"></i>
              </button>
              <button class="btn btn-sm btn-outline-danger" onclick="ManageRolesController.deleteRole(${r.id}, '${name}')" title="Delete">
                <i class="fas fa-trash"></i>
              </button>` : '<span class="text-muted small">Locked</span>'}
          </td>
        </tr>`;
      }).join('');
    },

    renderPagination: function () {
      const ul = document.getElementById('pagination');
      if (!ul) return;

      const total = Math.ceil(this.filtered.length / this.perPage);
      if (total <= 1) { ul.innerHTML = ''; return; }

      let html = '';
      const p = this.currentPage;
      html += `<li class="page-item ${p === 1 ? 'disabled' : ''}"><a class="page-link" href="#" onclick="ManageRolesController.goPage(${p - 1});return false;">‹</a></li>`;
      for (let i = 1; i <= total; i++) {
        html += `<li class="page-item ${i === p ? 'active' : ''}"><a class="page-link" href="#" onclick="ManageRolesController.goPage(${i});return false;">${i}</a></li>`;
      }
      html += `<li class="page-item ${p === total ? 'disabled' : ''}"><a class="page-link" href="#" onclick="ManageRolesController.goPage(${p + 1});return false;">›</a></li>`;
      ul.innerHTML = html;
    },

    goPage: function (n) {
      this.currentPage = n;
      this.renderTable();
      this.renderPagination();
    },

    openModal: function (role) {
      document.getElementById('roleModalTitle').textContent = role ? 'Edit Role' : 'Add New Role';
      document.getElementById('roleId').value = role?.id || '';
      document.getElementById('roleName').value = role?.name || role?.role_name || '';
      document.getElementById('status').value = (role?.is_active || role?.status === 'active') ? 'active' : 'inactive';
      document.getElementById('description').value = role?.description || '';

      // Scope selector (system admin only)
      const scopeSel = document.getElementById('roleScope');
      if (scopeSel) {
        scopeSel.value = role?.scope || 'school';
      }
      const isSysSel = document.getElementById('roleIsSystem');
      if (isSysSel) {
        isSysSel.checked = !!role?.is_system;
      }

      document.querySelectorAll('.permission-check, .module-check').forEach(c => { c.checked = false; });

      if (role?.permissions && Array.isArray(role.permissions)) {
        role.permissions.forEach(p => {
          const id = typeof p === 'string' ? p : (p.code || p.name || '');
          const el = document.getElementById(id);
          if (el) el.checked = true;
        });
      }

      if (!this.modal) {
        this.modal = new bootstrap.Modal(document.getElementById('roleModal'));
      }
      this.modal.show();
    },

    editRole: async function (id) {
      try {
        const role = this.roles.find(r => r.id === id);
        if (!role) return;
        try {
          const full = await API.system.getRole(id);
          this.openModal(full || role);
        } catch (e) {
          this.openModal(role);
        }
      } catch (err) {
        console.error('Error opening edit:', err);
      }
    },

    saveRole: async function () {
      const id = document.getElementById('roleId').value;
      const name = document.getElementById('roleName').value.trim();
      if (!name) {
        alert('Role name is required.');
        return;
      }

      const permissions = Array.from(document.querySelectorAll('.permission-check:checked'))
        .map(c => c.id);

      const data = {
        name,
        description: document.getElementById('description').value.trim(),
        is_active: document.getElementById('status').value === 'active',
        permissions,
      };

      // System admin can set scope
      if (this.isSystemAdmin) {
        const scopeSel = document.getElementById('roleScope');
        if (scopeSel) data.scope = scopeSel.value;
        const isSysSel = document.getElementById('roleIsSystem');
        if (isSysSel) data.is_system = isSysSel.checked ? 1 : 0;
      }

      const saveBtn = document.getElementById('saveRoleBtn');
      if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Saving...'; }

      try {
        if (id) {
          await API.system.updateRole(id, data);
          this.showToast('Role updated successfully.');
        } else {
          await API.system.createRole(data);
          this.showToast('Role created successfully.');
        }

        if (this.modal) this.modal.hide();
        await this.loadRoles();
      } catch (err) {
        console.error('Save failed:', err);
        this.showToast('Failed to save role: ' + (err.message || 'Unknown error'), 'danger');
      } finally {
        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save Role'; }
      }
    },

    deleteRole: async function (id, name) {
      if (!confirm(`Delete role "${name}"? This cannot be undone.`)) return;
      try {
        await API.system.deleteRole(id);
        this.showToast('Role deleted.');
        await this.loadRoles();
      } catch (err) {
        this.showToast('Failed to delete: ' + (err.message || 'Error'), 'danger');
      }
    },

    toggleStatus: async function (id, isActive) {
      try {
        await API.system.toggleRoleStatus(id, isActive);
        const role = this.roles.find(r => r.id === id);
        if (role) role.is_active = isActive;
        this.updateStats();
      } catch (err) {
        this.showToast('Failed to update status.', 'danger');
        await this.loadRoles();
      }
    },

    exportCSV: function () {
      const rows = [['Name', 'Description', 'Scope', 'Type', 'Users', 'Status']];
      this.filtered.forEach(r => {
        rows.push([
          r.name || r.role_name || '',
          r.description || '',
          r.scope || 'school',
          r.is_system ? 'System' : 'Custom',
          r.user_count ?? '',
          (r.is_active || r.status === 'active') ? 'Active' : 'Inactive',
        ]);
      });
      const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
      const blob = new Blob([csv], { type: 'text/csv' });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'roles_export.csv';
      a.click();
    },

    esc: function (s) {
      return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    },

    showToast: function (msg, type = 'success') {
      if (typeof showNotification === 'function') {
        showNotification(msg, type === 'success' ? 'success' : 'error');
        return;
      }
      const el = document.createElement('div');
      el.className = `alert alert-${type} alert-dismissible position-fixed top-0 end-0 m-3`;
      el.style.zIndex = '9999';
      el.innerHTML = this.esc(msg) + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
      document.body.appendChild(el);
      setTimeout(() => el.remove(), 4000);
    },
  };

  window.ManageRolesController = ManageRolesController;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => ManageRolesController.init());
  } else {
    ManageRolesController.init();
  }

})();
