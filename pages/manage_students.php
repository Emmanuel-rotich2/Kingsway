<?php
// filepath: /home/opt/lampp/htdocs/Kingsway/pages/manage_students.php
include __DIR__ . '/../components/tables/table.php';

// Example: Fetch students from DB (replace with real DB logic)
$studentHeaders = ['No', 'Name', 'Admission Number', 'Class', 'Status'];
$studentRows = [
  [1, 'Faith Wanjiku', 'ADM001', 'Grade 4', 'Pending'],
  [3, 'Mercy Mwikali', 'ADM003', 'Grade 8', 'Inactive'],
  [5, 'Janet Njeri', 'ADM005', 'Grade 6', 'Pending'],
];
// Actions for admin: Approve, Activate, Deactivate, Edit, View Profile
$actionOptions = ['Approve', 'Activate', 'Deactivate', 'Edit', 'View Profile'];
?>

<div class="container mt-1">
  <h2 class="mb-4 d-flex justify-content-between align-items-center">
    Student Management
    <div class="btn-group">
      <button class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-list-check"></i> Bulk Actions
      </button>
      <ul class="dropdown-menu">
        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#bulkUploadModal">Bulk Upload (CSV/XLSX)</a></li>
        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#bulkUpdateModal">Bulk Update (CSV/XLSX)</a></li>
        <li><a class="dropdown-item" href="#" id="bulkDeleteBtn">Bulk Delete (Selected)</a></li>
        <li><hr class="dropdown-divider"></li>
        <li class="dropdown-submenu dropend">
          <a class="dropdown-item dropdown-toggle" href="#">Export</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item export-btn" href="#" data-format="csv">Export as CSV</a></li>
            <li><a class="dropdown-item export-btn" href="#" data-format="xlsx">Export as Excel</a></li>
            <li><a class="dropdown-item export-btn" href="#" data-format="pdf">Export as PDF</a></li>
            <li><a class="dropdown-item export-btn" href="#" data-format="docx">Export as Word</a></li>
            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#customExportModal">Custom Export...</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </h2>
  <div id="bulk-actions-feedback"></div>
  <?php renderTable("Student List", $studentHeaders, $studentRows, true, $actionOptions, true); ?>
</div>

<!-- Bulk Upload Modal -->
<div class="modal fade" id="bulkUploadModal" tabindex="-1" aria-labelledby="bulkUploadModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="bulkUploadForm">
        <div class="modal-header">
          <h5 class="modal-title" id="bulkUploadModalLabel">Bulk Upload Students</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="file" class="form-control" name="file" accept=".csv,.xlsx" required>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Upload</button>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- Bulk Update Modal -->
<div class="modal fade" id="bulkUpdateModal" tabindex="-1" aria-labelledby="bulkUpdateModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="bulkUpdateForm">
        <div class="modal-header">
          <h5 class="modal-title" id="bulkUpdateModalLabel">Bulk Update Students</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="file" class="form-control" name="file" accept=".csv,.xlsx" required>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Update</button>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- Custom Export Modal (for user-tweaked export) -->
