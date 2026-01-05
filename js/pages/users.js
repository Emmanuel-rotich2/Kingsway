/**
 * Manage Users Controller
 * Handles user management, roles, and permissions
 * Integrates with /api/users endpoints
 */

const manageUsersController = {
    users: [],
    filteredUsers: [],
    roles: [],
    permissions: [],
    currentUser: null,
    currentFilters: {
        role: '',
        status: '',
        search: ''
    },

    /**
     * Initialize controller
     */
    init: async function() {
        try {
            console.log('Initializing user management...');
            await this.loadRoles();
            await this.loadPermissions();
            await this.loadUsers();
            this.setupEventListeners();
            this.checkUserPermissions();
            console.log('User management loaded successfully');
        } catch (error) {
            console.error('Error initializing users controller:', error);
            showNotification('Failed to load user management', 'error');
        }
    },

    /**
     * Load all users from API
     */
    loadUsers: async function() {
        try {
            const response = await API.users.index();
            this.users = response.data || response || [];
            this.filteredUsers = [...this.users];
            this.renderTable();
        } catch (error) {
            console.error('Error loading users:', error);
            showNotification('Failed to load users', 'error');
            document.getElementById('usersTableContainer').innerHTML = 
                '<div class="alert alert-danger">Failed to load users. Please try again.</div>';
        }
    },

    /**
     * Load all roles from API
     */
    loadRoles: async function() {
        try {
            const response = await API.users.getRoles();
            this.roles = response.data || response || [];
            this.populateRoleFilters();
            this.renderRolesList();
        } catch (error) {
            console.error('Error loading roles:', error);
            this.roles = [];
        }
    },

    /**
     * Load all permissions from API
     */
    loadPermissions: async function() {
        try {
            const response = await API.users.getPermissions();
            this.permissions = response.data || response || [];
        } catch (error) {
            console.error('Error loading permissions:', error);
            this.permissions = [];
        }
    },

    /**
     * Populate role filter dropdown
     */
    populateRoleFilters: function() {
      const roleFilter = document.getElementById("roleFilter");
      const mainRoleSelect = document.getElementById("mainRole");

      if (roleFilter) {
        roleFilter.innerHTML = '<option value="">All Roles</option>';
        this.roles.forEach((role) => {
          roleFilter.innerHTML += `<option value="${role.role_id || role.id}">${
            role.role_name || role.name
          }</option>`;
        });
      }

      if (mainRoleSelect) {
        mainRoleSelect.innerHTML =
          '<option value="">-- Select Role --</option>';
        this.roles.forEach((role) => {
          mainRoleSelect.innerHTML += `<option value="${
            role.role_id || role.id
          }">${role.role_name || role.name}</option>`;
        });
      }
      // Populate additional roles container for create modal
      const extraCreateContainer = document.getElementById(
        "extraRolesCreateContainer"
      );
      if (extraCreateContainer) {
        extraCreateContainer.innerHTML = "";
        this.roles.forEach((role) => {
          const roleId = role.role_id || role.id;
          extraCreateContainer.innerHTML += `
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="${roleId}" id="create_role_${roleId}">
                        <label class="form-check-label" for="create_role_${roleId}">${
            role.role_name || role.name
          }</label>
                    </div>
                `;
        });
      }
    },

    /**
     * Render users table
     */
    renderTable: function() {
        const container = document.getElementById('usersTableContainer');
        if (!container) return;

        if (this.filteredUsers.length === 0) {
            container.innerHTML = '<div class="alert alert-info">No users found</div>';
            return;
        }

        let html = `
            <div class="table-responsive">
                <table class="table table-hover table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Main Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        this.filteredUsers.forEach(user => {
            const statusBadge = user.status === 'active' 
                ? '<span class="badge bg-success">Active</span>' 
                : '<span class="badge bg-secondary">Inactive</span>';
            
            const fullName = `${user.first_name || ''} ${user.last_name || ''}`.trim() || 'N/A';
            const roleName = user.role_name || user.main_role || 'No Role';

            html += `
                <tr>
                    <td><strong>${user.username}</strong></td>
                    <td>${fullName}</td>
                    <td>${user.email || 'N/A'}</td>
                    <td><span class="badge bg-primary">${roleName}</span></td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="btn-group btn-group-sm" role="group">
                            <button class="btn btn-info" onclick="manageUsersController.viewUser(${user.user_id || user.id})" title="View Details">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-warning" onclick="manageUsersController.showEditModal(${user.user_id || user.id})" title="Edit User" data-permission="users_update">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-success" onclick="manageUsersController.showRolesModal(${user.user_id || user.id})" title="Manage Roles" data-permission="roles_manage">
                                <i class="bi bi-shield-lock"></i>
                            </button>
                            <button class="btn btn-primary" onclick="manageUsersController.showPermissionsModal(${user.user_id || user.id})" title="Manage Permissions" data-permission="permissions_manage">
                                <i class="bi bi-key"></i>
                            </button>
                            <button class="btn btn-secondary" onclick="manageUsersController.resetPassword(${user.user_id || user.id})" title="Reset Password" data-permission="users_update">
                                <i class="bi bi-lock-fill"></i>
                            </button>
                            <button class="btn btn-danger" onclick="manageUsersController.deleteUser(${user.user_id || user.id})" title="Delete User" data-permission="users_delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        html += `
                    </tbody>
                </table>
            </div>
            <div class="mt-2 text-muted">
                Showing ${this.filteredUsers.length} of ${this.users.length} users
            </div>
        `;

        container.innerHTML = html;
        this.checkUserPermissions();
    },

    /**
     * Render roles list in Roles tab
     */
    renderRolesList: function() {
        const container = document.getElementById('rolesListContainer');
        if (!container) return;

        if (this.roles.length === 0) {
            container.innerHTML = '<div class="alert alert-info">No roles defined</div>';
            return;
        }

        let html = `
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>Role Name</th>
                            <th>Description</th>
                            <th>Users Count</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        this.roles.forEach(role => {
            const userCount = this.users.filter(u => 
                (u.main_role_id || u.role_id) === (role.role_id || role.id)
            ).length;

            html += `
                <tr>
                    <td><strong>${role.role_name || role.name}</strong></td>
                    <td>${role.description || 'No description'}</td>
                    <td><span class="badge bg-info">${userCount} users</span></td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="manageUsersController.viewRolePermissions(${role.role_id || role.id})">
                            <i class="bi bi-eye"></i> View Permissions
                        </button>
                    </td>
                </tr>
            `;
        });

        html += '</tbody></table></div>';
        container.innerHTML = html;
    },

    /**
     * Handle search input
     */
    handleSearch: function(query) {
        this.currentFilters.search = query;
        this.applyFilters();
    },

    /**
     * Handle role filter
     */
    handleRoleFilter: function(roleId) {
        this.currentFilters.role = roleId;
        this.applyFilters();
    },

    /**
     * Handle status filter
     */
    handleStatusFilter: function(status) {
        this.currentFilters.status = status;
        this.applyFilters();
    },

    /**
     * Apply all filters
     */
    applyFilters: function() {
        let filtered = [...this.users];

        // Filter by role
        if (this.currentFilters.role) {
            filtered = filtered.filter(u => 
                (u.main_role_id || u.role_id) == this.currentFilters.role
            );
        }

        // Filter by status
        if (this.currentFilters.status) {
            filtered = filtered.filter(u => u.status === this.currentFilters.status);
        }

        // Filter by search
        if (this.currentFilters.search) {
            const term = this.currentFilters.search.toLowerCase();
            filtered = filtered.filter(u =>
                (u.username || '').toLowerCase().includes(term) ||
                (u.email || '').toLowerCase().includes(term) ||
                ((u.first_name || '') + ' ' + (u.last_name || '')).toLowerCase().includes(term)
            );
        }

        this.filteredUsers = filtered;
        this.renderTable();
    },

    /**
     * Clear all filters
     */
    clearFilters: function() {
        this.currentFilters = { role: '', status: '', search: '' };
        document.getElementById('searchUsers').value = '';
        document.getElementById('roleFilter').value = '';
        document.getElementById('statusFilter').value = '';
        this.applyFilters();
    },

    /**
     * Show create user modal
     */
    showCreateModal: function() {
        document.getElementById('userForm').reset();
        document.getElementById('userId').value = '';
        document.getElementById('userModalLabel').textContent = 'Add New User';
        document.getElementById('passwordSection').style.display = 'block';
        document.getElementById('password').required = true;
        
        const modal = new bootstrap.Modal(document.getElementById('userModal'));
        modal.show();
    },

    /**
     * Show edit user modal
     */
    showEditModal: async function(userId) {
        try {
            const user = await API.users.get(userId);
            
            document.getElementById('userId').value = user.user_id || user.id;
            document.getElementById('username').value = user.username || '';
            document.getElementById('email').value = user.email || '';
            document.getElementById('firstName').value = user.first_name || '';
            document.getElementById('lastName').value = user.last_name || '';
            document.getElementById('mainRole').value = user.main_role_id || user.role_id || '';
            document.getElementById('userStatus').value = user.status || 'active';
            
            document.getElementById('userModalLabel').textContent = 'Edit User';
            document.getElementById('passwordSection').style.display = 'none';
            document.getElementById('password').required = false;
            
            const modal = new bootstrap.Modal(document.getElementById('userModal'));
            modal.show();
        } catch (error) {
            console.error('Error loading user:', error);
            showNotification('Failed to load user details', 'error');
        }
    },

    /**
     * Save user (create or update)
     */
    saveUser: async function() {
        try {
          const userId = document.getElementById("userId").value;

          // Collect selected roles (main + extras)
          const mainRoleVal = document.getElementById("mainRole").value;
          const extraRoleVals = [
            ...document.querySelectorAll(
              '#extraRolesCreateContainer input[type="checkbox"]:checked'
            ),
          ].map((cb) => parseInt(cb.value));
          const roleSet = new Set();
          if (mainRoleVal) roleSet.add(parseInt(mainRoleVal));
          extraRoleVals.forEach((r) => {
            if (!isNaN(r)) roleSet.add(r);
          });
          const roleIds = Array.from(roleSet);

          // Collect optional staff fields from the form if present and include as staff_info
          const staffFieldMap = {
            staff_type_id: "staffTypeId",
            staff_category_id: "staffCategoryId",
            department_id: "departmentId",
            supervisor_id: "supervisorId",
            position: "position",
            employment_date: "employmentDate",
            contract_type: "contractType",
            nssf_no: "nssfNo",
            kra_pin: "kraPin",
            nhif_no: "nhifNo",
            bank_account: "bankAccount",
            salary: "salary",
            gender: "gender",
            marital_status: "maritalStatus",
            tsc_no: "tscNo",
            address: "address",
            date_of_birth: "dateOfBirth",
            first_name: "firstName",
            last_name: "lastName",
          };
          const staffInfo = {};
          Object.keys(staffFieldMap).forEach((key) => {
            const el = document.getElementById(staffFieldMap[key]);
            if (el && el.value !== undefined && el.value !== "") {
              staffInfo[key] = el.value;
            }
          });

          // Clear previous errors
          FormValidation.clearAllErrors("userForm");

          // Build base form data for validation
          const baseData = {
            username: document.getElementById("username").value,
            email: document.getElementById("email").value,
            first_name: document.getElementById("firstName").value,
            last_name: document.getElementById("lastName").value,
            role_ids: roleIds,
            status: document.getElementById("userStatus").value,
          };

          // Add password if creating or if password field has value
          const passwordField = document.getElementById("password");
          if (passwordField && passwordField.value) {
            baseData.password = passwordField.value;
          }

          // Validate form data
          const validation = FormValidation.validateUserForm(
            baseData,
            !!userId
          );

          if (!validation.valid) {
            // Show validation errors
            validation.errors.forEach((error) => {
              showNotification(error, "warning");
            });

            // Highlight specific fields with errors
            if (baseData.username) {
              const usernameResult = FormValidation.validateUsername(
                baseData.username
              );
              if (!usernameResult.valid) {
                FormValidation.showFieldError("username", usernameResult.error);
              }
            }

            if (baseData.email) {
              const emailResult = FormValidation.validateEmail(baseData.email);
              if (!emailResult.valid) {
                FormValidation.showFieldError("email", emailResult.error);
              }
            }

            if (baseData.password) {
              const passwordResult = FormValidation.validatePassword(
                baseData.password
              );
              if (!passwordResult.valid) {
                FormValidation.showFieldError("password", passwordResult.error);
              }
            }

            if (baseData.first_name) {
              const firstNameResult = FormValidation.validateName(
                baseData.first_name,
                "First name"
              );
              if (!firstNameResult.valid) {
                FormValidation.showFieldError(
                  "firstName",
                  firstNameResult.error
                );
              }
            }

            if (baseData.last_name) {
              const lastNameResult = FormValidation.validateName(
                baseData.last_name,
                "Last name"
              );
              if (!lastNameResult.valid) {
                FormValidation.showFieldError("lastName", lastNameResult.error);
              }
            }

            return;
          }

          // Check for profile picture file upload
          const profilePicFile =
            document.getElementById("profilePicFile")?.files[0];

          // Build FormData for multipart upload if file is present
          let formData;
          let useFormData = !!profilePicFile;

          if (useFormData) {
            formData = new FormData();
            formData.append("username", baseData.username);
            formData.append("email", baseData.email);
            formData.append("first_name", baseData.first_name);
            formData.append("last_name", baseData.last_name);
            formData.append("status", baseData.status);
            formData.append("role_ids", JSON.stringify(roleIds));

            if (baseData.password) {
              formData.append("password", baseData.password);
            }

            if (Object.keys(staffInfo).length > 0) {
              formData.append("staff_info", JSON.stringify(staffInfo));
            }

            formData.append("profile_pic", profilePicFile);
          } else {
            // Use JSON payload
            formData = { ...baseData };
            if (Object.keys(staffInfo).length > 0) {
              formData.staff_info = staffInfo;
            }
          }

          if (userId) {
            // Update existing user
            if (useFormData) {
              formData.append("id", userId);
              await API.apiCall(
                `/users/user/${userId}`,
                "PUT",
                formData,
                {},
                { isFile: true }
              );
            } else {
              await API.users.update(userId, formData);
            }
            showNotification("User updated successfully", "success");
          } else {
            // Create new user
            if (useFormData) {
              await API.apiCall(
                "/users/user",
                "POST",
                formData,
                {},
                { isFile: true }
              );
            } else {
              await API.users.create(formData);
            }
            showNotification("User created successfully", "success");
          }

          await this.loadUsers();
          bootstrap.Modal.getInstance(
            document.getElementById("userModal")
          ).hide();
        } catch (error) {
            console.error('Error saving user:', error);
            
            // Check if error has validation details from backend
            if (error.errors && Array.isArray(error.errors)) {
                error.errors.forEach(err => showNotification(err, 'error'));
            } else {
                showNotification('Failed to save user: ' + (error.message || 'Unknown error'), 'error');
            }
        }
    },

    /**
     * Delete user
     */
    deleteUser: async function(userId) {
        if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
            return;
        }

        try {
            await API.users.delete(userId);
            showNotification('User deleted successfully', 'success');
            await this.loadUsers();
        } catch (error) {
            console.error('Error deleting user:', error);
            showNotification('Failed to delete user: ' + (error.message || 'Unknown error'), 'error');
        }
    },

    /**
     * View user details
     */
    viewUser: async function(userId) {
        try {
            const user = await API.users.get(userId);
            const permissions = await API.users.getPermissionsEffective(userId);
            
            let details = `
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5>User Details: ${user.username}</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Full Name:</strong> ${user.first_name} ${user.last_name}</p>
                        <p><strong>Email:</strong> ${user.email}</p>
                        <p><strong>Role:</strong> ${user.role_name || 'N/A'}</p>
                        <p><strong>Status:</strong> ${user.status}</p>
                        <p><strong>Permissions:</strong> ${permissions.data?.length || 0} active permissions</p>
                    </div>
                </div>
            `;
            
            // Create temporary modal or use alert
            alert(details.replace(/<[^>]*>/g, '\n'));
        } catch (error) {
            console.error('Error viewing user:', error);
            showNotification('Failed to load user details', 'error');
        }
    },

    /**
     * Show roles management modal
     */
    showRolesModal: async function(userId) {
        try {
            this.currentUser = await API.users.get(userId);
            const mainRole = await API.users.getRoleMain(userId);
            const extraRoles = await API.users.getRoleExtra(userId);
            
            document.getElementById('roleUserName').textContent = this.currentUser.username;
            
            // Populate main role dropdown
            const mainRoleSelect = document.getElementById('mainRoleSelect');
            mainRoleSelect.innerHTML = '';
            this.roles.forEach(role => {
                const selected = (mainRole.data?.role_id || mainRole.role_id) === (role.role_id || role.id) ? 'selected' : '';
                mainRoleSelect.innerHTML += `<option value="${role.role_id || role.id}" ${selected}>${role.role_name || role.name}</option>`;
            });
            
            // Populate extra roles checkboxes
            const extraRolesContainer = document.getElementById('extraRolesContainer');
            extraRolesContainer.innerHTML = '';
            
            const extraRoleIds = (extraRoles.data || extraRoles || []).map(r => r.role_id || r.id);
            
            this.roles.forEach(role => {
                const roleId = role.role_id || role.id;
                const isChecked = extraRoleIds.includes(roleId);
                const isMain = (mainRole.data?.role_id || mainRole.role_id) === roleId;
                
                if (!isMain) {
                    extraRolesContainer.innerHTML += `
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="${roleId}" id="role_${roleId}" ${isChecked ? 'checked' : ''}>
                            <label class="form-check-label" for="role_${roleId}">
                                ${role.role_name || role.name}
                            </label>
                        </div>
                    `;
                }
            });
            
            const modal = new bootstrap.Modal(document.getElementById('rolesModal'));
            modal.show();
        } catch (error) {
            console.error('Error loading user roles:', error);
            showNotification('Failed to load user roles', 'error');
        }
    },

    /**
     * Update user roles
     */
    updateUserRoles: async function() {
        try {
            const userId = this.currentUser.user_id || this.currentUser.id;
            const mainRoleId = document.getElementById('mainRoleSelect').value;
            const extraRoleIds = [...document.querySelectorAll('#extraRolesContainer input:checked')]
                .map(cb => parseInt(cb.value));
            
            // Assign main role
            await API.users.assignRoleToUser(userId, mainRoleId);
            
            // Bulk assign extra roles
            if (extraRoleIds.length > 0) {
                await API.users.bulkAssignRolesToUser(userId, extraRoleIds);
            }
            
            showNotification('User roles updated successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('rolesModal')).hide();
            await this.loadUsers();
        } catch (error) {
            console.error('Error updating user roles:', error);
            showNotification('Failed to update roles: ' + (error.message || 'Unknown error'), 'error');
        }
    },

    /**
     * Show permissions management modal
     */
    showPermissionsModal: async function(userId) {
        try {
            this.currentUser = await API.users.get(userId);
            
            const effective = await API.users.getPermissionsEffective(userId);
            const direct = await API.users.getPermissionsDirect(userId);
            const denied = await API.users.getPermissionsDenied(userId);
            
            document.getElementById('permUserName').textContent = this.currentUser.username;
            
            this.renderPermissions('effective', effective.data || effective || []);
            this.renderPermissions('direct', direct.data || direct || []);
            this.renderPermissions('denied', denied.data || denied || []);
            
            this.showPermissionsTab('effective');
            
            const modal = new bootstrap.Modal(document.getElementById('permissionsModal'));
            modal.show();
        } catch (error) {
            console.error('Error loading permissions:', error);
            showNotification('Failed to load permissions', 'error');
        }
    },

    /**
     * Render permissions in modal
     */
    renderPermissions: function(type, permissions) {
        const container = document.getElementById(`${type}Permissions`);
        if (!container) return;

        if (!permissions || permissions.length === 0) {
            container.innerHTML = '<div class="alert alert-info">No permissions found</div>';
            return;
        }

        // Group permissions by entity
        const grouped = {};
        permissions.forEach(perm => {
            const entity = perm.entity || perm.permission_entity || 'Other';
            if (!grouped[entity]) {
                grouped[entity] = [];
            }
            grouped[entity].push(perm);
        });

        let html = '';
        Object.keys(grouped).sort().forEach(entity => {
            html += `<div class="mb-3">
                <h6 class="text-primary">${entity}</h6>
                <div class="row">`;
            
            grouped[entity].forEach(perm => {
                const permName = perm.permission_name || perm.name || perm.permission_code;
                const permCode = perm.permission_code || perm.code;
                
                if (type === 'direct') {
                    html += `
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input direct-perm" type="checkbox" value="${permCode}" id="perm_${permCode}">
                                <label class="form-check-label" for="perm_${permCode}">
                                    ${permName}
                                </label>
                            </div>
                        </div>
                    `;
                } else {
                    html += `
                        <div class="col-md-6">
                            <span class="badge bg-${type === 'denied' ? 'danger' : 'success'} me-1">${permName}</span>
                        </div>
                    `;
                }
            });
            
            html += '</div></div>';
        });

        container.innerHTML = html;
    },

    /**
     * Show specific permissions tab
     */
    showPermissionsTab: function(tab) {
        ['effective', 'direct', 'denied'].forEach(t => {
            const el = document.getElementById(`${t}Permissions`);
            if (el) {
                el.classList.toggle('d-none', t !== tab);
            }
        });

        // Update save button visibility
        const saveBtn = document.getElementById('savePermissionsBtn');
        if (saveBtn) {
            saveBtn.style.display = tab === 'direct' ? 'block' : 'none';
        }
    },

    /**
     * Save direct permissions
     */
    saveDirectPermissions: async function() {
        try {
            const userId = this.currentUser.user_id || this.currentUser.id;
            const permissionCodes = [...document.querySelectorAll('.direct-perm:checked')]
                .map(cb => cb.value);
            
            if (permissionCodes.length === 0) {
                showNotification('No permissions selected', 'warning');
                return;
            }
            
            await API.users.bulkAssignPermissionsToUserDirect(userId, permissionCodes);
            
            showNotification('Permissions updated successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('permissionsModal')).hide();
        } catch (error) {
            console.error('Error saving permissions:', error);
            showNotification('Failed to save permissions: ' + (error.message || 'Unknown error'), 'error');
        }
    },

    /**
     * Reset user password
     */
    resetPassword: async function(userId) {
        try {
            const user = await API.users.get(userId);
            
            if (!confirm(`Send password reset email to ${user.email}?`)) {
                return;
            }
            
            await API.users.requestPasswordReset(user.email);
            showNotification('Password reset email sent successfully', 'success');
        } catch (error) {
            console.error('Error resetting password:', error);
            showNotification('Failed to send reset email: ' + (error.message || 'Unknown error'), 'error');
        }
    },

    /**
     * View role permissions
     */
    viewRolePermissions: async function(roleId) {
        try {
            // This would need a specific API endpoint
            console.log('Role permissions view coming soon');
        } catch (error) {
            console.error('Error loading role permissions:', error);
        }
    },

    /**
     * Load activity logs
     */
    loadActivityLogs: function() {
        const container = document.getElementById('activityLogsContainer');
        container.innerHTML = '<div class="alert alert-info">Activity logs feature coming soon</div>';
    },

    /**
     * Check user permissions and hide/disable buttons
     */
    checkUserPermissions: function() {
        const currentUser = AuthContext.getUser();
        if (!currentUser || !currentUser.permissions) return;

        // Check and hide buttons based on permissions
        document.querySelectorAll('[data-permission]').forEach(btn => {
            const requiredPerm = btn.getAttribute('data-permission');
            if (!currentUser.permissions.includes(requiredPerm)) {
                btn.style.display = 'none';
            }
        });

        // Hide add user button if no create permission
        if (!currentUser.permissions.includes('users_create')) {
            const addBtn = document.getElementById('addUserBtn');
            if (addBtn) addBtn.style.display = 'none';
        }
    },

    /**
     * Setup event listeners
     */
    setupEventListeners: function() {
        // Form submission
        const userForm = document.getElementById('userForm');
        if (userForm) {
            userForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveUser();
            });
        }

        // Real-time validation
        if (document.getElementById('username')) {
            FormValidation.setupRealTimeValidation('username', FormValidation.validateUsername.bind(FormValidation));
        }

        if (document.getElementById('email')) {
            FormValidation.setupRealTimeValidation('email', FormValidation.validateEmail.bind(FormValidation));
        }

        if (document.getElementById('firstName')) {
            FormValidation.setupRealTimeValidation('firstName', FormValidation.validateName.bind(FormValidation), 'First name');
        }

        if (document.getElementById('lastName')) {
            FormValidation.setupRealTimeValidation('lastName', FormValidation.validateName.bind(FormValidation), 'Last name');
        }

        // Password strength meter
        if (document.getElementById('password')) {
            // Create meter container if not exists
            const passwordField = document.getElementById('password');
            let meterContainer = document.getElementById('passwordStrengthMeter');
            
            if (!meterContainer) {
                meterContainer = document.createElement('div');
                meterContainer.id = 'passwordStrengthMeter';
                meterContainer.className = 'mt-2';
                passwordField.parentElement.appendChild(meterContainer);
            }

            FormValidation.setupPasswordStrengthMeter('password', 'passwordStrengthMeter');
            FormValidation.setupRealTimeValidation('password', FormValidation.validatePassword.bind(FormValidation));
        }
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('usersTableContainer')) {
        manageUsersController.init();
    }
});
