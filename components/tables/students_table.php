<?php
require_once __DIR__ . '/table.php';

if (!function_exists('renderStudentsTable')) {
    function renderStudentsTable() {
        $headers = ['Adm No', 'Photo', 'Name', 'Class', 'Parent Contact', 'Status'];
        $rows = [];
        // Render empty table; JS will populate
        $actionOptions = ['View Profile', 'Generate QR', 'Edit', 'Delete'];
    ?>
        <div class="mb-3">
            <button class="btn btn-primary" onclick="window.location.href='?route=new_admission'">
                <i class="fas fa-plus"></i> New Admission
            </button>
        </div>
        <?php
        renderTable('Students List', $headers, $rows, true, $actionOptions);
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            function reloadStudentsTable() {
                StudentsAPI.list().then(data => {
                    const tbody = document.querySelector('#dataTable tbody');
                    tbody.innerHTML = '';
                    if (!data || !data.students) return;
                    data.students.forEach(student => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${student.admission_no}</td>
                            <td><img src='images/students/${student.photo}' class='rounded-circle' width='40' alt='Student Photo'></td>
                            <td>${student.name}</td>
                            <td>${student.class}</td>
                            <td>${student.parent_contact}</td>
                            <td><span class='badge ${student.status === 'active' ? 'bg-success' : 'bg-warning'}'>${student.status}</span></td>
                            <td></td>
                        `;
                        tbody.appendChild(tr);
                    });
                    attachStudentActionHandlers();
                });
            }

            function attachStudentActionHandlers() {
            document.querySelectorAll('.action-option').forEach(item => {
                    item.onclick = function(e) {
                    e.preventDefault();
                    const action = this.getAttribute('data-action');
                    const rowData = JSON.parse(this.getAttribute('data-row'));
                        const studentId = rowData[0];
                        if (action === 'View Profile') {
                            parseApiResponse(StudentsAPI.get(studentId), {
                                onSuccess: data => showNotification('Student: ' + (data?.name || 'Profile loaded'), 'info'),
                                onError: err => showNotification('Error loading profile: ' + err.message, 'error')
                            });
                        } else if (action === 'Generate QR') {
                            parseApiResponse(StudentsAPI.getQRCode(studentId), {
                                onSuccess: data => showNotification('QR code generated', 'success'),
                                onError: err => showNotification('Error generating QR: ' + err.message, 'error')
                            });
                        } else if (action === 'Edit') {
                            window.location.href = `?route=edit_student&id=${studentId}`;
                        } else if (action === 'Delete') {
                            if (confirm('Are you sure you want to delete this student?')) {
                                parseApiResponse(StudentsAPI.delete(studentId), {
                                    onSuccess: () => { showNotification('Student deleted', 'success'); reloadStudentsTable(); },
                                    onError: err => showNotification('Error deleting student: ' + err.message, 'error')
                                });
                    }
                        }
                    };
            });
        }

            // Initial load
            reloadStudentsTable();
            // Auto-reload every 30 seconds
            setInterval(reloadStudentsTable, 30000);
        });
        </script>
    <?php
    }
}
?> 