<div class="modal fade" id="customExportModal" tabindex="-1" aria-labelledby="customExportModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="customExportForm">
        <div class="modal-header">
          <h5 class="modal-title" id="customExportModalLabel">Custom Export</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">Select columns to export:</div>
          <div id="exportColumnsCheckboxes"></div>
          <div class="mb-2 mt-3">Add filters (optional):</div>
          <input type="text" class="form-control" name="filters" placeholder="e.g. status=Active">
          <div class="mb-2 mt-3">Format:</div>
          <select class="form-select" name="format">
            <option value="csv">CSV</option>
            <option value="xlsx">Excel</option>
            <option value="pdf">PDF</option>
            <option value="docx">Word</option>
          </select>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Export</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.action-option').forEach(item => {
      item.addEventListener('click', function(e) {
        e.preventDefault();
        const action = this.getAttribute('data-action');
        const rowData = JSON.parse(this.getAttribute('data-row'));
      const studentId = rowData[2]; // Assuming Admission Number is unique
        if (action === 'Approve') {
        parseApiResponse(StudentsAPI.update(studentId, { status: 'approved' }), {
          onSuccess: () => showNotification('Student approved', 'success'),
          onError: err => showNotification('Error approving student: ' + err.message, 'error')
        });
        } else if (action === 'Activate') {
        parseApiResponse(StudentsAPI.update(studentId, { status: 'active' }), {
          onSuccess: () => showNotification('Student activated', 'success'),
          onError: err => showNotification('Error activating student: ' + err.message, 'error')
        });
        } else if (action === 'Deactivate') {
        parseApiResponse(StudentsAPI.update(studentId, { status: 'inactive' }), {
          onSuccess: () => showNotification('Student deactivated', 'success'),
          onError: err => showNotification('Error deactivating student: ' + err.message, 'error')
        });
        } else if (action === 'Edit') {
        window.location.href = `?route=edit_student&id=${studentId}`;
        } else if (action === 'View Profile') {
        parseApiResponse(StudentsAPI.get(studentId), {
          onSuccess: data => showNotification('Student: ' + (data?.name || 'Profile loaded'), 'info'),
          onError: err => showNotification('Error loading profile: ' + err.message, 'error')
        });
        }
      });
    });
  });
// Bulk Upload
const bulkUploadForm = document.getElementById('bulkUploadForm');
bulkUploadForm && bulkUploadForm.addEventListener('submit', function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  StudentsAPI.bulkInsertFile(formData).then(res => {
    showNotification('Bulk upload complete', 'success');
    location.reload();
  }).catch(err => showNotification('Bulk upload failed: ' + err.message, 'error'));
});
// Bulk Update
const bulkUpdateForm = document.getElementById('bulkUpdateForm');
bulkUpdateForm && bulkUpdateForm.addEventListener('submit', function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  StudentsAPI.bulkUpdateFile(formData).then(res => {
    showNotification('Bulk update complete', 'success');
    location.reload();
  }).catch(err => showNotification('Bulk update failed: ' + err.message, 'error'));
});
// Bulk Delete
const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
bulkDeleteBtn && bulkDeleteBtn.addEventListener('click', function(e) {
  e.preventDefault();
  const ids = Array.from(document.querySelectorAll('.row-select:checked')).map(cb => cb.value);
  if (!ids.length) return showNotification('Select at least one student to delete', 'warning');
  if (!confirm('Are you sure you want to delete selected students?')) return;
  StudentsAPI.bulkDelete({ ids }).then(res => {
    showNotification('Bulk delete complete', 'success');
    location.reload();
  }).catch(err => showNotification('Bulk delete failed: ' + err.message, 'error'));
});
// Export
Array.from(document.querySelectorAll('.export-btn')).forEach(btn => {
  btn.addEventListener('click', function(e) {
    e.preventDefault();
    const format = this.getAttribute('data-format');
    StudentsAPI.export({ format }).then(blob => {
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `students_export.${format}`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      window.URL.revokeObjectURL(url);
    }).catch(err => showNotification('Export failed: ' + err.message, 'error'));
  });
});
// Custom Export Modal
const customExportModal = document.getElementById('customExportModal');
customExportModal && customExportModal.addEventListener('show.bs.modal', function() {
  const columns = <?php echo json_encode($studentHeaders); ?>.slice(1); // skip No
  const container = document.getElementById('exportColumnsCheckboxes');
  container.innerHTML = columns.map(col => `<div class='form-check'><input class='form-check-input' type='checkbox' name='columns' value='${col}' checked><label class='form-check-label'>${col}</label></div>`).join('');
});
document.getElementById('customExportForm')?.addEventListener('submit', function(e) {
  e.preventDefault();
  const form = this;
  const columns = Array.from(form.querySelectorAll('input[name="columns"]:checked')).map(cb => cb.value);
  const filters = form.filters.value;
  const format = form.format.value;
  StudentsAPI.export({ format, columns, filters }).then(blob => {
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `students_export.${format}`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.URL.revokeObjectURL(url);
    bootstrap.Modal.getOrCreateInstance(customExportModal).hide();
  }).catch(err => showNotification('Custom export failed: ' + err.message, 'error'));
});
</script>