const AlumniController = {
    state: {
        allAlumni: [],
        alumni: [],
        graduationYears: [],
        classes: [],
    },

    async init() {
        await window.AuthContext?.ready?.();
        if (!window.AuthContext?.isAuthenticated()) return;
        this.bindEvents();
        await this.loadData();
    },

    async loadData() {
        try {
            this.showTableLoading();
            const result = await window.API.apiCall('/students/alumni-get', 'GET');
            const alumni = result?.data ?? result ?? [];

            this.state.allAlumni = Array.isArray(alumni) ? alumni : [];
            this.state.alumni = [...this.state.allAlumni];
            this.deriveFilters();
            this.updateStats();
            this.renderTable();
        } catch (error) {
            console.error('Error loading alumni:', error);
            this.showNotification('Error loading alumni data', 'error');
        }
    },

    deriveFilters() {
        const years = new Set();
        const classes = new Set();
        this.state.allAlumni.forEach(a => {
            if (a.graduation_year) years.add(a.graduation_year);
            if (a.class_name) classes.add(a.class_name);
        });
        this.state.graduationYears = [...years].sort((a, b) => b - a);
        this.state.classes = [...classes].sort();

        const yearSel = document.getElementById('filterYear');
        if (yearSel) {
            yearSel.innerHTML = '<option value="">All Graduation Years</option>' +
                this.state.graduationYears.map(y => `<option value="${y}">${y}</option>`).join('');
        }

        const classSel = document.getElementById('filterClass');
        if (classSel) {
            classSel.innerHTML = '<option value="">All Classes</option>' +
                this.state.classes.map(c => `<option value="${c}">${this.esc(c)}</option>`).join('');
        }
    },

    bindEvents() {
        const search = document.getElementById('searchAlumni');
        if (search) search.addEventListener('input', () => this.applyFilters());

        const year = document.getElementById('filterYear');
        if (year) year.addEventListener('change', () => this.applyFilters());

        const cls = document.getElementById('filterClass');
        if (cls) cls.addEventListener('change', () => this.applyFilters());

        const active = document.getElementById('filterActive');
        if (active) active.addEventListener('change', () => this.applyFilters());

        const exportBtn = document.getElementById('exportAlumni');
        if (exportBtn) exportBtn.addEventListener('click', () => this.exportCSV());
    },

    applyFilters() {
        const search = document.getElementById('searchAlumni')?.value?.toLowerCase() || '';
        const year = document.getElementById('filterYear')?.value || '';
        const cls = document.getElementById('filterClass')?.value || '';
        const active = document.getElementById('filterActive')?.value || '';

        let filtered = [...this.state.allAlumni];

        if (search) {
            filtered = filtered.filter(a =>
                (a.first_name || '').toLowerCase().includes(search) ||
                (a.middle_name || '').toLowerCase().includes(search) ||
                (a.last_name || '').toLowerCase().includes(search) ||
                `${a.first_name || ''} ${a.last_name || ''}`.toLowerCase().includes(search) ||
                (a.admission_no || '').toLowerCase().includes(search)
            );
        }
        if (year) filtered = filtered.filter(a => String(a.graduation_year) === year);
        if (cls) filtered = filtered.filter(a => a.class_name === cls);
        if (active !== '') filtered = filtered.filter(a => String(a.is_active_alumni ?? 1) === active);

        this.state.alumni = filtered;
        this.renderTable();
    },

    updateStats() {
        const all = this.state.allAlumni;
        const currentYear = new Date().getFullYear();
        const el = (id, val) => {
            const e = document.getElementById(id);
            if (e) e.textContent = val;
        };
        el('totalAlumni', all.length);
        el('recentAlumni', all.filter(a => String(a.graduation_year) === String(currentYear)).length);
        el('activeAlumni', all.filter(a => (a.contact_email || a.contact_phone) && (a.is_active_alumni ?? 1) == 1).length);
    },

    renderTable() {
        const tbody = document.querySelector('#alumniTable tbody');
        if (!tbody) return;

        if (this.state.alumni.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No alumni found</td></tr>';
            return;
        }

        tbody.innerHTML = this.state.alumni.map((a, i) => {
            const name = `${a.first_name || ''} ${a.middle_name || ''} ${a.last_name || ''}`.trim();
            const contact = [a.contact_email, a.contact_phone].filter(Boolean).join(', ') || '--';
            const active = (a.is_active_alumni ?? 1) == 1;
            return `
                <tr>
                    <td>${i + 1}</td>
                    <td>${this.esc(a.admission_no || '--')}</td>
                    <td><strong>${this.esc(name)}</strong></td>
                    <td>${this.esc(a.graduation_year || '--')}</td>
                    <td>${this.esc(a.class_name || '--')}</td>
                    <td>${this.esc(a.stream_name || '--')}</td>
                    <td><small>${this.esc(contact)}</small></td>
                    <td>${active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="AlumniController.viewAlumni(${i})" title="View"><i class="fas fa-eye"></i></button>
                            <button class="btn btn-outline-info" onclick="AlumniController.editAlumni(${i})" title="Edit"><i class="fas fa-edit"></i></button>
                            ${active ? `<button class="btn btn-outline-warning" onclick="AlumniController.deactivateAlumni(${i})" title="Deactivate"><i class="fas fa-user-slash"></i></button>` : ''}
                        </div>
                    </td>
                </tr>`;
        }).join('');
    },

    viewAlumni(index) {
        const a = this.state.alumni[index];
        if (!a) return;
        const name = `${a.first_name || ''} ${a.middle_name || ''} ${a.last_name || ''}`.trim();
        const contact = [a.contact_email, a.contact_phone].filter(Boolean).join(', ') || 'N/A';
        this.showModal('Alumni Details', `
            <div class="row">
                <div class="col-md-6">
                    <h6>Student Info</h6>
                    <p><strong>Name:</strong> ${this.esc(name)}</p>
                    <p><strong>Admission No:</strong> ${this.esc(a.admission_no || 'N/A')}</p>
                    <p><strong>Graduated:</strong> ${this.esc(a.graduation_year || 'N/A')}</p>
                    <p><strong>Class:</strong> ${this.esc(a.class_name || 'N/A')} - ${this.esc(a.stream_name || 'N/A')}</p>
                </div>
                <div class="col-md-6">
                    <h6>Alumni Info</h6>
                    <p><strong>Next School:</strong> ${this.esc(a.next_school || 'N/A')}</p>
                    <p><strong>Career Interest:</strong> ${this.esc(a.career_interest || 'N/A')}</p>
                    <p><strong>Conduct:</strong> ${this.esc(a.conduct_grade || 'N/A')}</p>
                    <p><strong>Final Grade:</strong> ${this.esc(a.final_grade || 'N/A')} (Avg: ${a.final_average || 'N/A'})</p>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-md-6">
                    <p><strong>Contact:</strong> ${this.esc(contact)}</p>
                </div>
                <div class="col-md-6">
                    <p><strong>Awards:</strong> ${this.esc(a.awards || 'None')}</p>
                </div>
            </div>
            ${a.alumni_notes ? `<div class="row"><div class="col-12"><p><strong>Notes:</strong><br>${this.esc(a.alumni_notes)}</p></div></div>` : ''}
        `);
    },

    editAlumni(index) {
        const a = this.state.alumni[index];
        if (!a) return;
        const name = `${a.first_name || ''} ${a.middle_name || ''} ${a.last_name || ''}`.trim();
        this.showModal('Edit Alumni', `
            <form id="editAlumniForm">
                <p><strong>${this.esc(name)}</strong> (${this.esc(a.admission_no || '')})</p>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Contact Email</label>
                        <input type="email" class="form-control" name="contact_email" value="${this.esc(a.contact_email || '')}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Contact Phone</label>
                        <input type="tel" class="form-control" name="contact_phone" value="${this.esc(a.contact_phone || '')}">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Next School</label>
                        <input type="text" class="form-control" name="next_school" value="${this.esc(a.next_school || '')}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Career Interest</label>
                        <input type="text" class="form-control" name="career_interest" value="${this.esc(a.career_interest || '')}">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Conduct Grade</label>
                        <select class="form-select" name="conduct_grade">
                            <option value="">-- Select --</option>
                            ${['Excellent', 'Very Good', 'Good', 'Fair', 'Poor'].map(g =>
                                `<option value="${g}" ${a.conduct_grade === g ? 'selected' : ''}>${g}</option>`
                            ).join('')}
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Last Contact Date</label>
                        <input type="date" class="form-control" name="last_contact_date" value="${a.last_contact_date || ''}">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Awards / Achievements</label>
                    <textarea class="form-control" name="awards" rows="2">${this.esc(a.awards || '')}</textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea class="form-control" name="alumni_notes" rows="2">${this.esc(a.alumni_notes || '')}</textarea>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Changes</button>
            </form>`,
            () => {
                document.getElementById('editAlumniForm')?.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const form = e.target;
                    const data = { id: a.id };
                    new FormData(form).forEach((val, key) => { data[key] = val; });
                    try {
                        await window.API.apiCall('/students/alumni-update', 'POST', data);
                        this.showNotification('Alumni updated successfully', 'success');
                        bootstrap.Modal.getInstance(document.getElementById('dynamicModal'))?.hide();
                        await this.loadData();
                    } catch (err) {
                        this.showNotification('Error updating alumni', 'error');
                    }
                });
            },
        );
    },

    async deactivateAlumni(index) {
        const a = this.state.alumni[index];
        if (!a) return;
        const name = `${a.first_name || ''} ${a.middle_name || ''} ${a.last_name || ''}`.trim();
        if (!(await window.confirmAction('Confirm Deletion', `Deactivate alumni record for ${name}?`, { confirmText: 'Delete', danger: true }))) return;
        try {
            await window.API.apiCall('/students/alumni-delete', 'POST', { id: a.id });
            this.showNotification('Alumni deactivated', 'success');
            await this.loadData();
        } catch (err) {
            this.showNotification('Error deactivating alumni', 'error');
        }
    },

    exportCSV() {
        if (this.state.alumni.length === 0) {
            this.showNotification('No data to export', 'warning');
            return;
        }
        const headers = ['#', 'Admission No', 'First Name', 'Middle Name', 'Last Name',
            'Graduation Year', 'Class', 'Stream', 'Contact Email', 'Contact Phone',
            'Next School', 'Career Interest', 'Final Grade', 'Final Average', 'Awards', 'Status'];
        const rows = this.state.alumni.map((a, i) => [
            i + 1, a.admission_no || '', a.first_name || '', a.middle_name || '', a.last_name || '',
            a.graduation_year || '', a.class_name || '', a.stream_name || '',
            a.contact_email || '', a.contact_phone || '',
            a.next_school || '', a.career_interest || '',
            a.final_grade || '', a.final_average || '', a.awards || '',
            (a.is_active_alumni ?? 1) == 1 ? 'Active' : 'Inactive',
        ]);
        const csv = [headers, ...rows]
            .map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(','))
            .join('\n');
        if (window.KingswayFileLifecycle?.exportText) {
            window.KingswayFileLifecycle.exportText(csv, 'alumni_export.csv', 'text/csv');
        } else {
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const aEl = document.createElement('a');
            aEl.href = url;
            aEl.download = 'alumni_export.csv';
            aEl.click();
            URL.revokeObjectURL(url);
        }
    },

    showTableLoading() {
        const tbody = document.querySelector('#alumniTable tbody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading alumni...</td></tr>';
    },

    esc(str) {
        if (!str && str !== 0) return '';
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    },

    showNotification(msg, type = 'info') {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
        alert.style.zIndex = '9999';
        alert.innerHTML = `${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        document.body.appendChild(alert);
        setTimeout(() => alert.remove(), 4000);
    },

    showModal(title, bodyHtml, onShow) {
        let modal = document.getElementById('dynamicModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'dynamicModal';
            modal.className = 'modal fade';
            modal.tabIndex = -1;
            modal.innerHTML = '<div class="modal-dialog modal-dialog-scrollable modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"></div></div></div>';
            document.body.appendChild(modal);
        }
        modal.querySelector('.modal-title').textContent = title;
        modal.querySelector('.modal-body').innerHTML = bodyHtml;
        new bootstrap.Modal(modal).show();
        if (onShow) setTimeout(onShow, 300);
    },
};

document.addEventListener('DOMContentLoaded', () => AlumniController.init());

window.AlumniController = AlumniController;
