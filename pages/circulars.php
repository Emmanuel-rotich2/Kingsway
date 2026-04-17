<?php /** School Circulars - List and management of official school circulars */ ?>

<div>
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-file-alt me-2"></i>School Circulars</h4>
                    <p class="text-muted mb-0">Official school circulars and notices</p>
                </div>
                <div>
                    <button class="btn btn-outline-secondary btn-sm me-2" id="exportCircularsBtn">
                        <i class="fas fa-download me-1"></i>Export CSV
                    </button>
                    <button class="btn btn-primary btn-sm" id="newCircularBtn" data-bs-toggle="modal" data-bs-target="#circularModal">
                        <i class="fas fa-plus me-1"></i>New Circular
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                            <i class="fas fa-file-alt text-primary fa-lg"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Circulars</h6>
                            <h4 class="mb-0" id="statTotal">0</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                            <i class="fas fa-calendar-check text-success fa-lg"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">This Month</h6>
                            <h4 class="mb-0" id="statThisMonth">0</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3">
                            <i class="fas fa-clock text-warning fa-lg"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Pending Acknowledgment</h6>
                            <h4 class="mb-0" id="statPending">0</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-secondary bg-opacity-10 p-3 me-3">
                            <i class="fas fa-archive text-secondary fa-lg"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Archived</h6>
                            <h4 class="mb-0" id="statArchived">0</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-3">
        <div class="col-md-3">
            <input type="text" class="form-control" id="circularSearch" placeholder="Search circulars...">
        </div>
        <div class="col-md-2">
            <select class="form-select" id="categoryFilter">
                <option value="">All Categories</option>
                <option value="academic">Academic</option>
                <option value="administrative">Administrative</option>
                <option value="financial">Financial</option>
                <option value="general">General</option>
                <option value="event">Events</option>
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select" id="targetFilter">
                <option value="">All Targets</option>
                <option value="all">All</option>
                <option value="staff">Staff</option>
                <option value="students">Students</option>
                <option value="parents">Parents</option>
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select" id="statusFilter">
                <option value="">All Status</option>
                <option value="published">Published</option>
                <option value="draft">Draft</option>
                <option value="archived">Archived</option>
            </select>
        </div>
        <div class="col-md-2">
            <input type="date" class="form-control" id="dateFilter">
        </div>
    </div>

    <!-- Circulars Table -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="circularsTable">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Subject</th>
                            <th>Category</th>
                            <th>Target</th>
                            <th>Sent By</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="circularsBody">
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <div class="spinner-border text-primary spinner-border-sm me-2" role="status"></div>
                                Loading circulars...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <nav class="mt-3">
        <ul class="pagination justify-content-center" id="circularsPagination"></ul>
    </nav>
</div>

<!-- Circular Detail Modal -->
<div class="modal fade" id="circularDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailTitle">Circular Detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailBody">
                <!-- filled by JS -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-outline-primary" id="downloadCircularBtn">
                    <i class="fas fa-download me-1"></i>Download
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Compose/Upload Circular Modal -->
<div class="modal fade" id="circularModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Circular</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="circularForm">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Subject *</label>
                            <input type="text" class="form-control" id="circular_subject" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Category *</label>
                            <select class="form-select" id="circular_category" required>
                                <option value="">Select</option>
                                <option value="academic">Academic</option>
                                <option value="administrative">Administrative</option>
                                <option value="financial">Financial</option>
                                <option value="general">General</option>
                                <option value="event">Events</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Target Audience *</label>
                            <select class="form-select" id="circular_target" required>
                                <option value="all">All</option>
                                <option value="staff">Staff Only</option>
                                <option value="students">Students Only</option>
                                <option value="parents">Parents Only</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Circular Date</label>
                            <input type="date" class="form-control" id="circular_date">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Content *</label>
                        <textarea class="form-control" id="circular_content" rows="6" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Attachment (optional)</label>
                        <input type="file" class="form-control" id="circular_file" accept=".pdf,.doc,.docx">
                        <small class="text-muted">Accepted: PDF, DOC, DOCX. Max 10MB.</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-secondary" id="saveDraftCircularBtn">Save Draft</button>
                <button type="button" class="btn btn-primary" id="publishCircularBtn">Publish</button>
            </div>
        </div>
    </div>
</div>

