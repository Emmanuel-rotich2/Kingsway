<?php /** Announcements Board - Read-only public announcement viewer */ ?>

<div>
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-bullhorn me-2"></i>Announcements</h4>
                    <p class="text-muted mb-0">School announcements and notices</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-md-3">
            <select class="form-select" id="categoryFilter">
                <option value="">All Categories</option>
                <option value="general">General</option>
                <option value="academic">Academic</option>
                <option value="administrative">Administrative</option>
                <option value="event">Events</option>
                <option value="emergency">Emergency</option>
            </select>
        </div>
        <div class="col-md-3">
            <input type="date" class="form-control" id="dateFilter" placeholder="Filter by date">
        </div>
        <div class="col-md-3">
            <select class="form-select" id="audienceFilter">
                <option value="">All Audiences</option>
                <option value="all">Everyone</option>
                <option value="students">Students</option>
                <option value="parents">Parents</option>
                <option value="staff">Staff</option>
            </select>
        </div>
    </div>

    <!-- Pinned Announcements -->
    <div id="pinnedSection" style="display:none;">
        <h6 class="text-muted fw-semibold mb-3"><i class="fas fa-thumbtack me-1"></i>Pinned</h6>
        <div class="row mb-4" id="pinnedList"></div>
    </div>

    <!-- All Announcements -->
    <div class="row" id="announcementCards">
        <div class="col-12 text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="text-muted mt-2">Loading announcements...</p>
        </div>
    </div>

    <nav>
        <ul class="pagination justify-content-center" id="announcementsPagination"></ul>
    </nav>
</div>

<script>
const AnnouncementsController = {
    data: [],
    page: 1,
    perPage: 9,

    init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
            window.location.href = '/';
            return;
        }
        document.getElementById('categoryFilter').addEventListener('change', () => { this.page = 1; this.render(); });
        document.getElementById('dateFilter').addEventListener('change', () => { this.page = 1; this.render(); });
        document.getElementById('audienceFilter').addEventListener('change', () => { this.page = 1; this.render(); });
        this.loadData();
    },

    async loadData() {
        try {
            const res = await window.API.apiCall('/communications/announcements', 'GET');
            this.data = Array.isArray(res) ? res : (res.data || []);
        } catch (e) {
            this.data = [];
        }
        this.render();
    },

    filtered() {
        const cat = document.getElementById('categoryFilter').value;
        const date = document.getElementById('dateFilter').value;
        const aud = document.getElementById('audienceFilter').value;
        return this.data.filter(a => {
            if (cat && a.announcement_type !== cat) return false;
            if (date && !((a.published_at || a.created_at || '').startsWith(date))) return false;
            if (aud && a.target_audience !== aud && a.target_audience !== 'all') return false;
            return true;
        });
    },

    render() {
        const all = this.filtered();
        const pinned = all.filter(a => a.priority === 'critical' || a.priority === 'high');
        const regular = all.filter(a => a.priority !== 'critical' && a.priority !== 'high');

        const pinnedSection = document.getElementById('pinnedSection');
        if (pinned.length > 0) {
            pinnedSection.style.display = '';
            document.getElementById('pinnedList').innerHTML = pinned.map(a => this.cardHtml(a, true)).join('');
        } else {
            pinnedSection.style.display = 'none';
        }

        const start = (this.page - 1) * this.perPage;
        const paged = regular.slice(start, start + this.perPage);
        const container = document.getElementById('announcementCards');
        container.innerHTML = paged.length
            ? paged.map(a => this.cardHtml(a, false)).join('')
            : '<div class="col-12 text-center text-muted py-5"><i class="fas fa-inbox fa-2x mb-3 d-block"></i>No announcements found.</div>';

        this.renderPagination(regular.length);
    },

    cardHtml(a, pin) {
        const typeColors = { general: 'secondary', academic: 'primary', administrative: 'info', event: 'success', emergency: 'danger', maintenance: 'warning' };
        const color = typeColors[a.announcement_type] || 'secondary';
        const date = a.published_at ? new Date(a.published_at).toLocaleDateString() : new Date(a.created_at).toLocaleDateString();
        const preview = (a.content || '').replace(/<[^>]*>/g, '').substring(0, 120);
        return `<div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0 h-100${pin ? ' border-start border-warning border-3' : ''}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge bg-${color}">${(a.announcement_type || 'general').replace('_', ' ')}</span>
                        <small class="text-muted">${date}</small>
                    </div>
                    <h6 class="card-title">${a.title || ''}</h6>
                    <p class="card-text text-muted small">${preview}${preview.length >= 120 ? '...' : ''}</p>
                </div>
                <div class="card-footer bg-transparent d-flex justify-content-between align-items-center">
                    <small class="text-muted"><i class="fas fa-users me-1"></i>${a.target_audience || 'All'}</small>
                    ${a.view_count != null ? `<small class="text-muted"><i class="fas fa-eye me-1"></i>${a.view_count}</small>` : ''}
                </div>
            </div>
        </div>`;
    },

    renderPagination(total) {
        const pages = Math.ceil(total / this.perPage);
        const el = document.getElementById('announcementsPagination');
        if (pages <= 1) { el.innerHTML = ''; return; }
        el.innerHTML = Array.from({ length: pages }, (_, i) =>
            `<li class="page-item${this.page === i + 1 ? ' active' : ''}">
                <a class="page-link" href="#" onclick="AnnouncementsController.page=${i+1};AnnouncementsController.render();return false;">${i+1}</a>
            </li>`).join('');
    }
};

document.addEventListener('DOMContentLoaded', () => AnnouncementsController.init());
</script>
