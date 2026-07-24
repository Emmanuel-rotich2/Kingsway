/**
 * Manage Non-Teaching Staff Controller
 * Uses existing api.js JWT authentication
 */
const manageNonTeachingStaffController = {
    allStaff: [],
    filteredStaff: [],
    departments: [],
    searchTerm: "",
    departmentFilter: "",
    statusFilter: "",
    editingId: null,

    async init() {
        if (typeof AuthContext !== 'undefined') {
            await AuthContext.ready();
        }

        if (!AuthContext?.isAuthenticated()) {
            window.location.href = (window.APP_BASE || "") + "/index.php";
            return;
        }

        if (!AuthContext.canView('staff')) {
            showNotification('You do not have permission to view staff', 'error');
            return;
        }

        await Promise.all([this.loadStaff(), this.loadDepartments()]);
        this.bindEvents();
    },

    async loadStaff() {
        try {
            const response = await window.API.staff.getNonTeaching({});
            const normalized = AppState.normalizeResponse(response);
            const all = this.extractList(normalized.data);
            this.allStaff = all;
            this.applyFilters();
        } catch (error) {
            console.error("Error loading non-teaching staff:", error);
            this.renderTable([]);
        }
    },

    extractList(response) {
        if (!response) return [];
        if (Array.isArray(response)) return response;
        if (Array.isArray(response.staff)) return response.staff;
        if (Array.isArray(response.data?.staff)) return response.data.staff;
        if (Array.isArray(response.data)) return response.data;
        return [];
    },

    async loadDepartments() {
        try {
            const response = await window.API.staff.getDepartments();
            const normalized = AppState.normalizeResponse(response);
            this.departments = Array.isArray(normalized.data) ? normalized.data : [];
            this.populateDepartmentDropdowns();
        } catch (error) {
            console.warn("Error loading departments:", error);
        }
    },

    populateDepartmentDropdowns() {
        const selects = [
            document.getElementById("filterDepartment"),
            document.getElementById("departmentSelect"),
        ];
        selects.forEach((el) => {
            if (!el) return;
            const isFilter = el.id === "filterDepartment";
            el.innerHTML = isFilter
                ? '<option value="">-- All Departments --</option>'
                : '<option value="">-- Select Department --</option>';
            this.departments.forEach((d) => {
                const name = d.name || d.department_name || d;
                const id = d.id || d.department_id || name;
                el.innerHTML += `<option value="${id}">${name}</option>`;
            });
        });
    },

    bindEvents() {
        document.getElementById('filterSearch')?.addEventListener('input', (e) => {
            this.searchTerm = e.target.value;
            this.applyFilters();
        });

        document.getElementById('filterDepartment')?.addEventListener('change', (e) => {
            this.departmentFilter = e.target.value || "";
            this.applyFilters();
        });

        document.getElementById('filterStatus')?.addEventListener('change', (e) => {
            this.statusFilter = e.target.value || "";
            this.applyFilters();
        });

        document.getElementById('resetFiltersBtn')?.addEventListener('click', () => {
            this.resetFilters();
        });

        document.getElementById('addStaffBtn')?.addEventListener('click', () => {
            if (AuthContext.canCreate('staff')) {
                this.showCreateForm();
            } else {
                showNotification('You do not have permission to add staff', 'error');
            }
        });

        document.getElementById('saveStaffBtn')?.addEventListener('click', () => {
            this.saveStaff();
        });

        document.getElementById('exportStaffBtn')?.addEventListener('click', () => {
            if (AuthContext.canExport('staff')) {
                this.exportStaff();
            } else {
                showNotification('You do not have permission to export staff', 'error');
            }
        });
    },

    search(term) {
        this.searchTerm = (term || "").toLowerCase();
        this.applyFilters();
    },

    filterByDepartment(dept) {
        this.departmentFilter = dept || "";
        this.applyFilters();
    },

    filterByStatus(status) {
        this.statusFilter = (status || "").toLowerCase();
        this.applyFilters();
    },

    applyFilters() {
        let list = [...this.allStaff];

        if (this.searchTerm) {
            list = list.filter((s) => {
                const name = `${s.first_name || ""} ${s.last_name || ""} ${s.name || ""}`.toLowerCase();
                const staffNo = (s.staff_no || "").toLowerCase();
                const email = (s.email || "").toLowerCase();
                return (
                    name.includes(this.searchTerm) ||
                    staffNo.includes(this.searchTerm) ||
                    email.includes(this.searchTerm)
                );
            });
        }

        if (this.departmentFilter) {
            list = list.filter((s) => {
                const deptId = String(s.department_id || s.department || "");
                return deptId === String(this.departmentFilter);
            });
        }

        if (this.statusFilter) {
            list = list.filter(
                (s) => (s.status || "").toLowerCase() === this.statusFilter,
            );
        }

        this.filteredStaff = list;
        this.renderTable(list);
        this.updateStats();
    },

    resetFilters() {
        this.searchTerm = "";
        this.departmentFilter = "";
        this.statusFilter = "";

        document.getElementById('filterSearch').value = "";
        document.getElementById('filterDepartment').value = "";
        document.getElementById('filterStatus').value = "";

        this.applyFilters();
    },

    renderTable(staff) {
        const container = document.getElementById("staffTableBody");
        if (!container) return;

        if (!staff || staff.length === 0) {
            container.innerHTML =
                '<tr><td colspan="9" class="text-center text-muted py-3">No non-teaching staff records found.</td></tr>';
            return;
        }

        let html = "";
        staff.forEach((s, i) => {
            const name = s.name || `${s.first_name || ""} ${s.last_name || ""}`.trim();
            const dept = s.department_name || s.department || "N/A";
            const role = s.position || s.role || s.job_title || "N/A";
            const statusClass = (s.status || "").toLowerCase() === "active" ? "bg-success" : "bg-secondary";

            html += `
                <tr>
                    <td>${i + 1}</td>
                    <td>${this.esc(s.staff_no || "—")}</td>
                    <td>${this.esc(name)}</td>
                    <td>${this.esc(dept)}</td>
                    <td>${this.esc(s.category || "—")}</td>
                    <td>${this.esc(role)}</td>
                    <td>${this.esc(s.contract_type || "—")}</td>
                    <td><span class="badge ${statusClass}">${this.esc(s.status || "Unknown")}</span></td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-info me-1" onclick="manageNonTeachingStaffController.viewStaff(${s.id})" title="View"><i class="bi bi-eye"></i></button>
                        <button class="btn btn-sm btn-outline-warning me-1" onclick="manageNonTeachingStaffController.showEditForm(${s.id})" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-outline-danger" onclick="manageNonTeachingStaffController.deleteStaff(${s.id})" title="Delete"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>`;
        });

        container.innerHTML = html;
    },

    updateStats() {
        const total = this.allStaff.length;
        const active = this.allStaff.filter(s => s.status === 'active').length;
        const departments = new Set(this.allStaff.map(s => s.department_id)).size;
        
        document.getElementById('statTotalStaff').textContent = total;
        document.getElementById('statActiveStaff').textContent = active;
        document.getElementById('statDepartments').textContent = departments;
        document.getElementById('statPresentToday').textContent = active; // Placeholder for now
    },

    showCreateForm() {
        this.editingId = null;
        document.getElementById("staffModalLabel").textContent = "Add Non-Teaching Staff";
        document.getElementById("staffId").value = "";
        document.getElementById("staffForm").reset();
        const modal = new bootstrap.Modal(document.getElementById("staffModal"));
        modal.show();
    },

    showEditForm: async function (id) {
        try {
            const response = await window.API.staff.get(id);
            const normalized = AppState.normalizeResponse(response);
            const staff = normalized.data;
            if (!staff) {
                this.notify("Staff record not found", "danger");
                return;
            }
            this.editingId = id;
            document.getElementById("staffModalLabel").textContent = "Edit Staff";
            document.getElementById("staffId").value = id;
            document.getElementById("firstName").value = staff.first_name || "";
            document.getElementById("lastName").value = staff.last_name || "";
            document.getElementById("email").value = staff.email || "";
            document.getElementById("phone").value = staff.phone || "";
            document.getElementById("role").value = staff.position || staff.role || "";
            document.getElementById("category").value = staff.category || "";
            document.getElementById("contractType").value = staff.contract_type || "permanent";
            document.getElementById("statusSelect").value = staff.status || "active";

            const deptEl = document.getElementById("departmentSelect");
            if (deptEl) {
                deptEl.value = staff.department_id || staff.department || "";
            }

            const modal = new bootstrap.Modal(document.getElementById("staffModal"));
            modal.show();
        } catch (error) {
            console.error("Error loading staff for edit:", error);
            this.notify("Failed to load staff details", "danger");
        }
    },

    async saveStaff(e) {
        if (e) e.preventDefault();

        const data = {
            first_name: document.getElementById("firstName").value.trim(),
            last_name: document.getElementById("lastName").value.trim(),
            email: document.getElementById("email").value.trim(),
            phone: document.getElementById("phone").value.trim(),
            department_id: document.getElementById("departmentSelect").value,
            position: document.getElementById("role").value.trim(),
            category: document.getElementById("category").value,
            contract_type: document.getElementById("contractType").value,
            status: document.getElementById("statusSelect").value,
            staff_type_id: 2, // Non-Teaching
        };

        if (!data.first_name || !data.last_name) {
            this.notify("First name and last name are required", "warning");
            return;
        }

        try {
            if (this.editingId) {
                await window.API.staff.update(this.editingId, data);
                this.notify("Staff updated successfully!", "success");
            } else {
                await window.API.staff.create(data);
                this.notify("Staff created successfully!", "success");
            }

            bootstrap.Modal.getInstance(document.getElementById("staffModal"))?.hide();
            await this.loadStaff();
        } catch (error) {
            console.error("Error saving staff:", error);
            this.notify("Failed to save staff record", "danger");
        }
    },

    viewStaff: async function (id) {
        try {
            const response = await window.API.staff.get(id);
            const normalized = AppState.normalizeResponse(response);
            const s = normalized.data;
            if (!s) {
                this.notify("Staff not found", "danger");
                return;
            }

            const name = s.name || `${s.first_name || ""} ${s.last_name || ""}`.trim();
            alert(`Staff Details:\n\nName: ${name}\nStaff No: ${s.staff_no || "—"}\nEmail: ${s.email || "—"}\nDepartment: ${s.department_name || s.department || "—"}\nPosition: ${s.position || s.role || "—"}\nStatus: ${s.status || "—"}`);
        } catch (error) {
            console.error("Error viewing staff:", error);
            this.notify("Failed to load staff details", "danger");
        }
    },

    deleteStaff: async function (id) {
        if (!confirm("Are you sure you want to delete this staff member?")) return;

        try {
            await window.API.staff.delete(id);
            this.notify("Staff deleted successfully!", "success");
            await this.loadStaff();
        } catch (error) {
            console.error("Error deleting staff:", error);
            this.notify("Failed to delete staff", "danger");
        }
    },

    exportStaff() {
        if (!this.filteredStaff.length) {
            this.notify("No data to export", "warning");
            return;
        }

        const headers = ['#', 'Name', 'Staff No', 'Department', 'Category', 'Position', 'Contract', 'Status'];
        const rows = this.filteredStaff.map((s, i) => [
            i + 1,
            s.name || `${s.first_name} ${s.last_name}`,
            s.staff_no || '--',
            s.department_name || '--',
            s.category || '--',
            s.position || s.role || '--',
            s.contract_type || '--',
            s.status || '--'
        ]);

        let csv = headers.join(',') + '\n' + 
            rows.map(r => r.map(v => '"' + (v || '') + '"').join(',')).join('\n');

        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
        a.download = 'non_teaching_staff.csv';
        a.click();
    },

    notify(msg, type = "info") {
        if (window.API?.showNotification) {
            window.API.showNotification(msg, type);
        } else {
            alert(msg);
        }
    },

    esc(str) {
        if (!str) return "";
        return String(str).replace(/[&<>"']/g, (m) => ({
            "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39"
        })[m]);
    }
};

document.addEventListener("DOMContentLoaded", () => {
    manageNonTeachingStaffController.init();
});