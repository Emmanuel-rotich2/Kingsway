const studentsManagementController = (() => {
    let currentPage = 1;
    let searchTerm = '';
    let genderChartInstance = null;

    /* ===============================
       LOAD STUDENTS (AJAX + PAGINATION)
    =============================== */
    function loadStudents(page = 1) {
        currentPage = page;
        fetch(`/api/students/list.php?page=${page}&search=${searchTerm}`)
            .then(r => r.json())
            .then(res => {
                renderTable(res.data);
                renderPagination(res.total, page);
            });
    }

    /* ===============================
       RENDER STUDENTS TABLE
    =============================== */
    function renderTable(students) {
        const tbody = document.getElementById('studentsTableBody');
        tbody.innerHTML = '';

        if (!students.length) {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4">No students found</td></tr>`;
            return;
        }

        students.forEach((s, i) => {
            tbody.innerHTML += `
            <tr>
                <td><img src="${s.photo_url || '/images/default-avatar.png'}" class="rounded-circle" width="40"></td>
                <td>${i + 1}</td>
                <td>${s.admission_no}</td>
                <td>${s.first_name} ${s.last_name}</td>
                <td>${s.gender}</td>
                <td>${s.date_of_birth}</td>
                <td>${s.upi_number || '-'}</td>
                <td><span class="badge bg-${s.status === 'active' ? 'success' : 'danger'}">${s.status}</span></td>
                <td>
                    <button class="btn btn-sm btn-info" onclick="studentsManagementController.viewStudent(${s.id})">View</button>
                    <button class="btn btn-sm btn-warning" onclick="studentsManagementController.editStudent(${s.id})">Edit</button>
                    <button class="btn btn-sm btn-danger" onclick="studentsManagementController.deleteStudent(${s.id})">Delete</button>
                </td>
            </tr>`;
        });
    }

    /* ===============================
       LOAD STATS & GENDER CHART
    =============================== */
    function loadStats() {
        fetch('/api/students/stats.php')
            .then(r => r.json())
            .then(s => {
                document.getElementById('totalStudentsCount').innerText = s.total;
                document.getElementById('activeStudentsCount').innerText = s.active;
                document.getElementById('inactiveStudentsCount').innerText = s.inactive;

                if (genderChartInstance) genderChartInstance.destroy();

                genderChartInstance = new Chart(
                    document.getElementById('genderChart'),
                    {
                        type: 'pie',
                        data: {
                            labels: s.gender.map(g => g.gender),
                            datasets: [{ data: s.gender.map(g => g.total), backgroundColor: ['#0d6efd', '#dc3545'] }]
                        }
                    }
                );
            });
    }

    /* ===============================
       SEARCH & FILTER
    =============================== */
    function search(value) {
        searchTerm = value;
        loadStudents(1);
    }

    function filterByGender(gender) {
        searchTerm = gender;
        loadStudents(1);
    }

    /* ===============================
       VIEW / EDIT / DELETE STUDENT
    =============================== */
    function viewStudent(id) {
        alert('View student ID: ' + id);
        // Can later open modal
    }

    function editStudent(id) {
        alert('Edit student ID: ' + id);
        // Fetch student data and populate modal for editing
    }

    function deleteStudent(id) {
        if (!confirm('Are you sure you want to delete this student?')) return;
        fetch(`/api/students/delete.php?id=${id}`, { method: 'DELETE' })
            .then(r => r.json())
            .then(res => {
                alert(res.message);
                loadStudents(currentPage);
                loadStats();
            });
    }

    /* ===============================
       SHOW STUDENT MODAL
    =============================== */
    function showStudentModal() {
        document.getElementById('studentForm').reset();
        document.getElementById('studentId').value = '';
        new bootstrap.Modal(document.getElementById('studentModal')).show();
    }

    /* ===============================
       SAVE STUDENT (ADD / UPDATE)
    =============================== */
    function saveStudent(event) {
        event.preventDefault();
        const form = document.getElementById('studentForm');
        const formData = new FormData(form);

        const id = document.getElementById('studentId').value;
        const url = id ? `/api/students/update.php?id=${id}` : '/api/students/create.php';

        fetch(url, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                alert(res.message);
                if (res.success) {
                    loadStudents(currentPage);
                    loadStats();
                    bootstrap.Modal.getInstance(document.getElementById('studentModal')).hide();
                }
            });
    }

    /* ===============================
       PAGINATION RENDER
    =============================== */
    function renderPagination(totalRecords, currentPage) {
        const perPage = 10;
        const totalPages = Math.ceil(totalRecords / perPage);
        const ul = document.getElementById('pagination');
        ul.innerHTML = '';
        for (let i = 1; i <= totalPages; i++) {
            ul.innerHTML += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                                <a class="page-link" href="javascript:void(0)" onclick="studentsManagementController.loadStudents(${i})">${i}</a>
                             </li>`;
        }
    }

    return {
        init() { loadStudents(); loadStats(); },
        loadStudents,
        search,
        filterByGender,
        viewStudent,
        editStudent,
        deleteStudent,
        showStudentModal,
        saveStudent
    };
})();
