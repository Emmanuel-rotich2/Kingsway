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
  <h2 class="mb-4">Student Management</h2>
  <?php renderTable("Student List", $studentHeaders, $studentRows, true, $actionOptions); ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Action handler for admin actions
  document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.action-option').forEach(item => {
      item.addEventListener('click', function(e) {
        e.preventDefault();
        const action = this.getAttribute('data-action');
        const rowData = JSON.parse(this.getAttribute('data-row'));
        if (action === 'Approve') {
          alert('Approve student: ' + rowData[1]);
          // Implement approve logic here (e.g., AJAX call)
        } else if (action === 'Activate') {
          alert('Activate student: ' + rowData[1]);
          // Implement activate logic here
        } else if (action === 'Deactivate') {
          alert('Deactivate student: ' + rowData[1]);
          // Implement deactivate logic here
        } else if (action === 'Edit') {
          alert('Edit student: ' + rowData[1]);
          // Open edit modal or form here
        } else if (action === 'View Profile') {
          alert('View profile for: ' + rowData[1]);
          // Redirect or show modal
        }
      });
    });
  });
</script>