<script>
const CircularsController = {
    data: [],
    filtered: [],
    page: 1,
    perPage: 15,
    detailModal: null,
    composeModal: null,

    init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
            window.location.href = '/';
            return;
        }
        this.detailModal = new bootstrap.Modal(document.getElementById('circularDetailModal'));
        this.composeModal = new bootstrap.Modal(document.getElementById('circularModal'));
        this.bindEvents();
        this.loadData();
    },

    bindEvents() {
        ['circularSearch', 'categoryFilter', 'targetFilter', 'statusFilter', 'dateFilter'].forEach(id => {
            document.getElementById(id).addEventListener('input', () => { this.page = 1; this.applyFilters(); this.render(); });
        });
        document.getElementById('exportCircularsBtn').addEventListener('click', () => this.exportCSV());
        document.getElementById('publishCircularBtn').addEventListener('click', () => this.saveCircular('published'));
        document.getElementById('saveDraftCircularBtn').addEventListener('click', () => this.saveCircular('draft'));
    },

    async loadData() {
        try {
            const res = await window.API.apiCall('/communications/circulars', 'GET');
            this.data = Array.isArray(res) ? res : (res.data || []);
        } catch (e) {
            this.data = [];
        }
        this.updateStats();
        this.applyFilters();
        this.render();
    },

    updateStats() {
        const now = new Date();
        const thisMonth = this.data.filter(c => {
            const d = new Date(c.created_at || c.date || '');
            return d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear();
        });
        document.getElementById('statTotal').textContent = this.data.length;
        document.getElementById('statThisMonth').textContent = thisMonth.length;
        document.getElementById('statPending').textContent = this.data.filter(c => c.status === 'draft' || c.status === 'pending').length;
        document.getElementById('statArchived').textContent = this.data.filter(c => c.status === 'archived').length;
    },

    applyFilters() {
        const q = (document.getElementById('circularSearch').value || '').toLowerCase();
        const cat = document.getElementById('categoryFilter').value;
        const target = document.getElementById('targetFilter').value;
        const status = document.getElementById('statusFilter').value;
        const date = document.getElementById('dateFilter').value;
        this.filtered = this.data.filter(c => {
            if (q && !(c.subject || c.title || '').toLowerCase().includes(q)) return false;
            if (cat && c.category !== cat) return false;
            if (target && c.target_audience !== target && c.target_audience !== 'all') return false;
            if (status && c.status !== status) return false;
            if (date && !((c.created_at || c.date || '').startsWith(date))) return false;
            return true;
        });
    },

    render() {
        const start = (this.page - 1) * this.perPage;
        const paged = this.filtered.slice(start, start + this.perPage);
        const tbody = document.getElementById('circularsBody');
        if (paged.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>No circulars found.</td></tr>';
        } else {
            const statusBadge = { published: 'success', draft: 'secondary', archived: 'dark', pending: 'warning' };
            tbody.innerHTML = paged.map(c => {
                const date = new Date(c.created_at || c.date || '').toLocaleDateString();
                const badge = statusBadge[c.status] || 'secondary';
                return `<tr>
                    <td>${date}</td>
                    <td>${c.subject || c.title || ''}</td>
                    <td><span class="badge bg-light text-dark">${c.category || 'general'}</span></td>
                    <td>${c.target_audience || 'All'}</td>
                    <td>${c.sent_by || c.author || '—'}</td>
                    <td><span class="badge bg-${badge}">${c.status || 'draft'}</span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="CircularsController.showDetail(${c.id})">
                            <i class="fas fa-eye"></i> View
                        </button>
                        ${c.attachment ? `<button class="btn btn-sm btn-outline-secondary" onclick="CircularsController.download(${c.id})"><i class="fas fa-download"></i></button>` : ''}
                    </td>
                </tr>`;
            }).join('');
        }
        this.renderPagination();
    },

    renderPagination() {
        const pages = Math.ceil(this.filtered.length / this.perPage);
        const el = document.getElementById('circularsPagination');
        if (pages <= 1) { el.innerHTML = ''; return; }
        el.innerHTML = Array.from({ length: pages }, (_, i) =>
            `<li class="page-item${this.page === i + 1 ? ' active' : ''}">
                <a class="page-link" href="#" onclick="CircularsController.page=${i+1};CircularsController.render();return false;">${i+1}</a>
            </li>`).join('');
    },

    showDetail(id) {
        const c = this.data.find(x => x.id === id);
        if (!c) return;
        document.getElementById('detailTitle').textContent = c.subject || c.title || 'Circular';
        document.getElementById('detailBody').innerHTML = `
            <dl class="row mb-3">
                <dt class="col-sm-3">Category</dt><dd class="col-sm-9">${c.category || '—'}</dd>
                <dt class="col-sm-3">Target</dt><dd class="col-sm-9">${c.target_audience || '—'}</dd>
                <dt class="col-sm-3">Status</dt><dd class="col-sm-9">${c.status || '—'}</dd>
                <dt class="col-sm-3">Date</dt><dd class="col-sm-9">${new Date(c.created_at || c.date || '').toLocaleDateString()}</dd>
            </dl>
            <hr>
            <div>${c.content || '<em>No content available.</em>'}</div>`;
        this.detailModal.show();
    },

    download(id) {
        const c = this.data.find(x => x.id === id);
        if (c && c.attachment) window.open(c.attachment, '_blank');
    },

    exportCSV() {
        const headers = ['Date', 'Subject', 'Category', 'Target', 'Status'];
        const rows = this.filtered.map(c => [
            new Date(c.created_at || c.date || '').toLocaleDateString(),
            c.subject || c.title || '',
            c.category || '',
            c.target_audience || '',
            c.status || ''
        ]);
        const csv = [headers, ...rows].map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
        const a = document.createElement('a');
        a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
        a.download = 'circulars.csv';
        a.click();
    },

    async saveCircular(status) {
        const subject = document.getElementById('circular_subject').value.trim();
        const content = document.getElementById('circular_content').value.trim();
        if (!subject || !content) { alert('Subject and content are required.'); return; }
        const payload = {
            subject,
            content,
            category: document.getElementById('circular_category').value,
            target_audience: document.getElementById('circular_target').value,
            date: document.getElementById('circular_date').value,
            status
        };
        try {
            await window.API.apiCall('/communications/circulars', 'POST', payload);
            this.composeModal.hide();
            document.getElementById('circularForm').reset();
            await this.loadData();
        } catch (e) {
            alert('Failed to save circular. Please try again.');
        }
    }
};

document.addEventListener('DOMContentLoaded', () => CircularsController.init());
</script>
