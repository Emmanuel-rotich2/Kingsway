<?php
/**
 * Discipline - Operator Layout
 * Minimal layout for Class Teachers, Subject Teachers
 *
 * Features:
 * - 2 stat cards
 * - No charts
 * - Simple table (4 columns)
 * - Can report cases, view own reports only
 */
/* PARTIAL — no DOCTYPE/html/head/body. Injected into app shell via fetch. */
?>

<!-- Stats - 2 columns -->
<div class="operator-stats">
    <div class="operator-stat-card">
        <div class="stat-icon">📋</div>
        <div class="stat-info">
            <div class="stat-value" id="myCases">0</div>
            <div class="stat-label">My Reports</div>
        </div>
    </div>
    <div class="operator-stat-card">
        <div class="stat-icon">🔴</div>
        <div class="stat-info">
            <div class="stat-value" id="pendingCases">0</div>
            <div class="stat-label">Pending</div>
        </div>
    </div>
</div>

<!-- Search -->
<div class="operator-filters">
    <input type="text" class="search-input form-control" id="searchCase" placeholder="Search cases...">
    <button class="btn btn-warning btn-sm" id="reportBtn">📋 Report Case</button>
</div>

<!-- Table - 4 essential columns -->
<div class="operator-table-card">
    <div class="operator-table-header">
        <span class="table-title">Cases I Reported</span>
    </div>

    <table class="operator-data-table" id="casesTable">
        <thead>
            <tr>
                <th>Date</th>
                <th>Student</th>
                <th>Category</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody id="casesTableBody">
            <!-- Data loaded dynamically -->
        </tbody>
    </table>
</div>

<!-- Simple Report Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">Report Discipline Case</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="reportForm">
                    <div class="mb-3">
                        <label class="form-label">Student</label>
                        <select class="form-select" id="studentId" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select class="form-select" id="category" required>
                            <option value="">Select</option>
                            <option value="misconduct">Misconduct</option>
                            <option value="truancy">Truancy</option>
                            <option value="fighting">Fighting</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">What happened?</label>
                        <textarea class="form-control" id="description" rows="4" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-warning" id="submitBtn">Submit Report</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        loadMyCases();
        loadMyClassStudents();

        document.getElementById('reportBtn').addEventListener('click', () => {
            new bootstrap.Modal(document.getElementById('reportModal')).show();
        });
        document.getElementById('searchCase').addEventListener('input', debounce(filterCases, 300));
        document.getElementById('submitBtn').addEventListener('click', submitReport);
    });

    async function loadMyCases() {
        try {
            // Load only cases reported by current user
            const response = await API.discipline.getMine();
            if (response.success) {
                renderCasesTable(response.data);
                document.getElementById('myCases').textContent = response.data.length;
                document.getElementById('pendingCases').textContent = response.data.filter(c => c.status === 'open').length;
            }
        } catch (error) {
            console.error('Error loading cases:', error);
        }
    }

    async function loadMyClassStudents() {
        try {
            const response = await API.students.getMyClass();
            if (response.success) {
                const select = document.getElementById('studentId');
                response.data.forEach(s => {
                    select.add(new Option(`${s.full_name} (${s.admission_no})`, s.id));
                });
            }
        } catch (error) {
            console.error('Error loading students:', error);
        }
    }

    function renderCasesTable(cases) {
        const tbody = document.getElementById('casesTableBody');
        tbody.innerHTML = '';

        if (cases.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted p-4">No cases reported yet</td></tr>';
            return;
        }

        cases.forEach(c => {
            const row = document.createElement('tr');
            row.innerHTML = `
            <td>${formatDate(c.incident_date)}</td>
            <td><strong>${escapeHtml(c.student_name)}</strong></td>
            <td><span class="badge">${c.category}</span></td>
            <td><span class="status-badge status-${c.status}">${c.status}</span></td>
        `;
            tbody.appendChild(row);
        });
    }

    function filterCases() {
        const search = document.getElementById('searchCase').value.toLowerCase();
        document.querySelectorAll('#casesTableBody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(search) ? '' : 'none';
        });
    }

    async function submitReport() {
        const data = {
            student_id: document.getElementById('studentId').value,
            category: document.getElementById('category').value,
            description: document.getElementById('description').value
        };

        try {
            const response = await API.discipline.create(data);
            if (response.success) {
                bootstrap.Modal.getInstance(document.getElementById('reportModal')).hide();
                loadMyCases();
            } else {
                alert(response.message || 'Error submitting report');
            }
        } catch (error) {
            console.error('Error submitting report:', error);
        }
    }

    function formatDate(d) { return d ? new Date(d).toLocaleDateString() : '-'; }
    function escapeHtml(s) { return s ? s.replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]) : ''; }
    function debounce(fn, d) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), d); }; }
</script>
